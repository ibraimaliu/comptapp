# Sélecteur de Société et Exercice Comptable

## Vue d'ensemble

Cette fonctionnalité ajoute une barre de sélection visible en haut de l'application permettant à l'utilisateur de :
1. **Choisir la société active** parmi toutes ses sociétés
2. **Sélectionner l'exercice comptable** (année fiscale) à consulter
3. **Accéder rapidement** à la gestion de ses sociétés

## Problème Résolu

### Problème Initial
- Les utilisateurs étaient connectés mais `$_SESSION['company_id']` n'était pas défini
- Impossibilité de créer des sociétés car aucune société active n'était sélectionnée
- Pas de moyen visible de changer de société ou d'exercice comptable
- Message d'erreur : "Tenant introuvable", "Limite atteinte" alors que le compte était actif

### Solution Implémentée
- ✅ **Sélection automatique** de la première société lors de la connexion
- ✅ **Barre de sélection visible** en haut de toutes les pages
- ✅ **Switch facile** entre sociétés et exercices
- ✅ **Alerte claire** si aucune société n'existe
- ✅ **Lien direct** vers la création/gestion de sociétés

## Architecture

### Emplacement de la Barre

```
┌─────────────────────────────────────────────────────────────┐
│  Menu Latéral  │  [Société: ABC Sàrl ▼] [Exercice: 2025 ▼] ⚙️ │
│   (250px)      │                                              │
│                │  Contenu de la page                          │
│                │                                              │
└─────────────────────────────────────────────────────────────┘
```

- **Position** : Fixe en haut, à droite du menu latéral
- **Left** : 250px (largeur du menu)
- **Right** : 0
- **Z-index** : 999 (au-dessus du contenu, sous les modales)

## Composants

### 1. Sélecteur de Société

**Code HTML** :
```php
<select id="company-selector" class="selector-dropdown" onchange="switchCompany(this.value)">
    <?php if (empty($_SESSION['company_id'])): ?>
        <option value="">-- Sélectionnez une société --</option>
    <?php endif; ?>
    <?php foreach ($companies as $comp): ?>
        <option value="<?php echo $comp['id']; ?>"
                <?php echo (isset($_SESSION['company_id']) && $_SESSION['company_id'] == $comp['id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($comp['name']); ?>
        </option>
    <?php endforeach; ?>
</select>
```

**Données Affichées** :
- Liste de toutes les sociétés de l'utilisateur (`WHERE user_id = :user_id`)
- Société actuellement sélectionnée marquée avec `selected`
- Tri par nom alphabétique

### 2. Sélecteur d'Exercice Comptable

**Code HTML** :
```php
<select id="fiscal-year-selector" class="selector-dropdown" onchange="switchFiscalYear(this.value)">
    <?php foreach ($fiscal_years as $fy): ?>
        <option value="<?php echo $fy['year']; ?>"
                <?php echo ($fy['year'] == date('Y')) ? 'selected' : ''; ?>>
            <?php echo $fy['label']; ?>
        </option>
    <?php endforeach; ?>
</select>
```

**Exercices Générés** :
- Année courante -2 jusqu'à +2 (5 années au total)
- Exemple en 2025 : 2023, 2024, **2025**, 2026, 2027
- Année courante sélectionnée par défaut
- Format : "Exercice YYYY"

### 3. Bouton de Gestion

**Code HTML** :
```html
<a href="index.php?page=mes_societes" class="btn-manage-companies" title="Gérer mes sociétés">
    <i class="fa-solid fa-gear"></i>
</a>
```

**Fonction** :
- Redirige vers la page de gestion multi-sociétés
- Icône engrenage pour clarté
- Tooltip au survol

### 4. Alerte "Aucune Société"

**Affichage** :
- Si l'utilisateur est connecté MAIS n'a aucune société créée
- Remplace la barre de sélection
- Style gradient orange-rouge (alerte)

**Code HTML** :
```html
<div class="no-company-alert">
    <div class="alert-content">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <span>Vous devez créer une société pour utiliser l'application.</span>
        <a href="society_setup.php" class="btn-create-company">
            <i class="fa-solid fa-plus"></i> Créer ma première société
        </a>
    </div>
</div>
```

## Flux Utilisateur

### Scénario 1 : Première Connexion (Aucune Société)

