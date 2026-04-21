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
include_once '../models/TVArate.php';

// Initialiser la connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Initialiser l'objet TVARate
$tvaRate = new TVARate($db);

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
        if(isset($data->rate) && !empty($data->description)) {
            // Assigner les valeurs
            $tvaRate->company_id = $_SESSION['company_id'];
            $tvaRate->rate = $data->rate;
            $tvaRate->description = $data->description;

            // Créer le taux TVA
            if($tvaRate->create()) {
                http_response_code(201);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Taux TVA créé avec succès.",
                    "id" => $tvaRate->id
                ));
            } else {
                http_response_code(503);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Impossible de créer le taux TVA."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Le taux et la description sont requis."
            ));
        }
        break;

    case 'read':
        // Lire un taux TVA spécifique
        if(isset($data->id)) {
            $tvaRate->id = $data->id;

            if($tvaRate->read()) {
                // Vérifier que le taux appartient à la société de l'utilisateur
                if($tvaRate->company_id == $_SESSION['company_id']) {
                    http_response_code(200);
                    echo json_encode(array(
                        "success" => true,
                        "data" => array(
                            "id" => $tvaRate->id,
                            "company_id" => $tvaRate->company_id,
                            "rate" => $tvaRate->rate,
                            "description" => $tvaRate->description,
                            "created_at" => $tvaRate->created_at
                        )
                    ));
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à ce taux TVA."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Taux TVA non trouvé."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de taux TVA manquant."
            ));
        }
        break;

    case 'list':
        // Lister tous les taux TVA de la société
        $company_id = $_SESSION['company_id'];
        $stmt = $tvaRate->readByCompany($company_id);
        $tvaRates = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $tvaRates[] = array(
                "id" => $row['id'],
                "rate" => $row['rate'],
                "description" => $row['description'],
                "created_at" => $row['created_at']
            );
        }

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "data" => $tvaRates,
            "count" => count($tvaRates)
        ));
        break;

    case 'update':
        // Valider les données requises
        if(!empty($data->id) && isset($data->rate) && !empty($data->description)) {
            // Vérifier d'abord que le taux existe et appartient à l'utilisateur
            $tvaRate->id = $data->id;
            if($tvaRate->read()) {
                if($tvaRate->company_id == $_SESSION['company_id']) {
                    // Assigner les nouvelles valeurs
                    $tvaRate->rate = $data->rate;
                    $tvaRate->description = $data->description;

                    // Mettre à jour le taux TVA
                    if($tvaRate->update()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Taux TVA mis à jour avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de mettre à jour le taux TVA."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à ce taux TVA."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Taux TVA non trouvé."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID, taux et description sont requis."
            ));
        }
        break;

    case 'delete':
        // Valider l'ID
        if(!empty($data->id)) {
            // Vérifier d'abord que le taux existe et appartient à l'utilisateur
            $tvaRate->id = $data->id;
            if($tvaRate->read()) {
                if($tvaRate->company_id == $_SESSION['company_id']) {
                    // Supprimer le taux TVA
                    if($tvaRate->delete()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Taux TVA supprimé avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de supprimer le taux TVA."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à ce taux TVA."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Taux TVA non trouvé."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de taux TVA manquant."
            ));
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Action non reconnue. Actions disponibles: create, read, list, update, delete."
        ));
        break;
}
?>
