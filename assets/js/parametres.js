// =====================================
// PARAMETRES.JS
// Gestion des paramètres de l'application
// =====================================

document.addEventListener('DOMContentLoaded', function() {
    console.log('Parametres.js chargé');

    // Charger les données initiales
    loadCategoriesList();
    loadTVARatesList();

    // ================================
    // GESTION DE LA NAVIGATION SIDEBAR
    // ================================

    const navLinks = document.querySelectorAll('.nav-link');
    const sections = document.querySelectorAll('.section');

    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();

            // Retirer la classe active de tous les liens
            navLinks.forEach(l => l.classList.remove('active'));

            // Ajouter la classe active au lien cliqué
            this.classList.add('active');

            // Cacher toutes les sections
            sections.forEach(s => s.classList.remove('active'));

            // Afficher la section correspondante
            const sectionId = this.getAttribute('data-section');
            const section = document.getElementById(sectionId);
            if(section) {
                section.classList.add('active');
            }
        });
    });

    // ======================================
    // MODAL: MODIFICATION SOCIÉTÉ
    // ======================================

    const editCompanyBtn = document.getElementById('editCompanyInfoBtn');
    const companyModal = document.getElementById('companyModal');
    const saveCompanyBtn = document.getElementById('saveCompanyBtn');

    if(editCompanyBtn) {
        editCompanyBtn.addEventListener('click', function() {
            openModal(companyModal);
        });
    }

    if(saveCompanyBtn) {
        saveCompanyBtn.addEventListener('click', function() {
            saveCompanyInfo();
        });
    }

    // ======================================
    // PLAN COMPTABLE: GESTION
    // ======================================

    const addAccountBtn = document.getElementById('addAccountBtn');
    const importPlanBtn = document.getElementById('importPlanBtn');
    const exportPlanBtn = document.getElementById('exportPlanBtn');
    const resetPlanBtn = document.getElementById('resetPlanBtn');
    const accountModal = document.getElementById('accountModal');
    const saveAccountBtn = document.getElementById('saveAccountBtn');

    if(addAccountBtn) {
        addAccountBtn.addEventListener('click', function() {
            openAccountModal();
        });
    }

    if(importPlanBtn) {
        importPlanBtn.addEventListener('click', function() {
            openModal(document.getElementById('importPlanModal'));
        });
    }

    if(exportPlanBtn) {
        exportPlanBtn.addEventListener('click', function() {
            exportAccountingPlan();
        });
    }

    if(resetPlanBtn) {
        resetPlanBtn.addEventListener('click', function() {
            resetAccountingPlan();
        });
    }

    if(saveAccountBtn) {
        saveAccountBtn.addEventListener('click', function() {
            saveAccount();
        });
    }

    // Submit import
    const submitImportPlanBtn = document.getElementById('submitImportPlanBtn');
    if(submitImportPlanBtn) {
        submitImportPlanBtn.addEventListener('click', function() {
            submitImportPlan();
        });
    }

    // Éditer/Supprimer compte
    document.addEventListener('click', function(e) {
        if(e.target.closest('.edit-account')) {
            const btn = e.target.closest('.edit-account');
            const accountId = btn.getAttribute('data-id');
            editAccount(accountId);
        }

        if(e.target.closest('.delete-account')) {
            const btn = e.target.closest('.delete-account');
            const accountId = btn.getAttribute('data-id');
            if(!btn.disabled) {
                deleteAccount(accountId);
            }
        }
    });

    // ======================================
    // CATÉGORIES: GESTION
    // ======================================

    const addCategoryBtn = document.getElementById('addCategoryBtn');
    const categoryModal = document.getElementById('categoryModal');
    const saveCategoryBtn = document.getElementById('saveCategoryBtn');

    if(addCategoryBtn) {
        addCategoryBtn.addEventListener('click', function() {
            openCategoryModal();
        });
    }

    if(saveCategoryBtn) {
        saveCategoryBtn.addEventListener('click', function() {
            saveCategory();
        });
    }

    // Éditer/Supprimer catégorie
    document.addEventListener('click', function(e) {
        if(e.target.closest('.edit-category')) {
            const btn = e.target.closest('.edit-category');
            const categoryId = btn.getAttribute('data-id');
            editCategory(categoryId);
        }

        if(e.target.closest('.delete-category')) {
            const btn = e.target.closest('.delete-category');
            const categoryId = btn.getAttribute('data-id');
            deleteCategory(categoryId);
        }
    });

    // ======================================
    // TAUX TVA: GESTION
    // ======================================

    const addTVARateBtn = document.getElementById('addTVARateBtn');
    const tvaRateModal = document.getElementById('tvaRateModal');
    const saveTVARateBtn = document.getElementById('saveTVARateBtn');

    if(addTVARateBtn) {
        addTVARateBtn.addEventListener('click', function() {
            openTVARateModal();
        });
    }

    if(saveTVARateBtn) {
        saveTVARateBtn.addEventListener('click', function() {
            saveTVARate();
        });
    }

    // Éditer/Supprimer taux TVA
    document.addEventListener('click', function(e) {
        if(e.target.closest('.edit-tva-rate')) {
            const btn = e.target.closest('.edit-tva-rate');
            const rateId = btn.getAttribute('data-id');
            editTVARate(rateId);
        }

        if(e.target.closest('.delete-tva-rate')) {
            const btn = e.target.closest('.delete-tva-rate');
            const rateId = btn.getAttribute('data-id');
            deleteTVARate(rateId);
        }
    });

    // ======================================
    // FERMETURE DES MODALS
    // ======================================

    document.querySelectorAll('.modal-close, .modal-cancel').forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            if(modal) {
                closeModal(modal);
            }
        });
    });

    // Fermer modal en cliquant en dehors
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if(e.target === this) {
                closeModal(this);
            }
        });
    });
});

