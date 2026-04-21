<?php
/**
 * AJAX Endpoint: Bank Import
 * Handles bank statement file uploads and imports
 */

header('Content-Type: application/json');

session_name('COMPTAPP_SESSION');
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/BankReconciliation.php';
require_once __DIR__ . '/../../models/BankAccount.php';
require_once __DIR__ . '/../../models/BankTransaction.php';

$database = new Database();
$db = $database->getConnection();

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['statement_file'])) {
    $action = $_POST['action'] ?? '';

    if ($action !== 'import') {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Action invalide']);
        exit;
    }

    // Validate bank account
    $bank_account_id = isset($_POST['bank_account_id']) ? intval($_POST['bank_account_id']) : 0;
    if ($bank_account_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Compte bancaire requis']);
        exit;
    }

    // Verify bank account belongs to company
    $bank_account = new BankAccount($db);
    $bank_account->id = $bank_account_id;
    $bank_account->company_id = $company_id;

    if (!$bank_account->read()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Compte bancaire introuvable']);
        exit;
    }

    // Validate file
    $file = $_FILES['statement_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'upload du fichier']);
        exit;
    }

    $allowed_extensions = ['xml', 'txt', 'csv', '940', '053'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($file_extension, $allowed_extensions)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Format de fichier non supporté']);
        exit;
    }

    // Check file size (max 10MB)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Fichier trop volumineux (max 10MB)']);
        exit;
    }

    try {
        // Create upload directory if not exists
        $upload_dir = __DIR__ . '/../../uploads/bank_imports/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generate unique filename
        $unique_filename = date('Ymd_His') . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            throw new Exception('Erreur lors de la sauvegarde du fichier');
        }

        // Initialize reconciliation service
        $reconciliation = new BankReconciliation($db);

        // Get format (auto-detect or from post)
        $format = $_POST['format'] ?? null;

        // Get CSV config if provided
        $csv_config = [];
        if (isset($_POST['csv_config'])) {
            $csv_config = json_decode($_POST['csv_config'], true);
        }

        // Import transactions
        $result = $reconciliation->importFromFile(
            $upload_path,
            $bank_account_id,
            $company_id,
            $format,
            $csv_config
        );

        if ($result['success'] > 0) {
            // Try automatic matching
            $bank_transaction = new BankTransaction($db);
            $pending = $bank_transaction->getPendingTransactions($company_id, $bank_account_id);

            $auto_matched = 0;
            foreach ($pending as $trans) {
                // Only try to match transactions from this import batch
                if (!empty($trans['qr_reference'])) {
                    $bank_transaction->id = $trans['id'];
                    $bank_transaction->company_id = $company_id;

                    if ($bank_transaction->read()) {
                        $invoice = $bank_transaction->findInvoiceByQRReference($trans['qr_reference']);
                        if ($invoice) {
                            if ($bank_transaction->reconcileWithInvoice($invoice['id'], $user_id, true)) {
                                $auto_matched++;
                            }
                        }
                    }
                }
            }

            // Calculate duplicates
            $duplicates = $result['total'] - $result['success'];

            echo json_encode([
                'success' => true,
                'message' => 'Import réussi',
                'imported' => $result['success'],
                'duplicates' => $duplicates,
                'errors' => $result['errors'],
                'auto_matched' => $auto_matched,
                'filename' => $unique_filename
            ]);
        } else {
            // Delete file if import failed
            unlink($upload_path);

            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Aucune transaction importée',
                'details' => $result['errors']
            ]);
        }

    } catch (Exception $e) {
        error_log("Bank Import Error: " . $e->getMessage());

        // Delete file if exists
        if (isset($upload_path) && file_exists($upload_path)) {
            unlink($upload_path);
        }

        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erreur lors de l\'import: ' . $e->getMessage()
        ]);
    }

    exit;
}

// Handle other POST requests (CSV config, format detection, etc.)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Données invalides']);
        exit;
    }

    $action = $data['action'] ?? '';

    switch ($action) {
        case 'detect_format':
            // Detect file format from content preview
            if (empty($data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Contenu requis']);
                exit;
            }

            $reconciliation = new BankReconciliation($db);
            $format = $reconciliation->detectFormat($data['content']);

            echo json_encode([
                'success' => true,
                'format' => $format
            ]);
            break;

        case 'parse_csv_headers':
            // Parse CSV headers for column mapping
            if (empty($data['content'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Contenu requis']);
                exit;
            }

            $delimiter = $data['delimiter'] ?? ',';
            $enclosure = $data['enclosure'] ?? '"';

            $lines = explode("\n", $data['content']);
            if (empty($lines)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Aucune ligne trouvée']);
                exit;
            }

            $headers = str_getcsv($lines[0], $delimiter, $enclosure);

            echo json_encode([
                'success' => true,
                'headers' => $headers,
                'total_lines' => count($lines)
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
