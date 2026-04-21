// ====================================
// COMPTABILITE.JS
// Gestion des transactions et factures
// ====================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Comptabilite.js chargé');

    // ===================
    // GESTION DES MODALS
    // ===================

    // Créer les modals dynamiquement s'ils n'existent pas
    createModals();

    // Fonction pour créer les modals
    function createModals() {
        // Modal Transaction
        if(!document.getElementById('transactionModal')) {
            const transactionModalHTML = `
                <div class="modal" id="transactionModal" style="display: none;">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 class="modal-title" id="transaction-modal-title">Nouvelle transaction</h2>
                                <button type="button" class="modal-close" onclick="closeTransactionModal()">&times;</button>
                            </div>
                            <div class="modal-body">
                                <form id="transaction-form">
                                    <input type="hidden" id="transaction-id" name="id">

                                    <div class="form-group">
                                        <label for="transaction-date">Date *</label>
                                        <input type="date" id="transaction-date" name="date" class="form-control" required>
                                    </div>

                                    <div class="form-group">
                                        <label for="transaction-type">Type *</label>
                                        <select id="transaction-type" name="type" class="form-control" required>
                                            <option value="">Sélectionner...</option>
                                            <option value="income">Revenu</option>
                                            <option value="expense">Dépense</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="transaction-description">Description *</label>
                                        <textarea id="transaction-description" name="description" class="form-control" rows="3" required></textarea>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="transaction-amount">Montant (CHF) *</label>
                                            <input type="number" id="transaction-amount" name="amount" class="form-control" step="0.01" min="0" required>
                                        </div>

                                        <div class="form-group">
                                            <label for="transaction-tva">TVA (%)</label>
                                            <input type="number" id="transaction-tva" name="tva_rate" class="form-control" step="0.1" min="0" value="0">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="transaction-category">Catégorie</label>
                                        <input type="text" id="transaction-category" name="category" class="form-control">
                                    </div>

                                    <div class="form-group">
                                        <label for="transaction-account">Compte comptable</label>
                                        <select id="transaction-account" name="account_id" class="form-control">
                                            <option value="">Aucun</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" onclick="closeTransactionModal()">Annuler</button>
                                <button type="button" class="btn primary-btn" onclick="saveTransaction()">Enregistrer</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', transactionModalHTML);
        }

        // Modal Facture
        if(!document.getElementById('invoiceModal')) {
            const invoiceModalHTML = `
                <div class="modal" id="invoiceModal" style="display: none;">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 class="modal-title" id="invoice-modal-title">Nouvelle facture</h2>
                                <button type="button" class="modal-close" onclick="closeInvoiceModal()">&times;</button>
                            </div>
                            <div class="modal-body">
                                <form id="invoice-form">
                                    <input type="hidden" id="invoice-id" name="id">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="invoice-number">Numéro</label>
                                            <input type="text" id="invoice-number" name="number" class="form-control" readonly>
                                        </div>

                                        <div class="form-group">
                                            <label for="invoice-date">Date *</label>
                                            <input type="date" id="invoice-date" name="date" class="form-control" required>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="invoice-client">Client *</label>
                                        <select id="invoice-client" name="client_id" class="form-control" required>
                                            <option value="">Sélectionner un client...</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label>Articles de la facture</label>
                                        <div id="invoice-items-container">
                                            <!-- Les articles seront ajoutés ici -->
                                        </div>
                                        <button type="button" class="btn btn-outline small-btn" onclick="addInvoiceItem()">
                                            <i class="fa-solid fa-plus"></i> Ajouter un article
                                        </button>
                                    </div>

                                    <div class="invoice-totals">
                                        <div class="total-row">
                                            <span>Sous-total:</span>
                                            <span id="invoice-subtotal">0.00 CHF</span>
                                        </div>
                                        <div class="total-row">
                                            <span>TVA:</span>
                                            <span id="invoice-tva">0.00 CHF</span>
                                        </div>
                                        <div class="total-row total-final">
                                            <span>Total:</span>
                                            <span id="invoice-total">0.00 CHF</span>
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="invoice-status">Statut</label>
                                        <select id="invoice-status" name="status" class="form-control">
                                            <option value="en attente">En attente</option>
                                            <option value="payée">Payée</option>
                                            <option value="annulée">Annulée</option>
                                        </select>
                                    </div>
                                </form>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" onclick="closeInvoiceModal()">Annuler</button>
                                <button type="button" class="btn primary-btn" onclick="saveInvoice()">Enregistrer</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', invoiceModalHTML);
        }
    }

    // ===================
    // BOUTONS PRINCIPAUX
    // ===================

    // Bouton Nouvelle transaction
    const addTransactionBtn = document.getElementById('addTransactionBtn');
    if(addTransactionBtn) {
        addTransactionBtn.addEventListener('click', function() {
            openTransactionModal();
        });
    }

    // Bouton Nouvelle facture
    const addInvoiceBtn = document.getElementById('addInvoiceBtn');
    if(addInvoiceBtn) {
        addInvoiceBtn.addEventListener('click', function() {
            openInvoiceModal();
        });
    }

    // ==================
    // ACTIONS SUR LISTE
    // ==================

    // Éditer transaction
    document.addEventListener('click', function(e) {
        if(e.target.closest('.edit-transaction')) {
            const btn = e.target.closest('.edit-transaction');
            const transactionId = btn.getAttribute('data-id');
            editTransaction(transactionId);
        }
    });

    // Supprimer transaction
    document.addEventListener('click', function(e) {
        if(e.target.closest('.delete-transaction')) {
            const btn = e.target.closest('.delete-transaction');
            const transactionId = btn.getAttribute('data-id');
            deleteTransaction(transactionId);
        }
    });

    // Éditer facture
    document.addEventListener('click', function(e) {
        if(e.target.closest('.edit-invoice')) {
            const btn = e.target.closest('.edit-invoice');
            const invoiceId = btn.getAttribute('data-id');
            editInvoice(invoiceId);
        }
    });

    // Voir facture
    document.addEventListener('click', function(e) {
        if(e.target.closest('.view-invoice')) {
            const btn = e.target.closest('.view-invoice');
            const invoiceId = btn.getAttribute('data-id');
            viewInvoice(invoiceId);
        }
    });

    // Supprimer facture
    document.addEventListener('click', function(e) {
        if(e.target.closest('.delete-invoice')) {
            const btn = e.target.closest('.delete-invoice');
            const invoiceId = btn.getAttribute('data-id');
            deleteInvoice(invoiceId);
        }
    });

    // Charger les données initiales
    loadAccountsList();
    loadClientsList();
});

