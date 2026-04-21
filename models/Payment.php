<?php
/**
 * Modèle: Payment
 * Description: Gestion des paiements (fournisseurs et clients)
 * Version: 1.0
 */

class Payment {
    private $conn;
    private $table_name = "payments";

    // Propriétés
    public $id;
    public $company_id;
    public $payment_date;
    public $amount;
    public $currency;
    public $payment_method;
    public $payment_type;
    public $reference;
    public $description;
    public $supplier_invoice_id;
    public $invoice_id;
    public $bank_account_id;
    public $contact_id;
    public $notes;
    public $receipt_path;
    public $created_by;
    public $created_at;
    public $updated_at;

    /**
     * Constructeur
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un paiement
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    payment_date = :payment_date,
                    amount = :amount,
                    currency = :currency,
                    payment_method = :payment_method,
                    payment_type = :payment_type,
                    reference = :reference,
                    description = :description,
                    supplier_invoice_id = :supplier_invoice_id,
                    invoice_id = :invoice_id,
                    bank_account_id = :bank_account_id,
                    contact_id = :contact_id,
                    notes = :notes,
                    created_by = :created_by";

        $stmt = $this->conn->prepare($query);

        // Bind
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":payment_date", $this->payment_date);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":payment_type", $this->payment_type);
        $stmt->bindParam(":reference", $this->reference);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":supplier_invoice_id", $this->supplier_invoice_id);
        $stmt->bindParam(":invoice_id", $this->invoice_id);
        $stmt->bindParam(":bank_account_id", $this->bank_account_id);
        $stmt->bindParam(":contact_id", $this->contact_id);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":created_by", $this->created_by);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Lire un paiement
     */
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->payment_date = $row['payment_date'];
            $this->amount = $row['amount'];
            $this->currency = $row['currency'];
            $this->payment_method = $row['payment_method'];
            $this->payment_type = $row['payment_type'];
            $this->reference = $row['reference'];
            $this->description = $row['description'];
            $this->supplier_invoice_id = $row['supplier_invoice_id'];
            $this->invoice_id = $row['invoice_id'];
            $this->bank_account_id = $row['bank_account_id'];
            $this->contact_id = $row['contact_id'];
            $this->notes = $row['notes'];
            $this->receipt_path = $row['receipt_path'];
            $this->created_by = $row['created_by'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];

            return true;
        }

        return false;
    }

    /**
     * Lister les paiements
     */
    public function readByCompany($company_id, $filters = []) {
        $query = "SELECT p.*, c.name as contact_name,
                    ba.name as bank_account_name
                FROM " . $this->table_name . " p
                LEFT JOIN contacts c ON p.contact_id = c.id
                LEFT JOIN bank_accounts ba ON p.bank_account_id = ba.id
                WHERE p.company_id = :company_id";

        // Filtres
        if (!empty($filters['payment_type'])) {
            $query .= " AND p.payment_type = :payment_type";
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND p.payment_date >= :date_from";
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND p.payment_date <= :date_to";
        }

        $query .= " ORDER BY p.payment_date DESC, p.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if (!empty($filters['payment_type'])) {
            $stmt->bindParam(":payment_type", $filters['payment_type']);
        }

        if (!empty($filters['date_from'])) {
            $stmt->bindParam(":date_from", $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $stmt->bindParam(":date_to", $filters['date_to']);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprimer un paiement
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
     * Obtenir l'historique des paiements pour une facture fournisseur
     */
    public function getBySupplierInvoice($supplier_invoice_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE supplier_invoice_id = :supplier_invoice_id
                ORDER BY payment_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":supplier_invoice_id", $supplier_invoice_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir les statistiques de paiement
     */
    public function getStatistics($company_id, $period_days = 30) {
        $query = "SELECT
                    COUNT(*) as total_payments,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN payment_type = 'supplier_payment' THEN amount ELSE 0 END) as supplier_payments,
                    SUM(CASE WHEN payment_type = 'customer_payment' THEN amount ELSE 0 END) as customer_payments,
                    COUNT(CASE WHEN payment_date >= DATE_SUB(CURDATE(), INTERVAL :period_days DAY) THEN 1 END) as recent_count,
                    SUM(CASE WHEN payment_date >= DATE_SUB(CURDATE(), INTERVAL :period_days DAY) THEN amount ELSE 0 END) as recent_amount
                FROM " . $this->table_name . "
                WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":period_days", $period_days);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
