<?php
/**
 * Test de l'API employees
 */
session_name('COMPTAPP_SESSION');
session_start();

echo "<h1>Test API Employees</h1>";

// Simuler une session
$_SESSION['user_id'] = 1;
$_SESSION['company_id'] = 1;
$_SESSION['tenant_code'] = 'B1548E8D';
$_SESSION['tenant_database'] = 'gestion_comptable_client_b1548e8d';

echo "<h2>Session</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Tester la vérification des limites
echo "<h2>Test EmployeeLimits</h2>";

require_once 'config/database_master.php';
require_once 'utils/EmployeeLimits.php';

$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();

try {
    $limits = EmployeeLimits::canCreateEmployee($db_master, $_SESSION['tenant_code'], $_SESSION['company_id']);
    echo "<pre>";
    print_r($limits);
    echo "</pre>";
} catch (Exception $e) {
    echo "<p style='color:red'>Erreur: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Tester création employé
echo "<h2>Test Création Employé</h2>";

require_once 'config/database.php';
require_once 'models/Employee.php';

$database = new Database();
$db = $database->getConnection();

$employee = new Employee($db);
$employee->company_id = 1;
$employee->first_name = "Test";
$employee->last_name = "Employé";
$employee->email = "test@example.com";
$employee->job_title = "Développeur";
$employee->hire_date = date('Y-m-d');
$employee->base_salary = 5000;
$employee->salary_type = "monthly";
$employee->employment_type = "full_time";
$employee->contract_type = "cdi";
$employee->currency = "CHF";
$employee->hours_per_week = 40;
$employee->country = "Suisse";
$employee->is_active = 1;

try {
    if($employee->create()) {
        echo "<p style='color:green'>✅ Employé créé avec succès! ID: {$employee->id}</p>";

        // Lire l'employé
        $employee2 = new Employee($db);
        $employee2->id = $employee->id;
        $employee2->company_id = 1;

        if($employee2->read()) {
            echo "<h3>Données de l'employé:</h3>";
            echo "<pre>";
            echo "ID: {$employee2->id}\n";
            echo "Nom: {$employee2->first_name} {$employee2->last_name}\n";
            echo "Email: {$employee2->email}\n";
            echo "Poste: {$employee2->job_title}\n";
            echo "Salaire: {$employee2->base_salary} {$employee2->currency}\n";
            echo "</pre>";

            // Supprimer l'employé de test
            $employee2->delete();
            echo "<p>Employé de test supprimé</p>";
        }
    } else {
        echo "<p style='color:red'>❌ Échec de création</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>Erreur: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<hr>";
echo "<a href='index.php?page=employees'>← Retour à la page Employés</a>";
?>
