<?php
// Inclure les modèles nécessaires
include_once 'config/database.php';
include_once 'models/Transaction.php';
include_once 'models/Invoice.php';
include_once 'models/Contact.php';

// Initialiser la base de données
$database = new Database();
$db = $database->getConnection();

// Vérifier si une société est sélectionnée
$company_id = isset($_SESSION['company_id']) ? $_SESSION['company_id'] : null;

// Rediriger vers l'accueil si aucune société n'est sélectionnée
if(!$company_id) {
    redirect('index.php?page=home');
}

// Variables pour la recherche
$query = isset($_GET['query']) ? $_GET['query'] : '';
$filter_transactions = isset($_GET['filter_transactions']) ? true : false;
$filter_invoices = isset($_GET['filter_invoices']) ? true : false;
$filter_contacts = isset($_GET['filter_contacts']) ? true : false;

// Si aucun filtre n'est sélectionné, sélectionner tous les filtres
if (!$filter_transactions && !$filter_invoices && !$filter_contacts) {
    $filter_transactions = true;
    $filter_invoices = true;
    $filter_contacts = true;
}

// Résultats de recherche
$transactions = [];
$invoices = [];
$contacts = [];

// Effectuer la recherche si une requête est fournie
if (!empty($query)) {
    // Recherche dans les transactions
    if ($filter_transactions) {
        $transaction = new Transaction($db);
        $stmt = $transaction->search($company_id, $query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $transactions[] = $row;
        }
    }
    
    // Recherche dans les factures
    if ($filter_invoices) {
        $invoice = new Invoice($db);
        $stmt = $invoice->search($company_id, $query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $invoices[] = $row;
        }
    }
    
    // Recherche dans les contacts
    if ($filter_contacts) {
        $contact = new Contact($db);
        $stmt = $contact->search($company_id, $query);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $contacts[] = $row;
        }
    }
}
?>

<div class="recherche-content">
    <h1>Recherche</h1>
    
    <div class="search-bar" style="margin: 30px 0;">
        <form id="search-form" method="get" action="index.php">
            <input type="hidden" name="page" value="recherche">
            <input type="text" id="global-search" name="query" placeholder="Rechercher dans toute l'application..." value="<?php echo htmlspecialchars($query); ?>">
            <button type="submit" id="globalSearchBtn"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
    </div>
    
    <div>
        <h3>Filtrer par:</h3>
        <form id="filter-form" method="get" action="index.php">
            <input type="hidden" name="page" value="recherche">
            <input type="hidden" name="query" value="<?php echo htmlspecialchars($query); ?>">
            <div style="margin: 15px 0; display: flex; gap: 10px; flex-wrap: wrap;">
                <label><input type="checkbox" name="filter_transactions" <?php echo $filter_transactions ? 'checked' : ''; ?>> Transactions</label>
                <label><input type="checkbox" name="filter_invoices" <?php echo $filter_invoices ? 'checked' : ''; ?>> Factures</label>
                <label><input type="checkbox" name="filter_contacts" <?php echo $filter_contacts ? 'checked' : ''; ?>> Contacts</label>
                <button type="submit" class="btn-small">Appliquer les filtres</button>
            </div>
        </form>
    </div>
    
    <div id="search-results" style="margin-top: 30px;">
        <?php if (empty($query)): ?>
            <p style="text-align: center;">Entrez un terme de recherche et lancez la recherche</p>
        <?php else: ?>
            <?php if (count($transactions) == 0 && count($invoices) == 0 && count($contacts) == 0): ?>
                <p style="text-align: center;">Aucun résultat trouvé pour "<strong><?php echo htmlspecialchars($query); ?></strong>"</p>
            <?php else: ?>
                <h2>Résultats de recherche pour "<strong><?php echo htmlspecialchars($query); ?></strong>"</h2>
                
                <?php if (count($transactions) > 0): ?>
                    <h3>Transactions (<?php echo count($transactions); ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Montant</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($transaction['date'])); ?></td>
                                    <td><?php echo $transaction['description']; ?></td>
                                    <td><?php echo number_format($transaction['amount'], 2); ?> CHF</td>
                                    <td><?php echo ($transaction['type'] == 'income') ? 'Revenu' : 'Dépense'; ?></td>
                                    <td>
                                        <a href="index.php?page=comptabilite&view=transaction&id=<?php echo $transaction['id']; ?>" class="btn-small">Voir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if (count($invoices) > 0): ?>
                    <h3>Factures (<?php echo count($invoices); ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>N° Facture</th>
                                <th>Date</th>
                                <th>Client</th>
                                <th>Montant</th>
                                <th>Statut</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($invoices as $invoice): ?>
                                <tr>
                                    <td><?php echo $invoice['number']; ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($invoice['date'])); ?></td>
                                    <td><?php echo $invoice['client_name']; ?></td>
                                    <td><?php echo number_format($invoice['total'], 2); ?> CHF</td>
                                    <td><?php echo $invoice['status']; ?></td>
                                    <td>
                                        <a href="index.php?page=comptabilite&view=invoice&id=<?php echo $invoice['id']; ?>" class="btn-small">Voir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
                
                <?php if (count($contacts) > 0): ?>
                    <h3>Contacts (<?php echo count($contacts); ?>)</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Type</th>
                                <th>Email</th>
                                <th>Téléphone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($contacts as $contact): ?>
                                <tr>
                                    <td><?php echo $contact['name']; ?></td>
                                    <td>
                                        <?php 
                                        $type = $contact['type'];
                                        if($type == 'client') echo 'Client';
                                        else if($type == 'fournisseur') echo 'Fournisseur';
                                        else echo 'Autre';
                                        ?>
                                    </td>
                                    <td><?php echo $contact['email'] ?: '-'; ?></td>
                                    <td><?php echo $contact['phone'] ?: '-'; ?></td>
                                    <td>
                                        <a href="index.php?page=adresses&view=contact&id=<?php echo $contact['id']; ?>" class="btn-small">Voir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Soumission automatique du formulaire de filtres quand les cases à cocher changent
    const filterCheckboxes = document.querySelectorAll('#filter-form input[type="checkbox"]');
    
    filterCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            document.getElementById('filter-form').submit();
        });
    });
});
</script>