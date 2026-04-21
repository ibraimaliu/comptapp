<?php
/**
 * Script de debug pour tester l'API des contacts
 */
session_name('COMPTAPP_SESSION');
session_start();

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Debug API Contacts</title>
    <style>
        body { font-family: Arial; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #667eea; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .section { margin: 20px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 10px; border-radius: 4px; margin: 10px 0; }
        pre { background: #282c34; color: #abb2bf; padding: 15px; border-radius: 4px; overflow-x: auto; }
        button { background: #667eea; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin: 5px; }
        button:hover { background: #5568d3; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #667eea; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug API Contacts</h1>

        <!-- Session Info -->
        <div class="section">
            <h2>📋 Informations Session</h2>
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="success">
                    <strong>✅ Utilisateur connecté:</strong> <?php echo htmlspecialchars($_SESSION['username']); ?> (ID: <?php echo $_SESSION['user_id']; ?>)
                </div>
                <?php if (isset($_SESSION['company_id'])): ?>
                    <div class="success">
                        <strong>✅ Société sélectionnée:</strong> ID <?php echo $_SESSION['company_id']; ?>
                    </div>
                <?php else: ?>
                    <div class="error">
                        <strong>❌ Aucune société sélectionnée</strong>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="error">
                    <strong>❌ Non connecté</strong> - <a href="index.php?page=login">Se connecter</a>
                </div>
            <?php endif; ?>
        </div>

        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['company_id'])): ?>

        <!-- Test API GET -->
        <div class="section">
            <h2>🔗 Test API GET (Liste des contacts)</h2>
            <button onclick="testListContacts()">📥 Tester GET contacts.php</button>
            <div id="resultList"></div>
        </div>

        <!-- Contacts en base de données -->
        <div class="section">
            <h2>💾 Contacts en Base de Données</h2>
            <?php
            require_once 'config/database.php';
            require_once 'models/Contact.php';

            $database = new Database();
            $db = $database->getConnection();

            if ($db) {
                echo '<div class="success">✅ Connexion à la base de données OK</div>';

                $contact = new Contact($db);
                $company_id = $_SESSION['company_id'];

                try {
                    $stmt = $contact->readByCompany($company_id);
                    $dbContacts = [];
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $dbContacts[] = $row;
                    }

                    if (count($dbContacts) > 0) {
                        echo '<div class="info">📊 <strong>' . count($dbContacts) . '</strong> contact(s) trouvé(s) pour la société ' . $company_id . '</div>';

                        echo '<table>';
                        echo '<thead><tr><th>ID</th><th>Type</th><th>Nom</th><th>Email</th><th>Téléphone</th><th>Ville</th></tr></thead>';
                        echo '<tbody>';
                        foreach ($dbContacts as $c) {
                            $name = $c['name'] ?? $c['nom'] ?? $c['title'] ?? $c['titre'] ?? 'N/A';
                            $email = $c['email'] ?? $c['mail'] ?? '';
                            $phone = $c['phone'] ?? $c['telephone'] ?? $c['tel'] ?? '';
                            $city = $c['city'] ?? $c['ville'] ?? $c['localite'] ?? '';

                            echo '<tr>';
                            echo '<td>' . $c['id'] . '</td>';
                            echo '<td>' . ($c['type'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($name) . '</td>';
                            echo '<td>' . htmlspecialchars($email) . '</td>';
                            echo '<td>' . htmlspecialchars($phone) . '</td>';
                            echo '<td>' . htmlspecialchars($city) . '</td>';
                            echo '</tr>';
                        }
                        echo '</tbody></table>';

                        echo '<h3>📄 Données brutes (premier contact):</h3>';
                        echo '<pre>' . htmlspecialchars(json_encode($dbContacts[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';

                    } else {
                        echo '<div class="info">ℹ️ Aucun contact trouvé pour la société ' . $company_id . '</div>';

                        // Vérifier s'il y a des contacts dans d'autres sociétés
                        $allStmt = $db->query("SELECT COUNT(*) as total FROM contacts");
                        $totalContacts = $allStmt->fetch(PDO::FETCH_ASSOC)['total'];
                        echo '<div class="info">💡 Total contacts dans toute la base: ' . $totalContacts . '</div>';
                    }

                } catch (Exception $e) {
                    echo '<div class="error">❌ Erreur: ' . htmlspecialchars($e->getMessage()) . '</div>';
                }

            } else {
                echo '<div class="error">❌ Erreur de connexion à la base de données</div>';
            }
            ?>
        </div>

        <script>
        async function testListContacts() {
            const resultDiv = document.getElementById('resultList');
            resultDiv.innerHTML = '<p>⏳ Chargement...</p>';

            try {
                const response = await fetch('assets/ajax/contacts.php');
                const text = await response.text();

                console.log('Réponse brute:', text);

                try {
                    const data = JSON.parse(text);

                    if (data.success) {
                        const contacts = data.contacts || [];
                        resultDiv.innerHTML = `
                            <div class="success">
                                <h3>✅ Succès!</h3>
                                <p><strong>Nombre de contacts:</strong> ${contacts.length}</p>
                                <p><strong>Total:</strong> ${data.total}</p>
                            </div>
                            <h3>📄 Réponse JSON:</h3>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        `;
                    } else {
                        resultDiv.innerHTML = `
                            <div class="error">
                                <h3>❌ Échec</h3>
                                <p><strong>Message:</strong> ${data.message}</p>
                            </div>
                            <pre>${JSON.stringify(data, null, 2)}</pre>
                        `;
                    }

                } catch (e) {
                    resultDiv.innerHTML = `
                        <div class="error">
                            <h3>❌ Erreur de parsing JSON</h3>
                            <p><strong>Erreur:</strong> ${e.message}</p>
                            <p><strong>Réponse brute (premiers 500 caractères):</strong></p>
                            <pre>${text.substring(0, 500)}</pre>
                        </div>
                    `;
                }

            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="error">
                        <h3>❌ Erreur Réseau</h3>
                        <p>${error.message}</p>
                    </div>
                `;
                console.error('Erreur:', error);
            }
        }
        </script>

        <?php endif; ?>

        <hr style="margin: 30px 0;">
        <div style="text-align: center;">
            <a href="index.php?page=adresses" style="color: #667eea;">← Retour à la page Contacts</a>
        </div>
    </div>
</body>
</html>
