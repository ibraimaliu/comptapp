<?php
/**
 * Page: Configuration Société
 * Version: 2.0 - Design moderne et informations détaillées
 * Description: Formulaire de création de société avec tous les détails nécessaires
 */

// Activer l'affichage des erreurs pour le debug
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Augmenter le timeout pour l'import du plan comptable
set_time_limit(120); // 2 minutes max
ini_set('max_execution_time', '120');

// Inclure les fichiers nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/config/database_master.php';
include_once dirname(__DIR__) . '/models/Company.php';
include_once dirname(__DIR__) . '/utils/TenantLimits.php';

// Vérifier si l'utilisateur est connecté
if(!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit();
}

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Récupérer les limites du plan
$company_limits = null;
$can_create = true;
$limit_message = "";

if (isset($_SESSION['tenant_database'])) {
    $database_master = new DatabaseMaster();
    $db_master = $database_master->getConnection();
    // Utiliser tenant_code si disponible, sinon database_name
    $tenant_code = $_SESSION['tenant_code'] ?? $_SESSION['tenant_database'];
    $user_id = $_SESSION['user_id'];

    $company_limits = TenantLimits::getCompanyLimits($db_master, $tenant_code, $user_id);
    $check_limit = TenantLimits::canCreateCompany($db_master, $tenant_code, $user_id);

    $can_create = $check_limit['allowed'];
    $limit_message = $check_limit['message'];
}

// Traitement du formulaire si soumis
$error_message = "";
$success_message = "";

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier d'abord si l'utilisateur peut créer une société
    if (!$can_create) {
        $error_message = $limit_message;
    } else {
        // Récupérer les données du formulaire - Informations de base
        $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
        $owner_name = isset($_POST['owner_name']) ? trim($_POST['owner_name']) : '';
        $owner_surname = isset($_POST['owner_surname']) ? trim($_POST['owner_surname']) : '';

    // Coordonnées
    $address = isset($_POST['address']) ? trim($_POST['address']) : '';
    $postal_code = isset($_POST['postal_code']) ? trim($_POST['postal_code']) : '';
    $city = isset($_POST['city']) ? trim($_POST['city']) : '';
    $country = isset($_POST['country']) ? trim($_POST['country']) : 'Suisse';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $website = isset($_POST['website']) ? trim($_POST['website']) : '';

    // Informations légales
    $ide_number = isset($_POST['ide_number']) ? trim($_POST['ide_number']) : '';
    $tva_number = isset($_POST['tva_number']) ? trim($_POST['tva_number']) : '';
    $rc_number = isset($_POST['rc_number']) ? trim($_POST['rc_number']) : '';

    // Informations bancaires
    $bank_name = isset($_POST['bank_name']) ? trim($_POST['bank_name']) : '';
    $iban = isset($_POST['iban']) ? trim($_POST['iban']) : '';
    $bic = isset($_POST['bic']) ? trim($_POST['bic']) : '';

    // Période comptable
    $fiscal_year_start = isset($_POST['fiscal_year_start']) ? trim($_POST['fiscal_year_start']) : '';
    $fiscal_year_end = isset($_POST['fiscal_year_end']) ? trim($_POST['fiscal_year_end']) : '';
    $tva_status = isset($_POST['tva_status']) ? $_POST['tva_status'] : 'non_assujetti';

    // Validation des champs obligatoires
    if(empty($company_name) || empty($owner_name) || empty($owner_surname) ||
       empty($fiscal_year_start) || empty($fiscal_year_end)) {
        $error_message = "Les champs marqués d'un astérisque (*) sont obligatoires.";
    } else {
        try {
            // Créer une nouvelle société
            $company = new Company($db);
            $company->user_id = $_SESSION['user_id'];
            $company->name = $company_name;
            $company->owner_name = $owner_name;
            $company->owner_surname = $owner_surname;
            $company->address = $address;
            $company->postal_code = $postal_code;
            $company->city = $city;
            $company->country = $country;
            $company->phone = $phone;
            $company->email = $email;
            $company->website = $website;
            $company->ide_number = $ide_number;
            $company->tva_number = $tva_number;
            $company->rc_number = $rc_number;
            $company->bank_name = $bank_name;
            $company->iban = $iban;
            $company->bic = $bic;
            $company->fiscal_year_start = $fiscal_year_start;
            $company->fiscal_year_end = $fiscal_year_end;
            $company->tva_status = $tva_status;

            if($company->create()) {
                // Enregistrer l'ID de la société dans la session
                $_SESSION['company_id'] = $company->id;

                error_log("Société créée avec ID: " . $company->id);

                // Initialiser le plan comptable par défaut
                try {
                    include_once dirname(__DIR__) . '/models/AccountingPlan.php';
                    $accountingPlan = new AccountingPlan($db);

                    error_log("Début import plan comptable pour société ID: " . $company->id);
                    $accountingPlan->importDefaultPlan($company->id);
                    error_log("Fin import plan comptable");

                } catch (Exception $e) {
                    error_log("Erreur lors de l'import du plan comptable: " . $e->getMessage());
                    // Continue quand même, le plan peut être importé plus tard
                }

                $success_message = "Votre société a été créée avec succès! Redirection en cours...";
            } else {
                $error_message = "Une erreur est survenue lors de la création de la société.";
                error_log("Erreur lors de la création de la société");
            }
        } catch(Exception $e) {
            $error_message = "Erreur: " . $e->getMessage();
        }
    }
    } // Fin du else pour can_create
}

