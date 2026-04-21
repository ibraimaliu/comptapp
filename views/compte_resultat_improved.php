<?php
/**
 * Compte de Résultat (Income Statement / Profit & Loss)
 * Affiche les produits et charges sur une période donnée
 */

// Vérifier si l'utilisateur est connecté
if(!isLoggedIn()) {
    redirect('index.php?page=login');
}

// Vérifier qu'une société est sélectionnée
if(!hasActiveCompany()) {
    redirect('index.php?page=society_setup');
}

require_once 'config/database.php';
require_once 'models/Report.php';

$database = new Database();
$db = $database->getConnection();

$company_id = getActiveCompanyId();

// Paramètres de période
$date_start = $_GET['date_start'] ?? date('Y-01-01'); // Début de l'année par défaut
$date_end = $_GET['date_end'] ?? date('Y-m-d');

// Générer le compte de résultat
$report = new Report($db);
$compte_resultat = $report->getIncomeStatement($company_id, $date_start, $date_end);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte de Résultat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .compte-resultat-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #e74c3c;
        }

        .page-header h1 {
            margin: 0;
            color: #2c3e50;
        }

        .date-filter {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .date-filter form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #34495e;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background-color: #e74c3c;
            color: white;
        }

        .btn-primary:hover {
            background-color: #c0392b;
        }

        .btn-success {
            background-color: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        .resultat-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 30px;
            text-align: center;
        }

        .resultat-card.benefice {
            border-left: 5px solid #27ae60;
        }

        .resultat-card.perte {
            border-left: 5px solid #e74c3c;
        }

        .resultat-card .icon {
            font-size: 64px;
            margin-bottom: 15px;
        }

        .resultat-card.benefice .icon {
            color: #27ae60;
        }

        .resultat-card.perte .icon {
            color: #e74c3c;
        }

        .resultat-card h2 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .resultat-card .amount {
            font-size: 36px;
            font-weight: bold;
            margin: 15px 0;
        }

        .resultat-card.benefice .amount {
            color: #27ae60;
        }

        .resultat-card.perte .amount {
            color: #e74c3c;
        }

        .resultat-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .section-header {
            padding: 15px 20px;
            font-size: 18px;
            font-weight: bold;
            color: white;
        }

        .section-header.produits {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .section-header.charges {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
        }

        .resultat-table {
            width: 100%;
        }

        .resultat-table tr {
            border-bottom: 1px solid #ecf0f1;
        }

        .resultat-table tr:hover {
            background-color: #f8f9fa;
        }

        .resultat-table td {
            padding: 12px 20px;
        }

        .account-number {
            font-weight: 600;
            color: #7f8c8d;
            width: 80px;
        }

        .account-name {
            color: #2c3e50;
        }

        .account-amount {
            text-align: right;
            font-weight: 600;
            white-space: nowrap;
        }

        .produits .account-amount {
            color: #27ae60;
        }

        .charges .account-amount {
            color: #e74c3c;
        }

        .total-row {
            background-color: #34495e;
            color: white;
            font-size: 16px;
            font-weight: bold;
        }

        .total-row td {
            padding: 15px 20px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .kpi-card .kpi-label {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .kpi-card .kpi-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }

        @media print {
            .date-filter, .action-buttons, .btn {
                display: none !important;
            }

            .compte-resultat-container {
                max-width: 100%;
            }
        }

        .empty-state {
            padding: 40px;
            text-align: center;
            color: #95a5a6;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="compte-resultat-container">
        <div class="page-header">
            <h1><i class="fas fa-chart-line"></i> Compte de Résultat</h1>
            <div class="action-buttons">
                <button class="btn btn-success" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Exporter en PDF
                </button>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>

        <!-- Filtres de période -->
        <div class="date-filter">
            <form method="GET" action="">
                <input type="hidden" name="page" value="compte_resultat">
                <div class="form-group">
                    <label for="date_start">Date de début *</label>
                    <input type="date" id="date_start" name="date_start" class="form-control" value="<?php echo htmlspecialchars($date_start); ?>" required>
                </div>
                <div class="form-group">
                    <label for="date_end">Date de fin *</label>
                    <input type="date" id="date_end" name="date_end" class="form-control" value="<?php echo htmlspecialchars($date_end); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
            </form>
        </div>

        <!-- Résultat Net -->
        <div class="resultat-card <?php echo $compte_resultat['resultat_type']; ?>">
            <div class="icon">
                <i class="fas fa-<?php echo $compte_resultat['resultat_type'] == 'benefice' ? 'arrow-trend-up' : 'arrow-trend-down'; ?>"></i>
            </div>
            <h2>
                <?php echo $compte_resultat['resultat_type'] == 'benefice' ? 'Bénéfice Net' : 'Perte Nette'; ?>
            </h2>
            <div class="amount">
                <?php echo number_format(abs($compte_resultat['resultat_net']), 2, '.', ' '); ?> CHF
            </div>
            <p>
                Sur la période du <?php echo date('d/m/Y', strtotime($date_start)); ?>
                au <?php echo date('d/m/Y', strtotime($date_end)); ?>
            </p>
        </div>

        <!-- KPIs -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-label">Total Produits</div>
                <div class="kpi-value" style="color: #27ae60;">
                    <?php echo number_format($compte_resultat['total_produits'], 2, '.', ' '); ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Total Charges</div>
                <div class="kpi-value" style="color: #e74c3c;">
                    <?php echo number_format($compte_resultat['total_charges'], 2, '.', ' '); ?>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Marge</div>
                <div class="kpi-value">
                    <?php
                    $marge_pct = $compte_resultat['total_produits'] > 0
                        ? ($compte_resultat['resultat_net'] / $compte_resultat['total_produits']) * 100
                        : 0;
                    echo number_format($marge_pct, 2);
                    ?>%
                </div>
            </div>
        </div>

        <!-- Produits -->
        <div class="resultat-section">
            <div class="section-header produits">
                <i class="fas fa-arrow-up"></i> PRODUITS
            </div>
            <table class="resultat-table produits">
                <tbody>
                    <?php if (count($compte_resultat['produits']) > 0): ?>
                        <?php foreach ($compte_resultat['produits'] as $account): ?>
                            <tr>
                                <td class="account-number"><?php echo htmlspecialchars($account['number']); ?></td>
                                <td class="account-name"><?php echo htmlspecialchars($account['name']); ?></td>
                                <td class="account-amount"><?php echo number_format($account['balance'], 2, '.', ' '); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="2">TOTAL PRODUITS</td>
                            <td class="account-amount"><?php echo number_format($compte_resultat['total_produits'], 2, '.', ' '); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Aucun produit sur cette période</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Charges -->
        <div class="resultat-section">
            <div class="section-header charges">
                <i class="fas fa-arrow-down"></i> CHARGES
            </div>
            <table class="resultat-table charges">
                <tbody>
                    <?php if (count($compte_resultat['charges']) > 0): ?>
                        <?php foreach ($compte_resultat['charges'] as $account): ?>
                            <tr>
                                <td class="account-number"><?php echo htmlspecialchars($account['number']); ?></td>
                                <td class="account-name"><?php echo htmlspecialchars($account['name']); ?></td>
                                <td class="account-amount"><?php echo number_format($account['balance'], 2, '.', ' '); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="2">TOTAL CHARGES</td>
                            <td class="account-amount"><?php echo number_format($compte_resultat['total_charges'], 2, '.', ' '); ?></td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <p>Aucune charge sur cette période</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function exportPDF() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.location.href = 'assets/ajax/export_compte_resultat_pdf.php?' + params.toString();
        }
    </script>
</body>
</html>
