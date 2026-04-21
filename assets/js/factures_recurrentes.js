/**
 * Factures Récurrentes et Abonnements - JavaScript
 */

let contacts = [];
let currentRecurring = null;
let currentSubscription = null;

// ============================================
// Initialisation
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    console.log('Factures Récurrentes chargées');

    // Charger les contacts
    loadContacts();

    // Charger les données initiales
    loadRecurringInvoices();
    loadRecurringStats();

    // Tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            switchTab(tabName);
        });
    });

    // Recherche
    document.getElementById('search-recurring').addEventListener('input', filterRecurringTable);
    document.getElementById('search-subscriptions').addEventListener('input', filterSubscriptionsTable);

    // Dates par défaut
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('start-date').value = today;
    document.getElementById('next-generation-date').value = today;
    document.getElementById('sub-start-date').value = today;

    const nextMonth = new Date();
    nextMonth.setMonth(nextMonth.getMonth() + 1);
    document.getElementById('sub-period-end').value = nextMonth.toISOString().split('T')[0];
});

// ============================================
// Gestion des Tabs
// ============================================
function switchTab(tabName) {
    // Changer l'onglet actif
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelector(`[data-tab="${tabName}"]`).classList.add('active');

    // Afficher le contenu correspondant
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    document.getElementById(`tab-${tabName}`).classList.add('active');

    // Charger les données si nécessaire
    if (tabName === 'subscriptions') {
        loadSubscriptions();
        loadSubscriptionStats();
    }
}

// ============================================
// Chargement des Contacts
// ============================================
function loadContacts() {
    fetch('assets/ajax/contacts.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                contacts = data.contacts;
                populateContactSelects();
            }
        })
        .catch(error => {
            console.error('Erreur chargement contacts:', error);
        });
}

function populateContactSelects() {
    const select1 = document.getElementById('contact-id');
    const select2 = document.getElementById('sub-contact-id');

    [select1, select2].forEach(select => {
        select.innerHTML = '<option value="">Sélectionner...</option>';
        contacts.forEach(contact => {
            const option = document.createElement('option');
            option.value = contact.id;
            option.textContent = contact.name || `${contact.first_name} ${contact.last_name}`;
            select.appendChild(option);
        });
    });
}

// ============================================
// FACTURES RÉCURRENTES
// ============================================
function loadRecurringInvoices() {
    fetch('assets/ajax/recurring_invoices.php?action=list_recurring')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayRecurringInvoices(data.data);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Erreur de chargement');
        });
}

function loadRecurringStats() {
    fetch('assets/ajax/recurring_invoices.php?action=get_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const stats = data.data;
                document.getElementById('recurring-total').textContent = stats.total || 0;
                document.getElementById('recurring-active').textContent = stats.active || 0;
                document.getElementById('recurring-paused').textContent = stats.paused || 0;
                document.getElementById('recurring-generated').textContent = stats.total_generated || 0;
            }
        })
        .catch(error => console.error('Erreur stats:', error));
}

