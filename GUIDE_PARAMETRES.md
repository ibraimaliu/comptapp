# Guide de la Page Paramètres
**Version**: 2.0.0
**Date**: 2024-11-12

---

## Table des Matières
1. [Informations de la Société](#informations-de-la-société)
2. [Configuration QR-Factures](#configuration-qr-factures)
3. [Plan Comptable](#plan-comptable)
4. [Catégories de Dépenses](#catégories-de-dépenses)
5. [Taux TVA](#taux-tva)
6. [Exportation de Données](#exportation-de-données)
7. [Profil Utilisateur](#profil-utilisateur)
8. [Sécurité & Sauvegarde](#sécurité--sauvegarde)

---

## Informations de la Société

### Données Modifiables
- Nom de la société
- Prénom et nom du propriétaire
- Période comptable (début/fin d'exercice)
- Statut TVA (soumis ou non)

### Actions Disponibles
- **Modifier** : Cliquez sur le bouton "Modifier" pour ouvrir le formulaire d'édition

---

## Configuration QR-Factures

### Prérequis
Pour générer des QR-factures suisses conformes (ISO 20022), vous devez configurer :

1. **QR-IBAN** (obligatoire)
   - Format : CH + 19 chiffres
   - Les positions 5-9 doivent être entre 30000-31999
   - Exemple : `CH58 3000 0001 2345 6789 0`
   - Contactez votre banque pour obtenir un QR-IBAN

2. **IBAN Bancaire Classique** (optionnel)
   - Pour les paiements standards sans QR-facture
   - Format : CH + 19 chiffres
   - Exemple : `CH93 0076 2011 6238 5295 7`

3. **Adresse Complète** (obligatoire)
   - Rue et numéro
   - Code postal
   - Ville
   - Pays (CH par défaut)

### Fonctionnalités
- ✅ Validation automatique des IBAN
- ✅ Génération de références QRR uniques (27 chiffres)
- ✅ QR-Codes conformes ISO 20022
- ✅ PDF professionnels avec section de paiement détachable
- ✅ Compatible avec toutes les apps bancaires suisses

### Guide d'Utilisation
1. Cliquez sur "Modifier" dans la section QR-Factures
2. Remplissez le formulaire avec vos informations bancaires
3. Cliquez sur "Valider IBAN" pour vérifier le QR-IBAN
4. Enregistrez les modifications
5. Vous pouvez maintenant générer des QR-factures depuis le module "Factures"

---

## Plan Comptable

### Import CSV

Le système supporte l'import de plans comptables au format CSV avec les colonnes suivantes :

**Format du fichier CSV:**
```
Numéro;Intitulé;Catégorie;Type
1000;Caisse;Actif;Bilan
1020;Banque;Actif;Bilan
```

**Colonnes Requises:**
- `Numéro` : Numéro de compte (ex: 1000, 6300)
- `Intitulé` : Nom du compte (ex: "Caisse", "Loyers")
- `Catégorie` : Actif, Passif, Charge, ou Produit
- `Type` : Bilan ou Résultat

**Fichier Exemple:**
Un fichier `plan_comptable_exemple.csv` est fourni à la racine du projet avec un plan comptable PME suisse standard.

### Modes d'Import

1. **Remplacer le plan actuel**
   - Supprime tous les comptes non utilisés
   - Importe les nouveaux comptes
   - ⚠️ Les comptes utilisés dans des transactions sont conservés

2. **Ajouter au plan actuel**
   - Conserve les comptes existants
   - Ajoute uniquement les nouveaux comptes
   - Ignore les comptes en doublon

### Procédure d'Import

1. Préparez votre fichier CSV selon le format ci-dessus
2. Cliquez sur "Importer" dans la section Plan Comptable
3. Sélectionnez votre fichier CSV
4. Choisissez l'action (Remplacer ou Ajouter)
5. Cliquez sur "Importer"
6. Le système affiche un rapport d'import avec les comptes importés et les erreurs

### Export CSV

Le plan comptable peut être exporté au format CSV :
- Cliquez sur "Exporter" dans la section Plan Comptable
- Le fichier CSV sera téléchargé avec le nom `plan_comptable_YYYY-MM-DD_HH-mm-ss.csv`
- Format UTF-8 compatible Excel

### Réinitialisation

Le bouton "Réinitialiser" supprime **uniquement les comptes non utilisés**.
Les comptes liés à des transactions sont conservés pour l'intégrité des données.

---

## Catégories de Dépenses

### Gestion des Catégories

Les catégories permettent de classifier les transactions pour faciliter l'analyse comptable.

**Actions Disponibles:**
- ✚ Ajouter une nouvelle catégorie
- ✏️ Modifier une catégorie existante
- 🗑️ Supprimer une catégorie non utilisée

**Catégories Courantes:**
- Fournitures
- Déplacements
- Marketing
- Télécommunications
- Charges sociales
- etc.

---

## Taux TVA

### Taux Suisses Standards

Le système est préconfiguré avec les taux TVA suisses :
- **7.7%** : Taux normal
- **2.5%** : Taux réduit
- **0.0%** : Exonéré

### Gestion des Taux

Vous pouvez :
- Ajouter des taux personnalisés
- Modifier les taux existants
- Supprimer les taux non utilisés

⚠️ **Important** : Les taux liés à des factures ou transactions existantes ne peuvent pas être supprimés.

---

## Exportation de Données

### Types d'Export Disponibles

1. **Transactions**
   - Toutes les opérations comptables
   - Inclut : date, type, montant, catégorie, compte, méthode de paiement

2. **Factures**
   - Toutes les factures clients
   - Inclut : numéro, date, client, montants, TVA, statut

3. **Contacts**
   - Clients et fournisseurs
   - Inclut : nom, type, coordonnées, adresse

4. **Plan Comptable**
   - Tous les comptes définis
   - Inclut : numéro, intitulé, catégorie, type

5. **Toutes les Données** (JSON uniquement)
   - Export complet de la base de données
   - Recommandé pour sauvegardes

### Formats Supportés

**CSV (Recommandé pour Excel):**
- Compatible Excel et Google Sheets
- Encodage UTF-8 avec BOM
- Séparateur : point-virgule (;)
- Idéal pour l'analyse de données

**JSON:**
- Format structuré
- Recommandé pour sauvegardes complètes
- Idéal pour import dans d'autres systèmes
- Conserve tous les types de données

### Procédure d'Export

1. Sélectionnez le type de données à exporter
2. Choisissez le format (CSV ou JSON)
3. Cliquez sur "Télécharger l'export"
4. Le fichier sera téléchargé automatiquement

---

## Profil Utilisateur

### Informations Modifiables

**Compte:**
- Nom d'utilisateur (non modifiable après création)
- Adresse email
- Membre depuis (date de création du compte)

**Mot de Passe:**
- Nécessite le mot de passe actuel
- Nouveau mot de passe (minimum 8 caractères)
- Confirmation du nouveau mot de passe

### Sécurité du Mot de Passe

Critères de sécurité :
- ✅ Minimum 8 caractères
- ⚡ Recommandé : combinaison de lettres, chiffres et symboles
- 🔐 Ne partagez jamais votre mot de passe
- 🔄 Changez régulièrement votre mot de passe

---

## Sécurité & Sauvegarde

### Sauvegarde Automatique

**Recommandations:**
- 📅 Sauvegarde mensuelle minimum
- 💾 Stockage sécurisé (disque externe, cloud crypté)
- 🔄 Conservation de plusieurs versions
- ✅ Test de restauration périodique

**Procédure de Sauvegarde:**
1. Accédez à la section "Sécurité & Sauvegarde"
2. Cliquez sur "Télécharger sauvegarde complète (JSON)"
3. Le fichier contient toutes les données de l'entreprise
4. Stockez le fichier dans un endroit sûr

### Conseils de Sécurité

**Bonnes Pratiques:**
- 🔒 Utilisez un mot de passe fort et unique
- 👥 Ne partagez jamais vos identifiants
- 🚪 Déconnectez-vous après chaque session
- 💾 Effectuez des sauvegardes régulières
- 📧 Vérifiez l'authenticité des emails suspects
- 🔐 Activez la double authentification si disponible

**Sécurité de Session:**
- Session automatique nommée : COMPTAPP_SESSION
- Timeout après 1 heure d'inactivité
- Protection CSRF sur tous les formulaires
- Hachage bcrypt des mots de passe

---

## Résolution de Problèmes

### Import du Plan Comptable Échoue

**Problème** : Le fichier CSV n'est pas accepté

**Solutions:**
1. Vérifiez le format du fichier (doit être .csv)
2. Assurez-vous que le séparateur est un point-virgule (;)
3. Vérifiez que les colonnes sont : Numéro;Intitulé;Catégorie;Type
4. Les catégories doivent être : Actif, Passif, Charge, ou Produit
5. Les types doivent être : Bilan ou Résultat

**Problème** : Certains comptes ne s'importent pas

**Solutions:**
1. Vérifiez qu'il n'y a pas de doublons de numéros de compte
2. En mode "Ajouter", les comptes existants sont ignorés
3. Utilisez "Remplacer" pour écraser le plan (comptes non utilisés uniquement)

### QR-IBAN Invalide

**Problème** : La validation IBAN échoue

**Solutions:**
1. Vérifiez le format : CH + 19 chiffres (sans espaces)
2. Pour un QR-IBAN, les positions 5-9 doivent être entre 30000-31999
3. Exemple valide : CH5830000001234567890
4. Contactez votre banque pour obtenir le bon QR-IBAN

### Export de Données Vide

**Problème** : Le fichier exporté ne contient pas de données

**Solutions:**
1. Vérifiez que vous avez des données dans la catégorie sélectionnée
2. Assurez-vous qu'une société est sélectionnée
3. Vérifiez les permissions d'accès aux données

### Changement de Mot de Passe Échoue

**Problème** : Impossible de changer le mot de passe

**Solutions:**
1. Vérifiez que le mot de passe actuel est correct
2. Le nouveau mot de passe doit contenir minimum 8 caractères
3. Les deux mots de passe (nouveau et confirmation) doivent être identiques
4. Essayez de vous déconnecter et reconnecter

---

## Support et Contact

Pour toute question ou problème technique :
1. Consultez d'abord ce guide
2. Vérifiez les logs d'erreur dans la console du navigateur (F12)
3. Contactez l'administrateur système

---

**Dernière mise à jour** : 12 novembre 2024
**Version de l'application** : 2.0.0
