-- ============================================
-- Installation: Module Gestion Fournisseurs & Achats
-- Version: 1.0
-- Description: Gestion factures fournisseurs et paiements
-- ============================================

USE gestion_comptable;

-- ============================================
-- Table: supplier_invoices
-- Description: Factures reçues des fournisseurs
-- ============================================
CREATE TABLE IF NOT EXISTS `supplier_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL COMMENT 'Référence vers contacts',
  `invoice_number` varchar(50) NOT NULL COMMENT 'Numéro facture fournisseur',
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `reception_date` date DEFAULT NULL COMMENT 'Date réception facture',
  `subtotal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tva_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total` decimal(12,2) NOT NULL,
  `status` enum('received','approved','paid','cancelled','disputed') NOT NULL DEFAULT 'received',
  `payment_date` date DEFAULT NULL,
  `payment_method` enum('bank_transfer','cash','card','other') DEFAULT NULL,
  `qr_reference` varchar(27) DEFAULT NULL COMMENT 'Référence QR-facture',
  `iban` varchar(34) DEFAULT NULL COMMENT 'IBAN fournisseur',
  `scanned_pdf_path` varchar(255) DEFAULT NULL COMMENT 'Chemin PDF scanné',
  `notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL COMMENT 'User qui a approuvé',
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_invoices_company` (`company_id`),
  KEY `idx_supplier_invoices_supplier` (`supplier_id`),
  KEY `idx_supplier_invoices_status` (`status`),
  KEY `idx_supplier_invoices_due_date` (`due_date`),
  KEY `idx_supplier_invoices_qr` (`qr_reference`),
  CONSTRAINT `fk_supplier_invoices_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_invoices_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contacts` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_supplier_invoices_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_supplier_invoices_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: supplier_invoice_items
-- Description: Lignes détaillées des factures fournisseurs
-- ============================================
CREATE TABLE IF NOT EXISTS `supplier_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_invoice_id` int(11) NOT NULL,
  `description` text NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(12,2) NOT NULL,
  `tva_rate` decimal(5,2) NOT NULL DEFAULT 7.70 COMMENT 'Taux TVA en %',
  `tva_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` decimal(12,2) NOT NULL,
  `total` decimal(12,2) NOT NULL,
  `account_id` int(11) DEFAULT NULL COMMENT 'Compte comptable',
  `sort_order` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_supplier_invoice_items_invoice` (`supplier_invoice_id`),
  KEY `idx_supplier_invoice_items_account` (`account_id`),
  CONSTRAINT `fk_supplier_invoice_items_invoice` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_invoice_items_account` FOREIGN KEY (`account_id`) REFERENCES `accounting_plan` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: payments
-- Description: Paiements (fournisseurs et clients)
-- ============================================
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'CHF',
  `payment_method` enum('bank_transfer','cash','card','check','other') NOT NULL,
  `payment_type` enum('supplier_payment','customer_payment','other') NOT NULL,
  `reference` varchar(100) DEFAULT NULL COMMENT 'Référence bancaire',
  `description` text DEFAULT NULL,

  -- Relations
  `supplier_invoice_id` int(11) DEFAULT NULL COMMENT 'Si paiement fournisseur',
  `invoice_id` int(11) DEFAULT NULL COMMENT 'Si paiement client',
  `bank_account_id` int(11) DEFAULT NULL COMMENT 'Compte bancaire utilisé',
  `contact_id` int(11) DEFAULT NULL COMMENT 'Fournisseur ou client',

  -- Métadonnées
  `notes` text DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL COMMENT 'Chemin reçu/justificatif',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_payments_company` (`company_id`),
  KEY `idx_payments_date` (`payment_date`),
  KEY `idx_payments_supplier_invoice` (`supplier_invoice_id`),
  KEY `idx_payments_invoice` (`invoice_id`),
  KEY `idx_payments_bank_account` (`bank_account_id`),
  KEY `idx_payments_contact` (`contact_id`),

  CONSTRAINT `fk_payments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payments_supplier_invoice` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_bank_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_payments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: payment_schedules
-- Description: Échéancier des paiements à venir
-- ============================================
CREATE TABLE IF NOT EXISTS `payment_schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `supplier_invoice_id` int(11) DEFAULT NULL,
  `contact_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `payment_id` int(11) DEFAULT NULL COMMENT 'Si payé, lien vers payment',
  `notes` text DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `reminder_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_payment_schedules_company` (`company_id`),
  KEY `idx_payment_schedules_due_date` (`due_date`),
  KEY `idx_payment_schedules_status` (`status`),
  KEY `idx_payment_schedules_supplier_invoice` (`supplier_invoice_id`),
  KEY `idx_payment_schedules_payment` (`payment_id`),

  CONSTRAINT `fk_payment_schedules_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_schedules_supplier_invoice` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_schedules_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_schedules_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Vue: Factures fournisseurs en retard
