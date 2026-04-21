<?php
// ajax/get_contact.php - Script pour récupérer un contact via AJAX
session_start();

// Headers pour JSON
header('Content-Type: application/json');

try {
    // Vérifier la session
    if (!isset($_SESSION['company_id'])) {
        throw new Exception('Session expirée. Veuillez vous reconnecter.');
    }
    
    // Vérifier l'ID
    if (empty($_GET['id'])) {
        throw new Exception('ID du contact requis');
    }
    
    // Inclure les fichiers nécessaires
    include_once '../config/database.php';
    include_once '../models/Contact.php';
    
    // Initialiser la base de données
    $database = new Database();
    $db = $database->getConnection();
    $contact = new Contact($db);
    
    $company_id = $_SESSION['company_id'];
    $contact_id = (int)$_GET['id'];
    
    // Récupérer le contact
    $contactData = $contact->getById($contact_id, $contact->hasCompanyId() ? $company_id : null);
    
    if ($contactData) {
        // Mapper les colonnes de la base vers les champs attendus par le formulaire
        $standardizedData = [
            'id' => $contactData['id']
        ];
        
        // Mapping des colonnes vers les champs standards
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
        
        foreach ($field_mapping as $standard_field => $possible_columns) {
            $standardizedData[$standard_field] = '';
            foreach ($possible_columns as $column) {
                if (isset($contactData[$column]) && !empty($contactData[$column])) {
                    $standardizedData[$standard_field] = $contactData[$column];
                    break; // Utiliser la première valeur non vide trouvée
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'contact' => $standardizedData
        ]);
    } else {
        throw new Exception('Contact non trouvé');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>