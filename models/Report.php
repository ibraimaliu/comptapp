<?php
/**
 * Modèle Report - Génération des rapports comptables
 * - Bilan (Balance Sheet)
 * - Compte de Résultat (Income Statement / Profit & Loss)
 */

class Report {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Calculer le bilan comptable
     *
     * @param int $company_id - ID de l'entreprise
     * @param string $date_start - Date de début (optionnel)
     * @param string $date_end - Date de fin (par défaut: aujourd'hui)
     * @return array - Structure du bilan avec actifs et passifs
     */
    public function getBalanceSheet($company_id, $date_start = null, $date_end = null) {
        if (!$date_end) {
            $date_end = date('Y-m-d');
        }

        // Récupérer tous les comptes de bilan (actif et passif)
        $query = "SELECT
                    ap.id,
                    ap.number,
                    ap.name,
                    ap.category,
                    COALESCE(SUM(
                        CASE
                            WHEN t.type = 'income' AND ap.category = 'actif' THEN t.amount
                            WHEN t.type = 'expense' AND ap.category = 'actif' THEN -t.amount
                            WHEN t.type = 'income' AND ap.category = 'passif' THEN -t.amount
                            WHEN t.type = 'expense' AND ap.category = 'passif' THEN t.amount
                            ELSE 0
                        END
                    ), 0) as balance
                  FROM accounting_plan ap
                  LEFT JOIN transactions t ON (ap.id = t.account_id OR ap.id = t.counterpart_account_id)
                  WHERE ap.company_id = :company_id
                  AND ap.type = 'bilan'";

        if ($date_start) {
            $query .= " AND (t.date IS NULL OR t.date >= :date_start)";
        }

        $query .= " AND (t.date IS NULL OR t.date <= :date_end)
                   GROUP BY ap.id, ap.number, ap.name, ap.category
                   ORDER BY ap.number";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        if ($date_start) {
            $stmt->bindParam(':date_start', $date_start);
        }
        $stmt->bindParam(':date_end', $date_end);
        $stmt->execute();

        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Organiser par actif et passif
        $actif = [];
        $passif = [];
        $total_actif = 0;
        $total_passif = 0;

        foreach ($accounts as $account) {
            if ($account['category'] == 'actif') {
                $actif[] = $account;
                $total_actif += floatval($account['balance']);
            } else if ($account['category'] == 'passif') {
                $passif[] = $account;
                $total_passif += floatval($account['balance']);
            }
        }

        // Calculer le résultat net et l'ajouter au passif
        $resultat_net = $this->getNetIncome($company_id, $date_start, $date_end);

        if ($resultat_net >= 0) {
            // Bénéfice = augmente le passif
            $passif[] = [
                'number' => '2900',
                'name' => 'Résultat de l\'exercice (Bénéfice)',
                'category' => 'passif',
                'balance' => $resultat_net
            ];
            $total_passif += $resultat_net;
        } else {
            // Perte = augmente l'actif (négatif)
            $actif[] = [
                'number' => '1900',
                'name' => 'Résultat de l\'exercice (Perte)',
                'category' => 'actif',
                'balance' => abs($resultat_net)
            ];
            $total_actif += abs($resultat_net);
        }

        return [
            'actif' => $actif,
            'passif' => $passif,
            'total_actif' => $total_actif,
            'total_passif' => $total_passif,
            'equilibre' => abs($total_actif - $total_passif) < 0.01, // Tolérance de 1 centime
            'difference' => $total_actif - $total_passif,
            'date_start' => $date_start,
            'date_end' => $date_end
        ];
    }

    /**
     * Calculer le compte de résultat (Profit & Loss)
     *
     * @param int $company_id - ID de l'entreprise
     * @param string $date_start - Date de début de la période
     * @param string $date_end - Date de fin de la période
     * @return array - Structure du compte de résultat avec charges et produits
     */
    public function getIncomeStatement($company_id, $date_start, $date_end = null) {
        if (!$date_end) {
            $date_end = date('Y-m-d');
        }

        // Récupérer tous les comptes de résultat (charge et produit)
        $query = "SELECT
                    ap.id,
                    ap.number,
                    ap.name,
                    ap.category,
                    COALESCE(SUM(
                        CASE
                            WHEN t.type = 'expense' AND ap.category = 'charge' THEN t.amount
                            WHEN t.type = 'income' AND ap.category = 'charge' THEN -t.amount
                            WHEN t.type = 'income' AND ap.category = 'produit' THEN t.amount
                            WHEN t.type = 'expense' AND ap.category = 'produit' THEN -t.amount
                            ELSE 0
                        END
                    ), 0) as balance
                  FROM accounting_plan ap
                  LEFT JOIN transactions t ON (ap.id = t.account_id OR ap.id = t.counterpart_account_id)
                  WHERE ap.company_id = :company_id
                  AND ap.type = 'resultat'
                  AND t.date >= :date_start
                  AND t.date <= :date_end
                  GROUP BY ap.id, ap.number, ap.name, ap.category
                  ORDER BY ap.number";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->bindParam(':date_start', $date_start);
        $stmt->bindParam(':date_end', $date_end);
        $stmt->execute();

        $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        // Organiser par charges et produits
        $charges = [];
        $produits = [];
        $total_charges = 0;
        $total_produits = 0;

        foreach ($accounts as $account) {
            if ($account['balance'] != 0) { // N'afficher que les comptes avec solde
                if ($account['category'] == 'charge') {
                    $charges[] = $account;
                    $total_charges += floatval($account['balance']);
                } else if ($account['category'] == 'produit') {
                    $produits[] = $account;
                    $total_produits += floatval($account['balance']);
                }
            }
        }

        $resultat_net = $total_produits - $total_charges;

        return [
            'produits' => $produits,
            'charges' => $charges,
            'total_produits' => $total_produits,
            'total_charges' => $total_charges,
            'resultat_net' => $resultat_net,
            'resultat_type' => $resultat_net >= 0 ? 'benefice' : 'perte',
            'date_start' => $date_start,
            'date_end' => $date_end
        ];
    }

