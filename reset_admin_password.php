<?php
/**
 * Script: Réinitialiser le mot de passe administrateur
 */

require_once 'config/database_master.php';

echo "=== RÉINITIALISATION MOT DE PASSE ADMIN ===\n\n";

// Nouveau mot de passe
$new_password = 'Admin@123';

// Générer le hash
$password_hash = password_hash($new_password, PASSWORD_BCRYPT);

echo "Nouveau mot de passe: $new_password\n";
echo "Hash généré: $password_hash\n\n";

try {
    $database = new DatabaseMaster();
    $db = $database->getConnection();

    // Mettre à jour le mot de passe
    $query = "UPDATE admin_users
              SET password_hash = :password_hash
              WHERE username = 'superadmin'";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':password_hash', $password_hash);

    if ($stmt->execute()) {
        echo "✅ Mot de passe mis à jour avec succès!\n\n";

        // Vérifier
        $check = $db->query("SELECT username, email FROM admin_users WHERE username = 'superadmin'");
        $admin = $check->fetch(PDO::FETCH_ASSOC);

        echo "Informations de connexion:\n";
        echo "  Email: " . $admin['email'] . "\n";
        echo "  Mot de passe: $new_password\n";
        echo "\n";
        echo "Page de connexion: http://localhost/gestion_comptable/admin/login.php\n";
    } else {
        echo "❌ Erreur lors de la mise à jour\n";
    }

} catch (Exception $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
}
?>
