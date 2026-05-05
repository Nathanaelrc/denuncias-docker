#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

pick_weekday() {
    local offset candidate dow
    while true; do
        offset=$((RANDOM % 21 + 1))
        candidate="$(date -d "-${offset} day" +%F)"
        dow="$(date -d "$candidate" +%u)"
        if [[ "$dow" -le 5 ]]; then
            printf '%s\n' "$candidate"
            return 0
        fi
    done
}

DATE_ONE="$(pick_weekday)"
DATE_TWO="$(pick_weekday)"
while [[ "$DATE_TWO" == "$DATE_ONE" ]]; do
    DATE_TWO="$(pick_weekday)"
done

RUN_TAG="$(date +%Y%m%d%H%M%S)-$RANDOM"

create_test_complaint() {
    local service="$1"
    local complaint_type="$2"
    local incident_date="$3"
    local scope="$4"

    docker compose exec -T \
        -e TEST_INCIDENT_DATE="$incident_date" \
        -e TEST_SCOPE="$scope" \
        -e TEST_RUN_TAG="$RUN_TAG" \
        -e TEST_COMPLAINT_TYPE="$complaint_type" \
        "$service" php -r '
require "/var/www/html/includes/bootstrap.php";

$scope = getenv("TEST_SCOPE") ?: "scope";
$incidentDate = getenv("TEST_INCIDENT_DATE") ?: date("Y-m-d");
$runTag = getenv("TEST_RUN_TAG") ?: "manual";
$complaintType = getenv("TEST_COMPLAINT_TYPE") ?: "general";

$data = [
    "complaint_type" => $complaintType,
    "description" => "Denuncia automatizada de prueba [" . $scope . "] run=" . $runTag . " fecha=" . $incidentDate,
    "is_anonymous" => 1,
    "involved_persons" => "Registro de prueba automatizada",
    "evidence_description" => "Sin evidencia adjunta (prueba automatizada)",
    "reporter_name" => null,
    "reporter_lastname" => null,
    "reporter_email" => null,
    "reporter_phone" => null,
    "reporter_department" => null,
    "accused_name" => "Entidad de prueba " . $scope,
    "accused_department" => "Area QA",
    "accused_position" => "N/A",
    "witnesses" => "Sin testigos (automatizado)",
    "incident_date" => $incidentDate,
    "incident_location" => "Prueba automatizada de flujo"
];

$result = createComplaint($data);
if (empty($result["success"])) {
    fwrite(STDERR, "Error creando denuncia test en " . $scope . ": " . ($result["message"] ?? "desconocido") . PHP_EOL);
    exit(1);
}

echo $scope . ":" . ($result["complaint_number"] ?? "SIN_NUMERO") . PHP_EOL;
'
}

echo "[test-flujo] Fechas habiles seleccionadas: karin=$DATE_ONE, generales=$DATE_TWO"
echo "[test-flujo] Ejecutando insercion de denuncias de prueba..."

KARIN_RESULT="$(create_test_complaint "app" "acoso_laboral" "$DATE_ONE" "karin")"
GENERALES_RESULT="$(create_test_complaint "app-generales" "operaciones" "$DATE_TWO" "generales")"

echo "[test-flujo] Resultado Karin: $KARIN_RESULT"
echo "[test-flujo] Resultado Generales: $GENERALES_RESULT"
echo "[test-flujo] Flujo de prueba completado correctamente."
