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
define('APP_NAME',      'Canal de Denuncias');
define('APP_FULL_NAME', 'Canal de Denuncias - EPCO');
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
// TIPOS DE DENUNCIA (ámbito portuario EPCO)
// =============================================
define('COMPLAINT_TYPES', [
    'operaciones'       => ['label' => 'Operaciones Portuarias',        'icon' => 'bi-box-seam',            'ley' => 'Ley N° 18.696 / DFL N° 340'],
    'medioambiente'     => ['label' => 'Medio Ambiente y Contaminación','icon' => 'bi-water',               'ley' => 'Ley N° 19.300'],
    'seguridad'         => ['label' => 'Seguridad en Recintos',         'icon' => 'bi-shield-exclamation',  'ley' => 'DS N° 90 / Normativa ISPS'],
    'contratos'         => ['label' => 'Contratos y Licitaciones',      'icon' => 'bi-file-earmark-text',   'ley' => 'Ley N° 19.886'],
    'corrupcion'        => ['label' => 'Corrupción o Fraude',           'icon' => 'bi-cash-coin',           'ley' => 'Ley N° 20.393'],
    'impacto_comunidad' => ['label' => 'Impacto en la Comunidad',       'icon' => 'bi-people',              'ley' => 'Ley N° 19.300 / Ley N° 20.936'],
    'servicios'         => ['label' => 'Servicios al Usuario',          'icon' => 'bi-person-check',        'ley' => 'Ley N° 19.880'],
    'infraestructura'   => ['label' => 'Infraestructura y Obras',       'icon' => 'bi-tools',               'ley' => 'Ley N° 19.886'],
    'otro'              => ['label' => 'Otro',                          'icon' => 'bi-question-circle',     'ley' => 'General'],
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
