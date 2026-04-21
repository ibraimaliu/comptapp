<?php
/**
 * Modèle RecurringInvoice
 * Gestion des factures récurrentes et leur génération automatique
 */
class RecurringInvoice {
    private $conn;
    private $table_name = "recurring_invoices";

    // Propriétés
    public $id;
    public $company_id;
    public $template_name;
    public $contact_id;
    public $status;
    public $frequency;
    public $start_date;
    public $end_date;
    public $next_generation_date;
    public $last_generation_date;
    public $occurrences_count;
    public $max_occurrences;
    public $invoice_prefix;
    public $payment_terms_days;
    public $currency;
    public $notes;
    public $footer_text;
    public $auto_send_email;
    public $email_template_id;
    public $auto_mark_sent;
    public $created_by;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer une facture récurrente
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET company_id = :company_id,
                      template_name = :template_name,
                      contact_id = :contact_id,
                      status = :status,
                      frequency = :frequency,
                      start_date = :start_date,
                      end_date = :end_date,
                      next_generation_date = :next_generation_date,
                      last_generation_date = :last_generation_date,
                      occurrences_count = :occurrences_count,
                      max_occurrences = :max_occurrences,
                      invoice_prefix = :invoice_prefix,
                      payment_terms_days = :payment_terms_days,
                      currency = :currency,
                      notes = :notes,
                      footer_text = :footer_text,
                      auto_send_email = :auto_send_email,
                      email_template_id = :email_template_id,
                      auto_mark_sent = :auto_mark_sent,
                      created_by = :created_by";

        $stmt = $this->conn->prepare($query);

