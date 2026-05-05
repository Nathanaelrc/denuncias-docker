<?php
/**
 * Canal de Denuncias Empresa Portuaria Coquimbo - Nueva Denuncia
 */
$pageTitle = 'Realizar Denuncia';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/captcha.php';

$errors = [];
$success = false;
$complaintNumber = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de seguridad inválido. Recarga la página.';
    }
    if (!SimpleCaptcha::validate($_POST['captcha_answer'] ?? '', $_POST['captcha_token'] ?? '')) {
        $errors[] = 'Verificación de seguridad incorrecta.';
    }

    // complaint_type es solo informativo; se guarda como valor genérico
    $complaintType = 'general';
    $description   = trim($_POST['description'] ?? '');
    
    // Validar tipo de identidad
    $identidadOption = $_POST['tipo_identidad'] ?? 'anonima';
    $isAnonymous = ($identidadOption === 'anonima') ? 1 : 0;

    if (strlen($description) < 10) {
        $errors[] = 'La descripción debe tener al menos 10 caracteres.';
    }
    if (empty(trim($_POST['incident_date'] ?? ''))) {
        $errors[] = 'La fecha del incidente es obligatoria.';
    }
    if (empty(trim($_POST['incident_location'] ?? ''))) {
        $errors[] = 'El lugar de los hechos es obligatorio.';
    }

    // Correo de contacto opcional (disponible incluso en modo anónimo)
    $anonymousEmail = trim($_POST['anonymous_email'] ?? '');
    if (!empty($anonymousEmail) && !filter_var($anonymousEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo de contacto ingresado no es válido.';
    }

    // Validaciones para denuncias identificadas (no anónimas)
    $reporterName  = '';
    $reporterEmail = '';
    $reporterPhone = '';
    $reporterDept  = '';
    
    if (!$isAnonymous) {
        $reporterName = trim($_POST['reporter_name'] ?? '');
        $reporterLastname = trim($_POST['reporter_lastname'] ?? '');
        
        $reporterEmail = trim($_POST['reporter_email'] ?? '');
        $reporterPhone = trim($_POST['reporter_phone'] ?? '');
        $reporterDept  = trim($_POST['reporter_department'] ?? '');

        if (empty($reporterName) || empty($reporterLastname)) {
            $errors[] = 'El nombre y apellido son obligatorios cuando la denuncia no es anónima.';
        } elseif (strlen($reporterName . ' ' . $reporterLastname) > 150) {
            $errors[] = 'El nombre completo no puede superar los 150 caracteres.';
        }
        
        if (!empty($reporterEmail) && !filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo electrónico ingresado no es válido.';
        }
    }

    if (empty($errors)) {
        $data = [
            'complaint_type'       => $complaintType,
            'description'          => $description,
            'is_anonymous'         => $isAnonymous,
            'involved_persons'     => trim($_POST['involved_persons'] ?? '') ?: null,   // N° referencia / boleta
            'evidence_description' => trim($_POST['evidence_description'] ?? '') ?: null,
            'reporter_name'        => $isAnonymous ? null : ($reporterName ?: null),
            'reporter_lastname'    => $isAnonymous ? null : ($reporterLastname ?: null),
            'reporter_email'       => $isAnonymous ? ($anonymousEmail ?: null) : ($reporterEmail ?: null),
            'reporter_phone'       => $isAnonymous ? null : ($reporterPhone ?: null),
            'reporter_department'  => $isAnonymous ? null : ($reporterDept ?: null),
            'accused_name'         => trim($_POST['accused_name'] ?? '') ?: null,       // Empresa/institución
            'accused_department'   => trim($_POST['accused_department'] ?? '') ?: null, // Área/Departamento
            'accused_position'     => trim($_POST['accused_position'] ?? '') ?: null,   // RUT / Dirección
            'witnesses'            => trim($_POST['witnesses'] ?? '') ?: null,
            'incident_date'        => !empty($_POST['incident_date']) ? $_POST['incident_date'] : null,
            'incident_location'    => trim($_POST['incident_location'] ?? '') ?: null,
        ];

        $result = createComplaint($data);
        if ($result['success']) {
            $success = true;
            $complaintNumber = $result['complaint_number'];
            // Guardar archivos adjuntos si los hay
            if (!empty($_FILES['attachments']['tmp_name'][0])) {
                saveComplaintAttachments($result['id'], $_FILES['attachments']);
            }
            logActivity(null, 'denuncia_creada', 'complaint', $result['id'], "Tipo: $complaintType | Anónima: " . ($isAnonymous ? 'Sí' : 'No'));
            sendNotification('denuncia_creada', 'Nueva denuncia: ' . $complaintNumber, 'Tipo: ' . $complaintType, 'complaint', $result['id']);
        } else {
            $errors[] = $result['message'] ?? 'Error al registrar la denuncia.';
        }
    }
}

