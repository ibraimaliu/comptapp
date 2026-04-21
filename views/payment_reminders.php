<?php
/**
 * View: Payment Reminders (Rappels de Paiement)
 * Purpose: Manage payment reminders for overdue invoices
 */

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'config/database.php';
require_once 'models/PaymentReminder.php';

$database = new Database();
$db = $database->getConnection();
$company_id = $_SESSION['company_id'];

// Initialize model
$reminder = new PaymentReminder($db);

// Get statistics
$stats = $reminder->getStatistics($company_id);

// Get overdue invoices
$overdue_invoices = $reminder->findOverdueInvoices($company_id);

// Get reminder settings
$settings = $reminder->getSettings($company_id);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rappels de Paiement - Gestion Comptable</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .reminders-container {
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
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .stat-card.red .stat-icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .stat-card.orange .stat-icon {
            background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
            color: white;
        }

        .stat-card.yellow .stat-icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .stat-card.green .stat-icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
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
            color: #ef4444;
        }

        .tab.active {
            color: #ef4444;
            border-bottom-color: #ef4444;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Overdue Invoices Table */
        .invoices-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-header {
            background: #f9fafb;
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            font-weight: 600;
            color: #374151;
            display: grid;
            grid-template-columns: 150px 200px 150px 120px 120px 100px 150px;
            gap: 15px;
        }

        .table-row {
            padding: 15px 20px;
            border-bottom: 1px solid #e5e7eb;
            display: grid;
            grid-template-columns: 150px 200px 150px 120px 120px 100px 150px;
            gap: 15px;
            align-items: center;
            transition: background 0.3s;
        }

        .table-row:hover {
            background: #f9fafb;
        }

        .table-row:last-child {
            border-bottom: none;
        }

        .badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-level {
            background: #e0e7ff;
            color: #3730a3;
        }

        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background: white;
            color: #374151;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }

        .btn-action:hover {
            background: #ef4444;
            color: white;
            border-color: #ef4444;
        }

        .btn-action.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Settings Panel */
        .settings-panel {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .settings-section {
            margin-bottom: 30px;
        }

        .settings-section h3 {
            font-size: 18px;
            color: #1f2937;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e5e7eb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group {
            margin-bottom: 15px;
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
            border-color: #ef4444;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
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
            margin-bottom: 10px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
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

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .reminder-preview {
            background: #f9fafb;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            margin-top: 15px;
        }

        .reminder-preview h4 {
            margin-top: 0;
            color: #374151;
        }

        .amount-breakdown {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .amount-row:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 16px;
            padding-top: 12px;
            margin-top: 5px;
            border-top: 2px solid #e5e7eb;
        }
    </style>
</head>
<body>
    <div class="reminders-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-bell"></i> Rappels de Paiement</h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="switchTab('settings')">
                    <i class="fas fa-cog"></i> Paramètres
                </button>
                <button class="btn btn-primary" onclick="generateAllReminders()">
                    <i class="fas fa-paper-plane"></i> Générer Rappels
                </button>
            </div>
        </div>

        <!-- Alert if no settings configured -->
        <?php if (!$settings || empty($settings)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Les paramètres de rappels ne sont pas configurés. Veuillez configurer les paramètres avant de générer des rappels.</span>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card red">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="stat-value"><?php echo count($overdue_invoices); ?></div>
                <div class="stat-label">Factures en Retard</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-value"><?php echo $stats['level1'] ?? 0; ?></div>
                <div class="stat-label">1ers Rappels</div>
            </div>

            <div class="stat-card yellow">
                <div class="stat-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="stat-value"><?php echo $stats['level2'] ?? 0; ?></div>
                <div class="stat-label">2èmes Rappels</div>
            </div>

            <div class="stat-card red">
                <div class="stat-icon">
                    <i class="fas fa-gavel"></i>
                </div>
                <div class="stat-value"><?php echo $stats['level3'] ?? 0; ?></div>
                <div class="stat-label">Mises en Demeure</div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['paid'] ?? 0; ?></div>
                <div class="stat-label">Payées Suite Rappel</div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="switchTab('overdue')" data-tab="overdue">
                <i class="fas fa-exclamation-triangle"></i> Factures en Retard (<?php echo count($overdue_invoices); ?>)
            </button>
            <button class="tab" onclick="switchTab('sent')" data-tab="sent">
                <i class="fas fa-paper-plane"></i> Rappels Envoyés
            </button>
            <button class="tab" onclick="switchTab('settings')" data-tab="settings">
                <i class="fas fa-cog"></i> Paramètres
            </button>
        </div>

        <!-- Tab Content: Overdue Invoices -->
        <div class="tab-content active" id="tab-overdue">
            <?php if (empty($overdue_invoices)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="empty-state-text">Aucune facture en retard</div>
                    <p style="color: #6b7280;">Toutes vos factures sont à jour!</p>
                </div>
            <?php else: ?>
                <div class="invoices-table">
                    <div class="table-header">
                        <div>Facture</div>
                        <div>Client</div>
                        <div>Date Échéance</div>
                        <div>Jours Retard</div>
                        <div>Montant</div>
                        <div>Dernier Rappel</div>
                        <div>Action</div>
                    </div>

                    <?php foreach ($overdue_invoices as $invoice): ?>
                        <div class="table-row">
                            <div>
                                <strong><?php echo htmlspecialchars($invoice['invoice_number']); ?></strong>
                                <br>
                                <small style="color: #6b7280;"><?php echo date('d.m.Y', strtotime($invoice['invoice_date'])); ?></small>
                            </div>
                            <div>
                                <?php echo htmlspecialchars($invoice['client_name']); ?>
                                <br>
                                <small style="color: #6b7280;"><?php echo htmlspecialchars($invoice['client_email']); ?></small>
                            </div>
                            <div>
                                <?php echo date('d.m.Y', strtotime($invoice['due_date'])); ?>
                            </div>
                            <div>
                                <span class="badge <?php echo $invoice['days_overdue'] > 30 ? 'badge-danger' : ($invoice['days_overdue'] > 15 ? 'badge-warning' : 'badge-success'); ?>">
                                    <?php echo $invoice['days_overdue']; ?> jours
                                </span>
                            </div>
                            <div>
                                <strong><?php echo number_format($invoice['total'], 2); ?> CHF</strong>
                            </div>
                            <div>
                                <?php if ($invoice['last_reminder_level'] > 0): ?>
                                    <span class="badge badge-level">
                                        Niveau <?php echo $invoice['last_reminder_level']; ?>
                                    </span>
                                    <?php if ($invoice['last_reminder_date']): ?>
                                        <br>
                                        <small style="color: #6b7280;"><?php echo date('d.m.Y', strtotime($invoice['last_reminder_date'])); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #6b7280;">Aucun</span>
                                <?php endif; ?>
                            </div>
                            <div>
                                <button class="btn-action"
                                        onclick="createReminder(<?php echo $invoice['invoice_id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>', <?php echo $invoice['last_reminder_level']; ?>)"
                                        <?php echo ($invoice['last_reminder_level'] >= 3) ? 'disabled class="btn-action disabled"' : ''; ?>>
                                    <i class="fas fa-bell"></i>
                                    <?php if ($invoice['last_reminder_level'] == 0): ?>
                                        1er Rappel
                                    <?php elseif ($invoice['last_reminder_level'] == 1): ?>
                                        2ème Rappel
                                    <?php elseif ($invoice['last_reminder_level'] == 2): ?>
                                        Mise en Demeure
                                    <?php else: ?>
                                        Max Atteint
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Tab Content: Sent Reminders -->
        <div class="tab-content" id="tab-sent">
            <div id="sentRemindersList">
                <!-- Will be loaded via AJAX -->
                <div style="text-align: center; padding: 40px;">
                    <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: #ef4444;"></i>
                    <p style="margin-top: 20px; color: #6b7280;">Chargement des rappels envoyés...</p>
                </div>
            </div>
        </div>

        <!-- Tab Content: Settings -->
        <div class="tab-content" id="tab-settings">
            <div class="settings-panel">
                <form id="settingsForm">
                    <!-- Timing Settings -->
                    <div class="settings-section">
                        <h3><i class="fas fa-clock"></i> Délais des Rappels</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>1er Rappel (jours après échéance)</label>
                                <input type="number" name="level1_days" value="<?php echo $settings['level1_days'] ?? 10; ?>" min="1" max="90">
                            </div>
                            <div class="form-group">
                                <label>2ème Rappel (jours après échéance)</label>
                                <input type="number" name="level2_days" value="<?php echo $settings['level2_days'] ?? 20; ?>" min="1" max="90">
                            </div>
                            <div class="form-group">
                                <label>Mise en Demeure (jours après échéance)</label>
                                <input type="number" name="level3_days" value="<?php echo $settings['level3_days'] ?? 30; ?>" min="1" max="90">
                            </div>
                        </div>
                    </div>

                    <!-- Fees Settings -->
                    <div class="settings-section">
                        <h3><i class="fas fa-coins"></i> Frais de Rappel</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Frais 1er Rappel (CHF)</label>
                                <input type="number" step="0.01" name="level1_fee" value="<?php echo $settings['level1_fee'] ?? 0.00; ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label>Frais 2ème Rappel (CHF)</label>
                                <input type="number" step="0.01" name="level2_fee" value="<?php echo $settings['level2_fee'] ?? 10.00; ?>" min="0">
                            </div>
                            <div class="form-group">
                                <label>Frais Mise en Demeure (CHF)</label>
                                <input type="number" step="0.01" name="level3_fee" value="<?php echo $settings['level3_fee'] ?? 20.00; ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <!-- Interest Settings -->
                    <div class="settings-section">
                        <h3><i class="fas fa-percent"></i> Intérêts de Retard</h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Taux d'intérêt annuel (%)</label>
                                <input type="number" step="0.1" name="interest_rate" value="<?php echo $settings['interest_rate'] ?? 5.00; ?>" min="0" max="15">
                            </div>
                            <div class="form-group">
                                <label>Appliquer les intérêts</label>
                                <div class="checkbox-group">
                                    <input type="checkbox" name="apply_interest" value="1"
                                           <?php echo (isset($settings['apply_interest']) && $settings['apply_interest']) ? 'checked' : ''; ?>>
                                    <span>Calculer automatiquement les intérêts de retard</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Automation Settings -->
                    <div class="settings-section">
                        <h3><i class="fas fa-robot"></i> Automatisation</h3>
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" name="auto_send" value="1"
                                       <?php echo (isset($settings['auto_send']) && $settings['auto_send']) ? 'checked' : ''; ?>>
                                <span>Envoi automatique des rappels (recommandé de désactiver en phase de test)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Save Button -->
                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Enregistrer les Paramètres
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Create Reminder -->
    <div class="modal" id="reminderModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="reminderModalTitle">Créer un Rappel</h2>
                <button class="modal-close" onclick="closeReminderModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="reminder_invoice_id">
                <input type="hidden" id="reminder_level">

                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span id="reminderInfoText"></span>
                </div>

                <div class="reminder-preview">
                    <h4>Aperçu du Rappel</h4>
                    <p><strong>Facture:</strong> <span id="preview_invoice_number"></span></p>
                    <p><strong>Niveau:</strong> <span id="preview_level"></span></p>
                    <p><strong>Jours de retard:</strong> <span id="preview_days"></span></p>

                    <div class="amount-breakdown">
                        <h4>Détail du Montant</h4>
                        <div class="amount-row">
                            <span>Montant original:</span>
                            <span id="preview_original_amount">0.00 CHF</span>
                        </div>
                        <div class="amount-row">
                            <span>Intérêts de retard:</span>
                            <span id="preview_interest">0.00 CHF</span>
                        </div>
                        <div class="amount-row">
                            <span>Frais de rappel:</span>
                            <span id="preview_fees">0.00 CHF</span>
                        </div>
                        <div class="amount-row">
                            <span>Total à payer:</span>
                            <span id="preview_total">0.00 CHF</span>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-top: 20px;">
                    <label>Notes (optionnel)</label>
                    <textarea id="reminder_notes" rows="3" placeholder="Notes internes sur ce rappel..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeReminderModal()">Annuler</button>
                <button class="btn btn-primary" onclick="sendReminder()">
                    <i class="fas fa-paper-plane"></i> Créer et Envoyer
                </button>
            </div>
        </div>
    </div>

    <script src="assets/js/payment_reminders.js"></script>
</body>
</html>
