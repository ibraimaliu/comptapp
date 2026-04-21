# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**Gestion Comptable** is a PHP-based medical practice/accounting management system for managing patients, consultations, invoices, and financial records. The application uses a hybrid architecture combining traditional page-based routing with an emerging MVC pattern.

## Development Environment

**Requirements:**
- PHP 7.4+ with PDO extension
- MySQL/MariaDB
- Apache/XAMPP (configured for `http://localhost/gestion_comptable`)

**Database Setup:**
```bash
# Run the installation script to create database tables
php install.php
# Or access via browser: http://localhost/gestion_comptable/install.php
```

**Database Configuration:**
- Database: `gestion_comptable`
- Credentials: Located in `config/database.php` (localhost/root/Abil)
- Connection: PDO with UTF-8 encoding and exception mode enabled

**Running the Application:**
```bash
# Ensure XAMPP is running with Apache and MySQL
# Access the application at:
http://localhost/gestion_comptable
```

## Architecture Pattern

### Hybrid MVC with Page-Based Routing

The application uses **two routing mechanisms** simultaneously:

1. **Traditional Page-Based Routing** (Primary):
   - Entry point: `index.php`
   - Route via: `?page=parameter` (e.g., `?page=home`, `?page=comptabilite`)
   - Pages mapped in switch statement to views in `views/` directory
   - Authentication check: Redirects to login if not authenticated

2. **MVC Controller Routing** (Emerging):
   - Example: `ContactsController.php`
   - Route via: `?controller=name&action=method`
   - Uses proper MVC separation with controller methods

### Request Flow

```
HTTP Request → index.php
    ↓
Include config/config.php (session start, defines functions)
    ↓
Include includes/header.php (navigation, company selector)
    ↓
Route based on $_GET['page']:
    login → views/login.php
    register → views/register.php
    home → views/home.php
    comptabilite → views/comptabilite.php
    adresses → views/adresses.php
    parametres → views/parametres.php
    recherche → views/recherche.php
    logout → destroy session + redirect
    default → views/404.php
    ↓
Include includes/footer.php (scripts)
```

### API/AJAX Flow

```
JavaScript (Fetch API) → api/{endpoint}.php or assets/ajax/{handler}.php
    ↓
Parse JSON request body
    ↓
Validate session and input
    ↓
Call model methods (with PDO)
    ↓
Return JSON response {success: bool, data/error: ...}
    ↓
JavaScript updates DOM or redirects
```

## Directory Structure

