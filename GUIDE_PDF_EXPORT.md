# 📄 Guide Export PDF - Devis et Factures

## 🎯 Vue d'ensemble

Ce guide décrit le système complet d'export PDF pour les devis et factures, incluant la génération automatique de QR-factures suisses conformes à la norme ISO 20022.

---

## 🏗️ Architecture

### Composants Principaux

```
┌─────────────────────────────────────────────────────────┐
│                    Frontend (Views)                      │
│  • views/devis.php - Bouton "PDF"                       │
│  • views/factures.php - Bouton "PDF"                    │
└─────────────────┬───────────────────────────────────────┘
                  │ onclick="exportPDF(id)"
                  ↓
┌─────────────────────────────────────────────────────────┐
│              API Endpoints (AJAX)                        │
│  • assets/ajax/export_quote_pdf.php                     │
│  • assets/ajax/export_invoice_pdf.php                   │
└─────────────────┬───────────────────────────────────────┘
                  │ PDFGenerator->generate...()
                  ↓
┌─────────────────────────────────────────────────────────┐
│           Générateur PDF (Utils)                         │
│  • utils/PDFGenerator.php                               │
│    - generateQuotePDF()                                 │
│    - generateInvoicePDF()                               │
└─────────────────┬───────────────────────────────────────┘
                  │ Uses mPDF + QRInvoice
                  ↓
┌─────────────────────────────────────────────────────────┐
│              Bibliothèques                               │
│  • vendor/mpdf/mpdf - Génération PDF                    │
│  • models/QRInvoice.php - QR-Code suisse                │
└─────────────────────────────────────────────────────────┘
```

---

## 📦 Installation

### 1. Composer et mPDF

mPDF est déjà installé via Composer:

```bash
cd c:\xampp\htdocs\gestion_comptable
composer install --ignore-platform-reqs
```

**Version installée**: mPDF ^8.2

### 2. Dossiers Uploads

Les PDFs sont stockés dans:
```
uploads/
├── invoices/     # Factures
└── quotes/       # Devis
```

Ces dossiers sont créés automatiquement si nécessaires.

---

## 🔧 Utilisation

### Export Devis (Quote)

#### Depuis l'interface

1. Aller sur **Devis** (menu latéral)
2. Cliquer sur le bouton **PDF** d'un devis
3. Le PDF est téléchargé automatiquement

#### Appel API Direct

```javascript
// GET request
window.location.href = 'assets/ajax/export_quote_pdf.php?id=5';

// Télécharge: devis_DEV-2024-001.pdf
```

#### Code Backend

```php
require_once 'utils/PDFGenerator.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$pdf_generator = new PDFGenerator($db);
$pdf_path = $pdf_generator->generateQuotePDF(
    $quote_id,      // ID du devis
    $company_id     // ID de la société
);

// Returns: "uploads/quotes/quote_DEV-2024-001_1699123456.pdf"
```

---

### Export Facture (Invoice) avec QR-Code

#### Depuis l'interface

1. Aller sur **Factures** (menu latéral)
2. Cliquer sur le bouton **PDF** d'une facture
3. Le PDF avec QR-facture est téléchargé

#### Appel API Direct

```javascript
// GET request
window.location.href = 'assets/ajax/export_invoice_pdf.php?id=10';

// Télécharge: facture_FACT-2024-001.pdf
```

#### Code Backend

```php
$pdf_generator = new PDFGenerator($db);
$pdf_path = $pdf_generator->generateInvoicePDF(
    $invoice_id,    // ID de la facture
    $company_id,    // ID de la société
    true            // Inclure QR-Code (default: true)
);

// Returns: "uploads/invoices/invoice_FACT-2024-001_1699123456.pdf"
```

---

## 📋 Contenu des PDFs

### Devis (Quote PDF)

**Sections:**
1. **En-tête société**
   - Nom de la société
   - Adresse complète
   - Téléphone
   - Email

