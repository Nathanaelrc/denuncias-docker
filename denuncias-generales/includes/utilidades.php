<?php
/**
 * Portal Denuncias Ciudadanas - Utilidades
 */

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

    $enc    = getEncryptionService();
    $tokens = $enc->computePersonNameTokens((string)($user['name'] ?? ''));
    if (empty($tokens)) return $empty;

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
    return [
        'and_sql'   => "AND ($combined)",
        'where_sql' => "WHERE ($combined)",
        'params'    => $params,
    ];
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
 * Genera un filtro para ocultar notificaciones ligadas a denuncias en conflicto.
 */
function getComplaintNotificationVisibilityFilter(array $user, string $notificationAlias = 'n', string $complaintAlias = 'c'): array
{
    $notifCol = $notificationAlias !== '' ? $notificationAlias . '.' : '';

    if (!canAccessComplaints($user)) {
        return [
            'and_sql' => "AND ({$notifCol}entity_type <> 'complaint' OR {$notifCol}entity_id IS NULL)",
            'params' => [],
        ];
    }

    $cf = getConflictFilter($user, $complaintAlias);
    if ($cf['and_sql'] === '') {
        return ['and_sql' => '', 'params' => []];
    }

    return [
        'and_sql' => "AND ({$notifCol}entity_type <> 'complaint' OR {$notifCol}entity_id IS NULL OR EXISTS (SELECT 1 FROM complaints {$complaintAlias} WHERE {$complaintAlias}.id = {$notifCol}entity_id {$cf['and_sql']}))",
        'params' => $cf['params'],
    ];
}

