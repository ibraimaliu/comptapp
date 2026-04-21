<?php
/**
 * Modèle Employee
 * Gestion des employés pour le module de paie
 */

class Employee {
    private $conn;
    private $table_name = "employees";

    // Propriétés de l'employé
    public $id;
    public $company_id;
    public $employee_number;
    public $first_name;
    public $last_name;
    public $email;
    public $phone;
    public $address;
    public $postal_code;
    public $city;
    public $country;

    // Informations contractuelles
    public $hire_date;
    public $termination_date;
    public $job_title;
    public $department;
    public $employment_type;
    public $contract_type;

    // Informations salariales
    public $salary_type;
    public $base_salary;
    public $currency;
    public $hours_per_week;

    // Informations AVS/AI/APG
    public $avs_number;
    public $accident_insurance;
    public $pension_fund;

    // Informations bancaires
    public $iban;
    public $bank_name;

    // Déductions et allocations
    public $family_allowances;
    public $num_children;

    // Statut
    public $is_active;
    public $notes;

    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un nouvel employé
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    employee_number = :employee_number,
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    postal_code = :postal_code,
                    city = :city,
                    country = :country,
                    hire_date = :hire_date,
                    termination_date = :termination_date,
                    job_title = :job_title,
                    department = :department,
                    employment_type = :employment_type,
                    contract_type = :contract_type,
                    salary_type = :salary_type,
                    base_salary = :base_salary,
                    currency = :currency,
                    hours_per_week = :hours_per_week,
                    avs_number = :avs_number,
                    accident_insurance = :accident_insurance,
                    pension_fund = :pension_fund,
                    iban = :iban,
                    bank_name = :bank_name,
                    family_allowances = :family_allowances,
                    num_children = :num_children,
                    is_active = :is_active,
                    notes = :notes";

        $stmt = $this->conn->prepare($query);

