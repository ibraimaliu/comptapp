<?php
require 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== Structure de la table accounting_plan ===\n\n";

$stmt = $conn->query('DESCRIBE accounting_plan');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-20s %-30s %s\n",
        $row['Field'],
        $row['Type'],
        $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
    );
}

echo "\n=== Exemple de données longues ===\n\n";

// Lire le fichier Excel
require_once 'vendor/autoload.php';

$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load('plan_comptable_exemple.xlsx');
$worksheet = $spreadsheet->getActiveSheet();
$data_rows = $worksheet->toArray();

echo "Nombre total de lignes: " . count($data_rows) . "\n\n";

// Afficher les longueurs des noms
array_shift($data_rows); // Remove header
foreach ($data_rows as $idx => $row) {
    if (isset($row[1])) {
        $len = strlen($row[1]);
        if ($len > 50) {
            echo "Ligne " . ($idx + 2) . ": Longueur = $len caractères\n";
            echo "  Nom: " . $row[1] . "\n\n";
        }
    }
}
?>
