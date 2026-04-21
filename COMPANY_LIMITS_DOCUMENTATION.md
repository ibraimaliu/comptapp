# Documentation: Limites de Sociétés par Plan d'Abonnement

## Vue d'ensemble

Le système permet de limiter le nombre de sociétés qu'un utilisateur peut créer en fonction de son plan d'abonnement. Cette fonctionnalité permet de proposer différents niveaux de service et d'encourager les utilisateurs à mettre à niveau leur plan.

## Configuration des Plans

### Plans Disponibles

| Plan | Max Sociétés | Prix Mensuel | Description |
|------|--------------|--------------|-------------|
| **Gratuit (Essai)** | 1 | 0 CHF | Une seule société pour tester l'application |
| **Starter** | 3 | 29 CHF | Jusqu'à 3 sociétés pour les indépendants |
| **Professional** | Illimité | 79 CHF | Nombre illimité de sociétés |
| **Enterprise** | Illimité | 199 CHF | Nombre illimité de sociétés + support dédié |

### Structure de la Base de Données

La colonne `max_companies` dans la table `subscription_plans` définit la limite :

```sql
ALTER TABLE subscription_plans
ADD COLUMN max_companies INT DEFAULT 1
COMMENT 'Nombre maximum de sociétés autorisées (-1 = illimité)';
```

**Valeurs spéciales** :
- `-1` : Illimité
- `N` : Nombre maximum de sociétés (1, 3, 5, etc.)

## Fichiers Impliqués

### 1. Utilitaire de Vérification
**Fichier**: `utils/TenantLimits.php`

Classe helper avec 3 méthodes principales :

#### `canCreateCompany($db_master, $tenant_code, $user_id)`
Vérifie si un utilisateur peut créer une nouvelle société.

**Retour** :
```php
[
    'allowed' => bool,        // true si création autorisée
    'current' => int,         // Nombre de sociétés actuelles
    'max' => int,            // Limite maximum (-1 = illimité)
    'plan_name' => string,   // Nom du plan
    'message' => string      // Message descriptif
]
```

**Exemple d'utilisation** :
```php
require_once 'config/database_master.php';
require_once 'utils/TenantLimits.php';

$db_master = (new DatabaseMaster())->getConnection();
$check = TenantLimits::canCreateCompany($db_master, 'tenant001', 123);

if (!$check['allowed']) {
    echo "Erreur: " . $check['message'];
    // Afficher message d'upgrade
} else {
    // Permettre la création
}
```

#### `getCompanyLimits($db_master, $tenant_code, $user_id)`
Récupère les informations de limite pour affichage.

**Retour** :
```php
[
    'current' => int,        // Sociétés actuelles
    'max' => int,           // Maximum autorisé
    'plan_name' => string,  // Nom du plan
    'remaining' => mixed,   // 'Illimité' ou nombre restant
    'unlimited' => bool     // true si illimité
]
```

#### `hasReachedLimit($db_master, $tenant_code, $user_id)`
Version simplifiée qui retourne juste `true/false`.

### 2. API de Création d'Entreprise
**Fichier**: `api/company.php`

La vérification est effectuée **avant** la création :

```php
// Vérifier les limites du plan d'abonnement
if (isset($_SESSION['tenant_database'])) {
    $database_master = new DatabaseMaster();
    $db_master = $database_master->getConnection();

    $tenant_code = $_SESSION['tenant_database'];
    $limit_check = TenantLimits::canCreateCompany($db_master, $tenant_code, $user_id);

    if (!$limit_check['allowed']) {
        http_response_code(403);
        echo json_encode([
            "message" => $limit_check['message'],
            "current" => $limit_check['current'],
            "max" => $limit_check['max'],
            "plan_name" => $limit_check['plan_name']
        ]);
        exit;
    }
}
```

**Réponse en cas de limite atteinte** :
```json
{
    "message": "Limite atteinte. Votre plan 'Gratuit (Essai)' autorise maximum 1 société(s). Mettez à niveau votre plan pour créer plus de sociétés.",
    "current": 1,
    "max": 1,
    "plan_name": "Gratuit (Essai)"
}
```

### 3. Interface Utilisateur
**Fichier**: `views/society_setup.php`

L'interface affiche automatiquement :

1. **Indicateur de limite** (encadré informatif) :
   - Affiche le plan actuel
   - Montre le nombre de sociétés utilisées / maximum
   - Indique le nombre de sociétés restantes

2. **Blocage du formulaire** :
   - Si la limite est atteinte, le formulaire est désactivé
   - Message d'erreur rouge visible
   - Lien vers upgrade du plan

