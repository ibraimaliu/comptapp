-- ============================================================================
-- TABLES MANQUANTES - Installation unifiée
-- Application: Gestion Comptable
-- Description: Crée toutes les tables requises par les modèles PHP mais
--              absentes du install.php principal.
-- ============================================================================

-- GROUPE 1: QR-FACTURES
CREATE TABLE IF NOT EXISTS `qr_payment_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `enable_qr_invoice` TINYINT(1) DEFAULT 1,
  `qr_iban` VARCHAR(34) NOT NULL,
  `creditor_name` VARCHAR(100) NOT NULL,
  `creditor_address` VARCHAR(255) NOT NULL,
  `creditor_postal_code` VARCHAR(10) NOT NULL,
  `creditor_city` VARCHAR(100) NOT NULL,
  `creditor_country` VARCHAR(2) DEFAULT 'CH',
  `additional_info` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_company_qr` (`company_id`),
  CONSTRAINT `fk_qr_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `qr_invoice_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `invoice_id` INT NOT NULL,
  `qr_reference` VARCHAR(27) NOT NULL,
  `qr_iban` VARCHAR(34) NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'CHF',
  `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  KEY `idx_qr_reference` (`qr_reference`),
  KEY `idx_qr_invoice_id` (`invoice_id`),
  CONSTRAINT `fk_qr_log_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_qr_log_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 2: DEVIS / QUOTES
CREATE TABLE IF NOT EXISTS `quotes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `contact_id` INT NOT NULL,
  `number` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) DEFAULT NULL,
  `date` DATE NOT NULL,
  `valid_until` DATE DEFAULT NULL,
  `status` ENUM('draft','sent','accepted','rejected','expired','converted') NOT NULL DEFAULT 'draft',
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(12,2) DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'CHF',
  `notes` TEXT DEFAULT NULL,
  `terms` TEXT DEFAULT NULL,
  `footer` TEXT DEFAULT NULL,
  `converted_to_invoice_id` INT DEFAULT NULL,
  `sent_at` TIMESTAMP NULL DEFAULT NULL,
  `accepted_at` TIMESTAMP NULL DEFAULT NULL,
  `rejected_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` INT DEFAULT NULL,
  UNIQUE KEY `unique_quote_number` (`company_id`, `number`),
  KEY `idx_quotes_company` (`company_id`),
  KEY `idx_quotes_contact` (`contact_id`),
  KEY `idx_quotes_status` (`status`),
  CONSTRAINT `fk_quotes_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_quotes_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quote_id` INT NOT NULL,
  `description` TEXT NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tax_rate` DECIMAL(5,2) DEFAULT 0.00,
  `discount_percent` DECIMAL(5,2) DEFAULT 0.00,
  `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `sort_order` INT DEFAULT 0,
  KEY `idx_quote_items_quote` (`quote_id`),
  CONSTRAINT `fk_quote_items_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `quote_status_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `quote_id` INT NOT NULL,
  `old_status` VARCHAR(20) DEFAULT NULL,
  `new_status` VARCHAR(20) NOT NULL,
  `notes` TEXT DEFAULT NULL,
  `changed_by` INT DEFAULT NULL,
  `changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_quote_history_quote` (`quote_id`),
  CONSTRAINT `fk_quote_history_quote` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 3: PRODUITS ET STOCK
CREATE TABLE IF NOT EXISTS `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `type` ENUM('product','service','bundle') NOT NULL DEFAULT 'product',
  `category_id` INT DEFAULT NULL,
  `purchase_price` DECIMAL(10,2) DEFAULT 0.00,
  `selling_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `tva_rate` DECIMAL(5,2) NOT NULL DEFAULT 7.70,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'CHF',
  `stock_quantity` DECIMAL(10,2) DEFAULT 0.00,
  `stock_min` DECIMAL(10,2) DEFAULT 0.00,
  `stock_max` DECIMAL(10,2) DEFAULT NULL,
  `unit` VARCHAR(20) DEFAULT 'pce',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_sellable` TINYINT(1) NOT NULL DEFAULT 1,
  `is_purchasable` TINYINT(1) NOT NULL DEFAULT 1,
  `track_stock` TINYINT(1) NOT NULL DEFAULT 1,
  `supplier_id` INT DEFAULT NULL,
  `barcode` VARCHAR(50) DEFAULT NULL,
  `image_path` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_product_code` (`company_id`, `code`),
  KEY `idx_products_company` (`company_id`),
  CONSTRAINT `fk_products_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_movements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `movement_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type` ENUM('in','out','adjustment','transfer','return') NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL,
  `unit_cost` DECIMAL(10,2) DEFAULT 0.00,
  `total_cost` DECIMAL(10,2) DEFAULT 0.00,
  `reference_type` VARCHAR(50) DEFAULT NULL,
  `reference_id` INT DEFAULT NULL,
  `reason` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_stock_movements_company` (`company_id`),
  KEY `idx_stock_movements_product` (`product_id`),
  CONSTRAINT `fk_stock_movements_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_movements_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_suppliers` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `supplier_id` INT NOT NULL,
  `supplier_code` VARCHAR(50) DEFAULT NULL,
  `purchase_price` DECIMAL(10,2) NOT NULL,
  `min_order_qty` DECIMAL(10,2) DEFAULT 1.00,
  `delivery_time_days` INT DEFAULT NULL,
  `is_preferred` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_product_supplier` (`product_id`, `supplier_id`),
  CONSTRAINT `fk_product_suppliers_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_product_suppliers_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stock_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `alert_type` ENUM('low_stock','out_of_stock','overstock','expiring') NOT NULL,
  `alert_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `quantity` DECIMAL(10,2) NOT NULL,
  `threshold` DECIMAL(10,2) DEFAULT NULL,
  `status` ENUM('active','resolved','ignored') DEFAULT 'active',
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_stock_alerts_company` (`company_id`),
  KEY `idx_stock_alerts_product` (`product_id`),
  CONSTRAINT `fk_stock_alerts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_alerts_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 4: COMPTES BANCAIRES
