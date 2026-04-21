<?php
// ajax/contacts.php
session_name('COMPTAPP_SESSION');
session_start();

// Inclure les modèles nécessaires
include_once dirname(dirname(__DIR__)) . '/config/database.php';
include_once dirname(dirname(__DIR__)) . '/models/Contact.php';

// Headers pour JSON
header('Content-Type: application/json');

// Vérifier que l'utilisateur est connecté
if(!isset($_SESSION['company_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$company_id = $_SESSION['company_id'];

// Initialiser la base de données
try {
    $database = new Database();
    $db = $database->getConnection();

    if (!$db) {
        echo json_encode(['success' => false, 'message' => 'Impossible de se connecter à la base de données']);
        exit;
    }

    $contact = new Contact($db);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur de connexion: ' . $e->getMessage()]);
    exit;
}

// Déterminer l'action à effectuer
$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

switch($action) {
    case 'create':
        createContact($contact, $company_id);
        break;
    
    case 'update':
        updateContact($contact, $company_id);
        break;
    
    case 'delete':
        deleteContact($contact, $company_id);
        break;
    
    case 'get':
        getContact($contact, $company_id);
        break;
    
    case 'export':
        exportContacts($contact, $company_id);
        break;
    
    case 'import':
        importContacts($contact, $company_id);
        break;
    
    default:
        // Par défaut, lister tous les contacts
        listContacts($contact, $company_id);
        break;
}

// Fonction pour lister tous les contacts
function listContacts($contact, $company_id) {
    try {
        // Récupérer tous les contacts de la société
        $stmt = $contact->readByCompany($company_id);
        $contacts = [];

        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Mapper les colonnes vers les champs standards
            $standardizedData = [
                'id' => $row['id']
            ];

            // Mapping des colonnes
            $field_mapping = [
                'type' => ['type'],
                'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
                'email' => ['email', 'mail', 'courriel'],
                'phone' => ['phone', 'telephone', 'tel'],
                'address' => ['address', 'adresse', 'rue'],
                'postal_code' => ['postal_code', 'code_postal', 'npa'],
                'city' => ['city', 'ville', 'localite'],
                'country' => ['country', 'pays']
            ];

            foreach ($field_mapping as $standard_field => $possible_columns) {
                $standardizedData[$standard_field] = '';
                foreach ($possible_columns as $column) {
                    if (isset($row[$column]) && !empty($row[$column])) {
                        $standardizedData[$standard_field] = $row[$column];
                        break;
                    }
                }
            }

            $contacts[] = $standardizedData;
        }

        echo json_encode([
            'success' => true,
            'contacts' => $contacts,
            'total' => count($contacts)
        ]);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

function createContact($contact, $company_id) {
    try {
        // Valider les données requises
        if(empty($_POST['name'])) {
            echo json_encode(['success' => false, 'message' => 'Le nom est requis']);
            return;
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
            'email' => ['email', 'mail', 'courriel'],
            'phone' => ['phone', 'telephone', 'tel'],
            'address' => ['address', 'adresse', 'rue'],
            'postal_code' => ['postal_code', 'code_postal', 'npa'],
            'city' => ['city', 'ville', 'localite'],
            'country' => ['country', 'pays']
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
            echo json_encode(['success' => true, 'message' => 'Contact créé avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la création du contact']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

function updateContact($contact, $company_id) {
    try {
        $contact_id = $_POST['id'] ?? '';
        
        if(empty($contact_id)) {
            echo json_encode(['success' => false, 'message' => 'ID du contact requis']);
            return;
        }
        
        if(empty($_POST['name'])) {
            echo json_encode(['success' => false, 'message' => 'Le nom est requis']);
            return;
        }
        
        // Vérifier que le contact appartient à la bonne société
        if($contact->hasCompanyId() && !$contact->belongsToCompany($contact_id, $company_id)) {
            echo json_encode(['success' => false, 'message' => 'Contact non trouvé']);
            return;
        }
        
        // Préparer les données en adaptant aux noms de colonnes de la table
        $data = ['id' => $contact_id];
        
        // Mapper les champs du formulaire aux colonnes de la table
        $field_mapping = [
            'type' => ['type'],
            'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
            'email' => ['email', 'mail', 'courriel'],
            'phone' => ['phone', 'telephone', 'tel'],
            'address' => ['address', 'adresse', 'rue'],
            'postal_code' => ['postal_code', 'code_postal', 'npa'],
            'city' => ['city', 'ville', 'localite'],
            'country' => ['country', 'pays']
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
            echo json_encode(['success' => true, 'message' => 'Contact mis à jour avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la mise à jour du contact']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

function deleteContact($contact, $company_id) {
    try {
        $contact_id = $_POST['id'] ?? '';
        
        if(empty($contact_id)) {
            echo json_encode(['success' => false, 'message' => 'ID du contact requis']);
            return;
        }
        
        // Vérifier que le contact appartient à la bonne société (seulement si company_id existe)
        if($contact->hasCompanyId() && !$contact->belongsToCompany($contact_id, $company_id)) {
            echo json_encode(['success' => false, 'message' => 'Contact non trouvé']);
            return;
        }
        
        // Supprimer le contact
        if($contact->delete($contact_id)) {
            echo json_encode(['success' => true, 'message' => 'Contact supprimé avec succès']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la suppression du contact']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

function getContact($contact, $company_id) {
    try {
        $contact_id = $_GET['id'] ?? '';
        
        if(empty($contact_id)) {
            echo json_encode(['success' => false, 'message' => 'ID du contact requis']);
            return;
        }
        
        // Récupérer le contact
        $contactData = $contact->getById($contact_id, $contact->hasCompanyId() ? $company_id : null);
        
        if($contactData) {
            // Mapper les colonnes de la base vers les champs attendus par le formulaire
            $standardizedData = [
                'id' => $contactData['id']
            ];
            
            // Mapping des colonnes vers les champs standards
            $field_mapping = [
                'type' => ['type'],
                'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
                'email' => ['email', 'mail', 'courriel'],
                'phone' => ['phone', 'telephone', 'tel'],
                'address' => ['address', 'adresse', 'rue'],
                'postal_code' => ['postal_code', 'code_postal', 'npa'],
                'city' => ['city', 'ville', 'localite'],
                'country' => ['country', 'pays']
            ];
            
            foreach ($field_mapping as $standard_field => $possible_columns) {
                $standardizedData[$standard_field] = '';
                foreach ($possible_columns as $column) {
                    if (isset($contactData[$column]) && !empty($contactData[$column])) {
                        $standardizedData[$standard_field] = $contactData[$column];
                        break; // Utiliser la première valeur non vide trouvée
                    }
                }
            }
            
            echo json_encode(['success' => true, 'contact' => $standardizedData]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Contact non trouvé']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur: ' . $e->getMessage()]);
    }
}

function exportContacts($contact, $company_id) {
    try {
        // Récupérer tous les contacts de la société
        $stmt = $contact->hasCompanyId() ? $contact->getByCompany($company_id) : $contact->readByCompany($company_id);
        $contacts = [];
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contacts[] = $row;
        }
        
        // Créer le fichier CSV
        $filename = 'contacts_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Content-Description: File Transfer');
        
        $output = fopen('php://output', 'w');
        
        // En-têtes CSV
        fputcsv($output, [
            'Nom',
            'Type',
            'Email',
            'Téléphone',
            'Adresse',
            'Code postal',
            'Ville',
            'Pays'
        ]);
        
        // Mapping des colonnes
        $field_mapping = [
            'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
            'type' => ['type'],
            'email' => ['email', 'mail', 'courriel'],
            'phone' => ['phone', 'telephone', 'tel'],
            'address' => ['address', 'adresse', 'rue'],
            'postal_code' => ['postal_code', 'code_postal', 'npa'],
            'city' => ['city', 'ville', 'localite'],
            'country' => ['country', 'pays']
        ];
        
        // Données
        foreach($contacts as $contactRow) {
            $csvRow = [];
            
            foreach (['name', 'type', 'email', 'phone', 'address', 'postal_code', 'city', 'country'] as $field) {
                $value = '';
                if (isset($field_mapping[$field])) {
                    foreach ($field_mapping[$field] as $column) {
                        if (isset($contactRow[$column]) && !empty($contactRow[$column])) {
                            $value = $contactRow[$column];
                            break;
                        }
                    }
                }
                $csvRow[] = $value;
            }
            
            fputcsv($output, $csvRow);
        }
        
        fclose($output);
        exit;
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'export: ' . $e->getMessage()]);
    }
}

function importContacts($contact, $company_id) {
    try {
        if(!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Aucun fichier sélectionné ou erreur d\'upload']);
            return;
        }
        
        $file = $_FILES['import_file']['tmp_name'];
        $skipHeader = isset($_POST['skip_header']);
        $updateExisting = isset($_POST['update_existing']);
        
        if(!is_readable($file)) {
            echo json_encode(['success' => false, 'message' => 'Impossible de lire le fichier']);
            return;
        }
        
        $handle = fopen($file, 'r');
        if(!$handle) {
            echo json_encode(['success' => false, 'message' => 'Impossible d\'ouvrir le fichier']);
            return;
        }
        
        $imported = 0;
        $updated = 0;
        $errors = 0;
        $lineNumber = 0;
        
        // Mapping des champs CSV vers les colonnes de la table
        $field_mapping = [
            'name' => ['name', 'nom', 'title', 'titre', 'raison_sociale'],
            'type' => ['type'],
            'email' => ['email', 'mail', 'courriel'],
            'phone' => ['phone', 'telephone', 'tel'],
            'address' => ['address', 'adresse', 'rue'],
            'postal_code' => ['postal_code', 'code_postal', 'npa'],
            'city' => ['city', 'ville', 'localite'],
            'country' => ['country', 'pays']
        ];
        
        while(($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $lineNumber++;
            
            // Ignorer l'en-tête si demandé
            if($lineNumber === 1 && $skipHeader) {
                continue;
            }
            
            // Vérifier qu'il y a assez de colonnes
            if(count($data) < 8) {
                $errors++;
                continue;
            }
            
            // Préparer les données en adaptant aux colonnes de la table
            $contactData = [];
            
            // Ajouter company_id si la table l'a
            if($contact->hasCompanyId()) {
                $contactData['company_id'] = $company_id;
            }
            
            // Mapper les données CSV
            $csvFields = ['name', 'type', 'email', 'phone', 'address', 'postal_code', 'city', 'country'];
            for($i = 0; $i < 8; $i++) {
                $csvField = $csvFields[$i];
                $value = trim($data[$i]);
                
                if($csvField === 'type' && empty($value)) {
                    $value = 'autre';
                }
                
                // Trouver la colonne correspondante dans la table
                if(isset($field_mapping[$csvField])) {
                    foreach($field_mapping[$csvField] as $column) {
                        if($contact->hasColumn($column)) {
                            $contactData[$column] = $value;
                            break;
                        }
                    }
                }
            }
            
            // Vérifier que le nom n'est pas vide
            $hasName = false;
            foreach($field_mapping['name'] as $nameColumn) {
                if(isset($contactData[$nameColumn]) && !empty($contactData[$nameColumn])) {
                    $hasName = true;
                    break;
                }
            }
            
            if(!$hasName) {
                $errors++;
                continue;
            }
            
            try {
                // Vérifier si le contact existe déjà (par nom et email)
                $existingContact = null;
                if($updateExisting) {
                    $nameValue = '';
                    $emailValue = '';
                    
                    // Récupérer le nom
                    foreach($field_mapping['name'] as $nameColumn) {
                        if(isset($contactData[$nameColumn]) && !empty($contactData[$nameColumn])) {
                            $nameValue = $contactData[$nameColumn];
                            break;
                        }
                    }
                    
                    // Récupérer l'email
                    foreach($field_mapping['email'] as $emailColumn) {
                        if(isset($contactData[$emailColumn]) && !empty($contactData[$emailColumn])) {
                            $emailValue = $contactData[$emailColumn];
                            break;
                        }
                    }
                    
                    if(!empty($nameValue) && !empty($emailValue)) {
                        $existingContact = $contact->findByNameAndEmail($nameValue, $emailValue, $company_id);
                    }
                }
                
                if($existingContact && $updateExisting) {
                    // Mettre à jour le contact existant
                    $contactData['id'] = $existingContact['id'];
                    if($contact->update($contactData)) {
                        $updated++;
                    } else {
                        $errors++;
                    }
                } else {
                    // Créer un nouveau contact
                    if($contact->create($contactData)) {
                        $imported++;
                    } else {
                        $errors++;
                    }
                }
                
            } catch (Exception $e) {
                $errors++;
            }
        }
        
        fclose($handle);
        
        $message = "Import terminé. ";
        if($imported > 0) $message .= "$imported contact(s) importé(s). ";
        if($updated > 0) $message .= "$updated contact(s) mis à jour. ";
        if($errors > 0) $message .= "$errors erreur(s).";
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'stats' => [
                'imported' => $imported,
                'updated' => $updated,
                'errors' => $errors
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'import: ' . $e->getMessage()]);
    }
}
?>