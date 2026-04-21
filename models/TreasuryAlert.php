<?php
/**
 * Modèle TreasuryAlert
 * Gestion des alertes de trésorerie
 */
class TreasuryAlert {
    private $conn;
    private $table_name = "treasury_alerts";

    // Propriétés de la table
    public $id;
    public $company_id;
    public $alert_type;
    public $alert_date;
    public $threshold_amount;
    public $actual_amount;
    public $forecast_date;
    public $severity;
    public $message;
    public $status;
    public $resolved_at;
    public $resolved_by;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une alerte
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET company_id = :company_id,
                      alert_type = :alert_type,
                      threshold_amount = :threshold_amount,
                      actual_amount = :actual_amount,
                      forecast_date = :forecast_date,
                      severity = :severity,
                      message = :message,
                      status = :status";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->company_id = htmlspecialchars(strip_tags($this->company_id));
        $this->alert_type = htmlspecialchars(strip_tags($this->alert_type));
        $this->threshold_amount = floatval($this->threshold_amount ?? 0);
        $this->actual_amount = floatval($this->actual_amount ?? 0);
        $this->forecast_date = $this->forecast_date ? htmlspecialchars(strip_tags($this->forecast_date)) : null;
        $this->severity = htmlspecialchars(strip_tags($this->severity ?? 'warning'));
        $this->message = htmlspecialchars(strip_tags($this->message));
        $this->status = htmlspecialchars(strip_tags($this->status ?? 'active'));

        // Bind values
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":alert_type", $this->alert_type);
        $stmt->bindParam(":threshold_amount", $this->threshold_amount);
        $stmt->bindParam(":actual_amount", $this->actual_amount);
        $stmt->bindParam(":forecast_date", $this->forecast_date);
        $stmt->bindParam(":severity", $this->severity);
        $stmt->bindParam(":message", $this->message);
        $stmt->bindParam(":status", $this->status);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Récupérer toutes les alertes actives pour une entreprise
     */
    public function readActiveByCompany($company_id) {
        $query = "SELECT * FROM v_active_treasury_alerts
                  WHERE company_id = :company_id
                  ORDER BY
                      FIELD(severity, 'critical', 'warning', 'info'),
                      alert_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marquer une alerte comme résolue
     */
    public function resolve($alert_id, $company_id, $user_id) {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'resolved',
                      resolved_at = NOW(),
                      resolved_by = :user_id
                  WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $alert_id);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":user_id", $user_id);

        return $stmt->execute();
    }

    /**
     * Marquer une alerte comme ignorée
     */
    public function ignore($alert_id, $company_id) {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'ignored'
                  WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $alert_id);
        $stmt->bindParam(":company_id", $company_id);

        return $stmt->execute();
    }

    /**
     * Vérifier les seuils et créer des alertes si nécessaire
     */
    public function checkAndCreateAlerts($company_id) {
        require_once 'TreasurySettings.php';
        require_once 'TreasuryForecast.php';

        $settings = new TreasurySettings($this->conn);
        $settings_data = $settings->getByCompany($company_id);

        if (!$settings_data) {
            return; // Pas de paramètres configurés
        }

        $forecast = new TreasuryForecast($this->conn);

        // Vérifier les prévisions pour les 30 prochains jours
        $start_date = date('Y-m-d');
        $end_date = date('Y-m-d', strtotime('+30 days'));
        $forecasts = $forecast->readByCompany($company_id, $start_date, $end_date);

        foreach ($forecasts as $fc) {
            $closing = floatval($fc['closing_balance']);
            $forecast_date = $fc['forecast_date'];

            // Alerte solde critique
            if ($closing <= floatval($settings_data['critical_balance_alert']) && $closing > 0) {
                $this->createAlertIfNotExists(
                    $company_id,
                    'low_balance',
                    'critical',
                    $settings_data['critical_balance_alert'],
                    $closing,
                    $forecast_date,
                    "Solde critique prévu: " . number_format($closing, 2) . " CHF le " . date('d/m/Y', strtotime($forecast_date))
                );
            }
            // Alerte solde bas
            elseif ($closing <= floatval($settings_data['min_balance_alert']) && $closing > floatval($settings_data['critical_balance_alert'])) {
                $this->createAlertIfNotExists(
                    $company_id,
                    'low_balance',
                    'warning',
                    $settings_data['min_balance_alert'],
                    $closing,
                    $forecast_date,
                    "Solde bas prévu: " . number_format($closing, 2) . " CHF le " . date('d/m/Y', strtotime($forecast_date))
                );
            }

            // Alerte solde négatif
            if ($closing < 0) {
                $this->createAlertIfNotExists(
                    $company_id,
                    'negative_forecast',
                    'critical',
                    0,
                    $closing,
                    $forecast_date,
                    "Solde négatif prévu: " . number_format($closing, 2) . " CHF le " . date('d/m/Y', strtotime($forecast_date))
                );
            }
        }

        // Vérifier les factures en retard
        $this->checkOverdueInvoices($company_id);
    }

    /**
     * Créer une alerte si elle n'existe pas déjà
     */
    private function createAlertIfNotExists($company_id, $type, $severity, $threshold, $actual, $forecast_date, $message) {
        // Vérifier si une alerte similaire existe déjà
        $query = "SELECT id FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  AND alert_type = :alert_type
                  AND forecast_date = :forecast_date
                  AND status = 'active'
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":alert_type", $type);
        $stmt->bindParam(":forecast_date", $forecast_date);
        $stmt->execute();

        // Si aucune alerte active n'existe, en créer une
        if ($stmt->rowCount() == 0) {
            $this->company_id = $company_id;
            $this->alert_type = $type;
            $this->severity = $severity;
            $this->threshold_amount = $threshold;
            $this->actual_amount = $actual;
            $this->forecast_date = $forecast_date;
            $this->message = $message;
            $this->status = 'active';
            $this->create();
        }
    }

    /**
     * Vérifier les factures en retard
     */
    private function checkOverdueInvoices($company_id) {
        $query = "SELECT COUNT(*) as count, SUM(total_amount) as total
                  FROM invoices
                  WHERE company_id = :company_id
                  AND status IN ('sent')
                  AND due_date < CURDATE()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && intval($row['count']) > 0) {
            $message = intval($row['count']) . " facture(s) en retard représentant " .
                       number_format(floatval($row['total']), 2) . " CHF";

            $this->createAlertIfNotExists(
                $company_id,
                'overdue_invoices',
                'warning',
                0,
                floatval($row['total']),
                null,
                $message
            );
        }
    }

    /**
     * Compter les alertes actives par sévérité
     */
    public function countAlertsBySeverity($company_id) {
        $query = "SELECT severity, COUNT(*) as count
                  FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  AND status = 'active'
                  GROUP BY severity";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = [
            'critical' => 0,
            'warning' => 0,
            'info' => 0
        ];

        foreach ($results as $row) {
            $counts[$row['severity']] = intval($row['count']);
        }

        return $counts;
    }
}
?>
