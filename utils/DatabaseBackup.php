<?php
/**
 * Classe DatabaseBackup - Sauvegarde automatique des bases de données
 */

class DatabaseBackup {
    private $backup_dir;
    private $db_host = 'localhost';
    private $db_user = 'root';
    private $db_pass = 'Abil';

    public function __construct() {
        // Créer le répertoire de backup s'il n'existe pas
        $this->backup_dir = dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'backups';

        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }

    /**
     * Sauvegarder une base de données spécifique
     */
    public function backupDatabase($database_name, $tenant_code = null) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $prefix = $tenant_code ? "{$tenant_code}_" : '';
            $filename = "{$prefix}{$database_name}_{$timestamp}.sql";
            $filepath = $this->backup_dir . DIRECTORY_SEPARATOR . $filename;

            // Utiliser mysqldump pour créer la sauvegarde
            $command = sprintf(
                'mysqldump -h%s -u%s -p%s %s > %s 2>&1',
                escapeshellarg($this->db_host),
                escapeshellarg($this->db_user),
                escapeshellarg($this->db_pass),
                escapeshellarg($database_name),
                escapeshellarg($filepath)
            );

            exec($command, $output, $return_code);

            if ($return_code === 0 && file_exists($filepath) && filesize($filepath) > 0) {
                // Compresser le fichier SQL
                $this->compressBackup($filepath);

                return [
                    'success' => true,
                    'filename' => $filename . '.gz',
                    'filepath' => $filepath . '.gz',
                    'size' => filesize($filepath . '.gz')
                ];
            } else {
                throw new Exception("Échec de la création du backup. Code: {$return_code}");
            }

        } catch (Exception $e) {
            error_log("Erreur backup database {$database_name}: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Sauvegarder toutes les bases de données des tenants
     */
    public function backupAllTenants() {
        require_once dirname(dirname(__FILE__)) . '/config/database_master.php';

        $database = new DatabaseMaster();
        $db = $database->getConnection();

        // Récupérer tous les tenants actifs
        $query = "SELECT id, tenant_code, database_name, company_name
                  FROM tenants
                  WHERE status IN ('active', 'trial')
                  ORDER BY id";
        $stmt = $db->query($query);
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [
            'total' => count($tenants),
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        foreach ($tenants as $tenant) {
            $result = $this->backupDatabase($tenant['database_name'], $tenant['tenant_code']);

            if ($result['success']) {
                $results['success']++;
            } else {
                $results['failed']++;
            }

            $results['details'][] = [
                'tenant_code' => $tenant['tenant_code'],
                'company_name' => $tenant['company_name'],
                'database' => $tenant['database_name'],
                'result' => $result
            ];
        }

        // Sauvegarder également la base master
        $master_result = $this->backupDatabase('gestion_comptable_master', 'MASTER');
        $results['details'][] = [
            'tenant_code' => 'MASTER',
            'company_name' => 'Base Master',
            'database' => 'gestion_comptable_master',
            'result' => $master_result
        ];

        if ($master_result['success']) {
            $results['success']++;
        } else {
            $results['failed']++;
        }

        $results['total']++;

        return $results;
    }

    /**
     * Compresser un fichier de backup
     */
    private function compressBackup($filepath) {
        if (!file_exists($filepath)) {
            return false;
        }

        $gz_filepath = $filepath . '.gz';

        // Lire le fichier SQL
        $file_content = file_get_contents($filepath);

        // Compresser avec gzip
        $gz = gzopen($gz_filepath, 'w9');
        gzwrite($gz, $file_content);
        gzclose($gz);

        // Supprimer le fichier SQL non compressé
        if (file_exists($gz_filepath)) {
            unlink($filepath);
            return true;
        }

        return false;
    }

    /**
     * Nettoyer les anciens backups (garder les 30 derniers jours)
     */
    public function cleanOldBackups($days_to_keep = 30) {
        $cutoff_time = time() - ($days_to_keep * 24 * 60 * 60);
        $deleted_count = 0;

        $files = glob($this->backup_dir . '/*.sql.gz');

        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_time) {
                if (unlink($file)) {
                    $deleted_count++;
                }
            }
        }

        return [
            'success' => true,
            'deleted' => $deleted_count,
            'cutoff_date' => date('Y-m-d', $cutoff_time)
        ];
    }

    /**
     * Lister les backups disponibles
     */
    public function listBackups($tenant_code = null) {
        $pattern = $tenant_code
            ? $this->backup_dir . "/{$tenant_code}_*.sql.gz"
            : $this->backup_dir . "/*.sql.gz";

        $files = glob($pattern);
        $backups = [];

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'filepath' => $file,
                'size' => filesize($file),
                'size_formatted' => $this->formatBytes(filesize($file)),
                'date' => date('Y-m-d H:i:s', filemtime($file)),
                'timestamp' => filemtime($file)
            ];
        }

        // Trier par date (plus récent en premier)
        usort($backups, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });

        return $backups;
    }

    /**
     * Restaurer une base de données depuis un backup
     */
    public function restoreDatabase($backup_filepath, $database_name) {
        try {
            // Vérifier que le fichier existe
            if (!file_exists($backup_filepath)) {
                throw new Exception("Fichier de backup introuvable");
            }

            // Décompresser si nécessaire
            $sql_file = $backup_filepath;
            if (substr($backup_filepath, -3) === '.gz') {
                $sql_file = substr($backup_filepath, 0, -3);

                $gz = gzopen($backup_filepath, 'r');
                $out = fopen($sql_file, 'w');

                while (!gzeof($gz)) {
                    fwrite($out, gzread($gz, 4096));
                }

                gzclose($gz);
                fclose($out);
            }

            // Restaurer avec mysql
            $command = sprintf(
                'mysql -h%s -u%s -p%s %s < %s 2>&1',
                escapeshellarg($this->db_host),
                escapeshellarg($this->db_user),
                escapeshellarg($this->db_pass),
                escapeshellarg($database_name),
                escapeshellarg($sql_file)
            );

            exec($command, $output, $return_code);

            // Nettoyer le fichier SQL temporaire si on l'a créé
            if ($sql_file !== $backup_filepath && file_exists($sql_file)) {
                unlink($sql_file);
            }

            if ($return_code === 0) {
                return [
                    'success' => true,
                    'message' => 'Base de données restaurée avec succès'
                ];
            } else {
                throw new Exception("Échec de la restauration. Code: {$return_code}");
            }

        } catch (Exception $e) {
            error_log("Erreur restore database: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Formater les octets en format lisible
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Obtenir le répertoire de backup
     */
    public function getBackupDirectory() {
        return $this->backup_dir;
    }
}
?>
