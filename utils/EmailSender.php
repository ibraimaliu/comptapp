<?php
/**
 * Classe: EmailSender
 * Description: Envoi d'emails avec PHPMailer ou mail() natif
 * Version: 1.0
 */

class EmailSender {
    private $from_email;
    private $from_name;
    private $smtp_enabled = false;
    private $smtp_host;
    private $smtp_port;
    private $smtp_username;
    private $smtp_password;
    private $smtp_secure; // tls ou ssl

    /**
     * Constructeur
     */
    public function __construct($config = []) {
        $this->from_email = $config['from_email'] ?? 'noreply@gestion-comptable.local';
        $this->from_name = $config['from_name'] ?? 'Gestion Comptable';

        // Configuration SMTP optionnelle
        if (isset($config['smtp'])) {
            $this->smtp_enabled = true;
            $this->smtp_host = $config['smtp']['host'] ?? 'localhost';
            $this->smtp_port = $config['smtp']['port'] ?? 587;
            $this->smtp_username = $config['smtp']['username'] ?? '';
            $this->smtp_password = $config['smtp']['password'] ?? '';
            $this->smtp_secure = $config['smtp']['secure'] ?? 'tls';
        }
    }

    /**
     * Envoyer un email simple
     */
    public function send($to, $subject, $body, $attachments = []) {
        if ($this->smtp_enabled && class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            return $this->sendWithPHPMailer($to, $subject, $body, $attachments);
        } else {
            return $this->sendWithNative($to, $subject, $body);
        }
    }

