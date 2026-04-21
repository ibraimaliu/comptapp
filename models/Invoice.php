<?php
class Invoice {
    private $conn;
    private $table_name = "invoices";
    private $items_table = "invoice_items";

    public $id;
    public $company_id;
    public $number;
    public $date;
    public $contact_id;
    public $subtotal;
    public $tva_amount;
    public $total;
    public $status;
    public $created_at;
    public $items = [];

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer une nouvelle facture
    public function create() {
        // Commencer une transaction pour assurer l'intégrité des données
        $this->conn->beginTransaction();

        try {
            // Insérer la facture
            $query = "INSERT INTO " . $this->table_name . "
                      SET company_id = :company_id,
                          number = :number,
                          date = :date,
                          contact_id = :contact_id,
                          subtotal = :subtotal,
                          tva_amount = :tva_amount,
                          total = :total,
                          status = :status";

            $stmt = $this->conn->prepare($query);

            // Nettoyer les données
            $this->company_id = htmlspecialchars(strip_tags($this->company_id));
            $this->number = htmlspecialchars(strip_tags($this->number));
            $this->date = htmlspecialchars(strip_tags($this->date));
            $this->contact_id = htmlspecialchars(strip_tags($this->contact_id));
            $this->subtotal = htmlspecialchars(strip_tags($this->subtotal));
            $this->tva_amount = htmlspecialchars(strip_tags($this->tva_amount));
            $this->total = htmlspecialchars(strip_tags($this->total));
            $this->status = htmlspecialchars(strip_tags($this->status));

            // Lier les valeurs
            $stmt->bindParam(":company_id", $this->company_id);
            $stmt->bindParam(":number", $this->number);
            $stmt->bindParam(":date", $this->date);
            $stmt->bindParam(":contact_id", $this->contact_id);
            $stmt->bindParam(":subtotal", $this->subtotal);
            $stmt->bindParam(":tva_amount", $this->tva_amount);
            $stmt->bindParam(":total", $this->total);
            $stmt->bindParam(":status", $this->status);

            // Exécuter la requête
            $stmt->execute();
            $this->id = $this->conn->lastInsertId();

            // Insérer les articles de la facture
            if(!empty($this->items)) {
                $query = "INSERT INTO " . $this->items_table . " 
                          (invoice_id, description, quantity, price, tva_rate, total, tva_amount) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $this->conn->prepare($query);

                foreach($this->items as $item) {
                    $stmt->execute([
                        $this->id,
                        $item['description'],
                        $item['quantity'],
                        $item['price'],
                        $item['tva_rate'],
                        $item['total'],
                        $item['tva_amount']
                    ]);
                }
            }

            // Valider la transaction
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->conn->rollBack();
            return false;
        }
    }

    // Lire une facture avec ses articles
    public function read() {
        // Lire la facture
        $query = "SELECT i.*, c.name as client_name
                  FROM " . $this->table_name . " i
                  LEFT JOIN contacts c ON i.contact_id = c.id
                  WHERE i.id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->company_id = $row['company_id'];
            $this->number = $row['number'];
            $this->date = $row['date'];
            $this->contact_id = $row['contact_id'];
            $this->client_name = $row['client_name'];
            $this->subtotal = $row['subtotal'];
            $this->tva_amount = $row['tva_amount'];
            $this->total = $row['total'];
            $this->status = $row['status'];
            $this->created_at = $row['created_at'];

            // Lire les articles de la facture
            $query = "SELECT * FROM " . $this->items_table . " WHERE invoice_id = :invoice_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":invoice_id", $this->id);
            $stmt->execute();

            $this->items = [];
            while($item_row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->items[] = $item_row;
            }

            return true;
        }
        return false;
    }

    // Lire toutes les factures d'une société
    public function readByCompany($company_id) {
        $query = "SELECT i.*, c.name as client_name
                  FROM " . $this->table_name . " i
                  LEFT JOIN contacts c ON i.contact_id = c.id
                  WHERE i.company_id = :company_id
                  ORDER BY i.date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt;
    }

    // Mettre à jour une facture
    public function update() {
        // Commencer une transaction
        $this->conn->beginTransaction();

        try {
            // Mettre à jour la facture
            $query = "UPDATE " . $this->table_name . "
                      SET number = :number,
                          date = :date,
                          contact_id = :contact_id,
                          subtotal = :subtotal,
                          tva_amount = :tva_amount,
                          total = :total,
                          status = :status
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            // Nettoyer les données
            $this->number = htmlspecialchars(strip_tags($this->number));
            $this->date = htmlspecialchars(strip_tags($this->date));
            $this->contact_id = htmlspecialchars(strip_tags($this->contact_id));
            $this->subtotal = htmlspecialchars(strip_tags($this->subtotal));
            $this->tva_amount = htmlspecialchars(strip_tags($this->tva_amount));
            $this->total = htmlspecialchars(strip_tags($this->total));
            $this->status = htmlspecialchars(strip_tags($this->status));
            $this->id = htmlspecialchars(strip_tags($this->id));

            // Lier les valeurs
            $stmt->bindParam(":number", $this->number);
            $stmt->bindParam(":date", $this->date);
            $stmt->bindParam(":contact_id", $this->contact_id);
            $stmt->bindParam(":subtotal", $this->subtotal);
            $stmt->bindParam(":tva_amount", $this->tva_amount);
            $stmt->bindParam(":total", $this->total);
            $stmt->bindParam(":status", $this->status);
            $stmt->bindParam(":id", $this->id);

            // Exécuter la requête
            $stmt->execute();

            // Supprimer les articles existants
            $query = "DELETE FROM " . $this->items_table . " WHERE invoice_id = :invoice_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":invoice_id", $this->id);
            $stmt->execute();

            // Insérer les nouveaux articles
            $query = "INSERT INTO " . $this->items_table . " 
                      (invoice_id, description, quantity, price, tva_rate, total, tva_amount) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->conn->prepare($query);

            foreach($this->items as $item) {
                $stmt->execute([
                    $this->id,
                    $item['description'],
                    $item['quantity'],
                    $item['price'],
                    $item['tva_rate'],
                    $item['total'],
                    $item['tva_amount']
                ]);
            }

            // Valider la transaction
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->conn->rollBack();
            return false;
        }
    }

    // Générer un numéro de facture unique
    public function generateNumber($company_id) {
        $year = date('Y');
        $prefix = 'FACT-' . $year . '-';
        
        // Trouver le dernier numéro de facture
        $query = "SELECT number FROM " . $this->table_name . " 
                  WHERE company_id = :company_id AND number LIKE :prefix 
                  ORDER BY number DESC LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $search_prefix = $prefix . '%';
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":prefix", $search_prefix);
        $stmt->execute();
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($row) {
            $last_number = $row['number'];
            $number_part = str_replace($prefix, '', $last_number);
            $next_number = intval($number_part) + 1;
        } else {
            $next_number = 1;
        }
        
        return $prefix . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }

    // Supprimer une facture
    public function delete() {
        // Commencer une transaction
        $this->conn->beginTransaction();

        try {
            // Supprimer les articles de la facture
            $query = "DELETE FROM " . $this->items_table . " WHERE invoice_id = :invoice_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":invoice_id", $this->id);
            $stmt->execute();

            // Supprimer la facture
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();

            // Valider la transaction
            $this->conn->commit();
            return true;
        } catch(Exception $e) {
            // Annuler la transaction en cas d'erreur
            $this->conn->rollBack();
            return false;
        }
    }
}
?>