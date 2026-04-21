<?php
/**
 * Script de test pour vérifier l'import de différents formats
 */

require_once 'vendor/autoload.php';

echo "=== Test de lecture des formats ===\n\n";

// Test 1: CSV/TXT avec tabulation
echo "1. Test CSV/TXT (tabulation):\n";
$file = 'plan_comptable_exemple.csv';
$handle = fopen($file, 'r');
$header = fgetcsv($handle, 1000, "\t");
echo "   En-tête: " . implode(' | ', $header) . "\n";
$first_row = fgetcsv($handle, 1000, "\t");
echo "   Première ligne: " . implode(' | ', $first_row) . "\n";
fclose($handle);
echo "   ✓ CSV/TXT OK\n\n";

// Test 2: Excel XLSX
echo "2. Test Excel XLSX:\n";
try {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('plan_comptable_exemple.xlsx');
    $worksheet = $spreadsheet->getActiveSheet();
    $data_rows = $worksheet->toArray();

    echo "   Nombre de lignes: " . count($data_rows) . "\n";
    echo "   En-tête: " . implode(' | ', $data_rows[0]) . "\n";
    echo "   Première ligne: " . implode(' | ', $data_rows[1]) . "\n";
    echo "   ✓ Excel XLSX OK\n\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n\n";
}

// Test 3: Vérifier la structure des colonnes
echo "3. Vérification structure:\n";
$expected_columns = ['numéro', 'intitulé', 'catégorie', 'type'];
$header_normalized = array_map('strtolower', array_map('trim', $data_rows[0]));

foreach ($expected_columns as $col) {
    if (in_array($col, $header_normalized)) {
        echo "   ✓ Colonne '$col' trouvée\n";
    } else {
        echo "   ✗ Colonne '$col' manquante\n";
    }
}

echo "\n=== Tous les tests terminés ===\n";
?>