function displayRecurringInvoices(invoices) {
    const tbody = document.getElementById('recurring-tbody');

    if (!invoices || invoices.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>Aucune facture récurrente</h3>
                    <p>Créez votre première facture récurrente pour automatiser vos facturations</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = invoices.map(inv => `
        <tr>
            <td><strong>${escapeHtml(inv.template_name)}</strong></td>
            <td>${escapeHtml(inv.contact_name || '-')}</td>
            <td><span class="frequency-badge">${getFrequencyLabel(inv.frequency)}</span></td>
            <td>${formatDate(inv.next_generation_date)}${inv.days_until_next !== null ? ` <small>(${inv.days_until_next}j)</small>` : ''}</td>
            <td><strong>${formatCurrency(inv.estimated_total || 0)}</strong></td>
            <td><span class="badge badge-${inv.status}">${getStatusLabel(inv.status)}</span></td>
            <td>
                <div class="action-buttons">
                    <button class="btn btn-sm btn-secondary" onclick="editRecurring(${inv.id})" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    ${inv.status === 'active' ? `
                        <button class="btn btn-sm btn-success" onclick="generateInvoice(${inv.id})" title="Générer maintenant">
                            <i class="fas fa-bolt"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="pauseRecurring(${inv.id})" title="Mettre en pause">
                            <i class="fas fa-pause"></i>
                        </button>
                    ` : ''}
                    ${inv.status === 'paused' ? `
                        <button class="btn btn-sm btn-success" onclick="activateRecurring(${inv.id})" title="Activer">
                            <i class="fas fa-play"></i>
                        </button>
                    ` : ''}
                    <button class="btn btn-sm btn-secondary" onclick="viewHistory(${inv.id})" title="Historique">
                        <i class="fas fa-history"></i>
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="deleteRecurring(${inv.id})" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>
    `).join('');
}

function openRecurringModal(id = null) {
    currentRecurring = id;
    const modal = document.getElementById('recurring-modal');
    const title = document.getElementById('recurring-modal-title');

    if (id) {
        title.textContent = 'Modifier Facture Récurrente';
        loadRecurringData(id);
    } else {
        title.textContent = 'Nouvelle Facture Récurrente';
        document.getElementById('recurring-form').reset();
        document.getElementById('recurring-id').value = '';
        resetItemsTable();
    }

    modal.classList.add('active');
}

function closeRecurringModal() {
    document.getElementById('recurring-modal').classList.remove('active');
    currentRecurring = null;
}

function loadRecurringData(id) {
    fetch(`assets/ajax/recurring_invoices.php?action=get_recurring&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const rec = data.data;
                document.getElementById('recurring-id').value = rec.id;
                document.getElementById('template-name').value = rec.template_name;
                document.getElementById('contact-id').value = rec.contact_id;
                document.getElementById('frequency').value = rec.frequency;
                document.getElementById('start-date').value = rec.start_date;
                document.getElementById('end-date').value = rec.end_date || '';
                document.getElementById('next-generation-date').value = rec.next_generation_date;
                document.getElementById('max-occurrences').value = rec.max_occurrences || '';
                document.getElementById('payment-terms').value = rec.payment_terms_days;
                document.getElementById('invoice-prefix').value = rec.invoice_prefix;
                document.getElementById('notes').value = rec.notes || '';
                document.getElementById('auto-mark-sent').checked = rec.auto_mark_sent == 1;

                // Charger les items
                loadItems(rec.items || []);
            }
        })
        .catch(error => console.error('Erreur:', error));
}

function loadItems(items) {
    const tbody = document.getElementById('items-tbody');
    tbody.innerHTML = '';

    if (items.length === 0) {
        addItemRow();
    } else {
        items.forEach(item => {
            const row = createItemRow();
            row.querySelector('.item-description').value = item.description;
            row.querySelector('.item-quantity').value = item.quantity;
            row.querySelector('.item-price').value = item.unit_price;
            row.querySelector('.item-tva').value = item.tva_rate;
            tbody.appendChild(row);
        });
    }
}

function resetItemsTable() {
    const tbody = document.getElementById('items-tbody');
    tbody.innerHTML = '';
    addItemRow();
}

function addItemRow() {
    const tbody = document.getElementById('items-tbody');
    tbody.appendChild(createItemRow());
}

function createItemRow() {
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="item-description" placeholder="Description"></td>
        <td><input type="number" class="item-quantity" value="1" step="0.01"></td>
        <td><input type="number" class="item-price" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="item-tva" value="7.70" step="0.01"></td>
        <td><button type="button" class="btn btn-sm btn-danger" onclick="removeItemRow(this)"><i class="fas fa-trash"></i></button></td>
    `;
    return row;
}

function removeItemRow(button) {
    const tbody = document.getElementById('items-tbody');
    if (tbody.children.length > 1) {
        button.closest('tr').remove();
    } else {
        alert('Au moins une ligne est requise');
    }
}

function collectItems() {
    const rows = document.querySelectorAll('#items-tbody tr');
    const items = [];

    rows.forEach(row => {
        const description = row.querySelector('.item-description').value;
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(row.querySelector('.item-price').value) || 0;
        const tva = parseFloat(row.querySelector('.item-tva').value) || 0;

        if (description && quantity > 0 && price > 0) {
            items.push({
                product_id: null,
                description: description,
                quantity: quantity,
                unit_price: price,
                tva_rate: tva,
                discount_percent: 0
            });
        }
    });

    return items;
}

function saveRecurring() {
    const items = collectItems();

    if (items.length === 0) {
        alert('Veuillez ajouter au moins une ligne de facture');
        return;
    }

    const formData = {
        action: currentRecurring ? 'update_recurring' : 'create_recurring',
        id: currentRecurring || undefined,
        template_name: document.getElementById('template-name').value,
        contact_id: parseInt(document.getElementById('contact-id').value),
        frequency: document.getElementById('frequency').value,
        start_date: document.getElementById('start-date').value,
        end_date: document.getElementById('end-date').value || null,
        next_generation_date: document.getElementById('next-generation-date').value,
        max_occurrences: document.getElementById('max-occurrences').value ? parseInt(document.getElementById('max-occurrences').value) : null,
        payment_terms_days: parseInt(document.getElementById('payment-terms').value),
        invoice_prefix: document.getElementById('invoice-prefix').value,
        notes: document.getElementById('notes').value,
        auto_mark_sent: document.getElementById('auto-mark-sent').checked ? 1 : 0,
        status: 'active',
        items: items
    };

    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeRecurringModal();
            loadRecurringInvoices();
            loadRecurringStats();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur de connexion');
    });
}

