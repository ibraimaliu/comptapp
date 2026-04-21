-- ============================================================================
-- BASE DE DONNÉES MASTER MULTI-TENANT
-- ============================================================================
-- Description: Base centrale pour gérer tous les clients (tenants)
-- Chaque client aura sa propre base de données isolée
-- Date: 2025-11-15
-- ============================================================================

-- Créer la base de données master
CREATE DATABASE IF NOT EXISTS `gestion_comptable_master`
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `gestion_comptable_master`;

-- ============================================================================
-- TABLE: tenants (Clients/Locataires)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tenant_code` VARCHAR(50) NOT NULL UNIQUE,
  `company_name` VARCHAR(255) NOT NULL,
  `database_name` VARCHAR(100) NOT NULL UNIQUE,

  -- Informations de contact
  `contact_name` VARCHAR(255) NOT NULL,
  `contact_email` VARCHAR(255) NOT NULL UNIQUE,
  `contact_phone` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,

  -- Statut et abonnement
  `status` ENUM('active', 'suspended', 'cancelled', 'trial') NOT NULL DEFAULT 'trial',
  `subscription_plan` ENUM('free', 'starter', 'professional', 'enterprise') NOT NULL DEFAULT 'free',
  `trial_ends_at` DATETIME DEFAULT NULL,
  `subscription_started_at` DATETIME DEFAULT NULL,
  `subscription_ends_at` DATETIME DEFAULT NULL,

  -- Limites par plan
  `max_users` INT(11) DEFAULT 1,
  `max_companies` INT(11) DEFAULT 1,
  `max_transactions_per_month` INT(11) DEFAULT 100,
  `max_storage_mb` INT(11) DEFAULT 100,

  -- Informations techniques
  `db_host` VARCHAR(255) DEFAULT 'localhost',
  `db_username` VARCHAR(100) DEFAULT NULL,
  `db_password` VARCHAR(255) DEFAULT NULL,

  -- Métadonnées
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `last_login_at` DATETIME DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,

  PRIMARY KEY (`id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_subscription_plan` (`subscription_plan`),
  INDEX `idx_contact_email` (`contact_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: admin_users (Administrateurs système)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `admin_users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `email` VARCHAR(255) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,

  `first_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) DEFAULT NULL,

  `role` ENUM('super_admin', 'admin', 'support') NOT NULL DEFAULT 'admin',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,

  `last_login_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_email` (`email`),
  INDEX `idx_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: subscription_plans (Plans d'abonnement)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `plan_code` VARCHAR(50) NOT NULL UNIQUE,
  `plan_name` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,

  -- Tarification
  `price_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `price_yearly` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `currency` VARCHAR(3) DEFAULT 'CHF',

  -- Limites
  `max_users` INT(11) DEFAULT 1,
  `max_companies` INT(11) DEFAULT 1,
  `max_transactions_per_month` INT(11) DEFAULT 100,
  `max_storage_mb` INT(11) DEFAULT 100,

  -- Fonctionnalités
  `features` JSON DEFAULT NULL,

  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `display_order` INT(11) DEFAULT 0,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_plan_code` (`plan_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: tenant_subscriptions (Historique des abonnements)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tenant_subscriptions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) NOT NULL,
  `plan_id` INT(11) NOT NULL,

  `status` ENUM('active', 'cancelled', 'expired', 'pending') NOT NULL DEFAULT 'pending',
  `billing_cycle` ENUM('monthly', 'yearly') NOT NULL DEFAULT 'monthly',

  `started_at` DATETIME NOT NULL,
  `ends_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,

  `price` DECIMAL(10,2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'CHF',

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`plan_id`) REFERENCES `subscription_plans`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: audit_logs (Journaux d'audit)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) DEFAULT NULL,
  `admin_user_id` INT(11) DEFAULT NULL,

  `action` VARCHAR(100) NOT NULL,
  `entity_type` VARCHAR(50) DEFAULT NULL,
  `entity_id` INT(11) DEFAULT NULL,

  `details` JSON DEFAULT NULL,

  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  INDEX `idx_tenant_id` (`tenant_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`admin_user_id`) REFERENCES `admin_users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: tenant_usage (Statistiques d'utilisation)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `tenant_usage` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `tenant_id` INT(11) NOT NULL,

  `period_year` INT(4) NOT NULL,
  `period_month` INT(2) NOT NULL,

  `users_count` INT(11) DEFAULT 0,
  `companies_count` INT(11) DEFAULT 0,
  `transactions_count` INT(11) DEFAULT 0,
  `storage_used_mb` DECIMAL(10,2) DEFAULT 0.00,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_tenant_period` (`tenant_id`, `period_year`, `period_month`),
  FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE: system_settings (Paramètres système globaux)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `setting_type` ENUM('string', 'integer', 'boolean', 'json') DEFAULT 'string',
  `description` TEXT DEFAULT NULL,

  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INSERTION DES PLANS PAR DÉFAUT
