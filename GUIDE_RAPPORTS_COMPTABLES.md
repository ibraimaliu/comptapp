# Guide - Rapports Comptables

**Version**: 1.0
**Date**: 2025-01-15

---

## Table des Matières

1. [Vue d'ensemble](#vue-densemble)
2. [Accès aux rapports](#accès-aux-rapports)
3. [Relevé de Compte](#relevé-de-compte)
4. [Bilan Comptable](#bilan-comptable)
5. [Compte de Résultat (PP)](#compte-de-résultat-pp)
6. [Export et Impression](#export-et-impression)

---

## Vue d'ensemble

Le module **Rapports Comptables** permet d'extraire différentes informations comptables essentielles pour le suivi et l'analyse de votre activité. Ces rapports sont similaires à ceux disponibles dans WinBiz.

### Rapports disponibles

1. **Relevé de Compte** - Détail des mouvements d'un compte spécifique
2. **Bilan** - Situation patrimoniale (Actif/Passif)
3. **Compte de Résultat (PP)** - Pertes et Profits (Produits/Charges)

---

## Accès aux rapports

### Navigation

Les rapports comptables sont accessibles depuis le menu **Comptabilité** qui dispose maintenant d'un sous-menu :

```
Comptabilité
  ├── Toutes les Transactions
  ├── Nouvelle Transaction
  ├── Relevé de Compte
  ├── Bilan
  └── Compte de Résultat (PP)
```

### URLs directes

- **Toutes les transactions** : `index.php?page=comptabilite`
- **Nouvelle transaction** : `index.php?page=transaction_create`
- **Relevé de compte** : `index.php?page=releve_compte`
- **Bilan** : `index.php?page=bilan`
- **Compte de Résultat** : `index.php?page=compte_resultat`

---

## Relevé de Compte

### Description

Le **Relevé de Compte** affiche tous les mouvements comptables d'un compte spécifique sur une période donnée, en mode débit/crédit avec calcul du solde progressif.

### Fonctionnalités

✅ **Sélection du compte** - Choisir parmi tous les comptes du plan comptable
✅ **Filtrage par période** - Définir date de début et date de fin
✅ **Solde initial** - Calculé automatiquement à partir des transactions antérieures
✅ **Détail des mouvements** - Affichage de chaque transaction avec :
  - Date
  - Description
  - Compte de contrepartie
  - Montant au débit
  - Montant au crédit
  - Solde progressif après chaque mouvement
✅ **Totaux** - Total débit, total crédit, solde final
✅ **Export** - Impression PDF (Ctrl+P)

### Utilisation

1. **Sélectionner un compte**
   - Utilisez la liste déroulante pour choisir le compte à consulter
   - Format : `Numéro - Nom du compte`

2. **Définir la période**
   - **Date début** : Par défaut 1er janvier de l'année en cours
   - **Date fin** : Par défaut date du jour

3. **Afficher le relevé**
   - Cliquez sur "Afficher"
   - Le système calcule automatiquement :
     - Solde initial (transactions avant la date de début)
     - Détail des mouvements sur la période
     - Solde final

### Cartes de synthèse

4 cartes affichent les informations clés :

| Carte | Description | Couleur |
|-------|-------------|---------|
| **Solde Initial** | Solde au début de la période | Bleu |
| **Total Débit** | Somme de tous les débits | Rouge |
| **Total Crédit** | Somme de tous les crédits | Vert |
| **Solde Final** | Solde à la fin de la période | Violet |

### Interprétation

**Principe de la partie double** :
- Si le compte sélectionné est **au débit** dans la transaction → affichage dans la colonne "Débit"
- Si le compte sélectionné est **au crédit** dans la transaction → affichage dans la colonne "Crédit"

**Calcul du solde** :
```
Solde = Solde précédent + Débit - Crédit
```

### Exemple pratique

**Relevé du compte 1020 - Banque UBS** (01/01/2025 au 31/01/2025)

| Date | Description | Compte contrepartie | Débit | Crédit | Solde |
|------|-------------|---------------------|-------|--------|-------|
| - | Solde initial | - | - | - | 5 000.00 |
| 05/01 | Vente service | 3200 - Prestations | 1 500.00 | - | 6 500.00 |
| 10/01 | Loyer | 6300 - Charges locatives | - | 800.00 | 5 700.00 |
| 15/01 | Facture client | 3200 - Prestations | 2 000.00 | - | 7 700.00 |
| **TOTAUX** | | | **3 500.00** | **800.00** | **7 700.00** |

---

## Bilan Comptable

### Description

Le **Bilan** présente la situation patrimoniale de l'entreprise à une date donnée, en opposant l'**Actif** (ce que possède l'entreprise) au **Passif** (ce que doit l'entreprise).

### Fonctionnalités

✅ **Date de clôture** - Situation à une date précise
✅ **Structure Actif/Passif** - Présentation en deux colonnes
✅ **Classification automatique** :
  - Actif circulant (comptes 1000-1499)
  - Actif immobilisé (comptes 1500+)
  - Capitaux propres (comptes 2000-2799)
  - Dettes (comptes 2800+)
✅ **Sous-totaux** - Par catégorie
✅ **Vérification d'équilibre** - Contrôle Actif = Passif
✅ **Export** - Impression PDF

### Structure du Bilan

```
ACTIF                                  PASSIF
──────────────────────                 ──────────────────────
Actif circulant                        Capitaux propres
  1000 Caisse            5 000           2000 Capital          50 000
  1020 Banque           15 000           2100 Réserves         10 000
  1100 Clients           8 000
  ────────────────────                  ────────────────────
  Total AC              28 000           Total CP              60 000

Actif immobilisé                       Dettes
  1500 Mobilier          5 000           2800 Fournisseurs      3 000
  1600 Matériel         30 000           2900 Dettes court T.   2 000
  ────────────────────                  ────────────────────
  Total AI              35 000           Total Dettes           5 000

═══════════════════                    ═══════════════════
TOTAL ACTIF            63 000           TOTAL PASSIF          65 000
```

### Carte Équilibre

Une carte en bas du bilan indique :
- ✅ **Équilibré** - Si Actif = Passif (différence < 0,01 CHF)
- ❌ **Déséquilibré** - Si Actif ≠ Passif (avec affichage de la différence)

**Note** : En comptabilité correcte, le bilan doit toujours être équilibré. Une différence indique généralement :
- Transactions non enregistrées en partie double
- Compte de contrepartie manquant
- Erreur de saisie

### Utilisation

1. **Sélectionner la date de clôture**
   - Par défaut : date du jour
   - Vous pouvez choisir n'importe quelle date (fin de mois, fin d'année, etc.)

2. **Cliquer sur "Calculer le bilan"**
   - Le système analyse toutes les transactions jusqu'à cette date
   - Seuls les comptes de type "Bilan" sont pris en compte

3. **Analyser les résultats**
   - Vérifier l'équilibre du bilan
   - Consulter les sous-totaux par catégorie
   - Identifier les comptes avec soldes importants

### Interprétation

**Actif circulant** : Éléments destinés à être consommés/vendus à court terme
- Trésorerie (Caisse, Banque)
- Créances clients
- Stocks

**Actif immobilisé** : Biens durables pour l'exploitation
- Immobilisations corporelles (Mobilier, Matériel)
- Immobilisations incorporelles (Logiciels, Brevets)

**Capitaux propres** : Ressources appartenant aux propriétaires
- Capital social
- Réserves
- Résultat de l'exercice

**Dettes** : Obligations envers les tiers
- Fournisseurs
- Emprunts bancaires
- Dettes fiscales et sociales

---

## Compte de Résultat (PP)

### Description

Le **Compte de Résultat** (ou Pertes et Profits) présente l'ensemble des **Produits** et des **Charges** sur une période, permettant de calculer le **résultat net** (bénéfice ou perte).

### Fonctionnalités

✅ **Période personnalisable** - Définir date début et date fin
✅ **Structure Produits/Charges** - Présentation en deux colonnes
✅ **Statistiques rapides** - 3 cartes en haut de page :
  - Total Produits
  - Total Charges
  - Résultat (Bénéfice ou Perte)
✅ **Détail par compte** - Liste de tous les comptes avec solde
✅ **Calcul automatique** - Résultat = Produits - Charges
✅ **Visualisation** - Code couleur selon bénéfice (vert) ou perte (rouge)
✅ **Export** - Impression PDF

### Structure du Compte de Résultat

```
PRODUITS                               CHARGES
──────────────────────                 ──────────────────────
3000 Ventes marchandises  50 000       4000 Achats            20 000
3200 Prestations services 30 000       6000 Achats matières    5 000
3400 Commissions           5 000       6100 Loyers             9 600
                                       6200 Entretien          2 000
                                       6300 Salaires          25 000
                                       6400 Charges sociales   7 500
                                       6500 Assurances         1 500
                                       6600 Publicité          3 000

═══════════════════                    ═══════════════════
TOTAL PRODUITS         85 000          TOTAL CHARGES        73 600

─────────────────────────────────────────────────────────
RÉSULTAT NET : BÉNÉFICE               11 400 CHF
```

### Cartes de Synthèse

3 cartes affichent les métriques clés :

| Carte | Indicateur | Icône |
|-------|------------|-------|
| **Total Produits** | Somme de tous les comptes de produits | 📈 Vert |
| **Total Charges** | Somme de tous les comptes de charges | 📉 Rouge |
| **Résultat** | Bénéfice (vert) ou Perte (rouge) | 🏆 ou ⚠️ |

### Résultat Final

Une section dédiée affiche :
- **Détail du calcul** : Produits (+) - Charges (-)
- **Résultat net** : En grand avec code couleur
  - **Vert** si bénéfice (Produits > Charges)
  - **Rouge** si perte (Produits < Charges)

### Utilisation

1. **Définir la période d'analyse**
   - **Date début** : Par défaut 1er janvier de l'année en cours
   - **Date fin** : Par défaut date du jour
   - Exemples courants :
     - Mois : 01/01/2025 → 31/01/2025
     - Trimestre : 01/01/2025 → 31/03/2025
     - Année : 01/01/2025 → 31/12/2025

2. **Cliquer sur "Calculer le résultat"**
   - Le système analyse toutes les transactions de la période
   - Seuls les comptes de type "Résultat" sont pris en compte

3. **Analyser la performance**
   - Comparer produits vs charges
   - Identifier les postes de charges importants
   - Vérifier la rentabilité sur la période

### Interprétation

**Bénéfice** :
```
Produits > Charges → Résultat positif
L'entreprise est rentable sur la période
```

**Perte** :
```
Produits < Charges → Résultat négatif
L'entreprise n'est pas rentable sur la période
```

**Analyse** :
- **Ratio de rentabilité** : `Résultat / Produits × 100`
- **Marge brute** : `Produits - Achats directs`
- **Charges d'exploitation** : Loyers + Salaires + Charges sociales + Divers

### Exemple pratique

**Compte de Résultat - Janvier 2025**

**Produits :**
- Ventes : 50 000 CHF
- Services : 30 000 CHF
- **Total : 80 000 CHF**

**Charges :**
- Achats : 25 000 CHF
- Loyers : 800 CHF/mois = 800 CHF
- Salaires : 25 000 CHF
- Autres : 5 000 CHF
- **Total : 55 800 CHF**

**Résultat : +24 200 CHF (Bénéfice)**

**Ratio de rentabilité : 24 200 / 80 000 = 30,25%** ✅ Excellent

---

## Export et Impression

### Impression PDF

Tous les rapports sont optimisés pour l'impression :

1. **Ouvrir le rapport** souhaité
2. Utiliser **Ctrl+P** (Windows) ou **Cmd+P** (Mac)
3. Sélectionner l'imprimante ou "Enregistrer au format PDF"
4. Ajuster les paramètres si nécessaire
5. Imprimer ou enregistrer

### Optimisations d'impression

Les rapports sont automatiquement formatés pour l'impression :
- ✅ Filtres masqués
- ✅ Boutons masqués
- ✅ Couleurs conservées (backgrounds, bordures)
- ✅ Mise en page A4
- ✅ Marges adaptées
- ✅ Sauts de page intelligents

### Format d'export futur

**Prochaines évolutions** :
- [ ] Export Excel (XLSX)
- [ ] Export CSV
- [ ] Envoi par email
- [ ] Génération PDF serveur (mPDF)
- [ ] Graphiques intégrés

---

## Cas d'Usage

### 1. Contrôle mensuel

**Objectif** : Vérifier les finances chaque fin de mois

**Actions** :
1. Générer le **Compte de Résultat** pour le mois écoulé
2. Vérifier que le résultat est positif
3. Analyser les charges importantes
4. Générer le **Bilan** au dernier jour du mois
5. Vérifier l'équilibre et l'évolution de la trésorerie

### 2. Préparation clôture annuelle

**Objectif** : Préparer le bilan de fin d'année

**Actions** :
1. Générer le **Bilan** au 31/12/YYYY
2. Vérifier l'équilibre (Actif = Passif)
3. Générer le **Compte de Résultat** du 01/01/YYYY au 31/12/YYYY
4. Calculer le bénéfice/perte de l'exercice
5. Exporter les rapports en PDF pour l'expert-comptable

### 3. Suivi d'un compte spécifique

**Objectif** : Contrôler les mouvements d'un compte bancaire

**Actions** :
1. Ouvrir le **Relevé de Compte**
2. Sélectionner le compte "1020 - Banque UBS"
3. Définir la période (par ex. dernier mois)
4. Vérifier toutes les transactions
5. Comparer le solde final avec le relevé bancaire réel

### 4. Analyse de rentabilité

**Objectif** : Évaluer la performance trimestrielle

**Actions** :
1. Générer le **Compte de Résultat** pour le trimestre (3 mois)
2. Noter le résultat net
3. Comparer avec les trimestres précédents
4. Identifier les tendances (hausse/baisse des produits ou charges)
5. Prendre des décisions stratégiques

---

## Questions Fréquentes

### Le bilan n'est pas équilibré, que faire ?

**Causes possibles :**
1. Transactions non enregistrées en partie double
2. Compte de contrepartie manquant
3. Saisie d'anciennes transactions sans partie double

**Solutions :**
1. Vérifier que toutes les transactions récentes ont un `counterpart_account_id`
2. Exécuter le script `add_counterpart_account.php` si la colonne manque
3. Ressaisir les transactions incorrectes

### Le relevé de compte ne correspond pas à mon extrait bancaire

**Causes possibles :**
1. Transactions non enregistrées
2. Compte sélectionné incorrect
3. Période incorrecte

**Solutions :**
1. Vérifier que toutes les opérations bancaires sont saisies
2. Utiliser le module **Rapprochement Bancaire** pour importer les relevés
3. Vérifier la période sélectionnée

### Le compte de résultat affiche une perte, est-ce normal ?

**Analyse :**
- Une perte ponctuelle peut être normale selon :
  - Phase de démarrage (investissements initiaux)
  - Saisonnalité de l'activité
  - Charges exceptionnelles

**Actions :**
- Comparer avec les périodes précédentes
- Analyser le détail des charges
- Vérifier les produits attendus
- Ajuster la stratégie si besoin

### Puis-je comparer deux périodes ?

**Actuellement :**
- Pas de fonctionnalité de comparaison intégrée

**Workaround :**
1. Générer le rapport pour la période 1
2. Exporter en PDF
3. Générer le rapport pour la période 2
4. Comparer manuellement les deux PDFs

**Évolution future :**
- Module de comparaison multi-périodes prévu

---

## Intégration avec autres modules

### Plan Comptable

Les rapports utilisent directement le **Plan Comptable** configuré dans **Paramètres** :
- Seuls les comptes définis apparaissent
- La catégorie (Actif, Passif, Charge, Produit) détermine l'affichage
- Le type (Bilan, Résultat) détermine dans quel rapport le compte apparaît

### Transactions

Toutes les **Transactions** saisies dans **Comptabilité → Nouvelle Transaction** alimentent automatiquement :
- Le **Relevé de Compte** (mouvements détaillés)
- Le **Bilan** (comptes de type Bilan)
- Le **Compte de Résultat** (comptes de type Résultat)

### Rapprochement Bancaire

Les transactions issues du **Rapprochement Bancaire** sont intégrées dans :
- Le relevé de compte du compte bancaire
- Le bilan (compte 1020 - Banque)

---

## Architecture Technique

### Fichiers créés

| Fichier | Description | Lignes |
|---------|-------------|--------|
| `views/releve_compte.php` | Page Relevé de Compte | ~450 |
| `views/bilan.php` | Page Bilan | ~500 |
| `views/compte_resultat.php` | Page Compte de Résultat | ~520 |

### Routes ajoutées dans `index.php`

```php
case 'releve_compte':
    include_once 'views/releve_compte.php';
    break;
case 'bilan':
    include_once 'views/bilan.php';
    break;
case 'compte_resultat':
    include_once 'views/compte_resultat.php';
    break;
```

### Sous-menu dans `includes/header.php`

Le menu **Comptabilité** est transformé en menu accordéon :

```php
<li class="menu-item has-submenu">
    <a href="#" onclick="toggleSubmenu(event, this)">
        Comptabilité
        <i class='fa-solid fa-chevron-down submenu-arrow'></i>
    </a>
    <ul class="submenu">
        <li><a href="?page=comptabilite">Toutes les Transactions</a></li>
        <li><a href="?page=transaction_create">Nouvelle Transaction</a></li>
        <li><a href="?page=releve_compte">Relevé de Compte</a></li>
        <li><a href="?page=bilan">Bilan</a></li>
        <li><a href="?page=compte_resultat">Compte de Résultat (PP)</a></li>
    </ul>
</li>
```

### Requêtes SQL clés

**Solde d'un compte (Relevé)** :
```sql
SELECT
    SUM(CASE WHEN account_id = :account_id THEN amount ELSE 0 END) -
    SUM(CASE WHEN counterpart_account_id = :account_id THEN amount ELSE 0 END) as solde
FROM transactions
WHERE (account_id = :account_id OR counterpart_account_id = :account_id)
  AND company_id = :company_id
  AND date >= :date_debut
  AND date <= :date_fin
```

**Comptes du Bilan** :
```sql
SELECT * FROM accounting_plan
WHERE company_id = :company_id
  AND type = 'Bilan'
ORDER BY category, number ASC
```

**Comptes du Résultat** :
```sql
SELECT * FROM accounting_plan
WHERE company_id = :company_id
  AND type = 'Résultat'
ORDER BY category, number ASC
```

---

## Compatibilité

- ✅ **PHP 7.4+**
- ✅ **MySQL 5.7+** / **MariaDB 10.2+**
- ✅ **Navigateurs modernes** (Chrome, Firefox, Safari, Edge)
- ✅ **Mobile responsive**
- ✅ **Impression PDF** (Ctrl+P)

---

## Support

Pour toute question ou problème :
1. Consulter ce guide
2. Vérifier le [CLAUDE.md](CLAUDE.md) pour la documentation technique
3. Créer une issue dans le repository

---

**Dernière mise à jour** : 15 janvier 2025
**Version de l'application** : 2.1.0
**Module** : Rapports Comptables
