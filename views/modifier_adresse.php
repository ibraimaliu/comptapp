<?php
session_start();

// Inclure les modèles nécessaires avec des chemins absolus
include_once dirname(__DIR__) . '/config/database.php';
include_once dirname(__DIR__) . '/models/Contact.php';
include_once 'includes/header.php';

// Vérifier si une société est sélectionnée
$company_id = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;

// Rediriger vers l'accueil si aucune société n'est sélectionnée
if(!$company_id) {
    redirect('index.php?page=home');
}

// Vérifier qu'un ID est fourni
$contact_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$contact_id) {
    redirect('index.php?page=adresses');
}

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();
$contact = new Contact($db);

// Variables pour les messages
$success_message = '';
$error_message = '';

// Récupérer les données du contact
$contactData = $contact->getById($contact_id, $contact->hasCompanyId() ? $company_id : null);

if(!$contactData) {
    redirect('index.php?page=adresses');
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validation des champs obligatoires
        if (empty($_POST['name']) || empty($_POST['type'])) {
            throw new Exception('Le nom et le type sont obligatoires');
        }

        // Vérifier que le contact appartient à la bonne société
        if($contact->hasCompanyId() && !$contact->belongsToCompany($contact_id, $company_id)) {
            throw new Exception('Contact non trouvé');
        }

        // Préparer les données en adaptant aux noms de colonnes de la table
        $data = ['id' => $contact_id];
        
        // Mapper les champs du formulaire aux colonnes de la table
        $field_mapping = [
            'type' => ['type'],
            'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
            'firstname' => ['firstname', 'prenom'],
            'email' => ['email', 'mail', 'courriel'],
            'phone' => ['phone', 'telephone', 'tel'],
            'mobile' => ['mobile', 'portable'],
            'fax' => ['fax'],
            'website' => ['website', 'site_web'],
            'address' => ['address', 'adresse', 'rue'],
            'postal_code' => ['postal_code', 'code_postal', 'npa'],
            'city' => ['city', 'ville', 'localite'],
            'country' => ['country', 'pays'],
            'vat_number' => ['vat_number', 'numero_tva', 'tva'],
            'siret' => ['siret', 'siren'],
            'notes' => ['notes', 'commentaires', 'remarques']
        ];
        
        foreach ($field_mapping as $form_field => $possible_columns) {
            $value = $_POST[$form_field] ?? '';
            foreach ($possible_columns as $column) {
                if ($contact->hasColumn($column)) {
                    $data[$column] = $value;
                    break; // Utiliser la première colonne trouvée
                }
            }
        }
        
        // Mettre à jour le contact
        if($contact->update($data)) {
            $success_message = 'Contact mis à jour avec succès !';
            
            // Recharger les données
            $contactData = $contact->getById($contact_id, $contact->hasCompanyId() ? $company_id : null);
        } else {
            throw new Exception('Erreur lors de la mise à jour du contact');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fonction pour obtenir la valeur d'un champ avec mapping
function getFieldValue($contactData, $field_mapping, $field) {
    if (isset($field_mapping[$field])) {
        foreach ($field_mapping[$field] as $column) {
            if (isset($contactData[$column]) && !empty($contactData[$column])) {
                return $contactData[$column];
            }
        }
    }
    return '';
}

// Mapping pour récupérer les valeurs
$field_mapping = [
    'type' => ['type'],
    'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
    'firstname' => ['firstname', 'prenom'],
    'email' => ['email', 'mail', 'courriel'],
    'phone' => ['phone', 'telephone', 'tel'],
    'mobile' => ['mobile', 'portable'],
    'fax' => ['fax'],
    'website' => ['website', 'site_web'],
    'address' => ['address', 'adresse', 'rue'],
    'postal_code' => ['postal_code', 'code_postal', 'npa'],
    'city' => ['city', 'ville', 'localite'],
    'country' => ['country', 'pays'],
    'vat_number' => ['vat_number', 'numero_tva', 'tva'],
    'siret' => ['siret', 'siren'],
    'notes' => ['notes', 'commentaires', 'remarques']
];
?>

<div class="nouvelle-adresse-content">
    <!-- En-tête avec titre et navigation -->
    <div class="content-header">
        <div class="header-left">
            <h1><i class="fa-solid fa-user-pen"></i> Modifier le Contact</h1>
            <nav class="breadcrumb">
                <a href="index.php?page=adresses">Adresses et Contacts</a>
                <span class="separator">></span>
                <span class="current">Modifier Contact</span>
            </nav>
        </div>
        <div class="header-actions">
            <a href="index.php?page=adresses" class="btn btn-secondary">
                <i class="fa-solid fa-arrow-left"></i> Retour à la liste
            </a>
        </div>
    </div>

    <!-- Messages -->
    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fa-solid fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div class="alert alert-error">
        <i class="fa-solid fa-exclamation-triangle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <!-- Formulaire principal -->
    <div class="form-container">
        <form method="POST" id="contact-form" class="contact-form">
            
            <!-- Section Informations générales -->
            <div class="form-section">
                <h2><i class="fa-solid fa-info-circle"></i> Informations générales</h2>
                
                <div class="form-row">
                    <div class="form-group required">
                        <label for="type">Type de contact</label>
                        <select id="type" name="type" required>
                            <option value="">-- Sélectionnez un type --</option>
                            <option value="client" <?php echo (getFieldValue($contactData, $field_mapping, 'type') == 'client') ? 'selected' : ''; ?>>Client</option>
                            <option value="fournisseur" <?php echo (getFieldValue($contactData, $field_mapping, 'type') == 'fournisseur') ? 'selected' : ''; ?>>Fournisseur</option>
                            <option value="autre" <?php echo (getFieldValue($contactData, $field_mapping, 'type') == 'autre' || empty(getFieldValue($contactData, $field_mapping, 'type'))) ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group required">
                        <label for="name">Nom / Raison sociale</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'name')); ?>" 
                               placeholder="Nom du contact ou raison sociale" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="firstname">Prénom</label>
                        <input type="text" id="firstname" name="firstname" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'firstname')); ?>" 
                               placeholder="Prénom (optionnel)">
                    </div>
                </div>
            </div>

            <!-- Section Contact -->
            <div class="form-section">
                <h2><i class="fa-solid fa-phone"></i> Informations de contact</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'email')); ?>" 
                               placeholder="adresse@exemple.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Téléphone</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'phone')); ?>" 
                               placeholder="+33 1 23 45 67 89">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mobile">Mobile</label>
                        <input type="tel" id="mobile" name="mobile" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'mobile')); ?>" 
                               placeholder="+33 6 12 34 56 78">
                    </div>
                    
                    <div class="form-group">
                        <label for="fax">Fax</label>
                        <input type="tel" id="fax" name="fax" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'fax')); ?>" 
                               placeholder="+33 1 23 45 67 90">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="website">Site web</label>
                        <input type="url" id="website" name="website" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'website')); ?>" 
                               placeholder="https://www.exemple.com">
                    </div>
                </div>
            </div>

            <!-- Section Adresse -->
            <div class="form-section">
                <h2><i class="fa-solid fa-map-marker-alt"></i> Adresse</h2>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="address">Adresse</label>
                        <input type="text" id="address" name="address" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'address')); ?>" 
                               placeholder="Numéro et nom de rue">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code">Code postal</label>
                        <input type="text" id="postal_code" name="postal_code" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'postal_code')); ?>" 
                               placeholder="75001">
                    </div>
                    
                    <div class="form-group">
                        <label for="city">Ville</label>
                        <input type="text" id="city" name="city" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'city')); ?>" 
                               placeholder="Paris">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Pays</label>
                        <input type="text" id="country" name="country" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'country') ?: 'France'); ?>" 
                               placeholder="France">
                    </div>
                </div>
            </div>

            <!-- Section Informations comptables -->
            <div class="form-section">
                <h2><i class="fa-solid fa-calculator"></i> Informations comptables</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="vat_number">Numéro de TVA</label>
                        <input type="text" id="vat_number" name="vat_number" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'vat_number')); ?>" 
                               placeholder="FR12345678901">
                    </div>
                    
                    <div class="form-group">
                        <label for="siret">SIRET / SIREN</label>
                        <input type="text" id="siret" name="siret" 
                               value="<?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'siret')); ?>" 
                               placeholder="12345678901234">
                    </div>
                </div>
            </div>

            <!-- Section Notes -->
            <div class="form-section">
                <h2><i class="fa-solid fa-sticky-note"></i> Notes et remarques</h2>
                
                <div class="form-row">
                    <div class="form-group full-width">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="4" 
                                  placeholder="Notes et remarques sur ce contact..."><?php echo htmlspecialchars(getFieldValue($contactData, $field_mapping, 'notes')); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Mettre à jour le contact
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fa-solid fa-undo"></i> Annuler les modifications
                </button>
                <a href="index.php?page=adresses" class="btn btn-light">
                    <i class="fa-solid fa-times"></i> Retour à la liste
                </a>
            </div>
        </form>
    </div>
</div>

<!-- JavaScript pour rétablir les valeurs après reset -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('contact-form');
    const resetBtn = form.querySelector('button[type="reset"]');
    
    // Stocker les valeurs originales
    const originalValues = {};
    const formFields = form.querySelectorAll('input, select, textarea');
    
    formFields.forEach(field => {
        originalValues[field.name] = field.value;
    });
    
    // Gérer le reset pour rétablir les valeurs d'origine
    resetBtn.addEventListener('click', function(e) {
        e.preventDefault();
        
        if (confirm('Êtes-vous sûr de vouloir annuler toutes les modifications ?')) {
            formFields.forEach(field => {
                if (originalValues[field.name] !== undefined) {
                    field.value = originalValues[field.name];
                }
            });
        }
    });
});
</script>

<?php
// Inclure le pied de page
include_once 'includes/footer.php';
?>