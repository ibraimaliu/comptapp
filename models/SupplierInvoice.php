<?php
/**
 * Modèle: SupplierInvoice
 * Description: Gestion des factures fournisseurs
 * Version: 1.0
 */

class SupplierInvoice {
    private $conn;
    private $table_name = "supplier_invoices";
    private $items_table = "supplier_invoice_items";

    // Propriétés de l'objet
    public $id;
    public $company_id;
    public $supplier_id;
    public $invoice_number;
    public $invoice_date;
    public $due_date;
    public $reception_date;
    public $subtotal;
    public $tva_amount;
    public $total;
    public $status;
    public $payment_date;
    public $payment_method;
    public $qr_reference;
    public $iban;
    public $scanned_pdf_path;
    public $notes;
    public $approved_by;
    public $approved_at;
    public $created_by;
    public $created_at;
    public $updated_at;

    // Lignes de facture
    public $items = [];

    /**
     * Constructeur
     */
    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une facture fournisseur
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET company_id = :company_id,
                    supplier_id = :supplier_id,
                    invoice_number = :invoice_number,
                    invoice_date = :invoice_date,
                    due_date = :due_date,
                    reception_date = :reception_date,
                    subtotal = :subtotal,
                    tva_amount = :tva_amount,
                    total = :total,
                    status = :status,
                    qr_reference = :qr_reference,
                    iban = :iban,
                    notes = :notes,
                    created_by = :created_by";

        $stmt = $this->conn->prepare($query);

        // Nettoyage
        $this->company_id = htmlspecialchars(strip_tags($this->company_id));
        $this->supplier_id = htmlspecialchars(strip_tags($this->supplier_id));
        $this->invoice_number = htmlspecialchars(strip_tags($this->invoice_number));
        $this->status = $this->status ?? 'received';

