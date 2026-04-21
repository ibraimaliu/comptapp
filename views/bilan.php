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

// Date de clôture pour le bilan
$date_cloture = isset($_GET['date_cloture']) ? $_GET['date_cloture'] : date('Y-m-d');

// Récupérer tous les comptes de type Bilan
$query_accounts = "SELECT * FROM accounting_plan
                   WHERE company_id = :company_id
                     AND type = 'Bilan'
                   ORDER BY category, number ASC";

$stmt = $db->prepare($query_accounts);
$stmt->bindParam(':company_id', $company_id);
$stmt->execute();
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculer les soldes pour chaque compte
$actif_data = ['Actif circulant' => [], 'Actif immobilisé' => []];
$passif_data = ['Capitaux propres' => [], 'Dettes' => []];

$total_actif = 0;
$total_passif = 0;

foreach ($accounts as $account) {
    // Calculer le solde du compte jusqu'à la date de clôture
    $query_solde = "SELECT
                        SUM(CASE WHEN account_id = :account_id THEN amount ELSE 0 END) -
                        SUM(CASE WHEN counterpart_account_id = :account_id THEN amount ELSE 0 END) as solde
                    FROM transactions
                    WHERE (account_id = :account_id OR counterpart_account_id = :account_id)
                      AND company_id = :company_id
                      AND date <= :date_cloture";

    $stmt_solde = $db->prepare($query_solde);
    $stmt_solde->bindParam(':account_id', $account['id']);
    $stmt_solde->bindParam(':company_id', $company_id);
    $stmt_solde->bindParam(':date_cloture', $date_cloture);
    $stmt_solde->execute();

    $result = $stmt_solde->fetch(PDO::FETCH_ASSOC);
    $solde = floatval($result['solde'] ?? 0);

    // Ne garder que les comptes avec un solde non nul
    if (abs($solde) > 0.01) {
        $account_with_solde = $account;
        $account_with_solde['solde'] = $solde;

        // Classer selon la catégorie
        if ($account['category'] == 'Actif') {
            // Déterminer si circulant ou immobilisé selon le numéro de compte
            $account_number = intval($account['number']);
            if ($account_number >= 1000 && $account_number < 1500) {
                $actif_data['Actif circulant'][] = $account_with_solde;
                $total_actif += abs($solde);
            } else {
                $actif_data['Actif immobilisé'][] = $account_with_solde;
                $total_actif += abs($solde);
            }
        } elseif ($account['category'] == 'Passif') {
            // Déterminer si capitaux propres ou dettes
            $account_number = intval($account['number']);
            if ($account_number >= 2000 && $account_number < 2800) {
                $passif_data['Capitaux propres'][] = $account_with_solde;
                $total_passif += abs($solde);
            } else {
                $passif_data['Dettes'][] = $account_with_solde;
                $total_passif += abs($solde);
            }
        }
    }
}

// Calculer les sous-totaux
$total_actif_circulant = 0;
foreach ($actif_data['Actif circulant'] as $acc) {
    $total_actif_circulant += abs($acc['solde']);
}

$total_actif_immobilise = 0;
foreach ($actif_data['Actif immobilisé'] as $acc) {
    $total_actif_immobilise += abs($acc['solde']);
}

$total_capitaux_propres = 0;
foreach ($passif_data['Capitaux propres'] as $acc) {
    $total_capitaux_propres += abs($acc['solde']);
}

