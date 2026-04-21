<?php
/**
 * Modèle Quote - Gestion des devis/offres
 *
 * Ce modèle gère les devis avec:
 * - Création, lecture, mise à jour, suppression (CRUD)
 * - Gestion des lignes de devis (items)
 * - Changements de statut avec historique
 * - Conversion devis → facture
 * - Calculs automatiques (totaux, taxes, remises)
 * - Statistiques
 *
 * @author Gestion Comptable
 * @version 2.0
 */

class Quote {
    private $conn;
    private $table_name = "quotes";
    private $items_table = "quote_items";
    private $history_table = "quote_status_history";

    // Propriétés du devis
    public $id;
    public $company_id;
    public $contact_id;
    public $number;
    public $title;
    public $date;
    public $valid_until;
    public $status;
    public $subtotal;
    public $tax_amount;
    public $discount_percent;
    public $discount_amount;
    public $total;
    public $currency;
    public $notes;
    public $terms;
    public $footer;
    public $converted_to_invoice_id;
    public $sent_at;
    public $accepted_at;
    public $rejected_at;
    public $created_at;
    public $updated_at;
    public $created_by;

    // Items du devis
    public $items = [];

    /**
     * Constructeur
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un nouveau devis avec ses lignes
     */
    public function create() {
        $this->conn->beginTransaction();

        try {
            // Générer le numéro de devis si non fourni
            if (empty($this->number)) {
                $this->number = $this->generateQuoteNumber();
            }

            // Calculer les totaux
            $this->calculateTotals();

            // Insérer le devis
            $query = "INSERT INTO " . $this->table_name . "
                    SET company_id = :company_id,
                        contact_id = :contact_id,
                        number = :number,
                        title = :title,
                        date = :date,
                        valid_until = :valid_until,
                        status = :status,
                        subtotal = :subtotal,
                        tax_amount = :tax_amount,
                        discount_percent = :discount_percent,
                        discount_amount = :discount_amount,
                        total = :total,
                        currency = :currency,
                        notes = :notes,
                        terms = :terms,
                        footer = :footer,
                        created_by = :created_by";

            $stmt = $this->conn->prepare($query);

            // Bind des valeurs
            $stmt->bindParam(":company_id", $this->company_id);
            $stmt->bindParam(":contact_id", $this->contact_id);
            $stmt->bindParam(":number", $this->number);
            $stmt->bindParam(":title", $this->title);
            $stmt->bindParam(":date", $this->date);
            $stmt->bindParam(":valid_until", $this->valid_until);
            $stmt->bindParam(":status", $this->status);
            $stmt->bindParam(":subtotal", $this->subtotal);
            $stmt->bindParam(":tax_amount", $this->tax_amount);
            $stmt->bindParam(":discount_percent", $this->discount_percent);
            $stmt->bindParam(":discount_amount", $this->discount_amount);
            $stmt->bindParam(":total", $this->total);
            $stmt->bindParam(":currency", $this->currency);
            $stmt->bindParam(":notes", $this->notes);
            $stmt->bindParam(":terms", $this->terms);
            $stmt->bindParam(":footer", $this->footer);
            $stmt->bindParam(":created_by", $this->created_by);

            $stmt->execute();

            // Récupérer l'ID du devis créé
            $this->id = $this->conn->lastInsertId();

            // Insérer les lignes du devis
            if (!empty($this->items)) {
                $this->createItems($this->id, $this->items);
            }

            // Logger le changement de statut initial
            $this->logStatusChange(null, $this->status, 'Devis créé');

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Erreur création devis: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lire un devis par son ID
     */
    public function read() {
        $query = "SELECT
                    q.*,
                    c.name as client_name,
                    c.email as client_email,
                    c.phone as client_phone,
                    c.address as client_address
                FROM " . $this->table_name . " q
                LEFT JOIN contacts c ON q.contact_id = c.id
                WHERE q.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            // Assigner les propriétés
            $this->company_id = $row['company_id'];
            $this->contact_id = $row['contact_id'];
            $this->number = $row['number'];
            $this->title = $row['title'];
            $this->date = $row['date'];
            $this->valid_until = $row['valid_until'];
            $this->status = $row['status'];
            $this->subtotal = $row['subtotal'];
            $this->tax_amount = $row['tax_amount'];
            $this->discount_percent = $row['discount_percent'];
            $this->discount_amount = $row['discount_amount'];
            $this->total = $row['total'];
            $this->currency = $row['currency'];
            $this->notes = $row['notes'];
            $this->terms = $row['terms'];
            $this->footer = $row['footer'];
            $this->converted_to_invoice_id = $row['converted_to_invoice_id'];
            $this->sent_at = $row['sent_at'];
            $this->accepted_at = $row['accepted_at'];
            $this->rejected_at = $row['rejected_at'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];
            $this->created_by = $row['created_by'];

            // Charger les lignes du devis
            $this->items = $this->readItems($this->id);

            return $row;
        }

        return false;
    }

    /**
     * Lire tous les devis d'une société
     */
    public function readByCompany($company_id, $filters = []) {
        $query = "SELECT
                    q.*,
                    c.name as client_name,
                    COUNT(qi.id) as items_count
                FROM " . $this->table_name . " q
                LEFT JOIN contacts c ON q.contact_id = c.id
                LEFT JOIN " . $this->items_table . " qi ON q.id = qi.quote_id
                WHERE q.company_id = :company_id";

        // Filtres optionnels
        if (!empty($filters['status'])) {
            $query .= " AND q.status = :status";
        }
        if (!empty($filters['contact_id'])) {
            $query .= " AND q.contact_id = :contact_id";
        }
        if (!empty($filters['date_from'])) {
            $query .= " AND q.date >= :date_from";
        }
        if (!empty($filters['date_to'])) {
            $query .= " AND q.date <= :date_to";
        }
        if (!empty($filters['search'])) {
            $query .= " AND (q.number LIKE :search OR q.title LIKE :search OR c.name LIKE :search)";
        }

        $query .= " GROUP BY q.id ORDER BY q.date DESC, q.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        // Bind des filtres
        if (!empty($filters['status'])) {
            $stmt->bindParam(":status", $filters['status']);
        }
        if (!empty($filters['contact_id'])) {
            $stmt->bindParam(":contact_id", $filters['contact_id']);
        }
        if (!empty($filters['date_from'])) {
            $stmt->bindParam(":date_from", $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $stmt->bindParam(":date_to", $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $search_term = "%" . $filters['search'] . "%";
            $stmt->bindParam(":search", $search_term);
        }

        $stmt->execute();
        return $stmt;
    }

    /**
     * Mettre à jour un devis
     */
    public function update() {
        $this->conn->beginTransaction();

        try {
            // Recalculer les totaux
            $this->calculateTotals();

            $query = "UPDATE " . $this->table_name . "
                    SET contact_id = :contact_id,
                        title = :title,
                        date = :date,
                        valid_until = :valid_until,
                        status = :status,
                        subtotal = :subtotal,
                        tax_amount = :tax_amount,
                        discount_percent = :discount_percent,
                        discount_amount = :discount_amount,
                        total = :total,
                        currency = :currency,
                        notes = :notes,
                        terms = :terms,
                        footer = :footer
                    WHERE id = :id AND company_id = :company_id";

            $stmt = $this->conn->prepare($query);

            $stmt->bindParam(":contact_id", $this->contact_id);
            $stmt->bindParam(":title", $this->title);
            $stmt->bindParam(":date", $this->date);
            $stmt->bindParam(":valid_until", $this->valid_until);
            $stmt->bindParam(":status", $this->status);
            $stmt->bindParam(":subtotal", $this->subtotal);
            $stmt->bindParam(":tax_amount", $this->tax_amount);
            $stmt->bindParam(":discount_percent", $this->discount_percent);
            $stmt->bindParam(":discount_amount", $this->discount_amount);
            $stmt->bindParam(":total", $this->total);
            $stmt->bindParam(":currency", $this->currency);
            $stmt->bindParam(":notes", $this->notes);
            $stmt->bindParam(":terms", $this->terms);
            $stmt->bindParam(":footer", $this->footer);
            $stmt->bindParam(":id", $this->id);
            $stmt->bindParam(":company_id", $this->company_id);

            $stmt->execute();

            // Supprimer les anciennes lignes et insérer les nouvelles
            if (!empty($this->items)) {
                $this->deleteItems($this->id);
                $this->createItems($this->id, $this->items);
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Erreur mise à jour devis: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Supprimer un devis
     */
    public function delete() {
        // Les lignes et l'historique seront supprimés automatiquement (CASCADE)
        $query = "DELETE FROM " . $this->table_name . "
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Changer le statut d'un devis
     */
    public function changeStatus($new_status, $notes = null) {
        $old_status = $this->status;

        $query = "UPDATE " . $this->table_name . "
                SET status = :status";

        // Mettre à jour les timestamps selon le statut
        if ($new_status == 'sent') {
            $query .= ", sent_at = NOW()";
        } elseif ($new_status == 'accepted') {
            $query .= ", accepted_at = NOW()";
        } elseif ($new_status == 'rejected') {
            $query .= ", rejected_at = NOW()";
        }

        $query .= " WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $new_status);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        if ($stmt->execute()) {
            $this->status = $new_status;
            $this->logStatusChange($old_status, $new_status, $notes);
            return true;
        }

        return false;
    }

    /**
     * Convertir un devis en facture
     */
    public function convertToInvoice() {
        // Charger les données du devis
        if (!$this->read()) {
            return false;
        }

        // Vérifier que le devis peut être converti
        if ($this->status != 'accepted' && $this->status != 'sent') {
            return false;
        }

        // Créer la facture
        require_once 'Invoice.php';
        $invoice = new Invoice($this->conn);

        $invoice->company_id = $this->company_id;
        $invoice->contact_id = $this->contact_id;
        $invoice->date = date('Y-m-d');
        $invoice->due_date = date('Y-m-d', strtotime('+30 days'));
        $invoice->status = 'pending';
        $invoice->subtotal = $this->subtotal;
        $invoice->tax_amount = $this->tax_amount;
        $invoice->discount_percent = $this->discount_percent;
        $invoice->discount_amount = $this->discount_amount;
        $invoice->total = $this->total;
        $invoice->currency = $this->currency;
        $invoice->notes = "Devis #" . $this->number . " - " . $this->title;

        // Convertir les items
        $invoice_items = [];
        foreach ($this->items as $item) {
            $invoice_items[] = [
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'],
                'discount_percent' => $item['discount_percent'],
                'line_total' => $item['line_total']
            ];
        }
        $invoice->items = $invoice_items;

        if ($invoice->create()) {
            // Mettre à jour le devis
            $query = "UPDATE " . $this->table_name . "
                    SET status = 'converted',
                        converted_to_invoice_id = :invoice_id
                    WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":invoice_id", $invoice->id);
            $stmt->bindParam(":id", $this->id);
            $stmt->execute();

            // Logger le changement
            $this->logStatusChange($this->status, 'converted', 'Converti en facture #' . $invoice->number);

            return $invoice->id;
        }

        return false;
    }

    /**
     * Générer le numéro de devis
     */
    private function generateQuoteNumber() {
        $query = "SELECT MAX(CAST(SUBSTRING(number, 5) AS UNSIGNED)) as max_num
                FROM " . $this->table_name . "
                WHERE company_id = :company_id
                AND number LIKE :pattern";

        $pattern = date('Y') . '%';

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":pattern", $pattern);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_num = $row['max_num'] ? $row['max_num'] + 1 : 1;

        return 'DEV-' . date('Y') . '-' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calculer les totaux du devis
     */
    private function calculateTotals() {
        $subtotal = 0;
        $tax_amount = 0;

        foreach ($this->items as &$item) {
            $line_subtotal = $item['quantity'] * $item['unit_price'];

            // Appliquer la remise sur la ligne
            if (!empty($item['discount_percent'])) {
                $line_subtotal -= ($line_subtotal * $item['discount_percent'] / 100);
            }

            // Calculer la TVA sur la ligne
            if (!empty($item['tax_rate'])) {
                $line_tax = $line_subtotal * $item['tax_rate'] / 100;
                $tax_amount += $line_tax;
            }

            $item['line_total'] = $line_subtotal;
            $subtotal += $line_subtotal;
        }

        // Appliquer la remise globale
        $this->subtotal = $subtotal;
        if (!empty($this->discount_percent)) {
            $this->discount_amount = $subtotal * $this->discount_percent / 100;
            $subtotal -= $this->discount_amount;
        }

        $this->tax_amount = $tax_amount;
        $this->total = $subtotal + $tax_amount;
    }

    /**
     * Créer les lignes du devis
     */
    private function createItems($quote_id, $items) {
        $query = "INSERT INTO " . $this->items_table . "
                (quote_id, description, quantity, unit_price, tax_rate, discount_percent, line_total, sort_order)
                VALUES (:quote_id, :description, :quantity, :unit_price, :tax_rate, :discount_percent, :line_total, :sort_order)";

        $stmt = $this->conn->prepare($query);

        $order = 0;
        foreach ($items as $item) {
            $stmt->bindParam(":quote_id", $quote_id);
            $stmt->bindParam(":description", $item['description']);
            $stmt->bindParam(":quantity", $item['quantity']);
            $stmt->bindParam(":unit_price", $item['unit_price']);
            $stmt->bindParam(":tax_rate", $item['tax_rate']);
            $stmt->bindParam(":discount_percent", $item['discount_percent']);
            $stmt->bindParam(":line_total", $item['line_total']);
            $stmt->bindParam(":sort_order", $order);

            $stmt->execute();
            $order++;
        }
    }

    /**
     * Lire les lignes d'un devis
     */
    private function readItems($quote_id) {
        $query = "SELECT * FROM " . $this->items_table . "
                WHERE quote_id = :quote_id
                ORDER BY sort_order ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quote_id", $quote_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprimer les lignes d'un devis
     */
    private function deleteItems($quote_id) {
        $query = "DELETE FROM " . $this->items_table . "
                WHERE quote_id = :quote_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quote_id", $quote_id);
        $stmt->execute();
    }

    /**
     * Logger un changement de statut
     */
    private function logStatusChange($old_status, $new_status, $notes = null) {
        $query = "INSERT INTO " . $this->history_table . "
                (quote_id, old_status, new_status, notes, changed_by)
                VALUES (:quote_id, :old_status, :new_status, :notes, :changed_by)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":quote_id", $this->id);
        $stmt->bindParam(":old_status", $old_status);
        $stmt->bindParam(":new_status", $new_status);
        $stmt->bindParam(":notes", $notes);
        $stmt->bindParam(":changed_by", $this->created_by);

        $stmt->execute();
    }

    /**
     * Obtenir les statistiques des devis
     */
    public function getStatistics($company_id) {
        $query = "SELECT
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN converted_to_invoice_id IS NOT NULL THEN 1 ELSE 0 END) as converted
                  FROM " . $this->table_name . "
                  WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // S'assurer que les valeurs sont des entiers
        return [
            'total' => (int)($stats['total'] ?? 0),
            'draft' => (int)($stats['draft'] ?? 0),
            'sent' => (int)($stats['sent'] ?? 0),
            'accepted' => (int)($stats['accepted'] ?? 0),
            'rejected' => (int)($stats['rejected'] ?? 0),
            'converted' => (int)($stats['converted'] ?? 0)
        ];
    }

    /**
     * Marquer les devis expirés
     */
    public function markExpiredQuotes($company_id) {
        $query = "UPDATE " . $this->table_name . "
                SET status = 'expired'
                WHERE company_id = :company_id
                AND status IN ('draft', 'sent')
                AND valid_until < CURDATE()";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        return $stmt->execute();
    }
}
