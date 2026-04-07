<?php
/**
 * Portal Denuncias Ciudadanas - Seguimiento de Denuncia
 */
$pageTitle = 'Seguimiento';
require_once __DIR__ . '/../includes/bootstrap.php';

$codigoInput = sanitize($_GET['codigo'] ?? '');
$result      = null;
$notFound    = false;
$rateLimitError = false;

if (!empty($codigoInput)) {
    // Rate limiting: máx. 15 consultas por IP en 5 minutos
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimitReached = false;
    try {
        if (isset($pdo)) {
            $stmtRate = $pdo->prepare(
                "SELECT COUNT(*) FROM activity_logs WHERE action = 'seguimiento_consultado' AND ip_address = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
            );
            $stmtRate->execute([$ip]);
            if ((int)$stmtRate->fetchColumn() >= 15) {
                $rateLimitReached = true;
            }
        }
    } catch (Exception $e) { /* continuar si falla la comprobación */ }

    if ($rateLimitReached) {
        $rateLimitError = true;
    } else {
        $complaint = findComplaintByNumber($codigoInput);
        if ($complaint) {
            $result = $complaint;
            logActivity(null, 'seguimiento_consultado', 'complaint', $complaint['id'], "Código: $codigoInput | IP: $ip");
        } else {
            $notFound = true;
            logActivity(null, 'seguimiento_consultado', null, null, "Código no encontrado: $codigoInput | IP: $ip");
        }
    }
}

require_once __DIR__ . '/../includes/encabezado.php';
?>
<?php require_once __DIR__ . '/../includes/navbar_publica.php'; ?>

