# 📋 Implémentation: Gestion des Devis/Offres

## Date: 2024-11-10

---

## ✅ Statut: Backend Terminé (Phase 1)

### Fonctionnalités Implémentées

#### 1. Base de Données ✅
- **Table `quotes`** - Devis principaux
  - ID, société, client, numéro, titre
  - Date, validité, statut (draft/sent/accepted/rejected/expired/converted)
  - Montants (sous-total, TVA, remise, total)
  - Notes, conditions, footer
  - Référence facture convertie
  - Timestamps (envoi, acceptation, rejet)

- **Table `quote_items`** - Lignes de devis
  - Description, quantité, prix unitaire
  - Taux TVA, remise
  - Total ligne, ordre de tri

- **Table `quote_status_history`** - Historique changements
  - Ancien/nouveau statut
  - Notes, utilisateur, date

- **Vue `quote_statistics`** - Statistiques
  - Compteurs par statut
  - Taux d'acceptation
  - Montant total accepté

#### 2. Modèle Quote.php ✅
**Fichier:** [models/Quote.php](models/Quote.php)

**Méthodes Principales:**
```php
// CRUD
create()                    // Créer un devis avec items
read()                      // Lire un devis par ID
readByCompany($company_id)  // Lister les devis (avec filtres)
update()                    // Mettre à jour un devis
delete()                    // Supprimer un devis

// Gestion statut
changeStatus($new_status)   // Changer le statut
logStatusChange()           // Logger l'historique

// Conversion
convertToInvoice()          // Convertir en facture

// Utilitaires
generateQuoteNumber()       // Générer numéro (DEV-YYYY-####)
calculateTotals()           // Calculer totaux
markExpiredQuotes()         // Marquer devis expirés
getStatistics()             // Obtenir statistiques
```

**Cycle de Vie d'un Devis:**
```
draft (brouillon)
  ↓ envoi au client
sent (envoyé)
  ↓ réponse client
accepted (accepté) → convertToInvoice() → converted
  ou
rejected (rejeté)
  ou
expired (expiré automatiquement si date dépassée)
```

#### 3. API REST ✅
**Fichier:** [api/quote.php](api/quote.php)

**Endpoints:**

| Méthode | Action | Description |
|---------|--------|-------------|
| POST | create | Créer un devis |
| GET | read | Lire un devis (param: id) |
| GET | list | Lister les devis (filtres: status, client_id, date_from, date_to, search) |
| PUT | update | Mettre à jour un devis |
| DELETE | delete | Supprimer un devis |
| POST | change_status | Changer le statut |
| POST | convert_to_invoice | Convertir en facture |
| GET | statistics | Obtenir statistiques |
| POST | mark_expired | Marquer devis expirés |

**Exemple Requête - Créer un Devis:**
```javascript
fetch('api/quote.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'create',
        client_id: 123,
        title: 'Devis pour projet X',
        date: '2024-11-10',
        valid_until: '2024-12-10',
        status: 'draft',
        discount_percent: 5,
        notes: 'Conditions spéciales',
        terms: 'Paiement sous 30 jours',
        items: [
            {
                description: 'Consultation',
                quantity: 1,
                unit_price: 150.00,
                tax_rate: 7.7,
                discount_percent: 0
            },
            {
                description: 'Formation',
                quantity: 5,
                unit_price: 100.00,
                tax_rate: 7.7,
                discount_percent: 10
            }
        ]
    })
})
.then(res => res.json())
.then(data => {
    if(data.success) {
        console.log('Devis créé:', data.data.number);
    }
});
```

**Exemple Requête - Convertir en Facture:**
```javascript
fetch('api/quote.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'convert_to_invoice',
        id: 42
    })
})
.then(res => res.json())
.then(data => {
    if(data.success) {
        console.log('Facture créée:', data.data.invoice_id);
    }
});
```

---

## 🔄 Fonctionnalités Clés

### Numérotation Automatique
Format: **DEV-YYYY-####**
- DEV = Devis
- YYYY = Année en cours
- #### = Numéro séquentiel (0001, 0002, etc.)

Exemple: `DEV-2024-0042`

### Calculs Automatiques
1. **Ligne de devis:**
   - Sous-total ligne = quantité × prix unitaire
   - Remise ligne = sous-total × (remise% / 100)
   - Sous-total après remise = sous-total - remise
   - TVA ligne = sous-total après remise × (TVA% / 100)

2. **Total devis:**
   - Sous-total = somme(sous-totaux lignes après remise)
   - Remise globale = sous-total × (remise globale% / 100)
   - Sous-total après remise = sous-total - remise globale
   - TVA totale = somme(TVA lignes)
   - **Total final = sous-total après remise + TVA totale**