$total_dettes = 0;
foreach ($passif_data['Dettes'] as $acc) {
    $total_dettes += abs($acc['solde']);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bilan Comptable</title>
    <style>
        .bilan-container {
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

        .bilan-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .bilan-section {
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

        .section-header.actif {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
        }

        .section-header.passif {
            background: linear-gradient(135deg, #48bb78 0%, #2f855a 100%);
        }

        .section-body {
            padding: 0;
        }

        .category-group {
            margin-bottom: 0;
        }

        .category-title {
            background: #f7fafc;
            padding: 12px 20px;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        .account-line {
            display: flex;
            justify-content: space-between;
            padding: 10px 20px;
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

        .subtotal-line {
            display: flex;
            justify-content: space-between;
            padding: 12px 20px;
            background: #edf2f7;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #cbd5e0;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 15px 20px;
            background: #e6fffa;
            font-weight: 700;
            font-size: 16px;
            color: #234e52;
        }

        .total-line.actif {
            background: #ebf8ff;
            color: #2c5282;
        }

        .total-line.passif {
            background: #f0fff4;
            color: #22543d;
        }

        .equilibre-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
        }

        .equilibre-card.balanced {
            border-left: 4px solid #48bb78;
        }

        .equilibre-card.unbalanced {
            border-left: 4px solid #f56565;
        }

        .equilibre-title {
            font-size: 14px;
            color: #718096;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .equilibre-value {
            font-size: 32px;
            font-weight: 700;
        }

        .equilibre-value.balanced {
            color: #48bb78;
        }

        .equilibre-value.unbalanced {
            color: #f56565;
        }

        .equilibre-message {
            margin-top: 10px;
            font-size: 14px;
            color: #4a5568;
        }

        .no-data {
            padding: 40px 20px;
            text-align: center;
            color: #718096;
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

            .bilan-grid {
                page-break-inside: avoid;
            }
        }

        @media (max-width: 968px) {
            .bilan-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="bilan-container">
        <div class="page-header">
            <h1><i class="fa-solid fa-balance-scale"></i> Bilan Comptable</h1>
            <p>Situation patrimoniale de l'entreprise à une date donnée</p>
        </div>

        <!-- Filtres -->
        <div class="filters-card">
            <form method="GET" action="index.php">
                <input type="hidden" name="page" value="bilan">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="date_cloture">Date de clôture</label>
                        <input type="date" name="date_cloture" id="date_cloture" class="form-control"
                               value="<?php echo htmlspecialchars($date_cloture); ?>">
                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-calculator"></i> Calculer le bilan
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Grille Actif / Passif -->
        <div class="bilan-grid">
            <!-- ACTIF -->
            <div class="bilan-section">
                <div class="section-header actif">
                    <i class="fa-solid fa-arrow-trend-up"></i> ACTIF
                </div>
                <div class="section-body">
                    <!-- Actif circulant -->
                    <div class="category-group">
                        <div class="category-title">Actif circulant</div>
                        <?php if (count($actif_data['Actif circulant']) > 0): ?>
                            <?php foreach ($actif_data['Actif circulant'] as $account): ?>
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
                            <div class="subtotal-line">
                                <div>Total Actif circulant</div>
                                <div><?php echo number_format($total_actif_circulant, 2, '.', ' '); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="no-data">Aucun compte</div>
                        <?php endif; ?>
                    </div>

                    <!-- Actif immobilisé -->
                    <div class="category-group">
                        <div class="category-title">Actif immobilisé</div>
                        <?php if (count($actif_data['Actif immobilisé']) > 0): ?>
                            <?php foreach ($actif_data['Actif immobilisé'] as $account): ?>
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
                            <div class="subtotal-line">
                                <div>Total Actif immobilisé</div>
                                <div><?php echo number_format($total_actif_immobilise, 2, '.', ' '); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="no-data">Aucun compte</div>
                        <?php endif; ?>
                    </div>

                    <!-- Total ACTIF -->
                    <div class="total-line actif">
                        <div>TOTAL ACTIF</div>
                        <div><?php echo number_format($total_actif, 2, '.', ' '); ?> CHF</div>
                    </div>
                </div>
            </div>

            <!-- PASSIF -->
            <div class="bilan-section">
                <div class="section-header passif">
                    <i class="fa-solid fa-arrow-trend-down"></i> PASSIF
                </div>
                <div class="section-body">
                    <!-- Capitaux propres -->
                    <div class="category-group">
                        <div class="category-title">Capitaux propres</div>
                        <?php if (count($passif_data['Capitaux propres']) > 0): ?>
                            <?php foreach ($passif_data['Capitaux propres'] as $account): ?>
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
                            <div class="subtotal-line">
                                <div>Total Capitaux propres</div>
                                <div><?php echo number_format($total_capitaux_propres, 2, '.', ' '); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="no-data">Aucun compte</div>
                        <?php endif; ?>
                    </div>

                    <!-- Dettes -->
                    <div class="category-group">
                        <div class="category-title">Dettes</div>
                        <?php if (count($passif_data['Dettes']) > 0): ?>
                            <?php foreach ($passif_data['Dettes'] as $account): ?>
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
                            <div class="subtotal-line">
                                <div>Total Dettes</div>
                                <div><?php echo number_format($total_dettes, 2, '.', ' '); ?></div>
                            </div>
                        <?php else: ?>
                            <div class="no-data">Aucun compte</div>
                        <?php endif; ?>
                    </div>

                    <!-- Total PASSIF -->
                    <div class="total-line passif">
                        <div>TOTAL PASSIF</div>
                        <div><?php echo number_format($total_passif, 2, '.', ' '); ?> CHF</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Carte Équilibre -->
        <?php
        $difference = abs($total_actif - $total_passif);
        $is_balanced = $difference < 0.01;
        ?>
        <div class="equilibre-card <?php echo $is_balanced ? 'balanced' : 'unbalanced'; ?>">
            <div class="equilibre-title">Équilibre du Bilan</div>
            <div class="equilibre-value <?php echo $is_balanced ? 'balanced' : 'unbalanced'; ?>">
                <?php if ($is_balanced): ?>
                    <i class="fa-solid fa-check-circle"></i> Équilibré
                <?php else: ?>
                    <i class="fa-solid fa-exclamation-triangle"></i> Déséquilibré
                <?php endif; ?>
            </div>
            <div class="equilibre-message">
                <?php if ($is_balanced): ?>
                    Le bilan est équilibré : Actif = Passif
                <?php else: ?>
                    Différence : <?php echo number_format($difference, 2, '.', ' '); ?> CHF
                    <br><small>(Actif : <?php echo number_format($total_actif, 2, '.', ' '); ?> - Passif : <?php echo number_format($total_passif, 2, '.', ' '); ?>)</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
