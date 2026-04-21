<?php
session_start();

// Headers pour les réponses JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Inclure les fichiers nécessaires avec des chemins relatifs depuis api/
include_once '../../config/database.php';
include_once '../../models/Contact.php';

try {
    // Vérifier que la société est sélectionnée
    if (!isset($_SESSION['company_id'])) {
        echo json_encode(['success' => false, 'message' => 'Aucune société sélectionnée']);
        exit;
    }

    // Vérifier que c'est une requête POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
        exit;
    }

    // Initialiser la base de données
    $database = new Database();
    $db = $database->getConnection();
    $contact = new Contact($db);

    // Récupérer les données du formulaire
    $contact_id = $_POST['id'] ?? null;
    $company_id = $_SESSION['company_id'];
    $type = $_POST['type'] ?? 'autre';
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $postal_code = $_POST['postal_code'] ?? '';
    $city = $_POST['city'] ?? '';
    $country = $_POST['country'] ?? '';

    // Validation des données obligatoires
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Le nom est obligatoire']);
        exit;
    }

    // Préparer les données pour le modèle
    $contact_data = [
        'company_id' => $company_id,
        'type' => $type,
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'postal_code' => $postal_code,
        'city' => $city,
        'country' => $country
    ];

    // Créer ou modifier le contact
    if (!empty($contact_id)) {
        // Modification
        $contact_data['id'] = $contact_id;
        $result = $contact->update($contact_data);
        $message = 'Contact modifié avec succès';
    } else {
        // Création
        $result = $contact->create($contact_data);
        $message = 'Contact créé avec succès';
    }

    if ($result) {
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
    }

} catch (Exception $e) {
    error_log("Erreur dans save_contact.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
}