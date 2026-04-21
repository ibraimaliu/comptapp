# Guide de Déploiement - Refonte Page Paramètres

**Version**: 2.0.0
**Date**: 2024-11-12

---

## 📋 Vue d'Ensemble

Ce guide détaille le processus de déploiement de la refonte complète de la page Paramètres incluant:
- Import/Export CSV du plan comptable
- Gestion du profil utilisateur
- Exportation multi-format des données
- Sécurité et sauvegarde

---

## 🎯 Prérequis

### Environnement Serveur
- **PHP**: 7.4 ou supérieur
- **MySQL/MariaDB**: 5.7 ou supérieur
- **Apache**: 2.4 ou supérieur (ou Nginx équivalent)
- **Extensions PHP requises**:
  - PDO
  - pdo_mysql
  - mbstring
  - fileinfo

### Vérification des Extensions
```bash
php -m | grep -E "PDO|pdo_mysql|mbstring|fileinfo"
```

### Permissions Fichiers
- **Upload**: Le répertoire temporaire PHP doit être accessible en écriture
- **Session**: Le répertoire de session PHP doit être accessible en écriture

---

## 📦 Fichiers à Déployer

### Nouveaux Fichiers Backend (3 fichiers)

1. **assets/ajax/accounting_plan_import.php** (350 lignes)
   - Gestion import/export CSV du plan comptable
   - Actions: import_csv, export_csv, import_default, reset

2. **assets/ajax/user_profile.php** (180 lignes)
   - Gestion du profil utilisateur
   - Actions: get_profile, update_profile, change_password

3. **assets/ajax/data_export.php** (280 lignes)
   - Exportation multi-format des données
   - Types: transactions, invoices, contacts, accounting_plan, all

### Fichiers Modifiés

1. **assets/js/parametres.js**
   - Ajout: ~250 lignes de code
   - Fonctions: submitImportPlan, loadUserProfile, changeUserPassword, exportData

2. **views/parametres.php**
   - Ajout: ~135 lignes HTML
   - Sections: Export, Profil Utilisateur, Sécurité & Sauvegarde, Configuration Avancée

3. **assets/css/parametres.css**
   - Ajout: ~91 lignes CSS
   - Styles: Alert boxes, sections description, guide content

### Fichiers de Documentation (6 fichiers)

1. **plan_comptable_exemple.csv** - Exemple de plan comptable
2. **GUIDE_PARAMETRES.md** - Guide utilisateur
3. **REFONTE_PARAMETRES_COMPLETE.md** - Documentation technique
4. **PARAMETRES_README.md** - Guide rapide
5. **TESTS_PARAMETRES_CHECKLIST.md** - Checklist de tests
6. **DEPLOYMENT_PARAMETRES.md** - Ce fichier

---

## 🚀 Étapes de Déploiement

### Étape 1: Sauvegarde Complète ⚠️

**IMPORTANT**: Toujours sauvegarder avant un déploiement

```bash
# Sauvegarde de la base de données
mysqldump -u root -p gestion_comptable > backup_$(date +%Y%m%d_%H%M%S).sql

# Sauvegarde des fichiers
tar -czf backup_files_$(date +%Y%m%d_%H%M%S).tar.gz \
  assets/ajax/ \
  assets/js/ \
  assets/css/ \
  views/
```

### Étape 2: Vérification de l'Environnement

```bash
# Tester la connexion à la base de données
php -r "
require_once 'config/database.php';
\$db = new Database();
\$conn = \$db->getConnection();
echo 'DB Connection: ' . (\$conn ? 'OK' : 'FAILED') . PHP_EOL;
"

# Vérifier les permissions d'upload
php -r "
echo 'Upload Max Filesize: ' . ini_get('upload_max_filesize') . PHP_EOL;
echo 'Post Max Size: ' . ini_get('post_max_size') . PHP_EOL;
echo 'Temp Directory: ' . sys_get_temp_dir() . PHP_EOL;
echo 'Temp Writable: ' . (is_writable(sys_get_temp_dir()) ? 'YES' : 'NO') . PHP_EOL;
"
```

**Valeurs Recommandées**:
- `upload_max_filesize`: 10M minimum
- `post_max_size`: 12M minimum
- Temp directory: Accessible en écriture

### Étape 3: Déploiement des Fichiers Backend

```bash
# Créer les nouveaux fichiers AJAX
cd /path/to/gestion_comptable/assets/ajax/

# Copier accounting_plan_import.php
cp /source/accounting_plan_import.php .

# Copier user_profile.php
cp /source/user_profile.php .

# Copier data_export.php
cp /source/data_export.php .

# Vérifier les permissions (664 recommandé)
chmod 664 accounting_plan_import.php user_profile.php data_export.php
```

### Étape 4: Mise à Jour des Fichiers Existants

