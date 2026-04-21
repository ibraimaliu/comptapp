<?php
/**
 * API: Génération PDF Facture Fournisseur
 * Version: 1.0
 */

session_name('COMPTAPP_SESSION');
session_start();

// Vérifier la session
if (!isset($_SESSION['company_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Non autorisé');
}

// Récupérer l'ID de la facture
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoice_id <= 0) {
    http_response_code(400);
    die('ID de facture invalide');
}

// Inclure les dépendances
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/models/SupplierInvoice.php';
require_once dirname(dirname(__DIR__)) . '/models/Company.php';
require_once dirname(dirname(__DIR__)) . '/models/Contact.php';
require_once dirname(dirname(__DIR__)) . '/vendor/fpdf/fpdf.php';

$company_id = $_SESSION['company_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    die('Erreur de connexion à la base de données');
}

try {
    // Charger la facture fournisseur
    $supplierInvoice = new SupplierInvoice($db);
    $supplierInvoice->id = $invoice_id;
    $supplierInvoice->company_id = $company_id;

    if (!$supplierInvoice->read()) {
        http_response_code(404);
        die('Facture non trouvée');
    }

    // Charger les données de la société
    $company = new Company($db);
    $company->id = $company_id;
    $company->read();

    // Charger les données du fournisseur
    $contact = new Contact($db);
    $contact->id = $supplierInvoice->supplier_id;
    $contact->company_id = $company_id;

    $supplier_name = 'Fournisseur inconnu';
    if ($contact->read()) {
        $supplier_name = $contact->name;
    }

    // Créer le PDF
    $pdf = new FPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // En-tête société
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 6, utf8_decode($company->name), 0, 1, 'R');
    $pdf->SetFont('Arial', '', 9);
    if (!empty($company->address)) {
        $pdf->Cell(0, 4, utf8_decode($company->address), 0, 1, 'R');
    }
    if (!empty($company->postal_code) && !empty($company->city)) {
        $pdf->Cell(0, 4, $company->postal_code . ' ' . utf8_decode($company->city), 0, 1, 'R');
    }
    $pdf->Ln(15);

    // Titre
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'FACTURE FOURNISSEUR', 0, 1, 'L');
    $pdf->Ln(5);

    // Informations facture
    $pdf->SetFont('Arial', '', 10);
    $col1_width = 50;
    $col2_width = 140;

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($col1_width, 6, 'Fournisseur:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell($col2_width, 6, utf8_decode($supplier_name), 0, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($col1_width, 6, utf8_decode('Numéro:'), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell($col2_width, 6, $supplierInvoice->invoice_number, 0, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($col1_width, 6, 'Date facture:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell($col2_width, 6, date('d.m.Y', strtotime($supplierInvoice->invoice_date)), 0, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($col1_width, 6, utf8_decode('Échéance:'), 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell($col2_width, 6, date('d.m.Y', strtotime($supplierInvoice->due_date)), 0, 1);

    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell($col1_width, 6, 'Statut:', 0, 0);
    $pdf->SetFont('Arial', '', 10);
    $status_labels = [
        'received' => 'Reçue',
        'approved' => 'Approuvée',
        'paid' => 'Payée',
        'cancelled' => 'Annulée',
        'disputed' => 'En litige'
    ];
    $pdf->Cell($col2_width, 6, utf8_decode($status_labels[$supplierInvoice->status] ?? $supplierInvoice->status), 0, 1);

    $pdf->Ln(10);

    // Tableau des articles
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);

    $pdf->Cell(90, 8, 'Description', 1, 0, 'L', true);
    $pdf->Cell(20, 8, utf8_decode('Qté'), 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Prix unit.', 1, 0, 'R', true);
    $pdf->Cell(20, 8, 'TVA', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Total', 1, 1, 'R', true);

    // Lignes
    $pdf->SetFont('Arial', '', 9);
    $pdf->SetTextColor(0, 0, 0);

    $fill = false;
    foreach ($supplierInvoice->items as $item) {
        $pdf->SetFillColor(245, 245, 245);

        $description = utf8_decode($item['description']);
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->MultiCell(90, 6, $description, 1, 'L', $fill);

        $height = $pdf->GetY() - $y;
        $pdf->SetXY($x + 90, $y);

        $pdf->Cell(20, $height, number_format($item['quantity'], 2), 1, 0, 'C', $fill);
        $pdf->Cell(30, $height, 'CHF ' . number_format($item['unit_price'], 2), 1, 0, 'R', $fill);
        $pdf->Cell(20, $height, number_format($item['tva_rate'], 2) . '%', 1, 0, 'C', $fill);
        $pdf->Cell(30, $height, 'CHF ' . number_format($item['total'], 2), 1, 1, 'R', $fill);

        $fill = !$fill;
    }

    // Totaux
    $pdf->Ln(5);
    $pdf->SetFont('Arial', '', 10);
    $label_width = 160;
    $value_width = 30;

    $pdf->Cell($label_width, 6, 'Sous-total HT:', 0, 0, 'R');
    $pdf->Cell($value_width, 6, 'CHF ' . number_format($supplierInvoice->subtotal, 2), 0, 1, 'R');

    $pdf->Cell($label_width, 6, 'TVA:', 0, 0, 'R');
    $pdf->Cell($value_width, 6, 'CHF ' . number_format($supplierInvoice->tva_amount, 2), 0, 1, 'R');

    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell($label_width, 8, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell($value_width, 8, 'CHF ' . number_format($supplierInvoice->total, 2), 0, 1, 'R');

    // Notes
    if (!empty($supplierInvoice->notes)) {
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(0, 5, 'Notes:', 0, 1);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, utf8_decode($supplierInvoice->notes));
    }

    // Télécharger le PDF
    $filename = 'Facture_Fournisseur_' . $supplierInvoice->invoice_number . '.pdf';
    $pdf->Output('D', $filename);

} catch (Exception $e) {
    error_log('Error generating supplier invoice PDF: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
}
?>
