/**
 * Dashboard de Trésorerie - JavaScript
 */

let treasuryChart = null;
let currentPeriod = 30;

// ============================================
// Initialisation
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard de Trésorerie chargé');

    // Charger les données initiales
    loadDashboardData();

    // Event listeners
    document.getElementById('btn-refresh').addEventListener('click', () => {
        loadDashboardData();
    });

    document.getElementById('btn-generate').addEventListener('click', () => {
        generateForecasts();
    });

    document.getElementById('btn-settings').addEventListener('click', () => {
        openSettingsModal();
    });

    document.getElementById('settings-form').addEventListener('submit', (e) => {
        e.preventDefault();
        saveSettings();
    });

    // Period selector
    document.querySelectorAll('.btn-period').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.btn-period').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentPeriod = parseInt(this.dataset.days);
            loadDashboardData();
        });
    });
});

// ============================================
// Chargement des données
// ============================================
function loadDashboardData() {
    showLoading();

    fetch(`assets/ajax/treasury_dashboard.php?action=get_dashboard_data&days=${currentPeriod}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                updateStats(data.data.stats);
                updateChart(data.data.forecasts);
                updateForecastTable(data.data.forecasts);
                updateAlerts(data.data.alerts);
                updateAlertCounts(data.data.alert_counts);
            } else {
                showError('Erreur lors du chargement des données: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Erreur de connexion au serveur');
        })
        .finally(() => {
            hideLoading();
        });
}

// ============================================
// Mise à jour des statistiques
// ============================================
function updateStats(stats) {
    if (!stats) return;

    // Solde actuel (dernier closing_balance des prévisions)
    const currentBalance = stats.max_balance || 0;
    document.getElementById('current-balance').textContent = formatCurrency(currentBalance);

    // Recettes prévues
    const totalIncome = parseFloat(stats.total_income) || 0;
    const dailyIncome = totalIncome / currentPeriod;
    document.getElementById('expected-income').textContent = formatCurrency(totalIncome);
    document.getElementById('daily-income').textContent = formatCurrency(dailyIncome);

    // Dépenses prévues
    const totalExpenses = parseFloat(stats.total_expenses) || 0;
    const dailyExpenses = totalExpenses / currentPeriod;
    document.getElementById('expected-expenses').textContent = formatCurrency(totalExpenses);
    document.getElementById('daily-expenses').textContent = formatCurrency(dailyExpenses);

    // Solde prévu
    const forecastBalance = currentBalance + totalIncome - totalExpenses;
    document.getElementById('forecast-balance').textContent = formatCurrency(forecastBalance);
    document.getElementById('min-balance').textContent = formatCurrency(stats.min_balance || 0);
    document.getElementById('max-balance').textContent = formatCurrency(stats.max_balance || 0);

    // Change indicator
    const change = forecastBalance - currentBalance;
    const changeEl = document.getElementById('balance-change');
    if (change > 0) {
        changeEl.textContent = `+${formatCurrency(change)} (${currentPeriod}j)`;
        changeEl.className = 'stat-change positive';
    } else if (change < 0) {
        changeEl.textContent = `${formatCurrency(change)} (${currentPeriod}j)`;
        changeEl.className = 'stat-change negative';
    } else {
        changeEl.textContent = 'Stable';
        changeEl.className = 'stat-change';
    }
}

// ============================================
// Mise à jour du graphique
// ============================================
function updateChart(forecasts) {
    if (!forecasts || forecasts.length === 0) {
        return;
    }

    const ctx = document.getElementById('treasuryChart');
    if (!ctx) return;

    // Préparer les données
    const labels = forecasts.map(f => formatDateShort(f.forecast_date));
    const incomeData = forecasts.map(f => parseFloat(f.expected_income));
    const expenseData = forecasts.map(f => parseFloat(f.expected_expenses));
    const balanceData = forecasts.map(f => parseFloat(f.closing_balance));

    // Détruire l'ancien graphique
    if (treasuryChart) {
        treasuryChart.destroy();
    }

    // Créer le nouveau graphique
    treasuryChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Recettes',
                    data: incomeData,
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Dépenses',
                    data: expenseData,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Solde',
                    data: balanceData,
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            label += formatCurrency(context.parsed.y);
                            return label;
                        }
                    }
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Flux (CHF)'
                    },
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Solde (CHF)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        callback: function(value) {
                            return formatCurrency(value);
                        }
                    }
                }
            }
        }
    });
}

// ============================================
// Mise à jour du tableau
// ============================================
function updateForecastTable(forecasts) {
    const tbody = document.querySelector('#forecast-table tbody');
    if (!tbody) return;

    tbody.innerHTML = '';

    if (!forecasts || forecasts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Aucune prévision disponible</td></tr>';
        return;
    }

    forecasts.forEach(forecast => {
        const income = parseFloat(forecast.expected_income);
        const expense = parseFloat(forecast.expected_expenses);
        const netFlow = income - expense;
        const balance = parseFloat(forecast.closing_balance);

        // Déterminer le statut
        let status = 'healthy';
        let statusText = 'Sain';
        if (balance < 0) {
            status = 'negative';
            statusText = 'Négatif';
        } else if (balance < 1000) {
            status = 'critical';
            statusText = 'Critique';
        } else if (balance < 5000) {
            status = 'warning';
            statusText = 'Attention';
        }

        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${formatDate(forecast.forecast_date)}</td>
            <td style="color: #10b981;">${formatCurrency(income)}</td>
            <td style="color: #ef4444;">${formatCurrency(expense)}</td>
            <td style="color: ${netFlow >= 0 ? '#10b981' : '#ef4444'};">
                ${netFlow >= 0 ? '+' : ''}${formatCurrency(netFlow)}
            </td>
            <td style="font-weight: 600;">${formatCurrency(balance)}</td>
            <td><span class="status-badge status-${status}">${statusText}</span></td>
        `;
        tbody.appendChild(row);
    });
}

