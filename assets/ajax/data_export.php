<?php
/**
 * API: Export de Données
 * Description: Export CSV/JSON des données comptables
 * Version: 1.0
 */

session_name('COMPTAPP_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

require_once '../../config/database.php';

$database = new Database();
$db = $database->getConnection();

$company_id = $_SESSION['company_id'];
$type = $_GET['type'] ?? '';
$format = $_GET['format'] ?? 'csv';

try {
    switch ($type) {
        case 'transactions':
            exportTransactions($db, $company_id, $format);
            break;

        case 'invoices':
            exportInvoices($db, $company_id, $format);
            break;

        case 'contacts':
            exportContacts($db, $company_id, $format);
            break;

        case 'accounting_plan':
            exportAccountingPlan($db, $company_id, $format);
            break;

        case 'all':
            exportAll($db, $company_id, $format);
            break;

        default:
            throw new Exception('Type d\'export invalide');
    }
} catch (Exception $e) {
    http_response_code(400);
    die('Erreur: ' . $e->getMessage());
}

/**
 * Export transactions
 */
function exportTransactions($db, $company_id, $format) {
    $query = "SELECT t.*, c.name as category_name, a.number as account_number,
                     a.name as account_name
              FROM transactions t
              LEFT JOIN categories c ON t.category_id = c.id
              LEFT JOIN accounting_plan a ON t.account_id = a.id
              WHERE t.company_id = :company_id
              ORDER BY t.date DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'json') {
        outputJSON($data, 'transactions');
    } else {
        outputCSV($data, 'transactions', [
            'Date' => 'date',
            'Type' => 'type',
            'Description' => 'description',
            'Montant' => 'amount',
            'Catégorie' => 'category_name',
            'Compte' => 'account_number',
            'Méthode Paiement' => 'payment_method',
            'Référence' => 'reference'
        ]);
    }
}

/**
 * Export invoices
 */
function exportInvoices($db, $company_id, $format) {
    $query = "SELECT i.*, c.name as client_name
              FROM invoices i
              LEFT JOIN contacts c ON i.client_id = c.id
              WHERE i.company_id = :company_id
              ORDER BY i.date DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'json') {
        outputJSON($data, 'factures');
    } else {
        outputCSV($data, 'factures', [
            'Numéro' => 'invoice_number',
            'Date' => 'date',
            'Client' => 'client_name',
            'Sous-total' => 'subtotal',
            'TVA' => 'tva_amount',
            'Total' => 'total_amount',
            'Statut' => 'status',
            'Date Échéance' => 'due_date'
        ]);
    }
}

/**
 * Export contacts
 */
function exportContacts($db, $company_id, $format) {
    $query = "SELECT *
              FROM contacts
              WHERE company_id = :company_id
              ORDER BY name ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'json') {
        outputJSON($data, 'contacts');
    } else {
        outputCSV($data, 'contacts', [
            'Nom' => 'name',
            'Type' => 'type',
            'Email' => 'email',
            'Téléphone' => 'phone',
            'Adresse' => 'address',
            'Code Postal' => 'postal_code',
            'Ville' => 'city',
            'Pays' => 'country'
        ]);
    }
}

/**
 * Export accounting plan
 */
function exportAccountingPlan($db, $company_id, $format) {
    $query = "SELECT *
              FROM accounting_plan
              WHERE company_id = :company_id
              ORDER BY number ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'json') {
        outputJSON($data, 'plan_comptable');
    } else {
        outputCSV($data, 'plan_comptable', [
            'Numéro' => 'number',
            'Intitulé' => 'name',
            'Catégorie' => 'category',
            'Type' => 'type',
            'Utilisé' => 'is_used'
        ]);
    }
}

/**
 * Export all data
 */
function exportAll($db, $company_id, $format) {
    if ($format !== 'json') {
        throw new Exception('L\'export complet n\'est disponible qu\'au format JSON');
    }

    $data = [];

    // Transactions
    $query = "SELECT * FROM transactions WHERE company_id = :company_id ORDER BY date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $data['transactions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Invoices
    $query = "SELECT * FROM invoices WHERE company_id = :company_id ORDER BY date DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $data['invoices'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Contacts
    $query = "SELECT * FROM contacts WHERE company_id = :company_id ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $data['contacts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Accounting plan
    $query = "SELECT * FROM accounting_plan WHERE company_id = :company_id ORDER BY number ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $data['accounting_plan'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Categories
    $query = "SELECT * FROM categories WHERE company_id = :company_id ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $data['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Products
    $query = "SELECT * FROM products WHERE company_id = :company_id ORDER BY name ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $data['products'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    outputJSON($data, 'export_complet');
}

/**
 * Output CSV
 */
function outputCSV($data, $filename, $columns) {
    $filename = $filename . '_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // UTF-8 BOM for Excel
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Header
    fputcsv($output, array_keys($columns), ';');

    // Data
    foreach ($data as $row) {
        $csvRow = [];
        foreach ($columns as $col) {
            $csvRow[] = $row[$col] ?? '';
        }
        fputcsv($output, $csvRow, ';');
    }

    fclose($output);
    exit;
}

/**
 * Output JSON
 */
function outputJSON($data, $filename) {
    $filename = $filename . '_' . date('Y-m-d_H-i-s') . '.json';

    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
?>
