<?php
// ajax/delete_contact.php - Script pour supprimer un contact via AJAX
session_name('COMPTAPP_SESSION');
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
    include_once dirname(dirname(__DIR__)) . '/config/database.php';
    include_once dirname(dirname(__DIR__)) . '/models/Contact.php';
    
    // Initialiser la base de données
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Impossible de se connecter à la base de données');
    }

    $contact = new Contact($db);
    
    $company_id = $_SESSION['company_id'];
    $contact_id = (int)$_POST['id'];
    
    // Vérifier que le contact appartient à la bonne société (seulement si company_id existe)
    if ($contact->hasCompanyId() && !$contact->belongsToCompany($contact_id, $company_id)) {
        throw new Exception('Contact non trouvé');
    }
    
    // Supprimer le contact
    if ($contact->delete($contact_id)) {
        echo json_encode([
            'success' => true,
            'message' => 'Contact supprimé avec succès'
        ]);
    } else {
        throw new Exception('Erreur lors de la suppression du contact');
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>