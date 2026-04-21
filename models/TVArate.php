<?php
class TVARate {
    private $conn;
    private $table_name = "tva_rates";

    public $id;
    public $company_id;
    public $rate;
    public $description;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer un nouveau taux de TVA
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (company_id, rate, description) VALUES (:company_id, :rate, :description)";
        $stmt = $this->conn->prepare($query);
        
        $this->company_id = htmlspecialchars(strip_tags($this->company_id));
        $this->rate = htmlspecialchars(strip_tags($this->rate));
        $this->description = htmlspecialchars(strip_tags($this->description));
        
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":rate", $this->rate);
        $stmt->bindParam(":description", $this->description);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Lire tous les taux de TVA d'une société
    public function readByCompany($company_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE company_id = :company_id ORDER BY rate";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();
        return $stmt;
    }

    // Lire un taux de TVA
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->company_id = $row['company_id'];
            $this->rate = $row['rate'];
            $this->description = $row['description'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Mettre à jour un taux de TVA
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET rate = :rate, description = :description WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $this->rate = htmlspecialchars(strip_tags($this->rate));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        $stmt->bindParam(":rate", $this->rate);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":id", $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer un taux de TVA
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        if($stmt->execute()) {
            return true;
        }
        return false;
    }
}
?>