<?php
/**
 * Page: Tableau de Bord / Accueil
 * Version: 4.0 - Dashboard opérationnel avec KPIs réels
 */

// Vérification de la connexion utilisateur
if (!isset($_SESSION['user_id'])) {
    redirect('index.php?page=login');
    exit;
}

// Connexion base de données
require_once dirname(__DIR__) . '/config/database.php';
$database = new Database();
$db = $database->getConnection();

if (!$db) {
    die('<div style="padding:20px;background:#f8d7da;color:#721c24;border-radius:8px;margin:20px;">
        <h3><i class="fas fa-exclamation-circle"></i> Erreur de connexion à la base de données</h3>
    </div>');
}

// Récupération de la société active
$company_id = isset($_SESSION['company_id']) ? (int)$_SESSION['company_id'] : 0;

if (!$company_id) {
    // Pas de société sélectionnée
    $has_company = false;
} else {
    $has_company = true;

    $today      = date('Y-m-d');
    $month_start = date('Y-m-01');
    $month_end   = date('Y-m-t');

    // ─────────────────────────────────────────────
    // KPI 1 : Chiffre d'affaires du mois (revenus)
    // ─────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM transactions
        WHERE company_id = :cid
          AND type = 'income'
          AND date BETWEEN :start AND :end
    ");
    $stmt->execute([':cid' => $company_id, ':start' => $month_start, ':end' => $month_end]);
    $ca_mois = (float)$stmt->fetchColumn();

    // ─────────────────────────────────────────────
    // KPI 2 : Dépenses du mois
    // ─────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM transactions
        WHERE company_id = :cid
          AND type = 'expense'
          AND date BETWEEN :start AND :end
    ");
    $stmt->execute([':cid' => $company_id, ':start' => $month_start, ':end' => $month_end]);
    $depenses_mois = (float)$stmt->fetchColumn();

    // ─────────────────────────────────────────────
    // KPI 3 : Factures impayées (count + total)
    // ─────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT COUNT(*) AS nb, COALESCE(SUM(total), 0) AS montant
        FROM invoices
        WHERE company_id = :cid
          AND status IN ('sent', 'overdue')
    ");
    $stmt->execute([':cid' => $company_id]);
    $row_inv = $stmt->fetch(PDO::FETCH_ASSOC);
    $factures_impayees_count  = (int)$row_inv['nb'];
    $factures_impayees_total  = (float)$row_inv['montant'];

    // ─────────────────────────────────────────────
    // KPI 4 : Devis en attente
    // ─────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT COUNT(*) AS nb, COALESCE(SUM(total), 0) AS montant
        FROM quotes
        WHERE company_id = :cid
          AND status IN ('draft', 'sent')
    ");
    $stmt->execute([':cid' => $company_id]);
    $row_quotes = $stmt->fetch(PDO::FETCH_ASSOC);
    $devis_count  = (int)$row_quotes['nb'];
    $devis_total  = (float)$row_quotes['montant'];

    // ─────────────────────────────────────────────
    // KPI 5 : Solde de trésorerie (all-time)
    // ─────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN type = 'income'  THEN amount ELSE 0 END), 0) AS revenus,
            COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) AS depenses
        FROM transactions
        WHERE company_id = :cid
    ");
    $stmt->execute([':cid' => $company_id]);
    $row_solde = $stmt->fetch(PDO::FETCH_ASSOC);
    $solde_tresorerie = (float)$row_solde['revenus'] - (float)$row_solde['depenses'];
    $total_revenus_all = (float)$row_solde['revenus'];
    $total_depenses_all = (float)$row_solde['depenses'];

    // ─────────────────────────────────────────────
    // Activité récente : 10 dernières transactions
    // ─────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT t.id, t.date, t.description, t.amount, t.type,
               a.number AS account_number, a.name AS account_name
        FROM transactions t
        LEFT JOIN accounting_plan a ON t.account_id = a.id
        WHERE t.company_id = :cid
        ORDER BY t.date DESC, t.id DESC
        LIMIT 10
    ");
    $stmt->execute([':cid' => $company_id]);
    $recent_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─────────────────────────────────────────────
    // Factures en retard (due_date < today, not paid)
    // ─────────────────────────────────────────────
    $stmt = $db->prepare("
        SELECT i.id, i.number, i.date, i.due_date, i.total, i.status,
               c.name AS client_name
        FROM invoices i
        LEFT JOIN contacts c ON i.contact_id = c.id
        WHERE i.company_id = :cid
          AND i.status NOT IN ('paid', 'cancelled')
          AND i.due_date IS NOT NULL
          AND i.due_date < :today
        ORDER BY i.due_date ASC
        LIMIT 10
    ");
    $stmt->execute([':cid' => $company_id, ':today' => $today]);
    $factures_retard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ─────────────────────────────────────────────
    // Compteur de contacts
    // ─────────────────────────────────────────────
    $stmt = $db->prepare("SELECT COUNT(*) FROM contacts WHERE company_id = :cid");
    $stmt->execute([':cid' => $company_id]);
    $nb_contacts = (int)$stmt->fetchColumn();

    // ─────────────────────────────────────────────
    // Nombre de produits
    // ─────────────────────────────────────────────
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE company_id = :cid");
        $stmt->execute([':cid' => $company_id]);
        $nb_produits = (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        $nb_produits = 0;
    }
}
?>

<style>
/* ============================================================
   DASHBOARD HOME — styles scoped à ce fichier
   ============================================================ */
.db-wrap {
    max-width: 1400px;
    margin: 0 auto;
    padding: 24px 20px 40px;
}

/* ---- En-tête ---- */
.db-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 28px 32px;
    color: #fff;
    margin-bottom: 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 8px 30px rgba(102,126,234,.35);
}

