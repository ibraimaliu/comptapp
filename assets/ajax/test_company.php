<?php
// ajax/test_company.php - Test rapide pour company_id (localhost uniquement)
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Accès refusé');
}
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    include_once dirname(__DIR__) . '/config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    echo "<h2>🔍 Test Company ID</h2>";
    
    // 1. Lister les companies existantes
    echo "<h3>1. Companies existantes :</h3>";
    $stmt = $db->query("SELECT id, nom FROM companies LIMIT 10");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($companies) > 0) {
        echo "<ul>";
        foreach($companies as $company) {
            echo "<li>ID: " . $company['id'] . " - " . ($company['nom'] ?? 'Nom non défini') . "</li>";
        }
        echo "</ul>";
        
        // Prendre le premier ID valide
        $valid_company_id = $companies[0]['id'];
        echo "<p><strong>Premier company_id valide trouvé: $valid_company_id</strong></p>";
        
    } else {
        echo "<p style='color: red;'>❌ Aucune company trouvée dans la table companies !</p>";
        exit;
    }
    
    // 2. Vérifier la structure de la table adresses
    echo "<h3>2. Structure table adresses :</h3>";
    $stmt = $db->query("DESCRIBE adresses");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $has_company_id = false;
    echo "<ul>";
    foreach($columns as $col) {
        echo "<li>" . $col['Field'] . " (" . $col['Type'] . ")";
        if ($col['Field'] === 'company_id') {
            $has_company_id = true;
            echo " ← <strong>COMPANY_ID TROUVÉ</strong>";
        }
        echo "</li>";
    }
    echo "</ul>";
    
    if (!$has_company_id) {
        echo "<p style='color: red;'>❌ La table adresses n'a pas de colonne company_id !</p>";
        exit;
    }
    
    // 3. Test d'insertion directe avec company_id valide
    echo "<h3>3. Test d'insertion directe :</h3>";
    
    $test_name = 'TEST CONTACT ' . date('H:i:s');
    $sql = "INSERT INTO adresses (company_id, nom, email, type) VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($sql);
    
    $result = $stmt->execute([$valid_company_id, $test_name, 'test@example.com', 'autre']);
    
    if ($result) {
        $new_id = $db->lastInsertId();
        echo "<p style='color: green;'>✅ Contact créé avec succès ! ID: $new_id</p>";
        
        // Vérifier qu'il est bien là
        $check = $db->prepare("SELECT * FROM adresses WHERE id = ?");
        $check->execute([$new_id]);
        $saved = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($saved) {
            echo "<p>✅ Contact vérifié en base :</p>";
            echo "<pre>" . print_r($saved, true) . "</pre>";
            
            // Nettoyer le test
            $delete = $db->prepare("DELETE FROM adresses WHERE id = ?");
            $delete->execute([$new_id]);
            echo "<p>✅ Contact test supprimé</p>";
            
        } else {
            echo "<p style='color: red;'>❌ Contact non trouvé après création</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Échec de l'insertion directe</p>";
        $errorInfo = $stmt->errorInfo();
        echo "<pre>Erreur SQL: " . print_r($errorInfo, true) . "</pre>";
    }
    
    // 4. Compter les adresses existantes par company
    echo "<h3>4. Adresses existantes par company :</h3>";
    $stmt = $db->query("SELECT company_id, COUNT(*) as total FROM adresses GROUP BY company_id");
    $counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($counts) > 0) {
        echo "<ul>";
        foreach($counts as $count) {
            echo "<li>Company ID " . $count['company_id'] . " : " . $count['total'] . " adresses</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Aucune adresse trouvée</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ ERREUR: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
?>