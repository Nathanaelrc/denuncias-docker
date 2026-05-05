<?php
/**
 * Canal de Denuncias Empresa Portuaria Coquimbo - Inicio
 */
$pageTitle = 'Canal de Denuncias';
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
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div style="width:60px;height:60px;background:rgba(255,255,255,0.15);border-radius:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;padding:8px;">
                        <img src="/img/Logo01.png" alt="EPC" style="width:100%;height:100%;object-fit:contain;filter:brightness(0) invert(1);">
                    </div>
                    <div>
                        <h1 class="display-5 fw-bold text-white mb-0" style="line-height:1.15;">
                            Canal de <span style="color:#93c5fd;">Denuncias</span>
                        </h1>
                        <p class="mb-0 opacity-75 text-white">Empresa Portuaria Coquimbo · Puerto y Entorno</p>
                    </div>
                </div>
                <p class="text-white opacity-75 fs-5 mb-4 text-justify" style="line-height:1.8;">
                    Presenta tu denuncia de forma <strong class="text-white">confidencial</strong> respecto de hechos que puedan afectar la operación portuaria,
                    la probidad, la seguridad, el medio ambiente, la relación con usuarios o cualquier otra situación relevante
                    dentro del ámbito institucional de Empresa Portuaria Coquimbo y su entorno operativo.
                </p>
                <div class="d-flex flex-wrap gap-3">
                    <a href="/nueva_denuncia" class="btn btn-light btn-lg fw-semibold px-4 py-3">
                        <i class="bi bi-pencil-square me-2"></i>Realizar Denuncia
                    </a>
                    <a href="/seguimiento" class="btn btn-outline-light btn-lg px-4 py-3 fw-semibold">
                        <i class="bi bi-search me-2"></i>Seguimiento
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-3 mt-4">
                    <div class="d-flex align-items-center gap-2 text-white-50 small">
                        <i class="bi bi-shield-lock-fill fs-5" style="color:#93c5fd;"></i>
                        <span>Datos encriptados de forma segura</span>
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
                <div class="mt-4 p-3 rounded-3" style="background:rgba(255,255,255,0.12);border:1px solid rgba(255,255,255,0.25);">
                    <div class="fw-semibold text-white mb-2"><i class="bi bi-journal-text me-2" style="color:#93c5fd;"></i>Marco de referencia</div>
                    <div class="small text-justify" style="line-height:1.7;color:rgba(255,255,255,0.88);">
                        Este canal considera, entre otras materias, la <strong class="text-white">Ley N° 19.542</strong>,
                        la <strong class="text-white">Ley N° 18.575</strong>, las <strong class="text-white">Leyes N° 20.393, 21.595 y 21.121</strong>,
                        la <strong class="text-white">Ley N° 16.744</strong>, el <strong class="text-white">DS N° 594</strong>, la <strong class="text-white">Ley N° 21.012</strong>
                        y la <strong class="text-white">Ley N° 20.417</strong>. Las controversias contractuales directas entre EPCO y sus concesionarios se encuentran excluidas de este canal.
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

