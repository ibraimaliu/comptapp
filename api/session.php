<?php
// IMPORTANT: Utiliser le même nom de session que le reste de l'application
session_name('COMPTAPP_SESSION');

// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Vérifier le type de requête
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Log pour débogage
    error_log('Requête reçue dans session.php: ' . print_r($data, true));
    
    // Vérifier si les données sont valides
    if (!$data || !isset($data['action'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
        exit;
    }
    
    // Traiter l'action demandée
    switch ($data['action']) {
        case 'change_company':
            if (!isset($data['company_id'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de société manquant']);
                exit;
            }

            // Vérifier que l'ID est valide
            if (!is_numeric($data['company_id'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'ID de société invalide']);
                exit;
            }

            // Vérifier que l'utilisateur a accès à cette société (facultatif)
            if (isset($_SESSION['user_id'])) {
                // Inclure les modèles nécessaires
                include_once dirname(__DIR__) . '/config/database.php';
                include_once dirname(__DIR__) . '/models/Company.php';

                // Initialiser la base de données
                $database = new Database();
                $db = $database->getConnection();

                // Vérifier l'accès
                $company = new Company($db);
                if (!$company->userHasAccess($_SESSION['user_id'], $data['company_id'])) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Accès non autorisé à cette société']);
                    exit;
                }
            }

            // Mettre à jour la session
            $old_company_id = $_SESSION['company_id'] ?? 'aucune';
            $_SESSION['company_id'] = $data['company_id'];
            error_log('CHANGEMENT DE SOCIÉTÉ - Ancienne: ' . $old_company_id . ' → Nouvelle: ' . $_SESSION['company_id']);
            error_log('Session ID: ' . session_id());
            error_log('Session complète après changement: ' . print_r($_SESSION, true));

            // Répondre avec succès
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'company_id' => $_SESSION['company_id'],
                'old_company_id' => $old_company_id
            ]);
            break;

        case 'change_fiscal_year':
            if (!isset($data['fiscal_year'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Année fiscale manquante']);
                exit;
            }

            // Vérifier que l'année est valide (format YYYY)
            if (!is_numeric($data['fiscal_year']) || strlen((string)$data['fiscal_year']) !== 4) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Année fiscale invalide']);
                exit;
            }

            // Mettre à jour la session
            $_SESSION['fiscal_year'] = (int)$data['fiscal_year'];
            error_log('Année fiscale mise à jour: ' . $_SESSION['fiscal_year']);

            // Répondre avec succès
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'fiscal_year' => $_SESSION['fiscal_year']]);
            break;
            
        default:
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
            break;
    }
} else {
    // Méthode non autorisée
    header('HTTP/1.1 405 Method Not Allowed');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>