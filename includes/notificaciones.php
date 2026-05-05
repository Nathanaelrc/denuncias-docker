<?php
/**
 * Portal de Denuncias - Notificaciones
 * Filtros de visibilidad y envío de notificaciones a suscriptores
 */

/**
 * Asegura la tabla de cola de correos para envíos asíncronos.
 */
function ensureEmailQueueTableExists(PDO $pdo): void
{
    static $initialized = false;
    if ($initialized) {
        return;
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS email_queue (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            recipient_email VARCHAR(190) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            html_body MEDIUMTEXT NOT NULL,
            attempts INT NOT NULL DEFAULT 0,
            max_attempts INT NOT NULL DEFAULT 5,
            status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
            next_attempt_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
            last_error TEXT DEFAULT NULL,
            locked_at TIMESTAMP NULL,
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status_next (status, next_attempt_at),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $initialized = true;
}

/**
 * Encola un correo para procesarlo de forma asíncrona.
 */
function enqueueEmail(string $to, string $subject, string $htmlBody, int $maxAttempts = 5): bool
{
    global $pdo;

    try {
        ensureEmailQueueTableExists($pdo);
        $stmt = $pdo->prepare(
            "INSERT INTO email_queue (recipient_email, subject, html_body, max_attempts, status, next_attempt_at)
             VALUES (?, ?, ?, ?, 'pending', NOW())"
        );
        $stmt->execute([$to, $subject, $htmlBody, max(1, $maxAttempts)]);
        return true;
    } catch (Throwable $e) {
        error_log('[Notificaciones] No se pudo encolar correo: ' . $e->getMessage());
        return false;
    }
}

/**
 * Procesa correos pendientes de la cola. Devuelve métricas del ciclo.
 */
function processEmailQueue(int $limit = 25): array
{
    global $pdo;

    $stats = ['picked' => 0, 'sent' => 0, 'failed' => 0];

    try {
        ensureEmailQueueTableExists($pdo);
        $limit = max(1, min($limit, 200));

        $pdo->beginTransaction();
        $select = $pdo->prepare(
            "SELECT id, recipient_email, subject, html_body, attempts, max_attempts
             FROM email_queue
             WHERE status = 'pending'
               AND (next_attempt_at IS NULL OR next_attempt_at <= NOW())
             ORDER BY id ASC
             LIMIT {$limit}
             FOR UPDATE SKIP LOCKED"
        );
        $select->execute();
        $rows = $select->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            $pdo->commit();
            return $stats;
        }

        $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $markProcessing = $pdo->prepare(
            "UPDATE email_queue
             SET status = 'processing', locked_at = NOW(), updated_at = NOW()
             WHERE id IN ($placeholders)"
        );
        $markProcessing->execute($ids);
        $pdo->commit();

        $stats['picked'] = count($rows);

        $markSent = $pdo->prepare(
            "UPDATE email_queue
             SET status = 'sent', sent_at = NOW(), last_error = NULL, locked_at = NULL, updated_at = NOW()
             WHERE id = ?"
        );

        $markRetry = $pdo->prepare(
            "UPDATE email_queue
             SET status = 'pending', attempts = ?, next_attempt_at = DATE_ADD(NOW(), INTERVAL ? SECOND),
                 last_error = ?, locked_at = NULL, updated_at = NOW()
             WHERE id = ?"
        );

        $markFailed = $pdo->prepare(
            "UPDATE email_queue
             SET status = 'failed', attempts = ?, next_attempt_at = NULL,
                 last_error = ?, locked_at = NULL, updated_at = NOW()
             WHERE id = ?"
        );

        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $attempts = (int)$row['attempts'];
            $maxAttempts = max(1, (int)$row['max_attempts']);

            try {
                $ok = sendEmail($row['recipient_email'], $row['subject'], $row['html_body']);
                if ($ok) {
                    $markSent->execute([$id]);
                    $stats['sent']++;
                    continue;
                }

                $nextAttempts = $attempts + 1;
                $errorMessage = 'sendEmail devolvió false';
                if ($nextAttempts >= $maxAttempts) {
                    $markFailed->execute([$nextAttempts, $errorMessage, $id]);
                    $stats['failed']++;
                } else {
                    $delaySeconds = min(3600, (int)pow(2, $nextAttempts) * 60);
                    $markRetry->execute([$nextAttempts, $delaySeconds, $errorMessage, $id]);
                }
            } catch (Throwable $e) {
                $nextAttempts = $attempts + 1;
                $errorMessage = mb_substr($e->getMessage(), 0, 2000);
                if ($nextAttempts >= $maxAttempts) {
                    $markFailed->execute([$nextAttempts, $errorMessage, $id]);
                    $stats['failed']++;
                } else {
                    $delaySeconds = min(3600, (int)pow(2, $nextAttempts) * 60);
                    $markRetry->execute([$nextAttempts, $delaySeconds, $errorMessage, $id]);
                }
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[Notificaciones] Error procesando cola de correos: ' . $e->getMessage());
    }

    return $stats;
}

/**
 * Genera un filtro para ocultar notificaciones ligadas a denuncias a usuarios sin acceso,
 * o para excluir denuncias en conflicto cuando el usuario sí es investigador.
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

/**
 * Enviar notificación a usuarios suscritos a un grupo
 */
function sendNotification(string $groupSlug, string $title, string $message = '', string $entityType = null, int $entityId = null): void
{
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

        $ins = $pdo->prepare("
            INSERT INTO notifications (user_id, group_slug, title, message, entity_type, entity_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $groupLabels = [
            'denuncia_creada' => 'Nueva Denuncia',
            'asignacion'      => 'Asignación de Caso',
            'investigacion'   => 'Investigación en Curso',
            'resuelta'        => 'Denuncia Resuelta',
            'cerrada'         => 'Denuncia Cerrada',
        ];
        $groupLabel = $groupLabels[$groupSlug] ?? 'Notificación';

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
                    $appUrl = getenv('APP_URL') ?: 'http://localhost:8091';
                    $detailLink = '<p style="margin-top: 20px; text-align: center;">
                        <a href="' . htmlspecialchars($appUrl) . '/detalle_denuncia?id=' . (int)$entityId . '"
                           style="background: #1a6591; color: #ffffff; padding: 12px 28px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 14px; display: inline-block;">
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
                            <td style="padding: 8px 15px; color: #1a6591; font-weight: 600; font-size: 14px;">' . htmlspecialchars($groupLabel) . '</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Asunto:</td>
                            <td style="padding: 8px 15px; color: #1a6591; font-weight: 700; font-size: 14px;">' . htmlspecialchars($title) . '</td>
                        </tr>' . ($message ? '
                        <tr>
                            <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Detalle:</td>
                            <td style="padding: 8px 15px; color: #374151; font-size: 14px;">' . htmlspecialchars($message) . '</td>
                        </tr>' : '') . '
                        <tr>
                            <td style="padding: 8px 15px; color: #6b7280; font-size: 13px;">Fecha:</td>
                            <td style="padding: 8px 15px; color: #1a6591; font-size: 14px;">' . date('d/m/Y H:i') . '</td>
                        </tr>
                    </table>
                    ' . $detailLink . '
                    <p style="color: #9ca3af; font-size: 12px; margin-top: 20px;">Puedes gestionar tus suscripciones desde el panel de administración.</p>';

                try {
                    $queued = enqueueEmail(
                        $user['email'],
                        "$groupLabel: $title",
                        emailTemplate($groupLabel, $emailContent)
                    );

                    // Fallback inmediato si no se pudo persistir en cola
                    if (!$queued) {
                        sendEmail($user['email'], "$groupLabel: $title", emailTemplate($groupLabel, $emailContent));
                    }
                } catch (Exception $e) {
                    error_log("[Notificaciones] Error enviando correo a {$user['email']}: " . $e->getMessage());
                }
            }
        }
    } catch (Exception $e) {
        error_log("[Notificaciones] Error: " . $e->getMessage());
    }
}
