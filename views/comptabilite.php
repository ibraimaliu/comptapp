<?php
/**
 * Page: Comptabilité
 * Version: 3.0 - Refonte complète
 * Description: Gestion des transactions, factures, devis et rapports
 */

// Inclure les modèles nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/Transaction.php';
include_once dirname(__DIR__) . '/models/Invoice.php';
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
        <p>Veuillez sélectionner une société pour accéder à la comptabilité.</p>
        <p><a href="index.php?page=home" style="color: #856404; font-weight: bold;">← Retour à l\'accueil</a></p>
    </div>';
    exit;
}

// Déterminer l'onglet actif
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'transactions';

// Récupérer les statistiques
$transaction = new Transaction($db);
$stats = $transaction->getStatistics($company_id);

// Récupérer les transactions récentes
$recent_transactions_stmt = $transaction->getRecentByCompany($company_id, 20);
$transactions = [];
while($row = $recent_transactions_stmt->fetch(PDO::FETCH_ASSOC)) {
    $transactions[] = $row;
}

// Récupérer les factures
$invoice = new Invoice($db);
$invoices_stmt = $invoice->readByCompany($company_id);
$invoices = [];
while($row = $invoices_stmt->fetch(PDO::FETCH_ASSOC)) {
    $invoices[] = $row;
}

// Récupérer les clients
$contact = new Contact($db);
$clients_stmt = $contact->readByCompany($company_id);
$clients = [];
while($row = $clients_stmt->fetch(PDO::FETCH_ASSOC)) {
    if (isset($row['type']) && $row['type'] === 'client') {
        $clients[] = $row;
    }
}

