<?php
/**
 * Modèle QRInvoice
 * Gestion des QR-factures suisses conformes à la norme ISO 20022
 *
 * @author Gestion Comptable
 * @version 1.0
 * @since 2024
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;

class QRInvoice {
    private $conn;
    private $table_name = "invoices";

    // Propriétés QR
    public $qr_iban;
    public $qr_reference;
    public $creditor_name;
    public $creditor_address;
    public $creditor_postal_code;
    public $creditor_city;
    public $creditor_country;
    public $amount;
    public $currency;
    public $debtor_name;
    public $debtor_address;
    public $debtor_postal_code;
    public $debtor_city;
    public $debtor_country;
    public $additional_info;

    public function __construct($db) {
        $this->conn = $db;
    }

    /**
     * Générer une référence QRR (QR Reference) avec checksum
     * Format: 27 caractères numériques avec checksum modulo 10 récursif
     *
     * @param string $invoice_number Numéro de facture
     * @param int $company_id ID de la société
     * @return string Référence QRR complète (27 caractères)
     */
    public function generateQRReference($invoice_number, $company_id) {
        // Extraire uniquement les chiffres du numéro de facture
        $invoice_digits = preg_replace('/[^0-9]/', '', $invoice_number);

        // Construire la référence de base (26 chiffres, sans checksum)
        // Format: CCCCC IIIII IIIII IIIII IIIII I
        // C = Company ID (5 digits)
        // I = Invoice number (21 digits)

        $company_part = str_pad($company_id, 5, '0', STR_PAD_LEFT);
        $invoice_part = str_pad($invoice_digits, 21, '0', STR_PAD_LEFT);

        $reference_base = $company_part . $invoice_part;

        // Calculer le checksum
        $checksum = $this->calculateQRRChecksum($reference_base);

        // Référence complète (27 caractères)
        $qr_reference = $reference_base . $checksum;

        return $qr_reference;
    }

    /**
     * Calculer le checksum modulo 10 récursif pour QRR
     * Algorithme conforme à la norme ISO 7064
     *
     * @param string $reference Référence de 26 chiffres
     * @return int Checksum (1 chiffre)
     */
    public function calculateQRRChecksum($reference) {
        $table = [0, 9, 4, 6, 8, 2, 7, 1, 3, 5];
        $carry = 0;

        for ($i = 0; $i < strlen($reference); $i++) {
            $digit = (int)$reference[$i];
            $carry = $table[($carry + $digit) % 10];
        }

        return (10 - $carry) % 10;
    }

    /**
     * Valider un IBAN suisse (QR-IBAN ou IBAN classique)
     *
     * @param string $iban IBAN à valider
     * @return bool True si valide, False sinon
     */
    public function validateIBAN($iban) {
        // Enlever les espaces
        $iban = str_replace(' ', '', $iban);

        // Vérifier la longueur (IBAN suisse = 21 caractères)
        if (strlen($iban) != 21) {
            return false;
        }

        // Vérifier le code pays (CH)
        if (substr($iban, 0, 2) != 'CH') {
            return false;
        }

        // Réorganiser pour le calcul du checksum
        // Déplacer les 4 premiers caractères à la fin
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Remplacer les lettres par des chiffres (A=10, B=11, ..., Z=35)
        $numeric = '';
        for ($i = 0; $i < strlen($rearranged); $i++) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numeric .= (ord($char) - ord('A') + 10);
            } else {
                $numeric .= $char;
            }
        }

        // Calcul modulo 97
        $checksum = bcmod($numeric, '97');

        return $checksum == 1;
    }

    /**
     * Vérifier si un IBAN est un QR-IBAN
     * QR-IBAN: positions 5-9 entre 30000 et 31999
     *
     * @param string $iban IBAN à vérifier
     * @return bool True si QR-IBAN, False sinon
     */
    public function isQRIBAN($iban) {
        $iban = str_replace(' ', '', $iban);

        if (strlen($iban) != 21) {
            return false;
        }

        // Extraire l'IID (positions 5-9)
        $iid = (int)substr($iban, 4, 5);

        // QR-IBAN si IID entre 30000 et 31999
        return ($iid >= 30000 && $iid <= 31999);
    }

    /**
     * Générer le contenu du QR-Code selon la norme Swiss QR Code
     *
     * @param array $invoice_data Données de la facture
     * @param array $company_data Données de la société
     * @param array $client_data Données du client
     * @return string Contenu du QR-Code formaté
     */
    public function generateQRContent($invoice_data, $company_data, $client_data) {
        $qr_lines = [];

        // 1. Header
        $qr_lines[] = 'SPC';                    // QRType
        $qr_lines[] = '0200';                   // Version
        $qr_lines[] = '1';                      // Coding (1 = UTF-8)

        // 2. Creditor Information (Bénéficiaire)
        $qr_iban = $company_data['qr_iban'] ?? $company_data['bank_iban'] ?? '';
        $qr_lines[] = $qr_iban;                 // IBAN

        // Creditor (structured address)
        $qr_lines[] = 'S';                      // Address Type (S = structured, K = combined)
        $qr_lines[] = $company_data['name'];   // Name
        $qr_lines[] = $company_data['address'] ?? ''; // Street
        $qr_lines[] = '';                       // Building number (optional)
        $qr_lines[] = $company_data['postal_code'] ?? ''; // Postal code
        $qr_lines[] = $company_data['city'] ?? ''; // City
        $qr_lines[] = $company_data['country'] ?? 'CH'; // Country

        // 3. Ultimate Creditor (empty - optional)
        $qr_lines[] = '';                       // Address Type
        $qr_lines[] = '';                       // Name
        $qr_lines[] = '';                       // Street
        $qr_lines[] = '';                       // Building number
        $qr_lines[] = '';                       // Postal code
        $qr_lines[] = '';                       // City
        $qr_lines[] = '';                       // Country

        // 4. Payment Amount Information
        $amount = number_format($invoice_data['total'], 2, '.', '');
        $qr_lines[] = $amount;                  // Amount
        $qr_lines[] = $invoice_data['currency'] ?? 'CHF'; // Currency

        // 5. Ultimate Debtor (Débiteur final - Client)
        $qr_lines[] = 'S';                      // Address Type
        $qr_lines[] = $client_data['name'] ?? ''; // Name
        $qr_lines[] = $client_data['address'] ?? ''; // Street
        $qr_lines[] = '';                       // Building number
        $qr_lines[] = $client_data['postal_code'] ?? ''; // Postal code
        $qr_lines[] = $client_data['city'] ?? ''; // City
        $qr_lines[] = $client_data['country'] ?? 'CH'; // Country

        // 6. Payment Reference
        $is_qr_iban = $this->isQRIBAN($qr_iban);

        if ($is_qr_iban && !empty($invoice_data['qr_reference'])) {
            $qr_lines[] = 'QRR';                // Reference Type (QRR = QR Reference)
            $qr_lines[] = $invoice_data['qr_reference']; // Reference
        } else if (!empty($invoice_data['number'])) {
            // SCOR (Creditor Reference ISO 11649) ou NON (sans référence)
            $qr_lines[] = 'NON';                // Reference Type
            $qr_lines[] = '';                   // Reference (empty)
        } else {
            $qr_lines[] = 'NON';
            $qr_lines[] = '';
        }

        // 7. Additional Information
        $payment_terms = $invoice_data['payment_terms'] ?? 'Payable dans les 30 jours';
        $qr_lines[] = $payment_terms;          // Unstructured message
        $qr_lines[] = 'EPD';                    // Bill information (structured message)

        // 8. Alternative Procedures (max 2)
        $qr_lines[] = '';                       // Alternative procedure 1
        $qr_lines[] = '';                       // Alternative procedure 2

        // Joindre avec retour à la ligne
        return implode("\r\n", $qr_lines);
    }

    /**
     * Générer le QR-Code et le sauvegarder
     *
     * @param int $invoice_id ID de la facture
     * @param int $company_id ID de la société
     * @return string|false Chemin du fichier QR-Code ou false en cas d'erreur
     */
    public function generateQRCode($invoice_id, $company_id) {
        try {
            // Récupérer les données de la facture
            require_once __DIR__ . '/Invoice.php';
            $invoice_model = new Invoice($this->conn);
            $invoice_model->id = $invoice_id;
            $invoice_model->read();

            $invoice_data = [
                'id' => $invoice_model->id,
                'number' => $invoice_model->number,
                'total' => $invoice_model->total,
                'currency' => 'CHF',
                'qr_reference' => $invoice_model->qr_reference ?? '',
                'payment_terms' => $invoice_model->payment_terms ?? 'Payable dans les 30 jours'
            ];

            // Récupérer les données de la société
            require_once __DIR__ . '/Company.php';
            $company_model = new Company($this->conn);
            $company_model->id = $company_id;
            $company_model->read();

            $company_data = [
                'name' => $company_model->name,
                'qr_iban' => $company_model->qr_iban ?? '',
                'bank_iban' => $company_model->bank_iban ?? '',
                'address' => $company_model->address ?? '',
                'postal_code' => $company_model->postal_code ?? '',
                'city' => $company_model->city ?? '',
                'country' => $company_model->country ?? 'CH'
            ];

            // Récupérer les données du client
            require_once __DIR__ . '/Contact.php';
            $contact_model = new Contact($this->conn);
            $client_data = $contact_model->getById($invoice_model->client_id, $company_id);

            if (!$client_data) {
                error_log("QRInvoice: Client non trouvé pour invoice_id=$invoice_id");
                $client_data = [
                    'name' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'country' => 'CH'
                ];
            }

            // Générer le contenu du QR-Code
            $qr_content = $this->generateQRContent($invoice_data, $company_data, $client_data);

            // Créer le QR-Code
            $qrCode = new QrCode($qr_content);
            $qrCode->setEncoding(new Encoding('UTF-8'));
            $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium());
            $qrCode->setSize(300); // Taille en pixels
            $qrCode->setMargin(10);
            $qrCode->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

            // Générer l'image PNG
            $writer = new PngWriter();
            $result = $writer->write($qrCode);

            // Créer le dossier qr_codes s'il n'existe pas
            $qr_dir = __DIR__ . '/../uploads/qr_codes';
            if (!is_dir($qr_dir)) {
                mkdir($qr_dir, 0755, true);
            }

            // Nom du fichier
            $filename = 'qr_invoice_' . $invoice_id . '_' . time() . '.png';
            $filepath = $qr_dir . '/' . $filename;

            // Sauvegarder le fichier
            $result->saveToFile($filepath);

            // Mettre à jour le chemin dans la base de données
            $query = "UPDATE " . $this->table_name . "
                      SET qr_code_path = :qr_code_path
                      WHERE id = :invoice_id";
            $stmt = $this->conn->prepare($query);
            $relative_path = 'uploads/qr_codes/' . $filename;
            $stmt->bindParam(':qr_code_path', $relative_path);
            $stmt->bindParam(':invoice_id', $invoice_id);
            $stmt->execute();

            // Logger la génération
            $this->logQRInvoiceGeneration($invoice_id, $company_id, $invoice_data, $company_data, $relative_path);

            return $relative_path;

        } catch (Exception $e) {
            error_log("QRInvoice::generateQRCode Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Logger la génération d'une QR-facture
     *
     * @param int $invoice_id ID de la facture
     * @param int $company_id ID de la société
     * @param array $invoice_data Données de la facture
     * @param array $company_data Données de la société
     * @param string $pdf_path Chemin du PDF
     * @return bool
     */
    private function logQRInvoiceGeneration($invoice_id, $company_id, $invoice_data, $company_data, $pdf_path) {
        $query = "INSERT INTO qr_invoice_log
                  (invoice_id, company_id, qr_reference, qr_iban, amount, currency, pdf_path)
                  VALUES (:invoice_id, :company_id, :qr_reference, :qr_iban, :amount, :currency, :pdf_path)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':invoice_id', $invoice_id);
        $stmt->bindParam(':company_id', $company_id);

        $qr_ref = $invoice_data['qr_reference'] ?? '';
        $stmt->bindParam(':qr_reference', $qr_ref);

        $qr_iban = $company_data['qr_iban'] ?? $company_data['bank_iban'] ?? '';
        $stmt->bindParam(':qr_iban', $qr_iban);

        $amount = $invoice_data['total'];
        $stmt->bindParam(':amount', $amount);

        $currency = $invoice_data['currency'];
        $stmt->bindParam(':currency', $currency);
        $stmt->bindParam(':pdf_path', $pdf_path);

        return $stmt->execute();
    }

    /**
     * Formater une référence QRR pour l'affichage (avec espaces)
     * Format: 00 00000 00000 00000 00000 00000 0
     *
     * @param string $reference Référence QRR (27 chiffres)
     * @return string Référence formatée
     */
    public function formatQRReference($reference) {
        if (strlen($reference) != 27) {
            return $reference;
        }

        // Format: 2 + 5 + 5 + 5 + 5 + 5
        return substr($reference, 0, 2) . ' ' .
               substr($reference, 2, 5) . ' ' .
               substr($reference, 7, 5) . ' ' .
               substr($reference, 12, 5) . ' ' .
               substr($reference, 17, 5) . ' ' .
               substr($reference, 22, 5);
    }

    /**
     * Formater un IBAN pour l'affichage (avec espaces)
     * Format: CH00 0000 0000 0000 0000 0
     *
     * @param string $iban IBAN (21 caractères)
     * @return string IBAN formaté
     */
    public function formatIBAN($iban) {
        $iban = str_replace(' ', '', $iban);

        if (strlen($iban) != 21) {
            return $iban;
        }

        // Format: 4 + 4 + 4 + 4 + 4 + 1
        return substr($iban, 0, 4) . ' ' .
               substr($iban, 4, 4) . ' ' .
               substr($iban, 8, 4) . ' ' .
               substr($iban, 12, 4) . ' ' .
               substr($iban, 16, 4) . ' ' .
               substr($iban, 20, 1);
    }
}
?>
