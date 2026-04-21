<?php
/**
 * Détails et Gestion d'un Tenant
 */

session_name('ADMIN_SESSION');
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$tenant_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($tenant_id === 0) {
    header('Location: tenants.php');
    exit;
}

require_once '../config/database_master.php';

$database = new DatabaseMaster();
$db = $database->getConnection();

// Récupérer les informations du tenant
$query = "
    SELECT t.*, sp.plan_name, sp.price_monthly
    FROM tenants t
    LEFT JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
    WHERE t.id = :id
";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $tenant_id);
$stmt->execute();
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
    header('Location: tenants.php');
    exit;
}

// Récupérer l'historique d'utilisation (si la table existe)
$usage_history = [];
try {
    $usage_query = "SELECT * FROM tenant_usage WHERE tenant_id = :id ORDER BY created_at DESC LIMIT 6";
    $usage_stmt = $db->prepare($usage_query);
    $usage_stmt->bindParam(':id', $tenant_id);
    $usage_stmt->execute();
    $usage_history = $usage_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table n'existe pas encore, ignorer l'erreur
    $usage_history = [];
}

// Récupérer les logs d'audit récents (si la table existe)
$audit_logs = [];
try {
    $audit_query = "SELECT * FROM audit_logs WHERE tenant_id = :id ORDER BY created_at DESC LIMIT 20";
    $audit_stmt = $db->prepare($audit_query);
    $audit_stmt->bindParam(':id', $tenant_id);
    $audit_stmt->execute();
    $audit_logs = $audit_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table n'existe pas encore, ignorer l'erreur
    $audit_logs = [];
}

// Récupérer tous les plans disponibles
$plans_query = "SELECT * FROM subscription_plans ORDER BY price_monthly ASC";
$plans = $db->query($plans_query)->fetchAll(PDO::FETCH_ASSOC);

