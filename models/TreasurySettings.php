<?php
/**
 * Modèle TreasurySettings
 * Gestion des paramètres de trésorerie
 */
class TreasurySettings {
    private $conn;
    private $table_name = "treasury_settings";

    // Propriétés de la table
    public $id;
    public $company_id;
    public $min_balance_alert;
    public $critical_balance_alert;
    public $forecast_horizon_days;
    public $alert_email_enabled;
    public $alert_email_recipients;
    public $working_capital_target;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer les paramètres par défaut
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET company_id = :company_id,
                      min_balance_alert = :min_balance_alert,
                      critical_balance_alert = :critical_balance_alert,
                      forecast_horizon_days = :forecast_horizon_days,
                      alert_email_enabled = :alert_email_enabled,
                      alert_email_recipients = :alert_email_recipients,
                      working_capital_target = :working_capital_target";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->company_id = htmlspecialchars(strip_tags($this->company_id));
        $this->min_balance_alert = floatval($this->min_balance_alert ?? 5000);
        $this->critical_balance_alert = floatval($this->critical_balance_alert ?? 1000);
        $this->forecast_horizon_days = intval($this->forecast_horizon_days ?? 90);
        $this->alert_email_enabled = $this->alert_email_enabled ?? 1;
        $this->alert_email_recipients = htmlspecialchars(strip_tags($this->alert_email_recipients ?? ''));
        $this->working_capital_target = $this->working_capital_target ? floatval($this->working_capital_target) : null;

        // Bind values
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":min_balance_alert", $this->min_balance_alert);
        $stmt->bindParam(":critical_balance_alert", $this->critical_balance_alert);
        $stmt->bindParam(":forecast_horizon_days", $this->forecast_horizon_days);
        $stmt->bindParam(":alert_email_enabled", $this->alert_email_enabled);
        $stmt->bindParam(":alert_email_recipients", $this->alert_email_recipients);
        $stmt->bindParam(":working_capital_target", $this->working_capital_target);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Mettre à jour les paramètres
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET min_balance_alert = :min_balance_alert,
                      critical_balance_alert = :critical_balance_alert,
                      forecast_horizon_days = :forecast_horizon_days,
                      alert_email_enabled = :alert_email_enabled,
                      alert_email_recipients = :alert_email_recipients,
                      working_capital_target = :working_capital_target
                  WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->min_balance_alert = floatval($this->min_balance_alert ?? 5000);
        $this->critical_balance_alert = floatval($this->critical_balance_alert ?? 1000);
        $this->forecast_horizon_days = intval($this->forecast_horizon_days ?? 90);
        $this->alert_email_enabled = $this->alert_email_enabled ?? 1;
        $this->alert_email_recipients = htmlspecialchars(strip_tags($this->alert_email_recipients ?? ''));
        $this->working_capital_target = $this->working_capital_target ? floatval($this->working_capital_target) : null;

        // Bind values
        $stmt->bindParam(":min_balance_alert", $this->min_balance_alert);
        $stmt->bindParam(":critical_balance_alert", $this->critical_balance_alert);
        $stmt->bindParam(":forecast_horizon_days", $this->forecast_horizon_days);
        $stmt->bindParam(":alert_email_enabled", $this->alert_email_enabled);
        $stmt->bindParam(":alert_email_recipients", $this->alert_email_recipients);
        $stmt->bindParam(":working_capital_target", $this->working_capital_target);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Récupérer les paramètres pour une entreprise
     */
    public function getByCompany($company_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si aucun paramètre n'existe, créer les paramètres par défaut
        if (!$row) {
            $this->company_id = $company_id;
            $this->min_balance_alert = 5000.00;
            $this->critical_balance_alert = 1000.00;
            $this->forecast_horizon_days = 90;
            $this->alert_email_enabled = 1;
            $this->alert_email_recipients = '';
            $this->working_capital_target = null;

            if ($this->create()) {
                return $this->getByCompany($company_id);
            }
            return null;
        }

        return $row;
    }

    /**
     * Sauvegarder ou mettre à jour les paramètres
     */
    public function saveSettings($company_id, $settings) {
        // Vérifier si les paramètres existent déjà
        $existing = $this->getByCompany($company_id);

        $this->company_id = $company_id;
        $this->min_balance_alert = $settings['min_balance_alert'] ?? 5000;
        $this->critical_balance_alert = $settings['critical_balance_alert'] ?? 1000;
        $this->forecast_horizon_days = $settings['forecast_horizon_days'] ?? 90;
        $this->alert_email_enabled = isset($settings['alert_email_enabled']) ? 1 : 0;
        $this->alert_email_recipients = $settings['alert_email_recipients'] ?? '';
        $this->working_capital_target = $settings['working_capital_target'] ?? null;

        if ($existing) {
            return $this->update();
        } else {
            return $this->create();
        }
    }
}
?>
