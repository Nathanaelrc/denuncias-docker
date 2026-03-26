<?php
/**
 * Portal de Denuncias EPCO - Cambiar Contraseña Obligatorio
 * Se muestra cuando must_change_password = 1 para el usuario.
 */
$pageTitle = 'Cambiar Contraseña';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth();

// Si ya no requiere cambio, redirigir al panel
if (empty($_SESSION['must_change_password'])) {
    redirect('/panel');
}

$errors = [];
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Token de seguridad inválido.';
    } else {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'La nueva contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        } elseif (!preg_match('/[A-Z]/', $newPass)) {
            $errors[] = 'La nueva contraseña debe incluir al menos una letra mayúscula.';
        } elseif (!preg_match('/[a-z]/', $newPass)) {
            $errors[] = 'La nueva contraseña debe incluir al menos una letra minúscula.';
        } elseif (!preg_match('/[0-9]/', $newPass)) {
            $errors[] = 'La nueva contraseña debe incluir al menos un número.';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $newPass)) {
            $errors[] = 'La nueva contraseña debe incluir al menos un carácter especial (!@#$%^&* etc).';
        } elseif ($newPass !== $confirmPass) {
            $errors[] = 'Las contraseñas no coinciden.';
        } else {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $u = $stmt->fetch();

            if (!$u || !password_verify($currentPass, $u['password'])) {
                $errors[] = 'La contraseña actual es incorrecta.';
            } elseif (password_verify($newPass, $u['password'])) {
                $errors[] = 'La nueva contraseña no puede ser igual a la actual.';
            } else {
                $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
                    ->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
                $_SESSION['must_change_password'] = false;
                logActivity($userId, 'cambio_contrasena', 'user', $userId, 'Contraseña inicial cambiada');
                redirect('/panel', 'Contraseña actualizada correctamente. Bienvenido al sistema.');
            }
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
                    <h3 class="fw-bold mt-2">Cambio de Contraseña Requerido</h3>
                    <p class="opacity-75 small">Por seguridad debes establecer una contraseña personal antes de continuar.</p>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-danger fade-in">
                    <?php foreach ($errors as $e): ?>
                    <div><i class="bi bi-exclamation-circle me-2"></i><?= htmlspecialchars($e) ?></div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <div class="card-epco p-4 fade-in">
                    <form method="POST" action="/cambiar_contrasena">
                        <?= csrfInput() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Contraseña Actual</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="current_password" class="form-control" required autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Nueva Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key"></i></span>
                                <input type="password" name="new_password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                            </div>
                            <div class="form-text">Mínimo <?= PASSWORD_MIN_LENGTH ?> caracteres.</div>
                            <div class="mt-2 p-2" style="background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <p class="mb-1 small fw-semibold text-dark">Requisitos de la contraseña:</p>
                                <ul class="mb-0 small text-muted" style="padding-left: 18px; list-style: none;">
                                    <li><i class="bi bi-check-circle text-success me-1"></i>Mínimo <?= PASSWORD_MIN_LENGTH ?> caracteres</li>
                                    <li><i class="bi bi-check-circle text-success me-1"></i>Al menos una letra mayúscula (A-Z)</li>
                                    <li><i class="bi bi-check-circle text-success me-1"></i>Al menos una letra minúscula (a-z)</li>
                                    <li><i class="bi bi-check-circle text-success me-1"></i>Al menos un número (0-9)</li>
                                    <li><i class="bi bi-check-circle text-success me-1"></i>Al menos un carácter especial (!@#$%&*)</li>
                                    <li><i class="bi bi-check-circle text-success me-1"></i>No puede ser igual a la contraseña actual</li>
                                </ul>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold text-dark">Confirmar Nueva Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                <input type="password" name="confirm_password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-epco btn-lg">
                                <i class="bi bi-arrow-right-circle me-2"></i>Cambiar y Continuar
                            </button>
                        </div>
                    </form>
                </div>

                <div class="text-center mt-3">
                    <a href="/cerrar_sesion" class="text-white-50 text-decoration-none small">
                        <i class="bi bi-arrow-left me-1"></i>Cerrar sesión
                    </a>
                </div>

            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
