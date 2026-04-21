# 🧪 Guide de Test - Gestion Comptable v3.0

## 🎯 Pages à Tester

### 1. Page d'Accueil (Dashboard)
**URL:** `http://localhost/gestion_comptable/index.php?page=home`

**À vérifier:**
- [ ] Affichage des 4 cartes statistiques
  - Total des revenus (vert)
  - Total des dépenses (rouge)
  - Bénéfice net (bleu)
  - TVA collectée (orange)
- [ ] Section "Activité récente" avec les 10 dernières transactions
- [ ] Icônes appropriées pour revenus (↑) et dépenses (↓)
- [ ] Liens rapides fonctionnels:
  - Nouvelle transaction → comptabilite
  - Nouvelle facture → comptabilite?tab=factures
  - Nouveau contact → adresses
  - Voir les rapports → comptabilite?tab=rapports
- [ ] Message d'accueil si aucune société
- [ ] Design responsive (tester en redimensionnant)

**État vide attendu:**
Si aucune transaction, affiche "Aucune transaction récente"

---

### 2. Page Contacts/Adresses
**URL:** `http://localhost/gestion_comptable/index.php?page=adresses`

**À vérifier:**

#### Statistiques
- [ ] Carte "Total Contacts" affiche le bon nombre
- [ ] Carte "Clients" compte correct
- [ ] Carte "Fournisseurs" compte correct
- [ ] Carte "Autres" compte correct

#### Recherche et Filtres
- [ ] Barre de recherche filtre en temps réel
- [ ] Bouton "X" efface la recherche
- [ ] Filtres par type fonctionnent:
  - [ ] Tous (actif par défaut)
  - [ ] Clients
  - [ ] Fournisseurs
  - [ ] Autres
- [ ] Compteur "X contacts" se met à jour

#### Cartes de Contacts
- [ ] Avatar avec initiales correctes
- [ ] Badge de type coloré (vert/bleu/gris)
- [ ] Email affiché (ou "Non renseigné")
- [ ] Téléphone affiché (ou "Non renseigné")
- [ ] Adresse complète formatée
- [ ] Boutons d'action:
  - [ ] Modifier (icône crayon)
  - [ ] Supprimer (icône poubelle)
- [ ] Effet de survol (carte se soulève)

#### Modal Ajouter/Modifier
- [ ] Bouton "Nouveau Contact" ouvre le modal
- [ ] Formulaire avec tous les champs:
  - [ ] Type (select: Client, Fournisseur, Autre)
  - [ ] Nom *
  - [ ] Email
  - [ ] Téléphone
  - [ ] Adresse
  - [ ] Code postal
  - [ ] Ville
  - [ ] Pays
- [ ] Validation: nom obligatoire
- [ ] Bouton "Enregistrer" fonctionne
- [ ] Modal se ferme après succès
- [ ] Liste se met à jour automatiquement

#### Suppression
- [ ] Bouton supprimer demande confirmation
- [ ] Confirmation affiche "Êtes-vous sûr..."
- [ ] Annuler ferme la confirmation
- [ ] Confirmer supprime le contact
- [ ] Liste se met à jour automatiquement

#### Console Développeur
- [ ] Ouvrir F12
- [ ] Onglet Console ne doit PAS afficher:
  - Erreurs JSON.parse
  - Erreurs 404
  - Erreurs de chemin (include_once)
- [ ] Onglet Réseau:
  - [ ] `assets/ajax/contacts.php` retourne 200 OK
  - [ ] Response est du JSON valide
  - [ ] `assets/ajax/save_contact.php` retourne 200 OK
  - [ ] `assets/ajax/delete_contact.php` retourne 200 OK

---

### 3. Page Comptabilité
**URL:** `http://localhost/gestion_comptable/index.php?page=comptabilite`

**À vérifier:**

#### Mini-Statistiques (en haut)
- [ ] 4 mini-cartes affichées:
  - Revenus totaux
  - Dépenses totales
  - Bénéfice
  - Nombre de factures

#### Navigation Onglets
- [ ] 4 onglets visibles:
  - Transactions
  - Factures
  - Devis
  - Rapports
- [ ] Clic sur onglet change le contenu
- [ ] Onglet actif visuellement distinct (fond blanc)
- [ ] URL change: `?tab=transactions`, `?tab=factures`, etc.

#### Onglet Transactions
- [ ] Liste des transactions affichée
- [ ] Chaque transaction montre:
  - [ ] Icône avec flèche (↑ revenus, ↓ dépenses)
  - [ ] Description
  - [ ] Date formatée (JJ.MM.AAAA)
  - [ ] Montant avec signe (+ pour revenus, - pour dépenses)
  - [ ] Code couleur (vert pour revenus, rouge pour dépenses)
