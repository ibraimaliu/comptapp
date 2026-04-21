# Plan de Développement: Fonctionnalités Winbiz
## Pour devenir concurrentiel à Winbiz

---

## 📊 Analyse Comparative: Fonctionnalités Existantes vs Winbiz

### ✅ Fonctionnalités DÉJÀ Implémentées (90%)

Votre application **Gestion Comptable** possède déjà une base solide:

| Fonctionnalité | Winbiz | Votre App | Qualité |
|----------------|--------|-----------|---------|
| Authentification utilisateurs | ✅ | ✅ | 100% |
| Multi-sociétés (tenancy) | ✅ | ✅ | 100% |
| Gestion contacts/clients | ✅ | ✅ | 95% |
| Transactions financières | ✅ | ✅ | 90% |
| Facturation | ✅ | ✅ | 85% |
| Plan comptable | ✅ | ✅ | 100% |
| Gestion TVA | ✅ | ✅ | 90% |
| Catégories transactions | ✅ | ✅ | 100% |
| Tableau de bord | ✅ | ✅ | 85% |
| API REST | ✅ | ✅ | 90% |

---

## 🚀 Fonctionnalités MANQUANTES pour être concurrentiel à Winbiz

### PHASE 1 - Fonctionnalités Critiques (3-4 semaines)
**Priorité HAUTE - Sans ces fonctionnalités, l'application n'est pas utilisable en Suisse**

#### 1.1 QR-Factures Suisses ⭐⭐⭐⭐⭐
**Complexité:** Haute | **Temps estimé:** 10-12 jours

**Contexte:**
Depuis le 1er octobre 2022, les QR-factures sont **obligatoires** en Suisse. C'est la fonctionnalité #1 la plus importante.

**Spécifications techniques:**
- **Génération QR-Code:**
  - Format Swiss QR Code selon norme SIX
  - Type de QR: QRType (SPC - Swiss Payment Code)
  - Version: 2.0
  - Coding: UTF-8
  - Dimensions: 46mm × 46mm (à 300 DPI)

- **Données obligatoires dans le QR:**
  ```
  QRType / Version / Coding
  IBAN (ou QR-IBAN pour ESR)
  Creditor (Nom, Adresse, CP/Ville, Pays)
  Ultimate Creditor (optionnel)
  Amount / Currency (CHF ou EUR uniquement)
  Ultimate Debtor / Reference
  Unstructured Message / Bill Information
  Alternative Procedures (max 2)
  ```

- **Formats de référence supportés:**
  - QRR (QR Reference) - 27 caractères avec checksum
  - SCOR (Creditor Reference) - ISO 11649
  - NON (sans référence structurée)

**Implémentation:**

**Base de données (migrations):**
```sql
-- Ajouter colonnes à la table companies
ALTER TABLE companies ADD COLUMN qr_iban VARCHAR(34) AFTER name;
ALTER TABLE companies ADD COLUMN qr_reference_prefix VARCHAR(10);
ALTER TABLE companies ADD COLUMN address VARCHAR(255);
ALTER TABLE companies ADD COLUMN postal_code VARCHAR(10);
ALTER TABLE companies ADD COLUMN city VARCHAR(100);
ALTER TABLE companies ADD COLUMN country VARCHAR(2) DEFAULT 'CH';

-- Ajouter colonnes à la table invoices
ALTER TABLE invoices ADD COLUMN qr_reference VARCHAR(27);
ALTER TABLE invoices ADD COLUMN payment_method ENUM('qr', 'bank_transfer', 'cash') DEFAULT 'qr';
ALTER TABLE invoices ADD COLUMN qr_code_path VARCHAR(255);
```

**Nouvelle classe PHP:**
```php
// models/QRInvoice.php
class QRInvoice {
    // Génération du QR-Code selon norme Swiss QR
    public function generateQRCode($invoice_id, $company_id);

    // Calcul checksum pour QRR
    public function calculateQRRChecksum($reference);

    // Validation IBAN/QR-IBAN
    public function validateIBAN($iban);

    // Génération référence structurée
    public function generateQRReference($invoice_number, $company_id);

    // Création PDF avec QR-Code intégré
    public function generatePDFWithQR($invoice_id);
}
```

**Bibliothèque requise:**
```bash
composer require endroid/qr-code
composer require fpdf/fpdf  # ou mpdf/mpdf pour meilleur contrôle
```

**Template facture avec QR:**
- Section paiement séparée (perforée)
- QR-Code en bas à gauche
- Montant et informations à droite
- Format A4 avec zone de découpe

