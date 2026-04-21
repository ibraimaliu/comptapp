-- ============================================================================
-- MODULE GESTION DES SALAIRES
-- ============================================================================
-- Ce script crée les tables nécessaires pour le module de gestion des salaires
-- avec restrictions par plan d'abonnement
-- ============================================================================

-- Table: employees (Employés)
-- Stocke les informations des employés de l'entreprise
CREATE TABLE IF NOT EXISTS `employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_number` varchar(50) DEFAULT NULL COMMENT 'Numéro matricule',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Suisse',

  -- Informations contractuelles
  `hire_date` date NOT NULL COMMENT 'Date d\'embauche',
  `termination_date` date DEFAULT NULL COMMENT 'Date de fin de contrat',
  `job_title` varchar(255) NOT NULL COMMENT 'Poste',
  `department` varchar(100) DEFAULT NULL COMMENT 'Département',
  `employment_type` enum('full_time', 'part_time', 'contractor', 'intern') DEFAULT 'full_time',
  `contract_type` enum('cdi', 'cdd', 'temporary', 'apprentice') DEFAULT 'cdi',

  -- Informations salariales
  `salary_type` enum('monthly', 'hourly', 'annual') DEFAULT 'monthly',
  `base_salary` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Salaire de base',
  `currency` varchar(10) DEFAULT 'CHF',
  `hours_per_week` decimal(5,2) DEFAULT 40.00 COMMENT 'Heures par semaine',

  -- Informations AVS/AI/APG (Suisse)
  `avs_number` varchar(50) DEFAULT NULL COMMENT 'Numéro AVS (13 chiffres)',
  `accident_insurance` varchar(255) DEFAULT NULL COMMENT 'Assurance accidents',
  `pension_fund` varchar(255) DEFAULT NULL COMMENT 'Caisse de pension (LPP)',

  -- Informations bancaires
  `iban` varchar(50) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,

  -- Déductions et allocations
  `family_allowances` tinyint(1) DEFAULT 0 COMMENT 'Allocations familiales',
  `num_children` int(11) DEFAULT 0 COMMENT 'Nombre d\'enfants',

  -- Statut
  `is_active` tinyint(1) DEFAULT 1,
  `notes` text DEFAULT NULL,

  -- Audit
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_employee_number` (`employee_number`),
  CONSTRAINT `fk_employee_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: payroll (Fiches de paie)
