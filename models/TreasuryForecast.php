<?php
/**
 * Modèle TreasuryForecast
 * Gestion des prévisions de trésorerie
 */
class TreasuryForecast {
    private $conn;
    private $table_name = "treasury_forecasts";

    // Propriétés de la table
    public $id;
    public $company_id;
    public $forecast_date;
    public $expected_income;
    public $expected_expenses;
    public $actual_income;
    public $actual_expenses;
    public $opening_balance;
    public $closing_balance;
    public $notes;
    public $is_actual;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une prévision de trésorerie
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET company_id = :company_id,
                      forecast_date = :forecast_date,
                      expected_income = :expected_income,
                      expected_expenses = :expected_expenses,
                      actual_income = :actual_income,
                      actual_expenses = :actual_expenses,
                      opening_balance = :opening_balance,
                      closing_balance = :closing_balance,
                      notes = :notes,
                      is_actual = :is_actual";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->company_id = htmlspecialchars(strip_tags($this->company_id));
        $this->forecast_date = htmlspecialchars(strip_tags($this->forecast_date));
        $this->expected_income = floatval($this->expected_income ?? 0);
        $this->expected_expenses = floatval($this->expected_expenses ?? 0);
        $this->actual_income = floatval($this->actual_income ?? 0);
        $this->actual_expenses = floatval($this->actual_expenses ?? 0);
        $this->opening_balance = floatval($this->opening_balance ?? 0);
        $this->closing_balance = floatval($this->closing_balance ?? 0);
        $this->notes = htmlspecialchars(strip_tags($this->notes ?? ''));
        $this->is_actual = $this->is_actual ?? 0;

