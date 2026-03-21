<?php
/**
 * Portal Denuncias Ciudadanas - Cerrar Sesión
 */
require_once __DIR__ . '/../includes/bootstrap.php';
$userId = $_SESSION['user_id'] ?? null;
logActivity($userId, 'logout', 'user', $userId, 'Cierre de sesión');
logout();
redirect('/iniciar_sesion', 'Sesión cerrada correctamente.');