```
gestion_comptable/
├── api/                          # RESTful-style JSON APIs
│   ├── auth.php                  # Login, register, logout
│   ├── session.php               # Company switching
│   ├── contact.php               # Contact CRUD
│   ├── company.php               # Company management
│   ├── test_auth.php             # Authentication testing
│   └── contacts/
│       └── save_contact.php      # Save contact endpoint
│
├── assets/
│   ├── ajax/                     # AJAX handler scripts
│   │   ├── add_contact.php
│   │   ├── contacts.php
│   │   ├── delete_contact.php
│   │   ├── get_contact.php
│   │   ├── save_contact.php
│   │   ├── test_company.php
│   │   ├── test_save.php
│   │   └── update_contact.php
│   ├── css/                      # Stylesheets
│   │   ├── style.css             # Global styles
│   │   ├── adresses.css          # Address book styles
│   │   ├── modal-styles.css      # Modal components
│   │   └── nouvelle_adresse.css  # New address form styles
│   └── js/                       # Client-side JavaScript (vanilla JS)
│       ├── main.js               # Global utilities
│       ├── script.js             # Additional scripts
│       ├── contact.js            # Contact page logic
│       └── contacts_redirect.js  # Contact redirect handling
│
├── config/
│   ├── config.php                # App constants, session start, helper functions
│   └── database.php              # PDO connection class
│
├── controllers/                  # MVC Controllers (emerging pattern)
│   └── ContactsController.php    # Contact CRUD controller
│
├── includes/
│   ├── header.php                # Global navigation, company selector
│   └── footer.php                # Global scripts
│
├── models/                       # Data models with database logic
│   ├── User.php                  # Authentication & user management
│   ├── Company.php               # Multi-tenant organization entities
│   ├── Contact.php               # Contacts/addresses with adaptive schema
│   ├── Transaction.php           # Financial transactions (double-entry)
│   ├── Invoice.php               # Invoicing with line items
│   ├── Quote.php                 # Quotes/Devis with items
│   ├── AccountingPlan.php        # Chart of accounts
│   ├── Category.php              # Transaction categories
│   ├── TVArate.php               # Tax rates
│   ├── Product.php               # Product catalog
│   ├── StockMovement.php         # Inventory movements
│   ├── BankAccount.php           # Bank account management
│   ├── BankTransaction.php       # Bank statement transactions
│   ├── BankReconciliation.php    # Bank import parsers (Camt.053, MT940, CSV)
│   ├── Payment.php               # Payment tracking
│   └── PaymentReminder.php       # Overdue payment reminders
│
├── views/                        # PHP templates (no templating engine)
│   ├── login.php                 # Login page
│   ├── register.php              # Registration page
│   ├── home.php                  # Dashboard
│   ├── comptabilite.php          # Accounting section
│   ├── adresses.php              # Address book
│   ├── parametres.php            # Settings
│   ├── recherche.php             # Search
│   ├── nouvelle_adresse.php      # New address form
│   ├── modifier_adresse.php      # Edit address form
│   ├── society_setup.php         # Company setup
│   ├── 404.php                   # Error page
│   └── contacts/
│       └── index.php             # MVC-style contact list view
│
├── .claude/                      # Claude configuration
├── index.php                     # Main entry point
├── install.php                   # Database schema installer
├── check_database.php            # Database verification script
├── create_admin.php              # Admin user creation
├── create_admin.sql              # Admin user SQL script
├── CREATE_DATABASE.sql           # Database creation script
└── test_session.php              # Session testing
```

## Database Schema

**Core Tables:**
- `users` - User accounts with bcrypt-hashed passwords
- `companies` - Multi-tenant organizations per user
- `contacts` - Client/supplier contacts
- `accounting_plan` - Chart of accounts
- `transactions` - Financial entries (double-entry accounting with counterpart_account_id)
- `invoices` + `invoice_items` - Invoicing with line items
- `quotes` + `quote_items` - Quotes/Devis with conversion to invoices
- `categories` - Transaction categories
- `tva_rates` - Tax rates
- `products` - Product/service catalog with stock tracking
- `stock_movements` - Inventory movement history
- `product_suppliers` - Product-supplier relationships
- `stock_alerts` - Low stock/out of stock alerts
- `bank_accounts` - Bank account management
- `bank_transactions` - Imported bank transactions
- `bank_import_configs` - CSV import configurations
- `bank_reconciliation_rules` - Automatic matching rules

**Key Pattern:** All tables are company-scoped via `company_id` foreign key.

**Important Notes:**
- **Double-Entry Accounting**: The `transactions` table includes `counterpart_account_id` for proper double-entry bookkeeping (debit/credit accounts)
- **Inventory Module**: Full stock management with triggers for automatic stock updates and alert generation
- **Bank Reconciliation**: Supports ISO 20022 (Camt.053), MT940, and CSV formats for Swiss banks

## Model Architecture

### Standard Model Pattern

Models do NOT inherit from a base class. Each follows this pattern:

```php
class ModelName {
    private $conn;              // PDO connection
    private $table_name = "...";

    // Public properties map to table columns
    public $id;
    public $company_id;
    public $name;
    // ... more properties

    public function __construct($db) {
        $this->conn = $db;  // PDO instance injected
    }

    public function create() { /* INSERT with prepared statements */ }
    public function read() { /* SELECT single record */ }
    public function readByCompany($company_id) { /* SELECT filtered */ }
    public function update() { /* UPDATE with prepared statements */ }
    public function delete() { /* DELETE with prepared statements */ }
}
```

