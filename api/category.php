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
include_once '../models/Category.php';

// Initialiser la connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Initialiser l'objet Category
$category = new Category($db);

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
        if(!empty($data->name)) {
            // Assigner les valeurs
            $category->company_id = $_SESSION['company_id'];
            $category->name = $data->name;

            // Créer la catégorie
            if($category->create()) {
                http_response_code(201);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Catégorie créée avec succès.",
                    "id" => $category->id
                ));
            } else {
                http_response_code(503);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Impossible de créer la catégorie."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Le nom de la catégorie est requis."
            ));
        }
        break;

    case 'read':
        // Lire une catégorie spécifique
        if(isset($data->id)) {
            $category->id = $data->id;

            if($category->read()) {
                // Vérifier que la catégorie appartient à la société de l'utilisateur
                if($category->company_id == $_SESSION['company_id']) {
                    http_response_code(200);
                    echo json_encode(array(
                        "success" => true,
                        "data" => array(
                            "id" => $category->id,
                            "company_id" => $category->company_id,
                            "name" => $category->name,
                            "created_at" => $category->created_at
                        )
                    ));
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette catégorie."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Catégorie non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de catégorie manquant."
            ));
        }
        break;

    case 'list':
        // Lister toutes les catégories de la société
        $company_id = $_SESSION['company_id'];
        $stmt = $category->readByCompany($company_id);
        $categories = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories[] = array(
                "id" => $row['id'],
                "name" => $row['name'],
                "created_at" => $row['created_at']
            );
        }

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "data" => $categories,
            "count" => count($categories)
        ));
        break;

    case 'update':
        // Valider les données requises
        if(!empty($data->id) && !empty($data->name)) {
            // Vérifier d'abord que la catégorie existe et appartient à l'utilisateur
            $category->id = $data->id;
            if($category->read()) {
                if($category->company_id == $_SESSION['company_id']) {
                    // Assigner la nouvelle valeur
                    $category->name = $data->name;

                    // Mettre à jour la catégorie
                    if($category->update()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Catégorie mise à jour avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de mettre à jour la catégorie."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette catégorie."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Catégorie non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID et nom de la catégorie sont requis."
            ));
        }
        break;

    case 'delete':
        // Valider l'ID
        if(!empty($data->id)) {
            // Vérifier d'abord que la catégorie existe et appartient à l'utilisateur
            $category->id = $data->id;
            if($category->read()) {
                if($category->company_id == $_SESSION['company_id']) {
                    // Supprimer la catégorie
                    if($category->delete()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Catégorie supprimée avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de supprimer la catégorie."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette catégorie."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Catégorie non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de catégorie manquant."
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
