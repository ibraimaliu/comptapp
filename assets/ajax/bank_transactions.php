<?php
/**
 * AJAX Endpoint: Bank Transactions
 * Handles transaction listing, matching, and reconciliation
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
require_once __DIR__ . '/../../models/BankTransaction.php';

$database = new Database();
$db = $database->getConnection();

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];
$bank_transaction = new BankTransaction($db);

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'pending':
            // Get pending transactions
            $bank_account_id = isset($_GET['bank_account_id']) ? intval($_GET['bank_account_id']) : null;
            $transactions = $bank_transaction->getPendingTransactions($company_id, $bank_account_id);

            echo json_encode([
                'success' => true,
                'transactions' => $transactions,
                'count' => count($transactions)
            ]);
            break;

        case 'reconciled':
            // Get reconciled transactions (matched + manual)
            $query = "SELECT bt.*,
                             ba.name as account_name,
                             ba.currency as account_currency,
                             i.number as invoice_number
                    FROM bank_transactions bt
                    INNER JOIN bank_accounts ba ON bt.bank_account_id = ba.id
                    LEFT JOIN invoices i ON bt.matched_invoice_id = i.id
                    WHERE bt.company_id = :company_id
                      AND bt.status IN ('matched', 'manual')
                    ORDER BY bt.transaction_date DESC
                    LIMIT 100";

            $stmt = $db->prepare($query);
            $stmt->bindParam(":company_id", $company_id);
            $stmt->execute();

            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'transactions' => $transactions,
                'count' => count($transactions)
            ]);
            break;

        case 'by_account':
            // Get transactions for specific account
            $bank_account_id = isset($_GET['bank_account_id']) ? intval($_GET['bank_account_id']) : 0;
            $status = $_GET['status'] ?? null;
            $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 100;
            $offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

            if ($bank_account_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID compte invalide']);
                exit;
            }

            $transactions = $bank_transaction->readByBankAccount($bank_account_id, $status, $limit, $offset);

            echo json_encode([
                'success' => true,
                'transactions' => $transactions,
                'count' => count($transactions)
            ]);
            break;

        case 'find_matches':
            // Find matching invoices for a transaction
            $transaction_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if ($transaction_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            $bank_transaction->id = $transaction_id;
            $bank_transaction->company_id = $company_id;

            if (!$bank_transaction->read()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Transaction introuvable']);
                exit;
            }

            $matches = [];

            // Try QR-Reference first (exact match)
            if (!empty($bank_transaction->qr_reference)) {
                $invoice = $bank_transaction->findInvoiceByQRReference($bank_transaction->qr_reference);
                if ($invoice) {
                    $invoice['match_type'] = 'qr_reference';
                    $invoice['confidence'] = 100;
                    $matches[] = $invoice;
                }
            }

            // Try amount matching (if no QR match or amount > 0)
            if (empty($matches) || $bank_transaction->amount > 0) {
                $amount_matches = $bank_transaction->findInvoicesByAmount(abs($bank_transaction->amount), 0.50);
                foreach ($amount_matches as $invoice) {
                    $invoice['match_type'] = 'amount';
                    $invoice['confidence'] = 80;
                    $matches[] = $invoice;
                }
            }

            echo json_encode([
                'success' => true,
                'matches' => $matches,
                'transaction' => [
                    'id' => $bank_transaction->id,
                    'description' => $bank_transaction->description,
                    'amount' => $bank_transaction->amount,
                    'qr_reference' => $bank_transaction->qr_reference
                ]
            ]);
            break;

        case 'statistics':
            // Get statistics
            $bank_account_id = isset($_GET['bank_account_id']) ? intval($_GET['bank_account_id']) : null;
            $stats = $bank_transaction->getStatistics($company_id, $bank_account_id);

            echo json_encode([
                'success' => true,
                'statistics' => $stats
            ]);
            break;

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
        case 'reconcile':
            // Reconcile transaction with invoice
            $transaction_id = isset($data['transaction_id']) ? intval($data['transaction_id']) : 0;
            $invoice_id = isset($data['invoice_id']) ? intval($data['invoice_id']) : 0;
            $auto = isset($data['auto']) ? (bool)$data['auto'] : false;

            if ($transaction_id <= 0 || $invoice_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'IDs invalides']);
                exit;
            }

            $bank_transaction->id = $transaction_id;
            $bank_transaction->company_id = $company_id;

            if (!$bank_transaction->read()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Transaction introuvable']);
                exit;
            }

            if ($bank_transaction->reconcileWithInvoice($invoice_id, $user_id, $auto)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Transaction rapprochée avec succès'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors du rapprochement']);
            }
            break;

        case 'ignore':
            // Mark transaction as ignored
            $transaction_id = isset($data['id']) ? intval($data['id']) : 0;

            if ($transaction_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            $bank_transaction->id = $transaction_id;
            $bank_transaction->company_id = $company_id;

            if (!$bank_transaction->read()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Transaction introuvable']);
                exit;
            }

            if ($bank_transaction->markAsIgnored()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Transaction ignorée'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de l\'opération']);
            }
            break;

        case 'auto_match':
            // Automatic matching for all pending transactions
            $bank_account_id = isset($data['bank_account_id']) ? intval($data['bank_account_id']) : null;
            $pending = $bank_transaction->getPendingTransactions($company_id, $bank_account_id);

            $matched_count = 0;
            $errors = [];

            foreach ($pending as $trans) {
                $bank_transaction->id = $trans['id'];
                $bank_transaction->company_id = $company_id;

                if ($bank_transaction->read()) {
                    // Try QR-Reference matching
                    if (!empty($bank_transaction->qr_reference)) {
                        $invoice = $bank_transaction->findInvoiceByQRReference($bank_transaction->qr_reference);
                        if ($invoice) {
                            if ($bank_transaction->reconcileWithInvoice($invoice['id'], $user_id, true)) {
                                $matched_count++;
                            } else {
                                $errors[] = "Transaction #{$trans['id']}: Erreur rapprochement";
                            }
                        }
                    }
                }
            }

            echo json_encode([
                'success' => true,
                'message' => "$matched_count transactions rapprochées automatiquement",
                'matched_count' => $matched_count,
                'errors' => $errors
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
