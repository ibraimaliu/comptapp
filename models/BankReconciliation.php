<?php
/**
 * Model: BankReconciliation
 * Purpose: Parse and import bank statements in multiple formats
 *
 * Supported Formats:
 * - ISO 20022 Camt.053 (XML) - Swiss banks standard
 * - MT940 (SWIFT) - International format
 * - CSV - Configurable column mapping
 *
 * Features:
 * - Automatic QR-Reference extraction
 * - Duplicate detection
 * - Batch import with transaction support
 * - Format auto-detection
 */

require_once __DIR__ . '/BankAccount.php';
require_once __DIR__ . '/BankTransaction.php';

class BankReconciliation {
    private $conn;
    private $bank_account;
    private $bank_transaction;

    /**
     * Constructor
     */
    public function __construct($db) {
        $this->conn = $db;
        $this->bank_account = new BankAccount($db);
        $this->bank_transaction = new BankTransaction($db);
    }

    /**
     * Parse ISO 20022 Camt.053 XML format (Swiss banks)
     * @param string $xml_content XML file content
     * @param int $bank_account_id Bank account ID
     * @param int $company_id Company ID
     * @return array Parsed transactions
     */
    public function parseCamt053($xml_content, $bank_account_id, $company_id) {
        $transactions = [];

        try {
            // Load XML
            $xml = new SimpleXMLElement($xml_content);

            // Register namespaces (ISO 20022)
            $namespaces = $xml->getNamespaces(true);
            $ns = isset($namespaces['']) ? '' : 'camt';

            // Navigate to statement entries
            if ($ns) {
                $xml->registerXPathNamespace('ns', $namespaces[$ns]);
                $entries = $xml->xpath('//ns:Stmt/ns:Ntry');
            } else {
                $entries = $xml->xpath('//Stmt/Ntry');
            }

            if (!$entries) {
                throw new Exception("No entries found in Camt.053 XML");
            }

            foreach ($entries as $entry) {
                $trans = [];

                // Amount (CdtDbtInd: CRDT=credit, DBIT=debit)
                $credit_debit = (string)$entry->CdtDbtInd;
                $amount = (float)$entry->Amt;
                $trans['amount'] = ($credit_debit === 'CRDT') ? $amount : -$amount;
                $trans['currency'] = (string)$entry->Amt['Ccy'] ?? 'CHF';

                // Dates
                $trans['booking_date'] = $this->parseDate((string)$entry->BookgDt->Dt);
                $trans['value_date'] = $this->parseDate((string)$entry->ValDt->Dt);
                $trans['transaction_date'] = $trans['booking_date']; // Default to booking date

                // Balance after transaction
                $trans['balance_after'] = isset($entry->Bal) ? (float)$entry->Bal->Amt : null;

                // Bank reference
                $trans['bank_reference'] = (string)$entry->AcctSvcrRef ?? null;

                // Transaction details
                $details = $entry->NtryDtls->TxDtls ?? $entry->NtryDtls;
                if ($details) {
                    // Description
                    $unstructured = (string)$details->RmtInf->Ustrd ?? '';
                    $additional = (string)$entry->AddtlNtryInf ?? '';
                    $trans['description'] = trim($unstructured . ' ' . $additional);

                    // Counterparty
                    if (isset($details->RltdPties)) {
                        if ($credit_debit === 'CRDT') {
                            // Credit: debtor is counterparty
                            $trans['counterparty_name'] = (string)$details->RltdPties->Dbtr->Nm ?? null;
                            $trans['counterparty_account'] = (string)$details->RltdPties->DbtrAcct->Id->IBAN ?? null;
                        } else {
                            // Debit: creditor is counterparty
                            $trans['counterparty_name'] = (string)$details->RltdPties->Cdtr->Nm ?? null;
                            $trans['counterparty_account'] = (string)$details->RltdPties->CdtrAcct->Id->IBAN ?? null;
                        }
                    }

                    // Structured reference (QR-Reference or creditor reference)
                    if (isset($details->RmtInf->Strd)) {
                        $creditor_ref = (string)$details->RmtInf->Strd->CdtrRefInf->Ref ?? '';
                        $trans['structured_reference'] = $creditor_ref;

                        // Check if it's a QR-Reference (27 digits)
                        $clean_ref = preg_replace('/\s+/', '', $creditor_ref);
                        if (preg_match('/^\d{27}$/', $clean_ref)) {
                            $trans['qr_reference'] = $clean_ref;
                        }
                    }

                    // Additional reference
                    $trans['counterparty_reference'] = (string)$details->Refs->EndToEndId ?? null;
                }

                // Add metadata
                $trans['bank_account_id'] = $bank_account_id;
                $trans['company_id'] = $company_id;
                $trans['raw_data'] = $entry->asXML();

                $transactions[] = $trans;
            }

        } catch (Exception $e) {
            error_log("BankReconciliation::parseCamt053 Error: " . $e->getMessage());
            throw new Exception("Erreur parsing Camt.053: " . $e->getMessage());
        }

        return $transactions;
    }

