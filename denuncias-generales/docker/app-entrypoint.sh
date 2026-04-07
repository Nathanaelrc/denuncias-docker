#!/bin/bash
# =============================================
# Portal Denuncias Ciudadanas - App Entrypoint
# =============================================
DB_HOST="${DB_HOST:-db-generales}"
DB_USER="${DB_USER:-generales_user}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-denuncias_generales}"
MAX_RETRIES=30
RETRY_INTERVAL=3

echo "[DenunciasGenerales] Verificando conexión a MySQL ($DB_HOST)..."

attempt=0
while [ $attempt -lt $MAX_RETRIES ]; do
    attempt=$((attempt + 1))
    # Credenciales leídas desde env dentro de PHP para no exponerlas en la lista de procesos
    if php -r "
        try {
            \$host = getenv('DB_HOST') ?: 'db-generales';
            \$name = getenv('DB_NAME') ?: 'denuncias_generales';
            \$user = getenv('DB_USER') ?: 'generales_user';
            \$pass = getenv('DB_PASS') ?: '';
            \$pdo = new PDO('mysql:host='.\$host.';dbname='.\$name.';charset=utf8mb4', \$user, \$pass, [PDO::ATTR_TIMEOUT => 3]);
            echo 'OK'; exit(0);
        } catch (Exception \$e) { echo \$e->getMessage(); exit(1); }
    " 2>/dev/null; then
        echo ""
        echo "[DenunciasGenerales] ✓ Conexión a MySQL exitosa (intento $attempt)"
        break
    else
        echo "[DenunciasGenerales] Intento $attempt/$MAX_RETRIES - reintentando en ${RETRY_INTERVAL}s..."
        sleep $RETRY_INTERVAL
    fi
done

if [ $attempt -eq $MAX_RETRIES ]; then
    echo "[DenunciasGenerales] ⚠ No se pudo conectar a MySQL. Iniciando de todas formas."
fi

chown -R www-data:www-data /var/www/html/logs 2>/dev/null || true
chmod -R 775 /var/www/html/logs 2>/dev/null || true
chown -R www-data:www-data /var/www/html/public/uploads 2>/dev/null || true
chmod -R 775 /var/www/html/public/uploads 2>/dev/null || true
mkdir -p /var/www/html/logs
chown www-data:www-data /var/www/html/logs

echo "[DenunciasGenerales] Iniciando Apache..."
exec "$@"
