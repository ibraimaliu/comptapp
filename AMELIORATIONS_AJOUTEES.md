# Améliorations Ajoutées - Gestion Comptable

**Date**: 2025-10-19
**Fichier modifié**: `config/config.php`

---

## 🎉 Résumé des Améliorations

L'application de gestion comptable a été améliorée avec des fonctions de sécurité, validation et utilitaires essentiels. Ces améliorations rendent l'application **plus sécurisée** et **plus facile à utiliser**.

---

## 🔒 Sécurité CSRF (Cross-Site Request Forgery)

### Fonctions Ajoutées

#### 1. `generateCSRFToken()`
Génère un token CSRF sécurisé et le stocke en session.

**Utilisation**:
```php
$token = generateCSRFToken();
```

#### 2. `verifyCSRFToken($token)`
Vérifie qu'un token CSRF est valide.

**Utilisation**:
```php
if (!verifyCSRFToken($_POST['csrf_token'])) {
    die('Token CSRF invalide');
}
```

#### 3. `csrfField()`
Génère un champ input hidden pour les formulaires.

**Utilisation dans un formulaire HTML**:
```php
<form method="POST">
    <?php echo csrfField(); ?>
    <input type="text" name="name">
    <button type="submit">Envoyer</button>
</form>
```

#### 4. `csrfToken()`
Retourne le token pour utilisation AJAX.

**Utilisation AJAX**:
```javascript
fetch('/api/endpoint.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?php echo csrfToken(); ?>'
    },
    body: JSON.stringify(data)
})
```

### Comment Intégrer CSRF

**Dans les formulaires existants** (exemple: `views/login.php`):

AVANT:
```html
<form method="POST">
    <input type="text" name="username">
    <input type="password" name="password">
    <button type="submit">Connexion</button>
</form>
```

APRÈS:
```php
<form method="POST">
    <?php echo csrfField(); ?>
    <input type="text" name="username">
    <input type="password" name="password">
    <button type="submit">Connexion</button>
</form>
```

**Dans les API** (exemple: `api/auth.php`):

AVANT:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Traiter les données
}
```

APRÈS:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
        exit;
    }

    // Traiter les données
}
```

---

## 💬 Messages Flash

Les messages flash permettent d'afficher des notifications à l'utilisateur après une action.

### Fonctions Ajoutées

#### 1. `setFlash($type, $message)`
Définit un message flash.

**Types disponibles**: `success`, `error`, `warning`, `info`

**Utilisation**:
```php
// Après avoir créé une transaction
setFlash('success', 'Transaction créée avec succès !');
redirect('index.php?page=comptabilite');
```

#### 2. `getFlash($type)`
Récupère et supprime un message flash.

**Utilisation**:
```php
$message = getFlash('success');
if ($message) {
    echo '<div class="alert alert-success">' . $message . '</div>';
}
```

#### 3. `hasFlash($type)`
Vérifie si un message flash existe.

**Utilisation**:
```php
if (hasFlash('error')) {
    // Il y a un message d'erreur
}
```

#### 4. `displayFlash()`
Affiche automatiquement tous les messages flash en HTML.

**Utilisation dans les vues** (à ajouter dans `includes/header.php` après le `<body>`):
```php
<?php echo displayFlash(); ?>
```

### Exemples d'Utilisation

**Dans un contrôleur**:
```php
// controllers/ContactsController.php
public function store() {
    // ... validation et sauvegarde

    if ($contact->create($data)) {
        setFlash('success', 'Contact créé avec succès !');
        redirect('index.php?page=adresses');
    } else {
        setFlash('error', 'Erreur lors de la création du contact');
        redirect('index.php?page=nouvelle_adresse');
    }
}
```

**Dans une API**:
```php
// api/contact.php
if ($contact->update()) {
    setFlash('success', 'Contact mis à jour');
    echo json_encode(['success' => true]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de mise à jour'
    ]);
}
```

---

## ✅ Fonctions de Validation

### Fonctions Ajoutées

#### 1. `validateEmail($email)`
Valide un email.

**Utilisation**:
```php
if (!validateEmail($_POST['email'])) {
    setFlash('error', 'Email invalide');
}
```

#### 2. `validateMinLength($value, $min)`
Vérifie la longueur minimale.

