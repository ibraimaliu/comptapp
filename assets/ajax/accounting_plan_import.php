<?php
/**
 * API: Import/Export Plan Comptable
 * Description: Gestion de l'import et export CSV du plan comptable
 * Version: 1.0
 */

header('Content-Type: application/json');
session_name('COMPTAPP_SESSION');
session_start();

if (!isset($_SESSION['user_id']) || !isset($_SESSION['company_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';
require_once '../../models/AccountingPlan.php';

$database = new Database();
$db = $database->getConnection();

$company_id = $_SESSION['company_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'import_csv':
            importCSV($db, $company_id);
            break;

        case 'export_csv':
            exportCSV($db, $company_id);
            break;

        case 'import_default':
            importDefault($db, $company_id);
            break;

        case 'reset':
            resetPlan($db, $company_id);
            break;

        default:
            throw new Exception('Action invalide');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Import CSV/TXT/Excel file
 */
function importCSV($db, $company_id) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Fichier manquant ou erreur d\'upload');
    }

    $file = $_FILES['csv_file']['tmp_name'];
    $action_type = $_POST['import_action'] ?? 'append'; // 'replace' or 'append'

    // Validate file extension
    $filename = $_FILES['csv_file']['name'];
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

    $allowed_formats = ['csv', 'txt', 'xls', 'xlsx'];
    if (!in_array($ext, $allowed_formats)) {
        throw new Exception('Format non supporté. Formats acceptés: CSV, TXT, XLS, XLSX');
    }

    // Read file data based on format
    $data_rows = [];

    if ($ext === 'xls' || $ext === 'xlsx') {
        // Check if ZIP extension is available (required for XLSX)
        if (!class_exists('ZipArchive') && $ext === 'xlsx') {
            throw new Exception('L\'extension PHP ZIP n\'est pas activée. Veuillez utiliser un fichier CSV ou TXT, ou contactez votre administrateur pour activer l\'extension ZIP.');
        }

        // Read Excel file using PhpSpreadsheet
        require_once '../../vendor/autoload.php';

        try {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
            $worksheet = $spreadsheet->getActiveSheet();
            $data_rows = $worksheet->toArray();
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            if (strpos($error_msg, 'ZipArchive') !== false) {
                throw new Exception('Extension PHP ZIP manquante. Veuillez utiliser le format CSV ou TXT avec séparateur TAB à la place.');
            }
            throw new Exception('Erreur lecture fichier Excel: ' . $error_msg . '. Essayez le format CSV ou TXT.');
        }

        if (empty($data_rows)) {
            throw new Exception('Le fichier Excel est vide');
        }

    } else {
        // Read CSV/TXT file
        $handle = fopen($file, 'r');
        if (!$handle) {
            throw new Exception('Impossible de lire le fichier');
        }

        // Détecter le séparateur (tab ou point-virgule)
        $first_line = fgets($handle);
        rewind($handle);

        $delimiter = "\t"; // Par défaut
        if (strpos($first_line, ';') !== false) {
            $delimiter = ';';
        } elseif (strpos($first_line, ',') !== false) {
            $delimiter = ',';
        }

        while (($row = fgetcsv($handle, 1000, $delimiter)) !== false) {
            $data_rows[] = $row;
        }

        fclose($handle);

        if (empty($data_rows)) {
            throw new Exception('Le fichier est vide');
        }
    }

    // Extract header
    $header = array_shift($data_rows);
    if (!$header || count($header) < 4) {
        throw new Exception('Format invalide. Colonnes attendues: Numéro | Intitulé | Catégorie | Type');
    }

    // Normalize header
    $header = array_map('trim', $header);
    $header = array_map('strtolower', $header);

    // Find column indices
    $num_idx = array_search('numero', $header);
    if ($num_idx === false) $num_idx = array_search('numéro', $header);
    if ($num_idx === false) $num_idx = array_search('number', $header);
    if ($num_idx === false) $num_idx = 0;

    $name_idx = array_search('intitule', $header);
    if ($name_idx === false) $name_idx = array_search('intitulé', $header);
    if ($name_idx === false) $name_idx = array_search('name', $header);
    if ($name_idx === false) $name_idx = 1;

    $cat_idx = array_search('categorie', $header);
    if ($cat_idx === false) $cat_idx = array_search('catégorie', $header);
    if ($cat_idx === false) $cat_idx = array_search('category', $header);
    if ($cat_idx === false) $cat_idx = 2;

    $type_idx = array_search('type', $header);
    if ($type_idx === false) $type_idx = 3;

    // If replace, delete existing accounts (only unused ones)
    if ($action_type === 'replace') {
        $query = "DELETE FROM accounting_plan
                  WHERE company_id = :company_id AND is_used = 0";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':company_id', $company_id);
        $stmt->execute();
    }

    $imported = 0;
    $errors = [];

    $db->beginTransaction();

    try {
        foreach ($data_rows as $line_num => $data) {
            // Skip empty rows
            if (empty(array_filter($data))) {
                continue;
            }

            if (count($data) < 4) {
                $errors[] = "Ligne " . ($line_num + 2) . " ignorée: nombre de colonnes insuffisant";
                continue;
            }

            $number = trim($data[$num_idx] ?? '');
            $name = trim($data[$name_idx] ?? '');
            $category = strtolower(trim($data[$cat_idx] ?? ''));
            $type = strtolower(trim($data[$type_idx] ?? ''));

            // Validate
            if (empty($number) || empty($name)) {
                $errors[] = "Ligne " . ($line_num + 2) . " ignorée: numéro ou nom vide";
                continue;
            }

            // Validate and truncate lengths
            if (strlen($number) > 20) {
                $errors[] = "Ligne " . ($line_num + 2) . ": Numéro trop long ($number), tronqué à 20 caractères";
                $number = substr($number, 0, 20);
            }

            if (strlen($name) > 255) {
                $errors[] = "Ligne " . ($line_num + 2) . ": Nom trop long (" . strlen($name) . " car.), tronqué à 255 caractères";
                $name = substr($name, 0, 255);
            }

            // Normalize category
            $category_map = [
                'actif' => 'actif',
                'asset' => 'actif',
                'passif' => 'passif',
                'liability' => 'passif',
                'charge' => 'charge',
                'expense' => 'charge',
                'produit' => 'produit',
                'revenue' => 'produit',
                'income' => 'produit',
                'salaires' => 'charge',
                'salaire' => 'charge',
                'produits' => 'produit', // Pluriel
                'charges' => 'charge',  // Pluriel
                'charges d\'exploitation' => 'charge',
                'produits d\'exploitation' => 'produit',
                'charges hors exploitation' => 'charge',
                'produits hors exploitation' => 'produit',
                'charges de personnel' => 'charge',
                'clotûre' => 'passif', // Les comptes de clôture vont au passif
                'cloture' => 'passif'
            ];

            $category = $category_map[$category] ?? $category;

            if (!in_array($category, ['actif', 'passif', 'charge', 'produit'])) {
                $errors[] = "Ligne ignorée pour compte $number: catégorie invalide '$category'";
                continue;
            }

            // Mapping entre catégorie CSV et type DB
            // La colonne 'type' dans la DB est un ENUM('actif','passif','charge','produit')
            // La colonne 'category' dans la DB est VARCHAR et peut contenir une sous-catégorie (ex: "Trésorerie")
            $type = $category; // actif/passif/charge/produit

            // Pour category, on peut utiliser une description plus spécifique si disponible
            // Pour l'instant, on met la même valeur normalisée
            $category_db = $category;

            // Déterminer si le compte est sélectionnable basé sur la longueur du numéro
            // Les comptes de 1-3 chiffres sont des catégories (non sélectionnables)
            // Les comptes de 4+ chiffres sont des comptes finaux (sélectionnables)
            $number_length = strlen($number);
            $is_selectable = ($number_length >= 4) ? 1 : 0;

            // Check if account already exists
            $check_query = "SELECT id FROM accounting_plan
                           WHERE company_id = :company_id AND number = :number";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':company_id', $company_id);
            $check_stmt->bindParam(':number', $number);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                if ($action_type === 'append') {
                    $errors[] = "Compte $number existe déjà, ignoré";
                    continue;
                }
            }

            // Insert account (using only existing columns)
            $insert_query = "INSERT INTO accounting_plan
                            (company_id, number, name, category, type, is_selectable, is_used)
                            VALUES
                            (:company_id, :number, :name, :category, :type, :is_selectable, 0)";

            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':company_id', $company_id);
            $insert_stmt->bindParam(':number', $number);
            $insert_stmt->bindParam(':name', $name);
            $insert_stmt->bindParam(':category', $category_db);
            $insert_stmt->bindParam(':type', $type);
            $insert_stmt->bindParam(':is_selectable', $is_selectable, PDO::PARAM_INT);

            if ($insert_stmt->execute()) {
                $imported++;
            } else {
                $errors[] = "Erreur insertion compte $number";
            }
        }

        $db->commit();

        echo json_encode([
            'success' => true,
            'message' => "$imported comptes importés avec succès",
            'imported' => $imported,
            'errors' => $errors
        ]);

    } catch (Exception $e) {
        $db->rollBack();
        throw new Exception('Erreur lors de l\'import: ' . $e->getMessage());
    }
}

