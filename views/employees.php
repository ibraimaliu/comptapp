<?php
/**
 * Vue Employees - Gestion des employés
 * Avec restrictions par plan d'abonnement
 */

// Vérifier l'authentification
if(!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'config/database_master.php';
require_once 'utils/EmployeeLimits.php';

$company_id = $_SESSION['company_id'];
$tenant_code = $_SESSION['tenant_code'] ?? $_SESSION['tenant_database'];

// Vérifier les limites du plan
$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();
$limits = EmployeeLimits::getEmployeeLimits($db_master, $tenant_code, $company_id);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Employés</title>
    <link rel="stylesheet" href="assets/css/employees.css">
</head>
<body>
    <div class="employees-container">
        <!-- En-tête avec informations du plan -->
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fa-solid fa-users"></i> Gestion des Employés</h1>
                <p class="subtitle">Gérez vos employés et leurs informations</p>
            </div>
            <div class="header-right">
                <?php if($limits['feature_locked']): ?>
                    <!-- Module bloqué -->
                    <div class="plan-info locked">
                        <i class="fa-solid fa-lock"></i>
                        <span class="plan-label">Module non disponible</span>
                        <button class="btn btn-upgrade" onclick="showUpgradeModal()">
                            <i class="fa-solid fa-arrow-up"></i> Mettre à niveau
                        </button>
                    </div>
                <?php else: ?>
                    <!-- Module activé - Afficher les limites -->
                    <div class="plan-info">
                        <i class="fa-solid fa-circle-info"></i>
                        <span class="plan-label">Plan: <?php echo htmlspecialchars($limits['plan_name']); ?></span>
                        <span class="plan-limits">
                            Employés: <?php echo $limits['current']; ?> /
                            <?php echo $limits['max'] == -1 ? '∞' : $limits['max']; ?>
                        </span>
                        <?php if($limits['allowed']): ?>
                            <button class="btn btn-primary" onclick="openEmployeeModal()">
                                <i class="fa-solid fa-plus"></i> Nouvel Employé
                            </button>
                        <?php else: ?>
                            <button class="btn btn-disabled" disabled title="Limite atteinte">
                                <i class="fa-solid fa-ban"></i> Limite atteinte
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if($limits['feature_locked']): ?>
            <!-- Message d'upgrade si module bloqué -->
            <div class="locked-message">
                <div class="locked-icon">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h2>Module de gestion des salaires non disponible</h2>
                <p><?php echo htmlspecialchars($limits['message']); ?></p>
                <button class="btn btn-upgrade-large" onclick="showUpgradeModal()">
                    <i class="fa-solid fa-rocket"></i> Découvrir les plans
                </button>
            </div>
        <?php else: ?>
            <!-- Contenu principal si module activé -->

            <!-- Barre de recherche et filtres -->
            <div class="toolbar">
                <div class="search-box">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="search-input" placeholder="Rechercher un employé..." />
                </div>
                <div class="filters">
                    <select id="filter-status" onchange="filterEmployees()">
                        <option value="all">Tous les statuts</option>
                        <option value="active" selected>Actifs uniquement</option>
                        <option value="inactive">Inactifs</option>
                    </select>
                    <select id="filter-type" onchange="filterEmployees()">
                        <option value="all">Tous les types</option>
                        <option value="full_time">Temps plein</option>
                        <option value="part_time">Temps partiel</option>
                        <option value="contractor">Contractuel</option>
                        <option value="intern">Stagiaire</option>
                    </select>
                </div>
            </div>

            <!-- Liste des employés -->
            <div class="employees-list" id="employees-list">
                <div class="loading">
                    <i class="fa-solid fa-spinner fa-spin"></i> Chargement...
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Nouvel Employé / Modifier -->
    <div id="employee-modal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h2 id="modal-title">Nouvel Employé</h2>
                <button class="close-btn" onclick="closeEmployeeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="employee-form">
                    <input type="hidden" id="employee-id" name="id">

                    <!-- Onglets -->
                    <div class="tabs">
                        <button type="button" class="tab-btn active" onclick="switchTab('general')">
                            <i class="fa-solid fa-user"></i> Informations générales
                        </button>
                        <button type="button" class="tab-btn" onclick="switchTab('contract')">
                            <i class="fa-solid fa-file-contract"></i> Contrat
                        </button>
                        <button type="button" class="tab-btn" onclick="switchTab('salary')">
                            <i class="fa-solid fa-money-bill"></i> Salaire
                        </button>
                        <button type="button" class="tab-btn" onclick="switchTab('social')">
                            <i class="fa-solid fa-shield-halved"></i> Assurances
                        </button>
                        <button type="button" class="tab-btn" onclick="switchTab('bank')">
                            <i class="fa-solid fa-building-columns"></i> Coordonnées bancaires
                        </button>
                    </div>

                    <!-- Onglet: Informations générales -->
                    <div id="tab-general" class="tab-content active">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Numéro matricule</label>
                                <input type="text" id="employee-number" name="employee_number" placeholder="Auto-généré">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group required">
                                <label>Prénom *</label>
                                <input type="text" id="first-name" name="first_name" required>
                            </div>
                            <div class="form-group required">
                                <label>Nom *</label>
                                <input type="text" id="last-name" name="last_name" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" id="email" name="email">
                            </div>
                            <div class="form-group">
                                <label>Téléphone</label>
                                <input type="tel" id="phone" name="phone">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Adresse</label>
                            <textarea id="address" name="address" rows="2"></textarea>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Code postal</label>
                                <input type="text" id="postal-code" name="postal_code">
                            </div>
                            <div class="form-group">
                                <label>Ville</label>
                                <input type="text" id="city" name="city">
                            </div>
                            <div class="form-group">
                                <label>Pays</label>
                                <input type="text" id="country" name="country" value="Suisse">
                            </div>
                        </div>
                    </div>

                    <!-- Onglet: Contrat -->
                    <div id="tab-contract" class="tab-content">
                        <div class="form-row">
                            <div class="form-group required">
                                <label>Date d'embauche *</label>
                                <input type="date" id="hire-date" name="hire_date" required>
                            </div>
                            <div class="form-group">
                                <label>Date de fin de contrat</label>
                                <input type="date" id="termination-date" name="termination_date">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group required">
                                <label>Poste *</label>
                                <input type="text" id="job-title" name="job_title" required>
                            </div>
                            <div class="form-group">
                                <label>Département</label>
                                <input type="text" id="department" name="department">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Type d'emploi</label>
                                <select id="employment-type" name="employment_type">
                                    <option value="full_time">Temps plein</option>
                                    <option value="part_time">Temps partiel</option>
                                    <option value="contractor">Contractuel</option>
                                    <option value="intern">Stagiaire</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Type de contrat</label>
                                <select id="contract-type" name="contract_type">
                                    <option value="cdi">CDI - Durée indéterminée</option>
                                    <option value="cdd">CDD - Durée déterminée</option>
                                    <option value="temporary">Temporaire</option>
                                    <option value="apprentice">Apprentissage</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Onglet: Salaire -->
                    <div id="tab-salary" class="tab-content">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Type de salaire</label>
                                <select id="salary-type" name="salary_type">
                                    <option value="monthly">Mensuel</option>
                                    <option value="hourly">Horaire</option>
                                    <option value="annual">Annuel</option>
                                </select>
                            </div>
                            <div class="form-group required">
                                <label>Salaire de base *</label>
                                <input type="number" id="base-salary" name="base_salary" step="0.01" required>
                            </div>
                            <div class="form-group">
                                <label>Devise</label>
                                <select id="currency" name="currency">
                                    <option value="CHF" selected>CHF</option>
                                    <option value="EUR">EUR</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Heures par semaine</label>
                                <input type="number" id="hours-per-week" name="hours_per_week" step="0.5" value="40">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" id="family-allowances" name="family_allowances" value="1">
                                    Allocations familiales
                                </label>
                            </div>
                            <div class="form-group">
                                <label>Nombre d'enfants</label>
                                <input type="number" id="num-children" name="num_children" min="0" value="0">
                            </div>
                        </div>
                    </div>

                    <!-- Onglet: Assurances -->
                    <div id="tab-social" class="tab-content">
                        <div class="form-group">
                            <label>Numéro AVS (13 chiffres)</label>
                            <input type="text" id="avs-number" name="avs_number" placeholder="756.XXXX.XXXX.XX">
                        </div>
                        <div class="form-group">
                            <label>Assurance accidents</label>
                            <input type="text" id="accident-insurance" name="accident_insurance">
                        </div>
                        <div class="form-group">
                            <label>Caisse de pension (LPP)</label>
                            <input type="text" id="pension-fund" name="pension_fund">
                        </div>
                    </div>

                    <!-- Onglet: Coordonnées bancaires -->
                    <div id="tab-bank" class="tab-content">
                        <div class="form-group">
                            <label>IBAN</label>
                            <input type="text" id="iban" name="iban" placeholder="CH93 0076 2011 6238 5295 7">
                        </div>
                        <div class="form-group">
                            <label>Nom de la banque</label>
                            <input type="text" id="bank-name" name="bank_name">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="notes" name="notes" rows="3"></textarea>
                    </div>

                    <!-- Statut actif -->
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="is-active" name="is_active" value="1" checked>
                            Employé actif
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEmployeeModal()">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="saveEmployee()">
                    <i class="fa-solid fa-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Upgrade -->
    <div id="upgrade-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Passer à un plan supérieur</h2>
                <button class="close-btn" onclick="closeUpgradeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p><?php echo htmlspecialchars(EmployeeLimits::getUpgradeMessage($limits['plan_name'])); ?></p>
                <div class="plans-comparison">
                    <div class="plan-card">
                        <h3>Starter</h3>
                        <p class="plan-price">CHF 29/mois</p>
                        <ul>
                            <li><i class="fa-solid fa-check"></i> Jusqu'à 3 sociétés</li>
                            <li><i class="fa-solid fa-check"></i> Jusqu'à 3 employés</li>
                            <li><i class="fa-solid fa-check"></i> Gestion des salaires</li>
                        </ul>
                    </div>
                    <div class="plan-card featured">
                        <div class="plan-badge">Recommandé</div>
                        <h3>Professionnel</h3>
                        <p class="plan-price">CHF 99/mois</p>
                        <ul>
                            <li><i class="fa-solid fa-check"></i> Sociétés illimitées</li>
                            <li><i class="fa-solid fa-check"></i> Employés illimités</li>
                            <li><i class="fa-solid fa-check"></i> Toutes les fonctionnalités</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeUpgradeModal()">Plus tard</button>
                <button type="button" class="btn btn-primary" onclick="window.location.href='index.php?page=mon_compte'">
                    <i class="fa-solid fa-rocket"></i> Changer de plan
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/employees.js"></script>
</body>
</html>
