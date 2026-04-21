/**
 * Script: Alertes Factures en Retard
 * Description: Affiche les alertes pour les factures fournisseurs en retard
 * Version: 1.0
 */

/**
 * Charger les alertes de factures en retard
 */
function loadOverdueAlerts() {
    fetch('assets/ajax/overdue_alerts.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayOverdueAlerts(data);
            } else {
                console.error('Erreur:', data.message);
            }
        })
        .catch(error => {
            console.error('Error loading overdue alerts:', error);
        });
}

/**
 * Afficher les alertes
 */
function displayOverdueAlerts(data) {
    const container = document.getElementById('overdueAlertsContainer');
    if (!container) return;

    if (data.count === 0) {
        container.innerHTML = `
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i>
                <span>Aucune facture en retard</span>
            </div>
        `;
        return;
    }

    // Créer le widget d'alerte
    const html = `
        <div class="overdue-alert-widget">
            <div class="alert-header">
                <h3><i class="fa-solid fa-exclamation-triangle"></i> Factures en Retard</h3>
                <span class="badge badge-danger">${data.count}</span>
            </div>

            <div class="alert-summary">
                <div class="summary-item">
                    <span class="label">Total dû:</span>
                    <span class="value danger">CHF ${data.total_amount.toFixed(2)}</span>
                </div>
                ${data.critical_count > 0 ? `
                <div class="summary-item critical">
                    <span class="label">Critiques (>30j):</span>
                    <span class="value">${data.critical_count}</span>
                </div>
                ` : ''}
            </div>

            <div class="alert-list">
                ${data.invoices.slice(0, 5).map(invoice => `
                    <div class="alert-item ${invoice.days_overdue > 30 ? 'critical' : ''}">
                        <div class="invoice-info">
                            <strong>${invoice.supplier_name}</strong>
                            <span class="invoice-number">${invoice.invoice_number}</span>
                        </div>
                        <div class="invoice-details">
                            <span class="days-overdue">${invoice.days_overdue} jours</span>
                            <span class="amount">CHF ${parseFloat(invoice.amount_due).toFixed(2)}</span>
                        </div>
                    </div>
                `).join('')}
            </div>

            ${data.count > 5 ? `
            <div class="alert-footer">
                <a href="index.php?page=supplier_invoices" class="btn-view-all">
                    Voir toutes les factures en retard
                    <i class="fa-solid fa-arrow-right"></i>
                </a>
            </div>
            ` : ''}
        </div>
    `;

    container.innerHTML = html;
}

/**
 * Initialiser le widget au chargement de la page
 */
if (document.getElementById('overdueAlertsContainer')) {
    document.addEventListener('DOMContentLoaded', loadOverdueAlerts);

    // Rafraîchir toutes les 5 minutes
    setInterval(loadOverdueAlerts, 300000);
}
