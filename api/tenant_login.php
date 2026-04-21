<?php
/**
 * API: Connexion Multi-Tenant
 * Identifie le tenant par email et se connecte à SA base de données
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

session_name('COMPTAPP_SESSION');
session_start();

require_once '../config/database_master.php';
require_once '../models/Tenant.php';

// Récupérer les données POST
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$remember = isset($_POST['remember']) && $_POST['remember'] == '1';

// Validation
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Email et mot de passe requis'
    ]);
    exit;
}

try {
    // 1. Connexion à la base master pour identifier le tenant
    $database = new DatabaseMaster();
    $db_master = $database->getConnection();

    if (!$db_master) {
        throw new Exception('Impossible de se connecter au système');
    }

    // 2. Rechercher le tenant par email
    $query = "SELECT * FROM tenants WHERE contact_email = :email LIMIT 1";
    $stmt = $db_master->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    $tenant_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant_data) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Email ou mot de passe incorrect'
        ]);
        exit;
    }

    // 3. Vérifier le statut du tenant
    if ($tenant_data['status'] === 'suspended') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Votre compte est suspendu. Contactez le support.'
        ]);
        exit;
    }

    if ($tenant_data['status'] === 'cancelled') {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Votre compte a été annulé. Contactez le support pour le réactiver.'
        ]);
        exit;
    }

    // 4. Vérifier la période d'essai
    if ($tenant_data['status'] === 'trial' && $tenant_data['trial_ends_at']) {
        $trial_end = new DateTime($tenant_data['trial_ends_at']);
        $now = new DateTime();

        if ($now > $trial_end) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Votre période d\'essai a expiré. Veuillez souscrire à un abonnement.'
            ]);
            exit;
        }
    }

    // 5. Se connecter à la base de données du tenant
    $tenant_db = new PDO(
        "mysql:host={$tenant_data['db_host']};dbname={$tenant_data['database_name']}",
        $tenant_data['db_username'],
        $tenant_data['db_password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    // 6. Chercher l'utilisateur dans la base du tenant
    $user_query = "SELECT * FROM users WHERE email = :email LIMIT 1";
    $user_stmt = $tenant_db->prepare($user_query);
    $user_stmt->bindParam(':email', $email);
    $user_stmt->execute();

    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Email ou mot de passe incorrect'
        ]);
        exit;
    }

    // 7. Vérifier le mot de passe
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Email ou mot de passe incorrect'
        ]);
        exit;
    }

    // 8. Créer la session multi-tenant
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];

    // IMPORTANT: Stocker les informations du tenant
    $_SESSION['tenant_id'] = $tenant_data['id'];
    $_SESSION['tenant_code'] = $tenant_data['tenant_code'];
    $_SESSION['tenant_name'] = $tenant_data['company_name'];
    $_SESSION['tenant_database'] = $tenant_data['database_name'];
    $_SESSION['tenant_db_host'] = $tenant_data['db_host'];
    $_SESSION['tenant_db_user'] = $tenant_data['db_username'];
    $_SESSION['tenant_db_pass'] = $tenant_data['db_password'];

    // Stocker le plan et les limites
    $_SESSION['subscription_plan'] = $tenant_data['subscription_plan'];
    $_SESSION['max_users'] = $tenant_data['max_users'];
    $_SESSION['max_companies'] = $tenant_data['max_companies'];
    $_SESSION['max_transactions_per_month'] = $tenant_data['max_transactions_per_month'];

    // AUTO-SÉLECTIONNER LA PREMIÈRE SOCIÉTÉ SI L'UTILISATEUR EN A UNE
    $company_query = "SELECT id FROM companies WHERE user_id = :user_id ORDER BY created_at ASC LIMIT 1";
    $company_stmt = $tenant_db->prepare($company_query);
    $company_stmt->bindParam(':user_id', $user['id']);
    $company_stmt->execute();
    $first_company = $company_stmt->fetch(PDO::FETCH_ASSOC);

    if ($first_company) {
        $_SESSION['company_id'] = $first_company['id'];
    }

    // Initialiser l'exercice comptable par défaut à l'année courante
    $_SESSION['fiscal_year'] = date('Y');

    // 9. Si "Se souvenir de moi"
    if ($remember) {
        $cookie_value = base64_encode(json_encode([
            'tenant_id' => $tenant_data['id'],
            'user_id' => $user['id'],
            'token' => bin2hex(random_bytes(32))
        ]));
        setcookie('remember_tenant', $cookie_value, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }

    // 10. Mettre à jour la dernière connexion dans la base master
    $update_query = "UPDATE tenants SET last_login_at = NOW() WHERE id = :tenant_id";
    $update_stmt = $db_master->prepare($update_query);
    $update_stmt->bindParam(':tenant_id', $tenant_data['id']);
    $update_stmt->execute();

    // 11. Logger la connexion dans audit_logs
    $log_query = "INSERT INTO audit_logs
                  (tenant_id, action, details, ip_address, user_agent, created_at)
                  VALUES (:tenant_id, 'login', :details, :ip, :user_agent, NOW())";
    $log_stmt = $db_master->prepare($log_query);
    $log_stmt->bindParam(':tenant_id', $tenant_data['id']);
    $log_stmt->bindValue(':details', json_encode([
        'user_id' => $user['id'],
        'username' => $user['username'],
        'email' => $email
    ]));
    $log_stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? null);
    $log_stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
    $log_stmt->execute();

    // 12. Retourner le succès
    echo json_encode([
        'success' => true,
        'message' => 'Connexion réussie',
        'user' => [
            'username' => $user['username'],
            'email' => $user['email']
        ],
        'tenant' => [
            'name' => $tenant_data['company_name'],
            'code' => $tenant_data['tenant_code'],
            'plan' => $tenant_data['subscription_plan']
        ]
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à votre base de données'
    ]);
    error_log("Erreur connexion tenant: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    error_log("Erreur connexion tenant: " . $e->getMessage());
}
?>
