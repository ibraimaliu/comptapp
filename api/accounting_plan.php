<?php
// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Démarrer la session
session_name('COMPTAPP_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Vérifier l'authentification
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(array("success" => false, "message" => "Non autorisé. Veuillez vous connecter."));
    exit();
}

// Vérifier qu'une société est sélectionnée
if(!isset($_SESSION['company_id'])) {
    http_response_code(400);
    echo json_encode(array("success" => false, "message" => "Aucune société sélectionnée."));
    exit();
}

// Inclure les fichiers de configuration et modèles
include_once '../config/database.php';
include_once '../models/AccountingPlan.php';

// Initialiser la connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Initialiser l'objet AccountingPlan
$account = new AccountingPlan($db);

// Récupérer les données de la requête
$data = json_decode(file_get_contents("php://input"));

// Déterminer l'action à effectuer
$action = isset($data->action) ? $data->action : '';

// Si pas d'action dans le JSON, vérifier dans l'URL (GET)
if(empty($action) && isset($_GET['action'])) {
    $action = $_GET['action'];
}

switch($action) {
    case 'create':
        // Valider les données requises
        if(
            !empty($data->number) &&
            !empty($data->name) &&
            !empty($data->category) &&
            !empty($data->type)
        ) {
            // Vérifier si le numéro de compte existe déjà
            $query = "SELECT id FROM accounting_plan WHERE company_id = :company_id AND number = :number";
            $stmt = $db->prepare($query);
            $stmt->bindParam(":company_id", $_SESSION['company_id']);
            $stmt->bindParam(":number", $data->number);
            $stmt->execute();

            if($stmt->rowCount() > 0) {
                http_response_code(400);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Un compte avec ce numéro existe déjà."
                ));
                break;
            }

            // Assigner les valeurs
            $account->company_id = $_SESSION['company_id'];
            $account->number = $data->number;
            $account->name = $data->name;
            $account->category = $data->category;
            $account->type = $data->type;
            $account->is_used = false;

            // Créer le compte
            if($account->create()) {
                http_response_code(201);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Compte créé avec succès.",
                    "id" => $account->id
                ));
            } else {
                http_response_code(503);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Impossible de créer le compte."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Données incomplètes. Les champs number, name, category et type sont requis."
            ));
        }
        break;

    case 'read':
        // Lire un compte spécifique
        if(isset($data->id)) {
            $account->id = $data->id;

            if($account->read()) {
                // Vérifier que le compte appartient à la société de l'utilisateur
                if($account->company_id == $_SESSION['company_id']) {
                    http_response_code(200);
                    echo json_encode(array(
                        "success" => true,
                        "data" => array(
                            "id" => $account->id,
                            "company_id" => $account->company_id,
                            "number" => $account->number,
                            "name" => $account->name,
                            "category" => $account->category,
                            "type" => $account->type,
                            "is_used" => $account->is_used
                        )
                    ));
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à ce compte."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Compte non trouvé."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de compte manquant."
            ));
        }
        break;

    case 'list':
        // Lister tous les comptes de la société
        $company_id = $_SESSION['company_id'];
        $stmt = $account->readByCompany($company_id);
        $accounts = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $accounts[] = array(
                "id" => $row['id'],
                "number" => $row['number'],
                "name" => $row['name'],
                "category" => $row['category'],
                "type" => $row['type'],
                "is_used" => $row['is_used']
            );
        }

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "data" => $accounts,
            "count" => count($accounts)
        ));
        break;

    case 'update':
        // Valider les données requises
        if(
            !empty($data->id) &&
            !empty($data->name) &&
            !empty($data->category) &&
            !empty($data->type)
        ) {
            // Vérifier d'abord que le compte existe et appartient à l'utilisateur
            $account->id = $data->id;
            if($account->read()) {
                if($account->company_id == $_SESSION['company_id']) {
                    // Assigner les nouvelles valeurs
                    $account->name = $data->name;
                    $account->category = $data->category;
                    $account->type = $data->type;

                    // Mettre à jour le compte
                    if($account->update()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Compte mis à jour avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de mettre à jour le compte."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à ce compte."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Compte non trouvé."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Données incomplètes. Les champs id, name, category et type sont requis."
            ));
        }
        break;

    case 'delete':
        // Valider l'ID
        if(!empty($data->id)) {
            // Vérifier d'abord que le compte existe et appartient à l'utilisateur
            $account->id = $data->id;
            if($account->read()) {
                if($account->company_id == $_SESSION['company_id']) {
                    // Vérifier si le compte est utilisé
                    if($account->is_used) {
                        http_response_code(400);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de supprimer un compte utilisé dans des transactions."
                        ));
                    } else {
                        // Supprimer le compte
                        if($account->delete()) {
                            http_response_code(200);
                            echo json_encode(array(
                                "success" => true,
                                "message" => "Compte supprimé avec succès."
                            ));
                        } else {
                            http_response_code(503);
                            echo json_encode(array(
                                "success" => false,
                                "message" => "Impossible de supprimer le compte."
                            ));
                        }
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à ce compte."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Compte non trouvé."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de compte manquant."
            ));
        }
        break;

    case 'import_default':
        // Importer le plan comptable par défaut
        $company_id = $_SESSION['company_id'];

        // Vérifier si un plan existe déjà
        $stmt = $account->readByCompany($company_id);
        if($stmt->rowCount() > 0) {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Un plan comptable existe déjà pour cette société."
            ));
            break;
        }

        // Importer le plan par défaut
        if($account->importDefaultPlan($company_id)) {
            http_response_code(200);
            echo json_encode(array(
                "success" => true,
                "message" => "Plan comptable par défaut importé avec succès."
            ));
        } else {
            http_response_code(503);
            echo json_encode(array(
                "success" => false,
                "message" => "Impossible d'importer le plan comptable."
            ));
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Action non reconnue. Actions disponibles: create, read, list, update, delete, import_default."
        ));
        break;
}
?>
