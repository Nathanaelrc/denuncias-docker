<?php
/**
 * Portal de Denuncias - CRUD de Denuncias
 * Creación, lectura y gestión de denuncias con cifrado extremo a extremo
 */

/**
 * Generar número de denuncia único con verificación de colisión
 */
function generateComplaintNumber(): string
{
    global $pdo;
    $prefix = 'DN-' . date('Ymd') . '-';
    for ($i = 0; $i < 10; $i++) {
        $number = $prefix . str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM complaints WHERE complaint_number = ? LIMIT 1');
            $stmt->execute([$number]);
            if (!$stmt->fetchColumn()) {
                return $number;
            }
        } catch (Exception $e) {
            return $number;
        }
    }
    return $prefix . str_pad(abs(crc32(uniqid('', true))) % 100000, 5, '0', STR_PAD_LEFT);
}

/**
 * Crear denuncia con datos encriptados
 */
function createComplaint(array $data): array
{
    global $pdo;

    try {
        $enc             = getEncryptionService();
        $complaintNumber = generateComplaintNumber();

        $description      = $enc->encryptForDb($data['description'] ?? '');
        $involvedPersons  = $enc->encryptForDb($data['involved_persons'] ?? null);
        $evidenceDesc     = $enc->encryptForDb($data['evidence_description'] ?? null);
        $reporterName     = $enc->encryptForDb($data['reporter_name'] ?? null);
        $reporterLastname = $enc->encryptForDb($data['reporter_lastname'] ?? null);
        $reporterEmail    = $enc->encryptForDb($data['reporter_email'] ?? null);
        $reporterPhone    = $enc->encryptForDb($data['reporter_phone'] ?? null);
        $reporterDept     = $enc->encryptForDb($data['reporter_department'] ?? null);
        $accusedName      = $enc->encryptForDb($data['accused_name'] ?? null);
        $accusedNameHmac  = $enc->computeSearchHash($data['accused_name'] ?? null);
        $accusedDept      = $enc->encryptForDb($data['accused_department'] ?? null);
        $accusedPos       = $enc->encryptForDb($data['accused_position'] ?? null);
        $witnesses        = $enc->encryptForDb($data['witnesses'] ?? null);
        $incidentLocation = $enc->encryptForDb($data['incident_location'] ?? null);

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
            $complaintNumber,
            $data['complaint_type'],
            $description['encrypted'],      $description['nonce'],
            $involvedPersons['encrypted'],  $involvedPersons['nonce'],
            $evidenceDesc['encrypted'],     $evidenceDesc['nonce'],
            $data['is_anonymous'] ?? 1,
            $reporterName['encrypted'],     $reporterName['nonce'],
            $reporterLastname['encrypted'], $reporterLastname['nonce'],
            $reporterEmail['encrypted'],    $reporterEmail['nonce'],
            $reporterPhone['encrypted'],    $reporterPhone['nonce'],
            $reporterDept['encrypted'],     $reporterDept['nonce'],
            $accusedName['encrypted'],      $accusedName['nonce'], $accusedNameHmac,
            $accusedDept['encrypted'],      $accusedDept['nonce'],
            $accusedPos['encrypted'],       $accusedPos['nonce'],
            $witnesses['encrypted'],        $witnesses['nonce'],
            $data['incident_date'] ?? null,
            $incidentLocation['encrypted'], $incidentLocation['nonce'],
        ]);

        $complaintId = $pdo->lastInsertId();

        // Tokens de conflicto de interés
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
            notifyAdminsNewComplaint($complaintNumber, $data['complaint_type'], (bool)($data['is_anonymous'] ?? 1));
            if (!($data['is_anonymous'] ?? 1) && !empty($data['reporter_email'])) {
                notifyComplainant($data['reporter_email'], $complaintNumber, $data['complaint_type']);
            }
        } catch (Throwable $e) {
            error_log("[Denuncias] Error enviando notificaciones: " . $e->getMessage());
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
function saveComplaintAttachments(int $complaintId, array $files): int
{
    global $pdo;
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
    $maxSize   = 10 * 1024 * 1024;
    $uploadDir = __DIR__ . '/../public/uploads/evidencia/';
    $enc       = getEncryptionService();

    $tmpNames = (array)$files['tmp_name'];
    $names    = (array)$files['name'];
    $errors   = (array)$files['error'];
    $sizes    = (array)$files['size'];
    $saved    = 0;

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
            $encName['encrypted'], $encName['nonce'],
            'uploads/evidencia/' . $storedName,
            $mimeType,
            $sizes[$i],
        ]);
        $saved++;
    }
    return $saved;
}

/**
 * Obtener denuncia con todos los campos desencriptados (solo admin/investigador)
 */