// ======================================
// FONCTIONS UTILITAIRES MODAL
// ======================================

function openModal(modal) {
    if(modal) {
        modal.style.display = 'flex';
        document.getElementById('modalBackdrop').style.display = 'block';
    }
}

function closeModal(modal) {
    if(modal) {
        modal.style.display = 'none';
        document.getElementById('modalBackdrop').style.display = 'none';
    }
}

function showNotification(message, type = 'success') {
    const notification = document.getElementById('notification');
    const notificationMessage = document.getElementById('notification-message');

    if(notification && notificationMessage) {
        notificationMessage.textContent = message;
        notification.className = 'notification ' + (type === 'success' ? 'success' : 'error');
        notification.style.display = 'flex';

        setTimeout(function() {
            notification.style.display = 'none';
        }, 3000);
    }
}

// ======================================
// SOCIÉTÉ: FONCTIONS
// ======================================

function saveCompanyInfo() {
    const form = document.getElementById('company-form');

    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    // Récupérer l'ID de la société depuis la session (passé via PHP)
    const companyId = document.getElementById('company-id-hidden') ?
                      document.getElementById('company-id-hidden').value :
                      null;

    const data = {
        id: companyId,
        name: document.getElementById('company-name').value,
        owner_surname: document.getElementById('owner-surname').value,
        owner_name: document.getElementById('owner-name').value,
        fiscal_year_start: document.getElementById('fiscal-year-start').value,
        fiscal_year_end: document.getElementById('fiscal-year-end').value,
        tva_status: document.querySelector('input[name="tva_status"]:checked').value
    };

    fetch('api/company.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            closeModal(document.getElementById('companyModal'));
            location.reload();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'enregistrement', 'error');
    });
}

// ======================================
// PLAN COMPTABLE: FONCTIONS
// ======================================

function openAccountModal(accountId = null) {
    const modal = document.getElementById('accountModal');
    const title = document.getElementById('account-modal-title');
    const form = document.getElementById('account-form');

    form.reset();
    document.getElementById('account-id').value = '';

    if(accountId) {
        title.textContent = 'Modifier le compte';
        loadAccountData(accountId);
    } else {
        title.textContent = 'Ajouter un compte';
    }

    openModal(modal);
}

function loadAccountData(accountId) {
    fetch('api/accounting_plan.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'read',
            id: accountId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            const data = response.data;
            document.getElementById('account-id').value = data.id;
            document.getElementById('account-number').value = data.number;
            document.getElementById('account-name').value = data.name;
            document.getElementById('account-category').value = data.category;
            document.getElementById('account-type').value = data.type;
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors du chargement', 'error');
    });
}

function saveAccount() {
    const form = document.getElementById('account-form');
    const accountId = document.getElementById('account-id').value;

    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        action: accountId ? 'update' : 'create',
        number: document.getElementById('account-number').value,
        name: document.getElementById('account-name').value,
        category: document.getElementById('account-category').value,
        type: document.getElementById('account-type').value
    };

    if(accountId) {
        data.id = accountId;
    }

    fetch('api/accounting_plan.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            closeModal(document.getElementById('accountModal'));
            location.reload();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'enregistrement', 'error');
    });
}

function editAccount(accountId) {
    openAccountModal(accountId);
}

