<?php
/**
 * Page Mon Compte - Statistiques et informations du tenant
 */

// Charger les classes nécessaires
require_once 'config/database.php';
require_once 'config/database_master.php';

// Vérifier si on est en mode multi-tenant
if (!Database::isMultiTenantMode()) {
    echo "<div class='alert alert-info'>Cette fonctionnalité n'est disponible qu'en mode multi-tenant.</div>";
    return;
}

$tenant_info = Database::getCurrentTenant();

$database = new Database();
$db = $database->getConnection();

$database_master = new DatabaseMaster();
$db_master = $database_master->getConnection();

// Récupérer les informations complètes du tenant
$query = "SELECT t.*, sp.plan_name, sp.price_monthly, sp.max_users, sp.max_transactions_per_month, sp.max_storage_mb
          FROM tenants t
          LEFT JOIN subscription_plans sp ON t.subscription_plan = sp.plan_code
          WHERE t.id = :tenant_id";
$stmt = $db_master->prepare($query);
$stmt->bindParam(':tenant_id', $tenant_info['id']);
$stmt->execute();
$tenant = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculer les statistiques d'utilisation
$stats = [];

// Nombre d'utilisateurs
$stats['users'] = $db->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];

// Transactions ce mois
$stats['transactions_this_month'] = $db->query(
    "SELECT COUNT(*) as count FROM transactions
     WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())"
)->fetch()['count'];

// Transactions totales
$stats['transactions_total'] = $db->query("SELECT COUNT(*) as count FROM transactions")->fetch()['count'];

// Factures
$stats['invoices'] = $db->query("SELECT COUNT(*) as count FROM invoices")->fetch()['count'];

// Contacts
$stats['contacts'] = $db->query("SELECT COUNT(*) as count FROM contacts")->fetch()['count'];

// Comptes comptables
$stats['accounts'] = $db->query("SELECT COUNT(*) as count FROM accounting_plan")->fetch()['count'];

// Calculer la progression de l'essai
$trial_ends_at = new DateTime($tenant['trial_ends_at']);
$now = new DateTime();
$diff = $now->diff($trial_ends_at);
$days_remaining = $diff->days;
$trial_expired = $trial_ends_at < $now;

// Calculer les pourcentages d'utilisation
$percent_users = ($tenant['max_users'] > 0) ? min(100, ($stats['users'] / $tenant['max_users']) * 100) : 0;
$percent_transactions = ($tenant['max_transactions_per_month'] > 0) ? min(100, ($stats['transactions_this_month'] / $tenant['max_transactions_per_month']) * 100) : 0;
?>

<style>
.mon-compte-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.page-title {
    font-size: 2em;
    margin-bottom: 30px;
    color: #2d3748;
}

.account-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.info-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.info-card h3 {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #e2e8f0;
    color: #2d3748;
}

.info-item {
    padding: 12px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #f7fafc;
}

.info-item label {
    font-weight: 600;
    color: #718096;
}

.info-item .value {
    color: #2d3748;
}

.badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85em;
    font-weight: 600;
}

.badge.active {
    background: #c6f6d5;
    color: #22543d;
}

.badge.trial {
    background: #feebc8;
    color: #7c2d12;
}

.badge.suspended {
    background: #fed7d7;
    color: #742a2a;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-box {
    background: white;
    padding: 25px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    text-align: center;
}

.stat-box .icon {
    font-size: 2.5em;
    margin-bottom: 10px;
    opacity: 0.8;
}

.stat-box .value {
    font-size: 2.5em;
    font-weight: 700;
    color: #2d3748;
}

.stat-box .label {
    color: #718096;
    margin-top: 5px;
}

.usage-card {
    background: white;
    padding: 30px;
    border-radius: 15px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.usage-item {
    margin-bottom: 25px;
}

.usage-item:last-child {
    margin-bottom: 0;
}

.usage-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 8px;
}

.usage-header label {
    font-weight: 600;
    color: #4a5568;
}

.usage-header .usage-value {
    color: #667eea;
    font-weight: 600;
}

.progress-bar {
    height: 12px;
    background: #e2e8f0;
    border-radius: 10px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 10px;
    transition: width 0.3s;
}

.progress-fill.low {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
}

.progress-fill.medium {
    background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
}

.progress-fill.high {
    background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%);
}

.trial-warning {
    background: #feebc8;
    border-left: 4px solid #ed8936;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 20px;
}

.trial-warning.expired {
    background: #fed7d7;
    border-left-color: #f56565;
}

.trial-warning h4 {
    color: #7c2d12;
    margin-bottom: 10px;
}

.trial-warning.expired h4 {
    color: #742a2a;
}