<!-- CONFIDENCIALIDAD Y SEGUIMIENTO -->
<section class="py-5" style="background:#ffffff;">
    <div class="container py-3">
        <div class="row g-5">
            <div class="col-md-6 fade-in">
                <h3 class="fw-bold mb-3" style="color:#1e293b;">Confidencialidad de la Denuncia</h3>
                <p class="text-justify" style="color:#475569;line-height:1.8;font-size:.95rem;">
                    Todas las denuncias emitidas serán tratadas de manera confidencial y serán remitidas, en
                    caso necesario, a los organismos y/o unidades correspondientes de la Empresa Portuaria
                    Coquimbo encargadas de su atención. La investigación se iniciará en un plazo no mayor a 5
                    días hábiles desde la recepción de denuncia.
                </p>
            </div>
            <div class="col-md-6 fade-in">
                <h3 class="fw-bold mb-3" style="color:#1e293b;">Seguimiento de Denuncias</h3>
                <p class="text-justify" style="color:#475569;line-height:1.8;font-size:.95rem;">
                    En caso de requerir seguimiento a su denuncia y para evitar perder el anonimato de esta,
                    le recomendamos a usted que nos pueda proporcionar alguna dirección de correo
                    electrónico genérico, considerando que pueda tener acceso a ella. De esta manera, Puerto
                    Coquimbo podrá brindarle una respuesta a su denuncia, dentro de los plazos establecidos
                    en los procedimientos internos.
                </p>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-12 fade-in">
                <div class="p-4 rounded-3" style="background:#eff6ff;border:1px solid #bfdbfe;">
                    <h5 class="fw-bold mb-3" style="color:#1e293b;">Marco legal y exclusiones del canal</h5>
                    <p class="mb-2 text-justify" style="color:#475569;line-height:1.8;font-size:.94rem;">
                        El portal considera denuncias relacionadas con seguridad operacional, control de accesos, prevención de la contaminación, probidad,
                        prevención de delitos económicos, conflictos de interés, seguridad y salud ocupacional, emergencias y materias ambientales dentro del ámbito de EPCO.
                        Se considera como norma de referencia la Ley N° 19.542,
                        además de la Ley N° 18.575, las Leyes N° 20.393, 21.595 y 21.121, la Ley N° 16.744, el DS N° 594,
                        la Ley N° 21.012 y la Ley N° 20.417.
                    </p>
                    <p class="mb-0 text-justify" style="color:#475569;line-height:1.8;font-size:.94rem;">
                        Las controversias contractuales directas entre EPCO y Terminal Puerto Coquimbo S.A. u otros concesionarios quedan excluidas de este canal.
                    </p>
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
                    <p class="small mb-0 text-justify" style="color:#64748b;"><?= $step['desc'] ?></p>
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
            <h2 class="fw-bold text-white">Áreas Fiscalizables en el Puerto</h2>
            <p style="color:rgba(255,255,255,0.6);">Ámbitos de denuncia en el entorno de la Empresa Portuaria Coquimbo</p>
        </div>
        <div class="row g-4">
            <?php
            $rights = [
                ['icon' => 'bi-water', 'title' => 'Medio Ambiente Portuario', 'desc' => 'Ley N° 19.300', 'detail' => 'Contaminación del borde costero, derrames de sustancias, emisiones de polvo y agentes contaminantes generados por la actividad portuaria.'],
                ['icon' => 'bi-file-earmark-text', 'title' => 'Transparencia en Contratos', 'desc' => 'Ley N° 19.886', 'detail' => 'Irregularidades en licitaciones, adjudicaciones de contratos, concesiones y uso indebido de recursos públicos de Empresa Portuaria Coquimbo.'],
                ['icon' => 'bi-people-fill', 'title' => 'Impacto en la Comunidad', 'desc' => 'Ley N° 20.936', 'detail' => 'Afectaciones a vecinos, usuarios y comunidades del entorno portuario: ruido excesivo, corte de vías, obras sin señalética o daños a terceros.'],
                ['icon' => 'bi-shield-exclamation', 'title' => 'Seguridad Operacional', 'desc' => 'DS N° 90 / Normativa ISPS', 'detail' => 'Incumplimiento de normas de seguridad en recintos portuarios, accidentes no reportados o riesgos para trabajadores y visitantes.'],
            ];
            foreach ($rights as $i => $r): ?>
            <div class="col-md-6 col-lg-3 fade-in">
                <div class="p-4 h-100 rounded-3" style="background: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                    <div class="mb-3">
                        <i class="bi <?= $r['icon'] ?> fs-2" style="color:#1a6591;"></i>
                    </div>
                    <h6 class="fw-bold mb-1" style="color:#1e293b;"><?= $r['title'] ?></h6>
                    <span class="badge mb-2" style="background:#eff6ff;color:#1a6591;font-size:0.7rem;"><?= $r['desc'] ?></span>
                    <p class="small mb-0 text-justify" style="color:#64748b;"><?= $r['detail'] ?></p>
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
                &copy; <?= date('Y') ?> Empresa Portuaria Coquimbo · Canal de Denuncias
            </div>
            <div class="col-md-6 text-end">
                <span class="text-white-50 small">
                    <i class="bi bi-shield-lock me-1"></i>Datos protegidos con alta seguridad · Confidencialidad garantizada
                </span>
            </div>
        </div>
    </div>
</footer>

</div>
<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
