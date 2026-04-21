# 🔧 Débogage du Bouton "Enregistrer" - Module Employés

## 📋 Problème Signalé

Le bouton "Enregistrer" pour valider l'enregistrement d'un employé ne fonctionne pas.

---

## 🛠️ Outils de Débogage Créés

### 1. Page de Diagnostic Complète

**Fichier:** `debug_employee_save.php`

**URL d'accès:**
```
http://localhost/gestion_comptable/debug_employee_save.php
```

**Fonctionnalités:**
- ✅ Affiche les informations de session (user_id, company_id, tenant_code, plan)
- ✅ Vérifie les limites du plan d'abonnement
- ✅ Tests automatiques JavaScript (formulaire, champs, fonctions)
- ✅ Console de débogage en temps réel
- ✅ Formulaire de test pré-rempli
- ✅ 4 boutons de test:
  - **Lancer les tests** - Vérifie tous les éléments
  - **Tester saveEmployee()** - Appelle directement la fonction
  - **Tester API directement** - Appel API sans passer par saveEmployee()
  - **Effacer console** - Nettoie la console de débogage

**Instructions:**
1. Ouvrir la page: `http://localhost/gestion_comptable/debug_employee_save.php`
2. Cliquer sur "Lancer les tests" → Voir les résultats dans la console
3. Cliquer sur "Tester saveEmployee()" → Vérifier si l'appel fonctionne
4. Cliquer sur "Tester API directement" → Vérifier si le backend répond

---

### 2. Formulaire de Test HTML Pur

**Fichier:** `test_employee_form.html`

**URL d'accès:**
```
http://localhost/gestion_comptable/test_employee_form.html
```

**Fonctionnalités:**
- ✅ Formulaire HTML pur (pas de PHP, pas de session)
- ✅ Charge le vrai fichier `employees.js`
- ✅ Console de débogage intégrée
- ✅ Affiche tous les console.log() en temps réel
- ✅ Vérifie que saveEmployee() est disponible

**Instructions:**
1. Ouvrir la page: `http://localhost/gestion_comptable/test_employee_form.html`
2. Vérifier les messages dans la console de débogage:
   - ✅ "DOM chargé"
   - ✅ "saveEmployee() est disponible" (ou ❌ si pas disponible)
3. Cliquer sur "Tester saveEmployee()"
4. Observer les logs étape par étape

---

## 🔍 Analyse du Code

### Vérifications Effectuées

#### 1. Structure du Formulaire (`views/employees.php`)
✅ **CORRECT** - Le formulaire a bien l'attribut `id="employee-form"` (ligne 128)
✅ **CORRECT** - Le bouton appelle bien `onclick="saveEmployee()"` (ligne 330)
✅ **CORRECT** - Tous les champs ont des ID corrects

#### 2. Fonction JavaScript (`assets/js/employees.js`)
✅ **CORRECT** - La fonction `saveEmployee()` est bien définie (lignes 240-321)
✅ **CORRECT** - La fonction contient des console.log pour le débogage
✅ **CORRECT** - La logique de collecte des données est correcte
✅ **CORRECT** - L'appel fetch() vers `assets/ajax/employees.php` est correct

#### 3. Chargement du Script
✅ **CORRECT** - Le script est chargé dans `views/employees.php` (ligne 377)
```html
<script src="assets/js/employees.js"></script>
```

---

## 🎯 Causes Possibles du Problème

### 1. JavaScript pas chargé ou erreur de syntaxe
**Symptômes:**
- Rien ne se passe quand on clique
- Pas de console.log() dans la console du navigateur
- Message d'erreur JavaScript dans la console

**Test:**
```
Ouvrir debug_employee_save.php
Cliquer "Lancer les tests"
Si "❌ Test 4: Fonction saveEmployee() N'EST PAS définie"
  → Le fichier employees.js n'est pas chargé correctement
```

**Solutions:**
- Vider le cache du navigateur (Ctrl + Shift + Delete)
- Vérifier la console du navigateur (F12) pour voir les erreurs
- Vérifier que `assets/js/employees.js` existe et est accessible

---

### 2. Validation du formulaire échoue
**Symptômes:**
- On voit dans la console: "⚠️ Formulaire invalide"
- Des champs requis sont vides ou mal formatés