/**
 * Export to CSV
 */
function exportCSV($db, $company_id) {
    $query = "SELECT number, name, category, type
              FROM accounting_plan
              WHERE company_id = :company_id
              ORDER BY number ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();

    $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Generate CSV
    $filename = 'plan_comptable_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output UTF-8 BOM for Excel compatibility
    echo "\xEF\xBB\xBF";

    $output = fopen('php://output', 'w');

    // Header - using tab delimiter
    fputcsv($output, ['Numéro', 'Intitulé', 'Catégorie', 'Type'], "\t");

    // Data
    foreach ($accounts as $account) {
        fputcsv($output, [
            $account['number'],
            $account['name'],
            ucfirst($account['category']),
            ucfirst($account['type'])
        ], "\t");
    }

    fclose($output);
    exit;
}

/**
 * Import default Swiss accounting plan (PME/KMU)
 */
function importDefault($db, $company_id) {
    // Check if plan already exists
    $check_query = "SELECT COUNT(*) as count FROM accounting_plan
                    WHERE company_id = :company_id";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['count'] > 0) {
        throw new Exception('Un plan comptable existe déjà. Utilisez la réinitialisation pour le remplacer.');
    }

    $account = new AccountingPlan($db);

    if ($account->importDefaultPlan($company_id)) {
        echo json_encode([
            'success' => true,
            'message' => 'Plan comptable PME importé avec succès'
        ]);
    } else {
        throw new Exception('Erreur lors de l\'import du plan par défaut');
    }
}

/**
 * Reset accounting plan (delete all unused accounts)
 */
function resetPlan($db, $company_id) {
    // Delete only unused accounts
    $query = "DELETE FROM accounting_plan
              WHERE company_id = :company_id AND is_used = 0";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);

    if ($stmt->execute()) {
        $deleted = $stmt->rowCount();

        // Check how many used accounts remain
        $check_query = "SELECT COUNT(*) as count FROM accounting_plan
                       WHERE company_id = :company_id AND is_used = 1";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':company_id', $company_id);
        $check_stmt->execute();
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

        $message = "$deleted comptes non utilisés supprimés.";
        if ($result['count'] > 0) {
            $message .= " {$result['count']} comptes utilisés conservés.";
        }

        echo json_encode([
            'success' => true,
            'message' => $message,
            'deleted' => $deleted,
            'remaining' => $result['count']
        ]);
    } else {
        throw new Exception('Erreur lors de la réinitialisation');
    }
}
?>
