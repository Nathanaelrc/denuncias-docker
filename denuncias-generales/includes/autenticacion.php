<?php
/**
 * Portal Denuncias Ciudadanas - Funciones de autenticación
 */

if (!isset($pdo)) {
    if (!defined('GENERALES_APP')) {
        define('GENERALES_APP', true);
    }
    require_once __DIR__ . '/../config/app.php';
    require_once __DIR__ . '/../config/database.php';
}

function login($identifier, $password) {
    global $pdo;

    // Bloqueo por IP: máx. 10 intentos fallidos en 15 minutos
    $ip      = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = 'rl_ip_' . hash('sha256', $ip);
    $ipBlocked = false;
    try {
        $stmtIp = $pdo->prepare(
            "SELECT COUNT(*) FROM activity_logs WHERE action = 'login_fallido' AND ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
        $stmtIp->execute([$ip]);
        if ((int)$stmtIp->fetchColumn() >= 10) {
            $ipBlocked = true;
        }
    } catch (Exception $e) {
        // Fallback a contador de sesión cuando la BD no responde
        $rlAttempts = (int)($_SESSION[$rateKey . '_count'] ?? 0);
        $rlTime     = (int)($_SESSION[$rateKey . '_time']  ?? 0);
        if ((time() - $rlTime) > 900) { $rlAttempts = 0; }
        if ($rlAttempts >= 10) { $ipBlocked = true; }
    }
    if ($ipBlocked) {
        log_auth('LOGIN_ATTEMPT', $identifier, false, 'IP blocked - too many failed attempts');
        return false;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) {
        log_auth('LOGIN_ATTEMPT', $identifier, false, 'User not found or inactive');
        return false;
    }

    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        log_auth('LOGIN_ATTEMPT', $identifier, false, 'User account locked');
        return false;
    }

    if (password_verify($password, $user['password'])) {
        $pdo->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?')
            ->execute([$user['id']]);

        // Prevenir Session Fixation: regenerar ID antes de escribir datos de sesión
        session_regenerate_id(true);

        $_SESSION['user_id']             = $user['id'];
        $_SESSION['user_name']           = $user['name'];
        $_SESSION['user_username']       = $user['username'];
        $_SESSION['user_email']          = $user['email'];
        $_SESSION['user_role']           = $user['role'];
        $_SESSION['logged_in']           = true;
        $_SESSION['must_change_password'] = (bool)($user['must_change_password'] ?? false);

        log_auth('LOGIN_SUCCESS', $identifier, true, 'User: ' . $user['name']);
        return true;
    }

    $attempts    = $user['login_attempts'] + 1;
    $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS
        ? date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME)
        : null;
    $pdo->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?')
        ->execute([$attempts, $lockedUntil, $user['id']]);

    log_auth('LOGIN_FAILED', $identifier, false, 'Invalid password (attempt ' . ($attempts + 1) . ')');
    return false;
}

function logout() {
    // Log logout event before clearing session
    if (isset($_SESSION['user_email'])) {
        log_auth('LOGOUT', $_SESSION['user_email'], true);
    }
    // Limpiar datos de sesión
    $_SESSION = [];
    // Eliminar la cookie de sesión del navegador
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(), '',
            time() - 42000,
            $params['path'], $params['domain'],
            $params['secure'], $params['httponly']
        );
    }
    session_destroy();
}

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function hasRole($roles) {
    if (!isLoggedIn()) return false;
    if (is_string($roles)) $roles = [$roles];
    return in_array($_SESSION['user_role'], $roles);
}

function requireAuth($redirect = '/iniciar_sesion') {
    if (!isLoggedIn()) {
        header('Location: ' . APP_BASE_PATH . $redirect);
        exit;
    }
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    if (!empty($_SESSION['must_change_password']) && !in_array($currentPage, ['cambiar_contrasena', 'cerrar_sesion'], true)) {
        header('Location: ' . APP_BASE_PATH . '/cambiar_contrasena');
        exit;
    }
}

