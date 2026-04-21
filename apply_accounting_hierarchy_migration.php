<?php
/**
 * Script de migration: Ajouter la structure hiérarchique au plan comptable
 * Date: 2025-11-15
 */

require_once 'config/database.php';

echo "=== MIGRATION: Structure hiérarchique du plan comptable ===\n\n";

$database = new Database();
$db = $database->getConnection();

try {
    $db->beginTransaction();

    echo "1. Vérification de l'existence des colonnes...\n";

    // Vérifier si les colonnes existent déjà
    $stmt = $db->query("SHOW COLUMNS FROM accounting_plan LIKE 'level'");
    $column_exists = $stmt->rowCount() > 0;

    if ($column_exists) {
        echo "   ⚠️  Les colonnes existent déjà. Migration déjà appliquée.\n";
        $db->rollBack();
        exit(0);
    }

    echo "   ✓ Les colonnes n'existent pas encore.\n\n";

    echo "2. Ajout des nouveaux champs...\n";

    // Ajouter level
    $db->exec("ALTER TABLE `accounting_plan`
               ADD COLUMN `level` ENUM('section', 'groupe', 'sous-groupe', 'compte')
               NOT NULL DEFAULT 'compte' AFTER `type`");
    echo "   ✓ Colonne 'level' ajoutée\n";

    // Ajouter parent_id
    $db->exec("ALTER TABLE `accounting_plan`
               ADD COLUMN `parent_id` INT(11) NULL DEFAULT NULL AFTER `level`");
    echo "   ✓ Colonne 'parent_id' ajoutée\n";

    // Ajouter is_selectable
    $db->exec("ALTER TABLE `accounting_plan`
               ADD COLUMN `is_selectable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `parent_id`");
    echo "   ✓ Colonne 'is_selectable' ajoutée\n";

    // Ajouter sort_order
    $db->exec("ALTER TABLE `accounting_plan`
               ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0 AFTER `is_selectable`");
    echo "   ✓ Colonne 'sort_order' ajoutée\n";

    // Ajouter section
    $db->exec("ALTER TABLE `accounting_plan`
               ADD COLUMN `section` ENUM('actif', 'passif', 'produits', 'charges', 'salaires', 'charges_hors_exploitation', 'cloture')
               NULL DEFAULT NULL AFTER `sort_order`");
    echo "   ✓ Colonne 'section' ajoutée\n\n";

    echo "3. Ajout des index...\n";

    $db->exec("ALTER TABLE `accounting_plan` ADD INDEX `idx_parent_id` (`parent_id`)");
    echo "   ✓ Index 'idx_parent_id' créé\n";

    $db->exec("ALTER TABLE `accounting_plan` ADD INDEX `idx_level` (`level`)");
    echo "   ✓ Index 'idx_level' créé\n";

    $db->exec("ALTER TABLE `accounting_plan` ADD INDEX `idx_section` (`section`)");
    echo "   ✓ Index 'idx_section' créé\n";

    $db->exec("ALTER TABLE `accounting_plan` ADD INDEX `idx_sort_order` (`sort_order`)");
    echo "   ✓ Index 'idx_sort_order' créé\n\n";

    echo "4. Ajout de la contrainte de clé étrangère...\n";

    $db->exec("ALTER TABLE `accounting_plan`
               ADD CONSTRAINT `fk_accounting_plan_parent`
               FOREIGN KEY (`parent_id`) REFERENCES `accounting_plan` (`id`)
               ON DELETE SET NULL
               ON UPDATE CASCADE");
    echo "   ✓ Contrainte 'fk_accounting_plan_parent' ajoutée\n\n";

    echo "5. Mise à jour des données existantes...\n";

    // Mettre à jour les comptes existants
    $db->exec("UPDATE `accounting_plan`
               SET `is_selectable` = 1,
                   `level` = 'compte'
               WHERE `level` = 'compte'");
    echo "   ✓ Comptes existants marqués comme sélectionnables\n";

    // Ajouter les sections basées sur les catégories
    $db->exec("UPDATE `accounting_plan`
               SET `section` = CASE
                   WHEN `category` = 'actif' THEN 'actif'
                   WHEN `category` = 'passif' THEN 'passif'
                   WHEN `category` = 'produit' THEN 'produits'
                   WHEN `category` = 'charge' THEN 'charges'
                   ELSE NULL
               END
               WHERE `section` IS NULL");
    echo "   ✓ Sections assignées aux comptes existants\n\n";

    // Générer les sort_order basés sur le numéro de compte
    $stmt = $db->query("SELECT id, number FROM accounting_plan ORDER BY company_id, number");
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $update_stmt = $db->prepare("UPDATE accounting_plan SET sort_order = :sort_order WHERE id = :id");
    foreach ($accounts as $index => $account) {
        $update_stmt->execute([
            ':sort_order' => ($index + 1) * 10,
            ':id' => $account['id']
        ]);
    }
    echo "   ✓ Ordre de tri (sort_order) généré\n\n";

    $db->commit();

    echo "✅ MIGRATION TERMINÉE AVEC SUCCÈS!\n\n";

    // Afficher la nouvelle structure
    echo "=== NOUVELLE STRUCTURE ===\n\n";
    $stmt = $db->query('DESCRIBE accounting_plan');
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo sprintf("%-25s %-50s %-10s %s\n",
            $row['Field'],
            $row['Type'],
            ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL'),
            $row['Default'] ?? ''
        );
    }

} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ ERREUR: " . $e->getMessage() . "\n";
    echo "La migration a été annulée (rollback).\n";
    exit(1);
}
?>
