<?php
/**
 * Page: Nouvelle Transaction (Comptabilité Double)
 * Description: Formulaire de création avec débit/crédit
 */

// Vérifier si une société est sélectionnée
$company_id = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;

if(!$company_id) {
    echo '<div style="padding: 20px; background: #fff3cd; color: #856404; border-radius: 8px; margin: 20px;">
        <h3>⚠️ Aucune société sélectionnée</h3>
        <p>Veuillez sélectionner une société pour créer une transaction.</p>
        <p><a href="index.php?page=home" style="color: #856404; font-weight: bold;">← Retour à l\'accueil</a></p>
    </div>';
    exit;
}

// Inclure les modèles
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/AccountingPlan.php';
include_once dirname(__DIR__) . '/models/Category.php';

$database = new Database();
$db = $database->getConnection();

// Récupérer uniquement les comptes sélectionnables (pas les sections/groupes)
$accountingPlan = new AccountingPlan($db);
$accounts_stmt = $accountingPlan->readSelectableByCompany($company_id);
$accounts = [];
while($row = $accounts_stmt->fetch(PDO::FETCH_ASSOC)) {
    $accounts[] = $row;
}

// Récupérer les catégories
$category_model = new Category($db);
$categories_stmt = $category_model->readByCompany($company_id);
$categories = [];
while($row = $categories_stmt->fetch(PDO::FETCH_ASSOC)) {
    $categories[] = $row;
}

// Debug: afficher le nombre de comptes
$debug_info = "Company ID: $company_id | Comptes trouvés: " . count($accounts);

