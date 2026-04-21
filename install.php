<?php
// Afficher toutes les erreurs
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Installation de la base de données</h1>";

// Inclure la configuration de la base de données
include_once 'config/database.php';

// Connexion à la base de données
$database = new Database();
$db = $database->getConnection();

// Vérifier la connexion
if (!$db) {
    die("<p style='color:red'>Impossible de se connecter à la base de données. Vérifiez vos paramètres.</p>");
}

echo "<p style='color:green'>Connexion à la base de données réussie.</p>";

// Liste des requêtes SQL pour créer les tables
$tables = [
    // Table des utilisateurs
    "users" => "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Table des sociétés
    "companies" => "CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        owner_name VARCHAR(100) NOT NULL,
        owner_surname VARCHAR(100) NOT NULL,
        fiscal_year_start DATE NOT NULL,
        fiscal_year_end DATE NOT NULL,
        tva_status ENUM('oui', 'non') NOT NULL DEFAULT 'non',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Table du plan comptable
    "accounting_plan" => "CREATE TABLE IF NOT EXISTS accounting_plan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        number VARCHAR(20) NOT NULL,
        name VARCHAR(100) NOT NULL,
        category ENUM('actif', 'passif', 'charge', 'produit') NOT NULL,
        type ENUM('bilan', 'resultat') NOT NULL,
        is_used BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        UNIQUE KEY (company_id, number)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Table des contacts
    "contacts" => "CREATE TABLE IF NOT EXISTS contacts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        type ENUM('client', 'fournisseur', 'autre') NOT NULL,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100),
        phone VARCHAR(20),
        address VARCHAR(255),
        postal_code VARCHAR(20),
        city VARCHAR(100),
        country VARCHAR(100) DEFAULT 'Suisse',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Table des transactions
    "transactions" => "CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        date DATE NOT NULL,
        description VARCHAR(255) NOT NULL,
        amount DECIMAL(10, 2) NOT NULL,
        type ENUM('income', 'expense') NOT NULL,
        category VARCHAR(50) NOT NULL,
        tva_rate DECIMAL(4, 2) DEFAULT 0,
        account_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (account_id) REFERENCES accounting_plan(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Table des factures
    "invoices" => "CREATE TABLE IF NOT EXISTS invoices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        number VARCHAR(50) NOT NULL,
        date DATE NOT NULL,
        client_id INT NOT NULL,
        subtotal DECIMAL(10, 2) NOT NULL,
        tva_amount DECIMAL(10, 2) NOT NULL,
        total DECIMAL(10, 2) NOT NULL,
        status ENUM('en attente', 'payée', 'annulée') NOT NULL DEFAULT 'en attente',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (client_id) REFERENCES contacts(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Table des articles de facture
    "invoice_items" => "CREATE TABLE IF NOT EXISTS invoice_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        tva_rate DECIMAL(4, 2) NOT NULL DEFAULT 0,
        total DECIMAL(10, 2) NOT NULL,
        tva_amount DECIMAL(10, 2) NOT NULL,
        FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Table des catégories de dépenses
    "categories" => "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        is_used BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
    
    // Table des taux de TVA
    "tva_rates" => "CREATE TABLE IF NOT EXISTS tva_rates (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        rate DECIMAL(4, 2) NOT NULL,
        description VARCHAR(100) NOT NULL,
        is_used BOOLEAN DEFAULT FALSE,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
];

// Exécuter les requêtes de création de tables
foreach ($tables as $table_name => $query) {
    try {
        // Vérifier si la table existe déjà
        $check_table = $db->query("SHOW TABLES LIKE '$table_name'");
        $table_exists = $check_table->rowCount() > 0;
        
        if ($table_exists) {
            echo "<p>La table '$table_name' existe déjà.</p>";
        } else {
            $db->exec($query);
            echo "<p style='color:green'>Table '$table_name' créée avec succès.</p>";
        }
    } catch (PDOException $exception) {
        echo "<p style='color:red'>Erreur lors de la création de la table '$table_name': " . $exception->getMessage() . "</p>";
    }
}

// Vérifier si un utilisateur administrateur existe déjà
$stmt = $db->query("SELECT COUNT(*) FROM users WHERE username = 'admin'");
$admin_exists = (int)$stmt->fetchColumn() > 0;

// Créer un utilisateur administrateur par défaut si nécessaire
if (!$admin_exists) {
    try {
        $admin_password = password_hash('admin123', PASSWORD_BCRYPT);
        $query = "INSERT INTO users (username, email, password) VALUES ('admin', 'admin@example.com', :password)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':password', $admin_password);
        $stmt->execute();
        
        echo "<p style='color:green'>Utilisateur administrateur créé avec succès:</p>";
        echo "<ul>";
        echo "<li>Nom d'utilisateur: admin</li>";
        echo "<li>Mot de passe: admin123</li>";
        echo "<li>Email: admin@example.com</li>";
        echo "</ul>";
        echo "<p><strong>IMPORTANT: Changez ce mot de passe dès que possible pour des raisons de sécurité!</strong></p>";
    } catch (PDOException $exception) {
        echo "<p style='color:red'>Erreur lors de la création de l'utilisateur admin: " . $exception->getMessage() . "</p>";
    }
}

echo "<p style='margin-top:20px;'><a href='index.php'>Retourner à l'application</a></p>";
?>