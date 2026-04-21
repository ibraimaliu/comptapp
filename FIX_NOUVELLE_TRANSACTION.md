# ✅ Correction de l'Erreur "JSON.parse: unexpected character"

## 🐛 Problème Rencontré

**Erreur:**
```
Erreur réseau: JSON.parse: unexpected character at line 1 column 1 of the JSON data
```

Cette erreur se produit lors de la création d'une nouvelle transaction.

## 🔍 Cause du Problème

**Colonne manquante dans le modèle:**

La table `transactions` dans la base de données contient une colonne `counterpart_account_id` pour la comptabilité en partie double, mais:
- ❌ Le modèle `Transaction.php` n'avait pas la propriété `$counterpart_account_id`
- ❌ La méthode `create()` n'incluait pas cette colonne dans l'INSERT
- ❌ L'API `api/transaction.php` ne récupérait pas ce champ

Résultat: **Erreur SQL** qui était affichée avant le JSON, rendant la réponse invalide.

---

## ✅ Corrections Appliquées

### 1. Modèle Transaction.php

**Fichier:** `models/Transaction.php`

**Ligne 15:** Ajout de la propriété
```php
public $counterpart_account_id;
```

**Lignes 24-33:** Ajout dans la requête INSERT
```php
public function create() {
    $query = "INSERT INTO " . $this->table_name . "
              SET company_id = :company_id,
                  date = :date,
                  description = :description,
                  amount = :amount,
                  type = :type,
                  category = :category,
                  tva_rate = :tva_rate,
                  account_id = :account_id,
                  counterpart_account_id = :counterpart_account_id";  // ← AJOUTÉ
```

**Lignes 53-58:** Gestion de la valeur NULL
```php
// Gestion de la valeur NULL pour counterpart_account_id
if(empty($this->counterpart_account_id) || $this->counterpart_account_id === 'none') {
    $this->counterpart_account_id = null;
} else {
    $this->counterpart_account_id = htmlspecialchars(strip_tags($this->counterpart_account_id));
}
```

**Ligne 69:** Binding du paramètre
```php
$stmt->bindParam(":counterpart_account_id", $this->counterpart_account_id);
```

---

### 2. API transaction.php

**Fichier:** `api/transaction.php`

**Ligne 92:** Récupération du champ depuis les données POST
```php
$transaction->counterpart_account_id = isset($data->counterpart_account_id) ? $data->counterpart_account_id : null;
```

---

## 🧪 Test de la Correction

### Script de Test Créé

**Fichier:** `test_transaction_create.php`

**URL d'accès:**
```
http://localhost/gestion_comptable/test_transaction_create.php
```

**Ce script teste:**
1. ✅ Vérification de la session
2. ✅ Connexion à la base de données
3. ✅ Vérification de la table transactions
4. ✅ Vérification des comptes disponibles
5. ✅ Test de création via le modèle
6. ✅ Test de création via l'API (bouton AJAX)

---

## 📊 Structure de la Table transactions

```sql
Field                    Type                  Null    Default
-----------------------  --------------------  ------  ----------
id                       int(11)               NO      auto_increment
company_id               int(11)               NO      
account_id               int(11)               NO      (Compte Débit)
counterpart_account_id   int(11)               YES     (Compte Crédit)
date                     date                  NO      
description              varchar(255)          NO      
amount                   decimal(10,2)         NO      
type                     enum('income','expense') NO   
tva_rate                 decimal(5,2)          YES     0.00
created_at               timestamp             NO      CURRENT_TIMESTAMP
```

---

## 🎯 Comptabilité en Partie Double

La colonne `counterpart_account_id` est **optionnelle** mais recommandée pour la comptabilité en partie double.

**Exemple:**

**Vente de marchandise (1000 CHF):**
- `account_id` = 1020 (Banque) - **Débit**
- `counterpart_account_id` = 3200 (Ventes) - **Crédit**

**Achat de fournitures (500 CHF):**
- `account_id` = 6000 (Achats) - **Débit**
- `counterpart_account_id` = 1020 (Banque) - **Crédit**

---

## ✅ Pour Tester Maintenant

### Méthode 1: Via l'Interface

1. Se connecter à l'application
2. Aller dans **Comptabilité**
3. Cliquer **"Nouvelle Transaction"**
4. Remplir le formulaire:
   - Date
   - Description
   - Montant
   - Type (Revenu/Dépense)
   - Compte
   - (Optionnel) Compte contrepartie
5. Cliquer **"Enregistrer"**
6. ✅ Devrait fonctionner sans erreur JSON

### Méthode 2: Via le Script de Test

1. Ouvrir: `http://localhost/gestion_comptable/test_transaction_create.php`
2. Vérifier que tous les tests passent ✅
3. Cliquer sur le bouton **"🧪 Tester l'API via AJAX"**
4. Vérifier la réponse JSON dans le panneau de résultats

---

## 🔧 Si le Problème Persiste

### Vérifier les Logs Apache

```bash
tail -f /c/xampp/apache/logs/error.log
```

Rechercher des erreurs PHP qui pourraient être affichées avant le JSON.

### Vérifier la Console du Navigateur

1. Ouvrir la console (F12)
2. Aller dans l'onglet **Network**
3. Tenter de créer une transaction
4. Cliquer sur la requête vers `api/transaction.php`
5. Vérifier la réponse brute dans l'onglet **Response**

**Si la réponse commence par du texte au lieu de `{`:**
→ Il y a une erreur PHP qui s'affiche avant le JSON

**Si la réponse est du JSON valide:**
→ Le problème est résolu ✅

---

## 📝 Notes Importantes

### Champs Requis pour Créer une Transaction

**Minimum:**
- `date` - Date de la transaction (YYYY-MM-DD)
- `description` - Description
- `amount` - Montant (> 0)
- `type` - Type ('income' ou 'expense')
- `account_id` - ID du compte

**Optionnels:**
- `counterpart_account_id` - Compte contrepartie (NULL accepté)
- `category` - Catégorie ('' par défaut)
- `tva_rate` - Taux TVA (0 par défaut)

### Validation Automatique

L'API valide automatiquement:
- ✅ Montant > 0
- ✅ Type = 'income' ou 'expense'
- ✅ Tous les champs requis présents

---

**Date:** $(date '+%Y-%m-%d %H:%M:%S')
**Statut:** ✅ CORRIGÉ