function getDefaultAuthenticatedPath(?array $user = null): string {
    $role = $user['role'] ?? getUserRole();

    if ($role === ROLE_SUPERADMIN || $role === ROLE_INVESTIGADOR) {
        return '/panel';
    }
    if ($role === ROLE_VIEWER) {
        return '/panel';
    }
    if ($role === ROLE_ADMIN) {
        return '/admin_usuarios';
    }
    if ($role === ROLE_AUDITOR) {
        return '/registro_actividad';
    }

    return '/notificaciones';
}

/** Puede VER denuncias (superadmin, investigador, viewer) */
function canAccessComplaints(?array $user = null): bool {
    $role = $user !== null ? ($user['role'] ?? null) : getUserRole();
    return in_array($role, [ROLE_SUPERADMIN, ROLE_INVESTIGADOR, ROLE_VIEWER], true);
}

/** Puede MODIFICAR denuncias (superadmin, investigador) */
function canModifyComplaints(?array $user = null): bool {
    $role = $user !== null ? ($user['role'] ?? null) : getUserRole();
    return in_array($role, [ROLE_SUPERADMIN, ROLE_INVESTIGADOR], true);
}

/** Puede ELIMINAR denuncias (solo superadmin) */
function canDeleteComplaints(?array $user = null): bool {
    $role = $user !== null ? ($user['role'] ?? null) : getUserRole();
    return $role === ROLE_SUPERADMIN;
}

/** Puede gestionar usuarios (superadmin, admin) */
function canManageUsers(?array $user = null): bool {
    $role = $user !== null ? ($user['role'] ?? null) : getUserRole();
    return in_array($role, [ROLE_SUPERADMIN, ROLE_ADMIN], true);
}

/** Puede ver logs y registro de actividad (superadmin, admin, auditor) */
function canViewAuditLogs(?array $user = null): bool {
    $role = $user !== null ? ($user['role'] ?? null) : getUserRole();
    return in_array($role, [ROLE_SUPERADMIN, ROLE_ADMIN, ROLE_AUDITOR], true);
}

function requireRole($roles, $redirect = null) {
    requireAuth();
    if (!hasRole($roles)) {
        redirect($redirect ?? getDefaultAuthenticatedPath(), 'No tienes permisos para acceder a esta sección.', 'danger');
    }
}

function requireComplaintAccess($redirect = null): void {
    requireAuth();
    if (!canAccessComplaints()) {
        redirect($redirect ?? getDefaultAuthenticatedPath(), 'No tienes permisos para acceder a las denuncias.', 'danger');
    }
}

function requireComplaintModify($redirect = null): void {
    requireAuth();
    if (!canModifyComplaints()) {
        redirect($redirect ?? getDefaultAuthenticatedPath(), 'No tienes permisos para modificar denuncias.', 'danger');
    }
}

function requireUserManagement($redirect = null): void {
    requireAuth();
    if (!canManageUsers()) {
        redirect($redirect ?? getDefaultAuthenticatedPath(), 'No tienes permisos para gestionar usuarios.', 'danger');
    }
}

function requireAuditAccess($redirect = null): void {
    requireAuth();
    if (!canViewAuditLogs()) {
        redirect($redirect ?? getDefaultAuthenticatedPath(), 'No tienes permisos para ver el registro de actividad.', 'danger');
    }
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;

    $hasInvestigatorArea = function_exists('dbColumnExists') && dbColumnExists('users', 'investigator_area');
    $sql = $hasInvestigatorArea
        ? 'SELECT id, name, username, email, role, department, position, investigator_area FROM users WHERE id = ?'
        : 'SELECT id, name, username, email, role, department, position, NULL AS investigator_area FROM users WHERE id = ?';

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Verificar si el usuario puede desencriptar datos sensibles de denuncias.
 * Solo superadmin e investigadores. Viewer solo ve datos no sensibles.
 */
function canDecrypt(?array $user = null) {
    $role = $user !== null ? ($user['role'] ?? null) : getUserRole();
    return in_array($role, [ROLE_SUPERADMIN, ROLE_INVESTIGADOR], true);
}
