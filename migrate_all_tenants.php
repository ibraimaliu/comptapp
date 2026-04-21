<?php
/**
 * Script pour migrer toutes les bases de données tenant
 * Ajoute les nouveaux champs à la table companies de tous les tenants
 */

require_once 'config/database_master.php';

// Définir les constantes si elles n'existent pas
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', 'Abil');
}

echo "=== Migration de toutes les bases de données tenant ===\n\n";

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

if (count($tenants) == 0) {
    echo "⚠️  Aucun tenant trouvé.\n";
    exit;
}

echo "📊 Nombre de tenants à migrer: " . count($tenants) . "\n\n";

// Liste des colonnes à ajouter
$columns = [
    'address' => "VARCHAR(255) DEFAULT NULL AFTER owner_surname",
    'postal_code' => "VARCHAR(20) DEFAULT NULL AFTER address",
    'city' => "VARCHAR(100) DEFAULT NULL AFTER postal_code",
    'country' => "VARCHAR(100) DEFAULT 'Suisse' AFTER city",
    'phone' => "VARCHAR(50) DEFAULT NULL AFTER country",
    'email' => "VARCHAR(255) DEFAULT NULL AFTER phone",
    'website' => "VARCHAR(255) DEFAULT NULL AFTER email",
    'ide_number' => "VARCHAR(50) DEFAULT NULL AFTER website",
    'tva_number' => "VARCHAR(50) DEFAULT NULL AFTER ide_number",
    'rc_number' => "VARCHAR(50) DEFAULT NULL AFTER tva_number",
    'bank_name' => "VARCHAR(255) DEFAULT NULL AFTER rc_number",
    'iban' => "VARCHAR(34) DEFAULT NULL AFTER bank_name",
    'bic' => "VARCHAR(11) DEFAULT NULL AFTER iban"
];

$total_success = 0;
$total_skip = 0;
$total_errors = 0;
$tenant_migrated = 0;

// Migrer chaque tenant
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
                $check_query = "SHOW COLUMNS FROM companies LIKE :column_name";
                $check_stmt = $db_tenant->prepare($check_query);
                $check_stmt->bindParam(':column_name', $column_name);
                $check_stmt->execute();

                if ($check_stmt->rowCount() > 0) {
                    $tenant_skip++;
                    continue;
                }

                // Ajouter la colonne
                $alter_query = "ALTER TABLE companies ADD COLUMN $column_name $column_definition";
                $db_tenant->exec($alter_query);

                $tenant_success++;

            } catch(PDOException $e) {
                echo "  ❌ Erreur '$column_name': " . $e->getMessage() . "\n";
                $total_errors++;
            }
        }

        echo "  ✅ Colonnes ajoutées: $tenant_success\n";
        echo "  ⏭️  Colonnes ignorées: $tenant_skip\n";

        $total_success += $tenant_success;
        $total_skip += $tenant_skip;
        $tenant_migrated++;

    } catch(PDOException $e) {
        echo "  ❌ Erreur de connexion: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "=== RÉSUMÉ GLOBAL ===\n";
echo "═══════════════════════════════════════════════════════\n";
echo "🏢 Tenants migrés: $tenant_migrated / " . count($tenants) . "\n";
echo "✅ Colonnes ajoutées: $total_success\n";
echo "⏭️  Colonnes ignorées: $total_skip\n";
echo "❌ Erreurs: $total_errors\n";

if ($tenant_migrated == count($tenants) && $total_errors == 0) {
    echo "\n✅ Migration globale terminée avec succès!\n";
} else {
    echo "\n⚠️  Migration terminée avec quelques problèmes.\n";
}
?>
