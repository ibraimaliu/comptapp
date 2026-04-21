# Statut d'Implémentation: QR-Factures Suisses

## ✅ Phase 1 - Backend & Core (TERMINÉ)

### Fichiers Créés

| Fichier | Statut | Description |
|---------|--------|-------------|
| `composer.json` | ✅ | Configuration Composer avec dépendances |
| `migrations/add_qr_invoice_fields.sql` | ✅ | Migration SQL pour champs QR |
| `run_migration_qr.php` | ✅ | Script d'exécution migration |
| `models/QRInvoice.php` | ✅ | Modèle QR-Invoice (550+ lignes) |
| `utils/PDFGenerator.php` | ✅ | Générateur PDF avec QR-Code |
| `api/qr_invoice.php` | ✅ | API REST pour QR-factures |
| `QR_INVOICE_GUIDE.md` | ✅ | Documentation complète |

### Dépendances Installées

| Package | Version | Statut |
|---------|---------|--------|
| `endroid/qr-code` | 4.8.5 | ✅ Installé |
| `mpdf/mpdf` | 8.2.6 | ✅ Installé |
| `phpmailer/phpmailer` | 6.12.0 | ✅ Installé |
| `phpoffice/phpspreadsheet` | 1.30.1 | ✅ Installé |

### Fonctionnalités Implémentées

- ✅ Génération référence QRR (27 chiffres avec checksum)
- ✅ Validation IBAN suisse (21 caractères)
- ✅ Détection QR-IBAN (IID 30000-31999)
- ✅ Génération QR-Code conforme ISO 20022
- ✅ Génération PDF professionnel avec section paiement
- ✅ Formatage référence et IBAN pour affichage
- ✅ API REST complète
- ✅ Logging des QR-factures générées

---

## 🔄 Phase 2 - Frontend & UI (EN COURS)

### À Faire

#### 1. Mettre à jour l'interface de facturation

**Fichiers à modifier:**

##### A. `views/comptabilite.php`

Ajouter boutons QR-facture dans la section factures:

```php
<!-- Dans la liste des factures -->
<td>
    <button onclick="generateQRInvoice(<?php echo $invoice['id']; ?>)"
            class="btn btn-primary">
        <i class="fa fa-qrcode"></i> QR-Facture
    </button>
    <button onclick="downloadPDF(<?php echo $invoice['id']; ?>)"
            class="btn btn-success">
        <i class="fa fa-download"></i> PDF
    </button>
</td>
```

##### B. `assets/js/comptabilite.js`

Ajouter les fonctions JavaScript:

```javascript
// Générer QR-facture
function generateQRInvoice(invoiceId) {
    const companyId = getActiveCompanyId(); // Fonction existante

    fetch('api/qr_invoice.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'generate_pdf',
            invoice_id: invoiceId,
            company_id: companyId,
            with_qr: true
        })
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) {
            alert('QR-Facture générée avec succès!');
            // Ouvrir le PDF dans un nouvel onglet
            window.open('api/qr_invoice.php?action=view_pdf&invoice_id=' + invoiceId, '_blank');
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(err => {
        console.error('Erreur:', err);
        alert('Erreur lors de la génération');
    });
}

// Télécharger PDF
function downloadPDF(invoiceId) {
    window.location.href = 'api/qr_invoice.php?action=download_pdf&invoice_id=' + invoiceId;
}
```

#### 2. Ajouter configuration QR-IBAN dans paramètres

**Fichier:** `views/parametres.php`

Ajouter section configuration QR:

```php
<div class="settings-section">
    <h3>Configuration QR-Factures</h3>

    <form id="qr-settings-form">
        <div class="form-group">
            <label>QR-IBAN *</label>
            <input type="text" name="qr_iban" id="qr_iban"
                   class="form-control"
                   placeholder="CH58 3000 0001 2345 6789 0"
                   maxlength="26">
            <small>Format: CH + 19 chiffres (IID entre 30000-31999 pour QR-IBAN)</small>
        </div>

        <div class="form-group">
            <label>IBAN Bancaire Classique</label>
            <input type="text" name="bank_iban"
                   class="form-control"
                   placeholder="CH93 0076 2011 6238 5295 7">
        </div>

        <div class="form-group">
            <label>Adresse Complète *</label>
            <input type="text" name="address" class="form-control"
                   placeholder="Rue de la Gare 15">
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="form-group">
                    <label>Code Postal *</label>
                    <input type="text" name="postal_code" class="form-control"
                           placeholder="1920">
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label>Ville *</label>
                    <input type="text" name="city" class="form-control"
                           placeholder="Martigny">
                </div>
            </div>
            <div class="col-md-2">
                <div class="form-group">
                    <label>Pays</label>
                    <input type="text" name="country" class="form-control"
                           value="CH" readonly>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">
            <i class="fa fa-save"></i> Enregistrer
        </button>

        <button type="button" onclick="validateIBAN()" class="btn btn-secondary">
            <i class="fa fa-check"></i> Valider IBAN
        </button>
    </form>
</div>
```

#### 3. Créer dossiers uploads

```bash
mkdir uploads/qr_codes
mkdir uploads/invoices
chmod 755 uploads/qr_codes
chmod 755 uploads/invoices
```

---

## 🧪 Phase 3 - Tests (À FAIRE)

### Tests à Effectuer

#### Test 1: Migration Base de Données

```
URL: http://localhost/gestion_comptable/run_migration_qr.php
Résultat attendu: ✅ Toutes les tables et colonnes créées
```

#### Test 2: Validation IBAN

