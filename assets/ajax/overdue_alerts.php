<?php
/**
 * API AJAX: Alertes Factures en Retard
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
require_once dirname(dirname(__DIR__)) . '/models/SupplierInvoice.php';

$company_id = $_SESSION['company_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

$supplierInvoice = new SupplierInvoice($db);

try {
    // Obtenir les factures en retard
    $overdueInvoices = $supplierInvoice->getOverdueInvoices($company_id);

    // Calculer les totaux
    $totalOverdue = 0;
    $criticalCount = 0; // Plus de 30 jours de retard

    foreach ($overdueInvoices as $invoice) {
        $totalOverdue += floatval($invoice['amount_due']);
        if (intval($invoice['days_overdue']) > 30) {
            $criticalCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'invoices' => $overdueInvoices,
        'count' => count($overdueInvoices),
        'total_amount' => $totalOverdue,
        'critical_count' => $criticalCount
    ]);

} catch (Exception $e) {
    error_log('Error in overdue_alerts.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>
