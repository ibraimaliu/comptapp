<?php
/**
 * Script pour ajouter la colonne tva_rate à la table transactions
 * dans toutes les bases de données tenant
 */

require_once 'config/database_master.php';

// Définir les constantes si elles n'existent pas
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', 'Abil');
}

echo "=== Ajout de la colonne tva_rate à la table transactions ===\n\n";

// Connexion à la base master
$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();

if (!$db_master) {
    die("❌ Erreur: Impossible de se connecter à la base master.\n");
}

// Récupérer tous les tenants actifs
$query = "SELECT tenant_code, database_name, company_name
          FROM tenants
          WHERE status IN ('active', 'trial')
          ORDER BY created_at";

$stmt = $db_master->prepare($query);
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

if (count($tenants) == 0) {
    echo "⚠️  Aucun tenant trouvé.\n";
    exit;
}

echo "📊 Nombre de tenants à mettre à jour: " . count($tenants) . "\n\n";

$total_success = 0;
$total_skip = 0;
$total_errors = 0;

// Mettre à jour chaque tenant
foreach ($tenants as $tenant) {
    $tenant_code = $tenant['tenant_code'];
    $database_name = $tenant['database_name'];
    $company_name = $tenant['company_name'];

    echo "───────────────────────────────────────────────────────\n";
    echo "🏢 Tenant: $company_name ($tenant_code)\n";
    echo "💾 Base: $database_name\n";
    echo "───────────────────────────────────────────────────────\n";

    try {
        // Connexion à la base de données tenant
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $database_name . ";charset=utf8mb4";
        $db_tenant = new PDO($dsn, DB_USER, DB_PASSWORD);
        $db_tenant->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Vérifier si la colonne existe déjà
        $check_query = "SHOW COLUMNS FROM transactions LIKE 'tva_rate'";
        $check_stmt = $db_tenant->query($check_query);

        if ($check_stmt->rowCount() > 0) {
            echo "  ⏭️  Colonne 'tva_rate' existe déjà\n";
            $total_skip++;
        } else {
            // Ajouter la colonne
            $alter_query = "ALTER TABLE transactions ADD COLUMN tva_rate decimal(5,2) DEFAULT 0.00 AFTER type";
            $db_tenant->exec($alter_query);

            echo "  ✅ Colonne 'tva_rate' ajoutée\n";
            $total_success++;
        }

    } catch(PDOException $e) {
        echo "  ❌ Erreur: " . $e->getMessage() . "\n";
        $total_errors++;
    }

    echo "\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "=== RÉSUMÉ GLOBAL ===\n";
echo "═══════════════════════════════════════════════════════\n";
echo "✅ Tenants mis à jour: $total_success / " . count($tenants) . "\n";
echo "⏭️  Colonnes déjà existantes: $total_skip\n";
echo "❌ Erreurs: $total_errors\n";

if ($total_success + $total_skip == count($tenants) && $total_errors == 0) {
    echo "\n✅ Migration terminée avec succès!\n";
} else {
    echo "\n⚠️  Migration terminée avec quelques problèmes.\n";
}
?>