function sendNotification(string $groupSlug, string $title, string $message = '', string $entityType = null, int $entityId = null): void {
    global $pdo;
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.email, u.name, u.role, u.position, u.department
            FROM notification_subscriptions ns
            JOIN notification_groups ng ON ns.group_id = ng.id
            JOIN users u ON ns.user_id = u.id
            WHERE ng.slug IN (?, 'todas') AND ng.is_active = 1 AND u.is_active = 1
        ");
        $stmt->execute([$groupSlug]);
        $subscribers = $stmt->fetchAll();
        if (empty($subscribers)) return;

        $ins = $pdo->prepare("INSERT INTO notifications (user_id, group_slug, title, message, entity_type, entity_id) VALUES (?, ?, ?, ?, ?, ?)");
        $groupLabels = [
            'denuncia_creada' => 'Nueva Denuncia',
            'asignacion'      => 'Asignación de Caso',
            'investigacion'   => 'En Revisión',
            'resuelta'        => 'Denuncia Resuelta',
            'cerrada'         => 'Denuncia Cerrada',
        ];
        $groupLabel = $groupLabels[$groupSlug] ?? 'Notificación';
        $appUrl     = getenv('APP_URL') ?: 'http://localhost:8093';

        foreach ($subscribers as $user) {
            if ($entityType === 'complaint' && $entityId) {
                if (!canAccessComplaints($user) || isComplaintConflict((int)$entityId, $user)) {
                    continue;
                }
            }

            $ins->execute([$user['id'], $groupSlug, $title, $message, $entityType, $entityId]);
            if (!empty($user['email'])) {
                $detailLink = '';
                if ($entityType === 'complaint' && $entityId) {
                    $detailLink = '<p style="margin-top:20px;text-align:center;"><a href="' . htmlspecialchars($appUrl) . '/detalle_denuncia?id=' . (int)$entityId . '" style="background:#1a6591;color:#ffffff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px;display:inline-block;">Ver Detalle</a></p>';
                }
                $content = '
                    <p style="color:#374151;font-size:14px;line-height:1.7;">Hola <strong>' . htmlspecialchars($user['name']) . '</strong>,</p>
                    <p style="color:#374151;font-size:14px;line-height:1.7;">Tienes una nueva notificación en el Portal Ciudadano:</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border-radius:8px;padding:20px;margin:15px 0;">
                        <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;width:35%;">Categoría:</td>
                            <td style="padding:8px 15px;color:#1a6591;font-weight:600;font-size:14px;">' . htmlspecialchars($groupLabel) . '</td></tr>
                        <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;">Asunto:</td>
                            <td style="padding:8px 15px;color:#1a6591;font-weight:700;font-size:14px;">' . htmlspecialchars($title) . '</td></tr>'
                    . ($message ? '<tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;">Detalle:</td><td style="padding:8px 15px;color:#374151;font-size:14px;">' . htmlspecialchars($message) . '</td></tr>' : '') . '
                        <tr><td style="padding:8px 15px;color:#6b7280;font-size:13px;">Fecha:</td>
                            <td style="padding:8px 15px;color:#1a6591;font-size:14px;">' . date('d/m/Y H:i') . '</td></tr>
                    </table>' . $detailLink;
                try {
                    sendEmail($user['email'], "$groupLabel: $title", emailTemplate($groupLabel, $content));
                } catch (Exception $e) {
                    error_log("[Notificaciones] Error email {$user['email']}: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("[Notificaciones] Error: " . $e->getMessage());
    }
}

function generateComplaintNumber(): string {
    return 'DC-' . date('Ymd') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
}

function getStatusBadge(string $status): string {
    $cfg = COMPLAINT_STATUSES[$status] ?? ['label' => $status, 'color' => 'secondary', 'icon' => 'bi-circle'];
    return sprintf('<span class="badge bg-%s"><i class="bi %s me-1"></i>%s</span>', $cfg['color'], $cfg['icon'], $cfg['label']);
}

function getTypeBadge(string $type): string {
    $cfg = COMPLAINT_TYPES[$type] ?? ['label' => $type, 'icon' => 'bi-circle'];
    $ley = $cfg['ley'] ?? '';
    $tooltip = $ley ? ' title="' . htmlspecialchars($ley) . '"' : '';
    return sprintf('<span class="badge bg-dark"%s><i class="bi %s me-1"></i>%s</span>', $tooltip, $cfg['icon'], $cfg['label']);
}

function createComplaint(array $data): array {
    global $pdo;
    try {
        $enc             = getEncryptionService();
        $complaintNumber = generateComplaintNumber();

        $description     = $enc->encryptForDb($data['description'] ?? '');
        $involvedPersons = $enc->encryptForDb($data['involved_persons'] ?? null);
        $evidenceDesc    = $enc->encryptForDb($data['evidence_description'] ?? null);
        $reporterName    = $enc->encryptForDb($data['reporter_name'] ?? null);
        $reporterLastname= $enc->encryptForDb($data['reporter_lastname'] ?? null);
        $reporterEmail   = $enc->encryptForDb($data['reporter_email'] ?? null);
        $reporterPhone   = $enc->encryptForDb($data['reporter_phone'] ?? null);
        $reporterDept    = $enc->encryptForDb($data['reporter_department'] ?? null);
        $accusedName     = $enc->encryptForDb($data['accused_name'] ?? null);
        $accusedNameHmac = $enc->computeSearchHash($data['accused_name'] ?? null);
        $accusedDept     = $enc->encryptForDb($data['accused_department'] ?? null);
        $accusedPos      = $enc->encryptForDb($data['accused_position'] ?? null);
        $witnesses       = $enc->encryptForDb($data['witnesses'] ?? null);
        $incidentLoc     = $enc->encryptForDb($data['incident_location'] ?? null);

        $sql = "INSERT INTO complaints (
            complaint_number, complaint_type,
            description_encrypted, description_nonce,
            involved_persons_encrypted, involved_persons_nonce,
            evidence_description_encrypted, evidence_description_nonce,
            is_anonymous,
            reporter_name_encrypted, reporter_name_nonce,
            reporter_lastname_encrypted, reporter_lastname_nonce,
            reporter_email_encrypted, reporter_email_nonce,
            reporter_phone_encrypted, reporter_phone_nonce,
            reporter_department_encrypted, reporter_department_nonce,
            accused_name_encrypted, accused_name_nonce, accused_name_hmac,
            accused_department_encrypted, accused_department_nonce,
            accused_position_encrypted, accused_position_nonce,
            witnesses_encrypted, witnesses_nonce,
            incident_date,
            incident_location_encrypted, incident_location_nonce,
            status
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'recibida')";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $complaintNumber,              $data['complaint_type'],
            $description['encrypted'],     $description['nonce'],
            $involvedPersons['encrypted'], $involvedPersons['nonce'],
            $evidenceDesc['encrypted'],    $evidenceDesc['nonce'],
            $data['is_anonymous'] ?? 1,
            $reporterName['encrypted'],     $reporterName['nonce'],
            $reporterLastname['encrypted'], $reporterLastname['nonce'],
            $reporterEmail['encrypted'],    $reporterEmail['nonce'],
            $reporterPhone['encrypted'],    $reporterPhone['nonce'],
            $reporterDept['encrypted'],     $reporterDept['nonce'],
            $accusedName['encrypted'],     $accusedName['nonce'],     $accusedNameHmac,
            $accusedDept['encrypted'],     $accusedDept['nonce'],
            $accusedPos['encrypted'],      $accusedPos['nonce'],
            $witnesses['encrypted'],       $witnesses['nonce'],
            $data['incident_date']       ?? null,
            $incidentLoc['encrypted'],     $incidentLoc['nonce'],
        ]);

        $complaintId = $pdo->lastInsertId();

        // Generar e insertar tokens de conflicto de interés
        $conflictTokens = $enc->computeAccusedConflictTokens(array_filter([
            $data['accused_name']       ?? null,
            $data['accused_position']   ?? null,
            $data['accused_department'] ?? null,
        ]));
        if (!empty($conflictTokens)) {
            $tokenStmt = $pdo->prepare("INSERT INTO complaint_conflict_tokens (complaint_id, token_hmac) VALUES (?, ?)");
            foreach ($conflictTokens as $tokenHmac) {
                $tokenStmt->execute([$complaintId, $tokenHmac]);
            }
        }

        addComplaintLog($complaintId, 'creada', 'Denuncia recibida en el sistema', null, false);

        try {
            notifyAdminsNewComplaint($complaintId, $complaintNumber, $data['complaint_type'], (bool)($data['is_anonymous'] ?? 1));
            if (!($data['is_anonymous'] ?? 1) && !empty($data['reporter_email'])) {
                notifyComplainant($data['reporter_email'], $complaintNumber, $data['complaint_type']);
            }
        } catch (Exception $e) {
            error_log("[Denuncias] Error notificaciones: " . $e->getMessage());
        }

        return ['success' => true, 'complaint_number' => $complaintNumber, 'id' => $complaintId];
    } catch (Exception $e) {
        error_log("[Denuncias] Error creando denuncia: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al registrar la denuncia: ' . $e->getMessage()];
    }
}

/**
 * Guardar archivos adjuntos de una denuncia
 */
function saveComplaintAttachments(int $complaintId, array $files): int {
    global $pdo;
    $saved = 0;
    if (empty($files['tmp_name'])) return 0;

    $allowedMime = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp4',
        'audio/aac', 'audio/webm', 'audio/x-m4a',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
        'video/mp4', 'video/quicktime',
    ];
    $maxSize   = 10 * 1024 * 1024; // 10 MB
    $uploadDir = __DIR__ . '/../public/uploads/evidencia/';
    $enc       = getEncryptionService();

    $tmpNames = (array)$files['tmp_name'];
    $names    = (array)$files['name'];
    $errors   = (array)$files['error'];
    $sizes    = (array)$files['size'];

    for ($i = 0; $i < count($tmpNames); $i++) {
        if ($errors[$i] !== UPLOAD_ERR_OK) continue;
        if ($sizes[$i] > $maxSize)          continue;

        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $tmpNames[$i]);
        finfo_close($finfo);
        if (!in_array($mimeType, $allowedMime, true)) continue;

        $ext        = strtolower(pathinfo($names[$i], PATHINFO_EXTENSION));
        $safeExt    = preg_replace('/[^a-z0-9]/', '', $ext);
        $storedName = bin2hex(random_bytes(16)) . ($safeExt ? '.' . $safeExt : '');
        $destPath   = $uploadDir . $storedName;

        if (!move_uploaded_file($tmpNames[$i], $destPath)) continue;

        $encName = $enc->encryptForDb($names[$i]);
        $pdo->prepare(
            'INSERT INTO complaint_attachments (complaint_id, filename, original_name_encrypted, original_name_nonce, file_path, file_type, file_size)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $complaintId,
            $storedName,
            $encName['encrypted'],
            $encName['nonce'],
            'uploads/evidencia/' . $storedName,
            $mimeType,
            $sizes[$i],
        ]);
        $saved++;
    }
    return $saved;
}