<div style="padding-top: 70px;">
<section class="gradient-bg py-5 min-vh-100">
    <div class="container py-3">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="text-white text-center mb-4 fade-in">
                    <h2 class="fw-bold"><i class="bi bi-search me-2"></i>Seguimiento de Denuncia</h2>
                    <p class="opacity-75">Ingresa tu código de seguimiento para consultar el estado</p>
                </div>

                <div class="p-4 mb-4 fade-in" style="background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.12);">
                    <form method="GET" action="/seguimiento">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text" style="background:#f8fafc;border:1px solid #cbd5e1;color:#1a6591;"><i class="bi bi-upc-scan"></i></span>
                            <input type="text" name="codigo" class="form-control" placeholder="Ej: DC-20240101-12345" value="<?= htmlspecialchars($codigoInput) ?>" required style="background:#f8fafc;border:1px solid #cbd5e1;color:#1e293b;">
                            <button type="submit" class="btn px-4" style="background:#1a6591;color:#fff;border:1px solid #1a6591;">
                                <i class="bi bi-search me-2"></i>Consultar
                            </button>
                        </div>
                        <div class="mt-2" style="font-size:.8rem;color:#64748b;">Formato: DC-YYYYMMDD-XXXXX</div>
                    </form>
                </div>

                <?php if ($rateLimitError): ?>
                <div class="p-4 text-center fade-in" style="background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.12);">
                    <i class="bi bi-shield-exclamation fs-1 mb-3" style="color:#f59e0b;"></i>
                    <h5 class="text-dark fw-bold">Demasiadas consultas</h5>
                    <p class="text-muted">Has realizado demasiadas búsquedas en poco tiempo. Por favor espera unos minutos antes de intentarlo nuevamente.</p>
                </div>
                <?php elseif ($notFound): ?>
                <div class="card-epco p-4 text-center fade-in">
                    <i class="bi bi-exclamation-circle fs-1 mb-3" style="color:#dc2626;"></i>
                    <h5 class="text-dark fw-bold">Código no encontrado</h5>
                    <p class="text-muted">El código <strong><?= htmlspecialchars($codigoInput) ?></strong> no existe o ha sido eliminado.</p>
                    <p class="text-muted small">Verifica que el código sea el que recibiste al presentar tu denuncia.</p>
                </div>
                <?php elseif ($result): ?>
                <div class="card-epco p-4 fade-in">
                    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
                        <div>
                            <h5 class="fw-bold text-dark mb-1">Denuncia <?= htmlspecialchars($result['complaint_number']) ?></h5>
                            <p class="text-muted small mb-0">Registrada el <?= formatDateTime($result['created_at']) ?></p>
                        </div>
                        <?= getStatusBadge($result['status']) ?>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.06);">
                                <div class="small mb-1" style="color:rgba(255,255,255,0.5);">Tipo de Denuncia</div>
                                <div class="fw-semibold text-dark"><?= getTypeBadge($result['complaint_type']) ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.06);">
                                <div class="small mb-1" style="color:rgba(255,255,255,0.5);">Modalidad</div>
                                <div class="fw-semibold text-dark">
                                    <?php if ($result['is_anonymous']): ?>
                                    <i class="bi bi-incognito me-1"></i>Anónima
                                    <?php else: ?>
                                    <i class="bi bi-person me-1"></i>Identificada
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.06);">
                                <div class="small mb-1" style="color:rgba(255,255,255,0.5);">Fecha del Incidente</div>
                                <div class="fw-semibold text-dark"><?= $result['incident_date'] ? formatDate($result['incident_date']) : 'No especificada' ?></div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.06);">
                                <div class="small mb-1" style="color:rgba(255,255,255,0.5);">Última Actualización</div>
                                <div class="fw-semibold text-dark"><?= formatDateTime($result['updated_at']) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline de estado -->
                    <?php
                    $statusOrder = ['recibida', 'en_investigacion', 'resuelta'];
                    $currentIdx  = array_search($result['status'], $statusOrder);
                    if ($currentIdx === false && $result['status'] !== 'desestimada') $currentIdx = -1;
                    ?>
                    <div class="mb-3">
                        <h6 class="fw-bold text-dark mb-3">Progreso del Caso</h6>
                        <?php if ($result['status'] === 'desestimada'): ?>
                        <div class="alert alert-secondary"><i class="bi bi-x-circle me-2"></i>Esta denuncia fue desestimada tras la revisión.</div>
                        <?php elseif ($result['status'] === 'archivada'): ?>
                        <div class="alert alert-dark"><i class="bi bi-archive me-2"></i>Esta denuncia ha sido archivada.</div>
                        <?php else: ?>
                        <div class="d-flex justify-content-between align-items-center position-relative">
                            <?php foreach ($statusOrder as $idx => $st): 
                                $isCompleted = $currentIdx !== false && $idx <= $currentIdx;
                                $isCurrent   = $currentIdx !== false && $idx === $currentIdx;
                                $statuses    = COMPLAINT_STATUSES;
                                $lbl         = $statuses[$st]['label'] ?? $st;
                                $ico         = $statuses[$st]['icon'] ?? 'bi-circle';
                            ?>
                            <div class="text-center flex-fill position-relative">
                                <div class="rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center"
                                    style="width:<?= $isCurrent ? 44 : 36 ?>px;height:<?= $isCurrent ? 44 : 36 ?>px;
                                    background:<?= $isCompleted ? '#1a6591' : '#e2e8f0' ?>;
                                    border:3px solid <?= $isCurrent ? '#2d9ad0' : 'transparent' ?>;">
                                    <i class="bi <?= $ico ?>" style="color:<?= $isCompleted ? 'white' : '#94a3b8' ?>;font-size:<?= $isCurrent ? '1.1rem' : '0.9rem' ?>;"></i>
                                </div>
                                <div style="font-size:0.75rem;font-weight:<?= $isCurrent ? 700 : 400 ?>;color:<?= $isCompleted ? '#1a6591' : '#94a3b8' ?>;"><?= $lbl ?></div>
                            </div>
                            <?php if ($idx < count($statusOrder) - 1): ?>
                            <div class="flex-grow-0" style="height:3px;background:<?= $currentIdx !== false && $idx < $currentIdx ? '#1a6591' : '#e2e8f0' ?>;width:50px;margin-bottom:20px;"></div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($result['resolved_at']): ?>
                    <div class="alert alert-success small">
                        <i class="bi bi-check-circle me-2"></i>
                        Denuncia resuelta el <?= formatDateTime($result['resolved_at']) ?>
                    </div>
                    <?php endif; ?>

                    <!-- Logs públicos -->
                    <?php 
                    $logs = getComplaintLogs((int)$result['id'], false);
                    if (!empty($logs)): ?>
                    <hr class="my-4">
                    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-ul me-2"></i>Historial</h6>
                    <?php foreach ($logs as $log): ?>
                    <div class="d-flex gap-3 mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width:32px;height:32px;background:#1a6591;">
                            <i class="bi bi-check text-white" style="font-size:0.8rem;"></i>
                        </div>
                        <div>
                            <div class="small text-dark fw-semibold"><?= htmlspecialchars($log['action']) ?></div>
                            <?php if ($log['description']): ?><div class="small text-muted"><?= htmlspecialchars($log['description']) ?></div><?php endif; ?>
                            <div class="small text-muted" style="font-size:0.7rem;"><?= formatDateTime($log['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="/" class="btn btn-outline-light px-4">
                        <i class="bi bi-house me-2"></i>Volver al inicio
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
