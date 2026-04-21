<?php
require_once 'config/database_master.php';

$db = (new DatabaseMaster())->getConnection();
$stmt = $db->query('DESCRIBE subscription_plans');

echo "Colonnes de la table subscription_plans:\n";
echo "========================================\n";

while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $row['Field'] . ' - ' . $row['Type'] . "\n";
}

echo "\n\nDonnées des plans:\n";
echo "==================\n";

$stmt = $db->query('SELECT * FROM subscription_plans');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
    echo "\n";
}
?>
