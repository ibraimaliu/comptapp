<?php
/**
 * Page: Gestion des Contacts/Adresses
 * Version: 3.0 - Refonte complète
 * Description: Interface moderne pour gérer clients, fournisseurs et autres contacts
 */

// Inclure les modèles nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/Contact.php';

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Vérifier la connexion à la base de données
if (!$db) {
    die('<div style="padding: 20px; background: #f8d7da; color: #721c24; border-radius: 8px; margin: 20px;">
        <h3>❌ Erreur de connexion à la base de données</h3>
        <p>Impossible de se connecter à la base de données. Veuillez vérifier:</p>
        <ul>
            <li>Que XAMPP MySQL est démarré</li>
            <li>Que la base de données "gestion_comptable" existe</li>
            <li>Que les identifiants dans config/database.php sont corrects</li>
        </ul>
        <p><a href="index.php" style="color: #721c24; font-weight: bold;">← Retour à l\'accueil</a></p>
    </div>');
}

// Vérifier si une société est sélectionnée
$company_id = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;

if(!$company_id) {
    echo '<div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 8px; margin: 20px;">
        <h3>⚠️ Aucune société sélectionnée</h3>
        <p>Veuillez sélectionner une société dans le menu ci-dessus pour gérer vos contacts.</p>
        <p><a href="index.php?page=home" style="color: #856404; font-weight: bold;">← Retour à l\'accueil</a></p>
    </div>';
    exit;
}

// Récupérer les statistiques
$contact = new Contact($db);
$stats_query = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN type = 'client' THEN 1 ELSE 0 END) as clients,
    SUM(CASE WHEN type = 'fournisseur' THEN 1 ELSE 0 END) as fournisseurs,
    SUM(CASE WHEN type = 'autre' OR type IS NULL THEN 1 ELSE 0 END) as autres
    FROM contacts
    WHERE company_id = :company_id";

