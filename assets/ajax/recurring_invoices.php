<?php
/**
 * API AJAX pour la gestion des factures récurrentes
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

session_name('COMPTAPP_SESSION');
session_start();

// Vérifier l'authentification
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Vérifier la sélection de l'entreprise
if(!isset($_SESSION['company_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Aucune entreprise sélectionnée']);
    exit;
}

require_once '../../config/database.php';
require_once '../../models/RecurringInvoice.php';
require_once '../../models/Subscription.php';

$database = new Database();
$db = $database->getConnection();

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Récupérer l'action
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    switch($action) {
        // ============================================
        // FACTURES RÉCURRENTES
        // ============================================
        case 'list_recurring':
            $recurring = new RecurringInvoice($db);
            $status = $_GET['status'] ?? null;
            $data = $recurring->readByCompany($company_id, $status);

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;

        case 'get_recurring':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $recurring = new RecurringInvoice($db);
            $recurring->id = $id;
            $recurring->company_id = $company_id;
            $data = $recurring->read();

            if ($data) {
                // Récupérer aussi les items
                $items = $recurring->getItems($id);
                $data['items'] = $items;

                echo json_encode([
                    'success' => true,
                    'data' => $data
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Facture récurrente introuvable']);
            }
            break;

        case 'create_recurring':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->template_name) || !isset($data->contact_id) || !isset($data->frequency)) {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
                break;
            }

            $recurring = new RecurringInvoice($db);
            $recurring->company_id = $company_id;
            $recurring->template_name = $data->template_name;
            $recurring->contact_id = $data->contact_id;
            $recurring->status = $data->status ?? 'active';
            $recurring->frequency = $data->frequency;
            $recurring->start_date = $data->start_date ?? date('Y-m-d');
            $recurring->end_date = $data->end_date ?? null;
            $recurring->next_generation_date = $data->next_generation_date ?? date('Y-m-d');
            $recurring->last_generation_date = null;
            $recurring->occurrences_count = 0;
            $recurring->max_occurrences = $data->max_occurrences ?? null;
            $recurring->invoice_prefix = $data->invoice_prefix ?? 'FACT';
            $recurring->payment_terms_days = $data->payment_terms_days ?? 30;
            $recurring->currency = $data->currency ?? 'CHF';
            $recurring->notes = $data->notes ?? '';
            $recurring->footer_text = $data->footer_text ?? '';
            $recurring->auto_send_email = $data->auto_send_email ?? 0;
            $recurring->email_template_id = $data->email_template_id ?? null;
            $recurring->auto_mark_sent = $data->auto_mark_sent ?? 1;
            $recurring->created_by = $user_id;

            if ($recurring->create()) {
                // Sauvegarder les items
                if (isset($data->items) && is_array($data->items)) {
                    $recurring->saveItems($recurring->id, $data->items);
                }

                echo json_encode([
                    'success' => true,
                    'id' => $recurring->id,
                    'message' => 'Facture récurrente créée avec succès'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
            }
            break;

        case 'update_recurring':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $recurring = new RecurringInvoice($db);
            $recurring->id = $data->id;
            $recurring->company_id = $company_id;
            $recurring->template_name = $data->template_name;
            $recurring->contact_id = $data->contact_id;
            $recurring->status = $data->status;
            $recurring->frequency = $data->frequency;
            $recurring->start_date = $data->start_date;
            $recurring->end_date = $data->end_date ?? null;
            $recurring->next_generation_date = $data->next_generation_date;
            $recurring->max_occurrences = $data->max_occurrences ?? null;
            $recurring->invoice_prefix = $data->invoice_prefix ?? 'FACT';
            $recurring->payment_terms_days = $data->payment_terms_days ?? 30;
            $recurring->currency = $data->currency ?? 'CHF';
            $recurring->notes = $data->notes ?? '';
            $recurring->footer_text = $data->footer_text ?? '';
            $recurring->auto_send_email = $data->auto_send_email ?? 0;
            $recurring->auto_mark_sent = $data->auto_mark_sent ?? 1;

            if ($recurring->update()) {
                // Mettre à jour les items
                if (isset($data->items) && is_array($data->items)) {
                    $recurring->saveItems($data->id, $data->items);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Facture récurrente mise à jour'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            break;

        case 'delete_recurring':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $recurring = new RecurringInvoice($db);
            $recurring->id = $data->id;
            $recurring->company_id = $company_id;

            if ($recurring->delete()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Facture récurrente supprimée'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
            }
            break;

        case 'generate_invoice':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $recurring = new RecurringInvoice($db);
            $result = $recurring->generateInvoice($data->id, $company_id);

            echo json_encode($result);
            break;

        case 'change_status':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id) || !isset($data->status)) {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
                break;
            }

            $recurring = new RecurringInvoice($db);
            if ($recurring->updateStatus($data->id, $company_id, $data->status)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Statut mis à jour'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            break;

        case 'get_history':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $recurring = new RecurringInvoice($db);
            $history = $recurring->getHistory($id, $company_id);

            echo json_encode([
                'success' => true,
                'data' => $history
            ]);
            break;

        case 'get_stats':
            $recurring = new RecurringInvoice($db);
            $stats = $recurring->getStats($company_id);

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        // ============================================
        // ABONNEMENTS
        // ============================================
        case 'list_subscriptions':
            $subscription = new Subscription($db);
            $status = $_GET['status'] ?? null;
            $data = $subscription->readByCompany($company_id, $status);

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;

        case 'get_subscription':
            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $subscription = new Subscription($db);
            $subscription->id = $id;
            $subscription->company_id = $company_id;
            $data = $subscription->read();

            if ($data) {
                // Récupérer l'historique
                $events = $subscription->getEvents($id, $company_id);
                $data['events'] = $events;

                echo json_encode([
                    'success' => true,
                    'data' => $data
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Abonnement introuvable']);
            }
            break;

        case 'create_subscription':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->subscription_name) || !isset($data->contact_id) || !isset($data->amount)) {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
                break;
            }

            $subscription = new Subscription($db);
            $subscription->company_id = $company_id;
            $subscription->contact_id = $data->contact_id;
            $subscription->recurring_invoice_id = $data->recurring_invoice_id ?? null;
            $subscription->subscription_name = $data->subscription_name;
            $subscription->subscription_type = $data->subscription_type ?? 'service';
            $subscription->status = $data->status ?? 'active';
            $subscription->start_date = $data->start_date ?? date('Y-m-d');
            $subscription->trial_end_date = $data->trial_end_date ?? null;
            $subscription->current_period_start = $data->current_period_start ?? date('Y-m-d');
            $subscription->current_period_end = $data->current_period_end;
            $subscription->cancel_at_period_end = $data->cancel_at_period_end ?? 0;
            $subscription->amount = $data->amount;
            $subscription->currency = $data->currency ?? 'CHF';
            $subscription->billing_cycle = $data->billing_cycle ?? 'monthly';
            $subscription->auto_renew = $data->auto_renew ?? 1;
            $subscription->renewal_reminder_days = $data->renewal_reminder_days ?? 7;
            $subscription->metadata = isset($data->metadata) ? json_encode($data->metadata) : null;

            if ($subscription->create()) {
                echo json_encode([
                    'success' => true,
                    'id' => $subscription->id,
                    'message' => 'Abonnement créé avec succès'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
            }
            break;

        case 'update_subscription':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $subscription = new Subscription($db);
            $subscription->id = $data->id;
            $subscription->company_id = $company_id;
            $subscription->subscription_name = $data->subscription_name;
            $subscription->amount = $data->amount;
            $subscription->billing_cycle = $data->billing_cycle;
            $subscription->auto_renew = $data->auto_renew ?? 1;
            $subscription->renewal_reminder_days = $data->renewal_reminder_days ?? 7;
            $subscription->metadata = isset($data->metadata) ? json_encode($data->metadata) : null;

            if ($subscription->update()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Abonnement mis à jour'
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            break;

        case 'renew_subscription':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $subscription = new Subscription($db);
            $result = $subscription->renew($data->id, $company_id);

            echo json_encode($result);
            break;

        case 'cancel_subscription':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $immediate = $data->immediate ?? false;
            $subscription = new Subscription($db);
            $result = $subscription->cancel($data->id, $company_id, $immediate);

            echo json_encode($result);
            break;

        case 'pause_subscription':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $subscription = new Subscription($db);
            $result = $subscription->pause($data->id, $company_id);

            echo json_encode($result);
            break;

        case 'reactivate_subscription':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $subscription = new Subscription($db);
            $result = $subscription->reactivate($data->id, $company_id);

            echo json_encode($result);
            break;

        case 'get_subscription_stats':
            $subscription = new Subscription($db);
            $stats = $subscription->getStats($company_id);

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Action non reconnue'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("Recurring Invoices API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>
