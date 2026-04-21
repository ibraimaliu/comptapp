# 🚀 Guide de Démarrage Rapide - QR-Factures

## ⏱️ Configuration en 5 minutes

---

## Étape 1: Exécuter la Migration (2 min)

### Accéder au script de migration

Ouvrez votre navigateur et allez à:
```
http://localhost/gestion_comptable/run_migration_qr.php
```

### Vérification

Vous devriez voir:
```
✅ Colonne qr_iban ajoutée à la table companies
✅ Colonne bank_iban ajoutée à la table companies
✅ Colonne address ajoutée à la table companies
...
✅ Table qr_payment_settings créée
✅ Table qr_invoice_log créée

Migration terminée avec succès!
```

---

## Étape 2: Configurer le QR-IBAN (1 min)

### 1. Accéder aux paramètres

Dans l'application:
```
Menu → Paramètres → QR-Factures Suisses
```

### 2. Cliquer sur "Modifier"

### 3. Remplir le formulaire de test

**QR-IBAN de test:**
```
CH5830000000123456789
```

**Adresse de test:**
```
Rue:          Rue de la Gare 15
Code postal:  1920
Ville:        Martigny
Pays:         CH (déjà rempli)
```

### 4. Valider l'IBAN

Cliquez sur **"Valider IBAN"**

Vous devriez voir:
```
✅ IBAN valide!
✅ Ceci est un QR-IBAN
Format: CH58 3000 0000 1234 5678 9
```

### 5. Enregistrer

Cliquez sur **"Enregistrer"**

Message attendu:
```
✅ Paramètres QR-facture enregistrés avec succès!
```

---

## Étape 3: Créer une Facture de Test (1 min)

### 1. Accéder aux factures

```
Menu → Comptabilité → Factures
```

### 2. Cliquer sur "Nouvelle facture"

### 3. Remplir les informations minimales

```
Client:       [Sélectionner un client existant ou créer un nouveau]
Date:         [Date du jour - déjà remplie]
```

### 4. Ajouter un article

```
Description:  Consultation médicale
Quantité:     1
Prix:         150.00
TVA:          7.7
```

### 5. Enregistrer la facture

Cliquez sur **"Enregistrer"**

---

## Étape 4: Générer votre première QR-Facture! (1 min)

### 1. Localiser la facture

Dans la liste des factures, trouvez la facture que vous venez de créer.

### 2. Cliquer sur le bouton QR-Code

Cliquez sur le bouton avec l'icône **QR-Code** (carré avec points).

### 3. Attendre la génération

Un spinner s'affiche pendant ~2 secondes.

### 4. Visualiser le PDF

Le PDF s'ouvre automatiquement dans un nouvel onglet!

### ✅ Vérifications

Votre PDF devrait contenir:

1. **En-tête facture**
   - Nom de votre société
   - Adresse
   - Informations client

2. **Tableau des articles**
   - Description: "Consultation médicale"
   - Quantité: 1
   - Prix: 150.00 CHF
   - TVA: 7.7%
   - Total: ~161.55 CHF

3. **Section de paiement (détachable)**
   - **QR-Code** (carré noir et blanc)
   - **Compte / Payable à**
     ```
     CH58 3000 0000 1234 5678 9
     Nom de votre société
     Rue de la Gare 15
     1920 Martigny
     ```
   - **Référence**
     ```
     00 00001 00000 00000 00000 00000 X
     (27 chiffres formatés)
     ```
   - **Montant**
     ```
     161.55 CHF
     ```
   - **Payable par**
     ```
     [Nom du client]
     [Adresse du client]
     ```

---

## 🎉 Félicitations!

Vous avez généré votre première QR-facture conforme au standard suisse!

---

## 🧪 Tests Supplémentaires

### Test 1: Télécharger le PDF

1. Retournez à la liste des factures
2. Cliquez sur le bouton **Download** (icône téléchargement)
3. Le fichier PDF se télécharge
4. Ouvrez-le pour vérifier qu'il est identique