### Gestion des Statuts

**Transitions Autorisées:**
```
draft → sent → accepted → converted
                   ↓
                rejected

draft/sent → expired (automatique)
```

**Restrictions:**
- Un devis converti ne peut plus être modifié
- Un devis ne peut être converti que s'il est accepté ou envoyé
- Les devis expirés automatiquement si date de validité dépassée

### Conversion Devis → Facture

**Processus:**
1. Vérifier statut (accepted ou sent uniquement)
2. Créer facture avec:
   - Même client
   - Même lignes (description, quantité, prix, taxes)
   - Date = aujourd'hui
   - Échéance = aujourd'hui + 30 jours
   - Notes = "Devis #DEV-YYYY-#### - [titre]"
3. Marquer devis comme "converted"
4. Lier facture au devis (converted_to_invoice_id)
5. Logger le changement de statut

---

## 📊 Statistiques Disponibles

Via la vue `quote_statistics`:
- Total devis
- Compteur par statut (draft, sent, accepted, rejected, expired, converted)
- Montant total des devis acceptés
- **Taux d'acceptation** = (acceptés / total) × 100

**Exemple d'utilisation:**
```javascript
fetch('api/quote.php?action=statistics')
    .then(res => res.json())
    .then(data => {
        console.log('Taux acceptation:', data.data.acceptance_rate + '%');
        console.log('Devis acceptés:', data.data.accepted_count);
        console.log('Montant total:', data.data.total_accepted_amount + ' CHF');
    });
```

---

## 📝 TODO: Frontend (Phase 2)

### Vues à Créer

1. **views/quotes/index.php** - Liste des devis
   - Tableau avec colonnes: Numéro, Date, Client, Montant, Statut, Actions
   - Filtres: Statut, Client, Date, Recherche
   - Boutons: Nouveau, Voir, Modifier, Supprimer, Convertir
   - Badge coloré pour les statuts

2. **views/quotes/create.php** - Créer un devis
   - Formulaire avec sélection client
   - Champs: Titre, Date, Validité, Conditions
   - Tableau lignes avec: Description, Quantité, Prix, TVA, Remise
   - Bouton "Ajouter ligne"
   - Calcul automatique des totaux
   - Remise globale
   - Bouton "Enregistrer"

3. **views/quotes/edit.php** - Modifier un devis
   - Même que create.php
   - Chargement données existantes
   - Désactivé si devis converti

4. **views/quotes/view.php** - Voir un devis
   - Affichage readonly
   - Boutons actions: Envoyer, Accepter, Rejeter, Convertir, PDF
   - Historique des changements de statut

### JavaScript à Créer

**assets/js/quotes.js:**
```javascript
// Gestion liste devis
function loadQuotes(filters = {})
function filterQuotes(status)
function searchQuotes(query)

// CRUD
function createQuote(data)
function updateQuote(id, data)
function deleteQuote(id)

// Gestion lignes
function addQuoteLine()
function removeLine(index)
function calculateLineTotals()
function calculateGlobalTotals()

// Actions
function changeStatus(id, status)
function convertToInvoice(id)
function sendQuote(id)
function generatePDF(id)

// Statistiques
function loadStatistics()
function displayStats(stats)
```

### Intégration dans Interface Existante

**views/comptabilite.php:**
Ajouter un onglet "Devis" à côté de "Factures"

```html
<div class="tabs">
    <button class="tab active" data-tab="transactions">Transactions</button>
    <button class="tab" data-tab="invoices">Factures</button>
    <button class="tab" data-tab="quotes">Devis</button>
</div>

<div id="quotes-section" class="tab-content">
    <!-- Inclure views/quotes/index.php -->
</div>
```

---

## 🎨 Design Recommandé

### Couleurs par Statut

```css
.status-draft { color: #6c757d; background: #f8f9fa; }
.status-sent { color: #0c5460; background: #d1ecf1; }
.status-accepted { color: #155724; background: #d4edda; }
.status-rejected { color: #721c24; background: #f8d7da; }
.status-expired { color: #856404; background: #fff3cd; }
.status-converted { color: #004085; background: #cce5ff; }
```

### Icônes

```
draft → 📝 (pen)
sent → 📤 (sent)
accepted → ✅ (check)
rejected → ❌ (cross)
expired → ⏰ (clock)
converted → 📄 (document)
```

---

## 🔐 Sécurité

### Contrôles Implémentés

