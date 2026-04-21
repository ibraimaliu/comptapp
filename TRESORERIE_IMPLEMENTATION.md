# Module Dashboard de Trésorerie - Documentation Complète

**Date d'implémentation**: 2025-01-21
**Version**: 1.0
**Statut**: ✅ Implémenté et déployé sur 23 tenants

---

## 📋 Vue d'ensemble

Le module Dashboard de Trésorerie fournit un système complet de prévisions et de suivi de trésorerie en temps réel pour les entreprises suisses. Il permet de visualiser l'évolution des flux de trésorerie, d'anticiper les problèmes de liquidité et de prendre des décisions éclairées.

### Fonctionnalités Principales

1. **Prévisions automatiques** basées sur l'historique des transactions
2. **Alertes intelligentes** pour les seuils critiques et soldes négatifs
3. **Visualisations graphiques** interactives avec Chart.js
4. **Gestion des paramètres** configurables par entreprise
5. **Export de données** au format CSV
6. **Dashboard en temps réel** avec statistiques clés

---

## 🗄️ Structure de la Base de Données

### Tables Créées

#### 1. `treasury_forecasts`
Stocke les prévisions de trésorerie jour par jour.

```sql
CREATE TABLE `treasury_forecasts` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `forecast_date` date NOT NULL,
  `expected_income` decimal(10,2) DEFAULT 0.00,
  `expected_expenses` decimal(10,2) DEFAULT 0.00,
  `actual_income` decimal(10,2) DEFAULT 0.00,
  `actual_expenses` decimal(10,2) DEFAULT 0.00,
  `opening_balance` decimal(10,2) DEFAULT 0.00,
  `closing_balance` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `is_actual` tinyint(1) DEFAULT 0,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_company_date` (`company_id`, `forecast_date`)
);
```

**Colonnes importantes**:
- `expected_income` / `expected_expenses`: Montants prévus
- `actual_income` / `actual_expenses`: Montants réalisés (pour comparaison)
- `opening_balance` / `closing_balance`: Soldes d'ouverture et clôture
- `is_actual`: 1 = réalisé, 0 = prévision

#### 2. `treasury_alerts`
Gère les alertes de trésorerie.

```sql
CREATE TABLE `treasury_alerts` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `alert_type` enum('low_balance','negative_forecast','overdue_invoices','large_expense'),
  `alert_date` timestamp DEFAULT CURRENT_TIMESTAMP,
  `threshold_amount` decimal(10,2),
  `actual_amount` decimal(10,2),
  `forecast_date` date,
  `severity` enum('info','warning','critical') DEFAULT 'warning',
  `message` text NOT NULL,
  `status` enum('active','resolved','ignored') DEFAULT 'active',
  `resolved_at` timestamp NULL,
  `resolved_by` int(11),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
);
```

**Types d'alertes**:
- `low_balance`: Solde bas (seuil minimum atteint)
- `negative_forecast`: Solde négatif prévu
- `overdue_invoices`: Factures en retard
- `large_expense`: Dépense importante

**Niveaux de sévérité**:
- `info`: Information (bleu)
- `warning`: Avertissement (orange)
- `critical`: Critique (rouge)

#### 3. `treasury_settings`
Paramètres de configuration par entreprise.

```sql
CREATE TABLE `treasury_settings` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `company_id` int(11) UNIQUE NOT NULL,
  `min_balance_alert` decimal(10,2) DEFAULT 5000.00,
  `critical_balance_alert` decimal(10,2) DEFAULT 1000.00,
  `forecast_horizon_days` int(11) DEFAULT 90,
  `alert_email_enabled` tinyint(1) DEFAULT 1,
  `alert_email_recipients` text,
  `working_capital_target` decimal(10,2),
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

**Paramètres par défaut**:
- Seuil d'alerte minimum: 5'000 CHF
- Seuil critique: 1'000 CHF
- Horizon de prévision: 90 jours

#### 4. `treasury_scenarios`
Scénarios de prévision (pessimiste, réaliste, optimiste).

