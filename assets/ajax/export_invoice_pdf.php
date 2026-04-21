<?php
/**
 * Export Invoice to PDF with QR-Code
 * Génère un PDF professionnel pour une facture avec QR-facture suisse
 */
session_name('COMPTAPP_SESSION');
session_start();

// Vérifier la session
if (!isset($_SESSION['company_id']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Non autorisé');
}

// Récupérer l'ID de la facture
$invoice_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($invoice_id <= 0) {
    http_response_code(400);
    die('ID de facture invalide');
}

// Inclure les dépendances
require_once dirname(dirname(__DIR__)) . '/config/database.php';
require_once dirname(dirname(__DIR__)) . '/utils/PDFGenerator.php';

try {
    // Connexion base de données
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Erreur de connexion à la base de données');
    }

    // Vérifier que la facture appartient à la société
    $query = "SELECT id, number FROM invoices WHERE id = :id AND company_id = :company_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $invoice_id);
    $stmt->bindParam(':company_id', $_SESSION['company_id']);
    $stmt->execute();

    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        http_response_code(404);
        die('Facture introuvable');
    }

    // Générer le PDF avec QR-Code
    $pdf_generator = new PDFGenerator($db);
    $pdf_path = $pdf_generator->generateInvoicePDF($invoice_id, $_SESSION['company_id'], true);

    if (!$pdf_path) {
        throw new Exception('Erreur lors de la génération du PDF');
    }

    // Chemin absolu du fichier
    $full_path = dirname(dirname(__DIR__)) . '/' . $pdf_path;

    if (!file_exists($full_path)) {
        throw new Exception('Fichier PDF non trouvé');
    }

    // Nom du fichier pour le téléchargement
    $filename = 'facture_' . $invoice['number'] . '.pdf';

    // Headers pour téléchargement
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($full_path));
    header('Cache-Control: private, max-age=0, must-revalidate');
    header('Pragma: public');

    // Envoyer le fichier
    readfile($full_path);

    // Optionnel: supprimer le fichier après téléchargement
    // unlink($full_path);

    exit;

} catch (Exception $e) {
    error_log('Error in export_invoice_pdf.php: ' . $e->getMessage());
    http_response_code(500);
    die('Erreur serveur: ' . $e->getMessage());
}
?>