    /**
     * Parse MT940 SWIFT format
     * @param string $mt940_content MT940 file content
     * @param int $bank_account_id Bank account ID
     * @param int $company_id Company ID
     * @return array Parsed transactions
     */
    public function parseMT940($mt940_content, $bank_account_id, $company_id) {
        $transactions = [];
        $lines = explode("\n", $mt940_content);
        $current_trans = null;
        $opening_balance = 0;
        $running_balance = 0;

        try {
            foreach ($lines as $line) {
                $line = trim($line);

                // :60F: Opening Balance
                if (preg_match('/^:60F:([CD])(\d{6})([A-Z]{3})([\d,\.]+)/', $line, $matches)) {
                    $sign = ($matches[1] === 'C') ? 1 : -1;
                    $opening_balance = $sign * $this->parseAmount($matches[4]);
                    $running_balance = $opening_balance;
                }

                // :61: Statement Line (Transaction)
                if (preg_match('/^:61:(\d{6})(\d{4})?([CD])([\d,\.]+)/', $line, $matches)) {
                    // Save previous transaction
                    if ($current_trans !== null) {
                        $transactions[] = $current_trans;
                    }

                    // New transaction
                    $current_trans = [];
                    $current_trans['transaction_date'] = $this->parseMT940Date($matches[1]);
                    $current_trans['value_date'] = isset($matches[2]) ? $this->parseMT940Date($matches[2], true) : $current_trans['transaction_date'];

                    $credit_debit = $matches[3];
                    $amount = $this->parseAmount($matches[4]);
                    $current_trans['amount'] = ($credit_debit === 'C') ? $amount : -$amount;

                    // Update running balance
                    $running_balance += $current_trans['amount'];
                    $current_trans['balance_after'] = $running_balance;

                    $current_trans['currency'] = 'CHF'; // Default
                    $current_trans['description'] = '';
                    $current_trans['bank_reference'] = substr($line, strpos($line, $matches[4]) + strlen($matches[4]));
                }

                // :86: Transaction Details
                if (preg_match('/^:86:(.+)/', $line, $matches)) {
                    if ($current_trans !== null) {
                        $details = $matches[1];

                        // Extract description
                        $current_trans['description'] = $this->cleanMT940Description($details);

                        // Try to extract QR-Reference (27 digits)
                        if (preg_match('/(\d{27})/', $details, $qr_match)) {
                            $current_trans['qr_reference'] = $qr_match[1];
                        }

                        // Extract counterparty name (after /NAME/)
                        if (preg_match('/\/NAME\/([^\/]+)/', $details, $name_match)) {
                            $current_trans['counterparty_name'] = trim($name_match[1]);
                        }

                        // Extract IBAN (after /IBAN/)
                        if (preg_match('/\/IBAN\/([A-Z]{2}\d{2}[A-Z0-9]+)/', $details, $iban_match)) {
                            $current_trans['counterparty_account'] = $iban_match[1];
                        }
                    }
                }

                // :62F: Closing Balance
                if (preg_match('/^:62F:([CD])(\d{6})([A-Z]{3})([\d,\.]+)/', $line, $matches)) {
                    // Validate closing balance
                    $sign = ($matches[1] === 'C') ? 1 : -1;
                    $closing_balance = $sign * $this->parseAmount($matches[4]);

                    if (abs($closing_balance - $running_balance) > 0.01) {
                        error_log("MT940 Warning: Closing balance mismatch (expected: $running_balance, got: $closing_balance)");
                    }
                }
            }

            // Add last transaction
            if ($current_trans !== null) {
                $transactions[] = $current_trans;
            }

            // Add metadata to all transactions
            foreach ($transactions as &$trans) {
                $trans['bank_account_id'] = $bank_account_id;
                $trans['company_id'] = $company_id;
                $trans['raw_data'] = null; // MT940 is plain text, could store line if needed
            }

        } catch (Exception $e) {
            error_log("BankReconciliation::parseMT940 Error: " . $e->getMessage());
            throw new Exception("Erreur parsing MT940: " . $e->getMessage());
        }

        return $transactions;
    }

