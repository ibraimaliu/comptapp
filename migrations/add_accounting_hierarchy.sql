-- Migration: Ajouter la structure hiérarchique au plan comptable
-- Date: 2025-11-15
-- Description: Ajout des champs pour gérer la hiérarchie Section > Groupe > Sous-groupe > Compte

-- Ajouter les nouveaux champs
ALTER TABLE `accounting_plan`
ADD COLUMN `level` ENUM('section', 'groupe', 'sous-groupe', 'compte') NOT NULL DEFAULT 'compte' AFTER `type`,
ADD COLUMN `parent_id` INT(11) NULL DEFAULT NULL AFTER `level`,
ADD COLUMN `is_selectable` TINYINT(1) NOT NULL DEFAULT 1 AFTER `parent_id`,
ADD COLUMN `sort_order` INT(11) NOT NULL DEFAULT 0 AFTER `is_selectable`,
ADD COLUMN `section` ENUM('actif', 'passif', 'produits', 'charges', 'salaires', 'charges_hors_exploitation', 'cloture') NULL DEFAULT NULL AFTER `sort_order`;

-- Ajouter un index sur parent_id pour améliorer les performances
ALTER TABLE `accounting_plan`
ADD INDEX `idx_parent_id` (`parent_id`),
ADD INDEX `idx_level` (`level`),
ADD INDEX `idx_section` (`section`),
ADD INDEX `idx_sort_order` (`sort_order`);

-- Ajouter une contrainte de clé étrangère pour parent_id
ALTER TABLE `accounting_plan`
ADD CONSTRAINT `fk_accounting_plan_parent`
FOREIGN KEY (`parent_id`) REFERENCES `accounting_plan` (`id`)
ON DELETE SET NULL
ON UPDATE CASCADE;

-- Mettre à jour les comptes existants pour être sélectionnables
UPDATE `accounting_plan`
SET `is_selectable` = 1,
    `level` = 'compte'
WHERE `level` = 'compte';

-- Ajouter des sections basées sur les catégories existantes
UPDATE `accounting_plan`
SET `section` = CASE
    WHEN `category` = 'actif' THEN 'actif'
    WHEN `category` = 'passif' THEN 'passif'
    WHEN `category` = 'produit' THEN 'produits'
    WHEN `category` = 'charge' THEN 'charges'
    ELSE NULL
END
WHERE `section` IS NULL;
