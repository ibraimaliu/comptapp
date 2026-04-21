<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test de Connexion - Gestion Comptable</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #2483ff;
            padding-bottom: 10px;
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .info {
            background-color: #d1ecf1;
            color: #0c5460;
            padding: 12px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #17a2b8;
        }
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .test-section h3 {
            margin-top: 0;
            color: #2483ff;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .back-link {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 20px;
            background-color: #2483ff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back-link:hover {
            background-color: #1a6bcc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Test de Connexion à la Base de Données</h1>

        <?php
        // Activer l'affichage des erreurs
        ini_set('display_errors', 1);
        error_reporting(E_ALL);

        // Test 1: Vérifier que le fichier de configuration existe
        echo '<div class="test-section">';
        echo '<h3>1. Fichier de Configuration</h3>';
        $config_file = 'config/database.php';
        if (file_exists($config_file)) {
            echo '<div class="success">✅ Le fichier <code>' . $config_file . '</code> existe</div>';
            require_once $config_file;
        } else {
            echo '<div class="error">❌ Le fichier <code>' . $config_file . '</code> n\'existe pas!</div>';
            echo '</div></div></body></html>';
            exit;
        }
        echo '</div>';

        // Test 2: Vérifier l'extension PDO
        echo '<div class="test-section">';
        echo '<h3>2. Extension PDO MySQL</h3>';
        if (extension_loaded('pdo_mysql')) {
            echo '<div class="success">✅ L\'extension PDO MySQL est chargée</div>';
        } else {
            echo '<div class="error">❌ L\'extension PDO MySQL n\'est pas chargée!</div>';
            echo '<div class="info">💡 Veuillez activer l\'extension <code>pdo_mysql</code> dans votre <code>php.ini</code></div>';
        }
        echo '</div>';

        // Test 3: Tentative de connexion
        echo '<div class="test-section">';
        echo '<h3>3. Connexion à la Base de Données</h3>';

        try {
            $database = new Database();
            $conn = $database->getConnection();

            if ($conn) {
                echo '<div class="success">✅ Connexion réussie à la base de données!</div>';

                // Test 4: Vérifier les tables
                echo '</div>';
                echo '<div class="test-section">';
                echo '<h3>4. Vérification des Tables</h3>';

                $tables = ['users', 'companies', 'contacts', 'invoices', 'transactions'];
                $tables_found = 0;

                foreach ($tables as $table) {
                    $stmt = $conn->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        echo '<div class="success">✅ Table <code>' . $table . '</code> existe</div>';
                        $tables_found++;
                    } else {
                        echo '<div class="error">❌ Table <code>' . $table . '</code> n\'existe pas</div>';
                    }
                }

                if ($tables_found === count($tables)) {
                    echo '<div class="success">✅ Toutes les tables principales existent (' . $tables_found . '/' . count($tables) . ')</div>';
                } else {
                    echo '<div class="info">ℹ️ Certaines tables sont manquantes. Vous devrez peut-être exécuter le script d\'installation.</div>';
                    echo '<p><a href="install.php" class="back-link">Exécuter l\'installation</a></p>';
                }

                echo '</div>';

                // Test 5: Compter les utilisateurs
                echo '<div class="test-section">';
                echo '<h3>5. Données de Test</h3>';

                $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $user_count = $result['count'];

                if ($user_count > 0) {
                    echo '<div class="success">✅ ' . $user_count . ' utilisateur(s) trouvé(s)</div>';
                } else {
                    echo '<div class="info">ℹ️ Aucun utilisateur trouvé. Vous devrez créer un compte.</div>';
                }

                $stmt = $conn->query("SELECT COUNT(*) as count FROM companies");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $company_count = $result['count'];

                if ($company_count > 0) {
                    echo '<div class="success">✅ ' . $company_count . ' société(s) trouvée(s)</div>';
                } else {
                    echo '<div class="info">ℹ️ Aucune société trouvée.</div>';
                }

                $stmt = $conn->query("SELECT COUNT(*) as count FROM contacts");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $contact_count = $result['count'];

                echo '<div class="info">ℹ️ ' . $contact_count . ' contact(s) dans la base</div>';

                echo '</div>';

                // Résumé final
                echo '<div class="test-section" style="background: #d4edda; border: 2px solid #28a745;">';
                echo '<h3 style="color: #155724;">✅ Diagnostic: TOUT FONCTIONNE!</h3>';
                echo '<p>La connexion à la base de données fonctionne correctement.</p>';
                echo '<p>Vous pouvez maintenant utiliser l\'application.</p>';
                echo '</div>';

            } else {
                echo '<div class="error">❌ Impossible de se connecter à la base de données</div>';
                echo '<div class="info">';
                echo '<p><strong>Vérifiez:</strong></p>';
                echo '<ul>';
                echo '<li>Que XAMPP MySQL est bien démarré</li>';
                echo '<li>Que la base de données <code>gestion_comptable</code> existe</li>';
                echo '<li>Que le mot de passe dans <code>config/database.php</code> est correct (actuellement: <code>Abil</code>)</li>';
                echo '</ul>';
                echo '</div>';
            }

        } catch (Exception $e) {
            echo '<div class="error">❌ Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }

        echo '</div>';
        ?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" class="back-link">← Retour à l'application</a>
        </div>
    </div>
</body>
</html>
