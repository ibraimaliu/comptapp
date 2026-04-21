# 🏦 Module Rapprochement Bancaire

## 🎯 Vue d'ensemble

Le **Module Rapprochement Bancaire** permet d'importer et de rapprocher automatiquement les relevés bancaires avec les factures émises. Compatible avec les formats bancaires suisses et internationaux.

---

## ✨ Fonctionnalités

### 1. Gestion des Comptes Bancaires
- ✅ Création et gestion de plusieurs comptes bancaires
- ✅ Support multi-devises (CHF, EUR, USD)
- ✅ Validation IBAN suisse
- ✅ Suivi des soldes (ouverture, actuel)
- ✅ Historique des rapprochements
- ✅ Activation/désactivation des comptes

### 2. Import de Relevés Bancaires

**Formats Supportés:**

#### **ISO 20022 Camt.053 (XML)**
- Format standard des banques suisses
- Compatible: UBS, Credit Suisse, PostFinance, Raiffeisen, etc.
- Extraction automatique:
  - Références QR 27 chiffres
  - Références structurées
  - Informations contrepartie (nom, IBAN)
  - Soldes après transaction

#### **MT940 (SWIFT)**
- Format international SWIFT
- Utilisé par les banques internationales
- Parsing des tags MT940 standards
- Extraction des références et descriptions

#### **CSV**
- Mapping configurable des colonnes
- Support de multiples formats de CSV
- Paramètres personnalisables:
  - Délimiteur (, ou ;)
  - Séparateur décimal (. ou ,)
  - Séparateur de milliers
  - Format de date (d.m.Y, Y-m-d, etc.)
  - Encodage (UTF-8, ISO-8859-1, Windows-1252)

### 3. Rapprochement Automatique

**Algorithmes de Matching:**

1. **QR-Reference Exact Match** (Priorité 1)
   - Matching 100% fiable par QR-Reference
   - Met à jour automatiquement la facture comme "payée"
   - Enregistre la date de paiement

2. **Amount Matching** (Priorité 2)
   - Recherche par montant avec tolérance configurable (défaut: 0.50 CHF)
   - Suggestions de factures correspondantes
   - Validation manuelle requise

3. **Description Keywords** (Priorité 3)
   - Recherche par mots-clés dans la description
   - Extraction automatique des numéros de facture

### 4. Rapprochement Manuel

- Interface intuitive pour transactions non rapprochées
- Liste de suggestions de factures correspondantes
- Validation rapide par clic
- Possibilité d'ignorer les transactions internes

### 5. Statistiques et Reporting

- Tableau de bord avec métriques:
  - Nombre de comptes actifs
  - Transactions rapprochées vs en attente
  - Solde total des comptes
  - Montants crédits/débits
- Filtrage par compte bancaire
- Historique des imports

---

## 📦 Installation

### 1. Créer les Tables

Exécuter le script SQL:

```bash
cd c:\xampp\htdocs\gestion_comptable
"C:\xampp\mysql\bin\mysql.exe" -u root -pAbil gestion_comptable < install_bank_reconciliation.sql
```

**Tables créées:**
- `bank_accounts` - Comptes bancaires
- `bank_transactions` - Transactions importées
- `bank_import_configs` - Configurations CSV
- `bank_reconciliation_rules` - Règles de rapprochement automatique

### 2. Vérification

```sql
USE gestion_comptable;
SHOW TABLES LIKE 'bank%';
```

Devrait afficher 4 tables.

### 3. Permissions

Créer le dossier uploads pour les imports:

```bash
mkdir uploads/bank_imports
chmod 755 uploads/bank_imports
```

---

## 🚀 Utilisation

### Accès au Module

Naviguer vers: **http://localhost/gestion_comptable/index.php?page=bank_reconciliation**

Ou cliquer sur **"Rapprochement"** dans le menu latéral gauche.

---

### 1. Créer un Compte Bancaire

1. Cliquer sur **"Nouveau Compte"**
2. Remplir les informations:
   - **Nom**: Ex: "Compte courant UBS"
   - **Banque**: Ex: "UBS SA"
   - **IBAN**: Format suisse CH44 3199 9123 0008 8901 2
   - **Devise**: CHF, EUR, USD
   - **Solde d'ouverture**: Montant initial
   - **Date du solde**: Date de référence
3. Cliquer sur **"Enregistrer"**

Le compte apparaît dans l'onglet "Comptes Bancaires".

---

### 2. Importer un Relevé Bancaire

#### Méthode 1: Glisser-Déposer

1. Aller sur l'onglet **"Importer Relevé"**
2. Sélectionner le compte bancaire dans la liste déroulante
3. Glisser le fichier (XML, CSV, TXT) dans la zone de dépôt
4. Cliquer sur **"Importer et Analyser"**

#### Méthode 2: Sélection de Fichier

