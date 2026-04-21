/**
 * Bank Reconciliation JavaScript
 * Handles all frontend interactions for bank reconciliation
 */

// Global variables
let currentAccountId = null;
let selectedFile = null;

/**
 * Tab switching
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
    const clickedTab = document.querySelector(`.tab[data-tab="${tabName}"]`);
    if (clickedTab) {
        clickedTab.classList.add('active');
    }

    // Show corresponding content
    const content = document.getElementById(`tab-${tabName}`);
    if (content) {
        content.classList.add('active');
    }

    // Load data for specific tabs
    if (tabName === 'pending') {
        loadPendingTransactions();
    } else if (tabName === 'reconciled') {
        loadReconciledTransactions();
    }
}

/**
 * Modal Management
 */
function openAccountModal(accountId = null) {
    const modal = document.getElementById('accountModal');
    const title = document.getElementById('accountModalTitle');
    const form = document.getElementById('accountForm');

    if (accountId) {
        title.textContent = 'Modifier le Compte';
        loadAccountData(accountId);
    } else {
        title.textContent = 'Nouveau Compte Bancaire';
        form.reset();
        document.getElementById('account_id').value = '';
    }

    modal.classList.add('active');
}

function closeAccountModal() {
    const modal = document.getElementById('accountModal');
    modal.classList.remove('active');
}