-- Stocke les fiches de paie mensuelles
CREATE TABLE IF NOT EXISTS `payroll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,

  -- Période
  `period_month` int(2) NOT NULL COMMENT 'Mois (1-12)',
  `period_year` int(4) NOT NULL COMMENT 'Année',
  `payment_date` date DEFAULT NULL COMMENT 'Date de paiement',

  -- Salaire de base
  `base_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `hours_worked` decimal(8,2) DEFAULT NULL COMMENT 'Heures travaillées (pour horaire)',
  `hourly_rate` decimal(10,2) DEFAULT NULL COMMENT 'Taux horaire',

  -- Éléments de paie additionnels
  `overtime_hours` decimal(8,2) DEFAULT 0.00 COMMENT 'Heures supplémentaires',
  `overtime_amount` decimal(15,2) DEFAULT 0.00 COMMENT 'Montant heures sup.',
  `bonus` decimal(15,2) DEFAULT 0.00 COMMENT 'Primes',
  `commission` decimal(15,2) DEFAULT 0.00 COMMENT 'Commissions',
  `allowances` decimal(15,2) DEFAULT 0.00 COMMENT 'Allocations (familiales, etc.)',
  `other_additions` decimal(15,2) DEFAULT 0.00 COMMENT 'Autres ajouts',

  -- Sous-total brut
  `gross_salary` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Salaire brut total',

  -- Cotisations sociales employé (Suisse)
  `avs_ai_apg_employee` decimal(15,2) DEFAULT 0.00 COMMENT 'AVS/AI/APG (5.3%)',
  `ac_employee` decimal(15,2) DEFAULT 0.00 COMMENT 'Assurance chômage (1.1%)',
  `lpp_employee` decimal(15,2) DEFAULT 0.00 COMMENT 'LPP - 2e pilier',
  `laa_employee` decimal(15,2) DEFAULT 0.00 COMMENT 'LAA - Accidents',
  `laac_employee` decimal(15,2) DEFAULT 0.00 COMMENT 'LAAC - Compl. accidents',

  -- Impôt à la source (si applicable)
  `income_tax` decimal(15,2) DEFAULT 0.00 COMMENT 'Impôt à la source',

  -- Autres déductions
  `other_deductions` decimal(15,2) DEFAULT 0.00 COMMENT 'Autres déductions',

  -- Total déductions
  `total_deductions` decimal(15,2) NOT NULL DEFAULT 0.00,

  -- Salaire net
  `net_salary` decimal(15,2) NOT NULL DEFAULT 0.00 COMMENT 'Salaire net à payer',

  -- Charges patronales (pour comptabilité)
  `avs_ai_apg_employer` decimal(15,2) DEFAULT 0.00 COMMENT 'AVS/AI/APG employeur (5.3%)',
  `ac_employer` decimal(15,2) DEFAULT 0.00 COMMENT 'AC employeur (1.1%)',
  `lpp_employer` decimal(15,2) DEFAULT 0.00 COMMENT 'LPP employeur',
  `af_employer` decimal(15,2) DEFAULT 0.00 COMMENT 'Allocations familiales',
  `other_employer_charges` decimal(15,2) DEFAULT 0.00,
  `total_employer_charges` decimal(15,2) DEFAULT 0.00 COMMENT 'Total charges patronales',

  -- Statut
  `status` enum('draft', 'validated', 'paid', 'cancelled') DEFAULT 'draft',
  `pdf_path` varchar(500) DEFAULT NULL COMMENT 'Chemin vers PDF fiche de paie',
  `notes` text DEFAULT NULL,

  -- Lien avec transaction comptable
  `transaction_id` int(11) DEFAULT NULL COMMENT 'Transaction comptable liée',

  -- Audit
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by` int(11) DEFAULT NULL,
  `validated_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_payroll` (`company_id`, `employee_id`, `period_month`, `period_year`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_period` (`period_year`, `period_month`),
  KEY `idx_status` (`status`),
  KEY `idx_transaction` (`transaction_id`),
  CONSTRAINT `fk_payroll_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: payroll_settings (Paramètres salaire par société)
-- Configuration des taux de cotisations sociales
CREATE TABLE IF NOT EXISTS `payroll_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,

  -- Taux de cotisations AVS/AI/APG (Suisse)
  `avs_ai_apg_rate_employee` decimal(5,2) DEFAULT 5.30 COMMENT 'Taux AVS/AI/APG employé (%)',
  `avs_ai_apg_rate_employer` decimal(5,2) DEFAULT 5.30 COMMENT 'Taux AVS/AI/APG employeur (%)',

  -- Assurance chômage (AC)
  `ac_rate_employee` decimal(5,2) DEFAULT 1.10 COMMENT 'Taux AC employé (%)',
  `ac_rate_employer` decimal(5,2) DEFAULT 1.10 COMMENT 'Taux AC employeur (%)',
  `ac_solidarity_rate` decimal(5,2) DEFAULT 0.50 COMMENT 'Cotisation de solidarité (>148,200 CHF)',
  `ac_threshold` decimal(15,2) DEFAULT 148200.00 COMMENT 'Seuil cotisation solidarité',

  -- LPP (2e pilier)
  `lpp_rate_employee` decimal(5,2) DEFAULT 7.00 COMMENT 'Taux LPP employé (%)',
  `lpp_rate_employer` decimal(5,2) DEFAULT 7.00 COMMENT 'Taux LPP employeur (%)',
  `lpp_min_salary` decimal(15,2) DEFAULT 21510.00 COMMENT 'Salaire min LPP (2024)',
  `lpp_max_salary` decimal(15,2) DEFAULT 86040.00 COMMENT 'Salaire max LPP (2024)',

  -- Allocations familiales
  `af_rate` decimal(5,2) DEFAULT 2.00 COMMENT 'Taux allocations familiales (%)',
  `af_amount_per_child` decimal(10,2) DEFAULT 200.00 COMMENT 'Montant par enfant',

  -- Accident (LAA/LAAC)
  `laa_rate` decimal(5,2) DEFAULT 1.00 COMMENT 'Taux LAA',
  `laac_rate` decimal(5,2) DEFAULT 2.00 COMMENT 'Taux LAAC',

  -- Comptes comptables par défaut
  `salary_expense_account` int(11) DEFAULT NULL COMMENT 'Compte charges de salaire',
  `social_charges_account` int(11) DEFAULT NULL COMMENT 'Compte charges sociales',
  `salary_payable_account` int(11) DEFAULT NULL COMMENT 'Compte salaires à payer',

  -- Audit
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_company` (`company_id`),
  CONSTRAINT `fk_payroll_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: time_tracking (Suivi du temps - optionnel)
