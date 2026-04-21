<?php
session_name('COMPTAPP_SESSION');
session_start();

require 'config/database.php';
require 'models/AccountingPlan.php';

$database = new Database();
$db = $database->getConnection();

echo "=== Test Récupération des Comptes ===\n\n";

if(!isset($_SESSION['company_id'])) {
    echo "❌ Aucune société en session\n";
    exit;
}

echo "Company ID: " . $_SESSION['company_id'] . "\n\n";

$ap = new AccountingPlan($db);
$stmt = $ap->readByCompany($_SESSION['company_id']);

$count = 0;
echo "Comptes disponibles:\n";
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $count++;
    echo "$count. [{$row['number']}] {$row['name']} - {$row['category']}/{$row['type']}\n";
}

echo "\n✓ Total: $count comptes\n";
?>
