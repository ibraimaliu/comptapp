# ✅ Installation du Module Salaires - COMPLÈTE

## 🎉 Installation Réussie!

Le module de gestion des salaires a été **installé avec succès** dans votre base de données.

---

## 📊 Tables Créées

### Base Tenant: `gestion_comptable_client_b1548e8d`

✅ **employees** - Informations des employés
- 28 colonnes (coordonnées, contrat, salaire, assurances, bancaire)
- Clés étrangères vers `companies`
- Index sur `company_id`, `is_active`, `employee_number`

✅ **payroll** - Fiches de paie mensuelles
- 38 colonnes (période, salaire, cotisations, charges)
- Contrainte unique sur `company_id + employee_id + period_month + period_year`
- Workflow: draft → validated → paid → cancelled
- Calculs automatiques: AVS/AI/APG, AC, LPP, LAA, LAAC, AF

✅ **payroll_settings** - Paramètres et taux par société
- Taux AVS/AI/APG: 5.3% (employé + employeur)
- Taux AC: 1.1% (employé + employeur)
- Taux LPP: 7% (employé + employeur)
- Seuils LPP: CHF 21,510 - 86,040
- Allocations familiales: 2% + CHF 200/enfant

✅ **time_tracking** - Suivi du temps (optionnel)
- Heures travaillées par employé/jour
- Heures supplémentaires
- Système d'approbation

### Base Master: `gestion_comptable_master`

✅ **subscription_plans.max_employees** - Colonne ajoutée
- Gratuit: 0 (module bloqué)
- Starter: 3 employés max
- Professional: -1 (illimité)
- Enterprise: -1 (illimité)

---

## 🔧 Commandes Exécutées

```bash
# Installation tables tenant
mysql -u root -pAbil gestion_comptable_client_b1548e8d < install_payroll_tenant.sql

# Mise à jour base master
mysql -u root -pAbil gestion_comptable_master < update_subscription_plans.sql
```

**Résultat:**
```
plan_code    | plan_name      | max_employees
-------------|----------------|---------------
free         | Gratuit        | 0
starter      | Starter        | 3
professional | Professional   | -1
enterprise   | Enterprise     | -1
```

---

## 🎯 Prochaines Étapes

### 1. Accéder au Module

Allez dans le menu de votre application:
```
Menu > Salaires > Employés
```

### 2. Créer votre Premier Employé

1. Cliquez sur **"Nouvel Employé"**
2. Remplissez les informations:
   - **Informations générales**: Nom, prénom, email, téléphone
   - **Contrat**: Date embauche, poste, type contrat
   - **Salaire**: Salaire de base, type (mensuel/horaire), devise
   - **Assurances**: Numéro AVS, caisse de pension
   - **Coordonnées bancaires**: IBAN
3. Cliquez **"Enregistrer"**

### 3. Générer une Fiche de Paie

1. Allez dans **Salaires > Fiches de Paie**
2. Cliquez sur **"Générer Fiches de Paie"**
3. Sélectionnez:
   - Mois: Décembre
   - Année: 2024
   - Date de paiement: 31/12/2024
4. Cliquez **"Générer"**

Le système calcule automatiquement:
- Salaire brut
- Cotisations AVS/AI/APG (5.3%)
- Assurance chômage (1.1%)
- LPP 2e pilier (7% si éligible)
- LAA/LAAC accidents
- Salaire net = Brut - Déductions
- Charges patronales

---

## 🔒 Restrictions par Plan

Votre plan actuel détermine le nombre d'employés que vous pouvez créer:

### Plan Gratuit (Actuel si max_employees = 0)
- ❌ **Module bloqué**
- 💡 Message: "Passez au plan Starter ou supérieur"
- 🔒 Aucun employé ne peut être créé
- 📈 **Upgrade requis**: CHF 29/mois (Starter)

### Plan Starter (CHF 29/mois)
- ✅ **Jusqu'à 3 employés** actifs
- ✅ Génération fiches de paie
- ✅ Calculs automatiques cotisations
- ⚠️ À partir du 4ème employé: "Limite atteinte"
- 📈 **Pour plus**: Passer au plan Professional

### Plans Professional & Enterprise
- ✅ **Employés illimités** (max_employees = -1)
- ✅ Toutes les fonctionnalités
- ✅ Pas de restriction

---

## 🧪 Tests à Effectuer

### ✅ Checklist Installation

- [x] Tables `employees`, `payroll`, `payroll_settings`, `time_tracking` créées
- [x] Colonne `subscription_plans.max_employees` ajoutée
- [x] Limites par plan configurées (0, 3, -1, -1)
- [ ] Accéder au menu "Salaires > Employés"
- [ ] Vérifier affichage des limites du plan
- [ ] Créer un employé de test
- [ ] Générer une fiche de paie
- [ ] Vérifier calculs des cotisations
- [ ] Tester workflow: Brouillon → Validée → Payée

### Test 1: Vérifier le Plan Actuel

Connectez-vous et allez dans **Salaires > Employés**.

**Vous devriez voir:**
```
Plan: [Nom de votre plan]
Employés: 0 / [limite]
```

