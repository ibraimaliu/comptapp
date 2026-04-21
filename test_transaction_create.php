<?php
/**
 * Script de test pour diagnostiquer l'erreur de création de transaction
 */

// Activer l'affichage des erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Transaction Create</title></head><body>";
echo "<h1>🧪 Test de Création de Transaction</h1>";
echo "<div style='background: #f0f0f0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";

// Démarrer la session
session_name('COMPTAPP_SESSION');
session_start();

echo "<h2>📋 Étape 1: Vérification de la Session</h2>";
if(!isset($_SESSION['user_id'])) {
    echo "<p style='color: red;'>❌ user_id non défini - Simulation...</p>";
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
    $_SESSION['company_id'] = 1;
    $_SESSION['tenant_code'] = 'client_b1548e8d';
} else {
    echo "<p style='color: green;'>✅ user_id: " . $_SESSION['user_id'] . "</p>";
}

if(!isset($_SESSION['company_id'])) {
    echo "<p style='color: red;'>❌ company_id non défini</p>";
} else {
    echo "<p style='color: green;'>✅ company_id: " . $_SESSION['company_id'] . "</p>";
}

echo "<h2>📋 Étape 2: Inclusion des Fichiers</h2>";

try {
    include_once 'config/database.php';
    echo "<p style='color: green;'>✅ config/database.php chargé</p>";
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Erreur database.php: " . $e->getMessage() . "</p>";
}

try {
    include_once 'models/Transaction.php';
    echo "<p style='color: green;'>✅ models/Transaction.php chargé</p>";
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Erreur Transaction.php: " . $e->getMessage() . "</p>";
}

echo "<h2>📋 Étape 3: Connexion à la Base de Données</h2>";