// Définir des valeurs par défaut pour les dates (année fiscale standard)
$default_year_start = date('Y') . '-01-01'; // Premier janvier de l'année en cours
$default_year_end = date('Y') . '-12-31'; // 31 décembre de l'année en cours
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuration de votre société</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .setup-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3498db;
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            color: #2c3e50;
            font-size: 32px;
        }

        .page-header .icon {
            font-size: 64px;
            color: #3498db;
            margin-bottom: 15px;
        }

        .intro-text {
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
            color: #555;
            text-align: center;
            background: #e8f4f8;
            padding: 20px;
            border-radius: 10px;
            border-left: 5px solid #3498db;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease-out;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            font-size: 20px;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .form-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .form-section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 25px;
            font-size: 18px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-section-header i {
            font-size: 22px;
        }

        .form-section-body {
            padding: 25px;
        }

        .section-info {
            font-size: 14px;
            color: #666;
            margin-bottom: 20px;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #3498db;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 14px;
        }

        .required {
            color: #e74c3c;
            font-weight: bold;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-control:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
        }

        .input-icon {
            position: relative;
        }

        .input-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }

        .input-icon .form-control {
            padding-left: 45px;
        }

        .radio-group {
            display: flex;
            gap: 30px;
            margin-top: 10px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .radio-option:hover {
            border-color: #3498db;
            background-color: #f0f8ff;
        }

        .radio-option input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .radio-option input[type="radio"]:checked + span {
            color: #3498db;
            font-weight: 600;
        }

        .form-text {
            display: block;
            margin-top: 6px;
            font-size: 13px;
            color: #777;
        }

        .form-actions {
            text-align: center;
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #ecf0f1;
        }

        .btn {
            padding: 14px 40px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-primary:active {
            transform: translateY(0);
        }

        .help-tip {
            display: inline-block;
            margin-left: 5px;
            color: #3498db;
            cursor: help;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .radio-group {
                flex-direction: column;
                gap: 15px;
            }

            .setup-container {
                padding: 10px;
            }

            .page-header h1 {
                font-size: 24px;
            }
        }

        /* Animation de chargement */
        .loading {
            pointer-events: none;
            opacity: 0.6;
        }

        .loading::after {
            content: "";
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30px;
            height: 30px;
            margin: -15px 0 0 -15px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="page-header">
            <div class="icon">
                <i class="fas fa-building"></i>
            </div>
            <h1>Configuration de votre société</h1>
        </div>

        <div class="intro-text">
            <i class="fas fa-info-circle"></i>
            Bienvenue dans votre application de gestion comptable! Pour commencer, veuillez configurer votre première société.
            Ces informations sont nécessaires pour paramétrer correctement votre environnement de travail et générer des documents conformes.
        </div>

        <?php if(!empty($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if(!empty($success_message)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <span><?php echo htmlspecialchars($success_message); ?></span>
        </div>
        <script>
        // Redirection automatique après 1 seconde
        setTimeout(function() {
            window.location.href = 'index.php?page=home';
        }, 1000);
        </script>
        <?php endif; ?>

        <?php if ($company_limits): ?>
        <div class="alert <?php echo $can_create ? 'alert-info' : 'alert-warning'; ?>">
            <i class="fas fa-info-circle"></i>
            <strong>Limite de sociétés (Plan: <?php echo htmlspecialchars($company_limits['plan_name']); ?>)</strong><br>
            <span>
                <?php if ($company_limits['unlimited']): ?>
                    Vous pouvez créer un nombre illimité de sociétés.
                <?php else: ?>
                    Sociétés actives: <?php echo $company_limits['current']; ?> / <?php echo $company_limits['max']; ?>
                    <?php if ($company_limits['remaining'] > 0): ?>
                        <br>Vous pouvez encore créer <?php echo $company_limits['remaining']; ?> société(s).
                    <?php else: ?>
                        <br><strong>⚠️ Limite atteinte.</strong> Mettez à niveau votre plan pour créer plus de sociétés.
                    <?php endif; ?>
                <?php endif; ?>
            </span>
        </div>
        <?php endif; ?>

        <?php if (!$can_create): ?>
        <div class="alert alert-danger">
            <i class="fas fa-ban"></i>
            <strong>Impossible de créer une nouvelle société</strong><br>
            <span><?php echo htmlspecialchars($limit_message); ?></span>
        </div>
        <?php endif; ?>

        <form id="society-form" method="post" action="index.php?page=society_setup" <?php echo !$can_create ? 'onsubmit="return false;"' : ''; ?>>

            <!-- Section: Informations générales -->
            <div class="form-card">
                <div class="form-section-header">
                    <i class="fas fa-info-circle"></i>
                    Informations générales
                </div>
                <div class="form-section-body">
                    <div class="form-group">
                        <label for="company_name">
                            Nom de la société <span class="required">*</span>
                        </label>
                        <div class="input-icon">
                            <i class="fas fa-building"></i>
                            <input type="text" id="company_name" name="company_name" class="form-control"
                                   placeholder="Ex: Entreprise SA" required>
                        </div>
                        <small class="form-text">Le nom officiel de votre entreprise ou raison sociale</small>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="owner_name">
                                Nom du propriétaire <span class="required">*</span>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" id="owner_name" name="owner_name" class="form-control"
                                       placeholder="Dupont" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="owner_surname">
                                Prénom du propriétaire <span class="required">*</span>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-user"></i>
                                <input type="text" id="owner_surname" name="owner_surname" class="form-control"
                                       placeholder="Jean" required>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Coordonnées -->
            <div class="form-card">
                <div class="form-section-header">
                    <i class="fas fa-map-marker-alt"></i>
                    Coordonnées
                </div>
                <div class="form-section-body">
                    <div class="section-info">
                        <i class="fas fa-lightbulb"></i>
                        Ces informations apparaîtront sur vos factures et documents officiels
                    </div>

                    <div class="form-group">
                        <label for="address">Adresse</label>
                        <div class="input-icon">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" id="address" name="address" class="form-control"
                                   placeholder="Rue de l'Exemple 123">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="postal_code">Code postal</label>
                            <div class="input-icon">
                                <i class="fas fa-mail-bulk"></i>
                                <input type="text" id="postal_code" name="postal_code" class="form-control"
                                       placeholder="1000">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="city">Ville</label>
                            <div class="input-icon">
                                <i class="fas fa-city"></i>
                                <input type="text" id="city" name="city" class="form-control"
                                       placeholder="Lausanne">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="country">Pays</label>
                            <div class="input-icon">
                                <i class="fas fa-globe"></i>
                                <input type="text" id="country" name="country" class="form-control"
                                       value="Suisse" placeholder="Suisse">
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <div class="input-icon">
                                <i class="fas fa-phone"></i>
                                <input type="tel" id="phone" name="phone" class="form-control"
                                       placeholder="+41 21 123 45 67">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="email">Email</label>
                            <div class="input-icon">
                                <i class="fas fa-envelope"></i>
                                <input type="email" id="email" name="email" class="form-control"
                                       placeholder="contact@entreprise.ch">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="website">Site web</label>
                        <div class="input-icon">
                            <i class="fas fa-globe"></i>
                            <input type="url" id="website" name="website" class="form-control"
                                   placeholder="https://www.entreprise.ch">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Informations légales -->
            <div class="form-card">
                <div class="form-section-header">
                    <i class="fas fa-gavel"></i>
                    Informations légales
                </div>
                <div class="form-section-body">
                    <div class="section-info">
                        <i class="fas fa-lightbulb"></i>
                        Numéros d'identification officiels de votre entreprise
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="ide_number">
                                Numéro IDE
                                <i class="fas fa-question-circle help-tip"
                                   title="Numéro d'identification des entreprises (CHE-XXX.XXX.XXX)"></i>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-id-card"></i>
                                <input type="text" id="ide_number" name="ide_number" class="form-control"
                                       placeholder="CHE-123.456.789">
                            </div>
                            <small class="form-text">Format: CHE-XXX.XXX.XXX</small>
                        </div>

                        <div class="form-group">
                            <label for="tva_number">
                                Numéro TVA
                                <i class="fas fa-question-circle help-tip"
                                   title="Si vous êtes assujetti à la TVA"></i>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-percentage"></i>
                                <input type="text" id="tva_number" name="tva_number" class="form-control"
                                       placeholder="CHE-123.456.789 TVA">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="rc_number">
                            Numéro RC (Registre du Commerce)
                            <i class="fas fa-question-circle help-tip"
                               title="Numéro d'inscription au Registre du Commerce"></i>
                        </label>
                        <div class="input-icon">
                            <i class="fas fa-stamp"></i>
                            <input type="text" id="rc_number" name="rc_number" class="form-control"
                                   placeholder="CH-XXX-XXXX-XXX-X">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Informations bancaires -->
            <div class="form-card">
                <div class="form-section-header">
                    <i class="fas fa-university"></i>
                    Informations bancaires
                </div>
                <div class="form-section-body">
                    <div class="section-info">
                        <i class="fas fa-lightbulb"></i>
                        Ces informations seront utilisées pour les factures et les paiements
                    </div>

                    <div class="form-group">
                        <label for="bank_name">Nom de la banque</label>
                        <div class="input-icon">
                            <i class="fas fa-university"></i>
                            <input type="text" id="bank_name" name="bank_name" class="form-control"
                                   placeholder="Banque Cantonale">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="iban">
                                IBAN
                                <i class="fas fa-question-circle help-tip"
                                   title="Numéro de compte international (CHXX XXXX XXXX XXXX XXXX X)"></i>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-credit-card"></i>
                                <input type="text" id="iban" name="iban" class="form-control"
                                       placeholder="CH93 0000 0000 0000 0000 0" maxlength="34">
                            </div>
                            <small class="form-text">Format: CHXX XXXX XXXX XXXX XXXX X</small>
                        </div>

                        <div class="form-group">
                            <label for="bic">
                                BIC/SWIFT
                                <i class="fas fa-question-circle help-tip"
                                   title="Code d'identification de la banque"></i>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-code-branch"></i>
                                <input type="text" id="bic" name="bic" class="form-control"
                                       placeholder="ABCDCHZZXXX" maxlength="11">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: Période comptable -->
            <div class="form-card">
                <div class="form-section-header">
                    <i class="fas fa-calendar-alt"></i>
                    Période comptable
                </div>
                <div class="form-section-body">
                    <div class="section-info">
                        <i class="fas fa-lightbulb"></i>
                        La période comptable définit l'exercice fiscal de votre entreprise, généralement d'une durée d'un an
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="fiscal_year_start">
                                Début de l'exercice <span class="required">*</span>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-calendar-check"></i>
                                <input type="date" id="fiscal_year_start" name="fiscal_year_start"
                                       class="form-control" value="<?php echo $default_year_start; ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="fiscal_year_end">
                                Fin de l'exercice <span class="required">*</span>
                            </label>
                            <div class="input-icon">
                                <i class="fas fa-calendar-times"></i>
                                <input type="date" id="fiscal_year_end" name="fiscal_year_end"
                                       class="form-control" value="<?php echo $default_year_end; ?>" required>
                            </div>
                        </div>
                    </div>

                    <small class="form-text">
                        <i class="fas fa-info-circle"></i>
                        En Suisse, l'exercice comptable correspond généralement à l'année civile (01.01 - 31.12)
                    </small>
                </div>
            </div>

            <!-- Section: Configuration TVA -->
            <div class="form-card">
                <div class="form-section-header">
                    <i class="fas fa-percent"></i>
                    Configuration TVA
                </div>
                <div class="form-section-body">
                    <div class="section-info">
                        <i class="fas fa-lightbulb"></i>
                        Indiquez si votre entreprise est soumise à la TVA. Cette configuration impactera la gestion de vos factures et déclarations
                    </div>

                    <div class="form-group">
                        <label>Statut TVA <span class="required">*</span></label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="tva_status" value="assujetti">
                                <span>
                                    <i class="fas fa-check-circle"></i>
                                    Assujetti à la TVA
                                </span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tva_status" value="non_assujetti" checked>
                                <span>
                                    <i class="fas fa-times-circle"></i>
                                    Non assujetti à la TVA
                                </span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="tva_status" value="franchise">
                                <span>
                                    <i class="fas fa-percentage"></i>
                                    Franchise de la taxe sur le chiffre d'affaires
                                </span>
                            </label>
                        </div>
                        <small class="form-text">
                            <i class="fas fa-info-circle"></i>
                            En Suisse, l'assujettissement à la TVA est obligatoire si le chiffre d'affaires annuel dépasse CHF 100'000.-
                        </small>
                    </div>
                </div>
            </div>

            <!-- Bouton de soumission -->
            <div class="form-actions">
                <button type="submit" id="submit-btn" class="btn btn-primary">
                    <i class="fas fa-check"></i>
                    Créer ma société
                </button>
            </div>
        </form>

        <!-- Loading Overlay -->
        <div id="loading-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center;">
            <div style="text-align: center; color: white;">
                <div style="font-size: 3em; margin-bottom: 20px;">
                    <i class="fas fa-spinner fa-spin"></i>
                </div>
                <h2>Création de votre société en cours...</h2>
                <p>Veuillez patienter, cela peut prendre quelques secondes.</p>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const societyForm = document.getElementById('society-form');
        const submitBtn = document.getElementById('submit-btn');
        const loadingOverlay = document.getElementById('loading-overlay');

        // Validation du formulaire
        societyForm.addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('fiscal_year_start').value);
            const endDate = new Date(document.getElementById('fiscal_year_end').value);

            // Vérifier que la date de fin est postérieure à la date de début
            if (endDate <= startDate) {
                e.preventDefault();
                alert('❌ La date de fin de l\'exercice doit être postérieure à la date de début.');
                return;
            }

            // Vérifier que la durée n'excède pas 18 mois (limite légale)
            const diffTime = Math.abs(endDate - startDate);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

            if (diffDays > 548) { // ~18 mois
                e.preventDefault();
                alert('❌ La durée de l\'exercice comptable ne peut pas dépasser 18 mois.');
                return;
            }

            // Validation de l'IBAN si rempli
            const iban = document.getElementById('iban').value.replace(/\s/g, '');
            if (iban && !validateIBAN(iban)) {
                e.preventDefault();
                alert('❌ Le format de l\'IBAN est invalide. Format attendu: CHXX XXXX XXXX XXXX XXXX X');
                return;
            }

            // Afficher le loading overlay
            loadingOverlay.style.display = 'flex';
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création en cours...';

            // Ajouter une classe de chargement
            societyForm.classList.add('loading');
        });

        // Mise à jour automatique de la date de fin lorsque la date de début change
        const fiscalYearStart = document.getElementById('fiscal_year_start');
        const fiscalYearEnd = document.getElementById('fiscal_year_end');

        fiscalYearStart.addEventListener('change', function() {
            const startDate = new Date(this.value);
            const endDate = new Date(startDate);
            endDate.setFullYear(endDate.getFullYear() + 1);
            endDate.setDate(endDate.getDate() - 1);

            fiscalYearEnd.value = endDate.toISOString().split('T')[0];
        });

        // Formatage automatique de l'IBAN
        const ibanInput = document.getElementById('iban');
        ibanInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\s/g, '').toUpperCase();
            let formatted = '';

            for (let i = 0; i < value.length && i < 21; i++) {
                if (i > 0 && i % 4 === 0) {
                    formatted += ' ';
                }
                formatted += value[i];
            }

            e.target.value = formatted;
        });

        // Formatage automatique du numéro IDE
        const ideInput = document.getElementById('ide_number');
        ideInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^0-9]/g, '');

            if (value.length > 0) {
                let formatted = 'CHE-';
                for (let i = 0; i < value.length && i < 9; i++) {
                    if (i > 0 && i % 3 === 0) {
                        formatted += '.';
                    }
                    formatted += value[i];
                }
                e.target.value = formatted;
            }
        });

        // Validation IBAN
        function validateIBAN(iban) {
            // Vérification basique du format suisse
            const ibanRegex = /^CH\d{2}\d{17}$/;
            return ibanRegex.test(iban);
        }

        // Animation au scroll
        const formCards = document.querySelectorAll('.form-card');
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, { threshold: 0.1 });

        formCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'opacity 0.5s, transform 0.5s';
            observer.observe(card);
        });
    });
    </script>
</body>
</html>
