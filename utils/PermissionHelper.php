<?php
/**
 * Helper class pour vérifier les permissions des utilisateurs
 * Fournit des méthodes statiques pour une utilisation facile dans toute l'application
 */

class PermissionHelper {

    /**
     * Vérifier si l'utilisateur connecté a une permission spécifique
     *
     * @param PDO $db - Connexion à la base de données
     * @param string $permission_name - Nom de la permission (ex: 'users.create')
     * @return bool
     */
    public static function hasPermission($db, $permission_name) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        try {
            $query = "SELECT COUNT(*) as count
                      FROM permissions p
                      JOIN role_permissions rp ON p.id = rp.permission_id
                      JOIN roles r ON rp.role_id = r.id
                      JOIN users u ON u.role_id = r.id
                      WHERE u.id = :user_id AND p.name = :permission_name AND u.is_active = 1";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':permission_name', $permission_name);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Erreur vérification permission: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si l'utilisateur connecté a l'un des permissions dans une liste
     *
     * @param PDO $db - Connexion à la base de données
     * @param array $permissions - Liste de permissions (ex: ['users.view', 'users.edit'])
     * @return bool
     */
    public static function hasAnyPermission($db, $permissions) {
        foreach ($permissions as $permission) {
            if (self::hasPermission($db, $permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifier si l'utilisateur connecté a toutes les permissions dans une liste
     *
     * @param PDO $db - Connexion à la base de données
     * @param array $permissions - Liste de permissions
     * @return bool
     */
    public static function hasAllPermissions($db, $permissions) {
        foreach ($permissions as $permission) {
            if (!self::hasPermission($db, $permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Vérifier si l'utilisateur connecté a un rôle spécifique
     *
     * @param PDO $db - Connexion à la base de données
     * @param string $role_name - Nom du rôle (ex: 'admin', 'accountant')
     * @return bool
     */
    public static function hasRole($db, $role_name) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        try {
            $query = "SELECT COUNT(*) as count
                      FROM users u
                      JOIN roles r ON u.role_id = r.id
                      WHERE u.id = :user_id AND r.name = :role_name AND u.is_active = 1";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':role_name', $role_name);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Erreur vérification rôle: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifier si l'utilisateur est un administrateur
     *
     * @param PDO $db - Connexion à la base de données
     * @return bool
     */
    public static function isAdmin($db) {
        return self::hasRole($db, 'admin');
    }

    /**
     * Obtenir toutes les permissions de l'utilisateur connecté
     *
     * @param PDO $db - Connexion à la base de données
     * @return array - Liste des permissions avec leurs détails
     */
    public static function getUserPermissions($db) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }

        try {
            $query = "SELECT p.name, p.display_name, p.module, p.description
                      FROM permissions p
                      JOIN role_permissions rp ON p.id = rp.permission_id
                      JOIN roles r ON rp.role_id = r.id
                      JOIN users u ON u.role_id = r.id
                      WHERE u.id = :user_id AND u.is_active = 1
                      ORDER BY p.module, p.name";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();

            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $permissions;
        } catch (Exception $e) {
            error_log("Erreur récupération permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtenir le rôle de l'utilisateur connecté
     *
     * @param PDO $db - Connexion à la base de données
     * @return array|false - Informations du rôle ou false
     */
    public static function getUserRole($db) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }

        try {
            $query = "SELECT r.id, r.name, r.display_name, r.description
                      FROM roles r
                      JOIN users u ON u.role_id = r.id
                      WHERE u.id = :user_id AND u.is_active = 1
                      LIMIT 1";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();

            $role = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $role;
        } catch (Exception $e) {
            error_log("Erreur récupération rôle: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Rediriger si l'utilisateur n'a pas la permission requise
     *
     * @param PDO $db - Connexion à la base de données
     * @param string $permission_name - Permission requise
     * @param string $redirect_url - URL de redirection (par défaut: accueil)
     */
    public static function requirePermission($db, $permission_name, $redirect_url = 'index.php?page=home') {
        if (!self::hasPermission($db, $permission_name)) {
            $_SESSION['error_message'] = "Vous n'avez pas la permission requise pour accéder à cette page.";
            header("Location: $redirect_url");
            exit();
        }
    }

    /**
     * Rediriger si l'utilisateur n'a pas le rôle requis
     *
     * @param PDO $db - Connexion à la base de données
     * @param string $role_name - Rôle requis
     * @param string $redirect_url - URL de redirection
     */
    public static function requireRole($db, $role_name, $redirect_url = 'index.php?page=home') {
        if (!self::hasRole($db, $role_name)) {
            $_SESSION['error_message'] = "Vous n'avez pas les droits suffisants pour accéder à cette page.";
            header("Location: $redirect_url");
            exit();
        }
    }

    /**
     * Récupérer tous les rôles disponibles
     *
     * @param PDO $db - Connexion à la base de données
     * @return array
     */
    public static function getAllRoles($db) {
        try {
            $query = "SELECT * FROM roles ORDER BY name";
            $stmt = $db->query($query);
            $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            return $roles;
        } catch (Exception $e) {
            error_log("Erreur récupération rôles: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Récupérer toutes les permissions disponibles
     *
     * @param PDO $db - Connexion à la base de données
     * @return array
     */
    public static function getAllPermissions($db) {
        try {
            $query = "SELECT * FROM permissions ORDER BY module, name";
            $stmt = $db->query($query);
            $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            return $permissions;
        } catch (Exception $e) {
            error_log("Erreur récupération permissions: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Vérifier si un utilisateur spécifique a une permission
     * (Utile pour l'administration)
     *
     * @param PDO $db - Connexion à la base de données
     * @param int $user_id - ID de l'utilisateur
     * @param string $permission_name - Nom de la permission
     * @return bool
     */
    public static function userHasPermission($db, $user_id, $permission_name) {
        try {
            $query = "SELECT COUNT(*) as count
                      FROM permissions p
                      JOIN role_permissions rp ON p.id = rp.permission_id
                      JOIN roles r ON rp.role_id = r.id
                      JOIN users u ON u.role_id = r.id
                      WHERE u.id = :user_id AND p.name = :permission_name AND u.is_active = 1";

            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':permission_name', $permission_name);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            return $result['count'] > 0;
        } catch (Exception $e) {
            error_log("Erreur vérification permission utilisateur: " . $e->getMessage());
            return false;
        }
    }
}
?>
