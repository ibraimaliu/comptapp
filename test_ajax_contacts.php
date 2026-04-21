<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test AJAX Contacts</title>
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
        .test-section {
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 4px;
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
        pre {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
        .btn {
            padding: 10px 20px;
            background-color: #2483ff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        .btn:hover {
            background-color: #1a6bcc;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test AJAX Contacts</h1>

        <?php
        session_name('COMPTAPP_SESSION');
        session_start();

        echo '<div class="test-section">';
        echo '<h3>1. État de la Session</h3>';

        if (isset($_SESSION['user_id'])) {
            echo '<div class="success">✅ Utilisateur connecté: ' . htmlspecialchars($_SESSION['username'] ?? 'Inconnu') . ' (ID: ' . $_SESSION['user_id'] . ')</div>';
        } else {
            echo '<div class="error">❌ Aucun utilisateur connecté</div>';
        }

        if (isset($_SESSION['company_id'])) {
            echo '<div class="success">✅ Société sélectionnée: ID ' . $_SESSION['company_id'] . '</div>';
        } else {
            echo '<div class="error">❌ Aucune société sélectionnée</div>';
            echo '<div class="info">💡 Vous devez vous connecter et sélectionner une société pour tester les endpoints AJAX</div>';
        }
        echo '</div>';

        echo '<div class="test-section">';
        echo '<h3>2. Variables de Session</h3>';
        echo '<pre>' . htmlspecialchars(print_r($_SESSION, true)) . '</pre>';
        echo '</div>';
        ?>

        <div class="test-section">
            <h3>3. Test des Endpoints</h3>
            <button class="btn" onclick="testListContacts()">Tester Liste Contacts</button>
            <button class="btn" onclick="testSaveContact()">Tester Sauvegarde</button>
            <button class="btn" onclick="testDeleteContact()">Tester Suppression (ID: 1)</button>

            <div id="test-result" style="margin-top: 20px;"></div>
        </div>

        <div style="text-align: center; margin-top: 30px;">
            <a href="index.php" style="display: inline-block; padding: 10px 20px; background-color: #2483ff; color: white; text-decoration: none; border-radius: 4px;">← Retour à l'application</a>
        </div>
    </div>

    <script>
        function showResult(title, success, data) {
            const resultDiv = document.getElementById('test-result');
            const color = success ? '#d4edda' : '#f8d7da';
            const borderColor = success ? '#28a745' : '#dc3545';
            const icon = success ? '✅' : '❌';

            resultDiv.innerHTML = `
                <div style="background: ${color}; padding: 15px; border-radius: 4px; border-left: 4px solid ${borderColor};">
                    <h4 style="margin-top: 0;">${icon} ${title}</h4>
                    <pre style="background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 4px; overflow-x: auto; white-space: pre-wrap;">${JSON.stringify(data, null, 2)}</pre>
                </div>
            `;
        }

        async function testListContacts() {
            try {
                const response = await fetch('assets/ajax/contacts.php');
                const text = await response.text();

                console.log('Raw response:', text);

                try {
                    const data = JSON.parse(text);
                    showResult('Liste des Contacts', data.success, data);
                } catch (e) {
                    showResult('Erreur de Parsing JSON', false, {
                        error: e.message,
                        raw_response: text.substring(0, 500) + (text.length > 500 ? '...' : '')
                    });
                }
            } catch (error) {
                showResult('Erreur Réseau', false, { error: error.message });
            }
        }

        async function testSaveContact() {
            try {
                const formData = new FormData();
                formData.append('name', 'Test Contact');
                formData.append('type', 'client');
                formData.append('email', 'test@example.com');
                formData.append('phone', '+41 12 345 67 89');

                const response = await fetch('assets/ajax/save_contact.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();

                console.log('Raw response:', text);

                try {
                    const data = JSON.parse(text);
                    showResult('Sauvegarde Contact', data.success, data);
                } catch (e) {
                    showResult('Erreur de Parsing JSON', false, {
                        error: e.message,
                        raw_response: text.substring(0, 500) + (text.length > 500 ? '...' : '')
                    });
                }
            } catch (error) {
                showResult('Erreur Réseau', false, { error: error.message });
            }
        }

        async function testDeleteContact() {
            if (!confirm('Voulez-vous vraiment tester la suppression du contact ID 1?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('id', '1');

                const response = await fetch('assets/ajax/delete_contact.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();

                console.log('Raw response:', text);

                try {
                    const data = JSON.parse(text);
                    showResult('Suppression Contact', data.success, data);
                } catch (e) {
                    showResult('Erreur de Parsing JSON', false, {
                        error: e.message,
                        raw_response: text.substring(0, 500) + (text.length > 500 ? '...' : '')
                    });
                }
            } catch (error) {
                showResult('Erreur Réseau', false, { error: error.message });
            }
        }
    </script>
</body>
</html>
