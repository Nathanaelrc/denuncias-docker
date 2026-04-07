<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Cerrar Sesión
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) {
    logActivity($_SESSION['user_id'], 'logout', 'user', $_SESSION['user_id'], 'Cierre de sesión');
}

logout();
header('Location: ' . APP_BASE_PATH . '/acceso');
exit;
