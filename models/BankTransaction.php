<?php
/**
 * Model: BankTransaction
 * Purpose: Manage imported bank transactions for reconciliation
 *
 * Features:
 * - Support for multiple import formats (Camt.053, MT940, CSV)
 * - QR-Reference matching for Swiss QR-Invoices
 * - Automatic and manual reconciliation status tracking
 * - Counterparty information storage
 */

class BankTransaction {
    private $conn;
    private $table_name = "bank_transactions";

    // Properties mapping to database columns
    public $id;
    public $bank_account_id;
    public $company_id;

    // Transaction identification
    public $transaction_date;
    public $value_date;
    public $booking_date;
    public $bank_reference;

    // Transaction details
    public $description;
    public $amount;
    public $currency;
    public $balance_after;

    // Counterparty
    public $counterparty_name;
    public $counterparty_account;
    public $counterparty_reference;

    // Swiss QR-Invoice
    public $qr_reference;
    public $structured_reference;

    // Reconciliation
    public $status; // pending, matched, manual, ignored
    public $matched_invoice_id;
    public $matched_transaction_id;
    public $reconciliation_date;
    public $reconciliation_user_id;

    // Import tracking
    public $import_batch_id;
    public $import_format; // camt053, mt940, csv, manual
    public $import_date;
    public $raw_data;

    // Metadata
    public $notes;
    public $created_at;
    public $updated_at;

    /**
     * Constructor
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create bank transaction
     * @return bool Success status
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET bank_account_id = :bank_account_id,
                    company_id = :company_id,
                    transaction_date = :transaction_date,
                    value_date = :value_date,
                    booking_date = :booking_date,
                    bank_reference = :bank_reference,
                    description = :description,
                    amount = :amount,
                    currency = :currency,
                    balance_after = :balance_after,
                    counterparty_name = :counterparty_name,
                    counterparty_account = :counterparty_account,
                    counterparty_reference = :counterparty_reference,
                    qr_reference = :qr_reference,
                    structured_reference = :structured_reference,
                    status = :status,
                    import_batch_id = :import_batch_id,
                    import_format = :import_format,
                    import_date = :import_date,
                    raw_data = :raw_data,
                    notes = :notes";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->bank_reference = htmlspecialchars(strip_tags($this->bank_reference ?? ''));
        $this->counterparty_name = htmlspecialchars(strip_tags($this->counterparty_name ?? ''));
        $this->counterparty_account = htmlspecialchars(strip_tags($this->counterparty_account ?? ''));
        $this->counterparty_reference = htmlspecialchars(strip_tags($this->counterparty_reference ?? ''));
        $this->qr_reference = htmlspecialchars(strip_tags($this->qr_reference ?? ''));
        $this->structured_reference = htmlspecialchars(strip_tags($this->structured_reference ?? ''));
        $this->notes = htmlspecialchars(strip_tags($this->notes ?? ''));
        $this->currency = strtoupper($this->currency ?? 'CHF');
        $this->status = $this->status ?? 'pending';

        // Bind
        $stmt->bindParam(":bank_account_id", $this->bank_account_id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":transaction_date", $this->transaction_date);
        $stmt->bindParam(":value_date", $this->value_date);
        $stmt->bindParam(":booking_date", $this->booking_date);
        $stmt->bindParam(":bank_reference", $this->bank_reference);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":balance_after", $this->balance_after);
        $stmt->bindParam(":counterparty_name", $this->counterparty_name);
        $stmt->bindParam(":counterparty_account", $this->counterparty_account);
        $stmt->bindParam(":counterparty_reference", $this->counterparty_reference);
        $stmt->bindParam(":qr_reference", $this->qr_reference);
        $stmt->bindParam(":structured_reference", $this->structured_reference);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":import_batch_id", $this->import_batch_id);
        $stmt->bindParam(":import_format", $this->import_format);
        $stmt->bindParam(":import_date", $this->import_date);
        $stmt->bindParam(":raw_data", $this->raw_data);
        $stmt->bindParam(":notes", $this->notes);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Read single transaction
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
            $this->populateFromRow($row);
            return true;
        }

        return false;
    }

    /**
     * Get transactions by bank account
     * @param int $bank_account_id Bank account ID
     * @param string $status Filter by status (optional)
     * @param int $limit Limit results (default 100)
     * @param int $offset Offset for pagination
     * @return array Transactions
     */
    public function readByBankAccount($bank_account_id, $status = null, $limit = 100, $offset = 0) {
        $query = "SELECT *
                FROM " . $this->table_name . "
                WHERE bank_account_id = :bank_account_id";

        if ($status !== null) {
            $query .= " AND status = :status";
        }

        $query .= " ORDER BY transaction_date DESC, id DESC
                    LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":bank_account_id", $bank_account_id, PDO::PARAM_INT);

