<?php
/**
 * Page: Tableau de Bord / Accueil
 * Version: 3.0 - Refonte complète
 * Description: Dashboard moderne avec statistiques et activité récente
 */

// Inclure les modèles nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/Company.php';
include_once dirname(__DIR__) . '/models/Transaction.php';

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

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    redirect('index.php?page=login');
}

// Récupérer les sociétés de l'utilisateur
$company = new Company($db);
$companies_stmt = $company->readByUser($_SESSION['user_id']);
$companies = [];
while ($row = $companies_stmt->fetch(PDO::FETCH_ASSOC)) {
    $companies[] = $row;
}

// Vérifier si l'utilisateur a des sociétés
if (count($companies) == 0) {
    // Ce cas ne devrait plus arriver car index.php redirige maintenant avant
    $stats = ['total_income' => 0, 'total_expenses' => 0, 'profit' => 0, 'total_tva' => 0];
    $recent_transactions = [];
    $company_id = null;
} else {
    // Récupérer l'ID de la société actuellement sélectionnée
    $company_id = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;

    // Si aucune société n'est sélectionnée, prendre la première
    if (!$company_id || !in_array($company_id, array_column($companies, 'id'))) {
        $company_id = $companies[0]['id'];
        $_SESSION['company_id'] = $company_id;
    }

    // Charger les statistiques
    $transaction = new Transaction($db);
    $stats = $transaction->getStatistics($company_id);

    // Charger les transactions récentes
    $recent_transactions_stmt = $transaction->getRecentByCompany($company_id, 10);
    $recent_transactions = [];
    while ($row = $recent_transactions_stmt->fetch(PDO::FETCH_ASSOC)) {
        $recent_transactions[] = $row;
    }
}

