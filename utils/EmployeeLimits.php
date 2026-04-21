<?php
/**
 * Utilitaire EmployeeLimits
 * Vérifie les limites du nombre d'employés selon le plan d'abonnement
 */

class EmployeeLimits {

    /**
     * Vérifier si l'utilisateur peut créer un nouvel employé
     *
     * @param PDO $db_master Connexion à la base master
     * @param string $tenant_code Code du tenant
     * @param int $company_id ID de la société
     * @return array ['allowed' => bool, 'current' => int, 'max' => int, 'plan_name' => string, 'message' => string]
     */
    public static function canCreateEmployee($db_master, $tenant_code, $company_id) {
        try {
            // 1. Récupérer les informations du tenant et son plan
            $query = "SELECT t.id, t.tenant_code, t.database_name, t.subscription_plan,
                             sp.max_employees, sp.plan_name
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
                    'message' => 'Tenant introuvable pour code: ' . $tenant_code,
                    'feature_locked' => true
                ];
            }

            $max_employees = intval($tenant['max_employees']);
            $plan_name = $tenant['plan_name'];

            // 2. Si plan gratuit (max_employees = 0), module bloqué
            if ($max_employees === 0) {
                return [
                    'allowed' => false,
                    'current' => 0,
                    'max' => 0,
                    'plan_name' => $plan_name,
                    'message' => 'Le module de gestion des salaires n\'est pas disponible avec le plan gratuit. Passez au plan Starter ou supérieur.',
                    'feature_locked' => true
                ];
            }

            // 3. Connexion à la base de données du tenant
            $db_name = $tenant['database_name'] ?: $tenant['tenant_code'];
            $dsn = "mysql:host=localhost;dbname=" . $db_name . ";charset=utf8mb4";
            $db_tenant = new PDO($dsn, 'root', 'Abil', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // 4. Compter les employés actifs de cette société
            $count_query = "SELECT COUNT(*) as total
                           FROM employees
                           WHERE company_id = :company_id AND is_active = 1";

            $count_stmt = $db_tenant->prepare($count_query);
            $count_stmt->bindParam(':company_id', $company_id, PDO::PARAM_INT);
            $count_stmt->execute();
            $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
            $current_employees = intval($count_result['total']);

            // 5. Vérifier les limites
            // -1 = illimité
            if ($max_employees === -1) {
                return [
                    'allowed' => true,
                    'current' => $current_employees,
                    'max' => -1,
                    'plan_name' => $plan_name,
                    'message' => 'Employés illimités',
                    'feature_locked' => false
                ];
            }

            // Limite atteinte?
            $allowed = $current_employees < $max_employees;

            return [
                'allowed' => $allowed,
                'current' => $current_employees,
                'max' => $max_employees,
                'plan_name' => $plan_name,
                'message' => $allowed
                    ? "Vous pouvez créer encore " . ($max_employees - $current_employees) . " employé(s)"
                    : "Limite atteinte. Passez à un plan supérieur pour ajouter plus d'employés.",
                'feature_locked' => false
            ];

        } catch (PDOException $e) {
            error_log("Erreur EmployeeLimits::canCreateEmployee: " . $e->getMessage());
            return [
                'allowed' => false,
                'current' => 0,
                'max' => 0,
                'plan_name' => 'Erreur',
                'message' => 'Erreur lors de la vérification des limites: ' . $e->getMessage(),
                'feature_locked' => true
            ];
        }
    }

    /**
     * Vérifier si la limite est atteinte (version simple)
     */
    public static function hasReachedLimit($db_master, $tenant_code, $company_id) {
        $result = self::canCreateEmployee($db_master, $tenant_code, $company_id);
        return !$result['allowed'];
    }

    /**
     * Obtenir les informations de limite pour affichage
     */
    public static function getEmployeeLimits($db_master, $tenant_code, $company_id) {
        return self::canCreateEmployee($db_master, $tenant_code, $company_id);
    }

    /**
     * Vérifier si le module de paie est activé pour ce plan
     */
    public static function isPayrollModuleEnabled($db_master, $tenant_code) {
        try {
            $query = "SELECT sp.max_employees, sp.plan_name
                      FROM tenants t
                      INNER JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
                      WHERE t.tenant_code = :tenant_code OR t.database_name = :tenant_code
                      LIMIT 1";

            $stmt = $db_master->prepare($query);
            $stmt->bindParam(':tenant_code', $tenant_code);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                return false;
            }

            // Module activé si max_employees > 0 ou -1 (illimité)
            return intval($result['max_employees']) !== 0;

        } catch (PDOException $e) {
            error_log("Erreur EmployeeLimits::isPayrollModuleEnabled: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtenir le message d'upgrade pour débloquer le module
     */
    public static function getUpgradeMessage($plan_name) {
        if ($plan_name === 'Gratuit' || $plan_name === 'Free') {
            return "Le module de gestion des salaires n'est disponible qu'à partir du plan Starter. " .
                   "Passez au plan Starter pour gérer jusqu'à 3 employés, " .
                   "ou au plan Professionnel pour un nombre illimité d'employés.";
        }

        return "Votre plan actuel limite le nombre d'employés. " .
               "Passez à un plan supérieur pour ajouter plus d'employés.";
    }
}
?>
