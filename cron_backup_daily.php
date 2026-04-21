<?php
/**
 * Script CRON: Sauvegarde quotidienne des bases de données
 *
 * Configuration Windows Task Scheduler:
 * Nom: Backup Gestion Comptable
 * Déclencheur: Quotidien à 02:00
 * Action: Démarrer un programme
 *   Programme: C:\xampp\php\php.exe
 *   Arguments: C:\xampp\htdocs\gestion_comptable\cron_backup_daily.php
 *
 * Configuration Linux Cron:
 * 0 2 * * * /usr/bin/php /path/to/gestion_comptable/cron_backup_daily.php >> /var/log/backup.log 2>&1
 */

// Exécution en ligne de commande seulement
if (php_sapi_name() !== 'cli') {
    die("Ce script doit être exécuté en ligne de commande.\n");
}

echo "=== BACKUP QUOTIDIEN - " . date('Y-m-d H:i:s') . " ===\n\n";

require_once __DIR__ . '/utils/DatabaseBackup.php';

$backup = new DatabaseBackup();

// 1. Sauvegarder toutes les bases de données
echo "1. Sauvegarde de toutes les bases de données...\n";
$results = $backup->backupAllTenants();

echo "   Total: {$results['total']} bases\n";
echo "   Succès: {$results['success']}\n";
echo "   Échecs: {$results['failed']}\n\n";

// Afficher les détails
foreach ($results['details'] as $detail) {
    $status = $detail['result']['success'] ? '✓' : '✗';
    $company = $detail['company_name'];
    $code = $detail['tenant_code'];

    if ($detail['result']['success']) {
        $size = round($detail['result']['size'] / 1024 / 1024, 2);
        echo "   {$status} [{$code}] {$company} - {$size} MB\n";
    } else {
        echo "   {$status} [{$code}] {$company} - ERREUR: {$detail['result']['error']}\n";
    }
}

echo "\n";

// 2. Nettoyer les anciens backups
echo "2. Nettoyage des anciens backups (> 30 jours)...\n";
$clean_results = $backup->cleanOldBackups(30);

if ($clean_results['success']) {
    echo "   {$clean_results['deleted']} fichier(s) supprimé(s)\n";
    echo "   Date limite: {$clean_results['cutoff_date']}\n";
}

echo "\n";

// 3. Statistiques
$backup_dir = $backup->getBackupDirectory();
$all_backups = $backup->listBackups();
$total_size = array_sum(array_column($all_backups, 'size'));
$total_size_mb = round($total_size / 1024 / 1024, 2);

echo "3. Statistiques des backups:\n";
echo "   Répertoire: {$backup_dir}\n";
echo "   Nombre total de backups: " . count($all_backups) . "\n";
echo "   Espace utilisé: {$total_size_mb} MB\n";

echo "\n=== BACKUP TERMINÉ - " . date('Y-m-d H:i:s') . " ===\n";
?>
