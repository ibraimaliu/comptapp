# Import Multi-Format pour Plan Comptable

## Vue d'ensemble

Le système d'import du plan comptable supporte maintenant **4 formats de fichiers** :

1. **CSV** (séparateur : tabulation)
2. **TXT** (séparateur : tabulation)
3. **XLS** (Excel ancien format) ⚠️ Nécessite extension PHP ZIP
4. **XLSX** (Excel nouveau format) ⚠️ Nécessite extension PHP ZIP

## Formats Acceptés

### Structure des Données

Tous les formats doivent respecter la structure suivante :

| Colonne | Nom | Description | Valeurs acceptées |
|---------|-----|-------------|-------------------|
| A / 1 | Numéro | Numéro du compte | Ex: 1000, 1020, 3000 |
| B / 2 | Intitulé | Nom du compte | Ex: Caisse, Banque |
| C / 3 | Catégorie | Catégorie comptable | Actif, Passif, Charge, Produit |
| D / 4 | Type | Type de compte | Bilan, Résultat |

### 1. Format CSV (Recommandé)

**Extension:** `.csv`
**Séparateur:** Tabulation (`\t`)
**Encodage:** UTF-8 avec BOM

**Exemple:**
```
Numéro	Intitulé	Catégorie	Type
1000	Caisse	Actif	Bilan
1020	Banque	Actif	Bilan
3000	Ventes de marchandises	Produit	Résultat
```

**Création depuis Excel:**
1. Ouvrir le fichier Excel
2. Fichier → Enregistrer sous
3. Choisir "Texte (délimité par des tabulations) (*.txt)"
4. Renommer l'extension `.txt` en `.csv`

### 2. Format TXT

**Extension:** `.txt`
**Séparateur:** Tabulation (`\t`)
**Encodage:** UTF-8

Identique au format CSV, juste avec l'extension `.txt`.

### 3. Format Excel XLS/XLSX

**Extensions:** `.xls`, `.xlsx`
**Structure:** Colonnes A, B, C, D dans la première feuille
**En-tête:** Première ligne = noms des colonnes

⚠️ **Prérequis:** Extension PHP `zip` doit être activée

**Exemple de structure Excel:**

| A | B | C | D |
|---|---|---|---|
| Numéro | Intitulé | Catégorie | Type |
| 1000 | Caisse | Actif | Bilan |
| 1020 | Banque | Actif | Bilan |

## Fichiers Exemples Fournis

Le système fournit 3 fichiers exemples :

1. **plan_comptable_exemple.csv** - Format CSV avec tabulations
2. **plan_comptable_exemple.txt** - Format TXT avec tabulations
3. **plan_comptable_exemple.xlsx** - Format Excel avec mise en forme

Téléchargez-les depuis le modal d'import.

## Utilisation

### Interface Web

1. Accéder à **Paramètres** → Onglet **Plan comptable**
2. Cliquer sur **Importer CSV/Excel**
3. Sélectionner le fichier (CSV, TXT, XLS ou XLSX)
4. Choisir l'action :
   - **Remplacer** : Supprime les comptes non utilisés et importe les nouveaux
   - **Ajouter** : Conserve les comptes existants et ajoute les nouveaux
5. Cliquer sur **Importer**

### Validation

Le système valide automatiquement :

✅ Extension du fichier (csv, txt, xls, xlsx)
✅ Taille du fichier (max 10 MB)
✅ Structure du fichier (4 colonnes minimum)
✅ Catégories valides (Actif, Passif, Charge, Produit)
✅ Types valides (Bilan, Résultat)
✅ Numéro et nom non vides

### Messages d'Erreur

| Erreur | Solution |
|--------|----------|
| "Extension PHP ZIP manquante" | Utiliser CSV ou TXT, ou activer l'extension ZIP dans php.ini |
| "Format invalide" | Vérifier que le fichier a 4 colonnes avec en-tête |
| "Catégorie invalide" | Utiliser: Actif, Passif, Charge ou Produit |
| "Type invalide" | Utiliser: Bilan ou Résultat |
| "Compte existe déjà" | En mode "Ajouter", les doublons sont ignorés |

## Normalisation Automatique

Le système normalise automatiquement :

### Catégories
- `Asset` → `Actif`
- `Liability` → `Passif`
- `Expense` → `Charge`
- `Revenue`/`Income` → `Produit`