function getComplaintDecrypted(int $id): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT c.*, u.name as investigator_name FROM complaints c LEFT JOIN users u ON c.investigator_id = u.id WHERE c.id = ?");
    $stmt->execute([$id]);
    $complaint = $stmt->fetch();
    if (!$complaint) return null;

    $enc = getEncryptionService();
    $complaint['description']          = $enc->decrypt($complaint['description_encrypted'],          $complaint['description_nonce']);
    $complaint['involved_persons']     = $enc->decrypt($complaint['involved_persons_encrypted'],     $complaint['involved_persons_nonce']);
    $complaint['evidence_description'] = $enc->decrypt($complaint['evidence_description_encrypted'], $complaint['evidence_description_nonce']);
    $complaint['reporter_name']        = $enc->decrypt($complaint['reporter_name_encrypted'],        $complaint['reporter_name_nonce']);
    $complaint['reporter_lastname']    = $enc->decrypt($complaint['reporter_lastname_encrypted'] ?? null, $complaint['reporter_lastname_nonce'] ?? null);
    $complaint['reporter_email']       = $enc->decrypt($complaint['reporter_email_encrypted'],       $complaint['reporter_email_nonce']);
    $complaint['reporter_phone']       = $enc->decrypt($complaint['reporter_phone_encrypted'],       $complaint['reporter_phone_nonce']);
    $complaint['reporter_department']  = $enc->decrypt($complaint['reporter_department_encrypted'],  $complaint['reporter_department_nonce']);
    $complaint['accused_name']         = $enc->decrypt($complaint['accused_name_encrypted'],         $complaint['accused_name_nonce']);
    $complaint['accused_department']   = $enc->decrypt($complaint['accused_department_encrypted'],   $complaint['accused_department_nonce']);
    $complaint['accused_position']     = $enc->decrypt($complaint['accused_position_encrypted'],     $complaint['accused_position_nonce']);
    $complaint['witnesses']            = $enc->decrypt($complaint['witnesses_encrypted'],            $complaint['witnesses_nonce']);
    $complaint['incident_location']    = $enc->decrypt($complaint['incident_location_encrypted'],    $complaint['incident_location_nonce']);
    $complaint['resolution']           = $enc->decrypt($complaint['resolution_encrypted'],           $complaint['resolution_nonce']);
    return $complaint;
}