1. Cliquer sur la zone de dépôt
2. Sélectionner le fichier depuis votre ordinateur
3. Vérifier les informations affichées
4. Cliquer sur **"Importer et Analyser"**

**Formats acceptés:**
- `.xml` - Camt.053
- `.txt` ou `.940` - MT940
- `.csv` - CSV

**Taille maximale:** 10 MB

#### Auto-Detection du Format

Le système détecte automatiquement le format du fichier:
- Camt.053: présence de tags XML `<camt.053>`
- MT940: présence de tags `:20:`, `:61:`, etc.
- CSV: présence de délimiteurs `,` ou `;`

---

### 3. Résultats de l'Import

Après l'import, le système affiche:

```
Import réussi: 45 transactions importées (3 doublons ignorés)
Rapprochement automatique: 12 transactions rapprochées
```

**Le système:**
- ✅ Importe toutes les nouvelles transactions
- ✅ Ignore automatiquement les doublons (détection par date + montant + description)
- ✅ Tente un rapprochement automatique par QR-Reference
- ✅ Place les transactions non rapprochées dans l'onglet "En Attente"

---

### 4. Rapprocher les Transactions En Attente

1. Aller sur l'onglet **"Transactions En Attente"**
2. Pour chaque transaction:
   - Visualiser: Date, Description, Montant, Contrepartie
   - **Option 1**: Cliquer sur **<i class="fas fa-check"></i>** pour rapprocher
     - Le système cherche automatiquement les factures correspondantes
     - Affiche une liste de suggestions avec niveau de confiance
     - Sélectionner la facture correcte
     - Valider le rapprochement
   - **Option 2**: Cliquer sur **<i class="fas fa-times"></i>** pour ignorer
     - Utiliser pour transactions internes, frais bancaires, etc.

#### Matching QR-Reference (Automatique)

Si la transaction contient une QR-Reference:
```
QR: 00 00010 00000 00000 00000 00056
```

Le système trouve automatiquement la facture FACT-2024-010 et propose un rapprochement avec confiance 100%.

#### Matching par Montant

Si pas de QR-Reference, recherche par montant:
```
Transaction: +1234.56 CHF
Suggestions:
- FACT-2024-015: 1234.56 CHF (80% confiance)
- FACT-2024-018: 1234.50 CHF (70% confiance)
```

Sélectionner manuellement la bonne facture.

---

### 5. Consulter l'Historique

Onglet **"Rapprochées"**: Liste toutes les transactions rapprochées avec:
- Date de transaction
- Description
- Montant
- Numéro de facture associée
- Statut (matched = auto, manual = manuel)
- Date de rapprochement

---

## 📋 Structure des Données

### Comptes Bancaires (bank_accounts)

```sql
id              -- ID unique
company_id      -- Société propriétaire
name            -- Nom du compte
bank_name       -- Nom de la banque
iban            -- IBAN (format suisse)
account_number  -- Numéro de compte alternatif
currency        -- CHF, EUR, USD
opening_balance -- Solde d'ouverture
current_balance -- Solde actuel calculé
last_reconciliation_date -- Dernière réconciliation
is_active       -- 1=actif, 0=inactif
```

### Transactions Bancaires (bank_transactions)

```sql
id                      -- ID unique
bank_account_id         -- Compte bancaire
company_id              -- Société
transaction_date        -- Date de transaction
value_date              -- Date de valeur
booking_date            -- Date de comptabilisation
bank_reference          -- Référence bancaire unique
description             -- Description/libellé
amount                  -- Montant (+ crédit, - débit)
currency                -- Devise
balance_after           -- Solde après transaction
counterparty_name       -- Nom contrepartie
counterparty_account    -- IBAN contrepartie
qr_reference            -- QR-Reference 27 chiffres
structured_reference    -- Référence structurée ISO 20022
status                  -- pending, matched, manual, ignored
matched_invoice_id      -- Facture rapprochée
reconciliation_date     -- Date du rapprochement
import_batch_id         -- ID du lot d'import
import_format           -- camt053, mt940, csv
raw_data                -- Données brutes (audit)
```

---

## 🔧 Configuration Avancée

### Mapping CSV Personnalisé

Pour configurer l'import de CSV d'une banque spécifique:

1. Créer une configuration dans `bank_import_configs`
2. Définir le mapping des colonnes:

```json
{
  "date": 0,
  "description": 1,
  "amount": 2,
  "currency": 3,
  "balance": 4,
  "counterparty_name": 5,
  "reference": 6
}
```

3. Paramètres CSV:
```json
{
  "delimiter": ";",
  "enclosure": "\"",
  "has_header": true,
  "skip_lines": 2,
  "encoding": "ISO-8859-1",
  "date_format": "d.m.Y",
  "decimal_separator": ".",
  "thousands_separator": "'"
}
```

