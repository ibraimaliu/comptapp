# ✅ Correction de l'Erreur "Data truncated for column 'type'"

## 🐛 Problème Rencontré

**Erreur:**
```
SQLSTATE[01000]: Warning: 1265 Data truncated for column 'type' at row 1
```

## 🔍 Cause du Problème

**Confusion entre deux concepts:**

1. **Dans le CSV:**
   - **Colonne "Catégorie"** = Actif/Passif/Charge/Produit
   - **Colonne "Type"** = Bilan/Résultat

2. **Dans la base de données:**
   - **Colonne `category`** = VARCHAR(50) - sous-catégorie descriptive (ex: "Trésorerie", "Créances")
   - **Colonne `type`** = ENUM('actif','passif','charge','produit') - type comptable principal

**Le problème:** Le code essayait d'insérer "bilan" ou "resultat" dans la colonne `type` qui attend uniquement 'actif', 'passif', 'charge', ou 'produit'.

---

## ✅ Correction Appliquée

### Mapping Correct

**CSV → Base de Données:**
- Colonne CSV "Catégorie" (Actif/Passif/Charge/Produit) → Colonne DB `type` (ENUM)
- Colonne CSV "Type" (Bilan/Résultat) → **IGNORÉ** (non nécessaire)

### Code Modifié

**Fichier:** `assets/ajax/accounting_plan_import.php`

**Lignes 241-248:**
```php
// Mapping entre catégorie CSV et type DB
// La colonne 'type' dans la DB est un ENUM('actif','passif','charge','produit')
// La colonne 'category' dans la DB est VARCHAR et peut contenir une sous-catégorie
$type = $category; // actif/passif/charge/produit

// Pour category, on utilise la même valeur normalisée
$category_db = $category;
```

**Ligne 281:**
```php
$insert_stmt->bindParam(':category', $category_db);  // Était $category
```

---

## 🧪 Test de la Correction

### Format CSV Attendu

**En-têtes (4 colonnes minimum):**
```csv
Numéro	Intitulé	Catégorie	Type
```

**Exemples de lignes:**
```csv
1000	Terrains	Actif	Bilan
2000	Capital social	Passif	Bilan
3200	Ventes	Produit	Résultat
6000	Achats	Charge	Résultat
```

### Normalisation Automatique

Le système accepte plusieurs variantes (insensible à la casse):

**Catégories acceptées:**
- **Actif:** actif, asset, Actif, ACTIF
- **Passif:** passif, liability, Passif, PASSIF
- **Charge:** charge, expense, charges, Charge
- **Produit:** produit, revenue, income, produits, Produit

**Exemples:**
```csv
1000	Caisse	ACTIF	Bilan          → type = 'actif' ✅
2000	Capital	Passif	Bilan        → type = 'passif' ✅
3200	Ventes	Revenue	Résultat     → type = 'produit' ✅
6000	Achats	Expense	Income       → type = 'charge' ✅
```

---

## 📊 Structure de la Table accounting_plan

```sql
CREATE TABLE accounting_plan (
    id INT AUTO_INCREMENT PRIMARY KEY,
    company_id INT NOT NULL,
    number VARCHAR(20) NOT NULL,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(50) NOT NULL,           -- Sous-catégorie descriptive
    type ENUM('actif','passif','charge','produit') NOT NULL,  -- Type principal
    is_used TINYINT(1) DEFAULT 0,
    is_selectable TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Exemples de Données

```
number  name                    category      type
1000    Caisse                  Trésorerie    actif
1010    Poste                   Trésorerie    actif
1020    Banque                  Trésorerie    actif
1100    Créances clients        Créances      actif
2000    Capital social          Capitaux      passif
3200    Ventes marchandises     Ventes        produit
6000    Achats marchandises     Achats        charge
```

---

## ✅ Résultat

**L'import fonctionne maintenant correctement !**

✅ Mapping correct CSV → DB
✅ Type ENUM respecté
✅ Normalisation automatique
✅ Support multi-formats (CSV, TXT, XLS, XLSX)

---

**Date:** $(date '+%Y-%m-%d %H:%M:%S')
**Statut:** ✅ CORRIGÉ
