<?php
/**
 * API Employees
 * Gestion des employés avec restrictions par plan d'abonnement
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
require_once dirname(__DIR__, 2) . '/models/Employee.php';
require_once dirname(__DIR__, 2) . '/utils/EmployeeLimits.php';

$database = new Database();
$db = $database->getConnection();

$employee = new Employee($db);
$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Récupérer le tenant_code pour les vérifications de limites
$tenant_code = $_SESSION['tenant_code'] ?? $_SESSION['tenant_database'];

// Déterminer l'action
$request_method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);
$action = $data['action'] ?? $_GET['action'] ?? '';

try {
    switch($action) {
        // ========== LISTER LES EMPLOYÉS ==========
        case 'list':
            $active_only = isset($_GET['active_only']) && $_GET['active_only'] === 'true';
            $employees = $employee->readByCompany($company_id, $active_only);

            echo json_encode([
                'success' => true,
                'data' => $employees,
                'total' => count($employees)
            ]);
            break;

        // ========== OBTENIR UN EMPLOYÉ ==========
        case 'get':
            $employee_id = $data['id'] ?? $_GET['id'] ?? null;

            if(!$employee_id) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID employé manquant']);
                exit;
            }

            $employee->id = $employee_id;
            $employee->company_id = $company_id;

            if($employee->read()) {
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'id' => $employee->id,
                        'company_id' => $employee->company_id,
                        'employee_number' => $employee->employee_number,
                        'first_name' => $employee->first_name,
                        'last_name' => $employee->last_name,
                        'email' => $employee->email,
                        'phone' => $employee->phone,
                        'address' => $employee->address,
                        'postal_code' => $employee->postal_code,
                        'city' => $employee->city,
                        'country' => $employee->country,
                        'hire_date' => $employee->hire_date,
                        'termination_date' => $employee->termination_date,
                        'job_title' => $employee->job_title,
                        'department' => $employee->department,
                        'employment_type' => $employee->employment_type,
                        'contract_type' => $employee->contract_type,
                        'salary_type' => $employee->salary_type,
                        'base_salary' => $employee->base_salary,
                        'currency' => $employee->currency,
                        'hours_per_week' => $employee->hours_per_week,
                        'avs_number' => $employee->avs_number,
                        'accident_insurance' => $employee->accident_insurance,
                        'pension_fund' => $employee->pension_fund,
                        'iban' => $employee->iban,
                        'bank_name' => $employee->bank_name,
                        'family_allowances' => $employee->family_allowances,
                        'num_children' => $employee->num_children,
                        'is_active' => $employee->is_active,
                        'notes' => $employee->notes
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Employé non trouvé']);
            }
            break;

        // ========== CRÉER UN EMPLOYÉ ==========
        case 'create':
            // Vérifier les limites du plan
            $database_master = new DatabaseMaster();
            $db_master = $database_master->getConnection();

            $limits = EmployeeLimits::canCreateEmployee($db_master, $tenant_code, $company_id);

            if(!$limits['allowed']) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => $limits['message'],
                    'limits' => $limits
                ]);
                exit;
            }

            // Validation des champs requis
            if(empty($data['first_name']) || empty($data['last_name']) ||
               empty($data['job_title']) || empty($data['hire_date']) ||
               !isset($data['base_salary'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Champs requis manquants (prénom, nom, poste, date d\'embauche, salaire de base)'
                ]);
                exit;
            }

            // Générer le numéro d'employé si non fourni
            if(empty($data['employee_number'])) {
                $data['employee_number'] = $employee->generateEmployeeNumber($company_id);
            }

            // Remplir les propriétés
            $employee->company_id = $company_id;
            $employee->employee_number = $data['employee_number'];
            $employee->first_name = $data['first_name'];
            $employee->last_name = $data['last_name'];
            $employee->email = $data['email'] ?? null;
            $employee->phone = $data['phone'] ?? null;
            $employee->address = $data['address'] ?? null;
            $employee->postal_code = $data['postal_code'] ?? null;
            $employee->city = $data['city'] ?? null;
            $employee->country = $data['country'] ?? 'Suisse';
            $employee->hire_date = $data['hire_date'];
            $employee->termination_date = $data['termination_date'] ?? null;
            $employee->job_title = $data['job_title'];
            $employee->department = $data['department'] ?? null;
            $employee->employment_type = $data['employment_type'] ?? 'full_time';
            $employee->contract_type = $data['contract_type'] ?? 'cdi';
            $employee->salary_type = $data['salary_type'] ?? 'monthly';
            $employee->base_salary = $data['base_salary'];
            $employee->currency = $data['currency'] ?? 'CHF';
            $employee->hours_per_week = $data['hours_per_week'] ?? 40.00;
            $employee->avs_number = $data['avs_number'] ?? null;
            $employee->accident_insurance = $data['accident_insurance'] ?? null;
            $employee->pension_fund = $data['pension_fund'] ?? null;
            $employee->iban = $data['iban'] ?? null;
            $employee->bank_name = $data['bank_name'] ?? null;
            $employee->family_allowances = $data['family_allowances'] ?? 0;
            $employee->num_children = $data['num_children'] ?? 0;
            $employee->is_active = $data['is_active'] ?? 1;
            $employee->notes = $data['notes'] ?? null;

            if($employee->create()) {
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Employé créé avec succès',
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création']);
            }
            break;

        // ========== MODIFIER UN EMPLOYÉ ==========
        case 'update':
            if(empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID employé manquant']);
                exit;
            }

            // Validation des champs requis
            if(empty($data['first_name']) || empty($data['last_name']) ||
               empty($data['job_title']) || !isset($data['base_salary'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Champs requis manquants']);
                exit;
            }

            $employee->id = $data['id'];
            $employee->company_id = $company_id;
            $employee->employee_number = $data['employee_number'];
            $employee->first_name = $data['first_name'];
            $employee->last_name = $data['last_name'];
            $employee->email = $data['email'] ?? null;
            $employee->phone = $data['phone'] ?? null;
            $employee->address = $data['address'] ?? null;
            $employee->postal_code = $data['postal_code'] ?? null;
            $employee->city = $data['city'] ?? null;
            $employee->country = $data['country'] ?? 'Suisse';
            $employee->hire_date = $data['hire_date'];
            $employee->termination_date = $data['termination_date'] ?? null;
            $employee->job_title = $data['job_title'];
            $employee->department = $data['department'] ?? null;
            $employee->employment_type = $data['employment_type'] ?? 'full_time';
            $employee->contract_type = $data['contract_type'] ?? 'cdi';
            $employee->salary_type = $data['salary_type'] ?? 'monthly';
            $employee->base_salary = $data['base_salary'];
            $employee->currency = $data['currency'] ?? 'CHF';
            $employee->hours_per_week = $data['hours_per_week'] ?? 40.00;
            $employee->avs_number = $data['avs_number'] ?? null;
            $employee->accident_insurance = $data['accident_insurance'] ?? null;
            $employee->pension_fund = $data['pension_fund'] ?? null;
            $employee->iban = $data['iban'] ?? null;
            $employee->bank_name = $data['bank_name'] ?? null;
            $employee->family_allowances = $data['family_allowances'] ?? 0;
            $employee->num_children = $data['num_children'] ?? 0;
            $employee->is_active = $data['is_active'] ?? 1;
            $employee->notes = $data['notes'] ?? null;

            if($employee->update()) {
                echo json_encode(['success' => true, 'message' => 'Employé modifié avec succès']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la modification']);
            }
            break;

        // ========== DÉSACTIVER UN EMPLOYÉ ==========
        case 'deactivate':
            if(empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID employé manquant']);
                exit;
            }

            $employee->id = $data['id'];
            $employee->company_id = $company_id;

            if($employee->deactivate()) {
                echo json_encode(['success' => true, 'message' => 'Employé désactivé avec succès']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la désactivation']);
            }
            break;

        // ========== SUPPRIMER UN EMPLOYÉ ==========
        case 'delete':
            if(empty($data['id'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'ID employé manquant']);
                exit;
            }

            $employee->id = $data['id'];
            $employee->company_id = $company_id;

            if($employee->delete()) {
                echo json_encode(['success' => true, 'message' => 'Employé supprimé avec succès']);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression']);
            }
            break;

        // ========== RECHERCHER DES EMPLOYÉS ==========
        case 'search':
            $search_term = $data['term'] ?? $_GET['term'] ?? '';

            if(empty($search_term)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }

            $results = $employee->search($company_id, $search_term);

            echo json_encode([
                'success' => true,
                'data' => $results,
                'total' => count($results)
            ]);
            break;

        // ========== VÉRIFIER LES LIMITES ==========
        case 'check_limits':
            $database_master = new DatabaseMaster();
            $db_master = $database_master->getConnection();

            $limits = EmployeeLimits::getEmployeeLimits($db_master, $tenant_code, $company_id);

            echo json_encode([
                'success' => true,
                'limits' => $limits
            ]);
            break;

        // ========== COMPTER LES EMPLOYÉS ACTIFS ==========
        case 'count':
            $count = $employee->countActiveByCompany($company_id);

            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }

} catch(PDOException $e) {
    error_log("Erreur employees.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
} catch(Exception $e) {
    error_log("Erreur employees.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur: ' . $e->getMessage()
    ]);
}
?>
