<?php
/**
 * Templates d'emails pour le système multi-tenant
 * Nécessite PHPMailer: composer require phpmailer/phpmailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class TenantEmailTemplates {
    private $from_email = 'noreply@gestioncomptable.local';
    private $from_name = 'Gestion Comptable';

    /**
     * Envoyer un email
     */
    public function sendEmail($to_email, $to_name, $subject, $html_body) {
        // Vérifier si PHPMailer est disponible
        if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            // Mode développement : logger dans les fichiers
            error_log("=== EMAIL ENVOYÉ (MODE DEV) ===");
            error_log("To: {$to_email} ({$to_name})");
            error_log("Subject: {$subject}");
            error_log("Body (HTML): " . substr(strip_tags($html_body), 0, 200) . "...");
            return true; // Succès simulé
        }

        try {
            $mail = new PHPMailer(true);

            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = 'localhost';
            $mail->SMTPAuth = false;
            $mail->Port = 25;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to_email, $to_name);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_body;
            $mail->AltBody = strip_tags($html_body);

            return $mail->send();

        } catch (Exception $e) {
            error_log("Erreur envoi email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Email de bienvenue pour nouveau tenant
     */
    public function sendWelcomeEmail($tenant_data, $temp_password) {
        $subject = "Bienvenue sur Gestion Comptable - Vos identifiants";

        $html_body = $this->getTemplate([
            'title' => 'Bienvenue !',
            'content' => "
                <p>Bonjour <strong>{$tenant_data['contact_name']}</strong>,</p>

                <p>Votre compte <strong>{$tenant_data['company_name']}</strong> a été créé avec succès sur Gestion Comptable.</p>

                <div style='background: #f7fafc; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <h3 style='color: #2d3748; margin-top: 0;'>Vos identifiants de connexion :</h3>
                    <p style='margin: 10px 0;'><strong>Code Tenant :</strong> {$tenant_data['tenant_code']}</p>
                    <p style='margin: 10px 0;'><strong>Email :</strong> {$tenant_data['contact_email']}</p>
                    <p style='margin: 10px 0;'><strong>Mot de passe temporaire :</strong> <code style='background: #fff; padding: 5px 10px; border-radius: 5px;'>{$temp_password}</code></p>
                </div>

                <p><strong>⚠️ Important :</strong> Veuillez changer votre mot de passe dès votre première connexion.</p>

                <p>Vous bénéficiez d'une <strong>période d'essai de 30 jours</strong> avec le plan {$tenant_data['subscription_plan']}.</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/gestion_comptable/login_tenant.php'
                       style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                              color: white;
                              padding: 15px 40px;
                              border-radius: 10px;
                              text-decoration: none;
                              display: inline-block;
                              font-weight: 600;'>
                        Se Connecter Maintenant
                    </a>
                </div>

                <p>Si vous avez des questions, n'hésitez pas à nous contacter.</p>

                <p>Cordialement,<br>L'équipe Gestion Comptable</p>
            "
        ]);

        return $this->sendEmail(
            $tenant_data['contact_email'],
            $tenant_data['contact_name'],
            $subject,
            $html_body
        );
    }

    /**
     * Email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail($email, $company_name, $reset_link) {
        $subject = "Réinitialisation de votre mot de passe";

        $html_body = $this->getTemplate([
            'title' => 'Réinitialisation de mot de passe',
            'content' => "
                <p>Bonjour,</p>

                <p>Vous avez demandé à réinitialiser le mot de passe de votre compte <strong>{$company_name}</strong>.</p>

                <p>Cliquez sur le bouton ci-dessous pour créer un nouveau mot de passe :</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{$reset_link}'
                       style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                              color: white;
                              padding: 15px 40px;
                              border-radius: 10px;
                              text-decoration: none;
                              display: inline-block;
                              font-weight: 600;'>
                        Réinitialiser mon mot de passe
                    </a>
                </div>

                <p style='color: #718096; font-size: 0.9em;'>
                    Ou copiez ce lien dans votre navigateur :<br>
                    <code style='background: #f7fafc; padding: 5px 10px; border-radius: 5px; display: inline-block; margin-top: 5px; word-break: break-all;'>{$reset_link}</code>
                </p>

                <p><strong>⚠️ Important :</strong> Ce lien est valide pendant <strong>1 heure</strong>.</p>

                <p>Si vous n'avez pas demandé cette réinitialisation, ignorez cet email. Votre mot de passe actuel reste inchangé.</p>

                <p>Cordialement,<br>L'équipe Gestion Comptable</p>
            "
        ]);

        return $this->sendEmail($email, '', $subject, $html_body);
    }

    /**
     * Email de rappel d'expiration d'essai
     */
    public function sendTrialExpirationReminder($tenant_data, $days_remaining) {
        $subject = "Votre période d'essai expire dans {$days_remaining} jours";

        $html_body = $this->getTemplate([
            'title' => 'Période d\'essai bientôt terminée',
            'content' => "
                <p>Bonjour <strong>{$tenant_data['contact_name']}</strong>,</p>

                <p>Votre période d'essai de Gestion Comptable se termine dans <strong>{$days_remaining} jours</strong>.</p>

                <p>Pour continuer à profiter de toutes les fonctionnalités, nous vous invitons à souscrire à un abonnement.</p>

                <div style='background: #f7fafc; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                    <h3 style='color: #2d3748; margin-top: 0;'>Nos plans :</h3>
                    <ul style='list-style: none; padding: 0;'>
                        <li style='margin: 10px 0;'>💼 <strong>Starter</strong> - 29 CHF/mois</li>
                        <li style='margin: 10px 0;'>🚀 <strong>Professional</strong> - 79 CHF/mois</li>
                        <li style='margin: 10px 0;'>⭐ <strong>Enterprise</strong> - 199 CHF/mois</li>
                    </ul>
                </div>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/gestion_comptable/index.php?page=parametres'
                       style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                              color: white;
                              padding: 15px 40px;
                              border-radius: 10px;
                              text-decoration: none;
                              display: inline-block;
                              font-weight: 600;'>
                        Choisir un Plan
                    </a>
                </div>

                <p>Des questions ? Contactez-nous, nous sommes là pour vous aider !</p>

                <p>Cordialement,<br>L'équipe Gestion Comptable</p>
            "
        ]);

        return $this->sendEmail(
            $tenant_data['contact_email'],
            $tenant_data['contact_name'],
            $subject,
            $html_body
        );
    }

    /**
     * Email de suspension de compte
     */
    public function sendAccountSuspendedEmail($tenant_data, $reason = '') {
        $subject = "Votre compte a été suspendu";

        $reason_text = $reason ? "<p><strong>Raison :</strong> {$reason}</p>" : '';

        $html_body = $this->getTemplate([
            'title' => 'Compte Suspendu',
            'content' => "
                <p>Bonjour <strong>{$tenant_data['contact_name']}</strong>,</p>

                <p>Votre compte <strong>{$tenant_data['company_name']}</strong> a été temporairement suspendu.</p>

                {$reason_text}

                <p>Pour réactiver votre compte, veuillez nous contacter à <a href='mailto:support@gestioncomptable.local'>support@gestioncomptable.local</a></p>

                <p>Cordialement,<br>L'équipe Gestion Comptable</p>
            "
        ]);

        return $this->sendEmail(
            $tenant_data['contact_email'],
            $tenant_data['contact_name'],
            $subject,
            $html_body
        );
    }

    /**
     * Email de réactivation de compte
     */
    public function sendAccountReactivatedEmail($tenant_data) {
        $subject = "Votre compte a été réactivé";

        $html_body = $this->getTemplate([
            'title' => 'Compte Réactivé',
            'content' => "
                <p>Bonjour <strong>{$tenant_data['contact_name']}</strong>,</p>

                <p>Bonne nouvelle ! Votre compte <strong>{$tenant_data['company_name']}</strong> a été réactivé.</p>

                <p>Vous pouvez maintenant vous reconnecter et continuer à utiliser tous les services de Gestion Comptable.</p>

                <div style='text-align: center; margin: 30px 0;'>
                    <a href='http://localhost/gestion_comptable/login_tenant.php'
                       style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                              color: white;
                              padding: 15px 40px;
                              border-radius: 10px;
                              text-decoration: none;
                              display: inline-block;
                              font-weight: 600;'>
                        Se Connecter
                    </a>
                </div>

                <p>Merci de votre confiance !</p>

                <p>Cordialement,<br>L'équipe Gestion Comptable</p>
            "
        ]);

        return $this->sendEmail(
            $tenant_data['contact_email'],
            $tenant_data['contact_name'],
            $subject,
            $html_body
        );
    }

    /**
     * Template HTML de base
     */
    private function getTemplate($data) {
        $title = $data['title'] ?? 'Gestion Comptable';
        $content = $data['content'] ?? '';

        return "
        <!DOCTYPE html>
        <html lang='fr'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>{$title}</title>
        </head>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f7fa;'>
            <div style='max-width: 600px; margin: 40px auto; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
                <!-- Header -->
                <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px; text-align: center;'>
                    <h1 style='color: white; margin: 0; font-size: 2em;'>
                        💼 Gestion Comptable
                    </h1>
                    <p style='color: rgba(255,255,255,0.9); margin: 10px 0 0 0;'>{$title}</p>
                </div>

                <!-- Body -->
                <div style='padding: 40px;'>
                    {$content}
                </div>

                <!-- Footer -->
                <div style='background: #f7fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0;'>
                    <p style='color: #718096; font-size: 0.9em; margin: 0;'>
                        © " . date('Y') . " Gestion Comptable. Tous droits réservés.
                    </p>
                    <p style='color: #a0aec0; font-size: 0.85em; margin: 10px 0 0 0;'>
                        Cet email a été envoyé à votre adresse car vous utilisez Gestion Comptable.
                    </p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
}
?>
