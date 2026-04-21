<?php
/**
 * Script d'installation du système de rôles et permissions
 * À exécuter sur chaque base de données tenant
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== INSTALLATION SYSTÈME DE RÔLES ET PERMISSIONS ===\n\n";

// Paramètres de connexion
$host = 'localhost';
$username = 'root';
$password = 'Abil';

// Base de données à traiter (passer en argument ou traiter toutes les bases tenants)
$database_name = $argv[1] ?? null;

if (!$database_name) {
    echo "Usage: php install_roles_and_permissions.php <database_name>\n";
    echo "Exemple: php install_roles_and_permissions.php gestion_comptable_client_9FF4F8B7\n\n";

    // Ou traiter automatiquement toutes les bases tenants
    echo "Voulez-vous installer sur TOUTES les bases tenants? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if(trim($line) != 'y'){
        exit("Installation annulée.\n");
    }

    // Récupérer toutes les bases tenants
    require_once 'config/database_master.php';
    $db_master = new DatabaseMaster();
    $conn_master = $db_master->getConnection();

    $stmt = $conn_master->query("SELECT database_name, company_name, tenant_code FROM tenants WHERE status IN ('active', 'trial')");
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    echo "\nTenants trouvés: " . count($tenants) . "\n\n";

    foreach ($tenants as $tenant) {
        echo "Traitement: [{$tenant['tenant_code']}] {$tenant['company_name']}\n";
        installRolesAndPermissions($host, $username, $password, $tenant['database_name']);
        echo "\n";
    }

    echo "=== TERMINÉ ===\n";
    exit;
}

// Installation sur une base spécifique
installRolesAndPermissions($host, $username, $password, $database_name);

function installRolesAndPermissions($host, $username, $password, $database_name) {
    try {
        $conn = new PDO(
            "mysql:host={$host};dbname={$database_name}",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ]
        );

        echo "  Connexion à {$database_name}...\n";

        // 1. Créer la table roles
        echo "  - Création table roles...\n";
        $conn->exec("
            CREATE TABLE IF NOT EXISTS `roles` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(50) NOT NULL,
              `display_name` varchar(100) NOT NULL,
              `description` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 2. Créer la table permissions
        echo "  - Création table permissions...\n";
        $conn->exec("
            CREATE TABLE IF NOT EXISTS `permissions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `name` varchar(50) NOT NULL,
              `display_name` varchar(100) NOT NULL,
              `description` text DEFAULT NULL,
              `module` varchar(50) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 3. Créer la table role_permissions
        echo "  - Création table role_permissions...\n";
        $conn->exec("
            CREATE TABLE IF NOT EXISTS `role_permissions` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `role_id` int(11) NOT NULL,
              `permission_id` int(11) NOT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
              FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
              FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 4. Modifier la table users (ajouter les colonnes manquantes)
        echo "  - Modification table users...\n";
        try {
            $conn->exec("ALTER TABLE `users` ADD COLUMN `company_id` int(11) DEFAULT NULL AFTER `id`");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "    Avertissement: " . $e->getMessage() . "\n";
            }
        }

        try {
            $conn->exec("ALTER TABLE `users` ADD COLUMN `role_id` int(11) DEFAULT NULL AFTER `password`");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "    Avertissement: " . $e->getMessage() . "\n";
            }
        }

        try {
            $conn->exec("ALTER TABLE `users` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `role_id`");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "    Avertissement: " . $e->getMessage() . "\n";
            }
        }

        try {
            $conn->exec("ALTER TABLE `users` ADD COLUMN `last_login_at` timestamp NULL DEFAULT NULL AFTER `is_active`");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "    Avertissement: " . $e->getMessage() . "\n";
            }
        }

        try {
            $conn->exec("ALTER TABLE `users` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`");
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column') === false) {
                echo "    Avertissement: " . $e->getMessage() . "\n";
            }
        }

        // Ajouter les index et clés étrangères
        try {
            $conn->exec("ALTER TABLE `users` ADD INDEX `idx_company_id` (`company_id`)");
        } catch (PDOException $e) {
            // Index existe déjà
        }

        try {
            $conn->exec("ALTER TABLE `users` ADD INDEX `idx_role_id` (`role_id`)");
        } catch (PDOException $e) {
            // Index existe déjà
        }

        try {
            $conn->exec("ALTER TABLE `users` ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL");
        } catch (PDOException $e) {
            // Contrainte existe déjà
        }

        // 5. Créer les autres tables
        echo "  - Création tables auxiliaires...\n";
        $conn->exec("
            CREATE TABLE IF NOT EXISTS `user_invitations` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `company_id` int(11) NOT NULL,
              `email` varchar(100) NOT NULL,
              `role_id` int(11) NOT NULL,
              `token` varchar(64) NOT NULL UNIQUE,
              `invited_by` int(11) NOT NULL,
              `expires_at` datetime NOT NULL,
              `accepted_at` datetime DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              INDEX `idx_token` (`token`),
              INDEX `idx_email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $conn->exec("
            CREATE TABLE IF NOT EXISTS `user_activity_logs` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `user_id` int(11) NOT NULL,
              `company_id` int(11) NOT NULL,
              `action` varchar(100) NOT NULL,
              `module` varchar(50) DEFAULT NULL,
              `entity_type` varchar(50) DEFAULT NULL,
              `entity_id` int(11) DEFAULT NULL,
              `description` text DEFAULT NULL,
              `ip_address` varchar(45) DEFAULT NULL,
              `user_agent` varchar(255) DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              INDEX `idx_user_id` (`user_id`),
              INDEX `idx_company_id` (`company_id`),
              INDEX `idx_action` (`action`),
              INDEX `idx_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // 6. Insérer les rôles par défaut
        echo "  - Insertion des rôles...\n";
        $roles = [
            ['admin', 'Administrateur', 'Accès complet à toutes les fonctionnalités'],
            ['accountant', 'Comptable', 'Accès aux fonctionnalités comptables et financières'],
            ['reader', 'Lecteur', 'Accès en lecture seule aux données']
        ];

        foreach ($roles as $role) {
            $stmt = $conn->prepare("INSERT IGNORE INTO roles (name, display_name, description) VALUES (?, ?, ?)");
            $stmt->execute($role);
            $stmt->closeCursor();
        }

        // 7. Insérer les permissions
        echo "  - Insertion des permissions...\n";
        $permissions = [
            ['users.view', 'Voir les utilisateurs', 'users', 'Peut voir la liste des utilisateurs'],
            ['users.create', 'Créer des utilisateurs', 'users', 'Peut inviter de nouveaux utilisateurs'],
            ['users.edit', 'Modifier les utilisateurs', 'users', 'Peut modifier les informations des utilisateurs'],
            ['users.delete', 'Supprimer des utilisateurs', 'users', 'Peut supprimer des utilisateurs'],

            ['companies.view', 'Voir les entreprises', 'companies', 'Peut voir les informations de l\'entreprise'],
            ['companies.edit', 'Modifier l\'entreprise', 'companies', 'Peut modifier les informations de l\'entreprise'],

            ['accounting.view', 'Voir la comptabilité', 'accounting', 'Peut consulter les écritures comptables'],
            ['accounting.create', 'Créer des écritures', 'accounting', 'Peut créer des écritures comptables'],
            ['accounting.edit', 'Modifier des écritures', 'accounting', 'Peut modifier des écritures comptables'],
            ['accounting.delete', 'Supprimer des écritures', 'accounting', 'Peut supprimer des écritures comptables'],

            ['chart_of_accounts.view', 'Voir le plan comptable', 'accounting', 'Peut consulter le plan comptable'],
            ['chart_of_accounts.edit', 'Modifier le plan comptable', 'accounting', 'Peut modifier le plan comptable'],

            ['contacts.view', 'Voir les contacts', 'contacts', 'Peut voir la liste des contacts'],
            ['contacts.create', 'Créer des contacts', 'contacts', 'Peut créer de nouveaux contacts'],
            ['contacts.edit', 'Modifier des contacts', 'contacts', 'Peut modifier des contacts'],
            ['contacts.delete', 'Supprimer des contacts', 'contacts', 'Peut supprimer des contacts'],

            ['invoices.view', 'Voir les factures', 'invoices', 'Peut voir la liste des factures'],
            ['invoices.create', 'Créer des factures', 'invoices', 'Peut créer de nouvelles factures'],
            ['invoices.edit', 'Modifier des factures', 'invoices', 'Peut modifier des factures'],
            ['invoices.delete', 'Supprimer des factures', 'invoices', 'Peut supprimer des factures'],
            ['invoices.send', 'Envoyer des factures', 'invoices', 'Peut envoyer des factures par email'],

            ['quotes.view', 'Voir les devis', 'quotes', 'Peut voir la liste des devis'],
            ['quotes.create', 'Créer des devis', 'quotes', 'Peut créer de nouveaux devis'],
            ['quotes.edit', 'Modifier des devis', 'quotes', 'Peut modifier des devis'],
            ['quotes.delete', 'Supprimer des devis', 'quotes', 'Peut supprimer des devis'],

            ['reports.view', 'Voir les rapports', 'reports', 'Peut consulter tous les rapports'],
            ['reports.export', 'Exporter les rapports', 'reports', 'Peut exporter les rapports en PDF/Excel'],

            ['settings.view', 'Voir les paramètres', 'settings', 'Peut voir les paramètres'],
            ['settings.edit', 'Modifier les paramètres', 'settings', 'Peut modifier les paramètres système']
        ];

        foreach ($permissions as $perm) {
            $stmt = $conn->prepare("INSERT IGNORE INTO permissions (name, display_name, module, description) VALUES (?, ?, ?, ?)");
            $stmt->execute($perm);
            $stmt->closeCursor();
        }

        // 8. Assigner les permissions aux rôles
        echo "  - Attribution des permissions aux rôles...\n";

        // ADMIN : Toutes les permissions
        $conn->exec("
            INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id
            FROM roles r, permissions p
            WHERE r.name = 'admin'
        ");

        // COMPTABLE : Permissions de gestion comptable
        $accountant_permissions = [
            'companies.view',
            'accounting.view', 'accounting.create', 'accounting.edit',
            'chart_of_accounts.view',
            'contacts.view', 'contacts.create', 'contacts.edit',
            'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.send',
            'quotes.view', 'quotes.create', 'quotes.edit',
            'reports.view', 'reports.export',
            'settings.view'
        ];

        foreach ($accountant_permissions as $perm_name) {
            try {
                $conn->exec("
                    INSERT IGNORE INTO role_permissions (role_id, permission_id)
                    SELECT r.id, p.id
                    FROM roles r, permissions p
                    WHERE r.name = 'accountant' AND p.name = '{$perm_name}'
                ");
            } catch (PDOException $e) {
                // Ignore duplicates
            }
        }

        // LECTEUR : Permissions de lecture seule
        $reader_permissions = [
            'companies.view',
            'accounting.view',
            'chart_of_accounts.view',
            'contacts.view',
            'invoices.view',
            'quotes.view',
            'reports.view'
        ];

        foreach ($reader_permissions as $perm_name) {
            try {
                $conn->exec("
                    INSERT IGNORE INTO role_permissions (role_id, permission_id)
                    SELECT r.id, p.id
                    FROM roles r, permissions p
                    WHERE r.name = 'reader' AND p.name = '{$perm_name}'
                ");
            } catch (PDOException $e) {
                // Ignore duplicates
            }
        }

        // 9. Assigner le rôle admin au premier utilisateur s'il existe
        echo "  - Attribution du rôle admin au premier utilisateur...\n";
        $stmt = $conn->query("SELECT id FROM users WHERE role_id IS NULL ORDER BY id LIMIT 1");
        $first_user = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($first_user) {
            $stmt = $conn->prepare("UPDATE users SET role_id = (SELECT id FROM roles WHERE name = 'admin' LIMIT 1) WHERE id = ?");
            $stmt->execute([$first_user['id']]);
            $stmt->closeCursor();
            echo "    ✓ Rôle admin attribué à l'utilisateur ID {$first_user['id']}\n";
        }

        echo "  ✓ Installation terminée avec succès!\n";

    } catch (PDOException $e) {
        echo "  ✗ ERREUR: " . $e->getMessage() . "\n";
    }
}
?>