CREATE TABLE IF NOT EXISTS `bank_accounts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `bank_name` VARCHAR(100) DEFAULT NULL,
  `iban` VARCHAR(34) DEFAULT NULL,
  `account_number` VARCHAR(50) DEFAULT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'CHF',
  `opening_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `opening_balance_date` DATE DEFAULT NULL,
  `current_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `last_reconciliation_date` DATE DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_bank_accounts_company` (`company_id`),
  CONSTRAINT `fk_bank_accounts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_transactions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bank_account_id` INT NOT NULL,
  `company_id` INT NOT NULL,
  `transaction_date` DATE NOT NULL,
  `value_date` DATE DEFAULT NULL,
  `description` TEXT NOT NULL,
  `amount` DECIMAL(15,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'CHF',
  `balance_after` DECIMAL(15,2) DEFAULT NULL,
  `counterparty_name` VARCHAR(200) DEFAULT NULL,
  `counterparty_account` VARCHAR(34) DEFAULT NULL,
  `qr_reference` VARCHAR(27) DEFAULT NULL,
  `status` ENUM('pending','matched','manual','ignored') NOT NULL DEFAULT 'pending',
  `matched_invoice_id` INT DEFAULT NULL,
  `matched_transaction_id` INT DEFAULT NULL,
  `import_format` ENUM('camt053','mt940','csv','manual') DEFAULT NULL,
  `import_date` DATETIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_bank_tx_account` (`bank_account_id`),
  KEY `idx_bank_tx_company` (`company_id`),
  KEY `idx_bank_tx_status` (`status`),
  KEY `idx_bank_tx_qr` (`qr_reference`),
  CONSTRAINT `fk_bank_tx_account` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bank_tx_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_import_configs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `bank_account_id` INT DEFAULT NULL,
  `config_name` VARCHAR(100) NOT NULL,
  `format_type` ENUM('csv','camt053','mt940') NOT NULL DEFAULT 'csv',
  `csv_delimiter` VARCHAR(1) DEFAULT ',',
  `csv_has_header` TINYINT(1) DEFAULT 1,
  `column_mapping` TEXT DEFAULT NULL,
  `date_format` VARCHAR(20) DEFAULT 'd.m.Y',
  `is_default` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_bank_import_company` (`company_id`),
  CONSTRAINT `fk_bank_import_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `bank_reconciliation_rules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `rule_name` VARCHAR(100) NOT NULL,
  `rule_type` ENUM('qr_reference','amount_exact','amount_range','description_keyword','counterparty_name') NOT NULL,
  `priority` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `rule_params` TEXT DEFAULT NULL,
  `auto_match` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_reconciliation_rules_company` (`company_id`),
  CONSTRAINT `fk_reconciliation_rules_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 5: FACTURES FOURNISSEURS ET PAIEMENTS
CREATE TABLE IF NOT EXISTS `supplier_invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `supplier_id` INT NOT NULL,
  `invoice_number` VARCHAR(50) NOT NULL,
  `invoice_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `reception_date` DATE DEFAULT NULL,
  `subtotal` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `tva_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `total` DECIMAL(12,2) NOT NULL,
  `status` ENUM('received','approved','paid','cancelled','disputed') NOT NULL DEFAULT 'received',
  `payment_date` DATE DEFAULT NULL,
  `payment_method` ENUM('bank_transfer','cash','card','other') DEFAULT NULL,
  `qr_reference` VARCHAR(27) DEFAULT NULL,
  `iban` VARCHAR(34) DEFAULT NULL,
  `scanned_pdf_path` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `approved_by` INT DEFAULT NULL,
  `approved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_supplier_invoices_company` (`company_id`),
  KEY `idx_supplier_invoices_status` (`status`),
  CONSTRAINT `fk_supplier_invoices_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_supplier_invoices_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `contacts` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `supplier_invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `supplier_invoice_id` INT NOT NULL,
  `description` TEXT NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(12,2) NOT NULL,
  `tva_rate` DECIMAL(5,2) NOT NULL DEFAULT 7.70,
  `tva_amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `subtotal` DECIMAL(12,2) NOT NULL,
  `total` DECIMAL(12,2) NOT NULL,
  `sort_order` INT DEFAULT 0,
  KEY `idx_supplier_invoice_items_invoice` (`supplier_invoice_id`),
  CONSTRAINT `fk_supplier_invoice_items` FOREIGN KEY (`supplier_invoice_id`) REFERENCES `supplier_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `payment_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `currency` VARCHAR(3) NOT NULL DEFAULT 'CHF',
  `payment_method` ENUM('bank_transfer','cash','card','check','other') NOT NULL,
  `payment_type` ENUM('supplier_payment','customer_payment','other') NOT NULL,
  `reference` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `supplier_invoice_id` INT DEFAULT NULL,
  `invoice_id` INT DEFAULT NULL,
  `bank_account_id` INT DEFAULT NULL,
  `contact_id` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_payments_company` (`company_id`),
  KEY `idx_payments_date` (`payment_date`),
  CONSTRAINT `fk_payments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `supplier_invoice_id` INT DEFAULT NULL,
  `contact_id` INT NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `status` ENUM('pending','paid','overdue','cancelled') NOT NULL DEFAULT 'pending',
  `payment_id` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_payment_schedules_company` (`company_id`),
  CONSTRAINT `fk_payment_schedules_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 6: RAPPELS DE PAIEMENT
CREATE TABLE IF NOT EXISTS `payment_reminders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `invoice_id` INT NOT NULL,
  `reminder_level` INT NOT NULL,
  `sent_date` DATE NOT NULL,
  `due_date` DATE NOT NULL,
  `days_overdue` INT NOT NULL,
  `original_amount` DECIMAL(10,2) NOT NULL,
  `amount_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `amount_due` DECIMAL(10,2) NOT NULL,
  `interest_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `fees` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('draft','sent','paid','cancelled','overdue') NOT NULL DEFAULT 'draft',
  `email_sent` TINYINT(1) NOT NULL DEFAULT 0,
  `email_sent_date` DATETIME DEFAULT NULL,
  `email_opened` TINYINT(1) NOT NULL DEFAULT 0,
  `email_opened_date` DATETIME DEFAULT NULL,
  `pdf_path` VARCHAR(255) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `sent_by_user_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_payment_reminders_company` (`company_id`),
  KEY `idx_payment_reminders_invoice` (`invoice_id`),
  KEY `idx_payment_reminders_status` (`status`),
  CONSTRAINT `fk_payment_reminders_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payment_reminders_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reminder_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `level1_days` INT NOT NULL DEFAULT 10,
  `level2_days` INT NOT NULL DEFAULT 20,
  `level3_days` INT NOT NULL DEFAULT 30,
  `level1_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `level2_fee` DECIMAL(10,2) NOT NULL DEFAULT 10.00,
  `level3_fee` DECIMAL(10,2) NOT NULL DEFAULT 20.00,
  `interest_rate` DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `apply_interest` TINYINT(1) NOT NULL DEFAULT 1,
  `grace_period_days` INT NOT NULL DEFAULT 5,
  `auto_send` TINYINT(1) NOT NULL DEFAULT 0,
  `level1_subject` VARCHAR(200) DEFAULT 'Rappel de paiement - Facture {invoice_number}',
  `level2_subject` VARCHAR(200) DEFAULT 'Deuxième rappel - Facture {invoice_number}',
  `level3_subject` VARCHAR(200) DEFAULT 'Mise en demeure - Facture {invoice_number}',
  `level1_message` TEXT DEFAULT NULL,
  `level2_message` TEXT DEFAULT NULL,
  `level3_message` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_reminder_settings_company` (`company_id`),
  CONSTRAINT `fk_reminder_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reminder_history_log` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `reminder_id` INT DEFAULT NULL,
  `invoice_id` INT NOT NULL,
  `action` VARCHAR(50) NOT NULL,
  `action_by_user_id` INT DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_reminder_log_company` (`company_id`),
  CONSTRAINT `fk_reminder_log_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 7: TRÉSORERIE
CREATE TABLE IF NOT EXISTS `treasury_forecasts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `forecast_date` DATE NOT NULL,
  `expected_income` DECIMAL(10,2) DEFAULT 0.00,
  `expected_expenses` DECIMAL(10,2) DEFAULT 0.00,
  `actual_income` DECIMAL(10,2) DEFAULT 0.00,
  `actual_expenses` DECIMAL(10,2) DEFAULT 0.00,
  `opening_balance` DECIMAL(10,2) DEFAULT 0.00,
  `closing_balance` DECIMAL(10,2) DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `is_actual` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_treasury_company_date` (`company_id`, `forecast_date`),
  CONSTRAINT `fk_treasury_forecasts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `treasury_alerts` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `alert_type` ENUM('low_balance','negative_forecast','overdue_invoices','large_expense') NOT NULL,
  `alert_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `threshold_amount` DECIMAL(10,2) DEFAULT NULL,
  `actual_amount` DECIMAL(10,2) DEFAULT NULL,
  `severity` ENUM('info','warning','critical') DEFAULT 'warning',
  `message` TEXT NOT NULL,
  `status` ENUM('active','resolved','ignored') DEFAULT 'active',
  `resolved_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_treasury_alerts_company` (`company_id`),
  CONSTRAINT `fk_treasury_alerts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `treasury_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `min_balance_alert` DECIMAL(10,2) DEFAULT 5000.00,
  `critical_balance_alert` DECIMAL(10,2) DEFAULT 1000.00,
  `forecast_horizon_days` INT DEFAULT 90,
  `alert_email_enabled` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_treasury_settings_company` (`company_id`),
  CONSTRAINT `fk_treasury_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `treasury_scenarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `scenario_name` VARCHAR(100) NOT NULL,
  `scenario_type` ENUM('pessimistic','realistic','optimistic','custom') DEFAULT 'realistic',
  `income_adjustment_percent` DECIMAL(5,2) DEFAULT 0.00,
  `expense_adjustment_percent` DECIMAL(5,2) DEFAULT 0.00,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_treasury_scenarios_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 8: FACTURES RÉCURRENTES ET ABONNEMENTS
CREATE TABLE IF NOT EXISTS `recurring_invoices` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `template_name` VARCHAR(100) NOT NULL,
  `contact_id` INT NOT NULL,
  `status` ENUM('active','paused','cancelled','completed') NOT NULL DEFAULT 'active',
  `frequency` ENUM('daily','weekly','biweekly','monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'monthly',
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `next_generation_date` DATE NOT NULL,
  `last_generation_date` DATE DEFAULT NULL,
  `occurrences_count` INT DEFAULT 0,
  `max_occurrences` INT DEFAULT NULL,
  `payment_terms_days` INT DEFAULT 30,
  `currency` VARCHAR(3) DEFAULT 'CHF',
  `notes` TEXT DEFAULT NULL,
  `auto_send_email` TINYINT(1) DEFAULT 0,
  `created_by` INT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_recurring_company` (`company_id`),
  KEY `idx_recurring_status` (`status`),
  CONSTRAINT `fk_recurring_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_recurring_contact` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recurring_invoice_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recurring_invoice_id` INT NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` DECIMAL(10,2) NOT NULL,
  `tva_rate` DECIMAL(5,2) NOT NULL DEFAULT 7.70,
  `sort_order` INT DEFAULT 0,
  CONSTRAINT `fk_recurring_items` FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `recurring_invoice_history` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recurring_invoice_id` INT NOT NULL,
  `generated_invoice_id` INT NOT NULL,
  `generation_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `scheduled_date` DATE NOT NULL,
  `invoice_number` VARCHAR(50) NOT NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('generated','sent','paid','cancelled') DEFAULT 'generated',
  CONSTRAINT `fk_recurring_history` FOREIGN KEY (`recurring_invoice_id`) REFERENCES `recurring_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscriptions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `contact_id` INT NOT NULL,
  `recurring_invoice_id` INT DEFAULT NULL,
  `subscription_name` VARCHAR(100) NOT NULL,
  `status` ENUM('trial','active','paused','cancelled','expired') NOT NULL DEFAULT 'active',
  `start_date` DATE NOT NULL,
  `current_period_start` DATE NOT NULL,
  `current_period_end` DATE NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'CHF',
  `billing_cycle` ENUM('monthly','quarterly','semiannual','annual') NOT NULL DEFAULT 'monthly',
  `auto_renew` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_subscriptions_company` (`company_id`),
  CONSTRAINT `fk_subscriptions_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `subscription_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `subscription_id` INT NOT NULL,
  `event_type` ENUM('created','activated','renewed','paused','cancelled','expired','payment_received','payment_failed') NOT NULL,
  `event_date` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `amount` DECIMAL(10,2) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  CONSTRAINT `fk_subscription_events` FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 9: PAIE ET EMPLOYÉS
CREATE TABLE IF NOT EXISTS `employees` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `employee_number` VARCHAR(50) DEFAULT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `postal_code` VARCHAR(20) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT 'Suisse',
  `hire_date` DATE NOT NULL,
  `termination_date` DATE DEFAULT NULL,
  `job_title` VARCHAR(255) NOT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `employment_type` ENUM('full_time','part_time','contractor','intern') DEFAULT 'full_time',
  `contract_type` ENUM('cdi','cdd','temporary','apprentice') DEFAULT 'cdi',
  `salary_type` ENUM('monthly','hourly','annual') DEFAULT 'monthly',
  `base_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(10) DEFAULT 'CHF',
  `hours_per_week` DECIMAL(5,2) DEFAULT 40.00,
  `avs_number` VARCHAR(50) DEFAULT NULL,
  `accident_insurance` VARCHAR(255) DEFAULT NULL,
  `pension_fund` VARCHAR(255) DEFAULT NULL,
  `iban` VARCHAR(50) DEFAULT NULL,
  `bank_name` VARCHAR(255) DEFAULT NULL,
  `family_allowances` TINYINT(1) DEFAULT 0,
  `num_children` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_employees_company` (`company_id`),
  CONSTRAINT `fk_employees_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payroll` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `period_month` INT NOT NULL,
  `period_year` INT NOT NULL,
  `payment_date` DATE DEFAULT NULL,
  `base_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `hours_worked` DECIMAL(8,2) DEFAULT NULL,
  `overtime_hours` DECIMAL(8,2) DEFAULT 0.00,
  `overtime_amount` DECIMAL(15,2) DEFAULT 0.00,
  `bonus` DECIMAL(15,2) DEFAULT 0.00,
  `gross_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `avs_ai_apg_employee` DECIMAL(15,2) DEFAULT 0.00,
  `ac_employee` DECIMAL(15,2) DEFAULT 0.00,
  `lpp_employee` DECIMAL(15,2) DEFAULT 0.00,
  `total_deductions` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `net_salary` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `avs_ai_apg_employer` DECIMAL(15,2) DEFAULT 0.00,
  `total_employer_charges` DECIMAL(15,2) DEFAULT 0.00,
  `status` ENUM('draft','validated','paid','cancelled') DEFAULT 'draft',
  `pdf_path` VARCHAR(500) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `transaction_id` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_payroll` (`company_id`, `employee_id`, `period_month`, `period_year`),
  KEY `idx_payroll_employee` (`employee_id`),
  KEY `idx_payroll_status` (`status`),
  CONSTRAINT `fk_payroll_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payroll_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `avs_ai_apg_rate_employee` DECIMAL(5,2) DEFAULT 5.30,
  `avs_ai_apg_rate_employer` DECIMAL(5,2) DEFAULT 5.30,
  `ac_rate_employee` DECIMAL(5,2) DEFAULT 1.10,
  `ac_rate_employer` DECIMAL(5,2) DEFAULT 1.10,
  `lpp_rate_employee` DECIMAL(5,2) DEFAULT 7.00,
  `lpp_rate_employer` DECIMAL(5,2) DEFAULT 7.00,
  `lpp_min_salary` DECIMAL(15,2) DEFAULT 21510.00,
  `af_rate` DECIMAL(5,2) DEFAULT 2.00,
  `af_amount_per_child` DECIMAL(10,2) DEFAULT 200.00,
  `laa_rate` DECIMAL(5,2) DEFAULT 1.00,
  `salary_expense_account` INT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_payroll_settings_company` (`company_id`),
  CONSTRAINT `fk_payroll_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `time_tracking` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT NOT NULL,
  `employee_id` INT NOT NULL,
  `date` DATE NOT NULL,
  `hours_worked` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` DECIMAL(5,2) DEFAULT 0.00,
  `description` TEXT DEFAULT NULL,
  `approved` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_time_tracking_employee` (`employee_id`),
  CONSTRAINT `fk_time_tracking_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_time_tracking_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GROUPE 10: COLONNES MANQUANTES SUR TABLES EXISTANTES
ALTER TABLE `transactions`
  ADD COLUMN IF NOT EXISTS `counterpart_account_id` INT DEFAULT NULL AFTER `account_id`,
  ADD COLUMN IF NOT EXISTS `category_id` INT DEFAULT NULL AFTER `description`;

ALTER TABLE `companies`
  ADD COLUMN IF NOT EXISTS `address` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `postal_code` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `city` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `country` VARCHAR(2) DEFAULT 'CH',
  ADD COLUMN IF NOT EXISTS `phone` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `email` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `website` VARCHAR(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `ide_number` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `tva_number` VARCHAR(20) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bank_name` VARCHAR(100) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `iban` VARCHAR(34) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bic` VARCHAR(11) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `qr_iban` VARCHAR(34) DEFAULT NULL;

ALTER TABLE `invoices`
  ADD COLUMN IF NOT EXISTS `contact_id` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `due_date` DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `qr_reference` VARCHAR(27) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `paid_date` DATE DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `notes` TEXT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE `accounting_plan`
  ADD COLUMN IF NOT EXISTS `parent_id` INT DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `is_selectable` TINYINT(1) NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `sort_order` INT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `categories`
  ADD COLUMN IF NOT EXISTS `type` ENUM('income','expense') DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

ALTER TABLE `tva_rates`
  ADD COLUMN IF NOT EXISTS `name` VARCHAR(50) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP;

SELECT 'install_missing_tables.sql execute avec succes' AS status;
