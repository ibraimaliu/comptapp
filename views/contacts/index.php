<?php
// views/contacts/index.php - Vue pour le contrôleur MVC
?>
<div class="adresses-content">
    <!-- En-tête avec titre et bouton d'ajout -->
    <div class="content-header">
        <h1>Adresses et Contacts</h1>
        <a href="index.php?controller=contacts&action=create" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Ajouter un contact
        </a>
    </div>
    
    <!-- Messages de succès/erreur -->
    <?php if(isset($_SESSION['success_message'])): ?>
    <div class="alert alert-success">
        <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>
    
    <?php if(isset($_SESSION['error_message'])): ?>
    <div class="alert alert-error">
        <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>
    
    <!-- Conteneur principal avec barre de menus en haut et contenu en dessous -->
    <div class="main-container">
        <!-- Barre de navigation horizontale en haut -->
        <div class="top-menu-bar">
            <!-- Catégories -->
            <div class="sidebar-section">
                <h3>Catégories</h3>
                <ul class="nav-tabs">
                    <li class="<?php echo $active_tab == 'tous' ? 'active' : ''; ?>">
                        <a href="index.php?controller=contacts&action=index&filter=tous">
                            Tous les contacts
                            <span class="badge"><?php echo $counts['tous']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $active_tab == 'clients' ? 'active' : ''; ?>">
                        <a href="index.php?controller=contacts&action=index&filter=clients">
                            Clients
                            <span class="badge"><?php echo $counts['clients']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $active_tab == 'fournisseurs' ? 'active' : ''; ?>">
                        <a href="index.php?controller=contacts&action=index&filter=fournisseurs">
                            Fournisseurs
                            <span class="badge"><?php echo $counts['fournisseurs']; ?></span>
                        </a>
                    </li>
                    <li class="<?php echo $active_tab == 'autres' ? 'active' : ''; ?>">
                        <a href="index.php?controller=contacts&action=index&filter=autres">
                            Autres
                            <span class="badge"><?php echo $counts['autres']; ?></span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <!-- Recherche avancée -->
            <div class="sidebar-section">
                <h3>Recherche avancée</h3>
                <form id="advanced-search-form" action="index.php" method="get">
                    <input type="hidden" name="controller" value="contacts">
                    <input type="hidden" name="action" value="index">
                    <input type="hidden" name="filter" value="<?php echo $active_tab; ?>">
                    
                    <div class="form-group">
                        <label for="advanced-search">Mots-clés</label>
                        <input type="text" id="advanced-search" name="search" 
                               value="<?php echo htmlspecialchars($search_keywords); ?>"
                               placeholder="Nom, email, téléphone...">
                    </div>
                    
                    <button type="submit" class="btn btn-full">Rechercher</button>
                    <?php if(!empty($search_keywords)): ?>
                    <a href="index.php?controller=contacts&action=index&filter=<?php echo $active_tab; ?>" class="btn btn-light btn-full">
                        Réinitialiser
                    </a>
                    <?php endif; ?>
                </form>
            </div>
            
            <!-- Actions -->
            <div class="sidebar-section">
                <h3>Actions</h3>
                <button class="btn btn-full" id="exportContactsBtn">
                    <i class="fa-solid fa-file-export"></i> Exporter
                </button>
                <button class="btn btn-full" id="importContactsBtn">
                    <i class="fa-solid fa-file-import"></i> Importer
                </button>
            </div>
        </div>
        
        <!-- Contenu principal -->
        <div class="main-content">
            <!-- Barre d'information et recherche rapide -->
            <div class="info-bar">
                <div class="filter-info">
                    <?php if(!empty($search_keywords)): ?>
                    <span class="filter-tag">
                        Recherche: "<?php echo htmlspecialchars($search_keywords); ?>"
                        <a href="index.php?controller=contacts&action=index&filter=<?php echo $active_tab; ?>" class="remove-filter">×</a>
                    </span>
                    <?php endif; ?>
                    
                    <?php if($active_tab != 'tous'): ?>
                    <span class="filter-tag">
                        Catégorie: <?php echo ucfirst($active_tab); ?>
                        <a href="index.php?controller=contacts&action=index" class="remove-filter">×</a>
                    </span>
                    <?php endif; ?>
                </div>
                
                <div class="quick-search">
                    <form id="quick-search-form" action="index.php" method="get">
                        <input type="hidden" name="controller" value="contacts">
                        <input type="hidden" name="action" value="index">
                        <input type="hidden" name="filter" value="<?php echo $active_tab; ?>">
                        <div class="search-input-group">
                            <input type="text" name="search" placeholder="Recherche rapide..." 
                                   value="<?php echo htmlspecialchars($search_keywords); ?>">
                            <button type="submit"><i class="fa-solid fa-magnifying-glass"></i></button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Debug information -->
            <?php if(isset($_GET['debug'])): ?>
            <div class="debug-info" style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;">
                <h4>Informations de debug :</h4>
                <p>Company ID: <?php echo $company_id; ?></p>
                <p>Nombre de contacts: <?php echo count($contacts); ?></p>
                <p>Filtre actif: <?php echo $active_tab; ?></p>
                <p>Recherche: <?php echo $search_keywords; ?></p>
            </div>
            <?php endif; ?>
            
            <!-- Tableau des contacts -->
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nom</th>
                            <th>Type</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Adresse</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contacts-list">
                        <?php if(count($contacts) == 0): ?>
                        <tr class="empty-state">
                            <td colspan="6">
                                <div class="empty-message">
                                    <i class="fa-solid fa-address-book"></i>
                                    <p>Aucun contact trouvé</p>
                                    <?php if(!empty($search_keywords) || $active_tab != 'tous'): ?>
                                    <a href="index.php?controller=contacts&action=index" class="btn btn-sm">Afficher tous les contacts</a>
                                    <?php else: ?>
                                    <a href="index.php?controller=contacts&action=create" class="btn btn-sm">Ajouter un contact</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach($contacts as $contact): ?>
                        <tr>
                            <td class="contact-name">
                                <?php 
                                // Afficher le nom du contact (flexible selon les colonnes disponibles)
                                $name = '';
                                if(isset($contact['nom'])) $name .= $contact['nom'] . ' ';
                                if(isset($contact['prenom'])) $name .= $contact['prenom'] . ' ';
                                if(isset($contact['name'])) $name .= $contact['name'] . ' ';
                                if(isset($contact['firstname'])) $name .= $contact['firstname'] . ' ';
                                if(isset($contact['raison_sociale'])) $name .= $contact['raison_sociale'] . ' ';
                                
                                echo trim($name) ?: 'Contact #' . $contact['id'];
                                ?>
                            </td>
                            <td>
                                <?php 
                                if (isset($contact['type'])) {
                                    echo '<span class="contact-badge contact-badge-' . $contact['type'] . '">';
                                    switch($contact['type']) {
                                        case 'client': echo 'Client'; break;
                                        case 'fournisseur': echo 'Fournisseur'; break;
                                        default: echo 'Autre'; break;
                                    }
                                    echo '</span>';
                                } else {
                                    echo '<span class="contact-badge contact-badge-autre">Autre</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                echo $contact['email'] ?? $contact['mail'] ?? $contact['courriel'] ?? '-';
                                ?>
                            </td>
                            <td>
                                <?php
                                echo $contact['phone'] ?? $contact['telephone'] ?? $contact['tel'] ?? $contact['mobile'] ?? '-';
                                ?>
                            </td>
                            <td>
                                <?php
                                $address_parts = [];
                                
                                // Adresse
                                if (!empty($contact['address'] ?? $contact['adresse'] ?? '')) {
                                    $address_parts[] = $contact['address'] ?? $contact['adresse'];
                                }
                                
                                // Code postal et ville
                                $location = '';
                                $postal = $contact['postal_code'] ?? $contact['code_postal'] ?? $contact['npa'] ?? '';
                                $city = $contact['city'] ?? $contact['ville'] ?? $contact['localite'] ?? '';
                                
                                if ($postal) $location .= $postal . ' ';
                                if ($city) $location .= $city;
                                
                                if (trim($location)) {
                                    $address_parts[] = trim($location);
                                }
                                
                                // Pays
                                if (!empty($contact['country'] ?? $contact['pays'] ?? '')) {
                                    $address_parts[] = $contact['country'] ?? $contact['pays'];
                                }
                                
                                echo count($address_parts) ? implode(', ', $address_parts) : '-';
                                ?>
                            </td>
                            <td class="actions-cell">
                                <a href="index.php?controller=contacts&action=edit&id=<?php echo $contact['id']; ?>" 
                                   class="btn-icon" title="Modifier">
                                    <i class="fa-solid fa-pen"></i>
                                </a>
                                <button class="btn-icon delete-contact" 
                                        data-id="<?php echo $contact['id']; ?>" 
                                        title="Supprimer">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Script pour la suppression AJAX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion de la suppression
    document.querySelectorAll('.delete-contact').forEach(button => {
        button.addEventListener('click', function() {
            const contactId = this.dataset.id;
            
            if (confirm('Êtes-vous sûr de vouloir supprimer ce contact ?')) {
                const formData = new FormData();
                formData.append('id', contactId);
                
                fetch('index.php?controller=contacts&action=delete', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Recharger la page pour mettre à jour la liste
                        location.reload();
                    } else {
                        alert('Erreur lors de la suppression: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur lors de la suppression du contact');
                });
            }
        });
    });
});
</script>

<!-- CSS pour les badges -->
<style>
.contact-badge {
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 600;
    text-transform: uppercase;
}

.contact-badge-client {
    background-color: #e8f5e8;
    color: #2e7d32;
}

.contact-badge-fournisseur {
    background-color: #e3f2fd;
    color: #1976d2;
}

.contact-badge-autre {
    background-color: #f3e5f5;
    color: #7b1fa2;
}

.debug-info {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    padding: 10px;
    margin: 10px 0;
    border-radius: 4px;
}

.alert {
    padding: 12px;
    margin: 10px 0;
    border-radius: 4px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}
</style>