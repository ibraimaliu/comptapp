<?php
/**
 * Script pour ajouter les tables manquantes aux bases de données tenants existantes
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== AJOUT DES TABLES MANQUANTES AUX TENANTS EXISTANTS ===\n\n";

try {
    require_once 'config/database_master.php';

    $database = new DatabaseMaster();
    $db = $database->getConnection();

    // Récupérer tous les tenants
    $query = "SELECT id, tenant_code, database_name, company_name FROM tenants WHERE status IN ('active', 'trial')";
    $stmt = $db->query($query);
    $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    echo "Tenants trouvés: " . count($tenants) . "\n\n";

    $sql_to_add = "
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('draft','sent','paid','cancelled') DEFAULT 'draft',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_company_id` (`company_id`),
  INDEX `idx_contact_id` (`contact_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quotes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `quote_number` varchar(50) NOT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `date` date NOT NULL,
  `valid_until` date DEFAULT NULL,
  `status` enum('draft','sent','accepted','refused','expired','converted') DEFAULT 'draft',
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_company_id` (`company_id`),
  INDEX `idx_contact_id` (`contact_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_company_id` (`company_id`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
";

    // Parser le SQL ligne par ligne
    $lines = explode("\n", $sql_to_add);
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

    // Appliquer les modifications à chaque tenant
    foreach ($tenants as $tenant) {
        echo "Traitement: [{$tenant['tenant_code']}] {$tenant['company_name']}\n";
        echo "  Base: {$tenant['database_name']}\n";

        try {
            // Se connecter à la base du tenant
            $tenant_conn = new PDO(
                "mysql:host=localhost;dbname={$tenant['database_name']}",
                "root",
                "Abil",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );

            $added = 0;
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    try {
                        $stmt = $tenant_conn->query($statement);
                        if ($stmt) {
                            $stmt->closeCursor();
                        }
                        $added++;
                    } catch (PDOException $e) {
                        if (strpos($e->getMessage(), 'already exists') === false) {
                            echo "  ⚠ Erreur: " . $e->getMessage() . "\n";
                        }
                    }
                }
            }

            echo "  ✓ {$added} table(s) ajoutée(s)\n\n";

        } catch (PDOException $e) {
            echo "  ✗ ERREUR connexion: " . $e->getMessage() . "\n\n";
        }
    }

    echo "\n=== TERMINÉ ===\n";

} catch (Exception $e) {
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
}
?>