// Tentative de connexion à la base du tenant pour statistiques réelles
$real_stats = null;
try {
    $tenant_conn = new PDO(
        "mysql:host={$tenant['db_host']};dbname={$tenant['database_name']}",
        $tenant['db_username'],
        $tenant['db_password']
    );
    $tenant_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $real_stats = [
        'users' => $tenant_conn->query("SELECT COUNT(*) as count FROM users")->fetch()['count'],
        'transactions_total' => $tenant_conn->query("SELECT COUNT(*) as count FROM transactions")->fetch()['count'],
        'transactions_this_month' => $tenant_conn->query(
            "SELECT COUNT(*) as count FROM transactions
             WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())"
        )->fetch()['count'],
        'invoices' => $tenant_conn->query("SELECT COUNT(*) as count FROM invoices")->fetch()['count'],
        'contacts' => $tenant_conn->query("SELECT COUNT(*) as count FROM contacts")->fetch()['count'],
        'accounts' => $tenant_conn->query("SELECT COUNT(*) as count FROM accounting_plan")->fetch()['count']
    ];
} catch (Exception $e) {
    error_log("Erreur connexion tenant DB: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Détails Client - <?php echo htmlspecialchars($tenant['company_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
        }

        .admin-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 20px 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .admin-header h1 {
            font-size: 1.8em;
        }

        .admin-header .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .admin-header a {
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .admin-header a:hover {
            background: rgba(255,255,255,0.2);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .breadcrumb {
            margin-bottom: 20px;
            color: #718096;
        }

        .breadcrumb a {
            color: #2a5298;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h2 {
            font-size: 2em;
            color: #2d3748;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card h3 {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
            color: #2d3748;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .info-item {
            padding: 12px 0;
        }

        .info-item label {
            display: block;
            font-weight: 600;
            color: #718096;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .info-item .value {
            color: #2d3748;
            font-size: 1.1em;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
            display: inline-block;
        }

        .badge.active {
            background: #c6f6d5;
            color: #22543d;
        }

        .badge.trial {
            background: #feebc8;
            color: #7c2d12;
        }

        .badge.suspended {
            background: #fed7d7;
            color: #742a2a;
        }

        .badge.cancelled {
            background: #e2e8f0;
            color: #4a5568;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .stat-box .value {
            font-size: 2em;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-box .label {
            color: #718096;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
            border: none;
            cursor: pointer;
            font-size: 1em;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .btn-warning {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .action-card {
            background: #f7fafc;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }

        .action-card:hover {
            border-color: #2a5298;
            background: white;
        }

        .action-card h4 {
            margin-bottom: 10px;
            color: #2d3748;
        }

        .action-card p {
            color: #718096;
            font-size: 0.9em;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #4a5568;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2a5298;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .modal-close {
            cursor: pointer;
            font-size: 1.5em;
            color: #718096;
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
    </style>
</head>
<body>
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-shield-alt"></i> Administration</h1>
        </div>
        <div class="nav-links">
            <a href="index.php"><i class="fas fa-home"></i> Tableau de Bord</a>
            <a href="tenants.php"><i class="fas fa-users"></i> Clients</a>
            <span style="color: rgba(255,255,255,0.7);">|</span>
            <span>
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
            </span>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <div class="breadcrumb">
            <a href="index.php">Tableau de Bord</a> /
            <a href="tenants.php">Clients</a> /
            <?php echo htmlspecialchars($tenant['company_name']); ?>
        </div>

        <div class="page-header">
            <h2>
                <i class="fas fa-building"></i>
                <?php echo htmlspecialchars($tenant['company_name']); ?>
            </h2>
            <span class="badge <?php echo $tenant['status']; ?>">
                <?php echo ucfirst($tenant['status']); ?>
            </span>
        </div>

        <div id="message"></div>

        <div class="grid-2">
            <!-- Informations principales -->
            <div class="card">
                <h3><i class="fas fa-info-circle"></i> Informations du Client</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Code Tenant</label>
                        <div class="value"><?php echo $tenant['tenant_code']; ?></div>
                    </div>
                    <div class="info-item">
                        <label>Entreprise</label>
                        <div class="value"><?php echo htmlspecialchars($tenant['company_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Contact</label>
                        <div class="value"><?php echo htmlspecialchars($tenant['contact_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <div class="value"><?php echo htmlspecialchars($tenant['contact_email']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Téléphone</label>
                        <div class="value"><?php echo htmlspecialchars($tenant['contact_phone'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Base de Données</label>
                        <div class="value"><code><?php echo $tenant['database_name']; ?></code></div>
                    </div>
                    <div class="info-item">
                        <label>Créé le</label>
                        <div class="value"><?php echo date('d/m/Y à H:i', strtotime($tenant['created_at'])); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Essai jusqu'au</label>
                        <div class="value">
                            <?php
                            if ($tenant['trial_ends_at']) {
                                $trial_end = new DateTime($tenant['trial_ends_at']);
                                $now = new DateTime();
                                $diff = $now->diff($trial_end);

                                echo date('d/m/Y', strtotime($tenant['trial_ends_at']));

                                if ($trial_end > $now) {
                                    echo " <small style='color: #48bb78;'>({$diff->days} jours restants)</small>";
                                } else {
                                    echo " <small style='color: #f56565;'>(Expiré)</small>";
                                }
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Abonnement -->
            <div class="card">
                <h3><i class="fas fa-crown"></i> Abonnement</h3>
                <div class="info-item">
                    <label>Plan Actuel</label>
                    <div class="value">
                        <?php echo htmlspecialchars($tenant['plan_name']); ?>
                        <small>(<?php echo number_format($tenant['price_monthly'], 2); ?> CHF/mois)</small>
                    </div>
                </div>
                <div class="info-item">
                    <label>Limites</label>
                    <div class="value">
                        <div><i class="fas fa-users"></i> <?php echo $tenant['max_users']; ?> utilisateurs</div>
                        <div><i class="fas fa-exchange-alt"></i> <?php echo $tenant['max_transactions_per_month']; ?> transactions/mois</div>
                        <div><i class="fas fa-database"></i> <?php echo $tenant['max_storage_mb']; ?> MB stockage</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques réelles -->
        <?php if ($real_stats): ?>
        <div class="card">
            <h3><i class="fas fa-chart-bar"></i> Statistiques d'Utilisation</h3>
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="value"><?php echo $real_stats['users']; ?></div>
                    <div class="label">Utilisateurs</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $real_stats['transactions_this_month']; ?></div>
                    <div class="label">Transactions ce Mois</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $real_stats['transactions_total']; ?></div>
                    <div class="label">Transactions Total</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $real_stats['invoices']; ?></div>
                    <div class="label">Factures</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $real_stats['contacts']; ?></div>
                    <div class="label">Contacts</div>
                </div>
                <div class="stat-box">
                    <div class="value"><?php echo $real_stats['accounts']; ?></div>
                    <div class="label">Comptes</div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions -->
        <div class="card">
            <h3><i class="fas fa-cog"></i> Actions de Gestion</h3>
            <div class="actions-grid">
                <div class="action-card">
                    <h4><i class="fas fa-toggle-on"></i> Changer le Statut</h4>
                    <p>Activer, suspendre ou annuler le compte</p>
                    <button class="btn btn-primary" onclick="openModal('statusModal')">Modifier le Statut</button>
                </div>

                <div class="action-card">
                    <h4><i class="fas fa-level-up-alt"></i> Changer le Plan</h4>
                    <p>Mettre à jour l'abonnement et les limites</p>
                    <button class="btn btn-success" onclick="openModal('planModal')">Modifier le Plan</button>
                </div>

                <div class="action-card">
                    <h4><i class="fas fa-clock"></i> Prolonger l'Essai</h4>
                    <p>Ajouter des jours à la période d'essai</p>
                    <button class="btn btn-warning" onclick="openModal('trialModal')">Prolonger l'Essai</button>
                </div>

                <div class="action-card">
                    <h4><i class="fas fa-trash-alt"></i> Supprimer le Tenant</h4>
                    <p>Supprimer le client et sa base de données</p>
                    <button class="btn btn-danger" onclick="openModal('deleteModal')">Supprimer</button>
                </div>
            </div>
        </div>

        <!-- Logs d'audit -->
        <div class="card">
            <h3><i class="fas fa-history"></i> Logs d'Audit (20 derniers)</h3>
            <?php if (empty($audit_logs)): ?>
                <p style="text-align: center; color: #718096;">Aucun log disponible</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Détails</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($audit_logs as $log): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                            <td>
                                <?php
                                if ($log['details']) {
                                    $details = json_decode($log['details'], true);
                                    if (is_array($details)) {
                                        echo '<small>' . htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</small>';
                                    }
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal: Changer le Statut -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Changer le Statut</h3>
                <span class="modal-close" onclick="closeModal('statusModal')">&times;</span>
            </div>
            <form id="statusForm">
                <div class="form-group">
                    <label>Nouveau Statut</label>
                    <select name="status" class="form-control" required>
                        <option value="active" <?php echo $tenant['status'] === 'active' ? 'selected' : ''; ?>>Actif</option>
                        <option value="trial" <?php echo $tenant['status'] === 'trial' ? 'selected' : ''; ?>>Essai</option>
                        <option value="suspended" <?php echo $tenant['status'] === 'suspended' ? 'selected' : ''; ?>>Suspendu</option>
                        <option value="cancelled" <?php echo $tenant['status'] === 'cancelled' ? 'selected' : ''; ?>>Annulé</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Mettre à Jour</button>
            </form>
        </div>
    </div>

    <!-- Modal: Changer le Plan -->
    <div id="planModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Changer le Plan</h3>
                <span class="modal-close" onclick="closeModal('planModal')">&times;</span>
            </div>
            <form id="planForm">
                <div class="form-group">
                    <label>Nouveau Plan</label>
                    <select name="plan" class="form-control" required>
                        <?php foreach ($plans as $plan): ?>
                        <option value="<?php echo $plan['plan_code']; ?>"
                                <?php echo $tenant['subscription_plan'] === $plan['plan_code'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($plan['plan_name']); ?> - <?php echo number_format($plan['price_monthly'], 2); ?> CHF/mois
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="width: 100%;">Mettre à Jour</button>
            </form>
        </div>
    </div>

    <!-- Modal: Prolonger l'Essai -->
    <div id="trialModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Prolonger l'Essai</h3>
                <span class="modal-close" onclick="closeModal('trialModal')">&times;</span>
            </div>
            <form id="trialForm">
                <div class="form-group">
                    <label>Nombre de Jours à Ajouter</label>
                    <input type="number" name="days" class="form-control" min="1" max="365" value="30" required>
                </div>
                <button type="submit" class="btn btn-warning" style="width: 100%;">Prolonger</button>
            </form>
        </div>
    </div>

    <!-- Modal: Supprimer -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Supprimer le Tenant</h3>
                <span class="modal-close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div class="alert alert-error">
                <strong>ATTENTION:</strong> Cette action est IRRÉVERSIBLE et supprimera:
                <ul style="margin: 10px 0 0 20px;">
                    <li>Le compte client</li>
                    <li>La base de données complète</li>
                    <li>Toutes les données (transactions, factures, contacts, etc.)</li>
                </ul>
            </div>
            <form id="deleteForm">
                <div class="form-group">
                    <label>Tapez <strong>DELETE</strong> pour confirmer</label>
                    <input type="text" name="confirmation" class="form-control" required placeholder="DELETE">
                </div>
                <button type="submit" class="btn btn-danger" style="width: 100%;">Supprimer Définitivement</button>
            </form>
        </div>
    </div>

    <script>
        const tenantId = <?php echo $tenant_id; ?>;

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function showMessage(message, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.innerHTML = `<div class="alert alert-${type}">${message}</div>`;
            setTimeout(() => {
                messageDiv.innerHTML = '';
            }, 5000);
        }

        // Changer le statut
        document.getElementById('statusForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('api/tenant_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'update_status',
                    tenant_id: tenantId,
                    status: formData.get('status')
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    closeModal('statusModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(err => showMessage('Erreur de connexion', 'error'));
        });

        // Changer le plan
        document.getElementById('planForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('api/tenant_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'update_plan',
                    tenant_id: tenantId,
                    plan: formData.get('plan')
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    closeModal('planModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(err => showMessage('Erreur de connexion', 'error'));
        });

        // Prolonger l'essai
        document.getElementById('trialForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            fetch('api/tenant_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'extend_trial',
                    tenant_id: tenantId,
                    days: parseInt(formData.get('days'))
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    closeModal('trialModal');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(err => showMessage('Erreur de connexion', 'error'));
        });

        // Supprimer le tenant
        document.getElementById('deleteForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            if (formData.get('confirmation') !== 'DELETE') {
                showMessage('Veuillez taper DELETE pour confirmer', 'error');
                return;
            }

            if (!confirm('Êtes-vous ABSOLUMENT SÛR? Cette action est IRRÉVERSIBLE!')) {
                return;
            }

            fetch('api/tenant_manage.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'delete_tenant',
                    tenant_id: tenantId,
                    confirmation: formData.get('confirmation')
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showMessage(data.message, 'success');
                    setTimeout(() => {
                        window.location.href = 'tenants.php';
                    }, 2000);
                } else {
                    showMessage(data.message, 'error');
                }
            })
            .catch(err => showMessage('Erreur de connexion', 'error'));
        });

        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
