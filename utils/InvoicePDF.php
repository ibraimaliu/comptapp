<?php
/**
 * Classe: InvoicePDF
 * Description: Génération de factures PDF avec QR-facture
 * Version: 1.0
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Sprain\SwissQrBill\QrBill;
use Sprain\SwissQrBill\DataGroup\Element\CombinedAddress;
use Sprain\SwissQrBill\DataGroup\Element\CreditorInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentAmountInformation;
use Sprain\SwissQrBill\DataGroup\Element\PaymentReference;

class InvoicePDF extends FPDF {

    private $company;
    private $client;
    private $invoice;
    private $items;
    private $qr_bill;

    /**
     * Constructeur
     */
    public function __construct($company, $client, $invoice, $items) {
        parent::__construct();
        $this->company = $company;
        $this->client = $client;
        $this->invoice = $invoice;
        $this->items = $items;
    }

    /**
     * En-tête du document
     */
    public function Header() {
        // Logo (si disponible)
        if (!empty($this->company['logo_path']) && file_exists($this->company['logo_path'])) {
            $this->Image($this->company['logo_path'], 10, 6, 30);
        }

        // Informations société
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, utf8_decode($this->company['name']), 0, 1, 'R');

        $this->SetFont('Arial', '', 9);
        if (!empty($this->company['address'])) {
            $this->Cell(0, 4, utf8_decode($this->company['address']), 0, 1, 'R');
        }
        if (!empty($this->company['postal_code']) && !empty($this->company['city'])) {
            $this->Cell(0, 4, $this->company['postal_code'] . ' ' . utf8_decode($this->company['city']), 0, 1, 'R');
        }
        if (!empty($this->company['email'])) {
            $this->Cell(0, 4, $this->company['email'], 0, 1, 'R');
        }
        if (!empty($this->company['phone'])) {
            $this->Cell(0, 4, $this->company['phone'], 0, 1, 'R');
        }

        $this->Ln(10);
    }

    /**
     * Pied de page
     */
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    /**
     * Générer le PDF
     */
    public function generate() {
        $this->AliasNbPages();
        $this->AddPage();
        $this->SetAutoPageBreak(true, 15);

        // Adresse client
        $this->renderClientAddress();

        // Informations facture
        $this->renderInvoiceInfo();

        // Tableau des articles
        $this->renderItemsTable();

        // Totaux
        $this->renderTotals();

        // Conditions de paiement
        $this->renderPaymentTerms();

        // QR-facture (nouvelle page)
        if (!empty($this->invoice['qr_reference']) && !empty($this->company['iban'])) {
            $this->AddPage();
            $this->renderQRBill();
        }
    }

    /**
     * Adresse du client
     */
    private function renderClientAddress() {
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 5, utf8_decode($this->client['name']), 0, 1);

        if (!empty($this->client['address'])) {
            $this->Cell(0, 5, utf8_decode($this->client['address']), 0, 1);
        }

        if (!empty($this->client['postal_code']) && !empty($this->client['city'])) {
            $this->Cell(0, 5, $this->client['postal_code'] . ' ' . utf8_decode($this->client['city']), 0, 1);
        }

        $this->Ln(10);
    }

    /**
     * Informations de la facture
     */
    private function renderInvoiceInfo() {
        // Titre
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, 'FACTURE', 0, 1, 'L');
        $this->Ln(5);

        // Détails
        $this->SetFont('Arial', '', 10);

        $col1_width = 50;
        $col2_width = 140;

        $this->SetFont('Arial', 'B', 10);
        $this->Cell($col1_width, 6, utf8_decode('Numéro:'), 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell($col2_width, 6, $this->invoice['number'], 0, 1);

        $this->SetFont('Arial', 'B', 10);
        $this->Cell($col1_width, 6, 'Date:', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell($col2_width, 6, date('d.m.Y', strtotime($this->invoice['date'])), 0, 1);

        if (!empty($this->invoice['due_date'])) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($col1_width, 6, utf8_decode('Échéance:'), 0, 0);
            $this->SetFont('Arial', '', 10);
            $this->Cell($col2_width, 6, date('d.m.Y', strtotime($this->invoice['due_date'])), 0, 1);
        }

        $this->Ln(10);
    }

    /**
     * Tableau des articles
     */
    private function renderItemsTable() {
        // En-tête du tableau
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(102, 126, 234);
        $this->SetTextColor(255, 255, 255);

        $this->Cell(80, 8, 'Description', 1, 0, 'L', true);
        $this->Cell(20, 8, utf8_decode('Qté'), 1, 0, 'C', true);
        $this->Cell(30, 8, 'Prix unit.', 1, 0, 'R', true);
        $this->Cell(20, 8, 'TVA', 1, 0, 'C', true);
        $this->Cell(40, 8, 'Total', 1, 1, 'R', true);

        // Lignes
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0, 0, 0);

        $fill = false;
        foreach ($this->items as $item) {
            $this->SetFillColor(245, 245, 245);

            // Description (peut être sur plusieurs lignes)
            $description = utf8_decode($item['description']);
            $x = $this->GetX();
            $y = $this->GetY();

            $this->MultiCell(80, 6, $description, 1, 'L', $fill);

            $height = $this->GetY() - $y;
            $this->SetXY($x + 80, $y);

            $this->Cell(20, $height, number_format($item['quantity'], 2), 1, 0, 'C', $fill);
            $this->Cell(30, $height, 'CHF ' . number_format($item['price'], 2), 1, 0, 'R', $fill);
            $this->Cell(20, $height, number_format($item['tva_rate'], 2) . '%', 1, 0, 'C', $fill);
            $this->Cell(40, $height, 'CHF ' . number_format($item['total'], 2), 1, 1, 'R', $fill);

            $fill = !$fill;
        }
    }

    /**
     * Totaux
     */
    private function renderTotals() {
        $this->Ln(5);

        $this->SetFont('Arial', '', 10);
        $label_width = 140;
        $value_width = 50;

        // Sous-total
        $this->Cell($label_width, 6, 'Sous-total HT:', 0, 0, 'R');
        $this->Cell($value_width, 6, 'CHF ' . number_format($this->invoice['subtotal'], 2), 0, 1, 'R');

        // TVA
        $this->Cell($label_width, 6, 'TVA:', 0, 0, 'R');
        $this->Cell($value_width, 6, 'CHF ' . number_format($this->invoice['tva_amount'], 2), 0, 1, 'R');

        // Total
        $this->SetFont('Arial', 'B', 12);
        $this->Cell($label_width, 8, 'TOTAL:', 0, 0, 'R');
        $this->Cell($value_width, 8, 'CHF ' . number_format($this->invoice['total'], 2), 0, 1, 'R');
    }

    /**
     * Conditions de paiement
     */
    private function renderPaymentTerms() {
        $this->Ln(10);
        $this->SetFont('Arial', 'I', 9);
        $this->MultiCell(0, 5, utf8_decode("Merci de régler cette facture avant le " .
            date('d.m.Y', strtotime($this->invoice['due_date'])) .
            ".\nEn cas de questions, n'hésitez pas à nous contacter."));

        $this->Ln(5);

        if (!empty($this->company['bank_name']) || !empty($this->company['iban'])) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 5, utf8_decode('Coordonnées bancaires:'), 0, 1);
            $this->SetFont('Arial', '', 9);

            if (!empty($this->company['bank_name'])) {
                $this->Cell(0, 5, 'Banque: ' . utf8_decode($this->company['bank_name']), 0, 1);
            }
            if (!empty($this->company['iban'])) {
                $this->Cell(0, 5, 'IBAN: ' . $this->company['iban'], 0, 1);
            }
            if (!empty($this->invoice['qr_reference'])) {
                $this->Cell(0, 5, utf8_decode('Référence: ') . $this->invoice['qr_reference'], 0, 1);
            }
        }
    }

    /**
     * Générer la QR-facture
     */
    private function renderQRBill() {
        try {
            // Créer les informations du créancier (société)
            $creditor = CombinedAddress::create(
                $this->company['name'],
                $this->company['address'] ?? '',
                $this->company['postal_code'] . ' ' . $this->company['city'],
                'CH'
            );

            $creditorInformation = CreditorInformation::create($this->company['iban']);

            // Créer les informations du débiteur (client)
            $debtor = CombinedAddress::create(
                $this->client['name'],
                $this->client['address'] ?? '',
                ($this->client['postal_code'] ?? '') . ' ' . ($this->client['city'] ?? ''),
                'CH'
            );

            // Montant
            $paymentAmount = PaymentAmountInformation::create('CHF', $this->invoice['total']);

            // Référence
            $paymentReference = PaymentReference::create(
                PaymentReference::TYPE_QR,
                $this->invoice['qr_reference']
            );

            // Créer la QR-facture
            $qrBill = QrBill::create();
            $qrBill->setCreditor($creditor);
            $qrBill->setCreditorInformation($creditorInformation);
            $qrBill->setUltimateDebtor($debtor);
            $qrBill->setPaymentAmountInformation($paymentAmount);
            $qrBill->setPaymentReference($paymentReference);

            // Générer l'image QR
            $qrCode = new \Sprain\SwissQrBill\PaymentPart\Output\HtmlOutput\HtmlOutput($qrBill, 'fr');

            // Afficher titre
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, 'QR-Facture', 0, 1, 'C');
            $this->Ln(5);

            // Note: Pour une vraie intégration, il faudrait utiliser le générateur PDF de la bibliothèque
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(0, 5, utf8_decode("Scannez le code QR ci-dessous avec votre application bancaire pour effectuer le paiement."));

        } catch (Exception $e) {
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 10, 'Erreur lors de la generation de la QR-facture: ' . $e->getMessage(), 0, 1);
        }
    }

    /**
     * Sauvegarder le PDF
     */
    public function save($filename) {
        $this->Output('F', $filename);
    }

    /**
     * Télécharger le PDF
     */
    public function download($filename) {
        $this->Output('D', $filename);
    }

    /**
     * Afficher le PDF dans le navigateur
     */
    public function display($filename) {
        $this->Output('I', $filename);
    }
}
?>
