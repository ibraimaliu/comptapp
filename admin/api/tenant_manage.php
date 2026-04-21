<?php
/**
 * API: Gestion des Tenants par l'Admin
 * Actions: update_status, update_plan, delete_tenant, get_stats
 */

header('Content-Type: application/json');

session_name('ADMIN_SESSION');
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

require_once '../../config/database_master.php';
require_once '../../models/Tenant.php';

$data = json_decode(file_get_contents("php://input"));
$action = $data->action ?? '';

$database = new DatabaseMaster();
$db = $database->getConnection();
$tenant = new Tenant($db);

try {
    switch ($action) {
        case 'update_status':
            // Changer le statut d'un tenant (actif, suspendu, annulé)
            if (empty($data->tenant_id) || empty($data->status)) {
                throw new Exception('ID tenant et statut requis');
            }

            $allowed_statuses = ['active', 'suspended', 'cancelled', 'trial'];
            if (!in_array($data->status, $allowed_statuses)) {
                throw new Exception('Statut invalide');
            }

            $query = "UPDATE tenants SET status = :status WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':status', $data->status);
            $stmt->bindParam(':id', $data->tenant_id);

            if ($stmt->execute()) {
                // Logger l'action
                $tenant->logAction('status_changed', [
                    'tenant_id' => $data->tenant_id,
                    'new_status' => $data->status,
                    'admin_id' => $_SESSION['admin_id'],
                    'admin_username' => $_SESSION['admin_username']
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Statut mis à jour avec succès'
                ]);
            } else {
                throw new Exception('Échec de la mise à jour du statut');
            }
            break;

        case 'update_plan':
            // Changer le plan d'abonnement d'un tenant
            if (empty($data->tenant_id) || empty($data->plan)) {
                throw new Exception('ID tenant et plan requis');
            }

            $allowed_plans = ['free', 'starter', 'professional', 'enterprise'];
            if (!in_array($data->plan, $allowed_plans)) {
                throw new Exception('Plan invalide');
            }

            $query = "UPDATE tenants SET subscription_plan = :plan WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':plan', $data->plan);
            $stmt->bindParam(':id', $data->tenant_id);

            if ($stmt->execute()) {
                // Récupérer les limites du nouveau plan
                $plan_query = "SELECT * FROM subscription_plans WHERE plan_code = :plan";
                $plan_stmt = $db->prepare($plan_query);
                $plan_stmt->bindParam(':plan', $data->plan);
                $plan_stmt->execute();
                $plan_info = $plan_stmt->fetch(PDO::FETCH_ASSOC);

                // Mettre à jour les limites
                $update_limits = "UPDATE tenants
                                  SET max_users = :max_users,
                                      max_transactions_per_month = :max_transactions,
                                      max_storage_mb = :max_storage
                                  WHERE id = :id";
                $limits_stmt = $db->prepare($update_limits);
                $limits_stmt->execute([
                    ':max_users' => $plan_info['max_users'],
                    ':max_transactions' => $plan_info['max_transactions_per_month'],
                    ':max_storage' => $plan_info['max_storage_mb'],
                    ':id' => $data->tenant_id
                ]);

                // Logger l'action
                $tenant->logAction('plan_changed', [
                    'tenant_id' => $data->tenant_id,
                    'new_plan' => $data->plan,
                    'admin_id' => $_SESSION['admin_id'],
                    'admin_username' => $_SESSION['admin_username']
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Plan mis à jour avec succès',
                    'plan_info' => $plan_info
                ]);
            } else {
                throw new Exception('Échec de la mise à jour du plan');
            }
            break;

        case 'extend_trial':
            // Prolonger la période d'essai
            if (empty($data->tenant_id) || empty($data->days)) {
                throw new Exception('ID tenant et nombre de jours requis');
            }

            $days = intval($data->days);
            if ($days < 1 || $days > 365) {
                throw new Exception('Nombre de jours invalide (1-365)');
            }

            $query = "UPDATE tenants
                      SET trial_ends_at = DATE_ADD(COALESCE(trial_ends_at, NOW()), INTERVAL :days DAY)
                      WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            $stmt->bindParam(':id', $data->tenant_id);

            if ($stmt->execute()) {
                // Logger l'action
                $tenant->logAction('trial_extended', [
                    'tenant_id' => $data->tenant_id,
                    'days_added' => $days,
                    'admin_id' => $_SESSION['admin_id'],
                    'admin_username' => $_SESSION['admin_username']
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => "Période d'essai prolongée de $days jours"
                ]);
            } else {
                throw new Exception("Échec de la prolongation de l'essai");
            }
            break;

        case 'get_stats':
            // Récupérer les statistiques d'utilisation d'un tenant
            if (empty($data->tenant_id)) {
                throw new Exception('ID tenant requis');
            }

            // Récupérer les infos du tenant
            $tenant_query = "SELECT * FROM tenants WHERE id = :id";
            $tenant_stmt = $db->prepare($tenant_query);
            $tenant_stmt->bindParam(':id', $data->tenant_id);
            $tenant_stmt->execute();
            $tenant_data = $tenant_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant_data) {
                throw new Exception('Tenant non trouvé');
            }

            // Récupérer les statistiques d'utilisation
            $usage_query = "SELECT * FROM tenant_usage WHERE tenant_id = :id ORDER BY period DESC LIMIT 6";
            $usage_stmt = $db->prepare($usage_query);
            $usage_stmt->bindParam(':id', $data->tenant_id);
            $usage_stmt->execute();
            $usage_stats = $usage_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Récupérer les dernières connexions depuis audit_logs
            $audit_query = "SELECT * FROM audit_logs
                            WHERE tenant_id = :id AND action = 'tenant_login'
                            ORDER BY created_at DESC LIMIT 10";
            $audit_stmt = $db->prepare($audit_query);
            $audit_stmt->bindParam(':id', $data->tenant_id);
            $audit_stmt->execute();
            $recent_logins = $audit_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Se connecter à la base du tenant pour obtenir des stats réelles
            try {
                $tenant_conn = new PDO(
                    "mysql:host={$tenant_data['db_host']};dbname={$tenant_data['database_name']}",
                    $tenant_data['db_username'],
                    $tenant_data['db_password']
                );
                $tenant_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // Compter les utilisateurs
                $users_count = $tenant_conn->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];

                // Compter les transactions ce mois
                $transactions_count = $tenant_conn->query(
                    "SELECT COUNT(*) as count FROM transactions
                     WHERE MONTH(date) = MONTH(CURRENT_DATE())
                     AND YEAR(date) = YEAR(CURRENT_DATE())"
                )->fetch()['count'];

                // Compter les factures
                $invoices_count = $tenant_conn->query("SELECT COUNT(*) as count FROM invoices")->fetch()['count'];

                // Compter les contacts
                $contacts_count = $tenant_conn->query("SELECT COUNT(*) as count FROM contacts")->fetch()['count'];

                $real_stats = [
                    'users' => $users_count,
                    'transactions_this_month' => $transactions_count,
                    'invoices' => $invoices_count,
                    'contacts' => $contacts_count
                ];
            } catch (Exception $e) {
                $real_stats = null;
                error_log("Erreur connexion tenant DB: " . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'tenant' => $tenant_data,
                'usage_history' => $usage_stats,
                'recent_logins' => $recent_logins,
                'real_stats' => $real_stats
            ]);
            break;

        case 'delete_tenant':
            // DANGER: Supprimer un tenant ET sa base de données
            if (empty($data->tenant_id) || empty($data->confirmation)) {
                throw new Exception('ID tenant et confirmation requis');
            }

            if ($data->confirmation !== 'DELETE') {
                throw new Exception('Confirmation invalide. Tapez DELETE pour confirmer.');
            }

            // Récupérer les infos du tenant
            $tenant_query = "SELECT * FROM tenants WHERE id = :id";
            $tenant_stmt = $db->prepare($tenant_query);
            $tenant_stmt->bindParam(':id', $data->tenant_id);
            $tenant_stmt->execute();
            $tenant_data = $tenant_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant_data) {
                throw new Exception('Tenant non trouvé');
            }

            $db->beginTransaction();

            try {
                // Supprimer la base de données du tenant
                $drop_db = "DROP DATABASE IF EXISTS `{$tenant_data['database_name']}`";
                $db->exec($drop_db);

                // Supprimer les enregistrements associés
                $db->exec("DELETE FROM tenant_subscriptions WHERE tenant_id = {$data->tenant_id}");
                $db->exec("DELETE FROM tenant_usage WHERE tenant_id = {$data->tenant_id}");
                $db->exec("DELETE FROM audit_logs WHERE tenant_id = {$data->tenant_id}");

                // Supprimer le tenant
                $db->exec("DELETE FROM tenants WHERE id = {$data->tenant_id}");

                // Logger l'action
                $tenant->logAction('tenant_deleted', [
                    'tenant_id' => $data->tenant_id,
                    'tenant_code' => $tenant_data['tenant_code'],
                    'company_name' => $tenant_data['company_name'],
                    'database_name' => $tenant_data['database_name'],
                    'admin_id' => $_SESSION['admin_id'],
                    'admin_username' => $_SESSION['admin_username']
                ]);

                $db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Tenant et base de données supprimés avec succès'
                ]);
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;

        default:
            throw new Exception('Action non reconnue');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log("Erreur API tenant_manage: " . $e->getMessage());
}
?>
