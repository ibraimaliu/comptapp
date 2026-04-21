<?php
/**
 * API Payroll
 * Gestion des fiches de paie et calculs automatiques
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');

session_name('COMPTAPP_SESSION');
session_start();

// Vérifier l'authentification
if(!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/config/database_master.php';
require_once dirname(__DIR__, 2) . '/models/Payroll.php';
require_once dirname(__DIR__, 2) . '/models/PayrollSettings.php';
require_once dirname(__DIR__, 2) . '/models/Employee.php';
require_once dirname(__DIR__, 2) . '/utils/EmployeeLimits.php';

$database = new Database();
$db = $database->getConnection();

$payroll = new Payroll($db);
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Vérifier si le module est activé pour ce plan
$tenant_code = $_SESSION['tenant_code'] ?? $_SESSION['tenant_database'];
$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();

if(!EmployeeLimits::isPayrollModuleEnabled($db_master, $tenant_code)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Le module de gestion des salaires n\'est pas activé pour votre plan. Passez au plan Starter ou supérieur.',
        'feature_locked' => true
    ]);
    exit;
}

// Déterminer l'action
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch($action) {
        // ========== LISTER LES FICHES DE PAIE ==========
        case 'list':
            $filters = [];
            if(isset($_GET['employee_id'])) $filters['employee_id'] = $_GET['employee_id'];
            if(isset($_GET['period_year'])) $filters['period_year'] = $_GET['period_year'];
            if(isset($_GET['period_month'])) $filters['period_month'] = $_GET['period_month'];
            if(isset($_GET['status'])) $filters['status'] = $_GET['status'];

            $payrolls = $payroll->readByCompany($company_id, $filters);

            echo json_encode([
                'success' => true,
                'data' => $payrolls,
                'total' => count($payrolls)
            ]);
            break;

        // ========== OBTENIR UNE FICHE DE PAIE ==========
        case 'get':
            $payroll_id = $data['id'] ?? $_GET['id'] ?? null;

            if(!$payroll_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID fiche de paie manquant']);
                exit;
            }

            $payroll->id = $payroll_id;
            $payroll->company_id = $company_id;

            $result = $payroll->read();

            if($result) {
                echo json_encode([
                    'success' => true,
                    'data' => $result
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Fiche de paie non trouvée']);
            }
            break;

        // ========== CRÉER UNE FICHE DE PAIE ==========
        case 'create':
        case 'generate':
            // Validation des champs requis
            if(empty($data['employee_id']) || empty($data['period_month']) ||
               empty($data['period_year']) || !isset($data['base_salary'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Champs requis manquants']);
                exit;
            }

            // Vérifier si une fiche existe déjà pour cette période
            if($payroll->exists($company_id, $data['employee_id'], $data['period_month'], $data['period_year'])) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'message' => 'Une fiche de paie existe déjà pour cette période'
                ]);
                exit;
            }

            // Récupérer les paramètres de paie
            $settings_model = new PayrollSettings($db);
            $settings = $settings_model->readByCompany($company_id);

            if(!$settings) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Paramètres de paie non configurés. Veuillez configurer les taux dans les paramètres.'
                ]);
                exit;
            }

            // Remplir les propriétés
            $payroll->company_id = $company_id;
            $payroll->employee_id = $data['employee_id'];
            $payroll->period_month = $data['period_month'];
            $payroll->period_year = $data['period_year'];
            $payroll->payment_date = $data['payment_date'] ?? null;

            // Salaire de base et éléments
            $payroll->base_salary = $data['base_salary'];
            $payroll->hours_worked = $data['hours_worked'] ?? null;
            $payroll->hourly_rate = $data['hourly_rate'] ?? null;
            $payroll->overtime_hours = $data['overtime_hours'] ?? 0;
            $payroll->overtime_amount = $data['overtime_amount'] ?? 0;
            $payroll->bonus = $data['bonus'] ?? 0;
            $payroll->commission = $data['commission'] ?? 0;
            $payroll->allowances = $data['allowances'] ?? 0;
            $payroll->other_additions = $data['other_additions'] ?? 0;

            // Calcul du salaire brut
            $payroll->calculateGrossSalary();

            // Calcul des cotisations sociales
            $payroll->calculateSocialContributions($settings);

            // Impôt et autres déductions
            $payroll->income_tax = $data['income_tax'] ?? 0;
            $payroll->other_deductions = $data['other_deductions'] ?? 0;

            // Recalculer le total des déductions et le net
            $payroll->total_deductions =
                $payroll->avs_ai_apg_employee +
                $payroll->ac_employee +
                $payroll->lpp_employee +
                $payroll->laa_employee +
                $payroll->laac_employee +
                $payroll->income_tax +
                $payroll->other_deductions;

            $payroll->net_salary = $payroll->gross_salary - $payroll->total_deductions;

            $payroll->status = 'draft';
            $payroll->notes = $data['notes'] ?? null;
            $payroll->created_by = $user_id;

            if($payroll->create()) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Fiche de paie créée avec succès',
                    'id' => $payroll->id,
                    'data' => [
                        'gross_salary' => $payroll->gross_salary,
                        'total_deductions' => $payroll->total_deductions,
                        'net_salary' => $payroll->net_salary,
                        'total_employer_charges' => $payroll->total_employer_charges,
                        'details' => [
                            'avs_ai_apg_employee' => $payroll->avs_ai_apg_employee,
                            'ac_employee' => $payroll->ac_employee,
                            'lpp_employee' => $payroll->lpp_employee,
                            'laa_employee' => $payroll->laa_employee,
                            'avs_ai_apg_employer' => $payroll->avs_ai_apg_employer,
                            'ac_employer' => $payroll->ac_employer,
                            'lpp_employer' => $payroll->lpp_employer,
                            'af_employer' => $payroll->af_employer
                        ]
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
            }
            break;

        // ========== MODIFIER UNE FICHE DE PAIE (seulement si draft) ==========
        case 'update':
            if(empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID fiche de paie manquant']);
                exit;
            }

            // Récupérer les paramètres de paie
            $settings_model = new PayrollSettings($db);
            $settings = $settings_model->readByCompany($company_id);

            $payroll->id = $data['id'];
            $payroll->company_id = $company_id;
            $payroll->payment_date = $data['payment_date'] ?? null;
            $payroll->base_salary = $data['base_salary'];
            $payroll->hours_worked = $data['hours_worked'] ?? null;
            $payroll->hourly_rate = $data['hourly_rate'] ?? null;
            $payroll->overtime_hours = $data['overtime_hours'] ?? 0;
            $payroll->overtime_amount = $data['overtime_amount'] ?? 0;
            $payroll->bonus = $data['bonus'] ?? 0;
            $payroll->commission = $data['commission'] ?? 0;
            $payroll->allowances = $data['allowances'] ?? 0;
            $payroll->other_additions = $data['other_additions'] ?? 0;

            // Recalculer
            $payroll->calculateGrossSalary();
            $payroll->calculateSocialContributions($settings);

            $payroll->income_tax = $data['income_tax'] ?? 0;
            $payroll->other_deductions = $data['other_deductions'] ?? 0;

            $payroll->total_deductions =
                $payroll->avs_ai_apg_employee +
                $payroll->ac_employee +
                $payroll->lpp_employee +
                $payroll->laa_employee +
                $payroll->laac_employee +
                $payroll->income_tax +
                $payroll->other_deductions;

            $payroll->net_salary = $payroll->gross_salary - $payroll->total_deductions;
            $payroll->notes = $data['notes'] ?? null;

            if($payroll->update()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Fiche de paie modifiée avec succès',
                    'data' => [
                        'gross_salary' => $payroll->gross_salary,
                        'total_deductions' => $payroll->total_deductions,
                        'net_salary' => $payroll->net_salary
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la modification (fiche peut-être déjà validée)'
                ]);
            }
            break;

        // ========== VALIDER UNE FICHE DE PAIE ==========
        case 'validate':
            if(empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID fiche de paie manquant']);
                exit;
            }

            $payroll->id = $data['id'];
            $payroll->company_id = $company_id;

            if($payroll->validate()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Fiche de paie validée avec succès'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la validation']);
            }
            break;

        // ========== MARQUER COMME PAYÉ ==========
        case 'mark_paid':
            if(empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID fiche de paie manquant']);
                exit;
            }

            $payroll->id = $data['id'];
            $payroll->company_id = $company_id;
            $transaction_id = $data['transaction_id'] ?? null;

            if($payroll->markAsPaid($transaction_id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Fiche de paie marquée comme payée'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            break;

        // ========== SUPPRIMER UNE FICHE DE PAIE (seulement si draft) ==========
        case 'delete':
            if(empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID fiche de paie manquant']);
                exit;
            }

            $payroll->id = $data['id'];
            $payroll->company_id = $company_id;

            if($payroll->delete()) {
                echo json_encode(['success' => true, 'message' => 'Fiche de paie supprimée avec succès']);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Erreur lors de la suppression (fiche peut-être déjà validée)'
                ]);
            }
            break;

        // ========== OBTENIR LES STATISTIQUES ==========
        case 'statistics':
            $year = $_GET['year'] ?? date('Y');
            $stats = $payroll->getCompanyStatistics($company_id, $year);

            echo json_encode([
                'success' => true,
                'data' => $stats
            ]);
            break;

        // ========== OBTENIR LES PARAMÈTRES DE PAIE ==========
        case 'get_settings':
            $settings_model = new PayrollSettings($db);
            $settings = $settings_model->readByCompany($company_id);

            echo json_encode([
                'success' => true,
                'data' => $settings
            ]);
            break;

        // ========== METTRE À JOUR LES PARAMÈTRES DE PAIE ==========
        case 'update_settings':
            $settings_model = new PayrollSettings($db);
            $settings_model->company_id = $company_id;
            $settings_model->avs_ai_apg_rate_employee = $data['avs_ai_apg_rate_employee'] ?? 5.30;
            $settings_model->avs_ai_apg_rate_employer = $data['avs_ai_apg_rate_employer'] ?? 5.30;
            $settings_model->ac_rate_employee = $data['ac_rate_employee'] ?? 1.10;
            $settings_model->ac_rate_employer = $data['ac_rate_employer'] ?? 1.10;
            $settings_model->ac_solidarity_rate = $data['ac_solidarity_rate'] ?? 0.50;
            $settings_model->ac_threshold = $data['ac_threshold'] ?? 148200.00;
            $settings_model->lpp_rate_employee = $data['lpp_rate_employee'] ?? 7.00;
            $settings_model->lpp_rate_employer = $data['lpp_rate_employer'] ?? 7.00;
            $settings_model->lpp_min_salary = $data['lpp_min_salary'] ?? 21510.00;
            $settings_model->lpp_max_salary = $data['lpp_max_salary'] ?? 86040.00;
            $settings_model->af_rate = $data['af_rate'] ?? 2.00;
            $settings_model->af_amount_per_child = $data['af_amount_per_child'] ?? 200.00;
            $settings_model->laa_rate = $data['laa_rate'] ?? 1.00;
            $settings_model->laac_rate = $data['laac_rate'] ?? 2.00;
            $settings_model->salary_expense_account = $data['salary_expense_account'] ?? null;
            $settings_model->social_charges_account = $data['social_charges_account'] ?? null;
            $settings_model->salary_payable_account = $data['salary_payable_account'] ?? null;

            if($settings_model->update()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Paramètres de paie mis à jour avec succès'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour']);
            }
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }

} catch(PDOException $e) {
    error_log("Erreur payroll.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Erreur payroll.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
