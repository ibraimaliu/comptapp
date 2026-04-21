-- Données initiales pour le système de rôles et permissions
-- À exécuter après CREATE_TENANT_TABLES.sql

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
