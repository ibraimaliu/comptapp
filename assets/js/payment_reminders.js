/**
 * Payment Reminders JavaScript
 * Handles all frontend interactions for payment reminders
 */

// Global variables
let currentInvoiceId = null;
let currentReminderLevel = 0;

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
    if (tabName === 'sent') {
        loadSentReminders();
    }
}

/**
 * Create reminder modal
 */
function createReminder(invoiceId, invoiceNumber, lastLevel) {
    currentInvoiceId = invoiceId;
    currentReminderLevel = lastLevel + 1;

    if (currentReminderLevel > 3) {
        showNotification('Niveau maximum de rappels atteint', 'warning');
        return;
    }

    // Set modal title and info
    const levelNames = ['', 'Premier Rappel', 'Deuxième Rappel', 'Mise en Demeure'];
    document.getElementById('reminderModalTitle').textContent = levelNames[currentReminderLevel];

    const infoTexts = [
        '',
        'Un premier rappel amical sera envoyé au client.',
        'Un deuxième rappel avec frais sera envoyé au client.',
        'Une mise en demeure formelle sera envoyée au client avec intérêts et frais.'
    ];
    document.getElementById('reminderInfoText').textContent = infoTexts[currentReminderLevel];

    // Set hidden fields
    document.getElementById('reminder_invoice_id').value = invoiceId;
    document.getElementById('reminder_level').value = currentReminderLevel;

    // Set preview fields
    document.getElementById('preview_invoice_number').textContent = invoiceNumber;
    document.getElementById('preview_level').textContent = levelNames[currentReminderLevel];

    // Calculate amounts
    calculateReminderAmounts(invoiceId, currentReminderLevel);

    // Show modal
    document.getElementById('reminderModal').classList.add('active');
}

function closeReminderModal() {
    document.getElementById('reminderModal').classList.remove('active');
    document.getElementById('reminder_notes').value = '';
}

/**
 * Calculate reminder amounts
 */
