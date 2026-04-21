<?php
// controllers/ContactsController.php - Version corrigée

class ContactsController {
    private $contact;
    private $db;
    
    public function __construct() {
        // Initialiser la base de données
        $database = new Database();
        $this->db = $database->getConnection();
        $this->contact = new Contact($this->db);
        
        // Vérifier si une société est sélectionnée
        if (!isset($_SESSION['company_id'])) {
            $_SESSION['error_message'] = 'Veuillez sélectionner une société';
            $this->redirect('index.php?controller=home&action=index');
        }
    }
    
    // Afficher la liste des contacts
    public function index() {
        $company_id = $_SESSION['company_id'];
        
        // Déterminer l'onglet actif (par défaut: tous)
        $active_tab = isset($_GET['filter']) ? $_GET['filter'] : 'tous';
        
        // Debug : vérifier si la table existe et a des données
        $this->debugTable($company_id);
        
        // Récupérer les contacts selon le filtre actif
        $filter_condition = '';
        switch($active_tab) {
            case 'clients':
                $filter_condition = " AND type = 'client'";
                break;
            case 'fournisseurs':
                $filter_condition = " AND type = 'fournisseur'";
                break;
            case 'autres':
                $filter_condition = " AND type = 'autre'";
                break;
            default: // 'tous'
                $filter_condition = '';
                break;
        }
        
        // Effectuer la recherche si des mots-clés sont fournis
        $search_keywords = isset($_GET['search']) ? $_GET['search'] : '';
        
        try {
            $contacts_stmt = $this->contact->searchWithFilter($company_id, $search_keywords, $filter_condition);
            $contacts = [];
            while($row = $contacts_stmt->fetch(PDO::FETCH_ASSOC)) {
                $contacts[] = $row;
            }
            
            // Compter les différents types pour les badges
            $counts = [
                'tous' => $this->contact->countByCompany($company_id),
                'clients' => $this->contact->countByType($company_id, 'client'),
                'fournisseurs' => $this->contact->countByType($company_id, 'fournisseur'),
                'autres' => $this->contact->countByType($company_id, 'autre')
            ];
            
        } catch (Exception $e) {
            // En cas d'erreur, essayer avec la table adresses
            $contacts = $this->fallbackToAdresses($company_id, $search_keywords);
            $counts = ['tous' => count($contacts), 'clients' => 0, 'fournisseurs' => 0, 'autres' => 0];
            $_SESSION['info_message'] = 'Affichage depuis la table adresses';
        }
        
        // Variables pour la vue
        $data = [
            'contacts' => $contacts,
            'active_tab' => $active_tab,
            'search_keywords' => $search_keywords,
            'counts' => $counts,
            'company_id' => $company_id
        ];
        
        $this->loadView('contacts/index', $data);
    }
    
    // Méthode de fallback pour utiliser la table adresses
    private function fallbackToAdresses($company_id, $search_keywords = '') {
        try {
            $sql = "SELECT * FROM adresses WHERE 1=1";
            $params = [];
            
            // Ajouter company_id si la colonne existe
            if ($this->columnExists('adresses', 'company_id')) {
                $sql .= " AND company_id = ?";
                $params[] = $company_id;
            }
            
            // Ajouter recherche
            if (!empty($search_keywords)) {
                $sql .= " AND (nom LIKE ? OR raison_sociale LIKE ? OR email LIKE ?)";
                $searchTerm = "%$search_keywords%";
                $params[] = $searchTerm;
                $params[] = $searchTerm;
                $params[] = $searchTerm;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $results = [];
            while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Standardiser les noms de colonnes
                $standardized = [
                    'id' => $row['id'],
                    'name' => $row['nom'] ?? $row['raison_sociale'] ?? $row['name'] ?? '',
                    'email' => $row['email'] ?? $row['mail'] ?? '',
                    'phone' => $row['telephone'] ?? $row['phone'] ?? '',
                    'type' => $row['type'] ?? 'autre',
                    'address' => $row['adresse'] ?? $row['address'] ?? '',
                    'city' => $row['ville'] ?? $row['city'] ?? '',
                    'postal_code' => $row['code_postal'] ?? $row['postal_code'] ?? ''
                ];
                $results[] = $standardized;
            }
            
            return $results;
            
        } catch (Exception $e) {
            error_log("Erreur fallback adresses: " . $e->getMessage());
            return [];
        }
    }
    
