# 📊 Documentation du Module Salaires

## Vue d'ensemble

Le **Module de Gestion des Salaires** est un module premium qui permet aux utilisateurs de gérer leurs employés et de générer automatiquement des fiches de paie conformes à la législation suisse (AVS/AI/APG, assurance chômage, LPP, etc.).

### Caractéristiques principales

- ✅ Gestion complète des employés (coordonnées, contrats, salaires)
- ✅ Génération automatique des fiches de paie
- ✅ Calcul automatique des cotisations sociales suisses
- ✅ Support multi-employés avec restrictions par plan
- ✅ Suivi du temps de travail (optionnel)
- ✅ Historique des salaires et statistiques
- ✅ **Restrictions par plan d'abonnement**

---

## Restrictions par Plan d'Abonnement

| Plan | Max Employés | Prix | Fonctionnalités |
|------|--------------|------|-----------------|
| **Gratuit** | ❌ Module bloqué | Gratuit | - |
| **Starter** | 3 employés | CHF 29/mois | Gestion employés, fiches de paie |
| **Professionnel** | ∞ Illimité | CHF 99/mois | Toutes fonctionnalités + illimité |
| **Enterprise** | ∞ Illimité | Sur devis | Toutes fonctionnalités + support prioritaire |

### Fonctionnement des Limites

- **Plan Gratuit**: Le module est complètement bloqué. Un message d'upgrade s'affiche.
- **Plan Starter**: Permet de créer jusqu'à 3 employés actifs maximum.
- **Plans supérieurs**: Nombre illimité d'employés.

L'application vérifie automatiquement les limites avant chaque création d'employé via la classe `EmployeeLimits`.

---

## Installation

### 1. Exécution du Script SQL

```bash
mysql -u root -pAbil gestion_comptable < install_payroll_module.sql
```

Ou via l'interface web:
```
http://localhost/gestion_comptable/install_payroll.php
```

### 2. Tables Créées

Le script crée les tables suivantes:

- **employees** - Informations des employés
- **payroll** - Fiches de paie mensuelles
- **payroll_settings** - Paramètres et taux de cotisations
- **time_tracking** - Suivi du temps (optionnel)

### 3. Mise à jour subscription_plans

Ajoute la colonne `max_employees` et définit les limites:

```sql
UPDATE subscription_plans SET max_employees = 0 WHERE plan_code = 'free';
UPDATE subscription_plans SET max_employees = 3 WHERE plan_code = 'starter';
UPDATE subscription_plans SET max_employees = -1 WHERE plan_code = 'professional';
UPDATE subscription_plans SET max_employees = -1 WHERE plan_code = 'enterprise';
```

---

## Architecture

### Structure des Fichiers

```
gestion_comptable/
├── models/
│   ├── Employee.php           # Modèle Employé
│   ├── Payroll.php            # Modèle Fiche de paie
│   └── PayrollSettings.php    # Paramètres de paie
├── utils/
│   └── EmployeeLimits.php     # Vérification des limites
├── assets/
│   ├── ajax/
│   │   ├── employees.php      # API CRUD employés
│   │   └── payroll.php        # API génération fiches
│   ├── js/
│   │   └── employees.js       # JavaScript gestion employés
│   └── css/
│       └── employees.css      # Styles du module
├── views/
│   ├── employees.php          # Page liste employés
│   └── payroll.php            # Page fiches de paie
├── install_payroll_module.sql # Script SQL installation
└── install_payroll.php        # Installateur web
```

---

## Modèles de Données

### Employee (Employé)

**Propriétés principales:**

```php
public $id;
public $company_id;              // Société propriétaire
public $employee_number;         // Numéro matricule (auto-généré)
public $first_name;              // Prénom
public $last_name;               // Nom
public $email;
public $phone;
public $address;
public $city;
public $country;

// Contrat
public $hire_date;               // Date d'embauche
public $termination_date;        // Date de fin
public $job_title;               // Poste
public $department;              // Département
public $employment_type;         // full_time, part_time, contractor, intern
public $contract_type;           // cdi, cdd, temporary, apprentice

// Salaire
public $salary_type;             // monthly, hourly, annual
public $base_salary;             // Salaire de base
public $hours_per_week;          // Heures par semaine
public $currency;                // CHF, EUR

// Assurances sociales
public $avs_number;              // Numéro AVS (13 chiffres)
public $accident_insurance;      // Assurance accidents
public $pension_fund;            // Caisse de pension (LPP)

// Bancaire
public $iban;
public $bank_name;

// Allocations
public $family_allowances;       // Boolean
public $num_children;            // Nombre d'enfants

public $is_active;               // Employé actif/inactif
```

