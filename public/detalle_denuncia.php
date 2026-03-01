<?php
/**
 * Portal de Denuncias EPCO - Detalle de Denuncia (Desencriptado)
 * Solo accesible por admin e investigador
 */
$pageTitle = 'Detalle de Denuncia';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN, ROLE_INVESTIGADOR]);

$user = getCurrentUser();
$isAdmin = hasRole([ROLE_ADMIN]);

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect('/denuncias_admin', 'Denuncia no especificada.', 'danger');
}

$complaint = getComplaintDecrypted($id);
if (!$complaint) {
    redirect('/denuncias_admin', 'Denuncia no encontrada.', 'danger');
}

logActivity($_SESSION['user_id'], 'ver_denuncia', 'complaint', $id, 'Acceso a denuncia desencriptada');

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

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
            redirect("/detalle_denuncia?id=$id", 'Estado actualizado correctamente.');
        }
    }

    if ($action === 'assign_investigator') {
        $investigatorId = (int)($_POST['investigator_id'] ?? 0);
        $pdo->prepare("UPDATE complaints SET investigator_id = ?, assigned_at = NOW(), status = 'en_investigacion' WHERE id = ?")
            ->execute([$investigatorId ?: null, $id]);
        addComplaintLog($id, 'asignacion', 'Investigador asignado al caso', $_SESSION['user_id']);
        logActivity($_SESSION['user_id'], 'asignar_investigador', 'complaint', $id, "Investigador ID: $investigatorId");
        redirect("/detalle_denuncia?id=$id", 'Investigador asignado.');
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

// Obtener notas de investigación
$notesStmt = $pdo->prepare("SELECT n.*, u.name as user_name FROM investigation_notes n LEFT JOIN users u ON n.user_id = u.id WHERE n.complaint_id = ? ORDER BY n.created_at DESC");
$notesStmt->execute([$id]);
$notes = $notesStmt->fetchAll();
$enc = getEncryptionService();

// Obtener investigadores (para asignar)
$investigators = $pdo->query("SELECT id, name, position FROM users WHERE role IN ('admin', 'investigador') AND is_active = 1")->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

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
            <span class="encrypted-badge"><i class="bi bi-shield-lock"></i> Desencriptado</span>
        </div>
    </div>

    <div class="row g-4">
        <!-- Columna principal -->
        <div class="col-lg-8">
            <!-- Información de la denuncia -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-file-text me-2"></i>Descripción de los Hechos</h6>
                </div>
                <div class="card-body">
                    <div class="bg-light rounded-3 p-4">
                        <p class="mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($complaint['description'] ?? '[Sin datos]') ?></p>
                    </div>
                </div>
            </div>

            <!-- Persona(s) denunciada(s) -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-x me-2"></i>Persona(s) Denunciada(s)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Nombre</small>
                            <strong><?= htmlspecialchars($complaint['accused_name'] ?? '-') ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Departamento</small>
                            <strong><?= htmlspecialchars($complaint['accused_department'] ?? '-') ?></strong>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Cargo</small>
                            <strong><?= htmlspecialchars($complaint['accused_position'] ?? '-') ?></strong>
                        </div>
                    </div>
                    <?php if ($complaint['involved_persons']): ?>
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted d-block">Otras personas involucradas</small>
                        <p class="mb-0"><?= htmlspecialchars($complaint['involved_persons']) ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($complaint['witnesses']): ?>
                    <div class="mt-3 pt-3 border-top">
                        <small class="text-muted d-block">Testigos</small>
                        <p class="mb-0"><?= htmlspecialchars($complaint['witnesses']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Denunciante -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
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
                            <small class="text-muted d-block">Departamento</small>
                            <strong><?= htmlspecialchars($complaint['reporter_department'] ?? '-') ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Resolución -->
            <?php if ($complaint['resolution']): ?>
            <div class="card border-0 shadow-sm mb-4 border-start border-success border-3">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0 text-success"><i class="bi bi-check-circle me-2"></i>Resolución</h6>
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
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0"><i class="bi bi-journal-text me-2"></i>Notas de Investigación</h6>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addNoteForm">
                        <i class="bi bi-plus me-1"></i>Agregar Nota
                    </button>
                </div>
                <div class="card-body">
                    <!-- Formulario agregar nota -->
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
                <div class="card-header bg-white border-0 py-3">
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

        <!-- Columna lateral (acciones) -->
        <div class="col-lg-4">
            <!-- Info rápida -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-info-circle me-2"></i>Información</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <small class="text-muted d-block">Tipo</small>
                        <?= getTypeBadge($complaint['complaint_type']) ?>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Fecha del Incidente</small>
                        <strong><?= formatDate($complaint['incident_date']) ?></strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Lugar</small>
                        <strong><?= htmlspecialchars($complaint['incident_location'] ?? '-') ?></strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Investigador</small>
                        <strong><?= htmlspecialchars($complaint['investigator_name'] ?? 'Sin asignar') ?></strong>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Creada</small>
                        <strong><?= formatDateTime($complaint['created_at']) ?></strong>
                    </div>
                    <?php if ($complaint['evidence_description']): ?>
                    <div class="mb-0">
                        <small class="text-muted d-block">Evidencia</small>
                        <p class="mb-0 small"><?= htmlspecialchars($complaint['evidence_description']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Acciones -->
            <?php if ($isAdmin): ?>
            <!-- Cambiar Estado -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>Cambiar Estado</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="change_status">
                        <select name="new_status" class="form-select mb-2">
                            <?php foreach (COMPLAINT_STATUSES as $key => $st): ?>
                            <option value="<?= $key ?>" <?= $complaint['status'] === $key ? 'selected' : '' ?>><?= $st['label'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-check me-1"></i>Actualizar</button>
                    </form>
                </div>
            </div>

            <!-- Asignar Investigador -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-person-plus me-2"></i>Asignar Investigador</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="assign_investigator">
                        <select name="investigator_id" class="form-select mb-2">
                            <option value="">Sin asignar</option>
                            <?php foreach ($investigators as $inv): ?>
                            <option value="<?= $inv['id'] ?>" <?= $complaint['investigator_id'] == $inv['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($inv['name']) ?> (<?= htmlspecialchars($inv['position'] ?? '') ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-check me-1"></i>Asignar</button>
                    </form>
                </div>
            </div>

            <!-- Cambiar Prioridad -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-flag me-2"></i>Prioridad</h6>
                </div>
                <div class="card-body">
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
            </div>

            <!-- Resolución -->
            <?php if (!$complaint['resolution'] && $complaint['status'] !== 'resuelta'): ?>
            <div class="card border-0 shadow-sm mb-4 border-top border-success border-3">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0 text-success"><i class="bi bi-check-circle me-2"></i>Resolver Caso</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfInput() ?>
                        <input type="hidden" name="action" value="add_resolution">
                        <textarea name="resolution" class="form-control mb-2" rows="4" placeholder="Describe la resolución del caso..." required></textarea>
                        <button type="submit" class="btn btn-success btn-sm w-100" onclick="return confirm('¿Confirmar resolución?')">
                            <i class="bi bi-check-circle me-1"></i>Marcar como Resuelta
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