```
Utilisateur se connecte
    ↓
Login API: $_SESSION['user_id'] = 1
Login API: Aucune société trouvée → company_id NOT SET
    ↓
Page chargée → includes/header.php
    ↓
Requête: SELECT * FROM companies WHERE user_id = 1
Résultat: [] (vide)
    ↓
Affichage: Alerte "Vous devez créer une société"
Bouton: "Créer ma première société"
    ↓
Utilisateur clique → Redirigé vers society_setup.php
```

### Scénario 2 : Connexion avec Société Existante

```
Utilisateur se connecte
    ↓
Login API: $_SESSION['user_id'] = 1
Login API: Recherche première société
Query: SELECT id FROM companies WHERE user_id = 1 ORDER BY created_at ASC LIMIT 1
Résultat: company_id = 5
    ↓
Login API: $_SESSION['company_id'] = 5
Login API: $_SESSION['fiscal_year'] = 2025
    ↓
Page chargée → includes/header.php
    ↓
Requête: SELECT * FROM companies WHERE user_id = 1
Résultat: [Société A, Société B, Société C]
    ↓
Affichage:
┌────────────────────────────────────────────────┐
│ [Société: Société A ▼] [Exercice: 2025 ▼] ⚙️  │
└────────────────────────────────────────────────┘
    ↓
Toutes les pages affichent les données de la Société A pour l'exercice 2025
```

### Scénario 3 : Changement de Société

```
Utilisateur clique sur le sélecteur de société
    ↓
Sélectionne "Société B" (ID: 7)
    ↓
JavaScript: switchCompany(7) appelé
    ↓
AJAX POST vers api/session.php:
{
    "action": "change_company",
    "company_id": 7
}
    ↓
API vérifie:
1. company_id est numérique ✓
2. Utilisateur a accès à la société 7 ✓
    ↓
API met à jour: $_SESSION['company_id'] = 7
    ↓
Réponse: {"success": true}
    ↓
JavaScript: window.location.reload()
    ↓
Page rechargée → Toutes les données affichent maintenant la Société B
```

### Scénario 4 : Changement d'Exercice Comptable

```
Utilisateur clique sur le sélecteur d'exercice
    ↓
Sélectionne "Exercice 2024"
    ↓
JavaScript: switchFiscalYear(2024) appelé
    ↓
AJAX POST vers api/session.php:
{
    "action": "change_fiscal_year",
    "fiscal_year": 2024
}
    ↓
API vérifie:
1. fiscal_year est numérique ✓
2. Format YYYY (4 chiffres) ✓
    ↓
API met à jour: $_SESSION['fiscal_year'] = 2024
    ↓
Réponse: {"success": true, "fiscal_year": 2024}
    ↓
JavaScript: window.location.reload()
    ↓
Page rechargée → Toutes les données filtrées pour l'année 2024
```

## Fichiers Modifiés/Créés

### 1. includes/header.php

**Lignes ajoutées** : ~165 lignes

**Modifications** :
```php
// Avant </head>
<body>
    <?php if(isLoggedIn()): ?>
    <div class="menus">

// Après
<body>
    <?php if(isLoggedIn()): ?>
    <!-- Sélecteur de Société et Exercice Comptable -->
    <?php
    // Récupération des sociétés
    $companies_query = "SELECT * FROM companies WHERE user_id = :user_id ORDER BY name";
    // ...
    ?>

    <!-- Barre de sélection OU alerte -->
    <?php if (!empty($companies)): ?>
        <div class="company-selector-bar">...</div>
    <?php elseif (isset($_SESSION['user_id']) && empty($companies)): ?>
        <div class="no-company-alert">...</div>
    <?php endif; ?>

    <div class="menus">
```

**Scripts ajoutés** :
- `switchCompany(companyId)` - Change la société active
- `switchFiscalYear(year)` - Change l'exercice comptable

### 2. assets/css/style.css

**Lignes ajoutées** : ~155 lignes

**Sections ajoutées** :
```css
/* ========================================
   SÉLECTEUR DE SOCIÉTÉ ET EXERCICE
   ======================================== */
.company-selector-bar { ... }
.selector-container { ... }
.selector-group { ... }
.selector-dropdown { ... }
.selector-actions { ... }
.btn-manage-companies { ... }

/* Alerte pour utilisateurs sans société */
.no-company-alert { ... }
.alert-content { ... }
.btn-create-company { ... }

/* Ajuster le contenu */
body:has(.company-selector-bar) .content,
body:has(.no-company-alert) .content {
    margin-top: 60px;
}
```

