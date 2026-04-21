<?php
echo "=== Vérification des longueurs dans le CSV ===\n\n";

$file = 'plan_comptable_exemple.csv';
$handle = fopen($file, 'r');

// Skip header
$header = fgetcsv($handle, 1000, "\t");
echo "En-tête: " . implode(' | ', $header) . "\n\n";

$line = 2;
while (($data = fgetcsv($handle, 1000, "\t")) !== false) {
    if (count($data) >= 2) {
        $number = $data[0];
        $name = $data[1];
        $len = strlen($name);

        if ($len > 100) {
            echo "⚠️  Ligne $line: TROP LONG ($len caractères)\n";
            echo "    Numéro: $number\n";
            echo "    Nom: $name\n\n";
        } elseif ($len > 80) {
            echo "⚡ Ligne $line: Proche de la limite ($len caractères)\n";
            echo "    Numéro: $number\n";
            echo "    Nom: $name\n\n";
        }
    }
    $line++;
}

fclose($handle);

echo "\n=== Structure base de données ===\n";
require 'config/database.php';
$db = new Database();
$conn = $db->getConnection();

$stmt = $conn->query("SHOW CREATE TABLE accounting_plan");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo $row['Create Table'] . "\n";
?>
