-- =====================================================
-- Migration: Ajout des fonctionnalités QR-Factures
-- Date: 2024
-- Description: Ajout des champs nécessaires pour les QR-factures suisses
-- =====================================================

-- 1. Ajouter les colonnes QR à la table companies
ALTER TABLE companies
ADD COLUMN IF NOT EXISTS qr_iban VARCHAR(34) AFTER name,
ADD COLUMN IF NOT EXISTS bank_iban VARCHAR(34) AFTER qr_iban,
ADD COLUMN IF NOT EXISTS qr_reference_prefix VARCHAR(10) AFTER bank_iban,
ADD COLUMN IF NOT EXISTS address VARCHAR(255) AFTER owner_surname,
ADD COLUMN IF NOT EXISTS postal_code VARCHAR(10) AFTER address,
ADD COLUMN IF NOT EXISTS city VARCHAR(100) AFTER postal_code,
ADD COLUMN IF NOT EXISTS country VARCHAR(2) DEFAULT 'CH' AFTER city;

-- 2. Ajouter les colonnes QR à la table invoices
ALTER TABLE invoices
ADD COLUMN IF NOT EXISTS qr_reference VARCHAR(27) AFTER number,
ADD COLUMN IF NOT EXISTS payment_method ENUM('qr', 'bank_transfer', 'cash', 'card') DEFAULT 'qr' AFTER status,
ADD COLUMN IF NOT EXISTS qr_code_path VARCHAR(255) AFTER payment_method,
ADD COLUMN IF NOT EXISTS payment_due_date DATE AFTER total,
ADD COLUMN IF NOT EXISTS payment_terms VARCHAR(255) DEFAULT 'Payable dans les 30 jours' AFTER payment_due_date;

-- 3. Créer la table pour les paramètres de paiement QR
CREATE TABLE IF NOT EXISTS qr_payment_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    enable_qr_invoice BOOLEAN DEFAULT TRUE,
    qr_iban VARCHAR(34) NOT NULL,
    creditor_name VARCHAR(100) NOT NULL,
    creditor_address VARCHAR(255) NOT NULL,
    creditor_postal_code VARCHAR(10) NOT NULL,
    creditor_city VARCHAR(100) NOT NULL,
    creditor_country VARCHAR(2) DEFAULT 'CH',
    additional_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_company_qr (company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Table pour historiser les QR-factures générées
CREATE TABLE IF NOT EXISTS qr_invoice_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    company_id INT NOT NULL,
    qr_reference VARCHAR(27) NOT NULL,
    qr_iban VARCHAR(34) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'CHF',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    pdf_path VARCHAR(255),
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_qr_reference (qr_reference),
    INDEX idx_invoice_id (invoice_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Mise à jour des données existantes
-- Ajouter le pays par défaut pour les sociétés existantes
UPDATE companies SET country = 'CH' WHERE country IS NULL OR country = '';

-- Ajouter la méthode de paiement par défaut pour les factures existantes
UPDATE invoices SET payment_method = 'qr' WHERE payment_method IS NULL;

-- Ajouter la date d'échéance par défaut (30 jours après la date de facture)
UPDATE invoices
SET payment_due_date = DATE_ADD(date, INTERVAL 30 DAY)
WHERE payment_due_date IS NULL;

-- Afficher le résultat
SELECT 'Migration QR-Invoice terminée avec succès!' AS message;
