<?php
/**
 * Portal Ciudadano - Health Check
 * Protegido por token configurable (HEALTH_CHECK_TOKEN).
 */
header('Content-Type: application/json; charset=UTF-8');

$healthToken = getenv('HEALTH_CHECK_TOKEN') ?: '';
$remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
$isLocalhost = in_array($remoteAddr, ['127.0.0.1', '::1'], true);
if (!empty($healthToken) && !$isLocalhost) {
    $provided = $_GET['token'] ?? ($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? '');
    if (!hash_equals($healthToken, $provided)) {
        http_response_code(403);
        echo json_encode(['status' => 'forbidden']);
        exit;
    }
}

$result = ['status' => 'ok', 'service' => 'denuncias-generales', 'time' => date('c')];

if (isset($_GET['db'])) {
    $dbHost = getenv('DB_HOST') ?: 'db-generales';
    $dbName = getenv('DB_NAME') ?: 'denuncias_generales';
    $dbUser = getenv('DB_USER') ?: 'generales_user';
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
