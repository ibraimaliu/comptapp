# Implémentation de la Structure Hiérarchique du Plan Comptable

**Date**: 15 novembre 2025
**Statut**: ✅ **IMPLEMENTATION TERMINÉE**

---

## Résumé

Le plan comptable a été restructuré pour supporter une hiérarchie complète conforme aux normes comptables suisses :

**Section → Groupe → Sous-groupe → Compte**

---

## 📋 Problèmes Identifiés et Résolus

### 1. Problème d'Importation Initial

**Problème**: Seulement 113 comptes sur 353 étaient importés depuis le fichier CSV.

**Causes**:
- Le fichier utilisait le séparateur `;` mais le code attendait `\t` (tabulations)
- Le fichier contenait des catégories et types non reconnus

**Solution**:
- Détection automatique du séparateur (`;`, `,`, `\t`)
- Mappings étendus pour toutes les variantes de catégories et types

---

## 🗂️ Modifications de la Base de Données

### Nouveaux champs ajoutés à `accounting_plan`

| Champ | Type | Description |
|-------|------|-------------|
| `level` | ENUM | Niveau hiérarchique: 'section', 'groupe', 'sous-groupe', 'compte' |
| `parent_id` | INT | ID du parent dans la hiérarchie |
| `is_selectable` | BOOLEAN | Indique si le compte peut être utilisé dans les transactions (seuls les "comptes" le peuvent) |
| `sort_order` | INT | Ordre de tri pour l'affichage |
| `section` | ENUM | Section suisse: 'actif', 'passif', 'produits', 'charges', 'salaires', 'charges_hors_exploitation', 'cloture' |

### Index et Contraintes

- Index sur `parent_id`, `level`, `section`, `sort_order`
- Contrainte de clé étrangère `fk_accounting_plan_parent` sur `parent_id`

---

## 📊 Structure Hiérarchique

### Détermination du Niveau

Le niveau est déterminé automatiquement selon le **nombre de chiffres** dans le numéro de compte :

```
1 chiffre  (1, 2, 3...)      → Section
2 chiffres (10, 14, 20...)   → Groupe
3 chiffres (100, 110, 200...) → Sous-groupe
4+ chiffres (1000, 1010...)  → Compte (sélectionnable)
```

### Sections Suisses

| Premier chiffre | Section |
|----------------|---------|
| 1 | Actif |
| 2 | Passif |
| 3, 4 | Produits |
| 5, 6 | Charges |
| 7 | Salaires |
| 8 | Charges hors exploitation |
| 9 | Clôture |

---

## 📈 Résultats de l'Import

### Import Réussi

✅ **353 comptes importés** sur 353 (100%)

### Répartition par Niveau

| Niveau | Nombre |
|--------|--------|
| Sections | 9 |
| Groupes | 26 |
| Sous-groupes | 74 |
| Comptes | 244 |

**Total comptes sélectionnables**: 244

### Répartition par Section

| Section | Nombre de comptes |
|---------|-------------------|
| Actif | 62 |
| Passif | 51 |
| Produits | 45 |
| Charges | 133 |
| Salaires | 5 |
| Charges hors exploitation | 39 |
| Clôture | 18 |

---

## 🔄 Fichiers Modifiés

### 1. Migration de la Base de Données

**Fichiers**:
- `/migrations/add_accounting_hierarchy.sql`
- `/apply_accounting_hierarchy_migration.php`

**Actions**: Ajout des nouveaux champs à la table `accounting_plan`

### 2. Modèle AccountingPlan

**Fichier**: `/models/AccountingPlan.php`

**Nouvelle méthode**:
```php
public function readSelectableByCompany($company_id)
```
Cette méthode retourne uniquement les comptes sélectionnables (niveau "compte"), excluant les sections/groupes/sous-groupes.

### 3. Import du Plan Comptable

**Fichier**: `/assets/ajax/accounting_plan_import.php`

**Améliorations**:
- ✅ Détection automatique du séparateur (`;`, `,`, `\t`)
- ✅ Support de multiples formats CSV, TXT, XLS, XLSX
- ✅ Détermination automatique du niveau hiérarchique
- ✅ Création automatique des liens parent-enfant
- ✅ Génération du `sort_order`
- ✅ Assignment automatique de la section

**Mappings de catégories ajoutés**:
```php
'produits' => 'produit'
'charges' => 'charge'
'charges d\'exploitation' => 'charge'
'produits d\'exploitation' => 'produit'
'charges hors exploitation' => 'charge'
'produits hors exploitation' => 'produit'
'salaires' => 'charge'
'clotûre' => 'passif'
```

**Mappings de types ajoutés**:
```php
'charges d\'exploitation' => 'resultat'
'produits d\'exploitation' => 'resultat'
'charges hors exploitation' => 'resultat'
'produits hors exploitation' => 'resultat'
'salaire' => 'resultat'
'clotûre' => 'resultat'
```

### 4. Formulaire de Transaction

