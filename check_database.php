<?php
/**
 * Script de Vérification de la Base de Données
 * Database: gestion_comptable
 *
 * UTILISATION:
 * Accédez à: http://localhost/gestion_comptable/check_database.php
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Base de Données</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
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
        h2 {
            color: #667eea;
            margin-top: 30px;
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
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d63384;
            font-family: monospace;
        }
        .table-structure {
            font-size: 0.9em;
            margin-top: 10px;
        }
        .table-structure th {
            background-color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Vérification de la Base de Données</h1>

        <?php
        try {
            // Connexion à la base de données
            $database = new Database();
            $db = $database->getConnection();

            if (!$db) {
                throw new Exception("Impossible de se connecter à la base de données");
            }

            echo '<div class="success">✅ Connexion à la base de données <strong>gestion_comptable</strong> réussie!</div>';

            // Récupérer la liste des tables
            $query = "SHOW TABLES";
            $stmt = $db->query($query);
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo '<h2>📊 Tables dans la base de données (' . count($tables) . ' tables)</h2>';

            if (count($tables) > 0) {
                echo '<table>';
                echo '<tr><th>Nom de la Table</th><th>Nombre d\'enregistrements</th><th>Actions</th></tr>';

                foreach ($tables as $table) {
                    try {
                        $count_query = "SELECT COUNT(*) as count FROM `$table`";
                        $count_stmt = $db->query($count_query);
                        $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
                        $count = $result['count'];

                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($table) . '</strong></td>';
                        echo '<td>' . $count . '</td>';
                        echo '<td><a href="#' . $table . '">Voir structure</a></td>';
                        echo '</tr>';
                    } catch (Exception $e) {
                        echo '<tr>';
                        echo '<td><strong>' . htmlspecialchars($table) . '</strong></td>';
                        echo '<td><em>Erreur</em></td>';
                        echo '<td>-</td>';
                        echo '</tr>';
                    }
                }
                echo '</table>';

                // Afficher la structure de chaque table
                echo '<h2>🗂️ Structure des Tables</h2>';

                foreach ($tables as $table) {
                    echo '<h3 id="' . $table . '">' . htmlspecialchars($table) . '</h3>';

                    try {
                        $describe_query = "DESCRIBE `$table`";
                        $describe_stmt = $db->query($describe_query);
                        $columns = $describe_stmt->fetchAll(PDO::FETCH_ASSOC);

                        echo '<table class="table-structure">';
                        echo '<tr><th>Colonne</th><th>Type</th><th>Null</th><th>Clé</th><th>Défaut</th><th>Extra</th></tr>';

                        foreach ($columns as $column) {
                            echo '<tr>';
                            echo '<td><code>' . htmlspecialchars($column['Field']) . '</code></td>';
                            echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                            echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                            echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
                            echo '<td>' . ($column['Default'] !== null ? htmlspecialchars($column['Default']) : '<em>NULL</em>') . '</td>';
                            echo '<td>' . htmlspecialchars($column['Extra']) . '</td>';
                            echo '</tr>';
                        }
                        echo '</table>';
                    } catch (Exception $e) {
                        echo '<div class="error">Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }

            } else {
                echo '<div class="info">⚠️ Aucune table trouvée dans la base de données!</div>';
                echo '<div class="info">Vous devez créer les tables. Exécutez le script d\'installation.</div>';
            }

            // Vérifier si le compte admin existe
            echo '<h2>👤 Vérification du Compte Administrateur</h2>';

            if (in_array('users', $tables)) {
                $admin_query = "SELECT * FROM users WHERE username = 'admin'";
                $admin_stmt = $db->query($admin_query);
                $admin = $admin_stmt->fetch(PDO::FETCH_ASSOC);

                if ($admin) {
                    echo '<div class="success">✅ Le compte administrateur existe!</div>';
                    echo '<table>';
                    echo '<tr><th>Champ</th><th>Valeur</th></tr>';
                    echo '<tr><td><strong>ID</strong></td><td>' . htmlspecialchars($admin['id']) . '</td></tr>';
                    echo '<tr><td><strong>Username</strong></td><td>' . htmlspecialchars($admin['username']) . '</td></tr>';
                    echo '<tr><td><strong>Email</strong></td><td>' . htmlspecialchars($admin['email']) . '</td></tr>';
                    echo '<tr><td><strong>Créé le</strong></td><td>' . htmlspecialchars($admin['created_at']) . '</td></tr>';
                    echo '</table>';
                } else {
                    echo '<div class="info">⚠️ Le compte administrateur n\'existe pas encore.</div>';
                    echo '<div class="info">Exécutez <code>create_admin.php</code> pour le créer.</div>';
                }
            } else {
                echo '<div class="error">❌ La table <code>users</code> n\'existe pas!</div>';
            }

            // Informations de configuration
            echo '<h2>⚙️ Configuration Actuelle</h2>';
            echo '<table>';
            echo '<tr><th>Paramètre</th><th>Valeur</th></tr>';
            echo '<tr><td><strong>Host</strong></td><td><code>localhost</code></td></tr>';
            echo '<tr><td><strong>Database</strong></td><td><code>gestion_comptable</code></td></tr>';
            echo '<tr><td><strong>User</strong></td><td><code>root</code></td></tr>';
            echo '<tr><td><strong>Password</strong></td><td><code>***</code></td></tr>';
            echo '</table>';

        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<strong>❌ Erreur :</strong><br>';
            echo htmlspecialchars($e->getMessage());
            echo '</div>';

            echo '<div class="info">';
            echo '<strong>💡 Suggestions :</strong><br>';
            echo '- Vérifiez que la base de données "gestion_comptable" existe<br>';
            echo '- Vérifiez les identifiants dans <code>config/database.php</code><br>';
            echo '- Assurez-vous que XAMPP MySQL est démarré';
            echo '</div>';
        }
        ?>

        <hr style="margin: 30px 0;">

        <p style="text-align: center; color: #999;">
            <small>Script de vérification - Gestion Comptable © 2025</small>
        </p>
    </div>
</body>
</html>
