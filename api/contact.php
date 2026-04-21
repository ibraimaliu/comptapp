<?php
// Démarrer la session
session_start();

// Inclure les fichiers nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/Contact.php';

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['company_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Vous devez être connecté pour effectuer cette action.'
    ]);
    exit;
}

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Créer une instance de Contact
$contact = new Contact($db);

// Définir le type de contenu
header('Content-Type: application/json');

// Déterminer l'action à effectuer
$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

switch ($action) {
    case 'create':
        // Récupérer les données du formulaire
        foreach ($_POST as $key => $value) {
            // Si la propriété existe dans l'objet Contact
            if (property_exists($contact, $key)) {
                $contact->$key = $value;
            }
        }
        
        // Essayer de créer le contact
        if ($contact->create()) {
            // Récupérer le contact créé pour le retourner
            $contact->read();
            
            echo json_encode([
                'success' => true,
                'message' => 'Contact créé avec succès.',
                'contact' => get_object_vars($contact)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de créer le contact.'
            ]);
        }
        break;
        
    case 'read':
        // Récupérer l'ID du contact
        $contact->id = isset($_GET['id']) ? $_GET['id'] : 0;
        
        // Essayer de lire le contact
        if ($contact->read()) {
            echo json_encode([
                'success' => true,
                'contact' => get_object_vars($contact)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Contact non trouvé.'
            ]);
        }
        break;
        
    case 'update':
        // Récupérer les données du formulaire
        foreach ($_POST as $key => $value) {
            // Si la propriété existe dans l'objet Contact
            if (property_exists($contact, $key)) {
                $contact->$key = $value;
            }
        }
        
        // Essayer de mettre à jour le contact
        if ($contact->update()) {
            // Récupérer le contact mis à jour
            $contact->read();
            
            echo json_encode([
                'success' => true,
                'message' => 'Contact mis à jour avec succès.',
                'contact' => get_object_vars($contact)
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de mettre à jour le contact.'
            ]);
        }
        break;
        
    case 'delete':
        // Récupérer l'ID du contact
        $contact->id = isset($_GET['id']) ? $_GET['id'] : 0;
        
        // Essayer de supprimer le contact
        if ($contact->delete()) {
            echo json_encode([
                'success' => true,
                'message' => 'Contact supprimé avec succès.'
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer le contact.'
            ]);
        }
        break;
        
    default:
        echo json_encode([
            'success' => false,
            'message' => 'Action non reconnue.'
        ]);
}
?>