$page_title = "Nouvelle Transaction";
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Gestion Comptable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .form-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .form-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }

        .form-header h1 {
            margin: 0 0 10px 0;
            font-size: 2em;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .form-header p {
            margin: 0;
            opacity: 0.95;
        }

        .form-header .debug-info {
            margin-top: 10px;
            padding: 8px 12px;
            background: rgba(255,255,255,0.2);
            border-radius: 6px;
            font-size: 0.85em;
        }

        .form-body {
            background: white;
            padding: 40px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2d3748;
            font-size: 0.95em;
        }

        .form-group label .required {
            color: #e53e3e;
            margin-left: 4px;
        }

        .form-group .label-help {
            font-weight: 400;
            color: #718096;
            font-size: 0.85em;
            margin-left: 5px;
        }

        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s;
            font-family: inherit;
            background: #f7fafc;
        }

        .form-control:hover {
            border-color: #cbd5e0;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-control.error {
            border-color: #e53e3e;
            background: #fff5f5;
        }

        .error-message {
            display: none;
            color: #e53e3e;
            font-size: 0.85em;
            margin-top: 6px;
        }

        .error-message.show {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 30px;
            border-top: 2px solid #f7fafc;
        }

        .btn {
            padding: 14px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-size: 1em;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: start;
            gap: 12px;
        }

        .alert-info {
            background: #e6fffa;
            color: #234e52;
            border-left: 4px solid #38b2ac;
        }

        .alert-warning {
            background: #fffaf0;
            color: #744210;
            border-left: 4px solid #ed8936;
        }

        .alert-danger {
            background: #fff5f5;
            color: #742a2a;
            border-left: 4px solid #e53e3e;
            display: none;
        }

        .alert-danger.show {
            display: flex;
        }

        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border-left: 4px solid #38a169;
            display: none;
        }

        .alert-success.show {
            display: flex;
        }

        .double-entry-section {
            background: #f7fafc;
            padding: 25px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
            margin: 25px 0;
        }

        .double-entry-section h3 {
            margin: 0 0 20px 0;
            color: #2d3748;
            font-size: 1.2em;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .form-header h1 {
                font-size: 1.5em;
            }

            .form-body {
                padding: 25px 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

<div class="form-container">
    <div class="form-header">
        <h1>
            <i class="fas fa-plus-circle"></i>
            Nouvelle Transaction (Comptabilité Double)
        </h1>
        <p>Enregistrez une transaction avec débit et crédit</p>
        <div class="debug-info">
            <i class="fas fa-info-circle"></i>
            <?php echo $debug_info; ?>
        </div>
    </div>

    <div class="form-body">
        <div class="alert alert-danger" id="globalError">
            <i class="fas fa-exclamation-circle"></i>
            <div id="globalErrorMessage"></div>
        </div>

        <div class="alert alert-success" id="successMessage">
            <i class="fas fa-check-circle"></i>
            <div id="successMessageText"></div>
        </div>

        <?php if(count($accounts) == 0): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Aucun compte comptable trouvé !</strong><br>
                    Vous devez d'abord créer votre plan comptable dans Paramètres → Plan Comptable.
                    <br><br>
                    <a href="index.php?page=parametres#accounting-plan" style="color: #744210; font-weight: bold;">
                        → Aller au Plan Comptable
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            <div>
                <strong>Comptabilité en Partie Double:</strong> Chaque transaction nécessite un compte au débit ET un compte au crédit.
                Le montant doit être <strong>strictement &gt; 0</strong>.
            </div>
        </div>

        <form id="transactionForm">
            <!-- Date et Montant -->
            <div class="form-row">
                <div class="form-group">
                    <label for="date">
                        Date <span class="required">*</span>
                    </label>
                    <input type="date"
                           class="form-control"
                           id="date"
                           name="date"
                           max="<?php echo date('Y-m-d'); ?>"
                           required>
                    <div class="error-message" id="error-date">
                        <i class="fas fa-exclamation-circle"></i>
                        La date est obligatoire
                    </div>
                </div>

                <div class="form-group">
                    <label for="amount">
                        Montant (CHF) <span class="required">*</span>
                    </label>
                    <input type="number"
                           class="form-control"
                           id="amount"
                           name="amount"
                           step="0.01"
                           min="0.01"
                           placeholder="0.00"
                           required>
                    <div class="error-message" id="error-amount">
                        <i class="fas fa-exclamation-circle"></i>
                        Le montant doit être supérieur à 0
                    </div>
                </div>
            </div>

            <!-- Description -->
            <div class="form-group">
                <label for="description">
                    Description / Libellé <span class="required">*</span>
                </label>
                <textarea class="form-control"
                          id="description"
                          name="description"
                          rows="3"
                          placeholder="Description détaillée de la transaction..."
                          required></textarea>
                <div class="error-message" id="error-description">
                    <i class="fas fa-exclamation-circle"></i>
                    La description est obligatoire
                </div>
            </div>

            <!-- Section Comptabilité Double -->
            <div class="double-entry-section">
                <h3>
                    <i class="fas fa-balance-scale"></i>
                    Écritures Comptables
                </h3>

                <div class="form-row">
                    <!-- Compte au Débit -->
                    <div class="form-group">
                        <label for="account_debit">
                            Compte au Débit <span class="required">*</span>
                            <span class="label-help">(Actif/Charge)</span>
                        </label>
                        <select class="form-control" id="account_debit" name="account_debit" required>
                            <option value="">-- Sélectionnez un compte --</option>
                            <?php foreach($accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo htmlspecialchars($account['number'] . ' - ' . $account['name']); ?>
                                    (<?php echo ucfirst($account['category']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-message" id="error-account-debit">
                            <i class="fas fa-exclamation-circle"></i>
                            Veuillez sélectionner un compte au débit
                        </div>
                    </div>

                    <!-- Compte au Crédit -->
                    <div class="form-group">
                        <label for="account_credit">
                            Compte au Crédit <span class="required">*</span>
                            <span class="label-help">(Passif/Produit)</span>
                        </label>
                        <select class="form-control" id="account_credit" name="account_credit" required>
                            <option value="">-- Sélectionnez un compte --</option>
                            <?php foreach($accounts as $account): ?>
                                <option value="<?php echo $account['id']; ?>">
                                    <?php echo htmlspecialchars($account['number'] . ' - ' . $account['name']); ?>
                                    (<?php echo ucfirst($account['category']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="error-message" id="error-account-credit">
                            <i class="fas fa-exclamation-circle"></i>
                            Veuillez sélectionner un compte au crédit
                        </div>
                    </div>
                </div>
            </div>

            <!-- Type et Catégorie -->
            <div class="form-row">
                <div class="form-group">
                    <label for="type">
                        Type de transaction <span class="required">*</span>
                    </label>
                    <select class="form-control" id="type" name="type" required>
                        <option value="">-- Sélectionnez --</option>
                        <option value="income">Revenu (Produit)</option>
                        <option value="expense">Dépense (Charge)</option>
                    </select>
                    <div class="error-message" id="error-type">
                        <i class="fas fa-exclamation-circle"></i>
                        Veuillez sélectionner un type
                    </div>
                </div>

                <div class="form-group">
                    <label for="category">
                        Catégorie
                        <span class="label-help">(Optionnel)</span>
                    </label>
                    <select class="form-control" id="category" name="category">
                        <option value="">-- Sans catégorie --</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['name']); ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- TVA -->
            <div class="form-group">
                <label for="tva_rate">
                    Taux TVA (%)
                    <span class="label-help">(Défaut: 7.7% Suisse)</span>
                </label>
                <select class="form-control" id="tva_rate" name="tva_rate">
                    <option value="0.00">0% - Exonéré</option>
                    <option value="2.50">2.5% - Taux réduit</option>
                    <option value="7.70" selected>7.7% - Taux normal</option>
                    <option value="8.10">8.1% - Taux spécial hébergement</option>
                </select>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <i class="fas fa-save"></i>
                    Enregistrer la transaction
                </button>
                <a href="index.php?page=comptabilite" class="btn btn-secondary">
                    <i class="fas fa-times"></i>
                    Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<script>
console.log('Script chargé - Début');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Ready');

    const form = document.getElementById('transactionForm');
    const submitBtn = document.getElementById('submitBtn');
    const globalError = document.getElementById('globalError');
    const globalErrorMessage = document.getElementById('globalErrorMessage');
    const successMessage = document.getElementById('successMessage');
    const successMessageText = document.getElementById('successMessageText');

    // Set default date to today
    document.getElementById('date').valueAsDate = new Date();

    console.log('Elements trouvés:', {
        form: !!form,
        submitBtn: !!submitBtn,
        globalError: !!globalError
    });

    // Validation fields
    const fields = {
        date: {
            element: document.getElementById('date'),
            error: document.getElementById('error-date'),
            validate: (value) => value !== ''
        },
        amount: {
            element: document.getElementById('amount'),
            error: document.getElementById('error-amount'),
            validate: (value) => {
                const num = parseFloat(value);
                return !isNaN(num) && num > 0;
            }
        },
        description: {
            element: document.getElementById('description'),
            error: document.getElementById('error-description'),
            validate: (value) => value.trim() !== ''
        },
        account_debit: {
            element: document.getElementById('account_debit'),
            error: document.getElementById('error-account-debit'),
            validate: (value) => value !== '' && value !== '0'
        },
        account_credit: {
            element: document.getElementById('account_credit'),
            error: document.getElementById('error-account-credit'),
            validate: (value) => value !== '' && value !== '0'
        },
        type: {
            element: document.getElementById('type'),
            error: document.getElementById('error-type'),
            validate: (value) => value !== ''
        }
    };

    // Validation function
    function validateField(fieldName) {
        const field = fields[fieldName];
        if (!field) return true;

        const value = field.element.value;
        const isValid = field.validate(value);

        if (isValid) {
            field.element.classList.remove('error');
            field.error.classList.remove('show');
        } else {
            field.element.classList.add('error');
            field.error.classList.add('show');
        }

        return isValid;
    }

    // Add blur validation
    Object.keys(fields).forEach(fieldName => {
        const field = fields[fieldName];
        if (field.element) {
            field.element.addEventListener('blur', () => validateField(fieldName));
            field.element.addEventListener('change', () => validateField(fieldName));
        }
    });

    // Form submission
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        console.log('Form submitted');

        // Hide previous messages
        globalError.classList.remove('show');
        successMessage.classList.remove('show');

        // Validate all fields
        let isValid = true;
        for (let fieldName in fields) {
            if (!validateField(fieldName)) {
                isValid = false;
            }
        }

        if (!isValid) {
            globalErrorMessage.innerHTML = '<strong>Erreur:</strong> Veuillez corriger les erreurs dans le formulaire.';
            globalError.classList.add('show');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            console.log('Validation échouée');
            return;
        }

        // Extra validation
        const amount = parseFloat(document.getElementById('amount').value);
        if (amount <= 0) {
            globalErrorMessage.innerHTML = '<strong>Erreur:</strong> Le montant doit être strictement supérieur à 0.';
            globalError.classList.add('show');
            fields.amount.element.classList.add('error');
            fields.amount.error.classList.add('show');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        // Check same account
        const debit = document.getElementById('account_debit').value;
        const credit = document.getElementById('account_credit').value;
        if (debit === credit) {
            globalErrorMessage.innerHTML = '<strong>Erreur:</strong> Les comptes au débit et au crédit doivent être différents.';
            globalError.classList.add('show');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        // Disable button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';

        // Prepare data
        const data = {
            action: 'create',
            date: document.getElementById('date').value,
            amount: amount,
            description: document.getElementById('description').value,
            account_id: debit,
            counterpart_account_id: credit,
            type: document.getElementById('type').value,
            category: document.getElementById('category').value || '',
            tva_rate: document.getElementById('tva_rate').value
        };

        console.log('Envoi des données:', data);

        // Send request
        fetch('api/transaction.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        })
        .then(response => {
            console.log('Réponse reçue:', response.status);
            return response.json();
        })
        .then(result => {
            console.log('Résultat:', result);

            if (result.success) {
                successMessageText.innerHTML = '<strong>Succès!</strong> La transaction a été enregistrée avec succès.';
                successMessage.classList.add('show');

                // Redirect after 2 seconds
                setTimeout(() => {
                    window.location.href = 'index.php?page=comptabilite';
                }, 2000);
            } else {
                globalErrorMessage.innerHTML = '<strong>Erreur:</strong> ' + (result.message || 'Une erreur est survenue.');
                globalError.classList.add('show');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer la transaction';
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            globalErrorMessage.innerHTML = '<strong>Erreur réseau:</strong> ' + error.message;
            globalError.classList.add('show');
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Enregistrer la transaction';
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    console.log('Script configuré - Fin');
});
</script>

</body>
</html>
