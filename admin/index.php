<?php
/**
 * Tableau de Bord Administrateur
 */

session_name('ADMIN_SESSION');
session_start();

// Vérifier si l'admin est connecté
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

require_once '../config/database_master.php';

$database = new DatabaseMaster();
$db = $database->getConnection();

// Récupérer les statistiques
$stats = [];

// Nombre total de tenants
$stmt = $db->query("SELECT COUNT(*) as total FROM tenants");
$stats['total_tenants'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Tenants actifs
$stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE status = 'active'");
$stats['active_tenants'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Tenants en essai
$stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE status = 'trial'");
$stats['trial_tenants'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Tenants suspendus
$stmt = $db->query("SELECT COUNT(*) as total FROM tenants WHERE status = 'suspended'");
$stats['suspended_tenants'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Revenus mensuels estimés
$stmt = $db->query("
    SELECT SUM(
        CASE sp.plan_code
            WHEN 'starter' THEN 29
            WHEN 'professional' THEN 79
            WHEN 'enterprise' THEN 199
            ELSE 0
        END
    ) as monthly_revenue
    FROM tenants t
    JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
    WHERE t.status IN ('active', 'trial')
");
$stats['monthly_revenue'] = $stmt->fetch(PDO::FETCH_ASSOC)['monthly_revenue'] ?? 0;

// Nouveaux clients ce mois
$stmt = $db->query("
    SELECT COUNT(*) as total
    FROM tenants
    WHERE DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(NOW(), '%Y-%m')
");
$stats['new_this_month'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Récupérer les derniers tenants
$tenants_query = "
    SELECT t.*, sp.plan_name
    FROM tenants t
    LEFT JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
    ORDER BY t.created_at DESC
    LIMIT 10
";
$recent_tenants = $db->query($tenants_query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - Tableau de Bord</title>
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

        .admin-header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .admin-header .logout-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .admin-header .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card .icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8em;
            margin-bottom: 15px;
        }

        .stat-card.blue .icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card.green .icon {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .stat-card.orange .icon {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
        }

        .stat-card.red .icon {
            background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
            color: white;
        }

        .stat-card .value {
            font-size: 2.5em;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .stat-card .label {
            color: #718096;
            font-size: 0.95em;
        }

        .section {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-header h2 {
            font-size: 1.5em;
            color: #2d3748;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: #f7fafc;
        }

        table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
        }

        table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        table tr:hover {
            background: #f7fafc;
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

        .action-btn {
            padding: 5px 10px;
            font-size: 0.9em;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="admin-header">
        <div>
            <h1><i class="fas fa-shield-alt"></i> Administration</h1>
        </div>
        <div class="user-info">
            <span>
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                <small>(<?php echo htmlspecialchars($_SESSION['admin_role']); ?>)</small>
            </span>
            <a href="logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Déconnexion
            </a>
        </div>
    </div>

    <div class="container">
        <!-- Statistiques -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="value"><?php echo $stats['total_tenants']; ?></div>
                <div class="label">Total Clients</div>
            </div>

            <div class="stat-card green">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="value"><?php echo $stats['active_tenants']; ?></div>
                <div class="label">Clients Actifs</div>
            </div>

            <div class="stat-card orange">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="value"><?php echo $stats['trial_tenants']; ?></div>
                <div class="label">En Essai</div>
            </div>

            <div class="stat-card blue">
                <div class="icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="value"><?php echo number_format($stats['monthly_revenue'], 0); ?> CHF</div>
                <div class="label">Revenus Mensuels</div>
            </div>

            <div class="stat-card green">
                <div class="icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="value"><?php echo $stats['new_this_month']; ?></div>
                <div class="label">Nouveaux ce Mois</div>
            </div>

            <div class="stat-card red">
                <div class="icon">
                    <i class="fas fa-ban"></i>
                </div>
                <div class="value"><?php echo $stats['suspended_tenants']; ?></div>
                <div class="label">Suspendus</div>
            </div>
        </div>

        <!-- Derniers clients -->
        <div class="section">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> Derniers Clients</h2>
                <a href="tenants.php" class="btn btn-primary">
                    <i class="fas fa-list"></i> Voir Tous les Clients
                </a>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Entreprise</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Plan</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tenants as $tenant): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($tenant['company_name']); ?></strong><br>
                            <small style="color: #718096;">Code: <?php echo $tenant['tenant_code']; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($tenant['contact_name']); ?></td>
                        <td><?php echo htmlspecialchars($tenant['contact_email']); ?></td>
                        <td><?php echo htmlspecialchars($tenant['plan_name'] ?? $tenant['subscription_plan']); ?></td>
                        <td>
                            <span class="badge <?php echo $tenant['status']; ?>">
                                <?php echo ucfirst($tenant['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($tenant['created_at'])); ?></td>
                        <td>
                            <a href="tenant_details.php?id=<?php echo $tenant['id']; ?>" class="btn btn-primary action-btn">
                                <i class="fas fa-eye"></i> Voir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