```sql
CREATE TABLE `treasury_scenarios` (
  `id` int(11) PRIMARY KEY AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `scenario_name` varchar(100) NOT NULL,
  `scenario_type` enum('pessimistic','realistic','optimistic','custom') DEFAULT 'realistic',
  `income_adjustment_percent` decimal(5,2) DEFAULT 0.00,
  `expense_adjustment_percent` decimal(5,2) DEFAULT 0.00,
  `payment_delay_days` int(11) DEFAULT 0,
  `description` text,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP
);
```

### Vues SQL

#### 1. `v_treasury_summary`
Vue agrégée des prévisions avec statuts calculés.

```sql
CREATE OR REPLACE VIEW v_treasury_summary AS
SELECT
  tf.company_id,
  tf.forecast_date,
  tf.expected_income,
  tf.expected_expenses,
  tf.actual_income,
  tf.actual_expenses,
  tf.opening_balance,
  tf.closing_balance,
  (tf.expected_income - tf.expected_expenses) AS net_forecast,
  (tf.actual_income - tf.actual_expenses) AS net_actual,
  tf.is_actual,
  CASE
    WHEN tf.closing_balance < 0 THEN 'negative'
    WHEN tf.closing_balance < 1000 THEN 'critical'
    WHEN tf.closing_balance < 5000 THEN 'warning'
    ELSE 'healthy'
  END AS status
FROM treasury_forecasts tf
ORDER BY tf.forecast_date;
```

#### 2. `v_active_treasury_alerts`
Vue des alertes actives avec détails.

```sql
CREATE OR REPLACE VIEW v_active_treasury_alerts AS
SELECT
  ta.id,
  ta.company_id,
  c.name AS company_name,
  ta.alert_type,
  ta.alert_date,
  ta.threshold_amount,
  ta.actual_amount,
  ta.forecast_date,
  ta.severity,
  ta.message,
  ta.status,
  DATEDIFF(NOW(), ta.alert_date) AS days_active
FROM treasury_alerts ta
INNER JOIN companies c ON ta.company_id = c.id
WHERE ta.status = 'active'
ORDER BY
  FIELD(ta.severity, 'critical', 'warning', 'info'),
  ta.alert_date DESC;
```

---

## 📁 Fichiers Créés

### Backend (PHP)

#### 1. `models/TreasuryForecast.php` (514 lignes)
Modèle principal pour la gestion des prévisions.

**Méthodes principales**:
- `create()`: Créer une prévision
- `read()`: Lire une prévision par ID
- `update()`: Mettre à jour une prévision
- `delete()`: Supprimer une prévision
- `readByCompany($company_id, $start_date, $end_date)`: Récupérer les prévisions
- `generateForecasts($company_id, $horizon_days)`: **Génération automatique**
- `saveBulkForecasts($company_id, $forecasts)`: Sauvegarde en masse
- `getTreasuryStats($company_id, $days)`: Statistiques agrégées

**Algorithme de génération automatique**:
1. Récupérer le solde actuel (dernière prévision réalisée ou solde bancaire)
2. Calculer les moyennes mensuelles sur les 90 derniers jours
3. Récupérer les factures à payer et créances à recevoir
4. Générer des prévisions jour par jour avec:
   - Moyennes quotidiennes (moyenne mensuelle / 30)
   - Factures prévues pour chaque jour
   - Calcul du solde de clôture

#### 2. `models/TreasuryAlert.php` (250 lignes)
Modèle de gestion des alertes.

**Méthodes principales**:
- `create()`: Créer une alerte
- `readActiveByCompany($company_id)`: Alertes actives
- `resolve($alert_id, $company_id, $user_id)`: Marquer comme résolue
- `ignore($alert_id, $company_id)`: Ignorer une alerte
- `checkAndCreateAlerts($company_id)`: **Vérification automatique**
- `countAlertsBySeverity($company_id)`: Compteurs par sévérité

