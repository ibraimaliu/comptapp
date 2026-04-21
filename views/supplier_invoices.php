<?php
/**
 * Page: Gestion des Factures Fournisseurs
 * Version: 1.0
 * Description: Interface pour gérer les factures reçues des fournisseurs
 */

// Inclure les modèles nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/SupplierInvoice.php';
include_once dirname(__DIR__) . '/models/Payment.php';
include_once dirname(__DIR__) . '/models/Contact.php';

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Vérifier si une société est sélectionnée
$company_id = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

if (!$company_id) {
    echo '<div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 8px; margin: 20px;">
        <h3>⚠️ Aucune société sélectionnée</h3>
        <p>Veuillez sélectionner une société pour gérer vos factures fournisseurs.</p>
    </div>';
    exit;
}

// Récupérer les statistiques
$supplierInvoice = new SupplierInvoice($db);
$stats = $supplierInvoice->getStatistics($company_id);
$overdue = $supplierInvoice->getOverdueInvoices($company_id);

// Récupérer les fournisseurs
$contact = new Contact($db);
$contact->company_id = $company_id;
$suppliers = [];
$stmt = $db->prepare("SELECT * FROM contacts WHERE company_id = :company_id AND type = 'supplier' ORDER BY name");
$stmt->bindParam(':company_id', $company_id);
$stmt->execute();
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    .supplier-invoices-container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .page-header {
        margin-bottom: 30px;
    }

    .page-title {
        font-size: 28px;
        font-weight: 600;
        color: #1f2937;
        margin-bottom: 10px;
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 4px solid;
    }

    .stat-card.blue { border-left-color: #3b82f6; }
    .stat-card.green { border-left-color: #10b981; }
    .stat-card.red { border-left-color: #ef4444; }
    .stat-card.orange { border-left-color: #f59e0b; }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #6b7280;
        font-size: 14px;
    }

    .tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #e5e7eb;
    }

    .tab {
        padding: 12px 24px;
        background: none;
        border: none;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-weight: 500;
        color: #6b7280;
        transition: all 0.2s;
    }

    .tab.active {
        color: #3b82f6;
        border-bottom-color: #3b82f6;
    }

    .tab:hover {
        color: #3b82f6;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .action-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .filters {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }

    .btn {
        padding: 10px 20px;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: #3b82f6;
        color: white;
    }

    .btn-primary:hover {
        background: #2563eb;
    }

    .btn-success {
        background: #10b981;
        color: white;
    }

    .btn-danger {
        background: #ef4444;
        color: white;
    }

    .btn-action {
        padding: 6px 12px;
        background: #f3f4f6;
        border: 1px solid #e5e7eb;
    }

    .btn-action:hover {
        background: #e5e7eb;
    }

    .invoices-table {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
    }

    .table-header, .table-row {
        display: grid;
        grid-template-columns: 120px 150px 1fr 120px 120px 100px 150px;
        gap: 15px;
        padding: 15px 20px;
        align-items: center;
    }

    .table-header {
        background: #f9fafb;
        font-weight: 600;
        color: #374151;
        border-bottom: 2px solid #e5e7eb;
    }

    .table-row {
        border-bottom: 1px solid #e5e7eb;
        transition: background 0.2s;
    }

    .table-row:hover {
        background: #f9fafb;
    }

    .badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }

    .badge-received { background: #dbeafe; color: #1e40af; }
    .badge-approved { background: #d1fae5; color: #065f46; }
    .badge-paid { background: #d4edda; color: #155724; }
    .badge-overdue { background: #fee2e2; color: #991b1b; }
    .badge-cancelled { background: #f3f4f6; color: #6b7280; }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 800px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
        padding: 30px;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .modal-title {
        font-size: 24px;
        font-weight: 600;
    }

    .close-modal {
        background: none;
        border: none;
        font-size: 28px;
        cursor: pointer;
        color: #6b7280;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        margin-bottom: 8px;
        font-weight: 500;
        color: #374151;
    }

    .form-group input, .form-group select, .form-group textarea {
        padding: 10px 12px;
        border: 1px solid #d1d5db;
        border-radius: 6px;
        font-size: 14px;
    }

    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    .items-section {
        margin-top: 25px;
        padding-top: 25px;
        border-top: 2px solid #e5e7eb;
    }

    .item-row {
        display: grid;
        grid-template-columns: 2fr 100px 120px 100px 1fr auto;
        gap: 10px;
        margin-bottom: 10px;
        align-items: end;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }

    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 15px;
        color: #d1d5db;
    }
</style>

<div class="supplier-invoices-container">
    <div class="page-header">
        <h1 class="page-title">Factures Fournisseurs</h1>
        <p style="color: #6b7280;">Gestion des factures reçues et paiements fournisseurs</p>
    </div>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-card blue">
            <div class="stat-value"><?php echo $stats['total_invoices'] ?? 0; ?></div>
            <div class="stat-label">Total Factures</div>
        </div>
        <div class="stat-card green">
            <div class="stat-value"><?php echo number_format($stats['paid_amount'] ?? 0, 2); ?> CHF</div>
            <div class="stat-label">Montant Payé</div>
        </div>
        <div class="stat-card orange">
            <div class="stat-value"><?php echo number_format($stats['pending_amount'] ?? 0, 2); ?> CHF</div>
            <div class="stat-label">À Payer</div>
        </div>
        <div class="stat-card red">
            <div class="stat-value"><?php echo $stats['overdue_count'] ?? 0; ?></div>
            <div class="stat-label">Factures en Retard</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <button class="tab active" onclick="switchTab('all')">Toutes</button>
        <button class="tab" onclick="switchTab('received')">À Approuver</button>
        <button class="tab" onclick="switchTab('approved')">Approuvées</button>
        <button class="tab" onclick="switchTab('overdue')">En Retard</button>
    </div>

    <!-- Contenu des tabs -->
    <div id="tab-all" class="tab-content active">
        <div class="action-bar">
            <div class="filters">
                <select id="filterSupplier" onchange="loadInvoices()">
                    <option value="">Tous les fournisseurs</option>
                    <?php foreach ($suppliers as $supplier): ?>
                        <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <i class="fas fa-plus"></i>
                Nouvelle Facture
            </button>
        </div>

        <div id="invoicesList" class="invoices-table">
            <!-- Chargé via JavaScript -->
        </div>
    </div>

    <div id="tab-received" class="tab-content">
        <div id="receivedInvoicesList"></div>
    </div>

    <div id="tab-approved" class="tab-content">
        <div id="approvedInvoicesList"></div>
    </div>

    <div id="tab-overdue" class="tab-content">
        <div class="invoices-table">
            <div class="table-header">
                <div>N° Facture</div>
                <div>Fournisseur</div>
                <div>Date</div>
                <div>Échéance</div>
                <div>Retard</div>
                <div>Montant</div>
                <div>Actions</div>
            </div>
            <?php if (empty($overdue)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-check-circle"></i></div>
                    <div class="empty-state-text">Aucune facture en retard</div>
                </div>
            <?php else: ?>
                <?php foreach ($overdue as $inv): ?>
                    <div class="table-row">
                        <div><strong><?php echo htmlspecialchars($inv['invoice_number']); ?></strong></div>
                        <div><?php echo htmlspecialchars($inv['supplier_name']); ?></div>
                        <div><?php echo date('d/m/Y', strtotime($inv['invoice_date'])); ?></div>
                        <div><?php echo date('d/m/Y', strtotime($inv['due_date'])); ?></div>
                        <div><span class="badge badge-overdue"><?php echo $inv['days_overdue']; ?> jours</span></div>
                        <div><strong><?php echo number_format($inv['amount_due'], 2); ?> CHF</strong></div>
                        <div>
                            <button class="btn-action" onclick="markAsPaid(<?php echo $inv['invoice_id']; ?>)">
                                <i class="fas fa-check"></i> Payer
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: Nouvelle Facture -->
<div id="createInvoiceModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Nouvelle Facture Fournisseur</h2>
            <button class="close-modal" onclick="closeCreateModal()">&times;</button>
        </div>

        <form id="createInvoiceForm" onsubmit="createInvoice(event)">
            <div class="form-grid">
                <div class="form-group">
                    <label>Fournisseur *</label>
                    <select name="supplier_id" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?php echo $supplier['id']; ?>"><?php echo htmlspecialchars($supplier['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>N° Facture *</label>
                    <input type="text" name="invoice_number" required>
                </div>

                <div class="form-group">
                    <label>Date Facture *</label>
                    <input type="date" name="invoice_date" required value="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label>Date Échéance *</label>
                    <input type="date" name="due_date" required value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                </div>

                <div class="form-group">
                    <label>Référence QR</label>
                    <input type="text" name="qr_reference" maxlength="27">
                </div>

                <div class="form-group">
                    <label>IBAN</label>
                    <input type="text" name="iban" maxlength="34">
                </div>

                <div class="form-group full-width">
                    <label>Notes</label>
                    <textarea name="notes" rows="3"></textarea>
                </div>
            </div>

            <div class="items-section">
                <h3 style="margin-bottom: 15px;">Lignes de Facture</h3>
                <div id="invoiceItems">
                    <div class="item-row">
                        <input type="text" placeholder="Description" name="items[0][description]" required>
                        <input type="number" placeholder="Qté" name="items[0][quantity]" step="0.01" value="1" required>
                        <input type="number" placeholder="Prix unit." name="items[0][unit_price]" step="0.01" required>
                        <input type="number" placeholder="TVA %" name="items[0][tva_rate]" step="0.01" value="7.7" required>
                        <input type="number" placeholder="Total" name="items[0][total]" step="0.01" readonly>
                        <button type="button" class="btn-action" onclick="removeItem(this)"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <button type="button" class="btn btn-action" onclick="addItem()" style="margin-top: 10px;">
                    <i class="fas fa-plus"></i> Ajouter ligne
                </button>
            </div>

            <div style="margin-top: 25px; padding-top: 25px; border-top: 2px solid #e5e7eb;">
                <div style="text-align: right; font-size: 18px; font-weight: 600;">
                    Total: <span id="totalAmount">0.00</span> CHF
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                <button type="button" class="btn btn-action" onclick="closeCreateModal()">Annuler</button>
                <button type="submit" class="btn btn-success">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script src="assets/js/supplier_invoices.js"></script>

<script>
// Variables globales
const companyId = <?php echo $company_id; ?>;
const userId = <?php echo $user_id; ?>;

// Charger les factures au chargement de la page
document.addEventListener('DOMContentLoaded', function() {
    loadInvoices();
});
</script>
