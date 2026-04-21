<?php
// ajax/save_contact.php
session_name('COMPTAPP_SESSION');
session_start();

// ----------------------
// En-tête JSON immédiat
// ----------------------
header('Content-Type: application/json; charset=utf-8');

// Empêcher toute sortie précédente (BOM/whitespace)
if (ob_get_length()) ob_clean();

// Fonction de réponse JSON centralisée
function sendJsonResponse($success, $message = '', $data = null, $code = 200) {
    http_response_code($code);
    // S'assurer qu'aucune autre sortie n'interfère
    if (ob_get_length()) ob_clean();
    echo json_encode([
        'success' => (bool)$success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Désactiver l'affichage des erreurs (mettre à true pendant dev si besoin)
ini_set('display_errors', '0');

// Vérifier méthode
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Méthode non autorisée', null, 405);
}

// Inclure les classes / bootstrap (attention aux include path)
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/models/Contact.php';

// Vérifier la présence d'une company_id envoyée
$company_id = isset($_POST['company_id']) ? (int)$_POST['company_id'] : null;
if (!$company_id && isset($_SESSION['company_id'])) {
    $company_id = (int)$_SESSION['company_id'];
}
if (!$company_id) {
    sendJsonResponse(false, 'Aucune société sélectionnée', null, 400);
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $contact = new Contact($db);

    // Récupération et nettoyage des champs
    $contact_id = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $type = isset($_POST['type']) ? trim($_POST['type']) : 'autre';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : '';

    // ===== Validation serveur (essentielle) =====
    if (empty($name)) {
        sendJsonResponse(false, 'Le nom est obligatoire', null, 422);
    }

    if ($contact->hasColumn('type') && !in_array($type, ['client', 'fournisseur', 'autre'])) {
        sendJsonResponse(false, 'Type de contact non valide', null, 422);
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJsonResponse(false, 'Format d\'email non valide', null, 422);
    }

    // Préparer le tableau de données à insérer / mettre à jour
    $contactData = [];
    if ($contact->hasCompanyId()) {
        $contactData['company_id'] = $company_id;
    }

    $columnMapping = [
        'type' => $type,
        'name' => $name,
        'nom' => $name,
        'titre' => $name,
        'title' => $name,
        'email' => $email,
        'mail' => $email,
        'courriel' => $email,
        'phone' => $phone,
        'telephone' => $phone,
        'tel' => $phone,
        'address' => $address,
        'adresse' => $address,
        'rue' => $address,
        'postal_code' => $postal_code,
        'code_postal' => $postal_code,
        'npa' => $postal_code,
        'city' => $city,
        'ville' => $city,
        'localite' => $city,
        'country' => $country,
        'pays' => $country
    ];

    foreach ($columnMapping as $col => $val) {
        if ($contact->hasColumn($col)) {
            $contactData[$col] = $val;
        }
    }

    // Si mise à jour
    if ($contact_id) {
        $contactData['id'] = $contact_id;

        // Vérifier appartenance à la société si nécessaire
        if ($contact->hasCompanyId()) {
            $existing = $contact->getById($contact_id, $company_id);
            if (!$existing) {
                sendJsonResponse(false, 'Contact non trouvé ou accès non autorisé', null, 403);
            }
        }

        $ok = $contact->update($contactData);
        if ($ok) {
            sendJsonResponse(true, 'Contact mis à jour avec succès', ['id' => $contact_id]);
        } else {
            // Log SQL/erreur pour debug serveur
            error_log('Contact::update a retourné false pour id=' . $contact_id . ' | data: ' . json_encode($contactData));
            sendJsonResponse(false, 'Erreur lors de la mise à jour du contact', null, 500);
        }
    }

    // Création
    $newId = $contact->create($contactData);
    if ($newId) {
        sendJsonResponse(true, 'Contact créé avec succès', ['id' => $newId], 201);
    } else {
        error_log('Contact::create a retourné false | data: ' . json_encode($contactData));
        sendJsonResponse(false, 'Erreur lors de la création du contact', null, 500);
    }

} catch (PDOException $pdoEx) {
    // Logger l'exception pour debug et renvoyer message générique
    error_log('PDOException save_contact: ' . $pdoEx->getMessage());
    sendJsonResponse(false, 'Erreur base de données', null, 500);
} catch (Exception $ex) {
    error_log('Exception save_contact: ' . $ex->getMessage());
    sendJsonResponse(false, 'Erreur serveur lors de la sauvegarde', null, 500);
}
