<?php
/**
 * API d'authentification
 * Gère les actions de connexion, inscription et déconnexion
 */

// Démarrer la session AVANT tout output (y compris les headers JSON)
session_name('COMPTAPP_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// En-têtes pour API REST
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Inclure les fichiers nécessaires
include_once '../config/database.php';
include_once '../models/User.php';

// Désactiver l'affichage des erreurs pour éviter les sorties non-JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Initialiser la connexion à la base de données
try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        throw new Exception("Impossible d'établir une connexion à la base de données");
    }
} catch (Exception $e) {
    error_log("Erreur de connexion à la base de données: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Erreur de connexion à la base de données",
        "error" => $e->getMessage()
    ]);
    exit;
}

// Récupérer les données POST
$data = json_decode(file_get_contents("php://input"));

// Journaliser les données reçues pour débogage
error_log("auth.php - Données reçues: " . print_r(file_get_contents("php://input"), true));

// Vérifier si les données JSON sont valides
if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Format JSON invalide",
        "error" => json_last_error_msg()
    ]);
    exit;
}

// Vérifier si l'action est spécifiée
if (!isset($data->action) || empty($data->action)) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Action non spécifiée"
    ]);
    exit;
}

// Initialiser l'objet User
$user = new User($db);

// Traiter les différentes actions
switch ($data->action) {
    
    // ================= CONNEXION =================
    case 'login':
        // Vérifier les champs requis
        if (empty($data->username) || empty($data->password)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Veuillez remplir tous les champs (nom d'utilisateur et mot de passe)"
            ]);
            exit;
        }
        
        try {
            // Nettoyer les données
            $user->username = htmlspecialchars(strip_tags($data->username));
            $password = htmlspecialchars(strip_tags($data->password));
            
            error_log("Tentative de connexion pour l'utilisateur: " . $user->username);
            
            // Vérifier si l'utilisateur existe
            if ($user->userExists()) {
                error_log("Utilisateur trouvé - vérification du mot de passe");
                
                // Vérifier le mot de passe
                if (password_verify($password, $user->password)) {
                    error_log("Mot de passe valide pour l'utilisateur: " . $user->username);

                    // Créer les données de session (session déjà démarrée en haut du fichier)
                    $_SESSION['user_id'] = $user->id;
                    $_SESSION['username'] = $user->username;
                    $_SESSION['email'] = $user->email;
                    $_SESSION['last_access'] = time();

                    // Sauvegarder et fermer la session pour s'assurer que les données sont écrites
                    session_write_close();

                    // Redémarrer pour les opérations suivantes
                    session_name('COMPTAPP_SESSION');
                    session_start();

                    error_log("Session créée pour l'utilisateur ID: " . $_SESSION['user_id']);
                    
                    // Réponse de succès
                    http_response_code(200);
                    echo json_encode([
                        "success" => true,
                        "message" => "Connexion réussie",
                        "user" => [
                            "id" => $user->id,
                            "username" => $user->username,
                            "email" => $user->email
                        ]
                    ]);
                } else {
                    error_log("Échec d'authentification - mot de passe incorrect pour: " . $user->username);
                    http_response_code(401);
                    echo json_encode([
                        "success" => false,
                        "message" => "Mot de passe incorrect"
                    ]);
                }
            } else {
                error_log("Échec d'authentification - utilisateur non trouvé: " . $user->username);
                http_response_code(401);
                echo json_encode([
                    "success" => false,
                    "message" => "Nom d'utilisateur introuvable"
                ]);
            }
        } catch (Exception $e) {
            error_log("Exception durant la connexion: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Erreur serveur lors de l'authentification",
                "error" => $e->getMessage()
            ]);
        }
        break;
    
    // ================= INSCRIPTION =================
    case 'register':
        // Vérifier les champs requis
        if (empty($data->username) || empty($data->email) || empty($data->password)) {
            http_response_code(400);
            echo json_encode([
                "success" => false,
                "message" => "Veuillez remplir tous les champs obligatoires"
            ]);
            exit;
        }
        
        try {
            // Nettoyer et valider les données
            $user->username = htmlspecialchars(strip_tags($data->username));
            $user->email = htmlspecialchars(strip_tags($data->email));
            $user->password = htmlspecialchars(strip_tags($data->password));
            
            error_log("Tentative d'inscription pour l'utilisateur: " . $user->username);
            
            // Validation du nom d'utilisateur
            if (strlen($user->username) < 3) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Le nom d'utilisateur doit contenir au moins 3 caractères"
                ]);
                exit;
            }
            
            // Validation de l'email
            if (!filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Format d'email invalide"
                ]);
                exit;
            }
            
            // Validation du mot de passe
            if (strlen($user->password) < 8) {
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Le mot de passe doit contenir au moins 8 caractères"
                ]);
                exit;
            }
            
            // Vérifier si le nom d'utilisateur existe déjà
            $user->username = $data->username;
            if ($user->userExists()) {
                error_log("Échec d'inscription - nom d'utilisateur déjà utilisé: " . $user->username);
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Ce nom d'utilisateur est déjà utilisé"
                ]);
                exit;
            }
            
            // Vérifier si l'email existe déjà
            if ($user->emailExists()) {
                error_log("Échec d'inscription - email déjà utilisé: " . $user->email);
                http_response_code(400);
                echo json_encode([
                    "success" => false,
                    "message" => "Cette adresse email est déjà utilisée"
                ]);
                exit;
            }
            
            // Hacher le mot de passe
            $user->password = password_hash($user->password, PASSWORD_BCRYPT);
            
            // Créer l'utilisateur
            if ($user->create()) {
                error_log("Inscription réussie pour l'utilisateur: " . $user->username);
                http_response_code(201);
                echo json_encode([
                    "success" => true,
                    "message" => "Inscription réussie ! Vous pouvez maintenant vous connecter."
                ]);
            } else {
                error_log("Échec de création de l'utilisateur dans la base de données");
                http_response_code(503);
                echo json_encode([
                    "success" => false,
                    "message" => "Impossible de créer votre compte. Veuillez réessayer ultérieurement."
                ]);
            }
        } catch (Exception $e) {
            error_log("Exception durant l'inscription: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Erreur serveur lors de l'inscription",
                "error" => $e->getMessage()
            ]);
        }
        break;
    
    // ================= DÉCONNEXION =================
    case 'logout':
        try {
            // Session déjà démarrée en haut du fichier
            error_log("Déconnexion de l'utilisateur ID: " . ($_SESSION['user_id'] ?? 'inconnu'));
            
            // Détruire toutes les variables de session
            $_SESSION = [];
            
            // Détruire le cookie de session
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            
            // Détruire la session
            session_destroy();
            
            http_response_code(200);
            echo json_encode([
                "success" => true,
                "message" => "Déconnexion réussie"
            ]);
        } catch (Exception $e) {
            error_log("Exception durant la déconnexion: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "message" => "Erreur lors de la déconnexion",
                "error" => $e->getMessage()
            ]);
        }
        break;
    
    // ================= ACTION NON RECONNUE =================
    default:
        http_response_code(400);
        echo json_encode([
            "success" => false,
            "message" => "Action non reconnue: " . $data->action
        ]);
        break;
}
?>