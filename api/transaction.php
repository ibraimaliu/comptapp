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
include_once '../models/Transaction.php';
include_once '../models/AccountingPlan.php';

// Initialiser la connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Initialiser l'objet Transaction
$transaction = new Transaction($db);

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
            !empty($data->date) &&
            !empty($data->description) &&
            isset($data->amount) &&
            !empty($data->type) &&
            !empty($data->account_id)
        ) {
            // Validation du montant
            $amount = floatval($data->amount);
            if($amount <= 0) {
                http_response_code(400);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Le montant doit être strictement supérieur à 0."
                ));
                break;
            }

            // Validation du type
            if(!in_array($data->type, ['income', 'expense'])) {
                http_response_code(400);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Le type doit être 'income' ou 'expense'."
                ));
                break;
            }

            // Assigner les valeurs
            $transaction->company_id = $_SESSION['company_id'];
            $transaction->date = $data->date;
            $transaction->description = $data->description;
            $transaction->amount = $amount;
            $transaction->type = $data->type;
            $transaction->tva_rate = isset($data->tva_rate) ? floatval($data->tva_rate) : 0;
            $transaction->account_id = $data->account_id;
            $transaction->counterpart_account_id = isset($data->counterpart_account_id) ? $data->counterpart_account_id : null;

            // Créer la transaction
            if($transaction->create()) {
                http_response_code(201);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Transaction créée avec succès.",
                    "id" => $transaction->id
                ));
            } else {
                http_response_code(503);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Impossible de créer la transaction."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Données incomplètes. Les champs date, description, amount, type et account_id sont requis."
            ));
        }
        break;

    case 'read':
        // Lire une transaction spécifique
        if(isset($data->id)) {
            $transaction->id = $data->id;

            if($transaction->read()) {
                // Vérifier que la transaction appartient à la société de l'utilisateur
                if($transaction->company_id == $_SESSION['company_id']) {
                    http_response_code(200);
                    echo json_encode(array(
                        "success" => true,
                        "data" => array(
                            "id" => $transaction->id,
                            "company_id" => $transaction->company_id,
                            "date" => $transaction->date,
                            "description" => $transaction->description,
                            "amount" => $transaction->amount,
                            "type" => $transaction->type,
                            "category" => $transaction->category,
                            "tva_rate" => $transaction->tva_rate,
                            "account_id" => $transaction->account_id,
                            "created_at" => $transaction->created_at
                        )
                    ));
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette transaction."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Transaction non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de transaction manquant."
            ));
        }
        break;

    case 'list':
        // Lister toutes les transactions de la société
        $company_id = $_SESSION['company_id'];
        $stmt = $transaction->readByCompany($company_id);
        $transactions = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = array(
                "id" => $row['id'],
                "date" => $row['date'],
                "description" => $row['description'],
                "amount" => $row['amount'],
                "type" => $row['type'],
                "category" => $row['category'],
                "tva_rate" => $row['tva_rate'],
                "account_id" => $row['account_id'],
                "account_number" => $row['account_number'],
                "account_name" => $row['account_name'],
                "created_at" => $row['created_at']
            );
        }

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "data" => $transactions,
            "count" => count($transactions)
        ));
        break;

    case 'update':
        // Valider les données requises
        if(
            !empty($data->id) &&
            !empty($data->date) &&
            !empty($data->description) &&
            isset($data->amount) &&
            !empty($data->type)
        ) {
            // Validation du montant
            $amount = floatval($data->amount);
            if($amount <= 0) {
                http_response_code(400);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Le montant doit être strictement supérieur à 0."
                ));
                break;
            }

            // Validation du type
            if(!in_array($data->type, ['income', 'expense'])) {
                http_response_code(400);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Le type doit être 'income' ou 'expense'."
                ));
                break;
            }

            // Vérifier d'abord que la transaction existe et appartient à l'utilisateur
            $transaction->id = $data->id;
            if($transaction->read()) {
                if($transaction->company_id == $_SESSION['company_id']) {
                    // Assigner les nouvelles valeurs
                    $transaction->date = $data->date;
                    $transaction->description = $data->description;
                    $transaction->amount = $amount;
                    $transaction->type = $data->type;
                    $transaction->tva_rate = isset($data->tva_rate) ? floatval($data->tva_rate) : 0;
                    $transaction->account_id = isset($data->account_id) ? $data->account_id : null;
                    $transaction->counterpart_account_id = isset($data->counterpart_account_id) ? $data->counterpart_account_id : null;

                    // Mettre à jour la transaction
                    if($transaction->update()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Transaction mise à jour avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de mettre à jour la transaction."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette transaction."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Transaction non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Données incomplètes. Les champs id, date, description, amount et type sont requis."
            ));
        }
        break;

    case 'delete':
        // Valider l'ID
        if(!empty($data->id)) {
            // Vérifier d'abord que la transaction existe et appartient à l'utilisateur
            $transaction->id = $data->id;
            if($transaction->read()) {
                if($transaction->company_id == $_SESSION['company_id']) {
                    // Supprimer la transaction
                    if($transaction->delete()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Transaction supprimée avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de supprimer la transaction."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette transaction."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Transaction non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de transaction manquant."
            ));
        }
        break;

    case 'stats':
        // Obtenir les statistiques pour le tableau de bord
        $company_id = $_SESSION['company_id'];
        $stats = $transaction->getStatistics($company_id);

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "data" => $stats
        ));
        break;

    default:
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Action non reconnue. Actions disponibles: create, read, list, update, delete, stats."
        ));
        break;
}
?>
