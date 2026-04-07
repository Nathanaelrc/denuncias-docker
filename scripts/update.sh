#!/usr/bin/env bash
# =============================================================================
# Canal de Denuncias - Empresa Portuaria Coquimbo
# Script de actualización de versiones CDN e infraestructura Docker
# =============================================================================
# Uso:
#   ./scripts/update.sh              → revisa y aplica updates CDN
#   ./scripts/update.sh --dry-run    → solo muestra qué cambiaría
#   ./scripts/update.sh --rebuild    → aplica updates CDN y reconstruye contenedores
#   ./scripts/update.sh --infra      → también verifica PHP y MySQL en Docker Hub
# =============================================================================

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"

# Colores
RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'
CYAN='\033[0;36m'; BOLD='\033[1m'; RESET='\033[0m'

# Flags
DRY_RUN=false
REBUILD=false
INFRA=false

for arg in "$@"; do
    case $arg in
        --dry-run) DRY_RUN=true ;;
        --rebuild) REBUILD=true ;;
        --infra)   INFRA=true ;;
        --help|-h)
            sed -n '4,9p' "$0" | sed 's/^# //'
            exit 0
            ;;
    esac
done

# =============================================================================
# Funciones
# =============================================================================

# Obtiene la última versión de un paquete npm
npm_latest() {
    local pkg="$1"
    local ver
    ver=$(curl -sf "https://registry.npmjs.org/${pkg}/latest" 2>/dev/null \
        | grep -o '"version":"[^"]*"' | head -1 | cut -d'"' -f4)
    echo "${ver:-ERROR}"
}

# Obtiene la versión actual de una constante en versions.php
current_version() {
    local key="$1"
    grep "define('${key}'" "${ROOT_DIR}/config/versions.php" \
        | grep -o "'[0-9][^']*'" | tr -d "'" | head -1
}

# Actualiza una constante en ambos versions.php
update_version() {
    local key="$1" value="$2"
    for f in "${ROOT_DIR}/config/versions.php" \
              "${ROOT_DIR}/denuncias-generales/config/versions.php"; do
        sed -i "s/define('${key}', *'[^']*')/define('${key}', '${value}')/" "$f"
    done
}

# Compara versiones semver, devuelve true si $1 < $2
version_lt() {
    [ "$(printf '%s\n' "$1" "$2" | sort -V | head -1)" != "$2" ] && return 1 || return 0
}

# Extrae major de un semver
major() { echo "$1" | cut -d. -f1; }

# Muestra una fila de comparación
show_diff() {
    local name="$1" current="$2" latest="$3" note="${4:-}"
    if [ "$latest" = "ERROR" ]; then
        printf "  %-22s ${RED}%s${RESET} (sin conexión)\n" "$name" "✗"
    elif [ "$current" = "$latest" ]; then
        printf "  %-22s ${GREEN}%s${RESET} → ya actualizado\n" "$name" "$current"
    else
        printf "  %-22s ${YELLOW}%s${RESET} → ${CYAN}%s${RESET}%s\n" \
            "$name" "$current" "$latest" "$note"
    fi
}

# =============================================================================
# Cabecera
# =============================================================================
echo ""
echo -e "${BOLD}==========================================${RESET}"
echo -e "${BOLD}  Actualizador - Canal de Denuncias EPC  ${RESET}"
echo -e "${BOLD}==========================================${RESET}"
echo ""

# =============================================================================
# 1. Versiones CDN (npm registry)
# =============================================================================
echo -e "${BOLD}[ Paquetes CDN ]${RESET}"
echo "  Consultando registry.npmjs.org..."
echo ""

BS_CURRENT=$(current_version VER_BOOTSTRAP)
BSI_CURRENT=$(current_version VER_BOOTSTRAP_ICONS)
GSAP_CURRENT=$(current_version VER_GSAP)
CHART_CURRENT=$(current_version VER_CHARTJS)

BS_LATEST=$(npm_latest bootstrap)
BSI_LATEST=$(npm_latest bootstrap-icons)
GSAP_LATEST=$(npm_latest gsap)
CHART_LATEST=$(npm_latest chart.js)

show_diff "Bootstrap"         "$BS_CURRENT"    "$BS_LATEST"
show_diff "Bootstrap Icons"   "$BSI_CURRENT"   "$BSI_LATEST"
show_diff "GSAP"              "$GSAP_CURRENT"  "$GSAP_LATEST"
show_diff "Chart.js"          "$CHART_CURRENT" "$CHART_LATEST"

echo ""

# Calcular si hay cambios CDN
CDN_CHANGED=false
for pair in \
    "VER_BOOTSTRAP:$BS_LATEST" \
    "VER_BOOTSTRAP_ICONS:$BSI_LATEST" \
    "VER_GSAP:$GSAP_LATEST" \
    "VER_CHARTJS:$CHART_LATEST"; do
    key="${pair%%:*}"; val="${pair##*:}"
    current=$(current_version "$key")
    if [ "$current" != "$val" ] && [ "$val" != "ERROR" ]; then
        CDN_CHANGED=true; break
    fi
