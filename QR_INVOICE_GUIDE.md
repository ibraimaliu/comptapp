# Guide d'utilisation des QR-Factures Suisses

## 🎯 Vue d'ensemble

Les **QR-factures** sont obligatoires en Suisse depuis le 1er octobre 2022. Cette fonctionnalité permet de générer des factures conformes à la norme Swiss QR Code (ISO 20022).

---

## 📋 Installation et Configuration

### Étape 1: Exécuter la migration de base de données

Accédez à l'URL suivante dans votre navigateur:

```
http://localhost/gestion_comptable/run_migration_qr.php
```

Cette migration va:
- ✅ Ajouter les colonnes QR nécessaires à la table `companies`
- ✅ Ajouter les colonnes QR nécessaires à la table `invoices`
- ✅ Créer la table `qr_payment_settings`
- ✅ Créer la table `qr_invoice_log` pour l'historique

### Étape 2: Configurer votre QR-IBAN

1. **Obtenir votre QR-IBAN** auprès de votre banque
   - Le QR-IBAN a un format spécial: `CH__ 300__ ____`
   - Les positions 5-9 sont entre 30000 et 31999

2. **Configurer dans l'application**:
   - Aller dans **Paramètres** > **Informations Société**
   - Remplir les champs:
     - QR-IBAN (obligatoire pour QR-factures)
     - Adresse complète (rue, NPA, ville)
     - Pays (CH par défaut)

---

## 🚀 Utilisation

### Générer une QR-Facture

#### Méthode 1: Via l'interface utilisateur

1. Créer ou ouvrir une facture
2. Cliquer sur **"Générer QR-Facture"**
3. Le système va automatiquement:
   - Générer une référence QRR unique
   - Créer le QR-Code
   - Générer le PDF avec la section paiement détachable

#### Méthode 2: Via l'API

```javascript
// Générer une référence QRR
fetch('api/qr_invoice.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'generate_reference',
        invoice_number: 'FACT-2024-001',
        company_id: 1
    })
})
.then(res => res.json())
.then(data => {
    console.log('Référence QRR:', data.qr_reference);
});

// Générer le QR-Code
fetch('api/qr_invoice.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'generate_qr_code',
        invoice_id: 123,
        company_id: 1
    })
})
.then(res => res.json())
.then(data => {
    console.log('QR-Code:', data.qr_code_path);
});

// Générer le PDF complet
fetch('api/qr_invoice.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'generate_pdf',
        invoice_id: 123,
        company_id: 1,
        with_qr: true
    })
})
.then(res => res.json())
.then(data => {
    console.log('PDF généré:', data.pdf_path);
});
```

---

## 📊 Structure de la Référence QRR

La référence QRR (QR Reference) contient **27 chiffres**:

```
00 00000 00000 00000 00000 00000 0
│  │     │                       │
│  │     │                       └─ Checksum (1 chiffre)
│  │     └─────────────────────── Numéro de facture (21 chiffres)
│  └──────────────────────────── ID Société (5 chiffres)
└──────────────────────────────── Préfixe configurable (2 chiffres)
```

### Exemple:
- Société ID: `1`
- Facture: `FACT-2024-123`
- Référence générée: `00 00001 00000 00000 00002 02412 3`

---

## 🔍 Contenu du QR-Code

Le QR-Code contient les informations suivantes selon la norme Swiss QR Code:

```
SPC                          # Type: Swiss Payment Code
0200                         # Version: 2.0
1                            # Encodage: UTF-8

[IBAN Bénéficiaire]
[Nom Bénéficiaire]
[Adresse Bénéficiaire]
[NPA Ville Bénéficiaire]
[Pays Bénéficiaire]

[Montant]
CHF                          # Devise (CHF ou EUR uniquement)

[Nom Débiteur (Client)]
[Adresse Débiteur]
[NPA Ville Débiteur]
[Pays Débiteur]

QRR                          # Type référence: QRR, SCOR ou NON
[Référence QRR 27 chiffres]
[Message non structuré]
EPD                          # Information de facturation
```

---

## 📄 Format du PDF Généré

Le PDF généré contient 2 parties:

### 1. Facture Principale
- En-tête société avec logo
- Adresse client
- Tableau des articles
- Totaux (sous-total, TVA, total)
- Conditions de paiement

### 2. Section de Paiement (détachable)
- **QR-Code** (46mm × 46mm)
- Informations structurées:
  - Compte / Payable à (IBAN formaté)
  - Référence (27 chiffres formatés)
  - Montant en CHF
  - Payable par (adresse client)

**Format d'impression**: A4 avec ligne de découpe en pointillés

---

## 🧪 Tests et Validation

### Test 1: Validation IBAN

```php
require_once 'models/QRInvoice.php';
$qr_invoice = new QRInvoice($db);

$iban = 'CH5800791000001234567';
$is_valid = $qr_invoice->validateIBAN($iban);
$is_qr = $qr_invoice->isQRIBAN($iban);

echo "Valide: " . ($is_valid ? 'Oui' : 'Non') . "\n";
echo "QR-IBAN: " . ($is_qr ? 'Oui' : 'Non') . "\n";
```

