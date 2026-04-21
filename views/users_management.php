<?php
/**
 * Page de gestion des utilisateurs
 * Permet de voir, ajouter, modifier et supprimer des utilisateurs
 * Requiert la permission 'users.view'
 */

// Vérifier que l'utilisateur est en mode multi-tenant
if (!isset($_SESSION['tenant_database'])) {
    echo "<div class='alert alert-warning'>Cette fonctionnalité n'est disponible qu'en mode multi-tenant.</div>";
    return;
}

require_once 'config/database.php';
require_once 'utils/PermissionHelper.php';

$database = new Database();
$db = $database->getConnection();

// Vérifier la permission
PermissionHelper::requirePermission($db, 'users.view');

$can_create = PermissionHelper::hasPermission($db, 'users.create');
$can_edit = PermissionHelper::hasPermission($db, 'users.edit');
$can_delete = PermissionHelper::hasPermission($db, 'users.delete');
$is_admin = PermissionHelper::isAdmin($db);

// Récupérer tous les utilisateurs
require_once 'models/User.php';
$user_model = new User($db);
$users = $user_model->readByCompany($_SESSION['company_id'] ?? null);

// Récupérer tous les rôles pour le formulaire
$roles = PermissionHelper::getAllRoles($db);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Utilisateurs</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .users-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0;
            color: #2c3e50;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background-color: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background-color: #2980b9;
        }

        .btn-success {
            background-color: #27ae60;
            color: white;
        }

        .btn-warning {
            background-color: #f39c12;
            color: white;
        }

        .btn-danger {
            background-color: #e74c3c;
            color: white;
        }

        .btn-sm {
            padding: 5px 10px;
            font-size: 12px;
        }

        .users-table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background-color: #34495e;
            color: white;
        }

        th, td {
            padding: 15px;
            text-align: left;
        }

        tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        tbody tr:hover {
            background-color: #e9ecef;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge-admin {
            background-color: #e74c3c;
            color: white;
        }

        .badge-accountant {
            background-color: #3498db;
            color: white;
        }

        .badge-reader {
            background-color: #95a5a6;
            color: white;
        }

        .badge-active {
            background-color: #27ae60;
            color: white;
        }

        .badge-inactive {
            background-color: #7f8c8d;
            color: white;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }

        .modal-content {
            background-color: white;
            margin: 50px auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #ecf0f1;
            padding-bottom: 10px;
        }

        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }

        .close {
            font-size: 28px;
            font-weight: bold;
            color: #95a5a6;
            cursor: pointer;
        }

        .close:hover {
            color: #e74c3c;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #34495e;
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }

        .form-control:focus {
            outline: none;
            border-color: #3498db;
        }

        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #95a5a6;
        }

        .last-login {
            font-size: 12px;
            color: #7f8c8d;
        }
    </style>
