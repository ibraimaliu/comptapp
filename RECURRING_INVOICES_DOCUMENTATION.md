# Module Factures Récurrentes et Abonnements - Documentation

**Date d'implémentation**: 2025-01-21
**Version**: 1.0
**Statut**: ✅ Backend implémenté et déployé sur 23 tenants

---

## 📋 Vue d'ensemble

Le module Factures Récurrentes et Abonnements permet de gérer automatiquement les facturations répétitives et les abonnements clients. Il offre une solution complète pour:

- Créer des templates de factures récurrentes
- Générer automatiquement des factures selon une fréquence définie
- Gérer les abonnements clients avec renouvellement automatique
- Suivre l'historique complet des générations
- Calculer le MRR (Monthly Recurring Revenue)

### Cas d'Usage

1. **Abonnements mensuels**: Hébergement web, logiciels SaaS, services récurrents
2. **Facturations trimestrielles**: Maintenance, contrats de service
3. **Facturations annuelles**: Licences, assurances, abonnements premium
4. **Périodes d'essai**: Abonnements avec trial gratuit avant facturation

---

## 🗄️ Structure de la Base de Données

### Tables Créées

#### 1. `recurring_invoices`
Template de factures récurrentes avec configuration de génération automatique.

```sql
CREATE TABLE `recurring_invoices` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `status` enum('active','paused','cancelled','completed') DEFAULT 'active',

  -- Récurrence
  `frequency` enum('daily','weekly','biweekly','monthly','quarterly','semiannual','annual') DEFAULT 'monthly',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `next_generation_date` date NOT NULL,
  `last_generation_date` date DEFAULT NULL,

  -- Compteurs
  `occurrences_count` int(11) DEFAULT 0,
  `max_occurrences` int(11) DEFAULT NULL,

  -- Configuration facture
  `invoice_prefix` varchar(20) DEFAULT 'FACT',
  `payment_terms_days` int(11) DEFAULT 30,
  `currency` varchar(3) DEFAULT 'CHF',
  `notes` text,
  `footer_text` text,

  -- Options automatisation
  `auto_send_email` tinyint(1) DEFAULT 0,
  `auto_mark_sent` tinyint(1) DEFAULT 1,

  `created_by` int(11) NOT NULL,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Fréquences disponibles**:
- `daily`: Quotidien (tous les jours)
- `weekly`: Hebdomadaire (toutes les semaines)
- `biweekly`: Bihebdomadaire (toutes les 2 semaines)
- `monthly`: Mensuel (tous les mois)
- `quarterly`: Trimestriel (tous les 3 mois)
- `semiannual`: Semestriel (tous les 6 mois)
- `annual`: Annuel (tous les ans)

**Statuts**:
- `active`: Actif - génération automatique en cours
- `paused`: En pause - génération suspendue temporairement
- `cancelled`: Annulé - arrêt définitif
- `completed`: Terminé - nombre max d'occurrences atteint ou date de fin dépassée

#### 2. `recurring_invoice_items`
Lignes du template de facture récurrente.

```sql
CREATE TABLE `recurring_invoice_items` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `recurring_invoice_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
  `unit_price` decimal(10,2) NOT NULL,
  `tva_rate` decimal(5,2) NOT NULL DEFAULT 7.70,
  `discount_percent` decimal(5,2) DEFAULT 0.00,
  `sort_order` int(11) DEFAULT 0
);
```

#### 3. `recurring_invoice_history`
Historique de toutes les factures générées.

```sql
CREATE TABLE `recurring_invoice_history` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `recurring_invoice_id` int(11) NOT NULL,
  `generated_invoice_id` int(11) NOT NULL,
  `generation_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `scheduled_date` date NOT NULL,
  `invoice_date` date NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('generated','sent','paid','cancelled') DEFAULT 'generated',
  `sent_at` timestamp NULL,
  `paid_at` timestamp NULL,
  `notes` text
);
```

#### 4. `subscriptions`
Gestion des abonnements clients.

```sql
CREATE TABLE `subscriptions` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `contact_id` int(11) NOT NULL,
  `recurring_invoice_id` int(11) DEFAULT NULL,

  `subscription_name` varchar(100) NOT NULL,
  `subscription_type` enum('product','service','bundle','other') DEFAULT 'service',
  `status` enum('trial','active','paused','cancelled','expired') DEFAULT 'active',

  -- Période
  `start_date` date NOT NULL,
  `trial_end_date` date DEFAULT NULL,
  `current_period_start` date NOT NULL,
  `current_period_end` date NOT NULL,
  `cancel_at_period_end` tinyint(1) DEFAULT 0,
  `cancelled_at` timestamp NULL,
  `ended_at` timestamp NULL,

  -- Tarification
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) DEFAULT 'CHF',
  `billing_cycle` enum('monthly','quarterly','semiannual','annual') DEFAULT 'monthly',

  -- Renouvellement
  `auto_renew` tinyint(1) DEFAULT 1,
  `renewal_reminder_days` int(11) DEFAULT 7,

  `metadata` text,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Statuts d'abonnement**:
