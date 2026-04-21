<?php
/**
 * Debug de session
 */
session_name('COMPTAPP_SESSION');
session_start();

echo "<h1>Debug Session</h1>";
echo "<pre>";
echo "Session ID: " . session_id() . "\n\n";
echo "Session Data:\n";
echo "═════════════\n";
print_r($_SESSION);
echo "\n\n";

if (isset($_SESSION['user_id'])) {
    echo "✓ Utilisateur connecté (ID: {$_SESSION['user_id']})\n";
} else {
    echo "❌ Utilisateur NON connecté\n";
}

if (isset($_SESSION['tenant_database'])) {
    echo "✓ Tenant défini: {$_SESSION['tenant_database']}\n";
} else {
    echo "❌ Tenant NON défini\n";
}

if (isset($_SESSION['company_id'])) {
    echo "✓ Société sélectionnée (ID: {$_SESSION['company_id']})\n";
} else {
    echo "❌ Société NON sélectionnée\n";
}

echo "</pre>";

if (!isset($_SESSION['user_id'])) {
    echo "<p><strong>Solution:</strong> Connectez-vous via <a href='login_tenant.php'>login_tenant.php</a></p>";
}
?>