@media (max-width: 768px) {
    .account-grid {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="mon-compte-container">
    <h1 class="page-title"><i class="fas fa-user-circle"></i> Mon Compte</h1>

    <?php if ($tenant['status'] === 'trial'): ?>
        <?php if ($trial_expired): ?>
            <div class="trial-warning expired">
                <h4><i class="fas fa-exclamation-triangle"></i> Période d'essai expirée</h4>
                <p>Votre période d'essai a expiré. Souscrivez à un abonnement pour continuer à utiliser tous les services.</p>
            </div>
        <?php else: ?>
            <div class="trial-warning">
                <h4><i class="fas fa-clock"></i> Période d'essai - <?php echo $days_remaining; ?> jours restants</h4>
                <p>Votre période d'essai se termine le <?php echo date('d/m/Y', strtotime($tenant['trial_ends_at'])); ?>.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Statistiques -->
    <div class="stats-grid">
        <div class="stat-box">
            <div class="icon" style="color: #667eea;">
                <i class="fas fa-users"></i>
            </div>
            <div class="value"><?php echo $stats['users']; ?></div>
            <div class="label">Utilisateurs</div>
        </div>

        <div class="stat-box">
            <div class="icon" style="color: #48bb78;">
                <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="value"><?php echo $stats['transactions_this_month']; ?></div>
            <div class="label">Transactions ce Mois</div>
        </div>

        <div class="stat-box">
            <div class="icon" style="color: #ed8936;">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div class="value"><?php echo $stats['invoices']; ?></div>
            <div class="label">Factures</div>
        </div>

        <div class="stat-box">
            <div class="icon" style="color: #9b59b6;">
                <i class="fas fa-address-book"></i>
            </div>
            <div class="value"><?php echo $stats['contacts']; ?></div>
            <div class="label">Contacts</div>
        </div>

        <div class="stat-box">
            <div class="icon" style="color: #3498db;">
                <i class="fas fa-list"></i>
            </div>
            <div class="value"><?php echo $stats['accounts']; ?></div>
            <div class="label">Comptes Comptables</div>
        </div>

        <div class="stat-box">
            <div class="icon" style="color: #e74c3c;">
                <i class="fas fa-history"></i>
            </div>
            <div class="value"><?php echo $stats['transactions_total']; ?></div>
            <div class="label">Total Transactions</div>
        </div>
    </div>

    <div class="account-grid">
        <!-- Informations du compte -->
        <div class="info-card">
            <h3><i class="fas fa-building"></i> Informations du Compte</h3>

            <div class="info-item">
                <label>Entreprise</label>
                <div class="value"><?php echo htmlspecialchars($tenant['company_name']); ?></div>
            </div>

            <div class="info-item">
                <label>Code Tenant</label>
                <div class="value"><code><?php echo $tenant['tenant_code']; ?></code></div>
            </div>

            <div class="info-item">
                <label>Contact</label>
                <div class="value"><?php echo htmlspecialchars($tenant['contact_name']); ?></div>
            </div>

            <div class="info-item">
                <label>Email</label>
                <div class="value"><?php echo htmlspecialchars($tenant['contact_email']); ?></div>
            </div>

            <div class="info-item">
                <label>Statut</label>
                <div class="value">
                    <span class="badge <?php echo $tenant['status']; ?>">
                        <?php echo ucfirst($tenant['status']); ?>
                    </span>
                </div>
            </div>

            <div class="info-item">
                <label>Créé le</label>
                <div class="value"><?php echo date('d/m/Y', strtotime($tenant['created_at'])); ?></div>
            </div>
        </div>

        <!-- Abonnement -->
        <div class="info-card">
            <h3><i class="fas fa-crown"></i> Abonnement</h3>

            <div class="info-item">
                <label>Plan Actuel</label>
                <div class="value"><?php echo htmlspecialchars($tenant['plan_name']); ?></div>
            </div>

            <div class="info-item">
                <label>Prix</label>
                <div class="value"><?php echo number_format($tenant['price_monthly'], 2); ?> CHF/mois</div>
            </div>

            <?php if ($tenant['status'] === 'trial'): ?>
            <div class="info-item">
                <label>Essai jusqu'au</label>
                <div class="value"><?php echo date('d/m/Y', strtotime($tenant['trial_ends_at'])); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Utilisation -->
    <div class="usage-card">
        <h3><i class="fas fa-chart-bar"></i> Utilisation des Ressources</h3>

        <div class="usage-item">
            <div class="usage-header">
                <label>Utilisateurs</label>
                <div class="usage-value">
                    <?php echo $stats['users']; ?> / <?php echo $tenant['max_users'] == 9999 ? '∞' : $tenant['max_users']; ?>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill <?php echo $percent_users > 80 ? 'high' : ($percent_users > 50 ? 'medium' : 'low'); ?>"
                     style="width: <?php echo $percent_users; ?>%"></div>
            </div>
        </div>

        <div class="usage-item">
            <div class="usage-header">
                <label>Transactions ce Mois</label>
                <div class="usage-value">
                    <?php echo $stats['transactions_this_month']; ?> / <?php echo $tenant['max_transactions_per_month'] == 999999 ? '∞' : number_format($tenant['max_transactions_per_month']); ?>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill <?php echo $percent_transactions > 80 ? 'high' : ($percent_transactions > 50 ? 'medium' : 'low'); ?>"
                     style="width: <?php echo $percent_transactions; ?>%"></div>
            </div>
        </div>

        <div class="usage-item">
            <div class="usage-header">
                <label>Stockage</label>
                <div class="usage-value">
                    -- MB / <?php echo $tenant['max_storage_mb'] == 99999 ? '∞' : $tenant['max_storage_mb']; ?> MB
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill low" style="width: 0%"></div>
            </div>
            <small style="color: #718096; margin-top: 5px; display: block;">
                <i class="fas fa-info-circle"></i> Le calcul du stockage sera implémenté prochainement
            </small>
        </div>
    </div>
</div>
