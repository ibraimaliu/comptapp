<?php
/**
 * API: Génération PDF Facture
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
require_once dirname(dirname(__DIR__)) . '/models/Invoice.php';
require_once dirname(dirname(__DIR__)) . '/models/Company.php';
require_once dirname(dirname(__DIR__)) . '/models/Contact.php';
require_once dirname(dirname(__DIR__)) . '/vendor/fpdf/fpdf.php';
require_once dirname(dirname(__DIR__)) . '/utils/InvoicePDF.php';

$company_id = $_SESSION['company_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    die('Erreur de connexion à la base de données');
}

try {
    // Charger la facture
    $invoice = new Invoice($db);
    $invoice->id = $invoice_id;
    $invoice->company_id = $company_id;

    if (!$invoice->read()) {
        http_response_code(404);
        die('Facture non trouvée');
    }

    // Charger les données de la société
    $company = new Company($db);
    $company->id = $company_id;
    $company->read();

    $company_data = [
        'name' => $company->name,
        'address' => $company->address ?? '',
        'postal_code' => $company->postal_code ?? '',
        'city' => $company->city ?? '',
        'email' => $company->email ?? '',
        'phone' => $company->phone ?? '',
        'iban' => $company->iban ?? '',
        'bank_name' => $company->bank_name ?? ''
    ];

    // Charger les données du client
    $contact = new Contact($db);
    $contact->id = $invoice->client_id;
    $contact->company_id = $company_id;

    $client_data = [
        'name' => 'Client inconnu',
        'address' => '',
        'postal_code' => '',
        'city' => ''
    ];

    if ($contact->read()) {
        $client_data = [
            'name' => $contact->name,
            'address' => $contact->address ?? '',
            'postal_code' => $contact->postal_code ?? '',
            'city' => $contact->city ?? ''
        ];
    }

    // Préparer les données de la facture
    $invoice_data = [
        'number' => $invoice->number,
        'date' => $invoice->date,
        'due_date' => $invoice->due_date ?? date('Y-m-d', strtotime('+30 days')),
        'subtotal' => $invoice->subtotal,
        'tva_amount' => $invoice->tva_amount,
        'total' => $invoice->total,
        'qr_reference' => $invoice->qr_reference ?? ''
    ];

    // Charger les lignes de facture
    $items = $invoice->items;

    // Générer le PDF
    $pdf = new InvoicePDF($company_data, $client_data, $invoice_data, $items);
    $pdf->generate();

    // Télécharger le PDF
    $filename = 'Facture_' . $invoice->number . '.pdf';
    $pdf->download($filename);

} catch (Exception $e) {
    error_log('Error generating PDF: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur lors de la génération du PDF: ' . $e->getMessage());
}
?>