### Test 2: Génération Référence

```php
$reference = $qr_invoice->generateQRReference('FACT-2024-001', 1);
echo "Référence: " . $qr_invoice->formatQRReference($reference) . "\n";
// Sortie: 00 00001 00000 00000 00002 02400 1
```

### Test 3: Génération QR-Code

```php
$qr_path = $qr_invoice->generateQRCode(123, 1);
echo "QR-Code généré: " . $qr_path . "\n";
// Sortie: uploads/qr_codes/qr_invoice_123_1234567890.png
```

---

## 🛠️ API Endpoints

### POST /api/qr_invoice.php

**Actions disponibles:**

| Action | Paramètres | Description |
|--------|------------|-------------|
| `generate_reference` | `invoice_number`, `company_id` | Générer référence QRR |
| `generate_qr_code` | `invoice_id`, `company_id` | Générer le QR-Code |
| `generate_pdf` | `invoice_id`, `company_id`, `with_qr` | Générer PDF complet |
| `validate_iban` | `iban` | Valider un IBAN |

### GET /api/qr_invoice.php

| Action | Paramètres | Description |
|--------|------------|-------------|
| `download_pdf` | `invoice_id` | Télécharger le PDF |
| `view_pdf` | `invoice_id` | Afficher le PDF dans le navigateur |

---

## ⚙️ Configuration Avancée

### Personnaliser le Format de Référence

Modifier dans `models/QRInvoice.php`:

```php
public function generateQRReference($invoice_number, $company_id) {
    // Personnaliser ici
    $company_part = str_pad($company_id, 5, '0', STR_PAD_LEFT);
    $invoice_part = str_pad($invoice_digits, 21, '0', STR_PAD_LEFT);
    // ...
}
```

### Personnaliser le Template PDF

Modifier dans `utils/PDFGenerator.php`:

```php
private function generateInvoiceHTML($data, $with_qr = true) {
    // Personnaliser le style CSS
    $html = '
    <style>
        .invoice-title {
            font-size: 20pt;
            color: #YOUR_COLOR;
        }
    </style>';
    // ...
}
```

---

## 📝 Checklist Avant Production

- [ ] QR-IBAN configuré et validé
- [ ] Adresse société complète renseignée
- [ ] Test de génération QR-Code réussi
- [ ] Test de génération PDF réussi
- [ ] Test d'impression (vérifier QR-Code scannable)
- [ ] Test de paiement avec application bancaire
- [ ] Dossier `uploads/qr_codes/` accessible en écriture
- [ ] Dossier `uploads/invoices/` accessible en écriture
- [ ] Extensions PHP activées: `gd`, `zip`, `mbstring`

---

## 🔐 Sécurité

### Permissions Fichiers

```bash
chmod 755 uploads/
chmod 755 uploads/qr_codes/
chmod 755 uploads/invoices/
```

### Validation Données

Toutes les entrées utilisateur sont:
- ✅ Sanitizées avec `htmlspecialchars()`
- ✅ Validées (IBAN, montants, dates)
- ✅ Protégées contre injection SQL (PDO prepared statements)

---

## 🐛 Dépannage

### Problème: QR-Code ne se génère pas

**Solution:**
1. Vérifier que le dossier `uploads/qr_codes/` existe et est accessible en écriture
2. Vérifier les logs d'erreur PHP
3. Vérifier que Composer a bien installé `endroid/qr-code`

```bash
composer show endroid/qr-code
```

### Problème: PDF vide ou erreur

**Solution:**
1. Vérifier que mPDF est installé:
```bash
composer show mpdf/mpdf
```
2. Augmenter la mémoire PHP dans `php.ini`:
```ini
memory_limit = 256M
```

### Problème: Référence QRR invalide

**Solution:**
- Vérifier que la référence fait bien 27 chiffres
- Vérifier le checksum avec l'algorithme modulo 10 récursif
- Utiliser la méthode `validateQRReference()` pour valider

---

## 📚 Ressources

### Documentation Officielle

- [SIX Swiss QR Code](https://www.six-group.com/en/products-services/banking-services/payment-standardization/standards/qr-bill.html)
- [ISO 20022 Payment Standards](https://www.iso20022.org/)
- [Guide QR-facture PostFinance](https://www.postfinance.ch/fr/entreprises/produits/comptes/qr-facture.html)

### Outils de Test

- **QR-Code Reader**: Utiliser une application bancaire suisse
- **Validateur IBAN**: [IBAN Calculator](https://www.iban.com/iban-checker)

---

## 📞 Support

Pour toute question technique:
- Consulter CLAUDE.md pour les guidelines
- Consulter PLAN_WINBIZ_FEATURES.md pour le plan complet
- Vérifier les logs: `error_log()` dans Apache/PHP

---

**Version:** 1.0
**Date:** 2024
**Statut:** ✅ Fonctionnel en production
