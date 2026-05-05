<?php
/**
 * Portal Ciudadano de Denuncias - Detalle de Denuncia
 * Accesible por superadmin, investigador (editable) y viewer (solo lectura)
 */
$pageTitle = 'Detalle de Denuncia';
require_once __DIR__ . '/../includes/bootstrap.php';
requireComplaintAccess();

$user = getCurrentUser();
$isSuperAdmin = ($user['role'] ?? null) === ROLE_SUPERADMIN;
$hasAreaSupport = hasAreaAssignmentSupport();
$canModify = $isSuperAdmin;                // Solo superadmin puede modificar
$canDelete = canDeleteComplaints($user);   // solo superadmin
$canDecryptData = canDecrypt($user);       // superadmin + investigador

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect('/denuncias_admin', 'Denuncia no especificada.', 'danger');
}

$complaint = getComplaintDecrypted($id);
if (!$complaint) {
    redirect('/denuncias_admin', 'Denuncia no encontrada.', 'danger');
}

if ($hasAreaSupport && !isComplaintInUserArea($complaint, $user)) {
    redirect('/denuncias_admin', 'No tienes acceso a esta denuncia (área no asignada para tu perfil).', 'danger');
}

// Protección de conflicto de interés: ningún usuario revisor puede ver denuncias en su contra.
if (isComplaintConflict($id, $user)) {
    redirect('/denuncias_admin', 'No tienes acceso a esta denuncia (conflicto de interés).', 'danger');
}

$logMsg = $canDecryptData ? 'Acceso a denuncia (datos sensibles)' : 'Acceso a denuncia (modo lectura)';
logActivity($_SESSION['user_id'], 'ver_denuncia', 'complaint', $id, $logMsg);