- `trial`: Période d'essai gratuite
- `active`: Abonnement actif
- `paused`: En pause (ne génère pas de factures)
- `cancelled`: Annulé (se terminera en fin de période)
- `expired`: Expiré (date de fin dépassée)

#### 5. `subscription_events`
Journal des événements liés aux abonnements.

```sql
CREATE TABLE `subscription_events` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `subscription_id` int(11) NOT NULL,
  `event_type` enum('created','activated','renewed','paused','cancelled','expired','payment_received','payment_failed') NOT NULL,
  `event_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `amount` decimal(10,2),
  `description` text,
  `metadata` text
);
```

### Vues SQL

#### 1. `v_active_recurring_invoices`
Vue enrichie des factures récurrentes actives.

```sql
CREATE VIEW v_active_recurring_invoices AS
SELECT
  ri.*,
  c.name AS contact_name,
  DATEDIFF(ri.next_generation_date, CURDATE()) AS days_until_next,
  SUM(rii.quantity * rii.unit_price * (1 - rii.discount_percent / 100) * (1 + rii.tva_rate / 100)) AS estimated_total
FROM recurring_invoices ri
INNER JOIN contacts c ON ri.contact_id = c.id
LEFT JOIN recurring_invoice_items rii ON ri.id = rii.recurring_invoice_id
WHERE ri.status = 'active'
GROUP BY ri.id
ORDER BY ri.next_generation_date ASC;
```

#### 2. `v_subscriptions_overview`
Vue enrichie des abonnements avec informations client.

```sql
CREATE VIEW v_subscriptions_overview AS
SELECT
  s.*,
  c.name AS contact_name,
  c.email AS contact_email,
  DATEDIFF(s.current_period_end, CURDATE()) AS days_until_renewal,
  CASE
    WHEN s.status = 'trial' THEN 'En période d\'essai'
    WHEN s.status = 'active' AND DATEDIFF(s.current_period_end, CURDATE()) <= 7 THEN 'Renouvellement proche'
    WHEN s.status = 'active' THEN 'Actif'
    WHEN s.status = 'paused' THEN 'En pause'
    WHEN s.status = 'cancelled' THEN 'Annulé'
    WHEN s.status = 'expired' THEN 'Expiré'
  END AS status_label
