<?php
/**
 * Vue Payroll - Gestion des fiches de paie
 * Version basique avec génération automatique des fiches
 */

// Vérifier l'authentification
if(!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    header('Location: index.php?page=login');
    exit;
}

require_once 'config/database_master.php';
require_once 'utils/EmployeeLimits.php';

$company_id = $_SESSION['company_id'];
$tenant_code = $_SESSION['tenant_code'] ?? $_SESSION['tenant_database'];

// Vérifier si le module est activé
$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();
$module_enabled = EmployeeLimits::isPayrollModuleEnabled($db_master, $tenant_code);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiches de Paie</title>
    <link rel="stylesheet" href="assets/css/employees.css">
    <style>
        .payroll-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }
        .payroll-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .payroll-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .payroll-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }
        .payroll-table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .payroll-table tr:hover {
            background: #f8f9fa;
        }
        .status-badge-draft {
            background: #ffc107;
            color: #333;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge-validated {
            background: #17a2b8;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-badge-paid {
            background: #28a745;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="employees-container">
        <div class="page-header">
            <div class="header-left">
                <h1><i class="fa-solid fa-file-invoice-dollar"></i> Fiches de Paie</h1>
                <p class="subtitle">Générez et gérez les salaires de vos employés</p>
            </div>
            <div class="header-right">
                <?php if(!$module_enabled): ?>
                    <div class="plan-info locked">
                        <i class="fa-solid fa-lock"></i>
                        <span class="plan-label">Module non disponible</span>
                        <button class="btn btn-upgrade" onclick="window.location.href='index.php?page=mon_compte'">
                            <i class="fa-solid fa-arrow-up"></i> Mettre à niveau
                        </button>
                    </div>
                <?php else: ?>
                    <select id="filter-year" class="btn" style="background:white;color:#333;border:2px solid #e0e0e0;" onchange="loadPayrolls()">
                        <?php
                        $current_year = date('Y');
                        for($y = $current_year; $y >= $current_year - 3; $y--) {
                            echo "<option value='$y'>$y</option>";
                        }
                        ?>
                    </select>
                    <select id="filter-month" class="btn" style="background:white;color:#333;border:2px solid #e0e0e0;margin-left:10px;" onchange="loadPayrolls()">
                        <option value="">Tous les mois</option>
                        <option value="1">Janvier</option>
                        <option value="2">Février</option>
                        <option value="3">Mars</option>
                        <option value="4">Avril</option>
                        <option value="5">Mai</option>
                        <option value="6">Juin</option>
                        <option value="7">Juillet</option>
                        <option value="8">Août</option>
                        <option value="9">Septembre</option>
                        <option value="10">Octobre</option>
                        <option value="11">Novembre</option>
                        <option value="12">Décembre</option>
                    </select>
                    <button class="btn btn-primary" onclick="showGenerateModal()">
                        <i class="fa-solid fa-magic"></i> Générer Fiches de Paie
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if(!$module_enabled): ?>
            <!-- Message module bloqué -->
            <div class="locked-message">
                <div class="locked-icon">
                    <i class="fa-solid fa-lock"></i>
                </div>
                <h2>Module de gestion des salaires non disponible</h2>
                <p>Le module de gestion des salaires n'est disponible qu'à partir du plan Starter. Passez à un plan supérieur pour accéder à cette fonctionnalité.</p>
                <button class="btn btn-upgrade-large" onclick="window.location.href='index.php?page=mon_compte'">
                    <i class="fa-solid fa-rocket"></i> Découvrir les plans
                </button>
            </div>
        <?php else: ?>
            <!-- Statistiques -->
            <div class="payroll-stats" id="payroll-stats">
                <div class="stat-card">
                    <h3>Fiches ce mois</h3>
                    <div class="stat-value" id="stat-count">-</div>
                </div>
                <div class="stat-card">
                    <h3>Total Brut</h3>
                    <div class="stat-value" id="stat-gross">-</div>
                </div>
                <div class="stat-card">
                    <h3>Total Net</h3>
                    <div class="stat-value" id="stat-net">-</div>
                </div>
                <div class="stat-card">
                    <h3>Charges Patronales</h3>
                    <div class="stat-value" id="stat-charges">-</div>
                </div>
            </div>

            <!-- Liste des fiches de paie -->
            <div class="payroll-table">
                <table>
                    <thead>
                        <tr>
                            <th>Employé</th>
                            <th>Période</th>
                            <th>Salaire Brut</th>
                            <th>Déductions</th>
                            <th>Salaire Net</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="payroll-list">
                        <tr>
                            <td colspan="7" style="text-align:center;padding:40px;">
                                <i class="fa-solid fa-spinner fa-spin"></i> Chargement...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal Génération -->
    <div id="generate-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Générer les Fiches de Paie</h2>
                <button class="close-btn" onclick="closeGenerateModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Mois</label>
                    <select id="gen-month" class="form-control">
                        <?php for($m=1; $m<=12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo ($m == date('n')) ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0,0,0,$m,1)); ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Année</label>
                    <select id="gen-year" class="form-control">
                        <?php for($y=date('Y'); $y>=date('Y')-2; $y--): ?>
                        <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date de paiement</label>
                    <input type="date" id="gen-payment-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <p class="info">Les fiches seront générées automatiquement pour tous les employés actifs avec leurs salaires de base.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeGenerateModal()">Annuler</button>
                <button class="btn btn-primary" onclick="generatePayrolls()">
                    <i class="fa-solid fa-magic"></i> Générer
                </button>
            </div>
        </div>
    </div>

    <script>
        // Charger les fiches de paie
        function loadPayrolls() {
            const year = document.getElementById('filter-year')?.value || '';
            const month = document.getElementById('filter-month')?.value || '';

            let url = 'assets/ajax/payroll.php?action=list';
            if(year) url += '&period_year=' + year;
            if(month) url += '&period_month=' + month;

            fetch(url)
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        displayPayrolls(data.data);
                        loadStatistics();
                    }
                })
                .catch(err => console.error('Erreur:', err));
        }

        function displayPayrolls(payrolls) {
            const tbody = document.getElementById('payroll-list');
            if(!payrolls || payrolls.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;padding:40px;">Aucune fiche de paie trouvée</td></tr>';
                return;
            }

            let html = '';
            payrolls.forEach(p => {
                const statusClass = 'status-badge-' + p.status;
                const statusText = {
                    'draft': 'Brouillon',
                    'validated': 'Validée',
                    'paid': 'Payée'
                }[p.status] || p.status;

                const monthNames = ['Jan','Fév','Mar','Avr','Mai','Juin','Juil','Août','Sept','Oct','Nov','Déc'];

                html += `
                    <tr>
                        <td><strong>${escapeHtml(p.first_name)} ${escapeHtml(p.last_name)}</strong></td>
                        <td>${monthNames[p.period_month-1]} ${p.period_year}</td>
                        <td>${formatAmount(p.gross_salary)} CHF</td>
                        <td>${formatAmount(p.total_deductions)} CHF</td>
                        <td><strong>${formatAmount(p.net_salary)} CHF</strong></td>
                        <td><span class="${statusClass}">${statusText}</span></td>
                        <td>
                            ${p.status === 'draft' ? `
                                <button class="btn btn-sm btn-primary" onclick="validatePayroll(${p.id})">Valider</button>
                                <button class="btn btn-sm btn-warning" onclick="deletePayroll(${p.id})">Supprimer</button>
                            ` : ''}
                            ${p.status === 'validated' ? `
                                <button class="btn btn-sm btn-primary" onclick="markPaid(${p.id})">Marquer payé</button>
                            ` : ''}
                        </td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        function loadStatistics() {
            const year = document.getElementById('filter-year')?.value || new Date().getFullYear();

            fetch(`assets/ajax/payroll.php?action=statistics&year=${year}`)
                .then(res => res.json())
                .then(data => {
                    if(data.success && data.data) {
                        document.getElementById('stat-count').textContent = data.data.total_payrolls || 0;
                        document.getElementById('stat-gross').textContent = formatAmount(data.data.total_gross || 0) + ' CHF';
                        document.getElementById('stat-net').textContent = formatAmount(data.data.total_net || 0) + ' CHF';
                        document.getElementById('stat-charges').textContent = formatAmount(data.data.total_charges || 0) + ' CHF';
                    }
                })
                .catch(err => console.error('Erreur:', err));
        }

        // Modal génération
        function showGenerateModal() {
            document.getElementById('generate-modal').style.display = 'flex';
        }

        function closeGenerateModal() {
            document.getElementById('generate-modal').style.display = 'none';
        }

        // Générer les fiches
        function generatePayrolls() {
            const month = document.getElementById('gen-month').value;
            const year = document.getElementById('gen-year').value;
            const paymentDate = document.getElementById('gen-payment-date').value;

            // Récupérer tous les employés actifs
            fetch('assets/ajax/employees.php?action=list&active_only=true')
                .then(res => res.json())
                .then(data => {
                    if(!data.success || !data.data || data.data.length === 0) {
                        alert('Aucun employé actif trouvé');
                        return;
                    }

                    let generated = 0;
                    let errors = 0;

                    const promises = data.data.map(emp => {
                        return fetch('assets/ajax/payroll.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({
                                action: 'generate',
                                employee_id: emp.id,
                                period_month: month,
                                period_year: year,
                                payment_date: paymentDate,
                                base_salary: emp.base_salary
                            })
                        })
                        .then(r => r.json())
                        .then(result => {
                            if(result.success) generated++;
                            else errors++;
                        });
                    });

                    Promise.all(promises).then(() => {
                        alert(`Génération terminée: ${generated} fiches créées, ${errors} erreurs`);
                        closeGenerateModal();
                        loadPayrolls();
                    });
                });
        }

        // Valider une fiche
        function validatePayroll(id) {
            if(!confirm('Voulez-vous valider cette fiche de paie?')) return;

            fetch('assets/ajax/payroll.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'validate', id: id})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert('Fiche validée avec succès');
                    loadPayrolls();
                } else {
                    alert('Erreur: ' + data.message);
                }
            });
        }

        // Marquer comme payé
        function markPaid(id) {
            if(!confirm('Marquer cette fiche comme payée?')) return;

            fetch('assets/ajax/payroll.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'mark_paid', id: id})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert('Fiche marquée comme payée');
                    loadPayrolls();
                } else {
                    alert('Erreur: ' + data.message);
                }
            });
        }

        // Supprimer
        function deletePayroll(id) {
            if(!confirm('Supprimer cette fiche de paie?')) return;

            fetch('assets/ajax/payroll.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({action: 'delete', id: id})
            })
            .then(res => res.json())
            .then(data => {
                if(data.success) {
                    alert('Fiche supprimée');
                    loadPayrolls();
                } else {
                    alert('Erreur: ' + data.message);
                }
            });
        }

        // Utilitaires
        function formatAmount(amount) {
            return new Intl.NumberFormat('fr-CH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Charger au démarrage
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($module_enabled): ?>
            loadPayrolls();
            loadStatistics();
            <?php endif; ?>
        });
    </script>
</body>
</html>