**Test:**
```
Ouvrir debug_employee_save.php
Cliquer "Tester saveEmployee()"
Si on voit "⚠️ Formulaire invalide"
  → Remplir tous les champs obligatoires (*, required)
```

**Champs obligatoires:**
- ✅ Prénom (first_name)
- ✅ Nom (last_name)
- ✅ Date d'embauche (hire_date)
- ✅ Poste (job_title)
- ✅ Salaire de base (base_salary)

**Solutions:**
- Remplir tous les champs marqués avec *
- Vérifier le format des dates (YYYY-MM-DD)
- Vérifier que le salaire est un nombre > 0

---

### 3. API retourne une erreur
**Symptômes:**
- L'appel fetch() se fait
- Mais on reçoit success: false
- Message d'erreur dans data.message

**Test:**
```
Ouvrir debug_employee_save.php
Cliquer "Tester API directement"
Regarder la réponse dans la console
```

**Erreurs possibles:**
- ❌ "Non authentifié" → Session expirée, se reconnecter
- ❌ "Limite atteinte" → Le plan a atteint sa limite d'employés
- ❌ "Module non activé" → Le plan gratuit n'a pas accès
- ❌ Erreur SQL → Problème de base de données

**Solutions:**
- Se reconnecter à l'application
- Vérifier le plan d'abonnement
- Vérifier les tables de la base de données

---

### 4. Session expirée
**Symptômes:**
- API retourne "Non authentifié"
- Redirection vers la page de login

**Test:**
```
Ouvrir debug_employee_save.php
Vérifier la section "Informations de Session"
Si User ID: ❌ NON DÉFINI
  → Session expirée
```

**Solutions:**
- Se reconnecter à l'application
- Vérifier que le cookie de session est bien envoyé

---

### 5. Limite du plan atteinte
**Symptômes:**
- API retourne "Limite atteinte"
- Message: "Vous avez atteint la limite de X employés"

**Test:**
```
Ouvrir debug_employee_save.php
Vérifier la section "Informations de Session"
Ligne "Limites employés": 3 / 3 ⚠️ LIMITE ATTEINTE
```

**Solutions:**
- Passer à un plan supérieur (Professionnel ou Enterprise)
- Ou désactiver un employé existant avant d'en créer un nouveau

---

## 📝 Procédure de Débogage Étape par Étape

### Étape 1: Tests Automatiques
1. Ouvrir: `http://localhost/gestion_comptable/debug_employee_save.php`
2. Cliquer sur "Lancer les tests"
3. **Vérifier les résultats dans la console:**

**Résultats attendus:**
```
✅ Test 1: Formulaire trouvé (ID: employee-form)
✅ Test 2.first-name: Champ trouvé, valeur = "Jean"
✅ Test 2.last-name: Champ trouvé, valeur = "Dupont"
✅ Test 2.hire-date: Champ trouvé, valeur = "2024-01-15"
✅ Test 2.job-title: Champ trouvé, valeur = "Développeur"
✅ Test 2.base-salary: Champ trouvé, valeur = "5000"
✅ Test 3: Formulaire valide
✅ Test 4: Fonction saveEmployee() est définie
✅ Test 5.loadEmployees: Fonction définie
✅ Test 5.closeEmployeeModal: Fonction définie
✅ Test 5.showSuccess: Fonction définie
✅ Test 5.showError: Fonction définie
```

**Si tout est ✅:** Passer à l'étape 2
**Si un test ❌:** Noter quel test échoue et voir la section "Causes Possibles"

---

### Étape 2: Test de la fonction saveEmployee()
1. Dans la même page `debug_employee_save.php`
2. Cliquer sur "Tester saveEmployee()"
3. **Observer les logs:**

**Logs attendus:**
```
🔵 saveEmployee() appelée
✅ Formulaire trouvé
✅ Formulaire valide
📝 Action: create ID: nouveau
📤 [Envoi des données à l'API]
📥 Réponse reçue
✅ SUCCÈS: Employé créé avec succès
```

**Si erreur à une étape:** Noter où ça bloque et voir la section "Causes Possibles"

---

