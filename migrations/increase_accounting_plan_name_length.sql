-- Migration: Augmentation de la taille de la colonne 'name' dans accounting_plan
-- Date: 2025-01-13
-- Raison: Permettre l'import de noms de comptes plus longs (jusqu'à 255 caractères)
-- Ancienne valeur: VARCHAR(100)
-- Nouvelle valeur: VARCHAR(255)

USE gestion_comptable;

-- Vérifier la structure actuelle
-- DESCRIBE accounting_plan;

-- Modifier la colonne
ALTER TABLE accounting_plan
MODIFY COLUMN name VARCHAR(255) NOT NULL;

-- Vérifier le résultat
DESCRIBE accounting_plan;

-- Note: Cette modification est rétrocompatible (augmentation de taille uniquement)
-- Aucune perte de données
