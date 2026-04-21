<?php
/**
 * Script de diagnostic et correction des sessions tenant
 */

require_once 'config/database_master.php';

echo "=== Diagnostic Session Tenant ===\n\n";

// 1. Lister tous les tenants
$database = new DatabaseMaster();
$db = $database->getConnection();

$query = "SELECT id, tenant_code, company_name, contact_email, status, subscription_plan
          FROM tenants
          ORDER BY created_at DESC";

$stmt = $db->query($query);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Tenants dans la base master:\n";
echo "============================\n\n";

foreach ($tenants as $tenant) {
    echo sprintf(
        "ID: %d | Code: %s | Société: %s\n  Email: %s | Statut: %s | Plan: %s\n\n",
        $tenant['id'],
        $tenant['tenant_code'],
        $tenant['company_name'],
        $tenant['contact_email'],
        $tenant['status'],
        $tenant['subscription_plan']
    );
}

// 2. Vérifier les bases de données tenant
echo "\nBases de données tenant:\n";
echo "========================\n\n";

$db_query = "SHOW DATABASES LIKE 'tenant%'";
$db_stmt = $db->query($db_query);
$databases = $db_stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($databases as $database_name) {
    echo "✓ $database_name\n";
}

// 3. Proposer une solution
echo "\n\n=== Solution ===\n\n";
echo "Si vous ne pouvez pas vous connecter, vérifiez:\n\n";
echo "1. Utilisez la page de connexion multi-tenant:\n";
echo "   URL: http://localhost/gestion_comptable/login_tenant.php\n\n";

echo "2. Ou reconnectez-vous avec l'email utilisé lors de l'inscription\n\n";

// 4. Afficher les utilisateurs par tenant
echo "\n=== Utilisateurs par Tenant ===\n\n";

foreach ($tenants as $tenant) {
    $tenant_code = $tenant['tenant_code'];

    try {
        $dsn = "mysql:host=localhost;dbname=$tenant_code;charset=utf8mb4";
        $db_tenant = new PDO($dsn, 'root', 'Abil', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $user_query = "SELECT id, username, email FROM users LIMIT 5";
        $user_stmt = $db_tenant->query($user_query);
        $users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Tenant: {$tenant['company_name']} ($tenant_code)\n";
        echo "─────────────────────────────────────────\n";

        if (empty($users)) {
            echo "  ⚠️  Aucun utilisateur trouvé!\n\n";
        } else {
            foreach ($users as $user) {
                echo "  • ID: {$user['id']} | Username: {$user['username']} | Email: {$user['email']}\n";
            }
            echo "\n";
        }

    } catch (PDOException $e) {
        echo "  ❌ Erreur: Base de données '$tenant_code' inaccessible\n\n";
    }
}

// 5. Vérifier le plan de chaque tenant
echo "\n=== Vérification des Plans ===\n\n";

$plan_query = "SELECT t.tenant_code, t.company_name, t.subscription_plan, sp.max_companies
               FROM tenants t
               LEFT JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
               ORDER BY t.created_at DESC";

$plan_stmt = $db->query($plan_query);
$plans = $plan_stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($plans as $plan) {
    $max_display = $plan['max_companies'] == -1 ? 'Illimité' : $plan['max_companies'];
    echo sprintf(
        "%-20s | Plan: %-15s | Max sociétés: %s\n",
        $plan['company_name'],
        $plan['subscription_plan'],
        $max_display
    );
}

echo "\n\n✅ Diagnostic terminé!\n";
?>