### Étape 3: Test de l'API directement
1. Dans la même page `debug_employee_save.php`
2. Cliquer sur "Tester API directement"
3. **Observer la réponse:**

**Réponse attendue:**
```
📤 Envoi des données: {...}
📥 Réponse reçue, status: 200
✅ SUCCÈS: Employé créé avec succès
   Données: {success: true, message: "...", data: {...}}
```

**Si erreur:**
```
❌ ERREUR: [message d'erreur]
```
→ Identifier le message d'erreur et voir la section "Causes Possibles"

---

### Étape 4: Vérifier dans la vraie page
1. Ouvrir l'application: `http://localhost/gestion_comptable/index.php`
2. Se connecter
3. Aller dans **Salaires > Employés**
4. **Ouvrir la console du navigateur (F12)**
5. Cliquer sur "Nouvel Employé"
6. Remplir le formulaire
7. Cliquer sur "Enregistrer"
8. **Observer les logs dans la console:**

**Logs attendus (grâce aux console.log ajoutés):**
```
🔵 saveEmployee() appelée
✅ Formulaire trouvé
✅ Formulaire valide
📝 Action: create ID: nouveau
```

**Si rien n'apparaît dans la console:**
→ La fonction n'est pas appelée → Vérifier que le bouton a bien onclick="saveEmployee()"

---

## 🚀 Tests Complémentaires

### Test 1: Vérifier les fichiers existent
```bash
# Dans le terminal
cd /c/xampp/htdocs/gestion_comptable
ls -la views/employees.php
ls -la assets/js/employees.js
ls -la assets/ajax/employees.php
```

### Test 2: Vérifier les erreurs PHP
```bash
# Vérifier les logs Apache
tail -f /c/xampp/apache/logs/error.log
```

### Test 3: Tester l'API avec curl
```bash
curl -X POST http://localhost/gestion_comptable/assets/ajax/employees.php \
  -H "Content-Type: application/json" \
  -b "COMPTAPP_SESSION=VOTRE_SESSION_ID" \
  -d '{
    "action": "create",
    "first_name": "Jean",
    "last_name": "Dupont",
    "hire_date": "2024-01-15",
    "job_title": "Développeur",
    "base_salary": 5000,
    "employment_type": "full_time",
    "contract_type": "cdi",
    "salary_type": "monthly",
    "currency": "CHF",
    "hours_per_week": 40,
    "is_active": 1
  }'
```

---

## 📊 Checklist de Diagnostic

- [ ] Ouvrir `debug_employee_save.php`
- [ ] Vérifier informations de session (User ID, Company ID, Tenant Code)
- [ ] Vérifier le plan d'abonnement (pas gratuit, limite pas atteinte)
- [ ] Cliquer "Lancer les tests" → Tous les tests ✅
- [ ] Cliquer "Tester saveEmployee()" → Logs corrects
- [ ] Cliquer "Tester API directement" → Réponse success: true
- [ ] Ouvrir la vraie page employés avec F12
- [ ] Remplir formulaire et cliquer Enregistrer
- [ ] Vérifier les logs dans console navigateur
- [ ] Si erreur → Noter le message exact
- [ ] Si succès → Employé créé et visible dans la liste

---

## 🎯 Prochaines Étapes

Une fois le problème identifié:

1. **Si problème JavaScript:** Corriger le fichier `employees.js`
2. **Si problème API:** Corriger le fichier `assets/ajax/employees.php`
3. **Si problème de session:** Vérifier la connexion et les variables de session
4. **Si problème de limites:** Upgrade du plan ou désactivation d'employés

---

## 📞 Informations de Support

**Fichiers de débogage créés:**
- `debug_employee_save.php` - Page de diagnostic complète
- `test_employee_form.html` - Formulaire de test HTML pur
- `test_employees_api.php` - Test API backend (déjà créé précédemment)

**Fichiers modifiés avec debug:**
- `assets/js/employees.js` - Ajout de console.log() dans saveEmployee()

**Tables de base de données:**
- `employees` - Table principale (tenant DB)
- `subscription_plans` - Limites par plan (master DB)

---

**Créé le:** <?php echo date('Y-m-d H:i:s'); ?>
**Version du module:** 1.0.0
**Status:** En débogage 🔧
