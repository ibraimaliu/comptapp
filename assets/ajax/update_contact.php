<?php
// ajax/update_contact.php - Script pour modifier un contact via AJAX
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
    
    // Vérifier l'ID
    if (empty($_POST['id'])) {
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
    $contact_id = (int)$_POST['id'];
    
    // Validation des champs obligatoires
    if (empty($_POST['name'])) {
        throw new Exception('Le nom est obligatoire');
    }
    
    if (empty($_POST['type'])) {
        throw new Exception('Le type est obligatoire');
    }
    
    // Vérifier que le contact appartient à la bonne société
    if ($contact->hasCompanyId() && !$contact->belongsToCompany($contact_id, $company_id)) {
        throw new Exception('Contact non trouvé');
    }
    
    // Préparer les données
    $data = ['id' => $contact_id];
    
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
    
    // Mettre à jour le contact
    if ($contact->update($data)) {
        echo json_encode([
            'success' => true,
            'message' => 'Contact mis à jour avec succès !'
        ]);
    } else {
        throw new Exception('Erreur lors de la mise à jour du contact');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>