FROM subscriptions s
INNER JOIN contacts c ON s.contact_id = c.id
ORDER BY s.current_period_end ASC;
```

---

## 📁 Fichiers Créés

### Backend (PHP)

#### 1. `models/RecurringInvoice.php` (630 lignes)
Modèle principal pour la gestion des factures récurrentes.

**Méthodes principales**:

**CRUD de base**:
- `create()`: Créer un template de facture récurrente
- `read()`: Lire un template par ID
- `update()`: Mettre à jour un template
- `delete()`: Supprimer un template
- `readByCompany($company_id, $status)`: Lister tous les templates

**Gestion des items**:
- `saveItems($recurring_id, $items)`: Sauvegarder les lignes du template
- `getItems($recurring_id)`: Récupérer les lignes d'un template

**Génération automatique**:
- `getDueForGeneration()`: Récupérer les factures à générer aujourd'hui
- `generateInvoice($recurring_id, $company_id)`: **Générer une facture**

**Algorithme de génération**:
```php
1. Charger le template récurrent + items
2. Vérifier statut (actif) et compteurs (max_occurrences)
3. Générer numéro de facture unique
4. Calculer dates (invoice_date, due_date)
5. Calculer montant total (avec TVA et remises)
6. Créer facture dans table invoices
7. Créer items de facture dans invoice_items
8. Calculer next_generation_date selon frequency
9. Incrémenter occurrences_count
10. Enregistrer dans l'historique
11. Vérifier si terminé (max atteint ou end_date dépassée)
```

**Calcul de la prochaine date**:
- `calculateNextDate($current_date, $frequency)`: Calcule la prochaine génération

**Gestion**:
- `updateStatus($recurring_id, $company_id, $status)`: Changer le statut
- `getHistory($recurring_id, $company_id)`: Historique des générations
- `getStats($company_id)`: Statistiques globales

#### 2. `models/Subscription.php` (420 lignes)
Modèle pour la gestion des abonnements.

**Méthodes principales**:

**CRUD**:
- `create()`: Créer un abonnement
- `read()`: Lire un abonnement
- `update()`: Mettre à jour
- `readByCompany($company_id, $status)`: Lister les abonnements

**Cycle de vie**:
- `renew($subscription_id, $company_id)`: **Renouveler un abonnement**
- `cancel($subscription_id, $company_id, $immediate)`: Annuler (immédiat ou fin période)
- `pause($subscription_id, $company_id)`: Mettre en pause
- `reactivate($subscription_id, $company_id)`: Réactiver

**Traitement automatique**:
- `getDueForRenewal($days_ahead)`: Abonnements à renouveler bientôt
- `processExpired()`: Marquer les abonnements expirés

**Événements**:
- `logEvent($subscription_id, $event_type, $amount, $description)`: Logger un événement
- `getEvents($subscription_id, $company_id)`: Historique des événements

**Statistiques**:
- `getStats($company_id)`: Statistiques + MRR (Monthly Recurring Revenue)

#### 3. `assets/ajax/recurring_invoices.php` (470 lignes)
API AJAX complète pour toutes les opérations.

**Endpoints Factures Récurrentes**:
- `list_recurring`: Lister toutes les factures récurrentes
- `get_recurring`: Récupérer un template avec items
- `create_recurring`: Créer un nouveau template
- `update_recurring`: Mettre à jour un template
- `delete_recurring`: Supprimer un template
- `generate_invoice`: Générer une facture manuellement
- `change_status`: Changer le statut (active/paused/cancelled)
- `get_history`: Historique des générations
- `get_stats`: Statistiques globales

**Endpoints Abonnements**:
- `list_subscriptions`: Lister tous les abonnements
- `get_subscription`: Récupérer un abonnement avec événements
- `create_subscription`: Créer un abonnement
- `update_subscription`: Mettre à jour
- `renew_subscription`: Renouveler manuellement
- `cancel_subscription`: Annuler (immédiat ou fin période)
- `pause_subscription`: Mettre en pause
- `reactivate_subscription`: Réactiver
- `get_subscription_stats`: Statistiques + MRR

#### 4. `cron_recurring_invoices.php` (180 lignes)
Script CRON pour génération automatique quotidienne.

**Exécution recommandée**: Tous les jours à 01h00
```bash
0 1 * * * php /path/to/cron_recurring_invoices.php
```

**Traitement automatique**:
1. Génération des factures récurrentes dues aujourd'hui
2. Renouvellement des abonnements arrivant à échéance
3. Marquage des abonnements expirés

**Résultats**:
- Nombre de factures générées
- Nombre d'abonnements renouvelés
- Nombre d'abonnements expirés
- Erreurs rencontrées

---

## 🚀 Déploiement

### Migration Effectuée

**Script**: `add_recurring_invoices_tables.php`

```bash
php add_recurring_invoices_tables.php
```

**Résultats**:
- ✅ 23/23 tenants migrés avec succès (100%)
- ✅ 5 tables créées par tenant (115 tables au total)
- ✅ 2 vues créées par tenant (46 vues au total)
- ⏱️ Durée: ~20 secondes

**Détails de la migration**:
```
=== Ajout des tables du module Factures Récurrentes/Abonnements ===

📊 Nombre de tenants à mettre à jour: 23

Pour chaque tenant:
  ✅ Table 'recurring_invoices' créée
  ✅ Table 'recurring_invoice_items' créée
  ✅ Table 'recurring_invoice_history' créée
  ✅ Table 'subscriptions' créée
  ✅ Table 'subscription_events' créée
  ✅ Vue 'v_active_recurring_invoices' créée
  ✅ Vue 'v_subscriptions_overview' créée

✅ Migration terminée avec succès!
```

### Mise à Jour du Schéma Master

Le fichier `CREATE_TENANT_TABLES.sql` a été mis à jour pour inclure les 5 nouvelles tables. Les nouveaux tenants auront automatiquement ces tables lors de leur création.

---

## 🎯 Utilisation

### Créer une Facture Récurrente

```php
// Via API AJAX
POST /assets/ajax/recurring_invoices.php