// =============================
// FONCTIONS MODAL TRANSACTION
// =============================

function openTransactionModal(transactionId = null) {
    const modal = document.getElementById('transactionModal');
    const title = document.getElementById('transaction-modal-title');
    const form = document.getElementById('transaction-form');

    // Réinitialiser le formulaire
    form.reset();
    document.getElementById('transaction-id').value = '';

    if(transactionId) {
        title.textContent = 'Modifier la transaction';
        // Charger les données de la transaction
        loadTransactionData(transactionId);
    } else {
        title.textContent = 'Nouvelle transaction';
        // Date par défaut = aujourd'hui
        document.getElementById('transaction-date').value = new Date().toISOString().split('T')[0];
    }

    modal.style.display = 'flex';
}

function closeTransactionModal() {
    const modal = document.getElementById('transactionModal');
    modal.style.display = 'none';
}

function saveTransaction() {
    const form = document.getElementById('transaction-form');
    const transactionId = document.getElementById('transaction-id').value;

    // Validation
    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Préparer les données
    const data = {
        action: transactionId ? 'update' : 'create',
        date: document.getElementById('transaction-date').value,
        type: document.getElementById('transaction-type').value,
        description: document.getElementById('transaction-description').value,
        amount: parseFloat(document.getElementById('transaction-amount').value),
        tva_rate: parseFloat(document.getElementById('transaction-tva').value),
        category: document.getElementById('transaction-category').value,
        account_id: document.getElementById('transaction-account').value || null
    };

    if(transactionId) {
        data.id = transactionId;
    }

    // Envoyer la requête
    fetch('api/transaction.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            alert(response.message);
            closeTransactionModal();
            location.reload();
        } else {
            alert('Erreur: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'enregistrement de la transaction');
    });
}

