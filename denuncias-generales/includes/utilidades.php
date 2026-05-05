<?php
/**
 * Portal Denuncias Ciudadanas - Utilidades
 */

/**
 * Verifica si una columna existe en una tabla de la base actual.
 */
function dbColumnExists(string $table, string $column): bool
{
    global $pdo;
    static $cache = [];

    $cacheKey = $table . '.' . $column;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([DB_NAME, $table, $column]);
        $cache[$cacheKey] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        $cache[$cacheKey] = false;
    }

    return $cache[$cacheKey];
}

/**
 * Indica si la funcionalidad de asignación por área está disponible en BD.
 */
function hasAreaAssignmentSupport(): bool
{
    return dbColumnExists('users', 'investigator_area') && dbColumnExists('complaints', 'assigned_area');
}

/**
 * Normaliza el área de investigación a una clave válida del catálogo.
 */
function normalizeInvestigationArea(?string $area): ?string
{
    $key = strtolower(trim((string)$area));
    return array_key_exists($key, INVESTIGATION_AREAS) ? $key : null;
}

/**
 * Retorna el label del área de investigación o un fallback seguro.
 */
function getInvestigationAreaLabel(?string $area): string
{
    $normalized = normalizeInvestigationArea($area);
    return $normalized ? INVESTIGATION_AREAS[$normalized] : 'Sin área';
}

/**
 * Filtro por área para investigadores del portal general.
 * Si el investigador no tiene área configurada, no ve denuncias.
 */
function getAreaVisibilityFilter(array $user, string $alias = 'c'): array
{
    $empty = ['and_sql' => '', 'where_sql' => '', 'params' => []];
    if (($user['role'] ?? null) !== ROLE_INVESTIGADOR) return $empty;
    if (!hasAreaAssignmentSupport()) return $empty;

    $col = $alias !== '' ? "{$alias}." : '';
    $investigationArea = normalizeInvestigationArea($user['investigator_area'] ?? null);
    if (!$investigationArea) {
        return [
            'and_sql' => 'AND 1=0',
            'where_sql' => 'WHERE 1=0',
            'params' => [],
        ];
    }

    return [
        'and_sql' => "AND {$col}assigned_area = ?",
        'where_sql' => "WHERE {$col}assigned_area = ?",
        'params' => [$investigationArea],
    ];
}

/**
 * Genera el fragmento SQL (AND/WHERE) y parámetros para excluir denuncias en conflicto de interés.
 * En el portal ciudadano se considera conflicto cualquier coincidencia por nombre completo,
 * nombre o apellido del usuario revisor contra los tokens del denunciado.
 * Para consultas sin alias de tabla pasa $alias = ''.
 *
 * @param  array  $user   Usuario actual (con 'role', 'name', 'position', 'department')
 * @param  string $alias  Alias de la tabla complaints (default 'c', usar '' para queries sin alias)
 * @return array  ['and_sql' => string, 'where_sql' => string, 'params' => array]
 */
function getConflictFilter(array $user, string $alias = 'c'): array
{
    $empty = ['and_sql' => '', 'where_sql' => '', 'params' => []];
    if (!canAccessComplaints($user)) return $empty;

    $areaFilter = getAreaVisibilityFilter($user, $alias);

    $enc    = getEncryptionService();
    $tokens = $enc->computePersonNameTokens((string)($user['name'] ?? ''));
    if (empty($tokens)) {
        return $areaFilter;
    }

    $col  = $alias !== '' ? "{$alias}." : '';
    $ph   = implode(',', array_fill(0, count($tokens), '?'));
    $parts  = [];
    $params = [];

    // Si el nombre completo denunciado coincide exactamente con el nombre o apellido del revisor, se excluye.
    $parts[]  = "({$col}accused_name_hmac IS NULL OR {$col}accused_name_hmac NOT IN ($ph))";
    $params   = array_merge($params, $tokens);

    // Si cualquier token del nombre/apellido del revisor aparece en los tokens del denunciado, se excluye.
    $parts[]  = "{$col}id NOT IN (SELECT cct.complaint_id FROM complaint_conflict_tokens cct WHERE cct.token_hmac IN ($ph))";
    $params   = array_merge($params, $tokens);

    $combined = implode(' AND ', $parts);
    $base = [
        'and_sql'   => "AND ($combined)",
        'where_sql' => "WHERE ($combined)",
        'params'    => $params,
    ];

    if ($areaFilter['and_sql'] === '') {
        return $base;
    }

    if ($base['and_sql'] === '') {
        return $areaFilter;
    }

    return [
        'and_sql' => $base['and_sql'] . ' ' . $areaFilter['and_sql'],
        'where_sql' => 'WHERE (' . $combined . ') ' . $areaFilter['and_sql'],
        'params' => array_merge($base['params'], $areaFilter['params']),
    ];
}

