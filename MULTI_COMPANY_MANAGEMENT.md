# Gestion Multi-Sociétés

## Vue d'ensemble

Cette fonctionnalité permet aux utilisateurs de gérer plusieurs sociétés depuis un seul compte, avec des limites définies par leur plan d'abonnement.

## Accès

### Menu Principal
Un nouveau menu **"Mes Sociétés"** (icône building) est disponible dans la barre latérale.

**Chemin d'accès** :
```
Menu → Mes Sociétés
URL: index.php?page=mes_societes
```

## Fonctionnalités

### 1. Vue d'ensemble des Sociétés

La page affiche toutes les sociétés de l'utilisateur sous forme de cartes avec :

**Informations affichées** :
- Nom de la société
- Propriétaire (nom et prénom)
- Période fiscale (début et fin)
- Statut TVA
- Date de création
- Badge "Active" sur la société actuellement sélectionnée

### 2. Informations sur les Limites

Un encadré en haut de page affiche :

**Pour les plans limités** :
```
Plan: Starter
Sociétés: 2 / 3
Disponibles: 1
```

**Pour les plans illimités** :
```
Plan: Professional
Sociétés: Illimitées ∞
```

### 3. Actions Disponibles

#### Sur chaque société :

1. **Activer** (si pas déjà active)
   - Change la société active
   - Recharge l'application avec la nouvelle société
   - Icône: 🔄 Exchange

2. **Paramètres**
   - Redirige vers la page de paramètres
   - Pour modifier les détails de la société
   - Icône: ⚙️ Settings

#### Carte de création :

- **Créer une nouvelle société**
  - Visible si limite non atteinte
  - Redirige vers `society_setup.php`
  - Design en pointillés avec icône "+"

- **Limite atteinte** (carte désactivée)
  - Affiche "Limite atteinte"
  - Message "Mettez à niveau votre plan"
  - Carte grisée et non cliquable

## Limites par Plan

| Plan | Max Sociétés | Comportement |
|------|--------------|--------------|
| **Gratuit** | 1 | Carte de création désactivée après 1 société |
| **Starter** | 3 | Carte de création désactivée après 3 sociétés |
| **Professional** | ∞ | Toujours possible de créer des sociétés |
| **Enterprise** | ∞ | Toujours possible de créer des sociétés |

## Code Source

### Fichiers Créés

1. **views/mes_societes.php** (500 lignes)
   - Interface complète de gestion
   - Design moderne avec cartes
   - Responsive design

### Fichiers Modifiés

1. **index.php** - Route ajoutée :
   ```php
   case 'mes_societes':
   case 'my_companies':
       include_once 'views/mes_societes.php';
       break;
   ```

2. **includes/header.php** - Menu ajouté :
   ```php
   <li style="--clr:#8b5cf6;" class="menu-item">
       <a href="index.php?page=mes_societes">
           <i class="fa-solid fa-building"></i>
           <span>Mes Sociétés</span>
       </a>
   </li>
   ```

## Flux Utilisateur

### Scénario 1 : Utilisateur avec plan Gratuit (1 société)

```
1. Connexion → Création société A → Société A active
2. Accès "Mes Sociétés"
3. Affichage:
   ┌─────────────────┐  ┌──────────────────┐
   │   Société A     │  │  Limite atteinte │
   │   ✓ Active      │  │  ⚠️ Mettez à     │
   │   [Paramètres]  │  │  niveau le plan  │
   └─────────────────┘  └──────────────────┘
```

### Scénario 2 : Utilisateur avec plan Starter (3 sociétés)

```
1. Connexion → Création société A
2. "Mes Sociétés" → Création société B
3. "Mes Sociétés" → Création société C
4. Affichage:
   ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌─────────────┐
   │ Société A  │ │ Société B  │ │ Société C  │ │  Limite     │
   │ [Activer]  │ │ ✓ Active   │ │ [Activer]  │ │  atteinte   │
   └────────────┘ └────────────┘ └────────────┘ └─────────────┘
```

### Scénario 3 : Utilisateur avec plan Professional (illimité)

```
1. Connexion → Créations multiples
2. Affichage:
   ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌─────────────┐
   │ Société A  │ │ Société B  │ │ Société C  │ │ ➕ Créer    │
   │ [Activer]  │ │ ✓ Active   │ │ [Activer]  │ │ nouvelle    │
   └────────────┘ └────────────┘ └────────────┘ └─────────────┘

   Toujours possible d'ajouter des sociétés
```

## Changement de Société

### API Utilisée

**Endpoint** : `api/session.php`

**Requête** :
```javascript
fetch('api/session.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        action: 'change_company',
        company_id: 123
    })
})
```

