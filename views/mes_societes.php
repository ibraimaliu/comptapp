<?php
/**
 * Page: Mes Sociétés
 * Description: Gestion complète des sociétés et exercices comptables
 */

// Inclure les fichiers nécessaires
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/config/database_master.php';
include_once dirname(__DIR__) . '/models/Company.php';
include_once dirname(__DIR__) . '/utils/TenantLimits.php';

// Vérifier si l'utilisateur est connecté
if(!isset($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit();
}

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Récupérer les limites du plan
$company_limits = null;
$can_create = true;

if (isset($_SESSION['tenant_database'])) {
    $database_master = new DatabaseMaster();
    $db_master = $database_master->getConnection();
    // Utiliser tenant_code si disponible, sinon database_name
    $tenant_code = $_SESSION['tenant_code'] ?? $_SESSION['tenant_database'];
    $user_id = $_SESSION['user_id'];

    $company_limits = TenantLimits::getCompanyLimits($db_master, $tenant_code, $user_id);
    $can_create = !TenantLimits::hasReachedLimit($db_master, $tenant_code, $user_id);
}

// Récupérer toutes les sociétés de l'utilisateur
$company = new Company($db);
$stmt = $company->readByUser($_SESSION['user_id']);
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$current_company_id = $_SESSION['company_id'] ?? null;
$current_fiscal_year = $_SESSION['fiscal_year'] ?? date('Y');

// Générer les exercices disponibles (5 dernières années + 2 futures)
$current_year = date('Y');
$fiscal_years = [];
for ($i = -5; $i <= 2; $i++) {
    $year = $current_year + $i;
    $fiscal_years[] = $year;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Sociétés & Exercices</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        .page-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.3);
        }

        .page-header h1 {
            margin: 0 0 10px 0;
            font-size: 2.2em;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 1.1em;
        }

        /* Section Info Plan */
        .plan-info {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .plan-details {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .plan-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.1em;
        }

        .plan-stats {
            display: flex;
            gap: 20px;
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 1.8em;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 0.9em;
            color: #718096;
            margin-top: 5px;
        }

        /* Grille de Sociétés et Exercices */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Liste des Sociétés */
        .companies-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .section-title {
            font-size: 1.5em;
            color: #2d3748;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-create {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-create:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-create:disabled {
            background: #cbd5e0;
            cursor: not-allowed;
        }

        /* Liste des sociétés */
        .companies-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .company-item {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s;
            cursor: pointer;
            position: relative;
        }

        .company-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        }

        .company-item.active {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05) 0%, rgba(118, 75, 162, 0.05) 100%);
        }

        .company-item.active::before {
            content: '✓ ACTIVE';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #48bb78;
            color: white;
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 0.75em;
            font-weight: 700;
        }

        .company-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .company-name {
            font-size: 1.3em;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .company-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 0.9em;
            color: #4a5568;
        }

        .info-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-row i {
            color: #667eea;
            width: 20px;
        }

        .company-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }

        .btn-action {
            flex: 1;
            padding: 10px 16px;
            border: 2px solid #667eea;
            background: white;
            color: #667eea;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-action:hover {
            background: #667eea;
            color: white;
        }

        .btn-action.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .btn-action.primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* Section Exercices Comptables */
        .exercises-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: sticky;
            top: 80px;
        }

        .exercises-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 600px;
            overflow-y: auto;
        }

        .exercise-item {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 15px;
            transition: all 0.3s;
            cursor: pointer;
            text-align: center;
        }

        .exercise-item:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .exercise-item.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .exercise-year {
            font-size: 1.5em;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .exercise-label {
            font-size: 0.9em;
            opacity: 0.8;
        }

        .exercise-item.current {
            border-color: #48bb78;
        }

        .exercise-item.current .exercise-label::after {
            content: ' (Année en cours)';
            font-weight: 700;
        }

        /* Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 5px solid #f59e0b;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 5px solid #3b82f6;
        }

        /* État vide */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-building"></i>
                Mes Sociétés & Exercices Comptables
            </h1>
            <p>Gérez vos sociétés et sélectionnez l'exercice comptable à consulter</p>
        </div>

        <!-- Info Plan d'Abonnement -->
        <?php if ($company_limits): ?>
        <div class="plan-info">
            <div class="plan-details">
                <div class="plan-badge">
                    <i class="fas fa-crown"></i>
                    Plan <?php echo htmlspecialchars($company_limits['plan_name']); ?>
                </div>
                <div class="plan-stats">
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php echo $company_limits['current']; ?><?php if (!$company_limits['unlimited']): ?>/<?php echo $company_limits['max']; ?><?php endif; ?>
                        </div>
                        <div class="stat-label">Sociétés</div>
                    </div>
                    <?php if ($company_limits['unlimited']): ?>
                    <div class="stat-item">
                        <div class="stat-value">∞</div>
                        <div class="stat-label">Illimité</div>
                    </div>
                    <?php else: ?>
                    <div class="stat-item">
                        <div class="stat-value">
                            <?php echo max(0, $company_limits['max'] - $company_limits['current']); ?>
                        </div>
                        <div class="stat-label">Disponibles</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Grille Sociétés + Exercices -->
        <div class="content-grid">
            <!-- Section Sociétés -->
            <div class="companies-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-building"></i>
                        Vos Sociétés (<?php echo count($companies); ?>)
                    </h2>
                    <button
                        class="btn-create"
                        onclick="createNewCompany()"
                        <?php echo !$can_create ? 'disabled' : ''; ?>>
                        <i class="fas fa-plus-circle"></i>
                        Nouvelle Société
                    </button>
                </div>

                <?php if (!$can_create && $company_limits): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Limite atteinte !</strong><br>
                        Vous avez atteint la limite de votre plan (<?php echo $company_limits['max']; ?> sociétés).
                        Mettez à niveau votre abonnement pour créer plus de sociétés.
                    </div>
                </div>
                <?php endif; ?>

                <div class="companies-list">
                    <?php if (empty($companies)): ?>
                        <div class="empty-state">
                            <i class="fas fa-building"></i>
                            <h3>Aucune société</h3>
                            <p>Créez votre première société pour commencer</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($companies as $comp): ?>
                        <div class="company-item <?php echo ($comp['id'] == $current_company_id) ? 'active' : ''; ?>">
                            <div class="company-header">
                                <div>
                                    <div class="company-name">
                                        <?php echo htmlspecialchars($comp['name']); ?>
                                    </div>
                                </div>
                            </div>

                            <div class="company-info">
                                <div class="info-row">
                                    <i class="fas fa-user"></i>
                                    <span><?php echo htmlspecialchars($comp['owner_name'] . ' ' . $comp['owner_surname']); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-calendar"></i>
                                    <span>Du <?php echo date('d/m/Y', strtotime($comp['fiscal_year_start'])); ?> au <?php echo date('d/m/Y', strtotime($comp['fiscal_year_end'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-percent"></i>
                                    <span>TVA: <?php echo htmlspecialchars($comp['tva_status']); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-clock"></i>
                                    <span>Créée le <?php echo date('d/m/Y', strtotime($comp['created_at'])); ?></span>
                                </div>
                            </div>

                            <div class="company-actions">
                                <?php if ($comp['id'] != $current_company_id): ?>
                                <button class="btn-action primary" onclick="switchCompany(<?php echo $comp['id']; ?>)">
                                    <i class="fas fa-exchange-alt"></i>
                                    Activer
                                </button>
                                <?php else: ?>
                                <button class="btn-action" style="background: #48bb78; color: white; border: none;" disabled>
                                    <i class="fas fa-check-circle"></i>
                                    Société Active
                                </button>
                                <?php endif; ?>
                                <button class="btn-action" onclick="window.location.href='index.php?page=parametres'">
                                    <i class="fas fa-cog"></i>
                                    Paramètres
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Section Exercices Comptables -->
            <div class="exercises-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-calendar-days"></i>
                        Exercices
                    </h2>
                </div>

                <?php if (empty($current_company_id)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <span>Sélectionnez une société pour choisir l'exercice</span>
                    </div>
                <?php else: ?>
                    <div class="exercises-list">
                        <?php foreach (array_reverse($fiscal_years) as $year): ?>
                        <div class="exercise-item <?php echo ($year == $current_fiscal_year) ? 'active' : ''; ?> <?php echo ($year == $current_year) ? 'current' : ''; ?>"
                             onclick="switchFiscalYear(<?php echo $year; ?>)">
                            <div class="exercise-year"><?php echo $year; ?></div>
                            <div class="exercise-label">Exercice <?php echo $year; ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function switchCompany(companyId) {
        if (!companyId) return;

        console.log('Changement de société vers ID:', companyId);

        fetch('api/session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'change_company',
                company_id: parseInt(companyId)
            })
        })
        .then(response => response.json())
        .then(data => {
            console.log('Réponse de l\'API:', data);
            if (data.success) {
                console.log('Changement réussi! Ancien:', data.old_company_id, '→ Nouveau:', data.company_id);
                window.location.reload();
            } else {
                alert('Erreur: ' + (data.message || 'Impossible de changer de société'));
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de communication avec le serveur');
        });
    }

    function switchFiscalYear(year) {
        fetch('api/session.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'change_fiscal_year',
                fiscal_year: parseInt(year)
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                console.error('Erreur lors du changement d\'exercice');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
        });
    }

    function createNewCompany() {
        window.location.href = 'index.php?page=society_setup';
    }
    </script>
</body>
</html>
