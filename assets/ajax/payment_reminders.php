<?php
/**
 * AJAX Endpoint: Payment Reminders
 * Handles reminder creation, listing, and management
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST');

session_name('COMPTAPP_SESSION');
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/PaymentReminder.php';

$database = new Database();
$db = $database->getConnection();

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];
$reminder = new PaymentReminder($db);

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'calculate':
            // Calculate amounts for a reminder
            $invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
            $reminder_level = isset($_GET['reminder_level']) ? intval($_GET['reminder_level']) : 1;

            if ($invoice_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            // Get invoice to calculate days overdue
            $query = "SELECT DATEDIFF(CURDATE(), payment_due_date) AS days_overdue
                     FROM invoices
                     WHERE id = :invoice_id AND company_id = :company_id";

            $stmt = $db->prepare($query);
            $stmt->execute([':invoice_id' => $invoice_id, ':company_id' => $company_id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Facture introuvable']);
                exit;
            }

            $days_overdue = max(0, $invoice['days_overdue']);

            $amounts = $reminder->calculateAmounts($invoice_id, $reminder_level, $days_overdue);

            if ($amounts) {
                $amounts['days_overdue'] = $days_overdue;
                echo json_encode([
                    'success' => true,
                    'amounts' => $amounts
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur calcul montants']);
            }
            break;

        case 'list_sent':
            // List all sent reminders
            $reminders = $reminder->getByCompany($company_id, 'sent', 100);

            echo json_encode([
                'success' => true,
                'reminders' => $reminders,
                'count' => count($reminders)
            ]);
            break;

        case 'list_all':
            // List all reminders (any status)
            $status = $_GET['status'] ?? null;
            $reminders = $reminder->getByCompany($company_id, $status, 100);

            echo json_encode([
                'success' => true,
                'reminders' => $reminders,
                'count' => count($reminders)
            ]);
            break;

        case 'by_invoice':
            // Get reminders for specific invoice
            $invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;

            if ($invoice_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            $reminder->company_id = $company_id;
            $reminders = $reminder->getByInvoice($invoice_id);

            echo json_encode([
                'success' => true,
                'reminders' => $reminders,
                'count' => count($reminders)
            ]);
            break;

        case 'overdue':
            // Get overdue invoices
            $overdue = $reminder->findOverdueInvoices($company_id);

            echo json_encode([
                'success' => true,
                'invoices' => $overdue,
                'count' => count($overdue)
            ]);
            break;

        case 'settings':
            // Get reminder settings
            $settings = $reminder->getSettings($company_id);

            echo json_encode([
                'success' => true,
                'settings' => $settings
            ]);
            break;

        case 'statistics':
            // Get statistics
            $stats = $reminder->getStatistics($company_id);

            echo json_encode([
                'success' => true,
                'statistics' => $stats
            ]);
            break;

        case 'download_pdf':
            // Download reminder PDF
            $reminder_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if ($reminder_id <= 0) {
                http_response_code(400);
                die('ID invalide');
            }

            $reminder->id = $reminder_id;
            $reminder->company_id = $company_id;

            if (!$reminder->read()) {
                http_response_code(404);
                die('Rappel introuvable');
            }

            if (empty($reminder->pdf_path) || !file_exists($reminder->pdf_path)) {
                http_response_code(404);
                die('PDF non trouvé');
            }

            // Download
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="rappel_' . $reminder->id . '.pdf"');
            header('Content-Length: ' . filesize($reminder->pdf_path));
            readfile($reminder->pdf_path);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action invalide']);
    }
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
        exit;
    }

    $action = $data['action'] ?? '';

    switch ($action) {
        case 'create_reminder':
            // Create a single reminder
            $invoice_id = isset($data['invoice_id']) ? intval($data['invoice_id']) : 0;
            $reminder_level = isset($data['reminder_level']) ? intval($data['reminder_level']) : 0;
            $notes = $data['notes'] ?? '';
            $send_immediately = isset($data['send_immediately']) ? (bool)$data['send_immediately'] : false;

            if ($invoice_id <= 0 || $reminder_level <= 0 || $reminder_level > 3) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Données invalides']);
                exit;
            }

            $reminder_id = $reminder->generateReminder($invoice_id, $company_id, $user_id, $send_immediately);

            if ($reminder_id) {
                // Update invoice status to overdue
                $update_invoice = "UPDATE invoices SET status = 'overdue' WHERE id = :invoice_id";
                $stmt = $db->prepare($update_invoice);
                $stmt->execute([':invoice_id' => $invoice_id]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Rappel créé avec succès',
                    'reminder_id' => $reminder_id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la création du rappel']);
            }
            break;

        case 'generate_batch':
            // Generate reminders for all overdue invoices
            $send_immediately = isset($data['send_immediately']) ? (bool)$data['send_immediately'] : false;

            $overdue = $reminder->findOverdueInvoices($company_id);
            $created = 0;
            $errors = [];

            foreach ($overdue as $invoice) {
                // Check if can create next level reminder
                if ($invoice['last_reminder_level'] >= 3) {
                    continue; // Maximum level reached
                }

                // Check if enough time passed since last reminder
                $settings = $reminder->getSettings($company_id);
                if ($invoice['last_reminder_level'] > 0 && $invoice['last_reminder_date']) {
                    $days_since_last = (strtotime(date('Y-m-d')) - strtotime($invoice['last_reminder_date'])) / 86400;
                    $required_days = 10; // Default minimum days between reminders

                    if ($days_since_last < $required_days) {
                        continue; // Too soon
                    }
                }

                // Generate reminder
                $reminder_id = $reminder->generateReminder(
                    $invoice['invoice_id'],
                    $company_id,
                    $user_id,
                    $send_immediately
                );

                if ($reminder_id) {
                    $created++;

                    // Update invoice status
                    $update_invoice = "UPDATE invoices SET status = 'overdue' WHERE id = :invoice_id";
                    $stmt = $db->prepare($update_invoice);
                    $stmt->execute([':invoice_id' => $invoice['invoice_id']]);
                } else {
                    $errors[] = "Facture {$invoice['invoice_number']}: Erreur";
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "$created rappel(s) créé(s)",
                'created' => $created,
                'errors' => $errors
            ]);
            break;

        case 'update_settings':
            // Update reminder settings
            $settings = [
                'level1_days' => isset($data['level1_days']) ? intval($data['level1_days']) : 10,
                'level2_days' => isset($data['level2_days']) ? intval($data['level2_days']) : 20,
                'level3_days' => isset($data['level3_days']) ? intval($data['level3_days']) : 30,
                'level1_fee' => isset($data['level1_fee']) ? floatval($data['level1_fee']) : 0.00,
                'level2_fee' => isset($data['level2_fee']) ? floatval($data['level2_fee']) : 10.00,
                'level3_fee' => isset($data['level3_fee']) ? floatval($data['level3_fee']) : 20.00,
                'interest_rate' => isset($data['interest_rate']) ? floatval($data['interest_rate']) : 5.00,
                'apply_interest' => isset($data['apply_interest']) ? intval($data['apply_interest']) : 1,
                'auto_send' => isset($data['auto_send']) ? intval($data['auto_send']) : 0
            ];

            if ($reminder->updateSettings($company_id, $settings)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Paramètres mis à jour'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
            }
            break;

        case 'cancel_reminder':
            // Cancel a reminder
            $reminder_id = isset($data['id']) ? intval($data['id']) : 0;

            if ($reminder_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            $query = "UPDATE payment_reminders
                     SET status = 'cancelled'
                     WHERE id = :id AND company_id = :company_id";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $reminder_id);
            $stmt->bindParam(':company_id', $company_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Rappel annulé'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'annulation']);
            }
            break;

        case 'mark_paid':
            // Mark reminder as paid
            $reminder_id = isset($data['id']) ? intval($data['id']) : 0;

            if ($reminder_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            $reminder->id = $reminder_id;
            $reminder->company_id = $company_id;

            if (!$reminder->read()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Rappel introuvable']);
                exit;
            }

            // Update reminder
            $query = "UPDATE payment_reminders
                     SET status = 'paid'
                     WHERE id = :id";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $reminder_id);
            $stmt->execute();

            // Update invoice
            $update_invoice = "UPDATE invoices
                             SET status = 'paid',
                                 paid_date = CURDATE()
                             WHERE id = :invoice_id";

            $stmt2 = $db->prepare($update_invoice);
            $stmt2->bindParam(':invoice_id', $reminder->invoice_id);
            $stmt2->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Rappel et facture marqués comme payés'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Action invalide']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
?>
