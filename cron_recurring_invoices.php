<?php
/**
 * Script CRON pour la génération automatique des factures récurrentes
 * et le traitement des abonnements
 *
 * À exécuter quotidiennement via CRON:
 * 0 1 * * * php /path/to/cron_recurring_invoices.php
 */

require_once 'config/database_master.php';
require_once 'models/RecurringInvoice.php';
require_once 'models/Subscription.php';

// Définir les constantes si elles n'existent pas
if (!defined('DB_HOST')) {
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASSWORD', 'Abil');
}

echo "=== Traitement des Factures Récurrentes et Abonnements ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

// Connexion à la base master
$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();

if (!$db_master) {
    die("❌ Erreur: Impossible de se connecter à la base master.\n");
}

// Récupérer tous les tenants actifs
$query = "SELECT tenant_code, database_name, company_name
          FROM tenants
          WHERE status IN ('active', 'trial')
          ORDER BY created_at";

$stmt = $db_master->prepare($query);
$stmt->execute();
$tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
$stmt->closeCursor();

if (count($tenants) == 0) {
    echo "⚠️  Aucun tenant trouvé.\n";
    exit;
}

echo "📊 Tenants à traiter: " . count($tenants) . "\n\n";

$total_invoices_generated = 0;
$total_subscriptions_renewed = 0;
$total_subscriptions_expired = 0;
$total_errors = 0;

// Traiter chaque tenant
foreach ($tenants as $tenant) {
    $tenant_code = $tenant['tenant_code'];
    $database_name = $tenant['database_name'];
    $company_name = $tenant['company_name'];

    echo "───────────────────────────────────────────────────────\n";
    echo "🏢 Tenant: $company_name ($tenant_code)\n";
    echo "💾 Base: $database_name\n";

    try {
        // Connexion à la base tenant
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . $database_name . ";charset=utf8mb4";
        $db_tenant = new PDO($dsn, DB_USER, DB_PASSWORD, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]);

        // ============================================
        // 1. GÉNÉRATION DES FACTURES RÉCURRENTES
        // ============================================
        $recurring_invoice = new RecurringInvoice($db_tenant);
        $due_invoices = $recurring_invoice->getDueForGeneration();

        echo "  📝 Factures récurrentes à générer: " . count($due_invoices) . "\n";

        $invoices_generated = 0;
        $invoices_errors = 0;

        foreach ($due_invoices as $recurring) {
            $result = $recurring_invoice->generateInvoice($recurring['id'], $recurring['company_id']);

            if ($result['success']) {
                echo "    ✅ Facture générée: {$result['invoice_number']}\n";
                $invoices_generated++;
                $total_invoices_generated++;
            } else {
                echo "    ❌ Erreur: {$result['message']}\n";
                $invoices_errors++;
                $total_errors++;
            }
        }

        // ============================================
        // 2. RENOUVELLEMENT DES ABONNEMENTS
        // ============================================
        $subscription = new Subscription($db_tenant);
        $due_renewals = $subscription->getDueForRenewal(1); // Abonnements qui expirent aujourd'hui ou demain

        echo "  🔄 Abonnements à renouveler: " . count($due_renewals) . "\n";

        $subscriptions_renewed = 0;
        $subscription_errors = 0;

        foreach ($due_renewals as $sub) {
            $result = $subscription->renew($sub['id'], $sub['company_id']);

            if ($result['success']) {
                echo "    ✅ Abonnement renouvelé: {$sub['subscription_name']}\n";
                $subscriptions_renewed++;
                $total_subscriptions_renewed++;
            } else {
                echo "    ❌ Erreur: {$result['message']}\n";
                $subscription_errors++;
                $total_errors++;
            }
        }

        // ============================================
        // 3. TRAITEMENT DES ABONNEMENTS EXPIRÉS
        // ============================================
        $expired_count = $subscription->processExpired();
        if ($expired_count > 0) {
            echo "  ⏰ Abonnements expirés: $expired_count\n";
            $total_subscriptions_expired += $expired_count;
        }

        echo "  📊 Résumé: $invoices_generated factures, $subscriptions_renewed renouvellements\n";

    } catch (PDOException $e) {
        echo "  ❌ Erreur: " . $e->getMessage() . "\n";
        $total_errors++;
    }

    echo "\n";
}

// ============================================
// RÉSUMÉ GLOBAL
// ============================================
echo "═══════════════════════════════════════════════════════\n";
echo "=== RÉSUMÉ GLOBAL ===\n";
echo "═══════════════════════════════════════════════════════\n";
echo "📝 Factures générées: $total_invoices_generated\n";
echo "🔄 Abonnements renouvelés: $total_subscriptions_renewed\n";
echo "⏰ Abonnements expirés: $total_subscriptions_expired\n";
echo "❌ Erreurs: $total_errors\n\n";

if ($total_errors == 0) {
    echo "✅ Traitement terminé avec succès!\n";
} else {
    echo "⚠️  Traitement terminé avec $total_errors erreur(s).\n";
}

echo "\nDate de fin: " . date('Y-m-d H:i:s') . "\n";
?>
