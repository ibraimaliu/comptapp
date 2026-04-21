/**
 * JavaScript: Gestion des Factures Fournisseurs
 * Version: 1.0
 */

let itemCounter = 1;

/**
 * Changer de tab
 */
function switchTab(tabName) {
    // Remove active class from all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });

    // Remove active class from all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.remove('active');
    });

    // Add active class to clicked tab
    const clickedTab = document.querySelector(`.tab[onclick="switchTab('${tabName}')"]`);
    if (clickedTab) {
        clickedTab.classList.add('active');
    }

    // Show corresponding content
    const content = document.getElementById(`tab-${tabName}`);
    if (content) {
        content.classList.add('active');
    }

    // Load data for specific tabs
    if (tabName === 'received') {
        loadInvoices('received');
    } else if (tabName === 'approved') {
        loadInvoices('approved');
    } else if (tabName !== 'overdue') {
        loadInvoices();
    }
}

/**
 * Charger les factures
 */
function loadInvoices(status = null) {
    const supplierId = document.getElementById('filterSupplier')?.value || '';

    let url = `assets/ajax/supplier_invoices.php?action=list&company_id=${companyId}`;
    if (status) url += `&status=${status}`;
    if (supplierId) url += `&supplier_id=${supplierId}`;

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayInvoices(data.invoices, status);
            } else {
                showNotification('Erreur lors du chargement des factures', 'error');
            }
        })
        .catch(err => {
            console.error('Error loading invoices:', err);
            showNotification('Erreur réseau', 'error');
        });
}

/**
 * Afficher les factures
 */