    /**
     * Parse CSV format with configurable column mapping
     * @param string $csv_content CSV file content
     * @param int $bank_account_id Bank account ID
     * @param int $company_id Company ID
     * @param array $config Configuration array with column mapping
     * @return array Parsed transactions
     */
    public function parseCSV($csv_content, $bank_account_id, $company_id, $config = []) {
        $transactions = [];

        // Default config
        $defaults = [
            'delimiter' => ',',
            'enclosure' => '"',
            'has_header' => true,
            'skip_lines' => 0,
            'encoding' => 'UTF-8',
            'date_format' => 'd.m.Y', // Swiss format
            'decimal_separator' => '.',
            'thousands_separator' => '',
            'column_mapping' => [
                'date' => 0,
                'description' => 1,
                'amount' => 2,
                'currency' => 3,
                'balance' => 4
            ]
        ];

        $config = array_merge($defaults, $config);

        try {
            // Convert encoding if needed
            if (strtoupper($config['encoding']) !== 'UTF-8') {
                $csv_content = mb_convert_encoding($csv_content, 'UTF-8', $config['encoding']);
            }

            // Split lines
            $lines = str_getcsv($csv_content, "\n");

            // Skip header and initial lines
            $start_line = $config['skip_lines'] + ($config['has_header'] ? 1 : 0);

            for ($i = $start_line; $i < count($lines); $i++) {
                $line = $lines[$i];
                if (empty(trim($line))) continue;

                $row = str_getcsv($line, $config['delimiter'], $config['enclosure']);

                // Skip empty rows
                if (count($row) === 0 || (count($row) === 1 && empty($row[0]))) {
                    continue;
                }

                $trans = [];

                // Map columns
                $mapping = $config['column_mapping'];

                // Date
                if (isset($mapping['date']) && isset($row[$mapping['date']])) {
                    $trans['transaction_date'] = $this->parseCSVDate($row[$mapping['date']], $config['date_format']);
                } else {
                    error_log("CSV Warning: Missing date at line " . ($i + 1));
                    continue;
                }

                // Description
                if (isset($mapping['description']) && isset($row[$mapping['description']])) {
                    $trans['description'] = trim($row[$mapping['description']]);
                } else {
                    $trans['description'] = 'Import CSV';
                }

                // Amount
                if (isset($mapping['amount']) && isset($row[$mapping['amount']])) {
                    $trans['amount'] = $this->parseCSVAmount($row[$mapping['amount']], $config);
                } else {
                    error_log("CSV Warning: Missing amount at line " . ($i + 1));
                    continue;
                }

                // Currency
                if (isset($mapping['currency']) && isset($row[$mapping['currency']])) {
                    $trans['currency'] = strtoupper(trim($row[$mapping['currency']]));
                } else {
                    $trans['currency'] = $config['default_currency'] ?? 'CHF';
                }

                // Balance
                if (isset($mapping['balance']) && isset($row[$mapping['balance']])) {
                    $trans['balance_after'] = $this->parseCSVAmount($row[$mapping['balance']], $config);
                }

                // Optional: Value date
                if (isset($mapping['value_date']) && isset($row[$mapping['value_date']])) {
                    $trans['value_date'] = $this->parseCSVDate($row[$mapping['value_date']], $config['date_format']);
                }

                // Optional: Counterparty name
                if (isset($mapping['counterparty_name']) && isset($row[$mapping['counterparty_name']])) {
                    $trans['counterparty_name'] = trim($row[$mapping['counterparty_name']]);
                }

                // Optional: Counterparty account
                if (isset($mapping['counterparty_account']) && isset($row[$mapping['counterparty_account']])) {
                    $trans['counterparty_account'] = trim($row[$mapping['counterparty_account']]);
                }

                // Optional: Reference
                if (isset($mapping['reference']) && isset($row[$mapping['reference']])) {
                    $ref = preg_replace('/\s+/', '', $row[$mapping['reference']]);
                    $trans['counterparty_reference'] = $ref;

                    // Check if it's a QR-Reference
                    if (preg_match('/^\d{27}$/', $ref)) {
                        $trans['qr_reference'] = $ref;
                    }
                }

                // Add metadata
                $trans['bank_account_id'] = $bank_account_id;
                $trans['company_id'] = $company_id;
                $trans['raw_data'] = implode($config['delimiter'], $row);

                $transactions[] = $trans;
            }

        } catch (Exception $e) {
            error_log("BankReconciliation::parseCSV Error: " . $e->getMessage());
            throw new Exception("Erreur parsing CSV: " . $e->getMessage());
        }

        return $transactions;
    }

