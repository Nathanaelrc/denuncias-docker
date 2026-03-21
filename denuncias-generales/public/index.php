<?php
/**
 * Portal Denuncias Ciudadanas EPCO - Inicio
 */
$pageTitle = 'Inicio';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/encabezado.php';
?>

<?php require_once __DIR__ . '/../includes/navbar_publica.php'; ?>

<div style="padding-top: 70px;">

<!-- HERO -->
<section class="gradient-bg py-5" style="min-height: 85vh; display: flex; align-items: center;">
    <div class="container py-4">
        <div class="row align-items-center gy-5">
            <div class="col-lg-7 slide-in-left">
                <span class="legal-badge mb-3">
                    <i class="bi bi-globe2"></i>Portal Ciudadano · SERNAC · Ley 19.496
                </span>
                <h1 class="fw-bold text-white display-5 mb-3" style="line-height:1.2;">
                    Canal de Denuncias<br>
                    <span style="color:#93c5fd;">para el ciudadano</span>
                </h1>
                <p class="text-white opacity-75 fs-5 mb-4">
                    Presenta tu denuncia de forma confidencial ante situaciones de incumplimiento de derechos como
                    consumidor, servicios deficientes, infracciones ambientales y más, en cumplimiento
                    de la legislación chilena vigente.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/nueva_denuncia" class="btn btn-light btn-lg fw-semibold px-4 py-3">
                        <i class="bi bi-pencil-square me-2"></i>Realizar Denuncia
                    </a>
                    <a href="/seguimiento" class="btn btn-lg px-4 py-3 fw-semibold" style="background:#fff;color:#1a6591;border:2px solid #fff;">
                        <i class="bi bi-search me-2"></i>Seguimiento
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <div class="d-flex align-items-center gap-2 text-white-50 small">
                        <i class="bi bi-shield-lock-fill fs-5" style="color:#93c5fd;"></i>
                        <span>Datos encriptados AES-256</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-white-50 small">
                        <i class="bi bi-incognito fs-5" style="color:#93c5fd;"></i>
                        <span>Opción de denuncia anónima</span>
                    </div>
                    <div class="d-flex align-items-center gap-2 text-white-50 small">
                        <i class="bi bi-clock-history fs-5" style="color:#93c5fd;"></i>
                        <span>Seguimiento en tiempo real</span>
                    </div>
                </div>
            </div>
            <div class="col-lg-5 slide-in-right">
                <div class="p-4" style="background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.15);">
                    <h5 class="fw-bold mb-4" style="color:#1e293b;"><i class="bi bi-list-check me-2" style="color:#1a6591;"></i>Tipos de Denuncia</h5>
                    <div class="row g-2">
                        <?php foreach (COMPLAINT_TYPES as $key => $type): ?>
                        <?php if ($key !== 'otro'): ?>
                        <div class="col-12">
                            <div class="d-flex align-items-start gap-3 p-2 rounded-2" style="transition:background 0.2s;" onmouseenter="this.style.background='#f0f7ff'" onmouseleave="this.style.background=''">
                                <div class="rounded-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width:38px;height:38px;background:#eff6ff;">
                                    <i class="bi <?= $type['icon'] ?>" style="color:#1a6591;font-size:1.1rem;"></i>
                                </div>
                                <div>
                                    <div class="fw-semibold" style="font-size:0.9rem;color:#1e293b;"><?= $type['label'] ?></div>
                                    <div style="font-size:0.75rem;color:#64748b;"><?= $type['ley'] ?></div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CÓMO FUNCIONA -->