// Acciones POST: solo para roles con permisos de modificación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canModify) {
    redirect('/detalle_denuncia?id=' . $id, 'No tienes permisos para modificar esta denuncia.', 'danger');
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_complaint') {
        if (!$canDelete) {
            redirect('/detalle_denuncia?id=' . $id, 'No tienes permisos para eliminar esta denuncia.', 'danger');
        }

        try {
            $pdo->prepare('DELETE FROM complaints WHERE id = ?')->execute([$id]);
            logActivity($_SESSION['user_id'], 'eliminar_denuncia', 'complaint', $id, 'Denuncia eliminada por superadmin');
            redirect('/denuncias_admin', 'Denuncia eliminada correctamente.', 'success');
        } catch (Exception $e) {
            error_log('[Denuncias Generales] Error eliminando denuncia: ' . $e->getMessage());
            redirect('/detalle_denuncia?id=' . $id, 'No se pudo eliminar la denuncia.', 'danger');
        }
    }

    if ($action === 'change_status') {
        $newStatus = sanitize($_POST['new_status'] ?? '');
        if (array_key_exists($newStatus, COMPLAINT_STATUSES)) {
            $sql = "UPDATE complaints SET status = ?";
            $paramsList = [$newStatus];

            if ($newStatus === 'resuelta') {
                $sql .= ", resolved_at = NOW()";
            }

            $sql .= " WHERE id = ?";
            $paramsList[] = $id;

            $pdo->prepare($sql)->execute($paramsList);
            addComplaintLog($id, 'cambio_estado', "Estado cambiado a: " . COMPLAINT_STATUSES[$newStatus]['label'], $_SESSION['user_id']);
            logActivity($_SESSION['user_id'], 'cambio_estado', 'complaint', $id, "Nuevo estado: $newStatus");

            try {
                notifyStatusChange($id, $newStatus);
            } catch (Exception $e) {
                error_log("[Denuncias Generales] Error notificando cambio de estado: " . $e->getMessage());
            }

            $statusLabel = COMPLAINT_STATUSES[$newStatus]['label'] ?? $newStatus;
            $complaintNum = $complaint['complaint_number'] ?? "#$id";
            if (in_array($newStatus, ['resuelta'])) {
                sendNotification('resuelta', "Denuncia resuelta: $complaintNum", "Estado: $statusLabel", 'complaint', $id);
            } elseif (in_array($newStatus, ['desestimada', 'archivada'])) {
                sendNotification('cerrada', "Denuncia cerrada: $complaintNum", "Estado: $statusLabel", 'complaint', $id);
            } elseif ($newStatus === 'en_investigacion') {
                sendNotification('investigacion', "Investigación iniciada: $complaintNum", "Estado: $statusLabel", 'complaint', $id);
            }

            redirect("/detalle_denuncia?id=$id", 'Estado actualizado correctamente.');
        }
    }

    if ($action === 'assign_investigator') {
        if (!$isSuperAdmin) {
            redirect("/detalle_denuncia?id=$id", 'Solo el superadmin puede reasignar delegados.', 'danger');
        }
        $investigatorId = (int)($_POST['investigator_id'] ?? 0);
        $pdo->prepare("UPDATE complaints SET investigator_id = ?, assigned_at = NOW(), status = 'en_investigacion' WHERE id = ?")
            ->execute([$investigatorId ?: null, $id]);
        addComplaintLog($id, 'asignacion', 'Investigador asignado al caso', $_SESSION['user_id']);
        logActivity($_SESSION['user_id'], 'asignar_investigador', 'complaint', $id, "Investigador ID: $investigatorId");
        $complaintNum = $complaint['complaint_number'] ?? "#$id";
        sendNotification('asignacion', "Caso asignado: $complaintNum", 'Un investigador ha sido asignado a este caso', 'complaint', $id);
        redirect("/detalle_denuncia?id=$id", 'Investigador asignado.');
    }

    if ($action === 'assign_area') {
        if (!$hasAreaSupport) {
            redirect("/detalle_denuncia?id=$id", 'La asignación por área aún no está habilitada en base de datos.', 'danger');
        }

        if (!$isSuperAdmin) {
            redirect("/detalle_denuncia?id=$id", 'Solo el superadmin puede asignar áreas de investigación.', 'danger');
        }

        $newArea = normalizeInvestigationArea($_POST['assigned_area'] ?? null);
        if (!$newArea) {
            redirect("/detalle_denuncia?id=$id", 'Debes seleccionar un área válida.', 'danger');
        }

        $pdo->prepare("UPDATE complaints SET assigned_area = ?, status = 'en_investigacion', assigned_at = NOW() WHERE id = ?")
            ->execute([$newArea, $id]);

        $areaLabel = INVESTIGATION_AREAS[$newArea] ?? $newArea;
        addComplaintLog($id, 'asignacion_area', "Área asignada al caso: {$areaLabel}", $_SESSION['user_id']);
        logActivity($_SESSION['user_id'], 'asignar_area', 'complaint', $id, "Área: {$newArea}");

        $complaintNum = $complaint['complaint_number'] ?? "#$id";
        sendNotification('asignacion', "Caso asignado por área: $complaintNum", "Área designada: {$areaLabel}", 'complaint', $id);
        redirect("/detalle_denuncia?id=$id", 'Área de investigación asignada correctamente.');
    }

    if ($action === 'change_priority') {
        $newPriority = sanitize($_POST['new_priority'] ?? '');
        if (in_array($newPriority, ['normal', 'alta', 'urgente'])) {
            $pdo->prepare("UPDATE complaints SET priority = ? WHERE id = ?")->execute([$newPriority, $id]);
            addComplaintLog($id, 'cambio_prioridad', "Prioridad cambiada a: $newPriority", $_SESSION['user_id']);
            redirect("/detalle_denuncia?id=$id", 'Prioridad actualizada.');
        }
    }

    if ($action === 'add_note') {
        $noteContent = trim($_POST['note_content'] ?? '');
        $isConfidential = isset($_POST['is_confidential']) ? true : false;
        if (!empty($noteContent)) {
            addInvestigationNote($id, $_SESSION['user_id'], $noteContent, $isConfidential);
            addComplaintLog($id, 'nota_agregada', 'Se agregó una nota de investigación', $_SESSION['user_id'], $isConfidential);
            logActivity($_SESSION['user_id'], 'agregar_nota', 'complaint', $id, '');
            redirect("/detalle_denuncia?id=$id", 'Nota agregada.');
        }
    }

    if ($action === 'add_resolution') {
        $resolution = trim($_POST['resolution'] ?? '');
        if (!empty($resolution)) {
            $enc = getEncryptionService();
            $resEnc = $enc->encryptForDb($resolution);
            $pdo->prepare("UPDATE complaints SET resolution_encrypted = ?, resolution_nonce = ?, status = 'resuelta', resolved_at = NOW() WHERE id = ?")
                ->execute([$resEnc['encrypted'], $resEnc['nonce'], $id]);
            addComplaintLog($id, 'resolucion', 'Se agregó resolución al caso', $_SESSION['user_id']);
            redirect("/detalle_denuncia?id=$id", 'Resolución registrada.');
        }
    }
}

