# 🔧 Correction de l'Erreur de Login

**Erreur rencontrée** :
```
SyntaxError: JSON.parse: unexpected character at line 1 column 1 of the JSON data
```

Cette erreur signifie que l'API `auth.php` ne retourne pas du JSON pur, mais probablement du HTML ou du texte avant le JSON.

---

## ✅ Solutions Appliquées

### 1. Désactivation de `display_errors`
Le fichier `api/auth.php` a été modifié pour désactiver l'affichage des erreurs qui pourraient polluer la sortie JSON.

**Ligne 16-19 modifiée** :
```php
// Désactiver l'affichage des erreurs pour éviter les sorties non-JSON
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
```

### 2. Protection contre le double `session_start()`
Le fichier `api/auth.php` vérifie maintenant si une session est déjà démarrée avant d'en créer une.

**Ligne 100-103 modifiée** :
```php
// Démarrer la session si elle n'est pas déjà démarrée
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
```

---

## 🧪 Comment Tester la Correction

### Méthode 1 : Fichier de Test
J'ai créé un fichier de test : `api/test_auth.php`

1. Ouvrez votre navigateur
2. Allez sur : `http://localhost/gestion_comptable/api/test_auth.php`
3. Vous verrez :
   - ✅ La requête envoyée
   - ✅ La réponse JSON reçue
   - ✅ L'analyse du JSON

**Si le JSON est valide** → Problème résolu !
**Si le JSON est invalide** → Suivez les étapes ci-dessous

### Méthode 2 : Test Direct de Login
1. Ouvrez : `http://localhost/gestion_comptable/index.php?page=login`
2. Entrez :
   - Username : `admin`
   - Password : `Admin@2025`
3. Cliquez sur "Se connecter"
4. **Si ça fonctionne** → ✅ Problème résolu !

---

## 🔍 Diagnostic Plus Poussé

Si l'erreur persiste, suivez ces étapes :

### Étape 1 : Vérifier la Sortie Brute de l'API

1. Ouvrez votre navigateur
2. Appuyez sur `F12` pour ouvrir la console développeur
3. Allez dans l'onglet "Réseau" (Network)
4. Connectez-vous via la page de login
5. Cliquez sur la requête vers `auth.php`
6. Regardez l'onglet "Réponse" (Response)

**Ce que vous devriez voir** :
```json
{
  "success": true,
  "message": "Connexion réussie",
  "user": {
    "id": 1,
    "username": "admin",
    "email": "admin@gestion-comptable.com"
  }
}
```

**Si vous voyez du HTML ou des erreurs PHP AVANT le JSON** :
- C'est le problème ! Voir les solutions ci-dessous.

### Étape 2 : Vérifier les Logs d'Erreur

**Sur Windows avec XAMPP** :
1. Ouvrez le fichier : `C:\xampp\apache\logs\error.log`
2. Cherchez les erreurs récentes
3. Notez les erreurs PHP qui apparaissent

**Erreurs courantes** :
- `Cannot modify header information` → Sortie avant les headers
- `session_start()` : A session had already been started → Double session
- `Class 'Database' not found` → Problème d'inclusion

---

## 🛠️ Solutions Supplémentaires

### Solution A : Vérifier config.php

Le fichier `config/config.php` appelle `session_start()` au démarrage.

**Problème** : Si auth.php inclut config.php indirectement, il y aura un conflit.

**Solution** : S'assurer que `config/database.php` n'inclut PAS `config/config.php`

Vérifiez le fichier `config/database.php` :
```php
<?php
class Database {
    private $host = "localhost";
    private $db_name = "gestion_comptable";
    private $username = "root";
    private $password = "Abil";
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // NE PAS FAIRE echo ici car ça pollue le JSON !
            error_log("Erreur de connexion: " . $exception->getMessage());
        }
        return $this->conn;
    }
}
?>
```

**IMPORTANT** : Il ne doit PAS y avoir de `echo`, `print`, ou `var_dump` dans ce fichier !

### Solution B : Nettoyer les Espaces/Lignes Vides

Parfois, des espaces ou lignes vides AVANT `<?php` ou APRÈS `?>` peuvent causer le problème.

**Dans `api/auth.php`** :
- Assurez-vous qu'il n'y a AUCUN caractère avant `<?php` à la ligne 1
- Supprimez le `?>` final s'il existe (pas nécessaire en PHP)

