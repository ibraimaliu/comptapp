<?php
/**
 * API AJAX: Gestion des Produits
 * Version: 1.0
 */

session_name('COMPTAPP_SESSION');
session_start();

header('Content-Type: application/json; charset=utf-8');

// Vérifier la session
if (!isset($_SESSION['company_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

// Inclure les modèles
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/models/Product.php';
require_once dirname(dirname(__DIR__)) . '/models/StockMovement.php';

$company_id = $_SESSION['company_id'];
$user_id = $_SESSION['user_id'];

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion à la base de données']);
    exit;
}

$product = new Product($db);
$stockMovement = new StockMovement($db);

// Déterminer l'action
$action = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
} else {
    $action = $_GET['action'] ?? 'list';
}

try {
    switch ($action) {
        case 'create':
            createProduct($product, $input, $company_id);
            break;

        case 'read':
            readProduct($product, $company_id);
            break;

        case 'update':
            updateProduct($product, $input, $company_id);
            break;

        case 'delete':
            deleteProduct($product, $input, $company_id);
            break;

        case 'list':
            listProducts($product, $company_id);
            break;

        case 'statistics':
            getStatistics($product, $company_id);
            break;

        case 'stock_value':
            getStockValue($product, $company_id);
            break;

        case 'generate_code':
            generateCode($product, $company_id);
            break;

        case 'movement':
            createMovement($stockMovement, $input, $company_id, $user_id);
            break;

        case 'movements_history':
            getMovementsHistory($stockMovement, $company_id);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Action invalide']);
            break;
    }

} catch (Exception $e) {
    error_log('Error in products.php: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Erreur serveur: ' . $e->getMessage()
    ]);
}

/**
 * Créer un produit
 */