<section class="py-5 section-dark">
    <div class="container py-3">
        <div class="text-center mb-5 fade-in">
            <h2 class="fw-bold text-white">¿Cómo funciona?</h2>
            <p style="color:rgba(255,255,255,0.6);">Proceso simple, transparente y confidencial</p>
        </div>
        <div class="row g-4">
            <?php
            $steps = [
                ['icon' => 'bi-pencil-square', 'num' => '01', 'title' => 'Presenta tu Denuncia', 'desc' => 'Completa el formulario indicando el tipo de denuncia, describiendo los hechos y adjuntando evidencia si la tienes.'],
                ['icon' => 'bi-key', 'num' => '02', 'title' => 'Recibe tu Código', 'desc' => 'Obtendrás un código único de seguimiento. Guárdalo — es tu llave para consultar el estado de tu denuncia.'],
                ['icon' => 'bi-search', 'num' => '03', 'title' => 'Revisión del Caso', 'desc' => 'Nuestros delegados revisarán tu caso de forma confidencial y tomarán las acciones correspondientes.'],
                ['icon' => 'bi-check-circle', 'num' => '04', 'title' => 'Resolución', 'desc' => 'Serás informado del resultado a través del portal. Si dejaste tu correo, recibirás notificaciones por email.'],
            ];
            foreach ($steps as $i => $step): ?>
            <div class="col-md-3 fade-in stagger-<?= $i + 1 ?>">
                <div class="text-center p-4 h-100" style="border-radius: 16px; background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                    <div class="mx-auto mb-3 rounded-circle d-flex align-items-center justify-content-center" style="width:64px;height:64px;background:linear-gradient(135deg,#1a6591,#2380b0);">
                        <i class="bi <?= $step['icon'] ?> text-white fs-4"></i>
                    </div>
                    <div class="fw-bold mb-1" style="color:#1a6591;font-size:0.8rem;letter-spacing:1px;"><?= $step['num'] ?></div>
                    <h6 class="fw-bold mb-2" style="color:#1e293b;"><?= $step['title'] ?></h6>
                    <p class="small mb-0" style="color:#64748b;"><?= $step['desc'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- DERECHOS DEL CIUDADANO -->
<section class="py-5 section-dark">
    <div class="container py-3">
        <div class="text-center mb-5 fade-in">
            <h2 class="fw-bold text-white">Tus Derechos como Ciudadano</h2>
            <p style="color:rgba(255,255,255,0.6);">Legislación chilena que respalda tu denuncia</p>
        </div>
        <div class="row g-4">
            <?php
            $rights = [
                ['icon' => 'bi-bag-check', 'title' => 'Protección al Consumidor', 'desc' => 'Ley N° 19.496', 'detail' => 'Derecho a productos y servicios seguros, información veraz, reparación por daños y representación ante SERNAC.'],
                ['icon' => 'bi-droplet', 'title' => 'Servicios Básicos', 'desc' => 'DFL N° 382 / Ley 18.168', 'detail' => 'Derecho a servicios de agua potable, electricidad y telecomunicaciones de calidad sin interrupciones arbitrarias.'],
                ['icon' => 'bi-bank', 'title' => 'Servicios Públicos', 'desc' => 'Ley N° 19.880', 'detail' => 'Derecho a procedimientos administrativos justos, plazos razonables y trato imparcial por parte de organismos del Estado.'],
                ['icon' => 'bi-tree', 'title' => 'Medio Ambiente', 'desc' => 'Ley N° 19.300', 'detail' => 'Derecho a vivir en un ambiente libre de contaminación y a denunciar actividades que causen daño ambiental.'],
            ];
            foreach ($rights as $i => $r): ?>
            <div class="col-md-6 col-lg-3 fade-in">
                <div class="p-4 h-100 rounded-3" style="background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                    <div class="mb-3">
                        <i class="bi <?= $r['icon'] ?> fs-2" style="color:#1a6591;"></i>
                    </div>
                    <h6 class="fw-bold mb-1" style="color:#1e293b;"><?= $r['title'] ?></h6>
                    <span class="badge mb-2" style="background:#eff6ff;color:#1a6591;font-size:0.7rem;"><?= $r['desc'] ?></span>
                    <p class="small mb-0" style="color:#64748b;"><?= $r['detail'] ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="py-4" style="background:#1a6591;">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 text-white-50 small">
                &copy; <?= date('Y') ?> Empresa Portuaria Coquimbo · Portal Ciudadano de Denuncias
            </div>
            <div class="col-md-6 text-end">
                <span class="text-white-50 small">
                    <i class="bi bi-shield-lock me-1"></i>Datos protegidos con AES-256 · Confidencialidad garantizada
                </span>
            </div>
        </div>
    </div>
</footer>

</div>
<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
