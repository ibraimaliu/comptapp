<?php
/**
 * Script pour ajouter les colonnes is_used et is_selectable à la table accounting_plan
 * dans toutes les bases de données tenant
 */

require_once 'config/database_master.php';

// Définir les constantes si elles n'existent pas
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', 'Abil');
}

echo "=== Ajout des colonnes à la table accounting_plan ===\n\n";

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

// Liste des colonnes à ajouter
$columns = [
    'is_used' => "tinyint(1) DEFAULT 0 AFTER type",
    'is_selectable' => "tinyint(1) DEFAULT 1 AFTER is_used"
];

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

        $tenant_success = 0;
        $tenant_skip = 0;

        foreach ($columns as $column_name => $column_definition) {
            try {
                // Vérifier si la colonne existe déjà
                $check_query = "SHOW COLUMNS FROM accounting_plan LIKE :column_name";
                $check_stmt = $db_tenant->prepare($check_query);
                $check_stmt->bindParam(':column_name', $column_name);
                $check_stmt->execute();

                if ($check_stmt->rowCount() > 0) {
                    echo "  ⏭️  Colonne '$column_name' existe déjà\n";
                    $tenant_skip++;
                    continue;
                }

                // Ajouter la colonne
                $alter_query = "ALTER TABLE accounting_plan ADD COLUMN $column_name $column_definition";
                $db_tenant->exec($alter_query);

                echo "  ✅ Colonne '$column_name' ajoutée\n";
                $tenant_success++;

            } catch(PDOException $e) {
                echo "  ❌ Erreur '$column_name': " . $e->getMessage() . "\n";
                $total_errors++;
            }
        }

        if ($tenant_success > 0 || $tenant_skip > 0) {
            $total_success++;
        }
        $total_skip += $tenant_skip;

    } catch(PDOException $e) {
        echo "  ❌ Erreur de connexion: " . $e->getMessage() . "\n";
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

if ($total_success == count($tenants) && $total_errors == 0) {
    echo "\n✅ Migration terminée avec succès!\n";
} else {
    echo "\n⚠️  Migration terminée avec quelques problèmes.\n";
}
?>