// ============================================
// Mise à jour des alertes
// ============================================
function updateAlerts(alerts) {
    const alertsList = document.getElementById('alerts-list');
    const alertsContainer = document.getElementById('alerts-container');

    if (!alerts || alerts.length === 0) {
        alertsList.innerHTML = '<p style="text-align: center; color: #6b7280;">Aucune alerte active</p>';
        alertsContainer.innerHTML = '';
        return;
    }

    // Alertes dans la section principale (top 3)
    const topAlerts = alerts.slice(0, 3);
    alertsContainer.innerHTML = topAlerts.map(alert => `
        <div class="alert alert-${alert.severity}">
            <div class="alert-content">
                <div class="alert-icon">
                    <i class="fas fa-${getAlertIcon(alert.alert_type)}"></i>
                </div>
                <div class="alert-message">
                    <div class="alert-title">${getAlertTitle(alert.alert_type)}</div>
                    <div>${alert.message}</div>
                </div>
            </div>
            <div class="alert-actions">
                <button class="btn btn-sm btn-success" onclick="resolveAlert(${alert.id})">
                    <i class="fas fa-check"></i>
                </button>
                <button class="btn btn-sm btn-secondary" onclick="ignoreAlert(${alert.id})">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
    `).join('');

    // Toutes les alertes dans la liste
    alertsList.innerHTML = alerts.map(alert => `
        <div class="alert-item ${alert.severity}">
            <div class="alert-item-header">
                <div class="alert-item-title">
                    <i class="fas fa-${getAlertIcon(alert.alert_type)}"></i>
                    ${getAlertTitle(alert.alert_type)}
                </div>
                <div class="alert-item-date">${formatDateTime(alert.alert_date)}</div>
            </div>
            <div class="alert-item-message">${alert.message}</div>
            <div class="alert-item-actions">
                <button class="btn btn-sm btn-success" onclick="resolveAlert(${alert.id})">
                    <i class="fas fa-check"></i> Résoudre
                </button>
                <button class="btn btn-sm btn-secondary" onclick="ignoreAlert(${alert.id})">
                    <i class="fas fa-times"></i> Ignorer
                </button>
            </div>
        </div>
    `).join('');
}

function updateAlertCounts(counts) {
    const total = (counts.critical || 0) + (counts.warning || 0) + (counts.info || 0);
    document.getElementById('alerts-count').textContent = total;
}