function addComplaintLog(int $complaintId, string $action, ?string $description, ?int $userId, bool $isConfidential = false): void {
    global $pdo;
    $enc        = getEncryptionService();
    $content    = $action . ($description ? ': ' . $description : '');
    $contentEnc = $enc->encryptForDb($content);
    $pdo->prepare("INSERT INTO complaint_logs (complaint_id, content_encrypted, content_nonce, user_id, is_confidential) VALUES (?,?,?,?,?)")
        ->execute([$complaintId, $contentEnc['encrypted'], $contentEnc['nonce'], $userId, $isConfidential ? 1 : 0]);
}

function getComplaintLogs(int $complaintId, bool $includeConfidential = false): array {
    global $pdo;
    $sql = "SELECT cl.*, u.name as user_name FROM complaint_logs cl LEFT JOIN users u ON cl.user_id = u.id WHERE cl.complaint_id = ?";
    if (!$includeConfidential) $sql .= " AND cl.is_confidential = 0";
    $sql .= " ORDER BY cl.created_at ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$complaintId]);
    $logs = $stmt->fetchAll();
    $enc  = getEncryptionService();
    foreach ($logs as &$log) {
        $log['description'] = $enc->decrypt($log['content_encrypted'], $log['content_nonce']);
    }
    return $logs;
}

function addInvestigationNote(int $complaintId, int $userId, string $content, bool $isConfidential = true): void {
    global $pdo;
    $enc        = getEncryptionService();
    $contentEnc = $enc->encryptForDb($content);
    $pdo->prepare("INSERT INTO investigation_notes (complaint_id, user_id, content_encrypted, content_nonce, is_confidential) VALUES (?,?,?,?,?)")
        ->execute([$complaintId, $userId, $contentEnc['encrypted'], $contentEnc['nonce'], $isConfidential ? 1 : 0]);
}

function findComplaintByNumber(string $number): ?array {
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, complaint_number, complaint_type, status, is_anonymous, incident_date, created_at, updated_at, resolved_at FROM complaints WHERE complaint_number = ?");
    $stmt->execute([$number]);
    return $stmt->fetch() ?: null;
}

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
