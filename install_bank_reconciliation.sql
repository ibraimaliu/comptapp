-- Installation Script: Bank Reconciliation Module
-- Database: gestion_comptable
-- Compatible with: MySQL 5.7+, MariaDB 10.3+

USE gestion_comptable;

-- ============================================
-- Table: bank_accounts
-- Purpose: Store company bank account information
-- ============================================
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'Account name (e.g., "Compte courant UBS")',
  `bank_name` varchar(100) DEFAULT NULL COMMENT 'Bank name (e.g., "UBS SA")',
  `iban` varchar(34) DEFAULT NULL COMMENT 'IBAN format: CHxx xxxx xxxx xxxx xxxx x',
  `account_number` varchar(50) DEFAULT NULL COMMENT 'Account number if no IBAN',
  `currency` varchar(3) NOT NULL DEFAULT 'CHF' COMMENT 'ISO 4217: CHF, EUR, USD',
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Initial balance',
  `opening_balance_date` date DEFAULT NULL COMMENT 'Date of opening balance',
  `current_balance` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Current calculated balance',
  `last_reconciliation_date` date DEFAULT NULL COMMENT 'Last successful reconciliation',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=closed',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `fk_bank_accounts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: bank_transactions
-- Purpose: Store imported bank transactions
-- ============================================
CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bank_account_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,

  -- Transaction identification
  `transaction_date` date NOT NULL COMMENT 'Date effective de la transaction',
  `value_date` date DEFAULT NULL COMMENT 'Date de valeur',
  `booking_date` date DEFAULT NULL COMMENT 'Date de comptabilisation',
  `bank_reference` varchar(100) DEFAULT NULL COMMENT 'Référence bancaire unique',

  -- Transaction details
  `description` text NOT NULL COMMENT 'Libellé de la transaction',
  `amount` decimal(15,2) NOT NULL COMMENT 'Montant (+ crédit, - débit)',
  `currency` varchar(3) NOT NULL DEFAULT 'CHF',
  `balance_after` decimal(15,2) DEFAULT NULL COMMENT 'Solde après transaction',

  -- Counterparty information
  `counterparty_name` varchar(200) DEFAULT NULL COMMENT 'Nom du payeur/bénéficiaire',
  `counterparty_account` varchar(34) DEFAULT NULL COMMENT 'IBAN contrepartie',
  `counterparty_reference` varchar(100) DEFAULT NULL COMMENT 'Référence contrepartie',

  -- Swiss QR-Invoice specific
  `qr_reference` varchar(27) DEFAULT NULL COMMENT 'QR-Reference 27 chiffres',
  `structured_reference` varchar(100) DEFAULT NULL COMMENT 'Référence structurée ISO 20022',

  -- Reconciliation status
  `status` enum('pending','matched','manual','ignored') NOT NULL DEFAULT 'pending' COMMENT 'pending=à traiter, matched=rapproché auto, manual=rapproché manuel, ignored=ignoré',
  `matched_invoice_id` int(11) DEFAULT NULL COMMENT 'ID facture rapprochée',
  `matched_transaction_id` int(11) DEFAULT NULL COMMENT 'ID transaction comptable rapprochée',
  `reconciliation_date` datetime DEFAULT NULL COMMENT 'Date du rapprochement',
  `reconciliation_user_id` int(11) DEFAULT NULL COMMENT 'Utilisateur ayant rapproché',

  -- Import tracking
  `import_batch_id` varchar(50) DEFAULT NULL COMMENT 'ID du lot d\'import',
  `import_format` enum('camt053','mt940','csv','manual') DEFAULT NULL COMMENT 'Format source',
  `import_date` datetime DEFAULT NULL COMMENT 'Date d\'import',
  `raw_data` text DEFAULT NULL COMMENT 'Données brutes XML/CSV pour audit',

  -- Metadata
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `bank_account_id` (`bank_account_id`),
  KEY `company_id` (`company_id`),
  KEY `transaction_date` (`transaction_date`),
  KEY `status` (`status`),
  KEY `qr_reference` (`qr_reference`),
  KEY `bank_reference` (`bank_reference`),
  KEY `matched_invoice_id` (`matched_invoice_id`),
  KEY `matched_transaction_id` (`matched_transaction_id`),
  KEY `import_batch_id` (`import_batch_id`),

  CONSTRAINT `fk_bank_transactions_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bank_transactions_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bank_transactions_invoice` FOREIGN KEY (`matched_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bank_transactions_transaction` FOREIGN KEY (`matched_transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: bank_import_configs
-- Purpose: Store CSV column mapping configurations
-- ============================================
CREATE TABLE IF NOT EXISTS `bank_import_configs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `bank_account_id` int(11) DEFAULT NULL COMMENT 'Configuration par compte ou globale',
  `config_name` varchar(100) NOT NULL COMMENT 'Nom de la configuration (e.g., "UBS CSV Format")',
  `format_type` enum('csv','camt053','mt940') NOT NULL DEFAULT 'csv',

  -- CSV specific settings
  `csv_delimiter` varchar(1) DEFAULT ',' COMMENT 'Séparateur CSV: , ou ; ou tab',
  `csv_enclosure` varchar(1) DEFAULT '"' COMMENT 'Délimiteur de texte',
  `csv_has_header` tinyint(1) DEFAULT 1 COMMENT '1=première ligne = entêtes',
  `csv_skip_lines` int(11) DEFAULT 0 COMMENT 'Nombre de lignes à ignorer',
  `csv_encoding` varchar(20) DEFAULT 'UTF-8' COMMENT 'Encodage: UTF-8, ISO-8859-1, Windows-1252',

  -- Column mapping (JSON format)
  -- Example: {"date": 0, "description": 1, "amount": 2, "balance": 3}
  `column_mapping` text DEFAULT NULL COMMENT 'Mapping colonnes CSV -> champs (JSON)',
  `date_format` varchar(20) DEFAULT 'd.m.Y' COMMENT 'Format date: d.m.Y, Y-m-d, m/d/Y',
  `decimal_separator` varchar(1) DEFAULT '.' COMMENT 'Séparateur décimal: . ou ,',
  `thousands_separator` varchar(1) DEFAULT '' COMMENT 'Séparateur milliers',

  -- Default values
  `default_currency` varchar(3) DEFAULT 'CHF',

  -- Metadata
  `is_default` tinyint(1) DEFAULT 0 COMMENT '1=configuration par défaut',
  `last_used` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `bank_account_id` (`bank_account_id`),
  CONSTRAINT `fk_bank_import_configs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bank_import_configs_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: bank_reconciliation_rules
-- Purpose: Automatic matching rules
-- ============================================
CREATE TABLE IF NOT EXISTS `bank_reconciliation_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `rule_name` varchar(100) NOT NULL,
  `rule_type` enum('qr_reference','amount_exact','amount_range','description_keyword','counterparty_name') NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 0 COMMENT 'Ordre d\'application (0 = haute priorité)',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,

  -- Rule parameters (JSON format)
  -- Examples:
  -- QR: {"field": "qr_reference"}
  -- Amount exact: {"field": "amount", "tolerance": 0}
  -- Amount range: {"field": "amount", "tolerance": 5}
  -- Description: {"field": "description", "keywords": ["FACTURE", "INVOICE"]}
  -- Counterparty: {"field": "counterparty_name", "match": "partial"}
  `rule_params` text DEFAULT NULL COMMENT 'Paramètres de la règle (JSON)',

  -- Action on match
  `auto_match` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=rapprocher automatiquement',
  `suggest_only` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=suggérer seulement',

  -- Accounting action
  `auto_create_transaction` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=créer écriture comptable auto',
  `default_category_id` int(11) DEFAULT NULL COMMENT 'Catégorie par défaut',

  -- Metadata
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `priority` (`priority`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `fk_reconciliation_rules_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Indexes for Performance
-- ============================================
CREATE INDEX IF NOT EXISTS idx_bank_transactions_dates ON bank_transactions(transaction_date, value_date);
CREATE INDEX IF NOT EXISTS idx_bank_transactions_amount ON bank_transactions(amount);
CREATE INDEX IF NOT EXISTS idx_bank_accounts_company_active ON bank_accounts(company_id, is_active);

-- ============================================
-- Sample Data (Optional)
-- ============================================

-- Insert default reconciliation rules for QR-Reference matching
INSERT IGNORE INTO bank_reconciliation_rules
  (id, company_id, rule_name, rule_type, priority, is_active, rule_params, auto_match, suggest_only)
VALUES
  (1, 1, 'QR-Reference Automatique', 'qr_reference', 0, 1, '{"field":"qr_reference","exact_match":true}', 1, 0);

-- ============================================
-- Verification
-- ============================================
SELECT 'Bank Reconciliation tables created successfully!' AS Message;

SELECT
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'gestion_comptable'
  AND TABLE_NAME IN ('bank_accounts', 'bank_transactions', 'bank_import_configs', 'bank_reconciliation_rules')
ORDER BY TABLE_NAME;
