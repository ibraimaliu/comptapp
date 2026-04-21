<?php
/**
 * Ajout du champ counterpart_account_id pour comptabilité double
 */

require 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "=== Ajout Compte de Contrepartie ===\n\n";

try {
    // Vérifier si la colonne existe déjà
    $stmt = $db->query("SHOW COLUMNS FROM transactions LIKE 'counterpart_account_id'");

    if($stmt->rowCount() > 0) {
        echo "⊙ La colonne 'counterpart_account_id' existe déjà\n";
    } else {
        echo "Ajout de la colonne 'counterpart_account_id'...\n";

        $db->exec("ALTER TABLE transactions
                   ADD COLUMN counterpart_account_id INT(11) NULL AFTER account_id,
                   ADD CONSTRAINT fk_transactions_counterpart
                   FOREIGN KEY (counterpart_account_id)
                   REFERENCES accounting_plan(id) ON DELETE SET NULL");

        echo "✓ Colonne ajoutée avec succès\n";
    }

    echo "\n=== Structure mise à jour ===\n";
    $stmt = $db->query("DESCRIBE transactions");
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if(in_array($row['Field'], ['account_id', 'counterpart_account_id'])) {
            echo "✓ {$row['Field']}: {$row['Type']}\n";
        }
    }

} catch(PDOException $e) {
    echo "✗ Erreur: " . $e->getMessage() . "\n";
}
?>
