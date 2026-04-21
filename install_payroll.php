<?php
/**
 * Installation du module Salaires/Paie
 * Ce script crée les tables et configure les limites par plan
 */

// Activer l'affichage des erreurs pour le débogage
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Inclure la configuration
require_once 'config/database.php';
require_once 'config/database_master.php';

echo "<!DOCTYPE html>
<html lang='fr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Installation Module Salaires</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 900px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f6fa;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            padding-bottom: 15px;
        }
        h2 {
            color: #555;
            margin-top: 30px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #ffc107;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
            border-left: 4px solid #17a2b8;
        }
        code {
            background: #f4f4f4;
            padding: 3px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            opacity: 0.9;
        }
        ul {
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>📊 Installation du Module Salaires</h1>";

$errors = [];
$success_messages = [];
$warnings = [];

try {
    // ========== 1. VÉRIFIER LA CONNEXION ==========
    echo "<h2>1. Vérification de la connexion à la base de données</h2>";

    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        throw new Exception("Impossible de se connecter à la base de données");
    }

    $success_messages[] = "✅ Connexion à la base de données établie";

    // ========== 2. LIRE ET EXÉCUTER LE FICHIER SQL ==========
    echo "<h2>2. Installation des tables</h2>";

    $sql_file = 'install_payroll_module.sql';

    if (!file_exists($sql_file)) {
        throw new Exception("Le fichier SQL $sql_file n'existe pas!");
    }

    $sql_content = file_get_contents($sql_file);

    if ($sql_content === false) {
        throw new Exception("Impossible de lire le fichier SQL");
    }

    // Séparer les requêtes SQL
    $sql_statements = array_filter(
        array_map('trim',
            preg_split('/;(?=\s*(?:--|CREATE|ALTER|INSERT|UPDATE|DROP|SELECT)[^;]*$)/i',
                $sql_content,
                -1,
                PREG_SPLIT_NO_EMPTY
            )
        ),
        function($stmt) {
            return !empty($stmt) &&
                   !preg_match('/^\s*--/', $stmt) &&
                   !preg_match('/^\s*\/\*.*\*\/\s*$/s', $stmt);
        }
    );

    $executed = 0;
    $failed = 0;

    foreach ($sql_statements as $statement) {
        // Ignorer les commentaires
        if (preg_match('/^\s*--/', $statement) || empty(trim($statement))) {
            continue;
        }

        try {
            $db->exec($statement);
            $executed++;
        } catch (PDOException $e) {
            // Ignorer les erreurs "table already exists" et "duplicate column"
            if (
                strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate column') === false &&
                strpos($e->getMessage(), 'Duplicate key') === false
            ) {
                $warnings[] = "⚠️ Avertissement SQL: " . $e->getMessage();
                $failed++;
            }
        }
    }

    $success_messages[] = "✅ $executed requêtes SQL exécutées avec succès";
    if ($failed > 0) {
        $warnings[] = "⚠️ $failed requêtes ont été ignorées (probablement déjà existantes)";
    }

    // ========== 3. VÉRIFIER LES TABLES CRÉÉES ==========
    echo "<h2>3. Vérification des tables</h2>";

    $required_tables = ['employees', 'payroll', 'payroll_settings', 'time_tracking'];
    $missing_tables = [];

    foreach ($required_tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }

    if (empty($missing_tables)) {
        $success_messages[] = "✅ Toutes les tables ont été créées avec succès: " . implode(', ', $required_tables);
    } else {
        $errors[] = "❌ Tables manquantes: " . implode(', ', $missing_tables);
    }

    // ========== 4. VÉRIFIER subscription_plans.max_employees ==========
    echo "<h2>4. Vérification des plans d'abonnement</h2>";

    $check_column = $db->query("SHOW COLUMNS FROM subscription_plans LIKE 'max_employees'");

    if ($check_column->rowCount() > 0) {
        $success_messages[] = "✅ Colonne max_employees existe dans subscription_plans";

        // Vérifier les valeurs
        $plans = $db->query("SELECT plan_code, plan_name, max_employees FROM subscription_plans")->fetchAll(PDO::FETCH_ASSOC);

        echo "<div class='info'>";
        echo "<strong>Limites par plan:</strong><ul>";
        foreach ($plans as $plan) {
            $max = $plan['max_employees'];
            $display = $max == -1 ? 'Illimité' : $max;
            echo "<li><strong>{$plan['plan_name']}</strong>: {$display} employé(s)</li>";
        }
        echo "</ul></div>";
    } else {
        $warnings[] = "⚠️ La colonne max_employees n'existe pas encore dans subscription_plans";
    }

    // ========== 5. CRÉER LES PARAMÈTRES PAR DÉFAUT ==========
    echo "<h2>5. Initialisation des paramètres</h2>";

    $companies = $db->query("SELECT id FROM companies")->fetchAll(PDO::FETCH_ASSOC);

    if (count($companies) > 0) {
        $initialized = 0;
        foreach ($companies as $company) {
            try {
                $db->exec("INSERT IGNORE INTO payroll_settings (company_id) VALUES ({$company['id']})");
                $initialized++;
            } catch (PDOException $e) {
                // Ignorer si déjà existe
            }
        }
        $success_messages[] = "✅ Paramètres initialisés pour $initialized société(s)";
    } else {
        $warnings[] = "⚠️ Aucune société trouvée. Les paramètres seront créés automatiquement lors de la création de sociétés.";
    }

} catch (Exception $e) {
    $errors[] = "❌ Erreur fatale: " . $e->getMessage();
}

// ========== AFFICHAGE DES RÉSULTATS ==========
echo "<h2>Résumé de l'installation</h2>";

if (!empty($success_messages)) {
    foreach ($success_messages as $msg) {
        echo "<div class='success'>$msg</div>";
    }
}

if (!empty($warnings)) {
    foreach ($warnings as $msg) {
        echo "<div class='warning'>$msg</div>";
    }
}

if (!empty($errors)) {
    foreach ($errors as $msg) {
        echo "<div class='error'>$msg</div>";
    }
}

if (empty($errors)) {
    echo "<div class='success'>";
    echo "<h3>🎉 Installation réussie!</h3>";
    echo "<p>Le module de gestion des salaires a été installé avec succès.</p>";
    echo "<p><strong>Prochaines étapes:</strong></p>";
    echo "<ul>";
    echo "<li>Accédez au menu <strong>Salaires &gt; Employés</strong> pour commencer à ajouter vos employés</li>";
    echo "<li>Configurez les taux de cotisations sociales dans les paramètres si nécessaire</li>";
    echo "<li>Les limites par plan sont:</li>";
    echo "<ul>";
    echo "<li><strong>Gratuit:</strong> Module non disponible</li>";
    echo "<li><strong>Starter:</strong> Jusqu'à 3 employés</li>";
    echo "<li><strong>Professionnel:</strong> Employés illimités</li>";
    echo "<li><strong>Enterprise:</strong> Employés illimités</li>";
    echo "</ul>";
    echo "</ul>";
    echo "<a href='index.php?page=employees' class='btn'>Accéder au module Employés</a>";
    echo "</div>";
} else {
    echo "<div class='error'>";
    echo "<h3>❌ L'installation a échoué</h3>";
    echo "<p>Veuillez corriger les erreurs ci-dessus et réessayer.</p>";
    echo "<p>Si le problème persiste, contactez le support technique.</p>";
    echo "</div>";
}

echo "</div></body></html>";
?>
