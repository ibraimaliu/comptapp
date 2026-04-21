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

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();
$contact = new Contact($db);

// Variables pour les messages
$success_message = '';
$error_message = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Validation des champs obligatoires
        if (empty($_POST['name']) || empty($_POST['type'])) {
            throw new Exception('Le nom et le type sont obligatoires');
        }

        // Préparer les données en adaptant aux noms de colonnes de la table
        $data = [];
        
        // Ajouter company_id si la table l'a
        if($contact->hasCompanyId()) {
            $data['company_id'] = $company_id;
        }
        
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
        
        // Créer le contact
        if($contact->create($data)) {
            $success_message = 'Contact créé avec succès !';
            
            // Optionnel : rediriger vers la liste des contacts après 2 secondes
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'index.php?page=adresses';
                }, 2000);
            </script>";
        } else {
            throw new Exception('Erreur lors de la création du contact');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<div class="nouvelle-adresse-content">
    <!-- En-tête avec titre et navigation -->
    <div class="content-header">
        <div class="header-left">
            <h1><i class="fa-solid fa-user-plus"></i> Nouveau Contact</h1>
            <nav class="breadcrumb">
                <a href="index.php?page=adresses">Adresses et Contacts</a>
                <span class="separator">></span>
                <span class="current">Nouveau Contact</span>
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
                            <option value="client" <?php echo (isset($_POST['type']) && $_POST['type'] == 'client') ? 'selected' : ''; ?>>Client</option>
                            <option value="fournisseur" <?php echo (isset($_POST['type']) && $_POST['type'] == 'fournisseur') ? 'selected' : ''; ?>>Fournisseur</option>
                            <option value="autre" <?php echo (isset($_POST['type']) && $_POST['type'] == 'autre') ? 'selected' : ''; ?>>Autre</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group required">
                        <label for="name">Nom / Raison sociale</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               placeholder="Nom du contact ou raison sociale" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="firstname">Prénom</label>
                        <input type="text" id="firstname" name="firstname" 
                               value="<?php echo htmlspecialchars($_POST['firstname'] ?? ''); ?>" 
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
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               placeholder="adresse@exemple.com">
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Téléphone</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                               placeholder="+33 1 23 45 67 89">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="mobile">Mobile</label>
                        <input type="tel" id="mobile" name="mobile" 
                               value="<?php echo htmlspecialchars($_POST['mobile'] ?? ''); ?>" 
                               placeholder="+33 6 12 34 56 78">
                    </div>
                    
                    <div class="form-group">
                        <label for="fax">Fax</label>
                        <input type="tel" id="fax" name="fax" 
                               value="<?php echo htmlspecialchars($_POST['fax'] ?? ''); ?>" 
                               placeholder="+33 1 23 45 67 90">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="website">Site web</label>
                        <input type="url" id="website" name="website" 
                               value="<?php echo htmlspecialchars($_POST['website'] ?? ''); ?>" 
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
                               value="<?php echo htmlspecialchars($_POST['address'] ?? ''); ?>" 
                               placeholder="Numéro et nom de rue">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="postal_code">Code postal</label>
                        <input type="text" id="postal_code" name="postal_code" 
                               value="<?php echo htmlspecialchars($_POST['postal_code'] ?? ''); ?>" 
                               placeholder="75001">
                    </div>
                    
                    <div class="form-group">
                        <label for="city">Ville</label>
                        <input type="text" id="city" name="city" 
                               value="<?php echo htmlspecialchars($_POST['city'] ?? ''); ?>" 
                               placeholder="Paris">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="country">Pays</label>
                        <input type="text" id="country" name="country" 
                               value="<?php echo htmlspecialchars($_POST['country'] ?? 'France'); ?>" 
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
                               value="<?php echo htmlspecialchars($_POST['vat_number'] ?? ''); ?>" 
                               placeholder="FR12345678901">
                    </div>
                    
                    <div class="form-group">
                        <label for="siret">SIRET / SIREN</label>
                        <input type="text" id="siret" name="siret" 
                               value="<?php echo htmlspecialchars($_POST['siret'] ?? ''); ?>" 
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
                                  placeholder="Notes et remarques sur ce contact..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Boutons d'action -->
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-save"></i> Enregistrer le contact
                </button>
                <button type="reset" class="btn btn-secondary">
                    <i class="fa-solid fa-undo"></i> Réinitialiser
                </button>
                <a href="index.php?page=adresses" class="btn btn-light">
                    <i class="fa-solid fa-times"></i> Annuler
                </a>
            </div>
        </form>
    </div>
</div>

<?php
// Inclure le pied de page
include_once 'includes/footer.php';
?>