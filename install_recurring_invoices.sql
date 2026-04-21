-- ============================================
-- Installation: Module Factures Récurrentes/Abonnements
-- Version: 1.0
-- Description: Gestion des factures récurrentes et abonnements
-- ============================================

-- ============================================
-- Table: recurring_invoices
-- Description: Templates de factures récurrentes
-- ============================================
CREATE TABLE IF NOT EXISTS `recurring_invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `status` enum('active','paused','cancelled','completed') NOT NULL DEFAULT 'active',

  -- Récurrence
  `frequency` enum('daily','weekly','biweekly','monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL COMMENT 'Null = sans fin',
  `next_generation_date` date NOT NULL,
  `last_generation_date` date DEFAULT NULL,

  -- Compteurs
  `occurrences_count` int(11) DEFAULT 0 COMMENT 'Nombre de factures générées',
  `max_occurrences` int(11) DEFAULT NULL COMMENT 'Null = illimité',

  -- Détails facture
  `invoice_prefix` varchar(20) DEFAULT 'FACT',
  `payment_terms_days` int(11) DEFAULT 30,
  `currency` varchar(3) DEFAULT 'CHF',
  `notes` text DEFAULT NULL,
  `footer_text` text DEFAULT NULL,

  -- Options
  `auto_send_email` tinyint(1) DEFAULT 0,
  `email_template_id` int(11) DEFAULT NULL,
  `auto_mark_sent` tinyint(1) DEFAULT 1,

  -- Métadonnées
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_recurring_company` (`company_id`),
  INDEX `idx_recurring_contact` (`contact_id`),
  INDEX `idx_recurring_status` (`status`),
  INDEX `idx_recurring_next_date` (`next_generation_date`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: recurring_invoice_items
-- Description: Lignes des factures récurrentes (template)
-- ============================================
CREATE TABLE IF NOT EXISTS `recurring_invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(10,2) NOT NULL,
  `tva_rate` decimal(5,2) NOT NULL DEFAULT 7.70,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0,

  PRIMARY KEY (`id`),
  INDEX `idx_recurring_items_recurring` (`recurring_invoice_id`),
  INDEX `idx_recurring_items_product` (`product_id`),
  FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: recurring_invoice_history
-- Description: Historique des générations
-- ============================================
CREATE TABLE IF NOT EXISTS `recurring_invoice_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `recurring_invoice_id` int(11) NOT NULL,
  `generated_invoice_id` int(11) NOT NULL,
  `generation_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `scheduled_date` date NOT NULL COMMENT 'Date prévue de génération',
  `invoice_date` date NOT NULL COMMENT 'Date de la facture générée',
  `invoice_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('generated','sent','paid','cancelled') DEFAULT 'generated',
  `sent_at` timestamp NULL DEFAULT NULL,
  `paid_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,

  PRIMARY KEY (`id`),
  INDEX `idx_history_recurring` (`recurring_invoice_id`),
  INDEX `idx_history_invoice` (`generated_invoice_id`),
  INDEX `idx_history_scheduled` (`scheduled_date`),
  FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`generated_invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: subscriptions
-- Description: Gestion des abonnements clients
-- ============================================
CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `recurring_invoice_id` int(11) DEFAULT NULL,

  -- Détails abonnement
  `subscription_name` varchar(100) NOT NULL,
  `subscription_type` enum('product','service','bundle','other') DEFAULT 'service',
  `status` enum('trial','active','paused','cancelled','expired') NOT NULL DEFAULT 'active',

  -- Période
  `start_date` date NOT NULL,
  `trial_end_date` date DEFAULT NULL,
  `current_period_start` date NOT NULL,
  `current_period_end` date NOT NULL,
  `cancel_at_period_end` tinyint(1) DEFAULT 0,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,

  -- Tarification
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'CHF',
  `billing_cycle` enum('monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'monthly',

  -- Renouvellement
  `auto_renew` tinyint(1) DEFAULT 1,
  `renewal_reminder_days` int(11) DEFAULT 7 COMMENT 'Rappel X jours avant',

  -- Métadonnées
  `metadata` text DEFAULT NULL COMMENT 'JSON pour données additionnelles',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_subscriptions_company` (`company_id`),
  INDEX `idx_subscriptions_contact` (`contact_id`),
  INDEX `idx_subscriptions_status` (`status`),
  INDEX `idx_subscriptions_period_end` (`current_period_end`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`contact_id`) REFERENCES `contacts`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: subscription_events
-- Description: Événements liés aux abonnements
-- ============================================
CREATE TABLE IF NOT EXISTS `subscription_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `subscription_id` int(11) NOT NULL,
  `event_type` enum('created','activated','renewed','paused','cancelled','expired','payment_received','payment_failed') NOT NULL,
  `event_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `amount` decimal(10,2) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `metadata` text DEFAULT NULL COMMENT 'JSON pour détails',

  PRIMARY KEY (`id`),
  INDEX `idx_events_subscription` (`subscription_id`),
  INDEX `idx_events_type` (`event_type`),
  INDEX `idx_events_date` (`event_date`),
  FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Vue: Factures récurrentes actives avec prochaine date
-- ============================================
CREATE OR REPLACE VIEW v_active_recurring_invoices AS
SELECT
  ri.id,
  ri.company_id,
  ri.template_name,
  c.name AS contact_name,
  ri.frequency,
  ri.start_date,
  ri.end_date,
  ri.next_generation_date,
  ri.last_generation_date,
  ri.occurrences_count,
  ri.max_occurrences,
  ri.status,
  DATEDIFF(ri.next_generation_date, CURDATE()) AS days_until_next,
  -- Calculer le total depuis les items
  COALESCE(SUM(
    rii.quantity * rii.unit_price * (1 - rii.discount_percent / 100) * (1 + rii.tva_rate / 100)
  ), 0) AS estimated_total
FROM recurring_invoices ri
INNER JOIN contacts c ON ri.contact_id = c.id
LEFT JOIN recurring_invoice_items rii ON ri.id = rii.recurring_invoice_id
WHERE ri.status = 'active'
GROUP BY ri.id, ri.company_id, ri.template_name, c.name, ri.frequency,
         ri.start_date, ri.end_date, ri.next_generation_date,
         ri.last_generation_date, ri.occurrences_count, ri.max_occurrences, ri.status
ORDER BY ri.next_generation_date ASC;

-- ============================================
-- Vue: Abonnements avec informations enrichies
-- ============================================
CREATE OR REPLACE VIEW v_subscriptions_overview AS
SELECT
  s.id,
  s.company_id,
  s.subscription_name,
  c.name AS contact_name,
  c.email AS contact_email,
  s.status,
  s.start_date,
  s.current_period_start,
  s.current_period_end,
  s.amount,
  s.currency,
  s.billing_cycle,
  s.auto_renew,
  DATEDIFF(s.current_period_end, CURDATE()) AS days_until_renewal,
  CASE
    WHEN s.status = 'trial' THEN 'En période d\'essai'
    WHEN s.status = 'active' AND DATEDIFF(s.current_period_end, CURDATE()) <= 7 THEN 'Renouvellement proche'
    WHEN s.status = 'active' THEN 'Actif'
    WHEN s.status = 'paused' THEN 'En pause'
    WHEN s.status = 'cancelled' THEN 'Annulé'
    WHEN s.status = 'expired' THEN 'Expiré'
    ELSE 'Inconnu'
  END AS status_label
FROM subscriptions s
INNER JOIN contacts c ON s.contact_id = c.id
ORDER BY s.current_period_end ASC;

-- ============================================
-- Index supplémentaires pour performances
-- ============================================
CREATE INDEX IF NOT EXISTS idx_recurring_frequency ON recurring_invoices(frequency);
CREATE INDEX IF NOT EXISTS idx_subscriptions_billing ON subscriptions(billing_cycle);
CREATE INDEX IF NOT EXISTS idx_subscription_auto_renew ON subscriptions(auto_renew);

-- ============================================
-- Fin de l'installation
-- ============================================
SELECT 'Installation module Factures Récurrentes/Abonnements terminée avec succès!' AS message;
