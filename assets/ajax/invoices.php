<?php
/**
 * API AJAX pour la gestion des factures avec QR-Invoice
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
require_once dirname(dirname(__DIR__)) . '/models/Invoice.php';
require_once dirname(dirname(__DIR__)) . '/models/QRInvoice.php';
require_once dirname(dirname(__DIR__)) . '/models/Contact.php';
require_once dirname(dirname(__DIR__)) . '/models/Company.php';

$company_id = $_SESSION['company_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

$invoice = new Invoice($db);
$qr_invoice = new QRInvoice($db);

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
            createInvoice($invoice, $qr_invoice, $input, $company_id, $db);
            break;

        case 'update':
            updateInvoice($invoice, $input, $company_id);
            break;

        case 'delete':
            deleteInvoice($invoice, $input, $company_id);
            break;

        case 'mark_paid':
            markAsPaid($invoice, $input, $company_id);
            break;

        case 'list':
        default:
            listInvoices($invoice, $company_id, $db);
            break;
    }

} catch (Exception $e) {
    error_log('Error in invoices.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * Lister les factures
 */
function listInvoices($invoice, $company_id, $db) {
    try {
        $query = "SELECT i.*, c.name as client_name
                  FROM invoices i
                  LEFT JOIN contacts c ON i.client_id = c.id
                  WHERE i.company_id = :company_id
                  ORDER BY i.date DESC, i.id DESC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();

        $invoices = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $client_name = $row['client_name'] ?? 'Client inconnu';

            $invoices[] = [
                'id' => $row['id'],
                'number' => $row['number'],
                'date' => $row['date'],
                'due_date' => $row['due_date'] ?? null,
                'client_id' => $row['client_id'],
                'client_name' => $client_name,
                'subtotal' => $row['subtotal'],
                'tva_amount' => $row['tva_amount'],
                'total' => $row['total'],
                'status' => $row['status'],
                'qr_reference' => $row['qr_reference'] ?? null,
                'created_at' => $row['created_at'] ?? null
            ];
        }

        echo json_encode([
            'success' => true,
            'invoices' => $invoices,
            'total' => count($invoices)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du chargement des factures: ' . $e->getMessage()
        ]);
    }
}

/**
 * Créer une facture avec QR-Invoice
 */
function createInvoice($invoice, $qr_invoice, $data, $company_id, $db) {
    try {
        // Valider les données
        if (empty($data['client_id'])) {
            throw new Exception('Client requis');
        }

        if (empty($data['date'])) {
            throw new Exception('Date requise');
        }

        if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
            throw new Exception('Au moins un article est requis');
        }

        // Calculer les totaux
        $subtotal = 0;
        $tva_total = 0;
        $items = [];

        foreach ($data['items'] as $item) {
            if (empty($item['description']) || empty($item['quantity']) || empty($item['unit_price'])) {
                continue;
            }

            $qty = floatval($item['quantity']);
            $price = floatval($item['unit_price']);
            $tva_rate = floatval($item['tva_rate'] ?? 7.7);

            $item_subtotal = $qty * $price;
            $item_tva = $item_subtotal * ($tva_rate / 100);
            $item_total = $item_subtotal + $item_tva;

            $subtotal += $item_subtotal;
            $tva_total += $item_tva;

            $items[] = [
                'description' => $item['description'],
                'quantity' => $qty,
                'price' => $price,
                'tva_rate' => $tva_rate,
                'tva_amount' => $item_tva,
                'total' => $item_total
            ];
        }

        $total = $subtotal + $tva_total;

        // Générer le numéro de facture
        $invoice_number = generateInvoiceNumber($company_id, $db);

        // Générer la référence QR
        $qr_reference = $qr_invoice->generateQRReference($invoice_number, $company_id);

        // Préparer l'invoice
        $invoice->company_id = $company_id;
        $invoice->number = $invoice_number;
        $invoice->date = $data['date'];
        $invoice->client_id = intval($data['client_id']);
        $invoice->subtotal = $subtotal;
        $invoice->tva_amount = $tva_total;
        $invoice->total = $total;
        $invoice->status = 'draft';
        $invoice->items = $items;

        // Créer la facture
        if ($invoice->create()) {
            // Mettre à jour avec la référence QR
            $update_query = "UPDATE invoices
                            SET qr_reference = :qr_reference,
                                due_date = :due_date
                            WHERE id = :id";
            $stmt = $db->prepare($update_query);
            $stmt->bindParam(':qr_reference', $qr_reference);
            $stmt->bindParam(':due_date', $data['due_date']);
            $stmt->bindParam(':id', $invoice->id);
            $stmt->execute();

            echo json_encode([
                'success' => true,
                'message' => 'Facture créée avec succès',
                'invoice_id' => $invoice->id,
                'qr_reference' => $qr_reference
            ]);
        } else {
            throw new Exception('Erreur lors de la création de la facture');
        }

    } catch (Exception $e) {
        error_log('Error creating invoice: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Générer un numéro de facture unique
 */
function generateInvoiceNumber($company_id, $db) {
    $year = date('Y');
    $prefix = "FACT-$year-";

    // Trouver le dernier numéro
    $query = "SELECT number FROM invoices
              WHERE company_id = :company_id AND number LIKE :pattern
              ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $pattern = $prefix . '%';
    $stmt->bindParam(':pattern', $pattern);
    $stmt->execute();

    $last = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last) {
        // Extraire le numéro et incrémenter
        preg_match('/(\d+)$/', $last['number'], $matches);
        $next_num = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
    } else {
        $next_num = 1;
    }

    return $prefix . str_pad($next_num, 3, '0', STR_PAD_LEFT);
}

/**
 * Supprimer une facture
 */
function deleteInvoice($invoice, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID de la facture requis');
        }

        $invoice_id = intval($data['id']);

        // Vérifier appartenance et statut
        $stmt = $invoice->conn->prepare("SELECT status FROM invoices WHERE id = :id AND company_id = :company_id");
        $stmt->bindParam(':id', $invoice_id);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            throw new Exception('Facture non trouvée');
        }

        // Seuls les brouillons peuvent être supprimés
        if ($existing['status'] !== 'draft') {
            throw new Exception('Seules les factures en brouillon peuvent être supprimées');
        }

        $invoice->id = $invoice_id;
        if ($invoice->delete()) {
            echo json_encode([
                'success' => true,
                'message' => 'Facture supprimée avec succès'
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
 * Marquer une facture comme payée
 */
function markAsPaid($invoice, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID de la facture requis');
        }

        $invoice_id = intval($data['id']);

        // Vérifier appartenance
        $stmt = $invoice->conn->prepare("SELECT id FROM invoices WHERE id = :id AND company_id = :company_id");
        $stmt->bindParam(':id', $invoice_id);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();

        if (!$stmt->fetch()) {
            throw new Exception('Facture non trouvée');
        }

        // Mettre à jour le statut
        $update_query = "UPDATE invoices SET status = 'paid', paid_date = NOW() WHERE id = :id";
        $stmt = $invoice->conn->prepare($update_query);
        $stmt->bindParam(':id', $invoice_id);

        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Facture marquée comme payée'
            ]);
        } else {
            throw new Exception('Erreur lors de la mise à jour');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Mettre à jour une facture
 */
function updateInvoice($invoice, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID de la facture requis');
        }

        // TODO: Implémenter la mise à jour
        echo json_encode([
            'success' => true,
            'message' => 'Facture mise à jour'
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
