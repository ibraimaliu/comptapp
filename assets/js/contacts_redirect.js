// JavaScript pour la gestion des contacts avec redirection
document.addEventListener('DOMContentLoaded', function() {
    
    // Éléments du DOM
    const viewContactModal = document.getElementById('viewContactModal');
    const importContactsModal = document.getElementById('importContactsModal');
    
    // Boutons d'ajout de contact - redirection vers nouvelle page
    const addContactBtn = document.getElementById('addContactBtn');
    const emptyAddContactBtn = document.getElementById('emptyAddContactBtn');
    
    // Boutons de fermeture des modals
    const closeButtons = document.querySelectorAll('.modal .close');
    const closeViewContactBtn = document.getElementById('closeViewContactBtn');
    
    // Fonctions utilitaires pour les modals
    function openModal(modal) {
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Empêcher le scroll de la page
        }
    }
    
    function closeModal(modal) {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Restaurer le scroll
        }
    }
    
    // Rediriger vers la page de création de contact
    function redirectToNewContact() {
        window.location.href = 'nouvelle_adresse.php';
    }
    
    // Event listeners pour les boutons d'ajout - redirection
    if (addContactBtn) {
        addContactBtn.addEventListener('click', function(e) {
            e.preventDefault();
            redirectToNewContact();
        });
    }
    
    if (emptyAddContactBtn) {
        emptyAddContactBtn.addEventListener('click', function(e) {
            e.preventDefault();
            redirectToNewContact();
        });
    }
    
    // Event listeners pour les boutons de fermeture des modals restants
    closeButtons.forEach(function(closeBtn) {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal);
        });
    });
    
    if (closeViewContactBtn) {
        closeViewContactBtn.addEventListener('click', function() {
            closeModal(viewContactModal);
        });
    }
    
    // Fermer les modals en cliquant en dehors
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target);
        }
    });
    
    // Gestion des actions sur les contacts existants
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-contact')) {
            e.preventDefault();
            const contactId = e.target.closest('.edit-contact').getAttribute('data-id');
            editContact(contactId);
        }
        
        if (e.target.closest('.view-contact')) {
            e.preventDefault();
            const contactId = e.target.closest('.view-contact').getAttribute('data-id');
            viewContact(contactId);
        }
        
        if (e.target.closest('.delete-contact')) {
            e.preventDefault();
            const contactId = e.target.closest('.delete-contact').getAttribute('data-id');
            deleteContact(contactId);
        }
    });
    
    // Fonction pour éditer un contact - redirection vers page d'édition
    function editContact(contactId) {
        window.location.href = `modifier_adresse.php?id=${contactId}`;
    }
    
    // Fonction pour voir un contact
    function viewContact(contactId) {
        fetch(`ajax/contacts.php?action=get&id=${contactId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.contact) {
                const contact = data.contact;
                const detailsDiv = document.getElementById('contact-details');
                
                detailsDiv.innerHTML = `
                    <div class="contact-detail-grid">
                        <div class="contact-detail-item">
                            <strong>Type:</strong> 
                            <span class="contact-badge contact-badge-${contact.type || 'autre'}">
                                ${getTypeLabel(contact.type)}
                            </span>
                        </div>
                        <div class="contact-detail-item">
                            <strong>Nom:</strong> ${contact.name || 'Non spécifié'}
                        </div>
                        <div class="contact-detail-item">
                            <strong>Email:</strong> 
                            ${contact.email ? `<a href="mailto:${contact.email}">${contact.email}</a>` : 'Non spécifié'}
                        </div>
                        <div class="contact-detail-item">
                            <strong>Téléphone:</strong> 
                            ${contact.phone ? `<a href="tel:${contact.phone}">${contact.phone}</a>` : 'Non spécifié'}
                        </div>
                        <div class="contact-detail-item">
                            <strong>Adresse:</strong> ${contact.address || 'Non spécifiée'}
                        </div>
                        <div class="contact-detail-item">
                            <strong>Code postal:</strong> ${contact.postal_code || 'Non spécifié'}
                        </div>
                        <div class="contact-detail-item">
                            <strong>Ville:</strong> ${contact.city || 'Non spécifiée'}
                        </div>
                        <div class="contact-detail-item">
                            <strong>Pays:</strong> ${contact.country || 'Non spécifié'}
                        </div>
                    </div>
                    
                    <div class="contact-actions">
                        <button class="btn btn-primary" onclick="window.location.href='modifier_adresse.php?id=${contactId}'">
                            <i class="fa-solid fa-pen"></i> Modifier
                        </button>
                    </div>
                `;
                
                openModal(viewContactModal);
            } else {
                alert('Erreur lors du chargement du contact');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Une erreur est survenue');
        });
    }
    
    // Fonction utilitaire pour les labels de type
    function getTypeLabel(type) {
        switch(type) {
            case 'client': return 'Client';
            case 'fournisseur': return 'Fournisseur';
            default: return 'Autre';
        }
    }
    
    // Fonction pour supprimer un contact
    function deleteContact(contactId) {
        if (confirm('Êtes-vous sûr de vouloir supprimer ce contact ?')) {
            fetch('ajax/contacts.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=delete&id=${contactId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Recharger la page
                    window.location.reload();
                } else {
                    alert('Erreur lors de la suppression: ' + (data.message || 'Une erreur est survenue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de la suppression');
            });
        }
    }
    
    // Gestion des boutons d'export et d'import
    const exportContactsBtn = document.getElementById('exportContactsBtn');
    const importContactsBtn = document.getElementById('importContactsBtn');
    
    if (exportContactsBtn) {
        exportContactsBtn.addEventListener('click', function() {
            window.location.href = `ajax/contacts.php?action=export&company_id=${company_id}`;
        });
    }
    
    if (importContactsBtn) {
        importContactsBtn.addEventListener('click', function() {
            openModal(importContactsModal);
        });
    }
    
    // Gestion de l'import
    const importForm = document.getElementById('import-contacts-form');
    if (importForm) {
        importForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('company_id', company_id);
            formData.append('action', 'import');
            
            fetch('ajax/contacts.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Import réussi: ' + data.message);
                    closeModal(importContactsModal);
                    window.location.reload();
                } else {
                    alert('Erreur lors de l\'import: ' + (data.message || 'Une erreur est survenue'));
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue lors de l\'import');
            });
        });
    }
});

// JavaScript spécifique pour la page nouvelle_adresse.php
if (window.location.pathname.includes('nouvelle_adresse.php')) {
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('contact-form');
        
        if (form) {
            // Validation en temps réel
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                field.addEventListener('blur', function() {
                    validateField(this);
                });
                
                field.addEventListener('input', function() {
                    if (this.classList.contains('invalid')) {
                        validateField(this);
                    }
                });
            });
            
            // Validation avant soumission
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Veuillez remplir tous les champs obligatoires.');
                    return false;
                }
                
                // Afficher un indicateur de chargement
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enregistrement...';
                    submitBtn.disabled = true;
                }
            });
        }
        
        function validateField(field) {
            const isValid = field.checkValidity() && field.value.trim() !== '';
            
            if (isValid) {
                field.classList.remove('invalid');
                field.classList.add('valid');
            } else {
                field.classList.remove('valid');
                field.classList.add('invalid');
            }
            
            return isValid;
        }
        
        // Auto-formatage du code postal français
        const postalCodeField = document.getElementById('postal_code');
        if (postalCodeField) {
            postalCodeField.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, ''); // Supprimer tout sauf les chiffres
                if (value.length > 5) {
                    value = value.substring(0, 5);
                }
                this.value = value;
            });
        }
        
        // Auto-formatage des numéros de téléphone
        const phoneFields = document.querySelectorAll('input[type="tel"]');
        phoneFields.forEach(field => {
            field.addEventListener('input', function() {
                let value = this.value.replace(/\D/g, '');
                if (value.startsWith('33')) {
                    value = '+' + value;
                } else if (value.startsWith('0') && value.length === 10) {
                    value = '+33' + value.substring(1);
                }
                this.value = value;
            });
        });
    });
}