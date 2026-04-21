/**
 * JavaScript pour la gestion des employés
 */

let currentEmployee = null;
let allEmployees = [];

// ========== CHARGEMENT INITIAL ==========
document.addEventListener('DOMContentLoaded', function() {
    loadEmployees();
    setupEventListeners();
});

// ========== EVENT LISTENERS ==========
function setupEventListeners() {
    // Recherche en temps réel
    const searchInput = document.getElementById('search-input');
    if(searchInput) {
        searchInput.addEventListener('input', debounce(function() {
            const term = this.value.trim();
            if(term.length >= 2) {
                searchEmployees(term);
            } else if(term.length === 0) {
                loadEmployees();
            }
        }, 300));
    }
}

// ========== CHARGER LA LISTE DES EMPLOYÉS ==========
function loadEmployees() {
    const activeOnly = document.getElementById('filter-status')?.value === 'active';

    fetch(`assets/ajax/employees.php?action=list&active_only=${activeOnly}`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                allEmployees = data.data;
                displayEmployees(allEmployees);
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Erreur de chargement des employés');
        });
}

// ========== AFFICHER LA LISTE ==========
function displayEmployees(employees) {
    const listContainer = document.getElementById('employees-list');

    if(!employees || employees.length === 0) {
        listContainer.innerHTML = `
            <div class="empty-state">
                <i class="fa-solid fa-users-slash"></i>
                <p>Aucun employé trouvé</p>
                <button class="btn btn-primary" onclick="openEmployeeModal()">
                    <i class="fa-solid fa-plus"></i> Ajouter votre premier employé
                </button>
            </div>
        `;
        return;
    }

    let html = '<div class="employees-grid">';

    employees.forEach(emp => {
        const statusClass = emp.is_active == 1 ? 'active' : 'inactive';
        const statusText = emp.is_active == 1 ? 'Actif' : 'Inactif';
        const employmentTypeLabels = {
            'full_time': 'Temps plein',
            'part_time': 'Temps partiel',
            'contractor': 'Contractuel',
            'intern': 'Stagiaire'
        };

        html += `
            <div class="employee-card ${statusClass}" data-id="${emp.id}">
                <div class="employee-header">
                    <div class="employee-avatar">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <div class="employee-info">
                        <h3>${escapeHtml(emp.first_name)} ${escapeHtml(emp.last_name)}</h3>
                        <p class="employee-title">${escapeHtml(emp.job_title)}</p>
                        <p class="employee-number">${escapeHtml(emp.employee_number || '')}</p>
                    </div>
                    <div class="employee-status ${statusClass}">
                        <span class="status-badge">${statusText}</span>
                    </div>
                </div>
                <div class="employee-details">
                    <div class="detail-row">
                        <i class="fa-solid fa-briefcase"></i>
                        <span>${employmentTypeLabels[emp.employment_type] || emp.employment_type}</span>
                    </div>
                    <div class="detail-row">
                        <i class="fa-solid fa-calendar"></i>
                        <span>Embauché: ${formatDate(emp.hire_date)}</span>
                    </div>
                    <div class="detail-row">
                        <i class="fa-solid fa-money-bill"></i>
                        <span>${formatAmount(emp.base_salary)} ${emp.currency}/mois</span>
                    </div>
                    ${emp.email ? `
                    <div class="detail-row">
                        <i class="fa-solid fa-envelope"></i>
                        <span>${escapeHtml(emp.email)}</span>
                    </div>
                    ` : ''}
                    ${emp.phone ? `
                    <div class="detail-row">
                        <i class="fa-solid fa-phone"></i>
                        <span>${escapeHtml(emp.phone)}</span>
                    </div>
                    ` : ''}
                </div>
                <div class="employee-actions">
                    <button class="btn btn-sm btn-primary" onclick="editEmployee(${emp.id})">
                        <i class="fa-solid fa-edit"></i> Modifier
                    </button>
                    <button class="btn btn-sm btn-secondary" onclick="viewPayrolls(${emp.id})">
                        <i class="fa-solid fa-file-invoice"></i> Salaires
                    </button>
                    ${emp.is_active == 1 ? `
                        <button class="btn btn-sm btn-warning" onclick="deactivateEmployee(${emp.id})">
                            <i class="fa-solid fa-ban"></i> Désactiver
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
    });

    html += '</div>';
    listContainer.innerHTML = html;
}

