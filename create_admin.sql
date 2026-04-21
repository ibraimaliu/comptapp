-- ============================================
-- Script de Création d'un Compte Administrateur
-- Base de données: gestion_comptable
-- Date: 2025-10-19
-- ============================================

USE gestion_comptable;

-- ============================================
-- OPTION 1: Créer un utilisateur administrateur simple
-- ============================================
-- Username: admin
-- Email: admin@gestion-comptable.com
-- Password: Admin@2025
-- Password hash généré avec: password_hash('Admin@2025', PASSWORD_BCRYPT)

INSERT INTO users (username, email, password, created_at)
VALUES (
    'admin',
    'admin@gestion-comptable.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Password: Admin@2025
    NOW()
);

-- Récupérer l'ID de l'utilisateur créé
SET @admin_user_id = LAST_INSERT_ID();

SELECT CONCAT('✅ Compte administrateur créé avec succès!') as Resultat;
SELECT CONCAT('   Username: admin') as Info;
SELECT CONCAT('   Email: admin@gestion-comptable.com') as Info;
SELECT CONCAT('   Password: Admin@2025') as Info;
SELECT CONCAT('   User ID: ', @admin_user_id) as Info;

-- ============================================
-- OPTION 2: Créer également une entreprise de test pour l'admin
-- ============================================

INSERT INTO companies (
    user_id,
    name,
    owner_name,
    owner_surname,
    fiscal_year_start,
    fiscal_year_end,
    tva_status,
    created_at
)
VALUES (
    @admin_user_id,
    'Entreprise de Test',
    'Administrateur',
    'Système',
    '2025-01-01',
    '2025-12-31',
    'assujetti',
    NOW()
);

SET @admin_company_id = LAST_INSERT_ID();

SELECT CONCAT('✅ Entreprise de test créée!') as Resultat;
SELECT CONCAT('   Nom: Entreprise de Test') as Info;
SELECT CONCAT('   Company ID: ', @admin_company_id) as Info;

-- ============================================
-- OPTION 3: Ajouter un plan comptable par défaut (optionnel)
-- ============================================

-- Comptes de classe 1 - Comptes de capitaux
INSERT INTO accounting_plan (company_id, number, name, category, type, is_used) VALUES
(@admin_company_id, '101', 'Capital', 'Capitaux', 'passif', 0),
(@admin_company_id, '106', 'Réserves', 'Capitaux', 'passif', 0),
(@admin_company_id, '120', 'Résultat de l''exercice', 'Capitaux', 'passif', 0);

-- Comptes de classe 2 - Comptes d'immobilisations
INSERT INTO accounting_plan (company_id, number, name, category, type, is_used) VALUES
(@admin_company_id, '211', 'Terrains', 'Immobilisations', 'actif', 0),
(@admin_company_id, '213', 'Constructions', 'Immobilisations', 'actif', 0),
(@admin_company_id, '218', 'Matériel de bureau', 'Immobilisations', 'actif', 0);

-- Comptes de classe 4 - Comptes de tiers
INSERT INTO accounting_plan (company_id, number, name, category, type, is_used) VALUES
(@admin_company_id, '401', 'Fournisseurs', 'Tiers', 'passif', 0),
(@admin_company_id, '411', 'Clients', 'Tiers', 'actif', 0),
(@admin_company_id, '421', 'Personnel - Rémunérations dues', 'Tiers', 'passif', 0),
(@admin_company_id, '437', 'Autres organismes sociaux', 'Tiers', 'passif', 0),
(@admin_company_id, '445', 'État - Taxes sur le chiffre d''affaires', 'Tiers', 'passif', 0);

-- Comptes de classe 5 - Comptes financiers
INSERT INTO accounting_plan (company_id, number, name, category, type, is_used) VALUES
(@admin_company_id, '512', 'Banque', 'Financiers', 'actif', 0),
(@admin_company_id, '530', 'Caisse', 'Financiers', 'actif', 0);

-- Comptes de classe 6 - Comptes de charges
INSERT INTO accounting_plan (company_id, number, name, category, type, is_used) VALUES
(@admin_company_id, '601', 'Achats de matières premières', 'Charges', 'charge', 0),
(@admin_company_id, '606', 'Achats non stockés de matières et fournitures', 'Charges', 'charge', 0),
(@admin_company_id, '613', 'Locations', 'Charges', 'charge', 0),
(@admin_company_id, '615', 'Entretien et réparations', 'Charges', 'charge', 0),
(@admin_company_id, '621', 'Personnel - Rémunérations', 'Charges', 'charge', 0),
(@admin_company_id, '625', 'Déplacements, missions', 'Charges', 'charge', 0),
(@admin_company_id, '626', 'Frais postaux et télécommunications', 'Charges', 'charge', 0),
(@admin_company_id, '627', 'Services bancaires', 'Charges', 'charge', 0);

-- Comptes de classe 7 - Comptes de produits
INSERT INTO accounting_plan (company_id, number, name, category, type, is_used) VALUES
(@admin_company_id, '701', 'Ventes de produits finis', 'Produits', 'produit', 0),
(@admin_company_id, '706', 'Prestations de services', 'Produits', 'produit', 0),
(@admin_company_id, '708', 'Produits des activités annexes', 'Produits', 'produit', 0);

SELECT CONCAT('✅ Plan comptable par défaut créé!') as Resultat;
SELECT CONCAT('   Nombre de comptes: ', COUNT(*)) as Info FROM accounting_plan WHERE company_id = @admin_company_id;

-- ============================================
-- VÉRIFICATION FINALE
-- ============================================

SELECT '===========================================' as Separator;
SELECT '✅ CRÉATION TERMINÉE AVEC SUCCÈS!' as Resultat;
SELECT '===========================================' as Separator;
SELECT '' as Separator;

SELECT 'INFORMATIONS DE CONNEXION:' as Info;
SELECT '   URL: http://localhost/gestion_comptable' as Info;
SELECT '   Username: admin' as Info;
SELECT '   Email: admin@gestion-comptable.com' as Info;
SELECT '   Password: Admin@2025' as Info;
SELECT '' as Separator;

SELECT 'DONNÉES CRÉÉES:' as Info;
SELECT CONCAT('   User ID: ', @admin_user_id) as Info;
SELECT CONCAT('   Company ID: ', @admin_company_id) as Info;
SELECT CONCAT('   Nombre de comptes: ', COUNT(*)) as Info FROM accounting_plan WHERE company_id = @admin_company_id;

-- ============================================
-- NOTES IMPORTANTES
-- ============================================
-- 1. Changez le mot de passe après la première connexion
-- 2. Le hash du mot de passe a été généré avec PASSWORD_BCRYPT
-- 3. L'entreprise "Entreprise de Test" est créée automatiquement
-- 4. Un plan comptable de base est créé pour l'entreprise
-- 5. Pour supprimer ce compte: DELETE FROM users WHERE username = 'admin';
