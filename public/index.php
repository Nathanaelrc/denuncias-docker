<?php
/**
 * Portal de Denuncias EPCO - Página Principal
 */
$pageTitle = 'Canal de Denuncias';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/encabezado.php';
?>

<!-- Navbar pública -->
<nav class="navbar navbar-expand-lg navbar-epco fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <i class="bi bi-shield-lock-fill fs-4"></i>
            <span class="fw-bold">Canal de Denuncias <span class="fw-light">EPCO</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navPublic">
            <i class="bi bi-list text-white fs-4"></i>
        </button>
        <div class="collapse navbar-collapse" id="navPublic">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link text-white" href="/"><i class="bi bi-house me-1"></i>Inicio</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="/nueva_denuncia"><i class="bi bi-pencil-square me-1"></i>Realizar Denuncia</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="/seguimiento"><i class="bi bi-search me-1"></i>Seguimiento</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="/acceso"><i class="bi bi-box-arrow-in-right me-1"></i>Acceso Dashboard</a></li>
            </ul>
        </div>
    </div>
</nav>

<div style="padding-top: 70px;">

    <!-- Hero Section -->
    <section class="gradient-bg py-5">
        <div class="container py-5">
            <div class="row align-items-center">
                <div class="col-lg-7 text-white fade-in">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <div style="width: 60px; height: 60px; background: rgba(255,255,255,0.15); border-radius: 16px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-shield-check fs-2"></i>
                        </div>
                        <div>
                            <h1 class="display-5 fw-bold mb-0">Canal de Denuncias</h1>
                            <p class="mb-0 opacity-75">Empresa Portuaria Coquimbo</p>
                        </div>
                    </div>
                    <p class="lead mb-4" style="line-height: 1.8;">
                        Este es un canal seguro, confidencial y protegido para reportar situaciones de 
                        <strong>acoso laboral, acoso sexual, violencia en el trabajo</strong> y otras conductas 
                        que vulneren tus derechos, en cumplimiento de la <strong>Ley Karin (Nº 21.643)</strong>.
                    </p>
                    <div class="d-flex flex-wrap gap-3">
                        <a href="/nueva_denuncia" class="btn btn-light btn-lg px-4 fw-semibold">
                            <i class="bi bi-pencil-square me-2"></i>Realizar Denuncia
                        </a>
                        <a href="/seguimiento" class="btn btn-outline-light btn-lg px-4">
                            <i class="bi bi-search me-2"></i>Consultar Estado
                        </a>
                    </div>
                </div>
                <div class="col-lg-5 text-center mt-4 mt-lg-0 slide-in-right">
                    <div class="card-epco p-4" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2);">
                        <i class="bi bi-lock-fill text-white" style="font-size: 4rem;"></i>
                        <h5 class="text-white mt-3 fw-bold">100% Confidencial</h5>
                        <p class="text-white-50 mb-3">Todos los datos sensibles son encriptados con <strong class="text-white">AES-256</strong></p>
                        <div class="d-flex justify-content-center gap-2 flex-wrap">
                            <span class="encrypted-badge"><i class="bi bi-shield-lock"></i> Datos Encriptados</span>
                            <span class="encrypted-badge"><i class="bi bi-incognito"></i> Denuncia Anónima</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Garantías -->
    <section class="py-5" style="background: #0d2f50;">
        <div class="container">
            <h2 class="text-center text-white fw-bold mb-5 fade-in">
                <i class="bi bi-patch-check me-2"></i>Nuestras Garantías
            </h2>
            <div class="row g-4">
                <div class="col-md-4 fade-in">
                    <div class="card-epco p-4 text-center h-100">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: linear-gradient(135deg, #0369a1, #0ea5e9);">
                            <i class="bi bi-incognito text-white fs-3"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Anonimato Garantizado</h5>
                        <p class="text-muted">Puedes realizar tu denuncia de forma completamente anónima. No es obligatorio identificarse.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in">
                    <div class="card-epco p-4 text-center h-100">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: linear-gradient(135deg, #059669, #10b981);">
                            <i class="bi bi-shield-lock text-white fs-3"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Encriptación AES-256</h5>
                        <p class="text-muted">Toda la información se almacena encriptada. Nadie que acceda a la base de datos puede leer el contenido.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in">
                    <div class="card-epco p-4 text-center h-100">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: linear-gradient(135deg, #7c3aed, #a855f7);">
                            <i class="bi bi-people text-white fs-3"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Investigación Imparcial</h5>
                        <p class="text-muted">Tu denuncia será investigada por personal autorizado, de forma confidencial e imparcial.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tipos de denuncia -->
    <section class="py-5 gradient-bg">
        <div class="container">
            <h2 class="text-center text-white fw-bold mb-2 fade-in">¿Qué puedes denunciar?</h2>
            <p class="text-center text-white-50 mb-5 fade-in">En cumplimiento de la Ley N° 21.643 (Ley Karin)</p>
            <div class="row g-4">
                <?php
                $tipos = [
                    ['icon' => 'bi-exclamation-triangle', 'title' => 'Acoso Laboral', 'desc' => 'Conductas reiteradas de hostigamiento que menoscaben, maltraten o humillen al trabajador.', 'color' => '#f59e0b'],
                    ['icon' => 'bi-shield-exclamation', 'title' => 'Acoso Sexual', 'desc' => 'Requerimientos de carácter sexual no consentidos que amenacen o perjudiquen la situación laboral.', 'color' => '#ef4444'],
                    ['icon' => 'bi-lightning', 'title' => 'Violencia Laboral', 'desc' => 'Conductas ejercidas por personas ajenas a la relación laboral que afecten al trabajador.', 'color' => '#8b5cf6'],
                    ['icon' => 'bi-people', 'title' => 'Discriminación', 'desc' => 'Distinciones, exclusiones o preferencias basadas en motivos prohibidos por ley.', 'color' => '#06b6d4'],
                    ['icon' => 'bi-arrow-return-left', 'title' => 'Represalias', 'desc' => 'Acciones en contra del denunciante como consecuencia de haber presentado una denuncia.', 'color' => '#ec4899'],
                    ['icon' => 'bi-question-circle', 'title' => 'Otras Conductas', 'desc' => 'Cualquier otra conducta que vulnere derechos fundamentales en el ámbito laboral.', 'color' => '#64748b'],
                ];
                foreach ($tipos as $tipo): ?>
                <div class="col-md-4 fade-in">
                    <div class="card-epco p-4 h-100">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="rounded-3 d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; background: <?= $tipo['color'] ?>20;">
                                <i class="bi <?= $tipo['icon'] ?>" style="color: <?= $tipo['color'] ?>; font-size: 1.3rem;"></i>
                            </div>
                            <h6 class="text-dark fw-bold mb-0"><?= $tipo['title'] ?></h6>
                        </div>
                        <p class="text-muted small mb-0"><?= $tipo['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Proceso -->
    <section class="py-5" style="background: #0d2f50;">
        <div class="container">
            <h2 class="text-center text-white fw-bold mb-5 fade-in">¿Cómo funciona?</h2>
            <div class="row g-4">
                <?php
                $pasos = [
                    ['num' => '1', 'title' => 'Escribe tu denuncia', 'desc' => 'Completa el formulario con los detalles. Puedes hacerlo de forma anónima.', 'icon' => 'bi-pencil-square'],
                    ['num' => '2', 'title' => 'Se encripta y almacena', 'desc' => 'Tu denuncia se encripta automáticamente. Nadie puede leerla en la base de datos.', 'icon' => 'bi-lock'],
                    ['num' => '3', 'title' => 'Recibe un código', 'desc' => 'Recibirás un número de seguimiento único para consultar el estado.', 'icon' => 'bi-upc-scan'],
                    ['num' => '4', 'title' => 'Investigación', 'desc' => 'Un investigador autorizado revisa tu caso de forma confidencial.', 'icon' => 'bi-search'],
                ];
                foreach ($pasos as $paso): ?>
                <div class="col-md-3 fade-in">
                    <div class="text-center">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; background: rgba(255,255,255,0.1); border: 2px solid rgba(255,255,255,0.3);">
                            <i class="bi <?= $paso['icon'] ?> text-white fs-3"></i>
                        </div>
                        <div class="bg-white text-dark rounded-pill px-3 py-1 d-inline-block mb-2 fw-bold small">Paso <?= $paso['num'] ?></div>
                        <h5 class="text-white fw-bold"><?= $paso['title'] ?></h5>
                        <p class="text-white-50 small"><?= $paso['desc'] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-5 fade-in">
                <a href="/nueva_denuncia" class="btn btn-light btn-lg px-5 fw-semibold">
                    <i class="bi bi-pencil-square me-2"></i>Realizar Denuncia Ahora
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4" style="background: #061827;">
        <div class="container text-center text-white-50">
            <p class="mb-1"><i class="bi bi-shield-lock me-1"></i> Canal de Denuncias - Empresa Portuaria Coquimbo</p>
            <p class="mb-0 small">Ley N° 21.643 (Ley Karin) · Todos los datos protegidos con encriptación AES-256</p>
        </div>
    </footer>

</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
