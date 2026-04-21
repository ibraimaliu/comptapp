<?php
/**
 * API QR-Invoice
 * Gestion des QR-factures suisses
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET");

session_name('COMPTAPP_SESSION');
session_start();

// Vérifier l'authentification
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once '../config/database.php';
require_once '../models/QRInvoice.php';
require_once '../utils/PDFGenerator.php';

$database = new Database();
$db = $database->getConnection();

$qr_invoice = new QRInvoice($db);
$pdf_generator = new PDFGenerator($db);

// Récupérer les données
$data = json_decode(file_get_contents("php://input"));
$action = isset($data->action) ? $data->action : (isset($_GET['action']) ? $_GET['action'] : '');

switch($action) {

    // Générer une référence QRR
    case 'generate_reference':
        if(empty($data->invoice_number) || empty($data->company_id)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Paramètres manquants'
            ]);
            exit;
        }

        try {
            $qr_reference = $qr_invoice->generateQRReference(
                $data->invoice_number,
                $data->company_id
            );

            echo json_encode([
                'success' => true,
                'qr_reference' => $qr_reference,
                'formatted' => $qr_invoice->formatQRReference($qr_reference)
            ]);

        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur lors de la génération: ' . $e->getMessage()
            ]);
        }
        break;

    // Générer le QR-Code
    case 'generate_qr_code':
        if(empty($data->invoice_id) || empty($data->company_id)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Paramètres manquants'
            ]);
            exit;
        }

        try {
            $qr_code_path = $qr_invoice->generateQRCode(
                $data->invoice_id,
                $data->company_id
            );

            if($qr_code_path) {
                echo json_encode([
                    'success' => true,
                    'qr_code_path' => $qr_code_path,
                    'message' => 'QR-Code généré avec succès'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la génération du QR-Code'
                ]);
            }

        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
        break;

    // Générer le PDF avec QR-Code
    case 'generate_pdf':
        if(empty($data->invoice_id) || empty($data->company_id)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Paramètres manquants'
            ]);
            exit;
        }

        try {
            $with_qr = isset($data->with_qr) ? (bool)$data->with_qr : true;

            $pdf_path = $pdf_generator->generateInvoicePDF(
                $data->invoice_id,
                $data->company_id,
                $with_qr
            );

            if($pdf_path) {
                echo json_encode([
                    'success' => true,
                    'pdf_path' => $pdf_path,
                    'message' => 'PDF généré avec succès'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la génération du PDF'
                ]);
            }

        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
        break;

    // Valider un IBAN
    case 'validate_iban':
        if(empty($data->iban)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'IBAN manquant'
            ]);
            exit;
        }

        try {
            $is_valid = $qr_invoice->validateIBAN($data->iban);
            $is_qr_iban = $qr_invoice->isQRIBAN($data->iban);

            echo json_encode([
                'success' => true,
                'is_valid' => $is_valid,
                'is_qr_iban' => $is_qr_iban,
                'formatted' => $qr_invoice->formatIBAN($data->iban)
            ]);

        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
        break;

    // Télécharger un PDF
    case 'download_pdf':
        $invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
        $company_id = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;

        if(!$invoice_id || !$company_id) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Paramètres manquants'
            ]);
            exit;
        }

        try {
            // Générer le PDF
            $pdf_path = $pdf_generator->generateInvoicePDF($invoice_id, $company_id, true);

            if($pdf_path) {
                $full_path = __DIR__ . '/../' . $pdf_path;
                $filename = 'facture_' . $invoice_id . '.pdf';
                $pdf_generator->downloadPDF($full_path, $filename);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la génération du PDF'
                ]);
            }

        } catch(Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
        break;

    // Afficher un PDF dans le navigateur
    case 'view_pdf':
        $invoice_id = isset($_GET['invoice_id']) ? (int)$_GET['invoice_id'] : 0;
        $company_id = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;

        if(!$invoice_id || !$company_id) {
            http_response_code(400);
            echo "Paramètres manquants";
            exit;
        }

        try {
            // Générer le PDF
            $pdf_path = $pdf_generator->generateInvoicePDF($invoice_id, $company_id, true);

            if($pdf_path) {
                $full_path = __DIR__ . '/../' . $pdf_path;
                $pdf_generator->displayPDF($full_path);
            } else {
                http_response_code(500);
                echo "Erreur lors de la génération du PDF";
            }

        } catch(Exception $e) {
            http_response_code(500);
            echo "Erreur: " . $e->getMessage();
        }
        break;

    default:
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue'
        ]);
        break;
}
?>