done

# =============================================================================
# 2. Infraestructura Docker (opcional)
# =============================================================================
if [ "$INFRA" = true ]; then
    echo -e "${BOLD}[ Infraestructura Docker ]${RESET}"

    PHP_CURRENT=$(grep '^PHP_VERSION=' "${ROOT_DIR}/.env" 2>/dev/null | cut -d= -f2 | tr -d '"' || echo "8.5")
    MYSQL_CURRENT=$(grep '^MYSQL_VERSION=' "${ROOT_DIR}/.env" 2>/dev/null | cut -d= -f2 | tr -d '"' || echo "8.4")

    # Consultar Docker Hub - última imagen oficial de PHP 8.x
    PHP_LATEST=$(curl -sf \
        "https://hub.docker.com/v2/repositories/library/php/tags?page_size=100&name=${PHP_CURRENT}" \
        2>/dev/null \
        | grep -o "\"${PHP_CURRENT}\.[0-9]*-apache\"" | sort -V | tail -1 \
        | tr -d '"' | sed 's/-apache//' || echo "sin conexión")

    MYSQL_LATEST=$(curl -sf \
        "https://hub.docker.com/v2/repositories/library/mysql/tags?page_size=100&name=${MYSQL_CURRENT}" \
        2>/dev/null \
        | grep -o "\"${MYSQL_CURRENT}\.[0-9]*\"" | sort -V | tail -1 \
        | tr -d '"' || echo "sin conexión")

    show_diff "PHP (apache)"  "$PHP_CURRENT"   "${PHP_LATEST:-sin dato}"   " (actualizar en .env)"
    show_diff "MySQL"         "$MYSQL_CURRENT" "${MYSQL_LATEST:-sin dato}" " (hacer backup antes)"
    echo ""
    if [ "$PHP_LATEST" != "sin conexión" ] && [ "$PHP_LATEST" != "sin dato" ] \
       && [ "$PHP_LATEST" != "$PHP_CURRENT" ]; then
        echo -e "  ${YELLOW}Para actualizar PHP:${RESET}"
        echo "    1. Edita .env → PHP_VERSION=${PHP_LATEST}"
        echo "    2. ./scripts/update.sh --rebuild"
        echo ""
    fi
    if [ "$MYSQL_LATEST" != "sin conexión" ] && [ "$MYSQL_LATEST" != "sin dato" ] \
       && [ "$MYSQL_LATEST" != "$MYSQL_CURRENT" ]; then
        echo -e "  ${YELLOW}Para actualizar MySQL:${RESET}"
        echo "    1. ./scripts/backup-db.sh"
        echo "    2. Edita .env → MYSQL_VERSION=${MYSQL_LATEST}"
        echo "    3. docker compose up -d db db-generales"
        echo ""
    fi
fi

# =============================================================================
# 3. Aplicar cambios
# =============================================================================
if [ "$DRY_RUN" = true ]; then
    echo -e "${YELLOW}[dry-run]${RESET} Sin cambios aplicados."
    echo ""
    exit 0
fi

if [ "$CDN_CHANGED" = false ]; then
    echo -e "${GREEN}✓ Todo actualizado, sin cambios necesarios.${RESET}"
    echo ""
    exit 0
fi

echo -e "${BOLD}Aplicando actualizaciones CDN...${RESET}"
update_version VER_BOOTSTRAP       "$BS_LATEST"
update_version VER_BOOTSTRAP_ICONS "$BSI_LATEST"
update_version VER_GSAP            "$GSAP_LATEST"
update_version VER_CHARTJS         "$CHART_LATEST"
echo -e "${GREEN}✓ config/versions.php actualizado en ambos portales${RESET}"
echo ""

# =============================================================================
# 4. Reconstruir contenedores (si se pidió)
# =============================================================================
if [ "$REBUILD" = true ]; then
    echo -e "${BOLD}Reconstruyendo contenedores PHP/Apache...${RESET}"
    cd "$ROOT_DIR"
    docker compose build --no-cache app app-generales landing
    echo -e "${GREEN}✓ Imágenes reconstruidas${RESET}"
    echo ""
    echo -e "${BOLD}Reiniciando contenedores (sin bajar nginx)...${RESET}"
    docker compose up -d app app-generales landing
    echo -e "${GREEN}✓ Contenedores actualizados sin interrumpir nginx${RESET}"
    echo ""
    echo -e "${BOLD}Estado actual:${RESET}"
    docker compose ps
else
    echo -e "${YELLOW}Consejo:${RESET} Para que los cambios entren en producción ejecuta:"
    echo "  ./scripts/update.sh --rebuild"
    echo ""
fi

echo -e "${GREEN}${BOLD}✓ Actualización completada${RESET}"
echo ""