        // Bind values
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":forecast_date", $this->forecast_date);
        $stmt->bindParam(":expected_income", $this->expected_income);
        $stmt->bindParam(":expected_expenses", $this->expected_expenses);
        $stmt->bindParam(":actual_income", $this->actual_income);
        $stmt->bindParam(":actual_expenses", $this->actual_expenses);
        $stmt->bindParam(":opening_balance", $this->opening_balance);
        $stmt->bindParam(":closing_balance", $this->closing_balance);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":is_actual", $this->is_actual);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Lire une prévision par ID
     */
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE id = :id AND company_id = :company_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->forecast_date = $row['forecast_date'];
            $this->expected_income = $row['expected_income'];
            $this->expected_expenses = $row['expected_expenses'];
            $this->actual_income = $row['actual_income'];
            $this->actual_expenses = $row['actual_expenses'];
            $this->opening_balance = $row['opening_balance'];
            $this->closing_balance = $row['closing_balance'];
            $this->notes = $row['notes'];
            $this->is_actual = $row['is_actual'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }
        return false;
    }

    /**
     * Mettre à jour une prévision
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET forecast_date = :forecast_date,
                      expected_income = :expected_income,
                      expected_expenses = :expected_expenses,
                      actual_income = :actual_income,
                      actual_expenses = :actual_expenses,
                      opening_balance = :opening_balance,
                      closing_balance = :closing_balance,
                      notes = :notes,
                      is_actual = :is_actual
                  WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->forecast_date = htmlspecialchars(strip_tags($this->forecast_date));
        $this->expected_income = floatval($this->expected_income ?? 0);
        $this->expected_expenses = floatval($this->expected_expenses ?? 0);
        $this->actual_income = floatval($this->actual_income ?? 0);
        $this->actual_expenses = floatval($this->actual_expenses ?? 0);
        $this->opening_balance = floatval($this->opening_balance ?? 0);
        $this->closing_balance = floatval($this->closing_balance ?? 0);
        $this->notes = htmlspecialchars(strip_tags($this->notes ?? ''));
        $this->is_actual = $this->is_actual ?? 0;

        // Bind values
        $stmt->bindParam(":forecast_date", $this->forecast_date);
        $stmt->bindParam(":expected_income", $this->expected_income);
        $stmt->bindParam(":expected_expenses", $this->expected_expenses);
        $stmt->bindParam(":actual_income", $this->actual_income);
        $stmt->bindParam(":actual_expenses", $this->actual_expenses);
        $stmt->bindParam(":opening_balance", $this->opening_balance);
        $stmt->bindParam(":closing_balance", $this->closing_balance);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":is_actual", $this->is_actual);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Supprimer une prévision
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . "
                  WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Récupérer toutes les prévisions pour une entreprise
     */
    public function readByCompany($company_id, $start_date = null, $end_date = null) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE company_id = :company_id";

        if ($start_date) {
            $query .= " AND forecast_date >= :start_date";
        }
        if ($end_date) {
            $query .= " AND forecast_date <= :end_date";
        }

        $query .= " ORDER BY forecast_date ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if ($start_date) {
            $stmt->bindParam(":start_date", $start_date);
        }
        if ($end_date) {
            $stmt->bindParam(":end_date", $end_date);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Générer des prévisions automatiques basées sur l'historique
     */
    public function generateForecasts($company_id, $horizon_days = 90) {
        // Récupérer le solde actuel (dernière prévision réalisée ou solde bancaire)
        $current_balance = $this->getCurrentBalance($company_id);

        // Récupérer les moyennes mensuelles des 3 derniers mois
        $avg_income = $this->getAverageIncome($company_id, 90);
        $avg_expenses = $this->getAverageExpenses($company_id, 90);

        // Récupérer les factures à payer et créances à recevoir
        $upcoming_invoices = $this->getUpcomingInvoices($company_id, $horizon_days);

        $forecasts = [];
        $running_balance = $current_balance;

        // Générer des prévisions jour par jour
        for ($i = 1; $i <= $horizon_days; $i++) {
            $forecast_date = date('Y-m-d', strtotime("+$i days"));

            // Répartir les moyennes mensuelles sur les jours
            $daily_income = $avg_income / 30;
            $daily_expenses = $avg_expenses / 30;

            // Ajouter les factures prévues pour ce jour
            $day_invoices = $this->getInvoicesForDate($upcoming_invoices, $forecast_date);
            if ($day_invoices) {
                $daily_income += $day_invoices['income'];
                $daily_expenses += $day_invoices['expenses'];
            }

            $opening = $running_balance;
            $closing = $opening + $daily_income - $daily_expenses;
            $running_balance = $closing;

            $forecasts[] = [
                'forecast_date' => $forecast_date,
                'expected_income' => round($daily_income, 2),
                'expected_expenses' => round($daily_expenses, 2),
                'opening_balance' => round($opening, 2),
                'closing_balance' => round($closing, 2),
                'is_actual' => 0
            ];
        }

        return $forecasts;
    }

    /**
     * Sauvegarder des prévisions générées en masse
     */
    public function saveBulkForecasts($company_id, $forecasts) {
        try {
            $this->conn->beginTransaction();

            foreach ($forecasts as $forecast) {
                // Vérifier si une prévision existe déjà pour cette date
                $query = "SELECT id FROM " . $this->table_name . "
                          WHERE company_id = :company_id AND forecast_date = :forecast_date";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":company_id", $company_id);
                $stmt->bindParam(":forecast_date", $forecast['forecast_date']);
                $stmt->execute();

                if ($stmt->rowCount() > 0) {
                    // Mettre à jour
                    $query = "UPDATE " . $this->table_name . "
                              SET expected_income = :expected_income,
                                  expected_expenses = :expected_expenses,
                                  opening_balance = :opening_balance,
                                  closing_balance = :closing_balance
                              WHERE company_id = :company_id AND forecast_date = :forecast_date";
                } else {
                    // Insérer
                    $query = "INSERT INTO " . $this->table_name . "
                              (company_id, forecast_date, expected_income, expected_expenses,
                               opening_balance, closing_balance, is_actual)
                              VALUES (:company_id, :forecast_date, :expected_income, :expected_expenses,
                                      :opening_balance, :closing_balance, 0)";
                }

                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(":company_id", $company_id);
                $stmt->bindParam(":forecast_date", $forecast['forecast_date']);
                $stmt->bindParam(":expected_income", $forecast['expected_income']);
                $stmt->bindParam(":expected_expenses", $forecast['expected_expenses']);
                $stmt->bindParam(":opening_balance", $forecast['opening_balance']);
                $stmt->bindParam(":closing_balance", $forecast['closing_balance']);
                $stmt->execute();
            }

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Error saving bulk forecasts: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer le solde actuel
     */
    private function getCurrentBalance($company_id) {
        // Essayer d'obtenir le dernier solde de clôture réel
        $query = "SELECT closing_balance FROM " . $this->table_name . "
                  WHERE company_id = :company_id AND is_actual = 1
                  ORDER BY forecast_date DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return floatval($row['closing_balance']);
        }

        // Sinon, calculer à partir des comptes de trésorerie
        $query = "SELECT SUM(t.amount * CASE WHEN t.type = 'income' THEN 1 ELSE -1 END) as balance
                  FROM transactions t
                  INNER JOIN accounting_plan ap ON t.account_id = ap.id
                  WHERE t.company_id = :company_id
                  AND ap.category IN ('Trésorerie', 'Banque', 'Caisse')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? floatval($row['balance'] ?? 0) : 0;
    }

    /**
     * Calculer le revenu moyen sur une période
     */
    private function getAverageIncome($company_id, $days = 90) {
        $start_date = date('Y-m-d', strtotime("-$days days"));

        $query = "SELECT AVG(daily_income) as avg_income FROM (
                      SELECT DATE(date) as day, SUM(amount) as daily_income
                      FROM transactions
                      WHERE company_id = :company_id
                      AND type = 'income'
                      AND date >= :start_date
                      GROUP BY DATE(date)
                  ) as daily_totals";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":start_date", $start_date);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? floatval($row['avg_income'] ?? 0) * 30 : 0; // Moyenne mensuelle
    }

    /**
     * Calculer les dépenses moyennes sur une période
     */
    private function getAverageExpenses($company_id, $days = 90) {
        $start_date = date('Y-m-d', strtotime("-$days days"));

        $query = "SELECT AVG(daily_expense) as avg_expense FROM (
                      SELECT DATE(date) as day, SUM(amount) as daily_expense
                      FROM transactions
                      WHERE company_id = :company_id
                      AND type = 'expense'
                      AND date >= :start_date
                      GROUP BY DATE(date)
                  ) as daily_totals";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":start_date", $start_date);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? floatval($row['avg_expense'] ?? 0) * 30 : 0; // Moyenne mensuelle
    }

    /**
     * Récupérer les factures à venir
     */
    private function getUpcomingInvoices($company_id, $days = 90) {
        $end_date = date('Y-m-d', strtotime("+$days days"));

        $query = "SELECT due_date, SUM(total_amount) as amount, 'income' as type
                  FROM invoices
                  WHERE company_id = :company_id
                  AND status IN ('sent', 'draft')
                  AND due_date BETWEEN CURDATE() AND :end_date
                  GROUP BY due_date

                  UNION ALL

                  SELECT due_date, SUM(total_amount) as amount, 'expense' as type
                  FROM supplier_invoices
                  WHERE company_id = :company_id
                  AND status IN ('received', 'approved')
                  AND due_date BETWEEN CURDATE() AND :end_date
                  GROUP BY due_date";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":end_date", $end_date);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Extraire les factures pour une date donnée
     */
    private function getInvoicesForDate($invoices, $date) {
        $income = 0;
        $expenses = 0;

        foreach ($invoices as $invoice) {
            if ($invoice['due_date'] == $date) {
                if ($invoice['type'] == 'income') {
                    $income += floatval($invoice['amount']);
                } else {
                    $expenses += floatval($invoice['amount']);
                }
            }
        }

        return $income > 0 || $expenses > 0 ? ['income' => $income, 'expenses' => $expenses] : null;
    }

    /**
     * Récupérer les statistiques de trésorerie
     */
    public function getTreasuryStats($company_id, $days = 30) {
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime("+$days days"));

        $query = "SELECT
                      MIN(closing_balance) as min_balance,
                      MAX(closing_balance) as max_balance,
                      AVG(closing_balance) as avg_balance,
                      SUM(expected_income) as total_income,
                      SUM(expected_expenses) as total_expenses
                  FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  AND forecast_date BETWEEN :start_date AND :end_date";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