// Refrescar datos
$complaint = getComplaintDecrypted($id);
$logs = getComplaintLogs($id, canDecrypt());

// Notas de investigación
$notesStmt = $pdo->prepare("SELECT n.*, u.name as user_name FROM investigation_notes n LEFT JOIN users u ON n.user_id = u.id WHERE n.complaint_id = ? ORDER BY n.created_at DESC");
$notesStmt->execute([$id]);
$notes = $notesStmt->fetchAll();
$enc = getEncryptionService();

// Investigadores disponibles
$investigators = $pdo->query(
    $hasAreaSupport
        ? "SELECT id, name, position, investigator_area FROM users WHERE role = 'investigador' AND is_active = 1"
        : "SELECT id, name, position, NULL AS investigator_area FROM users WHERE role = 'investigador' AND is_active = 1"
)->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<style>
.action-panel .action-section {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 12px;
}

.action-panel .section-title {
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    text-transform: uppercase;
    color: #334155;
    margin-bottom: 8px;
}

.action-panel .form-select,
.action-panel .form-control {
    background: #ffffff;
}

.action-panel .form-select {
    color: #0f172a !important;
    background-color: #ffffff !important;
}

.action-panel .form-select:focus {
    color: #0f172a !important;
}

.action-panel .form-select option {
    color: #0f172a !important;
    background-color: #ffffff !important;
}

.action-panel .form-select option:checked {
    color: #ffffff !important;
    background: #1a6591 linear-gradient(0deg, #1a6591, #1a6591) !important;
}
</style>

