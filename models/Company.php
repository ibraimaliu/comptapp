<?php
class Company {
    // Propriétés de la base de données
    private $conn;
    private $table_name = "companies";

    // Propriétés de l'objet
    public $id;
    public $user_id;
    public $name;
    public $owner_name;
    public $owner_surname;

    // Coordonnées
    public $address;
    public $postal_code;
    public $city;
    public $country;
    public $phone;
    public $email;
    public $website;

    // Informations légales
    public $ide_number;
    public $tva_number;
    public $rc_number;

    // Informations bancaires
    public $bank_name;
    public $iban;
    public $bic;

    // Configuration
    public $fiscal_year_start;
    public $fiscal_year_end;
    public $tva_status;
    public $created_at;

    // Constructeur
    public function __construct($db) {
        $this->conn = $db;
    }

    // Créer une nouvelle société
    public function create() {
        // Requête d'insertion
        $query = "INSERT INTO " . $this->table_name . "
                  SET user_id = :user_id,
                      name = :name,
                      owner_name = :owner_name,
                      owner_surname = :owner_surname,
                      address = :address,
                      postal_code = :postal_code,
                      city = :city,
                      country = :country,
                      phone = :phone,
                      email = :email,
                      website = :website,
                      ide_number = :ide_number,
                      tva_number = :tva_number,
                      rc_number = :rc_number,
                      bank_name = :bank_name,
                      iban = :iban,
                      bic = :bic,
                      fiscal_year_start = :fiscal_year_start,
                      fiscal_year_end = :fiscal_year_end,
                      tva_status = :tva_status";

        // Préparer la requête
        $stmt = $this->conn->prepare($query);

        // Nettoyer et sécuriser les données
        $this->user_id = htmlspecialchars(strip_tags($this->user_id ?? ''));
        $this->name = htmlspecialchars(strip_tags($this->name ?? ''));
        $this->owner_name = htmlspecialchars(strip_tags($this->owner_name ?? ''));
        $this->owner_surname = htmlspecialchars(strip_tags($this->owner_surname ?? ''));
        $this->address = htmlspecialchars(strip_tags($this->address ?? ''));
        $this->postal_code = htmlspecialchars(strip_tags($this->postal_code ?? ''));
        $this->city = htmlspecialchars(strip_tags($this->city ?? ''));
        $this->country = htmlspecialchars(strip_tags($this->country ?? 'Suisse'));
        $this->phone = htmlspecialchars(strip_tags($this->phone ?? ''));
        $this->email = htmlspecialchars(strip_tags($this->email ?? ''));
        $this->website = htmlspecialchars(strip_tags($this->website ?? ''));
        $this->ide_number = htmlspecialchars(strip_tags($this->ide_number ?? ''));
        $this->tva_number = htmlspecialchars(strip_tags($this->tva_number ?? ''));
        $this->rc_number = htmlspecialchars(strip_tags($this->rc_number ?? ''));
        $this->bank_name = htmlspecialchars(strip_tags($this->bank_name ?? ''));
        $this->iban = htmlspecialchars(strip_tags($this->iban ?? ''));
        $this->bic = htmlspecialchars(strip_tags($this->bic ?? ''));
        $this->fiscal_year_start = htmlspecialchars(strip_tags($this->fiscal_year_start ?? ''));
        $this->fiscal_year_end = htmlspecialchars(strip_tags($this->fiscal_year_end ?? ''));
        $this->tva_status = htmlspecialchars(strip_tags($this->tva_status ?? 'non'));

        // Lier les valeurs
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":owner_name", $this->owner_name);
        $stmt->bindParam(":owner_surname", $this->owner_surname);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":postal_code", $this->postal_code);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":country", $this->country);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":website", $this->website);
        $stmt->bindParam(":ide_number", $this->ide_number);
        $stmt->bindParam(":tva_number", $this->tva_number);
        $stmt->bindParam(":rc_number", $this->rc_number);
        $stmt->bindParam(":bank_name", $this->bank_name);
        $stmt->bindParam(":iban", $this->iban);
        $stmt->bindParam(":bic", $this->bic);
        $stmt->bindParam(":fiscal_year_start", $this->fiscal_year_start);
        $stmt->bindParam(":fiscal_year_end", $this->fiscal_year_end);
        $stmt->bindParam(":tva_status", $this->tva_status);

        // Exécuter la requête
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    // Lire les détails d'une société
    public function read() {
        // Requête de lecture
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Lier l'ID
        $stmt->bindParam(":id", $this->id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer la ligne
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Si une société est trouvée
        if($row) {
            // Définir les valeurs des propriétés
            $this->user_id = $row['user_id'];
            $this->name = $row['name'];
            $this->owner_name = $row['owner_name'];
            $this->owner_surname = $row['owner_surname'];
            $this->address = $row['address'] ?? '';
            $this->postal_code = $row['postal_code'] ?? '';
            $this->city = $row['city'] ?? '';
            $this->country = $row['country'] ?? 'Suisse';
            $this->phone = $row['phone'] ?? '';
            $this->email = $row['email'] ?? '';
            $this->website = $row['website'] ?? '';
            $this->ide_number = $row['ide_number'] ?? '';
            $this->tva_number = $row['tva_number'] ?? '';
            $this->rc_number = $row['rc_number'] ?? '';
            $this->bank_name = $row['bank_name'] ?? '';
            $this->iban = $row['iban'] ?? '';
            $this->bic = $row['bic'] ?? '';
            $this->fiscal_year_start = $row['fiscal_year_start'];
            $this->fiscal_year_end = $row['fiscal_year_end'];
            $this->tva_status = $row['tva_status'];
            $this->created_at = $row['created_at'];

            return true;
        }
        
        return false;
    }

    // Lire toutes les sociétés d'un utilisateur
    public function readByUser($user_id) {
        // Requête pour lire toutes les sociétés d'un utilisateur
        $query = "SELECT * FROM " . $this->table_name . " WHERE user_id = :user_id ORDER BY name";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Lier l'ID utilisateur
        $stmt->bindParam(":user_id", $user_id);
        
        // Exécuter la requête
        $stmt->execute();
        
        return $stmt;
    }

    // Vérifier si un utilisateur a accès à une société
    public function userHasAccess($user_id, $company_id) {
        // Requête pour vérifier l'accès
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " 
                  WHERE id = :company_id AND user_id = :user_id";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Lier les paramètres
        $stmt->bindParam(":company_id", $company_id);
        $stmt->bindParam(":user_id", $user_id);
        
        // Exécuter la requête
        $stmt->execute();
        
        // Récupérer le résultat
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Renvoyer true si l'utilisateur a accès, false sinon
        return $row['count'] > 0;
    }

    // Mettre à jour une société
    public function update() {
        // Requête de mise à jour
        $query = "UPDATE " . $this->table_name . "
                  SET name = :name,
                      owner_name = :owner_name,
                      owner_surname = :owner_surname,
                      address = :address,
                      postal_code = :postal_code,
                      city = :city,
                      country = :country,
                      phone = :phone,
                      email = :email,
                      website = :website,
                      ide_number = :ide_number,
                      tva_number = :tva_number,
                      rc_number = :rc_number,
                      bank_name = :bank_name,
                      iban = :iban,
                      bic = :bic,
                      fiscal_year_start = :fiscal_year_start,
                      fiscal_year_end = :fiscal_year_end,
                      tva_status = :tva_status
                  WHERE id = :id";

        // Préparer la requête
        $stmt = $this->conn->prepare($query);

        // Nettoyer et sécuriser les données
        $this->name = htmlspecialchars(strip_tags($this->name ?? ''));
        $this->owner_name = htmlspecialchars(strip_tags($this->owner_name ?? ''));
        $this->owner_surname = htmlspecialchars(strip_tags($this->owner_surname ?? ''));
        $this->address = htmlspecialchars(strip_tags($this->address ?? ''));
        $this->postal_code = htmlspecialchars(strip_tags($this->postal_code ?? ''));
        $this->city = htmlspecialchars(strip_tags($this->city ?? ''));
        $this->country = htmlspecialchars(strip_tags($this->country ?? 'Suisse'));
        $this->phone = htmlspecialchars(strip_tags($this->phone ?? ''));
        $this->email = htmlspecialchars(strip_tags($this->email ?? ''));
        $this->website = htmlspecialchars(strip_tags($this->website ?? ''));
        $this->ide_number = htmlspecialchars(strip_tags($this->ide_number ?? ''));
        $this->tva_number = htmlspecialchars(strip_tags($this->tva_number ?? ''));
        $this->rc_number = htmlspecialchars(strip_tags($this->rc_number ?? ''));
        $this->bank_name = htmlspecialchars(strip_tags($this->bank_name ?? ''));
        $this->iban = htmlspecialchars(strip_tags($this->iban ?? ''));
        $this->bic = htmlspecialchars(strip_tags($this->bic ?? ''));
        $this->fiscal_year_start = htmlspecialchars(strip_tags($this->fiscal_year_start ?? ''));
        $this->fiscal_year_end = htmlspecialchars(strip_tags($this->fiscal_year_end ?? ''));
        $this->tva_status = htmlspecialchars(strip_tags($this->tva_status ?? 'non'));
        $this->id = htmlspecialchars(strip_tags($this->id ?? ''));

        // Lier les valeurs
        $stmt->bindParam(":name", $this->name);
        $stmt->bindParam(":owner_name", $this->owner_name);
        $stmt->bindParam(":owner_surname", $this->owner_surname);
        $stmt->bindParam(":address", $this->address);
        $stmt->bindParam(":postal_code", $this->postal_code);
        $stmt->bindParam(":city", $this->city);
        $stmt->bindParam(":country", $this->country);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":website", $this->website);
        $stmt->bindParam(":ide_number", $this->ide_number);
        $stmt->bindParam(":tva_number", $this->tva_number);
        $stmt->bindParam(":rc_number", $this->rc_number);
        $stmt->bindParam(":bank_name", $this->bank_name);
        $stmt->bindParam(":iban", $this->iban);
        $stmt->bindParam(":bic", $this->bic);
        $stmt->bindParam(":fiscal_year_start", $this->fiscal_year_start);
        $stmt->bindParam(":fiscal_year_end", $this->fiscal_year_end);
        $stmt->bindParam(":tva_status", $this->tva_status);
        $stmt->bindParam(":id", $this->id);

        // Exécuter la requête
        if($stmt->execute()) {
            return true;
        }

        return false;
    }

    // Supprimer une société
    public function delete() {
        // Requête de suppression
        $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
        
        // Préparer la requête
        $stmt = $this->conn->prepare($query);
        
        // Lier l'ID
        $stmt->bindParam(":id", $this->id);
        
        // Exécuter la requête
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
}
?>