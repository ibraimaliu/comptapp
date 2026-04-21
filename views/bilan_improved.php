<?php
/**
 * Bilan Comptable (Balance Sheet)
 * Affiche l'actif et le passif à une date donnée
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

// Paramètres de date
$date_start = $_GET['date_start'] ?? null;
$date_end = $_GET['date_end'] ?? date('Y-m-d');

// Générer le bilan
$report = new Report($db);
$bilan = $report->getBalanceSheet($company_id, $date_start, $date_end);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilan Comptable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .bilan-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #3498db;
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
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-success {
            background-color: #27ae60;
            color: white;
        }

        .btn-success:hover {
            background-color: #229954;
        }

        .bilan-tables {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .bilan-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 15px 20px;
            font-size: 18px;
            font-weight: bold;
            color: white;
        }

        .section-header.actif {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .section-header.passif {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .bilan-table {
            width: 100%;
        }

        .bilan-table tr {
            border-bottom: 1px solid #ecf0f1;
        }

        .bilan-table tr:hover {
            background-color: #f8f9fa;
        }

        .bilan-table td {
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
            color: #27ae60;
            white-space: nowrap;
        }

        .subtotal-row {
            background-color: #ecf0f1;
            font-weight: bold;
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

        .equilibre-check {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .equilibre-check.balanced {
            border-left: 5px solid #27ae60;
        }

        .equilibre-check.unbalanced {
            border-left: 5px solid #e74c3c;
        }

        .equilibre-check h3 {
            margin: 0 0 10px 0;
        }

        .equilibre-check .icon {
            font-size: 48px;
            margin-bottom: 10px;
        }

        .equilibre-check.balanced .icon {
            color: #27ae60;
        }

        .equilibre-check.unbalanced .icon {
            color: #e74c3c;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        @media print {
            .date-filter, .action-buttons, .btn {
                display: none !important;
            }

            .bilan-container {
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
    <div class="bilan-container">
        <div class="page-header">
            <h1><i class="fas fa-balance-scale"></i> Bilan Comptable</h1>
            <div class="action-buttons">
                <button class="btn btn-success" onclick="exportPDF()">
                    <i class="fas fa-file-pdf"></i> Exporter en PDF
                </button>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print"></i> Imprimer
                </button>
            </div>
        </div>

        <!-- Filtres de date -->
        <div class="date-filter">
            <form method="GET" action="">
                <input type="hidden" name="page" value="bilan">
                <div class="form-group">
                    <label for="date_start">Date de début (optionnel)</label>
                    <input type="date" id="date_start" name="date_start" class="form-control" value="<?php echo htmlspecialchars($date_start ?? ''); ?>">
                    <small>Laisser vide pour tout l'historique</small>
                </div>
                <div class="form-group">
                    <label for="date_end">Date de clôture *</label>
                    <input type="date" id="date_end" name="date_end" class="form-control" value="<?php echo htmlspecialchars($date_end); ?>" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
            </form>
        </div>

        <!-- Vérification de l'équilibre -->
        <div class="equilibre-check <?php echo $bilan['equilibre'] ? 'balanced' : 'unbalanced'; ?>">
            <div class="icon">
                <i class="fas fa-<?php echo $bilan['equilibre'] ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            </div>
            <h3>
                <?php if ($bilan['equilibre']): ?>
                    Bilan équilibré
                <?php else: ?>
                    Bilan non équilibré
                <?php endif; ?>
            </h3>
            <p>
                <?php if ($bilan['equilibre']): ?>
                    L'actif et le passif sont égaux. Le bilan est correct.
                <?php else: ?>
                    Différence de <?php echo number_format(abs($bilan['difference']), 2, '.', ' '); ?> CHF
                    <?php echo $bilan['difference'] > 0 ? '(Actif > Passif)' : '(Passif > Actif)'; ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Tables Actif et Passif -->
        <div class="bilan-tables">
            <!-- ACTIF -->
            <div class="bilan-section">
                <div class="section-header actif">
                    <i class="fas fa-coins"></i> ACTIF
                </div>
                <table class="bilan-table">
                    <tbody>
                        <?php if (count($bilan['actif']) > 0): ?>
                            <?php foreach ($bilan['actif'] as $account): ?>
                                <?php if ($account['balance'] != 0): ?>
                                    <tr>
                                        <td class="account-number"><?php echo htmlspecialchars($account['number']); ?></td>
                                        <td class="account-name"><?php echo htmlspecialchars($account['name']); ?></td>
                                        <td class="account-amount"><?php echo number_format($account['balance'], 2, '.', ' '); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2">TOTAL ACTIF</td>
                                <td class="account-amount"><?php echo number_format($bilan['total_actif'], 2, '.', ' '); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>Aucun compte d'actif</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- PASSIF -->
            <div class="bilan-section">
                <div class="section-header passif">
                    <i class="fas fa-hand-holding-usd"></i> PASSIF
                </div>
                <table class="bilan-table">
                    <tbody>
                        <?php if (count($bilan['passif']) > 0): ?>
                            <?php foreach ($bilan['passif'] as $account): ?>
                                <?php if ($account['balance'] != 0): ?>
                                    <tr>
                                        <td class="account-number"><?php echo htmlspecialchars($account['number']); ?></td>
                                        <td class="account-name"><?php echo htmlspecialchars($account['name']); ?></td>
                                        <td class="account-amount"><?php echo number_format($account['balance'], 2, '.', ' '); ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td colspan="2">TOTAL PASSIF</td>
                                <td class="account-amount"><?php echo number_format($bilan['total_passif'], 2, '.', ' '); ?></td>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="empty-state">
                                    <i class="fas fa-inbox"></i>
                                    <p>Aucun compte de passif</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Informations de période -->
        <div style="text-align: center; color: #7f8c8d; margin-top: 20px;">
            <p>
                <strong>Période:</strong>
                <?php if ($bilan['date_start']): ?>
                    Du <?php echo date('d/m/Y', strtotime($bilan['date_start'])); ?>
                <?php else: ?>
                    Depuis le début
                <?php endif; ?>
                au <?php echo date('d/m/Y', strtotime($bilan['date_end'])); ?>
            </p>
        </div>
    </div>

    <script>
        function exportPDF() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'pdf');
            window.location.href = 'assets/ajax/export_bilan_pdf.php?' + params.toString();
        }
    </script>
</body>
</html>