// ========== RECHERCHER ==========
function searchEmployees(term) {
    fetch(`assets/ajax/employees.php?action=search&term=${encodeURIComponent(term)}`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                displayEmployees(data.data);
            }
        })
        .catch(error => console.error('Erreur:', error));
}

// ========== FILTRER ==========
function filterEmployees() {
    loadEmployees();
}

// ========== OUVRIR MODAL NOUVEL EMPLOYÉ ==========
function openEmployeeModal() {
    currentEmployee = null;
    document.getElementById('modal-title').textContent = 'Nouvel Employé';
    document.getElementById('employee-form').reset();
    document.getElementById('employee-id').value = '';
    document.getElementById('is-active').checked = true;
    document.getElementById('employee-modal').style.display = 'flex';
    switchTab('general');
}

// ========== FERMER MODAL ==========
function closeEmployeeModal() {
    document.getElementById('employee-modal').style.display = 'none';
    currentEmployee = null;
}

// ========== SWITCH TAB ==========
function switchTab(tabName) {
    // Désactiver tous les onglets
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    // Activer l'onglet sélectionné
    document.querySelector(`[onclick="switchTab('${tabName}')"]`).classList.add('active');
    document.getElementById(`tab-${tabName}`).classList.add('active');
}

// ========== MODIFIER UN EMPLOYÉ ==========
function editEmployee(id) {
    fetch(`assets/ajax/employees.php?action=get&id=${id}`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                currentEmployee = data.data;
                fillEmployeeForm(data.data);
                document.getElementById('modal-title').textContent = 'Modifier l\'employé';
                document.getElementById('employee-modal').style.display = 'flex';
            } else {
                showError(data.message);
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            showError('Erreur de chargement');
        });
}

// ========== REMPLIR LE FORMULAIRE ==========
function fillEmployeeForm(employee) {
    document.getElementById('employee-id').value = employee.id;
    document.getElementById('employee-number').value = employee.employee_number || '';
    document.getElementById('first-name').value = employee.first_name;
    document.getElementById('last-name').value = employee.last_name;
    document.getElementById('email').value = employee.email || '';
    document.getElementById('phone').value = employee.phone || '';
    document.getElementById('address').value = employee.address || '';
    document.getElementById('postal-code').value = employee.postal_code || '';
    document.getElementById('city').value = employee.city || '';
    document.getElementById('country').value = employee.country || 'Suisse';
    document.getElementById('hire-date').value = employee.hire_date;
    document.getElementById('termination-date').value = employee.termination_date || '';
    document.getElementById('job-title').value = employee.job_title;
    document.getElementById('department').value = employee.department || '';
    document.getElementById('employment-type').value = employee.employment_type;
    document.getElementById('contract-type').value = employee.contract_type;
    document.getElementById('salary-type').value = employee.salary_type;
    document.getElementById('base-salary').value = employee.base_salary;
    document.getElementById('currency').value = employee.currency;
    document.getElementById('hours-per-week').value = employee.hours_per_week;
    document.getElementById('avs-number').value = employee.avs_number || '';
    document.getElementById('accident-insurance').value = employee.accident_insurance || '';
    document.getElementById('pension-fund').value = employee.pension_fund || '';
    document.getElementById('iban').value = employee.iban || '';
    document.getElementById('bank-name').value = employee.bank_name || '';
    document.getElementById('family-allowances').checked = employee.family_allowances == 1;
    document.getElementById('num-children').value = employee.num_children || 0;
    document.getElementById('is-active').checked = employee.is_active == 1;
    document.getElementById('notes').value = employee.notes || '';
}

