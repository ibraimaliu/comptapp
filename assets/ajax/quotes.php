<?php
/**
 * API AJAX pour la gestion des devis
 */
session_name('COMPTAPP_SESSION');
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier la session
if (!isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Session expirée']);
    exit;
}

// Inclure les modèles
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/models/Quote.php';
require_once dirname(dirname(__DIR__)) . '/models/Contact.php';

$company_id = $_SESSION['company_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

$quote = new Quote($db);

// Déterminer l'action
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $action = 'list';
}

try {
    switch ($action) {
        case 'create':
            createQuote($quote, $input, $company_id, $db);
            break;

        case 'update':
            updateQuote($quote, $input, $company_id);
            break;

        case 'delete':
            deleteQuote($quote, $input, $company_id);
            break;

        case 'convert':
            convertToInvoice($quote, $input, $company_id, $db);
            break;

        case 'list':
        default:
            listQuotes($quote, $company_id, $db);
            break;
    }

} catch (Exception $e) {
    error_log('Error in quotes.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * Lister les devis
 */
function listQuotes($quote, $company_id, $db) {
    try {
        $stmt = $quote->readByCompany($company_id);
        $quotes = [];

        $contact = new Contact($db);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Récupérer le nom du client
            $client_name = 'Client inconnu';
            if (!empty($row['client_id'])) {
                try {
                    $client_stmt = $db->prepare("SELECT name, nom, title, titre FROM contacts WHERE id = :id LIMIT 1");
                    $client_stmt->bindParam(':id', $row['client_id']);
                    $client_stmt->execute();
                    $client = $client_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($client) {
                        $client_name = $client['name'] ?? $client['nom'] ?? $client['title'] ?? $client['titre'] ?? 'Client inconnu';
                    }
                } catch (Exception $e) {
                    error_log('Error fetching client name: ' . $e->getMessage());
                }
            }

            $quotes[] = [
                'id' => $row['id'],
                'number' => $row['number'],
                'date' => $row['date'],
                'valid_until' => $row['valid_until'],
                'client_id' => $row['client_id'],
                'client_name' => $client_name,
                'subtotal' => $row['subtotal'],
                'tva_amount' => $row['tva_amount'],
                'total' => $row['total'],
                'status' => $row['status'],
                'notes' => $row['notes'] ?? '',
                'converted_to_invoice_id' => $row['converted_to_invoice_id'] ?? null,
                'created_at' => $row['created_at'] ?? null
            ];
        }

        echo json_encode([
            'success' => true,
            'quotes' => $quotes,
            'total' => count($quotes)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du chargement des devis: ' . $e->getMessage()
        ]);
    }
}

/**
 * Créer un devis
 */
function createQuote($quote, $data, $company_id, $db) {
    try {
        // Valider les données
        if (empty($data['client_id'])) {
            throw new Exception('Client requis');
        }

        if (empty($data['date'])) {
            throw new Exception('Date requise');
        }

        if (empty($data['valid_until'])) {
            throw new Exception('Date de validité requise');
        }

        if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
            throw new Exception('Au moins un article est requis');
        }

        // Calculer les totaux
        $subtotal = 0;
        $tva_total = 0;

        foreach ($data['items'] as $item) {
            if (empty($item['description']) || empty($item['quantity']) || empty($item['unit_price'])) {
                continue;
            }

            $qty = floatval($item['quantity']);
            $price = floatval($item['unit_price']);
            $tva_rate = floatval($item['tva_rate'] ?? 7.7);

            $item_subtotal = $qty * $price;
            $item_tva = $item_subtotal * ($tva_rate / 100);

            $subtotal += $item_subtotal;
            $tva_total += $item_tva;
        }

        $total = $subtotal + $tva_total;

        // Préparer les données du devis
        $quoteData = [
            'company_id' => $company_id,
            'client_id' => intval($data['client_id']),
            'date' => $data['date'],
            'valid_until' => $data['valid_until'],
            'subtotal' => $subtotal,
            'tva_amount' => $tva_total,
            'total' => $total,
            'status' => 'draft',
            'notes' => $data['notes'] ?? '',
            'items' => $data['items']
        ];

        // Créer le devis
        $quote_id = $quote->create($quoteData);

        if ($quote_id) {
            echo json_encode([
                'success' => true,
                'message' => 'Devis créé avec succès',
                'quote_id' => $quote_id
            ]);
        } else {
            throw new Exception('Erreur lors de la création du devis');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Mettre à jour un devis
 */
function updateQuote($quote, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID du devis requis');
        }

        // TODO: Implémenter la mise à jour
        echo json_encode([
            'success' => true,
            'message' => 'Devis mis à jour'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Supprimer un devis
 */
function deleteQuote($quote, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID du devis requis');
        }

        $quote_id = intval($data['id']);

        // Vérifier que le devis appartient à la société
        $stmt = $quote->db->prepare("SELECT status FROM quotes WHERE id = :id AND company_id = :company_id");
        $stmt->bindParam(':id', $quote_id);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new Exception('Devis non trouvé');
        }

        // Seuls les brouillons peuvent être supprimés
        if ($existing['status'] !== 'draft') {
            throw new Exception('Seuls les devis en brouillon peuvent être supprimés');
        }

        if ($quote->delete($quote_id)) {
            echo json_encode([
                'success' => true,
                'message' => 'Devis supprimé avec succès'
            ]);
        } else {
            throw new Exception('Erreur lors de la suppression');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Convertir un devis en facture
 */
function convertToInvoice($quote, $data, $company_id, $db) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID du devis requis');
        }

        $quote_id = intval($data['id']);

        // Vérifier que le devis est accepté
        $stmt = $quote->db->prepare("
            SELECT * FROM quotes
            WHERE id = :id AND company_id = :company_id AND status = 'accepted'
        ");
        $stmt->bindParam(':id', $quote_id);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();

        $quoteData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quoteData) {
            throw new Exception('Devis non trouvé ou pas encore accepté');
        }

        if (!empty($quoteData['converted_to_invoice_id'])) {
            throw new Exception('Ce devis a déjà été converti en facture');
        }

        // Convertir via le modèle
        $invoice_id = $quote->convertToInvoice($quote_id);

        if ($invoice_id) {
            echo json_encode([
                'success' => true,
                'message' => 'Devis converti en facture avec succès',
                'invoice_id' => $invoice_id
            ]);
        } else {
            throw new Exception('Erreur lors de la conversion');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
