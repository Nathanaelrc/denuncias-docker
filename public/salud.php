<?php
/**
 * Portal de Denuncias - Health Check
 * Protegido por token configurable (HEALTH_CHECK_TOKEN en .env).
 * Sin token configurado, solo responde el estado básico sin detalles de BD.
 */
header('Content-Type: application/json; charset=UTF-8');

$healthToken = getenv('HEALTH_CHECK_TOKEN') ?: '';
if (!empty($healthToken)) {
    $provided = $_GET['token'] ?? ($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? '');
    if (!hash_equals($healthToken, $provided)) {
        http_response_code(403);
        echo json_encode(['status' => 'forbidden']);
        exit;
    }
}

$result = ['status' => 'ok', 'service' => 'denuncias-app', 'time' => date('c')];

if (isset($_GET['db'])) {
    $dbHost = getenv('DB_HOST') ?: 'db';
    $dbName = getenv('DB_NAME') ?: 'denuncias';
    $dbUser = getenv('DB_USER') ?: 'denuncias_user';
    $dbPass = getenv('DB_PASS');
    $dbPass = ($dbPass !== false) ? $dbPass : '';

    try {
        $pdo = new PDO("mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass, [PDO::ATTR_TIMEOUT => 3]);
        $result['database'] = 'connected';
    } catch (PDOException $e) {
        $result['status'] = 'degraded';
        $result['database'] = 'error';
    }
}

http_response_code($result['status'] === 'ok' ? 200 : 503);
echo json_encode($result, JSON_PRETTY_PRINT);
