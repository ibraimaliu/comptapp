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

// Récupérer tous les comptes du plan comptable
$accounting_plan = new AccountingPlan($db);
$accounts = $accounting_plan->readByCompany($company_id);

// Compte sélectionné
$selected_account_id = isset($_GET['account_id']) ? intval($_GET['account_id']) : null;

// Filtres de date
$date_start = isset($_GET['date_start']) ? $_GET['date_start'] : date('Y-01-01'); // Début de l'année
$date_end = isset($_GET['date_end']) ? $_GET['date_end'] : date('Y-m-d'); // Aujourd'hui

$selected_account = null;
$transactions = [];
$solde_initial = 0;
$solde_final = 0;
$total_debit = 0;
$total_credit = 0;

if ($selected_account_id) {
    // Récupérer les détails du compte
    foreach ($accounts as $acc) {
        if ($acc['id'] == $selected_account_id) {
            $selected_account = $acc;
            break;
        }
    }

    if ($selected_account) {
        // Récupérer les transactions pour ce compte (comme débit ou crédit)
        $query = "SELECT t.*,
                         a1.number as account_number, a1.name as account_name,
                         a2.number as counterpart_number, a2.name as counterpart_name
                  FROM transactions t
                  LEFT JOIN accounting_plan a1 ON t.account_id = a1.id
                  LEFT JOIN accounting_plan a2 ON t.counterpart_account_id = a2.id
                  WHERE (t.account_id = :account_id OR t.counterpart_account_id = :account_id)
                    AND t.company_id = :company_id
                    AND t.date >= :date_start
                    AND t.date <= :date_end
                  ORDER BY t.date ASC, t.id ASC";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':account_id', $selected_account_id);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_start', $date_start);
        $stmt->bindParam(':date_end', $date_end);
        $stmt->execute();

        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculer le solde initial (transactions avant date_start)
        $query_initial = "SELECT SUM(
                            CASE
                                WHEN t.account_id = :account_id THEN t.amount
                                WHEN t.counterpart_account_id = :account_id THEN -t.amount
                                ELSE 0
                            END
                          ) as solde_initial
                          FROM transactions t
                          WHERE (t.account_id = :account_id OR t.counterpart_account_id = :account_id)
                            AND t.company_id = :company_id
                            AND t.date < :date_start";

        $stmt_initial = $db->prepare($query_initial);
        $stmt_initial->bindParam(':account_id', $selected_account_id);
        $stmt_initial->bindParam(':company_id', $company_id);
        $stmt_initial->bindParam(':date_start', $date_start);
        $stmt_initial->execute();

        $result_initial = $stmt_initial->fetch(PDO::FETCH_ASSOC);
        $solde_initial = floatval($result_initial['solde_initial'] ?? 0);

        // Calculer les totaux et le solde final
        $solde_courant = $solde_initial;
        foreach ($transactions as &$transaction) {
            // Déterminer si c'est un débit ou un crédit pour ce compte
            if ($transaction['account_id'] == $selected_account_id) {
                // Ce compte est au débit
                $transaction['debit'] = $transaction['amount'];
                $transaction['credit'] = 0;
                $solde_courant += $transaction['amount'];
                $total_debit += $transaction['amount'];
            } else {
                // Ce compte est au crédit
                $transaction['debit'] = 0;
                $transaction['credit'] = $transaction['amount'];
                $solde_courant -= $transaction['amount'];
                $total_credit += $transaction['amount'];
            }
            $transaction['solde'] = $solde_courant;
        }
        unset($transaction);

        $solde_final = $solde_courant;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relevé de Compte</title>
    <style>
        .releve-container {
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
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
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

        .btn-export {
            background: #48bb78;
            color: white;
            margin-left: 10px;
        }

        .btn-export:hover {
            background: #38a169;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .summary-card.initial {
            border-left: 4px solid #3182ce;
        }

        .summary-card.debit {
            border-left: 4px solid #f56565;
        }

        .summary-card.credit {
            border-left: 4px solid #48bb78;
        }

        .summary-card.final {
            border-left: 4px solid #9f7aea;
        }

        .summary-card label {
            display: block;
            color: #718096;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .summary-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #2d3748;
        }

        .transactions-table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f7fafc;
        }

        th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            border-bottom: 2px solid #e2e8f0;
            font-size: 13px;
            text-transform: uppercase;
        }

        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .text-right {
            text-align: right;
        }

        .amount-debit {
            color: #f56565;
            font-weight: 500;
        }

        .amount-credit {
            color: #48bb78;
            font-weight: 500;
        }

        .amount-solde {
            color: #2d3748;
            font-weight: 600;
        }

        .no-data {
            padding: 60px 20px;
            text-align: center;
            color: #718096;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #cbd5e0;
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
        }
    </style>
