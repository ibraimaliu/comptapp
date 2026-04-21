<?php
/**
 * API REST pour la gestion des devis
 *
 * Actions disponibles:
 * - create: Créer un nouveau devis
 * - read: Lire un devis par ID
 * - list: Lister les devis d'une société
 * - update: Mettre à jour un devis
 * - delete: Supprimer un devis
 * - change_status: Changer le statut d'un devis
 * - convert_to_invoice: Convertir un devis en facture
 * - statistics: Obtenir les statistiques des devis
 * - mark_expired: Marquer les devis expirés
 *
 * @author Gestion Comptable
 * @version 2.0
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With');

// Démarrer la session
session_name('COMPTAPP_SESSION');
session_start();

// Vérifier l'authentification
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Non authentifié'
    ]);
    exit;
}

// Inclure les fichiers nécessaires
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Quote.php';

// Créer la connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Créer l'objet Quote
$quote = new Quote($db);

// Obtenir la méthode HTTP
$method = $_SERVER['REQUEST_METHOD'];

// Obtenir les données de la requête
$data = json_decode(file_get_contents("php://input"));

// Router selon la méthode et l'action
try {
    switch ($method) {
        case 'POST':
            handlePost($quote, $data);
            break;

        case 'GET':
            handleGet($quote);
            break;

        case 'PUT':
            handlePut($quote, $data);
            break;

        case 'DELETE':
            handleDelete($quote, $data);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'message' => 'Méthode non autorisée'
            ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * Gérer les requêtes POST
 */
function handlePost($quote, $data) {
    if (!isset($data->action)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action non spécifiée'
        ]);
        return;
    }

    switch ($data->action) {
        case 'create':
            createQuote($quote, $data);
            break;

        case 'change_status':
            changeQuoteStatus($quote, $data);
            break;

        case 'convert_to_invoice':
            convertToInvoice($quote, $data);
            break;

        case 'mark_expired':
            markExpired($quote, $data);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Action invalide'
            ]);
    }
}

/**
 * Gérer les requêtes GET
 */
function handleGet($quote) {
    $action = isset($_GET['action']) ? $_GET['action'] : 'list';

    switch ($action) {
        case 'read':
            readQuote($quote);
            break;

        case 'list':
            listQuotes($quote);
            break;

        case 'statistics':
            getStatistics($quote);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Action invalide'
            ]);
    }
}

/**
 * Gérer les requêtes PUT
 */
function handlePut($quote, $data) {
    if (!isset($data->action)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action non spécifiée'
        ]);
        return;
    }

    if ($data->action === 'update') {
        updateQuote($quote, $data);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Action invalide'
        ]);
    }
}

/**
 * Gérer les requêtes DELETE
 */
function handleDelete($quote, $data) {
    deleteQuote($quote, $data);
}

/**
 * Créer un nouveau devis
 */
function createQuote($quote, $data) {
    // Vérifier les données requises
    if (empty($data->client_id) || empty($data->items)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Données incomplètes (client_id et items requis)'
        ]);
        return;
    }

    // Vérifier que l'utilisateur a une société active
    if (!isset($_SESSION['company_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aucune société active'
        ]);
        return;
    }

    // Assigner les valeurs
    $quote->company_id = $_SESSION['company_id'];
    $quote->client_id = $data->client_id;
    $quote->number = $data->number ?? null;
    $quote->title = $data->title ?? 'Devis';
    $quote->date = $data->date ?? date('Y-m-d');
    $quote->valid_until = $data->valid_until ?? date('Y-m-d', strtotime('+30 days'));
    $quote->status = $data->status ?? 'draft';
    $quote->currency = $data->currency ?? 'CHF';
    $quote->discount_percent = $data->discount_percent ?? 0;
    $quote->notes = $data->notes ?? null;
    $quote->terms = $data->terms ?? "Paiement sous 30 jours";
    $quote->footer = $data->footer ?? null;
    $quote->created_by = $_SESSION['user_id'];

    // Convertir les items en tableau
    $quote->items = json_decode(json_encode($data->items), true);

    // Créer le devis
    if ($quote->create()) {
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'Devis créé avec succès',
            'data' => [
                'id' => $quote->id,
                'number' => $quote->number,
                'total' => $quote->total
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la création du devis'
        ]);
    }
}

/**
 * Lire un devis
 */
function readQuote($quote) {
    if (!isset($_GET['id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du devis requis'
        ]);
        return;
    }

    $quote->id = $_GET['id'];
    $result = $quote->read();

    if ($result) {
        // Vérifier que le devis appartient à la société de l'utilisateur
        if ($quote->company_id != $_SESSION['company_id']) {
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'message' => 'Accès non autorisé'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Devis non trouvé'
        ]);
    }
}

/**
 * Lister les devis
 */
