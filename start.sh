#!/bin/bash
# =============================================
# Plataforma de Denuncias Empresa Portuaria Coquimbo - Script de Inicio
# Portales: Landing / Ley Karin / Portal Ciudadano
# Detecta puertos disponibles automáticamente
# Uso: bash start.sh
# =============================================

set -e

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"

echo ""
echo -e "${BOLD}=========================================="
echo -e "  Plataforma de Denuncias Empresa Portuaria Coquimbo - v2.0"
echo -e "==========================================${NC}"
echo ""

# =============================================
# Detectar puertos libres
# =============================================
get_all_used_ports() {
    local ports=""
    if command -v ss &>/dev/null; then
        ports+=$(ss -tlnp 2>/dev/null | awk '{print $4}' | grep -oP ':\K[0-9]+$' | sort -un)
    fi
    if command -v docker &>/dev/null; then
        local docker_ports
        docker_ports=$(docker ps --format '{{.Ports}}' 2>/dev/null | grep -oP '0\.0\.0\.0:\K[0-9]+' | sort -un 2>/dev/null || true)
        if [ -n "$docker_ports" ]; then
            ports+=$'\n'"$docker_ports"
        fi
    fi
    echo "$ports" | grep -v '^$' | sort -un
}

is_port_free() {
    local port=$1
    local used_ports="$2"
    if echo "$used_ports" | grep -qw "^${port}$"; then
        return 1
    fi
    return 0
}

find_free_port() {
    local preferred=$1
    local used_ports="$2"
    if is_port_free "$preferred" "$used_ports"; then
        echo "$preferred"
        return
    fi
    local port=$((preferred + 1))
    while [ $port -lt 65535 ]; do
        if is_port_free "$port" "$used_ports"; then
            echo "$port"
            return
        fi
        port=$((port + 1))
    done
    echo "$preferred"
}

echo -e "${CYAN}► Detectando puertos disponibles...${NC}"
USED_PORTS=$(get_all_used_ports)

APP_PORT=$(find_free_port 8091 "$USED_PORTS")
DB_PORT=$(find_free_port 3307 "$USED_PORTS")
PMA_PORT=$(find_free_port 8092 "$USED_PORTS")
APP_GENERALES_PORT=$(find_free_port 8093 "$USED_PORTS")
DB_GENERALES_PORT=$(find_free_port 3308 "$USED_PORTS")
LANDING_PORT=$(find_free_port 8090 "$USED_PORTS")

echo ""
echo -e "${GREEN}Puertos asignados:${NC}"
echo -e "  Landing:              ${BOLD}${LANDING_PORT}${NC}"
echo -e "  App Ley Karin:        ${BOLD}${APP_PORT}${NC}"
echo -e "  App Portal Ciudadano: ${BOLD}${APP_GENERALES_PORT}${NC}"
echo -e "  MySQL Ley Karin:      ${BOLD}${DB_PORT}${NC}"
echo -e "  MySQL Ciudadano:      ${BOLD}${DB_GENERALES_PORT}${NC}"
echo -e "  phpMyAdmin:           ${BOLD}${PMA_PORT}${NC}"

# =============================================
# Generar .env
# =============================================
if [ ! -f "$ENV_FILE" ]; then
    ENCRYPTION_KEY=$(openssl rand -base64 32 2>/dev/null || cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)
    GENERALES_ENCRYPTION_KEY=$(openssl rand -base64 32 2>/dev/null || cat /dev/urandom | tr -dc 'a-zA-Z0-9' | fold -w 32 | head -n 1)

    cat > "$ENV_FILE" <<EOF
# Generado automáticamente - $(date)
# ─── Puertos ───────────────────────────────────
LANDING_PORT=${LANDING_PORT}
APP_PORT=${APP_PORT}
DB_PORT=${DB_PORT}
PMA_PORT=${PMA_PORT}
APP_GENERALES_PORT=${APP_GENERALES_PORT}
DB_GENERALES_PORT=${DB_GENERALES_PORT}

