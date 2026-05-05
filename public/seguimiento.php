<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Seguimiento de Denuncia
 */
$pageTitle = 'Seguimiento de Denuncia';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/encabezado.php';

$complaint = null;
$searched = false;
$rateLimitError = false;
$publicLogs = [];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['codigo'])) {
    $searched = true;
    $code = sanitize($_GET['codigo']);

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
        $complaint = null;
        $searched = false;
        $rateLimitError = true;
    } else {
        $complaint = findComplaintByNumber($code);
        if ($complaint) {
            $publicLogs = getComplaintLogs((int)$complaint['id'], false);
        }
        logActivity(null, 'seguimiento_consultado', 'complaint', $complaint['id'] ?? null, "Código: $code | IP: $ip");
    }
}
?>

<?php require_once __DIR__ . '/../includes/navbar_publica.php'; ?>
<style>
.seguimiento-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 16px;
    box-shadow: 0 12px 36px rgba(15, 23, 42, 0.12);
}
.seguimiento-info-box {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
}
.seguimiento-label {
    color: #64748b;
}
/* Overrides para anular text-white heredado de card-epco cuando tiene fondo blanco */
.card-epco.seguimiento-card,
.seguimiento-card { color: #0f172a !important; }
.card-epco.seguimiento-card .text-dark,
.seguimiento-card .text-dark { color: #0f172a !important; }
.card-epco.seguimiento-card .text-muted,
.seguimiento-card .text-muted { color: #64748b !important; }
.card-epco.seguimiento-card h5,
.card-epco.seguimiento-card h6,
.card-epco.seguimiento-card .fw-bold,
.card-epco.seguimiento-card .fw-semibold,
.seguimiento-card h5,
.seguimiento-card h6,
.seguimiento-card .fw-bold,
.seguimiento-card .fw-semibold { color: #0f172a !important; }
.card-epco.seguimiento-card p,
.card-epco.seguimiento-card small,
.card-epco.seguimiento-card .small,
.seguimiento-card p,
.seguimiento-card small,
.seguimiento-card .small { color: #334155; }
.seguimiento-card .text-white { color: #ffffff !important; }
</style>

<div style="padding-top: 70px;">
    <section class="gradient-bg py-5 min-vh-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">

                    <div class="text-white text-center mb-4 fade-in">
                        <h2 class="fw-bold"><i class="bi bi-search me-2"></i>Seguimiento de Denuncia</h2>
                        <p class="opacity-75">Ingresa tu código de seguimiento para consultar el estado de tu denuncia.</p>
                    </div>

                    <!-- Formulario de búsqueda -->
                    <div class="card-epco seguimiento-card p-4 mb-4 fade-in">
                        <form method="GET" action="/seguimiento">
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" name="codigo" class="form-control" placeholder="Ej: DN-20260301-12345" value="<?= htmlspecialchars($_GET['codigo'] ?? '') ?>" required pattern="DN-\d{8}-\d{5}" title="Formato: DN-YYYYMMDD-XXXXX">
                                <button type="submit" class="btn btn-epco px-4">
                                    <i class="bi bi-search me-2"></i>Buscar
                                </button>
                            </div>
                            <div class="form-text text-muted mt-2">Formato: DN-YYYYMMDD-XXXXX</div>
                        </form>
                    </div>

                    <?php if ($rateLimitError): ?>
                    <div class="card-epco seguimiento-card p-4 text-center fade-in">
                        <i class="bi bi-shield-exclamation fs-1 mb-3" style="color:#f59e0b;"></i>
                        <h5 class="text-dark fw-bold">Demasiadas consultas</h5>
                        <p class="text-muted">Has realizado demasiadas búsquedas en poco tiempo. Por favor espera unos minutos antes de intentarlo nuevamente.</p>
                    </div>
                    <?php elseif ($searched && $complaint): ?>
                    <!-- Resultado encontrado -->
                    <div class="card-epco seguimiento-card p-4 fade-in">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h5 class="text-dark fw-bold mb-0">
                                <i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($complaint['complaint_number']) ?>
                            </h5>
                            <?= getStatusBadge($complaint['status']) ?>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="seguimiento-info-box p-3">
                                    <small class="seguimiento-label d-block mb-1">Tipo de Denuncia</small>
                                    <?= getTypeBadge($complaint['complaint_type']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="seguimiento-info-box p-3">
                                    <small class="seguimiento-label d-block mb-1">Modalidad</small>
                                    <span class="badge bg-<?= $complaint['is_anonymous'] ? 'secondary' : 'info' ?>">
                                        <i class="bi bi-<?= $complaint['is_anonymous'] ? 'incognito' : 'person' ?> me-1"></i>
                                        <?= $complaint['is_anonymous'] ? 'Anónima' : 'Identificada' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="seguimiento-info-box p-3">
                                    <small class="seguimiento-label d-block mb-1">Fecha de Registro</small>
                                    <strong class="text-dark"><?= formatDateTime($complaint['created_at']) ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="seguimiento-info-box p-3">
                                    <small class="seguimiento-label d-block mb-1">Última Actualización</small>
                                    <strong class="text-dark"><?= formatDateTime($complaint['updated_at']) ?></strong>
                                </div>
                            </div>
                            <?php if ($complaint['incident_date']): ?>
                            <div class="col-md-6">
                                <div class="seguimiento-info-box p-3">
                                    <small class="seguimiento-label d-block mb-1">Fecha del Incidente</small>
                                    <strong class="text-dark"><?= formatDate($complaint['incident_date']) ?></strong>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($complaint['resolved_at']): ?>
                            <div class="col-md-6">
                                <div class="seguimiento-info-box p-3">
                                    <small class="seguimiento-label d-block mb-1">Fecha de Resolución</small>
                                    <strong class="text-dark"><?= formatDateTime($complaint['resolved_at']) ?></strong>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Timeline de estado -->
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="text-dark fw-bold mb-3"><i class="bi bi-clock-history me-2"></i>Progreso</h6>
                            <?php
                            $statusOrder = ['recibida', 'en_investigacion', 'resuelta'];
                            $currentIndex = array_search($complaint['status'], $statusOrder);
                            if ($currentIndex === false) $currentIndex = -1;
                            ?>
                            <div class="d-flex justify-content-between position-relative">
                                <?php foreach ($statusOrder as $i => $st):
                                    $config = COMPLAINT_STATUSES[$st];
                                    $isDone = $i <= $currentIndex;
                                    $isCurrent = $i === $currentIndex;
                                ?>
                                <div class="text-center flex-fill">
                                    <div class="rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center"
                                         style="width: 40px; height: 40px; background: <?= $isDone ? '#1a6591' : '#e2e8f0' ?>; transition: all 0.3s;">
                                        <i class="bi <?= $isDone ? 'bi-check' : $config['icon'] ?>" style="color: <?= $isDone ? 'white' : '#64748b' ?>;"></i>
                                    </div>
                                    <small class="<?= $isCurrent ? 'fw-bold text-dark' : 'text-muted' ?>"><?= $config['label'] ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php
                        $latestUpdates = array_slice(array_reverse($publicLogs), 0, 5);
                        $presentAction = static function (?string $rawAction): array {
                            $action = strtolower(trim((string)$rawAction));

                            if ($action === '') {
                                return ['Actualizacion del caso', 'bi-arrow-repeat'];
                            }
                            if (str_contains($action, 'cread') || str_contains($action, 'ingres')) {
                                return ['Denuncia creada', 'bi-file-earmark-plus'];
                            }
                            if (str_contains($action, 'asign')) {
                                return ['Asignacion de responsable', 'bi-person-check'];
                            }
                            if (str_contains($action, 'estado') || str_contains($action, 'investig')) {
                                return ['Cambio de estado', 'bi-diagram-3'];
                            }
                            if (str_contains($action, 'nota') || str_contains($action, 'coment')) {
                                return ['Nueva actualizacion interna', 'bi-chat-left-text'];
                            }
                            if (str_contains($action, 'resol') || str_contains($action, 'cerr') || str_contains($action, 'final')) {
                                return ['Resolucion final del caso', 'bi-check2-circle'];
                            }
                            if (str_contains($action, 'archiv')) {
                                return ['Caso archivado', 'bi-archive'];
                            }
                            if (str_contains($action, 'desestim')) {
                                return ['Caso desestimado', 'bi-x-circle'];
                            }

                            return ['Actualizacion del caso', 'bi-arrow-repeat'];
                        };
                        $resolutionLog = null;
                        foreach (array_reverse($publicLogs) as $logItem) {
                            $actionText = strtolower((string)($logItem['action'] ?? ''));
                            if (str_contains($actionText, 'resol') || str_contains($actionText, 'cerr') || str_contains($actionText, 'final')) {
                                $resolutionLog = $logItem;
                                break;
                            }
                        }
                        ?>

                        <?php if ($complaint['resolved_at']): ?>
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="text-dark fw-bold mb-2"><i class="bi bi-check2-circle me-2"></i>Resolución Final</h6>
                            <div class="alert alert-success mb-0">
                                <div class="fw-semibold">La denuncia fue resuelta el <?= formatDateTime($complaint['resolved_at']) ?>.</div>
                                <?php if (!empty($resolutionLog['description'])): ?>
                                <div class="small mt-1"><?= htmlspecialchars($resolutionLog['description']) ?></div>
                                <?php else: ?>
                                <div class="small mt-1">La investigación concluyó y el caso fue cerrado por el equipo autorizado.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($latestUpdates)): ?>
                        <div class="mt-4 pt-3 border-top">
                            <h6 class="text-dark fw-bold mb-3"><i class="bi bi-journal-text me-2"></i>Actualizaciones Recientes</h6>
                            <?php foreach ($latestUpdates as $update): ?>
                            <?php [$actionLabel, $actionIcon] = $presentAction($update['action'] ?? null); ?>
                            <div class="d-flex gap-3 mb-3">
                                <div class="rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background: #1a6591;">
                                    <i class="bi <?= htmlspecialchars($actionIcon) ?> text-white" style="font-size: 0.8rem;"></i>
                                </div>
                                <div>
                                    <div class="small text-dark fw-semibold"><?= htmlspecialchars($actionLabel) ?></div>
                                    <?php if (!empty($update['description'])): ?>
                                    <div class="small text-muted"><?= htmlspecialchars($update['description']) ?></div>
                                    <?php endif; ?>
                                    <div class="small text-muted" style="font-size: 0.7rem;"><?= formatDateTime($update['created_at']) ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-info mt-4 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Por razones de seguridad, el contenido de la denuncia solo es visible para los investigadores autorizados.
                        </div>
                    </div>

                    <?php elseif ($searched): ?>
                    <!-- No encontrado -->
                    <div class="card-epco seguimiento-card p-4 text-center fade-in">
                        <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-dark fw-bold mt-3">Denuncia no encontrada</h5>
                        <p class="text-muted">No se encontró ninguna denuncia con el código ingresado. Verifica que esté correcto.</p>
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
