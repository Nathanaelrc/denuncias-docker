<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Configuración de Base de Datos
 */

if (!defined('DENUNCIAS_APP')) {
    die('Acceso directo no permitido');
}

define('DB_HOST', getenv('DB_HOST') ?: 'db');
define('DB_NAME', getenv('DB_NAME') ?: 'denuncias');
define('DB_USER', getenv('DB_USER') ?: 'denuncias_user');
$dbPass = getenv('DB_PASS');
define('DB_PASS', $dbPass !== false ? $dbPass : '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

$dsn = sprintf("mysql:host=%s;dbname=%s;charset=%s", DB_HOST, DB_NAME, DB_CHARSET);

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $pdoOptions);
} catch (PDOException $e) {
    error_log("[Denuncias DB] Error: " . $e->getMessage());
    http_response_code(500);
    if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
        die("Error de conexión: " . htmlspecialchars($e->getMessage()));
    } else {
        die("Error de conexión a la base de datos.");
    }
}