</head>
<body>
    <div class="releve-container">
        <div class="page-header">
            <h1><i class="fa-solid fa-file-lines"></i> Relevé de Compte</h1>
            <p>Consultez le détail des mouvements comptables par compte</p>
        </div>

        <!-- Filtres -->
        <div class="filters-card">
            <form method="GET" action="index.php">
                <input type="hidden" name="page" value="releve_compte">
                <div class="filter-row">
                    <div class="form-group">
                        <label for="account_id">Compte comptable *</label>
                        <select name="account_id" id="account_id" class="form-control" required>
                            <option value="">-- Sélectionnez un compte --</option>
                            <?php foreach ($accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>"
                                    <?php echo ($selected_account_id == $account['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($account['number'] . ' - ' . $account['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="date_start">Date début</label>
                        <input type="date" name="date_start" id="date_start" class="form-control"
                               value="<?php echo htmlspecialchars($date_start); ?>">
                    </div>

                    <div class="form-group">
                        <label for="date_end">Date fin</label>
                        <input type="date" name="date_end" id="date_end" class="form-control"
                               value="<?php echo htmlspecialchars($date_end); ?>">
                    </div>

                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-search"></i> Afficher
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <?php if ($selected_account): ?>
            <!-- Résumé -->
            <div class="summary-cards">
                <div class="summary-card initial">
                    <label>Solde Initial</label>
                    <div class="value"><?php echo number_format($solde_initial, 2, '.', ' '); ?> CHF</div>
                </div>

                <div class="summary-card debit">
                    <label>Total Débit</label>
                    <div class="value"><?php echo number_format($total_debit, 2, '.', ' '); ?> CHF</div>
                </div>

                <div class="summary-card credit">
                    <label>Total Crédit</label>
                    <div class="value"><?php echo number_format($total_credit, 2, '.', ' '); ?> CHF</div>
                </div>

                <div class="summary-card final">
                    <label>Solde Final</label>
                    <div class="value"><?php echo number_format($solde_final, 2, '.', ' '); ?> CHF</div>
                </div>
            </div>

            <!-- Tableau des transactions -->
            <div class="transactions-table-card">
                <div class="table-responsive">
                    <?php if (count($transactions) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Compte contrepartie</th>
                                    <th class="text-right">Débit</th>
                                    <th class="text-right">Crédit</th>
                                    <th class="text-right">Solde</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Ligne solde initial -->
                                <tr style="background: #edf2f7; font-weight: 600;">
                                    <td colspan="3">Solde initial au <?php echo date('d/m/Y', strtotime($date_start)); ?></td>
                                    <td class="text-right">-</td>
                                    <td class="text-right">-</td>
                                    <td class="text-right amount-solde"><?php echo number_format($solde_initial, 2, '.', ' '); ?></td>
                                </tr>

                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($transaction['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td>
                                            <?php
                                            if ($transaction['account_id'] == $selected_account_id) {
                                                // Afficher le compte de contrepartie (crédit)
                                                echo htmlspecialchars($transaction['counterpart_number'] . ' - ' . $transaction['counterpart_name']);
                                            } else {
                                                // Afficher le compte principal (débit)
                                                echo htmlspecialchars($transaction['account_number'] . ' - ' . $transaction['account_name']);
                                            }
                                            ?>
                                        </td>
                                        <td class="text-right amount-debit">
                                            <?php echo $transaction['debit'] > 0 ? number_format($transaction['debit'], 2, '.', ' ') : '-'; ?>
                                        </td>
                                        <td class="text-right amount-credit">
                                            <?php echo $transaction['credit'] > 0 ? number_format($transaction['credit'], 2, '.', ' ') : '-'; ?>
                                        </td>
                                        <td class="text-right amount-solde">
                                            <?php echo number_format($transaction['solde'], 2, '.', ' '); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>

                                <!-- Ligne totaux -->
                                <tr style="background: #edf2f7; font-weight: 600;">
                                    <td colspan="3">TOTAUX</td>
                                    <td class="text-right amount-debit"><?php echo number_format($total_debit, 2, '.', ' '); ?></td>
                                    <td class="text-right amount-credit"><?php echo number_format($total_credit, 2, '.', ' '); ?></td>
                                    <td class="text-right amount-solde"><?php echo number_format($solde_final, 2, '.', ' '); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-data">
                            <i class="fa-solid fa-inbox"></i>
                            <p>Aucune transaction trouvée pour ce compte sur la période sélectionnée.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-data" style="background: white; border-radius: 10px; padding: 60px;">
                <i class="fa-solid fa-filter"></i>
                <p>Veuillez sélectionner un compte comptable pour afficher le relevé.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Fonction pour imprimer
        function printReleve() {
            window.print();
        }
    </script>
</body>
</html>
