<?php
/**
 * Script de débogage pour identifier pourquoi le bouton "Enregistrer" ne fonctionne pas
 */
session_name('COMPTAPP_SESSION');
session_start();

// Simuler une session si nécessaire
if(!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'test_user';
    $_SESSION['company_id'] = 1;
    $_SESSION['tenant_code'] = 'client_b1548e8d';
    $_SESSION['tenant_database'] = 'gestion_comptable_client_b1548e8d';
}

require_once 'config/database_master.php';
require_once 'utils/EmployeeLimits.php';

$company_id = $_SESSION['company_id'];
$tenant_code = $_SESSION['tenant_code'] ?? $_SESSION['tenant_database'];

// Vérifier les limites du plan
$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();
$limits = EmployeeLimits::getEmployeeLimits($db_master, $tenant_code, $company_id);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Bouton Enregistrer Employé</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/employees.css">
    <style>
        body { background: #f5f5f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .debug-container { max-width: 1200px; margin: 20px auto; }
        .debug-panel { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .debug-panel h2 { margin-top: 0; color: #333; border-bottom: 2px solid #667eea; padding-bottom: 10px; }
        .status-ok { color: #10b981; }
        .status-error { color: #ef4444; }
        .status-warning { color: #f59e0b; }
        .debug-console { background: #1e1e1e; color: #00ff00; padding: 15px; border-radius: 4px; font-family: 'Courier New', monospace; max-height: 400px; overflow-y: auto; font-size: 12px; }
        .debug-console div { margin: 2px 0; }
        .info-table { width: 100%; border-collapse: collapse; }
        .info-table td { padding: 8px; border-bottom: 1px solid #eee; }
        .info-table td:first-child { font-weight: bold; width: 200px; }
        .test-button { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; margin: 5px; }
        .test-button:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="debug-container">
        <div class="debug-panel">
            <h2><i class="fa-solid fa-bug"></i> Diagnostic du Problème</h2>
            <p>Ce script permet de diagnostiquer pourquoi le bouton "Enregistrer" ne fonctionne pas dans le module employés.</p>
        </div>

        <!-- Informations de session -->
        <div class="debug-panel">
            <h2><i class="fa-solid fa-user-lock"></i> Informations de Session</h2>
            <table class="info-table">
                <tr>
                    <td>User ID:</td>
                    <td class="<?php echo isset($_SESSION['user_id']) ? 'status-ok' : 'status-error'; ?>">
                        <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '❌ NON DÉFINI'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Company ID:</td>
                    <td class="<?php echo isset($_SESSION['company_id']) ? 'status-ok' : 'status-error'; ?>">
                        <?php echo isset($_SESSION['company_id']) ? $_SESSION['company_id'] : '❌ NON DÉFINI'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Tenant Code:</td>
                    <td class="<?php echo !empty($tenant_code) ? 'status-ok' : 'status-error'; ?>">
                        <?php echo !empty($tenant_code) ? $tenant_code : '❌ NON DÉFINI'; ?>
                    </td>
                </tr>
                <tr>
                    <td>Plan:</td>
                    <td class="<?php echo !$limits['feature_locked'] ? 'status-ok' : 'status-warning'; ?>">
                        <?php echo htmlspecialchars($limits['plan_name']); ?>
                        <?php if($limits['feature_locked']): ?>
                            <span class="status-error">⚠️ MODULE BLOQUÉ</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>Limites employés:</td>
                    <td>
                        <?php echo $limits['current']; ?> /
                        <?php echo $limits['max'] == -1 ? '∞' : $limits['max']; ?>
                        <?php if(!$limits['allowed'] && !$limits['feature_locked']): ?>
                            <span class="status-error">⚠️ LIMITE ATTEINTE</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Tests JavaScript -->
        <div class="debug-panel">
            <h2><i class="fa-solid fa-code"></i> Tests JavaScript</h2>
            <div style="margin-bottom: 15px;">
                <button class="test-button" onclick="runTests()">
                    <i class="fa-solid fa-play"></i> Lancer les tests
                </button>
                <button class="test-button" onclick="testSaveEmployee()">
                    <i class="fa-solid fa-save"></i> Tester saveEmployee()
                </button>
                <button class="test-button" onclick="testAPICall()">
                    <i class="fa-solid fa-cloud"></i> Tester API directement
                </button>
                <button class="test-button" onclick="clearConsole()">
                    <i class="fa-solid fa-trash"></i> Effacer console
                </button>
            </div>
            <div class="debug-console" id="debug-console">
                <div><strong>📋 Console de débogage JavaScript</strong></div>
            </div>
        </div>

        <!-- Formulaire de test -->
        <div class="debug-panel">
            <h2><i class="fa-solid fa-file-invoice"></i> Formulaire de Test</h2>
            <form id="employee-form">
                <input type="hidden" id="employee-id" name="id" value="">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label><strong>Prénom *</strong></label>
                        <input type="text" id="first-name" name="first_name" value="Jean" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label><strong>Nom *</strong></label>
                        <input type="text" id="last-name" name="last_name" value="Dupont" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label><strong>Email</strong></label>
                        <input type="email" id="email" name="email" value="jean.dupont@test.ch" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label><strong>Téléphone</strong></label>
                        <input type="tel" id="phone" name="phone" value="0791234567" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label><strong>Date d'embauche *</strong></label>
                        <input type="date" id="hire-date" name="hire_date" value="2024-01-15" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label><strong>Poste *</strong></label>
                        <input type="text" id="job-title" name="job_title" value="Développeur" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                    <div>
                        <label><strong>Salaire de base *</strong></label>
                        <input type="number" id="base-salary" name="base_salary" step="0.01" value="5000" required style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    <div>
                        <label><strong>Devise</strong></label>
                        <select id="currency" name="currency" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <option value="CHF" selected>CHF</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>
                    <div>
                        <label><strong>Heures/semaine</strong></label>
                        <input type="number" id="hours-per-week" name="hours_per_week" step="0.5" value="40" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                </div>

                <!-- Champs cachés requis -->
                <input type="text" id="employee-number" name="employee_number" value="" style="display:none;">
                <input type="text" id="address" name="address" value="" style="display:none;">
                <input type="text" id="postal-code" name="postal_code" value="" style="display:none;">
                <input type="text" id="city" name="city" value="" style="display:none;">
                <input type="text" id="country" name="country" value="Suisse" style="display:none;">
                <input type="date" id="termination-date" name="termination_date" value="" style="display:none;">
                <input type="text" id="department" name="department" value="" style="display:none;">
                <select id="employment-type" name="employment_type" style="display:none;"><option value="full_time">Temps plein</option></select>
                <select id="contract-type" name="contract_type" style="display:none;"><option value="cdi">CDI</option></select>
                <select id="salary-type" name="salary_type" style="display:none;"><option value="monthly">Mensuel</option></select>
                <input type="text" id="avs-number" name="avs_number" value="" style="display:none;">
                <input type="text" id="accident-insurance" name="accident_insurance" value="" style="display:none;">
                <input type="text" id="pension-fund" name="pension_fund" value="" style="display:none;">
                <input type="text" id="iban" name="iban" value="" style="display:none;">
                <input type="text" id="bank-name" name="bank_name" value="" style="display:none;">
                <input type="checkbox" id="family-allowances" name="family_allowances" value="1" style="display:none;">
                <input type="number" id="num-children" name="num_children" min="0" value="0" style="display:none;">
                <input type="checkbox" id="is-active" name="is_active" value="1" checked style="display:none;">
                <textarea id="notes" name="notes" style="display:none;"></textarea>
            </form>
        </div>
    </div>

    <script>
        // Override console pour afficher dans notre debug panel
        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;
        const debugConsoleDiv = document.getElementById('debug-console');

        function addDebugLine(msg, type = 'log') {
            const line = document.createElement('div');
            const timestamp = new Date().toLocaleTimeString();
            line.textContent = `[${timestamp}] ${msg}`;
            if(type === 'error') line.style.color = '#ff4444';
            if(type === 'warn') line.style.color = '#ffaa00';
            if(type === 'success') line.style.color = '#00ff00';
            debugConsoleDiv.appendChild(line);
            debugConsoleDiv.scrollTop = debugConsoleDiv.scrollHeight;
        }

        console.log = function(...args) {
            originalLog.apply(console, args);
            addDebugLine(args.join(' '), 'log');
        };

        console.error = function(...args) {
            originalError.apply(console, args);
            addDebugLine('❌ ' + args.join(' '), 'error');
        };

        console.warn = function(...args) {
            originalWarn.apply(console, args);
            addDebugLine('⚠️ ' + args.join(' '), 'warn');
        };

        function clearConsole() {
            debugConsoleDiv.innerHTML = '<div><strong>📋 Console de débogage JavaScript</strong></div>';
        }

        function runTests() {
            console.log('=== DÉBUT DES TESTS ===');

            // Test 1: Vérifier que le formulaire existe
            const form = document.getElementById('employee-form');
            if(form) {
                console.log('✅ Test 1: Formulaire trouvé (ID: employee-form)');
            } else {
                console.error('❌ Test 1: Formulaire NON trouvé!');
                return;
            }

            // Test 2: Vérifier les champs requis
            const requiredFields = ['first-name', 'last-name', 'hire-date', 'job-title', 'base-salary'];
            let allFieldsOk = true;
            requiredFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if(field) {
                    console.log(`✅ Test 2.${fieldId}: Champ trouvé, valeur = "${field.value}"`);
                } else {
                    console.error(`❌ Test 2.${fieldId}: Champ NON trouvé!`);
                    allFieldsOk = false;
                }
            });

            // Test 3: Vérifier la validité du formulaire
            if(form.checkValidity()) {
                console.log('✅ Test 3: Formulaire valide');
            } else {
                console.warn('⚠️ Test 3: Formulaire invalide (certains champs requis sont vides)');
            }

            // Test 4: Vérifier que saveEmployee() existe
            if(typeof saveEmployee === 'function') {
                console.log('✅ Test 4: Fonction saveEmployee() est définie');
            } else {
                console.error('❌ Test 4: Fonction saveEmployee() N\'EST PAS définie!');
                console.error('   → Vérifier que employees.js est bien chargé');
            }

            // Test 5: Vérifier que les autres fonctions existent
            const functions = ['loadEmployees', 'closeEmployeeModal', 'showSuccess', 'showError'];
            functions.forEach(func => {
                if(typeof window[func] === 'function') {
                    console.log(`✅ Test 5.${func}: Fonction définie`);
                } else {
                    console.warn(`⚠️ Test 5.${func}: Fonction NON définie`);
                }
            });

            console.log('=== FIN DES TESTS ===');
        }

        function testSaveEmployee() {
            console.log('🧪 TEST DE saveEmployee()');

            if(typeof saveEmployee === 'function') {
                saveEmployee();
            } else {
                console.error('❌ saveEmployee() n\'est pas définie! Impossible de tester.');
            }
        }

        function testAPICall() {
            console.log('🧪 TEST DIRECT DE L\'API');

            const employeeData = {
                action: 'create',
                employee_number: '',
                first_name: 'Jean',
                last_name: 'Dupont',
                email: 'jean.dupont@test.ch',
                phone: '0791234567',
                address: '',
                postal_code: '',
                city: '',
                country: 'Suisse',
                hire_date: '2024-01-15',
                termination_date: '',
                job_title: 'Développeur',
                department: '',
                employment_type: 'full_time',
                contract_type: 'cdi',
                salary_type: 'monthly',
                base_salary: 5000,
                currency: 'CHF',
                hours_per_week: 40,
                avs_number: '',
                accident_insurance: '',
                pension_fund: '',
                iban: '',
                bank_name: '',
                family_allowances: 0,
                num_children: 0,
                is_active: 1,
                notes: ''
            };

            console.log('📤 Envoi des données:', employeeData);

            fetch('assets/ajax/employees.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(employeeData)
            })
            .then(response => {
                console.log('📥 Réponse reçue, status:', response.status);
                return response.json();
            })
            .then(data => {
                if(data.success) {
                    console.log('✅ SUCCÈS:', data.message);
                    console.log('   Données:', data);
                } else {
                    console.error('❌ ERREUR:', data.message);
                    if(data.limits) {
                        console.log('   Limites:', data.limits);
                    }
                }
            })
            .catch(error => {
                console.error('❌ ERREUR FETCH:', error);
            });
        }

        // Lancer les tests au chargement
        window.addEventListener('DOMContentLoaded', function() {
            console.log('✅ DOM chargé');
            setTimeout(() => {
                console.log('🔍 Vérification des fonctions après 500ms...');
                runTests();
            }, 500);
        });
    </script>

    <!-- Charger employees.js -->
    <script src="assets/js/employees.js"></script>
</body>
</html>
