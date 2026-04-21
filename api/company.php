<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../config/config.php';
include_once '../config/database_master.php';
include_once '../models/Company.php';
include_once '../models/AccountingPlan.php';
include_once '../utils/TenantLimits.php';

// Vérifier si l'utilisateur est connecté
if(!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(array("message" => "Non autorisé"));
    exit;
}

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Initialiser l'objet Company
$company = new Company($db);
$user_id = $_SESSION['user_id'];

// Déterminer la méthode de requête
$method = $_SERVER['REQUEST_METHOD'];

switch($method) {
    case 'GET':
        // Récupérer une société spécifique
        if(isset($_GET['id'])) {
            $company->id = $_GET['id'];
            if($company->read()) {
                // Vérifier que l'utilisateur est bien le propriétaire de la société
                if($company->user_id != $user_id) {
                    http_response_code(403);
                    echo json_encode(array("message" => "Accès non autorisé à cette société"));
                    exit;
                }
                
                http_response_code(200);
                echo json_encode(array(
                    "id" => $company->id,
                    "name" => $company->name,
                    "owner_name" => $company->owner_name,
                    "owner_surname" => $company->owner_surname,
                    "fiscal_year_start" => $company->fiscal_year_start,
                    "fiscal_year_end" => $company->fiscal_year_end,
                    "tva_status" => $company->tva_status,
                    "created_at" => $company->created_at
                ));
            } else {
                http_response_code(404);
                echo json_encode(array("message" => "Société non trouvée"));
            }
        } 
        // Récupérer toutes les sociétés de l'utilisateur
        else {
            $stmt = $company->readByUser($user_id);
            $num = $stmt->rowCount();
            
            if($num > 0) {
                $companies_arr = array();
                
                while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    extract($row);
                    
                    $company_item = array(
                        "id" => $id,
                        "name" => $name,
                        "owner_name" => $owner_name,
                        "owner_surname" => $owner_surname,
                        "fiscal_year_start" => $fiscal_year_start,
                        "fiscal_year_end" => $fiscal_year_end,
                        "tva_status" => $tva_status,
                        "created_at" => $created_at
                    );
                    
                    array_push($companies_arr, $company_item);
                }
                
                http_response_code(200);
                echo json_encode($companies_arr);
            } else {
                http_response_code(200);
                echo json_encode(array());
            }
        }
        break;
        
    case 'POST':
        // Créer une société
        $data = json_decode(file_get_contents("php://input"));

        if(
            !empty($data->name) &&
            !empty($data->owner_name) &&
            !empty($data->owner_surname) &&
            !empty($data->fiscal_year_start) &&
            !empty($data->fiscal_year_end) &&
            !empty($data->tva_status)
        ) {
            // Vérifier les limites du plan d'abonnement
            if (isset($_SESSION['tenant_database'])) {
                $database_master = new DatabaseMaster();
                $db_master = $database_master->getConnection();

                $tenant_code = $_SESSION['tenant_database'];
                $limit_check = TenantLimits::canCreateCompany($db_master, $tenant_code, $user_id);

                if (!$limit_check['allowed']) {
                    http_response_code(403);
                    echo json_encode(array(
                        "message" => $limit_check['message'],
                        "current" => $limit_check['current'],
                        "max" => $limit_check['max'],
                        "plan_name" => $limit_check['plan_name']
                    ));
                    exit;
                }
            }

            $company->user_id = $user_id;
            $company->name = $data->name;
            $company->owner_name = $data->owner_name;
            $company->owner_surname = $data->owner_surname;
            $company->fiscal_year_start = $data->fiscal_year_start;
            $company->fiscal_year_end = $data->fiscal_year_end;
            $company->tva_status = $data->tva_status;

            if($company->create()) {
                // Importer le plan comptable par défaut
                $accountingPlan = new AccountingPlan($db);
                $accountingPlan->importDefaultPlan($company->id);

                // IMPORTANT: Définir automatiquement cette société comme société active
                $_SESSION['company_id'] = $company->id;

                http_response_code(201);
                echo json_encode(array(
                    "message" => "Société créée avec succès",
                    "id" => $company->id
                ));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Impossible de créer la société"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Données incomplètes"));
        }
        break;
        
    case 'PUT':
        // Mettre à jour une société
        $data = json_decode(file_get_contents("php://input"));
        
        if(
            !empty($data->id) &&
            !empty($data->name) &&
            !empty($data->owner_name) &&
            !empty($data->owner_surname) &&
            !empty($data->fiscal_year_start) &&
            !empty($data->fiscal_year_end) &&
            !empty($data->tva_status)
        ) {
            $company->id = $data->id;
            
            // Vérifier que l'utilisateur est bien le propriétaire de la société
            if($company->read() && $company->user_id != $user_id) {
                http_response_code(403);
                echo json_encode(array("message" => "Accès non autorisé à cette société"));
                exit;
            }
            
            $company->name = $data->name;
            $company->owner_name = $data->owner_name;
            $company->owner_surname = $data->owner_surname;
            $company->fiscal_year_start = $data->fiscal_year_start;
            $company->fiscal_year_end = $data->fiscal_year_end;
            $company->tva_status = $data->tva_status;
            
            if($company->update()) {
                http_response_code(200);
                echo json_encode(array("message" => "Société mise à jour avec succès"));
            } else {
                http_response_code(503);
                echo json_encode(array("message" => "Impossible de mettre à jour la société"));
            }
        } else {
            http_response_code(400);
            echo json_encode(array("message" => "Données incomplètes"));
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(array("message" => "Méthode non autorisée"));
        break;
}
?>