<?php
/**
 * Classe Database - Connexion Multi-Tenant
 * Se connecte automatiquement à la base de données du tenant en session
 * OU à la base par défaut si pas de tenant en session (compatibilité arrière)
 */

class Database {
    private $host = "localhost";
    private $db_name = "gestion_comptable"; // Base par défaut (fallback)
    private $username = "root";
    private $password = "";
    private $conn;

    /**
     * Obtenir la connexion à la base de données appropriée
     * - Si un tenant est connecté (session), se connecte à SA base
     * - Sinon, se connecte à la base par défaut (ancien comportement)
     */
    public function getConnection() {
        $this->conn = null;

        // Démarrer la session si pas déjà démarrée
        if (session_status() === PHP_SESSION_NONE) {
            session_name('COMPTAPP_SESSION');
            session_start();
        }

        // Déterminer quelle base de données utiliser
        if (isset($_SESSION['tenant_database'])) {
            // Mode Multi-Tenant : Se connecter à la base du tenant
            $this->db_name = $_SESSION['tenant_database'];
            $this->host = $_SESSION['tenant_db_host'] ?? 'localhost';
            $this->username = $_SESSION['tenant_db_user'] ?? 'root';
            $this->password = $_SESSION['tenant_db_pass'] ?? '';
        }
        // Sinon, utiliser la base par défaut (compatibilité arrière)

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch(PDOException $exception) {
            // Log l'erreur
            error_log("Erreur de connexion à la base de données: " . $exception->getMessage());

            // En mode développement, afficher l'erreur détaillée
            if (ini_get('display_errors')) {
                echo "<div style='padding: 20px; background: #f8d7da; color: #721c24; border-radius: 8px; margin: 20px;'>";
                echo "<h3>❌ Erreur de connexion à la base de données</h3>";
                echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
                echo "<p><strong>Configuration:</strong></p>";
                echo "<ul>";
                echo "<li>Host: " . $this->host . "</li>";
                echo "<li>Database: " . $this->db_name . "</li>";
                echo "<li>Username: " . $this->username . "</li>";
                if (isset($_SESSION['tenant_code'])) {
                    echo "<li><strong>Tenant:</strong> " . $_SESSION['tenant_code'] . "</li>";
                }
                echo "</ul>";
                echo "</div>";
            }

            return null;
        }
        return $this->conn;
    }

    /**
     * Obtenir le nom de la base de données actuelle
     */
    public function getDatabaseName() {
        return $this->db_name;
    }

    /**
     * Vérifier si on est en mode multi-tenant
     */
    public static function isMultiTenantMode() {
        return isset($_SESSION['tenant_database']);
    }

    /**
     * Obtenir les informations du tenant actuel
     */
    public static function getCurrentTenant() {
        if (!self::isMultiTenantMode()) {
            return null;
        }

        return [
            'id' => $_SESSION['tenant_id'] ?? null,
            'code' => $_SESSION['tenant_code'] ?? null,
            'name' => $_SESSION['tenant_name'] ?? null,
            'database' => $_SESSION['tenant_database'] ?? null,
            'plan' => $_SESSION['subscription_plan'] ?? null
        ];
    }
}
?>