</head>
<body>
    <div class="users-container">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Gestion des Utilisateurs</h1>
            <?php if ($can_create): ?>
                <button class="btn btn-primary" onclick="openInviteModal()">
                    <i class="fas fa-user-plus"></i> Inviter un utilisateur
                </button>
            <?php endif; ?>
        </div>

        <div id="alertContainer"></div>

        <div class="users-table">
            <table>
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Dernière connexion</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($users) > 0): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php
                                    $role_class = 'badge-reader';
                                    if ($user['role_name'] == 'admin') $role_class = 'badge-admin';
                                    else if ($user['role_name'] == 'accountant') $role_class = 'badge-accountant';
                                    ?>
                                    <span class="badge <?php echo $role_class; ?>">
                                        <?php echo htmlspecialchars($user['role_display_name'] ?? 'Aucun rôle'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                        <?php echo $user['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="last-login">
                                        <?php
                                        if ($user['last_login_at']) {
                                            echo date('d/m/Y H:i', strtotime($user['last_login_at']));
                                        } else {
                                            echo 'Jamais connecté';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <?php if ($can_edit): ?>
                                            <button class="btn btn-sm btn-warning" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($can_delete && $user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($is_admin): ?>
                                            <button class="btn btn-sm btn-primary" onclick="viewPermissions(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="no-data">
                                <i class="fas fa-users fa-3x"></i>
                                <p>Aucun utilisateur trouvé</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal d'invitation -->
    <div id="inviteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Inviter un utilisateur</h2>
                <span class="close" onclick="closeInviteModal()">&times;</span>
            </div>
            <form id="inviteForm">
                <div class="form-group">
                    <label for="invite_email">Email *</label>
                    <input type="email" id="invite_email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="invite_role">Rôle *</label>
                    <select id="invite_role" name="role_id" class="form-control" required>
                        <option value="">Sélectionner un rôle</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo htmlspecialchars($role['display_name']); ?> - <?php echo htmlspecialchars($role['description']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-success" style="width: 100%;">
                    <i class="fas fa-paper-plane"></i> Envoyer l'invitation
                </button>
            </form>
        </div>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-edit"></i> Modifier l'utilisateur</h2>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form id="editForm">
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="form-group">
                    <label for="edit_username">Nom d'utilisateur</label>
                    <input type="text" id="edit_username" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label for="edit_email">Email</label>
                    <input type="email" id="edit_email" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label for="edit_role">Rôle *</label>
                    <select id="edit_role" name="role_id" class="form-control" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['id']; ?>">
                                <?php echo htmlspecialchars($role['display_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="edit_active">Statut</label>
                    <select id="edit_active" name="is_active" class="form-control">
                        <option value="1">Actif</option>
                        <option value="0">Inactif</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning" style="width: 100%;">
                    <i class="fas fa-save"></i> Mettre à jour
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal d'invitation
        function openInviteModal() {
            document.getElementById('inviteModal').style.display = 'block';
        }

        function closeInviteModal() {
            document.getElementById('inviteModal').style.display = 'none';
            document.getElementById('inviteForm').reset();
        }

        // Modal de modification
        function openEditModal(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role_id;
            document.getElementById('edit_active').value = user.is_active;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Fermer les modals en cliquant en dehors
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = 'none';
            }
        }

        // Soumettre l'invitation
        document.getElementById('inviteForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = {
                action: 'invite',
                email: document.getElementById('invite_email').value,
                role_id: document.getElementById('invite_role').value
            };

            try {
                const response = await fetch('assets/ajax/users_management.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Invitation envoyée avec succès!', 'success');
                    closeInviteModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Erreur lors de l\'invitation', 'danger');
                }
            } catch (error) {
                showAlert('Erreur de connexion au serveur', 'danger');
            }
        });

        // Soumettre la modification
        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = {
                action: 'update',
                user_id: document.getElementById('edit_user_id').value,
                role_id: document.getElementById('edit_role').value,
                is_active: document.getElementById('edit_active').value
            };

            try {
                const response = await fetch('assets/ajax/users_management.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Utilisateur mis à jour avec succès!', 'success');
                    closeEditModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Erreur lors de la mise à jour', 'danger');
                }
            } catch (error) {
                showAlert('Erreur de connexion au serveur', 'danger');
            }
        });

        // Confirmer la suppression
        async function confirmDelete(userId, username) {
            if (!confirm(`Êtes-vous sûr de vouloir supprimer l'utilisateur "${username}" ?`)) {
                return;
            }

            try {
                const response = await fetch('assets/ajax/users_management.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        action: 'delete',
                        user_id: userId
                    })
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Utilisateur supprimé avec succès!', 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Erreur lors de la suppression', 'danger');
                }
            } catch (error) {
                showAlert('Erreur de connexion au serveur', 'danger');
            }
        }

        // Voir les permissions
        function viewPermissions(userId) {
            window.location.href = `index.php?page=user_permissions&user_id=${userId}`;
        }

        // Afficher une alerte
        function showAlert(message, type) {
            const alertHTML = `
                <div class="alert alert-${type}">
                    ${message}
                </div>
            `;
            document.getElementById('alertContainer').innerHTML = alertHTML;

            setTimeout(() => {
                document.getElementById('alertContainer').innerHTML = '';
            }, 5000);
        }
    </script>
</body>
</html>
