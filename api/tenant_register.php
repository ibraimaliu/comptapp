<?php
/**
 * API: Inscription d'un nouveau tenant (client)
 * Crée automatiquement une base de données dédiée pour le client
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST');

require_once '../config/database_master.php';
require_once '../models/Tenant.php';

// Récupérer les données POST
$company_name = $_POST['company_name'] ?? '';
$contact_name = $_POST['contact_name'] ?? '';
$contact_email = $_POST['contact_email'] ?? '';
$contact_phone = $_POST['contact_phone'] ?? null;
$address = $_POST['address'] ?? null;
$subscription_plan = $_POST['subscription_plan'] ?? 'free';

// Validation
if (empty($company_name) || empty($contact_name) || empty($contact_email)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Veuillez remplir tous les champs obligatoires'
    ]);
    exit;
}

// Valider l'email
if (!filter_var($contact_email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Adresse email invalide'
    ]);
    exit;
}

try {
    // Connexion à la base master
    $database = new DatabaseMaster();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Impossible de se connecter à la base master');
    }

    // Vérifier si l'email existe déjà
    $tenant = new Tenant($db);
    $existing = $tenant->readByEmail($contact_email);

    if ($existing) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Un compte avec cet email existe déjà'
        ]);
        exit;
    }

    // Récupérer les limites du plan
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE plan_code = :plan LIMIT 1");
    $stmt->bindParam(':plan', $subscription_plan);
    $stmt->execute();
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor(); // Fermer le curseur pour éviter les erreurs unbuffered

    if (!$plan) {
        throw new Exception('Plan d\'abonnement invalide');
    }

    // Créer le tenant
    $tenant->company_name = $company_name;
    $tenant->contact_name = $contact_name;
    $tenant->contact_email = $contact_email;
    $tenant->contact_phone = $contact_phone;
    $tenant->address = $address;
    $tenant->subscription_plan = $subscription_plan;
    $tenant->max_users = $plan['max_users'];
    $tenant->max_companies = $plan['max_companies'];
    $tenant->max_transactions_per_month = $plan['max_transactions_per_month'];
    $tenant->max_storage_mb = $plan['max_storage_mb'];

    // Tenter de créer le tenant
    $tenant->create();

    // Envoyer un email de bienvenue (à implémenter)
    // sendWelcomeEmail($tenant);

    echo json_encode([
        'success' => true,
        'message' => 'Compte créé avec succès!',
        'tenant_code' => $tenant->tenant_code,
        'database_name' => $tenant->database_name,
        'username' => strtolower(str_replace(' ', '_', $contact_name)),
        'temp_password' => $tenant->temp_password ?? 'Changez ce mot de passe',
        'trial_ends_at' => $tenant->trial_ends_at
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_details' => [
            'file' => basename($e->getFile()),
            'line' => $e->getLine()
        ]
    ]);
    error_log("Erreur inscription tenant: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
}
?>
