<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test des Chemins - Gestion Comptable</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
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
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Vérification des Chemins d'Inclusion</h1>

        <div class="test-section">
            <h3>1. Chemins Absolus</h3>
            <?php
            echo '<div class="info">';
            echo '<strong>__FILE__:</strong> ' . __FILE__ . '<br>';
            echo '<strong>__DIR__:</strong> ' . __DIR__ . '<br>';
            echo '</div>';
            ?>
        </div>

        <div class="test-section">
            <h3>2. Simulation du chemin depuis assets/ajax/contacts.php</h3>
            <?php
            // Simuler le chemin comme si on était dans assets/ajax/contacts.php
            $ajax_dir = __DIR__ . '/assets/ajax';
            $parent_of_ajax = dirname($ajax_dir);  // assets/
            $parent_of_assets = dirname($parent_of_ajax);  // racine/

            echo '<div class="info">';
            echo '<strong>Répertoire AJAX:</strong> ' . $ajax_dir . '<br>';
            echo '<strong>dirname(__DIR__) depuis AJAX:</strong> ' . $parent_of_ajax . '<br>';
            echo '<strong>dirname(dirname(__DIR__)) depuis AJAX:</strong> ' . $parent_of_assets . '<br>';
            echo '</div>';
            ?>
        </div>

        <div class="test-section">
            <h3>3. Vérification des Fichiers</h3>
            <?php
            $files_to_check = [
                'config/database.php',
                'models/Contact.php',
                'assets/ajax/contacts.php',
                'assets/ajax/save_contact.php',
                'assets/ajax/delete_contact.php'
            ];

            foreach ($files_to_check as $file) {
                $full_path = __DIR__ . '/' . $file;
                if (file_exists($full_path)) {
                    echo '<div class="success">✅ <code>' . $file . '</code> existe</div>';
                } else {
                    echo '<div class="error">❌ <code>' . $file . '</code> n\'existe pas!</div>';
                }
            }
            ?>
        </div>

        <div class="test-section">
            <h3>4. Test d'Inclusion depuis ce fichier</h3>
            <?php
            try {
                require_once __DIR__ . '/config/database.php';
                echo '<div class="success">✅ Inclusion de config/database.php réussie</div>';

                require_once __DIR__ . '/models/Contact.php';
                echo '<div class="success">✅ Inclusion de models/Contact.php réussie</div>';

                $database = new Database();
                echo '<div class="success">✅ Classe Database instanciée</div>';

                $db = $database->getConnection();
                if ($db) {
                    echo '<div class="success">✅ Connexion à la base de données établie</div>';
                } else {
                    echo '<div class="error">❌ Impossible de se connecter à la base de données</div>';
                }

            } catch (Exception $e) {
                echo '<div class="error">❌ Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
            ?>
        </div>

        <div class="test-section">
            <h3>5. Chemins Corrects pour les Fichiers AJAX</h3>
            <div class="info">
                <p><strong>Depuis <code>assets/ajax/contacts.php</code>:</strong></p>
                <ul>
                    <li>❌ INCORRECT: <code>dirname(__DIR__) . '/config/database.php'</code> → <code>assets/config/database.php</code></li>
                    <li>✅ CORRECT: <code>dirname(dirname(__DIR__)) . '/config/database.php'</code> → <code>racine/config/database.php</code></li>
                </ul>
                <p><strong>Explication:</strong></p>
                <ul>
                    <li><code>__DIR__</code> dans contacts.php = <code>/assets/ajax</code></li>
                    <li><code>dirname(__DIR__)</code> = <code>/assets</code> ❌</li>
                    <li><code>dirname(dirname(__DIR__))</code> = <code>/racine</code> ✅</li>
                </ul>
            </div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="test_ajax_contacts.php" style="display: inline-block; padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; margin: 5px;">
                Tester les Endpoints AJAX
            </a>
            <a href="index.php" style="display: inline-block; padding: 10px 20px; background-color: #2483ff; color: white; text-decoration: none; border-radius: 4px; margin: 5px;">
                ← Retour à l'application
            </a>
        </div>
    </div>
</body>
</html>