function editRecurring(id) {
    openRecurringModal(id);
}

function deleteRecurring(id) {
    if (!confirm('Supprimer cette facture récurrente ?')) return;

    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'delete_recurring', id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadRecurringInvoices();
            loadRecurringStats();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function generateInvoice(id) {
    if (!confirm('Générer une facture maintenant ?')) return;

    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'generate_invoice', id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(`Facture ${data.invoice_number} générée avec succès!`);
            loadRecurringInvoices();
            loadRecurringStats();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function pauseRecurring(id) {
    changeRecurringStatus(id, 'paused');
}

function activateRecurring(id) {
    changeRecurringStatus(id, 'active');
}

function changeRecurringStatus(id, status) {
    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'change_status', id: id, status: status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadRecurringInvoices();
            loadRecurringStats();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function viewHistory(id) {
    fetch(`assets/ajax/recurring_invoices.php?action=get_history&id=${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayHistory(data.data);
            }
        })
        .catch(error => console.error('Erreur:', error));
}

function displayHistory(history) {
    if (history.length === 0) {
        alert('Aucune facture générée pour le moment');
        return;
    }

    let html = '<h3>Historique des Générations</h3><table class="data-table"><thead><tr><th>N° Facture</th><th>Date Génération</th><th>Montant</th><th>Statut</th></tr></thead><tbody>';

    history.forEach(h => {
        html += `<tr>
            <td>${h.invoice_number}</td>
            <td>${formatDate(h.invoice_date)}</td>
            <td>${formatCurrency(h.total_amount)}</td>
            <td><span class="badge badge-${h.current_invoice_status}">${h.current_invoice_status}</span></td>
        </tr>`;
    });

    html += '</tbody></table>';

    // Créer modal simple pour afficher l'historique
    alert(html); // Simplification - à améliorer avec un vrai modal
}

// ============================================
// ABONNEMENTS
// ============================================
function loadSubscriptions() {
    fetch('assets/ajax/recurring_invoices.php?action=list_subscriptions')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displaySubscriptions(data.data);
            }
        })
        .catch(error => console.error('Erreur:', error));
}

function loadSubscriptionStats() {
    fetch('assets/ajax/recurring_invoices.php?action=get_subscription_stats')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const stats = data.data;
                document.getElementById('sub-total').textContent = stats.total || 0;
                document.getElementById('sub-active').textContent = stats.active || 0;
                document.getElementById('sub-trial').textContent = stats.trial || 0;
                document.getElementById('sub-mrr').textContent = formatCurrency(stats.mrr || 0);
            }
        })
        .catch(error => console.error('Erreur stats:', error));
}

function displaySubscriptions(subscriptions) {
    const tbody = document.getElementById('subscriptions-tbody');

    if (!subscriptions || subscriptions.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>Aucun abonnement</h3>
                    <p>Créez votre premier abonnement client</p>
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = subscriptions.map(sub => `
        <tr>
            <td><strong>${escapeHtml(sub.subscription_name)}</strong></td>
            <td>${escapeHtml(sub.contact_name || '-')}</td>
            <td><strong>${formatCurrency(sub.amount)}</strong></td>
            <td><span class="frequency-badge">${getBillingCycleLabel(sub.billing_cycle)}</span></td>
            <td>${formatDate(sub.current_period_end)}${sub.days_until_renewal !== null ? ` <small>(${sub.days_until_renewal}j)</small>` : ''}</td>
            <td><span class="badge badge-${sub.status}">${sub.status_label || sub.status}</span></td>
            <td>
                <div class="action-buttons">
                    ${sub.status === 'active' ? `
                        <button class="btn btn-sm btn-success" onclick="renewSubscription(${sub.id})" title="Renouveler">
                            <i class="fas fa-sync"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" onclick="pauseSubscription(${sub.id})" title="Pause">
                            <i class="fas fa-pause"></i>
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="cancelSubscription(${sub.id})" title="Annuler">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                    ${sub.status === 'paused' ? `
                        <button class="btn btn-sm btn-success" onclick="reactivateSubscription(${sub.id})" title="Réactiver">
                            <i class="fas fa-play"></i>
                        </button>
                    ` : ''}
                </div>
            </td>
        </tr>
    `).join('');
}

function openSubscriptionModal(id = null) {
    currentSubscription = id;
    const modal = document.getElementById('subscription-modal');
    const title = document.getElementById('subscription-modal-title');

    if (id) {
        title.textContent = 'Modifier Abonnement';
        // Charger les données
    } else {
        title.textContent = 'Nouvel Abonnement';
        document.getElementById('subscription-form').reset();
        document.getElementById('subscription-id').value = '';
    }

    modal.classList.add('active');
}

function closeSubscriptionModal() {
    document.getElementById('subscription-modal').classList.remove('active');
    currentSubscription = null;
}

function saveSubscription() {
    const formData = {
        action: currentSubscription ? 'update_subscription' : 'create_subscription',
        id: currentSubscription || undefined,
        subscription_name: document.getElementById('sub-name').value,
        contact_id: parseInt(document.getElementById('sub-contact-id').value),
        amount: parseFloat(document.getElementById('sub-amount').value),
        billing_cycle: document.getElementById('sub-billing-cycle').value,
        subscription_type: document.getElementById('sub-type').value,
        start_date: document.getElementById('sub-start-date').value,
        current_period_start: document.getElementById('sub-start-date').value,
        current_period_end: document.getElementById('sub-period-end').value,
        auto_renew: document.getElementById('sub-auto-renew').checked ? 1 : 0,
        status: 'active'
    };

    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeSubscriptionModal();
            loadSubscriptions();
            loadSubscriptionStats();
        } else {
            alert('Erreur: ' + data.message);
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function renewSubscription(id) {
    if (!confirm('Renouveler cet abonnement ?')) return;

    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'renew_subscription', id: id })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            loadSubscriptions();
            loadSubscriptionStats();
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function pauseSubscription(id) {
    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'pause_subscription', id: id })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            loadSubscriptions();
            loadSubscriptionStats();
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function cancelSubscription(id) {
    if (!confirm('Annuler cet abonnement en fin de période ?')) return;

    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'cancel_subscription', id: id, immediate: false })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            loadSubscriptions();
            loadSubscriptionStats();
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function reactivateSubscription(id) {
    fetch('assets/ajax/recurring_invoices.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'reactivate_subscription', id: id })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (data.success) {
            loadSubscriptions();
            loadSubscriptionStats();
        }
    })
    .catch(error => console.error('Erreur:', error));
}