### Règles de Rapprochement

Créer des règles personnalisées dans `bank_reconciliation_rules`:

**Exemple: Matching par Description**
```sql
INSERT INTO bank_reconciliation_rules SET
  company_id = 1,
  rule_name = 'Facture dans description',
  rule_type = 'description_keyword',
  priority = 10,
  is_active = 1,
  rule_params = '{"keywords": ["FACTURE", "FACT-"], "match": "partial"}',
  auto_match = 0,  -- Suggérer seulement
  suggest_only = 1;
```

---

## 🐛 Dépannage

### Import échoue

**Problème**: "Aucune transaction importée"

**Solutions**:
1. Vérifier le format du fichier (XML valide, CSV avec bon encodage)
2. Vérifier les permissions du dossier `uploads/bank_imports/`
3. Consulter les logs Apache: `c:\xampp\apache\logs\error.log`
4. Tester avec un fichier d'exemple fourni

### Doublons importés

**Problème**: Transactions apparaissent en double

**Cause**: Le système détecte les doublons par: `date + montant + description + bank_reference`

**Solution**: Si le fichier ne contient pas de `bank_reference`, des doublons peuvent se produire. Ajouter une référence unique ou supprimer manuellement via SQL:

```sql
DELETE FROM bank_transactions
WHERE id IN (
  SELECT id FROM (
    SELECT id, ROW_NUMBER() OVER (
      PARTITION BY transaction_date, amount, description
      ORDER BY id DESC
    ) as rn
    FROM bank_transactions
  ) t WHERE t.rn > 1
);
```

### QR-Reference non détectée

**Problème**: QR-Reference présente mais pas extraite

**Solutions**:
1. **Camt.053**: Vérifier tag `<CdtrRefInf><Ref>`
2. **MT940**: Vérifier présence de 27 chiffres consécutifs dans tag `:86:`
3. **CSV**: Mapper la colonne `reference` correctement

**Debug**:
```sql
SELECT id, description, qr_reference, raw_data
FROM bank_transactions
WHERE qr_reference IS NULL
  AND description LIKE '%RF%'
LIMIT 10;
```

### Rapprochement automatique ne fonctionne pas

**Problème**: Aucune transaction rapprochée automatiquement

**Vérifications**:
1. Les factures ont-elles une `qr_reference` générée?
```sql
SELECT COUNT(*) FROM invoices WHERE qr_reference IS NOT NULL;
```

2. Les QR-References correspondent-elles?
```sql
SELECT bt.qr_reference AS trans_qr, i.qr_reference AS invoice_qr
FROM bank_transactions bt
LEFT JOIN invoices i ON bt.qr_reference = i.qr_reference
WHERE bt.status = 'pending' AND bt.qr_reference IS NOT NULL
LIMIT 10;
```

3. Les factures sont-elles dans le bon statut ('sent' ou 'overdue')?

---

## 📊 Exemples de Requêtes SQL

### Transactions Non Rapprochées avec Montants Élevés

```sql
SELECT
  bt.transaction_date,
  bt.description,
  bt.amount,
  bt.counterparty_name,
  ba.name AS account_name
FROM bank_transactions bt
INNER JOIN bank_accounts ba ON bt.bank_account_id = ba.id
WHERE bt.status = 'pending'
  AND ABS(bt.amount) > 5000
ORDER BY ABS(bt.amount) DESC;
```

### Factures Payées ce Mois

```sql
SELECT
  i.number,
  i.total,
  i.paid_date,
  c.name AS client_name
FROM invoices i
INNER JOIN contacts c ON i.client_id = c.id
WHERE i.status = 'paid'
  AND MONTH(i.paid_date) = MONTH(CURDATE())
  AND YEAR(i.paid_date) = YEAR(CURDATE())
ORDER BY i.paid_date DESC;
```

### Solde Total par Devise

```sql
SELECT
  currency,
  COUNT(*) AS total_accounts,
  SUM(current_balance) AS total_balance
FROM bank_accounts
WHERE company_id = 1 AND is_active = 1
GROUP BY currency;
```

### Taux de Rapprochement

```sql
SELECT
  COUNT(*) AS total_transactions,
  SUM(CASE WHEN status IN ('matched', 'manual') THEN 1 ELSE 0 END) AS reconciled,
  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
  ROUND(
    SUM(CASE WHEN status IN ('matched', 'manual') THEN 1 ELSE 0 END) * 100.0 / COUNT(*),
    2
  ) AS reconciliation_rate
FROM bank_transactions
WHERE company_id = 1;
```

---

## 🔐 Sécurité

### Contrôles d'Accès

Tous les endpoints AJAX vérifient:
- ✅ Session active (`$_SESSION['user_id']`)
- ✅ Société sélectionnée (`$_SESSION['company_id']`)
- ✅ Appartenance des données à la société (WHERE company_id = ...)

