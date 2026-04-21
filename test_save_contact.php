<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Enregistrement Contact</title>
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
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input, select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        button {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover {
            background: #5568d3;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 4px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        pre {
            background: #f4f4f4;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test Enregistrement Contact</h1>

        <div class="form-group">
            <label>Session Active:</label>
            <?php
            session_name('COMPTAPP_SESSION');
            session_start();

            if (isset($_SESSION['user_id'])) {
                echo '<div class="success">✅ Utilisateur connecté: ' . htmlspecialchars($_SESSION['username']) . '</div>';
                if (isset($_SESSION['company_id'])) {
                    echo '<div class="success">✅ Société sélectionnée: ID ' . $_SESSION['company_id'] . '</div>';
                } else {
                    echo '<div class="error">❌ Aucune société sélectionnée</div>';
                }
            } else {
                echo '<div class="error">❌ Non connecté - <a href="index.php?page=login">Se connecter</a></div>';
            }
            ?>
        </div>

        <?php if (isset($_SESSION['user_id']) && isset($_SESSION['company_id'])): ?>

        <h2>Formulaire de Test</h2>
        <form id="testForm">
            <div class="form-group">
                <label for="type">Type:</label>
                <select id="type" name="type" required>
                    <option value="">-- Sélectionner --</option>
                    <option value="client">Client</option>
                    <option value="fournisseur">Fournisseur</option>
                    <option value="autre">Autre</option>
                </select>
            </div>

            <div class="form-group">
                <label for="name">Nom (requis):</label>
                <input type="text" id="name" name="name" placeholder="Nom du contact" required>
            </div>

            <div class="form-group">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" placeholder="email@exemple.ch">
            </div>

            <div class="form-group">
                <label for="phone">Téléphone:</label>
                <input type="tel" id="phone" name="phone" placeholder="+41 XX XXX XX XX">
            </div>

            <input type="hidden" name="company_id" value="<?php echo $_SESSION['company_id']; ?>">

            <button type="button" onclick="testSaveContact()">💾 Tester l'Enregistrement</button>
        </form>

        <div id="result"></div>

        <script>
        async function testSaveContact() {
            const form = document.getElementById('testForm');
            const resultDiv = document.getElementById('result');

            if (!form.checkValidity()) {
                resultDiv.innerHTML = '<div class="error">❌ Veuillez remplir tous les champs requis</div>';
                form.reportValidity();
                return;
            }

            const formData = new FormData(form);

            // Afficher les données envoyées
            let dataPreview = '<h3>📤 Données envoyées:</h3><pre>';
            for (let [key, value] of formData.entries()) {
                dataPreview += key + ': ' + value + '\n';
            }
            dataPreview += '</pre>';

            resultDiv.innerHTML = dataPreview + '<p>⏳ Envoi en cours...</p>';

            try {
                const response = await fetch('assets/ajax/save_contact.php', {
                    method: 'POST',
                    body: formData
                });

                // Récupérer le texte brut d'abord
                const text = await response.text();
                console.log('Réponse brute:', text);

                // Essayer de parser en JSON
                let result;
                try {
                    result = JSON.parse(text);
                } catch (e) {
                    resultDiv.innerHTML += '<div class="error">' +
                        '<h3>❌ Erreur de parsing JSON</h3>' +
                        '<p><strong>Erreur:</strong> ' + e.message + '</p>' +
                        '<p><strong>Réponse brute (premiers 500 caractères):</strong></p>' +
                        '<pre>' + text.substring(0, 500) + '</pre>' +
                        '</div>';
                    return;
                }

                // Afficher la réponse
                if (result.success) {
                    resultDiv.innerHTML += '<div class="success">' +
                        '<h3>✅ Succès!</h3>' +
                        '<p>' + (result.message || 'Contact enregistré avec succès') + '</p>' +
                        '<p><strong>Réponse complète:</strong></p>' +
                        '<pre>' + JSON.stringify(result, null, 2) + '</pre>' +
                        '</div>';

                    // Réinitialiser le formulaire
                    form.reset();
                } else {
                    resultDiv.innerHTML += '<div class="error">' +
                        '<h3>❌ Échec</h3>' +
                        '<p><strong>Message:</strong> ' + (result.message || result.error || 'Erreur inconnue') + '</p>' +
                        '<p><strong>Réponse complète:</strong></p>' +
                        '<pre>' + JSON.stringify(result, null, 2) + '</pre>' +
                        '</div>';
                }

            } catch (error) {
                resultDiv.innerHTML += '<div class="error">' +
                    '<h3>❌ Erreur de Connexion</h3>' +
                    '<p>' + error.message + '</p>' +
                    '</div>';
                console.error('Erreur:', error);
            }
        }
        </script>

        <?php endif; ?>

        <hr style="margin: 30px 0;">

        <div style="text-align: center;">
            <a href="index.php?page=adresses" style="color: #667eea; text-decoration: none; font-weight: bold;">
                ← Retour à la page Contacts
            </a>
            <span style="margin: 0 10px;">|</span>
            <a href="test_ajax_contacts.php" style="color: #667eea; text-decoration: none; font-weight: bold;">
                Tester les Endpoints AJAX
            </a>
        </div>
    </div>
</body>
</html>