### Usage Pattern

```php
// Initialize database connection
require_once 'config/database.php';
require_once 'models/Contact.php';

$database = new Database();
$db = $database->getConnection();

$contact = new Contact($db);
$contact->company_id = $_SESSION['company_id'];
$contact->name = "John Doe";
$contact->email = "john@example.com";
$contact->create();
```

### Special Model Features

**Contact Model** - Adaptive Schema Detection:
```php
// Automatically detects table structure
private function detectTableStructure()  // Uses DESCRIBE table
public function hasColumn($column_name)  // Check if column exists
// Uses 'contacts' table
```

**Invoice Model** - Transaction Support:
```php
public function create() {
    $this->conn->beginTransaction();
    try {
        // Insert invoice
        // Insert invoice items
        $this->conn->commit();
    } catch(Exception $e) {
        $this->conn->rollBack();
    }
}
```

## Authentication & Sessions

### Session Configuration

The application uses a custom session name to avoid conflicts:
```php
session_name('COMPTAPP_SESSION');  // Set in config/config.php
```

### Authentication Flow

**Login:**
```php
// api/auth.php with action: 'login'
1. Validate credentials against users table
2. password_verify() against bcrypt hash
3. Set session: $_SESSION['user_id'], $_SESSION['username'], $_SESSION['email']
4. Return JSON: {success: true}
```

**Registration:**
```php
// api/auth.php with action: 'register'
1. Validate email format, password length (>=8), username length (>=3)
2. Check if username/email exists
3. Hash password: password_hash($password, PASSWORD_BCRYPT)
4. Create user in database
5. Return JSON: {success: true}
```

**Session Variables:**
- `$_SESSION['user_id']` - Current user ID
- `$_SESSION['username']` - Username
- `$_SESSION['email']` - User email
- `$_SESSION['company_id']` - Currently selected company (multi-tenant)
- `$_SESSION['last_activity']` - Last access timestamp for timeout checking
- `$_SESSION['csrf_token']` - CSRF protection token
- `$_SESSION['flash']` - Flash messages array

**Helper Functions (in config/config.php):**
```php
// Authentication
isLoggedIn()                    // Returns isset($_SESSION['user_id'])
redirect($page)                 // Header redirect helper
checkSessionTimeout()           // Check session timeout (1 hour)
regenerateSession()             // Regenerate session ID

// User info
getUserId()                     // Get current user ID
getUsername()                   // Get current username
getActiveCompanyId()            // Get selected company ID
setActiveCompanyId($id)         // Set company ID
hasActiveCompany()              // Check if company selected
```

**Company Switching:**
```php
// api/session.php with action: 'change_company'
1. Verify user has access: Company::userHasAccess($user_id, $company_id)
2. Update: $_SESSION['company_id'] = $company_id
3. Return JSON: {success: true}
```

## Security Features

### CSRF Protection

CSRF protection is implemented in `config/config.php`:

```php
// Generate token
generateCSRFToken()             // Creates/retrieves token

// Verify token
verifyCSRFToken($token)         // Validates token

// In forms
csrfField()                     // Returns hidden input field
csrfToken()                     // Returns token for AJAX
```

**Usage in Forms:**
```php
<form method="POST">
    <?php echo csrfField(); ?>
    <!-- form fields -->
</form>
```

**Usage in AJAX:**
```javascript
fetch('api/endpoint.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '<?php echo csrfToken(); ?>'
    },
    body: JSON.stringify(data)
})
```

### Flash Messages

Flash messages system for user feedback (one-time messages):

```php
// Set flash message
setFlash('success', 'Action completed successfully!');
setFlash('error', 'An error occurred');
setFlash('warning', 'Please be careful');
setFlash('info', 'Here is some information');

// Check if message exists
hasFlash('success')             // Returns boolean

// Get and display message (auto-clears)
getFlash('success')             // Returns message string

// Display all flash messages as HTML
displayFlash()                  // Returns Bootstrap-style alerts
```

### Input Validation

Helper functions in `config/config.php`:

