# Checklist de Tests - Page Paramètres

**Version**: 2.0.0
**Date**: 2024-11-12

---

## 🎯 Objectif

Ce document fournit une checklist complète pour tester toutes les fonctionnalités de la page Paramètres après la refonte.

---

## ✅ Tests Préliminaires

### Accès et Navigation
- [ ] La page se charge sans erreur: `http://localhost/gestion_comptable/index.php?page=parametres`
- [ ] Redirection vers login si non authentifié
- [ ] Toutes les 9 sections sont visibles dans la navigation
- [ ] Le passage d'une section à l'autre fonctionne
- [ ] Aucune erreur JavaScript dans la console (F12)
- [ ] Aucune erreur PHP dans les logs Apache

### Vérification des Fichiers
- [ ] `assets/ajax/accounting_plan_import.php` existe
- [ ] `assets/ajax/user_profile.php` existe
- [ ] `assets/ajax/data_export.php` existe
- [ ] `plan_comptable_exemple.csv` existe à la racine
- [ ] `test_parametres.php` existe et est accessible

---

## 📊 Section 1: Informations de la Société

### Test 1.1: Affichage
- [ ] Le nom de la société s'affiche correctement
- [ ] Le propriétaire s'affiche correctement
- [ ] La période comptable s'affiche
- [ ] Le statut TVA s'affiche

### Test 1.2: Modification
- [ ] Clic sur "Modifier" ouvre le formulaire
- [ ] Modification du nom de société fonctionne
- [ ] Modification du propriétaire fonctionne
- [ ] Modification de la période comptable fonctionne
- [ ] Modification du statut TVA fonctionne
- [ ] Message de confirmation s'affiche
- [ ] Les données sont persistées après rechargement

---

## 🧾 Section 2: Configuration QR-Factures

### Test 2.1: Affichage
- [ ] Les champs IBAN s'affichent
- [ ] L'adresse complète s'affiche
- [ ] Le bouton "Modifier" est présent

### Test 2.2: Validation IBAN
- [ ] IBAN invalide est rejeté (ex: CH12 3000 0001 2345 6789 0)
- [ ] QR-IBAN invalide est rejeté (ex: CH93 0076 2011 6238 5295 7)
- [ ] QR-IBAN valide est accepté (ex: CH58 3000 0001 2345 6789 0)
- [ ] Message d'erreur clair pour IBAN invalide
- [ ] Message de succès pour IBAN valide

### Test 2.3: Enregistrement
- [ ] Enregistrement avec QR-IBAN valide fonctionne
- [ ] Enregistrement de l'adresse fonctionne
- [ ] Les données sont persistées

---

## 📋 Section 3: Plan Comptable ⭐

### Test 3.1: Affichage du Plan
- [ ] La liste des comptes s'affiche
- [ ] Les numéros de compte sont corrects
- [ ] Les intitulés sont lisibles
- [ ] Les catégories s'affichent (Actif, Passif, Charge, Produit)
- [ ] Les types s'affichent (Bilan, Résultat)
- [ ] Le compteur total de comptes est correct

### Test 3.2: Import CSV - Fichier Exemple ⭐⭐⭐
**Étape 1: Préparation**
- [ ] Télécharger `plan_comptable_exemple.csv` depuis la racine
- [ ] Ouvrir le fichier dans un éditeur de texte
- [ ] Vérifier format: `Numéro;Intitulé;Catégorie;Type`
- [ ] Vérifier 32 lignes de comptes

**Étape 2: Import en mode "Remplacer"**
- [ ] Cliquer sur "Importer" dans la section Plan Comptable
- [ ] Modal d'import s'ouvre
- [ ] Sélectionner `plan_comptable_exemple.csv`
- [ ] Choisir l'action "Remplacer le plan actuel"
- [ ] Cliquer sur "Importer"
- [ ] Indicateur de chargement s'affiche
- [ ] Message de succès s'affiche avec nombre de comptes importés
- [ ] Le plan comptable est rechargé automatiquement
- [ ] Vérifier que 32 comptes sont présents (ou le nombre attendu)