1. **Authentification**: Session requise
2. **Authorization**: Vérification company_id
3. **Validation**: Données requises vérifiées
4. **Protection**: PDO prepared statements
5. **Restrictions**: Devis convertis non modifiables

### Logs

Tous les changements de statut sont loggés dans `quote_status_history`:
- Ancien statut
- Nouveau statut
- Notes
- Utilisateur
- Date/heure

---

## 📄 Génération PDF (À Implémenter)

### Méthode à Ajouter

**utils/PDFGenerator.php:**
```php
public function generateQuotePDF($quote_id, $company_id) {
    // Charger le devis
    // Charger les données société
    // Charger les données client
    // Générer HTML à partir du template
    // Convertir en PDF avec mPDF
    // Sauvegarder dans uploads/quotes/
    // Retourner le chemin
}
```

### Template PDF

**utils/pdf_templates/quote_swiss.html:**
- En-tête avec logo et coordonnées société
- Informations client
- Numéro et date devis
- Validité
- Tableau des lignes
- Totaux avec TVA
- Conditions générales
- Footer

---

## ✅ Tests à Effectuer

### Backend Tests

- [ ] Migration SQL: `http://localhost/gestion_comptable/run_migration_quotes.php`
- [ ] Créer un devis via API
- [ ] Lire un devis
- [ ] Lister les devis
- [ ] Mettre à jour un devis
- [ ] Supprimer un devis
- [ ] Changer le statut
- [ ] Convertir en facture
- [ ] Vérifier statistiques
- [ ] Marquer devis expirés

### Frontend Tests (Après Implémentation)

- [ ] Afficher liste des devis
- [ ] Filtrer par statut
- [ ] Rechercher
- [ ] Créer nouveau devis
- [ ] Ajouter/supprimer lignes
- [ ] Calcul automatique totaux
- [ ] Modifier devis existant
- [ ] Supprimer devis
- [ ] Changer statut via boutons
- [ ] Convertir en facture
- [ ] Générer PDF
- [ ] Afficher statistiques

---

## 📈 Prochaines Étapes

### Immédiat (Phase 2)

1. Créer les vues (index, create, edit, view)
2. Créer quotes.js
3. Intégrer dans comptabilite.php
4. Ajouter génération PDF
5. Tests utilisateurs

### Court Terme

1. Module d'envoi email des devis
2. Signature électronique
3. Templates personnalisables
4. Export Excel/CSV
5. Rappels automatiques (devis expirant bientôt)

### Long Terme

1. Versioning des devis (v1, v2, etc.)
2. Comparaison devis
3. Catalogue produits/services
4. Suggestions automatiques basées sur historique
5. Workflow approbation multi-niveaux

---

## 🎯 Avantages Business

### Pour l'Utilisateur

- ✅ Création rapide de devis professionnels
- ✅ Conversion automatique devis → facture (gain de temps)
- ✅ Suivi précis des taux d'acceptation
- ✅ Gestion de la validité (expiration automatique)
- ✅ Historique complet des changements

### Pour l'Entreprise

- 📊 KPIs: Taux d'acceptation, montant moyen, délai conversion
- 💰 Suivi du pipeline commercial
- 📈 Analyse performances par client/période
- ⏱️ Gain de temps (pas de ressaisie)
- 🎯 Meilleure gestion des opportunités

---

## 📚 Documentation API Complète

### Créer un Devis

**Requête:**
```http
POST /api/quote.php
Content-Type: application/json

{
    "action": "create",
    "client_id": 123,
    "title": "Titre du devis",
    "date": "2024-11-10",
    "valid_until": "2024-12-10",
    "discount_percent": 5,
    "notes": "Notes internes",
    "terms": "Conditions de paiement",
    "items": [
        {
            "description": "Service A",
            "quantity": 2,
            "unit_price": 100.00,
            "tax_rate": 7.7,
            "discount_percent": 0
        }
    ]
}
```

**Réponse:**
```json
{
    "success": true,
    "message": "Devis créé avec succès",
    "data": {
        "id": 42,
        "number": "DEV-2024-0042",
        "total": 215.40
    }
}
```

### Lister les Devis

**Requête:**
```http
GET /api/quote.php?action=list&status=sent&date_from=2024-01-01
```

**Réponse:**
```json
{
    "success": true,
    "data": [
        {
            "id": 42,
            "number": "DEV-2024-0042",
            "date": "2024-11-10",
            "client_name": "Client ABC",
            "total": 215.40,
            "status": "sent",
            "items_count": 2
        }
    ],
    "count": 1
}
```

---

**Version:** 2.0
**Date:** 2024-11-10
**Statut Backend:** ✅ Terminé
**Statut Frontend:** 🔄 En attente
