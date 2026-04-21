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
include_once '../models/Invoice.php';

// Initialiser la connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Initialiser l'objet Invoice
$invoice = new Invoice($db);

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
            !empty($data->client_id) &&
            isset($data->items) &&
            is_array($data->items) &&
            count($data->items) > 0
        ) {
            // Générer le numéro de facture
            $invoice_number = $invoice->generateNumber($_SESSION['company_id']);

            // Calculer les totaux
            $subtotal = 0;
            $tva_amount = 0;
            $items = [];

            foreach($data->items as $item) {
                if(!empty($item->description) && isset($item->quantity) && isset($item->price)) {
                    $item_subtotal = floatval($item->quantity) * floatval($item->price);
                    $item_tva_rate = isset($item->tva_rate) ? floatval($item->tva_rate) : 0;
                    $item_tva_amount = $item_subtotal * ($item_tva_rate / 100);
                    $item_total = $item_subtotal + $item_tva_amount;

                    $items[] = array(
                        'description' => $item->description,
                        'quantity' => floatval($item->quantity),
                        'price' => floatval($item->price),
                        'tva_rate' => $item_tva_rate,
                        'tva_amount' => $item_tva_amount,
                        'total' => $item_total
                    );

                    $subtotal += $item_subtotal;
                    $tva_amount += $item_tva_amount;
                }
            }

            $total = $subtotal + $tva_amount;

            // Assigner les valeurs
            $invoice->company_id = $_SESSION['company_id'];
            $invoice->number = $invoice_number;
            $invoice->date = $data->date;
            $invoice->client_id = $data->client_id;
            $invoice->subtotal = $subtotal;
            $invoice->tva_amount = $tva_amount;
            $invoice->total = $total;
            $invoice->status = isset($data->status) ? $data->status : 'en attente';
            $invoice->items = $items;

            // Créer la facture
            if($invoice->create()) {
                http_response_code(201);
                echo json_encode(array(
                    "success" => true,
                    "message" => "Facture créée avec succès.",
                    "id" => $invoice->id,
                    "number" => $invoice_number
                ));
            } else {
                http_response_code(503);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Impossible de créer la facture."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Données incomplètes. Les champs date, client_id et items (avec au moins un article) sont requis."
            ));
        }
        break;

    case 'read':
        // Lire une facture spécifique
        if(isset($data->id)) {
            $invoice->id = $data->id;

            if($invoice->read()) {
                // Vérifier que la facture appartient à la société de l'utilisateur
                if($invoice->company_id == $_SESSION['company_id']) {
                    http_response_code(200);
                    echo json_encode(array(
                        "success" => true,
                        "data" => array(
                            "id" => $invoice->id,
                            "company_id" => $invoice->company_id,
                            "number" => $invoice->number,
                            "date" => $invoice->date,
                            "client_id" => $invoice->client_id,
                            "client_name" => $invoice->client_name,
                            "subtotal" => $invoice->subtotal,
                            "tva_amount" => $invoice->tva_amount,
                            "total" => $invoice->total,
                            "status" => $invoice->status,
                            "items" => $invoice->items,
                            "created_at" => $invoice->created_at
                        )
                    ));
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette facture."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Facture non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de facture manquant."
            ));
        }
        break;

    case 'list':
        // Lister toutes les factures de la société
        $company_id = $_SESSION['company_id'];
        $stmt = $invoice->readByCompany($company_id);
        $invoices = array();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $invoices[] = array(
                "id" => $row['id'],
                "number" => $row['number'],
                "date" => $row['date'],
                "client_id" => $row['client_id'],
                "client_name" => $row['client_name'],
                "subtotal" => $row['subtotal'],
                "tva_amount" => $row['tva_amount'],
                "total" => $row['total'],
                "status" => $row['status'],
                "created_at" => $row['created_at']
            );
        }

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "data" => $invoices,
            "count" => count($invoices)
        ));
        break;

    case 'update':
        // Valider les données requises
        if(
            !empty($data->id) &&
            !empty($data->date) &&
            !empty($data->client_id) &&
            isset($data->items) &&
            is_array($data->items) &&
            count($data->items) > 0
        ) {
            // Vérifier d'abord que la facture existe et appartient à l'utilisateur
            $invoice->id = $data->id;
            if($invoice->read()) {
                if($invoice->company_id == $_SESSION['company_id']) {
                    // Calculer les totaux
                    $subtotal = 0;
                    $tva_amount = 0;
                    $items = [];

                    foreach($data->items as $item) {
                        if(!empty($item->description) && isset($item->quantity) && isset($item->price)) {
                            $item_subtotal = floatval($item->quantity) * floatval($item->price);
                            $item_tva_rate = isset($item->tva_rate) ? floatval($item->tva_rate) : 0;
                            $item_tva_amount = $item_subtotal * ($item_tva_rate / 100);
                            $item_total = $item_subtotal + $item_tva_amount;

                            $items[] = array(
                                'description' => $item->description,
                                'quantity' => floatval($item->quantity),
                                'price' => floatval($item->price),
                                'tva_rate' => $item_tva_rate,
                                'tva_amount' => $item_tva_amount,
                                'total' => $item_total
                            );

                            $subtotal += $item_subtotal;
                            $tva_amount += $item_tva_amount;
                        }
                    }

                    $total = $subtotal + $tva_amount;

                    // Assigner les nouvelles valeurs
                    $invoice->number = isset($data->number) ? $data->number : $invoice->number;
                    $invoice->date = $data->date;
                    $invoice->client_id = $data->client_id;
                    $invoice->subtotal = $subtotal;
                    $invoice->tva_amount = $tva_amount;
                    $invoice->total = $total;
                    $invoice->status = isset($data->status) ? $data->status : $invoice->status;
                    $invoice->items = $items;

                    // Mettre à jour la facture
                    if($invoice->update()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Facture mise à jour avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de mettre à jour la facture."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette facture."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Facture non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "Données incomplètes. Les champs id, date, client_id et items sont requis."
            ));
        }
        break;

    case 'delete':
        // Valider l'ID
        if(!empty($data->id)) {
            // Vérifier d'abord que la facture existe et appartient à l'utilisateur
            $invoice->id = $data->id;
            if($invoice->read()) {
                if($invoice->company_id == $_SESSION['company_id']) {
                    // Supprimer la facture
                    if($invoice->delete()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Facture supprimée avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de supprimer la facture."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette facture."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Facture non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID de facture manquant."
            ));
        }
        break;

    case 'generate_number':
        // Générer un nouveau numéro de facture
        $company_id = $_SESSION['company_id'];
        $number = $invoice->generateNumber($company_id);

        http_response_code(200);
        echo json_encode(array(
            "success" => true,
            "number" => $number
        ));
        break;

    case 'update_status':
        // Mettre à jour uniquement le statut de la facture
        if(!empty($data->id) && !empty($data->status)) {
            $invoice->id = $data->id;
            if($invoice->read()) {
                if($invoice->company_id == $_SESSION['company_id']) {
                    $invoice->status = $data->status;

                    // Utiliser une requête UPDATE simple pour le statut
                    $query = "UPDATE invoices SET status = :status WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(":status", $invoice->status);
                    $stmt->bindParam(":id", $invoice->id);

                    if($stmt->execute()) {
                        http_response_code(200);
                        echo json_encode(array(
                            "success" => true,
                            "message" => "Statut mis à jour avec succès."
                        ));
                    } else {
                        http_response_code(503);
                        echo json_encode(array(
                            "success" => false,
                            "message" => "Impossible de mettre à jour le statut."
                        ));
                    }
                } else {
                    http_response_code(403);
                    echo json_encode(array(
                        "success" => false,
                        "message" => "Accès refusé à cette facture."
                    ));
                }
            } else {
                http_response_code(404);
                echo json_encode(array(
                    "success" => false,
                    "message" => "Facture non trouvée."
                ));
            }
        } else {
            http_response_code(400);
            echo json_encode(array(
                "success" => false,
                "message" => "ID et statut requis."
            ));
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(array(
            "success" => false,
            "message" => "Action non reconnue. Actions disponibles: create, read, list, update, delete, generate_number, update_status."
        ));
        break;
}
?>