function deleteAccount(accountId) {
    if(!confirm('Êtes-vous sûr de vouloir supprimer ce compte ?')) {
        return;
    }

    fetch('api/accounting_plan.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete',
            id: accountId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            location.reload();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la suppression', 'error');
    });
}

function exportAccountingPlan() {
    window.location.href = 'assets/ajax/accounting_plan_import.php?action=export_csv';
}

function resetAccountingPlan() {
    if(!confirm('Êtes-vous sûr de vouloir réinitialiser le plan comptable ? Seuls les comptes non utilisés seront supprimés.')) {
        return;
    }

    fetch('assets/ajax/accounting_plan_import.php?action=reset', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            location.reload();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la réinitialisation', 'error');
    });
}

function submitImportPlan() {
    const fileInput = document.getElementById('import-file');
    const importAction = document.querySelector('input[name="import_action"]:checked').value;

    if(!fileInput.files || fileInput.files.length === 0) {
        alert('Veuillez sélectionner un fichier CSV');
        return;
    }

    const file = fileInput.files[0];

    // Validate file type
    if(!file.name.toLowerCase().endsWith('.csv')) {
        alert('Le fichier doit être au format CSV');
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', file);
    formData.append('import_action', importAction);
    formData.append('action', 'import_csv');

    // Show loading
    const btn = document.getElementById('submitImportPlanBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Import en cours...';
    btn.disabled = true;

    fetch('assets/ajax/accounting_plan_import.php?action=import_csv', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(response => {
        btn.innerHTML = originalText;
        btn.disabled = false;

        if(response.success) {
            let message = response.message;
            if(response.errors && response.errors.length > 0) {
                message += '\n\nAvertissements:\n' + response.errors.slice(0, 5).join('\n');
                if(response.errors.length > 5) {
                    message += `\n... et ${response.errors.length - 5} autres`;
                }
            }
            alert(message);
            closeModal(document.getElementById('importPlanModal'));
            location.reload();
        } else {
            alert('Erreur: ' + response.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        btn.innerHTML = originalText;
        btn.disabled = false;
        alert('Erreur lors de l\'import');
    });
}

function loadDefaultPlan() {
    if(!confirm('Importer le plan comptable PME suisse par défaut ? Cette action ne peut être effectuée que si aucun plan n\'existe.')) {
        return;
    }

    fetch('assets/ajax/accounting_plan_import.php?action=import_default', {
        method: 'POST'
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            location.reload();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'import', 'error');
    });
}

// ======================================
// CATÉGORIES: FONCTIONS
// ======================================

function loadCategoriesList() {
    fetch('api/category.php?action=list')
        .then(res => res.json())
        .then(response => {
            if(response.success) {
                const tbody = document.getElementById('categoriesList');
                if(tbody) {
                    if(response.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="2" style="text-align: center;">Aucune catégorie enregistrée</td></tr>';
                    } else {
                        tbody.innerHTML = '';
                        response.data.forEach(category => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${category.name}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon edit-btn edit-category" data-id="${category.id}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete-btn delete-category" data-id="${category.id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }
                }
            }
        })
        .catch(error => console.error('Erreur chargement catégories:', error));
}

function openCategoryModal(categoryId = null) {
    const modal = document.getElementById('categoryModal');
    const title = document.getElementById('category-modal-title');
    const form = document.getElementById('category-form');

    form.reset();
    document.getElementById('category-id').value = '';

    if(categoryId) {
        title.textContent = 'Modifier la catégorie';
        loadCategoryData(categoryId);
    } else {
        title.textContent = 'Ajouter une catégorie';
    }

    openModal(modal);
}

function loadCategoryData(categoryId) {
    fetch('api/category.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'read',
            id: categoryId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            const data = response.data;
            document.getElementById('category-id').value = data.id;
            document.getElementById('category-name').value = data.name;
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors du chargement', 'error');
    });
}

function saveCategory() {
    const form = document.getElementById('category-form');
    const categoryId = document.getElementById('category-id').value;

    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        action: categoryId ? 'update' : 'create',
        name: document.getElementById('category-name').value
    };

    if(categoryId) {
        data.id = categoryId;
    }

    fetch('api/category.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            closeModal(document.getElementById('categoryModal'));
            loadCategoriesList();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'enregistrement', 'error');
    });
}

function editCategory(categoryId) {
    openCategoryModal(categoryId);
}

function deleteCategory(categoryId) {
    if(!confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')) {
        return;
    }

    fetch('api/category.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete',
            id: categoryId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            loadCategoriesList();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la suppression', 'error');
    });
}

// ======================================
// TAUX TVA: FONCTIONS
// ======================================

function loadTVARatesList() {
    fetch('api/tva_rate.php?action=list')
        .then(res => res.json())
        .then(response => {
            if(response.success) {
                const tbody = document.getElementById('tvaRatesList');
                if(tbody) {
                    if(response.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="3" style="text-align: center;">Aucun taux TVA enregistré</td></tr>';
                    } else {
                        tbody.innerHTML = '';
                        response.data.forEach(rate => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${rate.rate}%</td>
                                <td>${rate.description}</td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-icon edit-btn edit-tva-rate" data-id="${rate.id}">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button class="btn-icon delete-btn delete-tva-rate" data-id="${rate.id}">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });
                    }
                }
            }
        })
        .catch(error => console.error('Erreur chargement taux TVA:', error));
}

function openTVARateModal(rateId = null) {
    const modal = document.getElementById('tvaRateModal');
    const title = document.getElementById('tva-rate-modal-title');
    const form = document.getElementById('tva-rate-form');

    form.reset();
    document.getElementById('tva-rate-id').value = '';

    if(rateId) {
        title.textContent = 'Modifier le taux TVA';
        loadTVARateData(rateId);
    } else {
        title.textContent = 'Ajouter un taux TVA';
    }

    openModal(modal);
}

function loadTVARateData(rateId) {
    fetch('api/tva_rate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'read',
            id: rateId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            const data = response.data;
            document.getElementById('tva-rate-id').value = data.id;
            document.getElementById('tva-rate-value').value = data.rate;
            document.getElementById('tva-rate-description').value = data.description;
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors du chargement', 'error');
    });
}

function saveTVARate() {
    const form = document.getElementById('tva-rate-form');
    const rateId = document.getElementById('tva-rate-id').value;

    if(!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    const data = {
        action: rateId ? 'update' : 'create',
        rate: parseFloat(document.getElementById('tva-rate-value').value),
        description: document.getElementById('tva-rate-description').value
    };

    if(rateId) {
        data.id = rateId;
    }

    fetch('api/tva_rate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            closeModal(document.getElementById('tvaRateModal'));
            loadTVARatesList();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de l\'enregistrement', 'error');
    });
}