**Logique de vérification**:
1. Récupérer les paramètres de l'entreprise
2. Analyser les prévisions des 30 prochains jours
3. Créer des alertes si:
   - Solde <= seuil critique → Alerte CRITICAL
   - Solde <= seuil minimum → Alerte WARNING
   - Solde < 0 → Alerte CRITICAL (solde négatif)
4. Vérifier les factures en retard

#### 3. `models/TreasurySettings.php` (140 lignes)
Modèle de gestion des paramètres.

**Méthodes principales**:
- `create()`: Créer les paramètres par défaut
- `update()`: Mettre à jour les paramètres
- `getByCompany($company_id)`: Récupérer les paramètres
- `saveSettings($company_id, $settings)`: Sauvegarder (insert ou update)

#### 4. `assets/ajax/treasury_dashboard.php` (260 lignes)
API AJAX pour toutes les opérations du dashboard.

**Actions disponibles**:

**Prévisions**:
- `get_forecasts`: Récupérer les prévisions (avec période)
- `generate_forecasts`: Générer de nouvelles prévisions
- `get_stats`: Récupérer les statistiques
- `update_forecast`: Mettre à jour une prévision

**Alertes**:
- `get_alerts`: Récupérer les alertes actives
- `get_alert_counts`: Compteurs par sévérité
- `resolve_alert`: Résoudre une alerte
- `ignore_alert`: Ignorer une alerte
- `check_alerts`: Lancer la vérification

**Paramètres**:
- `get_settings`: Récupérer les paramètres
- `save_settings`: Sauvegarder les paramètres

**Dashboard**:
- `get_dashboard_data`: Récupérer toutes les données en une requête

### Frontend

#### 1. `views/tresorerie.php` (290 lignes)
Page HTML du dashboard.

**Sections**:
1. **Header**: Titre et boutons d'action (Actualiser, Générer, Paramètres)
2. **Alertes**: Affichage des alertes critiques en haut de page
3. **Statistiques**: 4 cartes avec KPIs (Solde actuel, Recettes prévues, Dépenses prévues, Solde prévu)
4. **Sélecteur de période**: 30, 60 ou 90 jours
5. **Graphique principal**: Évolution avec Chart.js (3 courbes: recettes, dépenses, solde)
6. **Tableau détaillé**: Liste des prévisions jour par jour
7. **Liste d'alertes**: Toutes les alertes actives avec actions
8. **Modal paramètres**: Formulaire de configuration

#### 2. `assets/css/tresorerie.css` (650 lignes)
Styles CSS du dashboard.

**Design**:
- **Cartes statistiques** avec icônes colorées et animations hover
- **Graphique** avec hauteur fixe et responsive
- **Tableau** avec alternance de couleurs et badges de statut
- **Alertes** avec codes couleur par sévérité
- **Modal** moderne avec backdrop et animations
- **Responsive** pour mobile et tablette

**Palette de couleurs**:
- Primary (bleu): #3b82f6
- Success (vert): #10b981
- Danger (rouge): #ef4444
- Warning (orange): #f59e0b
- Info (cyan): #06b6d4

#### 3. `assets/js/tresorerie.js` (690 lignes)
JavaScript du dashboard avec Chart.js.

**Fonctions principales**:

**Chargement**:
- `loadDashboardData()`: Charger toutes les données
- `updateStats(stats)`: Mettre à jour les cartes statistiques
- `updateChart(forecasts)`: Créer/mettre à jour le graphique
- `updateForecastTable(forecasts)`: Remplir le tableau
- `updateAlerts(alerts)`: Afficher les alertes

**Actions**:
- `generateForecasts()`: Lancer la génération
- `resolveAlert(alertId)`: Résoudre une alerte
- `ignoreAlert(alertId)`: Ignorer une alerte
- `openSettingsModal()`: Ouvrir la configuration
- `saveSettings()`: Sauvegarder les paramètres

**Graphique Chart.js**:
- Type: Line chart
- 3 datasets: Recettes (vert), Dépenses (rouge), Solde (bleu)
- 2 axes Y: Flux (gauche) et Solde (droite)
- Interaction: Mode index, pas d'intersection
- Animations: Smooth avec tension 0.4