```php
// Sanitization
sanitize($string)               // htmlspecialchars + strip_tags

// Validation
validateEmail($email)           // Check valid email format
validateRequired($value)        // Check not empty
validateMinLength($value, $min) // Minimum length check
validateMaxLength($value, $max) // Maximum length check
validateAmount($amount)         // Numeric and >= 0
validateDate($date)             // Valid Y-m-d format
```

### Standard Security Patterns

**Input Sanitization:**
```php
$clean = htmlspecialchars(strip_tags($input));
// Or use helper:
$clean = sanitize($input);
```

**SQL Injection Prevention:**
```php
// All queries use PDO prepared statements
$query = "SELECT * FROM table WHERE id = :id";
$stmt = $this->conn->prepare($query);
$stmt->bindParam(":id", $this->id);
$stmt->execute();
```

**Password Security:**
```php
// Hashing (registration)
$hashed = password_hash($password, PASSWORD_BCRYPT);

// Verification (login)
password_verify($input_password, $stored_hash);
```

**Access Control:**
```php
// Check user is logged in
if(!isLoggedIn()) redirect('index.php?page=login');

// Verify company access
Company::userHasAccess($user_id, $company_id);

// Company-scoped queries
WHERE company_id = :company_id
```

## Utility Functions

Formatting helpers in `config/config.php`:

```php
// Number formatting
formatAmount($amount, $decimals = 2)  // Returns: "1 234,56"

// Date formatting
formatDate($date, $format = 'd/m/Y')  // Returns: "25/12/2024"
```

## API Endpoints

All APIs accept/return JSON and follow this pattern:

**Standard Response:**
```json
{
  "success": true/false,
  "data": {...},        // On success
  "error": "message"    // On failure
}
```

**Key Endpoints:**

- `api/auth.php` - POST with `{action: 'login'|'register'|'logout', ...}`
- `api/session.php` - POST with `{action: 'change_company', company_id: X}`
- `api/contact.php` - GET/POST with `action=create|read|update|delete`
- `api/company.php` - Company management
- `assets/ajax/add_contact.php` - POST to create contact
- `assets/ajax/get_contact.php` - GET with `?id=X` to fetch contact
- `assets/ajax/update_contact.php` - POST to update contact
- `assets/ajax/delete_contact.php` - POST with `{id: X}` to delete
- `assets/ajax/contacts.php` - GET to list contacts
- `assets/ajax/save_contact.php` - POST to save contact

**Client-Side Pattern:**
```javascript
fetch('api/auth.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'login',
        username: 'user',
        password: 'pass'
    })
})
.then(res => res.json())
.then(data => {
    if(data.success) {
        // Handle success
    } else {
        // Handle error
    }
});
```

## View Rendering

### No Templating Engine

Views are pure PHP files with embedded HTML and inline JavaScript:

```php
<?php
// Views can directly include models if needed
require_once 'config/database.php';
require_once 'models/Transaction.php';

// Access session data
$company_id = $_SESSION['company_id'];

// Query data
$database = new Database();
$db = $database->getConnection();
$transaction = new Transaction($db);
$results = $transaction->readByCompany($company_id);
?>

<!-- HTML with PHP echo -->
<div>
    <?php foreach($results as $row): ?>
        <p><?php echo htmlspecialchars($row['description']); ?></p>
    <?php endforeach; ?>
</div>

<!-- Inline JavaScript for AJAX -->
<script>
function deleteItem(id) {
    fetch('assets/ajax/delete_contact.php', {
        method: 'POST',
        body: JSON.stringify({id: id})
    })
    .then(res => res.json())
    .then(data => {
        if(data.success) location.reload();
    });
}
</script>
```

### Layout System

**Header Template** (`includes/header.php`):
- Session authentication check
- Dynamic CSS loading based on `$current_page`
- Global navigation menu
- Company selector dropdown (populated from `companies` table)

**Footer Template** (`includes/footer.php`):
- Global JavaScript includes (main.js)
- Page-specific script loading based on current page
- HTML close tags

