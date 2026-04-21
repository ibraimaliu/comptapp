<?php
/**
 * API AJAX pour le Dashboard de Trésorerie
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET, POST');

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
require_once '../../models/TreasuryForecast.php';
require_once '../../models/TreasuryAlert.php';
require_once '../../models/TreasurySettings.php';

$database = new Database();
$db = $database->getConnection();

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Récupérer l'action
$action = $_GET['action'] ?? ($_POST['action'] ?? null);

try {
    switch($action) {
        // ============================================
        // PRÉVISIONS
        // ============================================
        case 'get_forecasts':
            $forecast = new TreasuryForecast($db);

            $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime("+$days days"));

            $forecasts = $forecast->readByCompany($company_id, $start_date, $end_date);

            echo json_encode([
                'success' => true,
                'data' => $forecasts
            ]);
            break;

        case 'generate_forecasts':
            $forecast = new TreasuryForecast($db);
            $settings = new TreasurySettings($db);

            $settings_data = $settings->getByCompany($company_id);
            $horizon_days = $settings_data ? intval($settings_data['forecast_horizon_days']) : 90;

            // Générer les prévisions
            $generated = $forecast->generateForecasts($company_id, $horizon_days);

            // Sauvegarder en masse
            if ($forecast->saveBulkForecasts($company_id, $generated)) {
                // Vérifier et créer des alertes
                $alert = new TreasuryAlert($db);
                $alert->checkAndCreateAlerts($company_id);

                echo json_encode([
                    'success' => true,
                    'message' => 'Prévisions générées avec succès',
                    'count' => count($generated)
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la sauvegarde des prévisions'
                ]);
            }
            break;

        case 'get_stats':
            $forecast = new TreasuryForecast($db);

            $days = isset($_GET['days']) ? intval($_GET['days']) : 30;
            $stats = $forecast->getTreasuryStats($company_id, $days);

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        case 'update_forecast':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id) || !isset($data->forecast_date)) {
                echo json_encode(['success' => false, 'message' => 'Données manquantes']);
                break;
            }

            $forecast = new TreasuryForecast($db);
            $forecast->id = $data->id;
            $forecast->company_id = $company_id;
            $forecast->forecast_date = $data->forecast_date;
            $forecast->expected_income = $data->expected_income ?? 0;
            $forecast->expected_expenses = $data->expected_expenses ?? 0;
            $forecast->actual_income = $data->actual_income ?? 0;
            $forecast->actual_expenses = $data->actual_expenses ?? 0;
            $forecast->opening_balance = $data->opening_balance ?? 0;
            $forecast->closing_balance = $data->closing_balance ?? 0;
            $forecast->notes = $data->notes ?? '';
            $forecast->is_actual = $data->is_actual ?? 0;

            if ($forecast->update()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Prévision mise à jour'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la mise à jour'
                ]);
            }
            break;

        // ============================================
        // ALERTES
        // ============================================
        case 'get_alerts':
            $alert = new TreasuryAlert($db);
            $alerts = $alert->readActiveByCompany($company_id);

            echo json_encode([
                'success' => true,
                'data' => $alerts
            ]);
            break;

        case 'get_alert_counts':
            $alert = new TreasuryAlert($db);
            $counts = $alert->countAlertsBySeverity($company_id);

            echo json_encode([
                'success' => true,
                'data' => $counts
            ]);
            break;

        case 'resolve_alert':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $alert = new TreasuryAlert($db);
            if ($alert->resolve($data->id, $company_id, $user_id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Alerte résolue'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la résolution'
                ]);
            }
            break;

        case 'ignore_alert':
            $data = json_decode(file_get_contents("php://input"));

            if (!isset($data->id)) {
                echo json_encode(['success' => false, 'message' => 'ID manquant']);
                break;
            }

            $alert = new TreasuryAlert($db);
            if ($alert->ignore($data->id, $company_id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Alerte ignorée'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de l\'ignorance'
                ]);
            }
            break;

        case 'check_alerts':
            $alert = new TreasuryAlert($db);
            $alert->checkAndCreateAlerts($company_id);

            echo json_encode([
                'success' => true,
                'message' => 'Vérification des alertes effectuée'
            ]);
            break;

        // ============================================
        // PARAMÈTRES
        // ============================================
        case 'get_settings':
            $settings = new TreasurySettings($db);
            $data = $settings->getByCompany($company_id);

            echo json_encode([
                'success' => true,
                'data' => $data
            ]);
            break;

        case 'save_settings':
            $data = json_decode(file_get_contents("php://input"));

            $settings = new TreasurySettings($db);
            $settings_array = [
                'min_balance_alert' => $data->min_balance_alert ?? 5000,
                'critical_balance_alert' => $data->critical_balance_alert ?? 1000,
                'forecast_horizon_days' => $data->forecast_horizon_days ?? 90,
                'alert_email_enabled' => $data->alert_email_enabled ?? false,
                'alert_email_recipients' => $data->alert_email_recipients ?? '',
                'working_capital_target' => $data->working_capital_target ?? null
            ];

            if ($settings->saveSettings($company_id, $settings_array)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Paramètres sauvegardés'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la sauvegarde'
                ]);
            }
            break;

        // ============================================
        // DASHBOARD COMPLET
        // ============================================
        case 'get_dashboard_data':
            $forecast = new TreasuryForecast($db);
            $alert = new TreasuryAlert($db);
            $settings = new TreasurySettings($db);

            $days = isset($_GET['days']) ? intval($_GET['days']) : 30;

            // Récupérer toutes les données
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime("+$days days"));

            $forecasts = $forecast->readByCompany($company_id, $start_date, $end_date);
            $stats = $forecast->getTreasuryStats($company_id, $days);
            $alerts = $alert->readActiveByCompany($company_id);
            $alert_counts = $alert->countAlertsBySeverity($company_id);
            $settings_data = $settings->getByCompany($company_id);

            echo json_encode([
                'success' => true,
                'data' => [
                    'forecasts' => $forecasts,
                    'stats' => $stats,
                    'alerts' => $alerts,
                    'alert_counts' => $alert_counts,
                    'settings' => $settings_data
                ]
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
    error_log("Treasury Dashboard Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}
?>
