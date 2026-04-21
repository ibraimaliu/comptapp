# Documentation du Système Multi-Utilisateurs avec Permissions

## Vue d'ensemble

Le système multi-utilisateurs avec gestion des rôles et permissions (RBAC - Role-Based Access Control) permet de gérer plusieurs utilisateurs par entreprise avec différents niveaux d'accès.

**Date de création**: 2025-01-19
**Version**: 1.0
**Type**: Fonctionnalité multi-tenant

---

## Table des matières

1. [Architecture](#architecture)
2. [Rôles et Permissions](#rôles-et-permissions)
3. [Installation](#installation)
4. [Utilisation](#utilisation)
5. [API](#api)
6. [Sécurité](#sécurité)
7. [Dépannage](#dépannage)

---

## Architecture

### Tables de Base de Données

Le système utilise 5 tables principales:

#### 1. `roles`
Stocke les rôles disponibles dans le système.

```sql
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,              -- Nom technique (admin, accountant, reader)
  `display_name` varchar(100) NOT NULL,     -- Nom affiché
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
);
```

#### 2. `permissions`
Stocke les permissions granulaires.

```sql
CREATE TABLE `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,              -- Ex: users.create, invoices.edit
  `display_name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `module` varchar(50) DEFAULT NULL,        -- users, invoices, accounting, etc.
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
);
```

#### 3. `role_permissions`
Table de liaison many-to-many entre rôles et permissions.

```sql
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_permission` (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
);
```

#### 4. `user_invitations`
Stocke les invitations en attente.

```sql
CREATE TABLE `user_invitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL UNIQUE,      -- Token de validation
  `invited_by` int(11) NOT NULL,
  `expires_at` datetime NOT NULL,           -- Expire après 7 jours
  `accepted_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

#### 5. `user_activity_logs`
Piste d'audit pour toutes les actions utilisateurs.

```sql
CREATE TABLE `user_activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,           -- invite_user, update_user, etc.
  `module` varchar(50) DEFAULT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
);
```

### Table `users` (Mise à jour)

La table users a été mise à jour pour inclure les champs de gestion des rôles:

```sql
ALTER TABLE `users`
ADD COLUMN `company_id` int(11) DEFAULT NULL AFTER `id`,
ADD COLUMN `role_id` int(11) DEFAULT NULL AFTER `password`,
ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1 AFTER `role_id`,
ADD COLUMN `last_login_at` timestamp NULL DEFAULT NULL AFTER `is_active`,
ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
```

---

## Rôles et Permissions

### Rôles par Défaut

Le système comprend 3 rôles prédéfinis:

#### 1. Administrateur (`admin`)
**Accès complet** à toutes les fonctionnalités.

**Permissions** (29 au total):
- `users.*` - Gestion complète des utilisateurs
- `companies.*` - Gestion de l'entreprise
- `accounting.*` - Toutes les opérations comptables
- `chart_of_accounts.*` - Gestion du plan comptable
- `contacts.*` - Gestion complète des contacts
- `invoices.*` - Gestion complète des factures
- `quotes.*` - Gestion complète des devis
- `reports.*` - Accès et export des rapports
- `settings.*` - Configuration du système

**Cas d'usage**: Propriétaire de l'entreprise, responsable IT

#### 2. Comptable (`accountant`)
**Accès aux fonctionnalités comptables et financières**.

**Permissions** (17):
- `companies.view` - Voir les informations de l'entreprise
- `accounting.view`, `accounting.create`, `accounting.edit` - Gestion des écritures
- `chart_of_accounts.view` - Consultation du plan comptable
- `contacts.view`, `contacts.create`, `contacts.edit` - Gestion des contacts
- `invoices.view`, `invoices.create`, `invoices.edit`, `invoices.send` - Gestion des factures
- `quotes.view`, `quotes.create`, `quotes.edit` - Gestion des devis
- `reports.view`, `reports.export` - Consultation et export des rapports
- `settings.view` - Consultation des paramètres

**Cas d'usage**: Comptable, assistant administratif

#### 3. Lecteur (`reader`)
**Accès en lecture seule**.

**Permissions** (7):
- `companies.view`
- `accounting.view`
- `chart_of_accounts.view`
- `contacts.view`
- `invoices.view`
- `quotes.view`
- `reports.view`

**Cas d'usage**: Consultant externe, auditeur, stagiaire

### Système de Permissions

Les permissions suivent la convention de nommage: `{module}.{action}`

**Modules disponibles**:
- `users` - Gestion des utilisateurs
- `companies` - Informations de l'entreprise
- `accounting` - Écritures comptables
- `chart_of_accounts` - Plan comptable
- `contacts` - Clients et fournisseurs
- `invoices` - Facturation
- `quotes` - Devis
- `reports` - Rapports
- `settings` - Paramètres système

**Actions disponibles**:
- `view` - Consulter
- `create` - Créer
- `edit` - Modifier
- `delete` - Supprimer
- `send` - Envoyer (pour invoices)
- `export` - Exporter (pour reports)

---

## Installation

### Installation sur Nouveaux Tenants

Le système de rôles et permissions est **automatiquement installé** lors de la création d'un nouveau tenant.

Le premier utilisateur reçoit automatiquement le rôle **admin**.

### Installation sur Tenants Existants

Pour ajouter le système de rôles à un tenant existant:

#### Option 1: Installation Automatique (Tous les tenants)

```bash
php install_roles_and_permissions.php
```

Choisir 'y' pour installer sur tous les tenants actifs.

#### Option 2: Installation Manuelle (Un tenant spécifique)

```bash
php install_roles_and_permissions.php gestion_comptable_client_XXXXX
```

Remplacer `XXXXX` par le code tenant.

#### Option 3: Installation SQL

```bash
mysql -u root -pAbil gestion_comptable_client_XXXXX < install_user_roles.sql
```

### Vérification de l'Installation

Après l'installation, vérifier que:

1. Les 3 rôles ont été créés:
```sql
SELECT * FROM roles;
```

2. Les 29 permissions ont été créées:
```sql
SELECT COUNT(*) FROM permissions;
```

3. Le premier utilisateur a le rôle admin:
```sql
SELECT u.username, r.name as role
FROM users u
LEFT JOIN roles r ON u.role_id = r.id
WHERE u.id = 1;
```

---

## Utilisation

### Accès à l'Interface de Gestion

**URL**: `http://localhost/gestion_comptable/index.php?page=users_management`

**Prérequis**:
- Être connecté en mode multi-tenant
- Avoir la permission `users.view`

### Inviter un Utilisateur

1. Cliquer sur **"Inviter un utilisateur"**
2. Saisir l'email du nouvel utilisateur
3. Sélectionner le rôle approprié
4. Cliquer sur **"Envoyer l'invitation"**

Le système génère un **lien d'invitation unique** valide pendant **7 jours**.

**Note**: L'envoi d'email automatique n'est pas encore implémenté. Le lien d'invitation est retourné dans la réponse API.

### Modifier un Utilisateur

1. Cliquer sur l'icône **"Modifier"** (crayon) dans la liste
2. Changer le rôle et/ou le statut
3. Cliquer sur **"Mettre à jour"**

**Restrictions**:
- On ne peut pas se désactiver soi-même
- On ne peut pas modifier son propre rôle (nécessite un autre admin)

### Supprimer un Utilisateur

1. Cliquer sur l'icône **"Supprimer"** (poubelle)
2. Confirmer la suppression

**Restrictions**:
- On ne peut pas se supprimer soi-même
- Nécessite la permission `users.delete`

### Voir les Permissions d'un Utilisateur

Cliquer sur l'icône **"Clé"** pour voir toutes les permissions d'un utilisateur.

(Cette page nécessite le rôle **admin**)

---

## API

Toutes les requêtes API doivent être envoyées à: `assets/ajax/users_management.php`

### Inviter un Utilisateur

**Action**: `invite`
**Méthode**: POST
**Permission requise**: `users.create`

```javascript
fetch('assets/ajax/users_management.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'invite',
        email: 'user@example.com',
        role_id: 1
    })
})
.then(res => res.json())
.then(data => {
    if (data.success) {
        console.log('Invitation envoyée:', data.invitation_link);
    }
});
```

**Réponse**:
```json
{
  "success": true,
  "message": "Invitation envoyée avec succès",
  "invitation_link": "http://localhost/gestion_comptable/accept_invitation.php?token=abc123..."
}
```

### Mettre à Jour un Utilisateur

**Action**: `update`
**Méthode**: POST
**Permission requise**: `users.edit`

```javascript
fetch('assets/ajax/users_management.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'update',
        user_id: 5,
        role_id: 2,
        is_active: 1
    })
})
.then(res => res.json())
.then(data => console.log(data));
```

**Réponse**:
```json
{
  "success": true,
  "message": "Utilisateur mis à jour"
}
```

### Supprimer un Utilisateur

**Action**: `delete`
**Méthode**: POST
**Permission requise**: `users.delete`

```javascript
fetch('assets/ajax/users_management.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'delete',
        user_id: 5
    })
})
.then(res => res.json())
.then(data => console.log(data));
```

### Récupérer un Utilisateur

**Action**: `get`
**Méthode**: POST
**Permission requise**: `users.view`

```javascript
fetch('assets/ajax/users_management.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'get',
        user_id: 5
    })
})
.then(res => res.json())
.then(data => console.log(data.data));
```

### Récupérer les Permissions d'un Utilisateur

**Action**: `get_permissions`
**Méthode**: POST
**Permission requise**: Rôle `admin`

```javascript
fetch('assets/ajax/users_management.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'get_permissions',
        user_id: 5
    })
})
.then(res => res.json())
.then(data => console.log(data.permissions));
```

---

## Vérification des Permissions dans le Code

### Utilisation de PermissionHelper

La classe `PermissionHelper` fournit des méthodes statiques pour vérifier les permissions.

#### Vérifier une Permission

```php
require_once 'utils/PermissionHelper.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

if (PermissionHelper::hasPermission($db, 'invoices.create')) {
    // L'utilisateur peut créer des factures
    echo "Accès autorisé";
} else {
    echo "Accès refusé";
}
```

#### Vérifier un Rôle

```php
if (PermissionHelper::hasRole($db, 'admin')) {
    // L'utilisateur est administrateur
}

// Raccourci pour vérifier admin
if (PermissionHelper::isAdmin($db)) {
    // L'utilisateur est administrateur
}
```

#### Vérifier Plusieurs Permissions

```php
// Au moins une permission (OR)
if (PermissionHelper::hasAnyPermission($db, ['invoices.edit', 'invoices.create'])) {
    // L'utilisateur peut créer OU modifier des factures
}

// Toutes les permissions (AND)
if (PermissionHelper::hasAllPermissions($db, ['invoices.view', 'invoices.send'])) {
    // L'utilisateur peut voir ET envoyer des factures
}
```

#### Rediriger si Permission Manquante

```php
// Rediriger automatiquement si permission manquante
PermissionHelper::requirePermission($db, 'users.edit');
// Le code ci-dessous ne s'exécute que si l'utilisateur a la permission

// Rediriger vers une URL spécifique
PermissionHelper::requirePermission($db, 'users.edit', 'index.php?page=403');
```

#### Dans une Vue PHP

```php
<?php
require_once 'config/database.php';
require_once 'utils/PermissionHelper.php';

$database = new Database();
$db = $database->getConnection();

// Vérifier la permission au début de la page
PermissionHelper::requirePermission($db, 'invoices.view');

$can_create = PermissionHelper::hasPermission($db, 'invoices.create');
$can_edit = PermissionHelper::hasPermission($db, 'invoices.edit');
$can_delete = PermissionHelper::hasPermission($db, 'invoices.delete');
?>

<!-- Afficher conditionnellement les boutons -->
<?php if ($can_create): ?>
    <button onclick="createInvoice()">Créer une facture</button>
<?php endif; ?>

<?php if ($can_edit): ?>
    <button onclick="editInvoice()">Modifier</button>
<?php endif; ?>
```

### Utilisation du Modèle User

```php
require_once 'models/User.php';

$user = new User($db);

// Vérifier une permission pour un utilisateur spécifique
if ($user->hasPermission($user_id, 'invoices.edit')) {
    // Cet utilisateur peut modifier les factures
}

// Récupérer toutes les permissions d'un utilisateur
$permissions = $user->getUserPermissions($user_id);
foreach ($permissions as $perm) {
    echo $perm['display_name'] . "\n";
}
```

---

## Sécurité

### Bonnes Pratiques

1. **Toujours vérifier les permissions côté serveur**
   - Ne jamais se fier uniquement à l'UI pour la sécurité
   - Vérifier les permissions dans chaque endpoint API

2. **Ne pas exposer les permissions dans le JavaScript**
   - Les vérifications côté client sont pour l'UX uniquement
   - Un utilisateur malveillant peut contourner le JavaScript

3. **Utiliser HTTPS en production**
   - Les tokens d'invitation sont sensibles
   - Ne jamais transmettre des tokens en clair sur HTTP

4. **Expiration des tokens**
   - Les invitations expirent après 7 jours
   - Les tokens expirés sont automatiquement invalidés

5. **Audit Logging**
   - Toutes les actions importantes sont enregistrées dans `user_activity_logs`
   - Inclut: IP, user agent, timestamp, description de l'action

### Protection Contre les Attaques

**Protection implémentée**:

1. **SQL Injection** - Toutes les requêtes utilisent des prepared statements
2. **XSS** - Tous les inputs sont échappés avec `htmlspecialchars()`
3. **CSRF** - Vérification de session requise pour toutes les actions
4. **Brute Force** - Les invitations ont une limite de temps (7 jours)

**Protection recommandée (à implémenter)**:

1. **Rate Limiting** - Limiter le nombre de tentatives d'invitation par heure
2. **Email Verification** - Vérifier l'email avant d'activer le compte
3. **2FA** - Authentification à deux facteurs pour les admins
4. **Password Policy** - Forcer des mots de passe complexes

---

## Dépannage

### Problème: "Permission refusée" alors que je suis admin

**Solution**:
1. Vérifier que le rôle est bien assigné:
```sql
SELECT u.username, r.name as role
FROM users u
LEFT JOIN roles r ON u.role_id = r.id
WHERE u.id = [VOTRE_USER_ID];
```

2. Vérifier que le rôle admin a toutes les permissions:
```sql
SELECT COUNT(*)
FROM role_permissions rp
JOIN roles r ON rp.role_id = r.id
WHERE r.name = 'admin';
```
Devrait retourner 29.

### Problème: Menu "Gestion des Utilisateurs" n'apparaît pas

**Causes possibles**:
1. Pas en mode multi-tenant - Vérifier `$_SESSION['tenant_database']`
2. Pas la permission `users.view`
3. Erreur dans `header.php`

**Solution**: Vérifier les logs d'erreur PHP et la console du navigateur.

### Problème: Erreur "Table 'roles' doesn't exist"

**Cause**: Le système de rôles n'a pas été installé sur ce tenant.

**Solution**:
```bash
php install_roles_and_permissions.php gestion_comptable_client_XXXXX
```

### Problème: Impossible d'inviter un utilisateur

**Vérifications**:
1. Vérifier la permission `users.create`
2. Vérifier que l'email n'existe pas déjà
3. Vérifier les logs d'erreur dans `assets/ajax/users_management.php`

### Problème: L'utilisateur ne peut pas se connecter après invitation

**Cause**: La page `accept_invitation.php` n'existe pas encore.

**Solution temporaire**: Créer l'utilisateur manuellement:
```sql
INSERT INTO users (username, email, password, role_id, is_active, company_id)
VALUES ('username', 'email@example.com', PASSWORD_HASH, ROLE_ID, 1, COMPANY_ID);
```

---

## Évolutions Futures

### Phase 1 (Complété ✓)
- [x] Système de rôles et permissions
- [x] Interface de gestion des utilisateurs
- [x] API CRUD complète
- [x] Invitations par token
- [x] Audit logging

### Phase 2 (À développer)
- [ ] Page d'acceptation d'invitation (`accept_invitation.php`)
- [ ] Envoi automatique d'emails (intégration PHPMailer)
- [ ] Templates d'emails personnalisables
- [ ] Historique d'activité consultable (UI)
- [ ] Export des logs d'audit

### Phase 3 (Avancé)
- [ ] Permissions personnalisées par utilisateur (override du rôle)
- [ ] Rôles personnalisés créés par l'admin
- [ ] Hiérarchie de permissions (permissions héritées)
- [ ] Approbation en deux étapes pour actions critiques
- [ ] Authentification à deux facteurs (2FA)

---

## Support

Pour toute question ou problème:

1. Consulter les logs d'erreur PHP
2. Vérifier la base de données (tables, permissions, rôles)
3. Consulter `user_activity_logs` pour l'historique des actions
4. Consulter cette documentation

---

**Dernière mise à jour**: 2025-01-19
**Auteur**: Claude Code
**Version**: 1.0
