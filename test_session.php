<?php
/**
 * Script de Test des Sessions
 * Vérifier que les sessions fonctionnent correctement
 */

// Démarrer la session avec le même nom que l'application
session_name('COMPTAPP_SESSION');
session_start();

// Incrémenter un compteur de visite
if (!isset($_SESSION['visit_count'])) {
    $_SESSION['visit_count'] = 1;
} else {
    $_SESSION['visit_count']++;
}

// Récupérer les informations de session
$session_id = session_id();
$session_name = session_name();
$session_save_path = session_save_path();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Session</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
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
            background-color: #4CAF50;
            color: white;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #28a745;
            margin: 20px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            border-left: 5px solid #17a2b8;
            margin: 20px 0;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            color: #d63384;
        }
        .btn {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        .btn:hover {
            background: #45a049;
        }
        .btn-danger {
            background: #f44336;
        }
        .btn-danger:hover {
            background: #da190b;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🧪 Test des Sessions</h1>

        <?php if (session_status() === PHP_SESSION_ACTIVE): ?>
            <div class="success">
                ✅ Session active et fonctionnelle!
            </div>
        <?php else: ?>
            <div class="error">
                ❌ Problème avec la session!
            </div>
        <?php endif; ?>

        <h2>📊 Informations de Session</h2>
        <table>
            <tr>
                <th>Paramètre</th>
                <th>Valeur</th>
            </tr>
            <tr>
                <td><strong>Session ID</strong></td>
                <td><code><?php echo htmlspecialchars($session_id); ?></code></td>
            </tr>
            <tr>
                <td><strong>Session Name</strong></td>
                <td><code><?php echo htmlspecialchars($session_name); ?></code></td>
            </tr>
            <tr>
                <td><strong>Save Path</strong></td>
                <td><code><?php echo htmlspecialchars($session_save_path); ?></code></td>
            </tr>
            <tr>
                <td><strong>Nombre de visites</strong></td>
                <td><strong style="color: #4CAF50; font-size: 1.2em;"><?php echo $_SESSION['visit_count']; ?></strong></td>
            </tr>
            <tr>
                <td><strong>Cookie Lifetime</strong></td>
                <td><?php echo ini_get('session.cookie_lifetime'); ?> secondes</td>
            </tr>
            <tr>
                <td><strong>Cookie Path</strong></td>
                <td><?php echo ini_get('session.cookie_path'); ?></td>
            </tr>
        </table>

        <h2>🔍 Données en Session</h2>
        <table>
            <tr>
                <th>Clé</th>
                <th>Valeur</th>
            </tr>
            <?php if (empty($_SESSION)): ?>
                <tr>
                    <td colspan="2"><em>Aucune donnée en session</em></td>
                </tr>
            <?php else: ?>
                <?php foreach ($_SESSION as $key => $value): ?>
                    <tr>
                        <td><code><?php echo htmlspecialchars($key); ?></code></td>
                        <td><?php echo htmlspecialchars(is_array($value) ? json_encode($value) : $value); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </table>

        <div class="info">
            <strong>💡 Test de Persistance :</strong><br>
            Le compteur de visites devrait augmenter à chaque fois que vous rechargez cette page.<br>
            Si le compteur reste à 1, cela signifie que les sessions ne persistent pas.
        </div>

        <h2>⚡ Actions</h2>
        <a href="test_session.php" class="btn">🔄 Recharger la page</a>
        <a href="?action=add_user" class="btn">➕ Simuler connexion</a>
        <a href="?action=destroy" class="btn btn-danger">🗑️ Détruire session</a>
        <a href="index.php" class="btn">🏠 Retour à l'application</a>

        <?php
        // Gérer les actions
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'add_user':
                    $_SESSION['user_id'] = 999;
                    $_SESSION['username'] = 'test_user';
                    $_SESSION['email'] = 'test@example.com';
                    echo '<script>window.location.href = "test_session.php";</script>';
                    break;

                case 'destroy':
                    session_destroy();
                    echo '<script>alert("Session détruite!"); window.location.href = "test_session.php";</script>';
                    break;
            }
        }
        ?>

        <hr style="margin: 30px 0;">

        <h2>🔧 Diagnostic</h2>
        <ul>
            <li><strong>Session Status:</strong> <?php echo session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive'; ?></li>
            <li><strong>Session Name:</strong> <?php echo $session_name === 'COMPTAPP_SESSION' ? '✅ Correct (COMPTAPP_SESSION)' : '⚠️ Incorrect (' . $session_name . ')'; ?></li>
            <li><strong>Session ID généré:</strong> <?php echo !empty($session_id) ? '✅ Oui' : '❌ Non'; ?></li>
            <li><strong>Données persistantes:</strong> <?php echo $_SESSION['visit_count'] > 1 ? '✅ Oui (' . $_SESSION['visit_count'] . ' visites)' : '⚠️ Première visite'; ?></li>
        </ul>

        <p style="text-align: center; color: #999; margin-top: 30px;">
            <small>Script de test - Gestion Comptable © 2025</small>
        </p>
    </div>
</body>
</html>
