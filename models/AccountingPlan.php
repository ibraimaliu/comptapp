<?php
class AccountingPlan {
    private $conn;
    private $table_name = "accounting_plan";

    public $id;
    public $company_id;
    public $number;
    public $name;
    public $category;
    public $type;
    public $is_used;
    public $is_selectable; // Propriété ajoutée pour éviter l'erreur PHP 8.2+

    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer un nouveau compte
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " 
                  SET company_id = :company_id, 
                      number = :number, 
                      name = :name, 
                      category = :category, 
                      type = :type, 
                      is_used = :is_used";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->company_id = htmlspecialchars(strip_tags($this->company_id));
        $this->number = htmlspecialchars(strip_tags($this->number));
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $this->is_used = $this->is_used ? 1 : 0;

        // Lier les valeurs
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":number", $this->number);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":is_used", $this->is_used);

        // Exécuter la requête
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Lire un compte
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            $this->company_id = $row['company_id'];
            $this->number = $row['number'];
            $this->name = $row['name'];
            $this->category = $row['category'];
            $this->type = $row['type'];
            $this->is_used = $row['is_used'];
            return true;
        }
        return false;
    }

    // Lire tous les comptes d'une société
    public function readByCompany($company_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  ORDER BY number";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt;
    }

    // Lire uniquement les comptes sélectionnables d'une société (pour les transactions)
    public function readSelectableByCompany($company_id) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE company_id = :company_id
                  AND is_selectable = 1
                  ORDER BY number";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt;
    }

    // Mettre à jour un compte
    public function update() {
        $query = "UPDATE " . $this->table_name . " 
                  SET name = :name, 
                      category = :category, 
                      type = :type 
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->name = htmlspecialchars(strip_tags($this->name));
        $this->category = htmlspecialchars(strip_tags($this->category));
        $this->type = htmlspecialchars(strip_tags($this->type));
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Lier les valeurs
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":category", $this->category);
        $stmt->bindParam(":type", $this->type);
        $stmt->bindParam(":id", $this->id);

        // Exécuter la requête
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer un compte
    public function delete() {
        // Vérifier si le compte est utilisé
        if($this->is_used) {
            return false;
        }

        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Marquer un compte comme utilisé
    public function markAsUsed() {
        $query = "UPDATE " . $this->table_name . " SET is_used = 1 WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            $this->is_used = true;
            return true;
        }
        return false;
    }

    // Importer un plan comptable par défaut
    public function importDefaultPlan($company_id) {
        $default_accounts = [
            // ACTIFS (Bilan)
            ['1000', 'Caisse', 'Trésorerie', 'actif'],
            ['1010', 'Poste', 'Trésorerie', 'actif'],
            ['1020', 'Banque', 'Trésorerie', 'actif'],
            ['1060', 'Placements à court terme', 'Placements', 'actif'],
            ['1100', 'Créances clients', 'Créances', 'actif'],
            ['1140', 'Autres créances', 'Créances', 'actif'],
            ['1200', 'Stocks de marchandises', 'Stocks', 'actif'],
            ['1500', 'Mobilier et installations', 'Immobilisations', 'actif'],
            ['1510', 'Machines et équipements', 'Immobilisations', 'actif'],
            ['1520', 'Matériel informatique', 'Immobilisations', 'actif'],

            // PASSIFS (Bilan)
            ['2000', 'Fournisseurs', 'Dettes à court terme', 'passif'],
            ['2030', 'TVA due', 'Dettes à court terme', 'passif'],
            ['2100', 'Dettes bancaires à court terme', 'Dettes à court terme', 'passif'],
            ['2200', 'Emprunts bancaires', 'Dettes à long terme', 'passif'],
            ['2800', 'Capital social', 'Capitaux propres', 'passif'],
            ['2850', 'Réserves', 'Capitaux propres', 'passif'],
            ['2900', 'Bénéfice/Perte de l\'exercice', 'Capitaux propres', 'passif'],

            // CHARGES (Compte de résultat)
            ['3000', 'Achats de marchandises', 'Achats', 'charge'],
            ['4000', 'Salaires', 'Personnel', 'charge'],
            ['4200', 'Charges sociales', 'Personnel', 'charge'],
            ['5000', 'Loyer', 'Locaux', 'charge'],
            ['5700', 'Publicité et marketing', 'Marketing', 'charge'],
            ['6000', 'Fournitures de bureau', 'Administration', 'charge'],
            ['6100', 'Entretien et réparations', 'Administration', 'charge'],
            ['6300', 'Assurances', 'Administration', 'charge'],
            ['6400', 'Énergie et eau', 'Administration', 'charge'],
            ['6500', 'Frais de véhicules', 'Transport', 'charge'],
            ['6700', 'Charges financières', 'Finances', 'charge'],
            ['6800', 'Amortissements', 'Amortissements', 'charge'],
            ['6900', 'Autres charges', 'Divers', 'charge'],

            // PRODUITS (Compte de résultat)
            ['7000', 'Ventes de marchandises', 'Ventes', 'produit'],
            ['7500', 'Prestations de services', 'Services', 'produit'],
            ['7900', 'Autres produits', 'Divers', 'produit'],
            ['8000', 'Produits financiers', 'Finances', 'produit']
        ];

        $placeholders = [];
        $values = [];
        foreach ($default_accounts as $account) {
            $placeholders[] = "(?, ?, ?, ?, ?, 0)";
            $values[] = $company_id;
            $values[] = $account[0];
            $values[] = $account[1];
            $values[] = $account[2];
            $values[] = $account[3];
        }

        $sql = "INSERT IGNORE INTO accounting_plan (company_id, number, name, category, type, is_used) VALUES "
             . implode(', ', $placeholders);
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($values);

        return true;
    }
}
?>