function editTransaction(transactionId) {
    openTransactionModal(transactionId);
}

function loadTransactionData(transactionId) {
    fetch('api/transaction.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'read',
            id: transactionId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            const data = response.data;
            document.getElementById('transaction-id').value = data.id;
            document.getElementById('transaction-date').value = data.date;
            document.getElementById('transaction-type').value = data.type;
            document.getElementById('transaction-description').value = data.description;
            document.getElementById('transaction-amount').value = data.amount;
            document.getElementById('transaction-tva').value = data.tva_rate;
            document.getElementById('transaction-category').value = data.category;
            document.getElementById('transaction-account').value = data.account_id || '';
        } else {
            alert('Erreur: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors du chargement de la transaction');
    });
}

function deleteTransaction(transactionId) {
    if(!confirm('Êtes-vous sûr de vouloir supprimer cette transaction ?')) {
        return;
    }

    fetch('api/transaction.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete',
            id: transactionId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            alert(response.message);
            location.reload();
        } else {
            alert('Erreur: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la suppression de la transaction');
    });
}

// =========================
// FONCTIONS MODAL FACTURE
// =========================

function openInvoiceModal(invoiceId = null) {
    const modal = document.getElementById('invoiceModal');
    const title = document.getElementById('invoice-modal-title');
    const form = document.getElementById('invoice-form');

    // Réinitialiser le formulaire
    form.reset();
    document.getElementById('invoice-id').value = '';
    document.getElementById('invoice-items-container').innerHTML = '';

    if(invoiceId) {
        title.textContent = 'Modifier la facture';
        loadInvoiceData(invoiceId);
    } else {
        title.textContent = 'Nouvelle facture';
        // Date par défaut = aujourd'hui
        document.getElementById('invoice-date').value = new Date().toISOString().split('T')[0];
        // Générer un numéro de facture
        generateInvoiceNumber();
        // Ajouter un article vide
        addInvoiceItem();
    }

    modal.style.display = 'flex';
}

function closeInvoiceModal() {
    const modal = document.getElementById('invoiceModal');
    modal.style.display = 'none';
}

function generateInvoiceNumber() {
    fetch('api/invoice.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'generate_number'})
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            document.getElementById('invoice-number').value = response.number;
        }
    })
    .catch(error => console.error('Erreur:', error));
}

let invoiceItemCounter = 0;

function addInvoiceItem() {
    const container = document.getElementById('invoice-items-container');
    const itemId = 'item-' + (++invoiceItemCounter);

    const itemHTML = `
        <div class="invoice-item" id="${itemId}">
            <div class="invoice-item-row">
                <div class="form-group flex-grow">
                    <input type="text" class="form-control item-description" placeholder="Description" required>
                </div>
                <div class="form-group" style="width: 100px;">
                    <input type="number" class="form-control item-quantity" placeholder="Qté" step="1" min="1" value="1" required>
                </div>
                <div class="form-group" style="width: 120px;">
                    <input type="number" class="form-control item-price" placeholder="Prix" step="0.01" min="0" required>
                </div>
                <div class="form-group" style="width: 100px;">
                    <input type="number" class="form-control item-tva" placeholder="TVA %" step="0.1" min="0" value="0">
                </div>
                <button type="button" class="btn icon-btn" onclick="removeInvoiceItem('${itemId}')">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        </div>
    `;

    container.insertAdjacentHTML('beforeend', itemHTML);

    // Ajouter les événements de calcul
    const item = document.getElementById(itemId);
    item.querySelectorAll('input').forEach(input => {
        input.addEventListener('input', calculateInvoiceTotals);
    });
}