function displayInvoices(invoices, status = null) {
    let containerId = 'invoicesList';
    if (status === 'received') containerId = 'receivedInvoicesList';
    if (status === 'approved') containerId = 'approvedInvoicesList';

    const container = document.getElementById(containerId);

    if (!invoices || invoices.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <div class="empty-state-text">Aucune facture fournisseur</div>
            </div>
        `;
        return;
    }

    let html = '<div class="invoices-table">';
    html += `
        <div class="table-header">
            <div>N° Facture</div>
            <div>Fournisseur</div>
            <div>Date</div>
            <div>Échéance</div>
            <div>Montant</div>
            <div>Statut</div>
            <div>Actions</div>
        </div>
    `;

    invoices.forEach(invoice => {
        const statusBadges = {
            'received': '<span class="badge badge-received">Reçue</span>',
            'approved': '<span class="badge badge-approved">Approuvée</span>',
            'paid': '<span class="badge badge-paid">Payée</span>',
            'cancelled': '<span class="badge badge-cancelled">Annulée</span>'
        };

        const isPastDue = new Date(invoice.due_date) < new Date() && invoice.status !== 'paid';

        html += `
            <div class="table-row">
                <div><strong>${escapeHtml(invoice.invoice_number)}</strong></div>
                <div>${escapeHtml(invoice.supplier_name)}</div>
                <div>${formatDate(invoice.invoice_date)}</div>
                <div>${formatDate(invoice.due_date)}${isPastDue ? ' <span class="badge badge-overdue">Retard</span>' : ''}</div>
                <div>
                    <strong>${formatAmount(invoice.total)} CHF</strong>
                    ${invoice.amount_paid > 0 ? `<br><small style="color: #10b981;">Payé: ${formatAmount(invoice.amount_paid)} CHF</small>` : ''}
                </div>
                <div>${statusBadges[invoice.status] || ''}</div>
                <div>
                    ${invoice.status === 'received' ? `
                        <button class="btn-action" onclick="approveInvoice(${invoice.id})" title="Approuver">
                            <i class="fas fa-check"></i>
                        </button>
                    ` : ''}
                    ${invoice.status === 'approved' ? `
                        <button class="btn-action" onclick="markAsPaid(${invoice.id})" title="Marquer comme payée">
                            <i class="fas fa-money-bill"></i>
                        </button>
                    ` : ''}
                    <button class="btn-action" onclick="downloadPDF(${invoice.id})" title="Télécharger PDF">
                        <i class="fas fa-file-pdf" style="color: #dc3545;"></i>
                    </button>
                    <button class="btn-action" onclick="viewInvoice(${invoice.id})" title="Voir détails">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${invoice.status === 'received' ? `
                        <button class="btn-action" onclick="deleteInvoice(${invoice.id})" title="Supprimer">
                            <i class="fas fa-trash" style="color: #ef4444;"></i>
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Ouvrir modal création
 */
function openCreateModal() {
    document.getElementById('createInvoiceModal').classList.add('active');
    document.getElementById('createInvoiceForm').reset();
    itemCounter = 1;
}

/**
 * Fermer modal création
 */
function closeCreateModal() {
    document.getElementById('createInvoiceModal').classList.remove('active');
}

/**
 * Ajouter une ligne de facture
 */
function addItem() {
    const container = document.getElementById('invoiceItems');
    const newItem = document.createElement('div');
    newItem.className = 'item-row';
    newItem.innerHTML = `
        <input type="text" placeholder="Description" name="items[${itemCounter}][description]" required>
        <input type="number" placeholder="Qté" name="items[${itemCounter}][quantity]" step="0.01" value="1" required>
        <input type="number" placeholder="Prix unit." name="items[${itemCounter}][unit_price]" step="0.01" required>
        <input type="number" placeholder="TVA %" name="items[${itemCounter}][tva_rate]" step="0.01" value="7.7" required>
        <input type="number" placeholder="Total" name="items[${itemCounter}][total]" step="0.01" readonly>
        <button type="button" class="btn-action" onclick="removeItem(this)"><i class="fas fa-trash"></i></button>
    `;
    container.appendChild(newItem);
    itemCounter++;

    // Add event listeners for calculation
    attachCalculationListeners(newItem);
}

/**
 * Supprimer une ligne
 */
function removeItem(button) {
    const container = document.getElementById('invoiceItems');
    if (container.children.length > 1) {
        button.closest('.item-row').remove();
        calculateTotal();
    } else {
        showNotification('Au moins une ligne est requise', 'warning');
    }
}

/**
 * Attacher les listeners pour le calcul automatique
 */
function attachCalculationListeners(row) {
    const inputs = row.querySelectorAll('input[type="number"]:not([readonly])');
    inputs.forEach(input => {
        input.addEventListener('input', () => calculateItemTotal(row));
    });
}

/**
 * Calculer le total d'une ligne
 */
function calculateItemTotal(row) {
    const qty = parseFloat(row.querySelector('[name*="[quantity]"]').value) || 0;
    const price = parseFloat(row.querySelector('[name*="[unit_price]"]').value) || 0;
    const tvaRate = parseFloat(row.querySelector('[name*="[tva_rate]"]').value) || 0;

    const subtotal = qty * price;
    const tva = subtotal * (tvaRate / 100);
    const total = subtotal + tva;

    row.querySelector('[name*="[total]"]').value = total.toFixed(2);

    calculateTotal();
}

/**
 * Calculer le total général
 */
function calculateTotal() {
    let total = 0;
    document.querySelectorAll('.item-row [name*="[total]"]').forEach(input => {
        total += parseFloat(input.value) || 0;
    });

    document.getElementById('totalAmount').textContent = total.toFixed(2);
}

/**
 * Créer une facture
 */
function createInvoice(event) {
    event.preventDefault();

    const form = event.target;
    const formData = new FormData(form);

    // Construire l'objet items
    const items = [];
    let i = 0;
    while (formData.has(`items[${i}][description]`)) {
        const qty = parseFloat(formData.get(`items[${i}][quantity]`));
        const price = parseFloat(formData.get(`items[${i}][unit_price]`));
        const tvaRate = parseFloat(formData.get(`items[${i}][tva_rate]`));

        const subtotal = qty * price;
        const tvaAmount = subtotal * (tvaRate / 100);
        const total = subtotal + tvaAmount;

        items.push({
            description: formData.get(`items[${i}][description]`),
            quantity: qty,
            unit_price: price,
            tva_rate: tvaRate,
            tva_amount: tvaAmount,
            subtotal: subtotal,
            total: total
        });
        i++;
    }

    // Calculer les totaux
    const subtotal = items.reduce((sum, item) => sum + item.subtotal, 0);
    const tvaAmount = items.reduce((sum, item) => sum + item.tva_amount, 0);
    const total = subtotal + tvaAmount;

    const data = {
        action: 'create',
        company_id: companyId,
        supplier_id: formData.get('supplier_id'),
        invoice_number: formData.get('invoice_number'),
        invoice_date: formData.get('invoice_date'),
        due_date: formData.get('due_date'),
        qr_reference: formData.get('qr_reference'),
        iban: formData.get('iban'),
        notes: formData.get('notes'),
        subtotal: subtotal,
        tva_amount: tvaAmount,
        total: total,
        items: items
    };

    fetch('assets/ajax/supplier_invoices.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Facture créée avec succès!', 'success');
            closeCreateModal();
            loadInvoices();
            location.reload();
        } else {
            showNotification(data.message || 'Erreur lors de la création', 'error');
        }
    })
    .catch(err => {
        console.error('Error creating invoice:', err);
        showNotification('Erreur réseau', 'error');
    });
}

/**
 * Approuver une facture
 */
function approveInvoice(invoiceId) {
    if (!confirm('Approuver cette facture?')) return;

    fetch('assets/ajax/supplier_invoices.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'approve',
            id: invoiceId,
            company_id: companyId,
            user_id: userId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Facture approuvée', 'success');
            loadInvoices();
        } else {
            showNotification(data.message || 'Erreur', 'error');
        }
    });
}

/**
 * Marquer comme payée
 */
function markAsPaid(invoiceId) {
    const paymentDate = prompt('Date de paiement (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
    if (!paymentDate) return;

    fetch('assets/ajax/supplier_invoices.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'mark_paid',
            id: invoiceId,
            company_id: companyId,
            payment_date: paymentDate,
            payment_method: 'bank_transfer'
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Facture marquée comme payée', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message || 'Erreur', 'error');
        }
    });
}

/**
 * Supprimer une facture
 */
function deleteInvoice(invoiceId) {
    if (!confirm('Supprimer cette facture? Cette action est irréversible.')) return;

    fetch('assets/ajax/supplier_invoices.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete',
            id: invoiceId,
            company_id: companyId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Facture supprimée', 'success');
            loadInvoices();
        } else {
            showNotification(data.message || 'Erreur', 'error');
        }
    });
}

/**
 * Voir détails facture
 */
function viewInvoice(invoiceId) {
    // TODO: Implémenter la vue détaillée
    showNotification('Fonctionnalité en développement', 'info');
}

/**
 * Télécharger PDF de la facture
 */
function downloadPDF(invoiceId) {
    window.open(`assets/ajax/generate_supplier_invoice_pdf.php?id=${invoiceId}`, '_blank');
}

/**
 * Utilitaires
 */
function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-CH');
}

function formatAmount(amount) {
    return new Intl.NumberFormat('fr-CH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    const colors = {
        success: '#10b981',
        error: '#ef4444',
        warning: '#f59e0b',
        info: '#3b82f6'
    };

    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${colors[type]};
        color: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        z-index: 10000;
        animation: slideIn 0.3s ease;
        max-width: 400px;
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Initialiser les listeners au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Attacher les listeners pour le formulaire
    const firstRow = document.querySelector('.item-row');
    if (firstRow) {
        attachCalculationListeners(firstRow);
    }
});
