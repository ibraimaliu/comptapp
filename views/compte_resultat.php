<?php
// Vérifier si l'utilisateur est connecté
if(!isLoggedIn()) {
    redirect('index.php?page=login');
}

// Vérifier qu'une société est sélectionnée
if(!hasActiveCompany()) {
    redirect('index.php?page=society_setup');
}

// Récupérer les informations de la société
require_once 'config/database.php';
require_once 'models/AccountingPlan.php';
require_once 'models/Transaction.php';

$database = new Database();
$db = $database->getConnection();

$company_id = getActiveCompanyId();

// Période par défaut : année en cours
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-01-01');
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : date('Y-m-d');

// Récupérer tous les comptes de type Résultat
$query_accounts = "SELECT * FROM accounting_plan
                   WHERE company_id = :company_id
                     AND type = 'Résultat'
                   ORDER BY category, number ASC";

$stmt = $db->prepare($query_accounts);
$stmt->bindParam(':company_id', $company_id);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Structures pour stocker les données
$produits_data = [];
$charges_data = [];

$total_produits = 0;
$total_charges = 0;

foreach ($accounts as $account) {
    // Calculer le solde du compte sur la période
    $query_solde = "SELECT
                        SUM(CASE WHEN account_id = :account_id THEN amount ELSE 0 END) -
                        SUM(CASE WHEN counterpart_account_id = :account_id THEN amount ELSE 0 END) as solde
                    FROM transactions
                    WHERE (account_id = :account_id OR counterpart_account_id = :account_id)
                      AND company_id = :company_id
                      AND date >= :date_debut
                      AND date <= :date_fin";

    $stmt_solde = $db->prepare($query_solde);
    $stmt_solde->bindParam(':account_id', $account['id']);
    $stmt_solde->bindParam(':company_id', $company_id);
    $stmt_solde->bindParam(':date_debut', $date_debut);
    $stmt_solde->bindParam(':date_fin', $date_fin);
    $stmt_solde->execute();

    $result = $stmt_solde->fetch(PDO::FETCH_ASSOC);
    $solde = floatval($result['solde'] ?? 0);

    // Ne garder que les comptes avec un solde significatif
    if (abs($solde) > 0.01) {
        $account_with_solde = $account;
        $account_with_solde['solde'] = $solde;

        // Classer selon la catégorie
        if ($account['category'] == 'Produit') {
            $produits_data[] = $account_with_solde;
            $total_produits += abs($solde);
        } elseif ($account['category'] == 'Charge') {
            $charges_data[] = $account_with_solde;
            $total_charges += abs($solde);
        }
    }
}

