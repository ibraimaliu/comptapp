<?php
class Contact {
    private $conn;
    private $table_name = "contacts"; // Utilise la table contacts

    // Propriétés communes (pour éviter les erreurs d'analyseur)
    public $id;
    public $company_id;
    public $type;
    public $name;
    public $email;
    public $phone;
    public $address;
    public $postal_code;
    public $city;
    public $country;
    public $created_at;
    public $updated_at;

    // Propriétés dynamiques basées sur la structure de la table
    private $column_names = [];
    private $has_company_id = false;
    private $sort_column = 'id'; // Colonne par défaut pour le tri

    public function __construct($db) {
        $this->conn = $db;
        
        // Récupérer la structure de la table
        $this->detectTableStructure();
    }
    
    // Détecter la structure de la table
    private function detectTableStructure() {
        $query = "DESCRIBE " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $this->column_names = [];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $column_name = $row['Field'];
            $this->column_names[] = $column_name;
            
            // Définir dynamiquement la propriété
            $this->{$column_name} = null;
            
            // Vérifier si company_id existe
            if ($column_name === 'company_id') {
                $this->has_company_id = true;
            }
            
            // Déterminer une colonne appropriée pour le tri (préférence : nom, lastname, titre, etc.)
            if (in_array($column_name, ['nom', 'name', 'lastname', 'titre', 'title', 'societe', 'company'])) {
                $this->sort_column = $column_name;
            }
        }
        
