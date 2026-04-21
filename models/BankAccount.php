<?php
/**
 * Model: BankAccount
 * Purpose: Manage company bank accounts for reconciliation
 *
 * Features:
 * - Multi-currency support (CHF, EUR, USD)
 * - IBAN validation for Swiss format
 * - Balance tracking with opening/current balances
 * - Active/inactive account management
 */

class BankAccount {
    private $conn;
    private $table_name = "bank_accounts";

    // Properties mapping to database columns
    public $id;
    public $company_id;
    public $name;
    public $bank_name;
    public $iban;
    public $account_number;
    public $currency;
    public $opening_balance;
    public $opening_balance_date;
    public $current_balance;
    public $last_reconciliation_date;
    public $is_active;
    public $notes;
    public $created_at;
    public $updated_at;

    /**
     * Constructor with database connection injection
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new bank account
     * @return bool Success status
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    name = :name,
                    bank_name = :bank_name,
                    iban = :iban,
                    account_number = :account_number,
                    currency = :currency,
                    opening_balance = :opening_balance,
                    opening_balance_date = :opening_balance_date,
                    current_balance = :current_balance,
                    is_active = :is_active,
                    notes = :notes";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->bank_name = htmlspecialchars(strip_tags($this->bank_name ?? ''));
        $this->iban = $this->formatIBAN($this->iban ?? '');
        $this->account_number = htmlspecialchars(strip_tags($this->account_number ?? ''));
        $this->currency = strtoupper(htmlspecialchars(strip_tags($this->currency ?? 'CHF')));
        $this->notes = htmlspecialchars(strip_tags($this->notes ?? ''));

        // Set current_balance to opening_balance initially
        if (!isset($this->current_balance)) {
            $this->current_balance = $this->opening_balance ?? 0.00;
        }

        // Bind parameters
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":bank_name", $this->bank_name);
        $stmt->bindParam(":iban", $this->iban);
        $stmt->bindParam(":account_number", $this->account_number);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":opening_balance", $this->opening_balance);
        $stmt->bindParam(":opening_balance_date", $this->opening_balance_date);
        $stmt->bindParam(":current_balance", $this->current_balance);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":notes", $this->notes);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Read single bank account by ID
     * @return bool Success status
     */
    public function read() {
        $query = "SELECT *
                FROM " . $this->table_name . "
                WHERE id = :id AND company_id = :company_id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->name = $row['name'];
            $this->bank_name = $row['bank_name'];
            $this->iban = $row['iban'];
            $this->account_number = $row['account_number'];
            $this->currency = $row['currency'];
            $this->opening_balance = $row['opening_balance'];
            $this->opening_balance_date = $row['opening_balance_date'];
            $this->current_balance = $row['current_balance'];
            $this->last_reconciliation_date = $row['last_reconciliation_date'];
            $this->is_active = $row['is_active'];
            $this->notes = $row['notes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return true;
        }

        return false;
    }

