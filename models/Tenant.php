<?php
/**
 * Modèle Tenant - Gestion des clients (locataires) multi-tenant
 * Chaque tenant a sa propre base de données isolée
 */

class Tenant {
    private $conn; // Connexion à la base MASTER
    private $table_name = "tenants";

    // Propriétés du tenant
    public $id;
    public $tenant_code;
    public $company_name;
    public $database_name;

    public $contact_name;
    public $contact_email;
    public $contact_phone;
    public $address;

    public $status;
    public $subscription_plan;
    public $trial_ends_at;
    public $subscription_started_at;
    public $subscription_ends_at;

    public $max_users;
    public $max_companies;
    public $max_transactions_per_month;
    public $max_storage_mb;

    public $db_host;
    public $db_username;
    public $db_password;

    public $temp_password; // Mot de passe temporaire généré lors de la création

    public $created_at;
    public $updated_at;
    public $last_login_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un nouveau tenant avec sa base de données
     */
    public function create() {
        try {
            // 1. Générer un code tenant unique
            if (empty($this->tenant_code)) {
                $this->tenant_code = $this->generateTenantCode();
            }

            // 2. Générer le nom de la base de données
            if (empty($this->database_name)) {
                $this->database_name = 'gestion_comptable_client_' . $this->tenant_code;
            }

            // 3. Insérer le tenant dans la base master (avec transaction)
            $this->conn->beginTransaction();

            $query = "INSERT INTO " . $this->table_name . "
                     SET tenant_code = :tenant_code,
                         company_name = :company_name,
                         database_name = :database_name,
                         contact_name = :contact_name,
                         contact_email = :contact_email,
                         contact_phone = :contact_phone,
                         address = :address,
                         status = :status,
                         subscription_plan = :subscription_plan,
                         trial_ends_at = :trial_ends_at,
                         max_users = :max_users,
                         max_companies = :max_companies,
                         max_transactions_per_month = :max_transactions,
                         max_storage_mb = :max_storage,
                         db_host = :db_host,
                         db_username = :db_username,
                         db_password = :db_password";

            $stmt = $this->conn->prepare($query);

            // Nettoyer les données
            $this->tenant_code = htmlspecialchars(strip_tags($this->tenant_code));
            $this->company_name = htmlspecialchars(strip_tags($this->company_name));
            $this->contact_name = htmlspecialchars(strip_tags($this->contact_name));
            $this->contact_email = htmlspecialchars(strip_tags($this->contact_email));

            // Valeurs par défaut
            $this->status = $this->status ?? 'trial';
            $this->subscription_plan = $this->subscription_plan ?? 'free';
            $this->trial_ends_at = $this->trial_ends_at ?? date('Y-m-d H:i:s', strtotime('+30 days'));
            $this->max_users = $this->max_users ?? 1;
            $this->max_companies = $this->max_companies ?? 1;
            $this->max_transactions_per_month = $this->max_transactions_per_month ?? 100;
            $this->max_storage_mb = $this->max_storage_mb ?? 100;
            $this->db_host = $this->db_host ?? 'localhost';
            $this->db_username = $this->db_username ?? 'root';
            $this->db_password = $this->db_password ?? '';

            // Bind les valeurs
            $stmt->bindParam(":tenant_code", $this->tenant_code);
            $stmt->bindParam(":company_name", $this->company_name);
            $stmt->bindParam(":database_name", $this->database_name);
            $stmt->bindParam(":contact_name", $this->contact_name);
            $stmt->bindParam(":contact_email", $this->contact_email);
            $stmt->bindParam(":contact_phone", $this->contact_phone);
            $stmt->bindParam(":address", $this->address);
            $stmt->bindParam(":status", $this->status);
            $stmt->bindParam(":subscription_plan", $this->subscription_plan);
            $stmt->bindParam(":trial_ends_at", $this->trial_ends_at);
            $stmt->bindParam(":max_users", $this->max_users);
            $stmt->bindParam(":max_companies", $this->max_companies);
            $stmt->bindParam(":max_transactions", $this->max_transactions_per_month);
            $stmt->bindParam(":max_storage", $this->max_storage_mb);
            $stmt->bindParam(":db_host", $this->db_host);
            $stmt->bindParam(":db_username", $this->db_username);
            $stmt->bindParam(":db_password", $this->db_password);

            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de la création du tenant dans la base master");
            }

            $this->id = $this->conn->lastInsertId();

            // Fermer le curseur pour éviter l'erreur "unbuffered queries"
            $stmt->closeCursor();

            // 4. Commit la transaction AVANT d'autres opérations
            $this->conn->commit();

            // 5. Logger l'action (APRÈS le commit)
            $this->logAction('tenant_created', [
                'tenant_id' => $this->id,
                'tenant_code' => $this->tenant_code,
                'company_name' => $this->company_name
            ]);

            // 6. Créer la base de données du client (HORS transaction)
            // CREATE DATABASE ne peut pas être dans une transaction
            $this->createTenantDatabase();

            return true;

        } catch (Exception $e) {
            // Vérifier si une transaction est active avant de rollback
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log("Erreur création tenant: " . $e->getMessage());
            throw $e; // Relancer l'exception pour un meilleur débogage
        }
    }

    /**
     * Créer la base de données pour le tenant
     */
    private function createTenantDatabase() {
        // 1. Créer la base de données avec une nouvelle connexion dédiée
        // Pour éviter les problèmes d'unbuffered queries avec la connexion master
        $create_db_conn = new PDO(
            "mysql:host={$this->db_host}",
            $this->db_username,
            $this->db_password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ]
        );

        $create_db_conn->exec("CREATE DATABASE IF NOT EXISTS `{$this->database_name}`
                          DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // Fermer cette connexion temporaire
        $create_db_conn = null;

        // 2. Se connecter à la nouvelle base
        $tenant_conn = new PDO(
            "mysql:host={$this->db_host};dbname={$this->database_name}",
            $this->db_username,
            $this->db_password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ]
        );

        // 3. Exécuter le script d'installation (utiliser le fichier simplifié pour tenants)
        $install_sql = file_get_contents(__DIR__ . '/../CREATE_TENANT_TABLES.sql');

        // Parser correctement le SQL : construire les statements ligne par ligne
        $lines = explode("\n", $install_sql);
        $current_statement = '';
        $statements = [];

        foreach ($lines as $line) {
            $line = trim($line);
            // Ignorer les commentaires et lignes vides
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }

            $current_statement .= ' ' . $line;

            // Si la ligne se termine par ;, c'est la fin d'un statement
            if (substr($line, -1) === ';') {
                $statements[] = trim($current_statement);
                $current_statement = '';
            }
        }

        // Exécuter les commandes SQL
        $executed_count = 0;
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    // Utiliser query() au lieu d'exec() pour pouvoir fermer le curseur
                    $stmt = $tenant_conn->query($statement);
                    if ($stmt) {
                        $stmt->closeCursor(); // Fermer immédiatement le curseur
                    }
                    $executed_count++;
                } catch (PDOException $e) {
                    // Ignorer les erreurs "already exists"
                    if (strpos($e->getMessage(), 'already exists') === false) {
                        error_log("Erreur SQL tenant {$this->database_name}: " . $e->getMessage());
                        error_log("Statement: " . substr($statement, 0, 150));
                    }
                }
            }
        }
        error_log("Tables créées pour {$this->database_name}: {$executed_count} commandes exécutées");

        // Fermer et rouvrir la connexion pour nettoyer l'état
        $tenant_conn = null;
        $tenant_conn = new PDO(
            "mysql:host={$this->db_host};dbname={$this->database_name}",
            $this->db_username,
            $this->db_password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
            ]
        );

        // 4. Insérer les rôles et permissions par défaut
        $roles_sql = file_get_contents(__DIR__ . '/../INSERT_ROLES_PERMISSIONS.sql');

        // Parser et exécuter le SQL des rôles/permissions
        $lines = explode("\n", $roles_sql);
        $current_statement = '';
        $statements = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '--') === 0) {
                continue;
            }
            $current_statement .= ' ' . $line;
            if (substr($line, -1) === ';') {
                $statements[] = trim($current_statement);
                $current_statement = '';
            }
        }

        $roles_count = 0;
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $stmt = $tenant_conn->query($statement);
                    if ($stmt) {
                        $stmt->closeCursor();
                    }
                    $roles_count++;
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                        error_log("Erreur insertion rôles/permissions: " . $e->getMessage());
                    }
                }
            }
        }
        error_log("Rôles et permissions créés: {$roles_count} commandes exécutées");

        // 5. Créer le premier utilisateur admin pour ce tenant
        $this->createDefaultAdminUser($tenant_conn);
    }

    /**
     * Créer l'utilisateur admin par défaut pour le tenant
     */
    private function createDefaultAdminUser($tenant_conn) {
        // Générer un mot de passe temporaire
        $temp_password = bin2hex(random_bytes(8));
        $password_hash = password_hash($temp_password, PASSWORD_BCRYPT);

        $username = strtolower(str_replace(' ', '_', $this->contact_name));
        $email = $this->contact_email;

        // Récupérer l'ID du rôle admin
        $stmt = $tenant_conn->prepare("SELECT id FROM roles WHERE name = 'admin' LIMIT 1");
        $stmt->execute();
        $role = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $admin_role_id = $role ? $role['id'] : null;

        $query = "INSERT INTO users (username, email, password, role_id, is_active, created_at)
                  VALUES (:username, :email, :password, :role_id, 1, NOW())";

        $stmt = $tenant_conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password', $password_hash);
        $stmt->bindParam(':role_id', $admin_role_id);
        $stmt->execute();
        $stmt->closeCursor(); // Fermer le curseur

        // Stocker le mot de passe temporaire pour l'envoyer par email
        $this->temp_password = $temp_password;

        error_log("Utilisateur admin créé: {$username} avec le rôle admin (ID: {$admin_role_id})");
    }

    /**
     * Générer un code tenant unique
     */
    private function generateTenantCode() {
        do {
            $code = strtoupper(substr(uniqid(), -8));
            $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM tenants WHERE tenant_code = ?");
            $stmt->execute([$code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor(); // Fermer le curseur pour éviter les erreurs unbuffered
        } while ($result['count'] > 0);

        return $code;
    }

    /**
     * Lire un tenant par ID
     */
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); // Fermer le curseur

        if ($row) {
            $this->tenant_code = $row['tenant_code'];
            $this->company_name = $row['company_name'];
            $this->database_name = $row['database_name'];
            $this->contact_name = $row['contact_name'];
            $this->contact_email = $row['contact_email'];
            $this->contact_phone = $row['contact_phone'];
            $this->status = $row['status'];
            $this->subscription_plan = $row['subscription_plan'];
            // ... autres champs
            return true;
        }

        return false;
    }

    /**
     * Lire un tenant par email
     */
    public function readByEmail($email) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE contact_email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); // Fermer le curseur
        return $result;
    }

    /**
     * Lire tous les tenants
     */
    public function readAll() {
        $query = "SELECT * FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt;
    }

    /**
     * Mettre à jour le statut
     */
    public function updateStatus($status) {
        $query = "UPDATE " . $this->table_name . "
                  SET status = :status,
                      updated_at = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Mettre à jour la dernière connexion
     */
    public function updateLastLogin() {
        $query = "UPDATE " . $this->table_name . "
                  SET last_login_at = NOW()
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Logger une action dans audit_logs
     */
    private function logAction($action, $details = []) {
        try {
            $query = "INSERT INTO audit_logs
                      (tenant_id, action, details, ip_address, user_agent, created_at)
                      VALUES (:tenant_id, :action, :details, :ip, :user_agent, NOW())";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':tenant_id', $this->id);
            $stmt->bindParam(':action', $action);
            $stmt->bindValue(':details', json_encode($details));
            $stmt->bindValue(':ip', $_SERVER['REMOTE_ADDR'] ?? null);
            $stmt->bindValue(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);
            $stmt->execute();
            $stmt->closeCursor(); // Fermer le curseur après exécution
        } catch (Exception $e) {
            // Ne pas bloquer si le logging échoue
            error_log("Erreur logging action: " . $e->getMessage());
        }
    }

    /**
     * Obtenir la connexion à la base de données du tenant
     */
    public function getTenantConnection() {
        return new PDO(
            "mysql:host={$this->db_host};dbname={$this->database_name}",
            $this->db_username,
            $this->db_password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]
        );
    }
}
?>
