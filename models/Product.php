<?php
/**
 * Modèle: Product
 * Description: Gestion des produits et services
 * Version: 1.0
 */

class Product {
    private $conn;
    private $table_name = "products";

    // Propriétés
    public $id;
    public $company_id;
    public $code;
    public $name;
    public $description;
    public $type;
    public $category_id;

    // Prix
    public $purchase_price;
    public $selling_price;
    public $tva_rate;
    public $currency;

    // Stock
    public $stock_quantity;
    public $stock_min;
    public $stock_max;
    public $unit;

    // Options
    public $is_active;
    public $is_sellable;
    public $is_purchasable;
    public $track_stock;

    // Informations complémentaires
    public $supplier_id;
    public $barcode;
    public $image_path;
    public $notes;

    public $created_at;
    public $updated_at;

    /**
     * Constructeur
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un produit
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    code = :code,
                    name = :name,
                    description = :description,
                    type = :type,
                    category_id = :category_id,
                    purchase_price = :purchase_price,
                    selling_price = :selling_price,
                    tva_rate = :tva_rate,
                    currency = :currency,
                    stock_quantity = :stock_quantity,
                    stock_min = :stock_min,
                    stock_max = :stock_max,
                    unit = :unit,
                    is_active = :is_active,
                    is_sellable = :is_sellable,
                    is_purchasable = :is_purchasable,
                    track_stock = :track_stock,
                    supplier_id = :supplier_id,
                    barcode = :barcode,
                    notes = :notes";

        $stmt = $this->conn->prepare($query);

        // Nettoyage
        $this->code = htmlspecialchars(strip_tags($this->code));
        $this->name = htmlspecialchars(strip_tags($this->name));

        // Bind
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":code", $this->code);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":purchase_price", $this->purchase_price);
        $stmt->bindParam(":selling_price", $this->selling_price);
        $stmt->bindParam(":tva_rate", $this->tva_rate);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":stock_quantity", $this->stock_quantity);
        $stmt->bindParam(":stock_min", $this->stock_min);
        $stmt->bindParam(":stock_max", $this->stock_max);
        $stmt->bindParam(":unit", $this->unit);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":is_sellable", $this->is_sellable);
        $stmt->bindParam(":is_purchasable", $this->is_purchasable);
        $stmt->bindParam(":track_stock", $this->track_stock);
        $stmt->bindParam(":supplier_id", $this->supplier_id);
        $stmt->bindParam(":barcode", $this->barcode);
        $stmt->bindParam(":notes", $this->notes);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Lire un produit
     */
    public function read() {
        $query = "SELECT p.*, c.name as category_name, s.name as supplier_name
                FROM " . $this->table_name . " p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN contacts s ON p.supplier_id = s.id
                WHERE p.id = :id AND p.company_id = :company_id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->code = $row['code'];
            $this->name = $row['name'];
            $this->description = $row['description'];
            $this->type = $row['type'];
            $this->category_id = $row['category_id'];
            $this->purchase_price = $row['purchase_price'];
            $this->selling_price = $row['selling_price'];
            $this->tva_rate = $row['tva_rate'];
            $this->currency = $row['currency'];
            $this->stock_quantity = $row['stock_quantity'];
            $this->stock_min = $row['stock_min'];
            $this->stock_max = $row['stock_max'];
            $this->unit = $row['unit'];
            $this->is_active = $row['is_active'];
            $this->is_sellable = $row['is_sellable'];
            $this->is_purchasable = $row['is_purchasable'];
            $this->track_stock = $row['track_stock'];
            $this->supplier_id = $row['supplier_id'];
            $this->barcode = $row['barcode'];
            $this->image_path = $row['image_path'];
            $this->notes = $row['notes'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];

            return true;
        }

