<?php
/**
 * Script de test direct: Importer le plan comptable avec hiérarchie
 */

require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$company_id = 2; // Company de test

echo "=== IMPORT DIRECT DU PLAN COMPTABLE ===\n\n";

// Lire le fichier CSV
$file = __DIR__ . '/Plan comptable.csv';
$handle = fopen($file, 'r');

// Détecter le séparateur
$first_line = fgets($handle);
rewind($handle);

$delimiter = "\t";
if (strpos($first_line, ';') !== false) {
    $delimiter = ';';
    echo "Séparateur détecté: point-virgule (;)\n\n";
} else {
    echo "Séparateur détecté: tabulation (\\t)\n\n";
}

$data_rows = [];
while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
    $data_rows[] = $row;
}
fclose($handle);

// Retirer l'en-tête
$header = array_shift($data_rows);
echo "En-tête: " . implode(' | ', $header) . "\n";
echo "Nombre de lignes de données: " . count($data_rows) . "\n\n";

// Supprimer les anciens comptes
echo "Suppression des anciens comptes...\n";
$stmt = $db->prepare("DELETE FROM accounting_plan WHERE company_id = :company_id AND is_used = 0");
$stmt->execute([':company_id' => $company_id]);
echo "  ✓ " . $stmt->rowCount() . " comptes supprimés\n\n";

// Import
echo "Import des comptes...\n";

$imported = 0;
$parent_ids = [];

$db->beginTransaction();

try {
    foreach ($data_rows as $line_num => $data) {
        if (empty(array_filter($data))) continue;
        if (count($data) < 4) continue;

        $number = trim($data[0] ?? '');
        $name = trim($data[1] ?? '');
        $category = strtolower(trim($data[2] ?? ''));
        $type = strtolower(trim($data[3] ?? ''));

        if (empty($number) || empty($name)) continue;

        // Normaliser
        $category_map = [
            'actif' => 'actif',
            'passif' => 'passif',
            'charge' => 'charge',
            'produit' => 'produit',
            'salaires' => 'charge',
            'salaire' => 'charge',
            'produits' => 'produit', // Pluriel
            'charges' => 'charge',  // Pluriel
            'charges d\'exploitation' => 'charge',
            'produits d\'exploitation' => 'produit',
            'charges hors exploitation' => 'charge',
            'produits hors exploitation' => 'produit',
            'charges de personnel' => 'charge',
            'clotûre' => 'passif', // Les comptes de clôture vont au passif
            'cloture' => 'passif'
        ];
        $original_category = $category;
        $category = $category_map[$category] ?? $category;

        // Vérifier si la catégorie est valide
        if (!in_array($category, ['actif', 'passif', 'charge', 'produit'])) {
            echo "  ⚠️  Ligne " . ($line_num + 2) . ": Catégorie invalide '$original_category' (normalisée: '$category') pour compte $number - $name\n";
            continue;
        }

        $type_map = [
            'bilan' => 'bilan',
            'résultat' => 'resultat',
            'resultat' => 'resultat',
            'résultats' => 'resultat',
            'resultats' => 'resultat',
            'salaire' => 'resultat',
            'charges d\'exploitation' => 'resultat',
            'produits d\'exploitation' => 'resultat',
            'charges hors exploitation' => 'resultat',
            'produits hors exploitation' => 'resultat',
            'clotûre' => 'resultat',
            'cloture' => 'resultat'
        ];
        $original_type = $type;
        $type = $type_map[$type] ?? $type;

        // Vérifier si le type est valide
        if (!in_array($type, ['bilan', 'resultat'])) {
            echo "  ⚠️  Ligne " . ($line_num + 2) . ": Type invalide '$original_type' (normalisé: '$type') pour compte $number - $name\n";
            continue;
        }

        // Hiérarchie
        $number_length = strlen($number);
        $level = 'compte';
        $parent_id = null;
        $is_selectable = 1;

        if ($number_length == 1) {
            $level = 'section';
            $is_selectable = 0;
        } elseif ($number_length == 2) {
            $level = 'groupe';
            $is_selectable = 0;
            $parent_number = substr($number, 0, 1);
            $parent_id = $parent_ids[$parent_number] ?? null;
        } elseif ($number_length == 3) {
            $level = 'sous-groupe';
            $is_selectable = 0;
            $parent_number = substr($number, 0, 2);
            $parent_id = $parent_ids[$parent_number] ?? null;
        } else {
            $level = 'compte';
            $is_selectable = 1;
            $parent_number = substr($number, 0, 3);
            $parent_id = $parent_ids[$parent_number] ?? null;
        }

        // Section
        $first_digit = substr($number, 0, 1);
        if ($first_digit == '1') $section = 'actif';
        elseif ($first_digit == '2') $section = 'passif';
        elseif ($first_digit == '3' || $first_digit == '4') $section = 'produits';
        elseif ($first_digit == '5' || $first_digit == '6') $section = 'charges';
        elseif ($first_digit == '7') $section = 'salaires';
        elseif ($first_digit == '8') $section = 'charges_hors_exploitation';
        elseif ($first_digit == '9') $section = 'cloture';
        else $section = null;

        // Insert
        $stmt = $db->prepare("INSERT INTO accounting_plan
            (company_id, number, name, category, type, level, parent_id, is_selectable, sort_order, section, is_used)
            VALUES
            (:company_id, :number, :name, :category, :type, :level, :parent_id, :is_selectable, :sort_order, :section, 0)");

        $stmt->execute([
            ':company_id' => $company_id,
            ':number' => $number,
            ':name' => $name,
            ':category' => $category,
            ':type' => $type,
            ':level' => $level,
            ':parent_id' => $parent_id,
            ':is_selectable' => $is_selectable,
            ':sort_order' => ($line_num + 1) * 10,
            ':section' => $section
        ]);

        $parent_ids[$number] = $db->lastInsertId();
        $imported++;

        if ($imported % 50 == 0) {
            echo "  ... $imported comptes importés\n";
        }
    }

    $db->commit();
    echo "  ✓ Total: $imported comptes importés\n\n";

} catch (Exception $e) {
    $db->rollBack();
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}

// Stats
echo "=== STATISTIQUES ===\n\n";

$stmt = $db->query("SELECT level, COUNT(*) as count
                    FROM accounting_plan
                    WHERE company_id = $company_id
                    GROUP BY level");

echo "Répartition par niveau:\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("  %-15s : %d\n", ucfirst($row['level']), $row['count']);
}

echo "\n";

$stmt = $db->query("SELECT COUNT(*) as count
                    FROM accounting_plan
                    WHERE company_id = $company_id AND is_selectable = 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Comptes sélectionnables: " . $row['count'] . "\n\n";

$stmt = $db->query("SELECT section, COUNT(*) as count
                    FROM accounting_plan
                    WHERE company_id = $company_id
                    GROUP BY section");

echo "Répartition par section:\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("  %-30s : %d\n", ucfirst($row['section'] ?? 'Non défini'), $row['count']);
}

echo "\n✅ Import terminé avec succès!\n";
?>