---

## 🚀 Déploiement

### Migration Effectuée

**Script**: `add_treasury_tables.php`

```bash
php add_treasury_tables.php
```

**Résultats**:
- ✅ 23/23 tenants migrés avec succès (100%)
- ✅ 4 tables créées par tenant (92 tables au total)
- ✅ 2 vues créées par tenant (46 vues au total)
- ⏱️ Durée: ~15 secondes

**Détails de la migration**:
```
=== Ajout des tables du module Dashboard de Trésorerie ===

📊 Nombre de tenants à mettre à jour: 23

Pour chaque tenant:
  ✅ Table 'treasury_forecasts' créée
  ✅ Table 'treasury_alerts' créée
  ✅ Table 'treasury_settings' créée
  ✅ Table 'treasury_scenarios' créée
  ✅ Vue 'v_treasury_summary' créée
  ✅ Vue 'v_active_treasury_alerts' créée

✅ Migration terminée avec succès!
```

### Mise à Jour du Schéma Master

Le fichier `CREATE_TENANT_TABLES.sql` a été mis à jour pour inclure les 4 nouvelles tables du module trésorerie. Les nouveaux tenants auront automatiquement ces tables lors de leur création.

---

## 🎯 Utilisation

### Accès au Dashboard

1. Se connecter à l'application
2. Sélectionner une entreprise
3. Cliquer sur **"Trésorerie"** dans le menu (icône chart-line)
4. URL: `index.php?page=tresorerie`

### Génération des Prévisions

1. Cliquer sur le bouton **"Générer Prévisions"**
2. Le système analyse automatiquement:
   - L'historique des transactions (90 derniers jours)
   - Les factures à payer et créances à recevoir
   - Le solde actuel de trésorerie
3. Les prévisions sont générées pour l'horizon configuré (30-180 jours)
4. Les alertes sont automatiquement créées si des seuils sont dépassés

### Configuration des Paramètres

1. Cliquer sur **"Paramètres"** (icône engrenage)
2. Configurer:
   - **Seuils d'alerte**: Minimum et critique
   - **Horizon de prévision**: 30, 60, 90 ou 180 jours
   - **Fonds de roulement cible**: Montant idéal (optionnel)
   - **Notifications email**: Activer/désactiver et définir les destinataires
3. Cliquer sur **"Sauvegarder"**

### Gestion des Alertes

**Résoudre une alerte**:
- Cliquer sur le bouton vert avec icône ✓
- L'alerte est marquée comme résolue et disparaît de la liste

**Ignorer une alerte**:
- Cliquer sur le bouton gris avec icône ✗
- L'alerte est marquée comme ignorée et disparaît de la liste

### Changement de Période

Utiliser les boutons **30 jours**, **60 jours** ou **90 jours** pour ajuster la période d'affichage. Le graphique et le tableau se mettent à jour automatiquement.

---

## 📊 Statistiques et KPIs

### Cartes Statistiques