    /**
     * Calculer le résultat net sur une période
     *
     * @param int $company_id - ID de l'entreprise
     * @param string $date_start - Date de début (optionnel)
     * @param string $date_end - Date de fin
     * @return float - Résultat net (positif = bénéfice, négatif = perte)
     */
    public function getNetIncome($company_id, $date_start = null, $date_end = null) {
        if (!$date_end) {
            $date_end = date('Y-m-d');
        }

        $query = "SELECT
                    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
                    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense
                  FROM transactions
                  WHERE company_id = :company_id";

        if ($date_start) {
            $query .= " AND date >= :date_start";
        }

        $query .= " AND date <= :date_end";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        if ($date_start) {
            $stmt->bindParam(':date_start', $date_start);
        }
        $stmt->bindParam(':date_end', $date_end);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return floatval($result['total_income']) - floatval($result['total_expense']);
    }

    /**
     * Calculer les indicateurs clés de performance (KPIs)
     *
     * @param int $company_id - ID de l'entreprise
     * @param string $date_start - Date de début
     * @param string $date_end - Date de fin
     * @return array - KPIs calculés
     */
    public function getKPIs($company_id, $date_start, $date_end = null) {
        if (!$date_end) {
            $date_end = date('Y-m-d');
        }

        $income_statement = $this->getIncomeStatement($company_id, $date_start, $date_end);
        $balance_sheet = $this->getBalanceSheet($company_id, null, $date_end);

        $total_produits = $income_statement['total_produits'];
        $total_charges = $income_statement['total_charges'];
        $resultat_net = $income_statement['resultat_net'];

        // Marge brute (si on a le CA et le coût des ventes)
        $marge_brute_pct = $total_produits > 0 ? ($resultat_net / $total_produits) * 100 : 0;

        // Rentabilité
        $rentabilite_pct = $total_produits > 0 ? ($resultat_net / $total_produits) * 100 : 0;

        // Ratio charges/produits
        $ratio_charges_produits = $total_produits > 0 ? ($total_charges / $total_produits) * 100 : 0;

        return [
            'chiffre_affaires' => $total_produits,
            'charges_totales' => $total_charges,
            'resultat_net' => $resultat_net,
            'marge_brute_pct' => round($marge_brute_pct, 2),
            'rentabilite_pct' => round($rentabilite_pct, 2),
            'ratio_charges_produits' => round($ratio_charges_produits, 2),
            'total_actif' => $balance_sheet['total_actif'],
            'total_passif' => $balance_sheet['total_passif'],
            'periode' => [
                'start' => $date_start,
                'end' => $date_end
            ]
        ];
    }

    /**
     * Comparer deux périodes
     *
     * @param int $company_id - ID de l'entreprise
     * @param string $period1_start - Début période 1
     * @param string $period1_end - Fin période 1
     * @param string $period2_start - Début période 2
     * @param string $period2_end - Fin période 2
     * @return array - Comparaison des deux périodes
     */
    public function comparePeriods($company_id, $period1_start, $period1_end, $period2_start, $period2_end) {
        $kpi1 = $this->getKPIs($company_id, $period1_start, $period1_end);
        $kpi2 = $this->getKPIs($company_id, $period2_start, $period2_end);

        $comparison = [];
        $metrics = ['chiffre_affaires', 'charges_totales', 'resultat_net'];

        foreach ($metrics as $metric) {
            $value1 = $kpi1[$metric];
            $value2 = $kpi2[$metric];
            $difference = $value2 - $value1;
            $evolution_pct = $value1 != 0 ? ($difference / abs($value1)) * 100 : 0;

            $comparison[$metric] = [
                'period1' => $value1,
                'period2' => $value2,
                'difference' => $difference,
                'evolution_pct' => round($evolution_pct, 2),
                'trend' => $difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'stable')
            ];
        }

        return [
            'period1' => ['start' => $period1_start, 'end' => $period1_end],
            'period2' => ['start' => $period2_start, 'end' => $period2_end],
            'comparison' => $comparison
        ];
    }
}
?>