        // Bind
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":supplier_id", $this->supplier_id);
        $stmt->bindParam(":invoice_number", $this->invoice_number);
        $stmt->bindParam(":invoice_date", $this->invoice_date);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":reception_date", $this->reception_date);
        $stmt->bindParam(":subtotal", $this->subtotal);
        $stmt->bindParam(":tva_amount", $this->tva_amount);
        $stmt->bindParam(":total", $this->total);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":qr_reference", $this->qr_reference);
        $stmt->bindParam(":iban", $this->iban);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":created_by", $this->created_by);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();

            // Créer les lignes de facture
            if (!empty($this->items)) {
                return $this->createItems();
            }

            return true;
        }

        return false;
    }

    /**
     * Créer les lignes de la facture
     */
    private function createItems() {
        $query = "INSERT INTO " . $this->items_table . "
                (supplier_invoice_id, description, quantity, unit_price, tva_rate, tva_amount, subtotal, total, account_id, sort_order)
                VALUES (:supplier_invoice_id, :description, :quantity, :unit_price, :tva_rate, :tva_amount, :subtotal, :total, :account_id, :sort_order)";

        $stmt = $this->conn->prepare($query);

        $sort_order = 0;
        foreach ($this->items as $item) {
            $stmt->bindParam(":supplier_invoice_id", $this->id);
            $stmt->bindParam(":description", $item['description']);
            $stmt->bindParam(":quantity", $item['quantity']);
            $stmt->bindParam(":unit_price", $item['unit_price']);
            $stmt->bindParam(":tva_rate", $item['tva_rate']);
            $stmt->bindParam(":tva_amount", $item['tva_amount']);
            $stmt->bindParam(":subtotal", $item['subtotal']);
            $stmt->bindParam(":total", $item['total']);
            $stmt->bindParam(":account_id", $item['account_id']);
            $stmt->bindParam(":sort_order", $sort_order);

            if (!$stmt->execute()) {
                return false;
            }

            $sort_order++;
        }

        return true;
    }

    /**
     * Lire une facture par ID
     */
    public function read() {
        $query = "SELECT si.*, c.name as supplier_name, c.email as supplier_email
                FROM " . $this->table_name . " si
                LEFT JOIN contacts c ON si.supplier_id = c.id
                WHERE si.id = :id AND si.company_id = :company_id
                LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->invoice_number = $row['invoice_number'];
            $this->supplier_id = $row['supplier_id'];
            $this->invoice_date = $row['invoice_date'];
            $this->due_date = $row['due_date'];
            $this->reception_date = $row['reception_date'];
            $this->subtotal = $row['subtotal'];
            $this->tva_amount = $row['tva_amount'];
            $this->total = $row['total'];
            $this->status = $row['status'];
            $this->payment_date = $row['payment_date'];
            $this->payment_method = $row['payment_method'];
            $this->qr_reference = $row['qr_reference'];
            $this->iban = $row['iban'];
            $this->scanned_pdf_path = $row['scanned_pdf_path'];
            $this->notes = $row['notes'];
            $this->approved_by = $row['approved_by'];
            $this->approved_at = $row['approved_at'];
            $this->created_by = $row['created_by'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];

            // Charger les lignes
            $this->loadItems();

            return true;
        }

        return false;
    }

    /**
     * Charger les lignes de la facture
     */
    private function loadItems() {
        $query = "SELECT * FROM " . $this->items_table . "
                WHERE supplier_invoice_id = :supplier_invoice_id
                ORDER BY sort_order";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":supplier_invoice_id", $this->id);
        $stmt->execute();

        $this->items = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->items[] = $row;
        }
    }

    /**
     * Mettre à jour une facture
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                SET supplier_id = :supplier_id,
                    invoice_number = :invoice_number,
                    invoice_date = :invoice_date,
                    due_date = :due_date,
                    reception_date = :reception_date,
                    subtotal = :subtotal,
                    tva_amount = :tva_amount,
                    total = :total,
                    status = :status,
                    payment_date = :payment_date,
                    payment_method = :payment_method,
                    qr_reference = :qr_reference,
                    iban = :iban,
                    notes = :notes
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        // Bind
        $stmt->bindParam(":supplier_id", $this->supplier_id);
        $stmt->bindParam(":invoice_number", $this->invoice_number);
        $stmt->bindParam(":invoice_date", $this->invoice_date);
        $stmt->bindParam(":due_date", $this->due_date);
        $stmt->bindParam(":reception_date", $this->reception_date);
        $stmt->bindParam(":subtotal", $this->subtotal);
        $stmt->bindParam(":tva_amount", $this->tva_amount);
        $stmt->bindParam(":total", $this->total);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":payment_date", $this->payment_date);
        $stmt->bindParam(":payment_method", $this->payment_method);
        $stmt->bindParam(":qr_reference", $this->qr_reference);
        $stmt->bindParam(":iban", $this->iban);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Supprimer une facture
     */
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . "
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Lister toutes les factures d'une société
     */
    public function readByCompany($company_id, $filters = []) {
        $query = "SELECT si.*, c.name as supplier_name,
                    COALESCE(
                        (SELECT SUM(p.amount)
                         FROM payments p
                         WHERE p.supplier_invoice_id = si.id),
                        0
                    ) AS amount_paid,
                    (si.total - COALESCE(
                        (SELECT SUM(p.amount)
                         FROM payments p
                         WHERE p.supplier_invoice_id = si.id),
                        0
                    )) AS amount_due
                FROM " . $this->table_name . " si
                LEFT JOIN contacts c ON si.supplier_id = c.id
                WHERE si.company_id = :company_id";

        // Filtres
        if (!empty($filters['status'])) {
            $query .= " AND si.status = :status";
        }

        if (!empty($filters['supplier_id'])) {
            $query .= " AND si.supplier_id = :supplier_id";
        }

        if (!empty($filters['date_from'])) {
            $query .= " AND si.invoice_date >= :date_from";
        }

        if (!empty($filters['date_to'])) {
            $query .= " AND si.invoice_date <= :date_to";
        }

        $query .= " ORDER BY si.invoice_date DESC, si.id DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if (!empty($filters['status'])) {
            $stmt->bindParam(":status", $filters['status']);
        }

        if (!empty($filters['supplier_id'])) {
            $stmt->bindParam(":supplier_id", $filters['supplier_id']);
        }

        if (!empty($filters['date_from'])) {
            $stmt->bindParam(":date_from", $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $stmt->bindParam(":date_to", $filters['date_to']);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir les factures en retard
     */
    public function getOverdueInvoices($company_id) {
        $query = "SELECT * FROM v_overdue_supplier_invoices
                WHERE company_id = :company_id
                ORDER BY days_overdue DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marquer comme payée
     */
    public function markAsPaid($payment_date, $payment_method) {
        $query = "UPDATE " . $this->table_name . "
                SET status = 'paid',
                    payment_date = :payment_date,
                    payment_method = :payment_method
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":payment_date", $payment_date);
        $stmt->bindParam(":payment_method", $payment_method);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Approuver une facture
     */
    public function approve($user_id) {
        $query = "UPDATE " . $this->table_name . "
                SET status = 'approved',
                    approved_by = :user_id,
                    approved_at = NOW()
                WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $user_id);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Obtenir les statistiques
     */
    public function getStatistics($company_id) {
        $query = "SELECT
                    COUNT(*) as total_invoices,
                    SUM(CASE WHEN status = 'received' THEN 1 ELSE 0 END) as received_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                    SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(total) as total_amount,
                    SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_amount,
                    SUM(CASE WHEN status IN ('received', 'approved') THEN total ELSE 0 END) as pending_amount,
                    (SELECT COUNT(*) FROM v_overdue_supplier_invoices WHERE company_id = :company_id) as overdue_count,
                    (SELECT SUM(amount_due) FROM v_overdue_supplier_invoices WHERE company_id = :company_id) as overdue_amount
                FROM " . $this->table_name . "
                WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Scanner et extraire données d'une QR-facture
     * Note: Nécessiterait une bibliothèque OCR comme Tesseract
     */
    public function scanQRInvoice($image_path) {
        // TODO: Implémenter avec Tesseract OCR ou API tierce
        // Pour l'instant, retourner false
        return false;
    }

    /**
     * Calculer les totaux à partir des lignes
     */
    public function calculateTotals() {
        $subtotal = 0;
        $tva_total = 0;

        foreach ($this->items as $item) {
            $subtotal += floatval($item['subtotal']);
            $tva_total += floatval($item['tva_amount']);
        }

        $this->subtotal = $subtotal;
        $this->tva_amount = $tva_total;
        $this->total = $subtotal + $tva_total;
    }
}
?>
