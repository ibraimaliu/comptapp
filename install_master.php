<?php
/**
 * Installation de la Base de Données Master Multi-Tenant
 * Date: 2025-11-15
 */

// Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Abil');
define('MASTER_DB_NAME', 'gestion_comptable_master');

echo "=== INSTALLATION BASE DE DONNÉES MASTER ===\n\n";

try {
    // Connexion MySQL sans spécifier de base de données
    $conn = new PDO(
        "mysql:host=" . DB_HOST,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    echo "1. Connexion MySQL établie\n";

    // Créer la base de données master si elle n'existe pas
    $conn->exec("CREATE DATABASE IF NOT EXISTS `" . MASTER_DB_NAME . "` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "2. Base de données master créée ou déjà existante\n";

    // Se connecter à la base master
    $conn->exec("USE " . MASTER_DB_NAME);
    echo "3. Connexion à la base master établie\n";

    // Lire le fichier SQL
    $sql_file = __DIR__ . '/install_master.sql';
    if (!file_exists($sql_file)) {
        throw new Exception("Fichier install_master.sql introuvable");
    }

    $sql = file_get_contents($sql_file);
    echo "4. Fichier SQL chargé\n\n";

    // Exécuter les commandes SQL
    echo "5. Exécution du script SQL...\n";

    // Séparer les commandes SQL
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            // Ignorer les commentaires et lignes vides
            return !empty($stmt) &&
                   !preg_match('/^--/', $stmt) &&
                   !preg_match('/^\/\*/', $stmt);
        }
    );

    $executed = 0;
    $errors = 0;

    foreach ($statements as $statement) {
        try {
            $conn->exec($statement);
            $executed++;

            // Afficher des messages pour les commandes importantes
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*`(\w+)`/i', $statement, $matches);
                if (isset($matches[1])) {
                    echo "   ✓ Table '{$matches[1]}' créée\n";
                }
            } elseif (stripos($statement, 'CREATE DATABASE') !== false) {
                echo "   ✓ Base de données master créée\n";
            }
        } catch (PDOException $e) {
            // Ignorer les erreurs "already exists"
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "   ⚠️  Erreur: " . $e->getMessage() . "\n";
                $errors++;
            }
        }
    }

    echo "\n6. Exécution terminée\n";
    echo "   - Commandes exécutées: $executed\n";
    echo "   - Erreurs: $errors\n\n";

    // Vérifier que tout est OK
    $conn->exec("USE " . MASTER_DB_NAME);

    $tables = [
        'tenants',
        'admin_users',
        'subscription_plans',
        'tenant_subscriptions',
        'audit_logs',
        'tenant_usage',
        'system_settings'
    ];

    echo "7. Vérification des tables créées:\n";
    foreach ($tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "   ✓ $table\n";
        } else {
            echo "   ✗ $table (MANQUANTE!)\n";
        }
    }

    // Afficher les statistiques
    echo "\n8. Statistiques:\n";

    $stmt = $conn->query("SELECT COUNT(*) as count FROM subscription_plans");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   - Plans d'abonnement: {$row['count']}\n";

    $stmt = $conn->query("SELECT COUNT(*) as count FROM admin_users");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   - Administrateurs: {$row['count']}\n";

    $stmt = $conn->query("SELECT COUNT(*) as count FROM system_settings");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "   - Paramètres système: {$row['count']}\n";

    echo "\n✅ INSTALLATION RÉUSSIE!\n\n";

    echo "=== INFORMATIONS IMPORTANTES ===\n\n";
    echo "Base de données master: " . MASTER_DB_NAME . "\n";
    echo "Hôte: " . DB_HOST . "\n\n";

    echo "Compte administrateur par défaut:\n";
    echo "   Username: superadmin\n";
    echo "   Email: admin@gestioncomptable.local\n";
    echo "   Mot de passe: Admin@123\n";
    echo "   ⚠️  IMPORTANT: Changez ce mot de passe immédiatement!\n\n";

    echo "Plans disponibles:\n";
    $stmt = $conn->query("SELECT plan_code, plan_name, price_monthly FROM subscription_plans ORDER BY display_order");
    while ($plan = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "   - {$plan['plan_name']} ({$plan['plan_code']}): {$plan['price_monthly']} CHF/mois\n";
    }

    echo "\n=== PROCHAINES ÉTAPES ===\n\n";
    echo "1. Accédez au panneau d'administration:\n";
    echo "   http://localhost/gestion_comptable/admin/\n\n";
    echo "2. Créez votre premier client (tenant)\n\n";
    echo "3. Le système créera automatiquement une base de données pour chaque client\n\n";

} catch (PDOException $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "Code d'erreur: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
