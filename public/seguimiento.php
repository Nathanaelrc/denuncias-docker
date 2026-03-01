<?php
/**
 * Portal de Denuncias EPCO - Seguimiento de Denuncia
 */
$pageTitle = 'Seguimiento de Denuncia';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/encabezado.php';

$complaint = null;
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['codigo'])) {
    $searched = true;
    $code = sanitize($_GET['codigo']);
    $complaint = findComplaintByNumber($code);
    if ($complaint) {
        logActivity(null, 'seguimiento_consultado', 'complaint', $complaint['id'], "Código: $code");
    }
}
?>

<!-- Navbar pública -->
<nav class="navbar navbar-expand-lg navbar-epco fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <img src="/img/Logo01.png" alt="EPCO" style="height: 32px; width: auto;">
            <span class="fw-bold">Canal de Denuncias</span>
        </a>
        <div class="d-flex align-items-center gap-2 order-lg-last">
            <a href="/acceso" class="btn btn-outline-light btn-sm d-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 600; padding: 6px 16px;">
                <i class="bi bi-box-arrow-in-right"></i>
                <span class="d-none d-sm-inline">Iniciar Sesión</span>
            </a>
            <button class="navbar-toggler border-0 d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navPublic">
                <i class="bi bi-list text-white fs-4"></i>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="navPublic">
            <ul class="navbar-nav ms-auto me-3">
                <li class="nav-item"><a class="nav-link text-white" href="/"><i class="bi bi-house me-1"></i>Inicio</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="/nueva_denuncia"><i class="bi bi-pencil-square me-1"></i>Realizar Denuncia</a></li>
                <li class="nav-item"><a class="nav-link text-white active" href="/seguimiento"><i class="bi bi-search me-1"></i>Seguimiento</a></li>
            </ul>
        </div>
    </div>
</nav>

<div style="padding-top: 80px;">
    <section class="gradient-bg py-5 min-vh-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">

                    <div class="text-white text-center mb-4 fade-in">
                        <h2 class="fw-bold"><i class="bi bi-search me-2"></i>Seguimiento de Denuncia</h2>
                        <p class="opacity-75">Ingresa tu código de seguimiento para consultar el estado de tu denuncia.</p>
                    </div>

                    <!-- Formulario de búsqueda -->
                    <div class="card-epco p-4 mb-4 fade-in">
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

                    <?php if ($searched && $complaint): ?>
                    <!-- Resultado encontrado -->
                    <div class="card-epco p-4 fade-in">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h5 class="text-dark fw-bold mb-0">
                                <i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($complaint['complaint_number']) ?>
                            </h5>
                            <?= getStatusBadge($complaint['status']) ?>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3">
                                    <small class="text-muted d-block mb-1">Tipo de Denuncia</small>
                                    <?= getTypeBadge($complaint['complaint_type']) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3">
                                    <small class="text-muted d-block mb-1">Modalidad</small>
                                    <span class="badge bg-<?= $complaint['is_anonymous'] ? 'secondary' : 'info' ?>">
                                        <i class="bi bi-<?= $complaint['is_anonymous'] ? 'incognito' : 'person' ?> me-1"></i>
                                        <?= $complaint['is_anonymous'] ? 'Anónima' : 'Identificada' ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3">
                                    <small class="text-muted d-block mb-1">Fecha de Registro</small>
                                    <strong class="text-dark"><?= formatDateTime($complaint['created_at']) ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3">
                                    <small class="text-muted d-block mb-1">Última Actualización</small>
                                    <strong class="text-dark"><?= formatDateTime($complaint['updated_at']) ?></strong>
                                </div>
                            </div>
                            <?php if ($complaint['incident_date']): ?>
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3">
                                    <small class="text-muted d-block mb-1">Fecha del Incidente</small>
                                    <strong class="text-dark"><?= formatDate($complaint['incident_date']) ?></strong>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if ($complaint['resolved_at']): ?>
                            <div class="col-md-6">
                                <div class="bg-light rounded-3 p-3">
                                    <small class="text-muted d-block mb-1">Fecha de Resolución</small>
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
                                         style="width: 40px; height: 40px; background: <?= $isDone ? '#059669' : '#e2e8f0' ?>; transition: all 0.3s;">
                                        <i class="bi <?= $isDone ? 'bi-check' : $config['icon'] ?>" style="color: <?= $isDone ? 'white' : '#64748b' ?>;"></i>
                                    </div>
                                    <small class="<?= $isCurrent ? 'fw-bold text-dark' : 'text-muted' ?>"><?= $config['label'] ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4 mb-0">
                            <i class="bi bi-info-circle me-2"></i>
                            Por razones de seguridad, el contenido de la denuncia solo es visible para los investigadores autorizados.
                        </div>
                    </div>

                    <?php elseif ($searched): ?>
                    <!-- No encontrado -->
                    <div class="card-epco p-4 text-center fade-in">
                        <i class="bi bi-search text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-dark fw-bold mt-3">Denuncia no encontrada</h5>
                        <p class="text-muted">No se encontró ninguna denuncia con el código ingresado. Verifica que esté correcto.</p>
                    </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