**Vue:**
```
views/invoices/
  ├── create.php         # Formulaire création facture
  ├── edit.php           # Modification facture
  ├── view.php           # Visualisation facture
  ├── pdf_template.php   # Template PDF avec QR
  └── qr_section.php     # Section QR détachable
```

---

#### 1.2 Devis (Offres/Quotes) ⭐⭐⭐⭐⭐
**Complexité:** Moyenne | **Temps estimé:** 5-6 jours

**Description:**
Système de création de devis qui peuvent être convertis en factures.

**Base de données:**
```sql
CREATE TABLE quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    number VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    valid_until DATE NOT NULL,
    client_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tva_amount DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('draft', 'sent', 'accepted', 'rejected', 'expired', 'converted') DEFAULT 'draft',
    notes TEXT,
    converted_to_invoice_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (client_id) REFERENCES contacts(id) ON DELETE RESTRICT,
    FOREIGN KEY (converted_to_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    UNIQUE KEY unique_quote_number (company_id, number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE quote_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quote_id INT NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    tva_rate DECIMAL(5,2) NOT NULL,
    tva_amount DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Modèle:**
```php
// models/Quote.php
class Quote {
    public function create();
    public function read();
    public function update();
    public function delete();
    public function readByCompany($company_id);
    public function generateNumber($company_id); // Format: DEV-YYYY-###
    public function convertToInvoice($quote_id); // Conversion devis → facture
    public function calculateTotals(); // Calcul automatique
    public function sendByEmail($quote_id, $email); // Envoi email
}
```

**Fonctionnalités:**
- Numérotation automatique (DEV-2024-001)
- Date de validité configurable
- Conversion en facture en 1 clic
- Statuts: brouillon, envoyé, accepté, refusé, expiré, converti
- Export PDF professionnel
- Suivi des devis (acceptés vs refusés)

---

#### 1.3 Export PDF Professionnel ⭐⭐⭐⭐
**Complexité:** Moyenne-Haute | **Temps estimé:** 6-7 jours

**Description:**
Génération de PDF professionnels avec mise en page Suisse standard.

**Bibliothèques:**
```bash
composer require mpdf/mpdf
# ou alternative:
composer require dompdf/dompdf
```

**Implémentation:**
```php
// utils/PDFGenerator.php
class PDFGenerator {
    private $mpdf;

    public function __construct() {
        $this->mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20
        ]);
    }

    // Génération facture PDF avec QR
    public function generateInvoicePDF($invoice_id, $with_qr = true);

    // Génération devis PDF
    public function generateQuotePDF($quote_id);

    // Rapport financier PDF
    public function generateFinancialReportPDF($company_id, $period);

    // Liste transactions PDF
    public function generateTransactionListPDF($filters);

    // Bilan comptable PDF
    public function generateBalanceSheetPDF($company_id, $date);
}
```

**Templates PDF:**
```
utils/pdf_templates/
  ├── invoice_swiss.html      # Facture format Suisse
  ├── quote_swiss.html        # Devis format Suisse
  ├── financial_report.html   # Rapport financier
  ├── balance_sheet.html      # Bilan
  └── transaction_list.html   # Liste transactions
