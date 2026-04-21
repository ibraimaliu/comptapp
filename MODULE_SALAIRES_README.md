# 🎯 Module Salaires - Guide d'Installation Rapide

## 📋 Résumé

Le **Module de Gestion des Salaires** permet de gérer les employés et de générer automatiquement des fiches de paie conformes à la législation suisse, avec restrictions par plan d'abonnement.

### Restrictions par Plan

| Plan | Max Employés | Accès Module |
|------|--------------|--------------|
| Gratuit | ❌ 0 | Module bloqué |
| Starter | ✅ 3 | Activé |
| Professionnel | ✅ Illimité | Activé |
| Enterprise | ✅ Illimité | Activé |

---

## 🚀 Installation en 3 Étapes

### Étape 1: Accéder à l'installateur

Ouvrez votre navigateur et accédez à:
```
http://localhost/gestion_comptable/install_payroll.php
```

### Étape 2: Vérifier l'installation

Le script va automatiquement:
- ✅ Créer les tables nécessaires (employees, payroll, payroll_settings, time_tracking)
- ✅ Ajouter la colonne `max_employees` dans `subscription_plans`
- ✅ Configurer les limites par plan
- ✅ Initialiser les paramètres pour les sociétés existantes

### Étape 3: Accéder au module

Une fois l'installation terminée:
1. Cliquez sur le bouton **"Accéder au module Employés"**
2. Ou allez dans le menu: **Salaires > Employés**

---

## 📁 Fichiers Créés

```
✅ models/Employee.php              - Modèle Employé
✅ models/Payroll.php               - Modèle Fiche de paie
✅ models/PayrollSettings.php       - Paramètres de paie
✅ utils/EmployeeLimits.php         - Gestion des limites
✅ assets/ajax/employees.php        - API CRUD employés
✅ assets/ajax/payroll.php          - API fiches de paie
✅ assets/js/employees.js           - JavaScript employés
✅ assets/css/employees.css         - Styles module
✅ views/employees.php              - Page liste employés
✅ views/payroll.php                - Page fiches de paie
✅ install_payroll_module.sql      - Script SQL
✅ install_payroll.php              - Installateur web
```

---

## 🎮 Utilisation Rapide

### Créer un Employé

1. Allez dans **Salaires > Employés**
2. Cliquez sur **Nouvel Employé**
3. Remplissez:
   - Nom, prénom
   - Date d'embauche
   - Poste
   - Salaire de base
4. **Enregistrer**

### Générer des Fiches de Paie

1. Allez dans **Salaires > Fiches de Paie**
2. Cliquez sur **Générer Fiches de Paie**
3. Sélectionnez le mois et l'année
4. **Générer**

Les fiches sont créées automatiquement avec calcul des cotisations AVS, assurance chômage, LPP, etc.

---

## 🔐 Limites par Plan

### Plan Gratuit
- ❌ Module complètement bloqué
- 💡 Message affiché: "Passez au plan Starter ou supérieur"

### Plan Starter (CHF 29/mois)
- ✅ Jusqu'à **3 employés** actifs maximum
- ✅ Génération fiches de paie
- ✅ Calculs automatiques

### Plans Professionnel & Enterprise
- ✅ **Employés illimités**
- ✅ Toutes les fonctionnalités

---

## ✅ Vérification de l'Installation

### 1. Vérifier les Tables

Connectez-vous à MySQL et exécutez:

```sql
USE gestion_comptable;

SHOW TABLES LIKE 'employees';
SHOW TABLES LIKE 'payroll';
SHOW TABLES LIKE 'payroll_settings';
SHOW TABLES LIKE 'time_tracking';

-- Vérifier les limites
SELECT plan_code, plan_name, max_employees
FROM subscription_plans;
```

Résultat attendu:
```
+-------------+------------------+---------------+
| plan_code   | plan_name        | max_employees |
+-------------+------------------+---------------+
| free        | Gratuit          |             0 |
| starter     | Starter          |             3 |
| professional| Professionnel    |            -1 |
| enterprise  | Enterprise       |            -1 |
+-------------+------------------+---------------+
```

### 2. Tester la Création d'un Employé

1. Connectez-vous avec un compte ayant le **plan Starter**
2. Allez dans **Salaires > Employés**
3. Vérifiez l'affichage: "Employés: 0 / 3"
4. Créez un employé
5. Vérifiez: "Employés: 1 / 3"

