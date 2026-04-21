# 📦 Module Salaires - Liste Complète des Fichiers

## ✅ Fichiers Créés pour le Module

### 🗄️ Base de Données

| Fichier | Description |
|---------|-------------|
| `install_payroll_module.sql` | Script SQL complet pour créer les tables |
| `install_payroll.php` | Interface web d'installation avec vérifications |

**Tables créées:**
- `employees` - Employés avec toutes les informations
- `payroll` - Fiches de paie mensuelles
- `payroll_settings` - Paramètres et taux de cotisations par société
- `time_tracking` - Suivi du temps de travail (optionnel)

**Colonne ajoutée:**
- `subscription_plans.max_employees` - Limite d'employés par plan

**Vues créées:**
- `v_employee_annual_salary` - Salaires annuels par employé
- `v_company_payroll_summary` - Masse salariale par société

---

### 📁 Modèles (models/)

| Fichier | Lignes | Description |
|---------|--------|-------------|
| `Employee.php` | ~450 | Modèle Employé avec CRUD complet, recherche, génération numéro matricule |
| `Payroll.php` | ~500 | Modèle Fiche de paie avec calculs automatiques cotisations |
| `PayrollSettings.php` | ~150 | Paramètres de paie et taux par société |

**Méthodes principales Employee:**
- `create()`, `read()`, `update()`, `delete()`
- `deactivate()` - Soft delete
- `readByCompany($company_id)` - Liste par société
- `countActiveByCompany($company_id)` - Compteur
- `generateEmployeeNumber($company_id)` - Auto EMP-0001
- `search($company_id, $term)` - Recherche multi-critères
- `isLPPEligible()` - Vérifier éligibilité 2e pilier
- `getAnnualGrossSalary()` - Calcul salaire annuel

**Méthodes principales Payroll:**
- `create()`, `read()`, `update()`, `delete()`
- `validate()` - Valider fiche (statut draft → validated)
- `markAsPaid($transaction_id)` - Marquer payée
- `calculateGrossSalary()` - Calcul salaire brut
- `calculateSocialContributions($settings)` - Calcul AVS, AC, LPP, etc.
- `exists($company_id, $employee_id, $month, $year)` - Vérifier doublon
- `readByCompany($company_id, $filters)` - Liste avec filtres
- `getCompanyStatistics($company_id, $year)` - Statistiques annuelles

---

### 🔧 Utilitaires (utils/)

| Fichier | Lignes | Description |
|---------|--------|-------------|
| `EmployeeLimits.php` | ~200 | Gestion des limites d'employés par plan d'abonnement |

**Méthodes EmployeeLimits:**
- `canCreateEmployee($db_master, $tenant_code, $company_id)` - Vérifier si création autorisée
- `hasReachedLimit($db_master, $tenant_code, $company_id)` - Boolean limite atteinte
- `getEmployeeLimits($db_master, $tenant_code, $company_id)` - Informations complètes
- `isPayrollModuleEnabled($db_master, $tenant_code)` - Vérifier module activé
- `getUpgradeMessage($plan_name)` - Message personnalisé upgrade

**Réponse type:**
```php
[
    'allowed' => true/false,
    'current' => 2,              // Nombre actuel
    'max' => 3,                  // Limite (-1 = illimité)
    'plan_name' => 'Starter',
    'message' => 'Vous pouvez créer encore 1 employé(s)',
    'feature_locked' => false    // true si plan gratuit
]
```

---

### 🌐 APIs (assets/ajax/)

| Fichier | Lignes | Actions | Description |
|---------|--------|---------|-------------|
| `employees.php` | ~400 | 9 actions | API REST complète pour employés |
| `payroll.php` | ~500 | 11 actions | API REST pour fiches de paie |

