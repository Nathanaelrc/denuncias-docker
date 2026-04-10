<?php
/**
 * Portal Denuncias Ciudadanas Empresa Portuaria Coquimbo - Nueva Denuncia
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

    $complaintType = sanitize($_POST['complaint_type'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $isAnonymous   = isset($_POST['is_anonymous']) ? 1 : 0;

    if (empty($complaintType) || !array_key_exists($complaintType, COMPLAINT_TYPES)) {
        $errors[] = 'Selecciona un tipo de denuncia válido.';
    }
    if (strlen($description) < 50) {
        $errors[] = 'La descripción debe tener al menos 50 caracteres.';
    }

    // Validaciones para denuncias identificadas (no anónimas)
    $reporterName  = '';
    $reporterEmail = '';
    $reporterPhone = '';
    $reporterDept  = '';
    if (!$isAnonymous) {
        $reporterName  = trim($_POST['reporter_name'] ?? '');
        $reporterEmail = trim($_POST['reporter_email'] ?? '');
        $reporterPhone = trim($_POST['reporter_phone'] ?? '');
        $reporterDept  = trim($_POST['reporter_department'] ?? '');

        if (empty($reporterName)) {
            $errors[] = 'El nombre es obligatorio cuando la denuncia no es anónima.';
        } elseif (strlen($reporterName) > 150) {
            $errors[] = 'El nombre no puede superar los 150 caracteres.';
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
            'reporter_email'       => $isAnonymous ? null : ($reporterEmail ?: null),
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
            sendNotification('denuncia_creada', 'Nueva denuncia: ' . $complaintNumber, 'Tipo: ' . (COMPLAINT_TYPES[$complaintType]['label'] ?? $complaintType), 'complaint', $result['id']);
        } else {
            $errors[] = $result['message'] ?? 'Error al registrar la denuncia.';
        }
    }
}

require_once __DIR__ . '/../includes/encabezado.php';
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
        <style>
            @keyframes slideUp{from{opacity:0;transform:translateY(24px)}to{opacity:1;transform:translateY(0)}}
        </style>

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
        <p style="color:#475569;font-size:.92rem;line-height:1.65;margin-bottom:1.3rem;">
            Este canal está destinado a denuncias sobre <strong>operaciones del puerto, contratos, medio ambiente,
            seguridad, corrupción</strong> y otras materias relacionadas con la <strong>Empresa Portuaria Coquimbo</strong>.
        </p>
        <p style="color:#475569;font-size:.88rem;line-height:1.6;margin-bottom:1.5rem;
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

<style>
/* Card blanco para formulario de denuncia */
.card-form {
    background: #fff !important;
    border: none !important;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    color: #1e293b !important;
}
.card-form .form-label,
.card-form .text-dark,
.card-form h5,
.card-form h2,
.card-form h3,
.card-form strong,
.card-form .fw-bold,
.card-form .fw-semibold { color: #1e293b !important; }
.card-form .text-muted { color: #64748b !important; }
.card-form .form-text { color: #94a3b8 !important; }
.card-form .form-control,
.card-form .form-select {
    background: #fff !important;
    border: 1px solid #d1d5db !important;
    color: #1e293b !important;
}
.card-form .form-control::placeholder { color: #9ca3af !important; }
.card-form .form-control:focus,
.card-form .form-select:focus {
    border-color: #1a6591 !important;
    box-shadow: 0 0 0 0.2rem rgba(26,101,145,0.2) !important;
    background: #fff !important;
}
.card-form .btn-outline-dark {
    color: #374151 !important;
    border-color: #d1d5db !important;
    background: #fff !important;
}
.card-form .btn-outline-dark:hover,
.card-form .btn-check:checked + .btn-outline-dark {
    background: #1a6591 !important;
    border-color: #1a6591 !important;
    color: #fff !important;
}
.card-form .alert-info {
    background: #eff6ff !important;
    border-color: #bfdbfe !important;
    color: #1e40af !important;
}
.card-form .alert-warning {
    background: #fefce8 !important;
    border-color: #fde68a !important;
    color: #92400e !important;
}
.card-form .alert-danger {
    background: #fef2f2 !important;
    border-color: #fecaca !important;
    color: #991b1b !important;
}
.card-form .form-check-input {
    background-color: #fff !important;
    border-color: #d1d5db !important;
}
.card-form .form-check-input:checked {
    background-color: #1a6591 !important;
    border-color: #1a6591 !important;
}
.card-form .form-check-label { color: #1a6591 !important; }
.card-form .anon-box {
    background: #f0f7ff !important;
    border: 1px solid #bfdbfe !important;
    border-radius: 12px;
}
.card-form .anon-box .card-body { color: #1e293b; }
.card-form .bg-light {
    background: #f1f5f9 !important;
}
.card-form .bg-light .text-muted { color: #64748b !important; }
.card-form .bg-light h3 { color: #1a6591 !important; }
</style>

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
                    <p class="text-muted mb-4">Tu denuncia ha sido recibida y registrada de forma confidencial.</p>
                    <div class="bg-light rounded-3 p-4 mb-4">
                        <p class="text-muted small mb-1">Tu número de seguimiento es:</p>
                        <h3 class="fw-bold mb-0" style="color:#1a6591;" id="complaintCode"><?= htmlspecialchars($complaintNumber) ?></h3>
                    </div>
                    <div class="alert alert-warning text-start">
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
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="text-white text-center mb-4 fade-in">
                    <h2 class="fw-bold"><i class="bi bi-pencil-square me-2"></i>Realizar Denuncia Ciudadana</h2>
                    <p class="opacity-75">Completa el formulario. Tu información será tratada con total confidencialidad y encriptación.</p>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger fade-in">
                    <i class="bi bi-exclamation-circle me-2"></i><strong>Errores:</strong>
                    <ul class="mb-0 mt-2"><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>

                <div class="card-form p-4 p-md-5 fade-in">
                    <form method="POST" action="/nueva_denuncia" id="formDenuncia" enctype="multipart/form-data">
                        <?= csrfInput() ?>

                        <!-- Tipo de denuncia -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark">Tipo de Denuncia *</label>
                            <div class="row g-2">
                                <?php foreach (COMPLAINT_TYPES as $key => $type): ?>
                                <div class="col-md-4 col-6">
                                    <input type="radio" name="complaint_type" id="type_<?= $key ?>" value="<?= $key ?>" class="btn-check" <?= ($_POST['complaint_type'] ?? '') === $key ? 'checked' : '' ?>>
                                    <label for="type_<?= $key ?>" class="btn btn-outline-dark w-100 text-start py-3 h-100">
                                        <i class="bi <?= $type['icon'] ?> me-2"></i>
                                        <strong><?= $type['label'] ?></strong><br>
                                        <small class="text-muted" style="font-size:0.7rem;"><?= $type['ley'] ?></small>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="mb-4">
                            <label for="description" class="form-label fw-semibold text-dark">Descripción de los hechos *</label>
                            <textarea name="description" id="description" class="form-control" rows="6" required minlength="50"
                                placeholder="Describe con detalle lo ocurrido: qué pasó, cuándo, dónde, cómo te afectó..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                            <div class="form-text">Mínimo 50 caracteres.</div>
                        </div>

                        <!-- Fecha y Lugar -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="incident_date" class="form-label fw-semibold text-dark">Fecha del incidente</label>
                                <input type="date" name="incident_date" id="incident_date" class="form-control" value="<?= htmlspecialchars($_POST['incident_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="incident_location" class="form-label fw-semibold text-dark">Lugar / Sucursal</label>
                                <input type="text" name="incident_location" id="incident_location" class="form-control" value="<?= htmlspecialchars($_POST['incident_location'] ?? '') ?>" placeholder="Ej: Tienda Coquimbo, Mall Portal...">
                            </div>
                        </div>

                        <!-- Empresa/Institución Denunciada -->
                        <h5 class="text-dark fw-bold mb-3 mt-4"><i class="bi bi-buildings me-2"></i>Empresa o Institución Denunciada</h5>
                        <div class="row mb-3">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold text-dark">Nombre</label>
                                <input type="text" name="accused_name" class="form-control" value="<?= htmlspecialchars($_POST['accused_name'] ?? '') ?>" placeholder="Ej: Ripley, ESVAL, Municipalidad...">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">Área / Departamento</label>
                                <input type="text" name="accused_department" class="form-control" value="<?= htmlspecialchars($_POST['accused_department'] ?? '') ?>" placeholder="Área o departamento">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fw-semibold text-dark">RUT Empresa</label>
                                <input type="text" name="accused_position" class="form-control" value="<?= htmlspecialchars($_POST['accused_position'] ?? '') ?>" placeholder="Ej: 76.000.000-0">
                            </div>
                        </div>

                        <!-- Referencia y Testigos -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">N° de Referencia / Boleta / Contrato</label>
                                <input type="text" name="involved_persons" class="form-control" value="<?= htmlspecialchars($_POST['involved_persons'] ?? '') ?>" placeholder="N° boleta, folio, contrato...">
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

                        <!-- Anonimato -->
                        <div class="card border-0 mb-4 anon-box">
                            <div class="card-body">
                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="is_anonymous" id="isAnonymous"
                                        <?= ($_POST['is_anonymous'] ?? null) !== null ? (isset($_POST['is_anonymous']) ? 'checked' : '') : 'checked' ?>>
                                    <label class="form-check-label fw-semibold" for="isAnonymous">
                                        <i class="bi bi-incognito me-1"></i>Deseo mantener mi identidad en anonimato
                                    </label>
                                </div>
                                <div id="reporterFields" style="display: none;">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold text-dark">Nombre completo</label>
                                            <input type="text" name="reporter_name" class="form-control" value="<?= htmlspecialchars($_POST['reporter_name'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold text-dark">Correo electrónico</label>
                                            <input type="email" name="reporter_email" class="form-control" value="<?= htmlspecialchars($_POST['reporter_email'] ?? '') ?>" placeholder="Para recibir notificaciones">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold text-dark">Teléfono (opcional)</label>
                                            <input type="tel" name="reporter_phone" class="form-control" value="<?= htmlspecialchars($_POST['reporter_phone'] ?? '') ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-semibold text-dark">Comuna / Ciudad</label>
                                            <input type="text" name="reporter_department" class="form-control" value="<?= htmlspecialchars($_POST['reporter_department'] ?? '') ?>" placeholder="Ej: Coquimbo, La Serena...">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Captcha -->
                        <?= SimpleCaptcha::render() ?>

                        <div class="alert alert-info small">
                            <i class="bi bi-shield-lock me-2"></i>
                            Todos los datos son encriptados con AES-256 antes de ser almacenados. Solo personal autorizado puede acceder a la información desencriptada.
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
const anonCheck = document.getElementById('isAnonymous');
const reporterFields = document.getElementById('reporterFields');
function toggleReporter() {
    reporterFields.style.display = anonCheck.checked ? 'none' : 'block';
    document.querySelectorAll('#reporterFields input').forEach(i => { i.required = !anonCheck.checked; });
}
if (anonCheck) { anonCheck.addEventListener('change', toggleReporter); toggleReporter(); }

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
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
