<?php
/**
 * Classe pour vérifier les limites des tenants selon leur plan d'abonnement
 */
class TenantLimits {

    /**
     * Vérifier si un tenant peut créer une nouvelle société
     *
     * @param PDO $db_master Connexion à la base master
     * @param string $tenant_code Code du tenant
     * @param int $user_id ID de l'utilisateur
     * @return array ['allowed' => bool, 'current' => int, 'max' => int, 'message' => string]
     */
    public static function canCreateCompany($db_master, $tenant_code, $user_id) {
        // 1. Récupérer le tenant et son plan
        $query = "SELECT t.id, t.tenant_code, t.database_name, t.subscription_plan, sp.max_companies, sp.plan_name
                  FROM tenants t
                  INNER JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
                  WHERE t.tenant_code = :tenant_code OR t.database_name = :tenant_code
                  LIMIT 1";

        $stmt = $db_master->prepare($query);
        $stmt->bindParam(':tenant_code', $tenant_code);
        $stmt->execute();
        $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$tenant) {
            return [
                'allowed' => false,
                'current' => 0,
                'max' => 0,
                'plan_name' => 'Inconnu',
                'message' => 'Tenant introuvable pour code: ' . $tenant_code
            ];
        }

        $max_companies = (int) $tenant['max_companies'];
        $plan_name = $tenant['plan_name'];

        // 2. Si illimité (-1), autoriser
        if ($max_companies === -1) {
            return [
                'allowed' => true,
                'current' => 0,
                'max' => -1,
                'plan_name' => $plan_name,
                'message' => 'Sociétés illimitées'
            ];
        }

        // 3. Compter le nombre de sociétés actuelles de l'utilisateur
        // Utiliser database_name au lieu de tenant_code
        $db_name = $tenant['database_name'] ?: $tenant['tenant_code'];

        try {
            // Connexion à la base tenant (utiliser les mêmes credentials que master)
            $dsn = "mysql:host=localhost;dbname=" . $db_name . ";charset=utf8mb4";
            $db_tenant = new PDO($dsn, 'root', 'Abil', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            $count_query = "SELECT COUNT(*) as total FROM companies WHERE user_id = :user_id";
            $count_stmt = $db_tenant->prepare($count_query);
            $count_stmt->bindParam(':user_id', $user_id);
            $count_stmt->execute();
            $result = $count_stmt->fetch(PDO::FETCH_ASSOC);
            $current_companies = (int) $result['total'];

        } catch (PDOException $e) {
            return [
                'allowed' => false,
                'current' => 0,
                'max' => $max_companies,
                'message' => 'Erreur de connexion à la base tenant'
            ];
        }

        // 4. Vérifier si la limite est atteinte
        $allowed = $current_companies < $max_companies;

        $message = $allowed
            ? "Vous pouvez créer " . ($max_companies - $current_companies) . " société(s) supplémentaire(s)"
            : "Limite atteinte. Votre plan '$plan_name' autorise maximum $max_companies société(s). Mettez à niveau votre plan pour créer plus de sociétés.";

        return [
            'allowed' => $allowed,
            'current' => $current_companies,
            'max' => $max_companies,
            'plan_name' => $plan_name,
            'message' => $message
        ];
    }

    /**
     * Obtenir les informations de limite pour un tenant
     *
     * @param PDO $db_master Connexion à la base master
     * @param string $tenant_code Code du tenant
     * @param int $user_id ID de l'utilisateur
     * @return array ['current' => int, 'max' => int, 'plan_name' => string, 'remaining' => int]
     */
    public static function getCompanyLimits($db_master, $tenant_code, $user_id) {
        $check = self::canCreateCompany($db_master, $tenant_code, $user_id);

        $remaining = $check['max'] === -1
            ? 'Illimité'
            : max(0, $check['max'] - $check['current']);

        return [
            'current' => $check['current'],
            'max' => $check['max'],
            'plan_name' => $check['plan_name'] ?? 'Inconnu',
            'remaining' => $remaining,
            'unlimited' => $check['max'] === -1
        ];
    }

    /**
     * Vérifier si un utilisateur a atteint la limite
     *
     * @param PDO $db_master Connexion à la base master
     * @param string $tenant_code Code du tenant
     * @param int $user_id ID de l'utilisateur
     * @return bool
     */
    public static function hasReachedLimit($db_master, $tenant_code, $user_id) {
        $check = self::canCreateCompany($db_master, $tenant_code, $user_id);
        return !$check['allowed'];
    }
}
?>
