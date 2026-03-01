<?php
/**
 * Portal de Denuncias - Funciones de autenticación
 */

if (!isset($pdo)) {
    if (!defined('DENUNCIAS_APP')) {
        define('DENUNCIAS_APP', true);
    }
    require_once __DIR__ . '/../config/app.php';
    require_once __DIR__ . '/../config/database.php';
}

function login($identifier, $password) {
    global $pdo;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE (email = ? OR username = ?) AND is_active = 1');
    $stmt->execute([$identifier, $identifier]);
    $user = $stmt->fetch();

    if (!$user) return false;

    // Verificar bloqueo
    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
        return false;
    }

    if (password_verify($password, $user['password'])) {
        // Reset intentos
        $pdo->prepare('UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?')
            ->execute([$user['id']]);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_username'] = $user['username'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['logged_in'] = true;

        return true;
    }

    // Incrementar intentos
    $attempts = $user['login_attempts'] + 1;
    $lockedUntil = $attempts >= MAX_LOGIN_ATTEMPTS
        ? date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_TIME)
        : null;
    $pdo->prepare('UPDATE users SET login_attempts = ?, locked_until = ? WHERE id = ?')
        ->execute([$attempts, $lockedUntil, $user['id']]);

    return false;
}

function logout() {
    session_destroy();
    session_start();
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
        header("Location: $redirect");
        exit;
    }
}

function requireRole($roles, $redirect = '/') {
    requireAuth();
    if (!hasRole($roles)) {
        header("Location: $redirect");
        exit;
    }
}

function getCurrentUser() {
    global $pdo;
    if (!isLoggedIn()) return null;

    $stmt = $pdo->prepare('SELECT id, name, username, email, role, department, position FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Verificar si el usuario puede desencriptar datos
 * Solo admin e investigador tienen acceso
 */
function canDecrypt() {
    return hasRole([ROLE_ADMIN, ROLE_INVESTIGADOR]);
}