**Résultat Attendu:**
```
✅ 32 comptes importés avec succès

Avertissements:
(Aucun si premier import)
```

**Étape 3: Import en mode "Ajouter"**
- [ ] Réimporter le même fichier en mode "Ajouter au plan actuel"
- [ ] Message indique que les comptes existent déjà
- [ ] Aucun doublon créé
- [ ] Le nombre de comptes reste identique

**Résultat Attendu:**
```
✅ 0 nouveaux comptes importés

Avertissements:
- Ligne ignorée pour compte 1000: existe déjà
- Ligne ignorée pour compte 1020: existe déjà
...
```

### Test 3.3: Import CSV - Fichier Personnalisé
**Créer un fichier test: `test_import.csv`**
```csv
Numéro;Intitulé;Catégorie;Type
9000;Compte Test 1;Actif;Bilan
9001;Compte Test 2;Charge;Résultat
```

- [ ] Import du fichier test réussit
- [ ] Les 2 comptes apparaissent dans le plan
- [ ] Les données sont correctes

### Test 3.4: Import CSV - Validation Erreurs
**Créer un fichier invalide: `test_invalid.csv`**
```csv
Numéro;Intitulé;Catégorie;Type
;Compte sans numéro;Actif;Bilan
9999;Compte catégorie invalide;InvalidCategory;Bilan
8888;Compte type invalide;Actif;InvalidType
```

- [ ] Import détecte les erreurs
- [ ] Messages d'erreur spécifiques pour chaque ligne
- [ ] Les comptes invalides ne sont PAS importés
- [ ] Le rapport d'erreur est clair

**Résultat Attendu:**
```
Avertissements:
- Ligne 2: Numéro de compte manquant
- Ligne 3: Catégorie invalide 'InvalidCategory'
- Ligne 4: Type invalide 'InvalidType'
```

### Test 3.5: Import CSV - Colonnes Alternatives
**Tester avec noms de colonnes en anglais:**
```csv
Number;Name;Category;Type
9100;Test Account;Asset;Balance
```

- [ ] L'import détecte automatiquement les colonnes
- [ ] La normalisation fonctionne (Asset → Actif, Balance → Bilan)
- [ ] Le compte est importé correctement

### Test 3.6: Export CSV ⭐⭐
- [ ] Cliquer sur "Exporter"
- [ ] Un fichier CSV est téléchargé
- [ ] Le nom du fichier suit le format: `plan_comptable_YYYY-MM-DD_HH-mm-ss.csv`
- [ ] Ouvrir le fichier dans Excel ou LibreOffice
- [ ] Les caractères accentués sont corrects (UTF-8 avec BOM)
- [ ] Le séparateur est le point-virgule (;)
- [ ] Toutes les colonnes sont présentes: Numéro;Intitulé;Catégorie;Type;Utilisé
- [ ] Toutes les données sont exactes

### Test 3.7: Réinitialisation ⭐
**Étape 1: Créer un compte test non utilisé**
- [ ] Ajouter un compte: 9999, "Compte à supprimer", Actif, Bilan
- [ ] Vérifier que le compte apparaît dans la liste

**Étape 2: Créer une transaction avec un compte existant**
- [ ] Aller sur la page Comptabilité
- [ ] Créer une transaction liée au compte 1000 (Caisse)
- [ ] Retourner sur Paramètres → Plan comptable

**Étape 3: Réinitialiser**
- [ ] Cliquer sur "Réinitialiser"
- [ ] Confirmer la suppression
- [ ] Le compte 9999 est supprimé (non utilisé)
- [ ] Le compte 1000 est conservé (utilisé dans une transaction)
- [ ] Message de confirmation correct

**Résultat Attendu:**
```
✅ X comptes non utilisés supprimés
Les comptes liés à des transactions ont été conservés
```

### Test 3.8: Ajout Manuel de Compte
- [ ] Cliquer sur "Ajouter un compte"
- [ ] Remplir: Numéro 9200, Intitulé "Test Manuel", Catégorie Actif, Type Bilan
- [ ] Enregistrer
- [ ] Le compte apparaît dans la liste
- [ ] Export CSV contient le nouveau compte