**Méthodes principales:**

- `create()` - Créer un employé
- `read()` - Lire un employé
- `readByCompany($company_id)` - Liste employés d'une société
- `update()` - Modifier un employé
- `delete()` - Supprimer un employé
- `deactivate()` - Désactiver (soft delete)
- `generateEmployeeNumber($company_id)` - Générer numéro matricule
- `countActiveByCompany($company_id)` - Compter employés actifs
- `search($company_id, $term)` - Rechercher employés

### Payroll (Fiche de Paie)

**Propriétés principales:**

```php
public $id;
public $company_id;
public $employee_id;

// Période
public $period_month;            // 1-12
public $period_year;             // YYYY
public $payment_date;            // Date de paiement

// Salaire
public $base_salary;             // Salaire de base
public $overtime_hours;          // Heures supplémentaires
public $overtime_amount;         // Montant heures sup
public $bonus;                   // Primes
public $commission;              // Commissions
public $allowances;              // Allocations
public $other_additions;         // Autres ajouts

public $gross_salary;            // Salaire brut total

// Cotisations employé
public $avs_ai_apg_employee;     // AVS/AI/APG (5.3%)
public $ac_employee;             // Assurance chômage (1.1%)
public $lpp_employee;            // LPP 2e pilier (7%)
public $laa_employee;            // LAA accidents (1%)
public $laac_employee;           // LAAC compl. accidents (2%)

// Impôts et déductions
public $income_tax;              // Impôt à la source
public $other_deductions;        // Autres déductions
public $total_deductions;        // Total déductions

public $net_salary;              // Salaire net à payer

// Charges patronales
public $avs_ai_apg_employer;     // AVS/AI/APG employeur (5.3%)
public $ac_employer;             // AC employeur (1.1%)
public $lpp_employer;            // LPP employeur (7%)
public $af_employer;             // Allocations familiales (2%)
public $total_employer_charges;  // Total charges patronales

// Statut
public $status;                  // draft, validated, paid, cancelled
public $transaction_id;          // Transaction comptable liée
```

**Méthodes principales:**

- `create()` - Créer une fiche
- `read()` - Lire une fiche
- `readByCompany($company_id, $filters)` - Liste fiches avec filtres
- `update()` - Modifier (seulement si draft)
- `delete()` - Supprimer (seulement si draft)
- `validate()` - Valider une fiche
- `markAsPaid($transaction_id)` - Marquer comme payée
- `calculateGrossSalary()` - Calculer salaire brut
- `calculateSocialContributions($settings)` - Calculer cotisations
- `exists($company_id, $employee_id, $month, $year)` - Vérifier existence
- `getCompanyStatistics($company_id, $year)` - Statistiques annuelles

### PayrollSettings (Paramètres de Paie)

**Taux par défaut (Suisse 2024):**

```php
avs_ai_apg_rate_employee: 5.30%
avs_ai_apg_rate_employer: 5.30%
ac_rate_employee: 1.10%
ac_rate_employer: 1.10%
lpp_rate_employee: 7.00%
lpp_rate_employer: 7.00%
lpp_min_salary: CHF 21,510
lpp_max_salary: CHF 86,040
af_rate: 2.00%
af_amount_per_child: CHF 200
laa_rate: 1.00%
laac_rate: 2.00%
```

**Méthodes:**

- `readByCompany($company_id)` - Récupérer paramètres
- `update()` - Mettre à jour taux
- `createDefault($company_id)` - Créer paramètres par défaut

---

## APIs

### API Employees

**Endpoint:** `assets/ajax/employees.php`

**Actions disponibles:**

| Action | Méthode | Description |
|--------|---------|-------------|
| `list` | GET | Liste tous les employés |
| `get` | GET | Récupérer un employé par ID |
| `create` | POST | Créer un nouvel employé (vérifie limites) |
| `update` | POST | Modifier un employé |
| `delete` | POST | Supprimer un employé |
| `deactivate` | POST | Désactiver un employé |
| `search` | GET | Rechercher employés |
| `check_limits` | GET | Vérifier les limites du plan |
| `count` | GET | Compter employés actifs |