function loadAccountData(accountId) {
    fetch(`assets/ajax/bank_accounts.php?action=get&id=${accountId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const account = data.account;
                document.getElementById('account_id').value = account.id;
                document.getElementById('account_name').value = account.name;
                document.getElementById('account_bank_name').value = account.bank_name || '';
                document.getElementById('account_iban').value = account.iban || '';
                document.getElementById('account_number').value = account.account_number || '';
                document.getElementById('account_currency').value = account.currency;
                document.getElementById('account_opening_balance').value = account.opening_balance;
                document.getElementById('account_opening_balance_date').value = account.opening_balance_date || '';
                document.getElementById('account_notes').value = account.notes || '';
            } else {
                showNotification('Erreur lors du chargement du compte', 'error');
            }
        })
        .catch(err => {
            console.error('Error loading account:', err);
            showNotification('Erreur réseau', 'error');
        });
}

function saveAccount() {
    const form = document.getElementById('accountForm');
    const formData = new FormData(form);
    const accountId = document.getElementById('account_id').value;
    const action = accountId ? 'update' : 'create';

    const data = {
        action: action,
        id: accountId || undefined,
        name: formData.get('name'),
        bank_name: formData.get('bank_name'),
        iban: formData.get('iban'),
        account_number: formData.get('account_number'),
        currency: formData.get('currency'),
        opening_balance: formData.get('opening_balance'),
        opening_balance_date: formData.get('opening_balance_date'),
        notes: formData.get('notes')
    };

    fetch('assets/ajax/bank_accounts.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Compte enregistré avec succès', 'success');
            closeAccountModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.error || 'Erreur lors de l\'enregistrement', 'error');
        }
    })
    .catch(err => {
        console.error('Error saving account:', err);
        showNotification('Erreur réseau', 'error');
    });
}

function editAccount(accountId) {
    openAccountModal(accountId);
}

/**
 * File Upload Handling
 */
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    selectedFile = file;

    // Show file info
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = `(${formatFileSize(file.size)})`;
    document.getElementById('fileInfo').style.display = 'block';

    // Detect format
    const extension = file.name.split('.').pop().toLowerCase();
    let detectedFormat = 'unknown';

    if (extension === 'xml' || extension === '053') {
        detectedFormat = 'camt053';
    } else if (extension === 'txt' || extension === '940') {
        detectedFormat = 'mt940';
    } else if (extension === 'csv') {
        detectedFormat = 'csv';
    }

    console.log('Detected format:', detectedFormat);
}

function clearFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('fileInfo').style.display = 'none';
}

function formatFileSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// Drag and drop
const dropzone = document.getElementById('dropzone');

dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('dragover');
});

dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('dragover');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('dragover');

    const file = e.dataTransfer.files[0];
    if (file) {
        document.getElementById('fileInput').files = e.dataTransfer.files;
        handleFileSelect({target: {files: [file]}});
    }
});

/**
 * Import Form Submission
 */
document.getElementById('importForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const accountId = document.getElementById('import_account_id').value;
    const fileInput = document.getElementById('fileInput');

    if (!accountId) {
        showNotification('Veuillez sélectionner un compte bancaire', 'error');
        return;
    }

    if (!fileInput.files.length) {
        showNotification('Veuillez sélectionner un fichier', 'error');
        return;
    }

    const formData = new FormData();
    formData.append('action', 'import');
    formData.append('bank_account_id', accountId);
    formData.append('statement_file', fileInput.files[0]);

    // Show loading
    showNotification('Import en cours...', 'info');

    fetch('assets/ajax/bank_import.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(
                `Import réussi: ${data.imported} transactions importées` +
                (data.duplicates > 0 ? ` (${data.duplicates} doublons ignorés)` : ''),
                'success'
            );

            // Clear form
            clearFile();
            document.getElementById('importForm').reset();

            // Switch to pending tab
            setTimeout(() => {
                switchTab('pending');
            }, 1500);
        } else {
            showNotification(data.error || 'Erreur lors de l\'import', 'error');
        }
    })
    .catch(err => {
        console.error('Error importing file:', err);
        showNotification('Erreur réseau lors de l\'import', 'error');
    });
});

/**
 * Load pending transactions
 */
function loadPendingTransactions() {
    const container = document.getElementById('pendingTransactions');

    fetch('assets/ajax/bank_transactions.php?action=pending')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayTransactions(container, data.transactions, 'pending');
            } else {
                container.innerHTML = '<div class="empty-state"><p>Erreur lors du chargement</p></div>';
            }
        })
        .catch(err => {
            console.error('Error loading pending transactions:', err);
            container.innerHTML = '<div class="empty-state"><p>Erreur réseau</p></div>';
        });
}

/**
 * Load reconciled transactions
 */
function loadReconciledTransactions() {
    const container = document.getElementById('reconciledTransactions');

    fetch('assets/ajax/bank_transactions.php?action=reconciled')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayTransactions(container, data.transactions, 'reconciled');
            } else {
                container.innerHTML = '<div class="empty-state"><p>Erreur lors du chargement</p></div>';
            }
        })
        .catch(err => {
            console.error('Error loading reconciled transactions:', err);
            container.innerHTML = '<div class="empty-state"><p>Erreur réseau</p></div>';
        });
}

/**
 * Display transactions list
 */
function displayTransactions(container, transactions, type) {
    if (!transactions || transactions.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <div class="empty-state-text">Aucune transaction ${type === 'pending' ? 'en attente' : 'rapprochée'}</div>
            </div>
        `;
        return;
    }

    let html = '<div class="accounts-list">';

    transactions.forEach(trans => {
        const amountClass = trans.amount >= 0 ? 'credit' : 'debit';
        const amountIcon = trans.amount >= 0 ? 'fa-arrow-down' : 'fa-arrow-up';
        const amountColor = trans.amount >= 0 ? '#10b981' : '#ef4444';

        html += `
            <div class="account-item">
                <div style="flex: 0 0 80px; text-align: center;">
                    <div style="font-size: 12px; color: #6b7280;">${formatDate(trans.transaction_date)}</div>
                    <div style="font-size: 20px; margin-top: 5px; color: ${amountColor};">
                        <i class="fas ${amountIcon}"></i>
                    </div>
                </div>
                <div class="account-info">
                    <div class="account-name">${escapeHtml(trans.description)}</div>
                    <div class="account-details">
                        ${trans.counterparty_name ? escapeHtml(trans.counterparty_name) + ' • ' : ''}
                        ${trans.account_name}
                        ${trans.qr_reference ? ' • QR: ' + trans.qr_reference : ''}
                    </div>
                </div>
                <div class="account-balance">
                    <div class="balance-amount" style="color: ${amountColor};">
                        ${trans.amount >= 0 ? '+' : ''}${formatAmount(trans.amount)} ${trans.currency}
                    </div>
                </div>
                <div class="account-actions">
                    ${type === 'pending' ? `
                        <button class="btn-icon" onclick="reconcileTransaction(${trans.id})" title="Rapprocher">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn-icon" onclick="ignoreTransaction(${trans.id})" title="Ignorer">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : `
                        <button class="btn-icon" onclick="viewTransactionDetails(${trans.id})" title="Détails">
                            <i class="fas fa-eye"></i>
                        </button>
                    `}
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

/**
 * Reconcile transaction
 */
function reconcileTransaction(transactionId) {
    // Show matching modal or auto-match
    fetch(`assets/ajax/bank_transactions.php?action=find_matches&id=${transactionId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.matches.length > 0) {
                showMatchingModal(transactionId, data.matches);
            } else {
                showNotification('Aucune facture correspondante trouvée', 'warning');
            }
        })
        .catch(err => {
            console.error('Error finding matches:', err);
            showNotification('Erreur lors de la recherche', 'error');
        });
}

/**
 * Ignore transaction
 */
function ignoreTransaction(transactionId) {
    if (!confirm('Êtes-vous sûr de vouloir ignorer cette transaction ?')) {
        return;
    }

    fetch('assets/ajax/bank_transactions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'ignore',
            id: transactionId
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Transaction ignorée', 'success');
            loadPendingTransactions();
        } else {
            showNotification(data.error || 'Erreur', 'error');
        }
    })
    .catch(err => {
        console.error('Error ignoring transaction:', err);
        showNotification('Erreur réseau', 'error');
    });
}

/**
 * View transactions for account
 */
function viewTransactions(accountId) {
    currentAccountId = accountId;
    switchTab('pending');
}

/**
 * Import for specific account
 */
function importForAccount(accountId) {
    document.getElementById('import_account_id').value = accountId;
    switchTab('import');
}

/**
 * Utilities
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
    }).format(Math.abs(amount));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showNotification(message, type = 'info') {
    // Simple notification (you can replace with a better notification library)
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
    `;
    notification.textContent = message;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from { transform: translateX(400px); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    @keyframes slideOut {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(400px); opacity: 0; }
    }
`;
document.head.appendChild(style);