**Actions employees.php:**
1. `list` - Liste tous les employés (filtrable par actifs)
2. `get` - Récupérer un employé par ID
3. `create` - Créer employé (**vérifie limites automatiquement**)
4. `update` - Modifier employé
5. `delete` - Supprimer employé
6. `deactivate` - Désactiver employé (soft delete)
7. `search` - Recherche multi-critères
8. `check_limits` - Vérifier limites du plan
9. `count` - Compter employés actifs

**Actions payroll.php:**
1. `list` - Liste fiches (filtrable year/month/status/employee)
2. `get` - Récupérer fiche par ID
3. `create`/`generate` - Créer/Générer fiche avec calculs auto
4. `update` - Modifier fiche (draft uniquement)
5. `delete` - Supprimer fiche (draft uniquement)
6. `validate` - Valider fiche (draft → validated)
7. `mark_paid` - Marquer payée (validated → paid)
8. `statistics` - Statistiques annuelles
9. `get_settings` - Récupérer paramètres cotisations
10. `update_settings` - Mettre à jour taux
11. **Vérification automatique:** Module activé via `EmployeeLimits::isPayrollModuleEnabled()`

---

### 🎨 Frontend (assets/)

#### JavaScript

| Fichier | Lignes | Description |
|---------|--------|-------------|
| `assets/js/employees.js` | ~450 | Gestion complète interface employés |

**Fonctions principales:**
- `loadEmployees()` - Charger liste
- `displayEmployees(employees)` - Affichage grille
- `openEmployeeModal()` - Modal création
- `editEmployee(id)` - Modal édition
- `saveEmployee()` - Enregistrement avec validation
- `deactivateEmployee(id)` - Désactivation
- `searchEmployees(term)` - Recherche temps réel
- `switchTab(tabName)` - Navigation onglets modal
- `showUpgradeModal()` - Modal upgrade plan

#### CSS

| Fichier | Lignes | Description |
|---------|--------|-------------|
| `assets/css/employees.css` | ~600 | Styles modernes pour le module |

**Styles inclus:**
- Page header avec plan info
- Module locked (message upgrade)
- Toolbar avec recherche et filtres
- Employee cards (grille responsive)
- Modal multi-onglets
- Formulaires avec validation visuelle
- Boutons gradient modernes
- Plans comparison
- Responsive mobile

**Design:**
- Gradient violet/bleu pour actions principales
- Cartes avec hover effects
- Modal full-featured avec 5 onglets
- Badge de statut colorés
- Grid responsive (auto-fill minmax)

---

### 👁️ Vues (views/)

| Fichier | Lignes | Description |
|---------|--------|-------------|
| `employees.php` | ~350 | Page liste et gestion employés |
| `payroll.php` | ~400 | Page fiches de paie et génération |

**employees.php - Sections:**
1. **En-tête** - Titre + info plan + bouton "Nouvel Employé"
2. **Message locked** - Si module bloqué (plan gratuit)
3. **Toolbar** - Recherche + filtres (actif/type emploi)
4. **Liste employés** - Grille de cartes
5. **Modal employé** - Formulaire 5 onglets:
   - Informations générales
   - Contrat
   - Salaire
   - Assurances sociales
   - Coordonnées bancaires
6. **Modal upgrade** - Comparaison plans

**payroll.php - Sections:**
1. **En-tête** - Titre + filtres année/mois + bouton "Générer"
2. **Message locked** - Si module bloqué
3. **Statistiques** - 4 cartes (fiches, brut, net, charges)
4. **Tableau fiches** - Liste avec actions
5. **Modal génération** - Sélection mois/année/date paiement

---

### 📚 Documentation

| Fichier | Lignes | Description |
|---------|--------|-------------|
| `MODULE_SALAIRES_DOCUMENTATION.md` | ~800 | Documentation technique complète |
| `MODULE_SALAIRES_README.md` | ~400 | Guide d'installation rapide |
| `MODULE_SALAIRES_FICHIERS.md` | CE FICHIER | Liste complète des fichiers |

---

