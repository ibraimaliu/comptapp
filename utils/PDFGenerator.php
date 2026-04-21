<?php
/**
 * Générateur de PDF pour factures avec QR-Code
 * Utilise mPDF pour générer des PDFs professionnels
 *
 * @author Gestion Comptable
 * @version 1.0
 * @since 2024
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Mpdf\Mpdf;

class PDFGenerator {
    private $mpdf;
    private $conn;

    public function __construct($db) {
        $this->conn = $db;

        // Configuration mPDF
        $this->mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font' => 'dejavusans'
        ]);
    }

    /**
     * Générer une facture PDF avec QR-Code
     *
     * @param int $invoice_id ID de la facture
     * @param int $company_id ID de la société
     * @param bool $with_qr Inclure le QR-Code
     * @return string|false Chemin du PDF généré ou false en cas d'erreur
     */
    public function generateInvoicePDF($invoice_id, $company_id, $with_qr = true) {
        try {
            // Récupérer les données
            $invoice_data = $this->getInvoiceData($invoice_id, $company_id);

            if (!$invoice_data) {
                throw new Exception("Facture introuvable");
            }

            // Générer le QR-Code si nécessaire
            if ($with_qr) {
                require_once __DIR__ . '/../models/QRInvoice.php';
                $qr_invoice = new QRInvoice($this->conn);

                // Générer la référence QRR si elle n'existe pas
                if (empty($invoice_data['qr_reference'])) {
                    $qr_reference = $qr_invoice->generateQRReference(
                        $invoice_data['number'],
                        $company_id
                    );

                    // Mettre à jour la facture
                    $query = "UPDATE invoices SET qr_reference = :qr_reference WHERE id = :id";
                    $stmt = $this->conn->prepare($query);
                    $stmt->bindParam(':qr_reference', $qr_reference);
                    $stmt->bindParam(':id', $invoice_id);
                    $stmt->execute();

                    $invoice_data['qr_reference'] = $qr_reference;
                }

                // Générer le QR-Code
                $qr_code_path = $qr_invoice->generateQRCode($invoice_id, $company_id);
                $invoice_data['qr_code_path'] = $qr_code_path;
            }

            // Générer le HTML
            $html = $this->generateInvoiceHTML($invoice_data, $with_qr);

            // Écrire dans le PDF
            $this->mpdf->WriteHTML($html);

            // Créer le dossier invoices s'il n'existe pas
            $pdf_dir = __DIR__ . '/../uploads/invoices';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }

            // Nom du fichier
            $filename = 'invoice_' . $invoice_data['number'] . '_' . time() . '.pdf';
            $filepath = $pdf_dir . '/' . $filename;

            // Sauvegarder le PDF
            $this->mpdf->Output($filepath, 'F');

            return 'uploads/invoices/' . $filename;

        } catch (Exception $e) {
            error_log("PDFGenerator::generateInvoicePDF Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer toutes les données nécessaires pour la facture
     *
     * @param int $invoice_id ID de la facture
     * @param int $company_id ID de la société
     * @return array|false Données de la facture ou false
     */
    private function getInvoiceData($invoice_id, $company_id) {
        require_once __DIR__ . '/../models/Invoice.php';
        require_once __DIR__ . '/../models/Company.php';
        require_once __DIR__ . '/../models/Contact.php';

        // Facture
        $invoice_model = new Invoice($this->conn);
        $invoice_model->id = $invoice_id;
        if (!$invoice_model->read()) {
            return false;
        }

        // Société
        $company_model = new Company($this->conn);
        $company_model->id = $company_id;
        $company_model->read();

        // Client
        $contact_model = new Contact($this->conn);
        $client_data = $contact_model->getById($invoice_model->client_id, $company_id);

        // Items de la facture
        $query = "SELECT * FROM invoice_items WHERE invoice_id = :invoice_id ORDER BY id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':invoice_id', $invoice_id);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'id' => $invoice_model->id,
            'number' => $invoice_model->number,
            'date' => $invoice_model->date,
            'subtotal' => $invoice_model->subtotal,
            'tva_amount' => $invoice_model->tva_amount,
            'total' => $invoice_model->total,
            'status' => $invoice_model->status,
            'qr_reference' => $invoice_model->qr_reference ?? '',
            'payment_due_date' => $invoice_model->payment_due_date ?? date('Y-m-d', strtotime('+30 days')),
            'payment_terms' => $invoice_model->payment_terms ?? 'Payable dans les 30 jours',
            'qr_code_path' => $invoice_model->qr_code_path ?? '',
            'items' => $items,
            'company' => [
                'name' => $company_model->name,
                'owner_name' => $company_model->owner_name,
                'owner_surname' => $company_model->owner_surname,
                'address' => $company_model->address ?? '',
                'postal_code' => $company_model->postal_code ?? '',
                'city' => $company_model->city ?? '',
                'country' => $company_model->country ?? 'CH',
                'qr_iban' => $company_model->qr_iban ?? '',
                'bank_iban' => $company_model->bank_iban ?? ''
            ],
            'client' => $client_data
        ];
    }

    /**
     * Générer le HTML de la facture
     *
     * @param array $data Données de la facture
     * @param bool $with_qr Inclure la section QR
     * @return string HTML de la facture
     */
    private function generateInvoiceHTML($data, $with_qr = true) {
        require_once __DIR__ . '/../models/QRInvoice.php';
        $qr_invoice = new QRInvoice($this->conn);

        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 11pt;
                    color: #333;
                }
                .header {
                    margin-bottom: 30px;
                }
                .company-info {
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .invoice-title {
                    font-size: 20pt;
                    font-weight: bold;
                    margin: 30px 0;
                }
                .invoice-meta {
                    margin-bottom: 30px;
                }
                .invoice-meta table {
                    border-collapse: collapse;
                }
                .invoice-meta td {
                    padding: 5px 10px;
                }
                .client-address {
                    border: 1px solid #ccc;
                    padding: 15px;
                    margin: 20px 0;
                    max-width: 300px;
                }
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                .items-table th {
                    background-color: #f0f0f0;
                    padding: 10px;
                    text-align: left;
                    border-bottom: 2px solid #333;
                }
                .items-table td {
                    padding: 8px 10px;
                    border-bottom: 1px solid #ddd;
                }
                .items-table .amount {
                    text-align: right;
                }
                .totals {
                    float: right;
                    width: 300px;
                    margin-top: 20px;
                }
                .totals table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .totals td {
                    padding: 5px 10px;
                }
                .totals .total-row {
                    font-weight: bold;
                    font-size: 12pt;
                    border-top: 2px solid #333;
                }
                .payment-info {
                    clear: both;
                    margin-top: 40px;
                    padding: 15px;
                    background-color: #f9f9f9;
                    border: 1px solid #ddd;
                }
                .qr-section {
                    page-break-before: always;
                    border-top: 1px dashed #999;
                    padding-top: 20px;
                    margin-top: 20px;
                }
                .qr-title {
                    font-size: 14pt;
                    font-weight: bold;
                    margin-bottom: 20px;
                }
                .qr-container {
                    display: table;
                    width: 100%;
                }
                .qr-left {
                    display: table-cell;
                    width: 50%;
                    vertical-align: top;
                }
                .qr-right {
                    display: table-cell;
                    width: 50%;
                    vertical-align: top;
                }
                .qr-label {
                    font-size: 9pt;
                    font-weight: bold;
                    margin-top: 10px;
                    margin-bottom: 3px;
                }
                .qr-value {
                    font-size: 10pt;
                    margin-bottom: 8px;
                }
            </style>
        </head>
        <body>';

        // En-tête société
        $html .= '
        <div class="header">
            <div class="company-info">' . htmlspecialchars($data['company']['name']) . '</div>
            <div>' . htmlspecialchars($data['company']['address']) . '</div>
            <div>' . htmlspecialchars($data['company']['postal_code']) . ' ' . htmlspecialchars($data['company']['city']) . '</div>
        </div>';

        // Titre facture
        $html .= '
        <div class="invoice-title">FACTURE</div>';

        // Méta-données facture
        $html .= '
        <div class="invoice-meta">
            <table>
                <tr>
                    <td><strong>Numéro:</strong></td>
                    <td>' . htmlspecialchars($data['number']) . '</td>
                </tr>
                <tr>
                    <td><strong>Date:</strong></td>
                    <td>' . date('d.m.Y', strtotime($data['date'])) . '</td>
                </tr>
                <tr>
                    <td><strong>Échéance:</strong></td>
                    <td>' . date('d.m.Y', strtotime($data['payment_due_date'])) . '</td>
                </tr>
            </table>
        </div>';

        // Adresse client
        $html .= '
        <div class="client-address">
            <strong>' . htmlspecialchars($data['client']['name'] ?? 'Client') . '</strong><br>';

        if (!empty($data['client']['address'])) {
            $html .= htmlspecialchars($data['client']['address']) . '<br>';
        }

        if (!empty($data['client']['postal_code']) || !empty($data['client']['city'])) {
            $html .= htmlspecialchars($data['client']['postal_code'] ?? '') . ' ' .
                     htmlspecialchars($data['client']['city'] ?? '') . '<br>';
        }

        $html .= '
        </div>';

        // Tableau des items
        $html .= '
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Quantité</th>
                    <th class="amount">Prix unitaire</th>
                    <th class="amount">TVA</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($data['items'] as $item) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td class="amount">' . number_format($item['quantity'], 2, '.', ' ') . '</td>
                    <td class="amount">' . number_format($item['unit_price'], 2, '.', ' ') . ' CHF</td>
                    <td class="amount">' . number_format($item['tva_rate'], 2, '.', '') . '%</td>
                    <td class="amount">' . number_format($item['total'], 2, '.', ' ') . ' CHF</td>
                </tr>';
        }

        $html .= '
            </tbody>
        </table>';

        // Totaux
        $html .= '
        <div class="totals">
            <table>
                <tr>
                    <td>Sous-total:</td>
                    <td class="amount">' . number_format($data['subtotal'], 2, '.', ' ') . ' CHF</td>
                </tr>
                <tr>
                    <td>TVA:</td>
                    <td class="amount">' . number_format($data['tva_amount'], 2, '.', ' ') . ' CHF</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL:</td>
                    <td class="amount">' . number_format($data['total'], 2, '.', ' ') . ' CHF</td>
                </tr>
            </table>
        </div>';

        // Informations de paiement
        $html .= '
        <div class="payment-info">
            <strong>Conditions de paiement:</strong> ' . htmlspecialchars($data['payment_terms']) . '<br>
            <strong>Échéance:</strong> ' . date('d.m.Y', strtotime($data['payment_due_date'])) . '
        </div>';

        // Section QR-facture (si activée)
        if ($with_qr && !empty($data['qr_code_path'])) {
            $qr_image_path = __DIR__ . '/../' . $data['qr_code_path'];

            if (file_exists($qr_image_path)) {
                $html .= '
                <div class="qr-section">
                    <div class="qr-title">Section de paiement avec QR-Code</div>

                    <div class="qr-container">
                        <div class="qr-left">
                            <img src="' . $qr_image_path . '" width="200" height="200" />
                        </div>

                        <div class="qr-right">
                            <div class="qr-label">Compte / Payable à</div>
                            <div class="qr-value">' . $qr_invoice->formatIBAN($data['company']['qr_iban'] ?? $data['company']['bank_iban']) . '</div>
                            <div class="qr-value">
                                ' . htmlspecialchars($data['company']['name']) . '<br>
                                ' . htmlspecialchars($data['company']['address']) . '<br>
                                ' . htmlspecialchars($data['company']['postal_code']) . ' ' . htmlspecialchars($data['company']['city']) . '
                            </div>

                            <div class="qr-label">Référence</div>
                            <div class="qr-value">' . $qr_invoice->formatQRReference($data['qr_reference']) . '</div>

                            <div class="qr-label">Montant</div>
                            <div class="qr-value">' . number_format($data['total'], 2, '.', ' ') . ' CHF</div>

                            <div class="qr-label">Payable par</div>
                            <div class="qr-value">
                                ' . htmlspecialchars($data['client']['name'] ?? '') . '<br>';

                if (!empty($data['client']['address'])) {
                    $html .= htmlspecialchars($data['client']['address']) . '<br>';
                }
                if (!empty($data['client']['postal_code']) || !empty($data['client']['city'])) {
                    $html .= htmlspecialchars($data['client']['postal_code'] ?? '') . ' ' .
                             htmlspecialchars($data['client']['city'] ?? '');
                }

                $html .= '
                            </div>
                        </div>
                    </div>
                </div>';
            }
        }

        $html .= '
        </body>
        </html>';

        return $html;
    }

    /**
     * Générer un devis PDF
     *
     * @param int $quote_id ID du devis
     * @param int $company_id ID de la société
     * @return string|false Chemin du PDF généré ou false en cas d'erreur
     */
    public function generateQuotePDF($quote_id, $company_id) {
        try {
            // Récupérer les données du devis
            $quote_data = $this->getQuoteData($quote_id, $company_id);

            if (!$quote_data) {
                throw new Exception("Devis introuvable");
            }

            // Générer le HTML
            $html = $this->generateQuoteHTML($quote_data);

            // Écrire dans le PDF
            $this->mpdf->WriteHTML($html);

            // Créer le dossier quotes s'il n'existe pas
            $pdf_dir = __DIR__ . '/../uploads/quotes';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }

            // Nom du fichier
            $filename = 'quote_' . $quote_data['number'] . '_' . time() . '.pdf';
            $filepath = $pdf_dir . '/' . $filename;

            // Sauvegarder le PDF
            $this->mpdf->Output($filepath, 'F');

            return 'uploads/quotes/' . $filename;

        } catch (Exception $e) {
            error_log("PDFGenerator::generateQuotePDF Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer toutes les données nécessaires pour le devis
     *
     * @param int $quote_id ID du devis
     * @param int $company_id ID de la société
     * @return array|false Données du devis ou false
     */
    private function getQuoteData($quote_id, $company_id) {
        require_once __DIR__ . '/../models/Quote.php';
        require_once __DIR__ . '/../models/Company.php';
        require_once __DIR__ . '/../models/Contact.php';

        // Devis
        $quote_model = new Quote($this->conn);
        $query = "SELECT * FROM quotes WHERE id = :id AND company_id = :company_id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $quote_id);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();
        $quote = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quote) {
            return false;
        }

        // Société
        $company_model = new Company($this->conn);
        $company_model->id = $company_id;
        $company_model->read();

        // Client
        $contact_model = new Contact($this->conn);
        $client_data = $contact_model->getById($quote['client_id'], $company_id);

        // Items du devis
        $query = "SELECT * FROM quote_items WHERE quote_id = :quote_id ORDER BY id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':quote_id', $quote_id);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'id' => $quote['id'],
            'number' => $quote['number'],
            'date' => $quote['date'],
            'valid_until' => $quote['valid_until'],
            'subtotal' => $quote['subtotal'],
            'tva_amount' => $quote['tva_amount'],
            'total' => $quote['total'],
            'status' => $quote['status'],
            'notes' => $quote['notes'] ?? '',
            'items' => $items,
            'company' => [
                'name' => $company_model->name,
                'owner_name' => $company_model->owner_name,
                'owner_surname' => $company_model->owner_surname,
                'address' => $company_model->address ?? '',
                'postal_code' => $company_model->postal_code ?? '',
                'city' => $company_model->city ?? '',
                'country' => $company_model->country ?? 'CH',
                'phone' => $company_model->phone ?? '',
                'email' => $company_model->email ?? ''
            ],
            'client' => $client_data
        ];
    }

    /**
     * Générer le HTML du devis
     *
     * @param array $data Données du devis
     * @return string HTML du devis
     */
    private function generateQuoteHTML($data) {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: Arial, Helvetica, sans-serif;
                    font-size: 11pt;
                    color: #333;
                }
                .header {
                    margin-bottom: 30px;
                }
                .company-info {
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .quote-title {
                    font-size: 20pt;
                    font-weight: bold;
                    margin: 30px 0;
                    color: #667eea;
                }
                .quote-meta {
                    margin-bottom: 30px;
                }
                .quote-meta table {
                    border-collapse: collapse;
                }
                .quote-meta td {
                    padding: 5px 10px;
                }
                .client-address {
                    border: 1px solid #ccc;
                    padding: 15px;
                    margin: 20px 0;
                    max-width: 300px;
                }
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                }
                .items-table th {
                    background-color: #f0f0f0;
                    padding: 10px;
                    text-align: left;
                    border-bottom: 2px solid #333;
                }
                .items-table td {
                    padding: 8px 10px;
                    border-bottom: 1px solid #ddd;
                }
                .items-table .amount {
                    text-align: right;
                }
                .totals {
                    float: right;
                    width: 300px;
                    margin-top: 20px;
                }
                .totals table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .totals td {
                    padding: 5px 10px;
                }
                .totals .total-row {
                    font-weight: bold;
                    font-size: 12pt;
                    border-top: 2px solid #333;
                }
                .validity-info {
                    clear: both;
                    margin-top: 40px;
                    padding: 15px;
                    background-color: #f0f4ff;
                    border: 1px solid #667eea;
                    border-radius: 5px;
                }
                .notes {
                    margin-top: 20px;
                    padding: 15px;
                    background-color: #f9f9f9;
                    border-left: 3px solid #667eea;
                }
                .footer {
                    margin-top: 40px;
                    text-align: center;
                    font-size: 9pt;
                    color: #666;
                    border-top: 1px solid #ddd;
                    padding-top: 20px;
                }
            </style>
        </head>
        <body>';

        // En-tête société
        $html .= '
        <div class="header">
            <div class="company-info">' . htmlspecialchars($data['company']['name']) . '</div>
            <div>' . htmlspecialchars($data['company']['address']) . '</div>
            <div>' . htmlspecialchars($data['company']['postal_code']) . ' ' . htmlspecialchars($data['company']['city']) . '</div>';

        if (!empty($data['company']['phone'])) {
            $html .= '<div>Tél: ' . htmlspecialchars($data['company']['phone']) . '</div>';
        }
        if (!empty($data['company']['email'])) {
            $html .= '<div>Email: ' . htmlspecialchars($data['company']['email']) . '</div>';
        }

        $html .= '
        </div>';

        // Titre devis
        $html .= '
        <div class="quote-title">DEVIS / OFFRE</div>';

        // Méta-données devis
        $html .= '
        <div class="quote-meta">
            <table>
                <tr>
                    <td><strong>Numéro:</strong></td>
                    <td>' . htmlspecialchars($data['number']) . '</td>
                </tr>
                <tr>
                    <td><strong>Date:</strong></td>
                    <td>' . date('d.m.Y', strtotime($data['date'])) . '</td>
                </tr>
                <tr>
                    <td><strong>Valable jusqu\'au:</strong></td>
                    <td>' . date('d.m.Y', strtotime($data['valid_until'])) . '</td>
                </tr>
            </table>
        </div>';

        // Adresse client
        $html .= '
        <div class="client-address">
            <strong>' . htmlspecialchars($data['client']['name'] ?? 'Client') . '</strong><br>';

        if (!empty($data['client']['address'])) {
            $html .= htmlspecialchars($data['client']['address']) . '<br>';
        }

        if (!empty($data['client']['postal_code']) || !empty($data['client']['city'])) {
            $html .= htmlspecialchars($data['client']['postal_code'] ?? '') . ' ' .
                     htmlspecialchars($data['client']['city'] ?? '') . '<br>';
        }

        $html .= '
        </div>';

        // Tableau des items
        $html .= '
        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="amount">Quantité</th>
                    <th class="amount">Prix unitaire</th>
                    <th class="amount">TVA</th>
                    <th class="amount">Total</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($data['items'] as $item) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($item['description']) . '</td>
                    <td class="amount">' . number_format($item['quantity'], 2, '.', ' ') . '</td>
                    <td class="amount">' . number_format($item['unit_price'], 2, '.', ' ') . ' CHF</td>
                    <td class="amount">' . number_format($item['tva_rate'], 2, '.', '') . '%</td>
                    <td class="amount">' . number_format($item['total'], 2, '.', ' ') . ' CHF</td>
                </tr>';
        }

        $html .= '
            </tbody>
        </table>';

        // Totaux
        $html .= '
        <div class="totals">
            <table>
                <tr>
                    <td>Sous-total:</td>
                    <td class="amount">' . number_format($data['subtotal'], 2, '.', ' ') . ' CHF</td>
                </tr>
                <tr>
                    <td>TVA:</td>
                    <td class="amount">' . number_format($data['tva_amount'], 2, '.', ' ') . ' CHF</td>
                </tr>
                <tr class="total-row">
                    <td>TOTAL:</td>
                    <td class="amount">' . number_format($data['total'], 2, '.', ' ') . ' CHF</td>
                </tr>
            </table>
        </div>';

        // Informations de validité
        $html .= '
        <div class="validity-info">
            <strong>Validité de l\'offre:</strong> Ce devis est valable jusqu\'au ' .
            date('d.m.Y', strtotime($data['valid_until'])) . '<br>
            Les prix indiqués sont en francs suisses (CHF) et TVA incluse.
        </div>';

        // Notes si présentes
        if (!empty($data['notes'])) {
            $html .= '
            <div class="notes">
                <strong>Notes:</strong><br>
                ' . nl2br(htmlspecialchars($data['notes'])) . '
            </div>';
        }

        // Pied de page
        $html .= '
        <div class="footer">
            Nous vous remercions de votre confiance et restons à votre disposition pour toute question.
        </div>';

        $html .= '
        </body>
        </html>';

        return $html;
    }

    /**
     * Télécharger un PDF directement dans le navigateur
     *
     * @param string $filepath Chemin du fichier PDF
     * @param string $filename Nom du fichier pour le téléchargement
     */
    public function downloadPDF($filepath, $filename = 'invoice.pdf') {
        if (file_exists($filepath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }

    /**
     * Afficher un PDF dans le navigateur
     *
     * @param string $filepath Chemin du fichier PDF
     */
    public function displayPDF($filepath) {
        if (file_exists($filepath)) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }
    }
}
?>
