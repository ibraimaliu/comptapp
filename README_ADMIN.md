# 🔐 Création d'un Compte Administrateur

Ce guide explique comment créer un compte administrateur pour l'application Gestion Comptable.

---

## 📋 Informations du Compte Admin

**Identifiants par défaut** :
- **Username** : `admin`
- **Email** : `admin@gestion-comptable.com`
- **Password** : `Admin@2025`

⚠️ **IMPORTANT** : Changez le mot de passe après la première connexion !

---

## 🚀 Méthode 1 : Via le Script PHP (Recommandé)

### Étapes :

1. **Ouvrez votre navigateur** et allez sur :
   ```
   http://localhost/gestion_comptable/create_admin.php
   ```

2. **Le script va automatiquement** :
   - ✅ Créer l'utilisateur administrateur
   - ✅ Créer une entreprise de test
   - ✅ Importer un plan comptable par défaut
   - ✅ Afficher les informations de connexion

3. **Une fois terminé** :
   - Notez vos identifiants
   - Supprimez le fichier `create_admin.php` pour la sécurité
   - Connectez-vous sur : `http://localhost/gestion_comptable`

### Avantages :
- ✅ Interface visuelle claire
- ✅ Vérifications automatiques
- ✅ Messages d'erreur détaillés
- ✅ Statistiques de la base de données

---

## 🗄️ Méthode 2 : Via SQL (phpMyAdmin)

### Étapes :

1. **Ouvrez phpMyAdmin** :
   ```
   http://localhost/phpmyadmin
   ```

2. **Connectez-vous** :
   - Utilisateur : `root`
   - Mot de passe : `Abil`

3. **Sélectionnez** la base de données `gestion_comptable`

4. **Cliquez** sur l'onglet "SQL"

5. **Importez** le fichier `create_admin.sql` :
   - Cliquez sur "Importer"
   - Choisissez le fichier `create_admin.sql`
   - Cliquez sur "Exécuter"

6. **Vérifiez** que tout s'est bien passé :
   - Vous devriez voir des messages "✅" dans les résultats

### Avantages :
- ✅ Plus rapide
- ✅ Parfait pour les utilisateurs avancés
- ✅ Peut être exécuté en ligne de commande

---

## 🗄️ Méthode 3 : Via MySQL en Ligne de Commande

### Étapes :

1. **Ouvrez un terminal/invite de commandes**

2. **Connectez-vous** à MySQL :
   ```bash
   mysql -u root -pAbil
   ```

3. **Exécutez** le script SQL :
   ```bash
   source C:/xampp/htdocs/gestion_comptable/create_admin.sql
   ```

   Ou sur Windows :
   ```bash
   mysql -u root -pAbil gestion_comptable < C:\xampp\htdocs\gestion_comptable\create_admin.sql
   ```

4. **Vérifiez** que le compte a été créé :
   ```sql
   USE gestion_comptable;
   SELECT * FROM users WHERE username = 'admin';
   ```

---

## ✅ Vérification de la Création

### Via l'Application

1. Allez sur : `http://localhost/gestion_comptable`
2. Cliquez sur "Se connecter"
3. Utilisez les identifiants :
   - Username : `admin`
   - Password : `Admin@2025`
4. Si vous êtes connecté → ✅ Succès !

### Via phpMyAdmin

1. Ouvrez phpMyAdmin
2. Sélectionnez la base `gestion_comptable`
3. Cliquez sur la table `users`
4. Vérifiez qu'il y a un utilisateur `admin`

---

## 🎯 Que va créer le script ?

### 1. Utilisateur Administrateur
```
Username : admin
Email    : admin@gestion-comptable.com
Password : Admin@2025 (hashé en bcrypt)
```

### 2. Entreprise de Test
```
Nom              : Entreprise de Test
Propriétaire     : Administrateur Système
Année fiscale    : 2025-01-01 à 2025-12-31
Statut TVA       : Assujetti
```

### 3. Plan Comptable (25 comptes)

**Classe 1 - Capitaux** :
- 101 - Capital
- 106 - Réserves
- 120 - Résultat de l'exercice

**Classe 2 - Immobilisations** :
- 211 - Terrains
- 213 - Constructions
- 218 - Matériel de bureau