    /**
     * Auto-detect file format
     * @param string $file_content File content
     * @return string Format: camt053, mt940, csv, or unknown
     */
    public function detectFormat($file_content) {
        // Camt.053 XML
        if (strpos($file_content, '<?xml') !== false && strpos($file_content, 'camt.053') !== false) {
            return 'camt053';
        }

        // MT940
        if (preg_match('/:20:|:25:|:60F:|:61:/', $file_content)) {
            return 'mt940';
        }

        // CSV (has commas or semicolons)
        if (preg_match('/[,;]/', $file_content)) {
            return 'csv';
        }

        return 'unknown';
    }

    /**
     * Import transactions from file
     * @param string $file_path Path to file
     * @param int $bank_account_id Bank account ID
     * @param int $company_id Company ID
     * @param string $format Format override (optional, will auto-detect)
     * @param array $csv_config CSV configuration (if CSV format)
     * @return array Result with success/error counts
     */
    public function importFromFile($file_path, $bank_account_id, $company_id, $format = null, $csv_config = []) {
        if (!file_exists($file_path)) {
            throw new Exception("Fichier introuvable: $file_path");
        }

        $file_content = file_get_contents($file_path);

        // Auto-detect format if not specified
        if ($format === null) {
            $format = $this->detectFormat($file_content);
        }

        // Parse transactions
        switch ($format) {
            case 'camt053':
                $transactions = $this->parseCamt053($file_content, $bank_account_id, $company_id);
                break;

            case 'mt940':
                $transactions = $this->parseMT940($file_content, $bank_account_id, $company_id);
                break;

            case 'csv':
                $transactions = $this->parseCSV($file_content, $bank_account_id, $company_id, $csv_config);
                break;

            default:
                throw new Exception("Format non reconnu: $format");
        }

        // Generate batch ID
        $batch_id = 'IMPORT_' . date('YmdHis') . '_' . uniqid();

        // Import transactions
        $result = $this->bank_transaction->batchImport($transactions, $format, $batch_id);

        return $result;
    }

    /**
     * Helper: Parse date from ISO format
     */
    private function parseDate($date_string) {
        if (empty($date_string)) return null;

        $date = DateTime::createFromFormat('Y-m-d', $date_string);
        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * Helper: Parse MT940 date (YYMMDD or MMDD)
     */
    private function parseMT940Date($date_string, $is_short = false) {
        if ($is_short) {
            // MMDD format
            $current_year = date('y');
            $date_string = $current_year . $date_string;
        }

        // YYMMDD format
        $date = DateTime::createFromFormat('ymd', $date_string);
        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * Helper: Parse amount from string
     */
    private function parseAmount($amount_string) {
        // Remove thousands separators and convert decimal comma to dot
        $amount_string = str_replace([' ', "'", ','], ['', '', '.'], $amount_string);
        return (float)$amount_string;
    }

    /**
     * Helper: Clean MT940 description
     */
    private function cleanMT940Description($description) {
        // Remove MT940 tags
        $description = preg_replace('/\/[A-Z]{4}\//', ' ', $description);
        return trim(preg_replace('/\s+/', ' ', $description));
    }

    /**
     * Helper: Parse CSV date
     */
    private function parseCSVDate($date_string, $format) {
        $date = DateTime::createFromFormat($format, trim($date_string));
        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * Helper: Parse CSV amount
     */
    private function parseCSVAmount($amount_string, $config) {
        $amount_string = trim($amount_string);

        // Remove thousands separator
        if (!empty($config['thousands_separator'])) {
            $amount_string = str_replace($config['thousands_separator'], '', $amount_string);
        }

        // Replace decimal separator with dot
        if ($config['decimal_separator'] !== '.') {
            $amount_string = str_replace($config['decimal_separator'], '.', $amount_string);
        }

        return (float)$amount_string;
    }
}
?>
