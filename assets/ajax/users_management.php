<?php
/**
 * API de gestion des utilisateurs
 * Actions: invite, update, delete, get
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, GET');

session_name('COMPTAPP_SESSION');
session_start();

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier le mode multi-tenant
if (!isset($_SESSION['tenant_database'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Fonctionnalité multi-tenant uniquement']);
    exit;
}

require_once '../../config/database.php';
require_once '../../models/User.php';
require_once '../../utils/PermissionHelper.php';

$database = new Database();
$db = $database->getConnection();

if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

// Récupérer les données de la requête
$data = json_decode(file_get_contents("php://input"));

if (!$data || !isset($data->action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Action non spécifiée']);
    exit;
}

try {
    switch ($data->action) {
        case 'invite':
            // Vérifier la permission
            if (!PermissionHelper::hasPermission($db, 'users.create')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission refusée']);
                exit;
            }

            // Valider les données
            if (empty($data->email) || empty($data->role_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email et rôle requis']);
                exit;
            }

            // Valider l'email
            if (!filter_var($data->email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Email invalide']);
                exit;
            }

            // Vérifier si l'email existe déjà
            $user = new User($db);
            $user->email = $data->email;
            if ($user->emailExists()) {
                http_response_code(409);
                echo json_encode(['success' => false, 'message' => 'Cet email est déjà utilisé']);
                exit;
            }

            // Générer un token d'invitation
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));

            // Insérer l'invitation
            $query = "INSERT INTO user_invitations (company_id, email, role_id, token, invited_by, expires_at)
                      VALUES (:company_id, :email, :role_id, :token, :invited_by, :expires_at)";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':company_id', $_SESSION['company_id']);
            $stmt->bindParam(':email', $data->email);
            $stmt->bindParam(':role_id', $data->role_id);
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':invited_by', $_SESSION['user_id']);
            $stmt->bindParam(':expires_at', $expires_at);

            if ($stmt->execute()) {
                // TODO: Envoyer l'email d'invitation
                $invitation_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/accept_invitation.php?token=" . $token;

                // Log de l'action
                $log_query = "INSERT INTO user_activity_logs (user_id, company_id, action, module, description, ip_address)
                              VALUES (:user_id, :company_id, 'invite_user', 'users', :description, :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_description = "Invitation envoyée à {$data->email}";
                $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':company_id', $_SESSION['company_id']);
                $log_stmt->bindParam(':description', $log_description);
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bindParam(':ip', $ip);
                $log_stmt->execute();

                echo json_encode([
                    'success' => true,
                    'message' => 'Invitation envoyée avec succès',
                    'invitation_link' => $invitation_link
                ]);
            } else {
                throw new Exception('Erreur lors de la création de l\'invitation');
            }
            break;

        case 'update':
            // Vérifier la permission
            if (!PermissionHelper::hasPermission($db, 'users.edit')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission refusée']);
                exit;
            }

            // Valider les données
            if (empty($data->user_id) || empty($data->role_id) || !isset($data->is_active)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Données incomplètes']);
                exit;
            }

            // Ne pas permettre de se désactiver soi-même
            if ($data->user_id == $_SESSION['user_id'] && $data->is_active == 0) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas vous désactiver vous-même']);
                exit;
            }

            // Mettre à jour l'utilisateur
            $user = new User($db);
            $user->id = $data->user_id;
            $user->role_id = $data->role_id;
            $user->is_active = $data->is_active;

            if ($user->update()) {
                // Log de l'action
                $log_query = "INSERT INTO user_activity_logs (user_id, company_id, action, module, entity_type, entity_id, description, ip_address)
                              VALUES (:user_id, :company_id, 'update_user', 'users', 'user', :entity_id, :description, :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_description = "Utilisateur modifié (ID: {$data->user_id})";
                $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':company_id', $_SESSION['company_id']);
                $log_stmt->bindParam(':entity_id', $data->user_id);
                $log_stmt->bindParam(':description', $log_description);
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bindParam(':ip', $ip);
                $log_stmt->execute();

                echo json_encode(['success' => true, 'message' => 'Utilisateur mis à jour']);
            } else {
                throw new Exception('Erreur lors de la mise à jour de l\'utilisateur');
            }
            break;

        case 'delete':
            // Vérifier la permission
            if (!PermissionHelper::hasPermission($db, 'users.delete')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission refusée']);
                exit;
            }

            // Valider les données
            if (empty($data->user_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
                exit;
            }

            // Ne pas permettre de se supprimer soi-même
            if ($data->user_id == $_SESSION['user_id']) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Vous ne pouvez pas vous supprimer vous-même']);
                exit;
            }

            // Supprimer l'utilisateur
            $user = new User($db);
            $user->id = $data->user_id;

            if ($user->delete()) {
                // Log de l'action
                $log_query = "INSERT INTO user_activity_logs (user_id, company_id, action, module, entity_type, entity_id, description, ip_address)
                              VALUES (:user_id, :company_id, 'delete_user', 'users', 'user', :entity_id, :description, :ip)";
                $log_stmt = $db->prepare($log_query);
                $log_description = "Utilisateur supprimé (ID: {$data->user_id})";
                $log_stmt->bindParam(':user_id', $_SESSION['user_id']);
                $log_stmt->bindParam(':company_id', $_SESSION['company_id']);
                $log_stmt->bindParam(':entity_id', $data->user_id);
                $log_stmt->bindParam(':description', $log_description);
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_stmt->bindParam(':ip', $ip);
                $log_stmt->execute();

                echo json_encode(['success' => true, 'message' => 'Utilisateur supprimé']);
            } else {
                throw new Exception('Erreur lors de la suppression de l\'utilisateur');
            }
            break;

        case 'get':
            // Vérifier la permission
            if (!PermissionHelper::hasPermission($db, 'users.view')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission refusée']);
                exit;
            }

            if (empty($data->user_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
                exit;
            }

            $user = new User($db);
            $user_data = $user->readById($data->user_id);

            if ($user_data) {
                echo json_encode(['success' => true, 'data' => $user_data]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
            }
            break;

        case 'get_permissions':
            // Vérifier la permission
            if (!PermissionHelper::isAdmin($db)) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Permission refusée']);
                exit;
            }

            if (empty($data->user_id)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID utilisateur requis']);
                exit;
            }

            $user = new User($db);
            $permissions = $user->getUserPermissions($data->user_id);

            echo json_encode(['success' => true, 'permissions' => $permissions]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
    }
} catch (Exception $e) {
    error_log("Erreur gestion utilisateurs: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>
