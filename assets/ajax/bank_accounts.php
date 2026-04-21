<?php
/**
 * AJAX Endpoint: Bank Accounts
 * Handles CRUD operations for bank accounts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

session_name('COMPTAPP_SESSION');
session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Non autorisé']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/BankAccount.php';

$database = new Database();
$db = $database->getConnection();

$company_id = $_SESSION['company_id'];
$bank_account = new BankAccount($db);

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';

    switch ($action) {
        case 'list':
            // Get all accounts for company
            $active_only = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : false;
            $accounts = $bank_account->readByCompany($company_id, $active_only);

            echo json_encode([
                'success' => true,
                'accounts' => $accounts,
                'count' => count($accounts)
            ]);
            break;

        case 'get':
            // Get single account
            $account_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

            if ($account_id <= 0) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID invalide']);
                exit;
            }

            $bank_account->id = $account_id;
            $bank_account->company_id = $company_id;

            if ($bank_account->read()) {
                echo json_encode([
                    'success' => true,
                    'account' => [
                        'id' => $bank_account->id,
                        'company_id' => $bank_account->company_id,
                        'name' => $bank_account->name,
                        'bank_name' => $bank_account->bank_name,
                        'iban' => $bank_account->iban,
                        'account_number' => $bank_account->account_number,
                        'currency' => $bank_account->currency,
                        'opening_balance' => $bank_account->opening_balance,
                        'opening_balance_date' => $bank_account->opening_balance_date,
                        'current_balance' => $bank_account->current_balance,
                        'last_reconciliation_date' => $bank_account->last_reconciliation_date,
                        'is_active' => $bank_account->is_active,
                        'notes' => $bank_account->notes
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Compte introuvable']);
            }
            break;

        case 'statistics':
            // Get statistics
            $stats = $bank_account->getStatistics($company_id);
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
        case 'create':
            // Validate required fields
            if (empty($data['name']) || empty($data['currency'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Nom et devise requis']);
                exit;
            }

            // Set properties
            $bank_account->company_id = $company_id;
            $bank_account->name = $data['name'];
            $bank_account->bank_name = $data['bank_name'] ?? null;
            $bank_account->iban = $data['iban'] ?? null;
            $bank_account->account_number = $data['account_number'] ?? null;
            $bank_account->currency = $data['currency'];
            $bank_account->opening_balance = isset($data['opening_balance']) ? floatval($data['opening_balance']) : 0.00;
            $bank_account->opening_balance_date = $data['opening_balance_date'] ?? null;
            $bank_account->is_active = 1;
            $bank_account->notes = $data['notes'] ?? null;

            if ($bank_account->create()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Compte créé avec succès',
                    'account_id' => $bank_account->id
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la création']);
            }
            break;

        case 'update':
            // Validate required fields
            if (empty($data['id']) || empty($data['name']) || empty($data['currency'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Données incomplètes']);
                exit;
            }

            // Set properties
            $bank_account->id = intval($data['id']);
            $bank_account->company_id = $company_id;
            $bank_account->name = $data['name'];
            $bank_account->bank_name = $data['bank_name'] ?? null;
            $bank_account->iban = $data['iban'] ?? null;
            $bank_account->account_number = $data['account_number'] ?? null;
            $bank_account->currency = $data['currency'];
            $bank_account->opening_balance = isset($data['opening_balance']) ? floatval($data['opening_balance']) : 0.00;
            $bank_account->opening_balance_date = $data['opening_balance_date'] ?? null;
            $bank_account->is_active = isset($data['is_active']) ? intval($data['is_active']) : 1;
            $bank_account->notes = $data['notes'] ?? null;

            if ($bank_account->update()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Compte mis à jour avec succès'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour']);
            }
            break;

        case 'delete':
            // Soft delete (set inactive)
            if (empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'ID requis']);
                exit;
            }

            $bank_account->id = intval($data['id']);
            $bank_account->company_id = $company_id;

            if ($bank_account->delete()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Compte désactivé avec succès'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la suppression']);
            }
            break;

        case 'update_balance':
            // Update current balance
            if (empty($data['id']) || !isset($data['balance'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Données incomplètes']);
                exit;
            }

            $bank_account->id = intval($data['id']);
            $bank_account->company_id = $company_id;

            $new_balance = floatval($data['balance']);
            $reconciliation_date = $data['reconciliation_date'] ?? date('Y-m-d');

            if ($bank_account->updateBalance($new_balance, $reconciliation_date)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Solde mis à jour avec succès',
                    'new_balance' => $new_balance
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour du solde']);
            }
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