function editTVARate(rateId) {
    openTVARateModal(rateId);
}

function deleteTVARate(rateId) {
    if(!confirm('Êtes-vous sûr de vouloir supprimer ce taux TVA ?')) {
        return;
    }

    fetch('api/tva_rate.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'delete',
            id: rateId
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            loadTVARatesList();
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la suppression', 'error');
    });
}

// ======================================
// QR-INVOICE: GESTION
// ======================================

// Boutons d'édition et d'annulation pour les paramètres QR
const editQRSettingsBtn = document.getElementById('editQRSettingsBtn');
const cancelQREditBtn = document.getElementById('cancelQREditBtn');
const qrSettingsForm = document.getElementById('qr-settings-form');
const qrSettingsDisplay = document.getElementById('qr-settings-display');
const validateIBANBtn = document.getElementById('validateIBANBtn');

if(editQRSettingsBtn) {
    editQRSettingsBtn.addEventListener('click', function() {
        // Afficher le formulaire et cacher l'affichage
        qrSettingsForm.style.display = 'block';
        qrSettingsDisplay.style.display = 'none';
        editQRSettingsBtn.style.display = 'none';
    });
}

if(cancelQREditBtn) {
    cancelQREditBtn.addEventListener('click', function() {
        // Cacher le formulaire et afficher l'affichage
        qrSettingsForm.style.display = 'none';
        qrSettingsDisplay.style.display = 'block';
        editQRSettingsBtn.style.display = 'inline-block';
    });
}

// Validation IBAN
if(validateIBANBtn) {
    validateIBANBtn.addEventListener('click', function() {
        const qrIBAN = document.getElementById('qr_iban').value.trim();

        if(!qrIBAN) {
            alert('Veuillez saisir un QR-IBAN');
            return;
        }

        // Afficher un indicateur de chargement
        const originalHTML = validateIBANBtn.innerHTML;
        validateIBANBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Validation...';
        validateIBANBtn.disabled = true;

        fetch('api/qr_invoice.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'validate_iban',
                iban: qrIBAN
            })
        })
        .then(res => res.json())
        .then(data => {
            // Restaurer le bouton
            validateIBANBtn.innerHTML = originalHTML;
            validateIBANBtn.disabled = false;

            if(data.success) {
                const message = data.is_valid
                    ? `✅ IBAN valide!\n${data.is_qr_iban ? '✅ Ceci est un QR-IBAN' : '⚠️ Ceci n\'est PAS un QR-IBAN (IID non compatible)'}\nFormat: ${data.formatted}`
                    : '❌ IBAN invalide!';
                alert(message);
            } else {
                alert('❌ Erreur: ' + data.message);
            }
        })
        .catch(err => {
            console.error('Erreur:', err);
            validateIBANBtn.innerHTML = originalHTML;
            validateIBANBtn.disabled = false;
            alert('❌ Erreur lors de la validation');
        });
    });
}

