<?php
/**
 * Portal Denuncias Ciudadanas Empresa Portuaria Coquimbo - Configuración Principal
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
define('LOGS_PATH',    ROOT_PATH . '/logs');

// Ruta base fija del portal Ciudadano (Generales)
define('APP_BASE_PATH', '/generales');

// =============================================
// IDENTIDAD DEL PORTAL
// =============================================
define('APP_NAME', 'Canal de Denuncias');

// =============================================
// ROLES
// =============================================
define('ROLE_SUPERADMIN',   'superadmin');   // Ve y modifica denuncias + gestiona usuarios (con filtro de conflicto)
define('ROLE_ADMIN',        'admin');         // Solo gestión de usuarios, sin acceso a denuncias
define('ROLE_INVESTIGADOR', 'investigador'); // Investiga y modifica denuncias asignadas
define('ROLE_VIEWER',       'viewer');        // Solo lectura de denuncias (sin desencriptar datos sensibles)
define('ROLE_AUDITOR',      'auditor');       // Solo logs y registro de actividad

// Áreas habilitadas para investigadores del portal de denuncias generales
define('INVESTIGATION_AREAS', [
    'concesiones'    => 'Concesiones',
    'ingenieria'     => 'Ingeniería',
    'finanzas'       => 'Finanzas',
    'sostenibilidad' => 'Sostenibilidad',
]);

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
// TIPOS DE DENUNCIA (ámbito portuario Empresa Portuaria Coquimbo)
// =============================================
define('COMPLAINT_TYPES', [
    'operaciones'       => ['label' => 'Operaciones Portuarias',        'icon' => 'bi-box-seam',            'ley' => 'Ley N° 19.542',                                           'descripcion' => 'Hechos relacionados con seguridad operacional, navegación, control de accesos, maniobras, circulación interna, prevención de contaminación o incumplimientos vinculados a la normativa marítima y portuaria aplicable.'],
    'medioambiente'     => ['label' => 'Medio Ambiente y Contaminación','icon' => 'bi-water',               'ley' => 'Ley N° 20.417 / Ley N° 19.300',                        'descripcion' => 'Contaminación del mar, suelo o aire, derrames, emisiones, residuos o cualquier situación que pueda generar afectación ambiental y eventualmente ser fiscalizada por la Superintendencia del Medio Ambiente.'],
    'seguridad'         => ['label' => 'Seguridad y Emergencias',       'icon' => 'bi-shield-exclamation',  'ley' => 'Ley N° 16.744 / DS N° 594 / Ley N° 21.012',            'descripcion' => 'Incumplimientos en condiciones de seguridad, higiene, prevención de riesgos, emergencias, protección de trabajadores, visitantes o terceros dentro del entorno portuario.'],
    'contratos'         => ['label' => 'Concesiones, Contratos y Bases','icon' => 'bi-file-earmark-text',   'ley' => 'Contrato de Concesión y Bases de Licitación',          'descripcion' => 'Situaciones asociadas al cumplimiento de obligaciones concesionales, bases de licitación o deberes operativos frente a terceros. Las controversias contractuales directas entre EPCO y sus concesionarios quedan excluidas de este canal.'],
    'corrupcion'        => ['label' => 'Responsabilidad penal de las personas jurídicas', 'icon' => 'bi-building-exclamation', 'ley' => 'Ley N° 20.393 / Ley N° 21.595 / Ley N° 21.121', 'descripcion' => 'Delitos de distintas categorías vinculados a la empresa portuaria o a sus funcionarios, e incumplimiento del Modelo de Prevención de Delitos.'],
    'impacto_comunidad' => ['label' => 'Impacto en la Comunidad',       'icon' => 'bi-people',              'ley' => 'Ley N° 19.300 / Normativa sectorial aplicable',        'descripcion' => 'Afectaciones a vecinos, usuarios y comunidades del entorno portuario, tales como ruido, polvo, tránsito, señalización deficiente, daños a terceros o impactos relevantes sobre el entorno inmediato.'],
    'servicios'         => ['label' => 'Atención y Servicios',          'icon' => 'bi-person-check',        'ley' => 'Ley N° 19.880 / Principios de servicio público',       'descripcion' => 'Deficiencias en atención, trato impropio, demoras injustificadas, desinformación, obstáculos para la gestión de usuarios o respuestas administrativas improcedentes.'],
    'infraestructura'   => ['label' => 'Infraestructura y Obras',       'icon' => 'bi-tools',               'ley' => 'DS N° 594 / Ley N° 16.744 / Normativa técnica aplicable', 'descripcion' => 'Fallas constructivas, instalaciones en mal estado, riesgos físicos derivados de obras o infraestructura portuaria y omisiones que puedan afectar la seguridad operacional o sanitaria.'],
    'codigo_conducta'   => ['label' => 'Código de Conducta y Reglamentos Internos', 'icon' => 'bi-journal-bookmark-fill', 'ley' => 'Reglamento Interno / Código de Conducta EPCO', 'descripcion' => 'Incumplimientos del Código de Conducta institucional, Reglamentos Internos de Orden, Higiene y Seguridad u otras normas internas que regulan el comportamiento de los funcionarios y colaboradores.'],
    'rufa_manual'       => ['label' => 'RUFA y/o Manual de servicios del Concesionario', 'icon' => 'bi-file-ruled', 'ley' => 'RUFA / Manual de Servicios del Concesionario', 'descripcion' => 'Situaciones de incumplimiento del Reglamento de Uso y Funcionamiento del Área (RUFA) o del Manual de Servicios del Concesionario en la operación dentro del recinto portuario.'],
    'otro'              => ['label' => 'Otro',                          'icon' => 'bi-question-circle',     'ley' => 'Otras normas aplicables',                              'descripcion' => 'Cualquier otra situación irregular no clasificada en las categorías anteriores. Describe los hechos con el mayor detalle posible para orientar su revisión.'],
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
    // Rutas relativas al portal (ej. /panel → /generales/panel)
    if (str_starts_with($url, '/') && !str_starts_with($url, APP_BASE_PATH)) {
        $url = APP_BASE_PATH . $url;
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

// Versiones de dependencias CDN
require_once __DIR__ . '/versions.php';