function listQuotes($quote) {
    if (!isset($_SESSION['company_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aucune société active'
        ]);
        return;
    }

    // Construire les filtres
    $filters = [];
    if (isset($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    if (isset($_GET['client_id'])) {
        $filters['client_id'] = $_GET['client_id'];
    }
    if (isset($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    if (isset($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    if (isset($_GET['search'])) {
        $filters['search'] = $_GET['search'];
    }

    $stmt = $quote->readByCompany($_SESSION['company_id'], $filters);
    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $quotes,
        'count' => count($quotes)
    ]);
}

/**
 * Mettre à jour un devis
 */
function updateQuote($quote, $data) {
    // Vérifier les données requises
    if (empty($data->id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du devis requis'
        ]);
        return;
    }

    // Charger le devis existant
    $quote->id = $data->id;
    if (!$quote->read()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Devis non trouvé'
        ]);
        return;
    }

    // Vérifier l'accès
    if ($quote->company_id != $_SESSION['company_id']) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Accès non autorisé'
        ]);
        return;
    }

    // Vérifier que le devis n'est pas converti
    if ($quote->status == 'converted') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Impossible de modifier un devis déjà converti'
        ]);
        return;
    }

    // Mettre à jour les valeurs
    $quote->client_id = $data->client_id ?? $quote->client_id;
    $quote->title = $data->title ?? $quote->title;
    $quote->date = $data->date ?? $quote->date;
    $quote->valid_until = $data->valid_until ?? $quote->valid_until;
    $quote->status = $data->status ?? $quote->status;
    $quote->currency = $data->currency ?? $quote->currency;
    $quote->discount_percent = $data->discount_percent ?? $quote->discount_percent;
    $quote->notes = $data->notes ?? $quote->notes;
    $quote->terms = $data->terms ?? $quote->terms;
    $quote->footer = $data->footer ?? $quote->footer;

    if (isset($data->items)) {
        $quote->items = json_decode(json_encode($data->items), true);
    }

    if ($quote->update()) {
        echo json_encode([
            'success' => true,
            'message' => 'Devis mis à jour avec succès'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour'
        ]);
    }
}

/**
 * Supprimer un devis
 */
function deleteQuote($quote, $data) {
    if (empty($data->id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du devis requis'
        ]);
        return;
    }

    $quote->id = $data->id;
    $quote->company_id = $_SESSION['company_id'];

    // Charger le devis pour vérifier le statut
    if ($quote->read()) {
        if ($quote->status == 'converted') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Impossible de supprimer un devis converti'
            ]);
            return;
        }
    }

    if ($quote->delete()) {
        echo json_encode([
            'success' => true,
            'message' => 'Devis supprimé avec succès'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la suppression'
        ]);
    }
}

/**
 * Changer le statut d'un devis
 */
function changeQuoteStatus($quote, $data) {
    if (empty($data->id) || empty($data->status)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID et statut requis'
        ]);
        return;
    }

    // Statuts valides
    $valid_statuses = ['draft', 'sent', 'accepted', 'rejected', 'expired'];
    if (!in_array($data->status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Statut invalide'
        ]);
        return;
    }

    $quote->id = $data->id;
    $quote->company_id = $_SESSION['company_id'];

    // Charger le devis
    if (!$quote->read()) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Devis non trouvé'
        ]);
        return;
    }

    $notes = $data->notes ?? null;

    if ($quote->changeStatus($data->status, $notes)) {
        echo json_encode([
            'success' => true,
            'message' => 'Statut modifié avec succès',
            'data' => [
                'id' => $quote->id,
                'status' => $quote->status
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du changement de statut'
        ]);
    }
}

/**
 * Convertir un devis en facture
 */
function convertToInvoice($quote, $data) {
    if (empty($data->id)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'ID du devis requis'
        ]);
        return;
    }

    $quote->id = $data->id;
    $quote->company_id = $_SESSION['company_id'];

    $invoice_id = $quote->convertToInvoice();

    if ($invoice_id) {
        echo json_encode([
            'success' => true,
            'message' => 'Devis converti en facture avec succès',
            'data' => [
                'invoice_id' => $invoice_id
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la conversion. Le devis doit être accepté ou envoyé.'
        ]);
    }
}

/**
 * Obtenir les statistiques
 */
function getStatistics($quote) {
    if (!isset($_SESSION['company_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aucune société active'
        ]);
        return;
    }

    $stats = $quote->getStatistics($_SESSION['company_id']);

    if ($stats) {
        echo json_encode([
            'success' => true,
            'data' => $stats
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'data' => [
                'total_quotes' => 0,
                'draft_count' => 0,
                'sent_count' => 0,
                'accepted_count' => 0,
                'rejected_count' => 0,
                'expired_count' => 0,
                'converted_count' => 0,
                'total_accepted_amount' => 0,
                'acceptance_rate' => 0
            ]
        ]);
    }
}

/**
 * Marquer les devis expirés
 */
function markExpired($quote, $data) {
    if (!isset($_SESSION['company_id'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aucune société active'
        ]);
        return;
    }

    if ($quote->markExpiredQuotes($_SESSION['company_id'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Devis expirés marqués avec succès'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de la mise à jour'
        ]);
    }
}
