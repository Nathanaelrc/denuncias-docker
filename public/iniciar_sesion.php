<?php
/**
 * Portal de Denuncias EPCO - Iniciar Sesión (Admin/Investigador)
 */
$pageTitle = 'Iniciar Sesión';
require_once __DIR__ . '/../includes/bootstrap.php';

if (isLoggedIn()) {
    header('Location: /panel');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Token de seguridad inválido.';
    } else {
        $identifier = sanitize($_POST['identifier'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($identifier) || empty($password)) {
            $error = 'Completa todos los campos.';
        } elseif (login($identifier, $password)) {
            logActivity($_SESSION['user_id'], 'login', 'user', $_SESSION['user_id'], 'Inicio de sesión exitoso');
            header('Location: /panel');
            exit;
        } else {
            $error = 'Credenciales incorrectas o cuenta bloqueada.';
            logActivity(null, 'login_fallido', null, null, "Intento con: $identifier");
        }
    }
}

require_once __DIR__ . '/../includes/encabezado.php';
?>

<div class="gradient-bg min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="text-center text-white mb-4 fade-in">
                    <i class="bi bi-shield-lock-fill" style="font-size: 3rem;"></i>
                    <h3 class="fw-bold mt-2">Acceso Restringido</h3>
                    <p class="opacity-75 small">Solo personal autorizado</p>
                </div>

                <?php $flash = getFlashMessage(); if ($flash): ?>
                <div class="alert alert-<?= $flash['type'] ?> fade-in"><?= htmlspecialchars($flash['message']) ?></div>
                <?php endif; ?>

                <?php if ($error): ?>
                <div class="alert alert-danger fade-in">
                    <i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
                <?php endif; ?>

                <div class="card-epco p-4 fade-in">
                    <form method="POST" action="/iniciar_sesion">
                        <?= csrfInput() ?>

                        <div class="mb-3">
                            <label for="identifier" class="form-label fw-semibold text-dark">Usuario o Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-person"></i></span>
                                <input type="text" name="identifier" id="identifier" class="form-control" required autofocus value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold text-dark">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" id="password" class="form-control" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="togglePassword()">
                                    <i class="bi bi-eye" id="eyeIcon"></i>
                                </button>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-epco btn-lg">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
                            </button>
                        </div>
                    </form>
                </div>

                <div class="text-center mt-3">
                    <a href="/" class="text-white-50 text-decoration-none small">
                        <i class="bi bi-arrow-left me-1"></i>Volver al portal público
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function togglePassword() {
    const p = document.getElementById('password');
    const i = document.getElementById('eyeIcon');
    if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
    else { p.type = 'password'; i.className = 'bi bi-eye'; }
}
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
