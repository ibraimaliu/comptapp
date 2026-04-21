<?php
/**
 * Vue: Historique des Paiements
 * Description: Liste de tous les paiements fournisseurs et clients
 */

// Vérifier l'accès
if (!isset($_SESSION['company_id'])) {
    redirect('index.php?page=login');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historique des Paiements</title>
    <style>
        .payments-container {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e0e0e0;
        }

        .page-header h1 {
            font-size: 2em;
            color: #333;
            margin: 0;
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .stat-card.supplier {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.customer {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 0.9em;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 2em;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-card .label {
            font-size: 0.85em;
            opacity: 0.8;
        }

        /* Filters */
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 500;
            margin-bottom: 5px;
            color: #555;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1em;
        }

        .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn-filter,
        .btn-reset {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.3s;
        }

        .btn-filter {
            background: #667eea;
            color: white;
        }

        .btn-filter:hover {
            background: #5568d3;
        }

        .btn-reset {
            background: #e0e0e0;
            color: #333;
        }

        .btn-reset:hover {
            background: #d0d0d0;
        }

        /* Payments Table */
        .payments-table-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .payments-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payments-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .payments-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .payments-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: background-color 0.2s;
        }

        .payments-table tbody tr:hover {
            background-color: #f8f9ff;
        }

        .payments-table td {
            padding: 12px 15px;
        }

        .payment-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
        }

        .payment-type-supplier {
            background: #ffe0e0;
            color: #c00;
        }

        .payment-type-customer {
            background: #e0ffe0;
            color: #0a0;
        }

        .payment-method-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 5px;
            font-size: 0.85em;
            background: #f0f0f0;
            color: #555;
        }

        .amount-out {
            color: #c00;
            font-weight: 600;
        }

        .amount-in {
            color: #0a0;
            font-weight: 600;
        }

        .no-payments {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #667eea;
        }

        .loading i {
            font-size: 2em;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .payments-table-container {
                overflow-x: auto;
            }

            .payments-table {
                min-width: 800px;
            }
        }
    </style>