function createProduct($product, $data, $company_id) {
    try {
        // Validation
        if (empty($data['code'])) {
            throw new Exception('Code produit requis');
        }

        if (empty($data['name'])) {
            throw new Exception('Nom requis');
        }

        if (empty($data['selling_price'])) {
            throw new Exception('Prix de vente requis');
        }

        // Préparer le produit
        $product->company_id = $company_id;
        $product->code = $data['code'];
        $product->name = $data['name'];
        $product->description = $data['description'] ?? null;
        $product->type = $data['type'] ?? 'product';
        $product->category_id = !empty($data['category_id']) ? intval($data['category_id']) : null;
        $product->purchase_price = floatval($data['purchase_price'] ?? 0);
        $product->selling_price = floatval($data['selling_price']);
        $product->tva_rate = floatval($data['tva_rate'] ?? 7.70);
        $product->currency = 'CHF';
        $product->stock_quantity = floatval($data['stock_quantity'] ?? 0);
        $product->stock_min = floatval($data['stock_min'] ?? 5);
        $product->stock_max = !empty($data['stock_max']) ? floatval($data['stock_max']) : null;
        $product->unit = $data['unit'] ?? 'pce';
        $product->is_active = isset($data['is_active']) ? intval($data['is_active']) : 1;
        $product->is_sellable = isset($data['is_sellable']) ? intval($data['is_sellable']) : 1;
        $product->is_purchasable = isset($data['is_purchasable']) ? intval($data['is_purchasable']) : 1;
        $product->track_stock = isset($data['track_stock']) ? intval($data['track_stock']) : 1;
        $product->supplier_id = !empty($data['supplier_id']) ? intval($data['supplier_id']) : null;
        $product->barcode = $data['barcode'] ?? null;
        $product->notes = $data['notes'] ?? null;

        if ($product->create()) {
            echo json_encode([
                'success' => true,
                'message' => 'Produit créé avec succès',
                'product_id' => $product->id
            ]);
        } else {
            throw new Exception('Erreur lors de la création du produit');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Lire un produit
 */
function readProduct($product, $company_id) {
    try {
        if (empty($_GET['id'])) {
            throw new Exception('ID produit requis');
        }

        $product->id = intval($_GET['id']);
        $product->company_id = $company_id;

        if ($product->read()) {
            echo json_encode([
                'success' => true,
                'product' => [
                    'id' => $product->id,
                    'code' => $product->code,
                    'name' => $product->name,
                    'description' => $product->description,
                    'type' => $product->type,
                    'category_id' => $product->category_id,
                    'purchase_price' => $product->purchase_price,
                    'selling_price' => $product->selling_price,
                    'tva_rate' => $product->tva_rate,
                    'stock_quantity' => $product->stock_quantity,
                    'stock_min' => $product->stock_min,
                    'stock_max' => $product->stock_max,
                    'unit' => $product->unit,
                    'is_active' => $product->is_active,
                    'is_sellable' => $product->is_sellable,
                    'is_purchasable' => $product->is_purchasable,
                    'track_stock' => $product->track_stock,
                    'supplier_id' => $product->supplier_id,
                    'barcode' => $product->barcode,
                    'notes' => $product->notes
                ]
            ]);
        } else {
            throw new Exception('Produit non trouvé');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Mettre à jour un produit
 */
function updateProduct($product, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID produit requis');
        }

        $product->id = intval($data['id']);
        $product->company_id = $company_id;

        // Vérifier que le produit existe
        if (!$product->read()) {
            throw new Exception('Produit non trouvé');
        }

        // Mettre à jour les champs
        $product->code = $data['code'];
        $product->name = $data['name'];
        $product->description = $data['description'] ?? null;
        $product->type = $data['type'] ?? 'product';
        $product->category_id = !empty($data['category_id']) ? intval($data['category_id']) : null;
        $product->purchase_price = floatval($data['purchase_price'] ?? 0);
        $product->selling_price = floatval($data['selling_price']);
        $product->tva_rate = floatval($data['tva_rate'] ?? 7.70);
        $product->stock_min = floatval($data['stock_min'] ?? 5);
        $product->stock_max = !empty($data['stock_max']) ? floatval($data['stock_max']) : null;
        $product->unit = $data['unit'] ?? 'pce';
        $product->is_active = isset($data['is_active']) ? intval($data['is_active']) : 1;
        $product->is_sellable = isset($data['is_sellable']) ? intval($data['is_sellable']) : 1;
        $product->is_purchasable = isset($data['is_purchasable']) ? intval($data['is_purchasable']) : 1;
        $product->track_stock = isset($data['track_stock']) ? intval($data['track_stock']) : 1;
        $product->supplier_id = !empty($data['supplier_id']) ? intval($data['supplier_id']) : null;
        $product->barcode = $data['barcode'] ?? null;
        $product->notes = $data['notes'] ?? null;

        if ($product->update()) {
            echo json_encode([
                'success' => true,
                'message' => 'Produit mis à jour'
            ]);
        } else {
            throw new Exception('Erreur lors de la mise à jour');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Supprimer un produit
 */
function deleteProduct($product, $data, $company_id) {
    try {
        if (empty($data['id'])) {
            throw new Exception('ID produit requis');
        }

        $product->id = intval($data['id']);
        $product->company_id = $company_id;

        if ($product->delete()) {
            echo json_encode([
                'success' => true,
                'message' => 'Produit supprimé'
            ]);
        } else {
            throw new Exception('Erreur lors de la suppression');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Lister les produits
 */
function listProducts($product, $company_id) {
    try {
        $filters = [];

        if (!empty($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }

        if (!empty($_GET['category_id'])) {
            $filters['category_id'] = intval($_GET['category_id']);
        }

        if (!empty($_GET['is_active'])) {
            $filters['is_active'] = intval($_GET['is_active']);
        }

        if (!empty($_GET['search'])) {
            $filters['search'] = $_GET['search'];
        }

        if (isset($_GET['low_stock']) && $_GET['low_stock'] === 'true') {
            $filters['low_stock'] = true;
        }

        $products = $product->readByCompany($company_id, $filters);

        echo json_encode([
            'success' => true,
            'products' => $products,
            'count' => count($products)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Obtenir les statistiques
 */
function getStatistics($product, $company_id) {
    try {
        $statistics = $product->getStatistics($company_id);

        echo json_encode([
            'success' => true,
            'statistics' => $statistics
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Obtenir la valeur du stock
 */
function getStockValue($product, $company_id) {
    try {
        $value = $product->getStockValue($company_id);

        echo json_encode([
            'success' => true,
            'value' => $value
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Générer un code produit
 */
function generateCode($product, $company_id) {
    try {
        $prefix = $_GET['prefix'] ?? 'PROD';
        $code = $product->generateCode($company_id, $prefix);

        echo json_encode([
            'success' => true,
            'code' => $code
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Créer un mouvement de stock
 */
function createMovement($stockMovement, $data, $company_id, $user_id) {
    try {
        if (empty($data['product_id'])) {
            throw new Exception('ID produit requis');
        }

        if (empty($data['type'])) {
            throw new Exception('Type de mouvement requis');
        }

        if (empty($data['quantity'])) {
            throw new Exception('Quantité requise');
        }

        $stockMovement->company_id = $company_id;
        $stockMovement->product_id = intval($data['product_id']);
        $stockMovement->type = $data['type'];
        $stockMovement->quantity = floatval($data['quantity']);
        $stockMovement->unit_cost = floatval($data['unit_cost'] ?? 0);
        $stockMovement->total_cost = $stockMovement->quantity * $stockMovement->unit_cost;
        $stockMovement->reason = $data['reason'] ?? '';
        $stockMovement->notes = $data['notes'] ?? null;
        $stockMovement->reference_type = $data['reference_type'] ?? null;
        $stockMovement->reference_id = $data['reference_id'] ?? null;
        $stockMovement->created_by = $user_id;
        $stockMovement->movement_date = date('Y-m-d H:i:s');

        if ($stockMovement->create()) {
            echo json_encode([
                'success' => true,
                'message' => 'Mouvement enregistré',
                'movement_id' => $stockMovement->id
            ]);
        } else {
            throw new Exception('Erreur lors de l\'enregistrement du mouvement');
        }

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

/**
 * Obtenir l'historique des mouvements
 */
function getMovementsHistory($stockMovement, $company_id) {
    try {
        $filters = [];

        if (!empty($_GET['product_id'])) {
            $filters['product_id'] = intval($_GET['product_id']);
        }

        if (!empty($_GET['type'])) {
            $filters['type'] = $_GET['type'];
        }

        if (!empty($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }

        if (!empty($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }

        $movements = $stockMovement->readByCompany($company_id, $filters);

        echo json_encode([
            'success' => true,
            'movements' => $movements,
            'count' => count($movements)
        ]);

    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
?>