    // Vérifier si une colonne existe dans une table
    private function columnExists($table, $column) {
        try {
            $stmt = $this->db->prepare("SHOW COLUMNS FROM $table LIKE ?");
            $stmt->execute([$column]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    // Debug pour diagnostiquer les problèmes
    private function debugTable($company_id) {
        if (isset($_GET['debug'])) {
            echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px; border: 1px solid #ccc;'>";
            echo "<h3>Debug Informations</h3>";
            echo "Company ID: $company_id<br>";
            
            // Vérifier table contacts
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM contacts WHERE company_id = ?");
                $stmt->execute([$company_id]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "Contacts trouvés: " . $count['count'] . "<br>";
            } catch (Exception $e) {
                echo "Erreur table contacts: " . $e->getMessage() . "<br>";
            }
            
            // Vérifier table adresses
            try {
                $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM adresses WHERE company_id = ?");
                $stmt->execute([$company_id]);
                $count = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "Adresses trouvées: " . $count['count'] . "<br>";
            } catch (Exception $e) {
                echo "Erreur table adresses: " . $e->getMessage() . "<br>";
            }
            
            echo "</div>";
        }
    }
    
    // Afficher le formulaire de création
    public function create() {
        $data = [
            'title' => 'Nouveau Contact',
            'action' => 'create',
            'contact' => null,
            'success_message' => '',
            'error_message' => ''
        ];
        
        $this->loadView('contacts/form', $data);
    }
    
    // Traiter la création d'un contact - VERSION AMÉLIORÉE
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] != 'POST') {
            $this->redirect('index.php?controller=contacts&action=create');
        }
        
        $company_id = $_SESSION['company_id'];
        $success_message = '';
        $error_message = '';
        
        try {
            // Validation des champs obligatoires
            if (empty($_POST['name'])) {
                throw new Exception('Le nom est obligatoire');
            }
            
            // Déterminer quelle table utiliser
            $useAdressesTable = false;
            try {
                // Tester si la méthode create de Contact fonctionne
                $this->contact->hasColumn('name');
            } catch (Exception $e) {
                $useAdressesTable = true;
            }
            
            if ($useAdressesTable) {
                // Utiliser directement la table adresses
                $this->createInAdressesTable($company_id);
            } else {
                // Utiliser la classe Contact existante
                $this->createWithContactClass($company_id);
            }
            
            $_SESSION['success_message'] = 'Contact créé avec succès !';
            $this->redirect('index.php?controller=contacts&action=index');
            
        } catch (Exception $e) {
            $error_message = $e->getMessage();
            error_log("Erreur création contact: " . $e->getMessage());
        }
        
        // Si erreur, retourner au formulaire avec les données
        $data = [
            'title' => 'Nouveau Contact',
            'action' => 'create',
            'contact' => $_POST,
            'success_message' => $success_message,
            'error_message' => $error_message
        ];
        
        $this->loadView('contacts/form', $data);
    }
    
    // Création dans la table adresses
    private function createInAdressesTable($company_id) {
        $sql = "INSERT INTO adresses (company_id, nom, email, telephone, adresse, ville, code_postal, type) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $params = [
            $company_id,
            $_POST['name'] ?? '',
            $_POST['email'] ?? '',
            $_POST['phone'] ?? '',
            $_POST['address'] ?? '',
            $_POST['city'] ?? '',
            $_POST['postal_code'] ?? '',
            $_POST['type'] ?? 'autre'
        ];
        
        $stmt = $this->db->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception('Erreur lors de la création dans la table adresses');
        }
    }
    
    // Création avec la classe Contact
    private function createWithContactClass($company_id) {
        $data = [];
        
        // Ajouter company_id si la table l'a
        if($this->contact->hasCompanyId()) {
            $data['company_id'] = $company_id;
        }
        
        // Mapper les champs du formulaire aux colonnes de la table
        $field_mapping = [
            'type' => ['type'],
            'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
            'firstname' => ['firstname', 'prenom'],
            'email' => ['email', 'mail', 'courriel'],
            'phone' => ['phone', 'telephone', 'tel'],
            'mobile' => ['mobile', 'portable'],
            'fax' => ['fax'],
            'website' => ['website', 'site_web'],
            'address' => ['address', 'adresse', 'rue'],
            'postal_code' => ['postal_code', 'code_postal', 'npa'],
            'city' => ['city', 'ville', 'localite'],
            'country' => ['country', 'pays'],
            'vat_number' => ['vat_number', 'numero_tva', 'tva'],
            'siret' => ['siret', 'siren'],
            'notes' => ['notes', 'commentaires', 'remarques']
        ];
        
        foreach ($field_mapping as $form_field => $possible_columns) {
            $value = $_POST[$form_field] ?? '';
            foreach ($possible_columns as $column) {
                if ($this->contact->hasColumn($column)) {
                    $data[$column] = $value;
                    break;
                }
            }
        }
        
        // Créer le contact
        if(!$this->contact->create($data)) {
            throw new Exception('Erreur lors de la création du contact');
        }
    }
    
    // Garder les autres méthodes existantes (edit, update, delete, get)
    public function edit() {
        $contact_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        if(!$contact_id) {
            $this->redirect('index.php?controller=contacts&action=index');
        }
        
        $company_id = $_SESSION['company_id'];
        
        // Récupérer les données du contact
        $contactData = $this->contact->getById($contact_id, $this->contact->hasCompanyId() ? $company_id : null);
        
        if(!$contactData) {
            $_SESSION['error_message'] = 'Contact non trouvé';
            $this->redirect('index.php?controller=contacts&action=index');
        }
        
        $data = [
            'title' => 'Modifier le Contact',
            'action' => 'edit',
            'contact' => $contactData,
            'contact_id' => $contact_id,
            'success_message' => '',
            'error_message' => ''
        ];
        
        $this->loadView('contacts/form', $data);
    }
    
    // [Conserver les autres méthodes update, delete, get de l'original...]
    
    // Méthodes utilitaires
    private function loadView($view, $data = []) {
        extract($data);
        include "views/{$view}.php";
    }
    
    private function redirect($url) {
        header("Location: $url");
        exit;
    }
}
?>