function removeInvoiceItem(itemId) {
    const item = document.getElementById(itemId);
    if(item) {
        item.remove();
        calculateInvoiceTotals();
    }
}

function calculateInvoiceTotals() {
    let subtotal = 0;
    let tvaAmount = 0;

    document.querySelectorAll('.invoice-item').forEach(item => {
        const quantity = parseFloat(item.querySelector('.item-quantity').value) || 0;
        const price = parseFloat(item.querySelector('.item-price').value) || 0;
        const tvaRate = parseFloat(item.querySelector('.item-tva').value) || 0;

        const itemSubtotal = quantity * price;
        const itemTva = itemSubtotal * (tvaRate / 100);

        subtotal += itemSubtotal;
        tvaAmount += itemTva;
    });

    const total = subtotal + tvaAmount;

    document.getElementById('invoice-subtotal').textContent = subtotal.toFixed(2) + ' CHF';
    document.getElementById('invoice-tva').textContent = tvaAmount.toFixed(2) + ' CHF';
    document.getElementById('invoice-total').textContent = total.toFixed(2) + ' CHF';
}

function saveInvoice() {
    const form = document.getElementById('invoice-form');
    const invoiceId = document.getElementById('invoice-id').value;

    // Validation
    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Collecter les articles
    const items = [];
    document.querySelectorAll('.invoice-item').forEach(item => {
        items.push({
            description: item.querySelector('.item-description').value,
            quantity: parseFloat(item.querySelector('.item-quantity').value),
            price: parseFloat(item.querySelector('.item-price').value),
            tva_rate: parseFloat(item.querySelector('.item-tva').value)
        });
    });

    if(items.length === 0) {
        alert('Veuillez ajouter au moins un article');
        return;
    }

    // Préparer les données
    const data = {
        action: invoiceId ? 'update' : 'create',
        date: document.getElementById('invoice-date').value,
        client_id: document.getElementById('invoice-client').value,
        status: document.getElementById('invoice-status').value,
        items: items
    };

    if(invoiceId) {
        data.id = invoiceId;
        data.number = document.getElementById('invoice-number').value;
    }

    // Envoyer la requête
    fetch('api/invoice.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            alert(response.message);
            closeInvoiceModal();
            location.reload();
        } else {
            alert('Erreur: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'enregistrement de la facture');
    });
}

function editInvoice(invoiceId) {
    openInvoiceModal(invoiceId);
}

function loadInvoiceData(invoiceId) {
    fetch('api/invoice.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'read',
            id: invoiceId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            const data = response.data;
            document.getElementById('invoice-id').value = data.id;
            document.getElementById('invoice-number').value = data.number;
            document.getElementById('invoice-date').value = data.date;
            document.getElementById('invoice-client').value = data.client_id;
            document.getElementById('invoice-status').value = data.status;

            // Charger les articles
            document.getElementById('invoice-items-container').innerHTML = '';
            data.items.forEach(item => {
                addInvoiceItem();
                const lastItem = document.querySelector('.invoice-item:last-child');
                lastItem.querySelector('.item-description').value = item.description;
                lastItem.querySelector('.item-quantity').value = item.quantity;
                lastItem.querySelector('.item-price').value = item.price;
                lastItem.querySelector('.item-tva').value = item.tva_rate;
            });

            calculateInvoiceTotals();
        } else {
            alert('Erreur: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors du chargement de la facture');
    });
}

function viewInvoice(invoiceId) {
    // TODO: Implémenter la visualisation détaillée
    alert('Visualisation détaillée - À implémenter');
}

function deleteInvoice(invoiceId) {
    if(!confirm('Êtes-vous sûr de vouloir supprimer cette facture ?')) {
        return;
    }

    fetch('api/invoice.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete',
            id: invoiceId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            alert(response.message);
            location.reload();
        } else {
            alert('Erreur: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de la suppression de la facture');
    });
}

