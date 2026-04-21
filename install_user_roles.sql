-- =====================================================
-- SYSTÈME DE GESTION DES UTILISATEURS ET PERMISSIONS
-- =====================================================

-- Table des rôles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table de liaison rôles-permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modifier la table users pour ajouter company_id et role_id
ALTER TABLE `users`
ADD COLUMN `company_id` int(11) DEFAULT NULL AFTER `id`,
ADD COLUMN `role_id` int(11) DEFAULT NULL AFTER `password`,
ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `role_id`,
ADD COLUMN `last_login_at` timestamp NULL DEFAULT NULL AFTER `is_active`,
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;

-- Ajouter les clés étrangères
ALTER TABLE `users`
ADD INDEX `idx_company_id` (`company_id`),
ADD INDEX `idx_role_id` (`role_id`),
ADD CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
ADD CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL;

-- Table des invitations d'utilisateurs
CREATE TABLE IF NOT EXISTS `user_invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL UNIQUE,
  `invited_by` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,
  `accepted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_token` (`token`),
  INDEX `idx_email` (`email`),
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`invited_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table des logs d'activité utilisateur
CREATE TABLE IF NOT EXISTS `user_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_user_id` (`user_id`),
  INDEX `idx_company_id` (`company_id`),
  INDEX `idx_action` (`action`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DONNÉES INITIALES
-- =====================================================

-- Insérer les rôles par défaut
INSERT INTO `roles` (`name`, `display_name`, `description`) VALUES
('admin', 'Administrateur', 'Accès complet à toutes les fonctionnalités'),
('accountant', 'Comptable', 'Accès aux fonctionnalités comptables et financières'),
('reader', 'Lecteur', 'Accès en lecture seule aux données');

-- Insérer les permissions
INSERT INTO `permissions` (`name`, `display_name`, `module`, `description`) VALUES
-- Utilisateurs
('users.view', 'Voir les utilisateurs', 'users', 'Peut voir la liste des utilisateurs'),
('users.create', 'Créer des utilisateurs', 'users', 'Peut inviter de nouveaux utilisateurs'),
('users.edit', 'Modifier les utilisateurs', 'users', 'Peut modifier les informations des utilisateurs'),
('users.delete', 'Supprimer des utilisateurs', 'users', 'Peut supprimer des utilisateurs'),

-- Entreprises
('companies.view', 'Voir les entreprises', 'companies', 'Peut voir les informations de l\'entreprise'),
('companies.edit', 'Modifier l\'entreprise', 'companies', 'Peut modifier les informations de l\'entreprise'),

-- Comptabilité
('accounting.view', 'Voir la comptabilité', 'accounting', 'Peut consulter les écritures comptables'),
('accounting.create', 'Créer des écritures', 'accounting', 'Peut créer des écritures comptables'),
('accounting.edit', 'Modifier des écritures', 'accounting', 'Peut modifier des écritures comptables'),
('accounting.delete', 'Supprimer des écritures', 'accounting', 'Peut supprimer des écritures comptables'),

-- Plan comptable
('chart_of_accounts.view', 'Voir le plan comptable', 'accounting', 'Peut consulter le plan comptable'),
('chart_of_accounts.edit', 'Modifier le plan comptable', 'accounting', 'Peut modifier le plan comptable'),

-- Contacts
('contacts.view', 'Voir les contacts', 'contacts', 'Peut voir la liste des contacts'),
('contacts.create', 'Créer des contacts', 'contacts', 'Peut créer de nouveaux contacts'),
('contacts.edit', 'Modifier des contacts', 'contacts', 'Peut modifier des contacts'),
('contacts.delete', 'Supprimer des contacts', 'contacts', 'Peut supprimer des contacts'),

-- Factures
('invoices.view', 'Voir les factures', 'invoices', 'Peut voir la liste des factures'),
('invoices.create', 'Créer des factures', 'invoices', 'Peut créer de nouvelles factures'),
('invoices.edit', 'Modifier des factures', 'invoices', 'Peut modifier des factures'),
('invoices.delete', 'Supprimer des factures', 'invoices', 'Peut supprimer des factures'),
('invoices.send', 'Envoyer des factures', 'invoices', 'Peut envoyer des factures par email'),

-- Devis
('quotes.view', 'Voir les devis', 'quotes', 'Peut voir la liste des devis'),
('quotes.create', 'Créer des devis', 'quotes', 'Peut créer de nouveaux devis'),
('quotes.edit', 'Modifier des devis', 'quotes', 'Peut modifier des devis'),
('quotes.delete', 'Supprimer des devis', 'quotes', 'Peut supprimer des devis'),

-- Rapports
('reports.view', 'Voir les rapports', 'reports', 'Peut consulter tous les rapports'),
('reports.export', 'Exporter les rapports', 'reports', 'Peut exporter les rapports en PDF/Excel'),

-- Paramètres
('settings.view', 'Voir les paramètres', 'settings', 'Peut voir les paramètres'),
('settings.edit', 'Modifier les paramètres', 'settings', 'Peut modifier les paramètres système');

-- Assigner les permissions aux rôles

-- ADMIN : Toutes les permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r, `permissions` p
WHERE r.name = 'admin';

-- COMPTABLE : Permissions de gestion comptable
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r, `permissions` p
WHERE r.name = 'accountant'
AND p.name IN (
    'companies.view',
    'accounting.view', 'accounting.create', 'accounting.edit',
    'chart_of_accounts.view',
    'contacts.view', 'contacts.create', 'contacts.edit',
    'invoices.view', 'invoices.create', 'invoices.edit', 'invoices.send',
    'quotes.view', 'quotes.create', 'quotes.edit',
    'reports.view', 'reports.export',
    'settings.view'
);

-- LECTEUR : Permissions de lecture seule
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r, `permissions` p
WHERE r.name = 'reader'
AND p.name IN (
    'companies.view',
    'accounting.view',
    'chart_of_accounts.view',
    'contacts.view',
    'invoices.view',
    'quotes.view',
    'reports.view'
);
