<?php
/**
 * Script pour augmenter la taille de la colonne 'name' dans accounting_plan
 * De VARCHAR(100) à VARCHAR(255)
 */

require 'config/database.php';

echo "=== Modification de la table accounting_plan ===\n\n";

$db = new Database();
$conn = $db->getConnection();

try {
    // Afficher l'ancienne structure
    echo "Ancienne structure:\n";
    $stmt = $conn->query('DESCRIBE accounting_plan');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'name') {
            echo "  name: " . $row['Type'] . "\n\n";
        }
    }

    // Modifier la colonne
    echo "Modification de la colonne 'name' à VARCHAR(255)...\n";
    $conn->exec("ALTER TABLE accounting_plan MODIFY COLUMN name VARCHAR(255) NOT NULL");
    echo "✓ Modification réussie!\n\n";

    // Afficher la nouvelle structure
    echo "Nouvelle structure:\n";
    $stmt = $conn->query('DESCRIBE accounting_plan');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] === 'name') {
            echo "  name: " . $row['Type'] . "\n\n";
        }
    }

    echo "=== Opération terminée avec succès ===\n";

} catch (PDOException $e) {
    echo "Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