### 3. api/tenant_login.php

**Lignes ajoutées** : 13 lignes

**Modification** :
```php
// Après la création de session (ligne ~148)

// AUTO-SÉLECTIONNER LA PREMIÈRE SOCIÉTÉ SI L'UTILISATEUR EN A UNE
$company_query = "SELECT id FROM companies WHERE user_id = :user_id ORDER BY created_at ASC LIMIT 1";
$company_stmt = $tenant_db->prepare($company_query);
$company_stmt->bindParam(':user_id', $user['id']);
$company_stmt->execute();
$first_company = $company_stmt->fetch(PDO::FETCH_ASSOC);

if ($first_company) {
    $_SESSION['company_id'] = $first_company['id'];
}

// Initialiser l'exercice comptable par défaut à l'année courante
$_SESSION['fiscal_year'] = date('Y');
```

**Impact** :
- ✅ Utilisateur n'a plus besoin de sélectionner manuellement sa société après connexion
- ✅ Évite l'erreur "company_id non défini"
- ✅ Exercice comptable initialisé automatiquement

### 4. api/session.php

**Lignes ajoutées** : 23 lignes (nouveau case)

**Modification** :
```php
// Ajout du case 'change_fiscal_year' après 'change_company'

case 'change_fiscal_year':
    if (!isset($data['fiscal_year'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Année fiscale manquante']);
        exit;
    }

    // Vérifier que l'année est valide (format YYYY)
    if (!is_numeric($data['fiscal_year']) || strlen((string)$data['fiscal_year']) !== 4) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Année fiscale invalide']);
        exit;
    }

    // Mettre à jour la session
    $_SESSION['fiscal_year'] = (int)$data['fiscal_year'];
    error_log('Année fiscale mise à jour: ' . $_SESSION['fiscal_year']);

    // Répondre avec succès
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'fiscal_year' => $_SESSION['fiscal_year']]);
    break;
```

**API Endpoints** :

| Action | Méthode | Body | Réponse |
|--------|---------|------|---------|
| Changer de société | POST | `{"action": "change_company", "company_id": 5}` | `{"success": true}` |
| Changer d'exercice | POST | `{"action": "change_fiscal_year", "fiscal_year": 2024}` | `{"success": true, "fiscal_year": 2024}` |

## Variables de Session

### Nouvelles Variables

| Variable | Type | Description | Défini par |
|----------|------|-------------|------------|
| `$_SESSION['company_id']` | int | ID de la société active | Login API / Changement manuel |
| `$_SESSION['fiscal_year']` | int | Année fiscale sélectionnée | Login API / Changement manuel |

### Variables Existantes Utilisées

| Variable | Utilisé pour |
|----------|--------------|
| `$_SESSION['user_id']` | Filtrer les sociétés de l'utilisateur |
| `$_SESSION['tenant_database']` | Connexion à la base tenant |
| `$_SESSION['subscription_plan']` | Affichage du plan (optionnel) |

## Utilisation dans les Requêtes

### Filtrer par Société Active