### MVC Controller Views

When using controllers, views are loaded via:

```php
// In controller method
private function loadView($view, $data = []) {
    extract($data);  // Converts array keys to variables
    include "views/{$view}.php";
}

// Usage
$this->loadView('contacts/index', [
    'contacts' => $contacts,
    'total' => $total
]);

// In view file: $contacts and $total are available as variables
```

## Frontend Architecture

**JavaScript Approach:**
- Vanilla JavaScript (no jQuery/Vue/React)
- Fetch API for AJAX
- Event listeners for form handling
- Page-specific scripts in `assets/js/`:
  - `main.js` - Global utilities
  - `contact.js` - Contact page logic
  - `comptabilite.js` - Accounting page
  - `admin-utilities.js` - Admin features

**CSS:**
- Custom CSS (no framework like Bootstrap/Tailwind)
- Font Awesome for icons (CDN)
- Page-specific stylesheets loaded dynamically in header
- Modal styles in `assets/css/modal-styles.css`

## Adding New Features

### Adding a New Page (Page-Based Routing)

1. Create view file: `views/new_feature.php`
2. Add route in `index.php`:
   ```php
   case 'new_feature':
       include_once 'views/new_feature.php';
       break;
   ```
3. Add navigation link in `includes/header.php`
4. Create page-specific CSS/JS if needed

### Adding a New MVC Controller

1. Create controller: `controllers/NewController.php`
   ```php
   class NewController {
       private $model;
       private $db;

       public function __construct() {
           require_once 'config/database.php';
           require_once 'models/NewModel.php';
           $database = new Database();
           $this->db = $database->getConnection();
           $this->model = new NewModel($this->db);
       }

       public function index() {
           // List items
           $items = $this->model->readByCompany($_SESSION['company_id']);
           $this->loadView('new/index', ['items' => $items]);
       }

       private function loadView($view, $data = []) {
           extract($data);
           include "views/{$view}.php";
       }
   }
   ```

2. Create model: `models/NewModel.php`
   ```php
   class NewModel {
       private $conn;
       private $table_name = "new_table";

       public $id;
       public $company_id;
       public $name;

       public function __construct($db) {
           $this->conn = $db;
       }

       public function readByCompany($company_id) {
           $query = "SELECT * FROM " . $this->table_name . "
                     WHERE company_id = :company_id";
           $stmt = $this->conn->prepare($query);
           $stmt->bindParam(":company_id", $company_id);
           $stmt->execute();
           return $stmt->fetchAll(PDO::FETCH_ASSOC);
       }
   }
   ```

3. Create view: `views/new/index.php`

4. Add route (either in `index.php` or create controller router)

### Adding a New API Endpoint

1. Create API file: `api/new_endpoint.php`
   ```php
   <?php
   header('Content-Type: application/json');
   header('Access-Control-Allow-Methods: POST');

   session_name('COMPTAPP_SESSION');
   session_start();

   if(!isset($_SESSION['user_id'])) {
       http_response_code(401);
       echo json_encode(['success' => false, 'message' => 'Unauthorized']);
       exit;
   }

   $data = json_decode(file_get_contents("php://input"));

   require_once '../config/database.php';
   require_once '../models/ModelName.php';

   $database = new Database();
   $db = $database->getConnection();
   $model = new ModelName($db);

   switch($data->action) {
       case 'create':
           $model->name = $data->name;
           $model->company_id = $_SESSION['company_id'];
           if($model->create()) {
               echo json_encode(['success' => true]);
           } else {
               echo json_encode(['success' => false, 'error' => 'Creation failed']);
           }
           break;

       default:
           http_response_code(400);
           echo json_encode(['success' => false, 'message' => 'Invalid action']);
   }
   ?>
   ```

2. Call from JavaScript:
   ```javascript
   fetch('api/new_endpoint.php', {
       method: 'POST',
       headers: {'Content-Type': 'application/json'},
       body: JSON.stringify({
           action: 'create',
           name: 'Example'
       })
   })
   .then(res => res.json())
   .then(data => console.log(data));
   ```