        // Nettoyage des données
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email ?? ''));
        $this->job_title = htmlspecialchars(strip_tags($this->job_title));

        // Binding
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":employee_number", $this->employee_number);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":postal_code", $this->postal_code);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":country", $this->country);
        $stmt->bindParam(":hire_date", $this->hire_date);
        $stmt->bindParam(":termination_date", $this->termination_date);
        $stmt->bindParam(":job_title", $this->job_title);
        $stmt->bindParam(":department", $this->department);
        $stmt->bindParam(":employment_type", $this->employment_type);
        $stmt->bindParam(":contract_type", $this->contract_type);
        $stmt->bindParam(":salary_type", $this->salary_type);
        $stmt->bindParam(":base_salary", $this->base_salary);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":hours_per_week", $this->hours_per_week);
        $stmt->bindParam(":avs_number", $this->avs_number);
        $stmt->bindParam(":accident_insurance", $this->accident_insurance);
        $stmt->bindParam(":pension_fund", $this->pension_fund);
        $stmt->bindParam(":iban", $this->iban);
        $stmt->bindParam(":bank_name", $this->bank_name);
        $stmt->bindParam(":family_allowances", $this->family_allowances);
        $stmt->bindParam(":num_children", $this->num_children);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":notes", $this->notes);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Lire un employé par ID
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
            $this->employee_number = $row['employee_number'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->email = $row['email'];
            $this->phone = $row['phone'];
            $this->address = $row['address'];
            $this->postal_code = $row['postal_code'];
            $this->city = $row['city'];
            $this->country = $row['country'];
            $this->hire_date = $row['hire_date'];
            $this->termination_date = $row['termination_date'];
            $this->job_title = $row['job_title'];
            $this->department = $row['department'];
            $this->employment_type = $row['employment_type'];
            $this->contract_type = $row['contract_type'];
            $this->salary_type = $row['salary_type'];
            $this->base_salary = $row['base_salary'];
            $this->currency = $row['currency'];
            $this->hours_per_week = $row['hours_per_week'];
            $this->avs_number = $row['avs_number'];
            $this->accident_insurance = $row['accident_insurance'];
            $this->pension_fund = $row['pension_fund'];
            $this->iban = $row['iban'];
            $this->bank_name = $row['bank_name'];
            $this->family_allowances = $row['family_allowances'];
            $this->num_children = $row['num_children'];
            $this->is_active = $row['is_active'];
            $this->notes = $row['notes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];

            return true;
        }

        return false;
    }

    /**
     * Lire tous les employés d'une société
     */
    public function readByCompany($company_id, $active_only = false) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE company_id = :company_id";

        if($active_only) {
            $query .= " AND is_active = 1";
        }

        $query .= " ORDER BY last_name, first_name";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compter les employés actifs d'une société
     */
    public function countActiveByCompany($company_id) {
        $query = "SELECT COUNT(*) as total
                  FROM " . $this->table_name . "
                  WHERE company_id = :company_id AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    /**
     * Mettre à jour un employé
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET employee_number = :employee_number,
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    postal_code = :postal_code,
                    city = :city,
                    country = :country,
                    hire_date = :hire_date,
                    termination_date = :termination_date,
                    job_title = :job_title,
                    department = :department,
                    employment_type = :employment_type,
                    contract_type = :contract_type,
                    salary_type = :salary_type,
                    base_salary = :base_salary,
                    currency = :currency,
                    hours_per_week = :hours_per_week,
                    avs_number = :avs_number,
                    accident_insurance = :accident_insurance,
                    pension_fund = :pension_fund,
                    iban = :iban,
                    bank_name = :bank_name,
                    family_allowances = :family_allowances,
                    num_children = :num_children,
                    is_active = :is_active,
                    notes = :notes
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        // Nettoyage
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email ?? ''));
        $this->job_title = htmlspecialchars(strip_tags($this->job_title));

        // Binding
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":employee_number", $this->employee_number);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":postal_code", $this->postal_code);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":country", $this->country);
        $stmt->bindParam(":hire_date", $this->hire_date);
        $stmt->bindParam(":termination_date", $this->termination_date);
        $stmt->bindParam(":job_title", $this->job_title);
        $stmt->bindParam(":department", $this->department);
        $stmt->bindParam(":employment_type", $this->employment_type);
        $stmt->bindParam(":contract_type", $this->contract_type);
        $stmt->bindParam(":salary_type", $this->salary_type);
        $stmt->bindParam(":base_salary", $this->base_salary);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":hours_per_week", $this->hours_per_week);
        $stmt->bindParam(":avs_number", $this->avs_number);
        $stmt->bindParam(":accident_insurance", $this->accident_insurance);
        $stmt->bindParam(":pension_fund", $this->pension_fund);
        $stmt->bindParam(":iban", $this->iban);
        $stmt->bindParam(":bank_name", $this->bank_name);
        $stmt->bindParam(":family_allowances", $this->family_allowances);
        $stmt->bindParam(":num_children", $this->num_children);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":notes", $this->notes);

        return $stmt->execute();
    }

    /**
     * Supprimer un employé
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
     * Désactiver un employé au lieu de le supprimer
     */
    public function deactivate() {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = 0,
                      termination_date = CURDATE()
                  WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Générer un numéro d'employé automatique
     */
    public function generateEmployeeNumber($company_id) {
        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . "
                  WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_number = $row['total'] + 1;

        return 'EMP-' . str_pad($next_number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Rechercher des employés
     */
    public function search($company_id, $search_term) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  AND (
                      first_name LIKE :search
                      OR last_name LIKE :search
                      OR employee_number LIKE :search
                      OR email LIKE :search
                      OR job_title LIKE :search
                  )
                  ORDER BY last_name, first_name";

        $stmt = $this->conn->prepare($query);
        $search_param = "%{$search_term}%";
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":search", $search_param);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir le nom complet
     */
    public function getFullName() {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Vérifier si l'employé est éligible à la LPP (2e pilier)
     * Salaire annuel >= 21,510 CHF (2024)
     */
    public function isLPPEligible() {
        $annual_salary = $this->base_salary * 12; // Si salaire mensuel
        if($this->salary_type == 'annual') {
            $annual_salary = $this->base_salary;
        }
        return $annual_salary >= 21510;
    }

    /**
     * Calculer le salaire annuel brut
     */
    public function getAnnualGrossSalary() {
        if($this->salary_type == 'annual') {
            return $this->base_salary;
        } elseif($this->salary_type == 'monthly') {
            return $this->base_salary * 12;
        } elseif($this->salary_type == 'hourly') {
            // 52 semaines * heures par semaine * taux horaire
            return 52 * $this->hours_per_week * $this->base_salary;
        }
        return 0;
    }
}
?>