Si limite = 0 → Module bloqué, upgrade requis
Si limite = 3 → Plan Starter, max 3 employés
Si limite = ∞ → Plan Professional/Enterprise, illimité

### Test 2: Créer un Employé

Cliquez sur **"Nouvel Employé"** et créez:
```
Prénom: Jean
Nom: Dupont
Poste: Développeur
Date embauche: 01/01/2024
Salaire: 6000 CHF/mois
Type: Temps plein
Contrat: CDI
```

**Résultat attendu:**
- ✅ Employé créé avec succès
- ✅ Numéro matricule auto-généré (EMP-0001)
- ✅ Compteur: "Employés: 1 / [limite]"

### Test 3: Générer Fiche de Paie

Allez dans **Salaires > Fiches de Paie** → **Générer**
```
Mois: Décembre
Année: 2024
Date paiement: 31/12/2024
```

**Résultat attendu:**
```
Salaire brut: 6,000.00 CHF
AVS/AI/APG: -318.00 CHF (5.3%)
AC: -66.00 CHF (1.1%)
LPP: -420.00 CHF (7%)
LAA: -60.00 CHF (1%)
LAAC: -120.00 CHF (2%)
───────────────────────────
Total déductions: -984.00 CHF
Salaire net: 5,016.00 CHF

Charges patronales:
AVS/AI/APG: 318.00 CHF
AC: 66.00 CHF
LPP: 420.00 CHF
AF: 120.00 CHF
───────────────────────────
Total charges: 924.00 CHF
```

### Test 4: Tester Limite (Plan Starter)

Si vous avez le **plan Starter**:
1. Créez employé #1 → ✅ Succès
2. Créez employé #2 → ✅ Succès
3. Créez employé #3 → ✅ Succès
4. Tentez employé #4 → ❌ **Erreur: "Limite atteinte"**

**Message affiché:**
```
❌ Limite atteinte. Passez à un plan supérieur
pour ajouter plus d'employés.

Plan actuel: Starter (3 employés max)
Employés actifs: 3 / 3
```

---

## 📁 Fichiers du Module

### Modèles
- `models/Employee.php` - Gestion employés
- `models/Payroll.php` - Gestion fiches de paie
- `models/PayrollSettings.php` - Paramètres

### APIs
- `assets/ajax/employees.php` - CRUD employés (9 actions)
- `assets/ajax/payroll.php` - Gestion paie (11 actions)

### Vues
- `views/employees.php` - Interface employés
- `views/payroll.php` - Interface fiches de paie

### Frontend
- `assets/js/employees.js` - JavaScript
- `assets/css/employees.css` - Styles

### Utilitaires
- `utils/EmployeeLimits.php` - Vérification limites

### Installation
- `install_payroll_tenant.sql` - Tables tenant (✅ exécuté)
- `update_subscription_plans.sql` - Base master (✅ exécuté)

---

## 🆘 Résolution de Problèmes

### Problème: Module toujours bloqué

**Vérifier le plan:**
```sql
SELECT t.tenant_code, t.subscription_plan, sp.max_employees
FROM gestion_comptable_master.tenants t
JOIN gestion_comptable_master.subscription_plans sp
  ON t.subscription_plan = sp.plan_code
WHERE t.database_name = 'gestion_comptable_client_b1548e8d';
```

Si `max_employees = 0`, changez le plan:
```sql
UPDATE gestion_comptable_master.tenants
SET subscription_plan = 'starter'
WHERE database_name = 'gestion_comptable_client_b1548e8d';
```

### Problème: Calculs incorrects

**Vérifier paramètres:**
```sql
SELECT * FROM payroll_settings WHERE company_id = 1;
```

**Réinitialiser aux défauts:**
```sql
UPDATE payroll_settings SET
  avs_ai_apg_rate_employee = 5.30,
  avs_ai_apg_rate_employer = 5.30,
  ac_rate_employee = 1.10,
  ac_rate_employer = 1.10,
  lpp_rate_employee = 7.00,
  lpp_rate_employer = 7.00
WHERE company_id = 1;
```

### Problème: Tables manquantes

**Réinstaller:**
```bash
mysql -u root -pAbil gestion_comptable_client_b1548e8d < install_payroll_tenant.sql
```

---

## 📞 Support

- **Documentation complète:** `MODULE_SALAIRES_DOCUMENTATION.md`
- **Guide installation:** `MODULE_SALAIRES_README.md`
- **Liste fichiers:** `MODULE_SALAIRES_FICHIERS.md`

---

## ✨ Félicitations!

Le module de gestion des salaires est maintenant **opérationnel**! 🎉

Vous pouvez commencer à:
1. ✅ Créer vos employés
2. ✅ Générer des fiches de paie
3. ✅ Suivre la masse salariale
4. ✅ Consulter les statistiques

**Bon salaire!** 💰

---

**Date d'installation:** <?php echo date('d/m/Y H:i:s'); ?>
**Version:** 1.0.0
**Base tenant:** gestion_comptable_client_b1548e8d
**Base master:** gestion_comptable_master
