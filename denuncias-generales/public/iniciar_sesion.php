<?php
/**
 * Portal Denuncias Ciudadanas - Iniciar Sesión (delegados/admin)
 */
$pageTitle = 'Acceso de Delegados';
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) { redirect('/panel'); }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $identifier = sanitize($_POST['identifier'] ?? '');
        $password   = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $errors[] = 'Ingresa usuario/correo y contraseña.';
        } elseif (login($identifier, $password)) {
            logActivity($_SESSION['user_id'], 'login', 'user', $_SESSION['user_id'], 'Inicio de sesión exitoso');
            redirect('/panel');
        } else {
            logActivity(null, 'login_fallido', 'user', null, "Intento fallido: $identifier");
            $errors[] = 'Credenciales incorrectas o cuenta bloqueada.';
        }
    }
}

require_once __DIR__ . '/../includes/encabezado.php';
?>

<div class="min-vh-100 gradient-bg d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">

                <div class="text-center mb-4 fade-in">
                    <img src="/img/Logo01.png" alt="EPCO" style="height:70px;object-fit:contain;" class="mb-3" onerror="this.style.display='none'">
                    <h4 class="fw-bold text-white">Portal Ciudadano</h4>
                    <p class="text-white opacity-75 small">Acceso exclusivo para delegados y administradores</p>
                </div>

                <div class="card-epco p-4 fade-in">
                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small">
                        <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($errors[0]) ?>
                    </div>
                    <?php endif; ?>

                    <?php $flash = getFlashMessage(); if ($flash): ?>
                    <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'info' ?> small">
                        <?= htmlspecialchars($flash['message']) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="/iniciar_sesion">
                        <?= csrfInput() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark small">Usuario o correo</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person" style="color:#1a6591;"></i></span>
                                <input type="text" name="identifier" class="form-control" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" required autocomplete="username" autofocus>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark small">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock" style="color:#1a6591;"></i></span>
                                <input type="password" name="password" class="form-control" required autocomplete="current-password" id="passInput">
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePass()"><i class="bi bi-eye" id="eyeIcon"></i></button>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-epco btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Iniciar Sesión
                            </button>
                        </div>
                    </form>
                </div>

                <div class="text-center mt-3">
                    <a href="/" class="text-white-50 small text-decoration-none">
                        <i class="bi bi-arrow-left me-1"></i>Volver al portal público
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePass() {
    const i = document.getElementById('passInput');
    const e = document.getElementById('eyeIcon');
    i.type = i.type === 'password' ? 'text' : 'password';
    e.className = i.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
