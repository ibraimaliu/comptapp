<?php
/**
 * Page: Dashboard de Trésorerie
 * Description: Suivi et prévisions de trésorerie en temps réel
 */
if(!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

if(!isset($_SESSION['company_id'])) {
    header("Location: index.php?page=society_setup");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard de Trésorerie - Gestion Comptable</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/tresorerie.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body>
    <!-- Header Section -->
    <div class="dashboard-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-chart-line"></i> Dashboard de Trésorerie</h1>
                <p class="subtitle">Suivi et prévisions en temps réel</p>
            </div>
            <div class="header-right">
                <button id="btn-refresh" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Actualiser
                </button>
                <button id="btn-generate" class="btn btn-primary">
                    <i class="fas fa-magic"></i> Générer Prévisions
                </button>
                <button id="btn-settings" class="btn btn-secondary">
                    <i class="fas fa-cog"></i> Paramètres
                </button>
            </div>
        </div>
    </div>

    <!-- Alerts Section -->
    <div id="alerts-container" class="alerts-section">
        <!-- Les alertes seront chargées dynamiquement -->
    </div>

    <!-- Quick Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-balance">
            <div class="stat-icon">
                <i class="fas fa-wallet"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Solde Actuel</div>
                <div class="stat-value" id="current-balance">-</div>
                <div class="stat-change" id="balance-change">-</div>
            </div>
        </div>

        <div class="stat-card stat-income">
            <div class="stat-icon">
                <i class="fas fa-arrow-up"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Recettes Prévues (30j)</div>
                <div class="stat-value" id="expected-income">-</div>
                <div class="stat-info">Moyenne journalière: <span id="daily-income">-</span></div>
            </div>
        </div>

        <div class="stat-card stat-expense">
            <div class="stat-icon">
                <i class="fas fa-arrow-down"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Dépenses Prévues (30j)</div>
                <div class="stat-value" id="expected-expenses">-</div>
                <div class="stat-info">Moyenne journalière: <span id="daily-expenses">-</span></div>
            </div>
        </div>

        <div class="stat-card stat-forecast">
            <div class="stat-icon">
                <i class="fas fa-crystal-ball"></i>
            </div>
            <div class="stat-content">
                <div class="stat-label">Solde Prévu (30j)</div>
                <div class="stat-value" id="forecast-balance">-</div>
                <div class="stat-info">Min: <span id="min-balance">-</span> | Max: <span id="max-balance">-</span></div>
            </div>
        </div>
    </div>

    <!-- Period Selector -->
    <div class="period-selector">
        <label>Période d'affichage:</label>
        <div class="btn-group">
            <button class="btn-period active" data-days="30">30 jours</button>
            <button class="btn-period" data-days="60">60 jours</button>
            <button class="btn-period" data-days="90">90 jours</button>
        </div>
    </div>

    <!-- Main Chart -->
    <div class="chart-container">
        <div class="chart-header">
            <h2><i class="fas fa-chart-area"></i> Évolution de la Trésorerie</h2>
            <div class="chart-legend">
                <span class="legend-item"><span class="legend-color" style="background: #10b981;"></span> Recettes</span>
                <span class="legend-item"><span class="legend-color" style="background: #ef4444;"></span> Dépenses</span>
                <span class="legend-item"><span class="legend-color" style="background: #3b82f6;"></span> Solde</span>
            </div>
        </div>
        <div class="chart-wrapper">
            <canvas id="treasuryChart"></canvas>
        </div>
    </div>

    <!-- Two Column Layout -->
    <div class="two-column-layout">
        <!-- Left Column: Forecast Table -->
        <div class="column">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-table"></i> Détails des Prévisions</h3>
                    <button id="btn-export-table" class="btn btn-sm btn-secondary">
                        <i class="fas fa-download"></i> Export CSV
                    </button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="forecast-table" class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Recettes</th>
                                    <th>Dépenses</th>
                                    <th>Flux Net</th>
                                    <th>Solde</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Données chargées dynamiquement -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Alerts List -->
        <div class="column">
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Alertes Actives</h3>
                    <span class="badge" id="alerts-count">0</span>
                </div>
                <div class="card-body">
                    <div id="alerts-list">
                        <!-- Alertes chargées dynamiquement -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-cog"></i> Paramètres de Trésorerie</h2>
                <button class="btn-close" onclick="closeSettingsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="settings-form">
                    <div class="form-section">
                        <h3>Seuils d'Alerte</h3>

                        <div class="form-group">
                            <label for="min_balance_alert">
                                <i class="fas fa-exclamation-circle"></i> Seuil d'alerte minimum (CHF)
                            </label>
                            <input type="number" id="min_balance_alert" name="min_balance_alert"
                                   step="0.01" value="5000.00" class="form-control">
                            <small>Alerte de niveau "Warning" si le solde descend sous ce seuil</small>
                        </div>

                        <div class="form-group">
                            <label for="critical_balance_alert">
                                <i class="fas fa-exclamation-triangle"></i> Seuil critique (CHF)
                            </label>
                            <input type="number" id="critical_balance_alert" name="critical_balance_alert"
                                   step="0.01" value="1000.00" class="form-control">
                            <small>Alerte de niveau "Critical" si le solde descend sous ce seuil</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Prévisions</h3>

                        <div class="form-group">
                            <label for="forecast_horizon_days">
                                <i class="fas fa-calendar-alt"></i> Horizon de prévision (jours)
                            </label>
                            <select id="forecast_horizon_days" name="forecast_horizon_days" class="form-control">
                                <option value="30">30 jours</option>
                                <option value="60">60 jours</option>
                                <option value="90" selected>90 jours</option>
                                <option value="180">180 jours</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="working_capital_target">
                                <i class="fas fa-bullseye"></i> Fonds de roulement cible (CHF)
                            </label>
                            <input type="number" id="working_capital_target" name="working_capital_target"
                                   step="0.01" placeholder="Optionnel" class="form-control">
                            <small>Montant idéal à maintenir en trésorerie</small>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3>Notifications Email</h3>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="alert_email_enabled" name="alert_email_enabled">
                                <span>Activer les notifications par email</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label for="alert_email_recipients">
                                <i class="fas fa-envelope"></i> Destinataires (séparés par virgules)
                            </label>
                            <input type="text" id="alert_email_recipients" name="alert_email_recipients"
                                   placeholder="email1@example.com, email2@example.com" class="form-control">
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeSettingsModal()">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Sauvegarder
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loading-overlay" class="loading-overlay" style="display: none;">
        <div class="spinner">
            <i class="fas fa-spinner fa-spin fa-3x"></i>
            <p>Chargement...</p>
        </div>
    </div>

    <script src="assets/js/tresorerie.js"></script>
</body>
</html>
