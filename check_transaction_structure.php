<?php
require 'config/database.php';

$database = new Database();
$db = $database->getConnection();

echo "=== Structure Table Transactions ===\n\n";

$stmt = $db->query("DESCRIBE transactions");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf("%-20s %-30s %s\n",
        $row['Field'],
        $row['Type'],
        $row['Null'] === 'YES' ? 'NULL' : 'NOT NULL'
    );
}
?>
