<?php
/**
 * Script pour ajouter les nouveaux champs à la table companies
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "=== Ajout des nouveaux champs à la table companies ===\n\n";

// Liste des colonnes à ajouter
$columns = [
    'address' => "VARCHAR(255) DEFAULT NULL AFTER owner_surname",
    'postal_code' => "VARCHAR(20) DEFAULT NULL AFTER address",
    'city' => "VARCHAR(100) DEFAULT NULL AFTER postal_code",
    'country' => "VARCHAR(100) DEFAULT 'Suisse' AFTER city",
    'phone' => "VARCHAR(50) DEFAULT NULL AFTER country",
    'email' => "VARCHAR(255) DEFAULT NULL AFTER phone",
    'website' => "VARCHAR(255) DEFAULT NULL AFTER email",
    'ide_number' => "VARCHAR(50) DEFAULT NULL AFTER website",
    'tva_number' => "VARCHAR(50) DEFAULT NULL AFTER ide_number",
    'rc_number' => "VARCHAR(50) DEFAULT NULL AFTER tva_number",
    'bank_name' => "VARCHAR(255) DEFAULT NULL AFTER rc_number",
    'iban' => "VARCHAR(34) DEFAULT NULL AFTER bank_name",
    'bic' => "VARCHAR(11) DEFAULT NULL AFTER iban"
];

$success_count = 0;
$skip_count = 0;
$error_count = 0;

foreach ($columns as $column_name => $column_definition) {
    try {
        // Vérifier si la colonne existe déjà
        $check_query = "SHOW COLUMNS FROM companies LIKE :column_name";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':column_name', $column_name);
        $check_stmt->execute();

        if ($check_stmt->rowCount() > 0) {
            echo "⏭️  Colonne '$column_name' existe déjà - ignorée\n";
            $skip_count++;
            continue;
        }

        // Ajouter la colonne
        $alter_query = "ALTER TABLE companies ADD COLUMN $column_name $column_definition";
        $db->exec($alter_query);

        echo "✅ Colonne '$column_name' ajoutée avec succès\n";
        $success_count++;

    } catch(PDOException $e) {
        echo "❌ Erreur lors de l'ajout de '$column_name': " . $e->getMessage() . "\n";
        $error_count++;
    }
}

echo "\n=== Résumé ===\n";
echo "✅ Colonnes ajoutées: $success_count\n";
echo "⏭️  Colonnes ignorées (existantes): $skip_count\n";
echo "❌ Erreurs: $error_count\n";

if ($success_count > 0 || $skip_count > 0) {
    echo "\n✅ Migration terminée avec succès!\n";
} else {
    echo "\n❌ Aucune colonne n'a pu être ajoutée.\n";
}
?>
