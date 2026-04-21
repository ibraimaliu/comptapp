<?php
/**
 * Script pour initialiser les bases de données des tenants existants
 * Crée toutes les tables manquantes dans chaque base tenant
 */

require_once 'config/database_master.php';

// Définir les constantes si elles n'existent pas
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', 'Abil');
}

echo "=== Initialisation des bases de données tenant ===\n\n";

// Connexion à la base master
$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();

if (!$db_master) {
    die("❌ Erreur: Impossible de se connecter à la base master.\n");
}

// Récupérer tous les tenants actifs
$query = "SELECT tenant_code, database_name, company_name, db_host, db_username, db_password
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

echo "📊 Nombre de tenants à initialiser: " . count($tenants) . "\n\n";

// Lire le fichier SQL de création des tables
$create_tables_sql = file_get_contents(__DIR__ . '/CREATE_TENANT_TABLES.sql');
$roles_permissions_sql = file_get_contents(__DIR__ . '/INSERT_ROLES_PERMISSIONS.sql');

$total_success = 0;
$total_errors = 0;

// Initialiser chaque tenant
foreach ($tenants as $tenant) {
    $tenant_code = $tenant['tenant_code'];
    $database_name = $tenant['database_name'];
    $company_name = $tenant['company_name'];
    $db_host = $tenant['db_host'] ?? 'localhost';
    $db_username = $tenant['db_username'] ?? 'root';
    $db_password = $tenant['db_password'] ?? 'Abil';

    echo "───────────────────────────────────────────────────────\n";
    echo "🏢 Tenant: $company_name ($tenant_code)\n";
    echo "💾 Base: $database_name\n";
    echo "───────────────────────────────────────────────────────\n";

    try {
        // Connexion à la base de données tenant
        $dsn = "mysql:host=$db_host;dbname=$database_name;charset=utf8mb4";
        $db_tenant = new PDO($dsn, $db_username, $db_password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]);

        // 1. Créer les tables principales
        echo "  📝 Création des tables...\n";

        // Parser le SQL en statements individuels
        $lines = explode("\n", $create_tables_sql);
        $current_statement = '';
        $statements = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Ignorer les commentaires et lignes vides
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }

            $current_statement .= ' ' . $line;

            // Si la ligne se termine par ;, c'est la fin d'un statement
            if (substr($line, -1) === ';') {
                $statements[] = trim($current_statement);
                $current_statement = '';
            }
        }

        // Exécuter les commandes SQL
        $tables_created = 0;
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $stmt = $db_tenant->query($statement);
                    if ($stmt) {
                        $stmt->closeCursor();
                    }
                    $tables_created++;
                } catch (PDOException $e) {
                    // Ignorer les erreurs "already exists"
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate') === false) {
                        echo "    ⚠️  Avertissement SQL: " . substr($e->getMessage(), 0, 80) . "...\n";
                    }
                }
            }
        }

        echo "  ✅ Tables créées/vérifiées: $tables_created commandes\n";

        // 2. Insérer les rôles et permissions
        echo "  👥 Insertion des rôles et permissions...\n";

        // Parser le SQL des rôles/permissions
        $lines = explode("\n", $roles_permissions_sql);
        $current_statement = '';
        $statements = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }
            $current_statement .= ' ' . $line;
            if (substr($line, -1) === ';') {
                $statements[] = trim($current_statement);
                $current_statement = '';
            }
        }

        $roles_inserted = 0;
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $stmt = $db_tenant->query($statement);
                    if ($stmt) {
                        $stmt->closeCursor();
                    }
                    $roles_inserted++;
                } catch (PDOException $e) {
                    // Ignorer les erreurs de doublons
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        echo "    ⚠️  Avertissement rôles: " . substr($e->getMessage(), 0, 80) . "...\n";
                    }
                }
            }
        }

        echo "  ✅ Rôles/permissions: $roles_inserted commandes\n";

        // 3. Vérifier si un utilisateur admin existe
        $check_admin = $db_tenant->query("SELECT COUNT(*) as count FROM users WHERE role_id = (SELECT id FROM roles WHERE name = 'admin' LIMIT 1)");
        $admin_result = $check_admin->fetch(PDO::FETCH_ASSOC);
        $check_admin->closeCursor();

        if ($admin_result['count'] == 0) {
            echo "  ⚠️  Aucun utilisateur admin trouvé pour ce tenant\n";
        } else {
            echo "  ✅ Utilisateur admin: " . $admin_result['count'] . " trouvé(s)\n";
        }

        // 4. Vérifier que la table companies existe maintenant
        $check_companies = $db_tenant->query("SHOW TABLES LIKE 'companies'");
        $companies_exists = $check_companies->rowCount() > 0;
        $check_companies->closeCursor();

        if ($companies_exists) {
            echo "  ✅ Table companies: OK\n";
        } else {
            echo "  ❌ Table companies: MANQUANTE\n";
            $total_errors++;
        }

        $total_success++;

    } catch(PDOException $e) {
        echo "  ❌ Erreur: " . $e->getMessage() . "\n";
        $total_errors++;
    }

    echo "\n";
}

echo "═══════════════════════════════════════════════════════\n";
echo "=== RÉSUMÉ GLOBAL ===\n";
echo "═══════════════════════════════════════════════════════\n";
echo "✅ Tenants initialisés: $total_success / " . count($tenants) . "\n";
echo "❌ Erreurs: $total_errors\n";

if ($total_success == count($tenants) && $total_errors == 0) {
    echo "\n✅ Initialisation globale terminée avec succès!\n";
    echo "\nℹ️  Vous pouvez maintenant exécuter migrate_all_tenants.php pour ajouter les colonnes manquantes.\n";
} else {
    echo "\n⚠️  Initialisation terminée avec quelques problèmes.\n";
}
?>