-- ============================================================================

INSERT INTO `subscription_plans`
(`plan_code`, `plan_name`, `description`, `price_monthly`, `price_yearly`,
 `max_users`, `max_companies`, `max_transactions_per_month`, `max_storage_mb`,
 `features`, `display_order`)
VALUES
-- Plan Gratuit (Essai)
('free', 'Gratuit (Essai)',
 'Parfait pour tester l\'application pendant 30 jours',
 0.00, 0.00,
 1, 1, 100, 100,
 JSON_OBJECT(
   'trial_days', 30,
   'support', 'email',
   'accounting_plan', true,
   'transactions', true,
   'reports', true,
   'multi_user', false,
   'api_access', false
 ),
 1),

-- Plan Starter
('starter', 'Starter',
 'Idéal pour les petites entreprises et indépendants',
 29.00, 290.00,
 2, 2, 500, 500,
 JSON_OBJECT(
   'support', 'email',
   'accounting_plan', true,
   'transactions', true,
   'reports', true,
   'invoicing', true,
   'multi_user', true,
   'api_access', false
 ),
 2),

-- Plan Professional
('professional', 'Professional',
 'Pour les PME en croissance',
 79.00, 790.00,
 10, 5, 2000, 2000,
 JSON_OBJECT(
   'support', 'priority_email',
   'accounting_plan', true,
   'transactions', true,
   'reports', true,
   'invoicing', true,
   'multi_user', true,
   'bank_import', true,
   'api_access', true,
   'custom_reports', true
 ),
 3),

-- Plan Enterprise
('enterprise', 'Enterprise',
 'Solution complète pour grandes entreprises',
 199.00, 1990.00,
 999, 999, 999999, 10000,
 JSON_OBJECT(
   'support', 'phone_and_email',
   'accounting_plan', true,
   'transactions', true,
   'reports', true,
   'invoicing', true,
   'multi_user', true,
   'bank_import', true,
   'api_access', true,
   'custom_reports', true,
   'dedicated_support', true,
   'custom_features', true
 ),
 4);

-- ============================================================================
-- CRÉATION D'UN SUPER ADMIN PAR DÉFAUT
-- ============================================================================
-- Mot de passe: Admin@123 (à changer immédiatement!)
INSERT INTO `admin_users`
(`username`, `email`, `password_hash`, `first_name`, `last_name`, `role`)
VALUES
('superadmin', 'admin@gestioncomptable.local',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
 'Super', 'Admin', 'super_admin');

-- ============================================================================
-- PARAMÈTRES SYSTÈME PAR DÉFAUT
-- ============================================================================
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`) VALUES
('app_name', 'Gestion Comptable', 'string', 'Nom de l\'application'),
('trial_days', '30', 'integer', 'Nombre de jours d\'essai gratuit'),
('maintenance_mode', 'false', 'boolean', 'Mode maintenance activé'),
('auto_create_db', 'true', 'boolean', 'Créer automatiquement les bases de données clients'),
('db_prefix', 'gestion_comptable_client_', 'string', 'Préfixe des bases de données clients'),
('max_tenants', '1000', 'integer', 'Nombre maximum de clients'),
('require_email_verification', 'false', 'boolean', 'Vérification email obligatoire');

-- ============================================================================
-- FIN DU SCRIPT
-- ============================================================================

SELECT '✅ Base de données master créée avec succès!' as 'Status';
SELECT COUNT(*) as 'Plans disponibles' FROM subscription_plans;
SELECT COUNT(*) as 'Administrateurs créés' FROM admin_users;
