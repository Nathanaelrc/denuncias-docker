<?php
/**
 * Portal de Denuncias - Health Check
 */
header('Content-Type: application/json; charset=UTF-8');

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
