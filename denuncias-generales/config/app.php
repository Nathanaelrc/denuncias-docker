<?php
/**
 * Portal Denuncias Ciudadanas EPCO - Configuración Principal
 */

if (!defined('GENERALES_APP')) {
    define('GENERALES_APP', true);
}

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => isset($_SERVER['HTTPS']),
        'httponly' => true, 'samesite' => 'Strict'
    ]);
    session_start();
}

if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) {
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

date_default_timezone_set('America/Santiago');

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

define('ROOT_PATH',    dirname(__DIR__));
define('CONFIG_PATH',  ROOT_PATH . '/config');
define('INCLUDES_PATH',ROOT_PATH . '/includes');
define('PUBLIC_PATH',  ROOT_PATH . '/public');
define('UPLOADS_PATH', ROOT_PATH . '/public/uploads');
define('LOGS_PATH',    ROOT_PATH . '/logs');

define('BASE_URL',    '/');
define('UPLOADS_URL', '/uploads/');

// =============================================
// IDENTIDAD DEL PORTAL
// =============================================
define('APP_NAME',      'Denuncias Ciudadanas');
define('APP_FULL_NAME', 'Portal Ciudadano de Denuncias - EPCO');
define('APP_VERSION',   '1.0.0');
define('APP_COMPANY',   'Empresa Portuaria Coquimbo');

// =============================================
// COLORES (verde institucional)
// =============================================
define('PRIMARY_COLOR',   '#1a6591');
define('SECONDARY_COLOR', '#ffffff');
define('ACCENT_COLOR',    '#2380b0');
define('DANGER_COLOR',    '#dc2626');
define('SUCCESS_COLOR',   '#2380b0');
define('WARNING_COLOR',   '#f59e0b');

// =============================================
// ROLES
// =============================================
define('ROLE_ADMIN',       'admin');
define('ROLE_INVESTIGADOR','investigador');
define('ROLE_VIEWER',      'viewer');

// =============================================
// ESTADOS DE DENUNCIA
// =============================================
define('COMPLAINT_STATUSES', [
    'recibida'         => ['label' => 'Recibida',          'color' => 'primary',   'icon' => 'bi-inbox'],
    'en_investigacion' => ['label' => 'En Revisión',       'color' => 'warning',   'icon' => 'bi-search'],
    'resuelta'         => ['label' => 'Resuelta',          'color' => 'success',   'icon' => 'bi-check-circle'],
    'desestimada'      => ['label' => 'Desestimada',       'color' => 'secondary', 'icon' => 'bi-x-circle'],
    'archivada'        => ['label' => 'Archivada',         'color' => 'dark',      'icon' => 'bi-archive'],
]);

// =============================================
// TIPOS DE DENUNCIA (legislación chilena general)
// =============================================
define('COMPLAINT_TYPES', [
    'consumidor'        => ['label' => 'Protección al Consumidor',  'icon' => 'bi-bag-x',            'ley' => 'Ley N° 19.496'],
    'servicios_basicos' => ['label' => 'Servicios Básicos',         'icon' => 'bi-lightning-charge', 'ley' => 'DFL N° 382 / Ley 18.168'],
    'salud'             => ['label' => 'Servicios de Salud',        'icon' => 'bi-heart-pulse',      'ley' => 'Ley N° 19.966'],
    'transporte'        => ['label' => 'Transporte y Movilidad',    'icon' => 'bi-truck',            'ley' => 'Ley N° 18.290'],
    'municipal'         => ['label' => 'Servicios Municipales',     'icon' => 'bi-buildings',        'ley' => 'Ley N° 18.695'],
    'sector_publico'    => ['label' => 'Sector Público / Estado',   'icon' => 'bi-bank',             'ley' => 'Ley N° 19.880'],
    'medioambiente'     => ['label' => 'Medio Ambiente',            'icon' => 'bi-tree',             'ley' => 'Ley N° 19.300'],
    'financiero'        => ['label' => 'Servicios Financieros',     'icon' => 'bi-credit-card',      'ley' => 'DFL N° 3 / CMF'],
    'educacion'         => ['label' => 'Educación',                 'icon' => 'bi-mortarboard',      'ley' => 'Ley N° 20.529'],
    'inmobiliario'      => ['label' => 'Vivienda y Construcción',   'icon' => 'bi-house',            'ley' => 'Ley N° 20.703'],
    'otro'              => ['label' => 'Otro',                      'icon' => 'bi-question-circle',  'ley' => 'General'],
]);

// =============================================
// ARCHIVOS
// =============================================
define('MAX_UPLOAD_SIZE',    20 * 1024 * 1024);
define('ALLOWED_FILE_TYPES', [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf', 'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'text/plain'
]);

// =============================================
// SEGURIDAD
// =============================================
define('PASSWORD_MIN_LENGTH', 8);
define('CSRF_TOKEN_NAME',     'csrf_token');
define('MAX_LOGIN_ATTEMPTS',  5);
define('LOGIN_LOCKOUT_TIME',  900);

// =============================================
// FUNCIONES DE SEGURIDAD / UTILIDADES GLOBALES
// =============================================
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrfToken($token) {
    if (empty($_SESSION[CSRF_TOKEN_NAME]) || empty($token)) return false;
    return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfInput() {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generateCsrfToken() . '">';
}

function sanitize($data) {
    if (is_array($data)) return array_map('sanitize', $data);
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function redirect($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type']    = $type;
    }
    header("Location: $url");
    exit;
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $msg  = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'info';
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        return ['message' => $msg, 'type' => $type];
    }
    return null;
}

function logActivity($userId, $action, $entityType = null, $entityId = null, $details = '') {
    global $pdo;
    $ip        = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$userId ?: null, $action, $entityType, $entityId, $details, $ip, substr($userAgent, 0, 255)]);
        }
    } catch (Exception $e) {
        error_log("Error log auditoría: " . $e->getMessage());
    }
}
