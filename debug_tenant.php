<?php
/**
 * Script de debug pour vérifier les informations tenant
 */
session_name('COMPTAPP_SESSION');
session_start();

echo "<h1>Debug Informations Tenant</h1>";
echo "<pre>";
echo "=== SESSION ===\n";
print_r($_SESSION);

echo "\n\n=== VERIFICATION TENANT ===\n";

if (isset($_SESSION['tenant_code'])) {
    echo "✓ tenant_code défini: " . $_SESSION['tenant_code'] . "\n";
} else {
    echo "❌ tenant_code NON défini\n";
}

if (isset($_SESSION['tenant_database'])) {
    echo "✓ tenant_database défini: " . $_SESSION['tenant_database'] . "\n";
} else {
    echo "❌ tenant_database NON défini\n";
}

// Vérifier dans la base master
if (isset($_SESSION['tenant_code']) || isset($_SESSION['tenant_database'])) {
    require_once 'config/database_master.php';

    $database_master = new DatabaseMaster();
    $db_master = $database_master->getConnection();

    $search_term = $_SESSION['tenant_code'] ?? $_SESSION['tenant_database'];

    $query = "SELECT t.id, t.tenant_code, t.database_name, t.subscription_plan, sp.plan_name, sp.max_companies
              FROM tenants t
              LEFT JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
              WHERE t.tenant_code = :search OR t.database_name = :search
              LIMIT 1";

    $stmt = $db_master->prepare($query);
    $stmt->bindParam(':search', $search_term);
    $stmt->execute();
    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n=== RECHERCHE DANS BASE MASTER ===\n";
    echo "Terme de recherche: $search_term\n\n";

    if ($tenant) {
        echo "✓ TENANT TROUVÉ !\n";
        echo "ID: " . $tenant['id'] . "\n";
        echo "Code: " . $tenant['tenant_code'] . "\n";
        echo "Database: " . $tenant['database_name'] . "\n";
        echo "Plan: " . $tenant['subscription_plan'] . "\n";
        echo "Nom du plan: " . $tenant['plan_name'] . "\n";
        echo "Max sociétés: " . $tenant['max_companies'] . "\n";
    } else {
        echo "❌ TENANT NON TROUVÉ dans la base master !\n";
        echo "Vérifiez que le tenant existe dans la table 'tenants'\n";
    }
}

echo "</pre>";

echo "<hr>";
echo "<a href='index.php'>← Retour à l'accueil</a>";
?>