```

**Caractéristiques:**
- Logo entreprise
- En-tête avec coordonnées
- Mise en page professionnelle
- Footer avec conditions de paiement
- Numérotation pages
- Tableaux stylisés
- Couleurs personnalisables
- Watermark pour brouillons

---

#### 1.4 Réconciliation Bancaire ⭐⭐⭐⭐
**Complexité:** Haute | **Temps estimé:** 8-10 jours

**Description:**
Import et rapprochement automatique des relevés bancaires.

**Base de données:**
```sql
CREATE TABLE bank_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    iban VARCHAR(34) NOT NULL,
    qr_iban VARCHAR(34),
    bank_name VARCHAR(100),
    swift VARCHAR(11),
    currency VARCHAR(3) DEFAULT 'CHF',
    opening_balance DECIMAL(12,2) DEFAULT 0.00,
    current_balance DECIMAL(12,2) DEFAULT 0.00,
    last_reconciliation_date DATE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bank_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bank_account_id INT NOT NULL,
    company_id INT NOT NULL,
    transaction_date DATE NOT NULL,
    value_date DATE,
    reference VARCHAR(100),
    description TEXT,
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'CHF',
    balance_after DECIMAL(12,2),
    status ENUM('pending', 'matched', 'reconciled', 'ignored') DEFAULT 'pending',
    matched_transaction_id INT,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (matched_transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Modèle:**
```php
// models/BankReconciliation.php
class BankReconciliation {
    // Import relevé bancaire (CSV, MT940, Camt.053)
    public function importBankStatement($file, $format);

    // Rapprochement automatique
    public function autoMatch($bank_account_id);

    // Rapprochement manuel
    public function manualMatch($bank_transaction_id, $transaction_id);

    // Création transaction depuis relevé
    public function createTransactionFromBank($bank_transaction_id);

    // Lettrage
    public function markAsReconciled($bank_transaction_id);
}
```

**Formats supportés:**
- **ISO 20022 Camt.053** (XML) - Standard bancaire Suisse
- **MT940** (SWIFT) - Format ancien mais encore utilisé
- **CSV** - Format simple avec mapping colonnes

**Interface:**
```
views/banking/
  ├── accounts.php           # Liste comptes bancaires
  ├── import.php             # Import relevés
  ├── reconciliation.php     # Interface rapprochement
  ├── transactions.php       # Transactions bancaires
  └── matching.php           # Lettrage manuel
```

**Fonctionnalités:**
- Upload fichier relevé bancaire
- Parsing automatique
- Suggestions de rapprochement (par montant, date, référence)
- Rapprochement automatique (70-80% des cas)
- Rapprochement manuel pour transactions ambiguës
- Marquage comme réconcilié
- Solde bancaire vs comptable
- Écarts de rapprochement

---

### PHASE 2 - Fonctionnalités Importantes (3-4 semaines)
**Priorité MOYENNE-HAUTE - Améliore significativement l'application**

#### 2.1 Rappels de Paiement ⭐⭐⭐⭐
**Complexité:** Moyenne | **Temps estimé:** 5-6 jours

**Description:**
Système automatique de relance clients pour factures impayées.

**Base de données:**
```sql
CREATE TABLE payment_reminders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    invoice_id INT NOT NULL,
    reminder_level INT NOT NULL, -- 1=première relance, 2=deuxième, 3=mise en demeure
    sent_date DATE NOT NULL,
    due_date DATE NOT NULL,
    amount_due DECIMAL(10,2) NOT NULL,
    interest_amount DECIMAL(10,2) DEFAULT 0.00,
    fees DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('sent', 'paid', 'cancelled') DEFAULT 'sent',
    email_sent BOOLEAN DEFAULT FALSE,
    pdf_path VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Configuration rappels par société
CREATE TABLE reminder_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL UNIQUE,
    level1_days INT DEFAULT 10,      -- Jours après échéance pour 1ère relance
    level2_days INT DEFAULT 20,      -- Jours pour 2ème relance
    level3_days INT DEFAULT 30,      -- Jours pour mise en demeure
    level1_fee DECIMAL(10,2) DEFAULT 0.00,
    level2_fee DECIMAL(10,2) DEFAULT 0.00,
    level3_fee DECIMAL(10,2) DEFAULT 0.00,
    interest_rate DECIMAL(5,2) DEFAULT 5.00,  -- Taux d'intérêt annuel
    auto_send BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Modèle:**
```php
// models/PaymentReminder.php
class PaymentReminder {
    public function checkOverdueInvoices($company_id);
    public function generateReminder($invoice_id, $level);
    public function sendReminder($reminder_id);
    public function calculateInterest($invoice_id, $days_overdue);
    public function scheduleAutoReminders(); // Cron job
}
```

**Fonctionnalités:**
- Détection automatique des factures en retard
- 3 niveaux de relance configurables
- Calcul intérêts de retard (légal Suisse: 5%)
- Frais de rappel par niveau
- Envoi email automatique
- Génération PDF rappel
- Planning des rappels
- Tableau de bord créances

---

#### 2.2 Gestion des Fournisseurs & Achats ⭐⭐⭐⭐
**Complexité:** Moyenne | **Temps estimé:** 6-7 jours

**Description:**
Module complet pour gérer factures fournisseurs et paiements.

**Base de données:**
```sql
CREATE TABLE supplier_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    supplier_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tva_amount DECIMAL(10,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('received', 'approved', 'paid', 'cancelled') DEFAULT 'received',
    payment_date DATE,
    qr_reference VARCHAR(27),
    scanned_pdf_path VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES contacts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE supplier_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_invoice_id INT NOT NULL,
    description TEXT NOT NULL,
    quantity DECIMAL(10,2) NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    tva_rate DECIMAL(5,2) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    account_id INT, -- Lien vers plan comptable
    FOREIGN KEY (supplier_invoice_id) REFERENCES supplier_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounting_plan(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    payment_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('bank_transfer', 'cash', 'card', 'other') NOT NULL,
    reference VARCHAR(100),
    supplier_invoice_id INT,
    invoice_id INT, -- Si paiement client
    bank_account_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_invoice_id) REFERENCES supplier_invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
    FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Modèle:**
```php
// models/SupplierInvoice.php
class SupplierInvoice {
    public function create();
    public function read();
    public function update();
    public function delete();
    public function readByCompany($company_id);
    public function scanQRInvoice($image_path); // OCR pour QR-facture
    public function markAsPaid($invoice_id, $payment_date);
    public function getOverdueInvoices($company_id);
}
```

**Fonctionnalités:**
- Saisie manuelle factures fournisseurs
- Scan QR-facture avec OCR (extraction données)
- Import PDF factures
- Workflow d'approbation
- Échéancier paiements
- Alertes factures à payer
- Historique paiements
- Export fichier pain.001 (ordre bancaire ISO 20022)

---

#### 2.3 Tableaux de Bord Avancés ⭐⭐⭐⭐
**Complexité:** Moyenne | **Temps estimé:** 5-6 jours

**Description:**
Widgets et graphiques interactifs pour analyse financière.

**Bibliothèque:**
```bash
# Utiliser Chart.js pour graphiques
# Via CDN dans les vues
```

**Nouveaux widgets:**

1. **Évolution Trésorerie**
   - Graphique linéaire 12 mois
   - Encaissements vs Décaissements
   - Prévisionnel trésorerie

2. **Répartition Revenus**
   - Diagramme circulaire par catégorie
   - Top 10 clients
   - Évolution mensuelle CA

3. **Dépenses**
   - Graphique barres par catégorie
   - Tendances mensuelles
   - Budget vs Réel

4. **Créances Clients**
   - Factures en attente
   - Factures en retard
   - Délai moyen paiement
   - DSO (Days Sales Outstanding)

5. **Dettes Fournisseurs**
   - À payer ce mois
   - En retard
   - Échéancier 3 mois

6. **TVA**
   - TVA collectée vs déductible
   - Solde TVA à payer/récupérer
   - Graphique évolution trimestrielle

**Implémentation:**
```php
// models/Dashboard.php
class Dashboard {
    public function getCashflowData($company_id, $months = 12);
    public function getRevenueBreakdown($company_id, $period);
    public function getExpenseBreakdown($company_id, $period);
    public function getReceivablesAging($company_id);
    public function getPayablesAging($company_id);
    public function getTVABalance($company_id, $period);
    public function getKPIs($company_id); // Key Performance Indicators
}
```

**KPIs calculés:**
- Chiffre d'affaires (CA)
- Marge brute
- Taux de marge
- EBITDA
- Délai moyen paiement clients
- Ratio liquidité
- Fonds de roulement

---

#### 2.4 Rapports Comptables Complets ⭐⭐⭐⭐
**Complexité:** Haute | **Temps estimé:** 8-9 jours

**Description:**
Génération des rapports comptables légaux suisses.

**Rapports obligatoires:**

1. **Bilan (Balance Sheet)**
   - Actifs (Actif circulant + Actif immobilisé)
   - Passifs (Capitaux propres + Dettes)
   - Format selon CO (Code des Obligations)

2. **Compte de Résultat (P&L)**
   - Produits d'exploitation
   - Charges d'exploitation
   - Résultat d'exploitation
   - Résultat financier
   - Résultat extraordinaire
   - Résultat de l'exercice

3. **Grand Livre (General Ledger)**
   - Toutes écritures par compte
   - Soldes intermédiaires
   - Période personnalisable

4. **Balance Générale**
   - Liste tous comptes avec soldes
   - Colonnes: Compte, Libellé, Débit, Crédit, Solde
   - Vérification: Total Débit = Total Crédit

5. **Journal (Daybook)**
   - Écritures chronologiques
   - Numérotation séquentielle
   - Pas de modification possible après validation

6. **Annexes**
   - Notes explicatives
   - Principes comptables
   - Engagements hors bilan

**Base de données:**
```sql
-- Système écritures comptables double
CREATE TABLE journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    entry_number INT NOT NULL,
    entry_date DATE NOT NULL,
    description TEXT NOT NULL,
    transaction_id INT, -- Lien optionnel vers transaction
    invoice_id INT,
    is_validated BOOLEAN DEFAULT FALSE,
    validated_by INT,
    validated_at TIMESTAMP,
    fiscal_year INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    UNIQUE KEY unique_entry_number (company_id, entry_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE journal_entry_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(12,2) DEFAULT 0.00,
    credit DECIMAL(12,2) DEFAULT 0.00,
    description TEXT,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounting_plan(id) ON DELETE RESTRICT,
    CHECK (debit >= 0 AND credit >= 0),
    CHECK (NOT (debit > 0 AND credit > 0)) -- Soit débit, soit crédit, pas les deux
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Modèle:**
```php
// models/AccountingReport.php
class AccountingReport {
    // Bilan
    public function generateBalanceSheet($company_id, $date);

    // Compte de résultat
    public function generateProfitLoss($company_id, $start_date, $end_date);

    // Grand livre
    public function generateGeneralLedger($company_id, $account_id, $period);

    // Balance
    public function generateTrialBalance($company_id, $date);

    // Journal
    public function generateJournal($company_id, $period);

    // Export comptable pour fiduciaire
    public function exportToFiduciary($company_id, $format); // Format: CSV, XML
}
```

---

### PHASE 3 - Fonctionnalités Avancées (3-4 semaines)
**Priorité MOYENNE - Pour se différencier de Winbiz**

#### 3.1 Module Email & Notifications ⭐⭐⭐
**Complexité:** Moyenne | **Temps estimé:** 5-6 jours

**Description:**
Système d'envoi email automatique et notifications.

**Bibliothèque:**
```bash
composer require phpmailer/phpmailer
```

**Base de données:**
```sql
CREATE TABLE email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('invoice', 'quote', 'reminder', 'payment_confirmation', 'welcome') NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    variables TEXT, -- JSON: liste variables disponibles
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE email_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    attachments TEXT, -- JSON: liste fichiers attachés
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    scheduled_at TIMESTAMP NOT NULL,
    sent_at TIMESTAMP,
    error_message TEXT,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    company_id INT,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    link VARCHAR(255),
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Fonctionnalités:**
- Templates emails personnalisables
- Variables dynamiques: {client_name}, {invoice_number}, etc.
- Envoi automatique:
  - Facture lors création/modification
  - Devis lors envoi
  - Rappels de paiement
  - Confirmation paiement reçu
- File d'attente emails (queue)
- Retry automatique en cas d'échec
- Notifications in-app (cloche dans header)
- Historique emails envoyés

---

#### 3.2 API Externe & Webhooks ⭐⭐⭐
**Complexité:** Moyenne | **Temps estimé:** 6-7 jours

**Description:**
API REST complète pour intégrations tierces.

**Endpoints API:**

```
# API v1 - RESTful
/api/v1/auth/login              POST
/api/v1/auth/logout             POST
/api/v1/auth/refresh            POST

/api/v1/companies               GET, POST
/api/v1/companies/{id}          GET, PUT, DELETE

/api/v1/contacts                GET, POST
/api/v1/contacts/{id}           GET, PUT, DELETE
/api/v1/contacts/search         GET

/api/v1/invoices                GET, POST
/api/v1/invoices/{id}           GET, PUT, DELETE
/api/v1/invoices/{id}/pdf       GET
/api/v1/invoices/{id}/send      POST

/api/v1/quotes                  GET, POST
/api/v1/quotes/{id}             GET, PUT, DELETE
/api/v1/quotes/{id}/convert     POST

/api/v1/transactions            GET, POST
/api/v1/transactions/{id}       GET, PUT, DELETE

/api/v1/reports/balance-sheet   GET
/api/v1/reports/profit-loss     GET
/api/v1/reports/cashflow        GET

/api/v1/webhooks                GET, POST
/api/v1/webhooks/{id}           GET, PUT, DELETE
```

**Authentification API:**
- JWT (JSON Web Tokens)
- API Keys
- OAuth 2.0 (optionnel phase 4)

**Webhooks:**
```sql
CREATE TABLE webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    url VARCHAR(255) NOT NULL,
    events TEXT NOT NULL, -- JSON array: ["invoice.created", "payment.received"]
    secret VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_triggered_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE webhook_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    event VARCHAR(100) NOT NULL,
    payload TEXT NOT NULL,
    response_code INT,
    response_body TEXT,
    triggered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Événements déclenchables:**
- invoice.created, invoice.updated, invoice.paid
- quote.created, quote.accepted, quote.rejected
- payment.received, payment.sent
- contact.created, contact.updated
- transaction.created

---

#### 3.3 Gestion Multi-Utilisateurs & Rôles ⭐⭐⭐
**Complexité:** Moyenne-Haute | **Temps estimé:** 7-8 jours

**Description:**
Système de permissions et collaboration.

**Base de données:**
```sql
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    permissions TEXT NOT NULL -- JSON: liste permissions
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO roles (name, display_name, permissions) VALUES
('admin', 'Administrateur', '["*"]'),
('accountant', 'Comptable', '["view_all", "create_transaction", "create_invoice", "view_reports"]'),
('manager', 'Gestionnaire', '["view_dashboard", "create_invoice", "create_quote", "view_contacts"]'),
('viewer', 'Lecteur', '["view_dashboard", "view_invoices", "view_contacts"]');

CREATE TABLE company_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    is_owner BOOLEAN DEFAULT FALSE,
    invited_by INT,
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_company_user (company_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_invitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    invited_by INT NOT NULL,
    expires_at TIMESTAMP NOT NULL,
    accepted_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
    FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Permissions:**
```php
// config/permissions.php
return [
    'view_dashboard',
    'view_contacts', 'create_contact', 'edit_contact', 'delete_contact',
    'view_invoices', 'create_invoice', 'edit_invoice', 'delete_invoice',
    'view_quotes', 'create_quote', 'edit_quote', 'delete_quote',
    'view_transactions', 'create_transaction', 'edit_transaction', 'delete_transaction',
    'view_reports', 'export_reports',
    'view_settings', 'edit_settings',
    'manage_users', 'invite_users',
    'validate_accounting', 'close_period'
];
```

**Fonctionnalités:**
- Invitation utilisateurs par email
- Gestion rôles personnalisables
- Permissions granulaires
- Audit trail (qui a fait quoi)
- Historique modifications
- Commentaires sur documents
- Workflow approbation (ex: factures >1000 CHF)

---

#### 3.4 Import/Export Données ⭐⭐⭐
**Complexité:** Moyenne | **Temps estimé:** 5-6 jours

**Description:**
Import et export données en masse.

**Formats supportés:**

**Export:**
- CSV (contacts, transactions, invoices)
- Excel (.xlsx) via PhpSpreadsheet
- PDF (rapports)
- XML (comptabilité pour fiduciaire)
- JSON (backup complet)

**Import:**
- CSV (contacts, transactions)
- Excel (.xlsx)
- VCard (contacts)
- Relevés bancaires (ISO 20022, MT940)

**Bibliothèque:**
```bash
composer require phpoffice/phpspreadsheet
```

**Implémentation:**
```php
// utils/ImportExport.php
class ImportExport {
    // Export
    public function exportContactsCSV($company_id);
    public function exportTransactionsCSV($company_id, $filters);
    public function exportInvoicesExcel($company_id, $filters);
    public function exportAccountingXML($company_id, $period);
    public function exportFullBackup($company_id); // JSON complet

    // Import
    public function importContactsCSV($file, $company_id);
    public function importTransactionsCSV($file, $company_id);
    public function importBankStatementISO20022($file, $bank_account_id);

    // Validation
    public function validateImportData($data, $type);
    public function previewImport($file, $type);
}
```

**Fonctionnalités:**
- Mapping colonnes flexible
- Prévisualisation avant import
- Validation données
- Gestion doublons
- Logs d'import
- Rollback en cas d'erreur
- Template CSV téléchargeables

---

### PHASE 4 - Différenciation Premium (2-3 semaines)
**Priorité BASSE - Fonctionnalités "Nice to have"**

#### 4.1 Module Mobile (Progressive Web App) ⭐⭐⭐
**Complexité:** Haute | **Temps estimé:** 10-12 jours

**Description:**
Application web progressive pour utilisation mobile.

**Technologies:**
- Service Workers (cache offline)
- Manifest.json (installable)
- Responsive design optimisé mobile
- Notifications push
- Scan QR-factures avec caméra

**Fonctionnalités mobiles:**
- Consultation dashboard
- Scan et enregistrement factures fournisseurs
- Création rapide devis/factures
- Suivi paiements
- Mode hors ligne (consultation)
- Synchronisation automatique

---

#### 4.2 Intelligence Artificielle ⭐⭐
**Complexité:** Très Haute | **Temps estimé:** 15+ jours

**Description:**
Assistance IA pour comptabilité.

**Fonctionnalités:**
- OCR avancé (extraction données factures)
- Catégorisation automatique transactions
- Détection anomalies
- Prévisions trésorerie (ML)
- Suggestions optimisation fiscale
- Chatbot assistance

**Technologie:**
- API OpenAI / Anthropic Claude
- TensorFlow pour ML local
- Tesseract OCR

---

#### 4.3 Portail Client ⭐⭐⭐
**Complexité:** Haute | **Temps estimé:** 8-10 jours

**Description:**
Espace dédié pour les clients.

**Fonctionnalités:**
- Consultation factures en ligne
- Téléchargement PDF
- Historique paiements
- Paiement en ligne (Stripe/PayPal)
- Mise à jour coordonnées
- Tickets support

---

## 📋 Résumé des Priorités

### 🔴 PHASE 1 - CRITIQUE (3-4 semaines)
**Sans ces fonctionnalités, l'app n'est pas viable en Suisse**

| Fonctionnalité | Jours | Importance |
|----------------|-------|------------|
| QR-Factures Suisses | 10-12 | ⭐⭐⭐⭐⭐ |
| Devis (Quotes) | 5-6 | ⭐⭐⭐⭐⭐ |
| Export PDF Professionnel | 6-7 | ⭐⭐⭐⭐ |
| Réconciliation Bancaire | 8-10 | ⭐⭐⭐⭐ |
| **TOTAL** | **29-35 jours** | |

### 🟡 PHASE 2 - IMPORTANT (3-4 semaines)
**Fonctionnalités nécessaires pour concurrencer Winbiz**

| Fonctionnalité | Jours | Importance |
|----------------|-------|------------|
| Rappels de Paiement | 5-6 | ⭐⭐⭐⭐ |
| Gestion Fournisseurs | 6-7 | ⭐⭐⭐⭐ |
| Tableaux de Bord Avancés | 5-6 | ⭐⭐⭐⭐ |
| Rapports Comptables | 8-9 | ⭐⭐⭐⭐ |
| **TOTAL** | **24-28 jours** | |

### 🟢 PHASE 3 - AVANCÉ (3-4 semaines)
**Améliore significativement l'expérience**

| Fonctionnalité | Jours | Importance |
|----------------|-------|------------|
| Module Email | 5-6 | ⭐⭐⭐ |
| API & Webhooks | 6-7 | ⭐⭐⭐ |
| Multi-utilisateurs & Rôles | 7-8 | ⭐⭐⭐ |
| Import/Export | 5-6 | ⭐⭐⭐ |
| **TOTAL** | **23-27 jours** | |

### 🔵 PHASE 4 - PREMIUM (2-3 semaines)
**Différenciation et innovation**

| Fonctionnalité | Jours | Importance |
|----------------|-------|------------|
| PWA Mobile | 10-12 | ⭐⭐⭐ |
| Intelligence Artificielle | 15+ | ⭐⭐ |
| Portail Client | 8-10 | ⭐⭐⭐ |

---

## 🎯 Plan d'Exécution Recommandé

### SPRINT 1 (2 semaines) - QR-Factures
1. Configuration base de données (1 jour)
2. Modèle QRInvoice (2 jours)
3. Génération QR-Code (3 jours)
4. Template PDF avec QR (3 jours)
5. Interface utilisateur (2 jours)
6. Tests & validation (1 jour)

### SPRINT 2 (1 semaine) - Devis
1. Tables quotes + quote_items (0.5 jour)
2. Modèle Quote (1.5 jours)
3. Interface création/modification (2 jours)
4. Conversion devis → facture (1 jour)
5. Export PDF devis (1 jour)
6. Tests (1 jour)

### SPRINT 3 (1.5 semaines) - Export PDF Pro
1. Installation mPDF (0.5 jour)
2. Templates HTML professionnels (2 jours)
3. Classe PDFGenerator (2 jours)
4. Personnalisation (logo, couleurs) (1.5 jours)
5. Tests tous formats (1.5 jours)

### SPRINT 4 (2 semaines) - Réconciliation Bancaire
1. Tables bank_accounts + bank_transactions (1 jour)
2. Parser ISO 20022/MT940/CSV (3 jours)
3. Algorithme matching automatique (3 jours)
4. Interface rapprochement (3 jours)
5. Lettrage & validation (1 jour)
6. Tests (1 jour)

**Après PHASE 1:** Application utilisable en environnement de production Suisse

---

## 💰 Estimation Budget

### Option 1: Développement Interne
- **Phase 1:** 29-35 jours × 600 CHF/jour = **17'400 - 21'000 CHF**
- **Phase 2:** 24-28 jours × 600 CHF/jour = **14'400 - 16'800 CHF**
- **Phase 3:** 23-27 jours × 600 CHF/jour = **13'800 - 16'200 CHF**
- **Phase 4:** ~35 jours × 600 CHF/jour = **~21'000 CHF**

**Total complet:** ~**66'600 - 75'000 CHF**

### Option 2: Développement Externe (Freelance)
- **Phase 1:** 29-35 jours × 800 CHF/jour = **23'200 - 28'000 CHF**
- **Phase 2:** 24-28 jours × 800 CHF/jour = **19'200 - 22'400 CHF**
- **Phase 3:** 23-27 jours × 800 CHF/jour = **18'400 - 21'600 CHF**

**Total Phases 1-3:** ~**60'800 - 72'000 CHF**

### Option 3: Approche Hybride (Recommandée)
- **Phase 1 en interne** (critique pour votre business)
- **Phases 2-3 externalisées** (accélération)
- **Phase 4 optionnelle** selon retours utilisateurs

---

## 🔧 Stack Technique Recommandée

### Backend
- **PHP 8.1+** (versions actuelles plus performantes)
- **MySQL 8.0+** (ou MariaDB 10.6+)
- **Composer** pour dépendances

### Bibliothèques PHP Essentielles
```bash
composer require endroid/qr-code           # QR-Codes
composer require mpdf/mpdf                 # PDF generation
composer require phpmailer/phpmailer       # Emails
composer require phpoffice/phpspreadsheet  # Excel import/export
composer require firebase/php-jwt          # API authentication
```

### Frontend
- **Vanilla JavaScript** (déjà utilisé)
- **Chart.js** (graphiques)
- **Bootstrap 5** ou **Tailwind CSS** (optionnel pour moderniser UI)

### DevOps
- **Git** (versioning)
- **Docker** (environnement standardisé)
- **CI/CD** via GitHub Actions

---

## 📊 Comparaison Finale: Votre App vs Winbiz

| Fonctionnalité | Winbiz | Après Phase 1 | Après Phase 2 | Après Phase 3 |
|----------------|--------|---------------|---------------|---------------|
| QR-Factures | ✅ | ✅ | ✅ | ✅ |
| Devis | ✅ | ✅ | ✅ | ✅ |
| PDF Pro | ✅ | ✅ | ✅ | ✅ |
| Réconciliation | ✅ | ✅ | ✅ | ✅ |
| Rappels | ✅ | ❌ | ✅ | ✅ |
| Fournisseurs | ✅ | ❌ | ✅ | ✅ |
| Dashboard | ✅ | ✅ | ✅✅ | ✅✅ |
| Rapports | ✅ | ✅ | ✅✅ | ✅✅ |
| Emails | ✅ | ❌ | ❌ | ✅ |
| API | ✅ | ❌ | ❌ | ✅ |
| Multi-users | ✅ | ❌ | ❌ | ✅ |
| Import/Export | ✅ | ❌ | ❌ | ✅ |
| Mobile | ✅ | ❌ | ❌ | ❌ |
| **SCORE** | **12/12** | **7/12** | **10/12** | **12/12** |

---

## ✅ Checklist de Démarrage

### Avant de commencer Phase 1:

- [ ] Backup complet base de données
- [ ] Environnement de développement séparé
- [ ] Git repository configuré
- [ ] Composer installé et configuré
- [ ] Compte test PostFinance/Banque pour QR-IBAN
- [ ] Logo entreprise en haute résolution
- [ ] Templates documents existants (pour design PDF)
- [ ] Liste des besoins spécifiques utilisateurs

### Documentation à créer:

- [ ] Spécifications techniques détaillées
- [ ] Diagrammes UML (classes, séquences)
- [ ] Documentation API
- [ ] Guide utilisateur
- [ ] Procédures de test
- [ ] Plan de migration données

---

## 🚀 Prochaines Étapes

1. **Validation du plan** avec stakeholders
2. **Priorisation** des fonctionnalités (si budget limité)
3. **Constitution équipe** (interne/externe)
4. **Mise en place environnement** de développement
5. **Sprint Planning** détaillé
6. **Démarrage Phase 1** 🎉

---

## 📞 Support & Questions

Pour toute question sur ce plan de développement:
- Créer une issue dans le repository
- Contacter l'équipe technique
- Consulter CLAUDE.md pour guidelines de développement

**Dernière mise à jour:** 2024
**Version:** 1.0