// ============================================
// Filtres de Recherche
// ============================================
function filterRecurringTable() {
    const search = document.getElementById('search-recurring').value.toLowerCase();
    const rows = document.querySelectorAll('#recurring-tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
}

function filterSubscriptionsTable() {
    const search = document.getElementById('search-subscriptions').value.toLowerCase();
    const rows = document.querySelectorAll('#subscriptions-tbody tr');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(search) ? '' : 'none';
    });
}

// ============================================
// Utilitaires
// ============================================
function getFrequencyLabel(freq) {
    const labels = {
        daily: 'Quotidien',
        weekly: 'Hebdomadaire',
        biweekly: 'Bihebdomadaire',
        monthly: 'Mensuel',
        quarterly: 'Trimestriel',
        semiannual: 'Semestriel',
        annual: 'Annuel'
    };
    return labels[freq] || freq;
}

function getBillingCycleLabel(cycle) {
    const labels = {
        monthly: 'Mensuel',
        quarterly: 'Trimestriel',
        semiannual: 'Semestriel',
        annual: 'Annuel'
    };
    return labels[cycle] || cycle;
}

function getStatusLabel(status) {
    const labels = {
        active: 'Actif',
        paused: 'En Pause',
        cancelled: 'Annulé',
        completed: 'Terminé',
        trial: 'Période d\'essai',
        expired: 'Expiré'
    };
    return labels[status] || status;
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('fr-CH', {
        style: 'currency',
        currency: 'CHF'
    }).format(amount);
}

function formatDate(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('fr-CH');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    alert('Erreur: ' + message);
}
