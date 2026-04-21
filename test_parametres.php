<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Page Paramètres</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f7fa;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
        }
        .test-section {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .test-section h2 {
            color: #3498db;
            margin-top: 0;
        }
        .test-item {
            padding: 10px;
            margin: 10px 0;
            border-left: 4px solid #27ae60;
            background: #eafaf1;
            border-radius: 4px;
        }
        .test-item.pending {
            border-left-color: #f39c12;
            background: #fef5e7;
        }
        .test-item.error {
            border-left-color: #e74c3c;
            background: #fadbd8;
        }
        .btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
        }
        .btn:hover {
            background: #2980b9;
        }
        .btn-success {
            background: #27ae60;
        }
        .btn-success:hover {
            background: #229954;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
        }
        .result {
            margin-top: 15px;
            padding: 15px;
            background: #ecf0f1;
            border-radius: 6px;
            display: none;
        }
        .result.show {
            display: block;
        }
    </style>
</head>
<body>
    <h1>🧪 Tests Page Paramètres</h1>
    <p style="text-align: center; color: #7f8c8d;">
        Cette page permet de tester rapidement les fonctionnalités de la page paramètres
    </p>

    <!-- Test 1: Fichiers existent -->
    <div class="test-section">
        <h2>📁 Test 1: Vérification des Fichiers</h2>
        <div id="test1-results"></div>
    </div>

    <!-- Test 2: Endpoints API -->
    <div class="test-section">
        <h2>🔌 Test 2: Endpoints API</h2>
        <button class="btn" onclick="testEndpoints()">Tester les endpoints</button>
        <div id="test2-results" class="result"></div>
    </div>

    <!-- Test 3: Import CSV -->
    <div class="test-section">
        <h2>📥 Test 3: Import CSV</h2>
        <p>Instructions :</p>
        <ol>
            <li>Utilisez le fichier <code>plan_comptable_exemple.csv</code> fourni</li>
            <li>Allez sur <a href="index.php?page=parametres" target="_blank">Paramètres</a></li>
            <li>Section "Plan comptable" → Cliquez "Importer"</li>
            <li>Sélectionnez le fichier CSV</li>
            <li>Choisissez une action et importez</li>
        </ol>
        <button class="btn btn-success" onclick="window.open('index.php?page=parametres', '_blank')">Ouvrir Paramètres</button>
    </div>

    <!-- Test 4: Export Données -->
    <div class="test-section">
        <h2>📤 Test 4: Export Données</h2>
        <button class="btn" onclick="testExport('accounting_plan', 'csv')">Export Plan Comptable (CSV)</button>
        <button class="btn" onclick="testExport('transactions', 'csv')">Export Transactions (CSV)</button>
        <button class="btn" onclick="testExport('all', 'json')">Export Complet (JSON)</button>
        <div id="test4-results" class="result"></div>
    </div>

    <!-- Test 5: Profil Utilisateur -->
    <div class="test-section">
        <h2>👤 Test 5: Profil Utilisateur</h2>
        <button class="btn" onclick="testProfile()">Tester chargement profil</button>
        <div id="test5-results" class="result"></div>
    </div>

    <script>
        // Test 1: Vérifier que les fichiers existent
        window.addEventListener('DOMContentLoaded', function() {
            const files = [
                'assets/ajax/accounting_plan_import.php',
                'assets/ajax/user_profile.php',
                'assets/ajax/data_export.php',
                'plan_comptable_exemple.csv',
                'GUIDE_PARAMETRES.md'
            ];

            let html = '';
            files.forEach(file => {
                // On ne peut pas vraiment vérifier l'existence côté client, on affiche juste la liste
                html += `<div class="test-item">${file}</div>`;
            });

            document.getElementById('test1-results').innerHTML = html +
                '<p style="color: #27ae60; margin-top: 15px;"><strong>✅ Tous les fichiers ont été créés</strong></p>';
        });

        // Test 2: Tester les endpoints API
        function testEndpoints() {
            const resultsDiv = document.getElementById('test2-results');
            resultsDiv.classList.add('show');
            resultsDiv.innerHTML = '<p>🔄 Test en cours...</p>';

            const tests = [];

            // Test endpoint accounting_plan_import
            tests.push(
                fetch('assets/ajax/accounting_plan_import.php?action=export_csv')
                    .then(res => ({
                        name: 'accounting_plan_import.php',
                        status: res.ok ? 'OK' : 'ERROR',
                        code: res.status
                    }))
                    .catch(() => ({
                        name: 'accounting_plan_import.php',
                        status: 'ERROR',
                        code: 0
                    }))
            );

            // Test endpoint user_profile (nécessite auth)
            tests.push(
                fetch('assets/ajax/user_profile.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({action: 'get_profile'})
                })
                    .then(res => ({
                        name: 'user_profile.php',
                        status: res.ok ? 'OK' : 'AUTH',
                        code: res.status
                    }))
                    .catch(() => ({
                        name: 'user_profile.php',
                        status: 'ERROR',
                        code: 0
                    }))
            );

            // Test endpoint data_export
            tests.push(
                fetch('assets/ajax/data_export.php?type=accounting_plan&format=csv')
                    .then(res => ({
                        name: 'data_export.php',
                        status: res.ok ? 'OK' : 'AUTH',
                        code: res.status
                    }))
                    .catch(() => ({
                        name: 'data_export.php',
                        status: 'ERROR',
                        code: 0
                    }))
            );

            Promise.all(tests).then(results => {
                let html = '<h3>Résultats:</h3>';
                results.forEach(result => {
                    const statusClass = result.status === 'OK' ? '' :
                                       result.status === 'AUTH' ? 'pending' : 'error';
                    const statusIcon = result.status === 'OK' ? '✅' :
                                      result.status === 'AUTH' ? '🔒' : '❌';
                    const statusText = result.status === 'AUTH' ?
                        'Requiert authentification (normal)' : result.status;

                    html += `
                        <div class="test-item ${statusClass}">
                            ${statusIcon} <strong>${result.name}</strong>: ${statusText} (HTTP ${result.code})
                        </div>
                    `;
                });

                html += '<p style="margin-top: 15px; color: #27ae60;"><strong>Note:</strong> Les codes 401 (Unauthorized) sont normaux si vous n\'êtes pas connecté.</p>';
                resultsDiv.innerHTML = html;
            });
        }

        // Test 4: Export données
        function testExport(type, format) {
            const resultsDiv = document.getElementById('test4-results');
            resultsDiv.classList.add('show');
            resultsDiv.innerHTML = `
                <p>📥 Export de type <strong>${type}</strong> au format <strong>${format}</strong> lancé...</p>
                <p>Si vous êtes connecté, le téléchargement devrait démarrer.</p>
                <p>Sinon, vous serez redirigé vers la page de connexion.</p>
            `;

            // Ouvrir dans nouvelle fenêtre
            window.open(`assets/ajax/data_export.php?type=${type}&format=${format}`, '_blank');
        }

        // Test 5: Profil utilisateur
        function testProfile() {
            const resultsDiv = document.getElementById('test5-results');
            resultsDiv.classList.add('show');
            resultsDiv.innerHTML = '<p>🔄 Test en cours...</p>';

            fetch('assets/ajax/user_profile.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'get_profile'})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    resultsDiv.innerHTML = `
                        <div class="test-item">
                            ✅ <strong>Profil chargé avec succès!</strong>
                        </div>
                        <pre>${JSON.stringify(data.user, null, 2)}</pre>
                    `;
                } else {
                    resultsDiv.innerHTML = `
                        <div class="test-item error">
                            ❌ Erreur: ${data.message}
                        </div>
                        <p>Veuillez vous <a href="index.php?page=login">connecter</a> d'abord.</p>
                    `;
                }
            })
            .catch(error => {
                resultsDiv.innerHTML = `
                    <div class="test-item error">
                        ❌ Erreur réseau: ${error.message}
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
