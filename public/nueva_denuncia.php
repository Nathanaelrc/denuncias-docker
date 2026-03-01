<?php
/**
 * Portal de Denuncias EPCO - Formulario de Nueva Denuncia
 */
$pageTitle = 'Realizar Denuncia';
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/captcha.php';

$errors = [];
$success = false;
$complaintNumber = '';

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de seguridad inválido. Recarga la página.';
    }

    if (!SimpleCaptcha::validate($_POST['captcha_answer'] ?? '', $_POST['captcha_token'] ?? '')) {
        $errors[] = 'Verificación de seguridad incorrecta.';
    }

    $complaintType = sanitize($_POST['complaint_type'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $isAnonymous = isset($_POST['is_anonymous']) ? 1 : 0;

    if (empty($complaintType) || !array_key_exists($complaintType, COMPLAINT_TYPES)) {
        $errors[] = 'Selecciona un tipo de denuncia válido.';
    }
    if (empty($description) || strlen($description) < 50) {
        $errors[] = 'La descripción debe tener al menos 50 caracteres.';
    }

    if (empty($errors)) {
        $data = [
            'complaint_type' => $complaintType,
            'description' => $description,
            'is_anonymous' => $isAnonymous,
            'involved_persons' => trim($_POST['involved_persons'] ?? '') ?: null,
            'evidence_description' => trim($_POST['evidence_description'] ?? '') ?: null,
            'reporter_name' => $isAnonymous ? null : (trim($_POST['reporter_name'] ?? '') ?: null),
            'reporter_email' => $isAnonymous ? null : (trim($_POST['reporter_email'] ?? '') ?: null),
            'reporter_phone' => $isAnonymous ? null : (trim($_POST['reporter_phone'] ?? '') ?: null),
            'reporter_department' => $isAnonymous ? null : (trim($_POST['reporter_department'] ?? '') ?: null),
            'accused_name' => trim($_POST['accused_name'] ?? '') ?: null,
            'accused_department' => trim($_POST['accused_department'] ?? '') ?: null,
            'accused_position' => trim($_POST['accused_position'] ?? '') ?: null,
            'witnesses' => trim($_POST['witnesses'] ?? '') ?: null,
            'incident_date' => !empty($_POST['incident_date']) ? $_POST['incident_date'] : null,
            'incident_location' => trim($_POST['incident_location'] ?? '') ?: null,
        ];

        $result = createComplaint($data);

        if ($result['success']) {
            $success = true;
            $complaintNumber = $result['complaint_number'];
            logActivity(null, 'denuncia_creada', 'complaint', $result['id'], "Tipo: $complaintType | Anónima: " . ($isAnonymous ? 'Sí' : 'No'));
        } else {
            $errors[] = $result['message'] ?? 'Error al registrar la denuncia.';
        }
    }
}

require_once __DIR__ . '/../includes/encabezado.php';
?>

<!-- Navbar pública -->
<nav class="navbar navbar-expand-lg navbar-epco fixed-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="/">
            <i class="bi bi-shield-lock-fill fs-4"></i>
            <span class="fw-bold">Canal de Denuncias <span class="fw-light">EPCO</span></span>
        </a>
        <div class="d-flex align-items-center gap-2">
            <a href="/acceso" class="btn btn-outline-light btn-sm d-flex align-items-center gap-1" style="border-radius: 8px; font-weight: 600; padding: 6px 14px;">
                <i class="bi bi-box-arrow-in-right"></i>
                <span>Iniciar Sesión</span>
            </a>
            <button class="navbar-toggler border-0 d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#navPublic">
                <i class="bi bi-list text-white fs-4"></i>
            </button>
        </div>
        <div class="collapse navbar-collapse" id="navPublic">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link text-white" href="/"><i class="bi bi-house me-1"></i>Inicio</a></li>
                <li class="nav-item"><a class="nav-link text-white active" href="/nueva_denuncia"><i class="bi bi-pencil-square me-1"></i>Realizar Denuncia</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="/seguimiento"><i class="bi bi-search me-1"></i>Seguimiento</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="/acceso"><i class="bi bi-box-arrow-in-right me-1"></i>Acceso Dashboard</a></li>
            </ul>
        </div>
    </div>
</nav>

<div style="padding-top: 80px;">

<?php if ($success): ?>
    <!-- Denuncia exitosa -->
    <section class="gradient-bg py-5 min-vh-100 d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-7">
                    <div class="card-epco p-5 text-center fade-in">
                        <div class="rounded-circle mx-auto mb-4 d-flex align-items-center justify-content-center" style="width: 90px; height: 90px; background: linear-gradient(135deg, #059669, #10b981);">
                            <i class="bi bi-check-lg text-white" style="font-size: 3rem;"></i>
                        </div>
                        <h2 class="text-dark fw-bold">Denuncia Registrada</h2>
                        <p class="text-muted mb-4">Tu denuncia ha sido recibida y encriptada de forma segura.</p>

                        <div class="bg-light rounded-3 p-4 mb-4">
                            <p class="text-muted small mb-1">Tu número de seguimiento es:</p>
                            <h3 class="text-primary-dark fw-bold mb-0" id="complaintCode"><?= htmlspecialchars($complaintNumber) ?></h3>
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
    <!-- Formulario de denuncia -->
    <section class="gradient-bg py-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-9">
                    <div class="text-white text-center mb-4 fade-in">
                        <h2 class="fw-bold"><i class="bi bi-pencil-square me-2"></i>Realizar Denuncia</h2>
                        <p class="opacity-75">Todos los campos marcados con * son obligatorios. La información se encripta automáticamente.</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger fade-in">
                        <i class="bi bi-exclamation-circle me-2"></i>
                        <strong>Errores:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <div class="card-epco p-4 p-md-5 fade-in">
                        <form method="POST" action="/nueva_denuncia" id="formDenuncia">
                            <?= csrfInput() ?>

                            <!-- Tipo de denuncia -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark">Tipo de Denuncia *</label>
                                <div class="row g-2">
                                    <?php foreach (COMPLAINT_TYPES as $key => $type): ?>
                                    <div class="col-md-4 col-6">
                                        <input type="radio" name="complaint_type" id="type_<?= $key ?>" value="<?= $key ?>" class="btn-check" <?= ($_POST['complaint_type'] ?? '') === $key ? 'checked' : '' ?>>
                                        <label for="type_<?= $key ?>" class="btn btn-outline-dark w-100 text-start py-3">
                                            <i class="bi <?= $type['icon'] ?> me-2"></i><?= $type['label'] ?>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Descripción -->
                            <div class="mb-4">
                                <label for="description" class="form-label fw-semibold text-dark">
                                    Descripción de los hechos *
                                    <span class="encrypted-badge ms-2"><i class="bi bi-lock"></i> Encriptado</span>
                                </label>
                                <textarea name="description" id="description" class="form-control" rows="6" required minlength="50" placeholder="Describe con el mayor detalle posible lo ocurrido: qué sucedió, cuándo, dónde y cómo te afectó..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text">Mínimo 50 caracteres. Sé lo más específico/a posible.</div>
                            </div>

                            <!-- Fecha y lugar del incidente -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="incident_date" class="form-label fw-semibold text-dark">Fecha del incidente</label>
                                    <input type="date" name="incident_date" id="incident_date" class="form-control" value="<?= htmlspecialchars($_POST['incident_date'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="incident_location" class="form-label fw-semibold text-dark">
                                        Lugar del incidente
                                        <span class="encrypted-badge ms-2"><i class="bi bi-lock"></i> Encriptado</span>
                                    </label>
                                    <input type="text" name="incident_location" id="incident_location" class="form-control" value="<?= htmlspecialchars($_POST['incident_location'] ?? '') ?>" placeholder="Ej: Oficina 3er piso, Muelle...">
                                </div>
                            </div>

                            <!-- Persona(s) denunciada(s) -->
                            <h5 class="text-dark fw-bold mb-3 mt-4"><i class="bi bi-person-x me-2"></i>Persona(s) Denunciada(s)</h5>
                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark">
                                        Nombre <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                    </label>
                                    <input type="text" name="accused_name" class="form-control" value="<?= htmlspecialchars($_POST['accused_name'] ?? '') ?>" placeholder="Nombre del denunciado">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark">
                                        Departamento <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                    </label>
                                    <input type="text" name="accused_department" class="form-control" value="<?= htmlspecialchars($_POST['accused_department'] ?? '') ?>" placeholder="Departamento">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold text-dark">
                                        Cargo <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                    </label>
                                    <input type="text" name="accused_position" class="form-control" value="<?= htmlspecialchars($_POST['accused_position'] ?? '') ?>" placeholder="Cargo">
                                </div>
                            </div>

                            <!-- Personas involucradas y Testigos -->
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        Otras personas involucradas <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                    </label>
                                    <textarea name="involved_persons" class="form-control" rows="3" placeholder="Indica otras personas involucradas..."><?= htmlspecialchars($_POST['involved_persons'] ?? '') ?></textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">
                                        Testigos <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                    </label>
                                    <textarea name="witnesses" class="form-control" rows="3" placeholder="¿Hubo testigos? Indica nombres..."><?= htmlspecialchars($_POST['witnesses'] ?? '') ?></textarea>
                                </div>
                            </div>

                            <!-- Evidencia -->
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-dark">
                                    Descripción de evidencia <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                </label>
                                <textarea name="evidence_description" class="form-control" rows="3" placeholder="Describe la evidencia disponible: correos, mensajes, cámaras, documentos..."><?= htmlspecialchars($_POST['evidence_description'] ?? '') ?></textarea>
                            </div>

                            <hr class="my-4">

                            <!-- Anonimato -->
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="is_anonymous" id="is_anonymous" <?= !isset($_POST['is_anonymous']) || isset($_POST['is_anonymous']) ? 'checked' : '' ?> onchange="toggleReporterFields()">
                                    <label class="form-check-label fw-semibold text-dark" for="is_anonymous">
                                        <i class="bi bi-incognito me-1"></i>Denunciar de forma anónima
                                    </label>
                                </div>
                                <div class="form-text">Si desactivas el anonimato, podrás proporcionar tus datos de contacto.</div>
                            </div>

                            <!-- Datos del denunciante (ocultos si es anónima) -->
                            <div id="reporterFields" style="display: none;">
                                <h5 class="text-dark fw-bold mb-3"><i class="bi bi-person me-2"></i>Tus Datos (Confidenciales)</h5>
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            Nombre <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                        </label>
                                        <input type="text" name="reporter_name" class="form-control" value="<?= htmlspecialchars($_POST['reporter_name'] ?? '') ?>" placeholder="Tu nombre">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            Email <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                        </label>
                                        <input type="email" name="reporter_email" class="form-control" value="<?= htmlspecialchars($_POST['reporter_email'] ?? '') ?>" placeholder="tu@email.com">
                                    </div>
                                </div>
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            Teléfono <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                        </label>
                                        <input type="tel" name="reporter_phone" class="form-control" value="<?= htmlspecialchars($_POST['reporter_phone'] ?? '') ?>" placeholder="+56 9 XXXX XXXX">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold text-dark">
                                            Departamento <span class="encrypted-badge ms-1"><i class="bi bi-lock"></i></span>
                                        </label>
                                        <input type="text" name="reporter_department" class="form-control" value="<?= htmlspecialchars($_POST['reporter_department'] ?? '') ?>" placeholder="Tu departamento">
                                    </div>
                                </div>
                            </div>

                            <!-- CAPTCHA -->
                            <?= SimpleCaptcha::render() ?>

                            <!-- Submit -->
                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-epco btn-lg">
                                    <i class="bi bi-shield-lock me-2"></i>Enviar Denuncia (Encriptada)
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
    const chk = document.getElementById('is_anonymous');
    const fields = document.getElementById('reporterFields');
    fields.style.display = chk.checked ? 'none' : 'block';
}
toggleReporterFields();
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