        return false;
    }

    /**
     * Mettre à jour un produit
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET code = :code,
                    name = :name,
                    description = :description,
                    type = :type,
                    category_id = :category_id,
                    purchase_price = :purchase_price,
                    selling_price = :selling_price,
                    tva_rate = :tva_rate,
                    stock_min = :stock_min,
                    stock_max = :stock_max,
                    unit = :unit,
                    is_active = :is_active,
                    is_sellable = :is_sellable,
                    is_purchasable = :is_purchasable,
                    track_stock = :track_stock,
                    supplier_id = :supplier_id,
                    barcode = :barcode,
                    notes = :notes
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":code", $this->code);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":description", $this->description);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":category_id", $this->category_id);
        $stmt->bindParam(":purchase_price", $this->purchase_price);
        $stmt->bindParam(":selling_price", $this->selling_price);
        $stmt->bindParam(":tva_rate", $this->tva_rate);
        $stmt->bindParam(":stock_min", $this->stock_min);
        $stmt->bindParam(":stock_max", $this->stock_max);
        $stmt->bindParam(":unit", $this->unit);
        $stmt->bindParam(":is_active", $this->is_active);
        $stmt->bindParam(":is_sellable", $this->is_sellable);
        $stmt->bindParam(":is_purchasable", $this->is_purchasable);
        $stmt->bindParam(":track_stock", $this->track_stock);
        $stmt->bindParam(":supplier_id", $this->supplier_id);
        $stmt->bindParam(":barcode", $this->barcode);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Supprimer un produit
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . "
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Lister tous les produits d'une société
     */
    public function readByCompany($company_id, $filters = []) {
        $query = "SELECT p.*, c.name as category_name, s.name as supplier_name
                FROM " . $this->table_name . " p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN contacts s ON p.supplier_id = s.id
                WHERE p.company_id = :company_id";

        // Filtres
        if (!empty($filters['type'])) {
            $query .= " AND p.type = :type";
        }

        if (!empty($filters['category_id'])) {
            $query .= " AND p.category_id = :category_id";
        }

        if (!empty($filters['is_active'])) {
            $query .= " AND p.is_active = :is_active";
        }

        if (!empty($filters['search'])) {
            $query .= " AND (p.code LIKE :search OR p.name LIKE :search OR p.barcode LIKE :search)";
        }

        if (isset($filters['low_stock']) && $filters['low_stock'] == true) {
            $query .= " AND p.track_stock = 1 AND p.stock_quantity <= p.stock_min";
        }

        $query .= " ORDER BY p.name ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if (!empty($filters['type'])) {
            $stmt->bindParam(":type", $filters['type']);
        }

        if (!empty($filters['category_id'])) {
            $stmt->bindParam(":category_id", $filters['category_id']);
        }

        if (!empty($filters['is_active'])) {
            $stmt->bindParam(":is_active", $filters['is_active']);
        }

        if (!empty($filters['search'])) {
            $search_term = '%' . $filters['search'] . '%';
            $stmt->bindParam(":search", $search_term);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Recherche par code ou code-barres
     */
    public function searchByCode($company_id, $code) {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE company_id = :company_id
                AND (code = :code OR barcode = :code)
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":code", $code);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir produits en stock faible
     */
    public function getLowStockProducts($company_id) {
        $query = "SELECT * FROM v_low_stock_products
                WHERE company_id = :company_id
                ORDER BY stock_quantity ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir valeur totale du stock
     */
    public function getStockValue($company_id) {
        $query = "SELECT
                    COUNT(*) as product_count,
                    SUM(stock_value_cost) as total_cost_value,
                    SUM(stock_value_selling) as total_selling_value
                FROM v_stock_value
                WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir statistiques produits
     */
    public function getStatistics($company_id) {
        $query = "SELECT
                    COUNT(*) as total_products,
                    SUM(CASE WHEN type = 'product' THEN 1 ELSE 0 END) as product_count,
                    SUM(CASE WHEN type = 'service' THEN 1 ELSE 0 END) as service_count,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count,
                    SUM(CASE WHEN track_stock = 1 AND stock_quantity <= stock_min THEN 1 ELSE 0 END) as low_stock_count,
                    SUM(CASE WHEN track_stock = 1 AND stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock_count
                FROM " . $this->table_name . "
                WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Générer un code produit unique
     */
    public function generateCode($company_id, $prefix = 'PROD') {
        $query = "SELECT code FROM " . $this->table_name . "
                WHERE company_id = :company_id AND code LIKE :pattern
                ORDER BY code DESC LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $pattern = $prefix . '-%';
        $stmt->bindParam(":pattern", $pattern);
        $stmt->execute();

        $last = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($last) {
            preg_match('/(\d+)$/', $last['code'], $matches);
            $next_num = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        } else {
            $next_num = 1;
        }

        return $prefix . '-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    }
}
?>
