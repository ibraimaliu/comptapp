<?php
// Inclure les configurations
include_once 'config/config.php';

// Déterminer la page actuelle pour charger les CSS spécifiques
$current_page = isset($_GET['page']) ? $_GET['page'] : 'home';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
    <!-- Styles spécifiques à la page courante -->
    <?php
    // Charger les CSS spécifiques en fonction de la page
    if ($current_page == 'adresses') {
        echo '<link rel="stylesheet" href="assets/css/adresses.css">';
    } else if ($current_page == 'comptabilite') {
        echo '<link rel="stylesheet" href="assets/css/comptabilite.css">';
    }
    // Vous pouvez ajouter d'autres conditions pour d'autres pages
    ?>

    <!-- Script pour le toggle du sous-menu (doit être dans le head pour être disponible immédiatement) -->
    <script>
        function toggleSubmenu(event, element) {
            // Empêcher le comportement par défaut du lien
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }

            console.log('toggleSubmenu appelé', element);

            // Trouver le parent li avec la classe has-submenu
            let parentLi = element;
            while (parentLi && parentLi.tagName !== 'LI') {
                parentLi = parentLi.parentElement;
            }

            // Vérifier que c'est bien un has-submenu
            if (!parentLi || !parentLi.classList.contains('has-submenu')) {
                console.error('Parent .has-submenu non trouvé', parentLi);
                return false;
            }

            // Vérifier l'état actuel
            const isOpen = parentLi.classList.contains('submenu-open');
            console.log('État actuel:', isOpen ? 'ouvert' : 'fermé');

            // Fermer tous les autres sous-menus (comportement accordéon)
            const allSubmenus = document.querySelectorAll('.has-submenu');
            allSubmenus.forEach(function(submenu) {
                if (submenu !== parentLi) {
                    submenu.classList.remove('submenu-open');
                }
            });

            // Basculer l'état du sous-menu actuel
            if (isOpen) {
                parentLi.classList.remove('submenu-open');
                console.log('Fermé');
            } else {
                parentLi.classList.add('submenu-open');
                console.log('Ouvert');
            }

            return false;
        }

        // Auto-ouvrir le sous-menu si on est sur une page correspondante
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM chargé, recherche des items actifs');
            const activeSubmenuItem = document.querySelector('.submenu .submenu-item.active');
            if (activeSubmenuItem) {
                console.log('Item actif trouvé:', activeSubmenuItem);
                const parentSubmenu = activeSubmenuItem.closest('.has-submenu');
                if (parentSubmenu) {
                    console.log('Ouverture automatique du sous-menu parent');
                    parentSubmenu.classList.add('submenu-open');
                }
            }
        });
    </script>