**Utilisation**:
```php
if (!validateMinLength($_POST['password'], 8)) {
    setFlash('error', 'Le mot de passe doit contenir au moins 8 caractères');
}
```

#### 3. `validateMaxLength($value, $max)`
Vérifie la longueur maximale.

**Utilisation**:
```php
if (!validateMaxLength($_POST['name'], 100)) {
    setFlash('error', 'Le nom est trop long (max 100 caractères)');
}
```

#### 4. `validateRequired($value)`
Vérifie qu'un champ n'est pas vide.

**Utilisation**:
```php
if (!validateRequired($_POST['company_name'])) {
    setFlash('error', 'Le nom de l\'entreprise est requis');
}
```

#### 5. `validateAmount($amount)`
Valide un montant (nombre positif).

**Utilisation**:
```php
if (!validateAmount($_POST['amount'])) {
    setFlash('error', 'Le montant doit être un nombre positif');
}
```

#### 6. `validateDate($date)`
Valide une date au format Y-m-d.

**Utilisation**:
```php
if (!validateDate($_POST['transaction_date'])) {
    setFlash('error', 'Date invalide (format requis: AAAA-MM-JJ)');
}
```

### Exemple Complet de Validation

```php
// api/company.php - Création d'entreprise
function validateCompanyData($data) {
    $errors = [];

    if (!validateRequired($data['name'])) {
        $errors[] = 'Le nom de l\'entreprise est requis';
    } elseif (!validateMaxLength($data['name'], 100)) {
        $errors[] = 'Le nom est trop long';
    }

    if (!validateRequired($data['owner_name'])) {
        $errors[] = 'Le prénom du propriétaire est requis';
    }

    if (!validateRequired($data['owner_surname'])) {
        $errors[] = 'Le nom du propriétaire est requis';
    }

    if (!validateDate($data['fiscal_year_start'])) {
        $errors[] = 'Date de début d\'exercice invalide';
    }

    if (!validateDate($data['fiscal_year_end'])) {
        $errors[] = 'Date de fin d\'exercice invalide';
    }

    return $errors;
}

// Utilisation
$errors = validateCompanyData($_POST);
if (!empty($errors)) {
    foreach ($errors as $error) {
        setFlash('error', $error);
    }
    redirect('index.php?page=society_setup');
}
```

---

## 🛠️ Fonctions Utilitaires

### 1. Formatage

#### `formatAmount($amount, $decimals = 2)`
Formate un montant avec séparateurs.

**Utilisation**:
```php
echo formatAmount(1234.56); // Affiche: 1 234,56
echo formatAmount(1234.567, 3); // Affiche: 1 234,567
```

#### `formatDate($date, $format = 'd/m/Y')`
Formate une date pour affichage.

**Utilisation**:
```php
echo formatDate('2025-10-19'); // Affiche: 19/10/2025
echo formatDate('2025-10-19', 'd F Y'); // Affiche: 19 octobre 2025
```

### 2. Gestion des Entreprises

#### `getActiveCompanyId()`
Récupère l'ID de l'entreprise active.

**Utilisation**:
```php
$company_id = getActiveCompanyId();
if (!$company_id) {
    redirect('index.php?page=society_setup');
}
```

#### `setActiveCompanyId($company_id)`
Définit l'entreprise active.

**Utilisation**:
```php
setActiveCompanyId($_POST['company_id']);
```

#### `hasActiveCompany()`
Vérifie si une entreprise est sélectionnée.

**Utilisation**:
```php
if (!hasActiveCompany()) {
    setFlash('warning', 'Veuillez sélectionner une entreprise');
    redirect('index.php?page=home');
}
```

### 3. Sécurité

#### `sanitize($string)`
Nettoie une chaîne HTML.

**Utilisation**:
```php
$safe_name = sanitize($_POST['name']);
echo "Nom: " . $safe_name;
```

### 4. Utilisateur

#### `getUserId()`
Récupère l'ID de l'utilisateur connecté.

**Utilisation**:
```php
$user_id = getUserId();
if (!$user_id) {
    redirect('index.php?page=login');
}
```

#### `getUsername()`
Récupère le nom d'utilisateur connecté.

**Utilisation**:
```php
echo "Bienvenue, " . getUsername();
```

---

## 🔐 Sécurité des Sessions

### Session Timeout

