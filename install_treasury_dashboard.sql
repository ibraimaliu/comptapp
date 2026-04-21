-- ============================================
-- Installation: Module Dashboard de Trésorerie
-- Version: 1.0
-- Description: Prévisions de trésorerie, alertes et suivi en temps réel
-- ============================================

-- ============================================
-- Table: treasury_forecasts
-- Description: Prévisions de trésorerie par période
-- ============================================
CREATE TABLE IF NOT EXISTS `treasury_forecasts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `forecast_date` date NOT NULL,
  `expected_income` decimal(10,2) DEFAULT 0.00,
  `expected_expenses` decimal(10,2) DEFAULT 0.00,
  `actual_income` decimal(10,2) DEFAULT 0.00,
  `actual_expenses` decimal(10,2) DEFAULT 0.00,
  `opening_balance` decimal(10,2) DEFAULT 0.00,
  `closing_balance` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_actual` tinyint(1) DEFAULT 0 COMMENT '1 = réalisé, 0 = prévision',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_company_date` (`company_id`, `forecast_date`),
  KEY `idx_treasury_company` (`company_id`),
  KEY `idx_treasury_date` (`forecast_date`),
  CONSTRAINT `fk_treasury_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: treasury_alerts
-- Description: Alertes de trésorerie (seuils, ruptures)
-- ============================================
CREATE TABLE IF NOT EXISTS `treasury_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `alert_type` enum('low_balance','negative_forecast','overdue_invoices','large_expense') NOT NULL,
  `alert_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `threshold_amount` decimal(10,2) DEFAULT NULL,
  `actual_amount` decimal(10,2) DEFAULT NULL,
  `forecast_date` date DEFAULT NULL COMMENT 'Date de la prévision concernée',
  `severity` enum('info','warning','critical') DEFAULT 'warning',
  `message` text NOT NULL,
  `status` enum('active','resolved','ignored') DEFAULT 'active',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alerts_company` (`company_id`),
  KEY `idx_alerts_status` (`status`),
  KEY `idx_alerts_type` (`alert_type`),
  KEY `idx_alerts_date` (`alert_date`),
  CONSTRAINT `fk_alerts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_alerts_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: treasury_settings
-- Description: Configuration des seuils et paramètres
-- ============================================
CREATE TABLE IF NOT EXISTS `treasury_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `min_balance_alert` decimal(10,2) DEFAULT 5000.00 COMMENT 'Seuil minimum avant alerte',
  `critical_balance_alert` decimal(10,2) DEFAULT 1000.00 COMMENT 'Seuil critique',
  `forecast_horizon_days` int(11) DEFAULT 90 COMMENT 'Nombre de jours de prévision',
  `alert_email_enabled` tinyint(1) DEFAULT 1,
  `alert_email_recipients` text DEFAULT NULL COMMENT 'Emails séparés par virgules',
  `working_capital_target` decimal(10,2) DEFAULT NULL COMMENT 'Fonds de roulement cible',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_treasury_company` (`company_id`),
  CONSTRAINT `fk_treasury_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table: treasury_scenarios
-- Description: Scénarios de trésorerie (optimiste, pessimiste, réaliste)
-- ============================================
CREATE TABLE IF NOT EXISTS `treasury_scenarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `scenario_name` varchar(100) NOT NULL,
  `scenario_type` enum('pessimistic','realistic','optimistic','custom') DEFAULT 'realistic',
  `income_adjustment_percent` decimal(5,2) DEFAULT 0.00 COMMENT '% ajustement recettes',
  `expense_adjustment_percent` decimal(5,2) DEFAULT 0.00 COMMENT '% ajustement dépenses',
  `payment_delay_days` int(11) DEFAULT 0 COMMENT 'Retard moyen paiements clients',
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_scenarios_company` (`company_id`),
  CONSTRAINT `fk_scenarios_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Vue: Synthèse trésorerie avec prévisions
-- ============================================
CREATE OR REPLACE VIEW v_treasury_summary AS
SELECT
  tf.company_id,
  tf.forecast_date,
  tf.expected_income,
  tf.expected_expenses,
  tf.actual_income,
  tf.actual_expenses,
  tf.opening_balance,
  tf.closing_balance,
  (tf.expected_income - tf.expected_expenses) AS net_forecast,
  (tf.actual_income - tf.actual_expenses) AS net_actual,
  tf.is_actual,
  CASE
    WHEN tf.closing_balance < 0 THEN 'negative'
    WHEN tf.closing_balance < 1000 THEN 'critical'
    WHEN tf.closing_balance < 5000 THEN 'warning'
    ELSE 'healthy'
  END AS status
FROM treasury_forecasts tf
ORDER BY tf.forecast_date;

-- ============================================
-- Vue: Alertes actives avec détails
-- ============================================
CREATE OR REPLACE VIEW v_active_treasury_alerts AS
SELECT
  ta.id,
  ta.company_id,
  c.name AS company_name,
  ta.alert_type,
  ta.alert_date,
  ta.threshold_amount,
  ta.actual_amount,
  ta.forecast_date,
  ta.severity,
  ta.message,
  ta.status,
  DATEDIFF(NOW(), ta.alert_date) AS days_active
FROM treasury_alerts ta
INNER JOIN companies c ON ta.company_id = c.id
WHERE ta.status = 'active'
ORDER BY
  FIELD(ta.severity, 'critical', 'warning', 'info'),
  ta.alert_date DESC;

-- ============================================
-- Insertion des paramètres par défaut
-- ============================================
-- Note: Les paramètres seront créés automatiquement lors du premier accès au dashboard

-- ============================================
-- Insertion de scénarios par défaut
-- ============================================
-- Note: Les scénarios seront créés automatiquement pour chaque entreprise

-- ============================================
-- Index supplémentaires pour performances
-- ============================================
CREATE INDEX IF NOT EXISTS idx_forecasts_is_actual ON treasury_forecasts(is_actual);
CREATE INDEX IF NOT EXISTS idx_alerts_severity ON treasury_alerts(severity);

-- ============================================
-- Fin de l'installation
-- ============================================
SELECT 'Installation module Dashboard de Trésorerie terminée avec succès!' AS message;
