<?php
/**
 * API AJAX: Déclaration TVA
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

// Inclure les dépendances
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
$action = $_GET['action'] ?? 'calculate';

try {
    switch ($action) {
        case 'calculate':
            calculateTVA($db, $company_id);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action invalide']);
            break;
    }

} catch (Exception $e) {
    error_log('Error in tva_declaration.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * Calculer la TVA pour une période
 */
function calculateTVA($db, $company_id) {
    try {
        $date_from = $_GET['date_from'] ?? date('Y-01-01');
        $date_to = $_GET['date_to'] ?? date('Y-12-31');

        // TVA collectée (ventes/factures clients)
        $collected_query = "SELECT
                            tva_rate as rate,
                            SUM(subtotal) as base_amount,
                            SUM(tva_amount) as tva_amount,
                            COUNT(*) as count
                        FROM invoices
                        WHERE company_id = :company_id
                        AND date BETWEEN :date_from AND :date_to
                        AND status NOT IN ('cancelled', 'draft')
                        GROUP BY tva_rate
                        ORDER BY tva_rate DESC";

        $stmt = $db->prepare($collected_query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $collected_by_rate = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // TVA déductible (achats/factures fournisseurs)
        $deductible_query = "SELECT
                            tva_rate as rate,
                            SUM(subtotal) as base_amount,
                            SUM(tva_amount) as tva_amount,
                            COUNT(*) as count
                        FROM supplier_invoices
                        WHERE company_id = :company_id
                        AND invoice_date BETWEEN :date_from AND :date_to
                        AND status NOT IN ('cancelled')
                        GROUP BY tva_rate
                        ORDER BY tva_rate DESC";

        $stmt = $db->prepare($deductible_query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_from', $date_from);
        $stmt->bindParam(':date_to', $date_to);
        $stmt->execute();
        $deductible_by_rate = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer les totaux
        $total_collected = array_sum(array_column($collected_by_rate, 'tva_amount'));
        $total_deductible = array_sum(array_column($deductible_by_rate, 'tva_amount'));
        $tva_payable = $total_collected - $total_deductible;

        // Ajouter des lignes vides si aucun taux
        if (empty($collected_by_rate)) {
            $collected_by_rate = [
                ['rate' => '7.70', 'base_amount' => '0.00', 'tva_amount' => '0.00', 'count' => 0],
                ['rate' => '2.50', 'base_amount' => '0.00', 'tva_amount' => '0.00', 'count' => 0],
                ['rate' => '0.00', 'base_amount' => '0.00', 'tva_amount' => '0.00', 'count' => 0]
            ];
        }

        if (empty($deductible_by_rate)) {
            $deductible_by_rate = [
                ['rate' => '7.70', 'base_amount' => '0.00', 'tva_amount' => '0.00', 'count' => 0],
                ['rate' => '2.50', 'base_amount' => '0.00', 'tva_amount' => '0.00', 'count' => 0],
                ['rate' => '0.00', 'base_amount' => '0.00', 'tva_amount' => '0.00', 'count' => 0]
            ];
        }

        echo json_encode([
            'success' => true,
            'period' => [
                'from' => $date_from,
                'to' => $date_to
            ],
            'summary' => [
                'collected' => $total_collected,
                'deductible' => $total_deductible,
                'payable' => $tva_payable
            ],
            'collected_by_rate' => $collected_by_rate,
            'deductible_by_rate' => $deductible_by_rate
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du calcul: ' . $e->getMessage()
        ]);
    }
}
?>
