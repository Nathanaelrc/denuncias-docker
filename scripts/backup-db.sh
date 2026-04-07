#!/usr/bin/env bash
# =============================================================================
# Canal de Denuncias - Backup de bases de datos
# =============================================================================
# Uso:
#   ./scripts/backup-db.sh              → backup de ambas BDs
#   ./scripts/backup-db.sh --karin      → solo Portal Ley Karin
#   ./scripts/backup-db.sh --generales  → solo Portal Ciudadano
# =============================================================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; BOLD='\033[1m'; RESET='\033[0m'

mkdir -p "$BACKUP_DIR"

# Leer variables del .env
if [ -f "${ROOT_DIR}/.env" ]; then
    set -a; source "${ROOT_DIR}/.env"; set +a
fi

DO_KARIN=true
DO_GENERALES=true
for arg in "$@"; do
    case $arg in
        --karin)     DO_GENERALES=false ;;
        --generales) DO_KARIN=false ;;
    esac
done

echo ""
echo -e "${BOLD}==============================${RESET}"
echo -e "${BOLD}  Backup - Canal de Denuncias${RESET}"
echo -e "${BOLD}==============================${RESET}"
echo ""

backup_db() {
    local container="$1" db="$2" user="$3" pass="$4" label="$5"
    local outfile="${BACKUP_DIR}/${label}_${TIMESTAMP}.sql.gz"

    echo -e "  Exportando ${BOLD}${label}${RESET}..."
    docker exec "$container" \
        mysqldump --single-transaction --routines --triggers \
        -u "$user" -p"${pass}" "$db" \
        | gzip > "$outfile"

    local size
    size=$(du -sh "$outfile" | cut -f1)
    echo -e "  ${GREEN}✓${RESET} ${outfile##*/} (${size})"
}

if [ "$DO_KARIN" = true ]; then
    backup_db "denuncias-db" \
        "${DB_NAME:-denuncias}" \
        "${DB_USER:-denuncias_user}" \
        "${DB_PASS:-cambiar-db-pass}" \
        "karin"
fi

if [ "$DO_GENERALES" = true ]; then
    backup_db "denuncias-generales-db" \
        "${DB_GENERALES_NAME:-denuncias_generales}" \
        "${DB_GENERALES_USER:-generales_user}" \
        "${DB_GENERALES_PASS:-cambiar-db-pass}" \
        "generales"
fi

echo ""
echo -e "${YELLOW}Backups guardados en:${RESET} ${BACKUP_DIR}/"

# Limpiar backups con más de 30 días
OLD=$(find "$BACKUP_DIR" -name "*.sql.gz" -mtime +30 2>/dev/null | wc -l)
if [ "$OLD" -gt 0 ]; then
    find "$BACKUP_DIR" -name "*.sql.gz" -mtime +30 -delete
    echo -e "  ${YELLOW}Eliminados ${OLD} backup(s) con más de 30 días${RESET}"
fi

echo -e "${GREEN}${BOLD}✓ Backup completado${RESET}"
echo ""