Tester avec:
- ✅ IBAN valide: `CH5800791000001234567`
- ✅ QR-IBAN valide: `CH4431000000003300000`
- ❌ IBAN invalide: `CH0012345678901234567`

#### Test 3: Génération Référence QRR

```php
// Test dans console PHP ou fichier test
require_once 'config/database.php';
require_once 'models/QRInvoice.php';

$database = new Database();
$db = $database->getConnection();
$qr_invoice = new QRInvoice($db);

$ref = $qr_invoice->generateQRReference('FACT-2024-001', 1);
echo "Référence: " . $qr_invoice->formatQRReference($ref);
```

Résultat attendu: `00 00001 00000 00000 00002 02400 1`

#### Test 4: Génération QR-Code

```
Créer une facture test
Appeler API: POST api/qr_invoice.php
Action: generate_qr_code
Résultat: Fichier PNG créé dans uploads/qr_codes/
```

#### Test 5: Génération PDF

```
Appeler API: POST api/qr_invoice.php
Action: generate_pdf
Résultat: PDF créé avec QR-Code intégré
Vérifier: Section paiement détachable visible
```

#### Test 6: Scan QR-Code

```
1. Générer PDF
2. Imprimer ou afficher à l'écran
3. Scanner avec app bancaire (PostFinance, UBS, etc.)
4. Vérifier: Montant, référence, IBAN correctement lus
```

---

## 📋 Checklist Prochaines Étapes

### Immédiat (Aujourd'hui)

- [ ] Exécuter `run_migration_qr.php` pour créer les tables
- [ ] Vérifier que les dépendances Composer sont installées
- [ ] Créer les dossiers `uploads/qr_codes` et `uploads/invoices`
- [ ] Configurer le QR-IBAN dans l'application

### Court Terme (Cette Semaine)

- [ ] Modifier `views/comptabilite.php` pour ajouter boutons QR
- [ ] Ajouter JavaScript dans `assets/js/comptabilite.js`
- [ ] Modifier `views/parametres.php` pour configuration QR
- [ ] Tester génération complète sur facture existante

### Moyen Terme (Prochaines Semaines)

- [ ] Ajouter prévisualisation QR-Code avant génération PDF
- [ ] Ajouter historique QR-factures générées
- [ ] Ajouter envoi automatique par email
- [ ] Optimiser performance génération PDF

---

## 📊 Architecture Technique

### Flux de Génération QR-Facture

```
1. Utilisateur clique "Générer QR-Facture"
   ↓
2. JavaScript appelle API: POST api/qr_invoice.php
   ↓
3. API vérifie session et company_id
   ↓
4. QRInvoice::generateQRReference()
   - Génère référence 27 chiffres avec checksum
   ↓
5. QRInvoice::generateQRContent()
   - Construit contenu Swiss QR Code (ISO 20022)
   ↓
6. QRInvoice::generateQRCode()
   - Utilise endroid/qr-code
   - Sauvegarde PNG dans uploads/qr_codes/
   ↓
7. PDFGenerator::generateInvoicePDF()
   - Utilise mPDF
   - Génère HTML avec styles
   - Intègre QR-Code image
   - Ajoute section paiement détachable
   - Sauvegarde PDF dans uploads/invoices/
   ↓
8. Retourne chemin PDF à l'utilisateur
   ↓
9. Navigateur ouvre PDF dans nouvel onglet
```

### Base de Données - Nouvelles Tables

```sql
companies
├── qr_iban VARCHAR(34)
├── bank_iban VARCHAR(34)
├── address VARCHAR(255)
├── postal_code VARCHAR(10)
├── city VARCHAR(100)
└── country VARCHAR(2)

invoices
├── qr_reference VARCHAR(27)
├── payment_method ENUM
├── qr_code_path VARCHAR(255)
├── payment_due_date DATE
└── payment_terms VARCHAR(255)

qr_payment_settings
├── id
├── company_id
├── enable_qr_invoice
├── qr_iban
├── creditor_name
└── creditor_address

qr_invoice_log
├── id
├── invoice_id
├── company_id
├── qr_reference
├── qr_iban
├── amount
├── currency
└── generated_at
```

---

## 🎯 Résultats Attendus

Après Phase 2 terminée, vous aurez:

✅ Génération automatique de QR-factures conformes
✅ PDF professionnels avec section paiement détachable
✅ QR-Codes scannables par toutes apps bancaires suisses
✅ Références QRR uniques et validées
✅ Interface utilisateur intuitive
✅ API REST complète
✅ Conformité 100% aux normes suisses

---

## 📞 Support Technique

### Problèmes Courants

**Q: La migration échoue**
A: Vérifier credentials MySQL dans `config/database.php`

**Q: QR-Code ne se génère pas**
A: Vérifier permissions dossier `uploads/qr_codes` (755)

**Q: PDF vide**
A: Augmenter `memory_limit` dans php.ini à 256M

**Q: Extension GD manquante**
A: Activer dans php.ini: `extension=gd`

---

## 📚 Documentation

- **Guide Utilisateur**: [QR_INVOICE_GUIDE.md](QR_INVOICE_GUIDE.md)
- **Plan Complet**: [PLAN_WINBIZ_FEATURES.md](PLAN_WINBIZ_FEATURES.md)
- **Guidelines**: [CLAUDE.md](CLAUDE.md)

---

**Dernière mise à jour:** 2024
**Phase Actuelle:** Phase 1 (Backend) ✅ TERMINÉE
**Phase Suivante:** Phase 2 (Frontend) 🔄 EN COURS

**Temps Estimé Restant:** 1-2 jours pour Phase 2