$stmt = $db->prepare($stats_query);
$stmt->bindParam(':company_id', $company_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Titre de la page
$page_title = "Contacts & Adresses";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Gestion Comptable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/adresses.css">
    <style>
        /* Styles intégrés pour la nouvelle interface */
        .contacts-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 32px;
            font-weight: 700;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 16px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-card.total { border-left-color: #667eea; }
        .stat-card.clients { border-left-color: #28a745; }
        .stat-card.fournisseurs { border-left-color: #007bff; }
        .stat-card.autres { border-left-color: #ffc107; }

        .stat-card .icon {
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.8;
        }

        .stat-card.total .icon { color: #667eea; }
        .stat-card.clients .icon { color: #28a745; }
        .stat-card.fournisseurs .icon { color: #007bff; }
        .stat-card.autres .icon { color: #ffc107; }

        .stat-card .value {
            font-size: 36px;
            font-weight: 700;
            margin: 10px 0 5px 0;
        }

        .stat-card .label {
            color: #6c757d;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .toolbar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 300px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 45px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 25px;
            font-size: 15px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 18px;
        }

        .search-box .clear-search {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6c757d;
            cursor: pointer;
            font-size: 18px;
            display: none;
        }

        .search-box .clear-search.active {
            display: block;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 25px;
        }

        .filter-tab {
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: #6c757d;
            cursor: pointer;
            border-radius: 20px;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 14px;
        }

        .filter-tab:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .filter-tab.active {
            background: #667eea;
            color: white;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .contacts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .contact-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .contact-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }

        .contact-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .contact-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
        }

        .contact-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .contact-info {
            flex: 1;
        }

        .contact-name {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 5px 0;
            color: #2c3e50;
        }

        .contact-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .contact-type.client {
            background: #d4edda;
            color: #155724;
        }

        .contact-type.fournisseur {
            background: #cce5ff;
            color: #004085;
        }

        .contact-type.autre {
            background: #fff3cd;
            color: #856404;
        }

        .contact-details {
            margin: 15px 0;
        }

        .contact-detail {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 8px 0;
            color: #6c757d;
            font-size: 14px;
        }

        .contact-detail i {
            width: 20px;
            color: #667eea;
        }

        .contact-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .btn-action {
            flex: 1;
            padding: 8px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-action:hover {
            background: #f8f9fa;
            border-color: #667eea;
            color: #667eea;
        }

        .btn-action.danger:hover {
            background: #fff5f5;
            border-color: #dc3545;
            color: #dc3545;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 64px;
            color: #e0e0e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            color: #6c757d;
            margin: 0 0 10px 0;
        }

        .empty-state p {
            color: #adb5bd;
            margin: 0 0 20px 0;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease-out;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            color: #2c3e50;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #6c757d;
            cursor: pointer;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: #f8f9fa;
            color: #dc3545;
        }

        .modal-body {
            padding: 30px;
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

        .form-group label .required {
            color: #dc3545;
            margin-left: 3px;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s;
            font-family: inherit;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-secondary {
            padding: 12px 25px;
            border: 2px solid #e0e0e0;
            background: white;
            color: #6c757d;
            border-radius: 25px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            transition: all 0.3s;
        }

        .btn-secondary:hover {
            background: #f8f9fa;
            border-color: #6c757d;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .contacts-grid {
                grid-template-columns: 1fr;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                min-width: 100%;
            }

            .filter-tabs {
                flex-wrap: wrap;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 40px;
        }

        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="contacts-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-address-book"></i> Contacts & Adresses</h1>
            <p>Gérez vos clients, fournisseurs et autres contacts en un seul endroit</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-cards">
            <div class="stat-card total">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="value" id="stat-total"><?php echo $stats['total']; ?></div>
                <div class="label">Total Contacts</div>
            </div>

            <div class="stat-card clients">
                <div class="icon"><i class="fas fa-user-tie"></i></div>
                <div class="value" id="stat-clients"><?php echo $stats['clients']; ?></div>
                <div class="label">Clients</div>
            </div>

            <div class="stat-card fournisseurs">
                <div class="icon"><i class="fas fa-truck"></i></div>
                <div class="value" id="stat-fournisseurs"><?php echo $stats['fournisseurs']; ?></div>
                <div class="label">Fournisseurs</div>
            </div>

            <div class="stat-card autres">
                <div class="icon"><i class="fas fa-user-friends"></i></div>
                <div class="value" id="stat-autres"><?php echo $stats['autres']; ?></div>
                <div class="label">Autres</div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text"
                       id="searchInput"
                       placeholder="Rechercher par nom, email, téléphone, adresse..."
                       autocomplete="off">
                <button class="clear-search" id="clearSearch">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <button class="filter-tab active" data-filter="all">
                    <i class="fas fa-th"></i> Tous
                </button>
                <button class="filter-tab" data-filter="client">
                    <i class="fas fa-user-tie"></i> Clients
                </button>
                <button class="filter-tab" data-filter="fournisseur">
                    <i class="fas fa-truck"></i> Fournisseurs
                </button>
                <button class="filter-tab" data-filter="autre">
                    <i class="fas fa-user-friends"></i> Autres
                </button>
            </div>

            <!-- Add Contact Button -->
            <button class="btn-primary" id="addContactBtn">
                <i class="fas fa-plus"></i> Nouveau Contact
            </button>
        </div>

        <!-- Contacts Grid -->
        <div class="contacts-grid" id="contactsGrid">
            <div class="loading">
                <div class="spinner"></div>
                <p>Chargement des contacts...</p>
            </div>
        </div>

        <!-- Empty State (template) -->
        <div class="empty-state" id="emptyState" style="display: none;">
            <i class="fas fa-address-book"></i>
            <h3>Aucun contact trouvé</h3>
            <p>Commencez par ajouter votre premier contact</p>
            <button class="btn-primary" onclick="document.getElementById('addContactBtn').click()">
                <i class="fas fa-plus"></i> Ajouter un Contact
            </button>
        </div>
    </div>

    <!-- Modal Add/Edit Contact -->
    <div class="modal" id="contactModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Nouveau Contact</h2>
                <button class="modal-close" onclick="closeContactModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="contactForm">
                    <input type="hidden" id="contactId" name="id">
                    <input type="hidden" id="companyId" name="company_id" value="<?php echo $company_id; ?>">

                    <div class="form-group">
                        <label for="type">Type de Contact <span class="required">*</span></label>
                        <select id="type" name="type" class="form-control" required>
                            <option value="">-- Sélectionner --</option>
                            <option value="client">Client</option>
                            <option value="fournisseur">Fournisseur</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="name">Nom/Raison Sociale <span class="required">*</span></label>
                        <input type="text" id="name" name="name" class="form-control"
                               placeholder="Nom complet ou raison sociale" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control"
                                   placeholder="contact@exemple.ch">
                        </div>

                        <div class="form-group">
                            <label for="phone">Téléphone</label>
                            <input type="tel" id="phone" name="phone" class="form-control"
                                   placeholder="+41 XX XXX XX XX">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Adresse</label>
                        <input type="text" id="address" name="address" class="form-control"
                               placeholder="Rue et numéro">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="postal_code">Code Postal</label>
                            <input type="text" id="postal_code" name="postal_code" class="form-control"
                                   placeholder="1950">
                        </div>

                        <div class="form-group">
                            <label for="city">Ville</label>
                            <input type="text" id="city" name="city" class="form-control"
                                   placeholder="Sion">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="country">Pays</label>
                        <input type="text" id="country" name="country" class="form-control"
                               placeholder="Suisse" value="Suisse">
                    </div>

                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"
                                  placeholder="Notes internes..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeContactModal()">
                    Annuler
                </button>
                <button type="button" class="btn-primary" onclick="saveContact()">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>

    <!-- Script principal -->
    <script>
        // Configuration globale
        const API_URL = 'assets/ajax/contacts.php';
        const SAVE_URL = 'assets/ajax/save_contact.php';
        const DELETE_URL = 'assets/ajax/delete_contact.php';

        // État de l'application
        let contacts = [];
        let filteredContacts = [];
        let currentFilter = 'all';
        let searchQuery = '';

        // Charger les contacts au démarrage
        document.addEventListener('DOMContentLoaded', function() {
            loadContacts();
            initEventListeners();
        });

        // Initialiser les event listeners
        function initEventListeners() {
            // Recherche
            document.getElementById('searchInput').addEventListener('input', function(e) {
                searchQuery = e.target.value.toLowerCase();
                document.getElementById('clearSearch').classList.toggle('active', searchQuery.length > 0);
                filterContacts();
            });

            document.getElementById('clearSearch').addEventListener('click', function() {
                document.getElementById('searchInput').value = '';
                searchQuery = '';
                this.classList.remove('active');
                filterContacts();
            });

            // Filtres
            document.querySelectorAll('.filter-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    currentFilter = this.dataset.filter;
                    filterContacts();
                });
            });

            // Bouton ajouter contact
            document.getElementById('addContactBtn').addEventListener('click', openAddContactModal);

            // Fermer modal si clic en dehors
            document.getElementById('contactModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeContactModal();
                }
            });
        }

        // Charger les contacts depuis l'API
        async function loadContacts() {
            try {
                const response = await fetch(API_URL);
                const data = await response.json();

                if (data.success) {
                    contacts = data.contacts || [];
                    filterContacts();
                    updateStats();
                } else {
                    console.error('Erreur:', data.message);
                    showError('Erreur lors du chargement des contacts');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showError('Erreur de connexion au serveur');
            }
        }

        // Filtrer les contacts
        function filterContacts() {
            filteredContacts = contacts.filter(contact => {
                // Filtre par type
                const typeMatch = currentFilter === 'all' || contact.type === currentFilter;

                // Filtre par recherche
                const searchMatch = !searchQuery ||
                    contact.name?.toLowerCase().includes(searchQuery) ||
                    contact.email?.toLowerCase().includes(searchQuery) ||
                    contact.phone?.toLowerCase().includes(searchQuery) ||
                    contact.address?.toLowerCase().includes(searchQuery) ||
                    contact.city?.toLowerCase().includes(searchQuery);

                return typeMatch && searchMatch;
            });

            renderContacts();
        }

        // Afficher les contacts
        function renderContacts() {
            const grid = document.getElementById('contactsGrid');
            const emptyState = document.getElementById('emptyState');

            if (filteredContacts.length === 0) {
                grid.style.display = 'none';
                emptyState.style.display = 'block';
                return;
            }

            grid.style.display = 'grid';
            emptyState.style.display = 'none';

            grid.innerHTML = filteredContacts.map(contact => `
                <div class="contact-card" data-id="${contact.id}">
                    <div class="contact-header">
                        <div class="contact-avatar">
                            ${getInitials(contact.name)}
                        </div>
                        <div class="contact-info">
                            <h3 class="contact-name">${escapeHtml(contact.name)}</h3>
                            <span class="contact-type ${contact.type || 'autre'}">
                                ${formatType(contact.type)}
                            </span>
                        </div>
                    </div>

                    <div class="contact-details">
                        ${contact.email ? `
                            <div class="contact-detail">
                                <i class="fas fa-envelope"></i>
                                <a href="mailto:${contact.email}">${escapeHtml(contact.email)}</a>
                            </div>
                        ` : ''}

                        ${contact.phone ? `
                            <div class="contact-detail">
                                <i class="fas fa-phone"></i>
                                <a href="tel:${contact.phone}">${escapeHtml(contact.phone)}</a>
                            </div>
                        ` : ''}

                        ${contact.address ? `
                            <div class="contact-detail">
                                <i class="fas fa-map-marker-alt"></i>
                                <span>${escapeHtml(contact.address)}${contact.city ? ', ' + escapeHtml(contact.city) : ''}</span>
                            </div>
                        ` : ''}
                    </div>

                    <div class="contact-actions">
                        <button class="btn-action" onclick="viewContact(${contact.id})">
                            <i class="fas fa-eye"></i> Voir
                        </button>
                        <button class="btn-action" onclick="editContact(${contact.id})">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                        <button class="btn-action danger" onclick="deleteContact(${contact.id}, '${escapeHtml(contact.name)}')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Mettre à jour les statistiques
        function updateStats() {
            const total = contacts.length;
            const clients = contacts.filter(c => c.type === 'client').length;
            const fournisseurs = contacts.filter(c => c.type === 'fournisseur').length;
            const autres = contacts.filter(c => !c.type || c.type === 'autre').length;

            document.getElementById('stat-total').textContent = total;
            document.getElementById('stat-clients').textContent = clients;
            document.getElementById('stat-fournisseurs').textContent = fournisseurs;
            document.getElementById('stat-autres').textContent = autres;
        }

        // Ouvrir modal ajout contact
        function openAddContactModal() {
            document.getElementById('modalTitle').textContent = 'Nouveau Contact';
            document.getElementById('contactForm').reset();
            document.getElementById('contactId').value = '';
            document.getElementById('contactModal').classList.add('show');
        }

        // Modifier un contact
        async function editContact(id) {
            const contact = contacts.find(c => c.id == id);
            if (!contact) return;

            document.getElementById('modalTitle').textContent = 'Modifier le Contact';
            document.getElementById('contactId').value = contact.id;
            document.getElementById('type').value = contact.type || '';
            document.getElementById('name').value = contact.name || '';
            document.getElementById('email').value = contact.email || '';
            document.getElementById('phone').value = contact.phone || '';
            document.getElementById('address').value = contact.address || '';
            document.getElementById('postal_code').value = contact.postal_code || '';
            document.getElementById('city').value = contact.city || '';
            document.getElementById('country').value = contact.country || 'Suisse';
            document.getElementById('notes').value = contact.notes || '';

            document.getElementById('contactModal').classList.add('show');
        }

        // Voir un contact (ouvre en mode lecture seule)
        function viewContact(id) {
            editContact(id);
            // Désactiver tous les champs
            document.querySelectorAll('#contactForm input, #contactForm select, #contactForm textarea').forEach(field => {
                field.setAttribute('readonly', true);
                field.setAttribute('disabled', true);
            });
            document.getElementById('modalTitle').textContent = 'Détails du Contact';
        }

        // Sauvegarder un contact
        async function saveContact() {
            const form = document.getElementById('contactForm');

            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);

            try {
                const response = await fetch(SAVE_URL, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    closeContactModal();
                    loadContacts();
                    const contactId = formData.get('id');
                    showSuccess(contactId ? 'Contact modifié avec succès' : 'Contact ajouté avec succès');
                } else {
                    showError(result.message || result.error || 'Erreur lors de l\'enregistrement');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showError('Erreur de connexion au serveur');
            }
        }

        // Supprimer un contact
        async function deleteContact(id, name) {
            if (!confirm(`Êtes-vous sûr de vouloir supprimer le contact "${name}" ?`)) {
                return;
            }

            try {
                const response = await fetch(DELETE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ id })
                });

                const result = await response.json();

                if (result.success) {
                    loadContacts();
                    showSuccess('Contact supprimé avec succès');
                } else {
                    showError(result.error || 'Erreur lors de la suppression');
                }
            } catch (error) {
                console.error('Erreur:', error);
                showError('Erreur de connexion au serveur');
            }
        }

        // Fermer le modal
        function closeContactModal() {
            document.getElementById('contactModal').classList.remove('show');
            document.getElementById('contactForm').reset();
            // Réactiver tous les champs
            document.querySelectorAll('#contactForm input, #contactForm select, #contactForm textarea').forEach(field => {
                field.removeAttribute('readonly');
                field.removeAttribute('disabled');
            });
        }

        // Utilitaires
        function getInitials(name) {
            if (!name) return '?';
            const parts = name.trim().split(' ');
            if (parts.length >= 2) {
                return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
            }
            return name.substring(0, 2).toUpperCase();
        }

        function formatType(type) {
            const types = {
                'client': 'Client',
                'fournisseur': 'Fournisseur',
                'autre': 'Autre'
            };
            return types[type] || 'Autre';
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function showSuccess(message) {
            alert('✅ ' + message);
        }

        function showError(message) {
            alert('❌ ' + message);
        }
    </script>
</body>
</html>
