<?php
/**
 * Page: Gestion des Factures Récurrentes et Abonnements
 */
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

if(!isset($_SESSION['company_id'])) {
    header("Location: index.php?page=society_setup");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factures Récurrentes - Gestion Comptable</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .recurring-container {
            padding: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0;
            font-size: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab {
            padding: 1rem 2rem;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab:hover {
            color: #667eea;
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #667eea;
        }

        .stat-card h3 {
            margin: 0 0 0.5rem 0;
            font-size: 0.875rem;
            color: #6b7280;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: bold;
            color: #1f2937;
        }

        .actions-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            gap: 1rem;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: white;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn-success {
            background: #10b981;
            color: white;
        }

        .btn-danger {
            background: #ef4444;
            color: white;
        }

        .btn-warning {
            background: #f59e0b;
            color: white;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: #f9fafb;
        }

        .data-table th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 2px solid #e5e7eb;
        }

        .data-table td {
            padding: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .data-table tbody tr:hover {
            background: #f9fafb;
        }

        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-paused {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-completed {
            background: #e0e7ff;
            color: #3730a3;
        }

        .badge-trial {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-expired {
            background: #fecaca;
            color: #7f1d1d;
        }

        .frequency-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            background: #f3f4f6;
            color: #374151;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            max-width: 800px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .btn-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .empty-state h3 {
            margin: 0 0 0.5rem 0;
            color: #374151;
        }

        .items-table {
            width: 100%;
            margin-top: 1rem;
        }

        .items-table th,
        .items-table td {
            padding: 0.5rem;
            text-align: left;
        }

        .items-table input {
            width: 100%;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: #6b7280;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .search-box {
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="recurring-container">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-repeat"></i>
                Factures Récurrentes & Abonnements
            </h1>
            <p>Automatisez vos facturations répétitives et gérez vos abonnements clients</p>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" data-tab="recurring">
                <i class="fas fa-file-invoice"></i> Factures Récurrentes
            </button>
            <button class="tab" data-tab="subscriptions">
                <i class="fas fa-calendar-check"></i> Abonnements
            </button>
        </div>

        <!-- Tab: Factures Récurrentes -->
        <div id="tab-recurring" class="tab-content active">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total</h3>
                    <div class="value" id="recurring-total">-</div>
                </div>
                <div class="stat-card">
                    <h3>Actifs</h3>
                    <div class="value" id="recurring-active">-</div>
                </div>
                <div class="stat-card">
                    <h3>En Pause</h3>
                    <div class="value" id="recurring-paused">-</div>
                </div>
                <div class="stat-card">
                    <h3>Factures Générées</h3>
                    <div class="value" id="recurring-generated">-</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions-bar">
                <div class="search-box">
                    <input type="text" id="search-recurring" placeholder="Rechercher...">
                </div>
                <button class="btn btn-primary" onclick="openRecurringModal()">
                    <i class="fas fa-plus"></i> Nouvelle Facture Récurrente
                </button>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="data-table" id="recurring-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Client</th>
                            <th>Fréquence</th>
                            <th>Prochaine Génération</th>
                            <th>Total Estimé</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="recurring-tbody">
                        <tr>
                            <td colspan="7" class="loading">
                                <i class="fas fa-spinner fa-spin"></i> Chargement...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Abonnements -->
        <div id="tab-subscriptions" class="tab-content">
            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Total</h3>
                    <div class="value" id="sub-total">-</div>
                </div>
                <div class="stat-card">
                    <h3>Actifs</h3>
                    <div class="value" id="sub-active">-</div>
                </div>
                <div class="stat-card">
                    <h3>En Période d'Essai</h3>
                    <div class="value" id="sub-trial">-</div>
                </div>
                <div class="stat-card">
                    <h3>MRR</h3>
                    <div class="value" id="sub-mrr">-</div>
                </div>
            </div>

            <!-- Actions -->
            <div class="actions-bar">
                <div class="search-box">
                    <input type="text" id="search-subscriptions" placeholder="Rechercher...">
                </div>
                <button class="btn btn-primary" onclick="openSubscriptionModal()">
                    <i class="fas fa-plus"></i> Nouvel Abonnement
                </button>
            </div>

            <!-- Table -->
            <div class="table-container">
                <table class="data-table" id="subscriptions-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Cycle</th>
                            <th>Fin de Période</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="subscriptions-tbody">
                        <tr>
                            <td colspan="7" class="loading">
                                <i class="fas fa-spinner fa-spin"></i> Chargement...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal: Créer/Éditer Facture Récurrente -->
    <div id="recurring-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="recurring-modal-title">Nouvelle Facture Récurrente</h2>
                <button class="btn-close" onclick="closeRecurringModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="recurring-form">
                    <input type="hidden" id="recurring-id">

                    <div class="form-group">
                        <label>Nom du Template *</label>
                        <input type="text" id="template-name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Client *</label>
                            <select id="contact-id" required>
                                <option value="">Sélectionner...</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Fréquence *</label>
                            <select id="frequency" required>
                                <option value="monthly">Mensuel</option>
                                <option value="quarterly">Trimestriel</option>
                                <option value="semiannual">Semestriel</option>
                                <option value="annual">Annuel</option>
                                <option value="weekly">Hebdomadaire</option>
                                <option value="biweekly">Bihebdomadaire</option>
                                <option value="daily">Quotidien</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de Début *</label>
                            <input type="date" id="start-date" required>
                        </div>

                        <div class="form-group">
                            <label>Date de Fin</label>
                            <input type="date" id="end-date">
                            <small>Laisser vide pour sans fin</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Prochaine Génération *</label>
                            <input type="date" id="next-generation-date" required>
                        </div>

                        <div class="form-group">
                            <label>Max Occurrences</label>
                            <input type="number" id="max-occurrences" min="1">
                            <small>Laisser vide pour illimité</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Délai de Paiement (jours)</label>
                            <input type="number" id="payment-terms" value="30" min="0">
                        </div>

                        <div class="form-group">
                            <label>Préfixe Facture</label>
                            <input type="text" id="invoice-prefix" value="FACT" maxlength="20">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea id="notes"></textarea>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="auto-mark-sent" checked>
                            Marquer automatiquement comme "Envoyée"
                        </label>
                    </div>

                    <h3>Lignes de la Facture</h3>
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th style="width: 100px;">Quantité</th>
                                <th style="width: 120px;">Prix Unit.</th>
                                <th style="width: 100px;">TVA %</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="items-tbody">
                            <tr>
                                <td><input type="text" class="item-description" placeholder="Description"></td>
                                <td><input type="number" class="item-quantity" value="1" step="0.01"></td>
                                <td><input type="number" class="item-price" step="0.01" placeholder="0.00"></td>
                                <td><input type="number" class="item-tva" value="7.70" step="0.01"></td>
                                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(this)"><i class="fas fa-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-secondary" onclick="addItemRow()" style="margin-top: 0.5rem;">
                        <i class="fas fa-plus"></i> Ajouter une ligne
                    </button>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeRecurringModal()">Annuler</button>
                <button class="btn btn-primary" onclick="saveRecurring()">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>

    <!-- Modal: Créer/Éditer Abonnement -->
    <div id="subscription-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="subscription-modal-title">Nouvel Abonnement</h2>
                <button class="btn-close" onclick="closeSubscriptionModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="subscription-form">
                    <input type="hidden" id="subscription-id">

                    <div class="form-group">
                        <label>Nom de l'Abonnement *</label>
                        <input type="text" id="sub-name" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Client *</label>
                            <select id="sub-contact-id" required>
                                <option value="">Sélectionner...</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Montant (CHF) *</label>
                            <input type="number" id="sub-amount" step="0.01" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Cycle de Facturation *</label>
                            <select id="sub-billing-cycle" required>
                                <option value="monthly">Mensuel</option>
                                <option value="quarterly">Trimestriel</option>
                                <option value="semiannual">Semestriel</option>
                                <option value="annual">Annuel</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Type</label>
                            <select id="sub-type">
                                <option value="service">Service</option>
                                <option value="product">Produit</option>
                                <option value="bundle">Bundle</option>
                                <option value="other">Autre</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Date de Début *</label>
                            <input type="date" id="sub-start-date" required>
                        </div>

                        <div class="form-group">
                            <label>Fin de la Période Actuelle *</label>
                            <input type="date" id="sub-period-end" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="sub-auto-renew" checked>
                            Renouvellement automatique
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeSubscriptionModal()">Annuler</button>
                <button class="btn btn-primary" onclick="saveSubscription()">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/factures_recurrentes.js"></script>
</body>
</html>