- [ ] Bouton "Ajouter Transaction" visible

#### Onglet Factures
- [ ] Cartes de factures affichées
- [ ] Chaque facture montre:
  - [ ] Numéro de facture
  - [ ] Nom du client
  - [ ] Date d'émission
  - [ ] Date d'échéance
  - [ ] Montant total
  - [ ] Badge de statut (Payée/En attente/En retard)
  - [ ] Boutons d'action (Voir, Modifier, PDF)
- [ ] Message si aucune facture

#### Onglet Devis
- [ ] Message "Module Devis/Offres en cours de développement"
- [ ] Bouton "Créer un Devis" visible
- [ ] Lien vers migration si pas encore faite

#### Onglet Rapports
- [ ] Statistiques détaillées affichées
- [ ] 4 cartes de stats visibles
- [ ] Graphiques de revenus/dépenses (si implémentés)

---

## 🔧 Tests de Diagnostic

### Script 1: Test de Connexion
**URL:** `http://localhost/gestion_comptable/test_connection.php`

**Résultat attendu:**
- ✅ Connexion réussie à la base de données
- ✅ Table `users` existe
- ✅ Table `companies` existe
- ✅ Table `contacts` existe
- ✅ Table `invoices` existe
- ✅ Table `transactions` existe

**Si erreur:**
- Vérifier que XAMPP MySQL est démarré
- Vérifier que la base `gestion_comptable` existe
- Vérifier les credentials dans `config/database.php`

### Script 2: Test AJAX Contacts
**URL:** `http://localhost/gestion_comptable/test_ajax_contacts.php`

**Tests effectués:**
1. **Liste des Contacts**
   - Appel GET à `assets/ajax/contacts.php`
   - Doit retourner `{"success": true, "contacts": [...], "total": X}`

2. **Création Contact**
   - Appel POST à `assets/ajax/save_contact.php`
   - Avec données: name, type, email, phone
   - Doit retourner `{"success": true, "message": "Contact créé avec succès"}`

3. **Modification Contact**
   - Appel POST avec `id` existant
   - Doit retourner `{"success": true, "message": "Contact mis à jour avec succès"}`

4. **Suppression Contact**
   - Appel POST à `assets/ajax/delete_contact.php`
   - Avec `id` du contact
   - Doit retourner `{"success": true, "message": "Contact supprimé avec succès"}`

**Résultat attendu:**
- Tous les tests affichent ✅ Succès (vert)
- Aucune erreur de parsing JSON
- Réponses contiennent `success: true`

**Si erreur:**
- Ouvrir la console (F12) et voir les détails
- Vérifier les chemins d'inclusion
- Vérifier que la session est active

### Script 3: Test des Chemins
**URL:** `http://localhost/gestion_comptable/test_paths.php`

**Résultat attendu:**
- Affiche les chemins absolus corrects
- Simulation AJAX montre `dirname(dirname(__DIR__))` = racine
- Tous les fichiers existent:
  - ✅ config/database.php
  - ✅ models/Contact.php
  - ✅ assets/ajax/contacts.php
  - ✅ assets/ajax/save_contact.php
  - ✅ assets/ajax/delete_contact.php
- Test d'inclusion réussit
- Connexion à la DB établie

---

## 📱 Tests Responsive

### Desktop (>1024px)
- [ ] Grilles affichent 4 colonnes
- [ ] Tous les éléments visibles
- [ ] Navigation horizontale

### Tablette (768px - 1024px)
- [ ] Grilles affichent 2 colonnes
- [ ] Statistiques s'empilent
- [ ] Menu reste accessible

### Mobile (<768px)
- [ ] Grilles affichent 1 colonne
- [ ] Cartes prennent toute la largeur
- [ ] Texte reste lisible
- [ ] Boutons accessibles
- [ ] Formulaires utilisables

**Comment tester:**
1. Ouvrir DevTools (F12)
2. Cliquer sur l'icône mobile (Ctrl+Shift+M)
3. Sélectionner différentes résolutions
4. Vérifier l'affichage et l'utilisabilité

---

## 🔍 Points de Contrôle Critiques

### Sécurité
- [ ] Sessions actives sur toutes les pages
- [ ] Redirection vers login si non connecté
- [ ] `company_id` vérifié sur toutes les opérations
- [ ] Pas d'injection SQL possible (requêtes préparées)
- [ ] HTML échappé partout (`htmlspecialchars`)

### Performance
- [ ] Chargement page < 2 secondes
- [ ] AJAX répond en < 500ms
- [ ] Pas de requêtes N+1
- [ ] Recherche en temps réel fluide