### Types
- `Balance Sheet` → `Bilan`
- `Income Statement`/`P&L` → `Résultat`

### En-têtes de colonnes
- Détection automatique de: `Numéro`/`Numero`/`Number`
- Détection automatique de: `Intitulé`/`Intitule`/`Name`
- Détection automatique de: `Catégorie`/`Categorie`/`Category`
- Non sensible à la casse et aux accents

## Backend Technique

### Fichier Principal
`assets/ajax/accounting_plan_import.php`

### Bibliothèques Utilisées
- **PhpSpreadsheet** (v1.29+) pour lire les fichiers Excel
- **PDO** pour les transactions de base de données
- **fgetcsv()** natif PHP pour CSV/TXT

### Architecture

```php
// Détection automatique du format
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if ($ext === 'xls' || $ext === 'xlsx') {
    // Lecture via PhpSpreadsheet
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    $data_rows = $spreadsheet->getActiveSheet()->toArray();
} else {
    // Lecture CSV/TXT avec tabulation
    while (($row = fgetcsv($handle, 1000, "\t")) !== false) {
        $data_rows[] = $row;
    }
}
```

### Gestion des Transactions

L'import utilise des transactions PDO pour garantir l'intégrité :

```php
$db->beginTransaction();
try {
    // Import des comptes
    foreach ($data_rows as $row) {
        // INSERT INTO accounting_plan...
    }
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
}
```

## Configuration PHP Requise

### Pour CSV/TXT (Toujours disponible)
- Aucune extension spéciale requise
- PHP 7.4+

### Pour XLS/XLSX (Optionnel)
- PHP extension `zip` activée
- PHP extension `xml` activée
- PHP extension `gd` activée (pour génération Excel)

### Activer l'extension ZIP

**Dans `php.ini` (C:\xampp\php\php.ini) :**

```ini
; Décommenter ces lignes (retirer le ;)
extension=zip
extension=xml
extension=gd
```

Puis redémarrer Apache.

## Export

L'export génère toujours un fichier **CSV avec tabulations** :

- Format: UTF-8 avec BOM
- Séparateur: Tabulation
- Compatible Excel
- Nom: `plan_comptable_YYYY-MM-DD_HH-MM-SS.csv`

## Limites

- **Taille maximale:** 10 MB par fichier
- **Lignes vides:** Ignorées automatiquement
- **Comptes utilisés:** Ne peuvent pas être supprimés (mode "Remplacer")
- **Feuilles multiples:** Seule la première feuille Excel est lue

## Compatibilité

✅ **Excel 2007+** (xlsx)
✅ **Excel 97-2003** (xls) ⚠️ Nécessite extension ZIP
✅ **LibreOffice Calc** (export en CSV/TXT avec tabulation)
✅ **Google Sheets** (télécharger en CSV ou XLSX)
✅ **Numbers (macOS)** (export en CSV ou Excel)

## Dépannage

### Problème: "Extension PHP ZIP manquante"

**Solution 1:** Utiliser le format CSV ou TXT (recommandé)

**Solution 2:** Activer l'extension ZIP
1. Ouvrir `C:\xampp\php\php.ini`
2. Trouver `;extension=zip`
3. Retirer le `;` pour obtenir `extension=zip`
4. Redémarrer Apache
5. Vérifier avec `php -m | findstr zip`

### Problème: "Le fichier est vide"

Vérifier que :
- Le fichier contient au moins 2 lignes (en-tête + données)
- Les colonnes sont bien séparées par des tabulations (pas des espaces)
- L'encodage est UTF-8

### Problème: "Colonnes insuffisantes"

Vérifier que chaque ligne contient exactement 4 colonnes séparées par des tabulations.

## Historique des Versions

### Version 1.1 (Actuelle)
- ✅ Support multi-format : CSV, TXT, XLS, XLSX
- ✅ Détection automatique du format
- ✅ Validation améliorée
- ✅ Messages d'erreur détaillés
- ✅ Fichiers exemples multiples
- ✅ Gestion gracieuse de l'absence de ZIP

### Version 1.0
- Support CSV uniquement
- Séparateur : tabulation

---

**Date de mise à jour:** 2025-01-13
**Auteur:** Claude Code