    /**
     * Envoyer avec PHPMailer (si disponible)
     */
    private function sendWithPHPMailer($to, $subject, $body, $attachments = []) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);

            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = $this->smtp_host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->smtp_username;
            $mail->Password = $this->smtp_password;
            $mail->SMTPSecure = $this->smtp_secure;
            $mail->Port = $this->smtp_port;
            $mail->CharSet = 'UTF-8';

            // Expéditeur et destinataire
            $mail->setFrom($this->from_email, $this->from_name);

            if (is_array($to)) {
                foreach ($to as $email) {
                    $mail->addAddress($email);
                }
            } else {
                $mail->addAddress($to);
            }

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);

            // Pièces jointes
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $mail->addAttachment($attachment);
                }
            }

            $mail->send();
            return true;

        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer avec fonction mail() native
     */
    private function sendWithNative($to, $subject, $body) {
        try {
            $headers = [
                'From: ' . $this->from_name . ' <' . $this->from_email . '>',
                'Reply-To: ' . $this->from_email,
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: PHP/' . phpversion()
            ];

            $to_address = is_array($to) ? implode(', ', $to) : $to;

            return mail(
                $to_address,
                $subject,
                $body,
                implode("\r\n", $headers)
            );

        } catch (Exception $e) {
            error_log("Email error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer un rappel de paiement
     */
    public function sendPaymentReminder($client_email, $client_name, $invoice_number, $amount, $due_date, $days_overdue, $level = 1) {
        $subject = $this->getReminderSubject($level);
        $body = $this->getReminderBody($client_name, $invoice_number, $amount, $due_date, $days_overdue, $level);

        return $this->send($client_email, $subject, $body);
    }

    /**
     * Obtenir le sujet selon le niveau de relance
     */
    private function getReminderSubject($level) {
        switch ($level) {
            case 1:
                return 'Rappel de paiement - Facture en attente';
            case 2:
                return 'Rappel urgent - Facture impayée';
            case 3:
                return 'Mise en demeure - Dernière relance';
            default:
                return 'Rappel de paiement';
        }
    }

    /**
     * Générer le corps du message
     */
    private function getReminderBody($client_name, $invoice_number, $amount, $due_date, $days_overdue, $level) {
        $urgency_text = '';
        $color = '#667eea';

        switch ($level) {
            case 1:
                $urgency_text = 'Nous vous rappelons que le paiement de votre facture est en attente.';
                $color = '#f59e0b';
                break;
            case 2:
                $urgency_text = 'Nous n\'avons toujours pas reçu votre paiement malgré notre premier rappel.';
                $color = '#ef4444';
                break;
            case 3:
                $urgency_text = 'MISE EN DEMEURE - Ceci est notre dernière relance avant poursuites judiciaires.';
                $color = '#dc2626';
                break;
        }

        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px 20px;
            border: 1px solid #e0e0e0;
        }
        .alert {
            background: ' . $color . ';
            color: white;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .invoice-details {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .detail-label {
            font-weight: bold;
            color: #666;
        }
        .detail-value {
            color: #333;
        }
        .amount {
            font-size: 1.5em;
            font-weight: bold;
            color: ' . $color . ';
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            font-size: 0.9em;
            color: #666;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: ' . $color . ';
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rappel de Paiement</h1>
    </div>

    <div class="content">
        <p>Madame, Monsieur ' . htmlspecialchars($client_name) . ',</p>

        <div class="alert">
            ' . $urgency_text . '
        </div>

        <div class="invoice-details">
            <div class="detail-row">
                <span class="detail-label">Numéro de facture:</span>
                <span class="detail-value">' . htmlspecialchars($invoice_number) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date d\'échéance:</span>
                <span class="detail-value">' . date('d.m.Y', strtotime($due_date)) . '</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Jours de retard:</span>
                <span class="detail-value" style="color: ' . $color . ';"><strong>' . $days_overdue . ' jours</strong></span>
            </div>
            <div class="detail-row" style="border-bottom: none;">
                <span class="detail-label">Montant dû:</span>
                <span class="amount">CHF ' . number_format($amount, 2, '.', '\'') . '</span>
            </div>
        </div>

        <p>Nous vous prions de bien vouloir régulariser votre situation dans les plus brefs délais.</p>

        ' . ($level >= 2 ? '<p><strong>Si vous avez déjà effectué le paiement, veuillez ne pas tenir compte de ce rappel.</strong></p>' : '') . '

        ' . ($level == 3 ? '
        <p style="color: #dc2626;"><strong>ATTENTION:</strong> En l\'absence de paiement sous 10 jours, nous serons contraints d\'engager des poursuites judiciaires, ce qui entraînera des frais supplémentaires à votre charge.</p>
        ' : '') . '

        <p>Pour toute question concernant cette facture, n\'hésitez pas à nous contacter.</p>

        <p>Cordialement,<br>
        <strong>' . htmlspecialchars($this->from_name) . '</strong></p>
    </div>

    <div class="footer">
        <p>Cet email a été envoyé automatiquement par notre système de gestion.</p>
        <p style="font-size: 0.8em; color: #999;">
            Si vous estimez avoir reçu cet email par erreur, veuillez nous contacter immédiatement.
        </p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Envoyer une notification de facture
     */
    public function sendInvoiceNotification($client_email, $client_name, $invoice_number, $amount, $due_date, $pdf_path = null) {
        $subject = 'Nouvelle facture N° ' . $invoice_number;

        $body = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #ffffff;
            padding: 30px 20px;
            border: 1px solid #e0e0e0;
        }
        .invoice-summary {
            background: #f0f4ff;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .amount {
            font-size: 2em;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 0 0 10px 10px;
            font-size: 0.9em;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Nouvelle Facture</h1>
    </div>

    <div class="content">
        <p>Madame, Monsieur ' . htmlspecialchars($client_name) . ',</p>

        <p>Nous vous informons qu\'une nouvelle facture a été émise à votre attention.</p>

        <div class="invoice-summary">
            <p><strong>Numéro de facture:</strong> ' . htmlspecialchars($invoice_number) . '</p>
            <p><strong>Date d\'échéance:</strong> ' . date('d.m.Y', strtotime($due_date)) . '</p>
            <div class="amount">CHF ' . number_format($amount, 2, '.', '\'') . '</div>
        </div>

        <p>Vous trouverez la facture détaillée en pièce jointe de cet email (format PDF).</p>

        <p>Le paiement peut être effectué par virement bancaire en scannant le code QR présent sur la facture ou en utilisant les coordonnées bancaires indiquées.</p>

        <p>Nous vous remercions pour votre confiance.</p>

        <p>Cordialement,<br>
        <strong>' . htmlspecialchars($this->from_name) . '</strong></p>
    </div>

    <div class="footer">
        <p>Pour toute question, n\'hésitez pas à nous contacter.</p>
    </div>
</body>
</html>';

        $attachments = [];
        if ($pdf_path && file_exists($pdf_path)) {
            $attachments[] = $pdf_path;
        }

        return $this->send($client_email, $subject, $body, $attachments);
    }
}
?>
