<?php
/**
 * API: Génération PDF Facture Fournisseur (mPDF)
 */

session_name('COMPTAPP_SESSION');
session_start();

if (!isset($_SESSION['company_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Non autorisé');
}

$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoice_id <= 0) {
    http_response_code(400);
    die('ID de facture invalide');
}

require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/models/SupplierInvoice.php';
require_once dirname(dirname(__DIR__)) . '/models/Company.php';
require_once dirname(dirname(__DIR__)) . '/models/Contact.php';
require_once dirname(dirname(__DIR__)) . '/vendor/autoload.php';

$company_id = $_SESSION['company_id'];

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    die('Erreur de connexion à la base de données');
}

try {
    $supplierInvoice = new SupplierInvoice($db);
    $supplierInvoice->id = $invoice_id;
    $supplierInvoice->company_id = $company_id;

    if (!$supplierInvoice->read()) {
        http_response_code(404);
        die('Facture non trouvée');
    }

    $company = new Company($db);
    $company->id = $company_id;
    $company->read();

    $contact = new Contact($db);
    $contact->id = $supplierInvoice->supplier_id;
    $contact->company_id = $company_id;

    $supplier_name = 'Fournisseur inconnu';
    if ($contact->read()) {
        $supplier_name = htmlspecialchars($contact->name);
    }

    $status_labels = [
        'received'  => 'Reçue',
        'approved'  => 'Approuvée',
        'paid'      => 'Payée',
        'cancelled' => 'Annulée',
        'disputed'  => 'En litige',
    ];
    $status_label = $status_labels[$supplierInvoice->status] ?? $supplierInvoice->status;

    // Construction du HTML pour mPDF
    $rows_html = '';
    $fill = false;
    foreach ($supplierInvoice->items as $item) {
        $bg = $fill ? '#f5f5f5' : '#ffffff';
        $rows_html .= '<tr style="background:' . $bg . ';">
            <td style="padding:6px 8px;border:1px solid #ddd;">' . htmlspecialchars($item['description']) . '</td>
            <td style="padding:6px 8px;border:1px solid #ddd;text-align:center;">' . number_format($item['quantity'], 2) . '</td>
            <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">CHF ' . number_format($item['unit_price'], 2) . '</td>
            <td style="padding:6px 8px;border:1px solid #ddd;text-align:center;">' . number_format($item['tva_rate'], 2) . '%</td>
            <td style="padding:6px 8px;border:1px solid #ddd;text-align:right;">CHF ' . number_format($item['total'], 2) . '</td>
        </tr>';
        $fill = !$fill;
    }

    $notes_html = '';
    if (!empty($supplierInvoice->notes)) {
        $notes_html = '<div style="margin-top:20px;">
            <strong>Notes:</strong><br>
            <p style="font-size:9pt;">' . nl2br(htmlspecialchars($supplierInvoice->notes)) . '</p>
        </div>';
    }

    $html = '
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;font-size:10pt;color:#333;">

    <!-- En-tête société -->
    <table width="100%" style="margin-bottom:20px;">
        <tr>
            <td width="60%">
                <span style="font-size:18pt;font-weight:bold;color:#667eea;">FACTURE FOURNISSEUR</span>
            </td>
            <td width="40%" style="text-align:right;font-size:9pt;">
                <strong>' . htmlspecialchars($company->name ?? '') . '</strong><br>
                ' . htmlspecialchars($company->address ?? '') . '<br>
                ' . htmlspecialchars(($company->postal_code ?? '') . ' ' . ($company->city ?? '')) . '
            </td>
        </tr>
    </table>

    <hr style="border:1px solid #667eea;margin-bottom:20px;">

    <!-- Détails facture -->
    <table width="100%" style="margin-bottom:20px;">
        <tr>
            <td width="30%" style="padding:4px 0;"><strong>Fournisseur:</strong></td>
            <td>' . $supplier_name . '</td>
        </tr>
        <tr>
            <td style="padding:4px 0;"><strong>Numéro:</strong></td>
            <td>' . htmlspecialchars($supplierInvoice->invoice_number) . '</td>
        </tr>
        <tr>
            <td style="padding:4px 0;"><strong>Date facture:</strong></td>
            <td>' . date('d.m.Y', strtotime($supplierInvoice->invoice_date)) . '</td>
        </tr>
        <tr>
            <td style="padding:4px 0;"><strong>Échéance:</strong></td>
            <td>' . date('d.m.Y', strtotime($supplierInvoice->due_date)) . '</td>
        </tr>
        <tr>
            <td style="padding:4px 0;"><strong>Statut:</strong></td>
            <td>' . htmlspecialchars($status_label) . '</td>
        </tr>
    </table>

    <!-- Tableau des articles -->
    <table width="100%" style="border-collapse:collapse;margin-bottom:20px;">
        <thead>
            <tr style="background:#667eea;color:#fff;">
                <th style="padding:8px;border:1px solid #667eea;text-align:left;">Description</th>
                <th style="padding:8px;border:1px solid #667eea;text-align:center;width:50px;">Qté</th>
                <th style="padding:8px;border:1px solid #667eea;text-align:right;width:80px;">Prix unit.</th>
                <th style="padding:8px;border:1px solid #667eea;text-align:center;width:50px;">TVA</th>
                <th style="padding:8px;border:1px solid #667eea;text-align:right;width:80px;">Total</th>
            </tr>
        </thead>
        <tbody>' . $rows_html . '</tbody>
    </table>

    <!-- Totaux -->
    <table width="100%" style="margin-bottom:20px;">
        <tr>
            <td width="70%"></td>
            <td width="30%">
                <table width="100%" style="font-size:10pt;">
                    <tr>
                        <td style="padding:4px 0;">Sous-total HT:</td>
                        <td style="text-align:right;">CHF ' . number_format($supplierInvoice->subtotal, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding:4px 0;">TVA:</td>
                        <td style="text-align:right;">CHF ' . number_format($supplierInvoice->tva_amount, 2) . '</td>
                    </tr>
                    <tr style="font-size:13pt;font-weight:bold;border-top:2px solid #667eea;">
                        <td style="padding:6px 0;">TOTAL:</td>
                        <td style="text-align:right;color:#667eea;">CHF ' . number_format($supplierInvoice->total, 2) . '</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    ' . $notes_html . '

</body>
</html>';

    $mpdf = new \Mpdf\Mpdf([
        'margin_left'   => 15,
        'margin_right'  => 15,
        'margin_top'    => 20,
        'margin_bottom' => 20,
        'default_font'  => 'dejavusans',
    ]);

    $mpdf->SetTitle('Facture fournisseur ' . $supplierInvoice->invoice_number);
    $mpdf->WriteHTML($html);

    $filename = 'Facture_Fournisseur_' . $supplierInvoice->invoice_number . '.pdf';
    $mpdf->Output($filename, 'D');

} catch (Exception $e) {
    error_log('Error generating supplier invoice PDF: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
}
?>
