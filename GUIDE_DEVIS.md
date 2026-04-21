# 📄 Guide d'Utilisation - Module Devis

## 🎯 Vue d'Ensemble

Le module Devis permet de créer des offres professionnelles pour vos clients, avec conversion automatique en factures une fois acceptées.

---

## 📊 Accès au Module

**URL**: `http://localhost/gestion_comptable/index.php?page=devis`

**Navigation**: Menu latéral → **Devis** (icône document)

---

## 🆕 Créer un Nouveau Devis

### Étape 1: Cliquer sur "Nouveau Devis"
- Bouton violet en haut de la page
- Un modal s'ouvre avec le formulaire

### Étape 2: Remplir les Informations
**Champs obligatoires**:
- **Client** : Sélectionner dans la liste (doit exister dans Contacts)
- **Date du Devis** : Date du jour par défaut
- **Valide jusqu'au** : +30 jours par défaut (modifiable)

### Étape 3: Ajouter des Articles/Services
**Pour chaque ligne**:
- **Description** : Nom du produit/service
- **Quantité** : Nombre d'unités
- **Prix Unit. HT** : Prix hors TVA
- **TVA %** : Taux TVA (7.7% par défaut en Suisse)

**Actions**:
- **Bouton "+"** : Ajouter une ligne
- **Bouton corbeille** : Supprimer une ligne
- Les totaux se calculent automatiquement

### Étape 4: Enregistrer
- Bouton **"Enregistrer"** en bas du modal
- Le devis est créé avec le statut "Brouillon"
- Un numéro unique est généré automatiquement (format: DEV-2024-001)

---

## 📋 Liste des Devis

### Statistiques en Haut
Six cartes affichent:
- **Total Devis** : Nombre total
- **Brouillons** : Non finalisés
- **Envoyés** : Transmis aux clients
- **Acceptés** : Confirmés par les clients
- **Refusés** : Déclinés
- **Convertis** : Transformés en factures

### Filtres
**Onglets disponibles**:
- Tous
- Brouillons
- Envoyés
- Acceptés
- Refusés
- Expirés

Cliquez sur un onglet pour filtrer la liste.

---

## 🔄 Cycle de Vie d'un Devis

```
┌──────────┐    Envoi     ┌────────┐
│ Brouillon│─────────────→│ Envoyé │
└──────────┘              └────────┘
                               │
                    ┌──────────┴──────────┐
                    │                     │
                    ↓                     ↓
               ┌──────────┐         ┌──────────┐
               │ Accepté  │         │ Refusé   │
               └──────────┘         └──────────┘
                    │
                    ↓
            ┌──────────────┐
            │ Converti en  │
            │   Facture    │
            └──────────────┘
```

**Statuts possibles**:
1. **Brouillon** (jaune) : En cours de rédaction
2. **Envoyé** (bleu) : Transmis au client
3. **Accepté** (vert) : Client a accepté
4. **Refusé** (rouge) : Client a décliné
5. **Expiré** (gris) : Date de validité dépassée
6. **Converti** (violet) : Transformé en facture

---

## ⚡ Actions Disponibles

Pour chaque devis, selon son statut:

### 👁️ Voir
- Visualiser les détails du devis
- Disponible pour tous les statuts

### ✏️ Modifier
- Éditer le devis
- **Disponible uniquement** pour: Brouillon, Envoyé

### 🔄 Convertir en Facture
- Transformer le devis en facture
- **Disponible uniquement** pour: Accepté (et non déjà converti)
- Crée automatiquement une facture avec les mêmes lignes

### 🗑️ Supprimer
- Supprimer définitivement
- **Disponible uniquement** pour: Brouillon

---

## 💡 Bonnes Pratiques

### Numérotation
- Format automatique: **DEV-YYYY-NNN**
- Exemple: DEV-2024-001
- Séquentiel par société

### Validité
- **Durée standard** : 30 jours
- Ajustez selon votre secteur:
  - Bâtiment : 60-90 jours
  - IT/Services : 30 jours
  - Produits : 15 jours

### Articles/Services
**Description claire**:
- ✅ BON : "Consultation médicale spécialisée (1h)"
- ❌ MAUVAIS : "Consultation"

**Quantité**:
- Entiers pour produits : 5 unités
- Décimales pour services : 2.5 heures

### TVA
**Taux Suisses**:
- **7.7%** : Taux normal
- **2.5%** : Taux réduit (hébergement, eau, etc.)
- **0%** : Exonéré

---

## 🔗 Conversion en Facture

### Processus
1. **Accepter le devis** : Changer statut à "Accepté"
2. **Cliquer "Convertir"** : Bouton vert dans les actions
3. **Confirmation** : Popup de validation
4. **Résultat** :
   - Facture créée automatiquement
   - Numéro facture différent (FACT-YYYY-NNN)
   - Devis marqué comme "Converti"
   - Lien vers facture dans le devis

### Ce qui est Copié
✅ **Repris de la facture**:
- Client
- Articles/Services (descriptions, quantités, prix)
- TVA
- Notes/Conditions

❌ **Non repris**:
- Date (nouvelle date = date du jour)
- Numéro (nouveau numéro séquentiel)
- Échéance (calculée selon paramètres)

---

## 🚨 Erreurs Courantes

