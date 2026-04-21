<?php
require 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "=== Tables dans la base de données ===\n\n";

$stmt = $conn->query('SHOW TABLES');
$tables = [];
while($row = $stmt->fetch(PDO::FETCH_NUM)) {
    $tables[] = $row[0];
    echo "- " . $row[0] . "\n";
}

echo "\n=== Recherche de tables 'product' ===\n";
$product_tables = array_filter($tables, function($t) {
    return stripos($t, 'product') !== false;
});

if (empty($product_tables)) {
    echo "❌ Aucune table 'product' trouvée\n";
} else {
    echo "✓ Tables trouvées:\n";
    foreach ($product_tables as $table) {
        echo "  - $table\n";
    }
}

echo "\n=== Recherche de tables 'stock' ===\n";
$stock_tables = array_filter($tables, function($t) {
    return stripos($t, 'stock') !== false;
});

if (empty($stock_tables)) {
    echo "❌ Aucune table 'stock' trouvée\n";
} else {
    echo "✓ Tables trouvées:\n";
    foreach ($stock_tables as $table) {
        echo "  - $table\n";
    }
}
?>
