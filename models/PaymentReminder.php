<?php
/**
 * Model: PaymentReminder
 * Purpose: Manage payment reminders for overdue invoices
 *
 * Features:
 * - 3-level reminder system (1st, 2nd, 3rd/final notice)
 * - Automatic calculation of interest and fees
 * - Email notification system
 * - PDF generation for each reminder level
 * - Escalation tracking
 */

class PaymentReminder {
    private $conn;
    private $table_name = "payment_reminders";

    // Properties
    public $id;
    public $company_id;
    public $invoice_id;
    public $reminder_level;
    public $sent_date;
    public $due_date;
    public $days_overdue;

    // Amounts
    public $original_amount;
    public $amount_paid;
    public $amount_due;
    public $interest_amount;
    public $fees;
    public $total_amount;

    // Status
    public $status;
    public $email_sent;
    public $email_sent_date;
    public $email_opened;
    public $email_opened_date;

    // Documents
    public $pdf_path;
    public $pdf_generated_date;

    // Meta
    public $notes;
    public $sent_by_user_id;
    public $created_at;
    public $updated_at;

    /**
     * Constructor
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Create a new payment reminder
     * @return bool Success status
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    invoice_id = :invoice_id,
                    reminder_level = :reminder_level,
                    sent_date = :sent_date,
                    due_date = :due_date,
                    days_overdue = :days_overdue,
                    original_amount = :original_amount,
                    amount_paid = :amount_paid,
                    amount_due = :amount_due,
                    interest_amount = :interest_amount,
                    fees = :fees,
                    total_amount = :total_amount,
                    status = :status,
                    email_sent = :email_sent,
                    sent_by_user_id = :sent_by_user_id,
                    notes = :notes";

        $stmt = $this->conn->prepare($query);

        // Sanitize
        $this->notes = htmlspecialchars(strip_tags($this->notes ?? ''));
        $this->status = $this->status ?? 'draft';

        // Bind
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":invoice_id", $this->invoice_id);
        $stmt->bindParam(":reminder_level", $this->reminder_level);
        $stmt->bindParam(":sent_date", $this->sent_date);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":days_overdue", $this->days_overdue);
        $stmt->bindParam(":original_amount", $this->original_amount);
        $stmt->bindParam(":amount_paid", $this->amount_paid);
        $stmt->bindParam(":amount_due", $this->amount_due);
        $stmt->bindParam(":interest_amount", $this->interest_amount);
        $stmt->bindParam(":fees", $this->fees);
        $stmt->bindParam(":total_amount", $this->total_amount);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":email_sent", $this->email_sent);
        $stmt->bindParam(":sent_by_user_id", $this->sent_by_user_id);
        $stmt->bindParam(":notes", $this->notes);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();

            // Log history
            $this->logHistory('created', 'Reminder created', $this->sent_by_user_id);

            return true;
        }

        return false;
    }

    /**
     * Read single reminder
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
     * Get all reminders for an invoice
     * @param int $invoice_id Invoice ID
     * @return array Reminders
     */
    public function getByInvoice($invoice_id) {
        $query = "SELECT *
                FROM " . $this->table_name . "
                WHERE invoice_id = :invoice_id
                  AND company_id = :company_id
                ORDER BY reminder_level ASC, sent_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":invoice_id", $invoice_id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all reminders for a company
     * @param int $company_id Company ID
     * @param string $status Filter by status (optional)
     * @param int $limit Limit results
     * @return array Reminders
     */
    public function getByCompany($company_id, $status = null, $limit = 100) {
        $query = "SELECT pr.*,
                         i.number AS invoice_number,
                         i.date AS invoice_date,
                         c.name AS client_name,
                         c.email AS client_email
                FROM " . $this->table_name . " pr
                INNER JOIN invoices i ON pr.invoice_id = i.id
                INNER JOIN contacts c ON i.client_id = c.id
                WHERE pr.company_id = :company_id";

        if ($status !== null) {
            $query .= " AND pr.status = :status";
        }

        $query .= " ORDER BY pr.sent_date DESC, pr.reminder_level ASC
                    LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id, PDO::PARAM_INT);

        if ($status !== null) {
            $stmt->bindParam(":status", $status);
        }

        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Find overdue invoices that need reminders
     * @param int $company_id Company ID
     * @return array Overdue invoices with reminder info
     */
    public function findOverdueInvoices($company_id) {
        // Use the view created in SQL
        $query = "SELECT * FROM v_overdue_invoices
                WHERE company_id = :company_id
                ORDER BY days_overdue DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate amounts for a reminder
     * Uses stored procedure sp_calculate_reminder_amounts
     * @param int $invoice_id Invoice ID
     * @param int $reminder_level Level (1, 2, 3)
     * @param int $days_overdue Days overdue
     * @return array Calculated amounts
     */
    public function calculateAmounts($invoice_id, $reminder_level, $days_overdue) {
        try {
            $stmt = $this->conn->prepare("CALL sp_calculate_reminder_amounts(?, ?, ?, @original, @interest, @fees, @total)");
            $stmt->execute([$invoice_id, $reminder_level, $days_overdue]);

            // Get output parameters
            $result = $this->conn->query("SELECT @original AS original_amount, @interest AS interest_amount, @fees AS fees, @total AS total_amount")->fetch(PDO::FETCH_ASSOC);

            return [
                'original_amount' => (float)$result['original_amount'],
                'interest_amount' => (float)$result['interest_amount'],
                'fees' => (float)$result['fees'],
                'total_amount' => (float)$result['total_amount']
            ];
        } catch (Exception $e) {
            error_log("PaymentReminder::calculateAmounts Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate reminder for an invoice
     * @param int $invoice_id Invoice ID
     * @param int $company_id Company ID
     * @param int $user_id User creating the reminder
     * @param bool $send_immediately Send email immediately
     * @return int|false Reminder ID or false on failure
     */
    public function generateReminder($invoice_id, $company_id, $user_id, $send_immediately = false) {
        try {
            // Get invoice details
            $invoice_query = "SELECT i.*, c.email
                            FROM invoices i
                            INNER JOIN contacts c ON i.client_id = c.id
                            WHERE i.id = :invoice_id AND i.company_id = :company_id";

            $stmt = $this->conn->prepare($invoice_query);
            $stmt->execute([':invoice_id' => $invoice_id, ':company_id' => $company_id]);
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$invoice) {
                throw new Exception("Invoice not found");
            }

            // Calculate days overdue
            $due_date = new DateTime($invoice['payment_due_date']);
            $today = new DateTime();
            $days_overdue = $today->diff($due_date)->days;

            // Determine reminder level
            $last_reminder = $this->getLastReminderLevel($invoice_id);
            $reminder_level = $last_reminder + 1;

            if ($reminder_level > 3) {
                throw new Exception("Maximum reminder level reached");
            }

            // Get reminder settings
            $settings = $this->getSettings($company_id);
            if (!$settings) {
                throw new Exception("Reminder settings not configured");
            }

            // Calculate new due date
            $new_due_days = 10; // Default 10 days
            $new_due_date = (new DateTime())->add(new DateInterval('P' . $new_due_days . 'D'))->format('Y-m-d');

            // Calculate amounts
            $amounts = $this->calculateAmounts($invoice_id, $reminder_level, $days_overdue);
            if (!$amounts) {
                throw new Exception("Failed to calculate amounts");
            }

            // Create reminder
            $this->company_id = $company_id;
            $this->invoice_id = $invoice_id;
            $this->reminder_level = $reminder_level;
            $this->sent_date = date('Y-m-d');
            $this->due_date = $new_due_date;
            $this->days_overdue = $days_overdue;
            $this->original_amount = $amounts['original_amount'];
            $this->amount_paid = 0.00;
            $this->amount_due = $amounts['original_amount'];
            $this->interest_amount = $amounts['interest_amount'];
            $this->fees = $amounts['fees'];
            $this->total_amount = $amounts['total_amount'];
            $this->status = $send_immediately ? 'sent' : 'draft';
            $this->email_sent = 0;
            $this->sent_by_user_id = $user_id;

            if (!$this->create()) {
                throw new Exception("Failed to create reminder");
            }

            // Send email if requested
            if ($send_immediately && !empty($invoice['email'])) {
                $this->sendEmail($this->id, $invoice['email']);
            }

            return $this->id;

        } catch (Exception $e) {
            error_log("PaymentReminder::generateReminder Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get last reminder level for an invoice
     * @param int $invoice_id Invoice ID
     * @return int Last level (0 if none)
     */
    private function getLastReminderLevel($invoice_id) {
        $query = "SELECT MAX(reminder_level) AS max_level
                FROM " . $this->table_name . "
                WHERE invoice_id = :invoice_id
                  AND status IN ('sent', 'draft')";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":invoice_id", $invoice_id);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['max_level'] ?? 0);
    }

    /**
     * Send reminder email
     * @param int $reminder_id Reminder ID
     * @param string $to_email Recipient email
     * @return bool Success status
     */
    public function sendEmail($reminder_id, $to_email) {
        // Load reminder details
        $this->id = $reminder_id;
        if (!$this->read()) {
            return false;
        }

        // Get settings for email templates
        $settings = $this->getSettings($this->company_id);
        if (!$settings) {
            return false;
        }

        // Get invoice and client info
        $query = "SELECT i.*, c.name AS client_name, c.email AS client_email,
                         co.name AS company_name, co.email AS company_email
                FROM invoices i
                INNER JOIN contacts c ON i.client_id = c.id
                INNER JOIN companies co ON i.company_id = co.id
                WHERE i.id = :invoice_id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute([':invoice_id' => $this->invoice_id]);
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice) {
            return false;
        }

        // Determine subject based on level
        $subject_templates = [
            1 => $settings['level1_subject'],
            2 => $settings['level2_subject'],
            3 => $settings['level3_subject']
        ];

        $subject = str_replace('{invoice_number}', $invoice['number'], $subject_templates[$this->reminder_level]);

        // Generate email body
        $body = $this->generateEmailBody($invoice, $settings);

        // Envoyer via EmailSender (PHPMailer ou mail() natif selon config)
        require_once dirname(__DIR__) . '/utils/EmailSender.php';
        $sender = new EmailSender([
            'from_email' => $invoice['company_email'] ?? 'noreply@gestion-comptable.local',
            'from_name'  => $invoice['company_name'] ?? 'Gestion Comptable',
        ]);
        $days_overdue = (int) floor((time() - strtotime($invoice['due_date'])) / 86400);
        $sent = $sender->sendPaymentReminder(
            $to_email,
            $invoice['client_name'],
            $invoice['number'],
            $invoice['total'],
            $invoice['due_date'],
            $days_overdue,
            $this->reminder_level
        );
        if (!$sent) {
            error_log("PaymentReminder: échec envoi email à $to_email pour facture " . $invoice['number']);
        }

        // Update reminder as sent
        $update_query = "UPDATE " . $this->table_name . "
                        SET email_sent = 1,
                            email_sent_date = NOW(),
                            status = 'sent'
                        WHERE id = :id";

        $stmt = $this->conn->prepare($update_query);
        $stmt->bindParam(":id", $this->id);

        if ($stmt->execute()) {
            $this->logHistory('sent', 'Reminder email sent to ' . $to_email);
            return true;
        }

        return false;
    }

    /**
     * Generate email body for reminder
     * @param array $invoice Invoice data
     * @param array $settings Reminder settings
     * @return string Email body HTML
     */
    private function generateEmailBody($invoice, $settings) {
        $message_templates = [
            1 => $settings['level1_message'],
            2 => $settings['level2_message'],
            3 => $settings['level3_message']
        ];

        $template = $message_templates[$this->reminder_level] ?? '';

        // Default templates if not set
        if (empty($template)) {
            $template = $this->getDefaultTemplate($this->reminder_level);
        }

        // Replace placeholders
        $replacements = [
            '{client_name}' => $invoice['client_name'],
            '{invoice_number}' => $invoice['number'],
            '{invoice_date}' => date('d.m.Y', strtotime($invoice['date'])),
            '{original_due_date}' => date('d.m.Y', strtotime($invoice['payment_due_date'])),
            '{days_overdue}' => $this->days_overdue,
            '{original_amount}' => number_format($this->original_amount, 2) . ' CHF',
            '{interest_amount}' => number_format($this->interest_amount, 2) . ' CHF',
            '{fees}' => number_format($this->fees, 2) . ' CHF',
            '{total_amount}' => number_format($this->total_amount, 2) . ' CHF',
            '{new_due_date}' => date('d.m.Y', strtotime($this->due_date)),
            '{company_name}' => $invoice['company_name']
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get default email template for level
     * @param int $level Reminder level
     * @return string Template
     */
    private function getDefaultTemplate($level) {
        $templates = [
            1 => "Madame, Monsieur {client_name},\n\nNous vous informons que la facture {invoice_number} du {invoice_date}, d'un montant de {original_amount}, n'a pas été réglée à l'échéance du {original_due_date}.\n\nNous vous prions de bien vouloir procéder au paiement dans les meilleurs délais.\n\nMontant à payer: {total_amount}\nNouvelle échéance: {new_due_date}\n\nCordialement,\n{company_name}",

            2 => "Madame, Monsieur {client_name},\n\nMalgré notre premier rappel, nous constatons que la facture {invoice_number} demeure impayée ({days_overdue} jours de retard).\n\nMontant initial: {original_amount}\nFrais de rappel: {fees}\nTotal à payer: {total_amount}\n\nNous vous demandons de régulariser votre situation avant le {new_due_date}.\n\nCordialement,\n{company_name}",

            3 => "Madame, Monsieur {client_name},\n\nDERNIÈRE MISE EN DEMEURE\n\nLa facture {invoice_number} reste impayée malgré nos précédents rappels ({days_overdue} jours de retard).\n\nMontant initial: {original_amount}\nIntérêts de retard: {interest_amount}\nFrais: {fees}\nTOTAL: {total_amount}\n\nSans règlement avant le {new_due_date}, nous serons contraints d'engager une procédure de recouvrement judiciaire.\n\n{company_name}"
        ];

        return $templates[$level] ?? '';
    }

    /**
     * Get reminder settings for company
     * @param int $company_id Company ID
     * @return array|null Settings
     */
    public function getSettings($company_id) {
        $query = "SELECT * FROM reminder_settings WHERE company_id = :company_id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Update reminder settings
     * @param int $company_id Company ID
     * @param array $settings Settings array
     * @return bool Success status
     */
    public function updateSettings($company_id, $settings) {
        $query = "UPDATE reminder_settings
                SET level1_days = :level1_days,
                    level2_days = :level2_days,
                    level3_days = :level3_days,
                    level1_fee = :level1_fee,
                    level2_fee = :level2_fee,
                    level3_fee = :level3_fee,
                    interest_rate = :interest_rate,
                    apply_interest = :apply_interest,
                    auto_send = :auto_send
                WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":level1_days", $settings['level1_days']);
        $stmt->bindParam(":level2_days", $settings['level2_days']);
        $stmt->bindParam(":level3_days", $settings['level3_days']);
        $stmt->bindParam(":level1_fee", $settings['level1_fee']);
        $stmt->bindParam(":level2_fee", $settings['level2_fee']);
        $stmt->bindParam(":level3_fee", $settings['level3_fee']);
        $stmt->bindParam(":interest_rate", $settings['interest_rate']);
        $stmt->bindParam(":apply_interest", $settings['apply_interest']);
        $stmt->bindParam(":auto_send", $settings['auto_send']);

        return $stmt->execute();
    }

    /**
     * Get statistics
     * @param int $company_id Company ID
     * @return array Statistics
     */
    public function getStatistics($company_id) {
        $query = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) AS paid,
                    SUM(CASE WHEN reminder_level = 1 THEN 1 ELSE 0 END) AS level1,
                    SUM(CASE WHEN reminder_level = 2 THEN 1 ELSE 0 END) AS level2,
                    SUM(CASE WHEN reminder_level = 3 THEN 1 ELSE 0 END) AS level3,
                    SUM(total_amount) AS total_amount_due
                FROM " . $this->table_name . "
                WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Log action history
     * @param string $action Action name
     * @param string $details Details (optional)
     * @param int $user_id User ID (optional)
     * @return bool Success status
     */
    private function logHistory($action, $details = null, $user_id = null) {
        $query = "INSERT INTO reminder_history_log
                SET company_id = :company_id,
                    reminder_id = :reminder_id,
                    invoice_id = :invoice_id,
                    action = :action,
                    action_by_user_id = :user_id,
                    details = :details";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":reminder_id", $this->id);
        $stmt->bindParam(":invoice_id", $this->invoice_id);
        $stmt->bindParam(":action", $action);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":details", $details);

        return $stmt->execute();
    }

    /**
     * Populate object from database row
     * @param array $row Database row
     */
    private function populateFromRow($row) {
        $this->invoice_id = $row['invoice_id'];
        $this->reminder_level = $row['reminder_level'];
        $this->sent_date = $row['sent_date'];
        $this->due_date = $row['due_date'];
        $this->days_overdue = $row['days_overdue'];
        $this->original_amount = $row['original_amount'];
        $this->amount_paid = $row['amount_paid'];
        $this->amount_due = $row['amount_due'];
        $this->interest_amount = $row['interest_amount'];
        $this->fees = $row['fees'];
        $this->total_amount = $row['total_amount'];
        $this->status = $row['status'];
        $this->email_sent = $row['email_sent'];
        $this->email_sent_date = $row['email_sent_date'];
        $this->email_opened = $row['email_opened'];
        $this->email_opened_date = $row['email_opened_date'];
        $this->pdf_path = $row['pdf_path'];
        $this->pdf_generated_date = $row['pdf_generated_date'];
        $this->notes = $row['notes'];
        $this->sent_by_user_id = $row['sent_by_user_id'];
        $this->created_at = $row['created_at'];
        $this->updated_at = $row['updated_at'];
    }
}
?>