-- ============================================
CREATE OR REPLACE VIEW v_overdue_supplier_invoices AS
SELECT
  si.id AS invoice_id,
  si.company_id,
  si.supplier_id,
  si.invoice_number,
  si.invoice_date,
  si.due_date,
  si.total,
  si.status,
  c.name AS supplier_name,
  c.email AS supplier_email,
  DATEDIFF(CURDATE(), si.due_date) AS days_overdue,
  COALESCE(
    (SELECT SUM(p.amount)
     FROM payments p
     WHERE p.supplier_invoice_id = si.id),
    0
  ) AS amount_paid,
  (si.total - COALESCE(
    (SELECT SUM(p.amount)
     FROM payments p
     WHERE p.supplier_invoice_id = si.id),
    0
  )) AS amount_due
FROM supplier_invoices si
INNER JOIN contacts c ON si.supplier_id = c.id
WHERE si.status IN ('received', 'approved')
  AND si.due_date < CURDATE()
  AND si.payment_date IS NULL
ORDER BY si.due_date ASC;

-- ============================================
-- Vue: Échéancier des paiements
-- ============================================
CREATE OR REPLACE VIEW v_payment_schedule_summary AS
SELECT
  si.company_id,
  si.supplier_id,
  c.name AS supplier_name,
  COUNT(si.id) AS pending_invoices,
  SUM(si.total) AS total_amount,
  MIN(si.due_date) AS next_due_date,
  SUM(CASE WHEN si.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count,
  SUM(CASE WHEN si.due_date < CURDATE() THEN si.total ELSE 0 END) AS overdue_amount,
  SUM(CASE WHEN si.due_date >= CURDATE() AND si.due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN si.total ELSE 0 END) AS due_this_week,
  SUM(CASE WHEN si.due_date >= CURDATE() AND si.due_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN si.total ELSE 0 END) AS due_this_month
FROM supplier_invoices si
INNER JOIN contacts c ON si.supplier_id = c.id
WHERE si.status IN ('received', 'approved')
  AND si.payment_date IS NULL
GROUP BY si.company_id, si.supplier_id, c.name;

-- ============================================
-- Indexes pour performance
-- ============================================
CREATE INDEX IF NOT EXISTS idx_supplier_invoices_created_at ON supplier_invoices(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_payments_created_at ON payments(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_payment_schedules_reminder ON payment_schedules(reminder_sent, due_date);

-- ============================================
-- Triggers: Mise à jour automatique du statut
-- ============================================
DELIMITER //

CREATE TRIGGER trg_update_supplier_invoice_status_after_payment
AFTER INSERT ON payments
FOR EACH ROW
BEGIN
  DECLARE total_paid DECIMAL(12,2);
  DECLARE invoice_total DECIMAL(12,2);

  IF NEW.supplier_invoice_id IS NOT NULL THEN
    -- Calculer le montant total payé
    SELECT COALESCE(SUM(amount), 0) INTO total_paid
    FROM payments
    WHERE supplier_invoice_id = NEW.supplier_invoice_id;

    -- Récupérer le total de la facture
    SELECT total INTO invoice_total
    FROM supplier_invoices
    WHERE id = NEW.supplier_invoice_id;

    -- Mettre à jour le statut si entièrement payé
    IF total_paid >= invoice_total THEN
      UPDATE supplier_invoices
      SET status = 'paid',
          payment_date = NEW.payment_date
      WHERE id = NEW.supplier_invoice_id;
    END IF;
  END IF;
END//

DELIMITER ;

-- ============================================
-- Données de test (optionnel)
-- ============================================
-- Commenter en production

/*
-- Exemple: Créer un fournisseur test
INSERT INTO contacts (company_id, name, type, email, phone, address, created_at)
VALUES (1, 'Fournisseur Test SA', 'supplier', 'contact@fournisseur-test.ch', '+41 22 123 45 67', 'Rue du Commerce 10, 1204 Genève', NOW());

-- Exemple: Créer une facture fournisseur test
INSERT INTO supplier_invoices (company_id, supplier_id, invoice_number, invoice_date, due_date, subtotal, tva_amount, total, status, created_by)
VALUES (1, LAST_INSERT_ID(), 'FOURNTEST-2024-001', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 30 DAY), 1000.00, 77.00, 1077.00, 'received', 1);

-- Exemple: Ajouter des lignes
INSERT INTO supplier_invoice_items (supplier_invoice_id, description, quantity, unit_price, tva_rate, tva_amount, subtotal, total)
VALUES (LAST_INSERT_ID(), 'Fourniture bureau', 10, 100.00, 7.70, 77.00, 1000.00, 1077.00);
*/

-- ============================================
-- Fin de l'installation
-- ============================================
SELECT 'Installation module Gestion Fournisseurs terminée avec succès!' AS message;