// ============================================
// Actions sur les alertes
// ============================================
function resolveAlert(alertId) {
    if (!confirm('Marquer cette alerte comme résolue ?')) return;

    fetch('assets/ajax/treasury_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'resolve_alert',
            id: alertId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showSuccess('Alerte résolue');
            loadDashboardData();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showError('Erreur de connexion');
    });
}

function ignoreAlert(alertId) {
    if (!confirm('Ignorer cette alerte ?')) return;

    fetch('assets/ajax/treasury_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'ignore_alert',
            id: alertId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showSuccess('Alerte ignorée');
            loadDashboardData();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showError('Erreur de connexion');
    });
}

// ============================================
// Génération des prévisions
// ============================================
function generateForecasts() {
    if (!confirm('Générer de nouvelles prévisions ? Cela écrasera les prévisions existantes.')) return;

    showLoading();

    fetch('assets/ajax/treasury_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'generate_forecasts'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showSuccess(`${data.count} prévisions générées avec succès`);
            loadDashboardData();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showError('Erreur de connexion');
    })
    .finally(() => {
        hideLoading();
    });
}

// ============================================
// Modal des paramètres
// ============================================
function openSettingsModal() {
    showLoading();

    fetch('assets/ajax/treasury_dashboard.php?action=get_settings')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                const settings = data.data;
                document.getElementById('min_balance_alert').value = settings.min_balance_alert || 5000;
                document.getElementById('critical_balance_alert').value = settings.critical_balance_alert || 1000;
                document.getElementById('forecast_horizon_days').value = settings.forecast_horizon_days || 90;
                document.getElementById('alert_email_enabled').checked = settings.alert_email_enabled == 1;
                document.getElementById('alert_email_recipients').value = settings.alert_email_recipients || '';
                document.getElementById('working_capital_target').value = settings.working_capital_target || '';

                document.getElementById('settings-modal').classList.add('active');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Erreur lors du chargement des paramètres');
        })
        .finally(() => {
            hideLoading();
        });
}

function closeSettingsModal() {
    document.getElementById('settings-modal').classList.remove('active');
}

function saveSettings() {
    const formData = {
        action: 'save_settings',
        min_balance_alert: parseFloat(document.getElementById('min_balance_alert').value),
        critical_balance_alert: parseFloat(document.getElementById('critical_balance_alert').value),
        forecast_horizon_days: parseInt(document.getElementById('forecast_horizon_days').value),
        alert_email_enabled: document.getElementById('alert_email_enabled').checked,
        alert_email_recipients: document.getElementById('alert_email_recipients').value,
        working_capital_target: document.getElementById('working_capital_target').value || null
    };

    showLoading();

    fetch('assets/ajax/treasury_dashboard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showSuccess('Paramètres sauvegardés');
            closeSettingsModal();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showError('Erreur de connexion');
    })
    .finally(() => {
        hideLoading();
    });
}

// ============================================
// Utilitaires
// ============================================
function getAlertIcon(type) {
    const icons = {
        'low_balance': 'exclamation-circle',
        'negative_forecast': 'exclamation-triangle',
        'overdue_invoices': 'clock',
        'large_expense': 'arrow-down'
    };
    return icons[type] || 'info-circle';
}

function getAlertTitle(type) {
    const titles = {
        'low_balance': 'Solde Bas',
        'negative_forecast': 'Solde Négatif Prévu',
        'overdue_invoices': 'Factures en Retard',
        'large_expense': 'Dépense Importante'
    };
    return titles[type] || 'Alerte';
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-CH', {
        style: 'currency',
        currency: 'CHF'
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-CH', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

function formatDateShort(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-CH', {
        day: '2-digit',
        month: '2-digit'
    });
}

function formatDateTime(dateTimeString) {
    const date = new Date(dateTimeString);
    return date.toLocaleDateString('fr-CH', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function showLoading() {
    document.getElementById('loading-overlay').style.display = 'flex';
}

function hideLoading() {
    document.getElementById('loading-overlay').style.display = 'none';
}

function showSuccess(message) {
    alert(message);
}

function showError(message) {
    alert('Erreur: ' + message);
}
