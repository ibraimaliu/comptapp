<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Migration Devis - Gestion Comptable</title>
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
        .code {
            background-color: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            margin: 10px 0;
            overflow-x: auto;
        }
        .step {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
        }
        .step-number {
            display: inline-block;
            width: 30px;
            height: 30px;
            background-color: #2483ff;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 30px;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗂️ Migration: Devis/Offres</h1>

        <div class="info">
            <strong>📋 Cette migration va créer:</strong>
            <ul>
                <li>Table <code>quotes</code> - Devis principaux</li>
                <li>Table <code>quote_items</code> - Lignes des devis</li>
                <li>Table <code>quote_status_history</code> - Historique statuts</li>
                <li>Vue <code>quote_statistics</code> - Statistiques devis</li>
            </ul>
        </div>

        <?php
        // Inclure la configuration de la base de données
        require_once 'config/database.php';

        try {
            // Créer une instance de connexion
            $database = new Database();
            $conn = $database->getConnection();

            // Activer le buffering des requêtes pour éviter l'erreur 2014
            $conn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

            echo '<div class="step">';
            echo '<span class="step-number">1</span>';
            echo '<strong>Connexion à la base de données...</strong>';
            echo '<div class="success">✅ Connexion établie avec succès</div>';
            echo '</div>';

            // Lire le fichier SQL
            $sql_file = 'migrations/add_quotes_tables.sql';

            echo '<div class="step">';
            echo '<span class="step-number">2</span>';
            echo '<strong>Lecture du fichier de migration...</strong>';

            if (!file_exists($sql_file)) {
                throw new Exception("Le fichier de migration n'existe pas: $sql_file");
            }

            $sql_content = file_get_contents($sql_file);
            echo '<div class="success">✅ Fichier de migration chargé</div>';
            echo '</div>';

            // Exécuter les commandes SQL
            echo '<div class="step">';
            echo '<span class="step-number">3</span>';
            echo '<strong>Exécution des commandes SQL...</strong>';

            // Séparer les commandes SQL
            $statements = array_filter(
                array_map('trim',
                    preg_split('/;(?=(?:[^\'"]*[\'"][^\'"]*[\'"])*[^\'"]*$)/', $sql_content)
                ),
                function($stmt) {
                    return !empty($stmt) &&
                           !preg_match('/^--/', $stmt) &&
                           strlen($stmt) > 10;
                }
            );

            $executed = 0;
            $errors = [];

            foreach ($statements as $statement) {
                // Ignorer les commentaires
                if (preg_match('/^--/', trim($statement))) {
                    continue;
                }

                try {
                    $conn->exec($statement);
                    $executed++;

                    // Afficher un message pour les CREATE TABLE
                    if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                        echo '<div class="success">✅ Table créée: ' . $matches[1] . '</div>';
                    }
                    // Afficher un message pour les INDEX
                    elseif (preg_match('/CREATE INDEX.*?`?(\w+)`?/i', $statement, $matches)) {
                        echo '<div class="success">✅ Index créé: ' . $matches[1] . '</div>';
                    }
                    // Afficher un message pour les VIEW
                    elseif (preg_match('/CREATE.*?VIEW.*?`?(\w+)`?/i', $statement, $matches)) {
                        echo '<div class="success">✅ Vue créée: ' . $matches[1] . '</div>';
                    }
                } catch (PDOException $e) {
                    // Ignorer l'erreur si la table existe déjà
                    if (strpos($e->getMessage(), 'already exists') !== false ||
                        strpos($e->getMessage(), 'Duplicate key name') !== false) {
                        if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                            echo '<div class="info">ℹ️ Table existe déjà: ' . $matches[1] . '</div>';
                        }
                    } else {
                        $errors[] = $e->getMessage();
                        echo '<div class="error">❌ Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
                    }
                }
            }

            echo '<div class="success">✅ ' . $executed . ' commande(s) SQL exécutée(s)</div>';
            echo '</div>';

            // Vérification des tables créées
            echo '<div class="step">';
            echo '<span class="step-number">4</span>';
            echo '<strong>Vérification des tables créées...</strong>';

            $tables_to_check = ['quotes', 'quote_items', 'quote_status_history'];

            foreach ($tables_to_check as $table) {
                $stmt = $conn->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    // Compter les colonnes
                    $stmt = $conn->query("DESCRIBE $table");
                    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    echo '<div class="success">✅ Table <code>' . $table . '</code> créée avec ' . count($columns) . ' colonnes</div>';
                } else {
                    echo '<div class="error">❌ Table <code>' . $table . '</code> non trouvée!</div>';
                }
            }

            // Vérifier la vue
            $stmt = $conn->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'");
            $views = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (in_array('quote_statistics', $views)) {
                echo '<div class="success">✅ Vue <code>quote_statistics</code> créée</div>';
            } else {
                echo '<div class="info">ℹ️ Vue <code>quote_statistics</code> non trouvée</div>';
            }

            echo '</div>';

            // Afficher le résumé
            echo '<div class="step">';
            echo '<h2 style="color: #28a745;">✅ Migration terminée avec succès!</h2>';
            echo '<p><strong>Prochaines étapes:</strong></p>';
            echo '<ol>';
            echo '<li>Créer le modèle <code>models/Quote.php</code></li>';
            echo '<li>Créer l\'API <code>api/quote.php</code></li>';
            echo '<li>Créer les vues dans <code>views/quotes/</code></li>';
            echo '<li>Ajouter les fonctions JavaScript</li>';
            echo '<li>Tester la création de devis</li>';
            echo '</ol>';
            echo '</div>';

            // Afficher les statistiques
            echo '<div class="step">';
            echo '<h3>📊 Structure de la base de données:</h3>';
            echo '<div class="code">';
            echo "quotes: Devis principaux\n";
            echo "├─ id, company_id, client_id\n";
            echo "├─ number, title, date, valid_until\n";
            echo "├─ status (draft/sent/accepted/rejected/expired/converted)\n";
            echo "├─ subtotal, tax_amount, discount, total\n";
            echo "└─ notes, terms, footer\n\n";

            echo "quote_items: Lignes de devis\n";
            echo "├─ id, quote_id\n";
            echo "├─ description, quantity, unit_price\n";
            echo "├─ tax_rate, discount_percent\n";
            echo "└─ line_total, sort_order\n\n";

            echo "quote_status_history: Historique\n";
            echo "└─ id, quote_id, old_status, new_status, notes\n\n";

            echo "quote_statistics: Vue statistiques\n";
            echo "└─ Compteurs par statut, taux d'acceptation";
            echo '</div>';
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h3>❌ Erreur lors de la migration</h3>';
            echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <div style="margin-top: 30px; text-align: center;">
            <a href="index.php" style="display: inline-block; padding: 10px 20px; background-color: #2483ff; color: white; text-decoration: none; border-radius: 4px;">
                ← Retour à l'application
            </a>
        </div>
    </div>
</body>
</html>