### 3. Tester le Blocage (Plan Gratuit)

1. Connectez-vous avec un compte **plan Gratuit**
2. Allez dans **Salaires > Employés**
3. Vérifiez:
   - ❌ Message "Module non disponible"
   - 🔒 Icône de cadenas
   - Bouton "Mettre à niveau" visible

---

## 🧪 Tests à Effectuer

### Test 1: Plan Gratuit - Module Bloqué
```
✅ Message d'upgrade affiché
✅ Bouton "Mettre à niveau" présent
✅ Impossibilité de créer employés
```

### Test 2: Plan Starter - Limite 3 Employés
```
✅ Créer 1er employé → Succès
✅ Créer 2ème employé → Succès
✅ Créer 3ème employé → Succès
✅ Créer 4ème employé → Erreur "Limite atteinte"
✅ Message: "Passez à un plan supérieur"
```

### Test 3: Plan Professionnel - Illimité
```
✅ Créer plus de 3 employés → Succès
✅ Affichage: "Employés: X / ∞"
```

### Test 4: Génération Fiches de Paie
```
✅ Créer 3 employés actifs
✅ Générer fiches pour décembre 2024
✅ Vérifier 3 fiches créées
✅ Vérifier calculs:
   - AVS/AI/APG: 5.3% employé + 5.3% employeur
   - AC: 1.1% employé + 1.1% employeur
   - LPP: 7% employé + 7% employeur (si éligible)
✅ Salaire net = Brut - Déductions
```

### Test 5: Workflow Fiches
```
✅ Fiche créée en statut "Brouillon"
✅ Modifier fiche → Succès
✅ Valider fiche → Statut "Validée"
✅ Tenter modifier validée → Erreur
✅ Marquer payée → Statut "Payée"
```

---

## 🐛 Résolution de Problèmes

### Erreur: "Table 'employees' doesn't exist"

**Solution:**
```bash
mysql -u root -pAbil gestion_comptable < install_payroll_module.sql
```

Ou réexécutez: `http://localhost/gestion_comptable/install_payroll.php`

### Erreur: "Column 'max_employees' not found"

**Solution:**
```sql
ALTER TABLE subscription_plans
ADD COLUMN max_employees INT DEFAULT 0 COMMENT 'Nombre max d\'employés (-1 = illimité)';

UPDATE subscription_plans SET max_employees = 0 WHERE plan_code = 'free';
UPDATE subscription_plans SET max_employees = 3 WHERE plan_code = 'starter';
UPDATE subscription_plans SET max_employees = -1 WHERE plan_code = 'professional';
UPDATE subscription_plans SET max_employees = -1 WHERE plan_code = 'enterprise';
```

### Module toujours bloqué malgré plan Starter

**Vérifications:**
1. Vérifier le plan du tenant:
```sql
SELECT t.tenant_code, t.subscription_plan, sp.max_employees
FROM tenants t
JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
WHERE t.tenant_code = 'VOTRE_CODE';
```

2. Vider le cache de session:
```php
// Déconnexion/Reconnexion
```

3. Vérifier `$_SESSION['tenant_code']`:
```php
// Dans debug_tenant.php
print_r($_SESSION);
```

### Calculs incorrects des cotisations

**Vérifier les paramètres:**
1. Aller dans **Salaires > Fiches de Paie**
2. Les taux sont configurables via API:
```javascript
fetch('assets/ajax/payroll.php?action=get_settings')
    .then(res => res.json())
    .then(data => console.log(data));
```

---

## 📞 Support

- **Documentation complète:** `MODULE_SALAIRES_DOCUMENTATION.md`
- **Code source:** Voir fichiers dans `models/`, `assets/ajax/`, `views/`
- **Issues:** Contactez l'équipe de développement

---

## ✨ Prochaines Étapes

Après installation:

1. ✅ Configurer les taux de cotisations (si différents des défauts)
2. ✅ Créer vos premiers employés
3. ✅ Générer les fiches de paie du mois
4. ✅ Valider et marquer comme payées
5. ✅ Consulter les statistiques annuelles

---

**Version:** 1.0.0
**Dernière mise à jour:** 2025-01-13
**Compatibilité:** PHP 7.4+, MySQL 5.7+

🎉 **Bon salaire!**
