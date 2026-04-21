<?php
/**
 * Script de test pour la génération de PDFs
 * Usage: Accéder via navigateur à http://localhost/gestion_comptable/test_pdf_generation.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Démarrer session
session_name('COMPTAPP_SESSION');
session_start();

// Vérifier la session
if (!isset($_SESSION['company_id'])) {
    die('<h2>❌ Erreur: Session non active</h2><p>Veuillez vous connecter d\'abord: <a href="index.php?page=login">Se connecter</a></p>');
}

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Test Génération PDF</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #667eea; }
        h2 { color: #333; margin-top: 30px; }
        .success { color: #065f46; background: #d1fae5; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { color: #991b1b; background: #fee2e2; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { color: #1e40af; background: #dbeafe; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .test-item { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 3px solid #667eea; }
        .btn { display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #5568d3; }
        code { background: #f3f4f6; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🧪 Test Génération PDF</h1>
    <div class='info'>
        <strong>Session active:</strong><br>
        • User ID: " . $_SESSION['user_id'] . "<br>
        • Company ID: " . $_SESSION['company_id'] . "<br>
        • Username: " . ($_SESSION['username'] ?? 'N/A') . "
    </div>";

// Inclure les dépendances
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/utils/PDFGenerator.php';

try {
    // Connexion base de données
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception('Erreur de connexion à la base de données');
    }

    echo "<div class='success'>✅ Connexion base de données réussie</div>";

    // Vérifier si mPDF est disponible
    if (!class_exists('Mpdf\Mpdf')) {
        echo "<div class='error'>❌ mPDF non trouvé. Exécutez: <code>composer install --ignore-platform-reqs</code></div>";
    } else {
        echo "<div class='success'>✅ mPDF disponible</div>";
    }

    // Vérifier PDFGenerator
    if (class_exists('PDFGenerator')) {
        echo "<div class='success'>✅ Classe PDFGenerator disponible</div>";
    } else {
        echo "<div class='error'>❌ Classe PDFGenerator introuvable</div>";
    }

    $company_id = $_SESSION['company_id'];

    // TEST 1: Chercher un devis
    echo "<h2>📋 Test 1: Recherche Devis</h2>";
    $query = "SELECT id, number, total FROM quotes WHERE company_id = :company_id ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($quote) {
        echo "<div class='test-item'>
            <strong>Devis trouvé:</strong><br>
            • ID: {$quote['id']}<br>
            • Numéro: {$quote['number']}<br>
            • Total: " . number_format($quote['total'], 2) . " CHF<br><br>
            <a class='btn' href='assets/ajax/export_quote_pdf.php?id={$quote['id']}' target='_blank'>
                📄 Télécharger PDF Devis
            </a>
        </div>";
    } else {
        echo "<div class='info'>ℹ️ Aucun devis trouvé pour cette société. <a href='index.php?page=devis'>Créer un devis</a></div>";
    }

    // TEST 2: Chercher une facture
    echo "<h2>🧾 Test 2: Recherche Facture</h2>";
    $query = "SELECT id, number, total, qr_reference FROM invoices WHERE company_id = :company_id ORDER BY id DESC LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice) {
        echo "<div class='test-item'>
            <strong>Facture trouvée:</strong><br>
            • ID: {$invoice['id']}<br>
            • Numéro: {$invoice['number']}<br>
            • Total: " . number_format($invoice['total'], 2) . " CHF<br>
            • QR-Reference: " . ($invoice['qr_reference'] ?? 'Non générée') . "<br><br>
            <a class='btn' href='assets/ajax/export_invoice_pdf.php?id={$invoice['id']}' target='_blank'>
                📄 Télécharger PDF Facture
            </a>
        </div>";
    } else {
        echo "<div class='info'>ℹ️ Aucune facture trouvée pour cette société. <a href='index.php?page=factures'>Créer une facture</a></div>";
    }

    // TEST 3: Vérifier dossiers uploads
    echo "<h2>📁 Test 3: Vérification Dossiers</h2>";
    $dirs = [
        'uploads/quotes' => 'Devis',
        'uploads/invoices' => 'Factures',
        'uploads/qr_codes' => 'QR-Codes'
    ];

    foreach ($dirs as $dir => $label) {
        $full_path = __DIR__ . '/' . $dir;
        if (is_dir($full_path)) {
            $writable = is_writable($full_path) ? '✅ Écriture autorisée' : '❌ Pas d\'écriture';
            echo "<div class='test-item'>
                <strong>{$label}:</strong> <code>{$dir}</code><br>
                • Existe: ✅<br>
                • {$writable}
            </div>";
        } else {
            echo "<div class='error'>❌ Dossier manquant: <code>{$dir}</code></div>";
        }
    }

    // TEST 4: Vérifier QR-IBAN
    echo "<h2>🏦 Test 4: Configuration QR-IBAN</h2>";
    $query = "SELECT name, qr_iban, bank_iban FROM companies WHERE id = :company_id LIMIT 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':company_id', $company_id);
    $stmt->execute();
    $company = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($company) {
        echo "<div class='test-item'>
            <strong>Société:</strong> {$company['name']}<br>
            • QR-IBAN: " . ($company['qr_iban'] ? '✅ ' . $company['qr_iban'] : '❌ Non configuré') . "<br>
            • IBAN standard: " . ($company['bank_iban'] ? '✅ ' . $company['bank_iban'] : '❌ Non configuré') . "
        </div>";

        if (empty($company['qr_iban'])) {
            echo "<div class='error'>
                ⚠️ QR-IBAN non configuré. Les QR-factures ne seront pas générées correctement.<br>
                <a href='index.php?page=parametres'>Configurer QR-IBAN</a>
            </div>";
        }
    }

    // TEST 5: Test génération direct (si données disponibles)
    if (isset($quote) && $quote) {
        echo "<h2>🔬 Test 5: Génération PDF Devis (Direct)</h2>";
        try {
            $pdf_generator = new PDFGenerator($db);
            $pdf_path = $pdf_generator->generateQuotePDF($quote['id'], $company_id);

            if ($pdf_path) {
                echo "<div class='success'>
                    ✅ PDF Devis généré avec succès!<br>
                    • Chemin: <code>{$pdf_path}</code><br>
                    • <a href='{$pdf_path}' target='_blank'>Ouvrir le PDF</a>
                </div>";
            } else {
                echo "<div class='error'>❌ Échec génération PDF devis</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    if (isset($invoice) && $invoice) {
        echo "<h2>🔬 Test 6: Génération PDF Facture avec QR (Direct)</h2>";
        try {
            $pdf_generator = new PDFGenerator($db);
            $pdf_path = $pdf_generator->generateInvoicePDF($invoice['id'], $company_id, true);

            if ($pdf_path) {
                echo "<div class='success'>
                    ✅ PDF Facture généré avec succès!<br>
                    • Chemin: <code>{$pdf_path}</code><br>
                    • <a href='{$pdf_path}' target='_blank'>Ouvrir le PDF</a>
                </div>";
            } else {
                echo "<div class='error'>❌ Échec génération PDF facture</div>";
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Erreur: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    }

    echo "<h2>✅ Tests Terminés</h2>";
    echo "<div class='info'>
        <strong>Actions recommandées:</strong><br>
        1. Vérifier que les PDFs s'ouvrent correctement<br>
        2. Scanner le QR-Code avec une application bancaire suisse<br>
        3. Vérifier le formatage (dates, montants, accents)<br>
        4. Tester avec différents navigateurs
    </div>";

    echo "<div style='margin-top: 30px;'>
        <a class='btn' href='index.php?page=devis'>← Retour aux Devis</a>
        <a class='btn' href='index.php?page=factures'>← Retour aux Factures</a>
        <a class='btn' href='index.php?page=home'>← Retour Accueil</a>
    </div>";

} catch (Exception $e) {
    echo "<div class='error'>
        <strong>❌ Erreur fatale:</strong><br>
        " . htmlspecialchars($e->getMessage()) . "
    </div>";
    error_log("Test PDF Generation Error: " . $e->getMessage());
}

echo "</div>
</body>
</html>";
?>
