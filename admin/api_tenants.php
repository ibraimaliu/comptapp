<?php
/**
 * API pour la gestion des tenants (suppression, activation, désactivation)
 */

session_name('ADMIN_SESSION');
session_start();

header('Content-Type: application/json');

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once '../config/database_master.php';

$database = new DatabaseMaster();
$db = $database->getConnection();

// Récupérer l'action
$action = $_POST['action'] ?? $_GET['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action non spécifiée']);
    exit;
}

try {
    switch($action) {
        // ============================================
        // ACTIVER UN TENANT
        // ============================================
        case 'activate':
            $tenant_id = $_POST['tenant_id'] ?? null;

            if (!$tenant_id) {
                throw new Exception('ID du tenant manquant');
            }

            // Mettre à jour le statut
            $query = "UPDATE tenants SET status = 'active' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $tenant_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Client activé avec succès'
                ]);
            } else {
                throw new Exception('Erreur lors de l\'activation');
            }
            break;

        // ============================================
        // DÉSACTIVER UN TENANT
        // ============================================
        case 'deactivate':
            $tenant_id = $_POST['tenant_id'] ?? null;

            if (!$tenant_id) {
                throw new Exception('ID du tenant manquant');
            }

            // Mettre à jour le statut
            $query = "UPDATE tenants SET status = 'suspended' WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $tenant_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Client désactivé avec succès'
                ]);
            } else {
                throw new Exception('Erreur lors de la désactivation');
            }
            break;

        // ============================================
        // SUPPRIMER UN TENANT
        // ============================================
        case 'delete':
            $tenant_id = $_POST['tenant_id'] ?? null;
            $confirm = $_POST['confirm'] ?? null;

            if (!$tenant_id) {
                throw new Exception('ID du tenant manquant');
            }

            if ($confirm !== 'DELETE') {
                throw new Exception('Confirmation requise');
            }

            // Récupérer les infos du tenant
            $query = "SELECT database_name, company_name FROM tenants WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $tenant_id);
            $stmt->execute();
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant) {
                throw new Exception('Tenant introuvable');
            }

            $database_name = $tenant['database_name'];
            $company_name = $tenant['company_name'];

            // ATTENTION: Suppression définitive de la base de données
            $db->exec("DROP DATABASE IF EXISTS `$database_name`");

            // Supprimer l'entrée du tenant
            $query = "DELETE FROM tenants WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $tenant_id);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => "Client '$company_name' et sa base de données supprimés définitivement"
            ]);
            break;

        // ============================================
        // CHANGER LE PLAN D'ABONNEMENT
        // ============================================
        case 'change_plan':
            $tenant_id = $_POST['tenant_id'] ?? null;
            $new_plan = $_POST['plan'] ?? null;

            if (!$tenant_id || !$new_plan) {
                throw new Exception('Paramètres manquants');
            }

            // Vérifier que le plan existe
            $valid_plans = ['free', 'starter', 'professional', 'enterprise'];
            if (!in_array($new_plan, $valid_plans)) {
                throw new Exception('Plan invalide');
            }

            $query = "UPDATE tenants SET subscription_plan = :plan WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':plan', $new_plan);
            $stmt->bindParam(':id', $tenant_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Plan modifié avec succès'
                ]);
            } else {
                throw new Exception('Erreur lors de la modification du plan');
            }
            break;

        // ============================================
        // PROLONGER LA PÉRIODE D'ESSAI
        // ============================================
        case 'extend_trial':
            $tenant_id = $_POST['tenant_id'] ?? null;
            $days = $_POST['days'] ?? 30;

            if (!$tenant_id) {
                throw new Exception('ID du tenant manquant');
            }

            $new_date = date('Y-m-d', strtotime("+$days days"));

            $query = "UPDATE tenants SET trial_ends_at = :trial_date WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':trial_date', $new_date);
            $stmt->bindParam(':id', $tenant_id);

            if ($stmt->execute()) {
                echo json_encode([
                    'success' => true,
                    'message' => "Période d'essai prolongée jusqu'au " . date('d/m/Y', strtotime($new_date))
                ]);
            } else {
                throw new Exception('Erreur lors de la prolongation');
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

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
