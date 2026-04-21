<?php
/**
 * Script de réparation : Créer les bases de données tenant manquantes
 */

require_once 'config/database_master.php';

echo "=== Réparation des Bases de Données Tenant ===\n\n";

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASSWORD', 'Abil');

$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();

// Récupérer tous les tenants
$query = "SELECT id, tenant_code, company_name, database_name, contact_email
          FROM tenants
          WHERE status IN ('active', 'trial')
          ORDER BY created_at";

$stmt = $db_master->query($query);
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Tenants à vérifier: " . count($tenants) . "\n\n";

$repaired = 0;
$already_exists = 0;
$errors = 0;

foreach ($tenants as $tenant) {
    $db_name = $tenant['database_name'] ?? $tenant['tenant_code'];

    echo "─────────────────────────────────────────\n";
    echo "Tenant: {$tenant['company_name']}\n";
    echo "Code: {$tenant['tenant_code']}\n";
    echo "Base: $db_name\n";

    // Vérifier si la base existe
    $check_query = "SHOW DATABASES LIKE '$db_name'";
    $check_stmt = $db_master->query($check_query);
    $exists = $check_stmt->rowCount() > 0;

    if ($exists) {
        echo "✓ Base de données existe déjà\n";
        $already_exists++;
    } else {
        echo "➜ Création de la base de données...\n";

        try {
            // Créer la base
            $db_master->exec("CREATE DATABASE IF NOT EXISTS `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            // Se connecter à la nouvelle base
            $dsn = "mysql:host=" . DB_HOST . ";dbname=$db_name;charset=utf8mb4";
            $db_tenant = new PDO($dsn, DB_USER, DB_PASSWORD, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Lire le fichier SQL d'installation
            $sql_file = __DIR__ . '/CREATE_TENANT_TABLES_V2.sql';

            if (file_exists($sql_file)) {
                $sql = file_get_contents($sql_file);

                // Exécuter le SQL
                $db_tenant->exec($sql);

                echo "✓ Tables créées avec succès\n";

                // Créer l'utilisateur initial si email fourni
                if (!empty($tenant['contact_email'])) {
                    $username = explode('@', $tenant['contact_email'])[0];
                    $password_hash = password_hash('password123', PASSWORD_BCRYPT);

                    $user_query = "INSERT INTO users (username, email, password, role, created_at)
                                   VALUES (:username, :email, :password, 'admin', NOW())
                                   ON DUPLICATE KEY UPDATE email = email";

                    $user_stmt = $db_tenant->prepare($user_query);
                    $user_stmt->execute([
                        ':username' => $username,
                        ':email' => $tenant['contact_email'],
                        ':password' => $password_hash
                    ]);

                    echo "✓ Utilisateur créé: $username / password123\n";
                }

                // Mettre à jour database_name dans tenants si nécessaire
                if (empty($tenant['database_name'])) {
                    $update_query = "UPDATE tenants SET database_name = :db_name WHERE id = :id";
                    $update_stmt = $db_master->prepare($update_query);
                    $update_stmt->execute([
                        ':db_name' => $db_name,
                        ':id' => $tenant['id']
                    ]);
                }

                $repaired++;
                echo "✅ RÉPARÉ\n";

            } else {
                echo "❌ Fichier SQL introuvable: $sql_file\n";
                $errors++;
            }

        } catch (PDOException $e) {
            echo "❌ Erreur: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    echo "\n";
}

echo "═══════════════════════════════════════════\n";
echo "RÉSUMÉ:\n";
echo "═══════════════════════════════════════════\n";
echo "Bases déjà existantes: $already_exists\n";
echo "Bases réparées: $repaired\n";
echo "Erreurs: $errors\n";
echo "\n";

if ($repaired > 0) {
    echo "✅ $repaired base(s) de données créée(s) avec succès!\n";
    echo "\n";
    echo "Vous pouvez maintenant vous connecter avec:\n";
    echo "URL: http://localhost/gestion_comptable/login_tenant.php\n";
    echo "Email: [votre email d'inscription]\n";
    echo "Mot de passe: password123 (à changer)\n";
}

echo "\n✅ Réparation terminée!\n";
?>
