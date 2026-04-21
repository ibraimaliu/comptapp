<?php
/**
 * Vérifier la structure de la table companies dans une base tenant
 */

$dsn = "mysql:host=localhost;dbname=gestion_comptable_client_9FF4F8B7;charset=utf8mb4";
$db = new PDO($dsn, 'root', 'Abil');

echo "=== Structure de la table companies ===\n\n";

$stmt = $db->query("DESCRIBE companies");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($columns as $column) {
    echo sprintf("%-20s %-30s %s\n",
        $column['Field'],
        $column['Type'],
        $column['Null'] == 'NO' ? 'NOT NULL' : 'NULL'
    );
}

echo "\n✅ Total de colonnes: " . count($columns) . "\n";

// Vérifier spécifiquement les nouvelles colonnes
$new_columns = ['address', 'postal_code', 'city', 'country', 'phone', 'email', 'website',
                'ide_number', 'tva_number', 'rc_number', 'bank_name', 'iban', 'bic'];

echo "\n=== Vérification des nouvelles colonnes ===\n";
$found = 0;
foreach ($new_columns as $col) {
    $exists = false;
    foreach ($columns as $column) {
        if ($column['Field'] === $col) {
            $exists = true;
            $found++;
            break;
        }
    }
    echo ($exists ? "✅" : "❌") . " $col\n";
}

echo "\n📊 Nouvelles colonnes trouvées: $found / " . count($new_columns) . "\n";
?>