## Common Pitfalls

1. **Session Company ID**: Always check `$_SESSION['company_id']` is set before querying company-scoped data
2. **Session Name**: API endpoints must use `session_name('COMPTAPP_SESSION')` before `session_start()`
3. **Table Name**: The Contact model uses `contacts` table
4. **PDO Error Mode**: Database class sets `PDO::ERRMODE_EXCEPTION` - all DB errors throw exceptions
5. **Authentication Check**: Pages should check `isLoggedIn()` or rely on `index.php` redirect
6. **API Response Format**: Always return JSON with `success` boolean and either `data` or `error` key
7. **Input Sanitization**: Use `htmlspecialchars(strip_tags())` or `sanitize()` on all user input before database operations
8. **Password Handling**: Never store plain passwords - always use `password_hash()` with `PASSWORD_BCRYPT`
9. **CSRF Tokens**: Include CSRF tokens in forms and verify on submission for POST requests

## Error Handling

**Development Mode:**
```php
// Currently enabled in many files
ini_set('display_errors', 1);
error_reporting(E_ALL);
error_log("Debug message");  // Logs to Apache error log
```

**Production Considerations:**
- Disable `display_errors`
- Log errors to file instead of displaying
- Implement proper error pages
- Session timeout is configured (SESSION_TIMEOUT = 3600 seconds)
- CSRF protection should be enforced on all forms

## Multi-Tenancy

The application supports multiple companies per user:

1. User logs in → `$_SESSION['user_id']` set
2. User selects company → `$_SESSION['company_id']` set via `api/session.php`
3. All models filter by `company_id` in WHERE clauses
4. Company access verified via `Company::userHasAccess($user_id, $company_id)`
5. Company selector dropdown in header shows available companies

**When querying:**
```php
// Always scope by company
$query = "SELECT * FROM transactions WHERE company_id = :company_id";
$stmt->bindParam(":company_id", $_SESSION['company_id']);
```

## Code Style

**PHP Conventions:**
- PascalCase for class names: `ContactsController`, `Transaction`
- camelCase for method names: `readByCompany()`, `userHasAccess()`
- snake_case for database columns: `company_id`, `created_at`
- Private properties use `$this->conn`, `$this->table_name`

**SQL Conventions:**
- Always use prepared statements with named parameters: `:id`, `:company_id`
- Table names in lowercase with underscores: `accounting_plan`, `invoice_items`

**JavaScript Conventions:**
- camelCase for functions and variables
- Fetch API for AJAX (not XMLHttpRequest)
- Event delegation for dynamic content

## Language

The codebase is primarily in **French** with some English:
- Database table/column names: English
- Comments in PHP files: French
- Variable names: Mix of French and English
- User-facing text: French

## Key Features and Modules

### 1. Double-Entry Accounting (Comptabilité en Partie Double)

The accounting system implements proper double-entry bookkeeping:

**Transaction Structure:**
- `account_id` - Debit account (Compte au Débit)
- `counterpart_account_id` - Credit account (Compte au Crédit)
- Both accounts must be different and from the accounting plan
- Validation enforced client-side and server-side

**Creating Double-Entry Transactions:**
```javascript
const data = {
    action: 'create',
    date: '2025-01-13',
    amount: 1000.50,
    description: 'Vente de service',
    account_id: debit_account,           // e.g., 1020 (Bank)
    counterpart_account_id: credit_account,  // e.g., 3200 (Revenue)
    type: 'income',
    tva_rate: 7.7
};
```

**Important:** Always verify that debit and credit accounts are different before submission.

### 2. Quotes/Devis System

Full quote management with conversion to invoices:

**Quote Lifecycle:**
1. **draft** - Being edited
2. **sent** - Sent to client
3. **accepted** - Client approved
4. **refused** - Client declined
5. **expired** - Past valid_until date
6. **converted** - Transformed into invoice