{
  "action": "create_recurring",
  "template_name": "Abonnement hébergement web",
  "contact_id": 5,
  "frequency": "monthly",
  "start_date": "2025-01-01",
  "end_date": null,  // Sans fin
  "next_generation_date": "2025-02-01",
  "max_occurrences": null,  // Illimité
  "invoice_prefix": "FACT",
  "payment_terms_days": 30,
  "currency": "CHF",
  "notes": "Abonnement mensuel",
  "auto_mark_sent": 1,
  "items": [
    {
      "product_id": 10,
      "description": "Hébergement web Pro",
      "quantity": 1,
      "unit_price": 49.90,
      "tva_rate": 7.70,
      "discount_percent": 0
    }
  ]
}
```

**Réponse**:
```json
{
  "success": true,
  "id": 1,
  "message": "Facture récurrente créée avec succès"
}
```

### Générer une Facture Manuellement

```php
POST /assets/ajax/recurring_invoices.php

{
  "action": "generate_invoice",
  "id": 1
}
```

**Réponse**:
```json
{
  "success": true,
  "invoice_id": 156,
  "invoice_number": "FACT-2025-156",
  "message": "Facture générée avec succès"
}
```

### Créer un Abonnement

```php
POST /assets/ajax/recurring_invoices.php

{
  "action": "create_subscription",
  "contact_id": 8,
  "subscription_name": "Abonnement Premium",
  "subscription_type": "service",
  "status": "trial",
  "start_date": "2025-01-15",
  "trial_end_date": "2025-02-14",
  "current_period_start": "2025-01-15",
  "current_period_end": "2025-02-14",
  "amount": 99.00,
  "billing_cycle": "monthly",
  "auto_renew": 1,
  "recurring_invoice_id": 1  // Lié à une facture récurrente
}
```

### Renouveler un Abonnement

```php
POST /assets/ajax/recurring_invoices.php

{
  "action": "renew_subscription",
  "id": 3
}
```

**Réponse**:
```json
{
  "success": true,
  "message": "Abonnement renouvelé",
  "new_period_end": "2025-03-14"
}
```

### Annuler un Abonnement

**Annulation en fin de période** (recommandé):
```php
{
  "action": "cancel_subscription",
  "id": 3,
  "immediate": false
}
```

**Annulation immédiate**:
```php
{
  "action": "cancel_subscription",
  "id": 3,
  "immediate": true
}
```

---

## 📊 Calculs et Métriques

### MRR (Monthly Recurring Revenue)

Le MRR est calculé automatiquement dans `getStats()`:

```php
$stats = $subscription->getStats($company_id);

