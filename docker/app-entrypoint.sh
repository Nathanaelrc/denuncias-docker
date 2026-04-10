#!/bin/bash
# =============================================
# Portal de Denuncias Empresa Portuaria Coquimbo - App Entrypoint
# 1. Espera a que MySQL esté listo
# 2. Ajusta permisos de volúmenes montados
# =============================================

DB_HOST="${DB_HOST:-db}"
DB_USER="${DB_USER:-denuncias_user}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-denuncias}"
MAX_RETRIES=30
RETRY_INTERVAL=3

bootstrap_schema() {
    php <<'PHP'
<?php
$host = getenv('DB_HOST') ?: 'db';
$name = getenv('DB_NAME') ?: 'denuncias';
$user = getenv('DB_USER') ?: 'denuncias_user';
$pass = getenv('DB_PASS');
$pass = $pass !== false ? $pass : '';
$schemaFile = '/var/www/html/database/init.sql';

$mysqli = mysqli_init();
if (!$mysqli || !$mysqli->real_connect($host, $user, $pass, $name, 3306)) {
    fwrite(STDERR, "[Denuncias] No fue posible conectar para bootstrap de esquema: " . mysqli_connect_error() . PHP_EOL);
    exit(1);
}

$result = $mysqli->query("SHOW TABLES LIKE 'complaints'");
if ($result instanceof mysqli_result && $result->num_rows > 0) {
    echo "[Denuncias] Esquema presente, no se requiere bootstrap." . PHP_EOL;
    $result->free();
    $mysqli->close();
    exit(0);
}
if ($result instanceof mysqli_result) {
    $result->free();
}

$sql = @file_get_contents($schemaFile);
if ($sql === false) {
    fwrite(STDERR, "[Denuncias] No se pudo leer el esquema: $schemaFile" . PHP_EOL);
    $mysqli->close();
    exit(1);
}

$sql = preg_replace('/^\s*USE\s+`?[^;]+`?;\s*$/mi', '', $sql);
if (!$mysqli->multi_query($sql)) {
    fwrite(STDERR, "[Denuncias] Error bootstrap inicial: " . $mysqli->error . PHP_EOL);
    $mysqli->close();
    exit(1);
}

do {
    $queryResult = $mysqli->store_result();
    if ($queryResult instanceof mysqli_result) {
        $queryResult->free();
    }
    if (!$mysqli->more_results()) {
        break;
    }
    if (!$mysqli->next_result()) {
        fwrite(STDERR, "[Denuncias] Error bootstrap siguiente sentencia: " . $mysqli->error . PHP_EOL);
        $mysqli->close();
        exit(1);
    }
} while (true);

echo "[Denuncias] Esquema bootstrap aplicado correctamente." . PHP_EOL;
$mysqli->close();
PHP
}

echo "[Denuncias] Verificando conexión a MySQL ($DB_HOST)..."

attempt=0
while [ $attempt -lt $MAX_RETRIES ]; do
    attempt=$((attempt + 1))
    # Credenciales leídas desde env dentro de PHP para no exponerlas en la lista de procesos
    if php -r "
        try {
            \$host = getenv('DB_HOST') ?: 'db';
            \$name = getenv('DB_NAME') ?: 'denuncias';
            \$user = getenv('DB_USER') ?: 'denuncias_user';
            \$pass = getenv('DB_PASS') ?: '';
            \$pdo = new PDO('mysql:host='.\$host.';dbname='.\$name.';charset=utf8mb4', \$user, \$pass, [PDO::ATTR_TIMEOUT => 3]);
            echo 'OK';
            exit(0);
        } catch (Exception \$e) {
            echo \$e->getMessage();
            exit(1);
        }
    " 2>/dev/null; then
        echo ""
        echo "[Denuncias] ✓ Conexión a MySQL exitosa (intento $attempt)"
        break
    else
        echo "[Denuncias] Intento $attempt/$MAX_RETRIES - MySQL no disponible, reintentando en ${RETRY_INTERVAL}s..."
        sleep $RETRY_INTERVAL
    fi
done

if [ $attempt -eq $MAX_RETRIES ]; then
    echo "[Denuncias] ⚠ ADVERTENCIA: No se pudo conectar a MySQL después de $MAX_RETRIES intentos."
    echo "[Denuncias]   La app se iniciará de todas formas."
else
    bootstrap_schema || echo "[Denuncias] ⚠ No se pudo verificar/aplicar el esquema automáticamente."
fi

# Ajustar permisos de volúmenes montados
chown -R www-data:www-data /var/www/html/logs 2>/dev/null || true
chmod -R 775 /var/www/html/logs 2>/dev/null || true

chown -R www-data:www-data /var/www/html/public/uploads 2>/dev/null || true
chmod -R 775 /var/www/html/public/uploads 2>/dev/null || true

mkdir -p /var/www/html/logs
chown www-data:www-data /var/www/html/logs

echo "[Denuncias] Iniciando Apache..."
exec "$@"
