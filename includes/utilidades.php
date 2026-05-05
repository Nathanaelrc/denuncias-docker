<?php
/**
 * Portal de Denuncias - Utilidades
 */

/**
 * Genera el fragmento SQL (AND/WHERE) y parámetros para excluir denuncias en conflicto de interés.
 * Combina el check de registros legacy (accused_name_hmac) con el check de tabla de tokens.
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

    $enc    = getEncryptionService();
    $tokens = $enc->computeInvestigatorTokens(
        $user['name'],
        $user['position']   ?? null,
        $user['department'] ?? null
    );
    if (empty($tokens)) return $empty;

    $col  = $alias !== '' ? "{$alias}." : '';
    $ph   = implode(',', array_fill(0, count($tokens), '?'));
    $parts  = [];
    $params = [];

    // Check legacy: accused_name_hmac (cubre registros anteriores al nuevo sistema)
    $parts[]  = "({$col}accused_name_hmac IS NULL OR {$col}accused_name_hmac NOT IN ($ph))";
    $params   = array_merge($params, $tokens);

    // Check expandido: tabla complaint_conflict_tokens — requiere al menos 2 tokens coincidentes
    // para evitar falsos positivos con palabras genéricas como "jefe"
    $parts[]  = "{$col}id NOT IN (SELECT cct.complaint_id FROM complaint_conflict_tokens cct WHERE cct.token_hmac IN ($ph) GROUP BY cct.complaint_id HAVING COUNT(DISTINCT cct.token_hmac) >= 2)";
    $params   = array_merge($params, $tokens);

    $combined = implode(' AND ', $parts);
    return [
        'and_sql'   => "AND ($combined)",
        'where_sql' => "WHERE ($combined)",
        'params'    => $params,
    ];
}

/**
 * Verifica si una denuncia específica está en conflicto de interés para el investigador.
 * Para uso en detalle_denuncia.php.
 */
function isComplaintConflict(int $complaintId, array $user): bool
{
    global $pdo;
    if (!canAccessComplaints($user)) return false;

    $enc    = getEncryptionService();
    $tokens = $enc->computeInvestigatorTokens(
        $user['name'],
        $user['position']   ?? null,
        $user['department'] ?? null
    );
    if (empty($tokens)) return false;

    // Check legacy: accused_name_hmac
    $stmt = $pdo->prepare("SELECT accused_name_hmac FROM complaints WHERE id = ?");
    $stmt->execute([$complaintId]);
    $storedHmac = $stmt->fetchColumn();
    if ($storedHmac && in_array($storedHmac, $tokens, true)) return true;

    // Check tabla de tokens expandidos — requiere al menos 2 coincidencias
    $ph    = implode(',', array_fill(0, count($tokens), '?'));
    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT token_hmac) FROM complaint_conflict_tokens WHERE complaint_id = ? AND token_hmac IN ($ph)");
    $stmt2->execute(array_merge([$complaintId], $tokens));
    return (int)$stmt2->fetchColumn() >= 2;
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

/**
 * Tiempo relativo
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) return 'Hace un momento';
    if ($diff < 3600) return 'Hace ' . floor($diff / 60) . ' min';
    if ($diff < 86400) return 'Hace ' . floor($diff / 3600) . ' horas';
    if ($diff < 604800) return 'Hace ' . floor($diff / 86400) . ' días';
    return formatDate($datetime);
}
