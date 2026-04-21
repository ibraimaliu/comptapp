<?php
/**
 * Page: Paramètres
 * Version: 4.0 - Refonte complète du design
 * Description: Interface moderne pour configurer l'application
 */

// Inclure les modèles nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/Company.php';
include_once dirname(__DIR__) . '/models/AccountingPlan.php';
include_once dirname(__DIR__) . '/models/Category.php';
include_once dirname(__DIR__) . '/models/TVARate.php';

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Vérifier la connexion
if (!$db) {
    die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 8px; margin: 20px;">
        <h3>❌ Erreur de connexion à la base de données</h3>
        <p>Impossible de se connecter à la base de données.</p>
    </div>');
}

// Vérifier si une société est sélectionnée
$company_id = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;

// S'il n'y a pas de société sélectionnée, vérifier si l'utilisateur en a une par défaut
if (!$company_id && isset($_SESSION['user_id'])) {
    $query = "SELECT id FROM companies WHERE user_id = ? ORDER BY id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        $company_id = $result['id'];
        $_SESSION['company_id'] = $company_id;
    }
}

// Si toujours pas de société, afficher un message (ne devrait plus arriver)
if (!$company_id) {
    echo '<div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 8px; margin: 20px;">
        <h3>⚠️ Aucune société sélectionnée</h3>
        <p>Une erreur inattendue s\'est produite. Veuillez vous déconnecter et vous reconnecter.</p>
        <a href="index.php?page=logout" class="btn btn-primary">Se déconnecter</a>
    </div>';
    exit;
}

// Charger les données de la société
$company = new Company($db);
$company->id = $company_id;
$company_exists = $company->read();

// Si la société n'existe pas, rediriger
if (!$company_exists) {
    echo '<div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 8px; margin: 20px;">
        <h3>❌ Erreur</h3>
        <p>La société sélectionnée n\'existe pas.</p>
        <p><a href="index.php?page=home" style="color: #721c24; font-weight: bold;">← Retour à l\'accueil</a></p>
    </div>';
    exit;
}

// Charger le plan comptable
$accountingPlan = new AccountingPlan($db);
$accounts_stmt = $accountingPlan->readByCompany($company_id);
$accounts = [];
while ($row = $accounts_stmt->fetch(PDO::FETCH_ASSOC)) {
    $accounts[] = $row;
}

// Définir les valeurs par défaut
if (!$company->fiscal_year_start) $company->fiscal_year_start = date('Y-01-01');
if (!$company->fiscal_year_end) $company->fiscal_year_end = date('Y-12-31');
if (!$company->tva_status) $company->tva_status = 'non';