**Converting Quote to Invoice:**
```php
// Models/Quote.php method convertToInvoice()
$quote->convertToInvoice($quote_id);
// Creates invoice with same items, new number (FACT-YYYY-NNN)
// Marks quote as 'converted'
```

**Quote Number Format:** `DEV-YYYY-NNN` (e.g., DEV-2024-001)

### 3. PDF Export with Swiss QR-Invoices

The system generates professional PDFs with Swiss QR-Invoices (ISO 20022):

**Dependencies:**
- mPDF ^8.2 (PDF generation)
- endroid/qr-code (QR-Code generation)

**PDF Generation:**
```php
require_once 'utils/PDFGenerator.php';

$pdf_generator = new PDFGenerator($db);

// Generate quote PDF
$pdf_path = $pdf_generator->generateQuotePDF($quote_id, $company_id);

// Generate invoice PDF with QR-Code
$pdf_path = $pdf_generator->generateInvoicePDF($invoice_id, $company_id, true);
```

**QR-Invoice Requirements:**
- QR-IBAN must be configured in company settings
- Format: CH + 19 digits with positions 5-9 between 30000-31999
- QR-Reference: 27 digits with checksum
- Compatible with all Swiss banking apps

**PDF Endpoints:**
- `assets/ajax/export_quote_pdf.php?id=X` - Export quote
- `assets/ajax/export_invoice_pdf.php?id=X` - Export invoice with QR-Code

### 4. Inventory/Stock Management

Complete inventory system with automatic updates:

**Key Features:**
- Product catalog (products, services, bundles)
- Real-time stock tracking
- Automatic stock updates via database triggers
- Low stock and out-of-stock alerts
- Multi-supplier support per product
- Barcode support

**Stock Movement Types:**
- `in` - Stock entry
- `out` - Stock exit
- `adjustment` - Manual correction
- `transfer` - Between locations
- `return` - Customer/supplier return

**Automatic Trigger:**
The trigger `trg_update_stock_after_movement` automatically:
- Updates product.stock_quantity after each movement
- Creates alerts when stock ≤ stock_min or stock = 0

**Creating Stock Movement:**
```php
$movement = new StockMovement($db);
$movement->company_id = $_SESSION['company_id'];
$movement->product_id = 1;
$movement->type = 'in';
$movement->quantity = 20;
$movement->reason = 'Achat fournisseur';
$movement->create();
// Stock updated automatically by trigger
```

**Installation:**
```bash
php install_inventory_tables.php
```

### 5. Bank Reconciliation

Import and reconcile bank statements automatically:

**Supported Formats:**
1. **ISO 20022 Camt.053** (XML) - Swiss bank standard
2. **MT940** (SWIFT) - International format
3. **CSV** - Configurable column mapping

**Reconciliation Methods:**
1. **QR-Reference Match** (Priority 1) - 100% accurate, automatic
2. **Amount Match** (Priority 2) - With tolerance (default 0.50 CHF)
3. **Description Keywords** (Priority 3) - Manual validation required

**Import Flow:**
```
Upload bank file → Auto-detect format → Parse transactions
→ Check for duplicates → Attempt QR-Reference matching
→ Pending transactions for manual reconciliation
```

**Installation:**
```bash
mysql -u root -pAbil gestion_comptable < install_bank_reconciliation.sql
```

**Key Model Methods:**
```php
// BankReconciliation.php
parseCamt053($xml_content);  // Parse ISO 20022 XML
parseMT940($mt940_content);   // Parse SWIFT MT940
parseCSV($csv_content, $config);  // Parse CSV with custom mapping
```

### 6. Accounting Plan Import/Export

Multi-format import support for chart of accounts:

**Supported Formats:**
- CSV (Tab-separated, UTF-8 with BOM)
- TXT (Tab-separated)
- XLS (Excel 97-2003) - Requires PHP zip extension
- XLSX (Excel 2007+) - Requires PHP zip extension

**Required Columns:**
1. Numéro - Account number (e.g., 1000, 3200)
2. Intitulé - Account name
3. Catégorie - Actif, Passif, Charge, or Produit
4. Type - Bilan or Résultat

