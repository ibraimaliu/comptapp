<?php
/**
 * Fichier de test pour l'API auth.php (localhost uniquement)
 */
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Accès refusé');
}

// Simuler une requête POST
$_SERVER['REQUEST_METHOD'] = 'POST';

// Données de test
$testData = json_encode([
    'action' => 'login',
    'username' => 'admin',
    'password' => 'Admin@2025'
]);

// Créer un fichier temporaire pour simuler php://input
$temp = tmpfile();
fwrite($temp, $testData);
fseek($temp, 0);

// Rediriger php://input
stream_wrapper_unregister("php");
stream_wrapper_register("php", "VariableStream");

class VariableStream {
    protected $position;
    protected $varname;

    function stream_open($path, $mode, $options, &$opened_path) {
        $url = parse_url($path);
        $this->varname = $url["host"];
        $this->position = 0;
        return true;
    }

    function stream_read($count) {
        global $testData;
        $ret = substr($testData, $this->position, $count);
        $this->position += strlen($ret);
        return $ret;
    }

    function stream_eof() {
        global $testData;
        return $this->position >= strlen($testData);
    }

    function stream_stat() {
        return [];
    }
}

// Capturer la sortie
ob_start();

// Inclure le fichier auth.php
require_once 'auth.php';

// Récupérer la sortie
$output = ob_get_clean();

// Afficher le résultat
header("Content-Type: text/html; charset=UTF-8");
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test API Auth</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #f5f5f5;
        }
        .box {
            background: white;
            padding: 20px;
            margin: 10px 0;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        pre {
            background: #f8f8f8;
            padding: 15px;
            border-left: 3px solid #007bff;
            overflow-x: auto;
        }
        .success { border-left-color: #28a745; }
        .error { border-left-color: #dc3545; }
        h2 {
            margin-top: 0;
            color: #333;
        }
    </style>
</head>
<body>
    <h1>🧪 Test de l'API auth.php</h1>

    <div class="box">
        <h2>📤 Requête envoyée</h2>
        <pre><?php echo htmlspecialchars($testData); ?></pre>
    </div>

    <div class="box">
        <h2>📥 Réponse reçue</h2>
        <pre class="<?php echo (strpos($output, '"success":true') !== false) ? 'success' : 'error'; ?>">
<?php
echo htmlspecialchars($output);

// Essayer de décoder le JSON
echo "\n\n=== Analyse JSON ===\n";
$decoded = json_decode($output);
if ($decoded === null) {
    echo "❌ ERREUR: La réponse n'est pas du JSON valide!\n";
    echo "Erreur JSON: " . json_last_error_msg() . "\n";
    echo "\nCaractères suspects au début:\n";
    echo bin2hex(substr($output, 0, 50)) . "\n";
} else {
    echo "✅ JSON valide!\n";
    echo "Contenu décodé:\n";
    print_r($decoded);
}
?>
        </pre>
    </div>

    <div class="box">
        <h2>📊 Informations</h2>
        <ul>
            <li>Longueur de la réponse: <?php echo strlen($output); ?> octets</li>
            <li>Premiers caractères: <code><?php echo htmlspecialchars(substr($output, 0, 50)); ?></code></li>
            <li>Derniers caractères: <code><?php echo htmlspecialchars(substr($output, -50)); ?></code></li>
        </ul>
    </div>

    <div class="box">
        <h2>💡 Actions</h2>
        <p>
            <a href="test_auth.php" style="padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px;">🔄 Rafraîchir</a>
            <a href="../index.php?page=login" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin-left: 10px;">🔓 Page de login</a>
        </p>
    </div>
</body>
</html>
