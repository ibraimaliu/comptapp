<?php
/**
 * Page: Gestion des Factures avec QR-Invoice
 * Version: 1.0
 * Description: Interface moderne pour créer et gérer les factures conformes ISO 20022
 */

// Inclure les modèles nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/Invoice.php';
include_once dirname(__DIR__) . '/models/QRInvoice.php';
include_once dirname(__DIR__) . '/models/Contact.php';
include_once dirname(__DIR__) . '/models/Company.php';

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
        <p>Veuillez sélectionner une société pour gérer vos factures.</p>
        <p><a href="index.php?page=home" style="color: #856404; font-weight: bold;">← Retour à l\'accueil</a></p>
    </div>';
    exit;
}

// Récupérer les informations de la société pour le QR-Invoice
$company = new Company($db);
$company->id = $company_id;
$company->read();
$company_info = [
    'qr_iban' => $company->qr_iban ?? '',
    'name' => $company->name ?? ''
];

// Récupérer les statistiques des factures
$invoice = new Invoice($db);
try {
    $stats_query = "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
        SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid,
        SUM(CASE WHEN status = 'overdue' THEN 1 ELSE 0 END) as overdue,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
        SUM(total) as total_amount,
        SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_amount,
        SUM(CASE WHEN status != 'paid' AND status != 'cancelled' THEN total ELSE 0 END) as pending_amount
        FROM invoices
        WHERE company_id = :company_id";

    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'draft' => 0, 'sent' => 0, 'paid' => 0, 'overdue' => 0, 'cancelled' => 0,
              'total_amount' => 0, 'paid_amount' => 0, 'pending_amount' => 0];
}

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
    /* Styles similaires à devis.php avec adaptations pour factures */
    .factures-header {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 10px 30px rgba(17, 153, 142, 0.3);
    }

    .factures-header h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 600;
    }

    .factures-header p {
        margin: 0;
        opacity: 0.9;
        font-size: 16px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmin(180px, 1fr));
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

    .stat-card .icon {
        font-size: 32px;
        margin-bottom: 10px;
    }

    .stat-card .value {
        font-size: 28px;
        font-weight: 600;
        margin: 10px 0 5px 0;
    }

    .stat-card .label {
        color: #666;
        font-size: 14px;
    }

    .stat-card.total { border-left: 4px solid #11998e; }
    .stat-card.total .icon { color: #11998e; }
    .stat-card.draft { border-left: 4px solid #f59e0b; }
    .stat-card.draft .icon { color: #f59e0b; }
    .stat-card.sent { border-left: 4px solid #3b82f6; }
    .stat-card.sent .icon { color: #3b82f6; }
    .stat-card.paid { border-left: 4px solid #10b981; }
    .stat-card.paid .icon { color: #10b981; }
    .stat-card.overdue { border-left: 4px solid #ef4444; }
    .stat-card.overdue .icon { color: #ef4444; }

    .actions-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
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
        box-shadow: 0 8px 20px rgba(17, 153, 142, 0.4);
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
        border-color: #11998e;
        color: #11998e;
    }

    .filter-tab.active {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border-color: transparent;
    }

    .invoices-grid {
        display: grid;
        gap: 20px;
    }

    .invoice-card {
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

    .invoice-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }

    .invoice-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }

    .invoice-field {
        display: flex;
        flex-direction: column;
    }

    .invoice-field label {
        font-size: 12px;
        color: #666;
        margin-bottom: 4px;
    }

    .invoice-field value {
        font-size: 16px;
        font-weight: 600;
        color: #333;
    }

    .invoice-number {
        font-size: 20px;
        font-weight: 600;
        color: #11998e;
        margin-bottom: 5px;
    }

    .invoice-status {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
    }

    .status-draft { background: #fef3c7; color: #92400e; }
    .status-sent { background: #dbeafe; color: #1e40af; }
    .status-paid { background: #d1fae5; color: #065f46; }
    .status-overdue { background: #fee2e2; color: #991b1b; }
    .status-cancelled { background: #f3f4f6; color: #374151; }

    .invoice-actions {
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

    .btn-action.primary {
        background: #11998e;
        color: white;
        border-color: #11998e;
    }

    .btn-action.primary:hover {
        background: #0d7a6f;
    }

    .btn-action.danger {
        color: #ef4444;
        border-color: #ef4444;
    }

    .btn-action.danger:hover {
        background: #fef2f2;
    }

    /* Modal styles (réutilisé de devis.php) */
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
        max-width: 900px;
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
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        border-radius: 15px 15px 0 0;
    }

    .modal-header h2 {
        margin: 0;
        font-size: 24px;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: white;
        transition: transform 0.2s;
    }

    .modal-close:hover {
        transform: scale(1.2);
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
        border-color: #11998e;
        box-shadow: 0 0 0 3px rgba(17, 153, 142, 0.1);
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
        border: 2px solid #11998e;
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
        color: #11998e;
    }

    .qr-info {
        margin-top: 20px;
        padding: 15px;
        background: #e0f2f1;
        border-radius: 8px;
        border-left: 4px solid #11998e;
    }

    .qr-info h4 {
        margin: 0 0 10px 0;
        color: #11998e;
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

    @media (max-width: 768px) {
        .invoice-card {
            grid-template-columns: 1fr;
        }

        .invoice-actions {
            flex-direction: row;
            flex-wrap: wrap;
        }

        .item-row {
            grid-template-columns: 1fr;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="factures-header">
    <h1><i class="fas fa-file-invoice"></i> Gestion des Factures</h1>
    <p>Créez et gérez vos factures avec QR-Code suisse (ISO 20022)</p>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card total">
        <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="value" id="stat-total"><?php echo $stats['total'] ?? 0; ?></div>
        <div class="label">Total Factures</div>
    </div>
    <div class="stat-card draft">
        <div class="icon"><i class="fas fa-file-alt"></i></div>
        <div class="value" id="stat-draft"><?php echo $stats['draft'] ?? 0; ?></div>
        <div class="label">Brouillons</div>
    </div>
    <div class="stat-card sent">
        <div class="icon"><i class="fas fa-paper-plane"></i></div>
        <div class="value" id="stat-sent"><?php echo $stats['sent'] ?? 0; ?></div>
        <div class="label">Envoyées</div>
    </div>
    <div class="stat-card paid">
        <div class="icon"><i class="fas fa-check-circle"></i></div>
        <div class="value" id="stat-paid"><?php echo $stats['paid'] ?? 0; ?></div>
        <div class="label">Payées</div>
    </div>
    <div class="stat-card overdue">
        <div class="icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="value" id="stat-overdue"><?php echo $stats['overdue'] ?? 0; ?></div>
        <div class="label">En Retard</div>
    </div>
    <div class="stat-card total">
        <div class="icon"><i class="fas fa-coins"></i></div>
        <div class="value"><?php echo number_format($stats['pending_amount'] ?? 0, 2); ?> CHF</div>
        <div class="label">À Encaisser</div>
    </div>
</div>

<!-- Barre d'actions -->
<div class="actions-bar">
    <button class="btn-primary" onclick="openCreateInvoiceModal()">
        <i class="fas fa-plus"></i> Nouvelle Facture
    </button>

    <div class="filter-tabs">
        <div class="filter-tab active" data-filter="all">Toutes</div>
        <div class="filter-tab" data-filter="draft">Brouillons</div>
        <div class="filter-tab" data-filter="sent">Envoyées</div>
        <div class="filter-tab" data-filter="paid">Payées</div>
        <div class="filter-tab" data-filter="overdue">En Retard</div>
    </div>
</div>

<!-- Liste des factures -->
<div class="invoices-grid" id="invoicesGrid">
    <!-- Les factures seront chargées ici via JavaScript -->
</div>

<!-- État vide -->
<div class="empty-state" id="emptyState" style="display: none;">
    <i class="fas fa-file-invoice"></i>
    <h3>Aucune facture trouvée</h3>
    <p>Créez votre première facture pour commencer</p>
    <button class="btn-primary" onclick="openCreateInvoiceModal()">
        <i class="fas fa-plus"></i> Créer une Facture
    </button>
</div>

<!-- Modal Créer/Modifier Facture -->
<div class="modal" id="invoiceModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Nouvelle Facture</h2>
            <button class="modal-close" onclick="closeInvoiceModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="invoiceForm">
                <input type="hidden" id="invoiceId" name="id">
                <input type="hidden" id="companyId" name="company_id" value="<?php echo $company_id; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label for="client_id">Client <span class="required">*</span></label>
                        <select id="client_id" name="client_id" class="form-control" required onchange="loadClientInfo()">
                            <option value="">-- Sélectionner un client --</option>
                            <?php foreach ($clients as $client):
                                $name = $client['name'] ?? $client['nom'] ?? $client['title'] ?? 'Client sans nom';
                            ?>
                                <option value="<?php echo $client['id']; ?>"
                                        data-name="<?php echo htmlspecialchars($name); ?>"
                                        data-address="<?php echo htmlspecialchars($client['address'] ?? $client['adresse'] ?? ''); ?>"
                                        data-postal="<?php echo htmlspecialchars($client['postal_code'] ?? $client['code_postal'] ?? $client['npa'] ?? ''); ?>"
                                        data-city="<?php echo htmlspecialchars($client['city'] ?? $client['ville'] ?? $client['localite'] ?? ''); ?>">
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date">Date de Facture <span class="required">*</span></label>
                        <input type="date" id="date" name="date" class="form-control"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="due_date">Date d'Échéance <span class="required">*</span></label>
                        <input type="date" id="due_date" name="due_date" class="form-control"
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

                <!-- Informations QR -->
                <div class="qr-info">
                    <h4><i class="fas fa-qrcode"></i> QR-Facture Suisse (ISO 20022)</h4>
                    <p style="margin: 0; font-size: 14px;">
                        Un QR-Code conforme sera automatiquement généré pour cette facture, permettant un paiement facile par votre client.
                    </p>
                    <?php if (!empty($company_info['qr_iban'])): ?>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #0d7a6f;">
                            <i class="fas fa-check-circle"></i> QR-IBAN configuré: <?php echo $company_info['qr_iban']; ?>
                        </p>
                    <?php else: ?>
                        <p style="margin: 5px 0 0 0; font-size: 13px; color: #f59e0b;">
                            <i class="fas fa-exclamation-circle"></i> QR-IBAN non configuré. Allez dans Paramètres pour l'ajouter.
                        </p>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="notes">Notes / Conditions</label>
                    <textarea id="notes" name="notes" class="form-control" rows="3"
                              placeholder="Conditions de paiement, remarques..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeInvoiceModal()">
                Annuler
            </button>
            <button type="button" class="btn-primary" onclick="saveInvoice()">
                <i class="fas fa-save"></i> Enregistrer
            </button>
        </div>
    </div>
</div>

<script>
    // Configuration
    const API_URL = 'assets/ajax/invoices.php';
    const company_id = <?php echo $company_id; ?>;

    // État
    let invoices = [];
    let currentFilter = 'all';
    let editingInvoiceId = null;

    // Charger les factures au démarrage
    document.addEventListener('DOMContentLoaded', function() {
        loadInvoices();
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
                renderInvoices();
            });
        });
    }

    // Charger les factures
    async function loadInvoices() {
        try {
            const response = await fetch(API_URL);
            const data = await response.json();

            if (data.success) {
                invoices = data.invoices || [];
                renderInvoices();
                updateStats();
            } else {
                console.error('Erreur:', data.message);
            }
        } catch (error) {
            console.error('Erreur:', error);
        }
    }

    // Afficher les factures
    function renderInvoices() {
        const grid = document.getElementById('invoicesGrid');
        const emptyState = document.getElementById('emptyState');

        // Filtrer
        let filtered = invoices;
        if (currentFilter !== 'all') {
            filtered = invoices.filter(inv => inv.status === currentFilter);
        }

        if (filtered.length === 0) {
            grid.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }

        grid.style.display = 'grid';
        emptyState.style.display = 'none';

        grid.innerHTML = filtered.map(invoice => `
            <div class="invoice-card">
                <div class="invoice-info">
                    <div class="invoice-field">
                        <div class="invoice-number">${escapeHtml(invoice.number)}</div>
                        <span class="invoice-status status-${invoice.status}">
                            ${formatStatus(invoice.status)}
                        </span>
                    </div>

                    <div class="invoice-field">
                        <label>Client</label>
                        <value>${escapeHtml(invoice.client_name || 'N/A')}</value>
                    </div>

                    <div class="invoice-field">
                        <label>Date</label>
                        <value>${formatDate(invoice.date)}</value>
                    </div>

                    <div class="invoice-field">
                        <label>Échéance</label>
                        <value>${formatDate(invoice.due_date)}</value>
                    </div>

                    <div class="invoice-field">
                        <label>Montant TTC</label>
                        <value style="color: #11998e; font-size: 18px;">${formatAmount(invoice.total)} CHF</value>
                    </div>
                </div>

                <div class="invoice-actions">
                    <button class="btn-action primary" onclick="viewInvoicePDF(${invoice.id})">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    ${invoice.status === 'draft' || invoice.status === 'sent' ? `
                        <button class="btn-action" onclick="editInvoice(${invoice.id})">
                            <i class="fas fa-edit"></i> Modifier
                        </button>
                    ` : ''}
                    ${invoice.status !== 'paid' && invoice.status !== 'cancelled' ? `
                        <button class="btn-action success" onclick="markAsPaid(${invoice.id})">
                            <i class="fas fa-check"></i> Marquer Payée
                        </button>
                    ` : ''}
                    ${invoice.status === 'draft' ? `
                        <button class="btn-action danger" onclick="deleteInvoice(${invoice.id}, '${escapeHtml(invoice.number)}')">
                            <i class="fas fa-trash"></i> Supprimer
                        </button>
                    ` : ''}
                </div>
            </div>
        `).join('');
    }

    // Ouvrir modal création
    function openCreateInvoiceModal() {
        document.getElementById('modalTitle').textContent = 'Nouvelle Facture';
        document.getElementById('invoiceForm').reset();
        document.getElementById('invoiceId').value = '';
        document.getElementById('companyId').value = company_id;

        // Dates par défaut
        document.getElementById('date').value = new Date().toISOString().split('T')[0];
        const dueDate = new Date();
        dueDate.setDate(dueDate.getDate() + 30);
        document.getElementById('due_date').value = dueDate.toISOString().split('T')[0];

        // Réinitialiser les items
        document.getElementById('itemsList').innerHTML = '';
        addItem();

        updateTotals();
        document.getElementById('invoiceModal').classList.add('show');
    }

    // Fermer modal
    function closeInvoiceModal() {
        document.getElementById('invoiceModal').classList.remove('show');
    }

    // Charger info client
    function loadClientInfo() {
        const select = document.getElementById('client_id');
        const option = select.options[select.selectedIndex];

        // On pourrait afficher les infos client ici si nécessaire
        console.log('Client sélectionné:', {
            name: option.dataset.name,
            address: option.dataset.address,
            postal: option.dataset.postal,
            city: option.dataset.city
        });
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

    // Sauvegarder la facture
    async function saveInvoice() {
        const form = document.getElementById('invoiceForm');

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
            due_date: formData.get('due_date'),
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
                alert('✅ Facture enregistrée avec succès' +
                      (result.qr_reference ? '\nRéférence QR: ' + result.qr_reference : ''));
                closeInvoiceModal();
                loadInvoices();
            } else {
                alert('❌ Erreur: ' + (result.message || 'Erreur inconnue'));
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('❌ Erreur de connexion au serveur');
        }
    }

    // Mettre à jour les statistiques
    function updateStats() {
        const stats = {
            total: invoices.length,
            draft: invoices.filter(i => i.status === 'draft').length,
            sent: invoices.filter(i => i.status === 'sent').length,
            paid: invoices.filter(i => i.status === 'paid').length,
            overdue: invoices.filter(i => i.status === 'overdue').length
        };

        document.getElementById('stat-total').textContent = stats.total;
        document.getElementById('stat-draft').textContent = stats.draft;
        document.getElementById('stat-sent').textContent = stats.sent;
        document.getElementById('stat-paid').textContent = stats.paid;
        document.getElementById('stat-overdue').textContent = stats.overdue;
    }

    // Utilitaires
    function formatStatus(status) {
        const statuses = {
            'draft': 'Brouillon',
            'sent': 'Envoyée',
            'paid': 'Payée',
            'overdue': 'En Retard',
            'cancelled': 'Annulée'
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

    function viewInvoicePDF(id) {
        // Télécharger le PDF de la facture avec QR-Code
        window.location.href = 'assets/ajax/export_invoice_pdf.php?id=' + id;
    }

    function editInvoice(id) {
        // TODO: Implémenter l'édition
        alert('Édition de la facture ' + id);
    }

    function markAsPaid(id) {
        if (confirm('Marquer cette facture comme payée ?')) {
            // TODO: Implémenter le marquage comme payée
            alert('Facture ' + id + ' marquée comme payée');
        }
    }

    async function deleteInvoice(id, number) {
        if (!confirm(`Êtes-vous sûr de vouloir supprimer la facture ${number} ?`)) {
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
                alert('✅ Facture supprimée');
                loadInvoices();
            } else {
                alert('❌ Erreur: ' + result.message);
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('❌ Erreur de connexion');
        }
    }

    // Fermer modal en cliquant en dehors
    document.getElementById('invoiceModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeInvoiceModal();
        }
    });
</script>
