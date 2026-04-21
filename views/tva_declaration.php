<?php
/**
 * Vue: Déclaration TVA
 * Description: Génération déclaration TVA suisse
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
    <title>Déclaration TVA</title>
    <style>
        .tva-container {
            padding: 20px;
            max-width: 1200px;
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
        }

        /* Period Selector */
        .period-selector {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .period-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
        }

        .form-group select,
        .form-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .btn-generate {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
        }

        .summary-card.deductible {
            border-left-color: #10b981;
        }

        .summary-card.payable {
            border-left-color: #ef4444;
        }

        .summary-label {
            font-size: 0.9em;
            color: #666;
            margin-bottom: 10px;
        }

        .summary-value {
            font-size: 2em;
            font-weight: bold;
            color: #333;
        }

        /* TVA Table */
        .tva-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            font-weight: 600;
            font-size: 1.1em;
        }

        .tva-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tva-table th,
        .tva-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .tva-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }

        .tva-table tbody tr:hover {
            background: #f8f9ff;
        }

        .amount-cell {
            text-align: right;
            font-weight: 600;
        }

        .total-row {
            background: #f0f4ff !important;
            font-weight: bold;
        }

        .total-row td {
            border-top: 2px solid #667eea;
            border-bottom: 2px solid #667eea;
        }

        /* Actions */
        .actions-bar {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .btn-export {
            padding: 10px 20px;
            background: white;
            border: 2px solid #667eea;
            color: #667eea;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-export:hover {
            background: #667eea;
            color: white;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loading i {
            font-size: 2em;
            animation: spin 1s linear infinite;
        }

        /* Info Box */
        .info-box {
            background: #e0f2fe;
            border-left: 4px solid #0284c7;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .info-box strong {
            color: #075985;
        }
    </style>
</head>
<body>
    <div class="tva-container">
        <div class="page-header">
            <h1><i class="fa-solid fa-percent"></i> Déclaration TVA</h1>
        </div>

        <div class="info-box">
            <strong>Information:</strong> Cette déclaration est générée automatiquement à partir de vos transactions.
            Veuillez vérifier les montants avant de soumettre à l'Administration Fédérale des Contributions (AFC).
        </div>

        <!-- Period Selector -->
        <div class="period-selector">
            <div class="period-grid">
                <div class="form-group">
                    <label>Type de période</label>
                    <select id="periodType" onchange="updatePeriodOptions()">
                        <option value="quarter">Trimestrielle</option>
                        <option value="month">Mensuelle</option>
                        <option value="semester">Semestrielle</option>
                        <option value="year">Annuelle</option>
                        <option value="custom">Personnalisée</option>
                    </select>
                </div>

                <div class="form-group" id="quarterField">
                    <label>Trimestre</label>
                    <select id="quarter">
                        <option value="Q1">T1 (Jan-Mar)</option>
                        <option value="Q2">T2 (Avr-Juin)</option>
                        <option value="Q3">T3 (Juil-Sep)</option>
                        <option value="Q4">T4 (Oct-Déc)</option>
                    </select>
                </div>

                <div class="form-group" id="monthField" style="display:none;">
                    <label>Mois</label>
                    <select id="month">
                        <option value="1">Janvier</option>
                        <option value="2">Février</option>
                        <option value="3">Mars</option>
                        <option value="4">Avril</option>
                        <option value="5">Mai</option>
                        <option value="6">Juin</option>
                        <option value="7">Juillet</option>
                        <option value="8">Août</option>
                        <option value="9">Septembre</option>
                        <option value="10">Octobre</option>
                        <option value="11">Novembre</option>
                        <option value="12">Décembre</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Année</label>
                    <select id="year">
                        <?php
                        $currentYear = date('Y');
                        for ($i = $currentYear; $i >= $currentYear - 5; $i--) {
                            echo "<option value='$i'>$i</option>";
                        }
                        ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>&nbsp;</label>
                    <button class="btn-generate" onclick="generateDeclaration()">
                        <i class="fa-solid fa-calculator"></i> Calculer
                    </button>
                </div>
            </div>

            <div id="customPeriodFields" style="display:none; margin-top:20px;">
                <div class="period-grid">
                    <div class="form-group">
                        <label>Date de début</label>
                        <input type="date" id="dateFrom">
                    </div>
                    <div class="form-group">
                        <label>Date de fin</label>
                        <input type="date" id="dateTo">
                    </div>
                </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid" id="summaryCards" style="display:none;">
            <div class="summary-card">
                <div class="summary-label">TVA collectée (ventes)</div>
                <div class="summary-value" id="tvaCollected">CHF 0.00</div>
            </div>

            <div class="summary-card deductible">
                <div class="summary-label">TVA déductible (achats)</div>
                <div class="summary-value" id="tvaDeductible">CHF 0.00</div>
            </div>

            <div class="summary-card payable">
                <div class="summary-label">TVA à payer / à récupérer</div>
                <div class="summary-value" id="tvaPayable">CHF 0.00</div>
            </div>
        </div>

        <!-- TVA Collected Section -->
        <div class="tva-section" id="collectedSection" style="display:none;">
            <div class="section-header">
                <i class="fa-solid fa-arrow-up"></i> TVA Collectée (Chiffre d'affaires)
            </div>
            <table class="tva-table">
                <thead>
                    <tr>
                        <th>Taux TVA</th>
                        <th>Base HT</th>
                        <th class="amount-cell">Montant TVA</th>
                    </tr>
                </thead>
                <tbody id="collectedTableBody">
                </tbody>
            </table>
        </div>

        <!-- TVA Deductible Section -->
        <div class="tva-section" id="deductibleSection" style="display:none;">
            <div class="section-header">
                <i class="fa-solid fa-arrow-down"></i> TVA Déductible (Achats)
            </div>
            <table class="tva-table">
                <thead>
                    <tr>
                        <th>Taux TVA</th>
                        <th>Base HT</th>
                        <th class="amount-cell">Montant TVA</th>
                    </tr>
                </thead>
                <tbody id="deductibleTableBody">
                </tbody>
            </table>
        </div>

        <!-- Actions -->
        <div class="actions-bar" id="actionsBar" style="display:none;">
            <button class="btn-export" onclick="exportPDF()">
                <i class="fa-solid fa-file-pdf"></i> Export PDF
            </button>
            <button class="btn-export" onclick="exportExcel()">
                <i class="fa-solid fa-file-excel"></i> Export Excel
            </button>
            <button class="btn-generate" onclick="submitDeclaration()">
                <i class="fa-solid fa-paper-plane"></i> Soumettre
            </button>
        </div>
    </div>

    <script>
        function updatePeriodOptions() {
            const type = document.getElementById('periodType').value;

            document.getElementById('quarterField').style.display = type === 'quarter' ? 'flex' : 'none';
            document.getElementById('monthField').style.display = type === 'month' ? 'flex' : 'none';
            document.getElementById('customPeriodFields').style.display = type === 'custom' ? 'block' : 'none';
        }

        function generateDeclaration() {
            // Construire les paramètres de période
            const periodType = document.getElementById('periodType').value;
            const year = document.getElementById('year').value;

            let dateFrom, dateTo;

            if (periodType === 'custom') {
                dateFrom = document.getElementById('dateFrom').value;
                dateTo = document.getElementById('dateTo').value;
            } else {
                const period = calculatePeriod(periodType, year);
                dateFrom = period.from;
                dateTo = period.to;
            }

            // Appeler l'API
            fetch(`assets/ajax/tva_declaration.php?action=calculate&date_from=${dateFrom}&date_to=${dateTo}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayDeclaration(data);
                    } else {
                        alert('Erreur: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Erreur de chargement');
                });
        }

        function calculatePeriod(type, year) {
            let from, to;

            switch (type) {
                case 'quarter':
                    const quarter = document.getElementById('quarter').value;
                    const quarters = {
                        'Q1': {from: `${year}-01-01`, to: `${year}-03-31`},
                        'Q2': {from: `${year}-04-01`, to: `${year}-06-30`},
                        'Q3': {from: `${year}-07-01`, to: `${year}-09-30`},
                        'Q4': {from: `${year}-10-01`, to: `${year}-12-31`}
                    };
                    return quarters[quarter];

                case 'month':
                    const month = document.getElementById('month').value.padStart(2, '0');
                    const lastDay = new Date(year, month, 0).getDate();
                    return {
                        from: `${year}-${month}-01`,
                        to: `${year}-${month}-${lastDay}`
                    };

                case 'year':
                    return {
                        from: `${year}-01-01`,
                        to: `${year}-12-31`
                    };

                default:
                    return {from: '', to: ''};
            }
        }

        function displayDeclaration(data) {
            // Afficher les cartes de résumé
            document.getElementById('summaryCards').style.display = 'grid';
            document.getElementById('tvaCollected').textContent = 'CHF ' + parseFloat(data.summary.collected).toFixed(2);
            document.getElementById('tvaDeductible').textContent = 'CHF ' + parseFloat(data.summary.deductible).toFixed(2);
            document.getElementById('tvaPayable').textContent = 'CHF ' + parseFloat(data.summary.payable).toFixed(2);

            // Afficher les tableaux
            displayRatesTable('collectedTableBody', data.collected_by_rate);
            displayRatesTable('deductibleTableBody', data.deductible_by_rate);

            document.getElementById('collectedSection').style.display = 'block';
            document.getElementById('deductibleSection').style.display = 'block';
            document.getElementById('actionsBar').style.display = 'flex';
        }

        function displayRatesTable(tableId, rates) {
            const tbody = document.getElementById(tableId);
            let html = '';
            let totalBase = 0;
            let totalTVA = 0;

            rates.forEach(rate => {
                totalBase += parseFloat(rate.base_amount);
                totalTVA += parseFloat(rate.tva_amount);

                html += `
                    <tr>
                        <td>${parseFloat(rate.rate).toFixed(2)}%</td>
                        <td>CHF ${parseFloat(rate.base_amount).toFixed(2)}</td>
                        <td class="amount-cell">CHF ${parseFloat(rate.tva_amount).toFixed(2)}</td>
                    </tr>
                `;
            });

            // Ligne de total
            html += `
                <tr class="total-row">
                    <td><strong>TOTAL</strong></td>
                    <td><strong>CHF ${totalBase.toFixed(2)}</strong></td>
                    <td class="amount-cell"><strong>CHF ${totalTVA.toFixed(2)}</strong></td>
                </tr>
            `;

            tbody.innerHTML = html;
        }

        function exportPDF() {
            alert('Export PDF en développement');
        }

        function exportExcel() {
            alert('Export Excel en développement');
        }

        function submitDeclaration() {
            if (confirm('Voulez-vous vraiment soumettre cette déclaration TVA ?')) {
                alert('Fonctionnalité de soumission en développement');
            }
        }
    </script>
</body>
</html>
