<?php
class Category {
    private $conn;
    private $table_name = "categories";

    public $id;
    public $company_id;
    public $name;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer une nouvelle catégorie
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " (company_id, name) VALUES (:company_id, :name)";
        $stmt = $this->conn->prepare($query);
        
        $this->company_id = htmlspecialchars(strip_tags($this->company_id));
        $this->name = htmlspecialchars(strip_tags($this->name));
        
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":name", $this->name);
        
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Lire toutes les catégories d'une société
    public function readByCompany($company_id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE company_id = :company_id ORDER BY name";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();
        return $stmt;
    }

    // Lire une catégorie
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->company_id = $row['company_id'];
            $this->name = $row['name'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Mettre à jour une catégorie
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET name = :name WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->id = htmlspecialchars(strip_tags($this->id));
        
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":id", $this->id);
        
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer une catégorie
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