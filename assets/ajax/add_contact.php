<?php
// ajax/add_contact.php - Script pour ajouter un contact via AJAX
session_start();

// Headers pour JSON
header('Content-Type: application/json');

try {
    // Vérifier la méthode
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Méthode non autorisée');
    }
    
    // Vérifier la session
    if (!isset($_SESSION['company_id'])) {
        throw new Exception('Session expirée. Veuillez vous reconnecter.');
    }
    
    // Inclure les fichiers nécessaires
    include_once '../config/database.php';
    include_once '../models/Contact.php';
    
    // Initialiser la base de données
    $database = new Database();
    $db = $database->getConnection();
    $contact = new Contact($db);
    
    $company_id = $_SESSION['company_id'];
    
    // Validation des champs obligatoires
    if (empty($_POST['name'])) {
        throw new Exception('Le nom est obligatoire');
    }
    
    if (empty($_POST['type'])) {
        throw new Exception('Le type est obligatoire');
    }
    
    // Préparer les données
    $data = [];
    
    // Ajouter company_id si la table l'a
    if ($contact->hasCompanyId()) {
        $data['company_id'] = $company_id;
    }
    
    // Mapper les champs du formulaire aux colonnes de la table
    $field_mapping = [
        'type' => ['type'],
        'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
        'firstname' => ['firstname', 'prenom'],
        'email' => ['email', 'mail', 'courriel'],
        'phone' => ['phone', 'telephone', 'tel'],
        'mobile' => ['mobile', 'portable'],
        'fax' => ['fax'],
        'website' => ['website', 'site_web'],
        'address' => ['address', 'adresse', 'rue'],
        'postal_code' => ['postal_code', 'code_postal', 'npa'],
        'city' => ['city', 'ville', 'localite'],
        'country' => ['country', 'pays'],
        'vat_number' => ['vat_number', 'numero_tva', 'tva'],
        'siret' => ['siret', 'siren'],
        'notes' => ['notes', 'commentaires', 'remarques']
    ];
    
    foreach ($field_mapping as $form_field => $possible_columns) {
        $value = $_POST[$form_field] ?? '';
        foreach ($possible_columns as $column) {
            if ($contact->hasColumn($column)) {
                $data[$column] = $value;
                break; // Utiliser la première colonne trouvée
            }
        }
    }
    
    // Créer le contact
    $contact_id = $contact->create($data);
    
    if ($contact_id) {
        echo json_encode([
            'success' => true,
            'message' => 'Contact créé avec succès !',
            'contact_id' => $contact_id
        ]);
    } else {
        throw new Exception('Erreur lors de la création du contact');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>