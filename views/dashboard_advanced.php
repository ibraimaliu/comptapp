<?php
/**
 * Vue: Tableau de Bord Avancé
 * Description: Dashboard avec graphiques et analytiques détaillées
 */

// Vérifier l'accès
if (!isset($_SESSION['company_id'])) {
    redirect('index.php?page=login');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tableau de Bord Avancé</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .dashboard-advanced {
            padding: 20px;
            max-width: 1600px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .page-header h1 {
            font-size: 2em;
            color: #333;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .period-selector {
            display: flex;
            gap: 10px;
        }

        .period-btn {
            padding: 8px 16px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .period-btn.active {
            background: #667eea;
            color: white;
        }

        .period-btn:hover {
            background: #667eea;
            color: white;
        }

        /* KPI Cards */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .kpi-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .kpi-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .kpi-card.income::before {
            background: linear-gradient(90deg, #11998e, #38ef7d);
        }

        .kpi-card.expense::before {
            background: linear-gradient(90deg, #f093fb, #f5576c);
        }

        .kpi-card.profit::before {
            background: linear-gradient(90deg, #4facfe, #00f2fe);
        }

        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .kpi-title {
            font-size: 0.9em;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
        }

        .kpi-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2em;
        }

        .kpi-card.income .kpi-icon {
            background: #c6f6d5;
            color: #22543d;
        }

        .kpi-card.expense .kpi-icon {
            background: #fed7d7;
            color: #742a2a;
        }

        .kpi-card.profit .kpi-icon {
            background: #bee3f8;
            color: #2c5282;
        }

        .kpi-value {
            font-size: 2.2em;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }

        .kpi-variation {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .kpi-variation.positive {
            background: #c6f6d5;
            color: #22543d;
        }

        .kpi-variation.negative {
            background: #fed7d7;
            color: #742a2a;
        }

        /* Chart Sections */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .chart-card.full-width {
            grid-column: 1 / -1;
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .chart-header h3 {
            margin: 0;
            font-size: 1.3em;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            position: relative;
            height: 350px;
        }

        .chart-container.small {
            height: 300px;
        }

        /* Rankings */
        .rankings-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .ranking-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .ranking-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .ranking-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .ranking-position {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.9em;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            margin-right: 15px;
        }

        .ranking-info {
            flex: 1;
            min-width: 0;
        }

        .ranking-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ranking-count {
            font-size: 0.85em;
            color: #666;
        }

        .ranking-amount {
            font-size: 1.2em;
            font-weight: bold;
            color: #667eea;
            white-space: nowrap;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
        }

        .loading i {
            font-size: 2em;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .kpi-grid,
            .rankings-grid {
                grid-template-columns: 1fr;
            }

            .period-selector {
                flex-wrap: wrap;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-advanced">
        <div class="page-header">
            <h1><i class="fa-solid fa-chart-line"></i> Tableau de Bord Avancé</h1>
            <div class="period-selector">
                <button class="period-btn" onclick="changePeriod(7)">7 jours</button>
                <button class="period-btn active" onclick="changePeriod(30)">30 jours</button>
                <button class="period-btn" onclick="changePeriod(90)">90 jours</button>
                <button class="period-btn" onclick="changePeriod(365)">1 an</button>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="kpi-grid" id="kpiGrid">
            <div class="kpi-card income">
                <div class="kpi-header">
                    <span class="kpi-title">Revenus</span>
                    <div class="kpi-icon"><i class="fa-solid fa-arrow-trend-up"></i></div>
                </div>
                <div class="kpi-value" id="kpiIncome">CHF 0.00</div>
                <span class="kpi-variation positive" id="kpiIncomeVariation">
                    <i class="fa-solid fa-arrow-up"></i> 0%
                </span>
            </div>

            <div class="kpi-card expense">
                <div class="kpi-header">
                    <span class="kpi-title">Dépenses</span>
                    <div class="kpi-icon"><i class="fa-solid fa-arrow-trend-down"></i></div>
                </div>
                <div class="kpi-value" id="kpiExpense">CHF 0.00</div>
                <span class="kpi-variation negative" id="kpiExpenseVariation">
                    <i class="fa-solid fa-arrow-up"></i> 0%
                </span>
            </div>

            <div class="kpi-card profit">
                <div class="kpi-header">
                    <span class="kpi-title">Bénéfice</span>
                    <div class="kpi-icon"><i class="fa-solid fa-coins"></i></div>
                </div>
                <div class="kpi-value" id="kpiProfit">CHF 0.00</div>
                <span class="kpi-variation positive" id="kpiProfitVariation">
                    <i class="fa-solid fa-arrow-up"></i> 0%
                </span>
            </div>

            <div class="kpi-card">
                <div class="kpi-header">
                    <span class="kpi-title">Factures impayées</span>
                    <div class="kpi-icon" style="background: #feebc8; color: #7c2d12;">
                        <i class="fa-solid fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="kpi-value" id="kpiUnpaidCount">0</div>
                <span style="font-size: 0.9em; color: #666;" id="kpiUnpaidAmount">CHF 0.00</span>
            </div>
        </div>

        <!-- Evolution Chart -->
        <div class="charts-grid">
            <div class="chart-card full-width">
                <div class="chart-header">
                    <h3><i class="fa-solid fa-chart-area"></i> Évolution Revenus & Dépenses</h3>
                </div>
                <div class="chart-container">
                    <canvas id="evolutionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Categories & Cash Flow -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fa-solid fa-chart-pie"></i> Dépenses par Catégorie</h3>
                </div>
                <div class="chart-container small">
                    <canvas id="categoriesChart"></canvas>
                </div>
            </div>

            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fa-solid fa-chart-line"></i> Flux de Trésorerie</h3>
                </div>
                <div class="chart-container small">
                    <canvas id="cashFlowChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Top Rankings -->
        <div class="rankings-grid">
            <div class="ranking-card">
                <div class="chart-header">
                    <h3><i class="fa-solid fa-trophy"></i> Top Clients</h3>
                </div>
                <div id="topClientsRanking">
                    <div class="loading"><i class="fa-solid fa-spinner"></i></div>
                </div>
            </div>

            <div class="ranking-card">
                <div class="chart-header">
                    <h3><i class="fa-solid fa-star"></i> Top Fournisseurs</h3>
                </div>
                <div id="topSuppliersRanking">
                    <div class="loading"><i class="fa-solid fa-spinner"></i></div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/dashboard_advanced.js"></script>
</body>
</html>
