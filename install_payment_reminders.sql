-- Installation Script: Payment Reminders (Rappels de Paiement)
-- Database: gestion_comptable
-- Compatible with: MySQL 5.7+, MariaDB 10.3+

USE gestion_comptable;

-- ============================================
-- Table: payment_reminders
-- Purpose: Store payment reminder history for overdue invoices
-- ============================================
CREATE TABLE IF NOT EXISTS `payment_reminders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `reminder_level` int(11) NOT NULL COMMENT '1=Premier rappel, 2=Deuxième rappel, 3=Mise en demeure',
  `sent_date` date NOT NULL COMMENT 'Date d\'envoi du rappel',
  `due_date` date NOT NULL COMMENT 'Nouvelle échéance',
  `days_overdue` int(11) NOT NULL COMMENT 'Nombre de jours de retard',

  -- Amounts
  `original_amount` decimal(10,2) NOT NULL COMMENT 'Montant facture original',
  `amount_paid` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Montant déjà payé',
  `amount_due` decimal(10,2) NOT NULL COMMENT 'Montant restant dû',
  `interest_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Intérêts de retard',
  `fees` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Frais de rappel',
  `total_amount` decimal(10,2) NOT NULL COMMENT 'Total à payer (montant dû + intérêts + frais)',

  -- Status and delivery
  `status` enum('draft','sent','paid','cancelled','overdue') NOT NULL DEFAULT 'draft' COMMENT 'Statut du rappel',
  `email_sent` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Email envoyé',
  `email_sent_date` datetime DEFAULT NULL COMMENT 'Date/heure envoi email',
  `email_opened` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Email ouvert (tracking)',
  `email_opened_date` datetime DEFAULT NULL,

  -- Documents
  `pdf_path` varchar(255) DEFAULT NULL COMMENT 'Chemin vers PDF généré',
  `pdf_generated_date` datetime DEFAULT NULL,

  -- Notes and history
  `notes` text DEFAULT NULL COMMENT 'Notes internes',
  `sent_by_user_id` int(11) DEFAULT NULL COMMENT 'Utilisateur qui a envoyé',

  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `reminder_level` (`reminder_level`),
  KEY `status` (`status`),
  KEY `sent_date` (`sent_date`),
  KEY `due_date` (`due_date`),

  CONSTRAINT `fk_payment_reminders_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_reminders_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_reminders_user` FOREIGN KEY (`sent_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: reminder_settings
-- Purpose: Configure reminder rules per company
-- ============================================
CREATE TABLE IF NOT EXISTS `reminder_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL UNIQUE,

  -- Timing (days after due date)
  `level1_days` int(11) NOT NULL DEFAULT 10 COMMENT 'Jours après échéance pour 1er rappel',
  `level2_days` int(11) NOT NULL DEFAULT 20 COMMENT 'Jours pour 2ème rappel',
  `level3_days` int(11) NOT NULL DEFAULT 30 COMMENT 'Jours pour mise en demeure',

  -- Fees per level
  `level1_fee` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Frais 1er rappel (CHF)',
  `level2_fee` decimal(10,2) NOT NULL DEFAULT 10.00 COMMENT 'Frais 2ème rappel (CHF)',
  `level3_fee` decimal(10,2) NOT NULL DEFAULT 20.00 COMMENT 'Frais mise en demeure (CHF)',

  -- Interest rate
  `interest_rate` decimal(5,2) NOT NULL DEFAULT 5.00 COMMENT 'Taux d\'intérêt annuel (%)',
  `apply_interest` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Appliquer intérêts',

  -- Grace period
  `grace_period_days` int(11) NOT NULL DEFAULT 5 COMMENT 'Délai de grâce avant 1er rappel',

  -- Automation
  `auto_send` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Envoi automatique',
  `auto_send_time` time DEFAULT '09:00:00' COMMENT 'Heure d\'envoi automatique',
  `auto_escalate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Escalade automatique des niveaux',

  -- Email settings
  `send_copy_to_admin` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Envoyer copie à admin',
  `admin_email` varchar(100) DEFAULT NULL COMMENT 'Email admin pour copies',

  -- Exclusions
  `exclude_weekends` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Pas d\'envoi weekend',
  `exclude_holidays` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Pas d\'envoi jours fériés',

  -- Templates
  `level1_subject` varchar(200) DEFAULT 'Rappel de paiement - Facture {invoice_number}',
  `level2_subject` varchar(200) DEFAULT 'Deuxième rappel - Facture {invoice_number}',
  `level3_subject` varchar(200) DEFAULT 'Mise en demeure - Facture {invoice_number}',

  `level1_message` text DEFAULT NULL COMMENT 'Template email niveau 1',
  `level2_message` text DEFAULT NULL COMMENT 'Template email niveau 2',
  `level3_message` text DEFAULT NULL COMMENT 'Template email niveau 3',

  -- Legal
  `legal_notice` text DEFAULT NULL COMMENT 'Mentions légales sur rappels',

  -- Timestamps
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_company` (`company_id`),
  CONSTRAINT `fk_reminder_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: reminder_history_log
-- Purpose: Audit trail for all reminder actions
-- ============================================
CREATE TABLE IF NOT EXISTS `reminder_history_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `reminder_id` int(11) DEFAULT NULL,
  `invoice_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'created, sent, opened, paid, cancelled, escalated',
  `action_by_user_id` int(11) DEFAULT NULL COMMENT 'Utilisateur (NULL si auto)',
  `details` text DEFAULT NULL COMMENT 'Détails de l\'action (JSON)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),

  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `reminder_id` (`reminder_id`),
  KEY `invoice_id` (`invoice_id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`),

  CONSTRAINT `fk_reminder_log_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reminder_log_reminder` FOREIGN KEY (`reminder_id`) REFERENCES `payment_reminders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_reminder_log_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Default settings for existing companies
-- ============================================
INSERT INTO reminder_settings (company_id, level1_days, level2_days, level3_days, level1_fee, level2_fee, level3_fee, interest_rate)
SELECT
  id,
  10,   -- level1_days
  20,   -- level2_days
  30,   -- level3_days
  0.00, -- level1_fee
  10.00,-- level2_fee
  20.00,-- level3_fee
  5.00  -- interest_rate
FROM companies
WHERE id NOT IN (SELECT company_id FROM reminder_settings)
ON DUPLICATE KEY UPDATE company_id = company_id;

-- ============================================
-- Indexes for Performance
-- ============================================
CREATE INDEX IF NOT EXISTS idx_payment_reminders_sent_date ON payment_reminders(sent_date, status);
CREATE INDEX IF NOT EXISTS idx_payment_reminders_level_status ON payment_reminders(reminder_level, status);
CREATE INDEX IF NOT EXISTS idx_reminder_history_date ON reminder_history_log(created_at);

-- ============================================
-- Add missing columns to invoices table if needed
-- ============================================
-- Add paid_date column if it doesn't exist
SET @query = IF(
    (SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = 'gestion_comptable'
     AND TABLE_NAME = 'invoices'
     AND COLUMN_NAME = 'paid_date') = 0,
    'ALTER TABLE invoices ADD COLUMN paid_date date DEFAULT NULL AFTER payment_due_date',
    'SELECT "Column paid_date already exists" AS msg'
);
PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update status enum to include more statuses
ALTER TABLE invoices MODIFY COLUMN status enum('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft';

-- ============================================
-- View: Overdue Invoices (for reminder candidates)
-- ============================================
CREATE OR REPLACE VIEW v_overdue_invoices AS
SELECT
  i.id AS invoice_id,
  i.company_id,
  i.client_id,
  i.number AS invoice_number,
  i.date AS invoice_date,
  i.payment_due_date AS due_date,
  i.total,
  i.status,
  c.name AS client_name,
  c.email AS client_email,
  DATEDIFF(CURDATE(), i.payment_due_date) AS days_overdue,
  COALESCE(
    (SELECT MAX(reminder_level) FROM payment_reminders
     WHERE invoice_id = i.id AND status IN ('sent', 'overdue')),
    0
  ) AS last_reminder_level,
  COALESCE(
    (SELECT MAX(sent_date) FROM payment_reminders
     WHERE invoice_id = i.id),
    NULL
  ) AS last_reminder_date
FROM invoices i
INNER JOIN contacts c ON i.client_id = c.id
WHERE i.status IN ('sent', 'overdue')
  AND i.payment_due_date < CURDATE()
  AND i.paid_date IS NULL
ORDER BY i.payment_due_date ASC;

-- ============================================
-- Stored Procedure: Calculate Reminder Amounts
-- ============================================
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS sp_calculate_reminder_amounts(
  IN p_invoice_id INT,
  IN p_reminder_level INT,
  IN p_days_overdue INT,
  OUT p_original_amount DECIMAL(10,2),
  OUT p_interest_amount DECIMAL(10,2),
  OUT p_fees DECIMAL(10,2),
  OUT p_total_amount DECIMAL(10,2)
)
BEGIN
  DECLARE v_company_id INT;
  DECLARE v_interest_rate DECIMAL(5,2);
  DECLARE v_apply_interest TINYINT(1);
  DECLARE v_level_fee DECIMAL(10,2);

  -- Get invoice and company info
  SELECT company_id, total INTO v_company_id, p_original_amount
  FROM invoices
  WHERE id = p_invoice_id;

  -- Get reminder settings
  SELECT
    interest_rate,
    apply_interest,
    CASE p_reminder_level
      WHEN 1 THEN level1_fee
      WHEN 2 THEN level2_fee
      WHEN 3 THEN level3_fee
      ELSE 0
    END
  INTO v_interest_rate, v_apply_interest, v_level_fee
  FROM reminder_settings
  WHERE company_id = v_company_id;

  -- Calculate interest (simple interest: Principal * Rate * (Days/365))
  IF v_apply_interest = 1 THEN
    SET p_interest_amount = p_original_amount * (v_interest_rate / 100) * (p_days_overdue / 365);
    SET p_interest_amount = ROUND(p_interest_amount, 2);
  ELSE
    SET p_interest_amount = 0.00;
  END IF;

  -- Set fees
  SET p_fees = v_level_fee;

  -- Calculate total
  SET p_total_amount = p_original_amount + p_interest_amount + p_fees;
END //

DELIMITER ;

-- ============================================
-- Verification
-- ============================================
SELECT 'Payment Reminders tables created successfully!' AS Message;

SELECT
    TABLE_NAME,
    TABLE_ROWS,
    CREATE_TIME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'gestion_comptable'
  AND TABLE_NAME IN ('payment_reminders', 'reminder_settings', 'reminder_history_log')
ORDER BY TABLE_NAME;

-- Show view structure
SHOW CREATE VIEW v_overdue_invoices;

-- Show stored procedure
SHOW CREATE PROCEDURE sp_calculate_reminder_amounts;
