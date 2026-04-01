<?php
/**
 * Portal Denuncias Ciudadanas - Bootstrap Principal
 */

ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!headers_sent()) {
    if (!$isAjaxRequest) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header(
        "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
        "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; " .
        "img-src 'self' data:; " .
        "connect-src 'self'; " .
        "frame-ancestors 'none';"
    );

    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['logged_in'])) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

if (!defined('GENERALES_APP')) {
    define('GENERALES_APP', true);
}

define('GENERALES_ROOT', dirname(__DIR__));

require_once GENERALES_ROOT . '/config/app.php';
require_once GENERALES_ROOT . '/config/database.php';
require_once GENERALES_ROOT . '/includes/encriptacion.php';
require_once GENERALES_ROOT . '/includes/correo.php';
require_once GENERALES_ROOT . '/includes/utilidades.php';
require_once GENERALES_ROOT . '/includes/autenticacion.php';