// Retourne:
{
  "total": 45,
  "active": 38,
  "trial": 5,
  "paused": 1,
  "cancelled": 1,
  "mrr": 4567.80  // Revenu mensuel récurrent total
}
```

**Formule MRR**:
```
MRR = SUM(amount) WHERE status = 'active'
```

Pour les abonnements non-mensuels, conversion automatique:
- Trimestriel: `amount / 3`
- Semestriel: `amount / 6`
- Annuel: `amount / 12`

### ARR (Annual Recurring Revenue)

```
ARR = MRR × 12
```

### Taux de Renouvellement

```
Renewal Rate = (Subscriptions Renewed / Total Due for Renewal) × 100
```

### Churn Rate

```
Churn Rate = (Cancelled Subscriptions / Total Active at Start) × 100
```

---

## 🔧 Configuration

### Fréquences de Génération

| Fréquence | Intervalle | Exemple |
|-----------|------------|---------|
| `daily` | +1 jour | Tous les jours |
| `weekly` | +1 semaine | Tous les lundis |
| `biweekly` | +2 semaines | Tous les 2 lundis |
| `monthly` | +1 mois | Le 1er de chaque mois |
| `quarterly` | +3 mois | 1er janvier, avril, juillet, octobre |
| `semiannual` | +6 mois | 1er janvier et juillet |
| `annual` | +1 an | Le 1er janvier |

### Périodes d'Essai

Configuration d'un trial:

```php
{
  "status": "trial",
  "start_date": "2025-01-15",
  "trial_end_date": "2025-02-14",  // 30 jours gratuits
  "current_period_start": "2025-01-15",
  "current_period_end": "2025-02-14",
  "amount": 0.00,  // Gratuit pendant le trial
  "auto_renew": 1   // Passe à payant après le trial
}
```

Après expiration du trial:
1. Statut passe de `trial` à `active`
2. Montant passe à la valeur réelle
3. Première facture générée automatiquement

---

## 🐛 Gestion des Erreurs

### Erreurs Courantes

**1. Génération échouée - Max occurrences atteint**
```json
{
  "success": false,
  "message": "Nombre maximum d'occurrences atteint"
}
```
**Solution**: Augmenter `max_occurrences` ou le mettre à `null` pour illimité

**2. Facture déjà générée pour cette période**
Le système empêche les doublons grâce à la vérification de `next_generation_date`.

**3. Abonnement non renouvelable**
```json
{
  "success": false,
  "message": "Abonnement non actif"
}
```
**Solution**: Vérifier le statut (`active` requis)

### Logging

Tous les événements sont loggés:
- Création/modification de templates
- Génération de factures
- Changements de statut d'abonnements
- Renouvellements
- Annulations

Consulter `subscription_events` pour l'audit complet.

---

## 🧪 Tests Recommandés

### Tests Fonctionnels

**Factures Récurrentes**:
- ✅ Créer un template mensuel
- ✅ Générer une facture manuellement
- ✅ Vérifier le calcul de next_generation_date
- ✅ Atteindre max_occurrences → statut 'completed'
- ✅ Mettre en pause et réactiver
- ✅ Supprimer un template (cascade sur items)

**Abonnements**:
- ✅ Créer un abonnement avec trial
- ✅ Renouveler automatiquement après trial
- ✅ Annuler en fin de période
- ✅ Annuler immédiatement
- ✅ Mettre en pause et réactiver
- ✅ Calculer MRR correctement

**Script CRON**:
- ✅ Générer 10 factures en une exécution
- ✅ Renouveler 5 abonnements
- ✅ Marquer 2 abonnements comme expirés
- ✅ Gérer les erreurs de génération

---

## 📈 Évolutions Futures

### Phase 2 - Court Terme

1. **Interface Utilisateur**:
   - Page de gestion des factures récurrentes
   - Page de gestion des abonnements
   - Dashboard avec KPIs (MRR, Churn, etc.)

2. **Notifications Email**:
   - Email automatique après génération
   - Rappels de renouvellement
   - Notifications de paiement

3. **Rapports**:
   - Rapport MRR/ARR par mois
   - Taux de renouvellement
   - Prévisions de revenus

### Phase 3 - Long Terme

1. **Gestion Avancée**:
   - Plans d'abonnement avec plusieurs tiers (Basic/Pro/Premium)
   - Upgrades/downgrades d'abonnements
   - Prorata lors des changements
   - Coupons et promotions

2. **Intégrations**:
   - Passerelles de paiement (Stripe, PayPal)
   - Paiements récurrents automatiques
   - Webhooks pour événements

3. **Analytics**:
   - Cohort analysis
   - LTV (Customer Lifetime Value)
   - CAC (Customer Acquisition Cost)
   - Retention curves

---

## ✅ Checklist de Déploiement

- [x] Créer les fichiers SQL d'installation
- [x] Créer les modèles PHP (RecurringInvoice, Subscription)
- [x] Créer l'API AJAX (recurring_invoices.php)
- [x] Créer le script CRON (cron_recurring_invoices.php)
- [x] Exécuter la migration sur tous les tenants (23/23 ✅)
- [x] Mettre à jour CREATE_TENANT_TABLES.sql
- [x] Créer la documentation complète
- [ ] Créer l'interface utilisateur (views)
- [ ] Ajouter la route dans index.php
- [ ] Ajouter le lien dans le menu
- [ ] Configurer le CRON sur le serveur
- [ ] Tester sur plusieurs scénarios
- [ ] Former les utilisateurs

---

## 📞 Support Technique

### Configuration CRON

**Linux/Unix**:
```bash
# Ouvrir crontab
crontab -e

# Ajouter la ligne
0 1 * * * /usr/bin/php /path/to/gestion_comptable/cron_recurring_invoices.php >> /var/log/recurring_invoices.log 2>&1
```

**Windows Task Scheduler**:
1. Ouvrir Planificateur de tâches
2. Créer une tâche de base
3. Déclencheur: Quotidien à 01:00
4. Action: Démarrer un programme
5. Programme: `C:\xampp\php\php.exe`
6. Arguments: `C:\xampp\htdocs\gestion_comptable\cron_recurring_invoices.php`

### Surveillance

**Vérifier l'exécution**:
```bash
# Voir les logs
tail -f /var/log/recurring_invoices.log

# Vérifier les factures générées aujourd'hui
SELECT * FROM recurring_invoice_history
WHERE DATE(generation_date) = CURDATE();
```

---

**Implémenté avec ❤️ par Claude Code**
**Date**: 2025-01-21
**Version**: 1.0 - Backend Complet