# ─── URLs públicas ─────────────────────────────
APP_ENV=production
APP_URL=http://localhost:${APP_PORT}
APP_URL_GENERAL=http://localhost:${APP_GENERALES_PORT}

# ─── Base de datos Ley Karin ───────────────────
DB_NAME=denuncias
DB_USER=denuncias_user
DB_PASS='CAMBIAR-ESTA-CONTRASEÑA'
DB_ROOT_PASSWORD='CAMBIAR-ESTA-CONTRASEÑA'

# ─── Base de datos Portal Ciudadano ───────────
DB_GENERALES_NAME=denuncias_generales
DB_GENERALES_USER=generales_user
DB_GENERALES_PASS='CAMBIAR-ESTA-CONTRASEÑA'

# ─── Claves de cifrado (AES-256) ───────────────
ENCRYPTION_KEY=${ENCRYPTION_KEY}
GENERALES_ENCRYPTION_KEY=${GENERALES_ENCRYPTION_KEY}

# ─── SMTP (compartido entre portales) ─────────
SMTP_ENABLED=false
# Deja SMTP_HOST/PORT/ENCRYPTION vacíos para autodetección por dominio de SMTP_USER
# (gmail.com, outlook/hotmail/live, yahoo, zoho, icloud)
SMTP_HOST=
SMTP_PORT=
SMTP_USER=
SMTP_PASS=
SMTP_ENCRYPTION=
SMTP_FROM_EMAIL=denuncias@epco.cl
SMTP_FROM_NAME=Canal de Denuncias - Empresa Portuaria Coquimbo
SMTP_ADMIN_EMAIL=

# Worker de cola de correos (lote por ejecución)
EMAIL_QUEUE_BATCH=25
EOF

    echo ""
    echo -e "${GREEN}✓ Archivo .env generado${NC}"
    echo -e "${RED}⚠ IMPORTANTE: Edita .env y cambia las contraseñas DB_PASS y DB_ROOT_PASSWORD${NC}"
else
    echo ""
    echo -e "${GREEN}✓ Archivo .env existente encontrado (no se sobreescribe)${NC}"
    # Actualizar puertos si cambiaron
    source "$ENV_FILE"
fi

# =============================================
# Construir y levantar
# =============================================
echo ""
echo -e "${CYAN}► Construyendo contenedores...${NC}"
cd "$SCRIPT_DIR"
docker compose --profile dev build --no-cache

echo ""
echo -e "${CYAN}► Iniciando servicios (con phpMyAdmin)...${NC}"
docker compose --profile dev up -d

echo ""
echo -e "${BOLD}=========================================="
echo -e "${GREEN}  ✓ Plataforma de Denuncias Empresa Portuaria Coquimbo Lista"
echo -e "${NC}${BOLD}=========================================="
echo ""
echo -e "  🌐 Selector:       ${CYAN}http://localhost:${LANDING_PORT}${NC}"
echo -e "  🏢 Ley Karin:      ${CYAN}http://localhost:${APP_PORT}${NC}"
echo -e "  👥 Portal Ciudadano: ${CYAN}http://localhost:${APP_GENERALES_PORT}${NC}"
echo -e "  🗄️  phpMyAdmin:      ${CYAN}http://localhost:${PMA_PORT}${NC} (dev)"
echo ""
echo -e "  ${YELLOW}Usuarios iniciales — Ley Karin:${NC}"
echo -e "    admin.denuncias / password"
echo -e "    comite.etica / password"
echo -e "    investigador1 / password"
echo ""
echo -e "  ${YELLOW}Usuarios iniciales — Portal Ciudadano:${NC}"
echo -e "    admin.ciudadano / password"
echo -e "    delegado1 / password"
echo ""
echo -e "  ${RED}⚠ Cambiar contraseñas en el primer inicio de sesión${NC}"
echo -e "=========================================="
echo ""