### Test 2: Valider avec une app bancaire

Si vous avez une application bancaire suisse (PostFinance, UBS, etc.):

1. Imprimez le PDF ou affichez-le à l'écran
2. Ouvrez votre app bancaire
3. Choisissez "Scanner QR-facture"
4. Scannez le QR-Code
5. **Vérifiez:**
   - ✅ Montant pré-rempli: 161.55 CHF
   - ✅ IBAN pré-rempli: CH58 3000 0000 1234 5678 9
   - ✅ Référence pré-remplie: (27 chiffres)
   - ✅ Bénéficiaire pré-rempli: Votre société

**Note:** Ne validez pas le paiement, c'est juste un test! 😊

### Test 3: Essayer avec un IBAN classique

1. Retournez dans **Paramètres** > **QR-Factures Suisses**
2. Cliquez sur **"Modifier"**
3. Dans le champ **IBAN Bancaire Classique**, saisissez:
   ```
   CH9300762011623852957
   ```
4. Cliquez sur **"Valider IBAN"**
5. Vous devriez voir:
   ```
   ✅ IBAN valide!
   ⚠️ Ceci n'est PAS un QR-IBAN (IID non compatible)
   ```

Cela montre que la validation détecte la différence entre un IBAN classique et un QR-IBAN!

---

## 🐛 Dépannage

### Problème: "QR-IBAN est requis"

**Solution:** Vous n'avez pas configuré le QR-IBAN dans les paramètres. Retournez à l'Étape 2.

### Problème: "Erreur lors de la génération"

**Solution:** Vérifiez que:
- La migration a été exécutée (Étape 1)
- Les dossiers `uploads/qr_codes` et `uploads/invoices` existent
- Les permissions sont correctes (755)

```bash
mkdir -p uploads/qr_codes uploads/invoices
chmod 755 uploads uploads/qr_codes uploads/invoices
```

### Problème: "Extension GD manquante"

**Solution:** Activez l'extension GD dans php.ini:
```ini
extension=gd
```
Redémarrez Apache.

### Problème: PDF vide ou erreur mémoire

**Solution:** Augmentez la limite mémoire dans php.ini:
```ini
memory_limit = 256M
```
Redémarrez Apache.

### Problème: QR-Code ne s'affiche pas

**Solution:** Vérifiez que Composer a bien installé `endroid/qr-code`:
```bash
composer show endroid/qr-code
```

Si absent:
```bash
composer require endroid/qr-code:^4.8
```

---

## 📚 Documentation Complète

Pour aller plus loin:

- **Guide utilisateur détaillé:** [QR_INVOICE_GUIDE.md](QR_INVOICE_GUIDE.md)
- **Documentation technique:** [QR_INVOICE_IMPLEMENTATION_STATUS.md](QR_INVOICE_IMPLEMENTATION_STATUS.md)
- **Développement complet:** [DEVELOPPEMENT_COMPLET.md](DEVELOPPEMENT_COMPLET.md)

---

## 🎯 Prochaines Étapes

Maintenant que votre système fonctionne:

1. **Obtenir un vrai QR-IBAN**
   - Contactez votre banque
   - Demandez l'activation du service QR-facture
   - Remplacez le QR-IBAN de test

2. **Configurer votre logo**
   - Ajoutez votre logo société dans les paramètres
   - Il apparaîtra sur les PDF

3. **Créer vos vraies factures**
   - Créez vos clients dans "Adresses"
   - Créez vos factures dans "Comptabilité"
   - Générez les QR-factures!

4. **Envoyer aux clients**
   - Téléchargez les PDF
   - Envoyez par email à vos clients
   - Ils pourront payer en scannant le QR-Code

---

**Bonne utilisation! 🚀**

Si vous rencontrez des problèmes, consultez la documentation complète ou vérifiez les logs d'erreur Apache/PHP.
