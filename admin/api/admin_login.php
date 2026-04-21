<?php
/**
 * API: Connexion Administrateur
 */

header('Content-Type: application/json');

session_name('ADMIN_SESSION');
session_start();

require_once '../../config/database_master.php';

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Email et mot de passe requis']);
    exit;
}

try {
    $database = new DatabaseMaster();
    $db = $database->getConnection();

    $query = "SELECT * FROM admin_users WHERE email = :email AND is_active = 1 LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Email ou mot de passe incorrect']);
        exit;
    }

    // Créer la session admin
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_username'] = $admin['username'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['admin_role'] = $admin['role'];

    // Mettre à jour last_login
    $update = $db->prepare("UPDATE admin_users SET last_login_at = NOW() WHERE id = :id");
    $update->execute([':id' => $admin['id']]);

    echo json_encode([
        'success' => true,
        'admin' => [
            'username' => $admin['username'],
            'role' => $admin['role']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion']);
    error_log("Erreur connexion admin: " . $e->getMessage());
}
?>
