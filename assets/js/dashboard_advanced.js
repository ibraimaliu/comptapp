/**
 * Script: Tableau de Bord Avancé
 * Description: Gestion des graphiques et analytiques
 * Version: 1.0
 */

let currentPeriod = 30;
let charts = {};

// Charger les données au démarrage
document.addEventListener('DOMContentLoaded', function() {
    loadAllData();
});

/**
 * Changer la période
 */
function changePeriod(days) {
    currentPeriod = days;

    // Mettre à jour les boutons
    document.querySelectorAll('.period-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');

    // Recharger les données
    loadAllData();
}

/**
 * Charger toutes les données
 */
function loadAllData() {
    loadKPIs();
    loadEvolutionChart();
    loadCategoriesChart();
    loadCashFlowChart();
    loadTopClients();
    loadTopSuppliers();
}

/**
 * Charger les KPIs
 */
function loadKPIs() {
    fetch(`assets/ajax/dashboard_analytics.php?action=summary&period=${currentPeriod}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayKPIs(data.data);
            } else {
                console.error('Erreur:', data.message);
            }
        })
        .catch(error => console.error('Error loading KPIs:', error));
}

/**
 * Afficher les KPIs
 */
function displayKPIs(data) {
    // Revenus
    document.getElementById('kpiIncome').textContent = `CHF ${formatNumber(data.income.current)}`;
    updateVariationBadge('kpiIncomeVariation', data.income.variation);

    // Dépenses
    document.getElementById('kpiExpense').textContent = `CHF ${formatNumber(data.expenses.current)}`;
    updateVariationBadge('kpiExpenseVariation', data.expenses.variation);

    // Bénéfice
    document.getElementById('kpiProfit').textContent = `CHF ${formatNumber(data.profit.current)}`;
    const profitVariation = data.income.previous > 0
        ? ((data.profit.current - data.profit.previous) / Math.abs(data.profit.previous)) * 100
        : 0;
    updateVariationBadge('kpiProfitVariation', profitVariation);

    // Factures impayées
    document.getElementById('kpiUnpaidCount').textContent = data.unpaid_invoices.count;
    document.getElementById('kpiUnpaidAmount').textContent = `CHF ${formatNumber(data.unpaid_invoices.amount)}`;
}

/**
 * Mettre à jour le badge de variation
 */
function updateVariationBadge(elementId, variation) {
    const element = document.getElementById(elementId);
    const isPositive = variation >= 0;

    element.className = `kpi-variation ${isPositive ? 'positive' : 'negative'}`;
    element.innerHTML = `
        <i class="fa-solid fa-arrow-${isPositive ? 'up' : 'down'}"></i>
        ${Math.abs(variation).toFixed(1)}%
    `;
}

/**
 * Charger le graphique d'évolution
 */
function loadEvolutionChart() {
    fetch(`assets/ajax/dashboard_analytics.php?action=evolution&period=${currentPeriod}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayEvolutionChart(data.data);
            }
        })
        .catch(error => console.error('Error loading evolution chart:', error));
}

/**
 * Afficher le graphique d'évolution
 */