```bash
# Sauvegarder les versions actuelles
cp assets/js/parametres.js assets/js/parametres.js.backup
cp views/parametres.php views/parametres.php.backup
cp assets/css/parametres.css assets/css/parametres.css.backup

# Déployer les nouvelles versions
cp /source/parametres.js assets/js/
cp /source/parametres.php views/
cp /source/parametres.css assets/css/

# Vérifier les permissions
chmod 664 assets/js/parametres.js
chmod 664 views/parametres.php
chmod 664 assets/css/parametres.css
```

### Étape 5: Déploiement des Fichiers de Documentation

```bash
# Copier à la racine du projet
cp /source/plan_comptable_exemple.csv .
cp /source/GUIDE_PARAMETRES.md .
cp /source/REFONTE_PARAMETRES_COMPLETE.md .
cp /source/PARAMETRES_README.md .
cp /source/TESTS_PARAMETRES_CHECKLIST.md .
cp /source/DEPLOYMENT_PARAMETRES.md .

# Permissions en lecture seule
chmod 644 *.md *.csv
```

### Étape 6: Vérification de l'Intégrité

```bash
# Vérifier que tous les fichiers existent
ls -lh assets/ajax/accounting_plan_import.php
ls -lh assets/ajax/user_profile.php
ls -lh assets/ajax/data_export.php
ls -lh assets/js/parametres.js
ls -lh views/parametres.php
ls -lh assets/css/parametres.css
ls -lh plan_comptable_exemple.csv

# Vérifier la syntaxe PHP
php -l assets/ajax/accounting_plan_import.php
php -l assets/ajax/user_profile.php
php -l assets/ajax/data_export.php
php -l views/parametres.php
```

**Résultat Attendu**: `No syntax errors detected in ...`

### Étape 7: Configuration PHP (si nécessaire)

Modifier `php.ini` si les valeurs sont insuffisantes:

```ini
# Augmenter la taille maximale des uploads
upload_max_filesize = 10M
post_max_size = 12M
max_execution_time = 60
memory_limit = 256M

# Assurer l'encodage UTF-8
default_charset = "UTF-8"
```

Redémarrer Apache après modification:
```bash
sudo systemctl restart apache2
# Ou pour XAMPP
sudo /opt/lampp/lampp restart
```

### Étape 8: Test de Smoke

**Tests Rapides Post-Déploiement**:

1. **Accès à la Page**
   ```bash
   curl -I http://localhost/gestion_comptable/index.php?page=parametres
   # Doit retourner: HTTP/1.1 200 OK
   ```

2. **Test Endpoint Import**
   - Se connecter à l'application
   - Aller sur Paramètres → Plan Comptable
   - Cliquer sur "Importer" → Modal s'ouvre ✅

3. **Test Endpoint Profil**
   - Aller sur Paramètres → Profil Utilisateur
   - Les informations se chargent ✅

4. **Test Endpoint Export**
   - Aller sur Paramètres → Exportation de données
   - Sélectionner "Contacts" + "CSV"
   - Cliquer "Télécharger" → Fichier téléchargé ✅

### Étape 9: Vérification des Logs

```bash
# Logs Apache
tail -f /var/log/apache2/error.log
# Ou pour XAMPP
tail -f /opt/lampp/logs/error_log

# Rechercher les erreurs PHP
grep -i "error\|warning" /var/log/apache2/error.log | tail -20
```

**Aucune erreur ne devrait apparaître lors des tests.**

### Étape 10: Tests Fonctionnels Complets

Exécuter la checklist complète: **TESTS_PARAMETRES_CHECKLIST.md**

**Tests Critiques Minimum**:
- [ ] Import CSV avec `plan_comptable_exemple.csv` → Succès
- [ ] Export CSV du plan comptable → Fichier téléchargé et lisible dans Excel
- [ ] Changement de mot de passe → Succès et nouvelle connexion fonctionne
- [ ] Export complet JSON → Fichier téléchargé et JSON valide

---

## 🔧 Configuration Avancée

### Limites de Taille de Fichier CSV

Par défaut, les fichiers CSV peuvent atteindre 10 MB. Pour augmenter:

**Option 1: Via .htaccess**
```apache
php_value upload_max_filesize 20M
php_value post_max_size 22M
```

**Option 2: Via php.ini**
```ini
upload_max_filesize = 20M
post_max_size = 22M
```

### Timeout pour Gros Imports

Pour importer des fichiers CSV très volumineux (>5000 lignes):

```ini
max_execution_time = 120
max_input_time = 120
```

### Encodage CSV

L'application supporte:
- **UTF-8 avec BOM** (Excel Windows)
- **UTF-8 sans BOM** (Linux/Mac)
- **ISO-8859-1** (conversion automatique)

Pas de configuration nécessaire, la détection est automatique.