**Exemple - Créer un employé:**

```javascript
fetch('assets/ajax/employees.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'create',
        first_name: 'Jean',
        last_name: 'Dupont',
        email: 'jean.dupont@example.com',
        job_title: 'Développeur',
        hire_date: '2024-01-01',
        base_salary: 6000,
        salary_type: 'monthly',
        employment_type: 'full_time',
        contract_type: 'cdi'
    })
})
.then(res => res.json())
.then(data => {
    if(data.success) {
        console.log('Employé créé:', data.id);
    } else {
        console.error('Erreur:', data.message);
        if(data.limits) {
            console.log('Limites:', data.limits);
        }
    }
});
```

**Vérification automatique des limites:**

L'API vérifie automatiquement les limites avant création:

```php
$limits = EmployeeLimits::canCreateEmployee($db_master, $tenant_code, $company_id);

if(!$limits['allowed']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => $limits['message'],
        'limits' => $limits
    ]);
    exit;
}
```

### API Payroll

**Endpoint:** `assets/ajax/payroll.php`

**Actions disponibles:**

| Action | Méthode | Description |
|--------|---------|-------------|
| `list` | GET | Liste fiches de paie (filtrable) |
| `get` | GET | Récupérer une fiche par ID |
| `create`/`generate` | POST | Créer/Générer fiche de paie |
| `update` | POST | Modifier fiche (draft uniquement) |
| `delete` | POST | Supprimer fiche (draft uniquement) |
| `validate` | POST | Valider une fiche |
| `mark_paid` | POST | Marquer comme payée |
| `statistics` | GET | Statistiques annuelles |
| `get_settings` | GET | Récupérer paramètres de paie |
| `update_settings` | POST | Mettre à jour paramètres |

**Exemple - Générer une fiche de paie:**

```javascript
fetch('assets/ajax/payroll.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'generate',
        employee_id: 1,
        period_month: 12,
        period_year: 2024,
        payment_date: '2024-12-31',
        base_salary: 6000,
        bonus: 500
    })
})
.then(res => res.json())
.then(data => {
    if(data.success) {
        console.log('Fiche créée:', data.id);
        console.log('Brut:', data.data.gross_salary);
        console.log('Net:', data.data.net_salary);
        console.log('Déductions:', data.data.total_deductions);
        console.log('Charges patronales:', data.data.total_employer_charges);
    }
});
```

---

## Calcul des Cotisations Sociales

### Formule de Calcul

Le calcul est automatique via `Payroll::calculateSocialContributions($settings)`:

```php
// 1. Salaire brut
$gross = base_salary + overtime + bonus + commission + allowances

// 2. AVS/AI/APG - 5.3% employé + 5.3% employeur
$avs_employee = $gross * 0.053
$avs_employer = $gross * 0.053

// 3. Assurance chômage - 1.1% employé + 1.1% employeur
$ac_employee = $gross * 0.011
$ac_employer = $gross * 0.011

// 4. LPP (si salaire annuel >= CHF 21,510 et <= CHF 86,040)
if (annual_salary >= 21510 && annual_salary <= 86040) {
    $lpp_employee = $gross * 0.07  // 7%
    $lpp_employer = $gross * 0.07  // 7%
}

// 5. LAA/LAAC - Employé uniquement
$laa_employee = $gross * 0.01   // 1%
$laac_employee = $gross * 0.02  // 2%

// 6. Allocations familiales - Employeur uniquement
$af_employer = $gross * 0.02  // 2%

// 7. Total déductions employé
$total_deductions = avs_employee + ac_employee + lpp_employee + laa_employee + laac_employee + income_tax + other_deductions

// 8. Salaire net
$net_salary = $gross - $total_deductions

// 9. Total charges patronales
$total_employer_charges = avs_employer + ac_employer + lpp_employer + af_employer
```

---

## Utilisation

### 1. Créer un Employé

1. Accéder à **Salaires > Employés**
2. Cliquer sur **Nouvel Employé**
3. Remplir les onglets:
   - **Informations générales** (nom, coordonnées)
   - **Contrat** (date embauche, poste, type contrat)
   - **Salaire** (type, montant, devise)
   - **Assurances** (AVS, LPP, accidents)
   - **Coordonnées bancaires** (IBAN)
