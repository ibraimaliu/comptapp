<?php
/**
 * Modèle Subscription
 * Gestion des abonnements clients
 */
class Subscription {
    private $conn;
    private $table_name = "subscriptions";

    // Propriétés
    public $id;
    public $company_id;
    public $contact_id;
    public $recurring_invoice_id;
    public $subscription_name;
    public $subscription_type;
    public $status;
    public $start_date;
    public $trial_end_date;
    public $current_period_start;
    public $current_period_end;
    public $cancel_at_period_end;
    public $cancelled_at;
    public $ended_at;
    public $amount;
    public $currency;
    public $billing_cycle;
    public $auto_renew;
    public $renewal_reminder_days;
    public $metadata;
    public $created_at;
    public $updated_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Créer un abonnement
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                  SET company_id = :company_id,
                      contact_id = :contact_id,
                      recurring_invoice_id = :recurring_invoice_id,
                      subscription_name = :subscription_name,
                      subscription_type = :subscription_type,
                      status = :status,
                      start_date = :start_date,
                      trial_end_date = :trial_end_date,
                      current_period_start = :current_period_start,
                      current_period_end = :current_period_end,
                      cancel_at_period_end = :cancel_at_period_end,
                      amount = :amount,
                      currency = :currency,
                      billing_cycle = :billing_cycle,
                      auto_renew = :auto_renew,
                      renewal_reminder_days = :renewal_reminder_days,
                      metadata = :metadata";

        $stmt = $this->conn->prepare($query);