---

## 🐛 Résolution de Problèmes

### Problème 1: Import CSV Échoue

**Symptôme**: Message d'erreur "Erreur lors de l'upload du fichier"

**Solutions**:
1. Vérifier `upload_max_filesize` et `post_max_size` dans `php.ini`
2. Vérifier les permissions du répertoire temporaire:
   ```bash
   ls -ld $(php -r "echo sys_get_temp_dir();")
   ```
3. Vérifier les logs Apache pour l'erreur exacte
4. Tester avec un fichier plus petit (< 1 MB)

### Problème 2: Export CSV Vide

**Symptôme**: Le fichier CSV téléchargé ne contient que les en-têtes

**Solutions**:
1. Vérifier qu'une société est sélectionnée:
   ```php
   // Dans la console du navigateur (F12)
   console.log(sessionStorage.getItem('company_id'));
   ```
2. Vérifier que des données existent dans la base:
   ```sql
   SELECT COUNT(*) FROM accounting_plan WHERE company_id = 1;
   ```
3. Vérifier les logs PHP pour des erreurs SQL

### Problème 3: Caractères Accentués Incorrects dans Excel

**Symptôme**: Les é, è, à s'affichent mal dans Excel

**Cause**: BOM UTF-8 manquant

**Solution**: Vérifier que le code contient:
```php
// Dans outputCSV()
echo "\xEF\xBB\xBF"; // UTF-8 BOM
```

Si le problème persiste, ouvrir le CSV dans Excel via:
1. Données → Importer depuis un fichier texte
2. Sélectionner encodage: UTF-8
3. Sélectionner séparateur: Point-virgule

### Problème 4: Changement Mot de Passe Échoue

**Symptôme**: Message "Mot de passe actuel incorrect" alors qu'il est bon

**Solutions**:
1. Vérifier que le hash bcrypt existe dans la DB:
   ```sql
   SELECT password FROM users WHERE id = 1;
   -- Doit commencer par $2y$
   ```
2. Si le hash est en MD5 ou SHA1, réinitialiser:
   ```sql
   UPDATE users
   SET password = '$2y$10$...' -- Générer avec password_hash()
   WHERE id = 1;
   ```
3. Vérifier la version de PHP (>= 7.4 recommandé)

### Problème 5: Session Expire Trop Vite

**Symptôme**: Déconnexion après quelques minutes

**Solutions**:
1. Augmenter le timeout de session dans `config/config.php`:
   ```php
   define('SESSION_TIMEOUT', 7200); // 2 heures au lieu de 1 heure
   ```
2. Modifier `php.ini`:
   ```ini
   session.gc_maxlifetime = 7200
   ```
3. Redémarrer Apache

### Problème 6: Modal d'Import Ne S'Ouvre Pas

**Symptôme**: Clic sur "Importer" ne fait rien

**Solutions**:
1. Ouvrir la console (F12) et chercher des erreurs JavaScript
2. Vérifier que `parametres.js` est bien chargé:
   ```javascript
   // Dans la console
   typeof openModal
   // Doit retourner: "function"
   ```
3. Vider le cache du navigateur (Ctrl+F5)
4. Vérifier dans `views/parametres.php` que le modal existe:
   ```html
   <div id="importPlanModal" class="modal">
   ```

---

## 🔒 Sécurité

### Checklist de Sécurité

- [ ] **Sessions**: Custom session name `COMPTAPP_SESSION` configuré
- [ ] **Authentication**: Tous les endpoints AJAX vérifient `$_SESSION['user_id']`
- [ ] **SQL Injection**: Toutes les requêtes utilisent des prepared statements
- [ ] **XSS**: Tous les outputs utilisent `htmlspecialchars()`
- [ ] **File Upload**: Validation de type MIME pour les CSV
- [ ] **Password**: Bcrypt avec `PASSWORD_BCRYPT` (cost 10)
- [ ] **HTTPS**: Recommandé en production (optionnel en dev)

### Hardening Recommandé

**1. Bloquer l'accès direct aux fichiers AJAX**

Ajouter dans `assets/ajax/.htaccess`:
```apache
<Files *.php>
    Order Deny,Allow
    Deny from all
    Allow from 127.0.0.1
    Allow from ::1
</Files>
```

Puis dans chaque fichier PHP, vérifier:
```php
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}
```

**2. Désactiver l'affichage des erreurs en production**

Dans `php.ini` ou `.htaccess`:
```ini
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
```

**3. Limiter les tentatives de changement de mot de passe**

Ajouter un rate limiting (à implémenter):
```php
// assets/ajax/user_profile.php
// Max 5 tentatives par heure
if (getPasswordAttempts($user_id) > 5) {
    die(json_encode(['success' => false, 'error' => 'Trop de tentatives']));
}
```

---

