<?php
/**
 * Modèle Payroll
 * Gestion des fiches de paie et calculs des cotisations sociales
 */

class Payroll {
    private $conn;
    private $table_name = "payroll";

    // Propriétés de la fiche de paie
    public $id;
    public $company_id;
    public $employee_id;

    // Période
    public $period_month;
    public $period_year;
    public $payment_date;

    // Salaire de base
    public $base_salary;
    public $hours_worked;
    public $hourly_rate;

    // Éléments additionnels
    public $overtime_hours;
    public $overtime_amount;
    public $bonus;
    public $commission;
    public $allowances;
    public $other_additions;

    // Brut
    public $gross_salary;

    // Cotisations employé
    public $avs_ai_apg_employee;
    public $ac_employee;
    public $lpp_employee;
    public $laa_employee;
    public $laac_employee;

    // Impôts
    public $income_tax;

    // Autres déductions
    public $other_deductions;
    public $total_deductions;

    // Net
    public $net_salary;

    // Charges patronales
    public $avs_ai_apg_employer;
    public $ac_employer;
    public $lpp_employer;
    public $af_employer;
    public $other_employer_charges;
    public $total_employer_charges;

    // Statut et métadonnées
    public $status;
    public $pdf_path;
    public $notes;
    public $transaction_id;

    public $created_at;
    public $updated_at;
    public $created_by;
    public $validated_at;
    public $paid_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une nouvelle fiche de paie
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    employee_id = :employee_id,
                    period_month = :period_month,
                    period_year = :period_year,
                    payment_date = :payment_date,
                    base_salary = :base_salary,
                    hours_worked = :hours_worked,
                    hourly_rate = :hourly_rate,
                    overtime_hours = :overtime_hours,
                    overtime_amount = :overtime_amount,
                    bonus = :bonus,
                    commission = :commission,
                    allowances = :allowances,
                    other_additions = :other_additions,
                    gross_salary = :gross_salary,
                    avs_ai_apg_employee = :avs_ai_apg_employee,
                    ac_employee = :ac_employee,
                    lpp_employee = :lpp_employee,
                    laa_employee = :laa_employee,
                    laac_employee = :laac_employee,
                    income_tax = :income_tax,
                    other_deductions = :other_deductions,
                    total_deductions = :total_deductions,
                    net_salary = :net_salary,
                    avs_ai_apg_employer = :avs_ai_apg_employer,
                    ac_employer = :ac_employer,
                    lpp_employer = :lpp_employer,
                    af_employer = :af_employer,
                    other_employer_charges = :other_employer_charges,
                    total_employer_charges = :total_employer_charges,
                    status = :status,
                    notes = :notes,
                    created_by = :created_by";

        $stmt = $this->conn->prepare($query);

