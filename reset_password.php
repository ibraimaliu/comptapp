<?php
session_name('COMPTAPP_SESSION');
session_start();

require_once 'config/database_master.php';

$token = $_GET['token'] ?? '';
$error = '';
$expired = false;

// Vérifier si le token existe et est valide
if (!empty($token)) {
    $database = new DatabaseMaster();
    $db = $database->getConnection();

    $query = "SELECT pr.*, t.company_name, t.contact_email, t.database_name, t.db_host, t.db_username, t.db_password
              FROM password_resets pr
              JOIN tenants t ON pr.tenant_id = t.id
              WHERE pr.token = :token AND pr.used = 0";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();

    $reset_request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset_request) {
        $error = 'Lien de réinitialisation invalide ou déjà utilisé.';
    } else {
        $expires_at = new DateTime($reset_request['expires_at']);
        $now = new DateTime();

        if ($now > $expires_at) {
            $expired = true;
            $error = 'Ce lien de réinitialisation a expiré. Veuillez en demander un nouveau.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialiser le mot de passe - Gestion Comptable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 500px;
            width: 100%;
        }

        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .card-header i {
            font-size: 3em;
            margin-bottom: 15px;
        }

        .card-header h1 {
            font-size: 1.8em;
            margin-bottom: 10px;
        }

        .card-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
        }

        .input-group {
            position: relative;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .form-control {
            width: 100%;
            padding: 14px 45px 14px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0aec0;
        }

        .toggle-password:hover {
            color: #667eea;
        }

        .btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #48bb78;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .password-requirements {
            background: #f7fafc;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9em;
        }

        .password-requirements ul {
            margin: 10px 0 0 20px;
            color: #4a5568;
        }

        .password-requirements li {
            margin: 5px 0;
        }

        .password-requirements li.valid {
            color: #48bb78;
        }

        .password-requirements li.invalid {
            color: #f56565;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-lock"></i>
                <h1>Nouveau mot de passe</h1>
            </div>

            <div class="card-body">
                <div id="message"></div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <strong>Erreur:</strong> <?php echo htmlspecialchars($error); ?>
                    </div>
                    <div class="back-link">
                        <?php if ($expired): ?>
                            <a href="forgot_password.php">
                                <i class="fas fa-redo"></i> Demander un nouveau lien
                            </a>
                        <?php else: ?>
                            <a href="login_tenant.php">
                                <i class="fas fa-arrow-left"></i> Retour à la connexion
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="password-requirements">
                        <strong>Exigences du mot de passe :</strong>
                        <ul id="requirements">
                            <li id="req-length" class="invalid">
                                <i class="fas fa-times"></i> Au moins 8 caractères
                            </li>
                            <li id="req-uppercase" class="invalid">
                                <i class="fas fa-times"></i> Au moins une lettre majuscule
                            </li>
                            <li id="req-lowercase" class="invalid">
                                <i class="fas fa-times"></i> Au moins une lettre minuscule
                            </li>
                            <li id="req-number" class="invalid">
                                <i class="fas fa-times"></i> Au moins un chiffre
                            </li>
                            <li id="req-match" class="invalid">
                                <i class="fas fa-times"></i> Les mots de passe correspondent
                            </li>
                        </ul>
                    </div>

                    <form id="resetPasswordForm" method="POST">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                        <div class="form-group">
                            <label>Nouveau mot de passe</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password" id="password" class="form-control" required>
                                <span class="toggle-password" onclick="togglePassword('password')">
                                    <i class="fas fa-eye" id="eye-password"></i>
                                </span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirmer le mot de passe</label>
                            <div class="input-group">
                                <i class="fas fa-lock"></i>
                                <input type="password" name="password_confirm" id="password_confirm" class="form-control" required>
                                <span class="toggle-password" onclick="togglePassword('password_confirm')">
                                    <i class="fas fa-eye" id="eye-password_confirm"></i>
                                </span>
                            </div>
                        </div>

                        <button type="submit" class="btn" id="submitBtn">
                            <i class="fas fa-check"></i> Réinitialiser le mot de passe
                        </button>
                    </form>

                    <div class="back-link">
                        <a href="login_tenant.php">
                            <i class="fas fa-arrow-left"></i> Retour à la connexion
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const eye = document.getElementById('eye-' + fieldId);

        if (field.type === 'password') {
            field.type = 'text';
            eye.classList.remove('fa-eye');
            eye.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            eye.classList.remove('fa-eye-slash');
            eye.classList.add('fa-eye');
        }
    }

    function validatePassword() {
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('password_confirm').value;

        // Validation des exigences
        const hasLength = password.length >= 8;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const passwordsMatch = password === confirm && password !== '';

        // Mise à jour visuelle
        updateRequirement('req-length', hasLength);
        updateRequirement('req-uppercase', hasUppercase);
        updateRequirement('req-lowercase', hasLowercase);
        updateRequirement('req-number', hasNumber);
        updateRequirement('req-match', passwordsMatch);

        // Activer/désactiver le bouton
        const allValid = hasLength && hasUppercase && hasLowercase && hasNumber && passwordsMatch;
        document.getElementById('submitBtn').disabled = !allValid;

        return allValid;
    }

    function updateRequirement(id, valid) {
        const element = document.getElementById(id);
        const icon = element.querySelector('i');

        if (valid) {
            element.classList.remove('invalid');
            element.classList.add('valid');
            icon.classList.remove('fa-times');
            icon.classList.add('fa-check');
        } else {
            element.classList.remove('valid');
            element.classList.add('invalid');
            icon.classList.remove('fa-check');
            icon.classList.add('fa-times');
        }
    }

    // Écouter les changements
    document.getElementById('password')?.addEventListener('input', validatePassword);
    document.getElementById('password_confirm')?.addEventListener('input', validatePassword);

    // Soumettre le formulaire
    document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
        e.preventDefault();

        if (!validatePassword()) {
            return;
        }

        const formData = new FormData(this);
        const messageDiv = document.getElementById('message');
        const submitBtn = document.getElementById('submitBtn');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Réinitialisation...';

        fetch('api/password_reset.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                messageDiv.innerHTML = `
                    <div class="alert alert-success">
                        <strong>Succès!</strong> ${data.message}
                        <br><br>
                        Redirection vers la page de connexion...
                    </div>
                `;
                setTimeout(() => {
                    window.location.href = 'login_tenant.php';
                }, 3000);
            } else {
                messageDiv.innerHTML = `
                    <div class="alert alert-error">
                        <strong>Erreur:</strong> ${data.message}
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check"></i> Réinitialiser le mot de passe';
            }
        })
        .catch(err => {
            messageDiv.innerHTML = `
                <div class="alert alert-error">
                    <strong>Erreur:</strong> Une erreur s'est produite.
                </div>
            `;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check"></i> Réinitialiser le mot de passe';
        });
    });
    </script>
</body>
</html>
