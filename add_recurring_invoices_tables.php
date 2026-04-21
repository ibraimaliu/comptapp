<?php
/**
 * Script pour ajouter les tables du module Factures Récurrentes/Abonnements
 * dans toutes les bases de données tenant
 */

require_once 'config/database_master.php';

// Définir les constantes si elles n'existent pas
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', 'Abil');
}

echo "=== Ajout des tables du module Factures Récurrentes/Abonnements ===\n\n";

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

// Lire le fichier SQL d'installation
$sql_file = __DIR__ . '/install_recurring_invoices.sql';
if (!file_exists($sql_file)) {
    die("❌ Erreur: Fichier SQL introuvable: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);

// Parser le SQL pour extraire les tables et vues
$statements = [];
$current_statement = '';
$lines = explode("\n", $sql_content);

foreach ($lines as $line) {
    // Ignorer les commentaires et lignes vides
    if (trim($line) == '' || substr(trim($line), 0, 2) == '--') {
        continue;
    }

    $current_statement .= $line . "\n";

    // Si on trouve un point-virgule, on a une instruction complète
    if (substr(trim($line), -1) == ';') {
        $stmt_trimmed = trim($current_statement);

        // Exclure certaines instructions
        if (!preg_match('/^(USE |SELECT )/i', $stmt_trimmed)) {
            $statements[] = $stmt_trimmed;
        }

        $current_statement = '';
    }
}

echo "📝 Instructions SQL à exécuter: " . count($statements) . "\n\n";

$total_success = 0;
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
        $db_tenant = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]);

        $tables_created = 0;
        $views_created = 0;
        $indexes_created = 0;
        $errors = 0;

        foreach ($statements as $statement) {
            try {
                // Déterminer le type d'instruction
                if (preg_match('/^CREATE TABLE/i', $statement)) {
                    // Extraire le nom de la table
                    preg_match('/CREATE TABLE[^`]*`([^`]+)`/i', $statement, $matches);
                    $table_name = $matches[1] ?? 'unknown';

                    // Vérifier si la table existe
                    $check = $db_tenant->query("SHOW TABLES LIKE '$table_name'");
                    if ($check->rowCount() > 0) {
                        echo "  ⏭️  Table '$table_name' existe déjà\n";
                        continue;
                    }

                    $db_tenant->exec($statement);
                    echo "  ✅ Table '$table_name' créée\n";
                    $tables_created++;

                } elseif (preg_match('/^CREATE.*VIEW/i', $statement)) {
                    // Extraire le nom de la vue
                    preg_match('/VIEW[^`]*`([^`]+)`/i', $statement, $matches);
                    $view_name = $matches[1] ?? 'unknown';

                    $db_tenant->exec($statement);
                    echo "  ✅ Vue '$view_name' créée\n";
                    $views_created++;

                } elseif (preg_match('/^CREATE INDEX/i', $statement)) {
                    $db_tenant->exec($statement);
                    $indexes_created++;

                } else {
                    // Autres instructions (ALTER, etc.)
                    $db_tenant->exec($statement);
                }

            } catch (PDOException $e) {
                // Ignorer les erreurs de duplication
                if (strpos($e->getMessage(), 'Duplicate') === false &&
                    strpos($e->getMessage(), 'already exists') === false) {
                    echo "  ⚠️  Erreur: " . substr($e->getMessage(), 0, 100) . "...\n";
                    $errors++;
                }
            }
        }

        echo "  📊 Résumé: {$tables_created} tables, {$views_created} vues, {$indexes_created} index créés\n";

        if ($errors == 0) {
            $total_success++;
        } else {
            echo "  ⚠️  $errors erreur(s) rencontrée(s)\n";
            $total_errors++;
        }

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
echo "❌ Erreurs: $total_errors\n";

if ($total_success == count($tenants) && $total_errors == 0) {
    echo "\n✅ Migration terminée avec succès!\n";
} else {
    echo "\n⚠️  Migration terminée avec quelques problèmes.\n";
}
?>
