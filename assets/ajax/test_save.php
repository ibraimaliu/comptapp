<?php
// ajax/test_save.php - Test de sauvegarde ultra-simple
ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

try {
    echo "Début du test...\n";
    
    // Inclure les fichiers
    include_once dirname(__DIR__) . '/config/database.php';
    include_once dirname(__DIR__) . '/models/Contact.php';
    
    echo "Fichiers inclus OK\n";
    
    // Connexion DB
    $database = new Database();
    $db = $database->getConnection();
    
    echo "Connexion DB OK\n";
    
    // Instance Contact
    $contact = new Contact($db);
    
    echo "Instance Contact OK\n";
    echo "Colonnes: " . implode(", ", $contact->getColumnNames()) . "\n";
    
    // Données test très simples
    $testData = [];
    
    // Ajouter les données selon les colonnes disponibles
    if ($contact->hasColumn('name')) {
        $testData['name'] = 'Contact Test Simple';
    } else if ($contact->hasColumn('nom')) {
        $testData['nom'] = 'Contact Test Simple';
    } else if ($contact->hasColumn('titre')) {
        $testData['titre'] = 'Contact Test Simple';
    }
    
    if ($contact->hasColumn('email')) {
        $testData['email'] = 'test@example.com';
    }
    
    if ($contact->hasColumn('type')) {
        $testData['type'] = 'autre';
    }
    
    // Company_id si nécessaire
    if ($contact->hasCompanyId()) {
        $testData['company_id'] = 1; // CHANGEZ ce chiffre par un ID valide de votre table companies
    }
    
    echo "Données à insérer: " . print_r($testData, true) . "\n";
    
    // Tentative de création
    $result = $contact->create($testData);
    
    echo "Résultat create(): " . ($result ? $result : 'FALSE') . "\n";
    
    if ($result) {
        echo "✅ SUCCÈS ! Contact créé avec ID: $result\n";
        
        // Vérifier qu'il est bien en base
        $check = $db->prepare("SELECT * FROM adresses WHERE id = ?");
        $check->execute([$result]);
        $saved = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($saved) {
            echo "✅ Contact vérifié en base: " . print_r($saved, true) . "\n";
        } else {
            echo "❌ Contact non trouvé en base après création\n";
        }
        
    } else {
        echo "❌ ÉCHEC de la création\n";
        
        // Afficher l'erreur PDO
        $errorInfo = $db->errorInfo();
        echo "Erreur PDO: " . print_r($errorInfo, true) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack: " . $e->getTraceAsString() . "\n";
}
?>