$page_title = "Tableau de bord";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Gestion Comptable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/home.css">
    <link rel="stylesheet" href="assets/css/overdue_alerts.css">
    <style>
        /* Styles intégrés pour le dashboard */
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header */
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .dashboard-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.2em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .dashboard-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.05em;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.income::before {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-card.expense::before {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        .stat-card.profit::before {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.tva::before {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .stat-label {
            font-size: 0.95em;
            color: #718096;
            font-weight: 500;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-label i {
            font-size: 1.2em;
        }

        .stat-value {
            font-size: 2em;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .stat-value.positive {
            color: #38ef7d;
        }

        .stat-value.negative {
            color: #f45c43;
        }

        .stat-value.neutral {
            color: #667eea;
        }

        /* Recent Activity */
        .activity-section {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            padding: 25px;
            margin-bottom: 30px;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f7fafc;
        }

        .activity-header h2 {
            margin: 0;
            font-size: 1.5em;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-header a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .activity-header a:hover {
            color: #764ba2;
        }

        /* Transaction List */
        .transaction-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .transaction-item {
            display: flex;
            align-items: center;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            transition: background 0.2s, transform 0.2s;
        }

        .transaction-item:hover {
            background: #edf2f7;
            transform: translateX(5px);
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
            min-width: 0;
        }

        .transaction-description {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .transaction-date {
            font-size: 0.85em;
            color: #718096;
        }

        .transaction-amount {
            font-size: 1.1em;
            font-weight: 700;
            white-space: nowrap;
        }

        .transaction-amount.income {
            color: #38a169;
        }

        .transaction-amount.expense {
            color: #e53e3e;
        }

        /* Quick Links */
        .quick-links {
            margin-bottom: 30px;
        }

        .quick-links h2 {
            margin-bottom: 20px;
            color: #2d3748;
            font-size: 1.5em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .link-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            text-decoration: none;
            color: inherit;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .link-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .link-card-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto 15px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            color: white;
        }

        .link-card:nth-child(1) .link-card-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .link-card:nth-child(2) .link-card-icon {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .link-card:nth-child(3) .link-card-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .link-card:nth-child(4) .link-card-icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
        }

        .link-card h3 {
            margin: 0 0 8px 0;
            font-size: 1.1em;
            color: #2d3748;
        }

        .link-card p {
            margin: 0;
            font-size: 0.9em;
            color: #718096;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
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
            color: #718096;
        }

        .btn-primary {
            display: inline-block;
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header h1 {
                font-size: 1.6em;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .transaction-item {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .transaction-icon {
                margin: 0;
            }

            .links-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid,
            .links-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header">
        <h1><i class="fas fa-chart-line"></i> Tableau de bord</h1>
        <p>Vue d'ensemble de votre activité comptable</p>
    </div>

    <?php if (count($companies) == 0): ?>
        <!-- No Companies State -->
        <div class="empty-state">
            <i class="fas fa-building"></i>
            <h3>Bienvenue dans Gestion Comptable!</h3>
            <p>Pour commencer, veuillez créer votre première société.</p>
            <a href="index.php?page=parametres" class="btn-primary">
                <i class="fas fa-plus"></i> Créer une société
            </a>
        </div>

    <?php else: ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card income">
                <div class="stat-label">
                    <i class="fas fa-arrow-up"></i>
                    Total des revenus
                </div>
                <p class="stat-value positive"><?php echo number_format($stats['total_income'], 2); ?> CHF</p>
            </div>

            <div class="stat-card expense">
                <div class="stat-label">
                    <i class="fas fa-arrow-down"></i>
                    Total des dépenses
                </div>
                <p class="stat-value negative"><?php echo number_format($stats['total_expenses'], 2); ?> CHF</p>
            </div>

            <div class="stat-card profit">
                <div class="stat-label">
                    <i class="fas fa-balance-scale"></i>
                    Bénéfice
                </div>
                <p class="stat-value neutral"><?php echo number_format($stats['profit'], 2); ?> CHF</p>
            </div>

            <div class="stat-card tva">
                <div class="stat-label">
                    <i class="fas fa-percent"></i>
                    TVA à payer
                </div>
                <p class="stat-value"><?php echo number_format($stats['total_tva'], 2); ?> CHF</p>
            </div>
        </div>

        <!-- Overdue Invoices Alerts -->
        <div id="overdueAlertsContainer"></div>

        <!-- Recent Activity -->
        <div class="activity-section">
            <div class="activity-header">
                <h2><i class="fas fa-history"></i> Activité récente</h2>
                <a href="index.php?page=comptabilite">Voir tout <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if(count($recent_transactions) == 0): ?>
                <div class="empty-state" style="padding: 40px 20px;">
                    <i class="fas fa-inbox" style="font-size: 3em;"></i>
                    <h3>Aucune transaction enregistrée</h3>
                    <p>Commencez par ajouter votre première transaction</p>
                    <a href="index.php?page=comptabilite" class="btn-primary">
                        <i class="fas fa-plus"></i> Nouvelle transaction
                    </a>
                </div>
            <?php else: ?>
                <div class="transaction-list">
                    <?php foreach($recent_transactions as $trans): ?>
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

        <!-- Quick Links -->
        <div class="quick-links">
            <h2><i class="fas fa-bolt"></i> Accès rapides</h2>
            <div class="links-grid">
                <a href="index.php?page=comptabilite" class="link-card">
                    <div class="link-card-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h3>Transactions</h3>
                    <p>Gérer vos revenus et dépenses</p>
                </a>

                <a href="index.php?page=comptabilite&tab=factures" class="link-card">
                    <div class="link-card-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h3>Factures</h3>
                    <p>Créer et gérer vos factures</p>
                </a>

                <a href="index.php?page=adresses" class="link-card">
                    <div class="link-card-icon">
                        <i class="fas fa-address-book"></i>
                    </div>
                    <h3>Contacts</h3>
                    <p>Gérer vos clients et fournisseurs</p>
                </a>

                <a href="index.php?page=parametres" class="link-card">
                    <div class="link-card-icon">
                        <i class="fas fa-cog"></i>
                    </div>
                    <h3>Paramètres</h3>
                    <p>Configurer votre application</p>
                </a>
            </div>
        </div>

    <?php endif; ?>
</div>

<script src="assets/js/overdue_alerts.js"></script>

</body>
</html>