// =======================
// CHARGEMENT DES DONNÉES
// =======================

function loadAccountsList() {
    // Charger la liste des comptes comptables pour le select
    fetch('api/accounting_plan.php?action=list')
        .then(res => res.json())
        .then(response => {
            if(response.success) {
                const select = document.getElementById('transaction-account');
                if(select) {
                    select.innerHTML = '<option value="">Aucun</option>';
                    response.data.forEach(account => {
                        const option = document.createElement('option');
                        option.value = account.id;
                        option.textContent = account.number + ' - ' + account.name;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => console.error('Erreur chargement comptes:', error));
}

function loadClientsList() {
    // Charger la liste des clients pour le select
    fetch('assets/ajax/contacts.php')
        .then(res => res.json())
        .then(response => {
            if(response.success) {
                const select = document.getElementById('invoice-client');
                if(select) {
                    select.innerHTML = '<option value="">Sélectionner un client...</option>';
                    response.data.forEach(client => {
                        const option = document.createElement('option');
                        option.value = client.id;
                        option.textContent = client.name;
                        select.appendChild(option);
                    });
                }
            }
        })
        .catch(error => console.error('Erreur chargement clients:', error));
}

// ============================
// GESTION DES QR-FACTURES
// ============================

// Fonction pour obtenir le company_id actif
function getActiveCompanyId() {
    // Le company_id est stocké dans la session PHP
    // On pourrait le récupérer via un attribut data-company-id sur le body
    // Ou le passer via une variable globale depuis PHP
    const companySelector = document.querySelector('select[name="company"]');
    if(companySelector && companySelector.value) {
        return companySelector.value;
    }

    // Sinon essayer de le récupérer depuis un élément data
    const body = document.body;
    if(body.dataset.companyId) {
        return body.dataset.companyId;
    }

    // Par défaut, retourner null (sera géré côté serveur avec $_SESSION)
    return null;
}

// Générer une QR-facture
document.addEventListener('click', function(e) {
    if(e.target.closest('.qr-invoice-btn')) {
        const btn = e.target.closest('.qr-invoice-btn');
        const invoiceId = btn.getAttribute('data-id');
        generateQRInvoice(invoiceId);
    }
});

// Télécharger le PDF d'une facture
document.addEventListener('click', function(e) {
    if(e.target.closest('.download-pdf-btn')) {
        const btn = e.target.closest('.download-pdf-btn');
        const invoiceId = btn.getAttribute('data-id');
        downloadInvoicePDF(invoiceId);
    }
});

function generateQRInvoice(invoiceId) {
    const companyId = getActiveCompanyId();

    // Afficher un indicateur de chargement
    const originalBtn = document.querySelector(`.qr-invoice-btn[data-id="${invoiceId}"]`);
    const originalHTML = originalBtn.innerHTML;
    originalBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
    originalBtn.disabled = true;

    fetch('api/qr_invoice.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'generate_pdf',
            invoice_id: parseInt(invoiceId),
            company_id: companyId ? parseInt(companyId) : null,
            with_qr: true
        })
    })
    .then(res => res.json())
    .then(data => {
        // Restaurer le bouton
        originalBtn.innerHTML = originalHTML;
        originalBtn.disabled = false;

        if(data.success) {
            alert('✅ QR-Facture générée avec succès !');
            // Ouvrir le PDF dans un nouvel onglet
            window.open('api/qr_invoice.php?action=view_pdf&invoice_id=' + invoiceId, '_blank');
        } else {
            alert('❌ Erreur : ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(err => {
        console.error('Erreur:', err);
        // Restaurer le bouton
        originalBtn.innerHTML = originalHTML;
        originalBtn.disabled = false;
        alert('❌ Erreur lors de la génération de la QR-facture');
    });
}

function downloadInvoicePDF(invoiceId) {
    // Télécharger directement le PDF
    window.location.href = 'api/qr_invoice.php?action=download_pdf&invoice_id=' + invoiceId;
}
