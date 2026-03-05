<?php
/**
 * Portal de Denuncias - Utilidades
 */

/**
 * Enviar notificación a usuarios suscritos a un grupo
 */
function sendNotification(string $groupSlug, string $title, string $message = '', string $entityType = null, int $entityId = null): void {
    global $pdo;
    try {
        // Obtener usuarios suscritos a este grupo o a "todas" con su email
        $stmt = $pdo->prepare("
            SELECT DISTINCT u.id, u.email, u.name
            FROM notification_subscriptions ns
            JOIN notification_groups ng ON ns.group_id = ng.id
            JOIN users u ON ns.user_id = u.id
            WHERE ng.slug IN (?, 'todas') AND ng.is_active = 1 AND u.is_active = 1
        ");
        $stmt->execute([$groupSlug]);
        $subscribers = $stmt->fetchAll();

        if (empty($subscribers)) return;

        $ins = $pdo->prepare("
            INSERT INTO notifications (user_id, group_slug, title, message, entity_type, entity_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        // Slugs a etiquetas legibles para el correo
        $groupLabels = [
            'denuncia_creada' => 'Nueva Denuncia',
            'asignacion' => 'Asignación de Caso',
            'investigacion' => 'Investigación en Curso',
            'resuelta' => 'Denuncia Resuelta',
            'cerrada' => 'Denuncia Cerrada',
        ];
        $groupLabel = $groupLabels[$groupSlug] ?? 'Notificación';

        foreach ($subscribers as $user) {
            // Guardar notificación en BD
            $ins->execute([$user['id'], $groupSlug, $title, $message, $entityType, $entityId]);

            // Enviar correo electrónico
            if (!empty($user['email'])) {
                $detailLink = '';
                if ($entityType === 'complaint' && $entityId) {
                    $appUrl = getenv('APP_URL') ?: 'http://localhost:8091';
                    $detailLink = '<p style="margin-top: 20px; text-align: center;">
                        <a href="' . htmlspecialchars($appUrl) . '/detalle_denuncia?id=' . (int)$entityId . '" 
                           style="background: #0a2540; color: #ffffff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
                            Ver Detalle
                        </a>
                    </p>';
                }

                $emailContent = '
                    <p style="color: #374151; font-size: 14px; line-height: 1.7;">Hola <strong>' . htmlspecialchars($user['name']) . '</strong>,</p>
                    <p style="color: #374151; font-size: 14px; line-height: 1.7;">Tienes una nueva notificación en el Canal de Denuncias:</p>
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background: #f8fafc; border-radius: 8px; padding: 20px; margin: 15px 0;">
                        <tr>
                            <td style="padding: 8px 15px; color: #6b7280; font-size: 13px; width: 35%;">Categoría:</td>
                            <td style="padding: 8px 15px; color: #0a2540; font-weight: 600; font-size: 14px;">' . htmlspecialchars($groupLabel) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Asunto:</td>
                            <td style="padding: 8px 15px; color: #0a2540; font-weight: 700; font-size: 14px;">' . htmlspecialchars($title) . '</td>
                        </tr>' . ($message ? '
                        <tr>
                            <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Detalle:</td>
                            <td style="padding: 8px 15px; color: #374151; font-size: 14px;">' . htmlspecialchars($message) . '</td>
                        </tr>' : '') . '
                        <tr>
                            <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Fecha:</td>
                            <td style="padding: 8px 15px; color: #0a2540; font-size: 14px;">' . date('d/m/Y H:i') . '</td>
                        </tr>
                    </table>
                    ' . $detailLink . '
                    <p style="color: #9ca3af; font-size: 12px; margin-top: 20px;">Puedes gestionar tus suscripciones de notificación desde el panel de administración.</p>';

                try {
                    sendEmail($user['email'], "$groupLabel: $title", emailTemplate($groupLabel, $emailContent));
                } catch (Exception $e) {
                    error_log("[Notificaciones] Error enviando correo a {$user['email']}: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("[Notificaciones] Error: " . $e->getMessage());
    }
}

/**
 * Generar número de denuncia único
 */
function generateComplaintNumber(): string {
    return 'DN-' . date('Ymd') . '-' . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
}

/**
 * Obtener etiqueta de estado
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
 * Obtener etiqueta de tipo
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
 * Crear denuncia con datos encriptados
 */
function createComplaint(array $data): array {
    global $pdo;

    try {
        $enc = getEncryptionService();
        $complaintNumber = generateComplaintNumber();

        // Encriptar campos sensibles
        $description = $enc->encryptForDb($data['description'] ?? '');
        $involvedPersons = $enc->encryptForDb($data['involved_persons'] ?? null);
        $evidenceDesc = $enc->encryptForDb($data['evidence_description'] ?? null);
        $reporterName = $enc->encryptForDb($data['reporter_name'] ?? null);
        $reporterEmail = $enc->encryptForDb($data['reporter_email'] ?? null);
        $reporterPhone = $enc->encryptForDb($data['reporter_phone'] ?? null);
        $reporterDept = $enc->encryptForDb($data['reporter_department'] ?? null);
        $accusedName = $enc->encryptForDb($data['accused_name'] ?? null);
        $accusedDept = $enc->encryptForDb($data['accused_department'] ?? null);
        $accusedPos = $enc->encryptForDb($data['accused_position'] ?? null);
        $witnesses = $enc->encryptForDb($data['witnesses'] ?? null);
        $incidentLocation = $enc->encryptForDb($data['incident_location'] ?? null);

        $sql = "INSERT INTO complaints (
            complaint_number, complaint_type,
            description_encrypted, description_nonce,
            involved_persons_encrypted, involved_persons_nonce,
            evidence_description_encrypted, evidence_description_nonce,
            is_anonymous,
            reporter_name_encrypted, reporter_name_nonce,
            reporter_email_encrypted, reporter_email_nonce,
            reporter_phone_encrypted, reporter_phone_nonce,
            reporter_department_encrypted, reporter_department_nonce,
            accused_name_encrypted, accused_name_nonce,
            accused_department_encrypted, accused_department_nonce,
            accused_position_encrypted, accused_position_nonce,
            witnesses_encrypted, witnesses_nonce,
            incident_date,
            incident_location_encrypted, incident_location_nonce,
            status
        ) VALUES (
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?, ?,
            ?,
            ?, ?,
            'recibida'
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $complaintNumber,
            $data['complaint_type'],
            $description['encrypted'], $description['nonce'],
            $involvedPersons['encrypted'], $involvedPersons['nonce'],
            $evidenceDesc['encrypted'], $evidenceDesc['nonce'],
            $data['is_anonymous'] ?? 1,
            $reporterName['encrypted'], $reporterName['nonce'],
            $reporterEmail['encrypted'], $reporterEmail['nonce'],
            $reporterPhone['encrypted'], $reporterPhone['nonce'],
            $reporterDept['encrypted'], $reporterDept['nonce'],
            $accusedName['encrypted'], $accusedName['nonce'],
            $accusedDept['encrypted'], $accusedDept['nonce'],
            $accusedPos['encrypted'], $accusedPos['nonce'],
            $witnesses['encrypted'], $witnesses['nonce'],
            $data['incident_date'] ?? null,
            $incidentLocation['encrypted'], $incidentLocation['nonce'],
        ]);

        $complaintId = $pdo->lastInsertId();

        // Log inicial (encriptado)
        addComplaintLog($complaintId, 'creada', 'Denuncia recibida en el sistema', null, false);

        // Enviar notificaciones por correo
        try {
            // Notificar a administradores e investigadores
            notifyAdminsNewComplaint($complaintNumber, $data['complaint_type'], (bool)($data['is_anonymous'] ?? 1));

            // Notificar al denunciante si proporcionó email
            if (!($data['is_anonymous'] ?? 1) && !empty($data['reporter_email'])) {
                notifyComplainant($data['reporter_email'], $complaintNumber, $data['complaint_type']);
            }
        } catch (Exception $e) {
            error_log("[Denuncias] Error enviando notificaciones: " . $e->getMessage());
            // No falla la creación de la denuncia si el correo falla
        }

        return ['success' => true, 'complaint_number' => $complaintNumber, 'id' => $complaintId];

    } catch (Exception $e) {
        error_log("[Denuncias] Error creando denuncia: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error al registrar la denuncia: ' . $e->getMessage()];
    }
}

/**
 * Obtener denuncia desencriptada (solo admin/investigador)
 */
function getComplaintDecrypted(int $id): ?array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT c.*, u.name as investigator_name FROM complaints c LEFT JOIN users u ON c.investigator_id = u.id WHERE c.id = ?");
    $stmt->execute([$id]);
    $complaint = $stmt->fetch();

    if (!$complaint) return null;

    $enc = getEncryptionService();

    // Desencriptar todos los campos
    $complaint['description'] = $enc->decrypt($complaint['description_encrypted'], $complaint['description_nonce']);
    $complaint['involved_persons'] = $enc->decrypt($complaint['involved_persons_encrypted'], $complaint['involved_persons_nonce']);
    $complaint['evidence_description'] = $enc->decrypt($complaint['evidence_description_encrypted'], $complaint['evidence_description_nonce']);
    $complaint['reporter_name'] = $enc->decrypt($complaint['reporter_name_encrypted'], $complaint['reporter_name_nonce']);
    $complaint['reporter_email'] = $enc->decrypt($complaint['reporter_email_encrypted'], $complaint['reporter_email_nonce']);
    $complaint['reporter_phone'] = $enc->decrypt($complaint['reporter_phone_encrypted'], $complaint['reporter_phone_nonce']);
    $complaint['reporter_department'] = $enc->decrypt($complaint['reporter_department_encrypted'], $complaint['reporter_department_nonce']);
    $complaint['accused_name'] = $enc->decrypt($complaint['accused_name_encrypted'], $complaint['accused_name_nonce']);
    $complaint['accused_department'] = $enc->decrypt($complaint['accused_department_encrypted'], $complaint['accused_department_nonce']);
    $complaint['accused_position'] = $enc->decrypt($complaint['accused_position_encrypted'], $complaint['accused_position_nonce']);
    $complaint['witnesses'] = $enc->decrypt($complaint['witnesses_encrypted'], $complaint['witnesses_nonce']);
    $complaint['incident_location'] = $enc->decrypt($complaint['incident_location_encrypted'], $complaint['incident_location_nonce']);
    $complaint['resolution'] = $enc->decrypt($complaint['resolution_encrypted'], $complaint['resolution_nonce']);

    return $complaint;
}

/**
 * Agregar log a denuncia
 */
function addComplaintLog(int $complaintId, string $action, ?string $description, ?int $userId, bool $isConfidential = false): void {
    global $pdo;
    $enc = getEncryptionService();
    $descEnc = $enc->encryptForDb($description);

    $stmt = $pdo->prepare("INSERT INTO complaint_logs (complaint_id, action, description_encrypted, description_nonce, user_id, is_confidential) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$complaintId, $action, $descEnc['encrypted'], $descEnc['nonce'], $userId, $isConfidential ? 1 : 0]);
}

/**
 * Obtener logs desencriptados de una denuncia
 */
function getComplaintLogs(int $complaintId, bool $includeConfidential = false): array {
    global $pdo;

    $sql = "SELECT cl.*, u.name as user_name FROM complaint_logs cl LEFT JOIN users u ON cl.user_id = u.id WHERE cl.complaint_id = ?";
    if (!$includeConfidential) {
        $sql .= " AND cl.is_confidential = 0";
    }
    $sql .= " ORDER BY cl.created_at ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$complaintId]);
    $logs = $stmt->fetchAll();

    $enc = getEncryptionService();
    foreach ($logs as &$log) {
        $log['description'] = $enc->decrypt($log['description_encrypted'], $log['description_nonce']);
    }

    return $logs;
}

/**
 * Agregar nota de investigación (encriptada)
 */
function addInvestigationNote(int $complaintId, int $userId, string $content, bool $isConfidential = true): void {
    global $pdo;
    $enc = getEncryptionService();
    $contentEnc = $enc->encryptForDb($content);

    $stmt = $pdo->prepare("INSERT INTO investigation_notes (complaint_id, user_id, content_encrypted, content_nonce, is_confidential) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$complaintId, $userId, $contentEnc['encrypted'], $contentEnc['nonce'], $isConfidential ? 1 : 0]);
}

/**
 * Buscar denuncia por número (para seguimiento público)
 */
function findComplaintByNumber(string $number): ?array {
    global $pdo;

    $stmt = $pdo->prepare("SELECT id, complaint_number, complaint_type, status, is_anonymous, incident_date, created_at, updated_at, resolved_at FROM complaints WHERE complaint_number = ?");
    $stmt->execute([$number]);
    return $stmt->fetch() ?: null;
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