## 📊 Monitoring Post-Déploiement

### Métriques à Surveiller

**Jour 1-7**:
- Nombre d'imports CSV réussis vs échoués
- Taille moyenne des fichiers CSV importés
- Temps moyen d'import
- Nombre de changements de mot de passe
- Nombre d'exports (par type)

### Logs à Monitorer

```bash
# Erreurs PHP
tail -f /var/log/apache2/error.log | grep "gestion_comptable"

# Requêtes lentes MySQL
tail -f /var/log/mysql/slow-query.log

# Accès aux endpoints
tail -f /var/log/apache2/access.log | grep "accounting_plan_import\|user_profile\|data_export"
```

### Alertes Recommandées

1. **Erreur 500 sur les endpoints AJAX** → Critique
2. **Temps d'import > 30 secondes** → Warning
3. **Plus de 10 échecs de changement de mot de passe par heure** → Potentielle attaque brute-force
4. **Fichier CSV > 50 MB** → Investigation (possible upload malveillant)

---

## 🔄 Rollback

En cas de problème critique, rollback rapide:

### Étape 1: Restaurer les Fichiers

```bash
# Restaurer les fichiers modifiés
cp assets/js/parametres.js.backup assets/js/parametres.js
cp views/parametres.php.backup views/parametres.php
cp assets/css/parametres.css.backup assets/css/parametres.css

# Supprimer les nouveaux fichiers
rm assets/ajax/accounting_plan_import.php
rm assets/ajax/user_profile.php
rm assets/ajax/data_export.php
```

### Étape 2: Restaurer la Base de Données (si modifiée)

```bash
mysql -u root -p gestion_comptable < backup_YYYYMMDD_HHMMSS.sql
```

### Étape 3: Vider le Cache

```bash
# Cache navigateur: Demander aux utilisateurs de faire Ctrl+F5
# Cache PHP Opcache (si activé)
sudo systemctl restart apache2
```

### Étape 4: Vérifier

```bash
curl -I http://localhost/gestion_comptable/index.php?page=parametres
# Doit retourner 200 OK
```

---

## ✅ Checklist de Déploiement

### Pré-Déploiement
- [ ] Sauvegarde complète de la base de données
- [ ] Sauvegarde complète des fichiers
- [ ] Vérification de l'environnement (PHP, MySQL, extensions)
- [ ] Tests locaux réussis (100%)
- [ ] Documentation à jour

### Déploiement
- [ ] Fichiers backend déployés (3 nouveaux)
- [ ] Fichiers modifiés déployés (3 fichiers)
- [ ] Documentation déployée (6 fichiers)
- [ ] Vérification syntaxe PHP (0 erreur)
- [ ] Permissions fichiers correctes

### Post-Déploiement
- [ ] Tests de smoke réussis (4 tests minimum)
- [ ] Vérification des logs (0 erreur)
- [ ] Tests fonctionnels critiques réussis
- [ ] Import CSV testé avec `plan_comptable_exemple.csv`
- [ ] Export CSV testé et fichier lisible dans Excel
- [ ] Changement de mot de passe testé
- [ ] Monitoring activé

### Validation
- [ ] Aucune régression détectée
- [ ] Performances acceptables (< 2s par action)
- [ ] Documentation accessible aux utilisateurs
- [ ] Support technique informé

---

## 📞 Support

### En Cas de Problème

1. **Vérifier les logs**:
   - Apache error log
   - PHP error log
   - MySQL slow query log

2. **Consulter la documentation**:
   - `GUIDE_PARAMETRES.md` pour l'utilisation
   - `REFONTE_PARAMETRES_COMPLETE.md` pour les détails techniques
   - `TESTS_PARAMETRES_CHECKLIST.md` pour les tests

3. **Rollback si critique**:
   - Suivre la procédure de rollback ci-dessus
   - Documenter le problème rencontré

4. **Contact**:
   - Créer un ticket avec:
     - Description du problème
     - Logs d'erreur
     - Étapes de reproduction
     - Environnement (PHP version, OS, navigateur)

---

## 📈 Prochaines Étapes

Après un déploiement réussi, considérer:

1. **Formation des utilisateurs**:
   - Distribuer `GUIDE_PARAMETRES.md`
   - Organiser une démo de 30 minutes
   - Répondre aux questions

2. **Optimisations**:
   - Mise en cache des exports fréquents
   - Compression des fichiers CSV volumineux
   - Import asynchrone pour très gros fichiers

3. **Fonctionnalités futures**:
   - Import/export Excel (.xlsx) en plus de CSV
   - Planification d'exports automatiques
   - Historique des modifications du plan comptable
   - Authentification à deux facteurs

---

**Version**: 2.0.0
**Dernière mise à jour**: 12 novembre 2024
**Statut**: ✅ Prêt pour Déploiement Production