### 🔄 Modifications de Fichiers Existants

| Fichier | Ligne(s) | Modification |
|---------|----------|--------------|
| `index.php` | 140-148 | Ajout routes `employees`, `employes`, `payroll`, `salaires`, `fiches_paie` |
| `includes/header.php` | 403-424 | Ajout menu "Salaires" avec sous-menu Employés et Fiches de Paie |

**Menu ajouté:**
```html
<li class="menu-item has-submenu">
    <a href="#">Salaires</a>
    <ul class="submenu">
        <li><a href="index.php?page=employees">Employés</a></li>
        <li><a href="index.php?page=payroll">Fiches de Paie</a></li>
    </ul>
</li>
```

---

## 📊 Statistiques du Module

### Lignes de Code

| Catégorie | Fichiers | Lignes (~) |
|-----------|----------|------------|
| SQL | 1 | 350 |
| PHP Modèles | 3 | 1,100 |
| PHP Utilitaires | 1 | 200 |
| PHP APIs | 2 | 900 |
| PHP Vues | 2 | 750 |
| PHP Install | 1 | 250 |
| JavaScript | 1 | 450 |
| CSS | 1 | 600 |
| Documentation | 3 | 1,500 |
| **TOTAL** | **15** | **~6,100** |

### Fonctionnalités

- ✅ 20+ méthodes de modèles
- ✅ 20 actions API REST
- ✅ 15+ fonctions JavaScript
- ✅ 4 tables base de données
- ✅ 2 vues SQL
- ✅ Restrictions par plan
- ✅ Calculs automatiques cotisations suisses
- ✅ Interface moderne responsive
- ✅ Système de workflow (draft → validated → paid)
- ✅ Statistiques et reporting

---

## 🎯 Architecture Modulaire

```
Module Salaires
│
├── DATABASE LAYER
│   ├── Tables: employees, payroll, payroll_settings, time_tracking
│   └── Views: v_employee_annual_salary, v_company_payroll_summary
│
├── MODEL LAYER
│   ├── Employee.php (CRUD + Business Logic)
│   ├── Payroll.php (Calculs + Workflow)
│   └── PayrollSettings.php (Configuration)
│
├── UTILITY LAYER
│   └── EmployeeLimits.php (Vérification limites)
│
├── API LAYER
│   ├── employees.php (REST API Employés)
│   └── payroll.php (REST API Fiches)
│
├── VIEW LAYER
│   ├── employees.php (UI Employés)
│   └── payroll.php (UI Fiches)
│
└── PRESENTATION LAYER
    ├── employees.js (Logique frontend)
    └── employees.css (Styles)
```

---

## 🔒 Sécurité

Chaque composant implémente:

1. **Authentification** - Vérification `$_SESSION['user_id']`
2. **Autorisation** - Vérification `$_SESSION['company_id']`
3. **Limites Plan** - Vérification avant création via `EmployeeLimits`
4. **Module Lock** - Blocage si plan gratuit
5. **Validation** - Côté client ET serveur
6. **Sanitization** - `htmlspecialchars(strip_tags())`
7. **Prepared Statements** - Toutes les requêtes SQL
8. **Scope** - Toutes les requêtes filtrées par `company_id`

---

## 📝 Checklist Installation

- [ ] Exécuter `install_payroll.php`
- [ ] Vérifier création des 4 tables
- [ ] Vérifier colonne `max_employees` ajoutée
- [ ] Vérifier limites par plan configurées
- [ ] Tester création employé (plan Starter, max 3)
- [ ] Tester blocage module (plan Gratuit)
- [ ] Tester génération fiches de paie
- [ ] Vérifier calculs cotisations
- [ ] Tester workflow draft → validated → paid
- [ ] Consulter documentation complète

---

**Développé par:** Équipe Gestion Comptable
**Version:** 1.0.0
**Date:** 2025-01-13

🎉 **Module complet et prêt à l'emploi!**