// Sauvegarde des paramètres QR
if(qrSettingsForm) {
    qrSettingsForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const qrIban = document.getElementById('qr-iban');
        const bankIban = document.getElementById('bank-iban');

        const formData = {
            action: 'update_qr_settings',
            qr_iban: qrIban ? qrIban.value.trim() : '',
            bank_iban: bankIban ? bankIban.value.trim() : ''
        };

        // Validation
        if(!formData.qr_iban && !formData.bank_iban) {
            alert('Veuillez saisir au moins un IBAN');
            return;
        }

        // Envoyer via l'API company
        fetch('api/company.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(formData)
        })
        .then(res => res.json())
        .then(response => {
            if(response.success) {
                alert('✅ Paramètres QR-facture enregistrés avec succès!');
                // Recharger la page pour afficher les nouvelles valeurs
                location.reload();
            } else {
                alert('❌ Erreur: ' + response.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('❌ Erreur lors de l\'enregistrement');
        });
    });
}

// ======================================
// PROFIL UTILISATEUR: FONCTIONS
// ======================================

function loadUserProfile() {
    fetch('assets/ajax/user_profile.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'get_profile'})
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            const user = response.user;
            if(document.getElementById('profile-username')) {
                document.getElementById('profile-username').textContent = user.username;
            }
            if(document.getElementById('profile-email')) {
                document.getElementById('profile-email').value = user.email;
            }
            if(document.getElementById('profile-created')) {
                const date = new Date(user.created_at);
                document.getElementById('profile-created').textContent =
                    date.toLocaleDateString('fr-CH');
            }
        }
    })
    .catch(error => console.error('Erreur:', error));
}

function updateUserProfile() {
    const email = document.getElementById('profile-email').value.trim();

    if(!email) {
        alert('Email requis');
        return;
    }

    fetch('assets/ajax/user_profile.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'update_profile',
            email: email
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la mise à jour', 'error');
    });
}

function changeUserPassword() {
    const currentPassword = document.getElementById('current-password').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;

    if(!currentPassword || !newPassword || !confirmPassword) {
        alert('Tous les champs sont requis');
        return;
    }

    if(newPassword !== confirmPassword) {
        alert('Les mots de passe ne correspondent pas');
        return;
    }

    if(newPassword.length < 8) {
        alert('Le mot de passe doit contenir au moins 8 caractères');
        return;
    }

    fetch('assets/ajax/user_profile.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            action: 'change_password',
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        })
    })
    .then(res => res.json())
    .then(response => {
        if(response.success) {
            showNotification(response.message, 'success');
            // Reset form
            document.getElementById('current-password').value = '';
            document.getElementById('new-password').value = '';
            document.getElementById('confirm-password').value = '';
        } else {
            showNotification('Erreur: ' + response.message, 'error');
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showNotification('Erreur lors du changement de mot de passe', 'error');
    });
}

// ======================================
// EXPORT DE DONNÉES: FONCTIONS
// ======================================

function exportData() {
    const type = document.getElementById('export-type').value;
    const format = document.getElementById('export-format').value;

    if(!type) {
        alert('Veuillez sélectionner un type d\'export');
        return;
    }

    // Ouvrir dans nouvelle fenêtre pour déclencher téléchargement
    window.open(
        `assets/ajax/data_export.php?type=${type}&format=${format}`,
        '_blank'
    );

    showNotification('Export en cours...', 'success');
}

// Charger le profil au chargement de la page si on est sur la section profil
document.addEventListener('DOMContentLoaded', function() {
    const profileSection = document.getElementById('profile-section');
    if(profileSection) {
        loadUserProfile();
    }

    // Event listener pour le formulaire d'export
    const exportForm = document.getElementById('export-form');
    if(exportForm) {
        exportForm.addEventListener('submit', function(e) {
            e.preventDefault();
            exportData();
        });
    }

    // Event listeners pour le profil
    const updateProfileBtn = document.getElementById('updateProfileBtn');
    if(updateProfileBtn) {
        updateProfileBtn.addEventListener('click', updateUserProfile);
    }

    const changePasswordBtn = document.getElementById('changePasswordBtn');
    if(changePasswordBtn) {
        changePasswordBtn.addEventListener('click', changeUserPassword);
    }
});