.db-hero-text h1 {
    font-size: 1.9em;
    font-weight: 700;
    margin: 0 0 6px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.db-hero-text p {
    margin: 0;
    opacity: .88;
    font-size: 1em;
}

.db-hero-date {
    background: rgba(255,255,255,.18);
    border-radius: 10px;
    padding: 10px 18px;
    font-size: .95em;
    font-weight: 600;
    white-space: nowrap;
}

/* ---- Boutons d'action rapide ---- */
.db-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 28px;
}

.db-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 9px;
    font-weight: 600;
    font-size: .92em;
    text-decoration: none;
    color: #fff;
    transition: transform .2s, box-shadow .2s;
    box-shadow: 0 3px 10px rgba(0,0,0,.15);
}

.db-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(0,0,0,.22);
    color: #fff;
    text-decoration: none;
}

.db-btn-purple  { background: linear-gradient(135deg,#667eea,#764ba2); }
.db-btn-green   { background: linear-gradient(135deg,#11998e,#38ef7d); color:#fff; }
.db-btn-blue    { background: linear-gradient(135deg,#4facfe,#00b4d8); }
.db-btn-orange  { background: linear-gradient(135deg,#f7971e,#ffd200); color:#fff; }
.db-btn-teal    { background: linear-gradient(135deg,#43e97b,#38f9d7); color:#fff; }

/* ---- Grille KPI ---- */
.db-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(230px,1fr));
    gap: 18px;
    margin-bottom: 28px;
}

.db-kpi {
    background: #fff;
    border-radius: 14px;
    padding: 22px 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    position: relative;
    overflow: hidden;
    transition: transform .25s, box-shadow .25s;
}

.db-kpi:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0,0,0,.12);
}

.db-kpi::before {
    content:'';
    position:absolute;
    top:0; left:0; right:0;
    height:4px;
}

.db-kpi.kpi-income::before  { background: linear-gradient(90deg,#11998e,#38ef7d); }
.db-kpi.kpi-expense::before { background: linear-gradient(90deg,#eb3349,#f45c43); }
.db-kpi.kpi-invoice::before { background: linear-gradient(90deg,#f7971e,#ffd200); }
.db-kpi.kpi-quote::before   { background: linear-gradient(90deg,#4facfe,#00f2fe); }
.db-kpi.kpi-balance::before { background: linear-gradient(90deg,#667eea,#764ba2); }
.db-kpi.kpi-contact::before { background: linear-gradient(90deg,#fa709a,#fee140); }

.db-kpi-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 14px;
}

.db-kpi-label {
    font-size: .82em;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #718096;
}

.db-kpi-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15em;
}

.db-kpi.kpi-income  .db-kpi-icon { background:#c6f6d5; color:#22543d; }
.db-kpi.kpi-expense .db-kpi-icon { background:#fed7d7; color:#742a2a; }
.db-kpi.kpi-invoice .db-kpi-icon { background:#feebc8; color:#7c2d12; }
.db-kpi.kpi-quote   .db-kpi-icon { background:#bee3f8; color:#2c5282; }
.db-kpi.kpi-balance .db-kpi-icon { background:#e9d8fd; color:#44337a; }
.db-kpi.kpi-contact .db-kpi-icon { background:#fed7e2; color:#702459; }

.db-kpi-value {
    font-size: 1.85em;
    font-weight: 800;
    color: #2d3748;
    line-height: 1;
    margin-bottom: 6px;
}

.db-kpi-value.positive { color: #38a169; }
.db-kpi-value.negative { color: #e53e3e; }

.db-kpi-sub {
    font-size: .82em;
    color: #a0aec0;
}

/* ---- Grille 2 colonnes ---- */
.db-two-col {
    display: grid;
    grid-template-columns: 1.6fr 1fr;
    gap: 20px;
    margin-bottom: 28px;
}

/* ---- Panneaux ---- */
.db-panel {
    background: #fff;
    border-radius: 14px;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    overflow: hidden;
}

.db-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 22px 14px;
    border-bottom: 2px solid #f0f4f8;
}

.db-panel-header h2 {
    margin: 0;
    font-size: 1.1em;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 9px;
}

.db-panel-header a {
    font-size: .85em;
    font-weight: 600;
    color: #667eea;
    text-decoration: none;
    white-space: nowrap;
}

.db-panel-header a:hover { color: #764ba2; }

/* ---- Liste transactions ---- */
.db-tx-list {
    padding: 10px 10px;
}

.db-tx-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 12px;
    border-radius: 9px;
    transition: background .18s;
}

.db-tx-item:hover { background: #f7fafc; }

.db-tx-dot {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1em;
    flex-shrink: 0;
}

.db-tx-dot.income  { background: #c6f6d5; color: #22543d; }
.db-tx-dot.expense { background: #fed7d7; color: #742a2a; }

.db-tx-info { flex: 1; min-width: 0; }

.db-tx-desc {
    font-weight: 600;
    font-size: .92em;
    color: #2d3748;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.db-tx-date {
    font-size: .78em;
    color: #a0aec0;
    margin-top: 2px;
}

.db-tx-amount {
    font-weight: 700;
    font-size: .95em;
    white-space: nowrap;
}

.db-tx-amount.income  { color: #38a169; }
.db-tx-amount.expense { color: #e53e3e; }

/* ---- Factures en retard ---- */
.db-overdue-list { padding: 8px 12px 12px; }

.db-overdue-item {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px;
    border-radius: 9px;
    margin-bottom: 6px;
    background: #fff5f5;
    border-left: 4px solid #e53e3e;
    transition: background .18s;
}

.db-overdue-item:hover { background: #fed7d7; }

.db-overdue-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    background: #fed7d7;
    color: #742a2a;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: .95em;
}

.db-overdue-info { flex: 1; min-width: 0; }

.db-overdue-num {
    font-weight: 700;
    font-size: .9em;
    color: #2d3748;
}

.db-overdue-client {
    font-size: .8em;
    color: #718096;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.db-overdue-right { text-align: right; flex-shrink: 0; }

.db-overdue-amount {
    font-weight: 800;
    font-size: .95em;
    color: #e53e3e;
}

.db-overdue-days {
    font-size: .75em;
    color: #e53e3e;
    margin-top: 2px;
}

/* ---- Vide ---- */
.db-empty {
    text-align: center;
    padding: 30px 20px;
    color: #a0aec0;
}

.db-empty i { font-size: 2.2em; margin-bottom: 10px; display:block; }
.db-empty p { margin: 0; font-size: .9em; }

/* ---- Badge ---- */
.db-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: .72em;
    font-weight: 700;
    text-transform: uppercase;
    margin-left: 6px;
}

.db-badge.alert { background: #fed7d7; color: #742a2a; }
.db-badge.info  { background: #bee3f8; color: #2c5282; }

/* ---- Responsive ---- */
@media (max-width: 1100px) {
    .db-two-col { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .db-kpi-grid { grid-template-columns: repeat(2,1fr); }
    .db-hero { padding: 20px; }
    .db-hero-text h1 { font-size: 1.4em; }
}

@media (max-width: 480px) {
    .db-kpi-grid { grid-template-columns: 1fr; }
    .db-actions { flex-direction: column; }
    .db-btn { width: 100%; justify-content: center; }
}
</style>

<div class="db-wrap">

    <!-- ════════════════════════════════ HERO ════════════════════════════════ -->
    <div class="db-hero">
        <div class="db-hero-text">
            <h1><i class="fas fa-chart-line"></i> Tableau de bord</h1>
            <p>Vue d'ensemble de votre activité comptable <?php echo $has_company ? '— ' . date('F Y') : ''; ?></p>
        </div>
        <div class="db-hero-date">
            <i class="fas fa-calendar-day"></i>
            <?php echo date('d/m/Y'); ?>
        </div>
    </div>

    <?php if (!$has_company): ?>
    <!-- ════ Pas de société ════ -->
    <div class="db-panel" style="text-align:center;padding:60px 20px;">
        <i class="fas fa-building" style="font-size:3.5em;color:#cbd5e0;margin-bottom:16px;display:block;"></i>
        <h2 style="color:#4a5568;margin-bottom:8px;">Aucune société sélectionnée</h2>
        <p style="color:#718096;margin-bottom:24px;">Sélectionnez ou créez une société pour afficher votre tableau de bord.</p>
        <a href="index.php?page=parametres" class="db-btn db-btn-purple">
            <i class="fas fa-plus"></i> Créer une société
        </a>
    </div>

    <?php else: ?>

    <!-- ════════════════════════════ BOUTONS ACTIONS ════════════════════════════ -->
    <div class="db-actions">
        <a href="index.php?page=comptabilite" class="db-btn db-btn-purple">
            <i class="fas fa-plus-circle"></i> Nouvelle transaction
        </a>
        <a href="index.php?page=comptabilite&tab=factures" class="db-btn db-btn-green">
            <i class="fas fa-file-invoice"></i> Nouvelle facture
        </a>
        <a href="index.php?page=comptabilite&tab=devis" class="db-btn db-btn-blue">
            <i class="fas fa-file-alt"></i> Nouveau devis
        </a>
        <a href="index.php?page=adresses" class="db-btn db-btn-orange">
            <i class="fas fa-user-plus"></i> Nouveau contact
        </a>
    </div>

    <!-- ════════════════════════════ KPI CARDS ════════════════════════════ -->
    <div class="db-kpi-grid">

        <!-- CA du mois -->
        <div class="db-kpi kpi-income">
            <div class="db-kpi-header">
                <span class="db-kpi-label">CA du mois</span>
                <div class="db-kpi-icon"><i class="fas fa-arrow-trend-up"></i></div>
            </div>
            <div class="db-kpi-value positive">
                <?php echo number_format($ca_mois, 2, '.', "'"); ?> CHF
            </div>
            <div class="db-kpi-sub">Revenus <?php echo date('F'); ?></div>
        </div>

        <!-- Dépenses du mois -->
        <div class="db-kpi kpi-expense">
            <div class="db-kpi-header">
                <span class="db-kpi-label">Dépenses du mois</span>
                <div class="db-kpi-icon"><i class="fas fa-arrow-trend-down"></i></div>
            </div>
            <div class="db-kpi-value negative">
                <?php echo number_format($depenses_mois, 2, '.', "'"); ?> CHF
            </div>
            <div class="db-kpi-sub">Charges <?php echo date('F'); ?></div>
        </div>

        <!-- Solde de trésorerie -->
        <div class="db-kpi kpi-balance">
            <div class="db-kpi-header">
                <span class="db-kpi-label">Solde trésorerie</span>
                <div class="db-kpi-icon"><i class="fas fa-coins"></i></div>
            </div>
            <div class="db-kpi-value <?php echo $solde_tresorerie >= 0 ? 'positive' : 'negative'; ?>">
                <?php echo number_format($solde_tresorerie, 2, '.', "'"); ?> CHF
            </div>
            <div class="db-kpi-sub">
                Rev. <?php echo number_format($total_revenus_all, 0, '.', "'"); ?>
                &nbsp;/&nbsp;
                Dép. <?php echo number_format($total_depenses_all, 0, '.', "'"); ?>
            </div>
        </div>

        <!-- Factures impayées -->
        <div class="db-kpi kpi-invoice">
            <div class="db-kpi-header">
                <span class="db-kpi-label">Factures impayées</span>
                <div class="db-kpi-icon"><i class="fas fa-exclamation-circle"></i></div>
            </div>
            <div class="db-kpi-value <?php echo $factures_impayees_count > 0 ? 'negative' : ''; ?>">
                <?php echo $factures_impayees_count; ?>
                <?php if ($factures_impayees_count > 0): ?>
                    <span class="db-badge alert">!</span>
                <?php endif; ?>
            </div>
            <div class="db-kpi-sub">
                <?php echo number_format($factures_impayees_total, 2, '.', "'"); ?> CHF en attente
            </div>
        </div>

        <!-- Devis en attente -->
        <div class="db-kpi kpi-quote">
            <div class="db-kpi-header">
                <span class="db-kpi-label">Devis en attente</span>
                <div class="db-kpi-icon"><i class="fas fa-file-signature"></i></div>
            </div>
            <div class="db-kpi-value">
                <?php echo $devis_count; ?>
                <?php if ($devis_count > 0): ?>
                    <span class="db-badge info"><?php echo number_format($devis_total, 0, '.', "'"); ?></span>
                <?php endif; ?>
            </div>
            <div class="db-kpi-sub">
                <?php echo number_format($devis_total, 2, '.', "'"); ?> CHF potentiels
            </div>
        </div>

        <!-- Contacts -->
        <div class="db-kpi kpi-contact">
            <div class="db-kpi-header">
                <span class="db-kpi-label">Contacts</span>
                <div class="db-kpi-icon"><i class="fas fa-address-book"></i></div>
            </div>
            <div class="db-kpi-value"><?php echo $nb_contacts; ?></div>
            <div class="db-kpi-sub">
                <?php echo $nb_produits > 0 ? $nb_produits . ' produits enregistrés' : 'clients &amp; fournisseurs'; ?>
            </div>
        </div>

    </div>

    <!-- ════════════════════════ GRILLE 2 COLONNES ════════════════════════ -->
    <div class="db-two-col">

        <!-- Activité récente -->
        <div class="db-panel">
            <div class="db-panel-header">
                <h2><i class="fas fa-history" style="color:#667eea;"></i> Activité récente</h2>
                <a href="index.php?page=comptabilite">Tout voir <i class="fas fa-arrow-right"></i></a>
            </div>

            <?php if (empty($recent_transactions)): ?>
                <div class="db-empty">
                    <i class="fas fa-inbox"></i>
                    <p>Aucune transaction enregistrée.<br>
                    <a href="index.php?page=comptabilite" style="color:#667eea;">Ajouter une transaction</a></p>
                </div>
            <?php else: ?>
                <div class="db-tx-list">
                    <?php foreach ($recent_transactions as $tx): ?>
                        <div class="db-tx-item">
                            <div class="db-tx-dot <?php echo htmlspecialchars($tx['type']); ?>">
                                <i class="fas fa-<?php echo ($tx['type'] === 'income') ? 'arrow-up' : 'arrow-down'; ?>"></i>
                            </div>
                            <div class="db-tx-info">
                                <div class="db-tx-desc"><?php echo htmlspecialchars($tx['description'] ?: '—'); ?></div>
                                <div class="db-tx-date">
                                    <i class="far fa-calendar-alt"></i>
                                    <?php echo date('d.m.Y', strtotime($tx['date'])); ?>
                                    <?php if (!empty($tx['account_number'])): ?>
                                        &nbsp;&bull;&nbsp;<?php echo htmlspecialchars($tx['account_number'] . ' ' . $tx['account_name']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="db-tx-amount <?php echo htmlspecialchars($tx['type']); ?>">
                                <?php echo $tx['type'] === 'income' ? '+' : '-'; ?>
                                <?php echo number_format((float)$tx['amount'], 2, '.', "'"); ?> CHF
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Factures en retard -->
        <div class="db-panel">
            <div class="db-panel-header">
                <h2>
                    <i class="fas fa-clock" style="color:#e53e3e;"></i>
                    Factures en retard
                    <?php if (!empty($factures_retard)): ?>
                        <span class="db-badge alert"><?php echo count($factures_retard); ?></span>
                    <?php endif; ?>
                </h2>
                <a href="index.php?page=comptabilite&tab=factures">Voir factures</a>
            </div>

            <?php if (empty($factures_retard)): ?>
                <div class="db-empty">
                    <i class="fas fa-check-circle" style="color:#38a169;"></i>
                    <p>Aucune facture en retard.<br>Bonne gestion !</p>
                </div>
            <?php else: ?>
                <div class="db-overdue-list">
                    <?php foreach ($factures_retard as $inv):
                        $days_late = (int)floor((strtotime($today) - strtotime($inv['due_date'])) / 86400);
                    ?>
                        <div class="db-overdue-item">
                            <div class="db-overdue-icon">
                                <i class="fas fa-file-invoice"></i>
                            </div>
                            <div class="db-overdue-info">
                                <div class="db-overdue-num">
                                    <a href="index.php?page=comptabilite&tab=factures&id=<?php echo (int)$inv['id']; ?>"
                                       style="color:#2d3748;text-decoration:none;">
                                        <?php echo htmlspecialchars($inv['number'] ?: '#' . $inv['id']); ?>
                                    </a>
                                </div>
                                <div class="db-overdue-client">
                                    <?php echo htmlspecialchars($inv['client_name'] ?: 'Client inconnu'); ?>
                                </div>
                                <div class="db-overdue-client">
                                    Éch. <?php echo date('d.m.Y', strtotime($inv['due_date'])); ?>
                                </div>
                            </div>
                            <div class="db-overdue-right">
                                <div class="db-overdue-amount">
                                    <?php echo number_format((float)$inv['total'], 2, '.', "'"); ?> CHF
                                </div>
                                <div class="db-overdue-days">
                                    <?php echo $days_late; ?> j. retard
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- ════════════════════════ ACCÈS RAPIDES ════════════════════════ -->
    <div class="db-panel">
        <div class="db-panel-header">
            <h2><i class="fas fa-bolt" style="color:#f7971e;"></i> Accès rapides</h2>
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;padding:18px;">

            <a href="index.php?page=comptabilite" style="text-decoration:none;">
                <div style="text-align:center;padding:22px 12px;border-radius:12px;background:#f0eeff;transition:background .2s;" onmouseover="this.style.background='#ddd6fe'" onmouseout="this.style.background='#f0eeff'">
                    <div style="font-size:1.8em;margin-bottom:10px;color:#667eea;"><i class="fas fa-exchange-alt"></i></div>
                    <div style="font-weight:700;color:#2d3748;font-size:.92em;">Transactions</div>
                    <div style="font-size:.78em;color:#718096;margin-top:4px;">Revenus &amp; dépenses</div>
                </div>
            </a>

            <a href="index.php?page=comptabilite&tab=factures" style="text-decoration:none;">
                <div style="text-align:center;padding:22px 12px;border-radius:12px;background:#f0fdf4;transition:background .2s;" onmouseover="this.style.background='#bbf7d0'" onmouseout="this.style.background='#f0fdf4'">
                    <div style="font-size:1.8em;margin-bottom:10px;color:#38a169;"><i class="fas fa-file-invoice-dollar"></i></div>
                    <div style="font-weight:700;color:#2d3748;font-size:.92em;">Factures</div>
                    <div style="font-size:.78em;color:#718096;margin-top:4px;">Créer &amp; gérer</div>
                </div>
            </a>

            <a href="index.php?page=comptabilite&tab=devis" style="text-decoration:none;">
                <div style="text-align:center;padding:22px 12px;border-radius:12px;background:#eff6ff;transition:background .2s;" onmouseover="this.style.background='#bfdbfe'" onmouseout="this.style.background='#eff6ff'">
                    <div style="font-size:1.8em;margin-bottom:10px;color:#4facfe;"><i class="fas fa-file-alt"></i></div>
                    <div style="font-weight:700;color:#2d3748;font-size:.92em;">Devis</div>
                    <div style="font-size:.78em;color:#718096;margin-top:4px;">Offres &amp; propositions</div>
                </div>
            </a>

            <a href="index.php?page=adresses" style="text-decoration:none;">
                <div style="text-align:center;padding:22px 12px;border-radius:12px;background:#fff5f0;transition:background .2s;" onmouseover="this.style.background='#fed7aa'" onmouseout="this.style.background='#fff5f0'">
                    <div style="font-size:1.8em;margin-bottom:10px;color:#f7971e;"><i class="fas fa-address-book"></i></div>
                    <div style="font-weight:700;color:#2d3748;font-size:.92em;">Contacts</div>
                    <div style="font-size:.78em;color:#718096;margin-top:4px;">Clients &amp; fournisseurs</div>
                </div>
            </a>

            <a href="index.php?page=comptabilite&tab=plan" style="text-decoration:none;">
                <div style="text-align:center;padding:22px 12px;border-radius:12px;background:#fdf4ff;transition:background .2s;" onmouseover="this.style.background='#f3d0fe'" onmouseout="this.style.background='#fdf4ff'">
                    <div style="font-size:1.8em;margin-bottom:10px;color:#9f7aea;"><i class="fas fa-book"></i></div>
                    <div style="font-weight:700;color:#2d3748;font-size:.92em;">Plan comptable</div>
                    <div style="font-size:.78em;color:#718096;margin-top:4px;">Comptes &amp; rubriques</div>
                </div>
            </a>

            <a href="index.php?page=parametres" style="text-decoration:none;">
                <div style="text-align:center;padding:22px 12px;border-radius:12px;background:#f7f8f9;transition:background .2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f7f8f9'">
                    <div style="font-size:1.8em;margin-bottom:10px;color:#718096;"><i class="fas fa-cog"></i></div>
                    <div style="font-weight:700;color:#2d3748;font-size:.92em;">Paramètres</div>
                    <div style="font-size:.78em;color:#718096;margin-top:4px;">Configuration</div>
                </div>
            </a>

        </div>
    </div>

    <?php endif; ?>
</div>