        // Log pour le débogage
        error_log("Colonnes détectées dans la table $this->table_name: " . implode(", ", $this->column_names));
        error_log("Colonne de tri: $this->sort_column");
    }

    // Créer un nouveau contact avec un tableau de données
    public function create($data = null) {
        if ($data) {
            // Méthode moderne avec paramètres
            return $this->createWithData($data);
        } else {
            // Méthode legacy avec propriétés de l'objet
            return $this->createFromProperties();
        }
    }

    // Créer un contact avec un tableau de données
    private function createWithData($data) {
        // Construire dynamiquement la requête
        $query = "INSERT INTO " . $this->table_name . " SET ";
        $params = [];
        $bind_params = [];
        
        foreach ($this->column_names as $column) {
            // Exclure l'ID et created_at qui sont généralement auto-générés
            if ($column !== 'id' && $column !== 'created_at') {
                if (isset($data[$column]) || array_key_exists($column, $data)) {
                    $params[] = "$column = :$column";
                    $bind_params[$column] = $data[$column];
                }
            }
        }
        
        if (empty($params)) {
            return false;
        }
        
        $query .= implode(", ", $params);
        
        $stmt = $this->conn->prepare($query);
        
        // Lier les paramètres
        foreach ($bind_params as $column => $value) {
            $clean_value = $value ? htmlspecialchars(strip_tags($value)) : null;
            $stmt->bindValue(":$column", $clean_value);
        }
        
        // Exécuter la requête
        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Créer un contact avec les propriétés de l'objet (méthode legacy)
    private function createFromProperties() {
        // Construire dynamiquement la requête
        $query = "INSERT INTO " . $this->table_name . " SET ";
        $params = [];
        
        foreach ($this->column_names as $column) {
            // Exclure l'ID et created_at qui sont généralement auto-générés
            if ($column !== 'id' && $column !== 'created_at') {
                $params[] = "$column = :$column";
            }
        }
        
        $query .= implode(", ", $params);
        
        $stmt = $this->conn->prepare($query);
        
        // Lier les paramètres
        foreach ($this->column_names as $column) {
            if ($column !== 'id' && $column !== 'created_at') {
                $value = isset($this->{$column}) ? htmlspecialchars(strip_tags($this->{$column})) : null;
                $stmt->bindParam(":$column", $value);
            }
        }
        
        // Exécuter la requête
        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    // Lire un contact
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if($row) {
            // Affecter dynamiquement les propriétés
            foreach ($row as $key => $value) {
                $this->{$key} = $value;
            }
            return true;
        }
        return false;
    }

    // Récupérer un contact par ID
    public function getById($id, $company_id = null) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        
        if($company_id && $this->has_company_id) {
            $query .= " AND company_id = :company_id";
        }
        
        $query .= " LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        
        if($company_id && $this->has_company_id) {
            $stmt->bindParam(':company_id', $company_id);
        }
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Lire tous les contacts (d'une société si company_id existe)
    public function readByCompany($company_id) {
        if ($this->has_company_id) {
            $query = "SELECT * FROM " . $this->table_name . " 
                      WHERE company_id = :company_id 
                      ORDER BY " . $this->sort_column;
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":company_id", $company_id);
        } else {
            $query = "SELECT * FROM " . $this->table_name . " 
                      ORDER BY " . $this->sort_column;
                      
            $stmt = $this->conn->prepare($query);
        }
        
        $stmt->execute();
        return $stmt;
    }

    // Récupérer tous les contacts d'une société (alias pour compatibilité)
    public function getByCompany($company_id) {
        return $this->readByCompany($company_id);
    }

    // Lire tous les clients (d'une société si company_id existe)
    public function readClientsByCompany($company_id) {
        // Vérifier si la colonne type existe
        if (!in_array('type', $this->column_names)) {
            return $this->readByCompany($company_id); // Fallback si la colonne n'existe pas
        }
        
        if ($this->has_company_id) {
            $query = "SELECT * FROM " . $this->table_name . " 
                      WHERE company_id = :company_id AND type = 'client' 
                      ORDER BY " . $this->sort_column;
                      
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":company_id", $company_id);
        } else {
            $query = "SELECT * FROM " . $this->table_name . " 
                      WHERE type = 'client' 
                      ORDER BY " . $this->sort_column;
                      
            $stmt = $this->conn->prepare($query);
        }
        
        $stmt->execute();
        return $stmt;
    }
    
    // Mettre à jour un contact avec un tableau de données
    public function update($data = null) {
        if ($data && isset($data['id'])) {
            // Méthode moderne avec paramètres
            return $this->updateWithData($data);
        } else {
            // Méthode legacy avec propriétés de l'objet
            return $this->updateFromProperties();
        }
    }

    // Mettre à jour un contact avec un tableau de données
    private function updateWithData($data) {
        // Construire dynamiquement la requête
        $query = "UPDATE " . $this->table_name . " SET ";
        $params = [];
        $bind_params = [];
        
        foreach ($this->column_names as $column) {
            // Exclure l'ID et created_at
            if ($column !== 'id' && $column !== 'created_at') {
                if (isset($data[$column]) || array_key_exists($column, $data)) {
                    $params[] = "$column = :$column";
                    $bind_params[$column] = $data[$column];
                }
            }
        }
        
        if (empty($params)) {
            return false;
        }
        
        $query .= implode(", ", $params) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Lier l'ID
        $stmt->bindValue(":id", $data['id']);
        
        // Lier les autres paramètres
        foreach ($bind_params as $column => $value) {
            $clean_value = $value ? htmlspecialchars(strip_tags($value)) : null;
            $stmt->bindValue(":$column", $clean_value);
        }
        
        // Exécuter la requête
        return $stmt->execute();
    }

    // Mettre à jour un contact avec les propriétés de l'objet (méthode legacy)
    private function updateFromProperties() {
        // Construire dynamiquement la requête
        $query = "UPDATE " . $this->table_name . " SET ";
        $params = [];
        
        foreach ($this->column_names as $column) {
            // Exclure l'ID et created_at
            if ($column !== 'id' && $column !== 'created_at') {
                $params[] = "$column = :$column";
            }
        }
        
        $query .= implode(", ", $params) . " WHERE id = :id";
        
        $stmt = $this->conn->prepare($query);
        
        // Lier les paramètres
        foreach ($this->column_names as $column) {
            if ($column === 'id') {
                $value = htmlspecialchars(strip_tags($this->id));
                $stmt->bindParam(":$column", $value);
            } else if ($column !== 'created_at') {
                $value = isset($this->{$column}) ? htmlspecialchars(strip_tags($this->{$column})) : null;
                $stmt->bindParam(":$column", $value);
            }
        }
        
        // Exécuter la requête
        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer un contact
    public function delete($id = null) {
        if ($id) {
            // Méthode moderne avec paramètre
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $id);
        } else {
            // Méthode legacy avec propriété de l'objet
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);
        }

        return $stmt->execute();
    }

    // Vérifier qu'un contact appartient à une société
    public function belongsToCompany($contact_id, $company_id) {
        if (!$this->has_company_id) {
            return true; // Si pas de company_id dans la table, on considère que ça appartient
        }
        
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE id = :id AND company_id = :company_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $contact_id);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    // Trouver un contact par nom et email
    public function findByNameAndEmail($name, $email, $company_id) {
        // Déterminer les colonnes à utiliser pour le nom et l'email
        $name_column = null;
        $email_column = null;
        
        // Trouver la colonne pour le nom
        $name_columns = ['name', 'nom', 'lastname', 'titre', 'title'];
        foreach ($name_columns as $col) {
            if (in_array($col, $this->column_names)) {
                $name_column = $col;
                break;
            }
        }
        
        // Trouver la colonne pour l'email
        $email_columns = ['email', 'mail', 'courriel'];
        foreach ($email_columns as $col) {
            if (in_array($col, $this->column_names)) {
                $email_column = $col;
                break;
            }
        }
        
        if (!$name_column || !$email_column) {
            return null; // Impossible de faire la recherche
        }
        
        $query = "SELECT * FROM " . $this->table_name . " WHERE ";
        
        if ($this->has_company_id) {
            $query .= "company_id = :company_id AND ";
        }
        
        $query .= "$name_column = :name AND $email_column = :email LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        
        if ($this->has_company_id) {
            $stmt->bindParam(':company_id', $company_id);
        }
        
        $stmt->bindParam(':name', $name);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Rechercher des contacts avec filtre
    public function searchWithFilter($company_id, $keywords = '', $filter_condition = '') {
        if ($this->has_company_id) {
            $query = "SELECT * FROM " . $this->table_name . " WHERE company_id = :company_id";
        } else {
            $query = "SELECT * FROM " . $this->table_name . " WHERE 1=1";
        }
        
        if (!empty($keywords)) {
            $search_columns = [];
            
            // Identifier les colonnes textuelles pour la recherche
            $text_columns = array_intersect($this->column_names, [
                'nom', 'name', 'prenom', 'firstname', 'email', 'mail',
                'telephone', 'phone', 'adresse', 'address', 'ville', 'city',
                'societe', 'entreprise', 'raison_sociale', 'title', 'titre'
            ]);
            
            if (count($text_columns) > 0) {
                $search_conditions = [];
                foreach ($text_columns as $column) {
                    $search_conditions[] = "$column LIKE :keywords";
                }
                $query .= " AND (" . implode(" OR ", $search_conditions) . ")";
            }
        }
        
        if (!empty($filter_condition) && in_array('type', $this->column_names)) {
            $query .= $filter_condition;
        }
        
        $query .= " ORDER BY " . $this->sort_column;
        
        $stmt = $this->conn->prepare($query);
        
        if ($this->has_company_id) {
            $stmt->bindParam(":company_id", $company_id);
        }
        
        if (!empty($keywords) && isset($text_columns) && count($text_columns) > 0) {
            $keywords_param = "%" . $keywords . "%";
            $stmt->bindParam(":keywords", $keywords_param);
        }
        
        $stmt->execute();
        return $stmt;
    }
    
    // Compter le nombre total de contacts (d'une société si company_id existe)
    public function countByCompany($company_id) {
        if ($this->has_company_id) {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE company_id = :company_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":company_id", $company_id);
        } else {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $stmt = $this->conn->prepare($query);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }
    
    // Compter le nombre de contacts par type
    public function countByType($company_id, $type) {
        // Vérifier si la colonne type existe
        if (!in_array('type', $this->column_names)) {
            return 0; // Retourner 0 si la colonne n'existe pas
        }
        
        if ($this->has_company_id) {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE company_id = :company_id AND type = :type";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":company_id", $company_id);
            $stmt->bindParam(":type", $type);
        } else {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE type = :type";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":type", $type);
        }
        
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['total'];
    }

    // Obtenir les noms des colonnes de la table
    public function getColumnNames() {
        return $this->column_names;
    }

    // Vérifier si une colonne existe
    public function hasColumn($column_name) {
        return in_array($column_name, $this->column_names);
    }

    // Obtenir la colonne de tri utilisée
    public function getSortColumn() {
        return $this->sort_column;
    }

    // Définir une colonne de tri personnalisée
    public function setSortColumn($column_name) {
        if (in_array($column_name, $this->column_names)) {
            $this->sort_column = $column_name;
            return true;
        }
        return false;
    }

    // Obtenir le nom de la table
    public function getTableName() {
        return $this->table_name;
    }

    // Vérifier si la table a une colonne company_id
    public function hasCompanyId() {
        return $this->has_company_id;
    }
}
?>