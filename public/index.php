<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Página Principal
 */
$pageTitle = 'Canal de Denuncias Ley Karin';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/encabezado.php';
?>

<!-- Navbar unificada -->
<?php require_once __DIR__ . '/../includes/navbar_publica.php'; ?>

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
                            <h1 class="display-5 fw-bold mb-0">Canal de Denuncias Ley Karin</h1>
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
                    <div class="p-4 p-md-5" style="background:rgba(255,255,255,.07);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border:1px solid rgba(255,255,255,.15);border-radius:20px;">
                        <div class="mx-auto mb-3 d-flex align-items-center justify-content-center" style="width:80px;height:80px;background:linear-gradient(135deg,rgba(255,255,255,.15),rgba(255,255,255,.05));border-radius:50%;border:2px solid rgba(255,255,255,.2);">
                            <i class="bi bi-shield-lock-fill" style="font-size:2.4rem;color:#93c5fd;"></i>
                        </div>
                        <h4 class="text-white fw-bold mb-1">100% Confidencial</h4>
                        <p class="mb-4" style="color:rgba(255,255,255,.6);font-size:.92rem;">Canal protegido por la <strong class="text-white">Ley N° 21.643</strong></p>
                        <div class="row g-2 text-start">
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3 p-2 rounded-3" style="background:rgba(255,255,255,.06);">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:rgba(147,197,253,.15);border-radius:10px;">
                                        <i class="bi bi-incognito" style="color:#93c5fd;"></i>
                                    </div>
                                    <div>
                                        <div class="text-white fw-semibold" style="font-size:.85rem;">Denuncia Anónima</div>
                                        <div style="font-size:.72rem;color:rgba(255,255,255,.45);">No es obligatorio identificarse</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3 p-2 rounded-3" style="background:rgba(255,255,255,.06);">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:rgba(147,197,253,.15);border-radius:10px;">
                                        <i class="bi bi-shield-check" style="color:#93c5fd;"></i>
                                    </div>
                                    <div>
                                        <div class="text-white fw-semibold" style="font-size:.85rem;">Protección Legal</div>
                                        <div style="font-size:.72rem;color:rgba(255,255,255,.45);">Prohibidas las represalias</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center gap-3 p-2 rounded-3" style="background:rgba(255,255,255,.06);">
                                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px;background:rgba(147,197,253,.15);border-radius:10px;">
                                        <i class="bi bi-lock-fill" style="color:#93c5fd;"></i>
                                    </div>
                                    <div>
                                        <div class="text-white fw-semibold" style="font-size:.85rem;">Datos Encriptados</div>
                                        <div style="font-size:.72rem;color:rgba(255,255,255,.45);">Cifrado AES-256</div>
                                    </div>
                                </div>
                            </div>
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
                <i class="bi bi-patch-check me-2"></i>Tus Derechos y Garantías
            </h2>
            <div class="row g-4">
                <div class="col-md-4 fade-in">
                    <div class="card-epco p-4 text-center h-100">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: linear-gradient(135deg, #0369a1, #0ea5e9);">
                            <i class="bi bi-incognito text-white fs-3"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Anonimato Garantizado</h5>
                        <p class="text-muted">Puedes realizar tu denuncia de forma completamente anónima. No es obligatorio identificarse para presentar una denuncia.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in">
                    <div class="card-epco p-4 text-center h-100">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: linear-gradient(135deg, #1e40af, #3b82f6);">
                            <i class="bi bi-shield-check text-white fs-3"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Protección contra Represalias</h5>
                        <p class="text-muted">La Ley Karin prohíbe cualquier tipo de represalia contra el denunciante. Tu empleo y condiciones laborales están protegidos.</p>
                    </div>
                </div>
                <div class="col-md-4 fade-in">
                    <div class="card-epco p-4 text-center h-100">
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 70px; height: 70px; background: linear-gradient(135deg, #7c3aed, #a855f7);">
                            <i class="bi bi-people text-white fs-3"></i>
                        </div>
                        <h5 class="text-dark fw-bold">Investigación Imparcial</h5>
                        <p class="text-muted">Tu denuncia será investigada por personal autorizado de forma confidencial, imparcial y dentro de los plazos legales.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Marco Legal Ley Karin -->
    <section class="py-5 gradient-bg">
        <div class="container">
            <h2 class="text-center text-white fw-bold mb-2 fade-in"><i class="bi bi-journal-text me-2"></i>¿Qué es la Ley Karin?</h2>
            <p class="text-center text-white-50 mb-5 fade-in">Ley N° 21.643 — Vigente desde el 1 de agosto de 2024</p>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card-epco p-4 p-md-5 fade-in">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <h5 class="text-dark fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Sobre la Ley</h5>
                                <p class="text-muted">La <strong>Ley Karin</strong> modifica el Código del Trabajo en materia de prevención, investigación y sanción del <strong>acoso laboral, acoso sexual y violencia en el trabajo</strong>.</p>
                                <p class="text-muted">Obliga a los empleadores a implementar un <strong>protocolo de prevención</strong> y disponer de un <strong>canal de denuncias</strong> accesible para todas las trabajadoras y todos los trabajadores.</p>
                            </div>
                            <div class="col-md-6">
                                <h5 class="text-dark fw-bold mb-3"><i class="bi bi-check-circle text-success me-2"></i>Tus Derechos</h5>
                                <ul class="text-muted" style="line-height: 2;">
                                    <li>Denunciar de forma <strong>anónima o identificada</strong></li>
                                    <li>Protección contra <strong>represalias</strong> por denunciar</li>
                                    <li>Investigación en un plazo máximo de <strong>30 días</strong></li>
                                    <li>Derecho a ser <strong>informado/a del resultado</strong></li>
                                    <li>Recurrir ante la <strong>Inspección del Trabajo</strong></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Tipos de denuncia -->
    <section class="py-5" style="background: #0d2f50;">
        <div class="container">
            <h2 class="text-center text-white fw-bold mb-2 fade-in">¿Qué conductas puedes denunciar?</h2>
            <p class="text-center text-white-50 mb-5 fade-in">Según lo establecido en la Ley N° 21.643 (Ley Karin)</p>
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
    <section class="py-5 gradient-bg">
        <div class="container">
            <h2 class="text-center text-white fw-bold mb-5 fade-in">¿Cómo funciona el proceso?</h2>
            <div class="row g-4">
                <?php
                $pasos = [
                    ['num' => '1', 'title' => 'Escribe tu denuncia', 'desc' => 'Completa el formulario con los detalles de los hechos. Puedes hacerlo de forma anónima.', 'icon' => 'bi-pencil-square'],
                    ['num' => '2', 'title' => 'Recibe un código', 'desc' => 'Obtendrás un número de seguimiento único para consultar el estado de tu denuncia.', 'icon' => 'bi-upc-scan'],
                    ['num' => '3', 'title' => 'Investigación', 'desc' => 'Un investigador autorizado revisa tu caso de forma confidencial e imparcial.', 'icon' => 'bi-search'],
                    ['num' => '4', 'title' => 'Resolución', 'desc' => 'Se adoptan las medidas correspondientes y se te informa del resultado.', 'icon' => 'bi-check-circle'],
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
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-4" style="background: #061827;">
        <div class="container text-center text-white-50">
            <p class="mb-1"><i class="bi bi-shield-check me-1"></i> Canal de Denuncias Ley Karin - Empresa Portuaria Coquimbo</p>
            <p class="mb-0 small">En cumplimiento de la Ley N° 21.643 (Ley Karin) · Todos los datos tratados de forma confidencial</p>
        </div>
    </footer>

</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