-- Pour le suivi des heures de travail
CREATE TABLE IF NOT EXISTS `time_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `hours_worked` decimal(5,2) NOT NULL DEFAULT 0.00,
  `overtime_hours` decimal(5,2) DEFAULT 0.00,
  `description` text DEFAULT NULL,
  `approved` tinyint(1) DEFAULT 0,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_date` (`date`),
  KEY `idx_approved` (`approved`),
  CONSTRAINT `fk_time_tracking_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_time_tracking_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MISE À JOUR DES PLANS D'ABONNEMENT
-- ============================================================================

-- Ajouter la colonne max_employees aux plans d'abonnement
ALTER TABLE `subscription_plans`
ADD COLUMN IF NOT EXISTS `max_employees` int(11) DEFAULT 0 COMMENT 'Nombre max d\'employés (-1 = illimité)';

-- Mise à jour des limites par plan
UPDATE `subscription_plans` SET `max_employees` = 0 WHERE `plan_code` = 'free';
UPDATE `subscription_plans` SET `max_employees` = 3 WHERE `plan_code` = 'starter';
UPDATE `subscription_plans` SET `max_employees` = -1 WHERE `plan_code` = 'professional';
UPDATE `subscription_plans` SET `max_employees` = -1 WHERE `plan_code` = 'enterprise';

-- ============================================================================
-- VUES UTILES
-- ============================================================================

-- Vue: Salaire total par employé pour l'année en cours
CREATE OR REPLACE VIEW `v_employee_annual_salary` AS
SELECT
    e.id AS employee_id,
    e.company_id,
    e.first_name,
    e.last_name,
    YEAR(CURDATE()) AS year,
    COUNT(p.id) AS payroll_count,
    SUM(p.gross_salary) AS total_gross,
    SUM(p.net_salary) AS total_net,
    SUM(p.total_employer_charges) AS total_employer_charges,
    AVG(p.net_salary) AS avg_net_salary
FROM employees e
LEFT JOIN payroll p ON e.id = p.employee_id
    AND p.period_year = YEAR(CURDATE())
    AND p.status IN ('validated', 'paid')
WHERE e.is_active = 1
GROUP BY e.id, e.company_id, e.first_name, e.last_name;

-- Vue: Masse salariale par société
CREATE OR REPLACE VIEW `v_company_payroll_summary` AS
SELECT
    c.id AS company_id,
    c.name AS company_name,
    COUNT(DISTINCT e.id) AS total_employees,
    COUNT(DISTINCT CASE WHEN e.is_active = 1 THEN e.id END) AS active_employees,
    COALESCE(SUM(e.base_salary), 0) AS total_monthly_base_salary,
    COALESCE(SUM(p.gross_salary), 0) AS total_paid_gross,
    COALESCE(SUM(p.net_salary), 0) AS total_paid_net,
    COALESCE(SUM(p.total_employer_charges), 0) AS total_employer_charges
FROM companies c
LEFT JOIN employees e ON c.id = e.company_id
LEFT JOIN payroll p ON e.id = p.employee_id
    AND p.period_year = YEAR(CURDATE())
    AND p.status IN ('validated', 'paid')
GROUP BY c.id, c.name;

-- ============================================================================
-- DONNÉES D'EXEMPLE (OPTIONNEL)
-- ============================================================================

-- Insertion des paramètres par défaut pour les sociétés existantes
INSERT IGNORE INTO `payroll_settings` (company_id)
SELECT id FROM companies;

-- ============================================================================
-- INDEX SUPPLÉMENTAIRES POUR PERFORMANCE
-- ============================================================================

-- Index pour recherche rapide
CREATE INDEX IF NOT EXISTS idx_employee_name ON employees(last_name, first_name);
CREATE INDEX IF NOT EXISTS idx_payroll_period_status ON payroll(period_year, period_month, status);

-- ============================================================================
-- FIN DU SCRIPT
-- ============================================================================
