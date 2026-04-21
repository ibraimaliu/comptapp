<?php
/**
 * View: Bank Reconciliation
 * Purpose: Import and reconcile bank statements
 */

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'config/database.php';
require_once 'models/BankAccount.php';
require_once 'models/BankTransaction.php';

$database = new Database();
$db = $database->getConnection();
$company_id = $_SESSION['company_id'];

// Initialize models
$bank_account = new BankAccount($db);
$bank_transaction = new BankTransaction($db);

// Get statistics
$account_stats = $bank_account->getStatistics($company_id);
$transaction_stats = $bank_transaction->getStatistics($company_id);

// Get all bank accounts
$bank_accounts = $bank_account->readByCompany($company_id);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapprochement Bancaire - Gestion Comptable</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .reconciliation-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .page-header h1 {
            font-size: 28px;
            color: #1f2937;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        /* Statistics Cards */
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
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }

        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-card.purple .stat-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card.green .stat-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .stat-card.orange .stat-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .stat-card.blue .stat-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .stat-card .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #1f2937;
            margin: 10px 0 5px 0;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: #6b7280;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e5e7eb;
        }

        .tab {
            padding: 12px 24px;
            border: none;
            background: transparent;
            color: #6b7280;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
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

        /* Bank Accounts List */
        .accounts-list {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .account-item {
            padding: 20px;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background 0.3s;
        }

        .account-item:hover {
            background: #f9fafb;
        }

        .account-item:last-child {
            border-bottom: none;
        }

        .account-info {
            flex: 1;
        }

        .account-name {
            font-size: 16px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .account-details {
            font-size: 14px;
            color: #6b7280;
        }

        .account-balance {
            text-align: right;
            margin-right: 20px;
        }

        .balance-amount {
            font-size: 24px;
            font-weight: bold;
            color: #1f2937;
        }

        .balance-label {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
        }

        .account-actions {
            display: flex;
            gap: 10px;
        }

        .btn-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: 1px solid #d1d5db;
            background: white;
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .btn-icon:hover {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Import Zone */
        .import-zone {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }

        .dropzone {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 60px 40px;
            background: #f9fafb;
            transition: all 0.3s;
            cursor: pointer;
        }

        .dropzone:hover, .dropzone.dragover {
            border-color: #667eea;
            background: #eef2ff;
        }

        .dropzone-icon {
            font-size: 64px;
            color: #667eea;
            margin-bottom: 20px;
        }

        .dropzone-text {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 10px;
        }

        .dropzone-hint {
            font-size: 14px;
            color: #6b7280;
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
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
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
            font-size: 20px;
            color: #1f2937;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #6b7280;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6b7280;
        }

        .empty-state-icon {
            font-size: 64px;
            color: #d1d5db;
            margin-bottom: 20px;
        }

        .empty-state-text {
            font-size: 18px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="reconciliation-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-sync-alt"></i> Rapprochement Bancaire</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="openAccountModal()">
                    <i class="fas fa-plus"></i> Nouveau Compte
                </button>
                <button class="btn btn-primary" onclick="switchTab('import')">
                    <i class="fas fa-file-import"></i> Importer Relevé
                </button>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-university"></i>
                </div>
                <div class="stat-value"><?php echo $account_stats['active']; ?></div>
                <div class="stat-label">Comptes Actifs</div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $transaction_stats['matched']; ?></div>
                <div class="stat-label">Transactions Rapprochées</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $transaction_stats['pending']; ?></div>
                <div class="stat-label">En Attente</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-value"><?php echo number_format($account_stats['total_balance'], 2); ?> CHF</div>
                <div class="stat-label">Solde Total</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('accounts')" data-tab="accounts">
                <i class="fas fa-university"></i> Comptes Bancaires
            </button>
            <button class="tab" onclick="switchTab('import')" data-tab="import">
                <i class="fas fa-file-import"></i> Importer Relevé
            </button>
            <button class="tab" onclick="switchTab('pending')" data-tab="pending">
                <i class="fas fa-clock"></i> Transactions En Attente (<?php echo $transaction_stats['pending']; ?>)
            </button>
            <button class="tab" onclick="switchTab('reconciled')" data-tab="reconciled">
                <i class="fas fa-check-circle"></i> Rapprochées
            </button>
        </div>

        <!-- Tab Content: Accounts -->
        <div class="tab-content active" id="tab-accounts">
            <?php if (empty($bank_accounts)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="empty-state-text">Aucun compte bancaire configuré</div>
                    <button class="btn btn-primary" onclick="openAccountModal()">
                        <i class="fas fa-plus"></i> Créer le Premier Compte
                    </button>
                </div>
            <?php else: ?>
                <div class="accounts-list">
                    <?php foreach ($bank_accounts as $account): ?>
                        <div class="account-item">
                            <div class="account-info">
                                <div class="account-name">
                                    <?php echo htmlspecialchars($account['name']); ?>
                                    <?php if ($account['is_active']): ?>
                                        <span class="badge badge-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Inactif</span>
                                    <?php endif; ?>
                                </div>
                                <div class="account-details">
                                    <?php echo htmlspecialchars($account['bank_name'] ?? ''); ?> •
                                    <?php echo htmlspecialchars($account['iban'] ?? $account['account_number']); ?> •
                                    <?php echo htmlspecialchars($account['currency']); ?>
                                </div>
                            </div>
                            <div class="account-balance">
                                <div class="balance-amount">
                                    <?php echo number_format($account['current_balance'], 2); ?> <?php echo htmlspecialchars($account['currency']); ?>
                                </div>
                                <div class="balance-label">Solde actuel</div>
                            </div>
                            <div class="account-actions">
                                <button class="btn-icon" onclick="viewTransactions(<?php echo $account['id']; ?>)" title="Voir transactions">
                                    <i class="fas fa-list"></i>
                                </button>
                                <button class="btn-icon" onclick="importForAccount(<?php echo $account['id']; ?>)" title="Importer relevé">
                                    <i class="fas fa-file-import"></i>
                                </button>
                                <button class="btn-icon" onclick="editAccount(<?php echo $account['id']; ?>)" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Content: Import -->
        <div class="tab-content" id="tab-import">
            <div class="import-zone">
                <form id="importForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Compte Bancaire</label>
                        <select name="bank_account_id" id="import_account_id" required>
                            <option value="">Sélectionner un compte</option>
                            <?php foreach ($bank_accounts as $account): ?>
                                <?php if ($account['is_active']): ?>
                                    <option value="<?php echo $account['id']; ?>">
                                        <?php echo htmlspecialchars($account['name']); ?> - <?php echo htmlspecialchars($account['iban'] ?? $account['account_number']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dropzone" id="dropzone" onclick="document.getElementById('fileInput').click()">
                        <input type="file" id="fileInput" name="statement_file" accept=".xml,.txt,.csv,.940,.053" style="display:none" onchange="handleFileSelect(event)">
                        <div class="dropzone-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <div class="dropzone-text">Glissez votre relevé bancaire ici</div>
                        <div class="dropzone-hint">ou cliquez pour sélectionner un fichier</div>
                        <div class="dropzone-hint" style="margin-top: 10px;">
                            Formats supportés: Camt.053 (XML), MT940, CSV
                        </div>
                    </div>

                    <div id="fileInfo" style="display:none; margin-top: 20px; padding: 15px; background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <i class="fas fa-file" style="color: #16a34a; margin-right: 10px;"></i>
                                <span id="fileName" style="font-weight: 500;"></span>
                                <span id="fileSize" style="color: #6b7280; margin-left: 10px;"></span>
                            </div>
                            <button type="button" onclick="clearFile()" style="background: none; border: none; color: #dc2626; cursor: pointer;">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary" style="padding: 12px 40px; font-size: 16px;">
                            <i class="fas fa-upload"></i> Importer et Analyser
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tab Content: Pending Transactions -->
        <div class="tab-content" id="tab-pending">
            <div id="pendingTransactions">
                <!-- Will be loaded via AJAX -->
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #667eea;"></i>
                    <p style="margin-top: 20px; color: #6b7280;">Chargement des transactions...</p>
                </div>
            </div>
        </div>

        <!-- Tab Content: Reconciled -->
        <div class="tab-content" id="tab-reconciled">
            <div id="reconciledTransactions">
                <!-- Will be loaded via AJAX -->
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #667eea;"></i>
                    <p style="margin-top: 20px; color: #6b7280;">Chargement des transactions...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: New/Edit Account -->
    <div class="modal" id="accountModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="accountModalTitle">Nouveau Compte Bancaire</h2>
                <button class="modal-close" onclick="closeAccountModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="accountForm">
                    <input type="hidden" id="account_id" name="id">

                    <div class="form-group">
                        <label>Nom du compte *</label>
                        <input type="text" name="name" id="account_name" placeholder="Ex: Compte courant UBS" required>
                    </div>

                    <div class="form-group">
                        <label>Banque</label>
                        <input type="text" name="bank_name" id="account_bank_name" placeholder="Ex: UBS SA">
                    </div>

                    <div class="form-group">
                        <label>IBAN</label>
                        <input type="text" name="iban" id="account_iban" placeholder="CH44 3199 9123 0008 8901 2">
                    </div>

                    <div class="form-group">
                        <label>Numéro de compte (si pas d'IBAN)</label>
                        <input type="text" name="account_number" id="account_number" placeholder="1234567890">
                    </div>

                    <div class="form-group">
                        <label>Devise *</label>
                        <select name="currency" id="account_currency" required>
                            <option value="CHF">CHF - Franc Suisse</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="USD">USD - Dollar US</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Solde d'ouverture</label>
                        <input type="number" step="0.01" name="opening_balance" id="account_opening_balance" value="0.00">
                    </div>

                    <div class="form-group">
                        <label>Date du solde d'ouverture</label>
                        <input type="date" name="opening_balance_date" id="account_opening_balance_date">
                    </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="account_notes" rows="3" placeholder="Notes optionnelles"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeAccountModal()">Annuler</button>
                <button class="btn btn-primary" onclick="saveAccount()">
                    <i class="fas fa-save"></i> Enregistrer
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/bank_reconciliation.js"></script>
</body>
</html>
