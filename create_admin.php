<?php
/**
 * Script de Création d'un Compte Administrateur
 * Base de données: gestion_comptable
 *
 * UTILISATION:
 * 1. Accédez à ce script via votre navigateur: http://localhost/gestion_comptable/create_admin.php
 * 2. Le compte admin sera créé automatiquement
 * 3. Supprimez ce fichier après utilisation pour des raisons de sécurité
 *
 * IDENTIFIANTS CRÉÉS:
 * - Username: admin
 * - Email: admin@gestion-comptable.com
 * - Password: Admin@2025
 */

// Configuration
define('ADMIN_USERNAME', 'admin');
define('ADMIN_EMAIL', 'admin@gestion-comptable.com');
define('ADMIN_PASSWORD', 'Admin@2025');

// Inclure la configuration de la base de données
require_once 'config/database.php';
require_once 'models/User.php';
require_once 'models/Company.php';
require_once 'models/AccountingPlan.php';

// Style CSS pour l'affichage
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Création Compte Administrateur</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #28a745;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #dc3545;
            margin: 15px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #17a2b8;
            margin: 15px 0;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #ffc107;
            margin: 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #667eea;
            color: white;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
        .btn:hover {
            background: #764ba2;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d63384;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Création d'un Compte Administrateur</h1>

        <?php
        try {
            // Connexion à la base de données
            $database = new Database();
            $db = $database->getConnection();

            if (!$db) {
                throw new Exception("Impossible de se connecter à la base de données");
            }

            echo '<div class="info">✓ Connexion à la base de données réussie</div>';

            // Vérifier si l'utilisateur existe déjà
            $user = new User($db);

            if ($user->userExists(ADMIN_USERNAME)) {
                echo '<div class="warning">⚠️ L\'utilisateur "' . ADMIN_USERNAME . '" existe déjà!</div>';
                echo '<div class="info">Vous pouvez vous connecter avec les identifiants existants.</div>';
            } else {
                // Créer l'utilisateur admin
                $user->username = ADMIN_USERNAME;
                $user->email = ADMIN_EMAIL;
                $user->password = password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT);

                if ($user->create()) {
                    echo '<div class="success">✅ Utilisateur administrateur créé avec succès!</div>';

                    // Récupérer l'ID de l'utilisateur créé
                    $query = "SELECT id FROM users WHERE username = :username";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':username', $user->username);
                    $stmt->execute();
                    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $user_id = $user_data['id'];

                    echo '<div class="info">User ID: ' . $user_id . '</div>';

                    // Créer une entreprise de test
                    $company = new Company($db);
                    $company->user_id = $user_id;
                    $company->name = 'Entreprise de Test';
                    $company->owner_name = 'Administrateur';
                    $company->owner_surname = 'Système';
                    $company->fiscal_year_start = date('Y') . '-01-01';
                    $company->fiscal_year_end = date('Y') . '-12-31';
                    $company->tva_status = 'assujetti';

                    if ($company->create()) {
                        echo '<div class="success">✅ Entreprise de test créée avec succès!</div>';
                        echo '<div class="info">Company ID: ' . $company->id . '</div>';

                        // Importer le plan comptable par défaut
                        $accountingPlan = new AccountingPlan($db);
                        if (method_exists($accountingPlan, 'importDefaultPlan')) {
                            $accountingPlan->importDefaultPlan($company->id);
                            echo '<div class="success">✅ Plan comptable par défaut importé!</div>';
                        }
                    } else {
                        echo '<div class="warning">⚠️ L\'entreprise de test n\'a pas pu être créée</div>';
                    }

                } else {
                    throw new Exception("Erreur lors de la création de l'utilisateur");
                }
            }

            // Afficher les informations de connexion
            echo '<h2>📋 Informations de Connexion</h2>';
            echo '<table>';
            echo '<tr><th>Paramètre</th><th>Valeur</th></tr>';
            echo '<tr><td><strong>URL</strong></td><td><code>http://localhost/gestion_comptable</code></td></tr>';
            echo '<tr><td><strong>Username</strong></td><td><code>' . ADMIN_USERNAME . '</code></td></tr>';
            echo '<tr><td><strong>Email</strong></td><td><code>' . ADMIN_EMAIL . '</code></td></tr>';
            echo '<tr><td><strong>Password</strong></td><td><code>' . ADMIN_PASSWORD . '</code></td></tr>';
            echo '</table>';

            echo '<div class="warning">';
            echo '<strong>⚠️ IMPORTANT - Sécurité :</strong><br>';
            echo '1. Changez le mot de passe après la première connexion<br>';
            echo '2. Supprimez ce fichier <code>create_admin.php</code> immédiatement après utilisation<br>';
            echo '3. Ne partagez jamais vos identifiants';
            echo '</div>';

            // Statistiques de la base de données
            echo '<h2>📊 Statistiques de la Base de Données</h2>';
            echo '<table>';
            echo '<tr><th>Table</th><th>Nombre d\'enregistrements</th></tr>';

            $tables = ['users', 'companies', 'transactions', 'invoices', 'accounting_plan', 'adresses'];
            foreach ($tables as $table) {
                try {
                    $query = "SELECT COUNT(*) as count FROM " . $table;
                    $stmt = $db->query($query);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    echo '<tr><td>' . $table . '</td><td>' . $result['count'] . '</td></tr>';
                } catch (Exception $e) {
                    echo '<tr><td>' . $table . '</td><td><em>Table inexistante</em></td></tr>';
                }
            }
            echo '</table>';

            echo '<a href="index.php?page=login" class="btn">🔓 Aller à la page de connexion</a>';

        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>❌ Erreur :</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';

            echo '<div class="info">';
            echo '<strong>💡 Suggestions :</strong><br>';
            echo '- Vérifiez que la base de données "comptapp" existe<br>';
            echo '- Vérifiez les identifiants dans <code>config/database.php</code><br>';
            echo '- Assurez-vous que toutes les tables sont créées (exécutez <code>install.php</code>)';
            echo '</div>';
        }
        ?>

        <hr style="margin: 30px 0;">

        <h2>🔧 Autres Commandes Utiles</h2>

        <div class="info">
            <h3>Via phpMyAdmin :</h3>
            <p>Vous pouvez également importer le fichier <code>create_admin.sql</code> via phpMyAdmin :</p>
            <ol>
                <li>Ouvrez phpMyAdmin : <a href="http://localhost/phpmyadmin" target="_blank">http://localhost/phpmyadmin</a></li>
                <li>Sélectionnez la base de données <code>comptapp</code></li>
                <li>Cliquez sur l'onglet "Importer"</li>
                <li>Choisissez le fichier <code>create_admin.sql</code></li>
                <li>Cliquez sur "Exécuter"</li>
            </ol>
        </div>

        <div class="warning">
            <h3>⚠️ Pour supprimer le compte admin :</h3>
            <code>DELETE FROM users WHERE username = 'admin';</code>
        </div>

        <p style="text-align: center; color: #999; margin-top: 30px;">
            <small>Script de création automatique - Gestion Comptable © 2025</small>
        </p>
    </div>
</body>
</html>