### Validation des Fichiers

- ✅ Extensions autorisées: `.xml`, `.txt`, `.csv`, `.940`, `.053`
- ✅ Taille maximale: 10 MB
- ✅ Upload dans dossier sécurisé `uploads/bank_imports/`
- ✅ Nom de fichier unique avec timestamp

### Protection SQL

- ✅ Toutes les requêtes utilisent PDO prepared statements
- ✅ Paramètres bindés avec types appropriés
- ✅ Pas de concaténation de requêtes

### Logs et Audit

- ✅ `raw_data` conserve les données source pour audit
- ✅ `import_batch_id` permet de tracer les imports
- ✅ `reconciliation_user_id` enregistre qui a validé
- ✅ Logs d'erreurs dans Apache error.log

---

## 📈 Performance

### Optimisations Implémentées

1. **Index SQL**:
   - `transaction_date` - Recherches par période
   - `qr_reference` - Matching automatique rapide
   - `status` - Filtrage pending/matched
   - `import_batch_id` - Traçabilité des imports

2. **Pagination**:
   - Liste des transactions limitée à 100 par défaut
   - Paramètres `limit` et `offset` disponibles

3. **Détection de Doublons**:
   - Vérification avant insertion
   - Évite les imports multiples

### Temps d'Import Typiques

| Fichier | Transactions | Format | Temps |
|---------|--------------|--------|-------|
| Petit | 1-50 | Camt.053 | < 1s |
| Moyen | 50-500 | Camt.053 | 1-3s |
| Grand | 500-2000 | Camt.053 | 3-10s |
| CSV | 1-1000 | CSV | 2-5s |
| MT940 | 1-500 | MT940 | 1-4s |

---

## 🚀 Prochaines Évolutions

### Court Terme (1-2 semaines)
- [ ] Export des transactions rapprochées en Excel
- [ ] Graphiques de solde d'évolution
- [ ] Notifications email pour nouveaux imports
- [ ] Templates de mapping CSV prédéfinis (UBS, PostFinance, CS)

### Moyen Terme (1 mois)
- [ ] Import automatique via eBanking API
- [ ] Rapprochement multi-factures (paiement groupé)
- [ ] Gestion des devises avec taux de change
- [ ] Lettrage comptable automatique

### Long Terme (2-3 mois)
- [ ] Machine Learning pour matching intelligent
- [ ] OCR pour relevés PDF scannés
- [ ] Intégration directe banques suisses (Open Banking)
- [ ] Prévisions de trésorerie

---

## 📚 Ressources

### Documentation Externe

- **ISO 20022 Camt.053**: https://www.iso20022.org/
- **MT940 Specification**: https://www.sepaforcorporates.com/swift-for-corporates/account-statement-mt940-file-format-overview/
- **Swiss QR-Invoice**: https://www.paymentstandards.ch/
- **PHP SimpleXML**: https://www.php.net/manual/en/book.simplexml.php

### Fichiers du Module

```
gestion_comptable/
├── models/
│   ├── BankAccount.php           # Gestion comptes bancaires
│   ├── BankTransaction.php       # Gestion transactions
│   └── BankReconciliation.php    # Parsers (Camt.053, MT940, CSV)
├── views/
│   └── bank_reconciliation.php   # Interface utilisateur
├── assets/
│   ├── ajax/
│   │   ├── bank_accounts.php     # API CRUD comptes
│   │   ├── bank_transactions.php # API transactions
│   │   └── bank_import.php       # API import fichiers
│   └── js/
│       └── bank_reconciliation.js # Frontend JavaScript
├── uploads/
│   └── bank_imports/             # Fichiers importés
└── install_bank_reconciliation.sql # Script d'installation
```

---

## ✅ Checklist Déploiement Production

Avant de déployer en production:

- [ ] Tables créées avec `install_bank_reconciliation.sql`
- [ ] Dossier `uploads/bank_imports/` créé avec permissions 755
- [ ] Comptes bancaires configurés pour toutes les sociétés
- [ ] Tests d'import réalisés avec fichiers réels
- [ ] QR-References générées sur toutes les factures existantes
- [ ] Règles de rapprochement configurées
- [ ] Backup de la base de données effectué
- [ ] Logs d'erreurs surveillés
- [ ] Formation des utilisateurs effectuée
- [ ] Documentation remise aux utilisateurs

---

## 📞 Support

**Projet**: Gestion Comptable v3.4
**Module**: Rapprochement Bancaire
**Version**: 1.0
**Date**: 12 Novembre 2025

**Documentation complète**: [README_BANK_RECONCILIATION.md](README_BANK_RECONCILIATION.md)

---

**© 2024 Gestion Comptable - Module Rapprochement Bancaire**