        // Binding
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":employee_id", $this->employee_id);
        $stmt->bindParam(":period_month", $this->period_month);
        $stmt->bindParam(":period_year", $this->period_year);
        $stmt->bindParam(":payment_date", $this->payment_date);
        $stmt->bindParam(":base_salary", $this->base_salary);
        $stmt->bindParam(":hours_worked", $this->hours_worked);
        $stmt->bindParam(":hourly_rate", $this->hourly_rate);
        $stmt->bindParam(":overtime_hours", $this->overtime_hours);
        $stmt->bindParam(":overtime_amount", $this->overtime_amount);
        $stmt->bindParam(":bonus", $this->bonus);
        $stmt->bindParam(":commission", $this->commission);
        $stmt->bindParam(":allowances", $this->allowances);
        $stmt->bindParam(":other_additions", $this->other_additions);
        $stmt->bindParam(":gross_salary", $this->gross_salary);
        $stmt->bindParam(":avs_ai_apg_employee", $this->avs_ai_apg_employee);
        $stmt->bindParam(":ac_employee", $this->ac_employee);
        $stmt->bindParam(":lpp_employee", $this->lpp_employee);
        $stmt->bindParam(":laa_employee", $this->laa_employee);
        $stmt->bindParam(":laac_employee", $this->laac_employee);
        $stmt->bindParam(":income_tax", $this->income_tax);
        $stmt->bindParam(":other_deductions", $this->other_deductions);
        $stmt->bindParam(":total_deductions", $this->total_deductions);
        $stmt->bindParam(":net_salary", $this->net_salary);
        $stmt->bindParam(":avs_ai_apg_employer", $this->avs_ai_apg_employer);
        $stmt->bindParam(":ac_employer", $this->ac_employer);
        $stmt->bindParam(":lpp_employer", $this->lpp_employer);
        $stmt->bindParam(":af_employer", $this->af_employer);
        $stmt->bindParam(":other_employer_charges", $this->other_employer_charges);
        $stmt->bindParam(":total_employer_charges", $this->total_employer_charges);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":created_by", $this->created_by);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Lire une fiche de paie
     */
    public function read() {
        $query = "SELECT p.*,
                         e.first_name, e.last_name, e.employee_number,
                         e.avs_number, e.iban, e.bank_name
                  FROM " . $this->table_name . " p
                  LEFT JOIN employees e ON p.employee_id = e.id
                  WHERE p.id = :id AND p.company_id = :company_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lire toutes les fiches de paie d'une société
     */
    public function readByCompany($company_id, $filters = []) {
        $query = "SELECT p.*,
                         e.first_name, e.last_name, e.employee_number
                  FROM " . $this->table_name . " p
                  LEFT JOIN employees e ON p.employee_id = e.id
                  WHERE p.company_id = :company_id";

        // Filtres optionnels
        if(isset($filters['employee_id'])) {
            $query .= " AND p.employee_id = :employee_id";
        }
        if(isset($filters['period_year'])) {
            $query .= " AND p.period_year = :period_year";
        }
        if(isset($filters['period_month'])) {
            $query .= " AND p.period_month = :period_month";
        }
        if(isset($filters['status'])) {
            $query .= " AND p.status = :status";
        }

        $query .= " ORDER BY p.period_year DESC, p.period_month DESC, e.last_name, e.first_name";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if(isset($filters['employee_id'])) {
            $stmt->bindParam(":employee_id", $filters['employee_id']);
        }
        if(isset($filters['period_year'])) {
            $stmt->bindParam(":period_year", $filters['period_year']);
        }
        if(isset($filters['period_month'])) {
            $stmt->bindParam(":period_month", $filters['period_month']);
        }
        if(isset($filters['status'])) {
            $stmt->bindParam(":status", $filters['status']);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mettre à jour une fiche de paie
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET payment_date = :payment_date,
                    base_salary = :base_salary,
                    hours_worked = :hours_worked,
                    hourly_rate = :hourly_rate,
                    overtime_hours = :overtime_hours,
                    overtime_amount = :overtime_amount,
                    bonus = :bonus,
                    commission = :commission,
                    allowances = :allowances,
                    other_additions = :other_additions,
                    gross_salary = :gross_salary,
                    avs_ai_apg_employee = :avs_ai_apg_employee,
                    ac_employee = :ac_employee,
                    lpp_employee = :lpp_employee,
                    laa_employee = :laa_employee,
                    laac_employee = :laac_employee,
                    income_tax = :income_tax,
                    other_deductions = :other_deductions,
                    total_deductions = :total_deductions,
                    net_salary = :net_salary,
                    avs_ai_apg_employer = :avs_ai_apg_employer,
                    ac_employer = :ac_employer,
                    lpp_employer = :lpp_employer,
                    af_employer = :af_employer,
                    other_employer_charges = :other_employer_charges,
                    total_employer_charges = :total_employer_charges,
                    notes = :notes
                WHERE id = :id AND company_id = :company_id
                AND status = 'draft'";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":payment_date", $this->payment_date);
        $stmt->bindParam(":base_salary", $this->base_salary);
        $stmt->bindParam(":hours_worked", $this->hours_worked);
        $stmt->bindParam(":hourly_rate", $this->hourly_rate);
        $stmt->bindParam(":overtime_hours", $this->overtime_hours);
        $stmt->bindParam(":overtime_amount", $this->overtime_amount);
        $stmt->bindParam(":bonus", $this->bonus);
        $stmt->bindParam(":commission", $this->commission);
        $stmt->bindParam(":allowances", $this->allowances);
        $stmt->bindParam(":other_additions", $this->other_additions);
        $stmt->bindParam(":gross_salary", $this->gross_salary);
        $stmt->bindParam(":avs_ai_apg_employee", $this->avs_ai_apg_employee);
        $stmt->bindParam(":ac_employee", $this->ac_employee);
        $stmt->bindParam(":lpp_employee", $this->lpp_employee);
        $stmt->bindParam(":laa_employee", $this->laa_employee);
        $stmt->bindParam(":laac_employee", $this->laac_employee);
        $stmt->bindParam(":income_tax", $this->income_tax);
        $stmt->bindParam(":other_deductions", $this->other_deductions);
        $stmt->bindParam(":total_deductions", $this->total_deductions);
        $stmt->bindParam(":net_salary", $this->net_salary);
        $stmt->bindParam(":avs_ai_apg_employer", $this->avs_ai_apg_employer);
        $stmt->bindParam(":ac_employer", $this->ac_employer);
        $stmt->bindParam(":lpp_employer", $this->lpp_employer);
        $stmt->bindParam(":af_employer", $this->af_employer);
        $stmt->bindParam(":other_employer_charges", $this->other_employer_charges);
        $stmt->bindParam(":total_employer_charges", $this->total_employer_charges);
        $stmt->bindParam(":notes", $this->notes);

        return $stmt->execute();
    }

    /**
     * Supprimer une fiche de paie (seulement si draft)
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . "
                  WHERE id = :id AND company_id = :company_id AND status = 'draft'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Valider une fiche de paie
     */
    public function validate() {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'validated',
                      validated_at = NOW()
                  WHERE id = :id AND company_id = :company_id AND status = 'draft'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Marquer comme payé
     */
    public function markAsPaid($transaction_id = null) {
        $query = "UPDATE " . $this->table_name . "
                  SET status = 'paid',
                      paid_at = NOW(),
                      transaction_id = :transaction_id
                  WHERE id = :id AND company_id = :company_id AND status = 'validated'";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":transaction_id", $transaction_id);

        return $stmt->execute();
    }

    /**
     * Calculer le salaire brut total
     */
    public function calculateGrossSalary() {
        $this->gross_salary =
            floatval($this->base_salary) +
            floatval($this->overtime_amount) +
            floatval($this->bonus) +
            floatval($this->commission) +
            floatval($this->allowances) +
            floatval($this->other_additions);

        return $this->gross_salary;
    }

    /**
     * Calculer les cotisations sociales suisses
     */
    public function calculateSocialContributions($settings) {
        $gross = $this->gross_salary;

        // AVS/AI/APG - Employé (5.3%)
        $this->avs_ai_apg_employee = round($gross * ($settings['avs_ai_apg_rate_employee'] / 100), 2);

        // AVS/AI/APG - Employeur (5.3%)
        $this->avs_ai_apg_employer = round($gross * ($settings['avs_ai_apg_rate_employer'] / 100), 2);

        // Assurance chômage - Employé (1.1%)
        $this->ac_employee = round($gross * ($settings['ac_rate_employee'] / 100), 2);

        // Assurance chômage - Employeur (1.1%)
        $this->ac_employer = round($gross * ($settings['ac_rate_employer'] / 100), 2);

        // LPP (2e pilier) - Si éligible
        $annual_salary = $gross * 12;
        if($annual_salary >= $settings['lpp_min_salary'] && $annual_salary <= $settings['lpp_max_salary']) {
            $this->lpp_employee = round($gross * ($settings['lpp_rate_employee'] / 100), 2);
            $this->lpp_employer = round($gross * ($settings['lpp_rate_employer'] / 100), 2);
        } else {
            $this->lpp_employee = 0;
            $this->lpp_employer = 0;
        }

        // LAA/LAAC - Employé
        $this->laa_employee = round($gross * ($settings['laa_rate'] / 100), 2);
        $this->laac_employee = round($gross * ($settings['laac_rate'] / 100), 2);

        // Allocations familiales - Employeur uniquement
        $this->af_employer = round($gross * ($settings['af_rate'] / 100), 2);

        // Total déductions employé
        $this->total_deductions =
            $this->avs_ai_apg_employee +
            $this->ac_employee +
            $this->lpp_employee +
            $this->laa_employee +
            $this->laac_employee +
            floatval($this->income_tax) +
            floatval($this->other_deductions);

        // Total charges employeur
        $this->total_employer_charges =
            $this->avs_ai_apg_employer +
            $this->ac_employer +
            $this->lpp_employer +
            $this->af_employer +
            floatval($this->other_employer_charges);

        // Salaire net
        $this->net_salary = $this->gross_salary - $this->total_deductions;

        return true;
    }

    /**
     * Vérifier si une fiche de paie existe déjà pour cette période
     */
    public function exists($company_id, $employee_id, $month, $year) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  AND employee_id = :employee_id
                  AND period_month = :month
                  AND period_year = :year";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":employee_id", $employee_id);
        $stmt->bindParam(":month", $month);
        $stmt->bindParam(":year", $year);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'] > 0;
    }

    /**
     * Obtenir les statistiques de paie pour une société
     */
    public function getCompanyStatistics($company_id, $year) {
        $query = "SELECT
                    COUNT(*) as total_payrolls,
                    COUNT(DISTINCT employee_id) as total_employees,
                    SUM(gross_salary) as total_gross,
                    SUM(net_salary) as total_net,
                    SUM(total_employer_charges) as total_charges,
                    AVG(net_salary) as avg_net
                  FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  AND period_year = :year
                  AND status IN ('validated', 'paid')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":year", $year);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
