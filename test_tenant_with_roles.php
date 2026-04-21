<?php
/**
 * Script de test pour vérifier la création d'un tenant avec le système de rôles
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST CRÉATION TENANT AVEC SYSTÈME DE RÔLES ===\n\n";

try {
    echo "1. Connexion à la base master...\n";
    require_once 'config/database_master.php';
    require_once 'models/Tenant.php';

    $database = new DatabaseMaster();
    $db = $database->getConnection();

    if (!$db) {
        die("ERREUR: Connexion échouée\n");
    }
    echo "   ✓ Connexion OK\n\n";

    // Données de test
    $test_email = 'test_roles_' . time() . '@example.com';

    echo "2. Récupération du plan...\n";
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE plan_code = :plan LIMIT 1");
    $subscription_plan = 'free';
    $stmt->bindParam(':plan', $subscription_plan);
    $stmt->execute();
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (!$plan) {
        die("ERREUR: Plan introuvable\n");
    }
    echo "   ✓ Plan trouvé: {$plan['plan_name']}\n\n";

    echo "3. Configuration du tenant...\n";
    $tenant = new Tenant($db);
    $tenant->company_name = "Test Company Roles " . time();
    $tenant->contact_name = "Test User Roles";
    $tenant->contact_email = $test_email;
    $tenant->contact_phone = "0123456789";
    $tenant->address = "123 Test Street";
    $tenant->subscription_plan = $subscription_plan;
    $tenant->max_users = $plan['max_users'];
    $tenant->max_companies = $plan['max_companies'];
    $tenant->max_transactions_per_month = $plan['max_transactions_per_month'];
    $tenant->max_storage_mb = $plan['max_storage_mb'];
    echo "   ✓ Configuration OK\n\n";

    echo "4. Création du tenant...\n";
    $tenant->create();
    echo "   ✓ Tenant créé avec succès!\n\n";

    echo "=== RÉSULTAT ===\n";
    echo "Tenant Code: {$tenant->tenant_code}\n";
    echo "Database: {$tenant->database_name}\n";
    echo "Temp Password: {$tenant->temp_password}\n\n";

    echo "5. Vérification de la base de données tenant...\n";
    $tenant_conn = new PDO(
        "mysql:host=localhost;dbname={$tenant->database_name}",
        "root",
        "Abil",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Vérifier les rôles
    $stmt = $tenant_conn->query("SELECT * FROM roles");
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    echo "   ✓ Rôles créés: " . count($roles) . "\n";
    foreach ($roles as $role) {
        echo "      - {$role['name']}: {$role['display_name']}\n";
    }

    // Vérifier les permissions
    $stmt = $tenant_conn->query("SELECT COUNT(*) as count FROM permissions");
    $perm_count = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    echo "   ✓ Permissions créées: {$perm_count['count']}\n";

    // Vérifier l'utilisateur admin
    $stmt = $tenant_conn->query("SELECT u.*, r.name as role_name FROM users u LEFT JOIN roles r ON u.role_id = r.id");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    echo "   ✓ Utilisateur créé: {$user['username']} ({$user['email']})\n";
    echo "   ✓ Rôle assigné: {$user['role_name']}\n";
    echo "   ✓ Statut actif: " . ($user['is_active'] ? 'OUI' : 'NON') . "\n";

    // Vérifier les permissions de l'admin
    $stmt = $tenant_conn->query("
        SELECT COUNT(*) as count
        FROM role_permissions rp
        JOIN roles r ON rp.role_id = r.id
        WHERE r.name = 'admin'
    ");
    $admin_perm = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    echo "   ✓ Permissions admin: {$admin_perm['count']}\n\n";

    echo "=== TEST RÉUSSI! ===\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR DÉTECTÉE:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . "\n";
    echo "Ligne: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
