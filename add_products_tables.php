<?php
/**
 * Script pour ajouter les tables du module Produits/Stock
 * dans toutes les bases de données tenant
 */

require_once 'config/database_master.php';

// Définir les constantes si elles n'existent pas
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', 'Abil');
}

echo "=== Ajout des tables du module Produits/Stock ===\n\n";

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

// SQL des tables à créer (extrait de CREATE_TENANT_TABLES.sql)
$tables_sql = [
    'products' => "CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `type` enum('product','service','bundle') NOT NULL DEFAULT 'product',
  `category_id` int(11) DEFAULT NULL,
  `purchase_price` decimal(10,2) DEFAULT 0.00,
  `selling_price` decimal(10,2) NOT NULL,
  `tva_rate` decimal(5,2) NOT NULL DEFAULT 7.70,
  `currency` varchar(3) NOT NULL DEFAULT 'CHF',
  `stock_quantity` decimal(10,2) DEFAULT 0.00,
  `stock_min` decimal(10,2) DEFAULT 0.00,
  `stock_max` decimal(10,2) DEFAULT NULL,
  `unit` varchar(20) DEFAULT 'pce',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `is_sellable` tinyint(1) NOT NULL DEFAULT 1,
  `is_purchasable` tinyint(1) NOT NULL DEFAULT 1,
  `track_stock` tinyint(1) NOT NULL DEFAULT 1,
  `supplier_id` int(11) DEFAULT NULL,
  `barcode` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_code` (`company_id`, `code`),
  INDEX `idx_products_company` (`company_id`),
  INDEX `idx_products_category` (`category_id`),
  INDEX `idx_products_supplier` (`supplier_id`),
  INDEX `idx_products_barcode` (`barcode`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`supplier_id`) REFERENCES `contacts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'stock_movements' => "CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` enum('in','out','adjustment','transfer','return') NOT NULL,
  `quantity` decimal(10,2) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `total_cost` decimal(10,2) DEFAULT 0.00,
  `reference_type` enum('purchase','sale','invoice','supplier_invoice','manual') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_stock_movements_company` (`company_id`),
  INDEX `idx_stock_movements_product` (`product_id`),
  INDEX `idx_stock_movements_date` (`movement_date`),
  INDEX `idx_stock_movements_type` (`type`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_suppliers' => "CREATE TABLE IF NOT EXISTS `product_suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `supplier_code` varchar(50) DEFAULT NULL,
  `purchase_price` decimal(10,2) NOT NULL,
  `min_order_qty` decimal(10,2) DEFAULT 1.00,
  `delivery_time_days` int(11) DEFAULT NULL,
  `is_preferred` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_product_supplier` (`product_id`, `supplier_id`),
  INDEX `idx_product_suppliers_product` (`product_id`),
  INDEX `idx_product_suppliers_supplier` (`supplier_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`supplier_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'stock_alerts' => "CREATE TABLE IF NOT EXISTS `stock_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `alert_type` enum('low_stock','out_of_stock','overstock','expiring') NOT NULL,
  `alert_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `quantity` decimal(10,2) NOT NULL,
  `threshold` decimal(10,2) DEFAULT NULL,
  `status` enum('active','resolved','ignored') DEFAULT 'active',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_stock_alerts_company` (`company_id`),
  INDEX `idx_stock_alerts_product` (`product_id`),
  INDEX `idx_stock_alerts_status` (`status`),
  INDEX `idx_stock_alerts_date` (`alert_date`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

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
        $tables_skipped = 0;

        foreach ($tables_sql as $table_name => $sql) {
            try {
                // Vérifier si la table existe
                $check = $db_tenant->query("SHOW TABLES LIKE '$table_name'");
                if ($check->rowCount() > 0) {
                    echo "  ⏭️  Table '$table_name' existe déjà\n";
                    $tables_skipped++;
                    continue;
                }

                // Créer la table
                $db_tenant->exec($sql);
                echo "  ✅ Table '$table_name' créée\n";
                $tables_created++;

            } catch (PDOException $e) {
                echo "  ⚠️  Table '$table_name': " . substr($e->getMessage(), 0, 80) . "...\n";
            }
        }

        if ($tables_created > 0 || $tables_skipped > 0) {
            $total_success++;
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
