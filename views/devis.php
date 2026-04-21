<?php
/**
 * Page: Gestion des Devis/Offres
 * Version: 1.0
 * Description: Interface moderne pour créer et gérer les devis
 */

// Inclure les modèles nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/Quote.php';
include_once dirname(__DIR__) . '/models/Contact.php';

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

if(!$company_id) {
    echo '<div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 8px; margin: 20px;">
        <h3>⚠️ Aucune société sélectionnée</h3>
        <p>Veuillez sélectionner une société pour gérer vos devis.</p>
        <p><a href="index.php?page=home" style="color: #856404; font-weight: bold;">← Retour à l\'accueil</a></p>
    </div>';
    exit;
}

// Récupérer les statistiques
$quote = new Quote($db);
$stats = $quote->getStatistics($company_id);

// Récupérer la liste des contacts (clients)
$contact = new Contact($db);
$clients_stmt = $contact->readByCompany($company_id);
$clients = [];
while ($row = $clients_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($row['type']) && ($row['type'] == 'client' || empty($row['type']))) {
        $clients[] = $row;
    }
}
?>

<style>
    /* Styles spécifiques à la page Devis */
    .devis-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
    }

    .devis-header h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 600;
    }

    .devis-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 16px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .stat-card .value {
        font-size: 32px;
        font-weight: 600;
        margin: 10px 0;
    }

    .stat-card .label {
        color: #666;
        font-size: 14px;
    }

    .stat-card.total .value { color: #667eea; }
    .stat-card.draft .value { color: #f59e0b; }
    .stat-card.sent .value { color: #3b82f6; }
    .stat-card.accepted .value { color: #10b981; }
    .stat-card.rejected .value { color: #ef4444; }
    .stat-card.converted .value { color: #8b5cf6; }

    .actions-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .filter-tabs {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-tab {
        padding: 8px 16px;
        border-radius: 20px;
        background: white;
        border: 2px solid #e5e7eb;
        cursor: pointer;
        transition: all 0.3s;
        font-weight: 500;
    }

    .filter-tab:hover {
        border-color: #667eea;
        color: #667eea;
    }

    .filter-tab.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-color: transparent;
    }

    .quotes-grid {
        display: grid;
        gap: 20px;
    }

    .quote-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.3s, box-shadow 0.3s;
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 20px;
        align-items: center;
    }

    .quote-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .quote-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }

    .quote-field {
        display: flex;
        flex-direction: column;
    }

    .quote-field label {
        font-size: 12px;
        color: #666;
        margin-bottom: 4px;
    }

    .quote-field value {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }

    .quote-number {
        font-size: 18px;
        font-weight: 600;
        color: #667eea;
        margin-bottom: 5px;
    }

    .quote-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-draft { background: #fef3c7; color: #92400e; }
    .status-sent { background: #dbeafe; color: #1e40af; }
    .status-accepted { background: #d1fae5; color: #065f46; }
    .status-rejected { background: #fee2e2; color: #991b1b; }
    .status-expired { background: #f3f4f6; color: #374151; }
    .status-converted { background: #e9d5ff; color: #6b21a8; }

    .quote-actions {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .btn-action {
        padding: 8px 16px;
        border-radius: 6px;
        border: 1px solid #e5e7eb;
        background: white;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 6px;
        justify-content: center;
        white-space: nowrap;
    }

    .btn-action:hover {
        background: #f9fafb;
        transform: translateX(-2px);
    }

    .btn-action.success {
        background: #10b981;
        color: white;
        border-color: #10b981;
    }

    .btn-action.success:hover {
        background: #059669;
    }

    .btn-action.danger {
        color: #ef4444;
        border-color: #ef4444;
    }

    .btn-action.danger:hover {
        background: #fef2f2;
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
        color: #d1d5db;
        margin-bottom: 20px;
    }

    .empty-state h3 {
        font-size: 20px;
        color: #374151;
        margin-bottom: 10px;
    }

    .empty-state p {
        color: #6b7280;
        margin-bottom: 20px;
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
        z-index: 1000;
        overflow-y: auto;
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .modal-content {
        background: white;
        border-radius: 15px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        margin: 20px;
    }

    .modal-header {
        padding: 20px 30px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h2 {
        margin: 0;
        color: #667eea;
        font-size: 24px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #9ca3af;
        transition: color 0.2s;
    }

    .modal-close:hover {
        color: #374151;
    }

    .modal-body {
        padding: 30px;
    }

    .modal-footer {
        padding: 20px 30px;
        border-top: 1px solid #e5e7eb;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #374151;
    }

    .form-control {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        font-size: 16px;
        transition: border-color 0.2s;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }

    .required {
        color: #ef4444;
    }

    .btn-secondary {
        background: #f3f4f6;
        color: #374151;
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.2s;
    }

    .btn-secondary:hover {
        background: #e5e7eb;
    }

    /* Items section */
    .items-section {
        margin: 30px 0;
        padding: 20px;
        background: #f9fafb;
        border-radius: 8px;
    }

    .items-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }

    .items-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .item-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr auto;
        gap: 10px;
        align-items: end;
    }

    .btn-add-item {
        background: #10b981;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
    }

    .btn-add-item:hover {
        background: #059669;
    }

    .btn-remove-item {
        background: #ef4444;
        color: white;
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
    }

    .btn-remove-item:hover {
        background: #dc2626;
    }

    .totals-section {
        margin-top: 20px;
        padding: 15px;
        background: white;
        border-radius: 8px;
        border: 2px solid #667eea;
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 16px;
    }

    .total-row.grand-total {
        border-top: 2px solid #e5e7eb;
        margin-top: 10px;
        padding-top: 10px;
        font-size: 20px;
        font-weight: 600;
        color: #667eea;
    }

    @media (max-width: 768px) {
        .quote-card {
            grid-template-columns: 1fr;
        }

        .quote-actions {
            flex-direction: row;
            flex-wrap: wrap;
        }

        .item-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="devis-header">
    <h1><i class="fas fa-file-alt"></i> Gestion des Devis</h1>
    <p>Créez et gérez vos devis professionnels</p>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card total">
        <div class="label">Total Devis</div>
        <div class="value" id="stat-total"><?php echo $stats['total'] ?? 0; ?></div>
    </div>
    <div class="stat-card draft">
        <div class="label">Brouillons</div>
        <div class="value" id="stat-draft"><?php echo $stats['draft'] ?? 0; ?></div>
    </div>
    <div class="stat-card sent">
        <div class="label">Envoyés</div>
        <div class="value" id="stat-sent"><?php echo $stats['sent'] ?? 0; ?></div>
    </div>
    <div class="stat-card accepted">
        <div class="label">Acceptés</div>
        <div class="value" id="stat-accepted"><?php echo $stats['accepted'] ?? 0; ?></div>
    </div>
    <div class="stat-card rejected">
        <div class="label">Refusés</div>
        <div class="value" id="stat-rejected"><?php echo $stats['rejected'] ?? 0; ?></div>
    </div>
    <div class="stat-card converted">
        <div class="label">Convertis</div>
        <div class="value" id="stat-converted"><?php echo $stats['converted'] ?? 0; ?></div>
    </div>
</div>

<!-- Barre d'actions -->
<div class="actions-bar">
    <button class="btn-primary" onclick="openCreateQuoteModal()">
        <i class="fas fa-plus"></i> Nouveau Devis
    </button>

    <div class="filter-tabs">
        <div class="filter-tab active" data-filter="all">Tous</div>
        <div class="filter-tab" data-filter="draft">Brouillons</div>
        <div class="filter-tab" data-filter="sent">Envoyés</div>
        <div class="filter-tab" data-filter="accepted">Acceptés</div>
        <div class="filter-tab" data-filter="rejected">Refusés</div>
        <div class="filter-tab" data-filter="expired">Expirés</div>
    </div>
</div>

<!-- Liste des devis -->
<div class="quotes-grid" id="quotesGrid">
    <!-- Les devis seront chargés ici via JavaScript -->
</div>

<!-- État vide -->
<div class="empty-state" id="emptyState" style="display: none;">
    <i class="fas fa-file-alt"></i>
    <h3>Aucun devis trouvé</h3>
    <p>Créez votre premier devis pour commencer</p>
    <button class="btn-primary" onclick="openCreateQuoteModal()">
        <i class="fas fa-plus"></i> Créer un Devis
    </button>
</div>

<!-- Modal Créer/Modifier Devis -->
<div class="modal" id="quoteModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nouveau Devis</h2>
            <button class="modal-close" onclick="closeQuoteModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="quoteForm">
                <input type="hidden" id="quoteId" name="id">
                <input type="hidden" id="companyId" name="company_id" value="<?php echo $company_id; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="client_id">Client <span class="required">*</span></label>
                        <select id="client_id" name="client_id" class="form-control" required>
                            <option value="">-- Sélectionner un client --</option>
                            <?php foreach ($clients as $client):
                                $name = $client['name'] ?? $client['nom'] ?? $client['title'] ?? 'Client sans nom';
                            ?>
                                <option value="<?php echo $client['id']; ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date">Date du Devis <span class="required">*</span></label>
                        <input type="date" id="date" name="date" class="form-control"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="valid_until">Valide jusqu'au <span class="required">*</span></label>
                        <input type="date" id="valid_until" name="valid_until" class="form-control"
                               value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                    </div>
                </div>

                <!-- Section Articles/Services -->
                <div class="items-section">
                    <div class="items-header">
                        <h3>Articles / Services</h3>
                        <button type="button" class="btn-add-item" onclick="addItem()">
                            <i class="fas fa-plus"></i> Ajouter
                        </button>
                    </div>

                    <div class="items-list" id="itemsList">
                        <!-- Les items seront ajoutés ici -->
                    </div>
                </div>

                <!-- Totaux -->
                <div class="totals-section">
                    <div class="total-row">
                        <span>Sous-total HT:</span>
                        <span id="subtotalDisplay">0.00 CHF</span>
                    </div>
                    <div class="total-row">
                        <span>TVA:</span>
                        <span id="tvaDisplay">0.00 CHF</span>
                    </div>
                    <div class="total-row grand-total">
                        <span>Total TTC:</span>
                        <span id="totalDisplay">0.00 CHF</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="notes">Notes / Conditions</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"
                              placeholder="Conditions de paiement, remarques..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeQuoteModal()">
                Annuler
            </button>
            <button type="button" class="btn-primary" onclick="saveQuote()">
                <i class="fas fa-save"></i> Enregistrer
            </button>
        </div>
    </div>
</div>

<script>
    // Configuration
    const API_URL = 'assets/ajax/quotes.php';
    const company_id = <?php echo $company_id; ?>;

    // État
    let quotes = [];
    let currentFilter = 'all';
    let editingQuoteId = null;

    // Charger les devis au démarrage
    document.addEventListener('DOMContentLoaded', function() {
        loadQuotes();
        initEventListeners();
        addItem(); // Ajouter une première ligne d'item
    });

    // Event listeners
    function initEventListeners() {
        // Filtres
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                renderQuotes();
            });
        });
    }

    // Charger les devis
    async function loadQuotes() {
        try {
            const response = await fetch(API_URL);
            const data = await response.json();

            if (data.success) {
                quotes = data.quotes || [];
                renderQuotes();
            } else {
                console.error('Erreur:', data.message);
            }
        } catch (error) {
            console.error('Erreur:', error);
        }
    }

    // Afficher les devis
    function renderQuotes() {
        const grid = document.getElementById('quotesGrid');
        const emptyState = document.getElementById('emptyState');

        // Filtrer
        let filtered = quotes;
        if (currentFilter !== 'all') {
            filtered = quotes.filter(q => q.status === currentFilter);
        }

        if (filtered.length === 0) {
            grid.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }

        grid.style.display = 'grid';
        emptyState.style.display = 'none';

        grid.innerHTML = filtered.map(quote => `
            <div class="quote-card">
                <div class="quote-info">
                    <div class="quote-field">
                        <div class="quote-number">${escapeHtml(quote.number)}</div>
                        <span class="quote-status status-${quote.status}">
                            ${formatStatus(quote.status)}
                        </span>
                    </div>

                    <div class="quote-field">
                        <label>Client</label>
                        <value>${escapeHtml(quote.client_name || 'N/A')}</value>
                    </div>

                    <div class="quote-field">
                        <label>Date</label>
                        <value>${formatDate(quote.date)}</value>
                    </div>

                    <div class="quote-field">
                        <label>Valide jusqu'au</label>
                        <value>${formatDate(quote.valid_until)}</value>
                    </div>

                    <div class="quote-field">
                        <label>Montant TTC</label>
                        <value style="color: #667eea; font-size: 18px;">${formatAmount(quote.total)} CHF</value>
                    </div>
                </div>

                <div class="quote-actions">
                    <button class="btn-action" onclick="viewQuote(${quote.id})">
                        <i class="fas fa-eye"></i> Voir
                    </button>
                    <button class="btn-action" onclick="exportQuotePDF(${quote.id})">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    ${quote.status === 'draft' || quote.status === 'sent' ? `
                        <button class="btn-action" onclick="editQuote(${quote.id})">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                    ` : ''}
                    ${quote.status === 'accepted' && !quote.converted_to_invoice_id ? `
                        <button class="btn-action success" onclick="convertToInvoice(${quote.id})">
                            <i class="fas fa-file-invoice"></i> Convertir
                        </button>
                    ` : ''}
                    ${quote.status === 'draft' ? `
                        <button class="btn-action danger" onclick="deleteQuote(${quote.id}, '${escapeHtml(quote.number)}')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }

    // Ouvrir modal création
    function openCreateQuoteModal() {
        document.getElementById('modalTitle').textContent = 'Nouveau Devis';
        document.getElementById('quoteForm').reset();
        document.getElementById('quoteId').value = '';
        document.getElementById('companyId').value = company_id;

        // Réinitialiser les items
        document.getElementById('itemsList').innerHTML = '';
        addItem();

        updateTotals();
        document.getElementById('quoteModal').classList.add('show');
    }

    // Fermer modal
    function closeQuoteModal() {
        document.getElementById('quoteModal').classList.remove('show');
    }

    // Ajouter un item
    let itemCounter = 0;
    function addItem() {
        const itemsList = document.getElementById('itemsList');
        const itemId = itemCounter++;

        const itemRow = document.createElement('div');
        itemRow.className = 'item-row';
        itemRow.dataset.itemId = itemId;
        itemRow.innerHTML = `
            <div class="form-group">
                <label>Description</label>
                <input type="text" class="form-control item-description" placeholder="Description du produit/service" required>
            </div>
            <div class="form-group">
                <label>Quantité</label>
                <input type="number" class="form-control item-quantity" value="1" min="0" step="0.01" required onchange="updateTotals()">
            </div>
            <div class="form-group">
                <label>Prix Unit. HT</label>
                <input type="number" class="form-control item-price" value="0" min="0" step="0.01" required onchange="updateTotals()">
            </div>
            <div class="form-group">
                <label>TVA %</label>
                <input type="number" class="form-control item-tva" value="7.7" min="0" step="0.1" required onchange="updateTotals()">
            </div>
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="button" class="btn-remove-item" onclick="removeItem(${itemId})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;

        itemsList.appendChild(itemRow);
    }

    // Supprimer un item
    function removeItem(itemId) {
        const item = document.querySelector(`[data-item-id="${itemId}"]`);
        if (item) {
            item.remove();
            updateTotals();
        }
    }

    // Mettre à jour les totaux
    function updateTotals() {
        let subtotal = 0;
        let tvaTotal = 0;

        document.querySelectorAll('.item-row').forEach(row => {
            const qty = parseFloat(row.querySelector('.item-quantity').value) || 0;
            const price = parseFloat(row.querySelector('.item-price').value) || 0;
            const tvaRate = parseFloat(row.querySelector('.item-tva').value) || 0;

            const itemSubtotal = qty * price;
            const itemTva = itemSubtotal * (tvaRate / 100);

            subtotal += itemSubtotal;
            tvaTotal += itemTva;
        });

        const total = subtotal + tvaTotal;

        document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2) + ' CHF';
        document.getElementById('tvaDisplay').textContent = tvaTotal.toFixed(2) + ' CHF';
        document.getElementById('totalDisplay').textContent = total.toFixed(2) + ' CHF';
    }

    // Sauvegarder le devis
    async function saveQuote() {
        const form = document.getElementById('quoteForm');

        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Collecter les items
        const items = [];
        document.querySelectorAll('.item-row').forEach(row => {
            const description = row.querySelector('.item-description').value;
            const quantity = parseFloat(row.querySelector('.item-quantity').value);
            const unit_price = parseFloat(row.querySelector('.item-price').value);
            const tva_rate = parseFloat(row.querySelector('.item-tva').value);

            if (description && quantity > 0) {
                items.push({
                    description,
                    quantity,
                    unit_price,
                    tva_rate
                });
            }
        });

        if (items.length === 0) {
            alert('Veuillez ajouter au moins un article');
            return;
        }

        const formData = new FormData(form);
        const data = {
            id: formData.get('id') || null,
            company_id: parseInt(formData.get('company_id')),
            client_id: parseInt(formData.get('client_id')),
            date: formData.get('date'),
            valid_until: formData.get('valid_until'),
            notes: formData.get('notes'),
            items: items
        };

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'create', ...data })
            });

            const result = await response.json();

            if (result.success) {
                alert('✅ Devis enregistré avec succès');
                closeQuoteModal();
                loadQuotes();
            } else {
                alert('❌ Erreur: ' + (result.message || 'Erreur inconnue'));
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('❌ Erreur de connexion au serveur');
        }
    }

    // Utilitaires
    function formatStatus(status) {
        const statuses = {
            'draft': 'Brouillon',
            'sent': 'Envoyé',
            'accepted': 'Accepté',
            'rejected': 'Refusé',
            'expired': 'Expiré',
            'converted': 'Converti'
        };
        return statuses[status] || status;
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'N/A';
        const date = new Date(dateStr);
        return date.toLocaleDateString('fr-CH');
    }

    function formatAmount(amount) {
        return parseFloat(amount).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, "'");
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function viewQuote(id) {
        // TODO: Implémenter la visualisation PDF
        alert('Visualisation du devis ' + id);
    }

    function editQuote(id) {
        // TODO: Implémenter l'édition
        alert('Édition du devis ' + id);
    }

    function convertToInvoice(id) {
        if (confirm('Voulez-vous convertir ce devis en facture ?')) {
            // TODO: Implémenter la conversion
            alert('Conversion du devis ' + id + ' en facture');
        }
    }

    async function deleteQuote(id, number) {
        if (!confirm(`Êtes-vous sûr de vouloir supprimer le devis ${number} ?`)) {
            return;
        }

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ action: 'delete', id: id })
            });

            const result = await response.json();

            if (result.success) {
                alert('✅ Devis supprimé');
                loadQuotes();
            } else {
                alert('❌ Erreur: ' + result.message);
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('❌ Erreur de connexion');
        }
    }

    // Exporter le devis en PDF
    function exportQuotePDF(id) {
        window.location.href = 'assets/ajax/export_quote_pdf.php?id=' + id;
    }

    // Fermer modal en cliquant en dehors
    document.getElementById('quoteModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeQuoteModal();
        }
    });
</script>
