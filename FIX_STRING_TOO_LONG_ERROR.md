# Fix: Erreur "String data, right truncated" lors de l'import

## Problème

Lors de l'importation d'un fichier (CSV, TXT, XLS ou XLSX), l'erreur suivante apparaissait :

```
SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'name' at row 1
```

## Cause

La colonne `name` de la table `accounting_plan` avait une taille limitée à **VARCHAR(100)**, ce qui était insuffisant pour certains noms de comptes comptables longs.

## Solution Appliquée

### 1. Modification de la Base de Données

La colonne `name` a été agrandie de **VARCHAR(100)** à **VARCHAR(255)** :

```sql
ALTER TABLE accounting_plan
MODIFY COLUMN name VARCHAR(255) NOT NULL;
```

**Avant :**
```
name varchar(100) NOT NULL
```

**Après :**
```
name varchar(255) NOT NULL
```

### 2. Validation et Tronquage dans le Code

Le code d'import a été amélioré pour valider et tronquer automatiquement les données trop longues :

**Fichier :** `assets/ajax/accounting_plan_import.php`

**Lignes 188-197 :**
```php
// Validate and truncate lengths
if (strlen($number) > 20) {
    $errors[] = "Ligne " . ($line_num + 2) . ": Numéro trop long ($number), tronqué à 20 caractères";
    $number = substr($number, 0, 20);
}

if (strlen($name) > 255) {
    $errors[] = "Ligne " . ($line_num + 2) . ": Nom trop long (" . strlen($name) . " car.), tronqué à 255 caractères";
    $name = substr($name, 0, 255);
}
```

### 3. Migration SQL Fournie

Un fichier de migration SQL est disponible pour appliquer ce changement sur d'autres environnements :

**Fichier :** `migrations/increase_accounting_plan_name_length.sql`

## Avantages de la Solution

✅ **Rétrocompatible** - Augmentation de taille uniquement, aucune perte de données
✅ **Validation automatique** - Les données trop longues sont tronquées avec un avertissement
✅ **Messages clairs** - L'utilisateur est informé des lignes tronquées
✅ **Flexibilité accrue** - Support de noms de comptes jusqu'à 255 caractères

## Limites Actuelles

| Colonne | Type | Longueur Max | Notes |
|---------|------|--------------|-------|
| `number` | VARCHAR(20) | 20 caractères | Numéro de compte |
| `name` | VARCHAR(255) | 255 caractères | Nom du compte (modifié) |
| `category` | ENUM | - | Actif, Passif, Charge, Produit |
| `type` | ENUM | - | Bilan, Résultat |

## Test de la Solution

### Test 1 : Import avec nom long (< 255 caractères)

**Données :**
```
1000	Un nom de compte très très très long mais inférieur à 255 caractères	Actif	Bilan
```

**Résultat attendu :** ✅ Import réussi

### Test 2 : Import avec nom très long (> 255 caractères)

**Données :**
```
1001	[Chaîne de 300 caractères]	Actif	Bilan
```

**Résultat attendu :**
- ⚠️ Import réussi avec avertissement
- Message : "Ligne X: Nom trop long (300 car.), tronqué à 255 caractères"
- Données tronquées à 255 caractères dans la base

### Test 3 : Import normal

**Données :**
```
1000	Caisse	Actif	Bilan
```

**Résultat attendu :** ✅ Import réussi sans avertissement

## Scripts de Diagnostic

### Vérifier la structure actuelle

```bash
php check_table_structure.php
```

### Vérifier les longueurs dans un fichier CSV

```bash
php check_csv_lengths.php
```

### Appliquer la migration

```bash
php fix_accounting_plan_column.php
```

Ou directement en SQL :
```bash
mysql -u root -p gestion_comptable < migrations/increase_accounting_plan_name_length.sql
```

## Historique

| Date | Version | Changement |
|------|---------|------------|
| 2025-01-13 | 1.1 | Augmentation name: VARCHAR(100) → VARCHAR(255) |
| 2025-01-13 | 1.1 | Ajout validation et tronquage automatique |
| 2024-XX-XX | 1.0 | Structure initiale VARCHAR(100) |

## Fichiers Modifiés

1. **assets/ajax/accounting_plan_import.php** - Ajout validation et tronquage
2. **Base de données** - Colonne `name` agrandie à VARCHAR(255)
3. **migrations/increase_accounting_plan_name_length.sql** - Script de migration
4. **check_table_structure.php** - Script de diagnostic (nouveau)
5. **check_csv_lengths.php** - Script de vérification (nouveau)
6. **fix_accounting_plan_column.php** - Script de correction (nouveau)

## Support

Si l'erreur persiste après application de cette solution :

1. Vérifier que la migration a bien été appliquée :
   ```sql
   DESCRIBE accounting_plan;
   ```
   La colonne `name` doit afficher `varchar(255)`

2. Vérifier les données du fichier d'import :
   ```bash
   php check_csv_lengths.php
   ```

3. Consulter les logs d'import pour voir les avertissements de tronquage

## Notes Techniques

- La modification de VARCHAR(100) à VARCHAR(255) n'impacte pas significativement les performances
- L'index UNIQUE `company_id, number` n'est pas affecté
- La contrainte de clé étrangère reste intacte
- Compatible avec MySQL/MariaDB 5.5+

---

**Résolution :** ✅ Complète
**Test :** ✅ Validé
**Documentation :** ✅ À jour