require_once __DIR__ . '/../includes/encabezado.php';
?>

<style>
/* Card blanco para formulario de denuncia - Igual que Karin */
.card-form {
    background: #fff !important;
    border: none !important;
    border-radius: 16px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.1);
}
/* Hacer que textos y labels se vean oscuros sobre fondo blanco */
.card-form .text-dark,
.card-form h2, .card-form h5, .card-form h6 {
    color: #1e293b !important;
}
.card-form .text-muted {
    color: #64748b !important;
}
.card-form p.opacity-75 {
    color: #475569 !important;
    opacity: 1 !important;
}
.card-form .form-label, .form-label { color: #475569 !important; font-weight: 500; }

/* Controles de formulario (inputs, selects, textarea) */
.card-form .form-control, .card-form .form-select {
    background-color: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 0.75rem 1rem !important;
    color: #0f172a !important;
}
.card-form .form-control::placeholder, .card-form .form-select::placeholder {
    color: #94a3b8 !important;
}
.card-form .alert-info {
    color: #084298 !important;
    background-color: #cfe2ff !important;
    border-color: #b6d4fe !important;
}
.card-form .text-muted {
    color: #475569 !important;
}
.card-form .form-text {
    color: #475569 !important;
}
#captcha_container .form-label, 
#captcha_container .form-text,
.card-form .captcha-container .form-label,
.card-form .captcha-container .form-text {
    color: #1e293b !important;
}

.card-form .alert-info {
    color: #084298 !important;
    background-color: #cfe2ff !important;
    border-color: #b6d4fe !important;
}
.card-form .text-muted {
    color: #475569 !important;
}
.card-form .form-text {
    color: #475569 !important;
}
#captcha_container .form-label, 
#captcha_container .form-text,
.card-form .captcha-container .form-label,
.card-form .captcha-container .form-text {
    color: #1e293b !important;
}

.card-form .form-control:focus, .card-form .form-select:focus {
    background-color: #fff !important;
    border-color: #3b82f6 !important;
    box-shadow: 0 0 0 4px rgba(59,130,246,0.1) !important;
}

/* Captcha y tarjetas anidadas */
.card-form .input-group-text, .card-form .card {
    background-color: #f8fafc !important;
    border: 1px solid #e2e8f0 !important;
    color: #1e293b !important;
}

/* Tipos de denuncia que son labels visuales */
.card-form .btn-outline-dark {
    color: #475569 !important;
    border-color: #cbd5e1 !important;
}
.card-form .btn-outline-dark:hover, 
.card-form .btn-check:checked + .btn-outline-dark {
    color: #1e293b !important;
    border-color: #3b82f6 !important;
    background-color: #f0f7ff !important;
}

/* Tarjetas informacionales de tipo de denuncia */
.ley-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    transition: border-color .15s, background .15s;
    cursor: pointer;
    outline: none;
    line-height: 1.3;
}
.ley-card:hover {
    border-color: #93c5fd;
    background: #f0f7ff;
}
.ley-card:focus-visible {
    box-shadow: 0 0 0 3px rgba(59,130,246,.25);
}
/* Fondo azul degradado para secciones de página */
.gradient-bg {
    background: linear-gradient(150deg, #1a6591 0%, #2380b0 60%, #1a6591 100%);
}
/* Corregir colores de alertas dentro de la tarjeta blanca */
.card-form .alert-warning {
    color: #7c5e00 !important;
    background-color: #fff3cd !important;
    border-color: #ffc107 !important;
}
/* Modal Animation */
@keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
</style>

<?php
?>

<?php require_once __DIR__ . '/../includes/navbar_publica.php'; ?>

<!-- ============================================================
     MODAL: Confirmación de canal correcto - Denuncias Generales
     ============================================================ -->
<?php if ($_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
<div id="modalCanalGenerales" style="
    display:flex; position:fixed; inset:0; z-index:9999;
    align-items:center; justify-content:center;
    background:rgba(10,22,40,0.75); backdrop-filter:blur(4px);
" role="dialog" aria-modal="true" aria-labelledby="modalCanalTitulo">
    <div style="
        background:#fff; border-radius:20px; max-width:520px; width:90%;
        padding:2.2rem 2rem; box-shadow:0 20px 60px rgba(0,0,0,0.3);
        animation:slideUp .3s ease both;
    ">

        <!-- Cabecera -->
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:1.1rem;">
            <div style="width:48px;height:48px;border-radius:12px;background:#eff6ff;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
                <img src="/img/Logo01.png" alt="EPC" style="width:34px;height:34px;object-fit:contain;">
            </div>
            <div>
                <div style="font-size:.7rem;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:#1a6591;">Estás en el canal</div>
                <div id="modalCanalTitulo" style="font-size:1.1rem;font-weight:800;color:#1e293b;">Canal de Denuncias Empresa Portuaria Coquimbo</div>
            </div>
        </div>

        <!-- Descripción -->
        <p class="text-justify" style="color:#475569;font-size:.92rem;line-height:1.65;margin-bottom:1.3rem;">
            Este canal está destinado a denuncias sobre <strong>operaciones del puerto, contratos, medio ambiente,
            seguridad, corrupción</strong> y otras materias relacionadas con la <strong>Empresa Portuaria Coquimbo</strong>.
        </p>
        <p class="text-justify" style="color:#475569;font-size:.88rem;line-height:1.6;margin-bottom:1.5rem;
            background:#f8fafc;border-radius:10px;padding:.75rem 1rem;border-left:3px solid #93c5fd;">
            <i class="bi bi-info-circle me-1" style="color:#1a6591;"></i>
            Si tu denuncia es sobre <strong>acoso laboral, acoso sexual o violencia en el trabajo</strong>,
            debes ir al <strong>Canal de Denuncias Ley Karin</strong>.
        </p>

        <!-- Acciones -->
        <div style="display:flex;flex-direction:column;gap:.7rem;">
            <button onclick="document.getElementById('modalCanalGenerales').style.display='none'" style="
                background:#2563eb;color:#fff;border:none;border-radius:10px;
                padding:.75rem 1.2rem;font-size:.93rem;font-weight:700;cursor:pointer;
                display:flex;align-items:center;justify-content:center;gap:8px;
                transition:background .15s;
            " onmouseover="this.style.background='#1d4ed8'" onmouseout="this.style.background='#2563eb'">
                <i class="bi bi-pencil-square"></i> Continuar aquí — Realizar denuncia general
            </button>
            <a href="../karin/nueva_denuncia" style="
                background:#dc2626;color:#fff;border:2px solid #dc2626;border-radius:10px;
                padding:.7rem 1.2rem;font-size:.88rem;font-weight:600;cursor:pointer;
                display:flex;align-items:center;justify-content:center;gap:8px;text-decoration:none;
                transition:background .15s;
            " onmouseover="this.style.background='#b91c1c'" onmouseout="this.style.background='#dc2626'">
                <i class="bi bi-arrow-right-circle"></i> Ir al Canal de Denuncias Ley Karin
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<div style="padding-top: 70px;">


<?php if ($success): ?>
<section class="gradient-bg py-5 min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card-form p-5 text-center fade-in">
                    <div class="rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center" style="width:90px;height:90px;background:linear-gradient(135deg,#1a6591,#2380b0);">
                        <i class="bi bi-check-lg text-white" style="font-size:3rem;"></i>
                    </div>
                    <h2 class="text-dark fw-bold">Denuncia Registrada</h2>
                    <p class="text-muted mb-4 text-justify">Tu denuncia ha sido recibida y registrada de forma confidencial.</p>
                    <div class="bg-light rounded-3 p-4 mb-4">
                        <p class="text-muted small mb-1">Tu número de seguimiento es:</p>
                        <h3 class="fw-bold mb-0" style="color:#1a6591;" id="complaintCode"><?= htmlspecialchars($complaintNumber) ?></h3>
                    </div>
                    <div class="alert alert-warning text-start text-justify">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>¡Importante!</strong> Guarda este código. Es la única forma de consultar el estado de tu denuncia.
                    </div>
                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($complaintNumber) ?>').then(() => alert('Código copiado'))" class="btn btn-epco px-4">
                            <i class="bi bi-clipboard me-2"></i>Copiar Código
                        </button>
                        <a href="/seguimiento" class="btn btn-outline-dark px-4">
                            <i class="bi bi-search me-2"></i>Ir a Seguimiento
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php else: ?>
<section class="gradient-bg py-5">
    <div class="container-fluid px-3 px-lg-4 px-xl-5">
        <div class="row justify-content-center">
            <div class="col-xl-11 col-xxl-10">
                <div class="mb-3 fade-in">
                    <a href="/" class="btn btn-outline-light">
                        <i class="bi bi-arrow-left me-2"></i>Volver a página de Canal de Denuncias Generales
                    </a>
                </div>
                <div class="text-white text-center mb-4 fade-in">
                    <h2 class="fw-bold"><i class="bi bi-pencil-square me-2"></i>Realizar Denuncia</h2>
                    <p class="opacity-75 text-justify">Completa el formulario. Tu información será tratada con total confidencialidad y encriptación.</p>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger fade-in text-justify">
                    <i class="bi bi-exclamation-circle me-2"></i><strong>Errores:</strong>
                    <ul class="mb-0 mt-2"><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>

                <div class="card-form p-4 p-md-5 fade-in">
                    <form method="POST" action="/nueva_denuncia" id="formDenuncia" data-wizard="complaint_generales" enctype="multipart/form-data">
                        <?= csrfInput() ?>

                        <!-- Categorías informativas (no forman parte de la denuncia) -->
                        <div class="mb-4">
                            <p class="fw-semibold text-dark mb-2 text-justify" style="font-size:.93rem;">
                                <i class="bi bi-info-circle text-primary me-1"></i>
                                Categorías de referencia
                                <span class="fw-normal text-muted" style="font-size:.82rem;"> — Haz clic en cualquiera para ver la norma aplicable</span>
                            </p>
                            <!-- Tarjetas informacionales expandibles (no seleccionables) -->
                            <div class="row g-2 mb-1" id="leyCardsGrid">
                                <?php foreach (COMPLAINT_TYPES as $key => $type): ?>
                                <div class="col-md-4 col-6">
                                    <button type="button"
                                        class="ley-card w-100 text-start py-2 px-2 h-100"
                                        data-ley-key="<?= htmlspecialchars($key) ?>"
                                        onclick="toggleInfoLey('<?= htmlspecialchars($key) ?>', this)">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <div>
                                                <i class="bi <?= $type['icon'] ?> me-1" style="color:#1a6591;font-size:.85rem;"></i>
                                                <strong style="color:#1e293b;font-size:.78rem;"><?= $type['label'] ?></strong><br>
                                                <small style="color:#64748b;font-size:.66rem;padding-left:1.3rem;"><?= $type['ley'] ?></small>
                                            </div>
                                            <i class="bi bi-chevron-down ley-chevron ms-1" style="color:#94a3b8;font-size:.65rem;flex-shrink:0;transition:transform .2s;"></i>

                                        </div>
                                    </button>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <!-- Panel de descripción expandible -->
                            <div id="leyInfoPanel" style="display:none;border:1px solid #bfdbfe;border-radius:10px;background:#eff6ff;padding:1rem 1.2rem;margin-bottom:.75rem;">
                                <div class="d-flex align-items-start gap-2">
                                    <i id="leyPanelIcono" class="bi fs-5 mt-1" style="color:#1a6591;flex-shrink:0;"></i>
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center justify-content-between mb-1">
                                            <strong id="leyPanelTitulo" style="color:#1e293b;font-size:.93rem;"></strong>
                                            <span id="leyPanelNorma" style="font-size:.72rem;font-weight:700;color:#2563eb;letter-spacing:.3px;"></span>
                                        </div>
                                        <p id="leyPanelDesc" class="mb-0 text-justify" style="color:#334155;font-size:.87rem;line-height:1.65;"></p>
                                    </div>
                                    <button type="button" onclick="cerrarInfoLey()" style="background:none;border:none;color:#94a3b8;cursor:pointer;padding:0;flex-shrink:0;" aria-label="Cerrar">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold text-dark">Descripción de los hechos *</label>
                            <textarea name="description" id="description" class="form-control" rows="6" required minlength="10"
                                placeholder="Describe con detalle lo ocurrido: qué pasó, cuándo, dónde, cómo te afectó..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <div class="form-text text-justify">Mínimo 10 caracteres.</div>
                        </div>

                        <!-- Fecha y Lugar (obligatorios) -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="incident_date" class="form-label fw-semibold text-dark">Fecha del incidente *</label>
                                <input type="date" name="incident_date" id="incident_date" class="form-control" required value="<?= htmlspecialchars($_POST['incident_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="incident_location" class="form-label fw-semibold text-dark">Lugar de los hechos *</label>
                                <input type="text" name="incident_location" id="incident_location" class="form-control" required value="<?= htmlspecialchars($_POST['incident_location'] ?? '') ?>" placeholder="Ej: Puerto de Coquimbo, Oficina central...">
                            </div>
                        </div>

                        <!-- Empresa, Institución o Persona Denunciada -->
                        <h5 class="text-dark fw-bold mb-3 mt-4"><i class="bi bi-buildings me-2"></i>Empresa, Institución o Persona Denunciada</h5>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">Empresa</label>
                                <input type="text" name="accused_name" class="form-control" value="<?= htmlspecialchars($_POST['accused_name'] ?? '') ?>" placeholder="Nombre de la empresa">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">Institución</label>
                                <input type="text" name="accused_department" class="form-control" value="<?= htmlspecialchars($_POST['accused_department'] ?? '') ?>" placeholder="Nombre de la institución">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">Persona Denunciada</label>
                                <input type="text" name="accused_position" class="form-control" value="<?= htmlspecialchars($_POST['accused_position'] ?? '') ?>" placeholder="Nombre de la persona">
                            </div>
                        </div>

                        <!-- Referencia y Testigos -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Área de trabajo</label>
                                <input type="text" name="involved_persons" class="form-control" value="<?= htmlspecialchars($_POST['involved_persons'] ?? '') ?>" placeholder="Cualquier número de referencia relacionado">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Testigos (opcional)</label>
                                <input type="text" name="witnesses" class="form-control" value="<?= htmlspecialchars($_POST['witnesses'] ?? '') ?>" placeholder="Nombre de testigos si los hay">
                            </div>
                        </div>

                        <!-- Evidencia -->
                        <div class="mb-4">
                            <label for="evidence_description" class="form-label fw-semibold text-dark">Descripción de Evidencia</label>
                            <textarea name="evidence_description" id="evidence_description" class="form-control" rows="3"
                                placeholder="Describe fotos, videos, documentos u otra evidencia que tengas..."><?= htmlspecialchars($_POST['evidence_description'] ?? '') ?></textarea>
                        </div>

                        <!-- Archivos adjuntos -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark"><i class="bi bi-paperclip me-1"></i>Archivos adjuntos <span class="fw-normal text-muted">(opcional)</span></label>
                            <div id="dropZone" onclick="document.getElementById('fileInput').click()" style="
                                border: 2px dashed #cbd5e1; border-radius: 12px;
                                padding: 28px 20px; text-align: center; cursor: pointer;
                                background: #f8fafc; transition: all 0.2s;
                            " ondragover="event.preventDefault();this.style.cssText='border:2px dashed #2380b0;border-radius:12px;padding:28px 20px;text-align:center;cursor:pointer;background:#eff6ff;transition:all 0.2s;'" ondragleave="this.style.cssText='border:2px dashed #cbd5e1;border-radius:12px;padding:28px 20px;text-align:center;cursor:pointer;background:#f8fafc;transition:all 0.2s;'" ondrop="handleDrop(event)">
                                <i class="bi bi-cloud-arrow-up" style="font-size:2.2rem; color:#94a3b8;"></i>
                                <p class="mb-1 mt-2" style="color:#475569; font-size:0.92rem;">Arrastra archivos aquí o <strong style="color:#1a6591;">haz clic para seleccionar</strong></p>
                                <p class="mb-0" style="color:#94a3b8; font-size:0.78rem;">Imágenes, audio, PDF, Word, Excel, video &middot; Máx. 10 MB &middot; Hasta 10 archivos</p>
                            </div>
                            <input type="file" id="fileInput" name="attachments[]" multiple accept="image/*,audio/*,.pdf,.doc,.docx,.xls,.xlsx,.txt,.mp3,.wav,.ogg,.m4a,.aac,.webm,.mp4,.mov" style="display:none" onchange="updateFileList(this.files)">
                            <ul id="fileList" class="list-unstyled mt-2 mb-0"></ul>
                        </div>

                        <hr class="my-4">

                        <!-- Anonimato -->
                        <div class="mb-4">
                            <div class="form-check form-switch fs-5">
                                <input type="hidden" name="tipo_identidad" value="identificada">
                                <input class="form-check-input" type="checkbox" role="switch" name="tipo_identidad" value="anonima" id="optIdentidad_anonima" <?= (($_POST['tipo_identidad'] ?? 'anonima') === 'anonima') ? 'checked' : '' ?> onchange="toggleReporterFields()">
                                <label class="form-check-label fw-bold text-dark ms-2" for="optIdentidad_anonima" style="font-size:1.1rem;">
                                    <i class="bi bi-incognito text-primary me-2"></i>Denunciar de forma anónima
                                </label>
                            </div>
                            <div class="form-text mt-2 text-muted text-justify">
                                Si desactivas el anonimato, podrás proporcionar tus datos. Quedarán encriptados y estrictamente protegidos para quienes investiguen el caso.
                            </div>

                            <!-- Email de contacto opcional para denuncia anónima -->
                            <div id="anonymousEmailField" class="mt-3" style="display: none;">
                                <label class="form-label fw-semibold text-dark" for="anonymous_email">
                                    <i class="bi bi-envelope me-1"></i>Correo de contacto
                                    <span class="fw-normal text-muted">(opcional)</span>
                                </label>
                                <input type="email" name="anonymous_email" id="anonymous_email"
                                    class="form-control"
                                    value="<?= htmlspecialchars($_POST['anonymous_email'] ?? '') ?>"
                                    placeholder="Para recibir notificaciones sobre tu denuncia">
                                <div class="form-text mt-1" style="color:#1a6591;">
                                    <i class="bi bi-shield-check me-1"></i>Solo se usará para notificarte del estado de tu denuncia. Queda encriptado y <strong>no compromete tu anonimato</strong>.
                                </div>
                            </div>
                        </div>

                        <div id="reporterFields" style="display: none; border-top: 1px solid rgba(0,0,0,0.1); padding-top: 1.5rem; margin-top: 0.5rem;">
                            <h6 class="text-dark fw-bold mb-3"><i class="bi bi-person me-2"></i>Tus Datos (Confidenciales)</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Nombre *</label>
                                    <input type="text" name="reporter_name" id="reporter_name" class="form-control reporter-input" value="<?= htmlspecialchars($_POST['reporter_name'] ?? '') ?>" placeholder="Tu nombre">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Apellido *</label>
                                    <input type="text" name="reporter_lastname" id="reporter_lastname" class="form-control reporter-input" value="<?= htmlspecialchars($_POST['reporter_lastname'] ?? '') ?>" placeholder="Tus apellidos">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Correo electrónico</label>
                                    <input type="email" name="reporter_email" class="form-control reporter-input" value="<?= htmlspecialchars($_POST['reporter_email'] ?? '') ?>" placeholder="Para recibir notificaciones">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-dark">Teléfono</label>
                                    <input type="tel" name="reporter_phone" class="form-control" value="<?= htmlspecialchars($_POST['reporter_phone'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold text-dark">Comuna / Ciudad</label>
                                    <input type="text" name="reporter_department" class="form-control" value="<?= htmlspecialchars($_POST['reporter_department'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mt-3 form-text text-primary text-justify" style="font-size:0.85rem;">
                                <i class="bi bi-info-circle me-1"></i> Estos datos quedarán estrictamente protegidos con encriptación de alta seguridad para quienes investiguen el caso.
                            </div>
                        </div>

                        <!-- Captcha -->
                        <?= SimpleCaptcha::render() ?>

                        <div class="alert alert-info small text-justify">
                            <i class="bi bi-shield-lock me-2"></i>
                            Todos los datos son encriptados con alta seguridad antes de ser almacenados. Solo personal autorizado puede acceder a la información desencriptada.
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-epco btn-lg py-3">
                                <i class="bi bi-send me-2"></i>Enviar Denuncia
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
</div>

<script>
function toggleReporterFields() {
    const reporterFields = document.getElementById('reporterFields');
    const anonymousEmailField = document.getElementById('anonymousEmailField');
    if (!reporterFields) return;
    
    const isAnon = document.getElementById('optIdentidad_anonima').checked;
    reporterFields.style.display = isAnon ? 'none' : 'block';
    if (anonymousEmailField) anonymousEmailField.style.display = isAnon ? 'block' : 'none';
    
    // Select input fields and update their 'required' property
    const inputs = document.querySelectorAll('.reporter-input');
    inputs.forEach(i => {
        i.required = !isAnon;
    });
}
// Initialize
document.addEventListener("DOMContentLoaded", toggleReporterFields);

const leyInfoData = <?= json_encode(COMPLAINT_TYPES) ?>;
let leyActivaKey = null;

function toggleInfoLey(key, btn) {
    // Si ya está abierta la misma, cerrar
    if (leyActivaKey === key) {
        cerrarInfoLey();
        return;
    }
    const tipo = leyInfoData[key];
    if (!tipo) return;

    // Resetear chevron de todas las tarjetas
    document.querySelectorAll('.ley-card').forEach(c => {
        c.querySelector('.ley-chevron').style.transform = 'rotate(0deg)';
    });

    // Rotar chevron de la tarjeta pulsada (solo indicador visual, no selección)
    btn.querySelector('.ley-chevron').style.transform = 'rotate(180deg)';
    leyActivaKey = key;

    // Rellenar panel
    document.getElementById('leyPanelIcono').className = 'bi ' + tipo.icon + ' fs-5 mt-1';
    document.getElementById('leyPanelTitulo').textContent = tipo.label;
    document.getElementById('leyPanelNorma').textContent = tipo.ley;
    document.getElementById('leyPanelDesc').textContent = tipo.descripcion || 'Presenta tu denuncia describiendo con detalle los hechos ocurridos.';

    // Mostrar panel con animación
    const panel = document.getElementById('leyInfoPanel');
    panel.style.display = 'block';
    panel.style.opacity = '0';
    panel.style.transform = 'translateY(-6px)';
    requestAnimationFrame(() => {
        panel.style.transition = 'opacity .2s, transform .2s';
        panel.style.opacity = '1';
        panel.style.transform = 'translateY(0)';
    });
}

function cerrarInfoLey() {
    leyActivaKey = null;
    document.querySelectorAll('.ley-card').forEach(c => {
        c.querySelector('.ley-chevron').style.transform = 'rotate(0deg)';
    });
    const panel = document.getElementById('leyInfoPanel');
    panel.style.transition = 'opacity .15s';
    panel.style.opacity = '0';
    setTimeout(() => { panel.style.display = 'none'; }, 150);
}

let selectedFiles = new DataTransfer();

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/(1024*1024)).toFixed(1) + ' MB';
}

