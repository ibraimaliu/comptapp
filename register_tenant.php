<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - Gestion Comptable</title>
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
            max-width: 900px;
            width: 100%;
        }

        .register-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .register-header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }

        .register-header p {
            opacity: 0.9;
            font-size: 1.1em;
        }

        .register-body {
            padding: 40px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
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

        .form-group label .required {
            color: #e53e3e;
        }

        .form-control {
            width: 100%;
            padding: 14px;
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

        .plan-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .plan-option {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .plan-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .plan-option input[type="radio"]:checked + .plan-content {
            border-color: #667eea;
            background: #f7fafc;
        }

        .plan-option:hover {
            border-color: #667eea;
        }

        .plan-content {
            border: 2px solid transparent;
            border-radius: 8px;
            padding: 10px;
            transition: all 0.3s;
        }

        .plan-name {
            font-weight: 700;
            font-size: 1.2em;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .plan-price {
            color: #667eea;
            font-size: 1.5em;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .plan-features {
            list-style: none;
            font-size: 0.9em;
            color: #4a5568;
        }

        .plan-features li {
            margin-bottom: 5px;
        }

        .plan-features li::before {
            content: "✓ ";
            color: #48bb78;
            font-weight: bold;
        }

        .btn-submit {
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

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #c6f6d5;
            color: #22543d;
            border: 1px solid #9ae6b4;
        }

        .alert-error {
            background: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }

        .login-link {
            text-align: center;
            margin-top: 20px;
            color: #4a5568;
        }

        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .plan-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="register-header">
                <h1><i class="fas fa-rocket"></i> Commencez Gratuitement</h1>
                <p>Créez votre compte en quelques secondes et testez toutes les fonctionnalités pendant 30 jours</p>
            </div>

            <div class="register-body">
                <div id="message"></div>

                <form id="registerForm" method="POST">
                    <h3 style="margin-bottom: 20px; color: #2d3748;">Informations de l'entreprise</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Nom de l'entreprise <span class="required">*</span></label>
                            <input type="text" name="company_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Nom du contact <span class="required">*</span></label>
                            <input type="text" name="contact_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Email <span class="required">*</span></label>
                            <input type="email" name="contact_email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Téléphone</label>
                            <input type="tel" name="contact_phone" class="form-control">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Adresse (optionnel)</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>

                    <h3 style="margin: 30px 0 20px; color: #2d3748;">Choisissez votre plan</h3>

                    <div class="plan-selector">
                        <label class="plan-option">
                            <input type="radio" name="subscription_plan" value="free" checked>
                            <div class="plan-content">
                                <div class="plan-name">Gratuit</div>
                                <div class="plan-price">0 CHF/mois</div>
                                <ul class="plan-features">
                                    <li>30 jours d'essai</li>
                                    <li>1 utilisateur</li>
                                    <li>100 transactions/mois</li>
                                    <li>Support email</li>
                                </ul>
                            </div>
                        </label>

                        <label class="plan-option">
                            <input type="radio" name="subscription_plan" value="starter">
                            <div class="plan-content">
                                <div class="plan-name">Starter</div>
                                <div class="plan-price">29 CHF/mois</div>
                                <ul class="plan-features">
                                    <li>2 utilisateurs</li>
                                    <li>500 transactions/mois</li>
                                    <li>Facturation incluse</li>
                                    <li>Support prioritaire</li>
                                </ul>
                            </div>
                        </label>

                        <label class="plan-option">
                            <input type="radio" name="subscription_plan" value="professional">
                            <div class="plan-content">
                                <div class="plan-name">Professional</div>
                                <div class="plan-price">79 CHF/mois</div>
                                <ul class="plan-features">
                                    <li>10 utilisateurs</li>
                                    <li>2000 transactions/mois</li>
                                    <li>Import bancaire</li>
                                    <li>API Access</li>
                                </ul>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-check-circle"></i> Créer mon compte
                    </button>

                    <div class="login-link">
                        Vous avez déjà un compte? <a href="login_tenant.php">Se connecter</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('registerForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const messageDiv = document.getElementById('message');
        const submitBtn = this.querySelector('button[type="submit"]');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création en cours...';

        fetch('api/tenant_register.php', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                messageDiv.innerHTML = `
                    <div class="alert alert-success">
                        <h3><i class="fas fa-check-circle"></i> Compte créé avec succès!</h3>
                        <p>Votre base de données personnelle a été créée.</p>
                        <p><strong>Code Tenant:</strong> ${data.tenant_code}</p>
                        <p><strong>Nom d'utilisateur:</strong> ${data.username}</p>
                        <p><strong>Mot de passe temporaire:</strong> ${data.temp_password}</p>
                        <p style="margin-top: 15px;">
                            <a href="login_tenant.php" style="color: #22543d; font-weight: bold;">
                                → Se connecter maintenant
                            </a>
                        </p>
                    </div>
                `;
                this.reset();
            } else {
                messageDiv.innerHTML = `
                    <div class="alert alert-error">
                        <strong>Erreur:</strong> ${data.message}
                    </div>
                `;
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Créer mon compte';
            }
        })
        .catch(err => {
            messageDiv.innerHTML = `
                <div class="alert alert-error">
                    <strong>Erreur:</strong> Une erreur s'est produite. Veuillez réessayer.
                </div>
            `;
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-check-circle"></i> Créer mon compte';
        });
    });
    </script>
</body>
</html>
