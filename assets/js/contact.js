// contacts.js - JavaScript complet pour la gestion des contacts

document.addEventListener('DOMContentLoaded', function() {
    console.log('Script contacts chargé');
    
    // Variables globales
    const contactModal = document.getElementById('contactModal');
    const viewContactModal = document.getElementById('viewContactModal');
    const viewAddressModal = document.getElementById('viewAddressModal');
    const importContactsModal = document.getElementById('importContactsModal');
    
    // ================================
    // GESTION DES MODALES
    // ================================
    
    // Fonction pour ouvrir une modale
    function openModal(modal) {
        if (modal) {
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden'; // Empêcher le scroll
        }
    }
    
    // Fonction pour fermer une modale
    function closeModal(modal) {
        if (modal) {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto'; // Rétablir le scroll
        }
    }
    
    // Fermer les modales avec le bouton X
    document.querySelectorAll('.modal .close').forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            closeModal(modal);
        });
    });
    
    // Fermer les modales en cliquant en dehors
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeModal(modal);
            }
        });
    });
    
    // ================================
    // BOUTON AJOUTER CONTACT
    // ================================
    
    // Bouton principal "Ajouter un contact"
    const addContactBtn = document.getElementById('addContactBtn');
    if (addContactBtn) {
        addContactBtn.addEventListener('click', function() {
            console.log('Clic sur ajouter contact');
            openAddContactModal();
        });
    }
    
    // Bouton dans le message vide
    const emptyAddContactBtn = document.getElementById('emptyAddContactBtn');
    if (emptyAddContactBtn) {
        emptyAddContactBtn.addEventListener('click', function() {
            console.log('Clic sur ajouter contact (vide)');
            openAddContactModal();
        });
    }
    
    // Fonction pour ouvrir la modale d'ajout
    function openAddContactModal() {
        // Réinitialiser le formulaire
        const form = document.getElementById('contact-form');
        if (form) {
            form.reset();
            document.getElementById('contact-id').value = '';
            document.getElementById('contact-modal-title').textContent = 'Ajouter un contact';
            document.getElementById('saveContactBtn').textContent = 'Enregistrer';
        }
        
        openModal(contactModal);
    }
    
    // ================================
    // FORMULAIRE DE CONTACT
    // ================================
    
    const contactForm = document.getElementById('contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Soumission du formulaire contact');
            saveContact();
        });
    }
    
    // Fonction pour sauvegarder un contact
    async function saveContact() {
        const form = document.getElementById('contact-form');
        const formData = new FormData(form);
        const contactId = document.getElementById('contact-id').value;
        
        // Validation côté client
        const name = formData.get('name').trim();
        const type = formData.get('type');
        
        if (!name) {
            alert('Le nom est obligatoire');
            document.getElementById('contact-name').focus();
            return;
        }
        
        if (!type) {
            alert('Le type de contact est obligatoire');
            document.getElementById('contact-type').focus();
            return;
        }
        
        try {
            // Déterminer l'URL selon le mode (ajout ou modification)
            let url, method;
            
            if (contactId) {
                // Mode modification - utiliser le système MVC
                url = 'index.php?controller=contacts&action=update';
                method = 'POST';
                formData.append('id', contactId);
            } else {
                // Mode ajout - utiliser le système MVC
                url = 'index.php?controller=contacts&action=store';
                method = 'POST';
            }
            
            // Si le système MVC n'existe pas, fallback vers un script AJAX
            const response = await fetch(url, {
                method: method,
                body: formData
            });
            
            if (response.ok) {
                // Si c'est une réponse JSON (pour AJAX)
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    const result = await response.json();
                    if (result.success) {
                        showMessage('Contact sauvegardé avec succès !', 'success');
                        closeModal(contactModal);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showMessage('Erreur : ' + result.message, 'error');
                    }
                } else {
                    // Réponse HTML normale - redirection ou rechargement
                    showMessage('Contact sauvegardé avec succès !', 'success');
                    closeModal(contactModal);
                    setTimeout(() => location.reload(), 1000);
                }
            } else {
                throw new Error('Erreur réseau');
            }
            
        } catch (error) {
            console.error('Erreur:', error);
            // Fallback : essayer l'ancien système ou un script dédié
            savContactFallback(formData, contactId);
        }
    }
    
    // Fonction de fallback pour la sauvegarde
    async function savContactFallback(formData, contactId) {
        try {
            // Essayer avec un script AJAX dédié
            const url = contactId ? 'ajax/update_contact.php' : 'ajax/add_contact.php';
            
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showMessage('Contact sauvegardé avec succès !', 'success');
                closeModal(contactModal);
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('Erreur : ' + result.message, 'error');
            }
            
        } catch (error) {
            console.error('Erreur fallback:', error);
            showMessage('Erreur lors de la sauvegarde. Veuillez réessayer.', 'error');
        }
    }
    
    // ================================
    // ACTIONS SUR LES CONTACTS
    // ================================
    
    // Boutons de modification
    document.addEventListener('click', function(e) {
        if (e.target.closest('.edit-contact')) {
            const btn = e.target.closest('.edit-contact');
            const contactId = btn.dataset.id;
            console.log('Modifier contact:', contactId);
            editContact(contactId);
        }
        
        if (e.target.closest('.view-contact')) {
            const btn = e.target.closest('.view-contact');
            const contactId = btn.dataset.id;
            console.log('Voir contact:', contactId);
            viewContact(contactId);
        }
        
        if (e.target.closest('.delete-contact')) {
            const btn = e.target.closest('.delete-contact');
            const contactId = btn.dataset.id;
            console.log('Supprimer contact:', contactId);
            deleteContact(contactId);
        }
        
        if (e.target.closest('.view-address')) {
            const btn = e.target.closest('.view-address');
            const addressId = btn.dataset.id;
            console.log('Voir adresse:', addressId);
            viewAddress(addressId);
        }
    });
    
    // Fonction pour modifier un contact
    async function editContact(contactId) {
        try {
            const response = await fetch(`index.php?controller=contacts&action=get&id=${contactId}`);
            const result = await response.json();
            
            if (result.success) {
                const contact = result.contact;
                
                // Remplir le formulaire
                document.getElementById('contact-id').value = contact.id;
                document.getElementById('contact-type').value = contact.type || 'autre';
                document.getElementById('contact-name').value = contact.name || '';
                document.getElementById('contact-email').value = contact.email || '';
                document.getElementById('contact-phone').value = contact.phone || '';
                document.getElementById('contact-address').value = contact.address || '';
                document.getElementById('contact-postal').value = contact.postal_code || '';
                document.getElementById('contact-city').value = contact.city || '';
                document.getElementById('contact-country').value = contact.country || 'Suisse';
                
                // Changer le titre
                document.getElementById('contact-modal-title').textContent = 'Modifier le contact';
                document.getElementById('saveContactBtn').textContent = 'Mettre à jour';
                
                openModal(contactModal);
            } else {
                showMessage('Impossible de charger le contact', 'error');
            }
        } catch (error) {
            console.error('Erreur lors du chargement:', error);
            showMessage('Erreur lors du chargement du contact', 'error');
        }
    }
    
    // Fonction pour voir un contact
    async function viewContact(contactId) {
        try {
            const response = await fetch(`index.php?controller=contacts&action=get&id=${contactId}`);
            const result = await response.json();
            
            if (result.success) {
                const contact = result.contact;
                displayContactDetails(contact);
                openModal(viewContactModal);
            } else {
                showMessage('Impossible de charger le contact', 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            showMessage('Erreur lors du chargement du contact', 'error');
        }
    }
    
    // Fonction pour afficher les détails d'un contact
    function displayContactDetails(contact) {
        const detailsDiv = document.getElementById('contact-details');
        
        const html = `
            <div class="contact-detail-grid">
                <div class="detail-item">
                    <label>Type:</label>
                    <span class="contact-badge contact-badge-${contact.type || 'autre'}">
                        ${getTypeLabel(contact.type)}
                    </span>
                </div>
                <div class="detail-item">
                    <label>Nom:</label>
                    <span>${contact.name || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Email:</label>
                    <span>${contact.email ? `<a href="mailto:${contact.email}">${contact.email}</a>` : '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Téléphone:</label>
                    <span>${contact.phone ? `<a href="tel:${contact.phone}">${contact.phone}</a>` : '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Adresse:</label>
                    <span>${contact.address || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Code postal:</label>
                    <span>${contact.postal_code || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Ville:</label>
                    <span>${contact.city || '-'}</span>
                </div>
                <div class="detail-item">
                    <label>Pays:</label>
                    <span>${contact.country || '-'}</span>
                </div>
            </div>
        `;
        
        detailsDiv.innerHTML = html;
    }
    
    // Fonction pour supprimer un contact
    async function deleteContact(contactId) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce contact ?')) {
            return;
        }
        
        try {
            const formData = new FormData();
            formData.append('id', contactId);
            
            const response = await fetch('index.php?controller=contacts&action=delete', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showMessage('Contact supprimé avec succès !', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showMessage('Erreur : ' + result.message, 'error');
            }
        } catch (error) {
            console.error('Erreur:', error);
            showMessage('Erreur lors de la suppression', 'error');
        }
    }
    
    // Fonction pour voir une adresse
    function viewAddress(addressId) {
        // Implémenter selon vos besoins
        alert('Fonction de visualisation d\'adresse à implémenter');
    }
    
    // ================================
    // AUTRES BOUTONS
    // ================================
    
    // Bouton d'exportation
    const exportBtn = document.getElementById('exportContactsBtn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function() {
            console.log('Export contacts');
            // Implémenter l'export
            window.location.href = 'index.php?controller=contacts&action=export';
        });
    }
    
    // Bouton d'importation
    const importBtn = document.getElementById('importContactsBtn');
    if (importBtn) {
        importBtn.addEventListener('click', function() {
            console.log('Import contacts');
            openModal(importContactsModal);
        });
    }
    
    // ================================
    // FONCTIONS UTILITAIRES
    // ================================
    
    // Fonction pour afficher des messages
    function showMessage(message, type = 'info') {
        // Créer un élément de message
        const messageDiv = document.createElement('div');
        messageDiv.className = `alert alert-${type}`;
        messageDiv.textContent = message;
        
        // Insérer en haut du contenu
        const content = document.querySelector('.adresses-content');
        if (content) {
            content.insertBefore(messageDiv, content.firstChild);
            
            // Supprimer le message après 5 secondes
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.parentNode.removeChild(messageDiv);
                }
            }, 5000);
        } else {
            // Fallback : alert
            alert(message);
        }
    }
    
    // Fonction pour obtenir le libellé d'un type
    function getTypeLabel(type) {
        switch(type) {
            case 'client': return 'Client';
            case 'fournisseur': return 'Fournisseur';
            case 'autre': return 'Autre';
            default: return 'Autre';
        }
    }
    
    // Bouton de fermeture des modales de visualisation
    const closeViewContactBtn = document.getElementById('closeViewContactBtn');
    if (closeViewContactBtn) {
        closeViewContactBtn.addEventListener('click', function() {
            closeModal(viewContactModal);
        });
    }
    
    const closeViewAddressBtn = document.getElementById('closeViewAddressBtn');
    if (closeViewAddressBtn) {
        closeViewAddressBtn.addEventListener('click', function() {
            closeModal(viewAddressModal);
        });
    }
    
    console.log('Script contacts entièrement chargé');
});