**Code d'affichage** :
```php
<?php if ($company_limits): ?>
<div class="alert <?php echo $can_create ? 'alert-info' : 'alert-warning'; ?>">
    <strong>Limite de sociétés (Plan: <?php echo $company_limits['plan_name']; ?>)</strong><br>
    <?php if ($company_limits['unlimited']): ?>
        Vous pouvez créer un nombre illimité de sociétés.
    <?php else: ?>
        Sociétés actives: <?php echo $company_limits['current']; ?> / <?php echo $company_limits['max']; ?>
        <?php if ($company_limits['remaining'] > 0): ?>
            <br>Vous pouvez encore créer <?php echo $company_limits['remaining']; ?> société(s).
        <?php else: ?>
            <br><strong>⚠️ Limite atteinte.</strong> Mettez à niveau votre plan.
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php endif; ?>
```

## Scripts de Migration

### Script d'Installation
**Fichier**: `add_company_limits.php`

Exécute les opérations suivantes :
1. Ajoute la colonne `max_companies` si elle n'existe pas
2. Configure les limites par défaut pour chaque plan
3. Affiche la configuration actuelle
4. Affiche les statistiques d'utilisation

**Utilisation** :
```bash
cd c:\xampp\htdocs\gestion_comptable
php add_company_limits.php
```

**Sortie attendue** :
```
=== Ajout des limites de sociétés par plan ===

Vérification de la colonne max_companies...
✓ La colonne max_companies existe déjà

Configuration des limites par plan:
-----------------------------------
✓ Plan 'free': 1 société(s)
✓ Plan 'starter': 3 société(s)
✓ Plan 'professional': Illimité société(s)
✓ Plan 'enterprise': Illimité société(s)

✅ Migration terminée avec succès!
```

## Flux de Création d'une Société

```
┌─────────────────────────────────────┐
│  Utilisateur demande création       │
│  d'une nouvelle société             │
└───────────┬─────────────────────────┘
            │
            ▼
┌─────────────────────────────────────┐
│  Récupération du tenant_code        │
│  depuis $_SESSION['tenant_database']│
└───────────┬─────────────────────────┘
            │
            ▼
┌─────────────────────────────────────┐
│  TenantLimits::canCreateCompany()   │
│  - Connexion base master            │
│  - Récupère le plan et max_companies│
│  - Compte les sociétés actuelles    │
└───────────┬─────────────────────────┘
            │
            ├───────────┐
            ▼           ▼
    ┌──────────┐  ┌──────────┐
    │ Autorisé │  │  Refusé  │
    └─────┬────┘  └────┬─────┘
          │            │
          ▼            ▼
┌─────────────────┐  ┌──────────────────────┐
│ Création OK     │  │ HTTP 403             │
│ + Import plan   │  │ Message d'erreur     │
│   comptable     │  │ Suggestion d'upgrade │
└─────────────────┘  └──────────────────────┘
```

## Gestion Administrative

### Modifier les Limites d'un Plan

**Via SQL** :
```sql
-- Modifier la limite du plan Starter à 5 sociétés
UPDATE subscription_plans
SET max_companies = 5
WHERE plan_code = 'starter';

-- Rendre un plan illimité
UPDATE subscription_plans
SET max_companies = -1
WHERE plan_code = 'professional';
```

**Via l'interface admin** (à venir) :
- Page de gestion des plans
- Modification des limites en temps réel
- Historique des changements

### Vérifier l'Utilisation d'un Tenant

```php
require_once 'config/database_master.php';
require_once 'utils/TenantLimits.php';

$db_master = (new DatabaseMaster())->getConnection();
$limits = TenantLimits::getCompanyLimits($db_master, 'tenant001', 123);

echo "Plan: " . $limits['plan_name'] . "\n";
echo "Utilisées: " . $limits['current'] . "\n";
echo "Maximum: " . ($limits['max'] == -1 ? 'Illimité' : $limits['max']) . "\n";
echo "Restantes: " . $limits['remaining'] . "\n";
```

## Tests

### Test Manuel

1. **Se connecter avec un compte plan Gratuit**
   - Créer 1 société → ✅ Succès
   - Tenter d'en créer une 2e → ❌ Refusé avec message

2. **Se connecter avec un compte plan Starter**
   - Créer 3 sociétés → ✅ Succès
   - Tenter d'en créer une 4e → ❌ Refusé avec message

3. **Se connecter avec un compte plan Professional**
   - Créer autant de sociétés que souhaité → ✅ Toujours autorisé