**Fichier**: `/views/transaction_create.php`

**Modification**:
```php
// AVANT
$accounts_stmt = $accountingPlan->readByCompany($company_id);

// APRÈS
$accounts_stmt = $accountingPlan->readSelectableByCompany($company_id);
```

**Impact**: Les dropdowns de sélection de compte (débit/crédit) n'affichent plus que les comptes sélectionnables, pas les sections/groupes/sous-groupes.

---

## 🎯 Fonctionnalités Implémentées

### ✅ Import avec Hiérarchie

- Import CSV/TXT/XLS/XLSX avec détection automatique du séparateur
- Création automatique de la structure hiérarchique
- Gestion des relations parent-enfant
- Support de toutes les variations de catégories et types

### ✅ Filtrage des Comptes Sélectionnables

- Seuls les comptes de niveau "compte" (4+ chiffres) peuvent être utilisés dans les transactions
- Les sections, groupes et sous-groupes servent uniquement à l'organisation et à l'affichage

### ✅ Sections Comptables Suisses

- 7 sections conformes au droit comptable suisse
- Classification automatique selon le numéro de compte

---

## 📝 Tâches Restantes

### ⏳ Vue Bilan avec Hiérarchie

**Statut**: En cours

**Objectif**: Modifier la page `/views/bilan.php` pour afficher la hiérarchie complète:

```
Section Actif
  │
  ├─ Groupe: Actif circulant
  │  │
  │  ├─ Sous-groupe: Trésorerie
  │  │  │
  │  │  ├─ 1000 - Caisse: 5'000.00 CHF
  │  │  ├─ 1010 - Compte postal: 12'500.00 CHF
  │  │  └─ 1020 - Banque: 45'000.00 CHF
  │  │     ─────────────────────────────
  │  │     Sous-total Trésorerie: 62'500.00 CHF
  │  │
  │  └─ Sous-groupe: Créances
  │        ...
  │     ─────────────────────────────
  │     Sous-total Actif circulant: XXX CHF
  │
  └─ Groupe: Actif immobilisé
        ...
     ─────────────────────────────
     TOTAL ACTIF: XXX CHF
```

**Fonctionnalités à implémenter**:
- Affichage récursif de la hiérarchie
- Sous-totaux par sous-groupe
- Sous-totaux par groupe
- Total par section
- Indentation visuelle

---

## 🧪 Scripts de Test

### Test Direct d'Import

**Fichier**: `/test_direct_import.php`

**Usage**:
```bash
php test_direct_import.php
```

**Fonction**: Importer directement le fichier `Plan comptable.csv` avec affichage détaillé des statistiques.

### Vérification de la Structure

**Fichier**: `/check_accounting_plan_structure.php`

**Usage**:
```bash
php check_accounting_plan_structure.php
```

**Fonction**: Afficher la structure de la table et les statistiques par société.

---

## 📚 Documentation Associée

- `GUIDE_RAPPORTS_COMPTABLES.md` - Guide des rapports comptables
- `CLAUDE.md` - Documentation du projet

---

## 🔍 Exemple de Hiérarchie Créée

```
[✗] 1        Actif                                      section         actif
  [✗] 10       Actif circulant                           groupe          actif [parent: 1]
    [✗] 100      Trésorerie et actifs cotés...            sous-groupe     actif [parent: 10]
      [✓] 1000     Caisse                                 compte          actif [parent: 100]
      [✓] 1010     Compte postal                          compte          actif [parent: 100]
      [✓] 1020     Banque                                 compte          actif [parent: 100]
```

**Légende**:
- `[✓]` = Compte sélectionnable (peut être utilisé dans les transactions)
- `[✗]` = Non sélectionnable (affichage uniquement)

---

## ✅ Validation

### Tests Effectués

1. ✅ Import complet des 353 comptes
2. ✅ Hiérarchie correctement créée (parent_id assignés)
3. ✅ Sections automatiquement assignées
4. ✅ Comptes sélectionnables correctement identifiés (is_selectable = 1)
5. ✅ Formulaires de transaction filtrent correctement les comptes

### Prochaines Étapes

1. ⏳ Mettre à jour la vue Bilan pour afficher la hiérarchie
2. ⏳ Mettre à jour la vue Compte de Résultat (si nécessaire)
3. ⏳ Ajouter une vue d'arborescence du plan comptable dans Paramètres

---

## 💡 Notes Techniques

### Performance

- Index créés sur tous les champs critiques (parent_id, level, section, sort_order)
- Requêtes optimisées avec `WHERE is_selectable = 1` pour les formulaires
- Contrainte de clé étrangère assure l'intégrité référentielle

### Compatibilité

- Compatible avec les plans comptables suisses standard (PME/KMU)
- Support des formats CSV, TXT, XLS, XLSX
- Encodage UTF-8 avec BOM pour compatibilité Excel

---

**Auteur**: Claude Code
**Version**: 1.0
**Dernière mise à jour**: 2025-11-15
