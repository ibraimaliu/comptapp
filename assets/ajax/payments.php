<?php
/**
 * API AJAX: Gestion des Paiements
 * Version: 1.0
 */

session_name('COMPTAPP_SESSION');
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier la session
if (!isset($_SESSION['company_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Inclure les modèles
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/models/Payment.php';

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

$payment = new Payment($db);

// Déterminer l'action
$action = $_GET['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            listPayments($payment, $company_id);
            break;

        case 'statistics':
            getStatistics($payment, $company_id);
            break;

        case 'by_invoice':
            getByInvoice($payment);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action invalide']);
            break;
    }

} catch (Exception $e) {
    error_log('Error in payments.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * Lister les paiements
 */
function listPayments($payment, $company_id) {
    try {
        $filters = [];

        if (!empty($_GET['payment_type'])) {
            $filters['payment_type'] = $_GET['payment_type'];
        }

        if (!empty($_GET['payment_method'])) {
            $filters['payment_method'] = $_GET['payment_method'];
        }

        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }

        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        $payments = $payment->readByCompany($company_id, $filters);

        echo json_encode([
            'success' => true,
            'payments' => $payments,
            'count' => count($payments)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du chargement des paiements: ' . $e->getMessage()
        ]);
    }
}

/**
 * Obtenir les statistiques
 */
function getStatistics($payment, $company_id) {
    try {
        $period_days = isset($_GET['period_days']) ? intval($_GET['period_days']) : 30;
        $statistics = $payment->getStatistics($company_id, $period_days);

        echo json_encode([
            'success' => true,
            'statistics' => $statistics
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du chargement des statistiques: ' . $e->getMessage()
        ]);
    }
}

/**
 * Obtenir les paiements pour une facture
 */
function getByInvoice($payment) {
    try {
        if (empty($_GET['invoice_id'])) {
            throw new Exception('ID de facture requis');
        }

        $invoice_id = intval($_GET['invoice_id']);
        $type = $_GET['type'] ?? 'supplier'; // 'supplier' ou 'customer'

        if ($type === 'supplier') {
            $payments = $payment->getBySupplierInvoice($invoice_id);
        } else {
            // Pour les factures clients (à implémenter si nécessaire)
            $payments = [];
        }

        echo json_encode([
            'success' => true,
            'payments' => $payments
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