### Test via API

**Avec cURL** :
```bash
# Création autorisée
curl -X POST http://localhost/gestion_comptable/api/company.php \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test SA",
    "owner_name": "Jean",
    "owner_surname": "Dupont",
    "fiscal_year_start": "2025-01-01",
    "fiscal_year_end": "2025-12-31",
    "tva_status": "assujetti"
  }'

# Si limite atteinte
{
  "message": "Limite atteinte. Votre plan 'Gratuit (Essai)' autorise maximum 1 société(s)...",
  "current": 1,
  "max": 1,
  "plan_name": "Gratuit (Essai)"
}
```

## Upgrade de Plan

### Processus Recommandé

Lorsqu'un utilisateur atteint sa limite :

1. **Afficher un message clair** :
   ```
   ⚠️ Vous avez atteint la limite de votre plan Gratuit (1 société).
   Passez au plan Starter (3 sociétés) ou Professional (illimité) pour continuer.
   ```

2. **Proposer les options d'upgrade** :
   - Bouton "Voir les plans" → Page de comparaison
   - Bouton "Upgrader maintenant" → Processus de paiement

3. **Après upgrade** :
   - Mise à jour du `subscription_plan` du tenant
   - Nouvelle limite appliquée immédiatement
   - Email de confirmation

### Mise à Jour du Plan

```sql
-- Passer un tenant de 'free' à 'starter'
UPDATE tenants
SET subscription_plan = 'starter'
WHERE tenant_code = 'tenant001';
```

Après cette modification, l'utilisateur peut immédiatement créer jusqu'à 3 sociétés.

## Sécurité

### Points de Contrôle

1. **API Backend** (`api/company.php`) :
   - Vérification obligatoire avant création
   - Retour HTTP 403 si limite atteinte
   - Impossible de contourner côté client

2. **Interface Frontend** (`views/society_setup.php`) :
   - Affichage informatif
   - Désactivation du formulaire
   - Double vérification côté serveur

3. **Base de Données** :
   - Pas de contrainte SQL (souplesse)
   - Vérification applicative uniquement

### Recommandations

- ✅ Toujours vérifier côté serveur (API)
- ✅ Utiliser la classe `TenantLimits` (centralisée)
- ✅ Logger les tentatives de dépassement
- ✅ Proposer l'upgrade de manière claire
- ❌ Ne pas se fier uniquement au frontend

## Dépannage

### Problème : La limite n'est pas appliquée

**Causes possibles** :
1. La colonne `max_companies` n'existe pas
   - Solution : Exécuter `php add_company_limits.php`

2. Le plan n'a pas de limite configurée
   - Solution : Vérifier `SELECT * FROM subscription_plans`

3. La vérification n'est pas appelée
   - Solution : Vérifier que `TenantLimits::canCreateCompany()` est bien appelé

### Problème : "Erreur de connexion à la base tenant"

**Cause** : Le tenant_code ne correspond pas à une base existante

**Solution** :
```php
// Vérifier que la base existe
SHOW DATABASES LIKE 'tenant001';

// Vérifier le tenant_code en session
var_dump($_SESSION['tenant_database']);
```

### Problème : Les sociétés ne sont pas comptées correctement

**Cause** : L'utilisateur a des sociétés dans plusieurs tenants

**Solution** : Le système compte uniquement les sociétés du tenant actuel, ce qui est le comportement attendu (multi-tenant isolation).

## Évolutions Futures

### Phase 1 : Base (✅ Implémenté)
- [x] Configuration des limites par plan
- [x] Vérification côté API
- [x] Affichage dans l'interface
- [x] Documentation

### Phase 2 : Améliorations
- [ ] Page admin de gestion des plans
- [ ] Historique des changements de plan
- [ ] Analytics d'utilisation par tenant

### Phase 3 : Monétisation
- [ ] Intégration paiement (Stripe)
- [ ] Processus d'upgrade automatique
- [ ] Facturati on récurrente
- [ ] Gestion des abonnements

### Phase 4 : Avancé
- [ ] Limites dynamiques par feature
- [ ] Essais temporaires de plans supérieurs
- [ ] Alertes proactives avant limite
- [ ] Recommandations de plan basées sur l'usage

## Support

Pour toute question ou problème :
1. Consulter cette documentation
2. Vérifier les logs : `error_log` PHP
3. Tester avec le script : `php add_company_limits.php`
4. Contacter le support technique

---

**Dernière mise à jour** : 22 Novembre 2025
**Version** : 1.0