<div class="main-content">
    <?php $flash = getFlashMessage(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <a href="/denuncias_admin" class="text-muted text-decoration-none small"><i class="bi bi-arrow-left me-1"></i>Volver</a>
            <h4 class="fw-bold text-dark mb-0 mt-1">
                <i class="bi bi-file-earmark-lock me-2"></i><?= htmlspecialchars($complaint['complaint_number']) ?>
            </h4>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <?= getStatusBadge($complaint['status']) ?>
            <span class="badge bg-<?= $complaint['priority'] === 'urgente' ? 'danger' : ($complaint['priority'] === 'alta' ? 'warning text-dark' : 'secondary') ?> p-2">
                <i class="bi bi-flag me-1"></i><?= ucfirst($complaint['priority']) ?>
            </span>
        </div>
    </div>

    <div class="row g-4">
        <!-- Columna principal -->
        <div class="col-lg-8">
            <!-- Descripción -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-file-text me-2"></i>Descripción de los Hechos</h6>
                </div>
                <div class="card-body">
                    <div class="bg-light rounded-3 p-4">
                        <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($complaint['description'] ?? '[Sin datos]') ?></p>
                    </div>
                </div>
            </div>

            <!-- Empresa/Institución Denunciada -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-building me-2"></i>Empresa/Institución Denunciada</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Nombre / Razón Social</small>
                            <strong><?= htmlspecialchars($complaint['accused_name'] ?? '-') ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Área / Departamento</small>
                            <strong><?= htmlspecialchars($complaint['accused_department'] ?? '-') ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">RUT Empresa</small>
                            <strong><?= htmlspecialchars($complaint['accused_position'] ?? '-') ?></strong>
                        </div>
                    </div>
                    <?php if ($complaint['involved_persons']): ?>
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted d-block">N° Referencia / Boleta / Contrato</small>
                        <p class="mb-0"><?= htmlspecialchars($complaint['involved_persons']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['witnesses']): ?>
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted d-block">Testigos / Contactos</small>
                        <p class="mb-0"><?= htmlspecialchars($complaint['witnesses']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Denunciante -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0">
                        <i class="bi bi-person me-2"></i>Denunciante
                        <?php if ($complaint['is_anonymous']): ?>
                        <span class="badge bg-secondary ms-2">Anónima</span>
                        <?php endif; ?>
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($complaint['is_anonymous']): ?>
                    <p class="text-muted mb-0"><i class="bi bi-incognito me-2"></i>Denuncia realizada de forma anónima. No se registraron datos del denunciante.</p>
                    <?php else: ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Nombre</small>
                            <strong><?= htmlspecialchars($complaint['reporter_name'] ?? '-') ?></strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Email</small>
                            <strong><?= htmlspecialchars($complaint['reporter_email'] ?? '-') ?></strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Teléfono</small>
                            <strong><?= htmlspecialchars($complaint['reporter_phone'] ?? '-') ?></strong>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Comuna / Región</small>
                            <strong><?= htmlspecialchars($complaint['reporter_department'] ?? '-') ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resolución -->
            <?php if ($complaint['resolution']): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-success border-3">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0 text-info"><i class="bi bi-check-circle me-2"></i>Resolución</h6>
                </div>
                <div class="card-body">
                    <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($complaint['resolution']) ?></p>
                    <?php if ($complaint['resolved_at']): ?>
                    <small class="text-muted d-block mt-2"><i class="bi bi-calendar me-1"></i>Resuelta el <?= formatDateTime($complaint['resolved_at']) ?></small>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notas de investigación -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark-blue border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Notas de Investigación</h6>
                    <?php if ($canModify): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addNoteForm">
                        <i class="bi bi-plus me-1"></i>Agregar Nota
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="collapse mb-4" id="addNoteForm">
                        <form method="POST">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="add_note">
                            <textarea name="note_content" class="form-control mb-2" rows="3" placeholder="Escribe una nota de investigación..." required></textarea>
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_confidential" id="noteConfidential" checked>
                                    <label class="form-check-label small" for="noteConfidential">Nota confidencial</label>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Guardar Nota</button>
                            </div>
                        </form>
                    </div>

                    <?php if (empty($notes)): ?>
                    <p class="text-muted text-center py-3 mb-0">Sin notas de investigación</p>
                    <?php else: ?>
                    <?php foreach ($notes as $note): ?>
                    <div class="border-start border-3 border-primary ps-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <strong class="small"><?= htmlspecialchars($note['user_name'] ?? 'Sistema') ?></strong>
                            <small class="text-muted"><?= timeAgo($note['created_at']) ?>
                                <?php if ($note['is_confidential']): ?>
                                <i class="bi bi-lock text-warning ms-1" title="Confidencial"></i>
                                <?php endif; ?>
                            </small>
                        </div>
                        <p class="mb-0 small"><?= htmlspecialchars($enc->decrypt($note['content_encrypted'], $note['content_nonce']) ?? '-') ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Historial -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Historial</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($logs)): ?>
                    <p class="text-muted text-center py-3 mb-0">Sin actividad registrada</p>
                    <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <div class="d-flex gap-3 mb-3">
                        <div class="rounded-circle bg-light d-flex align-items-center justify-content-center flex-shrink-0" style="width: 35px; height: 35px;">
                            <i class="bi bi-clock text-muted small"></i>
                        </div>
                        <div>
                            <div class="small">
                                <strong><?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?></strong>
                                <span class="text-muted">· <?= htmlspecialchars($log['action']) ?></span>
                            </div>
                            <?php if ($log['description']): ?>
                            <p class="mb-0 text-muted small"><?= htmlspecialchars($log['description']) ?></p>
                            <?php endif; ?>
                            <small class="text-muted"><?= timeAgo($log['created_at']) ?></small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Columna lateral -->
        <div class="col-lg-4">
            <!-- Info rápida -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-info-circle me-2"></i>Información</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Tipo / Legislación</small>
                        <?= getTypeBadge($complaint['complaint_type']) ?>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Fecha del Incidente</small>
                        <strong><?= formatDate($complaint['incident_date']) ?></strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Lugar / Establecimiento</small>
                        <strong><?= htmlspecialchars($complaint['incident_location'] ?? '-') ?></strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Delegado Asignado</small>
                        <strong><?= htmlspecialchars($complaint['investigator_name'] ?? 'Sin asignar') ?></strong>
                    </div>
                    <?php if ($hasAreaSupport): ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Área Asignada</small>
                        <strong><?= htmlspecialchars(getInvestigationAreaLabel($complaint['assigned_area'] ?? null)) ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <small class="text-muted d-block">Ingresada</small>
                        <strong><?= formatDateTime($complaint['created_at']) ?></strong>
                    </div>
                    <?php if ($complaint['evidence_description']): ?>
                    <div class="mb-0">
                        <small class="text-muted d-block">Evidencia adjunta</small>
                        <p class="mb-0 small"><?= htmlspecialchars($complaint['evidence_description']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($canModify): ?>
            <div class="card border-0 shadow-sm mb-4 action-panel">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-sliders me-2"></i>Gestión del Caso</h6>
                </div>
                <div class="card-body d-grid gap-3">
                    <div class="action-section">
                        <div class="section-title">Estado</div>
                        <form method="POST" class="row g-2 align-items-end">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="change_status">
                            <div class="col-12">
                                <select name="new_status" class="form-select form-select-sm">
                                    <?php foreach (COMPLAINT_STATUSES as $key => $st): ?>
                                    <option value="<?= $key ?>" <?= $complaint['status'] === $key ? 'selected' : '' ?>><?= $st['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-check me-1"></i>Actualizar</button>
                            </div>
                        </form>
                    </div>

                    <?php if ($isSuperAdmin && $hasAreaSupport): ?>
                    <div class="action-section">
                        <div class="section-title">Asignación de Área</div>
                        <form method="POST" class="row g-2 align-items-end">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="assign_area">
                            <div class="col-12">
                                <select name="assigned_area" class="form-select form-select-sm" required>
                                    <option value="">Seleccionar área</option>
                                    <?php foreach (INVESTIGATION_AREAS as $areaKey => $areaLabel): ?>
                                    <option value="<?= htmlspecialchars($areaKey) ?>" <?= ($complaint['assigned_area'] ?? '') === $areaKey ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($areaLabel) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-check me-1"></i>Asignar Área</button>
                            </div>
                        </form>
                    </div>

                    <div class="action-section">
                        <div class="section-title">Asignación de Delegado</div>
                        <form method="POST" class="row g-2 align-items-end">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="assign_investigator">
                            <div class="col-12">
                                <select name="investigator_id" class="form-select form-select-sm">
                                    <option value="">Sin asignar</option>
                                    <?php foreach ($investigators as $inv): ?>
                                    <option value="<?= $inv['id'] ?>" <?= $complaint['investigator_id'] == $inv['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($inv['name']) ?>
                                        <?php if (!empty($inv['position'])): ?> (<?= htmlspecialchars($inv['position']) ?>)<?php endif; ?>
                                        · <?= htmlspecialchars(getInvestigationAreaLabel($inv['investigator_area'] ?? null)) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-check me-1"></i>Asignar</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <div class="action-section">
                        <div class="section-title">Prioridad</div>
                        <form method="POST">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="change_priority">
                            <div class="d-flex gap-2">
                                <?php foreach (['normal' => 'secondary', 'alta' => 'warning', 'urgente' => 'danger'] as $prio => $color): ?>
                                <button type="submit" name="new_priority" value="<?= $prio ?>" class="btn btn-<?= $complaint['priority'] === $prio ? $color : "outline-$color" ?> btn-sm flex-fill">
                                    <?= ucfirst($prio) ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </form>
                    </div>

                    <?php if (!$complaint['resolution'] && $complaint['status'] !== 'resuelta'): ?>
                    <div class="action-section">
                        <div class="section-title">Resolver Caso</div>
                        <form method="POST">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="add_resolution">
                            <textarea name="resolution" class="form-control form-control-sm mb-2" rows="3" placeholder="Describe la resolución del caso..." required></textarea>
                            <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('¿Confirmar resolución?')">
                                <i class="bi bi-check-circle me-1"></i>Marcar como Resuelta
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>

                    <?php if ($canDelete): ?>
                    <div class="action-section border border-danger-subtle bg-danger-subtle">
                        <div class="section-title text-danger">Eliminar Denuncia</div>
                        <form method="POST" onsubmit="return confirm('Esta accion eliminara la denuncia y sus registros asociados. ¿Continuar?');">
                            <?= csrfInput() ?>
                            <input type="hidden" name="action" value="delete_complaint">
                            <button type="submit" class="btn btn-danger btn-sm w-100">
                                <i class="bi bi-trash me-1"></i>Eliminar definitivamente
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
