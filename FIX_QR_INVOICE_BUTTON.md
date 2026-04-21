# ✅ Correction du Bouton "Modifier" - QR-Factures

## 🐛 Problème Rencontré

Le bouton "Modifier" dans la section **QR-Factures** des paramètres ne fonctionnait pas.

**URL:** `http://localhost/gestion_comptable/index.php?page=parametres#qr-invoice`

## 🔍 Cause du Problème

Le JavaScript cherchait des éléments HTML avec les IDs `qr-settings-form` et `qr-settings-display`, mais ces éléments n'existaient pas dans le HTML.

**Éléments manquants:**
- `<div id="qr-settings-display">` - Affichage en lecture seule
- `<form id="qr-settings-form">` - Formulaire d'édition

---

## ✅ Corrections Appliquées

### 1. Fichier: views/parametres.php (lignes 625-724)

**Ajouté:**

**a) Wrapper pour l'affichage en lecture seule:**
```html
<div id="qr-settings-display">
    <div class="info-grid">
        <!-- Affichage QR-IBAN, IBAN Bancaire, Adresse, Localité -->
    </div>
</div>
```

**b) Formulaire d'édition (caché par défaut):**
```html
<form id="qr-settings-form" style="display: none;">
    <div class="form-grid">
        <div class="form-group">
            <label for="qr-iban">QR-IBAN *</label>
            <input type="text" id="qr-iban" name="qr_iban" ...>
            <button type="button" id="validateIBANBtn">Valider l'IBAN</button>
        </div>
        
        <div class="form-group">
            <label for="bank-iban">IBAN Bancaire</label>
            <input type="text" id="bank-iban" name="bank_iban" ...>
        </div>
    </div>
    
    <div class="form-actions">
        <button type="button" id="cancelQREditBtn">Annuler</button>
        <button type="submit">Enregistrer</button>
    </div>
</form>
```

---

### 2. Fichier: assets/js/parametres.js (lignes 926-945)

**Modifié la soumission du formulaire:**

**AVANT:**
```javascript
const formData = {
    qr_iban: document.getElementById('qr_iban').value.trim(),
    bank_iban: document.getElementById('bank_iban').value.trim(),
    address: document.getElementById('address').value.trim(),  // ❌ N'existe pas
    postal_code: document.getElementById('postal_code').value.trim(),  // ❌ N'existe pas
    city: document.getElementById('city').value.trim(),  // ❌ N'existe pas
    country: document.getElementById('country').value.trim()  // ❌ N'existe pas
};
```

**APRÈS:**
```javascript
const qrIban = document.getElementById('qr-iban');
const bankIban = document.getElementById('bank-iban');

const formData = {
    action: 'update_qr_settings',
    qr_iban: qrIban ? qrIban.value.trim() : '',
    bank_iban: bankIban ? bankIban.value.trim() : ''
};
```

---

## 🎯 Fonctionnement

### 1. Affichage Initial
- Le formulaire est **caché** (`display: none`)
- L'affichage en lecture seule est **visible**
- Bouton "Modifier" visible

### 2. Clic sur "Modifier"
```javascript
editQRSettingsBtn.addEventListener('click', function() {
    qrSettingsForm.style.display = 'block';       // Afficher formulaire
    qrSettingsDisplay.style.display = 'none';     // Cacher affichage
    editQRSettingsBtn.style.display = 'none';     // Cacher bouton "Modifier"
});
```

### 3. Clic sur "Annuler"
```javascript
cancelQREditBtn.addEventListener('click', function() {
    qrSettingsForm.style.display = 'none';        // Cacher formulaire
    qrSettingsDisplay.style.display = 'block';    // Afficher affichage
    editQRSettingsBtn.style.display = 'inline-block';  // Afficher bouton "Modifier"
});
```

### 4. Soumission du Formulaire
```javascript
qrSettingsForm.addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validation
    if(!formData.qr_iban && !formData.bank_iban) {
        alert('Veuillez saisir au moins un IBAN');
        return;
    }
    
    // Envoi via API
    fetch('api/company.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'update_qr_settings',
            qr_iban: '...',
            bank_iban: '...'
        })
    })
    .then(...)
    .then(response => {
        if(response.success) {
            alert('✅ Paramètres enregistrés!');
            location.reload();  // Recharger pour afficher nouvelles valeurs
        }
    });
});
```

---

## 📋 Champs du Formulaire

**QR-IBAN:**
- Format: CH + 19 chiffres
- Pattern de validation: `CH\d{2}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d{4}\s?\d`
- Exemple: `CH93 0076 2011 6238 5295 7`
- Positions 5-9 doivent être entre 30000-31999 (pour QR-IBAN)

**IBAN Bancaire:**
- Format: CH + 19 chiffres
- Optionnel
- Utilisé pour les paiements standards

**Bouton "Valider l'IBAN":**
- Appelle `api/qr_invoice.php` avec action `validate_iban`
- Vérifie le format
- Vérifie si c'est un vrai QR-IBAN (IID entre 30000-31999)

---

## ✅ Test de la Correction

### Étape 1: Accéder à la page
```
http://localhost/gestion_comptable/index.php?page=parametres#qr-invoice
```

### Étape 2: Cliquer sur "Modifier"
- Le formulaire devrait s'afficher ✅
- L'affichage en lecture seule devrait disparaître
- Le bouton "Modifier" devrait disparaître

### Étape 3: Remplir le formulaire
- QR-IBAN: `CH93 0076 2011 6238 5295 7`
- IBAN Bancaire: (optionnel)

### Étape 4: Valider l'IBAN (optionnel)
- Cliquer sur "Valider l'IBAN"
- Message attendu: "✅ IBAN valide! ✅ Ceci est un QR-IBAN"

### Étape 5: Enregistrer
- Cliquer sur "Enregistrer"
- Message attendu: "✅ Paramètres QR-facture enregistrés avec succès!"
- La page devrait se recharger
- Les nouvelles valeurs devraient s'afficher

### Étape 6: Annuler (test)
- Cliquer à nouveau sur "Modifier"
- Cliquer sur "Annuler"
- Le formulaire devrait disparaître
- L'affichage en lecture seule devrait réapparaître

---

## 📝 API Utilisée

**Endpoint:** `api/company.php`

**Action:** `update_qr_settings`

**Payload:**
```json
{
  "action": "update_qr_settings",
  "qr_iban": "CH93 0076 2011 6238 5295 7",
  "bank_iban": "CH10 0023 0230 9876 5432 1"
}
```

**Note:** L'API `company.php` doit gérer l'action `update_qr_settings` et mettre à jour les colonnes `qr_iban` et `bank_iban` de la table `companies`.

---

**Date:** $(date '+%Y-%m-%d %H:%M:%S')
**Statut:** ✅ CORRIGÉ
