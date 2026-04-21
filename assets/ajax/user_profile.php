<?php
/**
 * API: Gestion Profil Utilisateur
 * Description: Modification profil et changement de mot de passe
 * Version: 1.0
 */

header('Content-Type: application/json');
session_name('COMPTAPP_SESSION');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../models/User.php';

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

try {
    switch ($action) {
        case 'get_profile':
            getProfile($db, $user_id);
            break;

        case 'update_profile':
            updateProfile($db, $user_id, $data);
            break;

        case 'change_password':
            changePassword($db, $user_id, $data);
            break;

        default:
            throw new Exception('Action invalide');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Get user profile
 */
function getProfile($db, $user_id) {
    $query = "SELECT id, username, email, created_at
              FROM users
              WHERE id = :user_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Utilisateur non trouvé');
    }

    echo json_encode([
        'success' => true,
        'user' => $user
    ]);
}

/**
 * Update profile
 */
function updateProfile($db, $user_id, $data) {
    $email = trim($data['email'] ?? '');

    if (empty($email)) {
        throw new Exception('Email requis');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email invalide');
    }

    // Check if email is already used by another user
    $check_query = "SELECT id FROM users
                    WHERE email = :email AND id != :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        throw new Exception('Cet email est déjà utilisé');
    }

    // Update user
    $query = "UPDATE users
              SET email = :email
              WHERE id = :user_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':user_id', $user_id);

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Profil mis à jour avec succès'
        ]);
    } else {
        throw new Exception('Erreur lors de la mise à jour');
    }
}

/**
 * Change password
 */
function changePassword($db, $user_id, $data) {
    $current_password = $data['current_password'] ?? '';
    $new_password = $data['new_password'] ?? '';
    $confirm_password = $data['confirm_password'] ?? '';

    // Validate
    if (empty($current_password)) {
        throw new Exception('Mot de passe actuel requis');
    }

    if (empty($new_password)) {
        throw new Exception('Nouveau mot de passe requis');
    }

    if (strlen($new_password) < 8) {
        throw new Exception('Le nouveau mot de passe doit contenir au moins 8 caractères');
    }

    if ($new_password !== $confirm_password) {
        throw new Exception('Les mots de passe ne correspondent pas');
    }

    // Get current password hash
    $query = "SELECT password FROM users WHERE id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Utilisateur non trouvé');
    }

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        throw new Exception('Mot de passe actuel incorrect');
    }

    // Hash new password
    $new_password_hash = password_hash($new_password, PASSWORD_BCRYPT);

    // Update password
    $update_query = "UPDATE users
                     SET password = :password
                     WHERE id = :user_id";

    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':password', $new_password_hash);
    $update_stmt->bindParam(':user_id', $user_id);

    if ($update_stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Mot de passe modifié avec succès'
        ]);
    } else {
        throw new Exception('Erreur lors du changement de mot de passe');
    }
}
?>
