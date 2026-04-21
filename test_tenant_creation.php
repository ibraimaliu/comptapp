<?php
/**
 * Script de test pour identifier l'erreur unbuffered queries
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST CRÉATION TENANT ===\n\n";

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
    $test_email = 'test_' . time() . '@example.com';

    echo "2. Vérification email existant...\n";
    $tenant = new Tenant($db);
    $existing = $tenant->readByEmail($test_email);
    echo "   ✓ Vérification OK (existing: " . ($existing ? 'OUI' : 'NON') . ")\n\n";

    echo "3. Récupération du plan...\n";
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

    echo "4. Configuration du tenant...\n";
    $tenant->company_name = "Test Company " . time();
    $tenant->contact_name = "Test User";
    $tenant->contact_email = $test_email;
    $tenant->contact_phone = "0123456789";
    $tenant->address = "123 Test Street";
    $tenant->subscription_plan = $subscription_plan;
    $tenant->max_users = $plan['max_users'];
    $tenant->max_companies = $plan['max_companies'];
    $tenant->max_transactions_per_month = $plan['max_transactions_per_month'];
    $tenant->max_storage_mb = $plan['max_storage_mb'];
    echo "   ✓ Configuration OK\n\n";

    echo "5. Création du tenant...\n";
    echo "   (ceci va appeler generateTenantCode, INSERT, commit, logAction, createTenantDatabase)\n";

    $tenant->create();

    echo "   ✓ Tenant créé avec succès!\n\n";

    echo "=== RÉSULTAT ===\n";
    echo "Tenant Code: {$tenant->tenant_code}\n";
    echo "Database: {$tenant->database_name}\n";
    echo "Temp Password: {$tenant->temp_password}\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR DÉTECTÉE:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "Fichier: " . $e->getFile() . "\n";
    echo "Ligne: " . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}
?>