function calculateReminderAmounts(invoiceId, reminderLevel) {
    fetch(`assets/ajax/payment_reminders.php?action=calculate&invoice_id=${invoiceId}&reminder_level=${reminderLevel}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const amounts = data.amounts;
                document.getElementById('preview_original_amount').textContent = formatAmount(amounts.original_amount) + ' CHF';
                document.getElementById('preview_interest').textContent = formatAmount(amounts.interest_amount) + ' CHF';
                document.getElementById('preview_fees').textContent = formatAmount(amounts.fees) + ' CHF';
                document.getElementById('preview_total').textContent = formatAmount(amounts.total_amount) + ' CHF';
                document.getElementById('preview_days').textContent = amounts.days_overdue + ' jours';
            } else {
                showNotification('Erreur lors du calcul des montants', 'error');
            }
        })
        .catch(err => {
            console.error('Error calculating amounts:', err);
            showNotification('Erreur réseau', 'error');
        });
}

/**
 * Send reminder
 */
function sendReminder() {
    const invoiceId = document.getElementById('reminder_invoice_id').value;
    const level = document.getElementById('reminder_level').value;
    const notes = document.getElementById('reminder_notes').value;

    if (!invoiceId || !level) {
        showNotification('Données manquantes', 'error');
        return;
    }

    // Show loading
    showNotification('Création du rappel en cours...', 'info');

    fetch('assets/ajax/payment_reminders.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'create_reminder',
            invoice_id: invoiceId,
            reminder_level: level,
            notes: notes,
            send_immediately: true
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Rappel créé et envoyé avec succès!', 'success');
            closeReminderModal();

            // Reload page after 1.5 seconds
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.error || 'Erreur lors de la création du rappel', 'error');
        }
    })
    .catch(err => {
        console.error('Error sending reminder:', err);
        showNotification('Erreur réseau', 'error');
    });
}

/**
 * Generate all reminders (batch)
 */
function generateAllReminders() {
    if (!confirm('Êtes-vous sûr de vouloir générer les rappels pour toutes les factures en retard?')) {
        return;
    }

    showNotification('Génération des rappels en cours...', 'info');

    fetch('assets/ajax/payment_reminders.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'generate_batch',
            send_immediately: false // Draft mode for review
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification(
                `${data.created} rappel(s) généré(s) avec succès!`,
                'success'
            );

            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.error || 'Erreur lors de la génération', 'error');
        }
    })
    .catch(err => {
        console.error('Error generating reminders:', err);
        showNotification('Erreur réseau', 'error');
    });
}

/**
 * Load sent reminders
 */
function loadSentReminders() {
    const container = document.getElementById('sentRemindersList');

    fetch('assets/ajax/payment_reminders.php?action=list_sent')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displaySentReminders(container, data.reminders);
            } else {
                container.innerHTML = '<div class="empty-state"><p>Erreur lors du chargement</p></div>';
            }
        })
        .catch(err => {
            console.error('Error loading sent reminders:', err);
            container.innerHTML = '<div class="empty-state"><p>Erreur réseau</p></div>';
        });
}

/**
 * Display sent reminders
 */
function displaySentReminders(container, reminders) {
    if (!reminders || reminders.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <div class="empty-state-text">Aucun rappel envoyé</div>
            </div>
        `;
        return;
    }

    let html = '<div class="invoices-table">';
    html += `
        <div class="table-header">
            <div>Date Envoi</div>
            <div>Facture</div>
            <div>Client</div>
            <div>Niveau</div>
            <div>Montant Total</div>
            <div>Statut</div>
            <div>Actions</div>
        </div>
    `;

    reminders.forEach(reminder => {
        const levelBadges = {
            1: '<span class="badge badge-warning">Niveau 1</span>',
            2: '<span class="badge" style="background: #fed7aa; color: #9a3412;">Niveau 2</span>',
            3: '<span class="badge badge-danger">Niveau 3</span>'
        };

        const statusBadges = {
            'draft': '<span class="badge" style="background: #e5e7eb; color: #374151;">Brouillon</span>',
            'sent': '<span class="badge" style="background: #dbeafe; color: #1e40af;">Envoyé</span>',
            'paid': '<span class="badge badge-success">Payé</span>',
            'cancelled': '<span class="badge" style="background: #fee2e2; color: #991b1b;">Annulé</span>'
        };

        html += `
            <div class="table-row">
                <div>${formatDate(reminder.sent_date)}</div>
                <div>
                    <strong>${escapeHtml(reminder.invoice_number)}</strong>
                </div>
                <div>
                    ${escapeHtml(reminder.client_name)}
                    <br><small style="color: #6b7280;">${escapeHtml(reminder.client_email)}</small>
                </div>
                <div>
                    ${levelBadges[reminder.reminder_level] || ''}
                </div>
                <div>
                    <strong>${formatAmount(reminder.total_amount)} CHF</strong>
                    <br><small style="color: #6b7280;">
                        Original: ${formatAmount(reminder.original_amount)} CHF
                        ${reminder.fees > 0 ? '+ Frais: ' + formatAmount(reminder.fees) + ' CHF' : ''}
                    </small>
                </div>
                <div>
                    ${statusBadges[reminder.status] || ''}
                    ${reminder.email_sent ? '<br><i class="fas fa-envelope" style="color: #10b981;" title="Email envoyé"></i>' : ''}
                </div>
                <div>
                    <button class="btn-action" onclick="viewReminder(${reminder.id})" title="Voir détails">
                        <i class="fas fa-eye"></i>
                    </button>
                    ${reminder.pdf_path ? `
                        <button class="btn-action" onclick="downloadReminderPDF(${reminder.id})" title="Télécharger PDF">
                            <i class="fas fa-file-pdf"></i>
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
 * Save settings
 */
document.getElementById('settingsForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = new FormData(e.target);
    const settings = {
        action: 'update_settings',
        level1_days: formData.get('level1_days'),
        level2_days: formData.get('level2_days'),
        level3_days: formData.get('level3_days'),
        level1_fee: formData.get('level1_fee'),
        level2_fee: formData.get('level2_fee'),
        level3_fee: formData.get('level3_fee'),
        interest_rate: formData.get('interest_rate'),
        apply_interest: formData.get('apply_interest') ? 1 : 0,
        auto_send: formData.get('auto_send') ? 1 : 0
    };

    fetch('assets/ajax/payment_reminders.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(settings)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            showNotification('Paramètres enregistrés avec succès', 'success');
        } else {
            showNotification(data.error || 'Erreur lors de l\'enregistrement', 'error');
        }
    })
    .catch(err => {
        console.error('Error saving settings:', err);
        showNotification('Erreur réseau', 'error');
    });
});

/**
 * View reminder details
 */
function viewReminder(reminderId) {
    // TODO: Implement reminder details view
    showNotification('Fonctionnalité en développement', 'info');
}

/**
 * Download reminder PDF
 */
function downloadReminderPDF(reminderId) {
    window.location.href = `assets/ajax/payment_reminders.php?action=download_pdf&id=${reminderId}`;
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
