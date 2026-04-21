<?php
/**
 * API: Réinitialisation de mot de passe
 * Actions: request_reset, reset_password
 */

header('Content-Type: application/json');

require_once '../config/database_master.php';

// Déterminer l'action
if (isset($_POST['token']) && isset($_POST['password'])) {
    $action = 'reset_password';
} else {
    $action = 'request_reset';
}

$database = new DatabaseMaster();
$db = $database->getConnection();

try {
    if ($action === 'request_reset') {
        // ===== DEMANDE DE RÉINITIALISATION =====
        $email = $_POST['email'] ?? '';

        if (empty($email)) {
            throw new Exception('Email requis');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email invalide');
        }

        // Vérifier si le tenant existe
        $query = "SELECT id, company_name, contact_name FROM tenants WHERE contact_email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenant) {
            // Pour des raisons de sécurité, on retourne toujours succès même si l'email n'existe pas
            echo json_encode([
                'success' => true,
                'message' => 'Si cet email existe, vous recevrez un lien de réinitialisation.'
            ]);
            exit;
        }

        // Créer la table password_resets si elle n'existe pas
        $create_table = "CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `tenant_id` INT(11) NOT NULL,
            `token` VARCHAR(64) NOT NULL UNIQUE,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `tenant_id` (`tenant_id`),
            KEY `token` (`token`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $db->exec($create_table);

        // Générer un token unique
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

        // Marquer les anciens tokens comme utilisés
        $invalidate = "UPDATE password_resets SET used = 1 WHERE tenant_id = :tenant_id AND used = 0";
        $stmt = $db->prepare($invalidate);
        $stmt->bindParam(':tenant_id', $tenant['id']);
        $stmt->execute();

        // Insérer le nouveau token
        $query = "INSERT INTO password_resets (tenant_id, token, expires_at)
                  VALUES (:tenant_id, :token, :expires_at)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':tenant_id', $tenant['id']);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expires_at);
        $stmt->execute();

        // Générer le lien
        $reset_link = "http://localhost/gestion_comptable/reset_password.php?token=" . $token;

        // Envoyer l'email
        require_once '../utils/TenantEmailTemplates.php';
        $emailTemplates = new TenantEmailTemplates();
        $email_sent = $emailTemplates->sendPasswordResetEmail($email, $tenant['company_name'], $reset_link);

        // Logger également dans les logs pour le développement
        error_log("=== RÉINITIALISATION MOT DE PASSE ===");
        error_log("Entreprise: " . $tenant['company_name']);
        error_log("Email: " . $email);
        error_log("Lien: " . $reset_link);
        error_log("Email envoyé: " . ($email_sent ? 'OUI' : 'NON'));

        echo json_encode([
            'success' => true,
            'message' => 'Un lien de réinitialisation a été envoyé à votre email (vérifiez les logs pour le développement).',
            'debug_link' => $reset_link  // À supprimer en production
        ]);

    } else if ($action === 'reset_password') {
        // ===== RÉINITIALISATION DU MOT DE PASSE =====
        $token = $_POST['token'] ?? '';
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        if (empty($token) || empty($password) || empty($password_confirm)) {
            throw new Exception('Tous les champs sont requis');
        }

        if ($password !== $password_confirm) {
            throw new Exception('Les mots de passe ne correspondent pas');
        }

        // Validation du mot de passe
        if (strlen($password) < 8) {
            throw new Exception('Le mot de passe doit contenir au moins 8 caractères');
        }

        if (!preg_match('/[A-Z]/', $password)) {
            throw new Exception('Le mot de passe doit contenir au moins une lettre majuscule');
        }

        if (!preg_match('/[a-z]/', $password)) {
            throw new Exception('Le mot de passe doit contenir au moins une lettre minuscule');
        }

        if (!preg_match('/[0-9]/', $password)) {
            throw new Exception('Le mot de passe doit contenir au moins un chiffre');
        }

        // Vérifier le token
        $query = "SELECT pr.*, t.database_name, t.db_host, t.db_username, t.db_password, t.company_name
                  FROM password_resets pr
                  JOIN tenants t ON pr.tenant_id = t.id
                  WHERE pr.token = :token AND pr.used = 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reset_request) {
            throw new Exception('Token invalide ou déjà utilisé');
        }

        // Vérifier l'expiration
        $expires_at = new DateTime($reset_request['expires_at']);
        $now = new DateTime();

        if ($now > $expires_at) {
            throw new Exception('Ce lien de réinitialisation a expiré');
        }

        // Se connecter à la base de données du tenant
        $tenant_conn = new PDO(
            "mysql:host={$reset_request['db_host']};dbname={$reset_request['database_name']}",
            $reset_request['db_username'],
            $reset_request['db_password']
        );
        $tenant_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Mettre à jour le mot de passe de l'utilisateur principal
        $password_hash = password_hash($password, PASSWORD_BCRYPT);

        // On met à jour le premier utilisateur (l'admin du tenant)
        $update_query = "UPDATE users SET password = :password WHERE id = (SELECT MIN(id) FROM (SELECT id FROM users) as temp)";
        $update_stmt = $tenant_conn->prepare($update_query);
        $update_stmt->bindParam(':password', $password_hash);
        $update_stmt->execute();

        // Marquer le token comme utilisé
        $mark_used = "UPDATE password_resets SET used = 1 WHERE id = :id";
        $stmt = $db->prepare($mark_used);
        $stmt->bindParam(':id', $reset_request['id']);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.'
        ]);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log("Erreur password_reset: " . $e->getMessage());
}
?>