**Dans `config/database.php`** :
- Même vérification

**Dans `models/User.php`** :
- Même vérification

### Solution C : Vérifier l'Encodage des Fichiers

Les fichiers doivent être en **UTF-8 sans BOM**.

**Avec VSCode** :
1. Ouvrez le fichier `api/auth.php`
2. En bas à droite, vérifiez l'encodage
3. S'il indique "UTF-8 with BOM", changez en "UTF-8"
4. Sauvegardez

### Solution D : Tester l'API Directement

Créez un fichier `test_simple.php` dans `api/` :

```php
<?php
header("Content-Type: application/json; charset=UTF-8");

echo json_encode([
    "success" => true,
    "message" => "API fonctionne !",
    "test" => "OK"
]);
?>
```

Testez : `http://localhost/gestion_comptable/api/test_simple.php`

**Si ça affiche du JSON propre** → Le problème est dans auth.php
**Si ça affiche autre chose** → Problème de configuration Apache/PHP

---

## 🔧 Correction Manuelle si Nécessaire

Si le problème persiste, voici un fichier `api/auth.php` simplifié et garanti sans erreur :

<details>
<summary>Cliquez pour voir le code de remplacement</summary>

```php
<?php
// En-têtes pour API REST (DOIT être la première ligne !)
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");

// Désactiver TOUT affichage d'erreurs
ini_set('display_errors', 0);
error_reporting(0);

// Inclure les fichiers (sans config.php qui fait session_start)
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';

// Fonction helper pour renvoyer du JSON
function returnJSON($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

// Récupérer les données POST
$input = file_get_contents("php://input");
$data = json_decode($input);

// Vérifier le JSON
if ($data === null) {
    returnJSON(["success" => false, "message" => "JSON invalide"], 400);
}

// Vérifier l'action
if (empty($data->action)) {
    returnJSON(["success" => false, "message" => "Action non spécifiée"], 400);
}

// Connexion DB
try {
    $database = new Database();
    $db = $database->getConnection();
    if (!$db) {
        returnJSON(["success" => false, "message" => "Erreur DB"], 500);
    }
} catch (Exception $e) {
    returnJSON(["success" => false, "message" => "Erreur connexion"], 500);
}

$user = new User($db);

// Traiter l'action
switch ($data->action) {
    case 'login':
        if (empty($data->username) || empty($data->password)) {
            returnJSON(["success" => false, "message" => "Champs requis"], 400);
        }

        $user->username = htmlspecialchars(strip_tags($data->username));
        $password = $data->password;

        if ($user->userExists()) {
            if (password_verify($password, $user->password)) {
                // Session
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['user_id'] = $user->id;
                $_SESSION['username'] = $user->username;
                $_SESSION['email'] = $user->email;

                returnJSON([
                    "success" => true,
                    "message" => "Connexion réussie",
                    "user" => [
                        "id" => $user->id,
                        "username" => $user->username,
                        "email" => $user->email
                    ]
                ]);
            } else {
                returnJSON(["success" => false, "message" => "Mot de passe incorrect"], 401);
            }
        } else {
            returnJSON(["success" => false, "message" => "Utilisateur introuvable"], 401);
        }
        break;

    case 'register':
        // ... code d'inscription ...
        returnJSON(["success" => false, "message" => "Inscription non implémentée"], 501);
        break;

    case 'logout':
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        session_destroy();
        returnJSON(["success" => true, "message" => "Déconnexion réussie"]);
        break;

    default:
        returnJSON(["success" => false, "message" => "Action inconnue"], 400);
}
```

</details>

---

## ✅ Vérification Finale

Après avoir appliqué les corrections :

1. **Videz le cache du navigateur** (Ctrl + Shift + Delete)
2. **Redémarrez Apache** dans XAMPP
3. **Testez à nouveau** le login

---

## 📞 Support

Si le problème persiste après toutes ces étapes :

1. Vérifiez le fichier `test_auth.php` pour voir la sortie exacte
2. Consultez les logs Apache : `C:\xampp\apache\logs\error.log`
3. Vérifiez que PHP fonctionne : créez un fichier `info.php` avec `<?php phpinfo(); ?>` et testez-le

---

**Bonne chance ! Le problème devrait être résolu avec ces corrections. 🎉**
