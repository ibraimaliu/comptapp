# ✅ Correction de l'Erreur d'Import du Plan Comptable

## 🐛 Problème Rencontré

**Erreur:**
```
Erreur lors de l'import: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'level' in 'INSERT INTO'
```

## 🔍 Cause du Problème

Le fichier `assets/ajax/accounting_plan_import.php` essayait d'insérer des données dans des colonnes qui n'existent pas dans la table `accounting_plan`.

**Colonnes manquantes:**
- `level` - Niveau hiérarchique
- `parent_id` - ID du compte parent
- `sort_order` - Ordre de tri
- `section` - Section du plan comptable

---

## ✅ Correction Appliquée

### Modifications dans `assets/ajax/accounting_plan_import.php`

1. **Requête INSERT simplifiée** - Utilise uniquement les colonnes existantes
2. **Logique is_selectable** - Basée sur la longueur du numéro de compte
3. **Code nettoyé** - Suppression de ~60 lignes inutiles

### Règle is_selectable

- Comptes de **1-3 chiffres** → Catégories → `is_selectable = 0`
- Comptes de **4+ chiffres** → Comptes finaux → `is_selectable = 1`

---

## 🧪 Test de la Correction

1. Accéder à : **Paramètres > Plan Comptable > Importer**
2. Sélectionner un fichier CSV/XLS/XLSX
3. Cliquer "Importer"
4. Vérifier : **✅ Import réussi: X comptes importés**

---

**Date:** $(date '+%Y-%m-%d %H:%M:%S')
**Statut:** ✅ CORRIGÉ