// Calculer le résultat (bénéfice ou perte)
$resultat = $total_produits - $total_charges;
$is_benefice = $resultat >= 0;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte de Résultat (Pertes et Profits)</title>
    <style>
        .compte-resultat-container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
        }

        .filters-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            margin-bottom: 5px;
            font-weight: 500;
            color: #333;
        }

        .form-control {
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
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
        }

        .resultat-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .resultat-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .section-header {
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
            color: white;
        }

        .section-header.produits {
            background: linear-gradient(135deg, #48bb78 0%, #2f855a 100%);
        }

        .section-header.charges {
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
        }

        .section-body {
            padding: 0;
        }

        .account-line {
            display: flex;
            justify-content: space-between;
            padding: 12px 20px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        .account-line:hover {
            background: #f7fafc;
        }

        .account-name {
            color: #4a5568;
        }

        .account-number {
            color: #718096;
            margin-right: 8px;
            font-family: monospace;
        }

        .account-amount {
            font-weight: 500;
            color: #2d3748;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 15px 20px;
            font-weight: 700;
            font-size: 16px;
        }

        .total-line.produits {
            background: #f0fff4;
            color: #22543d;
        }

        .total-line.charges {
            background: #fff5f5;
            color: #742a2a;
        }

        .resultat-final-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .resultat-header {
            padding: 20px;
            font-size: 18px;
            font-weight: 600;
            color: white;
        }

        .resultat-header.benefice {
            background: linear-gradient(135deg, #48bb78 0%, #2f855a 100%);
        }

        .resultat-header.perte {
            background: linear-gradient(135deg, #f56565 0%, #c53030 100%);
        }

        .resultat-body {
            padding: 30px;
        }

        .resultat-breakdown {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .breakdown-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            text-align: center;
        }

        .breakdown-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .breakdown-value {
            font-size: 24px;
            font-weight: 700;
        }

        .breakdown-value.produits {
            color: #48bb78;
        }

        .breakdown-value.charges {
            color: #f56565;
        }

        .resultat-total {
            padding: 20px;
            background: #edf2f7;
            border-radius: 8px;
            text-align: center;
        }

        .resultat-label {
            font-size: 14px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .resultat-value {
            font-size: 36px;
            font-weight: 700;
        }

        .resultat-value.benefice {
            color: #48bb78;
        }

        .resultat-value.perte {
            color: #f56565;
        }

        .no-data {
            padding: 40px 20px;
            text-align: center;
            color: #718096;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-icon {
            font-size: 32px;
            margin-bottom: 10px;
            opacity: 0.7;
        }

        @media print {
            .filters-card, .btn {
                display: none;
            }

            .page-header {
                background: #667eea;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .resultat-grid {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 968px) {
            .resultat-grid {
                grid-template-columns: 1fr;
            }

            .resultat-breakdown {
                grid-template-columns: 1fr;
            }

            .stats-cards {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="compte-resultat-container">
        <div class="page-header">
            <h1><i class="fa-solid fa-chart-bar"></i> Compte de Résultat (Pertes et Profits)</h1>
            <p>Analyse des performances financières de l'entreprise sur une période donnée</p>
        </div>

        <!-- Filtres -->
        <div class="filters-card">
            <form method="GET" action="index.php">
                <input type="hidden" name="page" value="compte_resultat">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="date_debut">Date début</label>
                        <input type="date" name="date_debut" id="date_debut" class="form-control"
                               value="<?php echo htmlspecialchars($date_debut); ?>">
                    </div>

                    <div class="form-group">
                        <label for="date_fin">Date fin</label>
                        <input type="date" name="date_fin" id="date_fin" class="form-control"
                               value="<?php echo htmlspecialchars($date_fin); ?>">
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-calculator"></i> Calculer le résultat
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Statistiques rapides -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-icon" style="color: #48bb78;">
                    <i class="fa-solid fa-arrow-trend-up"></i>
                </div>
                <div class="stat-label">Total Produits</div>
                <div class="stat-value" style="color: #48bb78;">
                    <?php echo number_format($total_produits, 2, '.', ' '); ?> CHF
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: #f56565;">
                    <i class="fa-solid fa-arrow-trend-down"></i>
                </div>
                <div class="stat-label">Total Charges</div>
                <div class="stat-value" style="color: #f56565;">
                    <?php echo number_format($total_charges, 2, '.', ' '); ?> CHF
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="color: <?php echo $is_benefice ? '#48bb78' : '#f56565'; ?>;">
                    <i class="fa-solid fa-<?php echo $is_benefice ? 'trophy' : 'exclamation-triangle'; ?>"></i>
                </div>
                <div class="stat-label"><?php echo $is_benefice ? 'Bénéfice' : 'Perte'; ?></div>
                <div class="stat-value" style="color: <?php echo $is_benefice ? '#48bb78' : '#f56565'; ?>;">
                    <?php echo number_format(abs($resultat), 2, '.', ' '); ?> CHF
                </div>
            </div>
        </div>

        <!-- Grille Produits / Charges -->
        <div class="resultat-grid">
            <!-- PRODUITS -->
            <div class="resultat-section">
                <div class="section-header produits">
                    <i class="fa-solid fa-plus-circle"></i> PRODUITS
                </div>
                <div class="section-body">
                    <?php if (count($produits_data) > 0): ?>
                        <?php foreach ($produits_data as $account): ?>
                            <div class="account-line">
                                <div class="account-name">
                                    <span class="account-number"><?php echo htmlspecialchars($account['number']); ?></span>
                                    <?php echo htmlspecialchars($account['name']); ?>
                                </div>
                                <div class="account-amount">
                                    <?php echo number_format(abs($account['solde']), 2, '.', ' '); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="total-line produits">
                            <div>TOTAL PRODUITS</div>
                            <div><?php echo number_format($total_produits, 2, '.', ' '); ?> CHF</div>
                        </div>
                    <?php else: ?>
                        <div class="no-data">Aucun produit sur la période</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- CHARGES -->
            <div class="resultat-section">
                <div class="section-header charges">
                    <i class="fa-solid fa-minus-circle"></i> CHARGES
                </div>
                <div class="section-body">
                    <?php if (count($charges_data) > 0): ?>
                        <?php foreach ($charges_data as $account): ?>
                            <div class="account-line">
                                <div class="account-name">
                                    <span class="account-number"><?php echo htmlspecialchars($account['number']); ?></span>
                                    <?php echo htmlspecialchars($account['name']); ?>
                                </div>
                                <div class="account-amount">
                                    <?php echo number_format(abs($account['solde']), 2, '.', ' '); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="total-line charges">
                            <div>TOTAL CHARGES</div>
                            <div><?php echo number_format($total_charges, 2, '.', ' '); ?> CHF</div>
                        </div>
                    <?php else: ?>
                        <div class="no-data">Aucune charge sur la période</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Résultat Final -->
        <div class="resultat-final-card">
            <div class="resultat-header <?php echo $is_benefice ? 'benefice' : 'perte'; ?>">
                <i class="fa-solid fa-calculator"></i> RÉSULTAT NET
            </div>
            <div class="resultat-body">
                <div class="resultat-breakdown">
                    <div class="breakdown-item">
                        <div class="breakdown-label">Produits</div>
                        <div class="breakdown-value produits">
                            + <?php echo number_format($total_produits, 2, '.', ' '); ?>
                        </div>
                    </div>

                    <div class="breakdown-item">
                        <div class="breakdown-label">Charges</div>
                        <div class="breakdown-value charges">
                            - <?php echo number_format($total_charges, 2, '.', ' '); ?>
                        </div>
                    </div>
                </div>

                <div class="resultat-total">
                    <div class="resultat-label">
                        <?php echo $is_benefice ? 'BÉNÉFICE' : 'PERTE'; ?> NET
                    </div>
                    <div class="resultat-value <?php echo $is_benefice ? 'benefice' : 'perte'; ?>">
                        <?php echo $is_benefice ? '+' : '-'; ?>
                        <?php echo number_format(abs($resultat), 2, '.', ' '); ?> CHF
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