**Constante définie**: `SESSION_TIMEOUT = 3600` (1 heure)

#### `checkSessionTimeout()`
Vérifie et applique le timeout de session.

**Utilisation** (à ajouter dans `index.php` après `session_start()`):
```php
// index.php
include_once 'config/config.php';

// Vérifier le timeout de session
if (isLoggedIn() && !checkSessionTimeout()) {
    setFlash('warning', 'Votre session a expiré. Veuillez vous reconnecter.');
    redirect('index.php?page=login');
}

include_once 'includes/header.php';
// ... reste du code
```

#### `regenerateSession()`
Régénère l'ID de session (protection contre session fixation).

**Utilisation** (après login réussi):
```php
// api/auth.php - après validation du login
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['email'] = $user['email'];
$_SESSION['last_activity'] = time();

// Régénérer l'ID de session pour la sécurité
regenerateSession();

echo json_encode(['success' => true]);
```

---

## 📝 Guide d'Intégration Rapide

### Étape 1: Ajouter CSRF aux Formulaires

Modifier **tous les formulaires** pour ajouter le token CSRF:

```php
<form method="POST" action="...">
    <?php echo csrfField(); ?>
    <!-- Vos champs existants -->
</form>
```

### Étape 2: Vérifier CSRF dans les APIs

Modifier **toutes les APIs** pour vérifier le token:

```php
// Au début de chaque fichier API
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token CSRF invalide']);
        exit;
    }
}
```

### Étape 3: Utiliser les Messages Flash

Dans `includes/header.php`, ajouter après `<body>`:

```php
<div class="container mt-3">
    <?php echo displayFlash(); ?>
</div>
```

### Étape 4: Ajouter Validation

Remplacer les vérifications basiques par les nouvelles fonctions:

AVANT:
```php
if (empty($_POST['email'])) {
    $error = "Email requis";
}
```

APRÈS:
```php
if (!validateRequired($_POST['email'])) {
    setFlash('error', 'Email requis');
}
if (!validateEmail($_POST['email'])) {
    setFlash('error', 'Email invalide');
}
```

### Étape 5: Activer Session Timeout

Dans `index.php`, après `include_once 'config/config.php'`:

```php
// Vérifier le timeout de session
if (isLoggedIn() && !checkSessionTimeout()) {
    setFlash('warning', 'Votre session a expiré. Veuillez vous reconnecter.');
    session_destroy();
    redirect('index.php?page=login');
}
```

---

## 🧪 Tests Recommandés

### Test CSRF Protection
1. Soumettre un formulaire sans token → devrait échouer
2. Soumettre avec un faux token → devrait échouer
3. Soumettre avec le bon token → devrait réussir

### Test Messages Flash
1. Créer une transaction → message de succès
2. Essayer avec données invalides → message d'erreur
3. Rafraîchir la page → message devrait disparaître

### Test Validation
1. Email invalide → rejeté
2. Mot de passe court → rejeté
3. Date invalide → rejetée
4. Montant négatif → rejeté

### Test Session Timeout
1. Se connecter
2. Attendre 1 heure (ou modifier `SESSION_TIMEOUT` à 60 secondes pour test)
3. Essayer d'accéder à une page → doit rediriger vers login

---

## 📊 Compatibilité

✅ **PHP Version**: 7.0+
✅ **Extensions requises**: PDO, session
✅ **Backward compatible**: Oui, toutes les fonctions existantes fonctionnent toujours

---

## 🎯 Prochaines Étapes Recommandées

1. **Intégrer CSRF** dans tous les formulaires et APIs ✅ Priorité HAUTE
2. **Utiliser Messages Flash** partout au lieu d'alertes JavaScript
3. **Valider toutes les entrées** avec les nouvelles fonctions
4. **Activer Session Timeout** dans index.php
5. **Tester l'application** complètement

---

## 📖 Documentation Complémentaire

- [STATUS_APPLICATION.md](STATUS_APPLICATION.md) - État complet de l'application
- [CLAUDE.md](../CLAUDE.md) - Guide développeur général
- [config/config.php](config/config.php) - Fichier modifié avec toutes les fonctions

---

**Date**: 2025-10-19
**Statut**: ✅ **Améliorations Appliquées avec Succès**

*Votre application est maintenant plus sécurisée et plus robuste !*