$page_title = "Paramètres";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Gestion Comptable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Container principal */
        .settings-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .settings-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.2em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .settings-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.05em;
        }

        /* Navigation par onglets */
        .settings-tabs {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .tabs-nav {
            display: flex;
            border-bottom: 2px solid #f7fafc;
            overflow-x: auto;
            flex-wrap: wrap;
        }

        .tab-button {
            flex: 1;
            min-width: 120px;
            padding: 18px 20px;
            background: transparent;
            border: none;
            color: #718096;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-bottom: 3px solid transparent;
            font-size: 0.95em;
        }

        .tab-button:hover {
            background: #f7fafc;
            color: #667eea;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f7fafc;
        }

        .tab-button i {
            font-size: 1.1em;
        }

        /* Contenu des onglets */
        .tab-content {
            padding: 0;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
            animation: fadeIn 0.3s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Cards */
        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 20px;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .settings-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f7fafc;
        }

        .card-title {
            margin: 0;
            font-size: 1.4em;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: #667eea;
        }

        .card-description {
            margin: 0 0 20px 0;
            color: #718096;
            font-size: 0.95em;
            line-height: 1.6;
        }

        /* Info Display */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            background: #f7fafc;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-size: 0.85em;
            color: #718096;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .info-value {
            font-size: 1.1em;
            color: #2d3748;
            font-weight: 500;
        }

        .text-muted {
            color: #a0aec0;
            font-style: italic;
        }

        /* Formulaires */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95em;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95em;
            transition: all 0.3s;
            box-sizing: border-box;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .form-row .form-group {
            margin-bottom: 0;
        }

        .form-text {
            display: block;
            margin-top: 5px;
            font-size: 0.85em;
            color: #718096;
        }

        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        /* Boutons */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95em;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-outline {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }

        .btn-outline:hover {
            background: #667eea;
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(56, 239, 125, 0.3);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(56, 239, 125, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(235, 51, 73, 0.3);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(235, 51, 73, 0.4);
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.85em;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .alert i {
            font-size: 1.2em;
            margin-top: 2px;
        }

        .alert-info {
            background: #ebf5fb;
            border-left-color: #3498db;
            color: #21618c;
        }

        .alert-warning {
            background: #fef5e7;
            border-left-color: #f39c12;
            color: #7d6608;
        }

        .alert-success {
            background: #eafaf1;
            border-left-color: #27ae60;
            color: #1e8449;
        }

        .alert-danger {
            background: #fadbd8;
            border-left-color: #e74c3c;
            color: #922b21;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .data-table thead {
            background: #f7fafc;
        }

        .data-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.9em;
        }

        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            color: #4a5568;
        }

        .data-table tbody tr:hover {
            background: #f7fafc;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 4em;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #4a5568;
            font-size: 1.3em;
        }

        .empty-state p {
            margin: 0 0 20px 0;
            color: #718096;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .settings-header h1 {
                font-size: 1.6em;
            }

            .tabs-nav {
                flex-direction: column;
            }

            .tab-button {
                min-width: auto;
                width: 100%;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .form-row {
                flex-direction: column;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="settings-container">
    <!-- Header -->
    <div class="settings-header">
        <h1><i class="fas fa-cog"></i> Paramètres</h1>
        <p>Configuration et gestion de votre application</p>
    </div>

    <!-- Navigation Tabs -->
    <div class="settings-tabs">
        <div class="tabs-nav">
            <button class="tab-button active" data-tab="company">
                <i class="fas fa-building"></i>
                <span>Société</span>
            </button>
            <button class="tab-button" data-tab="qr-invoice">
                <i class="fas fa-qrcode"></i>
                <span>QR-Factures</span>
            </button>
            <button class="tab-button" data-tab="accounting">
                <i class="fas fa-list-ol"></i>
                <span>Plan comptable</span>
            </button>
            <button class="tab-button" data-tab="categories">
                <i class="fas fa-tags"></i>
                <span>Catégories</span>
            </button>
            <button class="tab-button" data-tab="tva">
                <i class="fas fa-percent"></i>
                <span>TVA</span>
            </button>
            <button class="tab-button" data-tab="export">
                <i class="fas fa-file-export"></i>
                <span>Export</span>
            </button>
            <button class="tab-button" data-tab="profile">
                <i class="fas fa-user"></i>
                <span>Profil</span>
            </button>
            <button class="tab-button" data-tab="security">
                <i class="fas fa-shield-alt"></i>
                <span>Sécurité</span>
            </button>
            <button class="tab-button" data-tab="advanced">
                <i class="fas fa-cogs"></i>
                <span>Avancé</span>
            </button>
        </div>

        <div class="tab-content">
            <!-- Onglet Société -->
            <div class="tab-pane active" id="tab-company">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-building"></i>
                            Informations de la société
                        </h2>
                        <button id="editCompanyInfoBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Nom de la société</div>
                            <div class="info-value" id="company-name-display">
                                <?php echo htmlspecialchars($company->name); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Propriétaire</div>
                            <div class="info-value" id="owner-name-display">
                                <?php echo htmlspecialchars($company->owner_surname . ' ' . $company->owner_name); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Période comptable</div>
                            <div class="info-value" id="accounting-period-display">
                                <?php echo date('d.m.Y', strtotime($company->fiscal_year_start)) . ' - ' . date('d.m.Y', strtotime($company->fiscal_year_end)); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Statut TVA</div>
                            <div class="info-value" id="tva-status-display">
                                <?php echo ($company->tva_status == 'oui') ? 'Soumis à la TVA' : 'Non soumis à la TVA'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet QR-Factures -->
            <div class="tab-pane" id="tab-qr-invoice">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-qrcode"></i>
                            Configuration QR-Factures Suisses
                        </h2>
                        <button id="editQRSettingsBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                    </div>

                    <p class="card-description">
                        <i class="fas fa-info-circle"></i>
                        Configurez vos informations bancaires pour générer des QR-factures conformes au standard suisse (ISO 20022)
                    </p>

                    <!-- Affichage en lecture seule -->
                    <div id="qr-settings-display">
                        <div class="info-grid">
                            <div class="info-item">
                                <div class="info-label">QR-IBAN</div>
                                <div class="info-value">
                                    <?php
                                    if(!empty($company->qr_iban)) {
                                        echo htmlspecialchars($company->qr_iban);
                                    } else {
                                        echo '<span class="text-muted">Non configuré</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">IBAN Bancaire</div>
                                <div class="info-value">
                                    <?php
                                    if(!empty($company->bank_iban)) {
                                        echo htmlspecialchars($company->bank_iban);
                                    } else {
                                        echo '<span class="text-muted">Non configuré</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Adresse</div>
                                <div class="info-value">
                                    <?php
                                    if(!empty($company->address)) {
                                        echo htmlspecialchars($company->address);
                                    } else {
                                        echo '<span class="text-muted">Non configurée</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Localité</div>
                                <div class="info-value">
                                    <?php
                                    if(!empty($company->postal_code) && !empty($company->city)) {
                                        echo htmlspecialchars($company->postal_code . ' ' . $company->city);
                                    } else {
                                        echo '<span class="text-muted">Non configurée</span>';
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Formulaire d'édition (caché par défaut) -->
                    <form id="qr-settings-form" style="display: none;">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="qr-iban">
                                    <i class="fas fa-qrcode"></i> QR-IBAN *
                                    <small>Format: CH + 19 chiffres (positions 5-9 entre 30000-31999)</small>
                                </label>
                                <input type="text" id="qr-iban" name="qr_iban" class="form-control"
                                       value="<?php echo htmlspecialchars($company->qr_iban ?? ''); ?>"
                                       placeholder="CH93 0076 2011 6238 5295 7"
                                       pattern="CH\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d"
                                       maxlength="26">
                                <button type="button" id="validateIBANBtn" class="btn btn-sm btn-outline" style="margin-top: 8px;">
                                    <i class="fas fa-check-circle"></i> Valider l'IBAN
                                </button>
                            </div>

                            <div class="form-group">
                                <label for="bank-iban">
                                    <i class="fas fa-university"></i> IBAN Bancaire
                                    <small>IBAN standard pour les paiements</small>
                                </label>
                                <input type="text" id="bank-iban" name="bank_iban" class="form-control"
                                       value="<?php echo htmlspecialchars($company->bank_iban ?? ''); ?>"
                                       placeholder="CH93 0076 2011 6238 5295 7"
                                       pattern="CH\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d"
                                       maxlength="26">
                            </div>
                        </div>

                        <div class="alert alert-info" style="margin-top: 20px;">
                            <i class="fas fa-info-circle"></i>
                            <strong>Note:</strong> Le QR-IBAN est requis pour générer des QR-factures. Les positions 5 à 9 doivent être comprises entre 30000 et 31999.
                        </div>

                        <div class="form-actions">
                            <button type="button" id="cancelQREditBtn" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Annuler
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Onglet Plan Comptable -->
            <div class="tab-pane" id="tab-accounting">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-list-ol"></i>
                            Plan comptable
                        </h2>
                        <div class="form-actions" style="margin: 0;">
                            <button id="importPlanBtn" class="btn btn-primary btn-sm">
                                <i class="fas fa-upload"></i> Importer CSV
                            </button>
                            <button id="exportPlanBtn" class="btn btn-outline btn-sm">
                                <i class="fas fa-download"></i> Exporter CSV
                            </button>
                            <button id="addAccountBtn" class="btn btn-success btn-sm">
                                <i class="fas fa-plus"></i> Ajouter
                            </button>
                            <button id="resetPlanBtn" class="btn btn-danger btn-sm">
                                <i class="fas fa-trash"></i> Réinitialiser
                            </button>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Format CSV accepté:</strong> Numéro;Intitulé;Catégorie;Type<br>
                            <strong>Exemple:</strong> 1000;Caisse;Actif;Bilan
                        </div>
                    </div>

                    <div id="accounting-plan-list">
                        <?php if(count($accounts) > 0): ?>
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Numéro</th>
                                        <th>Intitulé</th>
                                        <th>Catégorie</th>
                                        <th>Type</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($accounts as $account): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($account['number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($account['name']); ?></td>
                                            <td><?php echo htmlspecialchars($account['category']); ?></td>
                                            <td><?php echo htmlspecialchars($account['type']); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline" onclick="editAccount(<?php echo $account['id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <p style="margin-top: 15px; color: #718096;">
                                <i class="fas fa-check-circle" style="color: #38ef7d;"></i>
                                <?php echo count($accounts); ?> compte(s) au plan comptable
                            </p>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-list-ol"></i>
                                <h3>Aucun compte comptable</h3>
                                <p>Importez un plan comptable ou créez votre premier compte</p>
                                <button class="btn btn-primary" id="importFirstPlan">
                                    <i class="fas fa-upload"></i> Importer un plan comptable
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Onglet Catégories -->
            <div class="tab-pane" id="tab-categories">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-tags"></i>
                            Catégories de dépenses
                        </h2>
                        <button id="addCategoryBtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </div>

                    <p class="card-description">
                        Gérez les catégories pour classifier vos transactions
                    </p>

                    <div id="categories-list">
                        <div class="empty-state">
                            <i class="fas fa-tags"></i>
                            <h3>Chargement des catégories...</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet TVA -->
            <div class="tab-pane" id="tab-tva">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-percent"></i>
                            Taux de TVA
                        </h2>
                        <button id="addTVABtn" class="btn btn-primary btn-sm">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </div>

                    <p class="card-description">
                        Gérez les taux de TVA applicables dans votre entreprise
                    </p>

                    <div id="tva-list">
                        <div class="empty-state">
                            <i class="fas fa-percent"></i>
                            <h3>Chargement des taux TVA...</h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Export -->
            <div class="tab-pane" id="tab-export">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-file-export"></i>
                            Exportation de données
                        </h2>
                    </div>

                    <p class="card-description">
                        Exportez vos données comptables aux formats CSV ou JSON
                    </p>

                    <div class="form-row">
                        <div class="form-group" style="flex: 1;">
                            <label for="export-type">Type de données</label>
                            <select id="export-type" class="form-control">
                                <option value="transactions">Transactions</option>
                                <option value="invoices">Factures</option>
                                <option value="contacts">Contacts</option>
                                <option value="accounting_plan">Plan comptable</option>
                                <option value="all">Toutes les données (JSON)</option>
                            </select>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="export-format">Format</label>
                            <select id="export-format" class="form-control">
                                <option value="csv">CSV (Excel compatible)</option>
                                <option value="json">JSON</option>
                            </select>
                        </div>
                    </div>

                    <button id="exportDataBtn" class="btn btn-success">
                        <i class="fas fa-download"></i> Télécharger l'export
                    </button>

                    <div class="alert alert-info" style="margin-top: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            Les fichiers CSV sont compatibles avec Excel et Google Sheets.
                            Le format JSON est recommandé pour les sauvegardes complètes.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Profil -->
            <div class="tab-pane" id="tab-profile">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user"></i>
                            Profil utilisateur
                        </h2>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Nom d'utilisateur</div>
                            <div class="info-value" id="profile-username">
                                <?php echo htmlspecialchars($_SESSION['username'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Adresse email</div>
                            <div class="info-value">
                                <input type="email" id="profile-email" class="form-control" value="">
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Membre depuis</div>
                            <div class="info-value" id="profile-created">-</div>
                        </div>
                    </div>

                    <button id="updateProfileBtn" class="btn btn-primary" style="margin-top: 20px;">
                        <i class="fas fa-save"></i> Mettre à jour le profil
                    </button>
                </div>

                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-key"></i>
                            Changer le mot de passe
                        </h2>
                    </div>

                    <div class="form-group">
                        <label for="current-password">Mot de passe actuel</label>
                        <input type="password" id="current-password" class="form-control" placeholder="Entrez votre mot de passe actuel">
                    </div>

                    <div class="form-group">
                        <label for="new-password">Nouveau mot de passe</label>
                        <input type="password" id="new-password" class="form-control" placeholder="Au moins 8 caractères">
                        <small class="form-text">Minimum 8 caractères</small>
                    </div>

                    <div class="form-group">
                        <label for="confirm-password">Confirmer le mot de passe</label>
                        <input type="password" id="confirm-password" class="form-control" placeholder="Retapez le nouveau mot de passe">
                    </div>

                    <button id="changePasswordBtn" class="btn btn-primary">
                        <i class="fas fa-lock"></i> Changer le mot de passe
                    </button>

                    <div class="alert alert-info" style="margin-top: 20px;">
                        <i class="fas fa-shield-alt"></i>
                        <div>
                            <strong>Conseils de sécurité:</strong><br>
                            • Utilisez au moins 8 caractères<br>
                            • Combinez lettres, chiffres et symboles<br>
                            • Évitez les mots du dictionnaire
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Sécurité -->
            <div class="tab-pane" id="tab-security">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-shield-alt"></i>
                            Sécurité & Sauvegarde
                        </h2>
                    </div>

                    <h3 style="margin-bottom: 15px; color: #2d3748; font-size: 1.2em;">
                        <i class="fas fa-database"></i> Sauvegarde des données
                    </h3>

                    <p style="color: #718096; margin-bottom: 20px;">
                        Téléchargez une sauvegarde complète de toutes vos données au format JSON
                    </p>

                    <button onclick="window.open('assets/ajax/data_export.php?type=all&format=json', '_blank')" class="btn btn-success">
                        <i class="fas fa-download"></i> Télécharger sauvegarde complète (JSON)
                    </button>

                    <div class="alert alert-warning" style="margin-top: 20px;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Recommandations:</strong><br>
                            • Effectuez une sauvegarde mensuelle minimum<br>
                            • Stockez vos sauvegardes dans un endroit sûr<br>
                            • Conservez plusieurs versions
                        </div>
                    </div>
                </div>

                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-user-shield"></i>
                            Conseils de sécurité
                        </h2>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb"></i>
                        <div>
                            <strong>Bonnes pratiques:</strong><br>
                            🔒 Utilisez un mot de passe fort et unique<br>
                            👥 Ne partagez jamais vos identifiants<br>
                            🚪 Déconnectez-vous après chaque session<br>
                            💾 Effectuez des sauvegardes régulières<br>
                            📧 Vérifiez l'authenticité des emails suspects
                        </div>
                    </div>

                    <div class="info-grid" style="margin-top: 20px;">
                        <div class="info-item">
                            <div class="info-label">Session active</div>
                            <div class="info-value">COMPTAPP_SESSION</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Timeout</div>
                            <div class="info-value">1 heure d'inactivité</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Onglet Avancé -->
            <div class="tab-pane" id="tab-advanced">
                <div class="settings-card">
                    <div class="card-header">
                        <h2 class="card-title">
                            <i class="fas fa-cogs"></i>
                            Configuration avancée
                        </h2>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Version de l'application</div>
                            <div class="info-value">4.0.0</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Version PHP</div>
                            <div class="info-value"><?php echo phpversion(); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Société active</div>
                            <div class="info-value"><?php echo htmlspecialchars($company->name); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">ID Société</div>
                            <div class="info-value"><?php echo $company_id; ?></div>
                        </div>
                    </div>

                    <div class="alert alert-info" style="margin-top: 20px;">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <strong>Documentation complète disponible:</strong><br>
                            • GUIDE_PARAMETRES.md - Guide utilisateur<br>
                            • PARAMETRES_README.md - Guide rapide<br>
                            • REFONTE_PARAMETRES_COMPLETE.md - Documentation technique
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Import Plan Comptable -->
<div id="importPlanModal" class="modal" style="display: none;">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2>
                <i class="fas fa-upload"></i>
                Importer un plan comptable (CSV)
            </h2>
            <button class="close-modal" onclick="closeModal('importPlanModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <div>
                    <strong>Formats acceptés:</strong> CSV, TXT (avec tabulations), XLS, XLSX<br>
                    <strong>Structure:</strong> Numéro | Intitulé | Catégorie | Type<br>
                    <strong>Exemple:</strong><br>
                    <code style="display: block; margin-top: 8px; padding: 8px; background: #2d3748; color: #fff; border-radius: 4px; font-family: monospace; font-size: 12px;">
                        Numéro&nbsp;&nbsp;&nbsp;&nbsp;Intitulé&nbsp;&nbsp;&nbsp;&nbsp;Catégorie&nbsp;&nbsp;&nbsp;&nbsp;Type<br>
                        1000&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Caisse&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Actif&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Bilan<br>
                        1010&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Poste&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Actif&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Bilan
                    </code>
                    <strong style="margin-top: 8px; display: block;">Pour CSV/TXT:</strong> Séparateur = Tabulation (touche TAB)<br>
                    <strong>Pour Excel:</strong> Colonnes A, B, C, D dans une feuille<br>
                    <strong>Encodage:</strong> UTF-8
                </div>
            </div>

            <div class="form-group">
                <label for="import-file">
                    <i class="fas fa-file-import"></i>
                    Fichier à importer
                </label>
                <input type="file"
                       id="import-file"
                       accept=".csv,.txt,.xls,.xlsx"
                       class="form-control">
                <small class="form-text">
                    <strong>Fichiers exemples:</strong>
                    <a href="plan_comptable_exemple.csv" download><i class="fas fa-file-csv"></i> CSV</a> •
                    <a href="plan_comptable_exemple.xlsx" download><i class="fas fa-file-excel"></i> Excel</a>
                </small>
            </div>

            <div class="form-group">
                <label>
                    <i class="fas fa-cogs"></i>
                    Action
                </label>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="radio" name="import_action" value="replace" checked>
                        <div>
                            <strong>Remplacer le plan actuel</strong><br>
                            <small style="color: #718096;">Supprime les comptes non utilisés et importe les nouveaux</small>
                        </div>
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                        <input type="radio" name="import_action" value="append">
                        <div>
                            <strong>Ajouter au plan actuel</strong><br>
                            <small style="color: #718096;">Conserve les comptes existants et ajoute les nouveaux</small>
                        </div>
                    </label>
                </div>
            </div>

            <div id="import-error" class="alert alert-danger" style="display: none;">
                <i class="fas fa-exclamation-triangle"></i>
                <div id="import-error-message"></div>
            </div>

            <div id="import-success" class="alert alert-success" style="display: none;">
                <i class="fas fa-check-circle"></i>
                <div id="import-success-message"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="closeModal('importPlanModal')">
                <i class="fas fa-times"></i> Annuler
            </button>
            <button type="button" id="submitImportPlanBtn" class="btn btn-primary">
                <i class="fas fa-upload"></i> Importer
            </button>
        </div>
    </div>
</div>

<style>
/* Styles pour le modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.65);
    backdrop-filter: blur(4px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.25s ease-in;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3), 0 0 0 1px rgba(0, 0, 0, 0.1);
    max-width: 700px;
    width: 100%;
    max-height: 85vh;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: slideUp 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(40px) scale(0.95);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.modal-header {
    padding: 28px 32px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
    font-size: 1.5em;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
}

.modal-header h2 i {
    font-size: 1.3em;
    opacity: 0.95;
}

.close-modal {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    font-size: 1.8em;
    color: white;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s;
    font-weight: 300;
}

.close-modal:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: rotate(90deg);
}

.modal-body {
    padding: 32px;
    overflow-y: auto;
    flex: 1;
}

.modal-body .alert {
    margin-bottom: 24px;
}

.modal-body .form-group {
    margin-bottom: 24px;
}

.modal-body .form-group label {
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 0.95em;
}

.modal-body .form-group label i {
    color: #667eea;
    font-size: 1.1em;
}

.modal-body .form-control {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 14px 16px;
    font-size: 0.95em;
    transition: all 0.3s;
}

.modal-body .form-control:hover {
    border-color: #cbd5e0;
}

.modal-body .form-control:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
}

.modal-body input[type="radio"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
    accent-color: #667eea;
    margin: 0;
}

.modal-body input[type="radio"] + div {
    flex: 1;
}

.modal-footer {
    padding: 24px 32px;
    background: #f7fafc;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

@media (max-width: 768px) {
    .modal-content {
        max-width: 95%;
        max-height: 90vh;
    }

    .modal-header {
        padding: 20px 24px;
    }

    .modal-header h2 {
        font-size: 1.2em;
    }

    .modal-body {
        padding: 24px;
    }

    .modal-footer {
        padding: 16px 24px;
        flex-direction: column;
    }

    .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script src="assets/js/parametres.js"></script>
<script>
// Fonctions modales
function openModal(modalId) {
    const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = typeof modalId === 'string' ? document.getElementById(modalId) : modalId;
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';

        // Réinitialiser le formulaire si c'est le modal d'import
        if (modalId === 'importPlanModal' || modal.id === 'importPlanModal') {
            const fileInput = document.getElementById('import-file');
            if (fileInput) fileInput.value = '';

            const errorDiv = document.getElementById('import-error');
            const successDiv = document.getElementById('import-success');
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';
        }
    }
}

// Fermer modal en cliquant en dehors
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        closeModal(e.target);
    }
});

// Fonction d'import CSV
function submitImportPlan() {
    const fileInput = document.getElementById('import-file');
    const importAction = document.querySelector('input[name="import_action"]:checked').value;
    const errorDiv = document.getElementById('import-error');
    const errorMsg = document.getElementById('import-error-message');
    const successDiv = document.getElementById('import-success');
    const successMsg = document.getElementById('import-success-message');
    const submitBtn = document.getElementById('submitImportPlanBtn');

    // Réinitialiser les messages
    errorDiv.style.display = 'none';
    successDiv.style.display = 'none';

    // Validation
    if (!fileInput.files || fileInput.files.length === 0) {
        errorMsg.innerHTML = '<strong>Erreur:</strong> Veuillez sélectionner un fichier';
        errorDiv.style.display = 'flex';
        return;
    }

    const file = fileInput.files[0];

    // Vérifier l'extension
    const fileName = file.name.toLowerCase();
    const allowedExtensions = ['.csv', '.txt', '.xls', '.xlsx'];
    const isValidExtension = allowedExtensions.some(ext => fileName.endsWith(ext));

    if (!isValidExtension) {
        errorMsg.innerHTML = '<strong>Format invalide:</strong> Formats acceptés: CSV, TXT, XLS, XLSX';
        errorDiv.style.display = 'flex';
        return;
    }

    // Vérifier la taille (max 10MB)
    if (file.size > 10 * 1024 * 1024) {
        errorMsg.innerHTML = '<strong>Fichier trop volumineux:</strong> La taille maximale est de 10 MB';
        errorDiv.style.display = 'flex';
        return;
    }

    // Préparer le FormData
    const formData = new FormData();
    formData.append('csv_file', file);
    formData.append('import_action', importAction);

    // Afficher le loading
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Import en cours...';
    submitBtn.disabled = true;

    // Envoyer la requête
    fetch('assets/ajax/accounting_plan_import.php?action=import_csv', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.innerHTML = '<i class="fas fa-upload"></i> Importer';
        submitBtn.disabled = false;

        if (data.success) {
            let message = `<strong>Succès!</strong> ${data.imported} compte(s) importé(s)`;

            if (data.errors && data.errors.length > 0) {
                message += '<br><br><strong>Avertissements:</strong><ul style="margin: 10px 0 0 0; padding-left: 20px;">';
                data.errors.slice(0, 10).forEach(error => {
                    message += `<li>${error}</li>`;
                });
                if (data.errors.length > 10) {
                    message += `<li>... et ${data.errors.length - 10} autre(s) avertissement(s)</li>`;
                }
                message += '</ul>';
            }

            successMsg.innerHTML = message;
            successDiv.style.display = 'flex';

            // Recharger la page après 2 secondes
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            let errorMessage = '<strong>Erreur lors de l\'import:</strong><br>';

            if (data.message) {
                errorMessage += data.message;
            }

            if (data.errors && data.errors.length > 0) {
                errorMessage += '<ul style="margin: 10px 0 0 0; padding-left: 20px;">';
                data.errors.slice(0, 5).forEach(error => {
                    errorMessage += `<li>${error}</li>`;
                });
                if (data.errors.length > 5) {
                    errorMessage += `<li>... et ${data.errors.length - 5} autre(s) erreur(s)</li>`;
                }
                errorMessage += '</ul>';
            }

            errorMsg.innerHTML = errorMessage;
            errorDiv.style.display = 'flex';
        }
    })
    .catch(error => {
        submitBtn.innerHTML = '<i class="fas fa-upload"></i> Importer';
        submitBtn.disabled = false;

        errorMsg.innerHTML = '<strong>Erreur réseau:</strong> Impossible de se connecter au serveur. Vérifiez votre connexion.';
        errorDiv.style.display = 'flex';
        console.error('Erreur:', error);
    });
}

// Gestion des onglets
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tab-button');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.dataset.tab;

            // Désactiver tous les onglets
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));

            // Activer l'onglet cliqué
            this.classList.add('active');
            document.getElementById('tab-' + tabId).classList.add('active');

            // Sauvegarder l'onglet actif dans l'URL
            window.location.hash = tabId;
        });
    });

    // Restaurer l'onglet depuis l'URL au chargement
    if (window.location.hash) {
        const tabId = window.location.hash.substring(1);
        const targetButton = document.querySelector(`[data-tab="${tabId}"]`);
        if (targetButton) {
            targetButton.click();
        }
    }

    // Event listeners pour les boutons
    const importPlanBtn = document.getElementById('importPlanBtn');
    if (importPlanBtn) {
        importPlanBtn.addEventListener('click', function() {
            openModal('importPlanModal');
        });
    }

    const importFirstPlan = document.getElementById('importFirstPlan');
    if (importFirstPlan) {
        importFirstPlan.addEventListener('click', function() {
            openModal('importPlanModal');
        });
    }

    const submitImportBtn = document.getElementById('submitImportPlanBtn');
    if (submitImportBtn) {
        submitImportBtn.addEventListener('click', submitImportPlan);
    }

    const exportPlanBtn = document.getElementById('exportPlanBtn');
    if (exportPlanBtn) {
        exportPlanBtn.addEventListener('click', function() {
            window.open('assets/ajax/accounting_plan_import.php?action=export_csv', '_blank');
        });
    }

    const resetPlanBtn = document.getElementById('resetPlanBtn');
    if (resetPlanBtn) {
        resetPlanBtn.addEventListener('click', function() {
            if (confirm('Êtes-vous sûr de vouloir réinitialiser le plan comptable ?\n\nCette action supprimera uniquement les comptes non utilisés.')) {
                fetch('assets/ajax/accounting_plan_import.php?action=reset', {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Plan comptable réinitialisé avec succès !');
                        window.location.reload();
                    } else {
                        alert('Erreur: ' + (data.message || 'Impossible de réinitialiser'));
                    }
                })
                .catch(error => {
                    alert('Erreur réseau: ' + error.message);
                });
            }
        });
    }

    const exportDataBtn = document.getElementById('exportDataBtn');
    if (exportDataBtn) {
        exportDataBtn.addEventListener('click', function() {
            const type = document.getElementById('export-type').value;
            const format = document.getElementById('export-format').value;
            window.open(`assets/ajax/data_export.php?type=${type}&format=${format}`, '_blank');
        });
    }

    // Charger le profil utilisateur
    if (typeof loadUserProfile === 'function') {
        loadUserProfile();
    }
});
</script>

</body>
</html>
