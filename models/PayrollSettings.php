<?php
/**
 * Modèle PayrollSettings
 * Paramètres de paie et taux de cotisations sociales par société
 */

class PayrollSettings {
    private $conn;
    private $table_name = "payroll_settings";

    public $id;
    public $company_id;

    // Taux AVS/AI/APG
    public $avs_ai_apg_rate_employee;
    public $avs_ai_apg_rate_employer;

    // Taux AC (Assurance chômage)
    public $ac_rate_employee;
    public $ac_rate_employer;
    public $ac_solidarity_rate;
    public $ac_threshold;

    // Taux LPP (2e pilier)
    public $lpp_rate_employee;
    public $lpp_rate_employer;
    public $lpp_min_salary;
    public $lpp_max_salary;

    // Allocations familiales
    public $af_rate;
    public $af_amount_per_child;

    // Accident
    public $laa_rate;
    public $laac_rate;

    // Comptes comptables
    public $salary_expense_account;
    public $social_charges_account;
    public $salary_payable_account;

    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer les paramètres par défaut pour une société
     */
    public function createDefault($company_id) {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    avs_ai_apg_rate_employee = 5.30,
                    avs_ai_apg_rate_employer = 5.30,
                    ac_rate_employee = 1.10,
                    ac_rate_employer = 1.10,
                    ac_solidarity_rate = 0.50,
                    ac_threshold = 148200.00,
                    lpp_rate_employee = 7.00,
                    lpp_rate_employer = 7.00,
                    lpp_min_salary = 21510.00,
                    lpp_max_salary = 86040.00,
                    af_rate = 2.00,
                    af_amount_per_child = 200.00,
                    laa_rate = 1.00,
                    laac_rate = 2.00
                ON DUPLICATE KEY UPDATE company_id = company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        return $stmt->execute();
    }

    /**
     * Lire les paramètres d'une société
     */
    public function readByCompany($company_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si pas de paramètres, créer les paramètres par défaut
        if(!$row) {
            $this->createDefault($company_id);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $row;
    }

    /**
     * Mettre à jour les paramètres
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET avs_ai_apg_rate_employee = :avs_ai_apg_rate_employee,
                    avs_ai_apg_rate_employer = :avs_ai_apg_rate_employer,
                    ac_rate_employee = :ac_rate_employee,
                    ac_rate_employer = :ac_rate_employer,
                    ac_solidarity_rate = :ac_solidarity_rate,
                    ac_threshold = :ac_threshold,
                    lpp_rate_employee = :lpp_rate_employee,
                    lpp_rate_employer = :lpp_rate_employer,
                    lpp_min_salary = :lpp_min_salary,
                    lpp_max_salary = :lpp_max_salary,
                    af_rate = :af_rate,
                    af_amount_per_child = :af_amount_per_child,
                    laa_rate = :laa_rate,
                    laac_rate = :laac_rate,
                    salary_expense_account = :salary_expense_account,
                    social_charges_account = :social_charges_account,
                    salary_payable_account = :salary_payable_account
                WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":avs_ai_apg_rate_employee", $this->avs_ai_apg_rate_employee);
        $stmt->bindParam(":avs_ai_apg_rate_employer", $this->avs_ai_apg_rate_employer);
        $stmt->bindParam(":ac_rate_employee", $this->ac_rate_employee);
        $stmt->bindParam(":ac_rate_employer", $this->ac_rate_employer);
        $stmt->bindParam(":ac_solidarity_rate", $this->ac_solidarity_rate);
        $stmt->bindParam(":ac_threshold", $this->ac_threshold);
        $stmt->bindParam(":lpp_rate_employee", $this->lpp_rate_employee);
        $stmt->bindParam(":lpp_rate_employer", $this->lpp_rate_employer);
        $stmt->bindParam(":lpp_min_salary", $this->lpp_min_salary);
        $stmt->bindParam(":lpp_max_salary", $this->lpp_max_salary);
        $stmt->bindParam(":af_rate", $this->af_rate);
        $stmt->bindParam(":af_amount_per_child", $this->af_amount_per_child);
        $stmt->bindParam(":laa_rate", $this->laa_rate);
        $stmt->bindParam(":laac_rate", $this->laac_rate);
        $stmt->bindParam(":salary_expense_account", $this->salary_expense_account);
        $stmt->bindParam(":social_charges_account", $this->social_charges_account);
        $stmt->bindParam(":salary_payable_account", $this->salary_payable_account);

        return $stmt->execute();
    }

    /**
     * Obtenir les taux par défaut pour la Suisse (2024)
     */
    public static function getSwissDefaults() {
        return [
            'avs_ai_apg_rate_employee' => 5.30,
            'avs_ai_apg_rate_employer' => 5.30,
            'ac_rate_employee' => 1.10,
            'ac_rate_employer' => 1.10,
            'ac_solidarity_rate' => 0.50,
            'ac_threshold' => 148200.00,
            'lpp_rate_employee' => 7.00,
            'lpp_rate_employer' => 7.00,
            'lpp_min_salary' => 21510.00,
            'lpp_max_salary' => 86040.00,
            'af_rate' => 2.00,
            'af_amount_per_child' => 200.00,
            'laa_rate' => 1.00,
            'laac_rate' => 2.00
        ];
    }
}
?>
