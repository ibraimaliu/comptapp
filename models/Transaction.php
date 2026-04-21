<?php
class Transaction {
    private $conn;
    private $table_name = "transactions";

    public $id;
    public $company_id;
    public $date;
    public $description;
    public $amount;
    public $type;
    public $tva_rate;
    public $account_id;
    public $counterpart_account_id;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer une nouvelle transaction
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET company_id = :company_id,
                      date = :date,
                      description = :description,
                      amount = :amount,
                      type = :type,
                      tva_rate = :tva_rate,
                      account_id = :account_id,
                      counterpart_account_id = :counterpart_account_id";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->company_id = htmlspecialchars(strip_tags($this->company_id));
        $this->date = htmlspecialchars(strip_tags($this->date));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $this->tva_rate = htmlspecialchars(strip_tags($this->tva_rate));
        
        // Gestion de la valeur NULL pour account_id
        if(empty($this->account_id) || $this->account_id === 'none') {
            $this->account_id = null;
        } else {
            $this->account_id = htmlspecialchars(strip_tags($this->account_id));
        }

        // Gestion de la valeur NULL pour counterpart_account_id
        if(empty($this->counterpart_account_id) || $this->counterpart_account_id === 'none') {
            $this->counterpart_account_id = null;
        } else {
            $this->counterpart_account_id = htmlspecialchars(strip_tags($this->counterpart_account_id));
        }

        // Lier les valeurs
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":date", $this->date);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":tva_rate", $this->tva_rate);
        $stmt->bindParam(":account_id", $this->account_id);
        $stmt->bindParam(":counterpart_account_id", $this->counterpart_account_id);

        // Exécuter la requête
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            
            // Si un compte est associé, le marquer comme utilisé
            if($this->account_id) {
                $account = new AccountingPlan($this->conn);
                $account->id = $this->account_id;
                $account->markAsUsed();
            }
            
            return true;
        }
        return false;
    }

    // Lire une transaction
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->company_id = $row['company_id'];
            $this->date = $row['date'];
            $this->description = $row['description'];
            $this->amount = $row['amount'];
            $this->type = $row['type'];
            $this->tva_rate = $row['tva_rate'];
            $this->account_id = $row['account_id'];
            $this->counterpart_account_id = $row['counterpart_account_id'] ?? null;
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Lire toutes les transactions d'une société
    public function readByCompany($company_id) {
        $query = "SELECT t.*, a.number as account_number, a.name as account_name 
                  FROM " . $this->table_name . " t
                  LEFT JOIN accounting_plan a ON t.account_id = a.id
                  WHERE t.company_id = :company_id
                  ORDER BY t.date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt;
    }

    // Lire les 5 transactions les plus récentes
    public function readRecent($company_id, $limit = 5) {
        $query = "SELECT t.*, a.number as account_number, a.name as account_name 
                  FROM " . $this->table_name . " t
                  LEFT JOIN accounting_plan a ON t.account_id = a.id
                  WHERE t.company_id = :company_id
                  ORDER BY t.date DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt;
    }

    // Mettre à jour une transaction
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET date = :date,
                      description = :description,
                      amount = :amount,
                      type = :type,
                      tva_rate = :tva_rate,
                      account_id = :account_id,
                      counterpart_account_id = :counterpart_account_id
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->date = htmlspecialchars(strip_tags($this->date));
        $this->description = htmlspecialchars(strip_tags($this->description));
        $this->amount = htmlspecialchars(strip_tags($this->amount));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $this->tva_rate = htmlspecialchars(strip_tags($this->tva_rate));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Gestion de la valeur NULL pour account_id
        if(empty($this->account_id) || $this->account_id === 'none') {
            $this->account_id = null;
        } else {
            $this->account_id = htmlspecialchars(strip_tags($this->account_id));
        }

        // Gestion de la valeur NULL pour counterpart_account_id
        if(empty($this->counterpart_account_id) || $this->counterpart_account_id === 'none') {
            $this->counterpart_account_id = null;
        } else {
            $this->counterpart_account_id = htmlspecialchars(strip_tags($this->counterpart_account_id));
        }

        // Lier les valeurs
        $stmt->bindParam(":date", $this->date);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":tva_rate", $this->tva_rate);
        $stmt->bindParam(":account_id", $this->account_id);
        $stmt->bindParam(":counterpart_account_id", $this->counterpart_account_id);
        $stmt->bindParam(":id", $this->id);

        // Exécuter la requête
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer une transaction
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Calculer les totaux pour le tableau de bord
    public function calculateDashboardStats($company_id) {
        $stats = [
            'total_income' => 0,
            'total_expenses' => 0,
            'profit' => 0,
            'total_tva' => 0
        ];

        // Revenus totaux
        $query = "SELECT SUM(amount) as total 
                  FROM " . $this->table_name . " 
                  WHERE company_id = :company_id AND type = 'income'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_income'] = $row['total'] ? $row['total'] : 0;

        // Dépenses totales
        $query = "SELECT SUM(amount) as total 
                  FROM " . $this->table_name . " 
                  WHERE company_id = :company_id AND type = 'expense'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_expenses'] = $row['total'] ? $row['total'] : 0;

        // Bénéfice
        $stats['profit'] = $stats['total_income'] - $stats['total_expenses'];

        // TVA à payer
        $query = "SELECT SUM(CASE 
                      WHEN type = 'income' THEN amount * (tva_rate / 100)
                      WHEN type = 'expense' THEN -amount * (tva_rate / 100)
                      ELSE 0
                  END) as total_tva
                  FROM " . $this->table_name . " 
                  WHERE company_id = :company_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stats['total_tva'] = $row['total_tva'] ? $row['total_tva'] : 0;

        return $stats;
    }
    
    // Méthode alias pour getStatistics - pour compatibilité avec home.php
    public function getStatistics($company_id) {
        return $this->calculateDashboardStats($company_id);
    }
    
    // Méthode alias pour getRecentByCompany - pour compatibilité avec home.php
    public function getRecentByCompany($company_id, $limit = 5) {
        return $this->readRecent($company_id, $limit);
    }
}
?>