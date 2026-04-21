<?php
class User {
    // Connexion à la base de données et nom de la table
    private $conn;
    private $table_name = "users";

    // Propriétés de l'objet
    public $id;
    public $company_id;
    public $username;
    public $email;
    public $password;
    public $role_id;
    public $is_active;
    public $last_login_at;
    public $created_at;
    public $updated_at;

    // Constructeur avec connexion à la base de données
    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer un nouvel utilisateur
    public function create() {
        try {
            // Requête d'insertion
            $query = "INSERT INTO " . $this->table_name . " 
                    (username, email, password) 
                    VALUES (:username, :email, :password)";

            // Préparer la requête
            $stmt = $this->conn->prepare($query);

            // Nettoyer les données (même si ça a déjà été fait avant)
            $this->username = htmlspecialchars(strip_tags($this->username));
            $this->email = htmlspecialchars(strip_tags($this->email));
            $this->password = htmlspecialchars(strip_tags($this->password));

            // Lier les paramètres
            $stmt->bindParam(':username', $this->username);
            $stmt->bindParam(':email', $this->email);
            $stmt->bindParam(':password', $this->password);

            // Exécuter la requête
            if ($stmt->execute()) {
                $this->id = $this->conn->lastInsertId();
                return true;
            }

            error_log("Échec d'insertion dans la base de données: " . print_r($stmt->errorInfo(), true));
            return false;
        } catch (Exception $e) {
            error_log("Exception dans create(): " . $e->getMessage());
            throw $e;
        }
    }

    // Vérifier si l'utilisateur existe
    public function userExists() {
        try {
            // Requête pour vérifier si l'utilisateur existe
            $query = "SELECT id, password, email FROM " . $this->table_name . " 
                    WHERE username = :username 
                    LIMIT 0,1";

            // Préparer la requête
            $stmt = $this->conn->prepare($query);

            // Lier le nom d'utilisateur
            $stmt->bindParam(':username', $this->username);

            // Exécuter la requête
            $stmt->execute();

            // Vérifier si l'utilisateur existe
            if ($stmt->rowCount() > 0) {
                // Récupérer les données de l'utilisateur
                $row = $stmt->fetch(PDO::FETCH_ASSOC);

                // Définir les valeurs des propriétés
                $this->id = $row['id'];
                $this->password = $row['password'];
                $this->email = $row['email'];

                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Exception dans userExists(): " . $e->getMessage());
            throw $e;
        }
    }

    // Vérifier si l'email existe
    public function emailExists() {
        try {
            $query = "SELECT id FROM " . $this->table_name . "
                     WHERE email = :email
                     LIMIT 0,1";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':email', $this->email);
            $stmt->execute();

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("Exception dans emailExists(): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Récupérer un utilisateur par ID avec ses informations de rôle
     */
    public function readById($user_id) {
        $query = "SELECT u.*, r.name as role_name, r.display_name as role_display_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN roles r ON u.role_id = r.id
                  WHERE u.id = :user_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            $this->id = $row['id'];
            $this->company_id = $row['company_id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->role_id = $row['role_id'];
            $this->is_active = $row['is_active'];
            $this->last_login_at = $row['last_login_at'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            return $row;
        }

        return false;
    }

    /**
     * Récupérer tous les utilisateurs d'une entreprise
     */
    public function readByCompany($company_id) {
        $query = "SELECT u.*, r.name as role_name, r.display_name as role_display_name
                  FROM " . $this->table_name . " u
                  LEFT JOIN roles r ON u.role_id = r.id
                  WHERE u.company_id = :company_id OR u.company_id IS NULL
                  ORDER BY u.created_at DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mettre à jour un utilisateur
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET role_id = :role_id,
                      is_active = :is_active,
                      updated_at = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':role_id', $this->role_id);
        $stmt->bindParam(':is_active', $this->is_active);
        $stmt->bindParam(':id', $this->id);

        return $stmt->execute();
    }

    /**
     * Supprimer un utilisateur
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    /**
     * Mettre à jour la date de dernière connexion
     */
    public function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . "
                  SET last_login_at = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);
        return $stmt->execute();
    }

    /**
     * Récupérer les permissions d'un utilisateur via son rôle
     */
    public function getUserPermissions($user_id) {
        $query = "SELECT p.name, p.display_name, p.module, p.description
                  FROM permissions p
                  JOIN role_permissions rp ON p.id = rp.permission_id
                  JOIN roles r ON rp.role_id = r.id
                  JOIN users u ON u.role_id = r.id
                  WHERE u.id = :user_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Vérifier si l'utilisateur a une permission spécifique
     */
    public function hasPermission($user_id, $permission_name) {
        $query = "SELECT COUNT(*) as count
                  FROM permissions p
                  JOIN role_permissions rp ON p.id = rp.permission_id
                  JOIN roles r ON rp.role_id = r.id
                  JOIN users u ON u.role_id = r.id
                  WHERE u.id = :user_id AND p.name = :permission_name";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':permission_name', $permission_name);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     */
    public function hasRole($user_id, $role_name) {
        $query = "SELECT COUNT(*) as count
                  FROM users u
                  JOIN roles r ON u.role_id = r.id
                  WHERE u.id = :user_id AND r.name = :role_name";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':role_name', $role_name);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
}
?>