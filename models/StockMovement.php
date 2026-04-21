<?php
/**
 * Modèle: StockMovement
 * Description: Gestion des mouvements de stock
 * Version: 1.0
 */

class StockMovement {
    private $conn;
    private $table_name = "stock_movements";

    // Propriétés
    public $id;
    public $company_id;
    public $product_id;
    public $movement_date;
    public $type;
    public $quantity;
    public $unit_cost;
    public $total_cost;
    public $reference_type;
    public $reference_id;
    public $reason;
    public $notes;
    public $created_by;
    public $created_at;

    /**
     * Constructeur
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un mouvement de stock
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    product_id = :product_id,
                    movement_date = :movement_date,
                    type = :type,
                    quantity = :quantity,
                    unit_cost = :unit_cost,
                    total_cost = :total_cost,
                    reference_type = :reference_type,
                    reference_id = :reference_id,
                    reason = :reason,
                    notes = :notes,
                    created_by = :created_by";

        $stmt = $this->conn->prepare($query);

        // Bind
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":product_id", $this->product_id);
        $stmt->bindParam(":movement_date", $this->movement_date);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":quantity", $this->quantity);
        $stmt->bindParam(":unit_cost", $this->unit_cost);
        $stmt->bindParam(":total_cost", $this->total_cost);
        $stmt->bindParam(":reference_type", $this->reference_type);
        $stmt->bindParam(":reference_id", $this->reference_id);
        $stmt->bindParam(":reason", $this->reason);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":created_by", $this->created_by);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Lister mouvements par société
     */
    public function readByCompany($company_id, $filters = []) {
        $query = "SELECT * FROM v_stock_movements_detailed
                WHERE company_id = :company_id";

        // Filtres
        if (!empty($filters['product_id'])) {
            $query .= " AND product_id = :product_id";
        }

        if (!empty($filters['type'])) {
            $query .= " AND type = :type";
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND movement_date >= :date_from";
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND movement_date <= :date_to";
        }

        $query .= " ORDER BY movement_date DESC LIMIT 100";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if (!empty($filters['product_id'])) {
            $stmt->bindParam(":product_id", $filters['product_id']);
        }

        if (!empty($filters['type'])) {
            $stmt->bindParam(":type", $filters['type']);
        }

        if (!empty($filters['date_from'])) {
            $stmt->bindParam(":date_from", $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $stmt->bindParam(":date_to", $filters['date_to']);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lister mouvements par produit
     */
    public function getByProduct($product_id, $limit = 50) {
        $query = "SELECT * FROM v_stock_movements_detailed
                WHERE product_id = :product_id
                ORDER BY movement_date DESC
                LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":product_id", $product_id);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Entrée de stock
     */
    public function stockIn($product_id, $quantity, $unit_cost, $reason, $created_by, $reference_type = null, $reference_id = null) {
        $this->product_id = $product_id;
        $this->type = 'in';
        $this->quantity = $quantity;
        $this->unit_cost = $unit_cost;
        $this->total_cost = $quantity * $unit_cost;
        $this->reason = $reason;
        $this->reference_type = $reference_type;
        $this->reference_id = $reference_id;
        $this->created_by = $created_by;
        $this->movement_date = date('Y-m-d H:i:s');

        return $this->create();
    }

    /**
     * Sortie de stock
     */
    public function stockOut($product_id, $quantity, $unit_cost, $reason, $created_by, $reference_type = null, $reference_id = null) {
        $this->product_id = $product_id;
        $this->type = 'out';
        $this->quantity = $quantity;
        $this->unit_cost = $unit_cost;
        $this->total_cost = $quantity * $unit_cost;
        $this->reason = $reason;
        $this->reference_type = $reference_type;
        $this->reference_id = $reference_id;
        $this->created_by = $created_by;
        $this->movement_date = date('Y-m-d H:i:s');

        return $this->create();
    }

    /**
     * Ajustement de stock (inventaire)
     */
    public function adjust($product_id, $new_quantity, $reason, $created_by) {
        $this->product_id = $product_id;
        $this->type = 'adjustment';
        $this->quantity = $new_quantity;
        $this->unit_cost = 0;
        $this->total_cost = 0;
        $this->reason = $reason;
        $this->created_by = $created_by;
        $this->movement_date = date('Y-m-d H:i:s');

        return $this->create();
    }

    /**
     * Obtenir statistiques des mouvements
     */
    public function getStatistics($company_id, $period_days = 30) {
        $date_from = date('Y-m-d', strtotime("-$period_days days"));

        $query = "SELECT
                    type,
                    COUNT(*) as count,
                    SUM(quantity) as total_quantity,
                    SUM(total_cost) as total_cost
                FROM " . $this->table_name . "
                WHERE company_id = :company_id
                AND movement_date >= :date_from
                GROUP BY type";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":date_from", $date_from);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