### "Aucun client sélectionné"
**Problème** : Liste clients vide
**Solution** :
1. Aller dans **Adresses - Contacts**
2. Créer au moins un contact de type "Client"
3. Retourner dans Devis

### "Au moins un article est requis"
**Problème** : Tentative d'enregistrer sans articles
**Solution** : Ajouter au moins une ligne avec description et quantité > 0

### "Seuls les devis en brouillon peuvent être supprimés"
**Problème** : Tentative de supprimer un devis envoyé/accepté
**Solution** :
- Les devis non-brouillons sont **archivés**, pas supprimés
- Créez un nouveau devis si nécessaire

---

## 📱 Interface Mobile

Le module Devis est **responsive** et s'adapte aux écrans mobiles:
- Cartes empilées verticalement
- Boutons d'action en ligne
- Modal pleine largeur
- Formulaire optimisé tactile

---

## 🔐 Sécurité

### Contrôles d'Accès
- ✅ Session utilisateur requise
- ✅ Vérification `company_id` sur toutes opérations
- ✅ Validation côté serveur des données
- ✅ Protection contre injection SQL (prepared statements)

### Permissions
- Tous les utilisateurs connectés peuvent:
  - Créer des devis
  - Voir les devis de leur société
  - Modifier leurs brouillons
- Seuls les **administrateurs** peuvent (à implémenter):
  - Supprimer des devis acceptés
  - Modifier les devis d'autres utilisateurs

---

## 📊 Rapports et Exports

### Statistiques Disponibles
Dans `models/Quote.php`, méthode `getStatistics()`:
- Total devis par société
- Répartition par statut
- Montant total devis acceptés
- Taux de conversion (acceptés / envoyés)

### Export PDF (À VENIR)
**Fonctionnalités prévues**:
- [ ] Génération PDF professionnel
- [ ] Logo entreprise
- [ ] Conditions générales
- [ ] Signature électronique
- [ ] Envoi par email

---

## 🛠️ Développement Technique

### API REST Endpoints

**Base URL**: `assets/ajax/quotes.php`

#### Liste des Devis
```javascript
GET /assets/ajax/quotes.php

Response:
{
  "success": true,
  "quotes": [
    {
      "id": 1,
      "number": "DEV-2024-001",
      "date": "2024-11-11",
      "valid_until": "2024-12-11",
      "client_id": 5,
      "client_name": "John Doe SA",
      "subtotal": "1000.00",
      "tva_amount": "77.00",
      "total": "1077.00",
      "status": "draft",
      "notes": "Conditions: 30 jours",
      "created_at": "2024-11-11 10:30:00"
    }
  ],
  "total": 1
}
```

#### Créer un Devis
```javascript
POST /assets/ajax/quotes.php
Content-Type: application/json

Body:
{
  "action": "create",
  "company_id": 1,
  "client_id": 5,
  "date": "2024-11-11",
  "valid_until": "2024-12-11",
  "notes": "Conditions: 30 jours",
  "items": [
    {
      "description": "Service X",
      "quantity": 2,
      "unit_price": 500.00,
      "tva_rate": 7.7
    }
  ]
}

Response:
{
  "success": true,
  "message": "Devis créé avec succès",
  "quote_id": 1
}
```

#### Supprimer un Devis
```javascript
POST /assets/ajax/quotes.php
Content-Type: application/json

Body:
{
  "action": "delete",
  "id": 1
}

Response:
{
  "success": true,
  "message": "Devis supprimé avec succès"
}
```

#### Convertir en Facture
```javascript
POST /assets/ajax/quotes.php
Content-Type: application/json

Body:
{
  "action": "convert",
  "id": 1
}

Response:
{
  "success": true,
  "message": "Devis converti en facture avec succès",
  "invoice_id": 42
}
```

---

## 🔧 Dépannage

### Le modal ne s'ouvre pas
**Vérification**:
1. Console navigateur (F12) : erreurs JavaScript ?
2. Fichier `views/devis.php` bien chargé ?
3. Session active ?

### Les totaux ne se calculent pas
**Vérification**:
1. Fonction `updateTotals()` appelée ?
2. Valeurs numériques dans champs ?
3. Console : erreurs de parsing ?

### API retourne erreur 500
**Vérification**:
1. Logs Apache : `xampp/apache/logs/error.log`
2. Base de données connectée ?
3. Table `quotes` existe ?
4. Permissions d'écriture ?

---

## 📞 Support

Pour tout problème ou question:
1. Vérifier ce guide
2. Consulter [PLAN_WINBIZ_FEATURES.md](PLAN_WINBIZ_FEATURES.md)
3. Voir [CLAUDE.md](CLAUDE.md) pour développement
4. Créer une issue dans le repository

---

## 🎯 Prochaines Améliorations

### Court Terme
- [ ] Édition devis existants
- [ ] Export PDF
- [ ] Envoi email
- [ ] Prévisualisation avant envoi

### Moyen Terme
- [ ] Templates devis personnalisables
- [ ] Conditions générales par défaut
- [ ] Relances automatiques
- [ ] Statistiques avancées

### Long Terme
- [ ] Signature électronique
- [ ] Portail client (validation en ligne)
- [ ] Multi-devises
- [ ] Gestion remises et promotions

---

**Dernière mise à jour**: 11 Novembre 2024
**Version du module**: 1.0
**Auteur**: Claude Code Assistant
