<?php
/**
 * Installation des tables pour la gestion des stocks et produits
 */

require 'config/database.php';

echo "=== Installation Module Gestion des Stocks ===\n\n";

$db = new Database();
$conn = $db->getConnection();

try {
    // Lire le fichier SQL
    $sql = file_get_contents('install_inventory.sql');

    // Séparer les commandes (gérer DELIMITER)
    $sql = str_replace('DELIMITER //', '', $sql);
    $sql = str_replace('DELIMITER ;', '', $sql);

    // Diviser par les triggers
    $statements = [];
    $current = '';
    $in_trigger = false;

    foreach (explode("\n", $sql) as $line) {
        $line = trim($line);

        // Ignorer les commentaires
        if (empty($line) || strpos($line, '--') === 0) {
            continue;
        }

        // Détecter début trigger
        if (stripos($line, 'CREATE TRIGGER') !== false) {
            if ($current) {
                $statements[] = $current;
            }
            $current = '';
            $in_trigger = true;
        }

        $current .= $line . "\n";

        // Fin de statement normal
        if (!$in_trigger && substr($line, -1) === ';') {
            $statements[] = $current;
            $current = '';
        }

        // Fin de trigger
        if ($in_trigger && stripos($line, 'END//') !== false) {
            $current = str_replace('END//', 'END', $current);
            $statements[] = $current;
            $current = '';
            $in_trigger = false;
        }
    }

    if ($current) {
        $statements[] = $current;
    }

    // Exécuter les statements
    $executed = 0;
    $errors = 0;

    foreach ($statements as $idx => $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;

        try {
            $conn->exec($statement);
            $executed++;

            // Afficher le type de commande
            if (stripos($statement, 'CREATE TABLE') !== false) {
                preg_match('/CREATE TABLE.*?`(\w+)`/i', $statement, $matches);
                $table = $matches[1] ?? 'unknown';
                echo "✓ Table créée: $table\n";
            } elseif (stripos($statement, 'CREATE.*VIEW') !== false) {
                preg_match('/CREATE.*?VIEW\s+(\w+)/i', $statement, $matches);
                $view = $matches[1] ?? 'unknown';
                echo "✓ Vue créée: $view\n";
            } elseif (stripos($statement, 'CREATE TRIGGER') !== false) {
                preg_match('/CREATE TRIGGER\s+(\w+)/i', $statement, $matches);
                $trigger = $matches[1] ?? 'unknown';
                echo "✓ Trigger créé: $trigger\n";
            } elseif (stripos($statement, 'CREATE INDEX') !== false) {
                echo "✓ Index créé\n";
            }
        } catch (PDOException $e) {
            $errors++;
            $error_msg = $e->getMessage();

            // Ignorer les erreurs "already exists"
            if (stripos($error_msg, 'already exists') !== false) {
                echo "⊙ Élément déjà existant (ignoré)\n";
            } else {
                echo "✗ Erreur: " . $error_msg . "\n";
                echo "  Statement: " . substr($statement, 0, 100) . "...\n";
            }
        }
    }

    echo "\n=== Résumé ===\n";
    echo "Statements exécutés: $executed\n";
    echo "Erreurs: $errors\n";

    // Vérifier les tables créées
    echo "\n=== Vérification des tables ===\n";
    $check_tables = ['products', 'stock_movements', 'product_suppliers', 'stock_alerts'];

    foreach ($check_tables as $table) {
        $stmt = $conn->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            $count_stmt = $conn->query("SELECT COUNT(*) as count FROM $table");
            $count = $count_stmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo "✓ $table existe ($count lignes)\n";
        } else {
            echo "✗ $table manquante\n";
        }
    }

    echo "\n=== Installation terminée avec succès ===\n";

} catch (Exception $e) {
    echo "ERREUR FATALE: " . $e->getMessage() . "\n";
    exit(1);
}
?>