---

## 📂 Section 4: Catégories de Dépenses

### Test 4.1: Liste
- [ ] Les catégories s'affichent
- [ ] Bouton "Ajouter" visible

### Test 4.2: Ajout
- [ ] Ajouter une catégorie "Test Category"
- [ ] La catégorie apparaît dans la liste

### Test 4.3: Modification
- [ ] Modifier la catégorie "Test Category" → "Updated Category"
- [ ] La modification est sauvegardée

### Test 4.4: Suppression
- [ ] Supprimer "Updated Category"
- [ ] La catégorie disparaît

---

## 💰 Section 5: Taux TVA

### Test 5.1: Affichage
- [ ] Les taux 7.7%, 2.5%, 0% s'affichent

### Test 5.2: Ajout
- [ ] Ajouter un taux personnalisé (ex: 3.8%)
- [ ] Le taux apparaît

### Test 5.3: Suppression
- [ ] Supprimer le taux 3.8%
- [ ] Le taux disparaît

---

## 📤 Section 6: Exportation de Données ⭐⭐

### Test 6.1: Export Transactions - CSV
- [ ] Sélectionner "Transactions"
- [ ] Sélectionner format "CSV"
- [ ] Cliquer sur "Télécharger l'export"
- [ ] Fichier téléchargé: `transactions_YYYY-MM-DD_HH-mm-ss.csv`
- [ ] Ouvrir dans Excel
- [ ] Vérifier colonnes: Date, Type, Description, Montant, Catégorie, Compte, Méthode Paiement, Référence
- [ ] Vérifier que les données sont correctes
- [ ] Caractères accentués corrects

### Test 6.2: Export Factures - CSV
- [ ] Sélectionner "Factures"
- [ ] Format "CSV"
- [ ] Télécharger
- [ ] Fichier: `factures_YYYY-MM-DD_HH-mm-ss.csv`
- [ ] Colonnes: Numéro, Date, Client, Sous-total, TVA, Total, Statut, Date Échéance
- [ ] Données correctes

### Test 6.3: Export Contacts - CSV
- [ ] Sélectionner "Contacts"
- [ ] Format "CSV"
- [ ] Télécharger
- [ ] Fichier: `contacts_YYYY-MM-DD_HH-mm-ss.csv`
- [ ] Colonnes: Nom, Type, Email, Téléphone, Adresse, Code Postal, Ville, Pays
- [ ] Données correctes

### Test 6.4: Export Plan Comptable - CSV
- [ ] Sélectionner "Plan comptable"
- [ ] Format "CSV"
- [ ] Télécharger
- [ ] Fichier: `plan_comptable_YYYY-MM-DD_HH-mm-ss.csv`
- [ ] Même résultat que le bouton "Exporter" de la section 3

### Test 6.5: Export Transactions - JSON
- [ ] Sélectionner "Transactions"
- [ ] Format "JSON"
- [ ] Télécharger
- [ ] Fichier: `transactions_YYYY-MM-DD_HH-mm-ss.json`
- [ ] Ouvrir dans un éditeur de texte
- [ ] Vérifier que c'est du JSON valide
- [ ] Données structurées correctement

### Test 6.6: Export Complet - JSON ⭐⭐⭐
- [ ] Sélectionner "Toutes les données (JSON uniquement)"
- [ ] Le format est automatiquement "JSON" (pas de choix CSV)
- [ ] Télécharger
- [ ] Fichier: `export_complet_YYYY-MM-DD_HH-mm-ss.json`
- [ ] Ouvrir dans un éditeur
- [ ] Vérifier les clés: transactions, invoices, contacts, accounting_plan, categories, products
- [ ] Toutes les données sont présentes
- [ ] Format JSON valide (tester avec jsonlint.com)

### Test 6.7: Export avec Données Vides
- [ ] Créer une nouvelle société sans données
- [ ] Essayer d'exporter "Transactions"
- [ ] Le fichier contient seulement les en-têtes (CSV) ou tableau vide (JSON)
- [ ] Aucune erreur

---

## 👤 Section 7: Profil Utilisateur ⭐⭐

