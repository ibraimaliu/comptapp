<?php
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== STRUCTURE TABLE accounting_plan ===\n\n";
$stmt = $conn->query('DESCRIBE accounting_plan');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-25s %-30s %-10s %s\n",
        $row['Field'],
        $row['Type'],
        ($row['Null'] === 'YES' ? 'NULL' : 'NOT NULL'),
        $row['Default'] ?? ''
    );
}

echo "\n\n=== COMPTE DES COMPTES PAR SOCIÉTÉ ===\n\n";
$stmt = $conn->query('SELECT company_id, COUNT(*) as count FROM accounting_plan GROUP BY company_id');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "Company ID {$row['company_id']}: {$row['count']} comptes\n";
}
?>
