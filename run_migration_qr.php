<?php
/**
 * Script d'exécution de la migration QR-Invoice
 * Exécuter via navigateur: http://localhost/gestion_comptable/run_migration_qr.php
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Migration QR-Invoice - Base de données</h1>";
echo "<pre>";

// Connexion à la base de données
require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();

    if (!$conn) {
        die("Erreur: Impossible de se connecter à la base de données\n");
    }

    // Activer le buffering des requêtes pour éviter l'erreur 2014
    $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

    echo "✓ Connexion à la base de données réussie\n\n";

    // Lire le fichier SQL
    $sql_file = __DIR__ . '/migrations/add_qr_invoice_fields.sql';

    if (!file_exists($sql_file)) {
        die("Erreur: Fichier de migration introuvable: $sql_file\n");
    }

    $sql_content = file_get_contents($sql_file);

    // Enlever les commentaires et séparer les requêtes
    $sql_lines = explode("\n", $sql_content);
    $sql_commands = [];
    $current_command = '';

    foreach ($sql_lines as $line) {
        $line = trim($line);

        // Ignorer les commentaires
        if (empty($line) || substr($line, 0, 2) == '--') {
            continue;
        }

        $current_command .= $line . ' ';

        // Si la ligne se termine par un point-virgule, c'est la fin d'une commande
        if (substr(trim($line), -1) == ';') {
            $sql_commands[] = trim($current_command);
            $current_command = '';
        }
    }

    echo "📋 " . count($sql_commands) . " commandes SQL à exécuter\n\n";

    // Exécuter chaque commande
    $success_count = 0;
    $error_count = 0;

    foreach ($sql_commands as $index => $command) {
        $command_num = $index + 1;
        echo "[$command_num] Exécution: " . substr($command, 0, 80) . "...\n";

        try {
            // Utiliser exec pour les commandes qui ne retournent pas de résultats
            // ou query avec fetchAll pour celles qui en retournent
            if (stripos($command, 'SELECT') === 0) {
                $stmt = $conn->query($command);
                $stmt->fetchAll(); // Consommer les résultats
            } else {
                $conn->exec($command);
            }
            echo "    ✓ Succès\n";
            $success_count++;
        } catch (PDOException $e) {
            echo "    ✗ Erreur: " . $e->getMessage() . "\n";
            $error_count++;
        }

        echo "\n";
    }

    echo "\n═══════════════════════════════════════\n";
    echo "RÉSUMÉ DE LA MIGRATION\n";
    echo "═══════════════════════════════════════\n";
    echo "✓ Commandes réussies: $success_count\n";
    echo "✗ Commandes échouées: $error_count\n";
    echo "\n";

    if ($error_count == 0) {
        echo "🎉 Migration QR-Invoice terminée avec succès!\n";
    } else {
        echo "⚠️  Migration terminée avec des erreurs\n";
    }

    // Vérifier les nouvelles colonnes
    echo "\n───────────────────────────────────────\n";
    echo "VÉRIFICATION DES MODIFICATIONS\n";
    echo "───────────────────────────────────────\n";

    // Vérifier table companies
    $stmt = $conn->query("DESCRIBE companies");
    $companies_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $qr_fields = ['qr_iban', 'bank_iban', 'address', 'postal_code', 'city', 'country'];
    echo "\nTable 'companies':\n";
    foreach ($qr_fields as $field) {
        $exists = in_array($field, $companies_columns) ? '✓' : '✗';
        echo "  $exists $field\n";
    }

    // Vérifier table invoices
    $stmt = $conn->query("DESCRIBE invoices");
    $invoices_columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $invoice_qr_fields = ['qr_reference', 'payment_method', 'qr_code_path', 'payment_due_date', 'payment_terms'];
    echo "\nTable 'invoices':\n";
    foreach ($invoice_qr_fields as $field) {
        $exists = in_array($field, $invoices_columns) ? '✓' : '✗';
        echo "  $exists $field\n";
    }

    // Vérifier nouvelles tables
    $stmt = $conn->query("SHOW TABLES LIKE 'qr_%'");
    $qr_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "\nNouvelles tables:\n";
    foreach ($qr_tables as $table) {
        echo "  ✓ $table\n";
    }

    echo "\n";

} catch (Exception $e) {
    echo "\n✗ ERREUR FATALE: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
echo "<p><a href='index.php'>← Retour à l'application</a></p>";
?>