// ========== ENREGISTRER ==========
function saveEmployee() {
    console.log('🔵 saveEmployee() appelée');

    const form = document.getElementById('employee-form');
    if(!form) {
        console.error('❌ Formulaire non trouvé!');
        alert('Erreur: Formulaire non trouvé');
        return;
    }

    console.log('✅ Formulaire trouvé');

    if(!form.checkValidity()) {
        console.warn('⚠️ Formulaire invalide');
        form.reportValidity();
        return;
    }

    console.log('✅ Formulaire valide');

    const employeeId = document.getElementById('employee-id').value;
    const action = employeeId ? 'update' : 'create';

    console.log('📝 Action:', action, 'ID:', employeeId || 'nouveau');

    const employeeData = {
        action: action,
        id: employeeId || undefined,
        employee_number: document.getElementById('employee-number').value,
        first_name: document.getElementById('first-name').value,
        last_name: document.getElementById('last-name').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value,
        address: document.getElementById('address').value,
        postal_code: document.getElementById('postal-code').value,
        city: document.getElementById('city').value,
        country: document.getElementById('country').value,
        hire_date: document.getElementById('hire-date').value,
        termination_date: document.getElementById('termination-date').value,
        job_title: document.getElementById('job-title').value,
        department: document.getElementById('department').value,
        employment_type: document.getElementById('employment-type').value,
        contract_type: document.getElementById('contract-type').value,
        salary_type: document.getElementById('salary-type').value,
        base_salary: parseFloat(document.getElementById('base-salary').value),
        currency: document.getElementById('currency').value,
        hours_per_week: parseFloat(document.getElementById('hours-per-week').value),
        avs_number: document.getElementById('avs-number').value,
        accident_insurance: document.getElementById('accident-insurance').value,
        pension_fund: document.getElementById('pension-fund').value,
        iban: document.getElementById('iban').value,
        bank_name: document.getElementById('bank-name').value,
        family_allowances: document.getElementById('family-allowances').checked ? 1 : 0,
        num_children: parseInt(document.getElementById('num-children').value) || 0,
        is_active: document.getElementById('is-active').checked ? 1 : 0,
        notes: document.getElementById('notes').value
    };

    fetch('assets/ajax/employees.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(employeeData)
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showSuccess(data.message);
            closeEmployeeModal();
            loadEmployees();
        } else {
            showError(data.message);
            if(data.limits) {
                // Afficher les limites si applicable
                console.log('Limites:', data.limits);
            }
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showError('Erreur de sauvegarde');
    });
}

// ========== DÉSACTIVER UN EMPLOYÉ ==========
function deactivateEmployee(id) {
    if(!confirm('Voulez-vous vraiment désactiver cet employé?')) {
        return;
    }

    fetch('assets/ajax/employees.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'deactivate', id: id})
    })
    .then(response => response.json())
    .then(data => {
        if(data.success) {
            showSuccess(data.message);
            loadEmployees();
        } else {
            showError(data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showError('Erreur');
    });
}

// ========== VOIR LES SALAIRES ==========
function viewPayrolls(employeeId) {
    window.location.href = `index.php?page=payroll&employee_id=${employeeId}`;
}

// ========== MODAL UPGRADE ==========
function showUpgradeModal() {
    document.getElementById('upgrade-modal').style.display = 'flex';
}

function closeUpgradeModal() {
    document.getElementById('upgrade-modal').style.display = 'none';
}

// ========== UTILITAIRES ==========
function formatAmount(amount) {
    return new Intl.NumberFormat('fr-CH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    }).format(amount);
}

function formatDate(dateStr) {
    if(!dateStr) return '';
    const date = new Date(dateStr);
    return date.toLocaleDateString('fr-CH');
}

function escapeHtml(text) {
    if(!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func.apply(this, args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

function showSuccess(message) {
    alert('✅ ' + message);
}

function showError(message) {
    alert('❌ ' + message);
}

// Fermer les modals en cliquant à l'extérieur
window.onclick = function(event) {
    const employeeModal = document.getElementById('employee-modal');
    const upgradeModal = document.getElementById('upgrade-modal');

    if (event.target == employeeModal) {
        closeEmployeeModal();
    }
    if (event.target == upgradeModal) {
        closeUpgradeModal();
    }
}
