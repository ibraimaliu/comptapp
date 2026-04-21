<?php
/**
 * Script de test: Importer le plan comptable avec hiérarchie
 */

session_name('COMPTAPP_SESSION');
session_start();

// Simuler une session utilisateur
$_SESSION['user_id'] = 1;
$_SESSION['company_id'] = 2; // Company avec 113 comptes

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "=== TEST IMPORTATION PLAN COMPTABLE HIÉRARCHIQUE ===\n\n";

// Supprimer les comptes existants de la company 2 non utilisés
echo "1. Suppression des comptes non utilisés de la société 2...\n";
$delete_query = "DELETE FROM accounting_plan WHERE company_id = 2 AND is_used = 0";
$stmt = $db->prepare($delete_query);
$stmt->execute();
echo "   ✓ " . $stmt->rowCount() . " comptes supprimés\n\n";

// Simuler l'upload du fichier
echo "2. Simulation de l'upload et importation...\n";

$_FILES['csv_file'] = [
    'name' => 'Plan comptable.csv',
    'tmp_name' => __DIR__ . '/Plan comptable.csv',
    'error' => UPLOAD_ERR_OK,
    'size' => filesize(__DIR__ . '/Plan comptable.csv')
];

$_POST['import_action'] = 'replace';
$_POST['action'] = 'import_csv';
$_GET['action'] = 'import_csv';

// Capturer la sortie
ob_start();
include 'assets/ajax/accounting_plan_import.php';
$output = ob_get_clean();

echo $output . "\n\n";

// Vérifier les résultats
echo "3. Vérification des résultats...\n\n";

$check_query = "SELECT level, COUNT(*) as count
                FROM accounting_plan
                WHERE company_id = 2
                GROUP BY level
                ORDER BY
                    CASE level
                        WHEN 'section' THEN 1
                        WHEN 'groupe' THEN 2
                        WHEN 'sous-groupe' THEN 3
                        WHEN 'compte' THEN 4
                    END";
$stmt = $db->prepare($check_query);
$stmt->execute();

echo "Répartition par niveau:\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("   %-15s : %d\n", ucfirst($row['level']), $row['count']);
}

echo "\n";

// Vérifier les comptes sélectionnables
$selectable_query = "SELECT COUNT(*) as count
                     FROM accounting_plan
                     WHERE company_id = 2 AND is_selectable = 1";
$stmt = $db->prepare($selectable_query);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Comptes sélectionnables: " . $row['count'] . "\n\n";

// Afficher quelques exemples de hiérarchie
echo "4. Exemples de hiérarchie:\n\n";

$hierarchy_query = "SELECT
                        a1.number,
                        a1.name,
                        a1.level,
                        a1.is_selectable,
                        a1.section,
                        a2.number as parent_number,
                        a2.name as parent_name
                    FROM accounting_plan a1
                    LEFT JOIN accounting_plan a2 ON a1.parent_id = a2.id
                    WHERE a1.company_id = 2
                    ORDER BY a1.number
                    LIMIT 30";

$stmt = $db->prepare($hierarchy_query);
$stmt->execute();

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $indent = str_repeat('  ', strlen($row['number']) - 1);
    $selectable = $row['is_selectable'] ? '✓' : '✗';
    $parent_info = $row['parent_number'] ? " [parent: {$row['parent_number']}]" : "";

    echo sprintf("%s[%s] %-8s %-50s %-15s %s%s\n",
        $indent,
        $selectable,
        $row['number'],
        substr($row['name'], 0, 50),
        $row['level'],
        $row['section'] ?? '',
        $parent_info
    );
}

echo "\n✅ Test terminé!\n";
?>