4. Cliquer **Enregistrer**

**Limite atteinte?**
Si la limite du plan est atteinte, un message s'affiche proposant de passer à un plan supérieur.

### 2. Générer des Fiches de Paie

1. Accéder à **Salaires > Fiches de Paie**
2. Cliquer sur **Générer Fiches de Paie**
3. Sélectionner:
   - Mois
   - Année
   - Date de paiement
4. Cliquer **Générer**

Le système crée automatiquement une fiche pour chaque employé actif avec calcul des cotisations.

### 3. Workflow des Fiches

```
DRAFT → VALIDATED → PAID
```

- **Draft**: Modifiable, supprimable
- **Validated**: Figée, plus modifiable
- **Paid**: Marquée comme payée, liée à transaction comptable (optionnel)

### 4. Consulter les Statistiques

La page Fiches de Paie affiche:
- Nombre de fiches ce mois
- Total Brut
- Total Net
- Total Charges Patronales

Filtrable par année et mois.

---

## Sécurité

### Vérification des Accès

```php
// 1. Authentification
if(!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit;
}

// 2. Vérification module activé
$module_enabled = EmployeeLimits::isPayrollModuleEnabled($db_master, $tenant_code);
if(!$module_enabled) {
    http_response_code(403);
    echo json_encode(['feature_locked' => true]);
    exit;
}

// 3. Vérification limites avant création
$limits = EmployeeLimits::canCreateEmployee($db_master, $tenant_code, $company_id);
if(!$limits['allowed']) {
    http_response_code(403);
    exit;
}

// 4. Scope par société
WHERE company_id = :company_id
```

### Validation des Données

- Validation côté serveur de tous les champs requis
- Sanitization avec `htmlspecialchars(strip_tags())`
- Prepared statements pour toutes les requêtes SQL
- Validation des formats (email, dates, montants)

---

## Tests

### Checklist de Test

- [ ] Installer le module via install_payroll.php
- [ ] Vérifier que plan Gratuit bloque le module
- [ ] Créer 3 employés sur plan Starter
- [ ] Tenter de créer 4ème employé (doit être bloqué)
- [ ] Passer au plan Professionnel
- [ ] Créer plus de 3 employés (doit fonctionner)
- [ ] Générer fiches de paie pour un mois
- [ ] Vérifier calculs AVS/AI/APG (10.6% total)
- [ ] Vérifier calculs LPP (14% total si éligible)
- [ ] Valider une fiche (doit devenir non-modifiable)
- [ ] Marquer une fiche comme payée
- [ ] Consulter les statistiques annuelles

---

## FAQ

**Q: Puis-je modifier une fiche validée?**
R: Non, seules les fiches au statut "draft" sont modifiables. Supprimez et recréez si nécessaire.

**Q: Comment débloquer le module sur plan Gratuit?**
R: Passez au plan Starter (CHF 29/mois) minimum.

**Q: Puis-je importer des employés en masse?**
R: Non actuellement. Créez-les manuellement via l'interface.

**Q: Les cotisations sont-elles conformes à la Suisse?**
R: Oui, basées sur les taux 2024. Vérifiez et ajustez dans Paramètres si nécessaire.

**Q: Puis-je générer des fiches pour plusieurs mois d'un coup?**
R: Non, générez mois par mois via l'interface.

**Q: Les fiches de paie sont-elles exportables en PDF?**
R: Pas encore implémenté. Fonctionnalité future.

---

## Roadmap

### Phase 1 - Fonctionnalités de Base ✅
- [x] Gestion des employés
- [x] Génération fiches de paie
- [x] Calculs automatiques cotisations
- [x] Restrictions par plan

### Phase 2 - Améliorations 🚧
- [ ] Export PDF des fiches de paie
- [ ] Import CSV employés
- [ ] Gestion des absences/congés
- [ ] Suivi détaillé des heures (timesheet)

### Phase 3 - Avancé 📋
- [ ] Certificats de salaire annuels
- [ ] Déclarations AVS automatiques
- [ ] Intégration avec SwissSalary
- [ ] Multi-devises

---

## Support

Pour toute question ou problème:
- Documentation complète: Ce fichier
- Issues GitHub: https://github.com/your-repo/issues
- Email support: support@example.com

---

**Version:** 1.0.0
**Dernière mise à jour:** <?php echo date('d/m/Y'); ?>