        // Bind
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":template_name", $this->template_name);
        $stmt->bindParam(":contact_id", $this->contact_id);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":frequency", $this->frequency);
        $stmt->bindParam(":start_date", $this->start_date);
        $stmt->bindParam(":end_date", $this->end_date);
        $stmt->bindParam(":next_generation_date", $this->next_generation_date);
        $stmt->bindParam(":last_generation_date", $this->last_generation_date);
        $stmt->bindParam(":occurrences_count", $this->occurrences_count);
        $stmt->bindParam(":max_occurrences", $this->max_occurrences);
        $stmt->bindParam(":invoice_prefix", $this->invoice_prefix);
        $stmt->bindParam(":payment_terms_days", $this->payment_terms_days);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":footer_text", $this->footer_text);
        $stmt->bindParam(":auto_send_email", $this->auto_send_email);
        $stmt->bindParam(":email_template_id", $this->email_template_id);
        $stmt->bindParam(":auto_mark_sent", $this->auto_mark_sent);
        $stmt->bindParam(":created_by", $this->created_by);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    /**
     * Lire une facture récurrente
     */
    public function read() {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE id = :id AND company_id = :company_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Mettre à jour une facture récurrente
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET template_name = :template_name,
                      contact_id = :contact_id,
                      status = :status,
                      frequency = :frequency,
                      start_date = :start_date,
                      end_date = :end_date,
                      next_generation_date = :next_generation_date,
                      max_occurrences = :max_occurrences,
                      invoice_prefix = :invoice_prefix,
                      payment_terms_days = :payment_terms_days,
                      currency = :currency,
                      notes = :notes,
                      footer_text = :footer_text,
                      auto_send_email = :auto_send_email,
                      auto_mark_sent = :auto_mark_sent
                  WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":template_name", $this->template_name);
        $stmt->bindParam(":contact_id", $this->contact_id);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":frequency", $this->frequency);
        $stmt->bindParam(":start_date", $this->start_date);
        $stmt->bindParam(":end_date", $this->end_date);
        $stmt->bindParam(":next_generation_date", $this->next_generation_date);
        $stmt->bindParam(":max_occurrences", $this->max_occurrences);
        $stmt->bindParam(":invoice_prefix", $this->invoice_prefix);
        $stmt->bindParam(":payment_terms_days", $this->payment_terms_days);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":notes", $this->notes);
        $stmt->bindParam(":footer_text", $this->footer_text);
        $stmt->bindParam(":auto_send_email", $this->auto_send_email);
        $stmt->bindParam(":auto_mark_sent", $this->auto_mark_sent);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Supprimer une facture récurrente
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
     * Lister toutes les factures récurrentes d'une entreprise
     */
    public function readByCompany($company_id, $status = null) {
        $query = "SELECT * FROM v_active_recurring_invoices WHERE company_id = :company_id";

        if ($status) {
            $query .= " AND status = :status";
        }

        $query .= " ORDER BY next_generation_date ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if ($status) {
            $stmt->bindParam(":status", $status);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupérer les factures récurrentes à générer
     */
    public function getDueForGeneration() {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE status = 'active'
                  AND next_generation_date <= CURDATE()
                  AND (max_occurrences IS NULL OR occurrences_count < max_occurrences)
                  ORDER BY next_generation_date ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Générer une facture à partir du template récurrent
     */
    public function generateInvoice($recurring_id, $company_id) {
        // Charger le template récurrent
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE id = :id AND company_id = :company_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $recurring_id);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $recurring = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$recurring) {
            return ['success' => false, 'message' => 'Template introuvable'];
        }

        // Vérifier si on peut générer
        if ($recurring['status'] != 'active') {
            return ['success' => false, 'message' => 'Template non actif'];
        }

        if ($recurring['max_occurrences'] && $recurring['occurrences_count'] >= $recurring['max_occurrences']) {
            return ['success' => false, 'message' => 'Nombre maximum d\'occurrences atteint'];
        }

        try {
            $this->conn->beginTransaction();

            // Charger les items du template
            $items_query = "SELECT * FROM recurring_invoice_items
                            WHERE recurring_invoice_id = :recurring_id
                            ORDER BY sort_order ASC";

            $items_stmt = $this->conn->prepare($items_query);
            $items_stmt->bindParam(":recurring_id", $recurring_id);
            $items_stmt->execute();
            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

            // Générer le numéro de facture
            require_once 'Invoice.php';
            $invoice = new Invoice($this->conn);
            $invoice_number = $invoice->generateInvoiceNumber($company_id, $recurring['invoice_prefix']);

            // Calculer les dates
            $invoice_date = date('Y-m-d');
            $due_date = date('Y-m-d', strtotime($invoice_date . ' + ' . $recurring['payment_terms_days'] . ' days'));

            // Calculer le total
            $total_amount = 0;
            foreach ($items as $item) {
                $line_total = $item['quantity'] * $item['unit_price'];
                $line_total *= (1 - $item['discount_percent'] / 100);
                $line_total *= (1 + $item['tva_rate'] / 100);
                $total_amount += $line_total;
            }

            // Créer la facture
            $invoice_insert = "INSERT INTO invoices
                               (company_id, invoice_number, contact_id, date, due_date, status, total_amount, notes, footer_text, currency)
                               VALUES
                               (:company_id, :invoice_number, :contact_id, :date, :due_date, :status, :total_amount, :notes, :footer_text, :currency)";

            $invoice_stmt = $this->conn->prepare($invoice_insert);
            $invoice_status = $recurring['auto_mark_sent'] ? 'sent' : 'draft';

            $invoice_stmt->bindParam(":company_id", $company_id);
            $invoice_stmt->bindParam(":invoice_number", $invoice_number);
            $invoice_stmt->bindParam(":contact_id", $recurring['contact_id']);
            $invoice_stmt->bindParam(":date", $invoice_date);
            $invoice_stmt->bindParam(":due_date", $due_date);
            $invoice_stmt->bindParam(":status", $invoice_status);
            $invoice_stmt->bindParam(":total_amount", $total_amount);
            $invoice_stmt->bindParam(":notes", $recurring['notes']);
            $invoice_stmt->bindParam(":footer_text", $recurring['footer_text']);
            $invoice_stmt->bindParam(":currency", $recurring['currency']);
            $invoice_stmt->execute();

            $invoice_id = $this->conn->lastInsertId();

            // Créer les items de la facture
            $item_insert = "INSERT INTO invoice_items
                            (invoice_id, product_id, description, quantity, unit_price, tva_rate, discount_percent, sort_order)
                            VALUES
                            (:invoice_id, :product_id, :description, :quantity, :unit_price, :tva_rate, :discount_percent, :sort_order)";

            $item_stmt = $this->conn->prepare($item_insert);

            foreach ($items as $item) {
                $item_stmt->bindParam(":invoice_id", $invoice_id);
                $item_stmt->bindParam(":product_id", $item['product_id']);
                $item_stmt->bindParam(":description", $item['description']);
                $item_stmt->bindParam(":quantity", $item['quantity']);
                $item_stmt->bindParam(":unit_price", $item['unit_price']);
                $item_stmt->bindParam(":tva_rate", $item['tva_rate']);
                $item_stmt->bindParam(":discount_percent", $item['discount_percent']);
                $item_stmt->bindParam(":sort_order", $item['sort_order']);
                $item_stmt->execute();
            }

            // Calculer la prochaine date de génération
            $next_date = $this->calculateNextDate($recurring['next_generation_date'], $recurring['frequency']);

            // Mettre à jour le template récurrent
            $update_query = "UPDATE " . $this->table_name . "
                             SET occurrences_count = occurrences_count + 1,
                                 last_generation_date = :current_date,
                                 next_generation_date = :next_date
                             WHERE id = :id";

            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(":current_date", $invoice_date);
            $update_stmt->bindParam(":next_date", $next_date);
            $update_stmt->bindParam(":id", $recurring_id);
            $update_stmt->execute();

            // Enregistrer dans l'historique
            $history_insert = "INSERT INTO recurring_invoice_history
                               (recurring_invoice_id, generated_invoice_id, scheduled_date, invoice_date, invoice_number, total_amount, status)
                               VALUES
                               (:recurring_id, :invoice_id, :scheduled_date, :invoice_date, :invoice_number, :total_amount, :status)";

            $history_stmt = $this->conn->prepare($history_insert);
            $history_stmt->bindParam(":recurring_id", $recurring_id);
            $history_stmt->bindParam(":invoice_id", $invoice_id);
            $history_stmt->bindParam(":scheduled_date", $recurring['next_generation_date']);
            $history_stmt->bindParam(":invoice_date", $invoice_date);
            $history_stmt->bindParam(":invoice_number", $invoice_number);
            $history_stmt->bindParam(":total_amount", $total_amount);
            $history_stmt->bindParam(":status", $invoice_status);
            $history_stmt->execute();

            // Vérifier si on a atteint le max ou la date de fin
            if ($recurring['max_occurrences'] && ($recurring['occurrences_count'] + 1) >= $recurring['max_occurrences']) {
                $this->updateStatus($recurring_id, $company_id, 'completed');
            } elseif ($recurring['end_date'] && $next_date > $recurring['end_date']) {
                $this->updateStatus($recurring_id, $company_id, 'completed');
            }

            $this->conn->commit();

            return [
                'success' => true,
                'invoice_id' => $invoice_id,
                'invoice_number' => $invoice_number,
                'message' => 'Facture générée avec succès'
            ];

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Erreur génération facture récurrente: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Calculer la prochaine date selon la fréquence
     */
    private function calculateNextDate($current_date, $frequency) {
        $date = new DateTime($current_date);

        switch ($frequency) {
            case 'daily':
                $date->modify('+1 day');
                break;
            case 'weekly':
                $date->modify('+1 week');
                break;
            case 'biweekly':
                $date->modify('+2 weeks');
                break;
            case 'monthly':
                $date->modify('+1 month');
                break;
            case 'quarterly':
                $date->modify('+3 months');
                break;
            case 'semiannual':
                $date->modify('+6 months');
                break;
            case 'annual':
                $date->modify('+1 year');
                break;
            default:
                $date->modify('+1 month');
        }

        return $date->format('Y-m-d');
    }

    /**
     * Changer le statut d'une facture récurrente
     */
    public function updateStatus($recurring_id, $company_id, $status) {
        $query = "UPDATE " . $this->table_name . "
                  SET status = :status
                  WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":status", $status);
        $stmt->bindParam(":id", $recurring_id);
        $stmt->bindParam(":company_id", $company_id);

        return $stmt->execute();
    }

    /**
     * Récupérer l'historique de génération
     */
    public function getHistory($recurring_id, $company_id) {
        $query = "SELECT h.*, i.status as current_invoice_status
                  FROM recurring_invoice_history h
                  INNER JOIN recurring_invoices ri ON h.recurring_invoice_id = ri.id
                  LEFT JOIN invoices i ON h.generated_invoice_id = i.id
                  WHERE h.recurring_invoice_id = :recurring_id
                  AND ri.company_id = :company_id
                  ORDER BY h.generation_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":recurring_id", $recurring_id);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajouter/mettre à jour les items du template
     */
    public function saveItems($recurring_id, $items) {
        try {
            $this->conn->beginTransaction();

            // Supprimer les items existants
            $delete_query = "DELETE FROM recurring_invoice_items WHERE recurring_invoice_id = :recurring_id";
            $delete_stmt = $this->conn->prepare($delete_query);
            $delete_stmt->bindParam(":recurring_id", $recurring_id);
            $delete_stmt->execute();

            // Insérer les nouveaux items
            $insert_query = "INSERT INTO recurring_invoice_items
                             (recurring_invoice_id, product_id, description, quantity, unit_price, tva_rate, discount_percent, sort_order)
                             VALUES
                             (:recurring_id, :product_id, :description, :quantity, :unit_price, :tva_rate, :discount_percent, :sort_order)";

            $insert_stmt = $this->conn->prepare($insert_query);

            foreach ($items as $index => $item) {
                $insert_stmt->bindParam(":recurring_id", $recurring_id);
                $insert_stmt->bindParam(":product_id", $item['product_id']);
                $insert_stmt->bindParam(":description", $item['description']);
                $insert_stmt->bindParam(":quantity", $item['quantity']);
                $insert_stmt->bindParam(":unit_price", $item['unit_price']);
                $insert_stmt->bindParam(":tva_rate", $item['tva_rate']);
                $insert_stmt->bindParam(":discount_percent", $item['discount_percent']);
                $sort_order = $index;
                $insert_stmt->bindParam(":sort_order", $sort_order);
                $insert_stmt->execute();
            }

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Erreur sauvegarde items: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupérer les items d'un template
     */
    public function getItems($recurring_id) {
        $query = "SELECT * FROM recurring_invoice_items
                  WHERE recurring_invoice_id = :recurring_id
                  ORDER BY sort_order ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":recurring_id", $recurring_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtenir les statistiques
     */
    public function getStats($company_id) {
        $query = "SELECT
                      COUNT(*) as total,
                      SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                      SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                      SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                      SUM(occurrences_count) as total_generated
                  FROM " . $this->table_name . "
                  WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