function getComplaintDecrypted(int $id): ?array
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT c.*, u.name as investigator_name FROM complaints c LEFT JOIN users u ON c.investigator_id = u.id WHERE c.id = ?");
    $stmt->execute([$id]);
    $complaint = $stmt->fetch();
    if (!$complaint) return null;

    $enc = getEncryptionService();
    $complaint['description']         = $enc->decrypt($complaint['description_encrypted'],         $complaint['description_nonce']);
    $complaint['involved_persons']    = $enc->decrypt($complaint['involved_persons_encrypted'],    $complaint['involved_persons_nonce']);
    $complaint['evidence_description']= $enc->decrypt($complaint['evidence_description_encrypted'],$complaint['evidence_description_nonce']);
    $complaint['reporter_name']       = $enc->decrypt($complaint['reporter_name_encrypted'],       $complaint['reporter_name_nonce']);
    $complaint['reporter_lastname']   = $enc->decrypt($complaint['reporter_lastname_encrypted']  ?? null, $complaint['reporter_lastname_nonce']  ?? null);
    $complaint['reporter_email']      = $enc->decrypt($complaint['reporter_email_encrypted'],      $complaint['reporter_email_nonce']);
    $complaint['reporter_phone']      = $enc->decrypt($complaint['reporter_phone_encrypted'],      $complaint['reporter_phone_nonce']);
    $complaint['reporter_department'] = $enc->decrypt($complaint['reporter_department_encrypted'], $complaint['reporter_department_nonce']);
    $complaint['accused_name']        = $enc->decrypt($complaint['accused_name_encrypted'],        $complaint['accused_name_nonce']);
    $complaint['accused_department']  = $enc->decrypt($complaint['accused_department_encrypted'],  $complaint['accused_department_nonce']);
    $complaint['accused_position']    = $enc->decrypt($complaint['accused_position_encrypted'],    $complaint['accused_position_nonce']);
    $complaint['witnesses']           = $enc->decrypt($complaint['witnesses_encrypted'],           $complaint['witnesses_nonce']);
    $complaint['incident_location']   = $enc->decrypt($complaint['incident_location_encrypted'],   $complaint['incident_location_nonce']);
    $complaint['resolution']          = $enc->decrypt($complaint['resolution_encrypted'],          $complaint['resolution_nonce']);

    return $complaint;
}

/**
 * Agregar entrada al log de una denuncia (encriptada)
 */
function addComplaintLog(int $complaintId, string $action, ?string $description, ?int $userId, bool $isConfidential = false): void
{
    global $pdo;
    static $usesActionSchema = null;

    if ($usesActionSchema === null) {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([DB_NAME, 'complaint_logs', 'action']);
            $usesActionSchema = ((int)$stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            $usesActionSchema = false;
        }
    }

    $enc = getEncryptionService();

    if ($usesActionSchema) {
        $descEnc = $enc->encryptForDb($description);
        $pdo->prepare("INSERT INTO complaint_logs (complaint_id, action, description_encrypted, description_nonce, user_id, is_confidential) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$complaintId, $action, $descEnc['encrypted'], $descEnc['nonce'], $userId, $isConfidential ? 1 : 0]);
        return;
    }

    // Compatibilidad con esquema legacy sin columna action.
    $legacyMessage = trim(($action !== '' ? '[' . $action . '] ' : '') . (string)($description ?? ''));
    $contentEnc = $enc->encryptForDb($legacyMessage);
    $pdo->prepare("INSERT INTO complaint_logs (complaint_id, user_id, content_encrypted, content_nonce, is_confidential) VALUES (?, ?, ?, ?, ?)")
        ->execute([$complaintId, $userId, $contentEnc['encrypted'], $contentEnc['nonce'], $isConfidential ? 1 : 0]);
}

/**
 * Obtener logs desencriptados de una denuncia
 */
function getComplaintLogs(int $complaintId, bool $includeConfidential = false): array
{
    global $pdo;
    static $usesActionSchema = null;

    if ($usesActionSchema === null) {
        try {
            $stmt = $pdo->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
            $stmt->execute([DB_NAME, 'complaint_logs', 'action']);
            $usesActionSchema = ((int)$stmt->fetchColumn() > 0);
        } catch (Throwable $e) {
            $usesActionSchema = false;
        }
    }

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
        if ($usesActionSchema) {
            $log['description'] = $enc->decrypt($log['description_encrypted'] ?? null, $log['description_nonce'] ?? null);
            continue;
        }

        $decoded = $enc->decrypt($log['content_encrypted'] ?? null, $log['content_nonce'] ?? null);
        $log['description'] = $decoded;
        if (empty($log['action']) && is_string($decoded) && preg_match('/^\[([^\]]+)\]\s*(.*)$/', $decoded, $m)) {
            $log['action'] = $m[1];
            $log['description'] = $m[2];
        }
    }

    return $logs;
}

/**
 * Agregar nota de investigación (encriptada, confidencial por defecto)
 */
function addInvestigationNote(int $complaintId, int $userId, string $content, bool $isConfidential = true): void
{
    global $pdo;
    $enc        = getEncryptionService();
    $contentEnc = $enc->encryptForDb($content);

    $pdo->prepare("INSERT INTO investigation_notes (complaint_id, user_id, content_encrypted, content_nonce, is_confidential) VALUES (?, ?, ?, ?, ?)")
        ->execute([$complaintId, $userId, $contentEnc['encrypted'], $contentEnc['nonce'], $isConfidential ? 1 : 0]);
}

/**
 * Buscar denuncia por número (para seguimiento público — sin datos sensibles)
 */
function findComplaintByNumber(string $number): ?array
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT id, complaint_number, complaint_type, status, is_anonymous, incident_date, created_at, updated_at, resolved_at FROM complaints WHERE complaint_number = ?");
    $stmt->execute([$number]);
    return $stmt->fetch() ?: null;
}