    /**
     * Get all bank accounts for a company
     * @param int $company_id Company ID
     * @param bool $active_only If true, return only active accounts
     * @return array List of bank accounts
     */
    public function readByCompany($company_id, $active_only = false) {
        $query = "SELECT *
                FROM " . $this->table_name . "
                WHERE company_id = :company_id";

        if ($active_only) {
            $query .= " AND is_active = 1";
        }

        $query .= " ORDER BY is_active DESC, name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Update bank account
     * @return bool Success status
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET name = :name,
                    bank_name = :bank_name,
                    iban = :iban,
                    account_number = :account_number,
                    currency = :currency,
                    opening_balance = :opening_balance,
                    opening_balance_date = :opening_balance_date,
                    is_active = :is_active,
                    notes = :notes
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->bank_name = htmlspecialchars(strip_tags($this->bank_name ?? ''));
        $this->iban = $this->formatIBAN($this->iban ?? '');
        $this->account_number = htmlspecialchars(strip_tags($this->account_number ?? ''));
        $this->currency = strtoupper(htmlspecialchars(strip_tags($this->currency)));
        $this->notes = htmlspecialchars(strip_tags($this->notes ?? ''));

        // Bind
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":bank_name", $this->bank_name);
        $stmt->bindParam(":iban", $this->iban);
        $stmt->bindParam(":account_number", $this->account_number);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":opening_balance", $this->opening_balance);
        $stmt->bindParam(":opening_balance_date", $this->opening_balance_date);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":notes", $this->notes);

        return $stmt->execute();
    }

    /**
     * Update current balance
     * @param float $new_balance New balance amount
     * @param string $reconciliation_date Date of reconciliation (Y-m-d)
     * @return bool Success status
     */
    public function updateBalance($new_balance, $reconciliation_date = null) {
        $query = "UPDATE " . $this->table_name . "
                SET current_balance = :current_balance,
                    last_reconciliation_date = :reconciliation_date
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        if ($reconciliation_date === null) {
            $reconciliation_date = date('Y-m-d');
        }

        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":current_balance", $new_balance);
        $stmt->bindParam(":reconciliation_date", $reconciliation_date);

        if ($stmt->execute()) {
            $this->current_balance = $new_balance;
            $this->last_reconciliation_date = $reconciliation_date;
            return true;
        }

        return false;
    }

    /**
     * Delete bank account (soft delete by setting inactive)
     * @return bool Success status
     */
    public function delete() {
        // Soft delete: set is_active = 0
        $query = "UPDATE " . $this->table_name . "
                SET is_active = 0
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Hard delete bank account (permanent)
     * WARNING: This will cascade delete all bank_transactions
     * @return bool Success status
     */
    public function hardDelete() {
        $query = "DELETE FROM " . $this->table_name . "
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Get account statistics
     * @param int $company_id Company ID
     * @return array Statistics with total accounts, active, total balance
     */
    public function getStatistics($company_id) {
        $query = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active,
                    SUM(CASE WHEN is_active = 1 THEN current_balance ELSE 0 END) as total_balance,
                    COUNT(DISTINCT currency) as currencies
                FROM " . $this->table_name . "
                WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int)($stats['total'] ?? 0),
            'active' => (int)($stats['active'] ?? 0),
            'inactive' => (int)($stats['total'] ?? 0) - (int)($stats['active'] ?? 0),
            'total_balance' => (float)($stats['total_balance'] ?? 0),
            'currencies' => (int)($stats['currencies'] ?? 0)
        ];
    }

    /**
     * Format IBAN with proper spacing
     * Swiss IBAN format: CH44 3199 9123 0008 8901 2 (groups of 4)
     * @param string $iban Raw IBAN
     * @return string Formatted IBAN
     */
    private function formatIBAN($iban) {
        // Remove all spaces and convert to uppercase
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        // Add spaces every 4 characters
        return trim(chunk_split($iban, 4, ' '));
    }

    /**
     * Validate Swiss IBAN format
     * @param string $iban IBAN to validate
     * @return bool Valid or not
     */
    public function validateSwissIBAN($iban) {
        // Remove spaces
        $iban = preg_replace('/\s+/', '', $iban);

        // Swiss IBAN must start with CH and be 21 characters
        if (!preg_match('/^CH\d{19}$/', $iban)) {
            return false;
        }

        // Modulo 97 checksum (ISO 13616)
        // Move first 4 chars to end, replace letters with digits (A=10, B=11, …)
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string)(ord(strtoupper($char)) - 55) : $char;
        }
        // bcmod handles arbitrarily large integers
        return bcmod($numeric, '97') === '1';
    }

    /**
     * Get account balance history
     * @param string $start_date Start date (Y-m-d)
     * @param string $end_date End date (Y-m-d)
     * @return array Balance progression
     */
    public function getBalanceHistory($start_date, $end_date) {
        $query = "SELECT
                    transaction_date,
                    balance_after
                FROM bank_transactions
                WHERE bank_account_id = :account_id
                  AND transaction_date BETWEEN :start_date AND :end_date
                  AND balance_after IS NOT NULL
                ORDER BY transaction_date ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":account_id", $this->id);
        $stmt->bindParam(":start_date", $start_date);
        $stmt->bindParam(":end_date", $end_date);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate balance from transactions
     * @return float Calculated balance
     */
    public function calculateBalance() {
        $query = "SELECT SUM(amount) as total_movements
                FROM bank_transactions
                WHERE bank_account_id = :account_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":account_id", $this->id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $movements = (float)($result['total_movements'] ?? 0);

        return (float)$this->opening_balance + $movements;
    }
}
?>