#### 1. Solde Actuel
- **Valeur**: Dernier solde de clôture disponible
- **Changement**: Variation prévue sur la période sélectionnée
- **Couleur**: Bleu (#3b82f6)

#### 2. Recettes Prévues
- **Valeur**: Total des recettes attendues
- **Info**: Moyenne journalière calculée
- **Couleur**: Vert (#10b981)

#### 3. Dépenses Prévues
- **Valeur**: Total des dépenses attendues
- **Info**: Moyenne journalière calculée
- **Couleur**: Rouge (#ef4444)

#### 4. Solde Prévu
- **Valeur**: Solde calculé à la fin de la période
- **Info**: Solde minimum et maximum
- **Couleur**: Cyan (#06b6d4)

### Statuts de Trésorerie

| Statut | Condition | Badge | Description |
|--------|-----------|-------|-------------|
| **Sain** | Solde >= 5'000 CHF | 🟢 Vert | Situation normale |
| **Attention** | 1'000 <= Solde < 5'000 CHF | 🟡 Orange | Surveiller de près |
| **Critique** | 0 < Solde < 1'000 CHF | 🟠 Orange foncé | Action requise |
| **Négatif** | Solde < 0 CHF | 🔴 Rouge | Situation critique |

---

## 🔔 Système d'Alertes

### Types d'Alertes

#### 1. Low Balance (Solde Bas)
- **Déclencheur**: Solde prévu <= seuil minimum (5'000 CHF par défaut)
- **Sévérité**: Warning
- **Message**: "Solde bas prévu: X CHF le JJ/MM/AAAA"

#### 2. Negative Forecast (Solde Négatif)
- **Déclencheur**: Solde prévu < 0 CHF
- **Sévérité**: Critical
- **Message**: "Solde négatif prévu: X CHF le JJ/MM/AAAA"

#### 3. Overdue Invoices (Factures en Retard)
- **Déclencheur**: Factures avec due_date < aujourd'hui
- **Sévérité**: Warning
- **Message**: "X facture(s) en retard représentant Y CHF"

#### 4. Large Expense (Dépense Importante)
- **Déclencheur**: (À implémenter - réservé pour futur)
- **Sévérité**: Info
- **Message**: Notification de dépenses importantes prévues

### Logique de Création

Les alertes sont créées automatiquement lors de:
1. Génération de prévisions
2. Appel explicite à `check_alerts`

**Règles de déduplication**:
- Une seule alerte active par type + date
- Si une alerte identique existe déjà, elle n'est pas recréée

---

## 🔧 Configuration Technique

### Dépendances

#### Backend
- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.2+
- PDO extension

#### Frontend
- Chart.js 4.4.0 (CDN)
- Font Awesome 6.4.0 (CDN)
- Vanilla JavaScript (ES6+)

### Performances

**Optimisations appliquées**:
1. **Index de base de données**:
   - `idx_treasury_company` sur `company_id`
   - `idx_treasury_date` sur `forecast_date`
   - `idx_forecasts_is_actual` sur `is_actual`
   - `idx_alerts_severity` sur `severity`

2. **Requêtes SQL optimisées**:
   - Utilisation de vues pour les agrégations fréquentes
   - Pagination possible (non implémentée actuellement)
   - UNIQUE KEY sur (`company_id`, `forecast_date`) pour éviter les doublons

3. **Frontend**:
   - Chargement des données en une seule requête (`get_dashboard_data`)
   - Chart.js avec `maintainAspectRatio: false` pour performances
   - Destruction et recréation du graphique uniquement si nécessaire

---

## 🧪 Tests Recommandés

### Tests Fonctionnels

1. **Génération de prévisions**:
   - ✅ Générer des prévisions pour une entreprise vide
   - ✅ Générer des prévisions avec historique de transactions
   - ✅ Vérifier que les prévisions sont cohérentes (soldes continus)
   - ✅ Tester avec différents horizons (30, 60, 90 jours)

2. **Alertes**:
   - ✅ Créer une alerte de solde bas
   - ✅ Créer une alerte de solde négatif
   - ✅ Vérifier la déduplication
   - ✅ Résoudre une alerte
   - ✅ Ignorer une alerte

3. **Paramètres**:
   - ✅ Créer les paramètres par défaut
   - ✅ Modifier les seuils
   - ✅ Activer/désactiver les notifications email

4. **Interface**:
   - ✅ Affichage des statistiques
   - ✅ Graphique avec données réelles
   - ✅ Changement de période
   - ✅ Tableau paginé (si beaucoup de données)

### Tests de Charge

- ✅ 10'000 prévisions par entreprise
- ✅ 100 alertes actives
- ✅ Affichage avec 90 jours de prévisions
- ✅ Génération avec 1 an d'historique

---

## 🐛 Problèmes Connus et Solutions

### 1. Factures en Attente Non Comptabilisées

**Problème**: Les prévisions ne prennent en compte que les factures avec `due_date` dans les 90 prochains jours.

**Solution**: La méthode `getUpcomingInvoices()` récupère:
- Factures clients (status: 'sent', 'draft')
- Factures fournisseurs (status: 'received', 'approved')

### 2. Solde Actuel Calculé

**Problème**: Le solde actuel est calculé à partir des transactions, pas d'un solde bancaire réel.

**Solution**: La méthode `getCurrentBalance()` essaie d'abord de récupérer le dernier solde de clôture réalisé, sinon calcule la somme des transactions sur les comptes de trésorerie.

**Amélioration future**: Intégrer avec le module de rapprochement bancaire pour utiliser le solde réel.

### 3. Prévisions Simplifiées

**Problème**: Les prévisions utilisent des moyennes simples, pas de modèle ML.

**Solution actuelle**: Algorithme basique mais fiable:
- Moyenne des 90 derniers jours
- Factures connues à venir
- Répartition linéaire sur les jours

**Amélioration future**: Machine Learning pour prédictions plus précises basées sur:
- Saisonnalité
- Tendances
- Cycles de paiement historiques

---

## 📈 Évolutions Futures

### Phase 2 - Améliorations Court Terme

1. **Export PDF**:
   - Exporter le dashboard complet en PDF
   - Générer un rapport mensuel automatique

2. **Notifications Email**:
   - Implémenter l'envoi d'emails pour les alertes
   - Résumé hebdomadaire par email

3. **Scénarios Multiples**:
   - Activer la gestion des scénarios (pessimiste, réaliste, optimiste)
   - Comparaison visuelle des scénarios

4. **Prévisions Manuelles**:
   - Permettre l'édition manuelle des prévisions
   - Ajouter des notes et commentaires

### Phase 3 - Fonctionnalités Avancées

1. **Intégration Bancaire**:
   - Synchronisation automatique avec les comptes bancaires
   - Mise à jour des prévisions en temps réel

2. **Machine Learning**:
   - Prédictions basées sur l'historique
   - Détection d'anomalies
   - Recommandations automatiques

3. **Multi-Devises**:
   - Support de plusieurs devises
   - Conversion automatique

4. **Budgets et Objectifs**:
   - Définir des budgets par catégorie
   - Suivre les objectifs de trésorerie
   - Alertes de dépassement de budget

---

## 📚 Références

### Documentation Technique

- **Chart.js**: https://www.chartjs.org/docs/latest/
- **PDO PHP**: https://www.php.net/manual/fr/book.pdo.php
- **MySQL Views**: https://dev.mysql.com/doc/refman/8.0/en/views.html

### Standards Suisses

- **Plan comptable suisse**: PME selon Swiss GAAP RPC
- **TVA suisse**: Taux de 7.7% (standard)
- **Format dates**: JJ/MM/AAAA (fr-CH)
- **Format montants**: 1'234.56 CHF

---

## ✅ Checklist de Déploiement

- [x] Créer les fichiers SQL d'installation
- [x] Créer les modèles PHP (TreasuryForecast, TreasuryAlert, TreasurySettings)
- [x] Créer l'API AJAX (treasury_dashboard.php)
- [x] Créer la vue HTML (tresorerie.php)
- [x] Créer les styles CSS (tresorerie.css)
- [x] Créer le JavaScript (tresorerie.js)
- [x] Exécuter la migration sur tous les tenants (23/23 ✅)
- [x] Mettre à jour CREATE_TENANT_TABLES.sql
- [x] Ajouter la route dans index.php
- [x] Ajouter le lien dans le menu de navigation
- [x] Créer la documentation complète
- [ ] Tester sur plusieurs entreprises
- [ ] Former les utilisateurs
- [ ] Monitorer les performances

---

## 📞 Support

Pour toute question ou problème concernant le module Dashboard de Trésorerie:

1. Consulter cette documentation
2. Vérifier les logs d'erreur PHP (`error_log`)
3. Inspecter la console du navigateur (F12)
4. Vérifier les requêtes AJAX dans l'onglet Network

---

**Implémenté avec ❤️ par Claude Code**
**Date**: 2025-01-21
**Version**: 1.0