        // Bind
        $stmt->bindParam(":company_id", $this->company_id);
        $stmt->bindParam(":contact_id", $this->contact_id);
        $stmt->bindParam(":recurring_invoice_id", $this->recurring_invoice_id);
        $stmt->bindParam(":subscription_name", $this->subscription_name);
        $stmt->bindParam(":subscription_type", $this->subscription_type);
        $stmt->bindParam(":status", $this->status);
        $stmt->bindParam(":start_date", $this->start_date);
        $stmt->bindParam(":trial_end_date", $this->trial_end_date);
        $stmt->bindParam(":current_period_start", $this->current_period_start);
        $stmt->bindParam(":current_period_end", $this->current_period_end);
        $stmt->bindParam(":cancel_at_period_end", $this->cancel_at_period_end);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":currency", $this->currency);
        $stmt->bindParam(":billing_cycle", $this->billing_cycle);
        $stmt->bindParam(":auto_renew", $this->auto_renew);
        $stmt->bindParam(":renewal_reminder_days", $this->renewal_reminder_days);
        $stmt->bindParam(":metadata", $this->metadata);

        if($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();

            // Créer un événement
            $this->logEvent($this->id, 'created', 0, 'Abonnement créé');

            return true;
        }
        return false;
    }

    /**
     * Lire un abonnement
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
     * Mettre à jour un abonnement
     */
    public function update() {
        $query = "UPDATE " . $this->table_name . "
                  SET subscription_name = :subscription_name,
                      amount = :amount,
                      billing_cycle = :billing_cycle,
                      auto_renew = :auto_renew,
                      renewal_reminder_days = :renewal_reminder_days,
                      metadata = :metadata
                  WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(":subscription_name", $this->subscription_name);
        $stmt->bindParam(":amount", $this->amount);
        $stmt->bindParam(":billing_cycle", $this->billing_cycle);
        $stmt->bindParam(":auto_renew", $this->auto_renew);
        $stmt->bindParam(":renewal_reminder_days", $this->renewal_reminder_days);
        $stmt->bindParam(":metadata", $this->metadata);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":company_id", $this->company_id);

        return $stmt->execute();
    }

    /**
     * Lister les abonnements
     */
    public function readByCompany($company_id, $status = null) {
        $query = "SELECT * FROM v_subscriptions_overview WHERE company_id = :company_id";

        if ($status) {
            $query .= " AND status = :status";
        }

        $query .= " ORDER BY current_period_end ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);

        if ($status) {
            $stmt->bindParam(":status", $status);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Renouveler un abonnement
     */
    public function renew($subscription_id, $company_id) {
        // Charger l'abonnement
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE id = :id AND company_id = :company_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $subscription_id);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscription) {
            return ['success' => false, 'message' => 'Abonnement introuvable'];
        }

        if ($subscription['status'] != 'active') {
            return ['success' => false, 'message' => 'Abonnement non actif'];
        }

        // Calculer la nouvelle période
        $new_period_start = date('Y-m-d', strtotime($subscription['current_period_end']) + 86400);
        $new_period_end = $this->calculatePeriodEnd($new_period_start, $subscription['billing_cycle']);

        // Mettre à jour
        $update_query = "UPDATE " . $this->table_name . "
                         SET current_period_start = :period_start,
                             current_period_end = :period_end,
                             cancel_at_period_end = 0
                         WHERE id = :id";

        $update_stmt = $this->conn->prepare($update_query);
        $update_stmt->bindParam(":period_start", $new_period_start);
        $update_stmt->bindParam(":period_end", $new_period_end);
        $update_stmt->bindParam(":id", $subscription_id);

        if ($update_stmt->execute()) {
            // Créer un événement
            $this->logEvent($subscription_id, 'renewed', $subscription['amount'], 'Abonnement renouvelé');

            return [
                'success' => true,
                'message' => 'Abonnement renouvelé',
                'new_period_end' => $new_period_end
            ];
        }

        return ['success' => false, 'message' => 'Erreur lors du renouvellement'];
    }

    /**
     * Annuler un abonnement
     */
    public function cancel($subscription_id, $company_id, $immediate = false) {
        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE id = :id AND company_id = :company_id
                  LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $subscription_id);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$subscription) {
            return ['success' => false, 'message' => 'Abonnement introuvable'];
        }

        $now = date('Y-m-d H:i:s');

        if ($immediate) {
            // Annulation immédiate
            $update_query = "UPDATE " . $this->table_name . "
                             SET status = 'cancelled',
                                 cancelled_at = :cancelled_at,
                                 ended_at = :ended_at,
                                 cancel_at_period_end = 0
                             WHERE id = :id";

            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(":cancelled_at", $now);
            $update_stmt->bindParam(":ended_at", $now);
            $update_stmt->bindParam(":id", $subscription_id);
            $update_stmt->execute();

            $this->logEvent($subscription_id, 'cancelled', 0, 'Abonnement annulé immédiatement');

            return ['success' => true, 'message' => 'Abonnement annulé immédiatement'];

        } else {
            // Annulation à la fin de la période
            $update_query = "UPDATE " . $this->table_name . "
                             SET cancel_at_period_end = 1,
                                 cancelled_at = :cancelled_at
                             WHERE id = :id";

            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(":cancelled_at", $now);
            $update_stmt->bindParam(":id", $subscription_id);
            $update_stmt->execute();

            $this->logEvent($subscription_id, 'cancelled', 0, 'Abonnement marqué pour annulation en fin de période');

            return [
                'success' => true,
                'message' => 'Abonnement sera annulé le ' . date('d/m/Y', strtotime($subscription['current_period_end']))
            ];
        }
    }

    /**
     * Mettre en pause un abonnement
     */
    public function pause($subscription_id, $company_id) {
        $update_query = "UPDATE " . $this->table_name . "
                         SET status = 'paused'
                         WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($update_query);
        $stmt->bindParam(":id", $subscription_id);
        $stmt->bindParam(":company_id", $company_id);

        if ($stmt->execute()) {
            $this->logEvent($subscription_id, 'paused', 0, 'Abonnement mis en pause');
            return ['success' => true, 'message' => 'Abonnement mis en pause'];
        }

        return ['success' => false, 'message' => 'Erreur lors de la mise en pause'];
    }

    /**
     * Réactiver un abonnement
     */
    public function reactivate($subscription_id, $company_id) {
        $update_query = "UPDATE " . $this->table_name . "
                         SET status = 'active',
                             cancel_at_period_end = 0
                         WHERE id = :id AND company_id = :company_id";

        $stmt = $this->conn->prepare($update_query);
        $stmt->bindParam(":id", $subscription_id);
        $stmt->bindParam(":company_id", $company_id);

        if ($stmt->execute()) {
            $this->logEvent($subscription_id, 'activated', 0, 'Abonnement réactivé');
            return ['success' => true, 'message' => 'Abonnement réactivé'];
        }

        return ['success' => false, 'message' => 'Erreur lors de la réactivation'];
    }

    /**
     * Récupérer les abonnements à renouveler
     */
    public function getDueForRenewal($days_ahead = 7) {
        $future_date = date('Y-m-d', strtotime("+$days_ahead days"));

        $query = "SELECT * FROM " . $this->table_name . "
                  WHERE status = 'active'
                  AND auto_renew = 1
                  AND current_period_end <= :future_date
                  AND cancel_at_period_end = 0
                  ORDER BY current_period_end ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":future_date", $future_date);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Traiter les abonnements expirés
     */
    public function processExpired() {
        $today = date('Y-m-d');

        $query = "UPDATE " . $this->table_name . "
                  SET status = 'expired',
                      ended_at = NOW()
                  WHERE status IN ('active', 'trial')
                  AND current_period_end < :today
                  AND cancel_at_period_end = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":today", $today);

        return $stmt->execute();
    }

    /**
     * Calculer la date de fin de période
     */
    private function calculatePeriodEnd($start_date, $billing_cycle) {
        $date = new DateTime($start_date);

        switch ($billing_cycle) {
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

        $date->modify('-1 day');
        return $date->format('Y-m-d');
    }

    /**
     * Logger un événement
     */
    private function logEvent($subscription_id, $event_type, $amount = null, $description = null) {
        $query = "INSERT INTO subscription_events
                  (subscription_id, event_type, amount, description)
                  VALUES
                  (:subscription_id, :event_type, :amount, :description)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":subscription_id", $subscription_id);
        $stmt->bindParam(":event_type", $event_type);
        $stmt->bindParam(":amount", $amount);
        $stmt->bindParam(":description", $description);

        return $stmt->execute();
    }

    /**
     * Récupérer l'historique des événements
     */
    public function getEvents($subscription_id, $company_id) {
        $query = "SELECT e.*
                  FROM subscription_events e
                  INNER JOIN subscriptions s ON e.subscription_id = s.id
                  WHERE e.subscription_id = :subscription_id
                  AND s.company_id = :company_id
                  ORDER BY e.event_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":subscription_id", $subscription_id);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Statistiques des abonnements
     */
    public function getStats($company_id) {
        $query = "SELECT
                      COUNT(*) as total,
                      SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                      SUM(CASE WHEN status = 'trial' THEN 1 ELSE 0 END) as trial,
                      SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                      SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                      SUM(CASE WHEN status = 'active' THEN amount ELSE 0 END) as mrr
                  FROM " . $this->table_name . "
                  WHERE company_id = :company_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":company_id", $company_id);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
