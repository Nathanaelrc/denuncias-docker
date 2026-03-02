<?php
/**
 * Portal de Denuncias - Bootstrap Principal
 */

ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

// Headers de seguridad
$isAjaxRequest = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!headers_sent()) {
    if (!$isAjaxRequest) {
        header('Content-Type: text/html; charset=UTF-8');
    }
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');

    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['logged_in'])) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

if (!defined('DENUNCIAS_APP')) {
    define('DENUNCIAS_APP', true);
}

define('DENUNCIAS_ROOT', dirname(__DIR__));

require_once DENUNCIAS_ROOT . '/config/app.php';
require_once DENUNCIAS_ROOT . '/config/database.php';
require_once DENUNCIAS_ROOT . '/includes/encriptacion.php';
require_once DENUNCIAS_ROOT . '/includes/correo.php';
require_once DENUNCIAS_ROOT . '/includes/utilidades.php';
require_once DENUNCIAS_ROOT . '/includes/autenticacion.php';
