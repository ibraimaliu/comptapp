<?php
/**
 * Liste Complète des Tenants
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

// Paramètres de recherche et filtrage
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$plan_filter = $_GET['plan'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Construire la requête avec filtres
$where_clauses = [];
$params = [];

if (!empty($search)) {
    $where_clauses[] = "(t.company_name LIKE :search OR t.contact_name LIKE :search OR t.contact_email LIKE :search OR t.tenant_code LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status_filter)) {
    $where_clauses[] = "t.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($plan_filter)) {
    $where_clauses[] = "t.subscription_plan = :plan";
    $params[':plan'] = $plan_filter;
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

// Colonnes de tri autorisées
$allowed_sort = ['company_name', 'contact_name', 'status', 'subscription_plan', 'created_at', 'trial_ends_at'];
if (!in_array($sort, $allowed_sort)) {
    $sort = 'created_at';
}

$allowed_order = ['ASC', 'DESC'];
if (!in_array($order, $allowed_order)) {
    $order = 'DESC';
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Compter le total
$count_query = "SELECT COUNT(*) as total FROM tenants t $where_sql";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_tenants = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_tenants / $per_page);

// Récupérer les tenants
$query = "
    SELECT t.*, sp.plan_name
    FROM tenants t
    LEFT JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
    $where_sql
    ORDER BY t.$sort $order
    LIMIT :offset, :per_page
";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':per_page', $per_page, PDO::PARAM_INT);
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Clients - Administration</title>
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
            max-width: 1600px;
            margin: 30px auto;
            padding: 0 20px;
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

        .filters-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 25px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
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

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .tenants-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stats-bar {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            margin-bottom: 20px;
            border-bottom: 2px solid #e2e8f0;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item .value {
            font-size: 1.5em;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-item .label {
            color: #718096;
            font-size: 0.9em;
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
            cursor: pointer;
            user-select: none;
        }

        table th:hover {
            background: #edf2f7;
        }

        table th i {
            margin-left: 5px;
            font-size: 0.8em;
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

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .btn-warning {
            background: #ed8936;
            color: white;
        }

        .btn-warning:hover {
            background: #dd6b20;
        }

        .btn-danger {
            background: #f56565;
            color: white;
        }

        .btn-danger:hover {
            background: #e53e3e;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #2d3748;
            margin: 0;
        }

        .modal-body {
            margin: 20px 0;
        }

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
            align-items: center;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            color: #4a5568;
            background: white;
            border: 1px solid #e2e8f0;
        }

        .pagination a:hover {
            background: #f7fafc;
        }

        .pagination .active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
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
            <a href="tenants.php" style="background: rgba(255,255,255,0.2);"><i class="fas fa-users"></i> Clients</a>
            <span style="color: rgba(255,255,255,0.7);">|</span>
            <span>
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
            </span>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2><i class="fas fa-users"></i> Gestion des Clients</h2>
        </div>

        <!-- Filtres -->
        <div class="filters-card">
            <form method="GET" action="tenants.php">
                <div class="filters-grid">
                    <div class="form-group">
                        <label>Rechercher</label>
                        <input type="text" name="search" class="form-control"
                               placeholder="Nom, email, code tenant..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>

                    <div class="form-group">
                        <label>Statut</label>
                        <select name="status" class="form-control">
                            <option value="">Tous</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Actif</option>
                            <option value="trial" <?php echo $status_filter === 'trial' ? 'selected' : ''; ?>>Essai</option>
                            <option value="suspended" <?php echo $status_filter === 'suspended' ? 'selected' : ''; ?>>Suspendu</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Annulé</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Plan</label>
                        <select name="plan" class="form-control">
                            <option value="">Tous</option>
                            <option value="free" <?php echo $plan_filter === 'free' ? 'selected' : ''; ?>>Gratuit</option>
                            <option value="starter" <?php echo $plan_filter === 'starter' ? 'selected' : ''; ?>>Starter</option>
                            <option value="professional" <?php echo $plan_filter === 'professional' ? 'selected' : ''; ?>>Professional</option>
                            <option value="enterprise" <?php echo $plan_filter === 'enterprise' ? 'selected' : ''; ?>>Enterprise</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </div>

                    <div class="form-group">
                        <label>&nbsp;</label>
                        <a href="tenants.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Réinitialiser
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Liste des tenants -->
        <div class="tenants-card">
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="value"><?php echo $total_tenants; ?></div>
                    <div class="label">Total Clients</div>
                </div>
                <div class="stat-item">
                    <div class="value">Page <?php echo $page; ?>/<?php echo max(1, $total_pages); ?></div>
                    <div class="label">Pagination</div>
                </div>
            </div>

            <?php if (empty($tenants)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Aucun client trouvé</h3>
                    <p>Aucun client ne correspond à vos critères de recherche.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th onclick="location.href='?sort=company_name&order=<?php echo $sort === 'company_name' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_diff_key($_GET, ['sort' => '', 'order' => ''])); ?>'">
                                Entreprise
                                <?php if ($sort === 'company_name'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </th>
                            <th onclick="location.href='?sort=contact_name&order=<?php echo $sort === 'contact_name' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_diff_key($_GET, ['sort' => '', 'order' => ''])); ?>'">
                                Contact
                                <?php if ($sort === 'contact_name'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </th>
                            <th>Email</th>
                            <th onclick="location.href='?sort=subscription_plan&order=<?php echo $sort === 'subscription_plan' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_diff_key($_GET, ['sort' => '', 'order' => ''])); ?>'">
                                Plan
                                <?php if ($sort === 'subscription_plan'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </th>
                            <th onclick="location.href='?sort=status&order=<?php echo $sort === 'status' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_diff_key($_GET, ['sort' => '', 'order' => ''])); ?>'">
                                Statut
                                <?php if ($sort === 'status'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </th>
                            <th onclick="location.href='?sort=created_at&order=<?php echo $sort === 'created_at' && $order === 'ASC' ? 'DESC' : 'ASC'; ?>&<?php echo http_build_query(array_diff_key($_GET, ['sort' => '', 'order' => ''])); ?>'">
                                Créé le
                                <?php if ($sort === 'created_at'): ?>
                                    <i class="fas fa-sort-<?php echo $order === 'ASC' ? 'up' : 'down'; ?>"></i>
                                <?php endif; ?>
                            </th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tenants as $tenant): ?>
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
                                <div class="action-buttons">
                                    <a href="tenant_details.php?id=<?php echo $tenant['id']; ?>" class="btn btn-primary action-btn" title="Voir les détails">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <?php if ($tenant['status'] === 'suspended' || $tenant['status'] === 'cancelled'): ?>
                                        <button onclick="activateTenant(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['company_name']); ?>')"
                                                class="btn btn-success action-btn" title="Activer">
                                            <i class="fas fa-check-circle"></i>
                                        </button>
                                    <?php else: ?>
                                        <button onclick="deactivateTenant(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['company_name']); ?>')"
                                                class="btn btn-warning action-btn" title="Désactiver">
                                            <i class="fas fa-ban"></i>
                                        </button>
                                    <?php endif; ?>

                                    <button onclick="openDeleteModal(<?php echo $tenant['id']; ?>, '<?php echo htmlspecialchars($tenant['company_name']); ?>')"
                                            class="btn btn-danger action-btn" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);

                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&<?php echo http_build_query(array_diff_key($_GET, ['page' => ''])); ?>">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de confirmation de suppression -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #dc2626;"></i> Confirmer la Suppression</h3>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger">
                    <strong>⚠️ ATTENTION - Cette action est IRRÉVERSIBLE!</strong>
                    <p style="margin-top: 10px;">Vous êtes sur le point de supprimer définitivement:</p>
                    <ul style="margin-top: 10px; padding-left: 20px;">
                        <li>Le client: <strong id="delete-company-name"></strong></li>
                        <li>Toute sa base de données</li>
                        <li>Toutes ses données (factures, contacts, transactions, etc.)</li>
                    </ul>
                </div>

                <p style="margin-top: 15px;"><strong>Pour confirmer, tapez "DELETE" ci-dessous:</strong></p>
                <input type="text" id="delete-confirmation" class="form-control" placeholder="Tapez DELETE">
                <input type="hidden" id="delete-tenant-id">
            </div>
            <div class="modal-footer">
                <button onclick="closeDeleteModal()" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Annuler
                </button>
                <button onclick="confirmDelete()" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Supprimer Définitivement
                </button>
            </div>
        </div>
    </div>

    <script>
        // Activer un tenant
        function activateTenant(tenantId, companyName) {
            if (!confirm(`Êtes-vous sûr de vouloir activer le client "${companyName}" ?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'activate');
            formData.append('tenant_id', tenantId);

            fetch('api_tenants.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de l\'activation');
            });
        }

        // Désactiver un tenant
        function deactivateTenant(tenantId, companyName) {
            if (!confirm(`Êtes-vous sûr de vouloir désactiver le client "${companyName}" ?\n\nL'accès à son compte sera bloqué.`)) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'deactivate');
            formData.append('tenant_id', tenantId);

            fetch('api_tenants.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la désactivation');
            });
        }

        // Ouvrir le modal de suppression
        function openDeleteModal(tenantId, companyName) {
            document.getElementById('delete-tenant-id').value = tenantId;
            document.getElementById('delete-company-name').textContent = companyName;
            document.getElementById('delete-confirmation').value = '';
            document.getElementById('deleteModal').style.display = 'block';
        }

        // Fermer le modal de suppression
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        // Confirmer la suppression
        function confirmDelete() {
            const confirmation = document.getElementById('delete-confirmation').value;
            const tenantId = document.getElementById('delete-tenant-id').value;
            const companyName = document.getElementById('delete-company-name').textContent;

            if (confirmation !== 'DELETE') {
                alert('Vous devez taper "DELETE" pour confirmer la suppression.');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('tenant_id', tenantId);
            formData.append('confirm', 'DELETE');

            fetch('api_tenants.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    closeDeleteModal();
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la suppression');
            });
        }

        // Fermer le modal si on clique en dehors
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