**Réponse** :
```json
{
    "success": true,
    "message": "Société changée avec succès"
}
```

### Effet du Changement

Lorsqu'un utilisateur change de société :

1. ✅ `$_SESSION['company_id']` est mis à jour
2. ✅ Redirection vers la page d'accueil
3. ✅ Toutes les données affichées correspondent à la nouvelle société
4. ✅ Le sélecteur de société dans le header est mis à jour

## Design et UX

### Carte Société Active

```
┌─────────────────────────────────────┐
│                    ✓ Active          │
│  Société ABC Sàrl                    │
│                                      │
│  👤 Jean Dupont                      │
│  📅 Exercice: 01/01/2025-31/12/2025  │
│  🧾 TVA: Assujetti                   │
│  🕐 Créée le 15/11/2025              │
│                                      │
│  ────────────────────────────────    │
│  [✓ Société active]  [⚙️ Paramètres] │
└─────────────────────────────────────┘
```

### Carte Société Inactive

```
┌─────────────────────────────────────┐
│  Société XYZ SA                      │
│                                      │
│  👤 Marie Martin                     │
│  📅 Exercice: 01/01/2025-31/12/2025  │
│  🧾 TVA: Exonéré                     │
│  🕐 Créée le 20/11/2025              │
│                                      │
│  ────────────────────────────────    │
│  [🔄 Activer]        [⚙️ Paramètres] │
└─────────────────────────────────────┘
```

### Carte de Création

**Quand autorisée** :
```
┌─────────────────────────────────────┐
│                                      │
│             ➕                       │
│                                      │
│   Créer une nouvelle société        │
│   Cliquez pour ajouter une société  │
│                                      │
└─────────────────────────────────────┘
```

**Quand limite atteinte** :
```
┌─────────────────────────────────────┐
│              (grisé)                 │
│             ➕                       │
│                                      │
│        Limite atteinte               │
│   Mettez à niveau votre plan        │
│                                      │
└─────────────────────────────────────┘
```

## Style CSS

### Couleurs Principales

- **Gradient header** : `#667eea` → `#764ba2`
- **Société active** : Bordure `#667eea`
- **Hover** : Élévation avec ombre
- **Icons** : Couleur `#667eea`

### Responsive

- **Desktop** : Grille 3 colonnes (350px min par carte)
- **Tablet** : Grille 2 colonnes
- **Mobile** : 1 colonne

## Améliorations Futures

### Phase 1 (Actuel) ✅
- [x] Liste des sociétés
- [x] Changement de société active
- [x] Création depuis cette page
- [x] Affichage des limites

### Phase 2 (À venir)
- [ ] Modification inline des sociétés
- [ ] Suppression de sociétés
- [ ] Duplication de société
- [ ] Export/Import de configuration

### Phase 3 (Avancé)
- [ ] Statistiques par société
- [ ] Comparaison entre sociétés
- [ ] Tableau de bord multi-sociétés
- [ ] Gestion centralisée des contacts

## Sécurité

### Vérifications

1. ✅ **Authentification** : `isLoggedIn()` requis
2. ✅ **Propriété** : Seules les sociétés de l'utilisateur sont affichées
3. ✅ **Limites** : Vérification du plan avant création
4. ✅ **Session** : Changement sécurisé via API

### Isolation des Données

Chaque société voit uniquement :
- Ses propres transactions
- Ses propres contacts
- Ses propres factures
- Ses propres paramètres

**Requêtes filtrées par** : `WHERE company_id = :company_id`

## Support

### Problèmes Courants

**Q: Je ne vois pas mes sociétés**
R: Vérifiez que vous êtes connecté et que `$_SESSION['user_id']` est défini.

**Q: La carte de création est désactivée**
R: Vous avez atteint la limite de votre plan. Mettez à niveau pour créer plus de sociétés.

**Q: Le changement de société ne fonctionne pas**
R: Vérifiez que l'API `api/session.php` est accessible et que la session est active.

**Q: Les limites ne s'affichent pas**
R: Vérifiez que `$_SESSION['tenant_database']` est défini (architecture multi-tenant).

## Logs et Debug

Pour déboguer les problèmes :

```php
// Dans mes_societes.php
error_log("User ID: " . $_SESSION['user_id']);
error_log("Company ID: " . $_SESSION['company_id']);
error_log("Tenant: " . $_SESSION['tenant_database']);
error_log("Limits: " . print_r($company_limits, true));
```

---

**Dernière mise à jour** : 22 Novembre 2025
**Version** : 1.0
**Fichier principal** : `views/mes_societes.php`
