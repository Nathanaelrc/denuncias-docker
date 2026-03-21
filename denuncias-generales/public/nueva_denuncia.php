<?php
/**
 * Portal Denuncias Ciudadanas EPCO - Nueva Denuncia
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

    if (empty($errors)) {
        $data = [
            'complaint_type'       => $complaintType,
            'description'          => $description,
            'is_anonymous'         => $isAnonymous,
            'involved_persons'     => trim($_POST['involved_persons'] ?? '') ?: null,   // N° referencia / boleta
            'evidence_description' => trim($_POST['evidence_description'] ?? '') ?: null,
            'reporter_name'        => $isAnonymous ? null : (trim($_POST['reporter_name'] ?? '') ?: null),
            'reporter_email'       => $isAnonymous ? null : (trim($_POST['reporter_email'] ?? '') ?: null),
            'reporter_phone'       => $isAnonymous ? null : (trim($_POST['reporter_phone'] ?? '') ?: null),
            'reporter_department'  => $isAnonymous ? null : (trim($_POST['reporter_department'] ?? '') ?: null),
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
                    <form method="POST" action="/nueva_denuncia" id="formDenuncia">
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
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
