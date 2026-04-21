<?php
/**
 * Script de migration pour ajouter les limites de sociétés par plan d'abonnement
 */

require_once 'config/database_master.php';

echo "=== Ajout des limites de sociétés par plan ===\n\n";

try {
    $database = new DatabaseMaster();
    $db = $database->getConnection();

    // 1. Vérifier si la colonne max_companies existe déjà
    echo "Vérification de la colonne max_companies...\n";

    $check_query = "SHOW COLUMNS FROM subscription_plans LIKE 'max_companies'";
    $stmt = $db->query($check_query);
    $column_exists = $stmt->rowCount() > 0;

    if ($column_exists) {
        echo "✓ La colonne max_companies existe déjà\n\n";
    } else {
        echo "➜ Ajout de la colonne max_companies...\n";

        $alter_query = "ALTER TABLE subscription_plans
                        ADD COLUMN max_companies INT DEFAULT 1
                        COMMENT 'Nombre maximum de sociétés autorisées (-1 = illimité)'";

        $db->exec($alter_query);
        echo "✓ Colonne max_companies ajoutée avec succès\n\n";
    }

    // 2. Mettre à jour les limites pour chaque plan
    echo "Configuration des limites par plan:\n";
    echo "-----------------------------------\n";

    $plans_limits = [
        'free' => 1,
        'starter' => 3,
        'professional' => -1,  // -1 = illimité
        'enterprise' => -1     // -1 = illimité
    ];

    foreach ($plans_limits as $plan_code => $max_companies) {
        $update_query = "UPDATE subscription_plans
                        SET max_companies = :max_companies
                        WHERE plan_code = :plan_code";

        $stmt = $db->prepare($update_query);
        $stmt->bindParam(':max_companies', $max_companies);
        $stmt->bindParam(':plan_code', $plan_code);
        $stmt->execute();

        $limit_text = $max_companies == -1 ? 'Illimité' : $max_companies;
        echo "✓ Plan '$plan_code': $limit_text société(s)\n";
    }

    echo "\n";

    // 3. Afficher la configuration actuelle
    echo "Configuration actuelle des plans:\n";
    echo "=================================\n";

    $query = "SELECT plan_code, plan_name, max_companies, monthly_price
              FROM subscription_plans
              ORDER BY monthly_price ASC";

    $stmt = $db->query($query);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($plans as $plan) {
        $limit_text = $plan['max_companies'] == -1 ? 'Illimité' : $plan['max_companies'];
        $price = number_format($plan['monthly_price'], 2);

        echo sprintf(
            "%-20s | Max: %-10s | Prix: %s CHF/mois\n",
            $plan['plan_name'],
            $limit_text,
            $price
        );
    }

    echo "\n";

    // 4. Statistiques des tenants actuels
    echo "Statistiques des tenants par plan:\n";
    echo "==================================\n";

    $stats_query = "SELECT
                        sp.plan_name,
                        sp.max_companies,
                        COUNT(t.id) as tenant_count,
                        SUM((SELECT COUNT(*) FROM companies WHERE user_id IN
                            (SELECT id FROM users WHERE email = t.contact_email))) as total_companies
                    FROM subscription_plans sp
                    LEFT JOIN tenants t ON sp.plan_code = t.subscription_plan
                    GROUP BY sp.plan_code, sp.plan_name, sp.max_companies
                    ORDER BY sp.monthly_price ASC";

    $stmt = $db->query($stats_query);
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($stats as $stat) {
        $limit_text = $stat['max_companies'] == -1 ? 'Illimité' : $stat['max_companies'];

        echo sprintf(
            "%-20s | Limite: %-10s | Tenants: %d | Sociétés totales: %d\n",
            $stat['plan_name'],
            $limit_text,
            $stat['tenant_count'] ?? 0,
            $stat['total_companies'] ?? 0
        );
    }

    echo "\n✅ Migration terminée avec succès!\n";

} catch (PDOException $e) {
    echo "❌ Erreur: " . $e->getMessage() . "\n";
    exit(1);
}
?>