        if ($status !== null) {
            $stmt->bindParam(":status", $status);
        }

        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get pending transactions (not reconciled)
     * @param int $company_id Company ID
     * @param int $bank_account_id Optional bank account filter
     * @return array Pending transactions
     */
    public function getPendingTransactions($company_id, $bank_account_id = null) {
        $query = "SELECT bt.*,
                         ba.name as account_name,
                         ba.currency as account_currency
                FROM " . $this->table_name . " bt
                INNER JOIN bank_accounts ba ON bt.bank_account_id = ba.id
                WHERE bt.company_id = :company_id
                  AND bt.status = 'pending'";

        if ($bank_account_id !== null) {
            $query .= " AND bt.bank_account_id = :bank_account_id";
        }

        $query .= " ORDER BY bt.transaction_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if ($bank_account_id !== null) {
            $stmt->bindParam(":bank_account_id", $bank_account_id);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Match transaction with invoice by QR-Reference
     * @param string $qr_reference QR-Reference to match
     * @return array|null Matched invoice or null
     */
    public function findInvoiceByQRReference($qr_reference) {
        // Remove spaces and validate format
        $qr_reference = preg_replace('/\s+/', '', $qr_reference);

        if (strlen($qr_reference) !== 27) {
            return null;
        }

        $query = "SELECT id, number, total, status
                FROM invoices
                WHERE qr_reference = :qr_reference
                  AND company_id = :company_id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":qr_reference", $qr_reference);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Match transaction with invoice by amount
     * @param float $amount Amount to match
     * @param float $tolerance Tolerance in CHF (default 0.01)
     * @return array Matched invoices
     */
    public function findInvoicesByAmount($amount, $tolerance = 0.01) {
        $query = "SELECT id, number, total, status, qr_reference
                FROM invoices
                WHERE company_id = :company_id
                  AND ABS(total - :amount) <= :tolerance
                  AND status IN ('sent', 'overdue')
                ORDER BY date DESC
                LIMIT 10";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":amount", $amount);
        $stmt->bindParam(":tolerance", $tolerance);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reconcile transaction with invoice
     * @param int $invoice_id Invoice ID to match
     * @param int $user_id User performing reconciliation
     * @param bool $auto Is automatic matching?
     * @return bool Success status
     */
    public function reconcileWithInvoice($invoice_id, $user_id, $auto = false) {
        try {
            $this->conn->beginTransaction();

            // Update transaction status
            $query = "UPDATE " . $this->table_name . "
                    SET status = :status,
                        matched_invoice_id = :invoice_id,
                        reconciliation_date = NOW(),
                        reconciliation_user_id = :user_id
                    WHERE id = :id AND company_id = :company_id";

            $stmt = $this->conn->prepare($query);
            $status = $auto ? 'matched' : 'manual';

            $stmt->bindParam(":id", $this->id);
            $stmt->bindParam(":company_id", $this->company_id);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":invoice_id", $invoice_id);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();

            // Update invoice status to paid
            $update_invoice = "UPDATE invoices
                             SET status = 'paid',
                                 paid_date = :transaction_date
                             WHERE id = :invoice_id";

            $stmt2 = $this->conn->prepare($update_invoice);
            $stmt2->bindParam(":invoice_id", $invoice_id);
            $stmt2->bindParam(":transaction_date", $this->transaction_date);
            $stmt2->execute();

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("BankTransaction::reconcileWithInvoice Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Mark transaction as ignored
     * @return bool Success status
     */
    public function markAsIgnored() {
        $query = "UPDATE " . $this->table_name . "
                SET status = 'ignored'
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Get transaction statistics
     * @param int $company_id Company ID
     * @param int $bank_account_id Optional account filter
     * @return array Statistics
     */
    public function getStatistics($company_id, $bank_account_id = null) {
        $query = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'matched' THEN 1 ELSE 0 END) as matched,
                    SUM(CASE WHEN status = 'manual' THEN 1 ELSE 0 END) as manual,
                    SUM(CASE WHEN status = 'ignored' THEN 1 ELSE 0 END) as ignored,
                    SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_credits,
                    SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as total_debits
                FROM " . $this->table_name . "
                WHERE company_id = :company_id";

        if ($bank_account_id !== null) {
            $query .= " AND bank_account_id = :bank_account_id";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if ($bank_account_id !== null) {
            $stmt->bindParam(":bank_account_id", $bank_account_id);
        }

        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'total' => (int)($stats['total'] ?? 0),
            'pending' => (int)($stats['pending'] ?? 0),
            'matched' => (int)($stats['matched'] ?? 0),
            'manual' => (int)($stats['manual'] ?? 0),
            'ignored' => (int)($stats['ignored'] ?? 0),
            'total_credits' => (float)($stats['total_credits'] ?? 0),
            'total_debits' => (float)($stats['total_debits'] ?? 0)
        ];
    }

    /**
     * Batch import transactions
     * @param array $transactions Array of transaction data
     * @param string $import_format Format (camt053, mt940, csv)
     * @param string $batch_id Unique batch identifier
     * @return array Result with success count and errors
     */
    public function batchImport($transactions, $import_format, $batch_id) {
        $success_count = 0;
        $errors = [];
        $import_date = date('Y-m-d H:i:s');

        foreach ($transactions as $index => $trans_data) {
            try {
                // Check for duplicate
                if ($this->isDuplicate($trans_data)) {
                    $errors[] = "Line " . ($index + 1) . ": Duplicate transaction (bank_reference: " . $trans_data['bank_reference'] . ")";
                    continue;
                }

                // Set properties from import data
                $this->bank_account_id = $trans_data['bank_account_id'];
                $this->company_id = $trans_data['company_id'];
                $this->transaction_date = $trans_data['transaction_date'];
                $this->value_date = $trans_data['value_date'] ?? null;
                $this->booking_date = $trans_data['booking_date'] ?? null;
                $this->bank_reference = $trans_data['bank_reference'] ?? null;
                $this->description = $trans_data['description'];
                $this->amount = $trans_data['amount'];
                $this->currency = $trans_data['currency'] ?? 'CHF';
                $this->balance_after = $trans_data['balance_after'] ?? null;
                $this->counterparty_name = $trans_data['counterparty_name'] ?? null;
                $this->counterparty_account = $trans_data['counterparty_account'] ?? null;
                $this->counterparty_reference = $trans_data['counterparty_reference'] ?? null;
                $this->qr_reference = $trans_data['qr_reference'] ?? null;
                $this->structured_reference = $trans_data['structured_reference'] ?? null;
                $this->status = 'pending';
                $this->import_batch_id = $batch_id;
                $this->import_format = $import_format;
                $this->import_date = $import_date;
                $this->raw_data = $trans_data['raw_data'] ?? null;

                if ($this->create()) {
                    $success_count++;
                } else {
                    $errors[] = "Line " . ($index + 1) . ": Failed to create transaction";
                }

            } catch (Exception $e) {
                $errors[] = "Line " . ($index + 1) . ": " . $e->getMessage();
            }
        }

        return [
            'success' => $success_count,
            'errors' => $errors,
            'total' => count($transactions)
        ];
    }

    /**
     * Check if transaction is duplicate
     * @param array $trans_data Transaction data
     * @return bool Is duplicate
     */
    private function isDuplicate($trans_data) {
        $query = "SELECT COUNT(*) as count
                FROM " . $this->table_name . "
                WHERE bank_account_id = :bank_account_id
                  AND transaction_date = :transaction_date
                  AND amount = :amount
                  AND description = :description";

        // Add bank_reference check if available
        if (!empty($trans_data['bank_reference'])) {
            $query .= " AND bank_reference = :bank_reference";
        }

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":bank_account_id", $trans_data['bank_account_id']);
        $stmt->bindParam(":transaction_date", $trans_data['transaction_date']);
        $stmt->bindParam(":amount", $trans_data['amount']);
        $stmt->bindParam(":description", $trans_data['description']);

        if (!empty($trans_data['bank_reference'])) {
            $stmt->bindParam(":bank_reference", $trans_data['bank_reference']);
        }

        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($result['count'] > 0);
    }

    /**
     * Populate object from database row
     * @param array $row Database row
     */
    private function populateFromRow($row) {
        $this->bank_account_id = $row['bank_account_id'];
        $this->transaction_date = $row['transaction_date'];
        $this->value_date = $row['value_date'];
        $this->booking_date = $row['booking_date'];
        $this->bank_reference = $row['bank_reference'];
        $this->description = $row['description'];
        $this->amount = $row['amount'];
        $this->currency = $row['currency'];
        $this->balance_after = $row['balance_after'];
        $this->counterparty_name = $row['counterparty_name'];
        $this->counterparty_account = $row['counterparty_account'];
        $this->counterparty_reference = $row['counterparty_reference'];
        $this->qr_reference = $row['qr_reference'];
        $this->structured_reference = $row['structured_reference'];
        $this->status = $row['status'];
        $this->matched_invoice_id = $row['matched_invoice_id'];
        $this->matched_transaction_id = $row['matched_transaction_id'];
        $this->reconciliation_date = $row['reconciliation_date'];
        $this->reconciliation_user_id = $row['reconciliation_user_id'];
        $this->import_batch_id = $row['import_batch_id'];
        $this->import_format = $row['import_format'];
        $this->import_date = $row['import_date'];
        $this->raw_data = $row['raw_data'];
        $this->notes = $row['notes'];
        $this->created_at = $row['created_at'];
        $this->updated_at = $row['updated_at'];
    }
}
?>
