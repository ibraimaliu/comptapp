<?php
// Configurations globales de l'application
define('APP_NAME', 'Gestion Comptable');
define('APP_URL', 'http://localhost/gestion_comptable'); // À modifier selon votre configuration
define('APP_ROOT', dirname(dirname(__FILE__)));

// Configuration des sessions avec nom unique pour éviter conflits
session_name('COMPTAPP_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fonction pour vérifier si l'utilisateur est connecté
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Fonction de redirection
function redirect($page) {
    header('Location: ' . APP_URL . '/' . $page);
    exit();
}

// ========== SÉCURITÉ CSRF ==========

/**
 * Générer un token CSRF
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Vérifier un token CSRF
 */
function verifyCSRFToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Obtenir un champ input CSRF caché
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Obtenir le token CSRF (pour AJAX)
 */
function csrfToken() {
    return generateCSRFToken();
}

// ========== MESSAGES FLASH ==========

/**
 * Définir un message flash
 */
function setFlash($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][$type] = $message;
}

/**
 * Obtenir et supprimer un message flash
 */
function getFlash($type) {
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

/**
 * Vérifier si un message flash existe
 */
function hasFlash($type) {
    return isset($_SESSION['flash'][$type]);
}

/**
 * Afficher un message flash HTML
 */
function displayFlash() {
    $html = '';
    $types = ['success', 'error', 'warning', 'info'];

    foreach ($types as $type) {
        if (hasFlash($type)) {
            $message = getFlash($type);
            $alertClass = $type === 'error' ? 'danger' : $type;
            $html .= '<div class="alert alert-' . $alertClass . ' alert-dismissible fade show" role="alert">';
            $html .= htmlspecialchars($message);
            $html .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
            $html .= '<span aria-hidden="true">&times;</span></button></div>';
        }
    }

    return $html;
}

// ========== VALIDATION ==========

/**
 * Valider un email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Valider une longueur minimale
 */
function validateMinLength($value, $min) {
    return strlen(trim($value)) >= $min;
}

/**
 * Valider une longueur maximale
 */
function validateMaxLength($value, $max) {
    return strlen(trim($value)) <= $max;
}

/**
 * Valider un champ requis
 */
function validateRequired($value) {
    return !empty(trim($value));
}

/**
 * Valider un montant (nombre positif)
 */
function validateAmount($amount) {
    return is_numeric($amount) && $amount >= 0;
}

/**
 * Valider une date au format Y-m-d
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

// ========== UTILITAIRES ==========

/**
 * Formater un montant pour affichage
 */
function formatAmount($amount, $decimals = 2) {
    return number_format($amount, $decimals, ',', ' ');
}

/**
 * Formater une date pour affichage
 */
function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    $d = new DateTime($date);
    return $d->format($format);
}

/**
 * Obtenir l'entreprise active
 */
function getActiveCompanyId() {
    return $_SESSION['company_id'] ?? null;
}

/**
 * Définir l'entreprise active
 */
function setActiveCompanyId($company_id) {
    $_SESSION['company_id'] = $company_id;
}

/**
 * Vérifier si une entreprise est sélectionnée
 */
function hasActiveCompany() {
    return isset($_SESSION['company_id']) && !empty($_SESSION['company_id']);
}

/**
 * Sanitize une chaîne HTML
 */
function sanitize($string) {
    return htmlspecialchars(strip_tags($string), ENT_QUOTES, 'UTF-8');
}

/**
 * Obtenir l'ID utilisateur connecté
 */
function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Obtenir le nom d'utilisateur connecté
 */
function getUsername() {
    return $_SESSION['username'] ?? null;
}

// ========== SÉCURITÉ SESSION ==========

/**
 * Configurer le timeout de session (en secondes)
 */
define('SESSION_TIMEOUT', 3600); // 1 heure

/**
 * Vérifier le timeout de session
 */
function checkSessionTimeout() {
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            return false;
        }
    }
    $_SESSION['last_activity'] = time();
    return true;
}

/**
 * Régénérer l'ID de session (protection contre session fixation)
 */
function regenerateSession() {
    session_regenerate_id(true);
}
?>