try {
    $database = new Database();
    $db = $database->getConnection();

    if($db) {
        echo "<p style='color: green;'>✅ Connexion à la base de données réussie</p>";
    } else {
        echo "<p style='color: red;'>❌ Connexion échouée</p>";
    }
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Exception: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<h2>📋 Étape 4: Vérification de la Table transactions</h2>";

try {
    $query = "DESCRIBE transactions";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p style='color: green;'>✅ Table transactions existe</p>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Erreur DESCRIBE: " . $e->getMessage() . "</p>";
}

echo "<h2>📋 Étape 5: Vérification des Comptes Disponibles</h2>";

try {
    $query = "SELECT id, number, name, type FROM accounting_plan
              WHERE company_id = :company_id
              AND is_selectable = 1
              LIMIT 5";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $_SESSION['company_id']);
    $stmt->execute();
    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if(count($accounts) > 0) {
        echo "<p style='color: green;'>✅ " . count($accounts) . " comptes trouvés</p>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Numéro</th><th>Nom</th><th>Type</th></tr>";
        foreach($accounts as $acc) {
            echo "<tr>";
            echo "<td>" . $acc['id'] . "</td>";
            echo "<td>" . htmlspecialchars($acc['number']) . "</td>";
            echo "<td>" . htmlspecialchars($acc['name']) . "</td>";
            echo "<td>" . htmlspecialchars($acc['type']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        $test_account_id = $accounts[0]['id'];
        echo "<p><strong>Compte de test sélectionné: ID " . $test_account_id . " - " . htmlspecialchars($accounts[0]['name']) . "</strong></p>";
    } else {
        echo "<p style='color: red;'>❌ Aucun compte sélectionnable trouvé</p>";
        echo "<p>⚠️ Vous devez d'abord importer un plan comptable!</p>";
        $test_account_id = null;
    }
} catch(Exception $e) {
    echo "<p style='color: red;'>❌ Erreur: " . $e->getMessage() . "</p>";
    $test_account_id = null;
}

if($test_account_id) {
    echo "<h2>📋 Étape 6: Test de Création de Transaction</h2>";

    try {
        $transaction = new Transaction($db);
        $transaction->company_id = $_SESSION['company_id'];
        $transaction->date = date('Y-m-d');
        $transaction->description = 'Test transaction - ' . date('H:i:s');
        $transaction->amount = 100.50;
        $transaction->type = 'expense';
        $transaction->category = 'Test';
        $transaction->tva_rate = 7.7;
        $transaction->account_id = $test_account_id;

        // Vérifier si counterpart_account_id existe
        if(property_exists($transaction, 'counterpart_account_id')) {
            echo "<p style='color: blue;'>ℹ️ La propriété counterpart_account_id existe dans le modèle</p>";
            // Utiliser un autre compte pour la contrepartie
            if(count($accounts) > 1) {
                $transaction->counterpart_account_id = $accounts[1]['id'];
                echo "<p style='color: blue;'>ℹ️ counterpart_account_id défini: " . $accounts[1]['id'] . " - " . htmlspecialchars($accounts[1]['name']) . "</p>";
            }
        }

        echo "<p><strong>Tentative de création...</strong></p>";

        if($transaction->create()) {
            echo "<p style='color: green; font-size: 18px; font-weight: bold;'>✅ SUCCÈS! Transaction créée avec l'ID: " . $transaction->id . "</p>";

            // Lire la transaction créée
            $transaction->read();
            echo "<div style='background: #e8f5e9; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h3>Détails de la transaction:</h3>";
            echo "<ul>";
            echo "<li>ID: " . $transaction->id . "</li>";
            echo "<li>Date: " . $transaction->date . "</li>";
            echo "<li>Description: " . htmlspecialchars($transaction->description) . "</li>";
            echo "<li>Montant: " . number_format($transaction->amount, 2) . " CHF</li>";
            echo "<li>Type: " . $transaction->type . "</li>";
            echo "<li>Compte: " . $transaction->account_id . "</li>";
            if(isset($transaction->counterpart_account_id)) {
                echo "<li>Compte contrepartie: " . $transaction->counterpart_account_id . "</li>";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<p style='color: red; font-size: 18px; font-weight: bold;'>❌ ÉCHEC de la création</p>";

            // Récupérer l'erreur PDO si disponible
            $errorInfo = $db->errorInfo();
            if($errorInfo[0] !== '00000') {
                echo "<p style='color: red;'>Erreur PDO: " . implode(' - ', $errorInfo) . "</p>";
            }
        }

    } catch(Exception $e) {
        echo "<p style='color: red;'>❌ Exception durant la création: " . $e->getMessage() . "</p>";
        echo "<pre style='background: #ffebee; padding: 10px;'>" . $e->getTraceAsString() . "</pre>";
    }
}

echo "<h2>📋 Test de l'API via Simulation POST</h2>";

if($test_account_id && count($accounts) > 1) {
    echo "<div style='background: #fff3e0; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h3>Simulation d'un appel API</h3>";

    // Simuler les données POST
    $test_data = [
        'action' => 'create',
        'date' => date('Y-m-d'),
        'description' => 'Transaction via simulation API',
        'amount' => 250.75,
        'type' => 'income',
        'category' => 'Ventes',
        'tva_rate' => 7.7,
        'account_id' => $test_account_id,
        'counterpart_account_id' => $accounts[1]['id']
    ];

    echo "<p><strong>Données JSON:</strong></p>";
    echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

    echo "<p><strong>URL de l'API:</strong> <code>api/transaction.php</code></p>";

    echo "<button onclick='testAPICall()' style='padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;'>
        🧪 Tester l'API via AJAX
    </button>";

    echo "<div id='api-result' style='margin-top: 20px;'></div>";

    echo "<script>
    function testAPICall() {
        document.getElementById('api-result').innerHTML = '<p>⏳ Envoi de la requête...</p>';

        const data = " . json_encode($test_data) . ";

        fetch('api/transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('Status:', response.status);
            console.log('Headers:', response.headers);
            return response.text();
        })
        .then(text => {
            console.log('Response text:', text);
            document.getElementById('api-result').innerHTML =
                '<div style=\"background: #e3f2fd; padding: 10px; border-radius: 5px;\">' +
                '<h4>Réponse brute du serveur:</h4>' +
                '<pre>' + text + '</pre>' +
                '</div>';

            try {
                const jsonData = JSON.parse(text);
                document.getElementById('api-result').innerHTML +=
                    '<div style=\"background: #e8f5e9; padding: 10px; border-radius: 5px; margin-top: 10px;\">' +
                    '<h4>✅ JSON valide:</h4>' +
                    '<pre>' + JSON.stringify(jsonData, null, 2) + '</pre>' +
                    '</div>';
            } catch(e) {
                document.getElementById('api-result').innerHTML +=
                    '<div style=\"background: #ffebee; padding: 10px; border-radius: 5px; margin-top: 10px;\">' +
                    '<h4>❌ Erreur de parsing JSON:</h4>' +
                    '<p>' + e.message + '</p>' +
                    '</div>';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            document.getElementById('api-result').innerHTML =
                '<div style=\"background: #ffebee; padding: 10px; border-radius: 5px;\">' +
                '<h4>❌ Erreur réseau:</h4>' +
                '<p>' + error.message + '</p>' +
                '</div>';
        });
    }
    </script>";

    echo "</div>";
}

echo "</div>";
echo "</body></html>";
?>
