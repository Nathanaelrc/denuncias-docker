<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Configuración Principal
 */

if (!defined('DENUNCIAS_APP')) {
    define('DENUNCIAS_APP', true);
}

// =============================================
// CONFIGURACIÓN DE SESIÓN SEGURA
// =============================================
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// =============================================
// ZONA HORARIA
// =============================================
date_default_timezone_set('America/Santiago');

// =============================================
// MODO DE DESARROLLO / PRODUCCIÓN
// =============================================
$environment = strtolower(trim((string)(getenv('APP_ENV') ?: 'production')));
if (!in_array($environment, ['development', 'production'], true)) {
    $environment = 'production';
}
define('ENVIRONMENT', $environment);

if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/error.log');
}

// =============================================
// RUTAS
// =============================================
define('ROOT_PATH', dirname(__DIR__));
define('LOGS_PATH', ROOT_PATH . '/logs');

// Ruta base fija del portal Ley Karin
define('APP_BASE_PATH', '/karin');

// =============================================
// INFORMACIÓN DE LA APLICACIÓN
// =============================================
define('APP_NAME', 'Canal de Denuncias');

// =============================================
// ROLES
// =============================================
define('ROLE_ADMIN', 'admin');
define('ROLE_INVESTIGADOR', 'investigador');
define('ROLE_VIEWER', 'viewer');

// =============================================
// ESTADOS DE DENUNCIA
// =============================================
define('COMPLAINT_STATUSES', [
    'recibida'          => ['label' => 'Recibida',          'color' => 'primary',   'icon' => 'bi-inbox'],
    'en_investigacion'  => ['label' => 'En Investigación',  'color' => 'warning',   'icon' => 'bi-search'],
    'resuelta'          => ['label' => 'Resuelta',          'color' => 'success',   'icon' => 'bi-check-circle'],
    'desestimada'       => ['label' => 'Desestimada',       'color' => 'secondary', 'icon' => 'bi-x-circle'],
    'archivada'         => ['label' => 'Archivada',         'color' => 'dark',      'icon' => 'bi-archive']
]);

define('COMPLAINT_TYPES', [
    'acoso_laboral'      => ['label' => 'Acoso Laboral',      'icon' => 'bi-exclamation-triangle'],
    'acoso_sexual'       => ['label' => 'Acoso Sexual',       'icon' => 'bi-shield-exclamation'],
    'violencia_laboral'  => ['label' => 'Violencia Laboral',  'icon' => 'bi-lightning'],
    'discriminacion'     => ['label' => 'Discriminación',     'icon' => 'bi-people'],
    'represalia'         => ['label' => 'Represalia',         'icon' => 'bi-arrow-return-left'],
    'otro'               => ['label' => 'Otro',               'icon' => 'bi-question-circle']
]);

// =============================================
// CONFIGURACIÓN DE ARCHIVOS
// =============================================
define('MAX_UPLOAD_SIZE', 20 * 1024 * 1024); // 20 MB (alineado con upload_max_filesize en php.ini)
define('ALLOWED_FILE_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain'
]);

// =============================================
// SEGURIDAD
// =============================================
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_NAME', 'csrf_token');
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);

// =============================================
// FUNCIONES DE SEGURIDAD
// =============================================

function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfInput() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generateCsrfToken() . '">';
}

function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    // Rutas relativas al portal (ej. /panel → /karin/panel)
    if (str_starts_with($url, '/') && !str_starts_with($url, APP_BASE_PATH)) {
        $url = APP_BASE_PATH . $url;
    }
    header("Location: $url");
    exit;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

function logActivity($userId, $action, $entityType = null, $entityId = null, $details = '') {
    global $pdo;

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId ?: null, $action, $entityType, $entityId, $details, $ip, substr($userAgent, 0, 255)]);
        }
    } catch (Exception $e) {
        error_log("Error log auditoría: " . $e->getMessage());
    }

    $logFile = LOGS_PATH . '/activity_' . date('Y-m-d') . '.log';
    $logEntry = sprintf("[%s] User: %s | IP: %s | Action: %s | Entity: %s#%s | Details: %s\n",
        date('Y-m-d H:i:s'), $userId ?? 'guest', $ip, $action, $entityType ?? '-', $entityId ?? '-', $details);

    if (!is_dir(LOGS_PATH)) {
        mkdir(LOGS_PATH, 0755, true);
    }
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Versiones de dependencias CDN
require_once __DIR__ . '/versions.php';