$page_title = "Comptabilité";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Gestion Comptable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* Container */
        .accounting-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .accounting-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .accounting-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.2em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .accounting-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.05em;
        }

        /* Tabs */
        .tabs-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .tabs-nav {
            display: flex;
            border-bottom: 2px solid #f7fafc;
            overflow-x: auto;
        }

        .tab-button {
            flex: 1;
            min-width: 150px;
            padding: 18px 25px;
            background: transparent;
            border: none;
            color: #718096;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            border-bottom: 3px solid transparent;
        }

        .tab-button:hover {
            background: #f7fafc;
            color: #667eea;
        }

        .tab-button.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: #f7fafc;
        }

        .tab-content {
            padding: 25px;
        }

        .tab-pane {
            display: none;
        }

        .tab-pane.active {
            display: block;
        }

        /* Stats Grid */
        .stats-mini-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-mini-card {
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .stat-mini-card.income {
            border-left-color: #38ef7d;
        }

        .stat-mini-card.expense {
            border-left-color: #f45c43;
        }

        .stat-mini-label {
            font-size: 0.85em;
            color: #718096;
            margin-bottom: 5px;
        }

        .stat-mini-value {
            font-size: 1.4em;
            font-weight: 700;
            color: #2d3748;
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .section-header h2 {
            margin: 0;
            color: #2d3748;
            font-size: 1.5em;
        }

        /* Buttons */
        .btn-primary {
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
        }

        .data-table thead {
            background: #f7fafc;
        }

        .data-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid #f7fafc;
            color: #4a5568;
        }

        .data-table tr:last-child td {
            border-bottom: none;
        }

        .data-table tr:hover {
            background: #f7fafc;
        }

        /* Transaction Item */
        .transaction-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: all 0.2s;
        }

        .transaction-item:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .transaction-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2em;
            flex-shrink: 0;
        }

        .transaction-icon.income {
            background: #c6f6d5;
            color: #22543d;
        }

        .transaction-icon.expense {
            background: #fed7d7;
            color: #742a2a;
        }

        .transaction-details {
            flex: 1;
        }

        .transaction-description {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .transaction-date {
            font-size: 0.85em;
            color: #718096;
        }

        .transaction-amount {
            font-size: 1.1em;
            font-weight: 700;
        }

        .transaction-amount.income {
            color: #38a169;
        }

        .transaction-amount.expense {
            color: #e53e3e;
        }

        /* Invoice Card */
        .invoice-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: all 0.2s;
        }

        .invoice-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }

        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .invoice-number {
            font-size: 1.2em;
            font-weight: 700;
            color: #2d3748;
        }

        .invoice-status {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .invoice-status.paid {
            background: #c6f6d5;
            color: #22543d;
        }

        .invoice-status.pending {
            background: #feebc8;
            color: #7c2d12;
        }

        .invoice-status.overdue {
            background: #fed7d7;
            color: #742a2a;
        }

        .invoice-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .invoice-detail-item {
            font-size: 0.9em;
        }

        .invoice-detail-label {
            color: #718096;
            margin-bottom: 4px;
        }

        .invoice-detail-value {
            font-weight: 600;
            color: #2d3748;
        }

        .invoice-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-sm {
            padding: 8px 15px;
            border: none;
            border-radius: 6px;
            font-size: 0.9em;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-view {
            background: #4299e1;
            color: white;
        }

        .btn-view:hover {
            background: #3182ce;
        }

        .btn-download {
            background: #38a169;
            color: white;
        }

        .btn-download:hover {
            background: #2f855a;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            color: #cbd5e0;
        }

        .empty-state h3 {
            margin: 0 0 10px 0;
            color: #4a5568;
            font-size: 1.4em;
        }

        .empty-state p {
            margin: 0 0 20px 0;
            font-size: 1.05em;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .accounting-header h1 {
                font-size: 1.6em;
            }

            .tabs-nav {
                flex-wrap: nowrap;
                overflow-x: scroll;
            }

            .tab-button {
                white-space: nowrap;
            }

            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .transaction-item,
            .invoice-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .transaction-icon {
                margin: 0;
            }
        }
    </style>
</head>
<body>

<div class="accounting-container">
    <!-- Header -->
    <div class="accounting-header">
        <h1><i class="fas fa-chart-line"></i> Comptabilité</h1>
        <p>Gestion financière complète de votre entreprise</p>
    </div>

    <!-- Stats Mini -->
    <div class="stats-mini-grid">
        <div class="stat-mini-card income">
            <div class="stat-mini-label">Revenus</div>
            <div class="stat-mini-value"><?php echo number_format($stats['total_income'], 2); ?> CHF</div>
        </div>
        <div class="stat-mini-card expense">
            <div class="stat-mini-label">Dépenses</div>
            <div class="stat-mini-value"><?php echo number_format($stats['total_expenses'], 2); ?> CHF</div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-label">Bénéfice</div>
            <div class="stat-mini-value"><?php echo number_format($stats['profit'], 2); ?> CHF</div>
        </div>
        <div class="stat-mini-card">
            <div class="stat-mini-label">TVA</div>
            <div class="stat-mini-value"><?php echo number_format($stats['total_tva'], 2); ?> CHF</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tabs-container">
        <div class="tabs-nav">
            <a href="?page=comptabilite&tab=transactions" class="tab-button <?php echo $active_tab == 'transactions' ? 'active' : ''; ?>">
                <i class="fas fa-exchange-alt"></i>
                Transactions
            </a>
            <a href="?page=comptabilite&tab=factures" class="tab-button <?php echo $active_tab == 'factures' ? 'active' : ''; ?>">
                <i class="fas fa-file-invoice"></i>
                Factures
            </a>
            <a href="?page=comptabilite&tab=devis" class="tab-button <?php echo $active_tab == 'devis' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                Devis
            </a>
            <a href="?page=comptabilite&tab=rapports" class="tab-button <?php echo $active_tab == 'rapports' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                Rapports
            </a>
        </div>

        <div class="tab-content">
            <!-- TAB: Transactions -->
            <div class="tab-pane <?php echo $active_tab == 'transactions' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>Transactions</h2>
                    <a href="index.php?page=transaction_create" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Nouvelle transaction
                    </a>
                </div>

                <?php if(count($transactions) == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>Aucune transaction</h3>
                        <p>Commencez par ajouter votre première transaction</p>
                        <a href="index.php?page=transaction_create" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Nouvelle transaction
                        </a>
                    </div>
                <?php else: ?>
                    <div class="transactions-list">
                        <?php foreach($transactions as $trans): ?>
                            <div class="transaction-item">
                                <div class="transaction-icon <?php echo $trans['type']; ?>">
                                    <i class="fas fa-<?php echo ($trans['type'] == 'income') ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <div class="transaction-description"><?php echo htmlspecialchars($trans['description']); ?></div>
                                    <div class="transaction-date">
                                        <i class="far fa-calendar"></i>
                                        <?php echo date('d.m.Y', strtotime($trans['date'])); ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?php echo $trans['type']; ?>">
                                    <?php echo ($trans['type'] == 'income' ? '+' : '-'); ?>
                                    <?php echo number_format($trans['amount'], 2); ?> CHF
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB: Factures -->
            <div class="tab-pane <?php echo $active_tab == 'factures' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>Factures</h2>
                    <a href="index.php?page=invoice_create" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Nouvelle facture
                    </a>
                </div>

                <?php if(count($invoices) == 0): ?>
                    <div class="empty-state">
                        <i class="fas fa-file-invoice"></i>
                        <h3>Aucune facture</h3>
                        <p>Créez votre première facture client</p>
                        <a href="index.php?page=invoice_create" class="btn-primary">
                            <i class="fas fa-plus"></i>
                            Nouvelle facture
                        </a>
                    </div>
                <?php else: ?>
                    <div class="invoices-list">
                        <?php foreach($invoices as $inv): ?>
                            <div class="invoice-card">
                                <div class="invoice-header">
                                    <div class="invoice-number">
                                        <i class="fas fa-file-invoice"></i>
                                        Facture <?php echo htmlspecialchars($inv['invoice_number']); ?>
                                    </div>
                                    <span class="invoice-status <?php echo $inv['status']; ?>">
                                        <?php echo ucfirst($inv['status']); ?>
                                    </span>
                                </div>

                                <div class="invoice-details">
                                    <div class="invoice-detail-item">
                                        <div class="invoice-detail-label">Client</div>
                                        <div class="invoice-detail-value"><?php echo htmlspecialchars($inv['client_name'] ?? 'N/A'); ?></div>
                                    </div>
                                    <div class="invoice-detail-item">
                                        <div class="invoice-detail-label">Date</div>
                                        <div class="invoice-detail-value"><?php echo date('d.m.Y', strtotime($inv['invoice_date'])); ?></div>
                                    </div>
                                    <div class="invoice-detail-item">
                                        <div class="invoice-detail-label">Échéance</div>
                                        <div class="invoice-detail-value"><?php echo date('d.m.Y', strtotime($inv['due_date'])); ?></div>
                                    </div>
                                    <div class="invoice-detail-item">
                                        <div class="invoice-detail-label">Montant</div>
                                        <div class="invoice-detail-value"><?php echo number_format($inv['total_amount'], 2); ?> CHF</div>
                                    </div>
                                </div>

                                <div class="invoice-actions">
                                    <button class="btn-sm btn-view" onclick="viewInvoice(<?php echo $inv['id']; ?>)">
                                        <i class="fas fa-eye"></i>
                                        Voir
                                    </button>
                                    <button class="btn-sm btn-download" onclick="downloadInvoice(<?php echo $inv['id']; ?>)">
                                        <i class="fas fa-download"></i>
                                        PDF
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- TAB: Devis -->
            <div class="tab-pane <?php echo $active_tab == 'devis' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>Devis</h2>
                    <a href="index.php?page=quote_create" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Nouveau devis
                    </a>
                </div>

                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <h3>Module Devis</h3>
                    <p>Le système de gestion des devis est disponible</p>
                    <a href="index.php?page=quote_create" class="btn-primary">
                        <i class="fas fa-plus"></i>
                        Créer un devis
                    </a>
                </div>
            </div>

            <!-- TAB: Rapports -->
            <div class="tab-pane <?php echo $active_tab == 'rapports' ? 'active' : ''; ?>">
                <div class="section-header">
                    <h2>Rapports Financiers</h2>
                </div>

                <div class="stats-mini-grid">
                    <div class="stat-mini-card income">
                        <div class="stat-mini-label"><i class="fas fa-arrow-trend-up"></i> Revenus Totaux</div>
                        <div class="stat-mini-value"><?php echo number_format($stats['total_income'], 2); ?> CHF</div>
                    </div>
                    <div class="stat-mini-card expense">
                        <div class="stat-mini-label"><i class="fas fa-arrow-trend-down"></i> Dépenses Totales</div>
                        <div class="stat-mini-value"><?php echo number_format($stats['total_expenses'], 2); ?> CHF</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label"><i class="fas fa-balance-scale"></i> Bénéfice Net</div>
                        <div class="stat-mini-value"><?php echo number_format($stats['profit'], 2); ?> CHF</div>
                    </div>
                    <div class="stat-mini-card">
                        <div class="stat-mini-label"><i class="fas fa-percent"></i> TVA à Payer</div>
                        <div class="stat-mini-value"><?php echo number_format($stats['total_tva'], 2); ?> CHF</div>
                    </div>
                </div>

                <div style="background: white; padding: 40px; border-radius: 12px; text-align: center;">
                    <i class="fas fa-chart-pie" style="font-size: 4em; color: #cbd5e0; margin-bottom: 20px;"></i>
                    <h3 style="color: #4a5568; margin-bottom: 10px;">Rapports Détaillés</h3>
                    <p style="color: #718096; margin-bottom: 20px;">Les graphiques et rapports détaillés seront disponibles prochainement</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function viewInvoice(id) {
    window.location.href = 'index.php?page=invoice_view&id=' + id;
}

function downloadInvoice(id) {
    window.location.href = 'api/invoice_pdf.php?id=' + id;
}
</script>

</body>
</html>
