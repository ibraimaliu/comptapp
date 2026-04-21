<?php
/**
 * Vérification de la Base Master
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Abil');
define('MASTER_DB_NAME', 'gestion_comptable_master');

try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . MASTER_DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ Connexion à la base master réussie\n\n";

    // Vérifier les tables
    $tables = ['tenants', 'admin_users', 'subscription_plans', 'tenant_subscriptions',
               'audit_logs', 'tenant_usage', 'system_settings'];

    echo "Tables:\n";
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        echo ($stmt->rowCount() > 0 ? "  ✓" : "  ✗") . " $table\n";
    }

    echo "\nStatistiques:\n";
    $stmt = $conn->query("SELECT COUNT(*) as count FROM subscription_plans");
    echo "  Plans: " . $stmt->fetch()['count'] . "\n";

    $stmt = $conn->query("SELECT COUNT(*) as count FROM admin_users");
    echo "  Admins: " . $stmt->fetch()['count'] . "\n";

    echo "\nPlans disponibles:\n";
    $stmt = $conn->query("SELECT plan_code, plan_name, price_monthly FROM subscription_plans ORDER BY display_order");
    while ($plan = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  - {$plan['plan_name']}: {$plan['price_monthly']} CHF/mois\n";
    }

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