**Avant** (risque d'erreur) :
```php
$query = "SELECT * FROM transactions WHERE company_id = :company_id";
// Si company_id non défini → Erreur
```

**Après** (garanti fonctionnel) :
```php
// Vérifier d'abord
if (!isset($_SESSION['company_id'])) {
    die('Veuillez sélectionner une société');
}

$query = "SELECT * FROM transactions WHERE company_id = :company_id";
$stmt->bindParam(':company_id', $_SESSION['company_id']);
```

### Filtrer par Exercice Comptable

```php
// Utiliser l'année fiscale pour filtrer les transactions
$fiscal_year = $_SESSION['fiscal_year'] ?? date('Y');

$query = "SELECT * FROM transactions
          WHERE company_id = :company_id
          AND YEAR(date) = :fiscal_year
          ORDER BY date DESC";

$stmt->bindParam(':company_id', $_SESSION['company_id']);
$stmt->bindParam(':fiscal_year', $fiscal_year, PDO::PARAM_INT);
```

## Design et UX

### Palette de Couleurs

**Barre de sélection** :
- Background : `linear-gradient(135deg, #667eea 0%, #764ba2 100%)`
- Texte : Blanc (#ffffff)
- Bordures : `rgba(255, 255, 255, 0.3)`

**Dropdown** :
- Normal : `rgba(255, 255, 255, 0.15)`
- Hover : `rgba(255, 255, 255, 0.25)`
- Focus : `rgba(255, 255, 255, 0.3)` + box-shadow

**Alerte "Aucune société"** :
- Background : `linear-gradient(135deg, #f59e0b 0%, #dc2626 100%)`
- Bouton : Blanc avec texte rouge (#dc2626)

### Responsive Design

**Desktop (>1024px)** :
- Sélecteurs côte à côte
- Bouton de gestion à droite

**Tablet (768-1024px)** :
- Même layout, espacement réduit

**Mobile (<768px)** :
- À implémenter : Sélecteurs empilés
- Menu latéral caché/toggle

### États Visuels

**Dropdown Normal** :
```
┌─────────────────────┐
│ Société ABC Sàrl  ▼ │
└─────────────────────┘
```

**Dropdown Hover** :
```
┌─────────────────────┐
│ Société ABC Sàrl  ▼ │  (fond plus clair)
└─────────────────────┘
```

**Dropdown Focus** :
```
╔═════════════════════╗
║ Société ABC Sàrl  ▼ ║  (bordure blanche + glow)
╚═════════════════════╝
```

**Dropdown Ouvert** :
```
┌─────────────────────┐
│ Société ABC Sàrl  ▲ │
├─────────────────────┤
│ Société ABC Sàrl    │ ← sélectionné
│ Société XYZ SA      │
│ Test Company Ltd    │
└─────────────────────┘
```

## Sécurité

### Vérifications Implémentées

**1. Accès à la société** :
```php
// Dans api/session.php
$company = new Company($db);
if (!$company->userHasAccess($_SESSION['user_id'], $data['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit;
}
```

**2. Validation des entrées** :
```php
// Vérifier que company_id est numérique
if (!is_numeric($data['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID invalide']);
    exit;
}

// Vérifier format de l'année (YYYY)
if (strlen((string)$data['fiscal_year']) !== 4) {
    echo json_encode(['success' => false, 'message' => 'Année invalide']);
    exit;
}
```

**3. Isolation des données** :
```php
// Toujours filtrer par user_id
$query = "SELECT * FROM companies WHERE user_id = :user_id";
// Un utilisateur ne voit QUE ses sociétés
```

### CSRF Protection

**À implémenter** (optionnel) :
```javascript
// Ajouter token CSRF aux requêtes
fetch('api/session.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?php echo csrfToken(); ?>'
    },
    body: JSON.stringify(data)
})
```

## Tests

### Test 1 : Connexion Sans Société

**Étapes** :
1. Créer un utilisateur sans société
2. Se connecter via login_tenant.php
3. Vérifier l'affichage de l'alerte orange
4. Cliquer sur "Créer ma première société"
5. Vérifier redirection vers society_setup.php

**Résultat attendu** :
- ✅ Alerte visible
- ✅ Bouton fonctionnel
- ✅ Redirection correcte

### Test 2 : Connexion Avec 1 Société

**Étapes** :
1. Utilisateur avec 1 société existante
2. Se connecter
3. Vérifier que `$_SESSION['company_id']` est défini
4. Vérifier que le sélecteur affiche la société

**Résultat attendu** :
- ✅ `company_id` = ID de la société
- ✅ Sélecteur affiche le nom
- ✅ Aucune option "-- Sélectionnez --"

### Test 3 : Connexion Avec 3 Sociétés

**Étapes** :
1. Utilisateur avec 3 sociétés (A, B, C)
2. Se connecter (société A sélectionnée automatiquement)
3. Ouvrir le sélecteur → Vérifier les 3 options
4. Sélectionner société B
5. Vérifier rechargement et données de B affichées

**Résultat attendu** :
- ✅ 3 sociétés listées
- ✅ Société A pré-sélectionnée
- ✅ Changement vers B fonctionne
- ✅ Rechargement automatique

### Test 4 : Changement d'Exercice

**Étapes** :
1. Se connecter (exercice 2025 par défaut)
2. Ouvrir sélecteur d'exercice
3. Vérifier années 2023-2027 disponibles
4. Sélectionner 2024
5. Vérifier `$_SESSION['fiscal_year']` = 2024

**Résultat attendu** :
- ✅ 5 années affichées
- ✅ 2025 pré-sélectionné
- ✅ Changement vers 2024 fonctionne
- ✅ Session mise à jour

### Test 5 : Sécurité - Accès Non Autorisé

**Étapes** :
1. Utilisateur A connecté (sociétés 1, 2)
2. Via console, tenter POST :
   ```javascript
   fetch('api/session.php', {
       method: 'POST',
       body: JSON.stringify({
           action: 'change_company',
           company_id: 999 // Société d'un autre utilisateur
       })
   })
   ```
3. Vérifier réponse d'erreur

**Résultat attendu** :
- ✅ Réponse : `{"success": false, "message": "Accès non autorisé"}`
- ✅ Session non modifiée

## Problèmes Résolus

### Problème 1 : "Tenant introuvable"

**Erreur** :
```
Limite de sociétés (Plan: Inconnu)
Sociétés actives: 0 / 0
⚠️ Limite atteinte
Tenant introuvable
```

**Cause** :
- `$_SESSION['company_id']` n'était pas défini après connexion
- TenantLimits.php cherchait des sociétés mais sans contexte

**Solution** :
- Auto-sélection de la première société dans login API
- Sélecteur visible pour changer de société

### Problème 2 : Impossible de Créer une Société

**Erreur** :
- Formulaire de création bloqué
- Message "Limite atteinte" alors que 0 société créée

**Cause** :
- Vérification des limites échouait sans `company_id`

**Solution** :
- `company_id` toujours défini après connexion
- Alerte claire si aucune société n'existe

### Problème 3 : Pas de Moyen de Changer de Société

**Problème** :
- Utilisateur bloqué sur la première société créée
- Devait aller dans "Mes Sociétés" pour changer

**Solution** :
- Sélecteur toujours visible en haut de page
- Changement immédiat sans navigation

## Améliorations Futures

### Phase 1 (Actuel) ✅
- [x] Sélecteur de société
- [x] Sélecteur d'exercice comptable
- [x] Auto-sélection au login
- [x] Alerte si aucune société
- [x] API de changement

### Phase 2 (À venir)
- [ ] Badge "Active" plus visible
- [ ] Indicateur de nombre de sociétés (2/3)
- [ ] Raccourci clavier (Ctrl+K)
- [ ] Recherche de société (si >5 sociétés)
- [ ] Dernières sociétés consultées

### Phase 3 (Avancé)
- [ ] Mode multi-onglets (plusieurs sociétés ouvertes)
- [ ] Comparaison côte à côte
- [ ] Exercice personnalisé (début/fin custom)
- [ ] Vue consolidée (toutes sociétés)
- [ ] Favoris/épingles

## Support

### Questions Fréquentes

**Q: Pourquoi je ne vois pas le sélecteur ?**
R: Vérifiez que vous êtes connecté (`$_SESSION['user_id']` défini) et que vous avez au moins une société.

**Q: Le sélecteur est vide**
R: Vérifiez la requête SQL dans header.php. Les sociétés doivent avoir `user_id` correspondant.

**Q: Le changement ne fonctionne pas**
R: Vérifiez la console JavaScript pour les erreurs. L'API `api/session.php` doit être accessible.

**Q: L'exercice ne filtre pas les données**
R: Les pages doivent utiliser `$_SESSION['fiscal_year']` dans leurs requêtes SQL. Ajoutez `WHERE YEAR(date) = :fiscal_year`.

**Q: CSS cassé après ajout**
R: Vérifiez que `assets/css/style.css` est bien inclus et que le cache est vidé.

### Debug

**Vérifier la session** :
```php
// Créer debug_session.php
<?php
session_name('COMPTAPP_SESSION');
session_start();
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
?>
```

**Vérifier les sociétés** :
```sql
-- Dans PhpMyAdmin
SELECT * FROM companies WHERE user_id = 1;
```

**Vérifier les logs** :
```bash
# Dans Apache error.log
tail -f /xampp/apache/logs/error.log | grep "société\|company"
```

---

**Dernière mise à jour** : 23 Novembre 2025
**Version** : 1.0
**Fichiers principaux** :
- `includes/header.php` (widget)
- `assets/css/style.css` (styles)
- `api/session.php` (API)
- `api/tenant_login.php` (auto-sélection)