### Erreurs Communes à Éviter
- ❌ Erreur "JSON.parse" → Vérifier chemins AJAX
- ❌ "Session expirée" → Vérifier `session_name()`
- ❌ "Société non sélectionnée" → Sélectionner dans le menu
- ❌ "Cannot execute queries" → Migration avec buffering
- ❌ "Failed to open stream" → Chemins d'inclusion incorrects

---

## ✅ Checklist de Déploiement

Avant de considérer la refonte terminée:

### Fonctionnalités
- [x] Page Accueil refaite
- [x] Page Contacts refaite
- [x] Page Comptabilité refaite
- [x] AJAX Contacts fonctionne
- [x] Recherche/Filtres fonctionnent
- [x] Modal CRUD fonctionne
- [x] Design responsive
- [x] Statistiques correctes

### Technique
- [x] Pas d'erreurs PHP
- [x] Pas d'erreurs JavaScript
- [x] Chemins d'inclusion corrects
- [x] Sessions synchronisées
- [x] Base de données connectée
- [x] Migration QR exécutée
- [x] Migration Devis exécutée

### Documentation
- [x] REFACTORING_SUMMARY.md créé
- [x] TESTING_GUIDE.md créé
- [x] Scripts de test créés
- [x] CLAUDE.md à jour

---

## 📊 Rapport de Test (Template)

```
# Test de la Refonte - [Date]

## Environnement
- Navigateur: _______
- Résolution: _______
- OS: _______

## Tests Effectués

### Page Accueil
- [ ] Statistiques: ☑️ OK / ☐ Erreur
- [ ] Activité récente: ☑️ OK / ☐ Erreur
- [ ] Liens rapides: ☑️ OK / ☐ Erreur

### Page Contacts
- [ ] Statistiques: ☑️ OK / ☐ Erreur
- [ ] Liste contacts: ☑️ OK / ☐ Erreur
- [ ] Recherche: ☑️ OK / ☐ Erreur
- [ ] Filtres: ☑️ OK / ☐ Erreur
- [ ] Modal ajout: ☑️ OK / ☐ Erreur
- [ ] Modification: ☑️ OK / ☐ Erreur
- [ ] Suppression: ☑️ OK / ☐ Erreur

### Page Comptabilité
- [ ] Mini-stats: ☑️ OK / ☐ Erreur
- [ ] Onglets: ☑️ OK / ☐ Erreur
- [ ] Transactions: ☑️ OK / ☐ Erreur
- [ ] Factures: ☑️ OK / ☐ Erreur
- [ ] Devis: ☑️ OK / ☐ Erreur
- [ ] Rapports: ☑️ OK / ☐ Erreur

### Scripts Diagnostic
- [ ] test_connection.php: ☑️ OK / ☐ Erreur
- [ ] test_ajax_contacts.php: ☑️ OK / ☐ Erreur
- [ ] test_paths.php: ☑️ OK / ☐ Erreur

### Responsive
- [ ] Desktop (>1024px): ☑️ OK / ☐ Erreur
- [ ] Tablette (768-1024px): ☑️ OK / ☐ Erreur
- [ ] Mobile (<768px): ☑️ OK / ☐ Erreur

## Problèmes Rencontrés
1. _______________________
2. _______________________

## Notes
_______________________
_______________________

## Conclusion
☑️ Tous les tests passent - Prêt pour production
☐ Problèmes mineurs - À corriger
☐ Problèmes majeurs - Refonte nécessaire
```

---

## 🎓 Commandes Utiles

### Redémarrer Apache/MySQL (XAMPP)
```bash
# Dans XAMPP Control Panel
Stop Apache + MySQL
Start Apache + MySQL
```

### Vérifier les logs d'erreur
```bash
# Windows
C:\xampp\apache\logs\error.log
C:\xampp\mysql\data\mysql_error.log

# Tail en temps réel (Git Bash)
tail -f /c/xampp/apache/logs/error.log
```

### Vider le cache du navigateur
```
Ctrl + Shift + Delete
Ou
Ctrl + F5 (rechargement forcé)
```

### Tester en ligne de commande
```bash
# Vérifier syntaxe PHP
cd c:\xampp\htdocs\gestion_comptable
php -l views/adresses.php
php -l assets/ajax/contacts.php

# Tester connexion DB
php -r "require 'config/database.php'; $db = new Database(); var_dump($db->getConnection());"
```

---

**Dernière mise à jour:** Janvier 2025
**Version testée:** 3.0
**Statut:** 📋 Guide prêt à l'emploi
