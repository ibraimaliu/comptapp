<?php
/**
 * API AJAX: Gestion des Factures Fournisseurs
 * Version: 1.0
 */

session_name('COMPTAPP_SESSION');
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier la session
if (!isset($_SESSION['company_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Inclure les modèles
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/models/SupplierInvoice.php';
require_once dirname(dirname(__DIR__)) . '/models/Payment.php';

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

$supplierInvoice = new SupplierInvoice($db);
$payment = new Payment($db);

// Déterminer l'action
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $action = $_GET['action'] ?? 'list';
}

try {
    switch ($action) {
        case 'create':
            createSupplierInvoice($supplierInvoice, $input, $company_id, $user_id);
            break;

        case 'update':
            updateSupplierInvoice($supplierInvoice, $input, $company_id);
            break;

        case 'delete':
            deleteSupplierInvoice($supplierInvoice, $input, $company_id);
            break;

        case 'approve':
            approveSupplierInvoice($supplierInvoice, $input, $company_id, $user_id);
            break;

        case 'mark_paid':
            markSupplierInvoiceAsPaid($supplierInvoice, $payment, $input, $company_id, $user_id);
            break;

        case 'list':
        default:
            listSupplierInvoices($supplierInvoice, $company_id);
            break;
    }

} catch (Exception $e) {
    error_log('Error in supplier_invoices.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * Lister les factures fournisseurs
 */
function listSupplierInvoices($supplierInvoice, $company_id) {
    try {
        $filters = [];

        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        if (!empty($_GET['supplier_id'])) {
            $filters['supplier_id'] = intval($_GET['supplier_id']);
        }

        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }

        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        $invoices = $supplierInvoice->readByCompany($company_id, $filters);

        echo json_encode([
            'success' => true,
            'invoices' => $invoices,
            'count' => count($invoices)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors du chargement des factures: ' . $e->getMessage()
        ]);
    }
}

/**
 * Créer une facture fournisseur
 */
function createSupplierInvoice($supplierInvoice, $data, $company_id, $user_id) {
    try {
        // Validation
        if (empty($data['supplier_id'])) {
            throw new Exception('Fournisseur requis');
        }

        if (empty($data['invoice_number'])) {
            throw new Exception('Numéro de facture requis');
        }

        if (empty($data['invoice_date'])) {
            throw new Exception('Date de facture requise');
        }

        if (empty($data['due_date'])) {
            throw new Exception('Date d\'échéance requise');
        }

        if (empty($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
            throw new Exception('Au moins une ligne est requise');
        }

        // Préparer la facture
        $supplierInvoice->company_id = $company_id;
        $supplierInvoice->supplier_id = intval($data['supplier_id']);
        $supplierInvoice->invoice_number = htmlspecialchars(strip_tags($data['invoice_number']));
        $supplierInvoice->invoice_date = $data['invoice_date'];
        $supplierInvoice->due_date = $data['due_date'];
        $supplierInvoice->reception_date = $data['reception_date'] ?? date('Y-m-d');
        $supplierInvoice->subtotal = floatval($data['subtotal']);
        $supplierInvoice->tva_amount = floatval($data['tva_amount']);
        $supplierInvoice->total = floatval($data['total']);
        $supplierInvoice->status = 'received';
        $supplierInvoice->qr_reference = $data['qr_reference'] ?? null;
        $supplierInvoice->iban = $data['iban'] ?? null;
        $supplierInvoice->notes = $data['notes'] ?? null;
        $supplierInvoice->created_by = $user_id;
        $supplierInvoice->items = $data['items'];

        // Créer la facture
        if ($supplierInvoice->create()) {
            echo json_encode([
                'success' => true,
                'message' => 'Facture créée avec succès',
                'invoice_id' => $supplierInvoice->id
            ]);
        } else {
            throw new Exception('Erreur lors de la création de la facture');
        }

    } catch (Exception $e) {
        error_log('Error creating supplier invoice: ' . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Mettre à jour une facture
 */
function updateSupplierInvoice($supplierInvoice, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID de la facture requis');
        }

        $supplierInvoice->id = intval($data['id']);
        $supplierInvoice->company_id = $company_id;

        // Vérifier que la facture existe
        if (!$supplierInvoice->read()) {
            throw new Exception('Facture non trouvée');
        }

        // Mettre à jour les champs
        if (isset($data['supplier_id'])) $supplierInvoice->supplier_id = intval($data['supplier_id']);
        if (isset($data['invoice_number'])) $supplierInvoice->invoice_number = htmlspecialchars(strip_tags($data['invoice_number']));
        if (isset($data['invoice_date'])) $supplierInvoice->invoice_date = $data['invoice_date'];
        if (isset($data['due_date'])) $supplierInvoice->due_date = $data['due_date'];
        if (isset($data['notes'])) $supplierInvoice->notes = $data['notes'];

        if ($supplierInvoice->update()) {
            echo json_encode([
                'success' => true,
                'message' => 'Facture mise à jour'
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
 * Supprimer une facture
 */
function deleteSupplierInvoice($supplierInvoice, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID de la facture requis');
        }

        $invoice_id = intval($data['id']);

        $supplierInvoice->id = $invoice_id;
        $supplierInvoice->company_id = $company_id;

        // Vérifier que la facture existe et est en statut 'received'
        if (!$supplierInvoice->read()) {
            throw new Exception('Facture non trouvée');
        }

        if ($supplierInvoice->status !== 'received') {
            throw new Exception('Seules les factures en statut "Reçue" peuvent être supprimées');
        }

        if ($supplierInvoice->delete()) {
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
 * Approuver une facture
 */
function approveSupplierInvoice($supplierInvoice, $data, $company_id, $user_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID de la facture requis');
        }

        $supplierInvoice->id = intval($data['id']);
        $supplierInvoice->company_id = $company_id;

        // Vérifier que la facture existe
        if (!$supplierInvoice->read()) {
            throw new Exception('Facture non trouvée');
        }

        if ($supplierInvoice->approve($user_id)) {
            echo json_encode([
                'success' => true,
                'message' => 'Facture approuvée'
            ]);
        } else {
            throw new Exception('Erreur lors de l\'approbation');
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
function markSupplierInvoiceAsPaid($supplierInvoice, $payment, $data, $company_id, $user_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID de la facture requis');
        }

        if (empty($data['payment_date'])) {
            throw new Exception('Date de paiement requise');
        }

        $supplierInvoice->id = intval($data['id']);
        $supplierInvoice->company_id = $company_id;

        // Vérifier que la facture existe
        if (!$supplierInvoice->read()) {
            throw new Exception('Facture non trouvée');
        }

        // Créer le paiement
        $payment->company_id = $company_id;
        $payment->payment_date = $data['payment_date'];
        $payment->amount = $supplierInvoice->total;
        $payment->currency = 'CHF';
        $payment->payment_method = $data['payment_method'] ?? 'bank_transfer';
        $payment->payment_type = 'supplier_payment';
        $payment->supplier_invoice_id = $supplierInvoice->id;
        $payment->contact_id = $supplierInvoice->supplier_id;
        $payment->description = 'Paiement facture ' . $supplierInvoice->invoice_number;
        $payment->created_by = $user_id;

        if ($payment->create()) {
            // Le trigger se charge de mettre à jour le statut de la facture
            echo json_encode([
                'success' => true,
                'message' => 'Paiement enregistré et facture marquée comme payée'
            ]);
        } else {
            throw new Exception('Erreur lors de l\'enregistrement du paiement');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
