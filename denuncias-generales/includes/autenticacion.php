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

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmtIp = $pdo->prepare(
        "SELECT COUNT(*) FROM activity_logs WHERE action = 'login_fallido' AND ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
    );
    $stmtIp->execute([$ip]);
    if ((int)$stmtIp->fetchColumn() >= 10) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) return false;

    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
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

        return true;
    }

    $attempts    = $user['login_attempts'] + 1;
    $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS
        ? date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME)
        : null;
    $pdo->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?')
        ->execute([$attempts, $lockedUntil, $user['id']]);

    return false;
}

function logout() {
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

    if ($role === ROLE_INVESTIGADOR) {
        return '/panel';
    }

    if ($role === ROLE_ADMIN) {
        return '/admin_usuarios';
    }

    return '/notificaciones';
}

function canAccessComplaints(?array $user = null): bool {
    if ($user !== null) {
        return ($user['role'] ?? null) === ROLE_INVESTIGADOR;
    }

    return hasRole([ROLE_INVESTIGADOR]);
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
        redirect($redirect ?? getDefaultAuthenticatedPath(), 'Solo los investigadores pueden acceder a las denuncias.', 'danger');
    }
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;

    $stmt = $pdo->prepare('SELECT id, name, username, email, role, department, position FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function canDecrypt(?array $user = null) {
    return canAccessComplaints($user);
}