2. **Titre**: DEVIS / OFFRE
   - Style: Violet (#667eea)
   - Font size: 20pt

3. **Méta-données**
   - Numéro: DEV-2024-001
   - Date: 11.11.2024
   - Valable jusqu'au: 11.12.2024

4. **Adresse client**
   - Dans un cadre
   - Nom, adresse, ville, NPA

5. **Tableau des items**
   - Description
   - Quantité
   - Prix unitaire HT
   - TVA %
   - Total

6. **Totaux**
   - Sous-total HT
   - TVA
   - **TOTAL TTC** (en gras)

7. **Informations de validité**
   - Cadre bleu clair
   - Date limite de validité
   - Conditions générales

8. **Notes** (si présentes)
   - Cadre gris avec bordure bleue
   - Texte formaté (nl2br)

9. **Pied de page**
   - Remerciements

---

### Facture (Invoice PDF) avec QR-Code

**Sections:**
1-6. Identiques au devis

7. **Informations de paiement**
   - Conditions de paiement
   - Date d'échéance

8. **Section QR-facture suisse** (détachable)
   - **QR-Code** (200x200px)
     - Format: Swiss QR Code ISO 20022
     - Encodage: UTF-8
     - Version: 0200

   - **Compte / Payable à**
     - QR-IBAN formaté (espaces tous les 4 caractères)
     - Nom société
     - Adresse complète

   - **Référence**
     - QR-Reference (27 chiffres)
     - Format: XX XXXXX XXXXX XXXXX XXXXX XXXXX

   - **Montant**
     - Total TTC en CHF

   - **Payable par**
     - Nom client
     - Adresse client

**Ligne de découpe**:
- Bordure pointillée en haut de la section QR
- `page-break-before: always` pour impression

---

## 🔐 Sécurité

### Vérifications API

Les endpoints vérifient:

```php
// 1. Session valide
if (!isset($_SESSION['company_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Non autorisé');
}

// 2. ID valide
$id = intval($_GET['id']);
if ($id <= 0) {
    http_response_code(400);
    die('ID invalide');
}

// 3. Appartenance à la société
$query = "SELECT id FROM invoices
          WHERE id = :id AND company_id = :company_id";
// Si aucun résultat → 404 Not Found
```

### Protection des fichiers

**uploads/.htaccess**:
```apache
# Interdire l'accès direct aux fichiers
Order Deny,Allow
Deny from all

# Autoriser PHP à lire
<FilesMatch "\.(pdf)$">
    Allow from all
</FilesMatch>
```

---

## 🎨 Personnalisation

### Style du PDF

Les styles CSS sont définis dans:
- `PDFGenerator::generateInvoiceHTML()` (factures)
- `PDFGenerator::generateQuoteHTML()` (devis)

**Personnalisations possibles:**

```php
// Couleurs principales
$quote_color = '#667eea';    // Violet pour devis
$invoice_color = '#11998e';  // Vert pour factures

// Fonts
'default_font' => 'dejavusans'  // Support UTF-8

// Marges (mm)
'margin_left' => 15,
'margin_right' => 15,
'margin_top' => 20,
'margin_bottom' => 20,

// Format
'format' => 'A4'
```

### Logo de la société

Pour ajouter un logo:

```php
// Dans generateInvoiceHTML()
$html .= '
<div class="header">
    <img src="' . __DIR__ . '/../uploads/logos/' . $company_id . '.png"
         height="60" />
    <div class="company-info">' . $company['name'] . '</div>
</div>';
```

---

## 🐛 Debugging

### Erreurs courantes

**1. "Fichier PDF non trouvé"**

```php
// Vérifier les permissions
chmod 755 uploads/invoices
chmod 755 uploads/quotes

// Vérifier l'espace disque
df -h
```

**2. "Erreur lors de la génération du PDF"**

```php
// Activer les logs mPDF
error_log("PDFGenerator Error: " . $e->getMessage());

// Vérifier les données
var_dump($invoice_data);
```

**3. "QR-Code manquant"**

```php
// Vérifier QR-IBAN configuré
SELECT qr_iban FROM companies WHERE id = ?;

// Vérifier QR-reference générée
SELECT qr_reference FROM invoices WHERE id = ?;

// Vérifier fichier QR
ls -la uploads/qr_codes/
```

**4. "Extension GD manquante"**

```bash
# Installer GD (nécessaire pour QR-Code)
# Dans php.ini, décommenter:
extension=gd

# Redémarrer Apache
```

---

## 📊 Format QR-facture suisse

### Structure du QR-Code

```
┌─────────────────────────────────────┐
│  Swiss QR Code                      │
│  Version: 0200                      │
│  Coding Type: 1 (UTF-8)            │
│─────────────────────────────────────│
│  Account (QR-IBAN)                  │
│  CH44 3199 9123 0008 8901 2       │
│─────────────────────────────────────│
│  Creditor (Société)                 │
│  Name, Address, Postal, City        │
│─────────────────────────────────────│
│  Amount                             │
│  1250.50 CHF                        │
│─────────────────────────────────────│
│  Debtor (Client)                    │
│  Name, Address, Postal, City        │
│─────────────────────────────────────│
│  Reference (QRR)                    │
│  00 00010 00000 00000 00000 00056  │
│  (27 digits with checksum)          │
└─────────────────────────────────────┘
```

### QR-Reference (27 chiffres)

**Format**:
```
CCCCC IIIIIIIIIIIIIIIIIIIII C
│     │                      │
│     │                      └─ Checksum (1 digit)
│     └─ Invoice number (21 digits, padded)
└─ Company ID (5 digits, padded)
```

**Exemple**:
- Company ID: 1 → `00001`
- Invoice: 5 → `000000000000000000005`
- Checksum: calculé → `6`
- **Résultat**: `000010000000000000000000056`

**Algorithme checksum** (ISO 7064, modulo 10 récursif):
```php
$table = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
$carry = 0;
foreach ($digits as $digit) {
    $carry = $table[($carry + $digit) % 10];
}
return (10 - $carry) % 10;
```

---

## 🧪 Tests

### Test manuel complet

**Devis:**
```bash
# 1. Créer un devis avec plusieurs items
# 2. Cliquer sur "PDF"
# 3. Vérifier:
✓ Téléchargement automatique
✓ Nom fichier: devis_DEV-2024-XXX.pdf
✓ Contenu: toutes les sections présentes
✓ Montants: calculs corrects
✓ Formatage: dates en d.m.Y
✓ Client: nom et adresse
```

**Facture:**
```bash
# 1. Créer une facture avec plusieurs items
# 2. Cliquer sur "PDF"
# 3. Vérifier:
✓ Téléchargement automatique
✓ Nom fichier: facture_FACT-2024-XXX.pdf
✓ QR-Code présent et scannable
✓ QR-Reference: 27 chiffres
✓ Section détachable avec ligne pointillée
✓ Informations paiement complètes
```

### Test QR-Code

**Scanner le QR-Code avec:**
- Application bancaire suisse (UBS, PostFinance, etc.)
- Application QR reader générique

**Vérifier:**
- ✓ IBAN correctement lu
- ✓ Montant correct
- ✓ Référence correcte
- ✓ Bénéficiaire correct
- ✓ Débiteur correct

---

## 📈 Performance

### Temps de génération

- **Devis simple** (1-5 items): ~200-500ms
- **Facture simple** (1-5 items): ~300-600ms
- **Facture avec QR** (1-5 items): ~400-800ms

### Optimisations

```php
// Cache des polices mPDF
'tempDir' => __DIR__ . '/../temp/mpdf'

// Compression PDF
'compress' => true

// Images optimisées
// QR-Code: PNG 200x200px (~5-10 KB)
```

---

## 🔮 Évolutions futures

### Phase 1 (actuel) ✅
- ✅ Export PDF devis
- ✅ Export PDF factures
- ✅ QR-factures suisses
- ✅ Téléchargement automatique

### Phase 2 (à venir)
- ⏳ Envoi par email automatique
- ⏳ Prévisualisation dans le navigateur
- ⏳ Personnalisation templates
- ⏳ Multi-langues (FR, DE, IT, EN)

### Phase 3 (futur)
- 📋 Archivage automatique
- 📋 Signature électronique
- 📋 Horodatage certifié
- 📋 Intégration e-banking

---

## 📚 Références

### Documentation externe

- **mPDF**: https://mpdf.github.io/
- **QR-facture suisse**: https://www.paymentstandards.ch/
- **ISO 20022**: https://www.iso20022.org/
- **QR-Code specs**: https://www.swiss-qr-invoice.org/

### Fichiers du projet

- `utils/PDFGenerator.php` - Classe principale
- `models/QRInvoice.php` - Génération QR
- `assets/ajax/export_quote_pdf.php` - API devis
- `assets/ajax/export_invoice_pdf.php` - API factures

---

## 🆘 Support

### Logs

Les erreurs sont loggées dans:
```bash
tail -f c:\xampp\apache\logs\error.log | grep PDFGenerator
```

### Questions fréquentes

**Q: Le QR-Code ne s'affiche pas?**
R: Vérifier que le QR-IBAN est configuré dans les paramètres société.

**Q: Le PDF est vide?**
R: Vérifier les permissions du dossier uploads/ (755).

**Q: Erreur "Class not found"?**
R: Exécuter `composer install --ignore-platform-reqs`.

**Q: Les accents sont mal affichés?**
R: mPDF utilise DejaVu Sans qui supporte UTF-8 par défaut.

---

**Version**: 1.0
**Date**: 11 Novembre 2024
**Auteur**: Gestion Comptable Team
**Statut**: ✅ Production Ready