function displayEvolutionChart(data) {
    const ctx = document.getElementById('evolutionChart');

    // Préparer les données
    const allDates = new Set();
    data.income.forEach(item => allDates.add(item.day));
    data.expenses.forEach(item => allDates.add(item.day));

    const dates = Array.from(allDates).sort();

    const incomeMap = new Map(data.income.map(item => [item.day, parseFloat(item.amount)]));
    const expenseMap = new Map(data.expenses.map(item => [item.day, parseFloat(item.amount)]));

    const incomeData = dates.map(date => incomeMap.get(date) || 0);
    const expenseData = dates.map(date => expenseMap.get(date) || 0);

    // Détruire l'ancien graphique si existant
    if (charts.evolution) {
        charts.evolution.destroy();
    }

    // Créer le graphique
    charts.evolution = new Chart(ctx, {
        type: 'line',
        data: {
            labels: dates.map(date => formatDate(date)),
            datasets: [
                {
                    label: 'Revenus',
                    data: incomeData,
                    borderColor: '#38ef7d',
                    backgroundColor: 'rgba(56, 239, 125, 0.1)',
                    fill: true,
                    tension: 0.4
                },
                {
                    label: 'Dépenses',
                    data: expenseData,
                    borderColor: '#f5576c',
                    backgroundColor: 'rgba(245, 87, 108, 0.1)',
                    fill: true,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    callbacks: {
                        label: function(context) {
                            return context.dataset.label + ': CHF ' + formatNumber(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'CHF ' + formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Charger le graphique des catégories
 */
function loadCategoriesChart() {
    fetch(`assets/ajax/dashboard_analytics.php?action=categories&period=${currentPeriod}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCategoriesChart(data.data);
            }
        })
        .catch(error => console.error('Error loading categories chart:', error));
}

/**
 * Afficher le graphique des catégories
 */
function displayCategoriesChart(data) {
    const ctx = document.getElementById('categoriesChart');

    // Détruire l'ancien graphique
    if (charts.categories) {
        charts.categories.destroy();
    }

    // Préparer les données
    const categories = data.expenses.map(item => item.category || 'Sans catégorie');
    const amounts = data.expenses.map(item => parseFloat(item.amount));

    // Générer des couleurs
    const colors = generateColors(categories.length);

    charts.categories = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: categories,
            datasets: [{
                data: amounts,
                backgroundColor: colors,
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': CHF ' + formatNumber(context.parsed) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

/**
 * Charger le graphique de flux de trésorerie
 */
function loadCashFlowChart() {
    fetch(`assets/ajax/dashboard_analytics.php?action=cash_flow&period=${currentPeriod}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayCashFlowChart(data.data);
            }
        })
        .catch(error => console.error('Error loading cash flow chart:', error));
}

/**
 * Afficher le graphique de flux de trésorerie
 */
function displayCashFlowChart(data) {
    const ctx = document.getElementById('cashFlowChart');

    if (charts.cashFlow) {
        charts.cashFlow.destroy();
    }

    const labels = data.map(item => formatDate(item.week_start));
    const balances = data.map(item => parseFloat(item.balance));

    charts.cashFlow = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Solde cumulé',
                data: balances,
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'Solde: CHF ' + formatNumber(context.parsed.y);
                        }
                    }
                }
            },
            scales: {
                y: {
                    ticks: {
                        callback: function(value) {
                            return 'CHF ' + formatNumber(value);
                        }
                    }
                }
            }
        }
    });
}

/**
 * Charger le top clients
 */
function loadTopClients() {
    fetch(`assets/ajax/dashboard_analytics.php?action=top_clients&period=${currentPeriod}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRanking('topClientsRanking', data.data);
            }
        })
        .catch(error => console.error('Error loading top clients:', error));
}

/**
 * Charger le top fournisseurs
 */
function loadTopSuppliers() {
    fetch(`assets/ajax/dashboard_analytics.php?action=top_suppliers&period=${currentPeriod}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayRanking('topSuppliersRanking', data.data);
            }
        })
        .catch(error => console.error('Error loading top suppliers:', error));
}

/**
 * Afficher un classement
 */
function displayRanking(containerId, data) {
    const container = document.getElementById(containerId);

    if (!data || data.length === 0) {
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Aucune donnée disponible</p>';
        return;
    }

    container.innerHTML = data.map((item, index) => `
        <div class="ranking-item">
            <div class="ranking-position">${index + 1}</div>
            <div class="ranking-info">
                <div class="ranking-name">${item.name || 'Inconnu'}</div>
                <div class="ranking-count">${item.invoice_count} facture(s)</div>
            </div>
            <div class="ranking-amount">CHF ${formatNumber(parseFloat(item.total_amount))}</div>
        </div>
    `).join('');
}

/**
 * Formater un nombre
 */
function formatNumber(value) {
    return parseFloat(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, "'");
}

/**
 * Formater une date
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-CH', {
        day: '2-digit',
        month: '2-digit'
    });
}

/**
 * Générer des couleurs pour le graphique
 */
function generateColors(count) {
    const baseColors = [
        '#667eea', '#764ba2', '#f093fb', '#f5576c',
        '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
        '#fa709a', '#fee140', '#30cfd0', '#330867'
    ];

    const colors = [];
    for (let i = 0; i < count; i++) {
        colors.push(baseColors[i % baseColors.length]);
    }
    return colors;
}