function getFileIcon(type) {
    if (type.startsWith('image/')) return 'bi-file-image text-success';
    if (type.startsWith('audio/')) return 'bi-file-music text-info';
    if (type === 'application/pdf') return 'bi-file-pdf text-danger';
    if (type.includes('word')) return 'bi-file-word text-primary';
    if (type.includes('excel') || type.includes('spreadsheet')) return 'bi-file-excel text-success';
    if (type.startsWith('video/')) return 'bi-file-play text-warning';
    return 'bi-file-text';
}

function renderFileList() {
    const list = document.getElementById('fileList');
    list.innerHTML = '';
    if (selectedFiles.files.length === 0) return;
    Array.from(selectedFiles.files).forEach((f, i) => {
        const li = document.createElement('li');
        li.className = 'd-flex align-items-center gap-2 py-2 px-3 rounded mt-2';
        li.style.cssText = 'background:#f1f5f9; border:1px solid #e2e8f0; font-size:0.85rem;';
        li.innerHTML = `<i class="bi ${getFileIcon(f.type)} fs-5"></i><span class="flex-grow-1" style="color:#1e293b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${f.name}</span><span style="color:#64748b;white-space:nowrap;">${formatSize(f.size)}</span><button type="button" class="btn btn-sm p-0 ms-1" style="color:#ef4444;background:none;border:none;" onclick="removeFile(${i})"><i class="bi bi-x-lg"></i></button>`;
        list.appendChild(li);
    });
    document.getElementById('fileInput').files = selectedFiles.files;
}

function updateFileList(newFiles) {
    Array.from(newFiles).forEach(f => {
        if (selectedFiles.files.length < 10) selectedFiles.items.add(f);
    });
    renderFileList();
}

function removeFile(index) {
    const dt = new DataTransfer();
    Array.from(selectedFiles.files).forEach((f, i) => { if (i !== index) dt.items.add(f); });
    selectedFiles = dt;
    renderFileList();
}

function handleDrop(e) {
    e.preventDefault();
    document.getElementById('dropZone').style.cssText = 'border:2px dashed #cbd5e1;border-radius:12px;padding:28px 20px;text-align:center;cursor:pointer;background:#f8fafc;transition:all 0.2s;';
    updateFileList(e.dataTransfer.files);
}
</script><script src="/js/wizard.js"></script>
<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
