<?php
// Point d'entrée principal de l'application
include_once 'config/config.php';

// Gestion des pages
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Gérer la déconnexion AVANT d'inclure le header (pour éviter "headers already sent")
if($page == 'logout') {
    session_destroy();
    redirect('login_tenant.php');
}

// Redirection vers la page de connexion multi-tenant si l'utilisateur n'est pas connecté
if(!isLoggedIn() && $page != 'login' && $page != 'register') {
    redirect('login_tenant.php');
}

// Vérifier si l'utilisateur a une société (sauf pour les pages qui n'en ont pas besoin)
$pages_sans_societe = ['society_setup', 'company_create', 'mon_compte'];
if(isLoggedIn() && !in_array($page, $pages_sans_societe)) {
    include_once 'config/database.php';
    include_once 'models/Company.php';

    $database = new Database();
    $db = $database->getConnection();

    if($db) {
        $company = new Company($db);
        $companies_stmt = $company->readByUser($_SESSION['user_id']);
        $companies = [];
        while ($row = $companies_stmt->fetch(PDO::FETCH_ASSOC)) {
            $companies[] = $row;
        }

        // Si l'utilisateur n'a pas de société, rediriger vers la création
        if (count($companies) == 0) {
            redirect('index.php?page=society_setup');
            exit;
        }

        // Si une société existe mais n'est pas sélectionnée, sélectionner la première
        if (!isset($_SESSION['company_id']) || !in_array($_SESSION['company_id'], array_column($companies, 'id'))) {
            $_SESSION['company_id'] = $companies[0]['id'];
        }
    }
}

// Inclure le header APRÈS les redirections
include_once 'includes/header.php';

// Afficher la page correspondante
switch($page) {
    case 'login':
        // Rediriger vers le système multi-tenant
        redirect('login_tenant.php');
        break;
    case 'register':
        // Rediriger vers l'inscription multi-tenant
        redirect('register_tenant.php');
        break;
    case 'home':
        include_once 'views/home.php';
        break;
    case 'dashboard_advanced':
        include_once 'views/dashboard_advanced.php';
        break;
    case 'comptabilite':
        include_once 'views/comptabilite.php';
        break;
    case 'transaction_create':
        include_once 'views/transaction_create.php';
        break;
    case 'releve_compte':
        include_once 'views/releve_compte.php';
        break;
    case 'bilan':
        include_once 'views/bilan_improved.php';
        break;
    case 'compte_resultat':
        include_once 'views/compte_resultat_improved.php';
        break;
    case 'adresses':
        include_once 'views/adresses.php';
        break;
    case 'devis':
        include_once 'views/devis.php';
        break;
    case 'factures':
        include_once 'views/factures.php';
        break;
    case 'recurring_invoices':
    case 'factures_recurrentes':
        include_once 'views/factures_recurrentes.php';
        break;
    case 'bank_reconciliation':
    case 'rapprochement':
        include_once 'views/bank_reconciliation.php';
        break;
    case 'payment_reminders':
    case 'rappels':
        include_once 'views/payment_reminders.php';
        break;
    case 'supplier_invoices':
        include_once 'views/supplier_invoices.php';
        break;
    case 'payments':
        include_once 'views/payments.php';
        break;
    case 'products':
        include_once 'views/products.php';
        break;
    case 'tva_declaration':
        include_once 'views/tva_declaration.php';
        break;
    case 'tresorerie':
        include_once 'views/tresorerie.php';
        break;
    case 'parametres':
        include_once 'views/parametres.php';
        break;
    case 'mon_compte':
        include_once 'views/mon_compte.php';
        break;
    case 'mes_societes':
    case 'my_companies':
        include_once 'views/mes_societes.php';
        break;
    case 'users_management':
    case 'gestion_utilisateurs':
        include_once 'views/users_management.php';
        break;
    case 'recherche':
        include_once 'views/recherche.php';
        break;
    case 'company_create':
    case 'society_setup':
        include_once 'views/society_setup.php';
        break;
    case 'employees':
    case 'employes':
        include_once 'views/employees.php';
        break;
    case 'payroll':
    case 'salaires':
    case 'fiches_paie':
        include_once 'views/payroll.php';
        break;
    default:
        include_once 'views/404.php';
        break;
}

include_once 'includes/footer.php';
?>