### Test 7.1: Chargement du Profil
- [ ] Ouvrir la section "Profil Utilisateur"
- [ ] Le nom d'utilisateur s'affiche correctement
- [ ] L'email s'affiche correctement
- [ ] La date de création du compte s'affiche (format français: JJ/MM/AAAA)

### Test 7.2: Modification Email - Succès
- [ ] Modifier l'email (ex: `newemail@example.com`)
- [ ] Cliquer sur "Mettre à jour le profil"
- [ ] Message de succès s'affiche
- [ ] Se déconnecter et reconnecter avec le nouvel email
- [ ] La connexion fonctionne

### Test 7.3: Modification Email - Email Existant
- [ ] Créer un deuxième utilisateur avec email `existing@example.com`
- [ ] Se connecter avec le premier utilisateur
- [ ] Essayer de changer l'email vers `existing@example.com`
- [ ] Message d'erreur: "Cet email est déjà utilisé"
- [ ] L'email n'est pas modifié

### Test 7.4: Modification Email - Format Invalide
- [ ] Essayer de modifier l'email vers `invalid-email`
- [ ] Message d'erreur: "Email invalide"

### Test 7.5: Changement Mot de Passe - Succès ⭐⭐⭐
**Étape 1: Préparer les données**
- [ ] Mot de passe actuel connu: `CurrentPass123`
- [ ] Nouveau mot de passe: `NewPassword456!`

**Étape 2: Soumettre le formulaire**
- [ ] Entrer le mot de passe actuel
- [ ] Entrer le nouveau mot de passe
- [ ] Confirmer le nouveau mot de passe (identique)
- [ ] Cliquer sur "Changer le mot de passe"
- [ ] Indicateur de chargement s'affiche
- [ ] Message de succès s'affiche
- [ ] Les champs de mot de passe sont vidés automatiquement

**Étape 3: Vérifier le changement**
- [ ] Se déconnecter
- [ ] Essayer de se connecter avec l'ancien mot de passe → Échec
- [ ] Se connecter avec le nouveau mot de passe → Succès

### Test 7.6: Changement Mot de Passe - Mot de Passe Actuel Incorrect
- [ ] Entrer un mot de passe actuel incorrect
- [ ] Nouveaux mots de passe valides
- [ ] Cliquer sur "Changer le mot de passe"
- [ ] Message d'erreur: "Mot de passe actuel incorrect"

### Test 7.7: Changement Mot de Passe - Moins de 8 Caractères
- [ ] Mot de passe actuel correct
- [ ] Nouveau mot de passe: `Short1` (7 caractères)
- [ ] Confirmer
- [ ] Cliquer sur "Changer le mot de passe"
- [ ] Message d'erreur: "Le mot de passe doit contenir au moins 8 caractères"

### Test 7.8: Changement Mot de Passe - Confirmation Non Concordante
- [ ] Mot de passe actuel correct
- [ ] Nouveau mot de passe: `NewPassword123`
- [ ] Confirmation: `DifferentPassword123`
- [ ] Cliquer sur "Changer le mot de passe"
- [ ] Message d'erreur: "Les mots de passe ne correspondent pas"

### Test 7.9: Changement Mot de Passe - Champs Vides
- [ ] Laisser le mot de passe actuel vide
- [ ] Cliquer sur "Changer le mot de passe"
- [ ] Message d'erreur approprié

---

## 🔒 Section 8: Sécurité & Sauvegarde

### Test 8.1: Téléchargement Sauvegarde Complète
- [ ] Cliquer sur "Télécharger sauvegarde complète (JSON)"
- [ ] Fichier téléchargé: `export_complet_YYYY-MM-DD_HH-mm-ss.json`
- [ ] C'est le même que l'export complet de la section 6
- [ ] Le fichier contient toutes les données

### Test 8.2: Affichage Conseils de Sécurité
- [ ] La liste des conseils s'affiche
- [ ] Le message sur la session s'affiche
- [ ] Tout est lisible et formaté

---

## ⚙️ Section 9: Configuration Avancée

### Test 9.1: Informations Système
- [ ] Version de l'application s'affiche (ex: 2.0.0)
- [ ] Informations PHP s'affichent
- [ ] Société active s'affiche

