<?php
/**
 * Script pour ajouter les tables du module Dashboard de Trésorerie
 * dans toutes les bases de données tenant
 */

require_once 'config/database_master.php';

// Définir les constantes si elles n'existent pas
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', 'Abil');
}

echo "=== Ajout des tables du module Dashboard de Trésorerie ===\n\n";

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

// SQL des tables à créer
$tables_sql = [
    'treasury_forecasts' => "CREATE TABLE IF NOT EXISTS `treasury_forecasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `forecast_date` date NOT NULL,
  `expected_income` decimal(10,2) DEFAULT 0.00,
  `expected_expenses` decimal(10,2) DEFAULT 0.00,
  `actual_income` decimal(10,2) DEFAULT 0.00,
  `actual_expenses` decimal(10,2) DEFAULT 0.00,
  `opening_balance` decimal(10,2) DEFAULT 0.00,
  `closing_balance` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_actual` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_date` (`company_id`, `forecast_date`),
  KEY `idx_treasury_company` (`company_id`),
  KEY `idx_treasury_date` (`forecast_date`),
  KEY `idx_forecasts_is_actual` (`is_actual`),
  CONSTRAINT `fk_treasury_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'treasury_alerts' => "CREATE TABLE IF NOT EXISTS `treasury_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `alert_type` enum('low_balance','negative_forecast','overdue_invoices','large_expense') NOT NULL,
  `alert_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `threshold_amount` decimal(10,2) DEFAULT NULL,
  `actual_amount` decimal(10,2) DEFAULT NULL,
  `forecast_date` date DEFAULT NULL,
  `severity` enum('info','warning','critical') DEFAULT 'warning',
  `message` text NOT NULL,
  `status` enum('active','resolved','ignored') DEFAULT 'active',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alerts_company` (`company_id`),
  KEY `idx_alerts_status` (`status`),
  KEY `idx_alerts_type` (`alert_type`),
  KEY `idx_alerts_date` (`alert_date`),
  KEY `idx_alerts_severity` (`severity`),
  CONSTRAINT `fk_alerts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alerts_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'treasury_settings' => "CREATE TABLE IF NOT EXISTS `treasury_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `min_balance_alert` decimal(10,2) DEFAULT 5000.00,
  `critical_balance_alert` decimal(10,2) DEFAULT 1000.00,
  `forecast_horizon_days` int(11) DEFAULT 90,
  `alert_email_enabled` tinyint(1) DEFAULT 1,
  `alert_email_recipients` text DEFAULT NULL,
  `working_capital_target` decimal(10,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_treasury_company` (`company_id`),
  CONSTRAINT `fk_treasury_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'treasury_scenarios' => "CREATE TABLE IF NOT EXISTS `treasury_scenarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `scenario_name` varchar(100) NOT NULL,
  `scenario_type` enum('pessimistic','realistic','optimistic','custom') DEFAULT 'realistic',
  `income_adjustment_percent` decimal(5,2) DEFAULT 0.00,
  `expense_adjustment_percent` decimal(5,2) DEFAULT 0.00,
  `payment_delay_days` int(11) DEFAULT 0,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scenarios_company` (`company_id`),
  CONSTRAINT `fk_scenarios_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

// Vues SQL
$views_sql = [
    'v_treasury_summary' => "CREATE OR REPLACE VIEW v_treasury_summary AS
SELECT
  tf.company_id,
  tf.forecast_date,
  tf.expected_income,
  tf.expected_expenses,
  tf.actual_income,
  tf.actual_expenses,
  tf.opening_balance,
  tf.closing_balance,
  (tf.expected_income - tf.expected_expenses) AS net_forecast,
  (tf.actual_income - tf.actual_expenses) AS net_actual,
  tf.is_actual,
  CASE
    WHEN tf.closing_balance < 0 THEN 'negative'
    WHEN tf.closing_balance < 1000 THEN 'critical'
    WHEN tf.closing_balance < 5000 THEN 'warning'
    ELSE 'healthy'
  END AS status
FROM treasury_forecasts tf
ORDER BY tf.forecast_date",

    'v_active_treasury_alerts' => "CREATE OR REPLACE VIEW v_active_treasury_alerts AS
SELECT
  ta.id,
  ta.company_id,
  c.name AS company_name,
  ta.alert_type,
  ta.alert_date,
  ta.threshold_amount,
  ta.actual_amount,
  ta.forecast_date,
  ta.severity,
  ta.message,
  ta.status,
  DATEDIFF(NOW(), ta.alert_date) AS days_active
FROM treasury_alerts ta
INNER JOIN companies c ON ta.company_id = c.id
WHERE ta.status = 'active'
ORDER BY
  FIELD(ta.severity, 'critical', 'warning', 'info'),
  ta.alert_date DESC"
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
        $views_created = 0;

        // Créer les tables
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

        // Créer les vues
        foreach ($views_sql as $view_name => $sql) {
            try {
                $db_tenant->exec($sql);
                echo "  ✅ Vue '$view_name' créée\n";
                $views_created++;
            } catch (PDOException $e) {
                echo "  ⚠️  Vue '$view_name': " . substr($e->getMessage(), 0, 80) . "...\n";
            }
        }

        if ($tables_created > 0 || $tables_skipped > 0 || $views_created > 0) {
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
