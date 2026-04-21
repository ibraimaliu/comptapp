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
            <li style="--clr:#2483ff;" class="menu-item <?php echo ($current_page == 'home') ? 'active' : ''; ?>" data-target="home">
                <a href="index.php?page=home">
                    <i class="fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
            </li>

            <li style="--clr:#9b59b6;" class="menu-item <?php echo ($current_page == 'dashboard_advanced') ? 'active' : ''; ?>" data-target="dashboard">
                <a href="index.php?page=dashboard_advanced">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Analytiques</span>
                </a>
            </li>

            <!-- Comptabilité avec sous-menu -->
            <li style="--clr:#fff200;" class="menu-item has-submenu <?php echo (in_array($current_page, ['comptabilite', 'transaction_create', 'releve_compte', 'bilan', 'compte_resultat'])) ? 'active' : ''; ?>" data-target="comptabilite">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class="fa-solid fa-book"></i>
                    <span>Comptabilité</span>
                    <i class='fa-solid fa-chevron-down submenu-arrow'></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'comptabilite') ? 'active' : ''; ?>">
                        <a href="index.php?page=comptabilite">
                            <i class='fa-solid fa-list'></i>
                            <span>Toutes les Transactions</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'transaction_create') ? 'active' : ''; ?>">
                        <a href="index.php?page=transaction_create">
                            <i class='fa-solid fa-plus-circle'></i>
                            <span>Nouvelle Transaction</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'releve_compte') ? 'active' : ''; ?>">
                        <a href="index.php?page=releve_compte">
                            <i class='fa-solid fa-file-lines'></i>
                            <span>Relevé de Compte</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'bilan') ? 'active' : ''; ?>">
                        <a href="index.php?page=bilan">
                            <i class='fa-solid fa-balance-scale'></i>
                            <span>Bilan</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'compte_resultat') ? 'active' : ''; ?>">
                        <a href="index.php?page=compte_resultat">
                            <i class='fa-solid fa-chart-bar'></i>
                            <span>Compte de Résultat (PP)</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li style="--clr:#e7f920bb;" class="menu-item <?php echo ($current_page == 'adresses') ? 'active' : ''; ?>" data-target="adresses">
                <a href="index.php?page=adresses">
                    <i class='fa-solid fa-user'></i>
                    <span>Adresses - Contacts</span>
                </a>
            </li>

            <li style="--clr:#f39c12;" class="menu-item <?php echo ($current_page == 'products') ? 'active' : ''; ?>" data-target="products">
                <a href="index.php?page=products">
                    <i class="fa-solid fa-box"></i>
                    <span>Produits & Stock</span>
                </a>
            </li>

            <li style="--clr:#e74c3c;" class="menu-item <?php echo ($current_page == 'tva_declaration') ? 'active' : ''; ?>" data-target="tva">
                <a href="index.php?page=tva_declaration">
                    <i class="fa-solid fa-percent"></i>
                    <span>Déclaration TVA</span>
                </a>
            </li>

            <li style="--clr:#06b6d4;" class="menu-item <?php echo ($current_page == 'tresorerie') ? 'active' : ''; ?>" data-target="tresorerie">
                <a href="index.php?page=tresorerie">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Trésorerie</span>
                </a>
            </li>

            <!-- Facturation avec sous-menu -->
            <li style="--clr:#38ef7d;" class="menu-item has-submenu <?php echo (in_array($current_page, ['devis', 'factures', 'recurring_invoices', 'factures_recurrentes', 'bank_reconciliation', 'rapprochement', 'payment_reminders', 'rappels', 'supplier_invoices', 'payments'])) ? 'active' : ''; ?>" data-target="facturation">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class='fa-solid fa-file-invoice'></i>
                    <span>Facturation</span>
                    <i class='fa-solid fa-chevron-down submenu-arrow'></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'devis') ? 'active' : ''; ?>">
                        <a href="index.php?page=devis">
                            <i class='fa-solid fa-file-alt'></i>
                            <span>Devis</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'factures') ? 'active' : ''; ?>">
                        <a href="index.php?page=factures">
                            <i class='fa-solid fa-file-invoice'></i>
                            <span>Factures</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'recurring_invoices' || $current_page == 'factures_recurrentes') ? 'active' : ''; ?>">
                        <a href="index.php?page=recurring_invoices">
                            <i class='fa-solid fa-repeat'></i>
                            <span>Factures Récurrentes</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'bank_reconciliation' || $current_page == 'rapprochement') ? 'active' : ''; ?>">
                        <a href="index.php?page=bank_reconciliation">
                            <i class='fa-solid fa-sync-alt'></i>
                            <span>Rapprochement</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'payment_reminders' || $current_page == 'rappels') ? 'active' : ''; ?>">
                        <a href="index.php?page=payment_reminders">
                            <i class='fa-solid fa-bell'></i>
                            <span>Rappels</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'supplier_invoices') ? 'active' : ''; ?>">
                        <a href="index.php?page=supplier_invoices">
                            <i class='fa-solid fa-file-invoice-dollar'></i>
                            <span>Factures Fournisseurs</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo ($current_page == 'payments') ? 'active' : ''; ?>">
                        <a href="index.php?page=payments">
                            <i class='fa-solid fa-money-bill-wave'></i>
                            <span>Historique Paiements</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- Salaires (module optionnel selon plan) -->
            <li style="--clr:#10b981;" class="menu-item has-submenu <?php echo (in_array($current_page, ['employees', 'employes', 'payroll', 'salaires', 'fiches_paie'])) ? 'active' : ''; ?>" data-target="salaires">
                <a href="#" onclick="toggleSubmenu(event, this)">
                    <i class="fa-solid fa-user-tie"></i>
                    <span>Salaires</span>
                    <i class='fa-solid fa-chevron-down submenu-arrow'></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item <?php echo ($current_page == 'employees' || $current_page == 'employes') ? 'active' : ''; ?>">
                        <a href="index.php?page=employees">
                            <i class='fa-solid fa-users'></i>
                            <span>Employés</span>
                        </a>
                    </li>
                    <li class="submenu-item <?php echo (in_array($current_page, ['payroll', 'salaires', 'fiches_paie'])) ? 'active' : ''; ?>">
                        <a href="index.php?page=payroll">
                            <i class='fa-solid fa-file-invoice-dollar'></i>
                            <span>Fiches de Paie</span>
                        </a>
                    </li>
                </ul>
            </li>

            <?php if (isset($_SESSION['tenant_database'])): ?>
            <li style="--clr:#3498db;" class="menu-item <?php echo ($current_page == 'mon_compte') ? 'active' : ''; ?>" data-target="mon_compte">
                <a href="index.php?page=mon_compte">
                    <i class="fa-solid fa-user-circle"></i>
                    <span>Mon Compte</span>
                </a>
            </li>

            <li style="--clr:#8b5cf6;" class="menu-item <?php echo ($current_page == 'mes_societes' || $current_page == 'my_companies') ? 'active' : ''; ?>" data-target="mes_societes">
                <a href="index.php?page=mes_societes">
                    <i class="fa-solid fa-building"></i>
                    <span>Mes Sociétés</span>
                </a>
            </li>
            <?php endif; ?>

            <?php
            // Menu Gestion des utilisateurs (multi-tenant uniquement, permission users.view requise)
            if (isset($_SESSION['tenant_database'])) {
                require_once 'config/database.php';
                require_once 'utils/PermissionHelper.php';
                $database = new Database();
                $db = $database->getConnection();
                if (PermissionHelper::hasPermission($db, 'users.view')) {
            ?>
            <li style="--clr:#9b59b6;" class="menu-item <?php echo ($current_page == 'users_management' || $current_page == 'gestion_utilisateurs') ? 'active' : ''; ?>" data-target="users">
                <a href="index.php?page=users_management">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>Gestion des Utilisateurs</span>
                </a>
            </li>
            <?php
                }
            }
            ?>

            <li style="--clr:#25d366;" class="menu-item <?php echo ($current_page == 'parametres') ? 'active' : ''; ?>" data-target="parametres">
                <a href="index.php?page=parametres">
                    <i class="fa-solid fa-gear"></i>
                    <span>Paramètres</span>
                </a>
            </li>

            <li style="--clr:#e70909;" class="menu-item <?php echo ($current_page == 'recherche') ? 'active' : ''; ?>" data-target="recherche">
                <a href="index.php?page=recherche">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <span>Recherche</span>
                </a>
            </li>
            
            <li style="--clr:#555555;">
                <a href="index.php?page=logout">
                    <i class="fa-solid fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </li>
        </ul>
    </div>
    <?php endif; ?>

    <div class="content">