---

## 🧪 Tests d'Intégration

### Test I1: Import → Export → Re-Import
**Scénario complet:**
1. [ ] Importer `plan_comptable_exemple.csv`
2. [ ] Vérifier que 32 comptes sont présents
3. [ ] Exporter le plan comptable en CSV
4. [ ] Réinitialiser le plan comptable (supprime tout)
5. [ ] Réimporter le fichier CSV exporté
6. [ ] Vérifier que les 32 comptes sont de retour
7. [ ] Les données sont identiques à l'import initial

### Test I2: Modification Profil → Export Complet
1. [ ] Modifier l'email du profil
2. [ ] Exporter toutes les données (JSON)
3. [ ] Ouvrir le fichier JSON
4. [ ] Vérifier que les données utilisateur ne sont PAS dans l'export
   (L'export contient seulement les données comptables, pas le profil utilisateur)

### Test I3: Import Plan → Création Transaction → Export
1. [ ] Importer un plan comptable
2. [ ] Créer une transaction utilisant le compte 1000 (Caisse)
3. [ ] Exporter les transactions en CSV
4. [ ] Vérifier que la transaction apparaît avec le numéro de compte 1000

---

## 🔥 Tests de Robustesse

### Test R1: Fichier CSV Trop Grand
- [ ] Créer un fichier CSV avec 1000 comptes
- [ ] Importer le fichier
- [ ] Vérifier temps de traitement acceptable (< 10 secondes)
- [ ] Tous les comptes sont importés

### Test R2: Caractères Spéciaux dans CSV
**Créer un fichier avec caractères accentués:**
```csv
Numéro;Intitulé;Catégorie;Type
9300;Café et thé;Charge;Résultat
9301;Matériel informatique;Charge;Résultat
9302;Frais de représentation;Charge;Résultat
```
- [ ] Import réussit
- [ ] Les accents sont préservés
- [ ] Export → Re-import préserve les accents

### Test R3: Fichier CSV avec BOM UTF-8
- [ ] Créer un fichier CSV avec BOM UTF-8 (Excel Windows)
- [ ] Importer le fichier
- [ ] Pas d'erreur de parsing
- [ ] Les données sont correctes

### Test R4: Session Expirée
1. [ ] Se connecter
2. [ ] Ouvrir la page Paramètres
3. [ ] Attendre 1 heure (ou modifier SESSION_TIMEOUT dans config.php pour tester)
4. [ ] Essayer d'exporter des données
5. [ ] Redirection vers page de login
6. [ ] Message: "Session expirée"

### Test R5: Plusieurs Onglets
1. [ ] Ouvrir Paramètres dans l'onglet 1
2. [ ] Ouvrir Paramètres dans l'onglet 2
3. [ ] Modifier le plan comptable dans l'onglet 1
4. [ ] Rafraîchir l'onglet 2
5. [ ] Les modifications apparaissent

### Test R6: Changement de Société Active
1. [ ] Créer 2 sociétés: Société A et Société B
2. [ ] Importer un plan comptable pour Société A
3. [ ] Changer vers Société B via le sélecteur
4. [ ] Vérifier que le plan comptable est vide (ou différent)
5. [ ] Importer un plan différent pour Société B
6. [ ] Revenir à Société A
7. [ ] Vérifier que le plan comptable de A est intact

---

## 📱 Tests Responsive

### Test Mobile (< 768px)
- [ ] Ouvrir la page sur mobile ou réduire la fenêtre
- [ ] Navigation latérale fonctionne
- [ ] Tous les boutons sont cliquables
- [ ] Les formulaires sont utilisables
- [ ] L'upload de fichier fonctionne
- [ ] Les modals s'affichent correctement

### Test Tablette (768px - 1024px)
- [ ] Tout fonctionne comme sur desktop
- [ ] Pas de débordement horizontal

---

## 🌐 Tests Cross-Browser

### Chrome
- [ ] Import CSV fonctionne
- [ ] Export CSV fonctionne
- [ ] Changement mot de passe fonctionne
- [ ] Tous les exports téléchargent correctement

### Firefox
- [ ] Mêmes tests que Chrome

### Safari
- [ ] Mêmes tests que Chrome

### Edge
- [ ] Mêmes tests que Chrome

---

## 🛠️ Tests de Performance

### Test P1: Temps de Chargement Initial
- [ ] La page se charge en < 2 secondes
- [ ] Les requêtes AJAX se complètent en < 1 seconde

### Test P2: Import de Gros Fichier
- [ ] Import de 1000 comptes en < 10 secondes
- [ ] Aucun timeout PHP

### Test P3: Export de Gros Volume
- [ ] Export de 10000 transactions en < 5 secondes
- [ ] Le fichier se télécharge sans corruption

---

## 📝 Tests de Validation

### Test V1: Messages d'Erreur
- [ ] Tous les messages d'erreur sont en français
- [ ] Les messages sont clairs et explicites
- [ ] Pas de messages techniques exposés à l'utilisateur

### Test V2: Messages de Succès
- [ ] Tous les messages de succès s'affichent
- [ ] Les messages disparaissent automatiquement après 3-5 secondes
- [ ] Les messages sont clairs

### Test V3: Indicateurs de Chargement
- [ ] Spinner s'affiche pendant les imports
- [ ] Spinner s'affiche pendant les exports
- [ ] Spinner s'affiche pendant les changements de mot de passe
- [ ] Les boutons sont désactivés pendant le traitement

---

## 🔍 Tests de Sécurité

### Test S1: Injection SQL
- [ ] Essayer d'importer un CSV avec `'; DROP TABLE users; --` dans le champ Intitulé
- [ ] Le compte est créé avec le texte littéral (pas d'exécution SQL)

### Test S2: XSS
- [ ] Créer un compte avec `<script>alert('XSS')</script>` dans l'intitulé
- [ ] Afficher le plan comptable
- [ ] Pas d'exécution de script (texte échappé)

### Test S3: Accès Non Autorisé
- [ ] Se déconnecter
- [ ] Essayer d'accéder à `assets/ajax/accounting_plan_import.php?action=export_csv`
- [ ] Erreur 401 Unauthorized

### Test S4: CSRF (Si implémenté)
- [ ] Vérifier présence de token CSRF dans les formulaires
- [ ] Essayer de soumettre sans token
- [ ] Requête rejetée

---

## ✅ Critères de Validation Globale

### Fonctionnel
- [ ] 100% des fonctionnalités décrites fonctionnent
- [ ] Aucune régression sur les fonctionnalités existantes
- [ ] Tous les exports sont utilisables (CSV ouvrable dans Excel)

### UX/UI
- [ ] Interface intuitive
- [ ] Messages clairs
- [ ] Pas d'erreur visible à l'utilisateur
- [ ] Responsive sur tous les écrans

### Performance
- [ ] Temps de réponse < 2 secondes pour toutes les actions
- [ ] Pas de timeout sur les imports/exports

### Sécurité
- [ ] Pas d'injection SQL possible
- [ ] Pas de XSS possible
- [ ] Authentification vérifiée sur tous les endpoints
- [ ] Mots de passe hashés avec bcrypt

### Code Quality
- [ ] Aucune erreur PHP dans les logs
- [ ] Aucune erreur JavaScript dans la console
- [ ] Code conforme aux standards du projet

---

## 📊 Rapport de Tests

**Testeur**: _______________
**Date**: _______________
**Environnement**: _______________
**Navigateur**: _______________

**Tests Réussis**: ___ / 150
**Tests Échoués**: ___ / 150
**Taux de Succès**: ____%

**Bugs Critiques Trouvés**: ___
**Bugs Mineurs Trouvés**: ___

**Commentaires**:
```
_______________________________________________
_______________________________________________
_______________________________________________
```

**Verdict Final**:
- [ ] ✅ Prêt pour la production
- [ ] ⚠️ Corrections mineures nécessaires
- [ ] ❌ Corrections majeures nécessaires

---

**Version**: 2.0.0
**Dernière mise à jour**: 12 novembre 2024
**Statut**: ✅ Checklist Complète
