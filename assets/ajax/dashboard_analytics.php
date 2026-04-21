<?php
/**
 * API AJAX: Analytiques Dashboard Avancé
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

$company_id = $_SESSION['company_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

// Déterminer l'action
$action = $_GET['action'] ?? 'summary';

try {
    switch ($action) {
        case 'summary':
            getSummaryKPIs($db, $company_id);
            break;

        case 'evolution':
            getEvolutionData($db, $company_id);
            break;

        case 'categories':
            getCategoryBreakdown($db, $company_id);
            break;

        case 'cash_flow':
            getCashFlowData($db, $company_id);
            break;

        case 'top_clients':
            getTopClients($db, $company_id);
            break;

        case 'top_suppliers':
            getTopSuppliers($db, $company_id);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action invalide']);
            break;
    }

} catch (Exception $e) {
    error_log('Error in dashboard_analytics.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * KPIs résumé
 */
function getSummaryKPIs($db, $company_id) {
    try {
        $period = isset($_GET['period']) ? intval($_GET['period']) : 30;

        // Calculer les dates
        $date_from = date('Y-m-d', strtotime("-$period days"));
        $date_to = date('Y-m-d');

        // Revenus période actuelle
        $query = "SELECT COALESCE(SUM(amount), 0) as total
                  FROM transactions
                  WHERE company_id = :company_id
                  AND type = 'income'
                  AND date BETWEEN :date_from AND :date_to";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $current_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Revenus période précédente (pour comparaison)
        $prev_date_from = date('Y-m-d', strtotime("-" . ($period * 2) . " days"));
        $prev_date_to = date('Y-m-d', strtotime("-$period days"));

        $stmt->bindParam(':date_from', $prev_date_from);
        $stmt->bindParam(':date_to', $prev_date_to);
        $stmt->execute();
        $prev_income = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Dépenses période actuelle
        $query = "SELECT COALESCE(SUM(amount), 0) as total
                  FROM transactions
                  WHERE company_id = :company_id
                  AND type = 'expense'
                  AND date BETWEEN :date_from AND :date_to";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $date_from = date('Y-m-d', strtotime("-$period days"));
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $current_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Dépenses période précédente
        $stmt->bindParam(':date_from', $prev_date_from);
        $stmt->bindParam(':date_to', $prev_date_to);
        $stmt->execute();
        $prev_expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Factures impayées
        $query = "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as amount
                  FROM invoices
                  WHERE company_id = :company_id
                  AND status NOT IN ('paid', 'cancelled')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();
        $unpaid_invoices = $stmt->fetch(PDO::FETCH_ASSOC);

        // Factures fournisseurs à payer
        $query = "SELECT COUNT(*) as count, COALESCE(SUM(total), 0) as amount
                  FROM supplier_invoices
                  WHERE company_id = :company_id
                  AND status IN ('received', 'approved')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();
        $pending_supplier_invoices = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calculer les variations
        $income_variation = $prev_income > 0
            ? (($current_income - $prev_income) / $prev_income) * 100
            : 0;
        $expenses_variation = $prev_expenses > 0
            ? (($current_expenses - $prev_expenses) / $prev_expenses) * 100
            : 0;

        echo json_encode([
            'success' => true,
            'data' => [
                'income' => [
                    'current' => floatval($current_income),
                    'previous' => floatval($prev_income),
                    'variation' => round($income_variation, 2)
                ],
                'expenses' => [
                    'current' => floatval($current_expenses),
                    'previous' => floatval($prev_expenses),
                    'variation' => round($expenses_variation, 2)
                ],
                'profit' => [
                    'current' => floatval($current_income - $current_expenses),
                    'previous' => floatval($prev_income - $prev_expenses)
                ],
                'unpaid_invoices' => [
                    'count' => intval($unpaid_invoices['count']),
                    'amount' => floatval($unpaid_invoices['amount'])
                ],
                'pending_supplier_invoices' => [
                    'count' => intval($pending_supplier_invoices['count']),
                    'amount' => floatval($pending_supplier_invoices['amount'])
                ]
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

/**
 * Évolution revenus/dépenses sur période
 */
function getEvolutionData($db, $company_id) {
    try {
        $period = isset($_GET['period']) ? intval($_GET['period']) : 90;
        $date_from = date('Y-m-d', strtotime("-$period days"));

        // Revenus par jour
        $query = "SELECT DATE(date) as day, COALESCE(SUM(amount), 0) as amount
                  FROM transactions
                  WHERE company_id = :company_id
                  AND type = 'income'
                  AND date >= :date_from
                  GROUP BY DATE(date)
                  ORDER BY day ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->execute();
        $income_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Dépenses par jour
        $query = "SELECT DATE(date) as day, COALESCE(SUM(amount), 0) as amount
                  FROM transactions
                  WHERE company_id = :company_id
                  AND type = 'expense'
                  AND date >= :date_from
                  GROUP BY DATE(date)
                  ORDER BY day ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->execute();
        $expense_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'income' => $income_data,
                'expenses' => $expense_data
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

/**
 * Répartition par catégories
 */
function getCategoryBreakdown($db, $company_id) {
    try {
        $period = isset($_GET['period']) ? intval($_GET['period']) : 30;
        $date_from = date('Y-m-d', strtotime("-$period days"));

        // Dépenses par catégorie
        $query = "SELECT c.name as category, COALESCE(SUM(t.amount), 0) as amount, COUNT(t.id) as count
                  FROM transactions t
                  LEFT JOIN categories c ON t.category_id = c.id
                  WHERE t.company_id = :company_id
                  AND t.type = 'expense'
                  AND t.date >= :date_from
                  GROUP BY c.id, c.name
                  ORDER BY amount DESC
                  LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->execute();
        $expense_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Revenus par catégorie
        $query = "SELECT c.name as category, COALESCE(SUM(t.amount), 0) as amount, COUNT(t.id) as count
                  FROM transactions t
                  LEFT JOIN categories c ON t.category_id = c.id
                  WHERE t.company_id = :company_id
                  AND t.type = 'income'
                  AND t.date >= :date_from
                  GROUP BY c.id, c.name
                  ORDER BY amount DESC
                  LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->execute();
        $income_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => [
                'expenses' => $expense_categories,
                'income' => $income_categories
            ]
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

/**
 * Flux de trésorerie
 */
function getCashFlowData($db, $company_id) {
    try {
        $period = isset($_GET['period']) ? intval($_GET['period']) : 90;
        $date_from = date('Y-m-d', strtotime("-$period days"));

        // Flux par semaine
        $query = "SELECT
                    YEARWEEK(date) as week,
                    DATE(MIN(date)) as week_start,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as income,
                    SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as expense,
                    SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END) as net
                  FROM transactions
                  WHERE company_id = :company_id
                  AND date >= :date_from
                  GROUP BY YEARWEEK(date)
                  ORDER BY week ASC";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->execute();
        $cash_flow = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer le solde cumulé
        $cumulative_balance = 0;
        foreach ($cash_flow as &$week) {
            $cumulative_balance += floatval($week['net']);
            $week['balance'] = $cumulative_balance;
        }

        echo json_encode([
            'success' => true,
            'data' => $cash_flow
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

/**
 * Top clients
 */
function getTopClients($db, $company_id) {
    try {
        $period = isset($_GET['period']) ? intval($_GET['period']) : 365;
        $date_from = date('Y-m-d', strtotime("-$period days"));

        $query = "SELECT
                    c.name,
                    COUNT(DISTINCT i.id) as invoice_count,
                    COALESCE(SUM(i.total), 0) as total_amount
                  FROM invoices i
                  LEFT JOIN contacts c ON i.client_id = c.id
                  WHERE i.company_id = :company_id
                  AND i.date >= :date_from
                  GROUP BY c.id, c.name
                  ORDER BY total_amount DESC
                  LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->execute();
        $top_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $top_clients
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}

/**
 * Top fournisseurs
 */
function getTopSuppliers($db, $company_id) {
    try {
        $period = isset($_GET['period']) ? intval($_GET['period']) : 365;
        $date_from = date('Y-m-d', strtotime("-$period days"));

        $query = "SELECT
                    c.name,
                    COUNT(DISTINCT si.id) as invoice_count,
                    COALESCE(SUM(si.total), 0) as total_amount
                  FROM supplier_invoices si
                  LEFT JOIN contacts c ON si.supplier_id = c.id
                  WHERE si.company_id = :company_id
                  AND si.invoice_date >= :date_from
                  GROUP BY c.id, c.name
                  ORDER BY total_amount DESC
                  LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->execute();
        $top_suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'data' => $top_suppliers
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur: ' . $e->getMessage()
        ]);
    }
}
?>