**Import Modes:**
- **Replace** - Deletes unused accounts, imports new ones
- **Add** - Keeps existing, adds only new accounts

**Automatic Normalization:**
- English → French categories (Asset→Actif, Revenue→Produit)
- Case-insensitive column detection
- Duplicate detection by account number

**API Endpoint:**
```
POST assets/ajax/accounting_plan_import.php
- Multipart form with file upload
- Returns: { success: true, imported: 45, skipped: 2, errors: [] }
```

**Export:**
```
GET assets/ajax/data_export.php?type=accounting_plan&format=csv
- Always exports as CSV (UTF-8 with BOM)
- Filename: plan_comptable_YYYY-MM-DD_HH-mm-ss.csv
```

### 7. Payment Reminders

Automated overdue invoice management:

**Features:**
- Automatic detection of overdue invoices
- Multi-level reminders (1st, 2nd, 3rd, final)
- Configurable delays between reminders
- Email sending with PHPMailer
- Reminder history tracking

**Configuration:**
Default delays configurable in company settings or defaults to:
- 1st reminder: 7 days after due date
- 2nd reminder: 14 days
- 3rd reminder: 21 days
- Final notice: 30 days

## Critical Implementation Notes

### Transaction Creation Validation

When creating transactions, always validate:

```php
// Server-side validation (api/transaction.php)
$amount = floatval($data->amount);
if($amount <= 0) {
    // Reject with 400 error
}

if(!in_array($data->type, ['income', 'expense'])) {
    // Reject with 400 error
}

// Ensure both accounts are different (double-entry)
if($data->account_id === $data->counterpart_account_id) {
    // Reject - same account for debit and credit
}
```

### Database Triggers

The system uses triggers for automatic operations:

**Stock Management:**
- `trg_update_stock_after_movement` - Updates stock after movements
- Located in `install_inventory.sql`
- IMPORTANT: Do not disable or modify without understanding impact

**Viewing Triggers:**
```sql
SHOW TRIGGERS LIKE 'stock_movements';
```

### Important File Paths

**Uploads Directory Structure:**
```
uploads/
├── quotes/          # Generated quote PDFs
├── invoices/        # Generated invoice PDFs
├── qr_codes/        # QR-Code images for invoices
├── bank_imports/    # Uploaded bank statement files
└── logos/           # Company logos (future)
```

**Permissions Required:**
```bash
chmod 755 uploads/quotes
chmod 755 uploads/invoices
chmod 755 uploads/qr_codes
chmod 755 uploads/bank_imports
```

### Installation Scripts

For setting up missing modules:

```bash
# Inventory/Stock module
php install_inventory_tables.php

# Bank reconciliation module
mysql -u root -pAbil gestion_comptable < install_bank_reconciliation.sql

# Quotes/Invoices module (if not installed)
mysql -u root -pAbil gestion_comptable < install_quotes_invoices.sql
```

### Common Issues and Fixes

**Issue 1: Products table missing**
```bash
# Solution:
php install_inventory_tables.php
```

**Issue 2: Accounts not showing in transaction dropdowns**
- Check company_id is set in session
- Verify accounting_plan table has entries for that company
- Check browser console for JavaScript errors
- Add debug info to see account count

**Issue 3: QR-Invoice not generating**
- Ensure QR-IBAN is configured in company settings
- Verify mPDF and endroid/qr-code are installed via composer
- Check that invoice has a generated qr_reference
- Review PDF generation logs in Apache error.log

**Issue 4: Bank import fails**
- Verify file format (XML must be valid, CSV must have headers)
- Check uploads/bank_imports/ directory exists and is writable
- For XLS/XLSX: Ensure PHP zip extension is enabled
- Review parsing logs for format-specific errors

**Issue 5: Double-entry validation fails**
- Ensure counterpart_account_id column exists in transactions table
- Run: `php add_counterpart_account.php` if missing
- Verify both account_id and counterpart_account_id are from accounting_plan
- Check that accounts are different (debit ≠ credit)