</head>
<body>
    <?php if(isLoggedIn()): ?>
    <!-- Sélecteur de Société et Exercice Comptable -->
    <?php
    // Récupérer les sociétés de l'utilisateur
    $companies = [];
    $fiscal_years = [];
    $current_company_name = '';

    if (isset($_SESSION['user_id'])) {
        require_once 'config/database.php';
        require_once 'models/Company.php';

        $database = new Database();
        $db = $database->getConnection();
        $company_model = new Company($db);

        // Récupérer toutes les sociétés de l'utilisateur
        $companies_query = "SELECT * FROM companies WHERE user_id = :user_id ORDER BY name";
        $stmt = $db->prepare($companies_query);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->execute();
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Si company_id est défini, récupérer le nom et les exercices
        if (isset($_SESSION['company_id'])) {
            $current_company_query = "SELECT * FROM companies WHERE id = :id";
            $stmt_current = $db->prepare($current_company_query);
            $stmt_current->bindParam(':id', $_SESSION['company_id']);
            $stmt_current->execute();
            $current_company = $stmt_current->fetch(PDO::FETCH_ASSOC);

            if ($current_company) {
                $current_company_name = $current_company['name'];

                // Générer les exercices comptables (années passées et futures)
                $current_year = date('Y');
                for ($i = -2; $i <= 2; $i++) {
                    $year = $current_year + $i;
                    $fiscal_years[] = [
                        'year' => $year,
                        'label' => "Exercice {$year}",
                        'start' => "{$year}-01-01",
                        'end' => "{$year}-12-31"
                    ];
                }
            }
        }
    }
    ?>

    <?php if (!empty($companies)): ?>
    <div class="company-selector-bar">
        <div class="selector-container">
            <!-- Sélecteur de société -->
            <div class="selector-group">
                <label>
                    <i class="fa-solid fa-building"></i>
                    <span>Société</span>
                </label>
                <select id="company-selector" class="selector-dropdown" onchange="switchCompany(this.value)">
                    <?php if (empty($_SESSION['company_id'])): ?>
                        <option value="">-- Sélectionnez une société --</option>
                    <?php endif; ?>
                    <?php foreach ($companies as $comp): ?>
                        <option value="<?php echo $comp['id']; ?>"
                                <?php echo (isset($_SESSION['company_id']) && $_SESSION['company_id'] == $comp['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($comp['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Sélecteur d'exercice comptable -->
            <?php if (!empty($fiscal_years)): ?>
            <div class="selector-group">
                <label>
                    <i class="fa-solid fa-calendar-days"></i>
                    <span>Exercice</span>
                </label>
                <select id="fiscal-year-selector" class="selector-dropdown" onchange="switchFiscalYear(this.value)">
                    <?php
                    $selected_year = $_SESSION['fiscal_year'] ?? date('Y');
                    foreach ($fiscal_years as $fy):
                    ?>
                        <option value="<?php echo $fy['year']; ?>"
                                <?php echo ($fy['year'] == $selected_year) ? 'selected' : ''; ?>>
                            <?php echo $fy['label']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <!-- Lien vers gestion des sociétés -->
            <div class="selector-actions">
                <a href="index.php?page=mes_societes" class="btn-manage-companies" title="Gérer mes sociétés">
                    <i class="fa-solid fa-gear"></i>
                </a>
            </div>
        </div>
    </div>

    <script>
    function switchCompany(companyId) {
        if (!companyId) return;

        console.log('Changement de société vers ID:', companyId);

        fetch('api/session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'change_company',
                company_id: parseInt(companyId)
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Réponse de l\'API:', data);
            if (data.success) {
                console.log('Changement réussi! Ancien:', data.old_company_id, '→ Nouveau:', data.company_id);
                // Recharger la page pour appliquer les changements
                window.location.reload();
            } else {
                alert('Erreur lors du changement de société: ' + (data.message || 'Erreur inconnue'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de communication avec le serveur');
        });
    }

    function switchFiscalYear(year) {
        // Stocker l'année fiscale sélectionnée dans la session
        fetch('api/session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'change_fiscal_year',
                fiscal_year: parseInt(year)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Recharger la page pour appliquer les changements
                window.location.reload();
            } else {
                console.error('Erreur lors du changement d\'exercice');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    }
    </script>
    <?php elseif (isset($_SESSION['user_id']) && empty($companies)): ?>
    <!-- Alerte si aucune société n'existe -->
    <div class="no-company-alert">
        <div class="alert-content">
            <i class="fa-solid fa-exclamation-triangle"></i>
            <span>Vous devez créer une société pour utiliser l'application.</span>
            <a href="index.php?page=society_setup" class="btn-create-company">
                <i class="fa-solid fa-plus"></i> Créer ma première société
            </a>
        </div>
    </div>
    <?php endif; ?>

    <div class="menus">
        <ul>
            <!-- Accueil -->
            <li style="--clr:#2483ff;" class="menu-item <?php echo ($current_page == 'home') ? 'active' : ''; ?>" data-target="home">
                <a href="index.php?page=home">
                    <i class="fa-solid fa-house"></i>
                    <span>Accueil</span>
                </a>
            </li>

            <!-- Tableau de bord analytique -->
            <li style="--clr:#9b59b6;" class="menu-item <?php echo ($current_page == 'dashboard_advanced') ? 'active' : ''; ?>" data-target="dashboard">
                <a href="index.php?page=dashboard_advanced">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Analytiques</span>
                </a>
            </li>

            <!-- Comptabilité -->
            <li style="--clr:#fff200;" class="menu-item has-submenu <?php echo (in_array($current_page, ['comptabilite', 'transaction_create', 'releve_compte', 'bilan', 'compte_resultat', 'tva_declaration'])) ? 'active' : ''; ?>" data-target="comptabilite">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class="fa-solid fa-book"></i>
                    <span>Comptabilité</span>
                    <i class="fa-solid fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'comptabilite') ? 'active' : ''; ?>">
                        <a href="index.php?page=comptabilite">
                            <i class="fa-solid fa-list"></i>
                            <span>Transactions</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'transaction_create') ? 'active' : ''; ?>">
                        <a href="index.php?page=transaction_create">
                            <i class="fa-solid fa-plus-circle"></i>
                            <span>Nouvelle Transaction</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'releve_compte') ? 'active' : ''; ?>">
                        <a href="index.php?page=releve_compte">
                            <i class="fa-solid fa-file-lines"></i>
                            <span>Grand Livre</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'bilan') ? 'active' : ''; ?>">
                        <a href="index.php?page=bilan">
                            <i class="fa-solid fa-scale-balanced"></i>
                            <span>Bilan</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'compte_resultat') ? 'active' : ''; ?>">
                        <a href="index.php?page=compte_resultat">
                            <i class="fa-solid fa-chart-bar"></i>
                            <span>Compte de Résultat</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'tva_declaration') ? 'active' : ''; ?>">
                        <a href="index.php?page=tva_declaration">
                            <i class="fa-solid fa-percent"></i>
                            <span>Déclaration TVA</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Ventes -->
            <li style="--clr:#38ef7d;" class="menu-item has-submenu <?php echo (in_array($current_page, ['devis', 'factures', 'recurring_invoices', 'factures_recurrentes', 'payment_reminders', 'rappels'])) ? 'active' : ''; ?>" data-target="ventes">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span>Ventes</span>
                    <i class="fa-solid fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'devis') ? 'active' : ''; ?>">
                        <a href="index.php?page=devis">
                            <i class="fa-solid fa-file-alt"></i>
                            <span>Devis</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'factures') ? 'active' : ''; ?>">
                        <a href="index.php?page=factures">
                            <i class="fa-solid fa-file-invoice"></i>
                            <span>Factures</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'recurring_invoices' || $current_page == 'factures_recurrentes') ? 'active' : ''; ?>">
                        <a href="index.php?page=factures_recurrentes">
                            <i class="fa-solid fa-repeat"></i>
                            <span>Factures Récurrentes</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'payment_reminders' || $current_page == 'rappels') ? 'active' : ''; ?>">
                        <a href="index.php?page=payment_reminders">
                            <i class="fa-solid fa-bell"></i>
                            <span>Rappels de Paiement</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Achats -->
            <li style="--clr:#f39c12;" class="menu-item has-submenu <?php echo (in_array($current_page, ['supplier_invoices', 'payments'])) ? 'active' : ''; ?>" data-target="achats">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class="fa-solid fa-basket-shopping"></i>
                    <span>Achats</span>
                    <i class="fa-solid fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'supplier_invoices') ? 'active' : ''; ?>">
                        <a href="index.php?page=supplier_invoices">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                            <span>Factures Fournisseurs</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'payments') ? 'active' : ''; ?>">
                        <a href="index.php?page=payments">
                            <i class="fa-solid fa-money-bill-wave"></i>
                            <span>Paiements</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Banque -->
            <li style="--clr:#06b6d4;" class="menu-item has-submenu <?php echo (in_array($current_page, ['bank_reconciliation', 'rapprochement', 'tresorerie'])) ? 'active' : ''; ?>" data-target="banque">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class="fa-solid fa-landmark"></i>
                    <span>Banque</span>
                    <i class="fa-solid fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'bank_reconciliation' || $current_page == 'rapprochement') ? 'active' : ''; ?>">
                        <a href="index.php?page=bank_reconciliation">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            <span>Rapprochement Bancaire</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'tresorerie') ? 'active' : ''; ?>">
                        <a href="index.php?page=tresorerie">
                            <i class="fa-solid fa-chart-line"></i>
                            <span>Trésorerie</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Stock -->
            <li style="--clr:#e67e22;" class="menu-item <?php echo ($current_page == 'products') ? 'active' : ''; ?>" data-target="stock">
                <a href="index.php?page=products">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Stock</span>
                </a>
            </li>

            <!-- RH / Salaires -->
            <li style="--clr:#10b981;" class="menu-item has-submenu <?php echo (in_array($current_page, ['employees', 'employes', 'payroll', 'salaires', 'fiches_paie'])) ? 'active' : ''; ?>" data-target="rh">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class="fa-solid fa-user-tie"></i>
                    <span>RH</span>
                    <i class="fa-solid fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'employees' || $current_page == 'employes') ? 'active' : ''; ?>">
                        <a href="index.php?page=employees">
                            <i class="fa-solid fa-users"></i>
                            <span>Employés</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo (in_array($current_page, ['payroll', 'salaires', 'fiches_paie'])) ? 'active' : ''; ?>">
                        <a href="index.php?page=payroll">
                            <i class="fa-solid fa-file-invoice-dollar"></i>
                            <span>Salaires / Paie</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Contacts -->
            <li style="--clr:#e7f920bb;" class="menu-item <?php echo ($current_page == 'adresses') ? 'active' : ''; ?>" data-target="contacts">
                <a href="index.php?page=adresses">
                    <i class="fa-solid fa-address-book"></i>
                    <span>Contacts</span>
                </a>
            </li>

            <!-- Administration -->
            <li style="--clr:#8b5cf6;" class="menu-item has-submenu <?php echo (in_array($current_page, ['parametres', 'mon_compte', 'mes_societes', 'my_companies', 'users_management', 'gestion_utilisateurs', 'recherche'])) ? 'active' : ''; ?>" data-target="administration">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class="fa-solid fa-sliders"></i>
                    <span>Administration</span>
                    <i class="fa-solid fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'parametres') ? 'active' : ''; ?>">
                        <a href="index.php?page=parametres">
                            <i class="fa-solid fa-gear"></i>
                            <span>Paramètres</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'mon_compte') ? 'active' : ''; ?>">
                        <a href="index.php?page=mon_compte">
                            <i class="fa-solid fa-circle-user"></i>
                            <span>Mon Compte</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'mes_societes' || $current_page == 'my_companies') ? 'active' : ''; ?>">
                        <a href="index.php?page=mes_societes">
                            <i class="fa-solid fa-building"></i>
                            <span>Mes Sociétés</span>
                        </a>
                    </li>
                    <?php
                    // Gestion des utilisateurs : visible si permission users.view disponible
                    $show_users_menu = false;
                    if (isset($_SESSION['tenant_database'])) {
                        if (!isset($db)) {
                            require_once 'config/database.php';
                            $database = new Database();
                            $db = $database->getConnection();
                        }
                        if (file_exists('utils/PermissionHelper.php')) {
                            require_once 'utils/PermissionHelper.php';
                            $show_users_menu = PermissionHelper::hasPermission($db, 'users.view');
                        }
                    } else {
                        // En mode non-tenant, afficher quand même si l'utilisateur est connecté
                        $show_users_menu = isset($_SESSION['user_id']);
                    }
                    if ($show_users_menu):
                    ?>
                    <li class="submenu-item <?php echo ($current_page == 'users_management' || $current_page == 'gestion_utilisateurs') ? 'active' : ''; ?>">
                        <a href="index.php?page=users_management">
                            <i class="fa-solid fa-users-gear"></i>
                            <span>Gestion Utilisateurs</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="submenu-item <?php echo ($current_page == 'recherche') ? 'active' : ''; ?>">
                        <a href="index.php?page=recherche">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <span>Recherche</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Déconnexion -->
            <li style="--clr:#ef4444;" class="menu-item menu-item-logout">
                <a href="index.php?page=logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>
    <?php endif; ?>

    <div class="content">