</head>
<body>
    <div class="payments-container">
        <div class="page-header">
            <h1><i class="fa-solid fa-money-bill-wave"></i> Historique des Paiements</h1>
        </div>

        <!-- Statistics -->
        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <h3>Total Paiements</h3>
                <div class="value" id="totalPayments">0</div>
                <div class="label">paiements enregistrés</div>
            </div>
            <div class="stat-card supplier">
                <h3>Paiements Fournisseurs</h3>
                <div class="value" id="supplierPayments">CHF 0.00</div>
                <div class="label">montant total</div>
            </div>
            <div class="stat-card customer">
                <h3>Paiements Clients</h3>
                <div class="value" id="customerPayments">CHF 0.00</div>
                <div class="label">montant total</div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-section">
            <div class="filters-grid">
                <div class="filter-group">
                    <label>Type de paiement</label>
                    <select id="filterType">
                        <option value="">Tous les types</option>
                        <option value="supplier_payment">Paiements fournisseurs</option>
                        <option value="customer_payment">Paiements clients</option>
                        <option value="other">Autres</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Méthode de paiement</label>
                    <select id="filterMethod">
                        <option value="">Toutes les méthodes</option>
                        <option value="bank_transfer">Virement bancaire</option>
                        <option value="cash">Espèces</option>
                        <option value="card">Carte</option>
                        <option value="check">Chèque</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Date de début</label>
                    <input type="date" id="filterDateFrom">
                </div>
                <div class="filter-group">
                    <label>Date de fin</label>
                    <input type="date" id="filterDateTo">
                </div>
                <div class="filter-actions">
                    <button class="btn-filter" onclick="applyFilters()">
                        <i class="fa-solid fa-filter"></i> Filtrer
                    </button>
                    <button class="btn-reset" onclick="resetFilters()">
                        <i class="fa-solid fa-rotate-left"></i> Réinitialiser
                    </button>
                </div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="payments-table-container">
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Contact</th>
                        <th>Référence</th>
                        <th>Méthode</th>
                        <th>Montant</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody id="paymentsTableBody">
                    <tr>
                        <td colspan="7" class="loading">
                            <i class="fa-solid fa-spinner"></i>
                            Chargement des paiements...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // Charger les paiements au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            loadPayments();
            loadStatistics();
        });

        /**
         * Charger les paiements
         */
        function loadPayments() {
            const filters = {
                type: document.getElementById('filterType').value,
                method: document.getElementById('filterMethod').value,
                date_from: document.getElementById('filterDateFrom').value,
                date_to: document.getElementById('filterDateTo').value
            };

            // Construire les paramètres URL
            const params = new URLSearchParams();
            if (filters.type) params.append('payment_type', filters.type);
            if (filters.method) params.append('payment_method', filters.method);
            if (filters.date_from) params.append('date_from', filters.date_from);
            if (filters.date_to) params.append('date_to', filters.date_to);

            fetch(`assets/ajax/payments.php?action=list&${params.toString()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayPayments(data.payments);
                    } else {
                        console.error('Erreur:', data.message);
                        document.getElementById('paymentsTableBody').innerHTML = `
                            <tr>
                                <td colspan="7" class="no-payments">
                                    <i class="fa-solid fa-exclamation-circle"></i>
                                    Erreur: ${data.message}
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('paymentsTableBody').innerHTML = `
                        <tr>
                            <td colspan="7" class="no-payments">
                                <i class="fa-solid fa-exclamation-triangle"></i>
                                Erreur de chargement
                            </td>
                        </tr>
                    `;
                });
        }

        /**
         * Afficher les paiements
         */
        function displayPayments(payments) {
            const tbody = document.getElementById('paymentsTableBody');

            if (!payments || payments.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="no-payments">
                            <i class="fa-solid fa-info-circle"></i>
                            Aucun paiement trouvé
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = payments.map(payment => {
                const typeClass = payment.payment_type === 'supplier_payment' ? 'payment-type-supplier' : 'payment-type-customer';
                const typeLabel = getPaymentTypeLabel(payment.payment_type);
                const methodLabel = getPaymentMethodLabel(payment.payment_method);
                const amountClass = payment.payment_type === 'supplier_payment' ? 'amount-out' : 'amount-in';
                const amountPrefix = payment.payment_type === 'supplier_payment' ? '-' : '+';

                return `
                    <tr>
                        <td>${formatDate(payment.payment_date)}</td>
                        <td><span class="payment-type-badge ${typeClass}">${typeLabel}</span></td>
                        <td>${payment.contact_name || '-'}</td>
                        <td>${payment.reference || '-'}</td>
                        <td><span class="payment-method-badge">${methodLabel}</span></td>
                        <td class="${amountClass}">${amountPrefix} CHF ${parseFloat(payment.amount).toFixed(2)}</td>
                        <td>${payment.description || '-'}</td>
                    </tr>
                `;
            }).join('');
        }

        /**
         * Charger les statistiques
         */
        function loadStatistics() {
            fetch('assets/ajax/payments.php?action=statistics')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.statistics) {
                        const stats = data.statistics;
                        document.getElementById('totalPayments').textContent = stats.total_count || 0;
                        document.getElementById('supplierPayments').textContent = `CHF ${parseFloat(stats.supplier_total || 0).toFixed(2)}`;
                        document.getElementById('customerPayments').textContent = `CHF ${parseFloat(stats.customer_total || 0).toFixed(2)}`;
                    }
                })
                .catch(error => console.error('Error loading statistics:', error));
        }

        /**
         * Appliquer les filtres
         */
        function applyFilters() {
            loadPayments();
        }

        /**
         * Réinitialiser les filtres
         */
        function resetFilters() {
            document.getElementById('filterType').value = '';
            document.getElementById('filterMethod').value = '';
            document.getElementById('filterDateFrom').value = '';
            document.getElementById('filterDateTo').value = '';
            loadPayments();
        }

        /**
         * Obtenir le label du type de paiement
         */
        function getPaymentTypeLabel(type) {
            const types = {
                'supplier_payment': 'Fournisseur',
                'customer_payment': 'Client',
                'other': 'Autre'
            };
            return types[type] || type;
        }

        /**
         * Obtenir le label de la méthode de paiement
         */
        function getPaymentMethodLabel(method) {
            const methods = {
                'bank_transfer': 'Virement',
                'cash': 'Espèces',
                'card': 'Carte',
                'check': 'Chèque',
                'other': 'Autre'
            };
            return methods[method] || method;
        }

        /**
         * Formater une date
         */
        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('fr-CH', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
        }
    </script>
</body>
</html>