/**
 * Determina si el usuario puede ver una denuncia según su área asignada.
 */
function isComplaintInUserArea(array $complaint, array $user): bool
{
    if (($user['role'] ?? null) !== ROLE_INVESTIGADOR) {
        return true;
    }

    $userArea = normalizeInvestigationArea($user['investigator_area'] ?? null);
    $complaintArea = normalizeInvestigationArea($complaint['assigned_area'] ?? null);
    return $userArea !== null && $complaintArea !== null && $userArea === $complaintArea;
}

/**
 * Verifica si una denuncia específica está en conflicto de interés para el investigador.
 */
function isComplaintConflict(int $complaintId, array $user): bool
{
    global $pdo;
    if (!canAccessComplaints($user)) return false;

    $enc    = getEncryptionService();
    $tokens = $enc->computePersonNameTokens((string)($user['name'] ?? ''));
    if (empty($tokens)) return false;

    $stmt = $pdo->prepare("SELECT accused_name_hmac FROM complaints WHERE id = ?");
    $stmt->execute([$complaintId]);
    $storedHmac = $stmt->fetchColumn();
    if ($storedHmac && in_array($storedHmac, $tokens, true)) return true;

    $ph    = implode(',', array_fill(0, count($tokens), '?'));
    $stmt2 = $pdo->prepare("SELECT 1 FROM complaint_conflict_tokens WHERE complaint_id = ? AND token_hmac IN ($ph) LIMIT 1");
    $stmt2->execute(array_merge([$complaintId], $tokens));
    return (bool)$stmt2->fetchColumn();
}

/**
 * Verifica acceso completo del usuario a una denuncia específica.
 */
function canUserViewComplaintById(int $complaintId, array $user): bool
{
    global $pdo;

    if (!canAccessComplaints($user)) {
        return false;
    }

    $areaSelect = hasAreaAssignmentSupport() ? 'assigned_area' : 'NULL AS assigned_area';
    $stmt = $pdo->prepare("SELECT id, $areaSelect FROM complaints WHERE id = ? LIMIT 1");
    $stmt->execute([$complaintId]);
    $complaint = $stmt->fetch();
    if (!$complaint) {
        return false;
    }

    if (!isComplaintInUserArea($complaint, $user)) {
        return false;
    }

    return !isComplaintConflict($complaintId, $user);
}

/**
 * Obtener etiqueta HTML de estado de denuncia
 */
function getStatusBadge(string $status): string {
    $statuses = COMPLAINT_STATUSES;
    $config = $statuses[$status] ?? ['label' => $status, 'color' => 'secondary', 'icon' => 'bi-circle'];
    return sprintf(
        '<span class="badge bg-%s"><i class="bi %s me-1"></i>%s</span>',
        $config['color'], $config['icon'], $config['label']
    );
}

/**
 * Obtener etiqueta HTML de tipo de denuncia
 */
function getTypeBadge(string $type): string {
    $types = COMPLAINT_TYPES;
    $config = $types[$type] ?? ['label' => $type, 'icon' => 'bi-circle'];
    return sprintf(
        '<span class="badge bg-dark"><i class="bi %s me-1"></i>%s</span>',
        $config['icon'], $config['label']
    );
}

/**
 * Formato de fecha
 */
function formatDate(?string $date, string $format = 'd/m/Y'): string {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

function formatDateTime(?string $date): string {
    if (!$date) return '-';
    return date('d/m/Y H:i', strtotime($date));
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'Hace un momento';
    if ($diff < 3600) return 'Hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . ' horas';
    if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . ' días';
    return formatDate($datetime);
}
