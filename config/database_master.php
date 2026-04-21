<?php
/**
 * Connexion à la Base de Données MASTER
 * Utilisée pour gérer les tenants (clients)
 */

class DatabaseMaster {
    private $host = "localhost";
    private $db_name = "gestion_comptable_master";
    private $username = "root";
    private $password = "";
    public $conn;

    /**
     * Obtenir la connexion à la base master
     */
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );
        } catch(PDOException $exception) {
            echo "Erreur de connexion à la base master: " . $exception->getMessage();
        }

        return $this->conn;
    }
}
?>