**Classe 4 - Tiers** :
- 401 - Fournisseurs
- 411 - Clients
- 421 - Personnel - Rémunérations dues
- 437 - Autres organismes sociaux
- 445 - État - Taxes sur le chiffre d'affaires

**Classe 5 - Financiers** :
- 512 - Banque
- 530 - Caisse

**Classe 6 - Charges** :
- 601 - Achats de matières premières
- 606 - Achats non stockés
- 613 - Locations
- 615 - Entretien et réparations
- 621 - Personnel - Rémunérations
- 625 - Déplacements, missions
- 626 - Frais postaux et télécommunications
- 627 - Services bancaires

**Classe 7 - Produits** :
- 701 - Ventes de produits finis
- 706 - Prestations de services
- 708 - Produits des activités annexes

---

## 🔒 Sécurité

### Après la création du compte :

1. **Changez immédiatement le mot de passe** :
   - Connectez-vous
   - Allez dans Paramètres
   - Changez votre mot de passe

2. **Supprimez les fichiers de création** :
   ```bash
   # Supprimez ces fichiers pour la sécurité
   delete create_admin.php
   delete create_admin.sql (optionnel, vous pouvez le garder)
   ```

3. **Recommandations** :
   - ✅ Utilisez un mot de passe fort (12+ caractères)
   - ✅ Mélangez majuscules, minuscules, chiffres, symboles
   - ✅ Ne partagez jamais vos identifiants
   - ✅ Déconnectez-vous après chaque session

---

## ❌ Problèmes Courants

### Erreur : "Table 'gestion_comptable.users' doesn't exist"

**Solution** : Créez d'abord les tables
```bash
# Exécutez le script d'installation
http://localhost/gestion_comptable/install.php
```

### Erreur : "Access denied for user 'root'@'localhost'"

**Solution** : Vérifiez vos identifiants MySQL
- Ouvrez `config/database.php`
- Vérifiez que :
  - Username = `root`
  - Password = `Abil`

### Erreur : "Duplicate entry 'admin' for key 'username'"

**Solution** : Le compte admin existe déjà !
- Supprimez-le d'abord :
  ```sql
  DELETE FROM users WHERE username = 'admin';
  ```
- Ou utilisez un autre username dans le script

### Le script PHP ne s'affiche pas

**Solution** : Vérifiez que XAMPP est démarré
- Apache doit être en cours d'exécution
- MySQL doit être en cours d'exécution

---

## 📞 Support

### Fichiers de Documentation
- `STATUS_APPLICATION.md` - État complet de l'application
- `AMELIORATIONS_AJOUTEES.md` - Nouvelles fonctionnalités
- `README_ADMIN.md` - Ce fichier

### Base de Données
- **Host** : localhost
- **Database** : gestion_comptable
- **User** : root
- **Password** : Abil

### phpMyAdmin
- **URL** : http://localhost/phpmyadmin
- **User** : root
- **Password** : Abil

---

## 🧪 Test de Connexion

Après avoir créé le compte, testez-le :

```
1. Ouvrez : http://localhost/gestion_comptable
2. Cliquez sur "Se connecter"
3. Entrez :
   - Username : admin
   - Password : Admin@2025
4. Cliquez sur "Connexion"
5. Vous devriez voir le dashboard
```

**Si ça fonctionne** → ✅ Compte créé avec succès !

**Si ça ne fonctionne pas** → Vérifiez :
- [ ] XAMPP est démarré
- [ ] La base de données `gestion_comptable` existe
- [ ] Les tables sont créées (`install.php`)
- [ ] Le mot de passe est correct
- [ ] Pas d'erreur dans la console du navigateur (F12)

---

## 🎉 Prochaines Étapes

Après avoir créé et testé le compte admin :

1. ✅ Connectez-vous
2. ✅ Changez votre mot de passe
3. ✅ Créez votre première entreprise (ou utilisez "Entreprise de Test")
4. ✅ Ajoutez des contacts (clients/fournisseurs)
5. ✅ Créez vos premières transactions
6. ✅ Générez vos premières factures

**Bon travail avec votre application de Gestion Comptable ! 🎊**

---

*Généré le 2025-10-19 - Documentation Gestion Comptable*
