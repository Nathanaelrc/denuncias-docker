<?php
/**
 * Portal Denuncias Ciudadanas - Cambio de Contraseña Obligatorio
 */
$pageTitle = 'Cambiar Contraseña';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth();

$errors  = [];
$success = false;

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
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $u = $stmt->fetch();

            if (!$u || !password_verify($currentPass, $u['password'])) {
                $errors[] = 'La contraseña actual es incorrecta.';
            } elseif (password_verify($newPass, $u['password'])) {
                $errors[] = 'La nueva contraseña no puede ser igual a la actual.';
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
                    ->execute([$hash, $_SESSION['user_id']]);
                $_SESSION['must_change_password'] = false;
                logActivity($_SESSION['user_id'], 'password_changed', 'user', $_SESSION['user_id'], 'Cambio de contraseña obligatorio completado');
                redirect('/panel', 'Contraseña actualizada correctamente.');
            }
        }
    }
}

require_once __DIR__ . '/../includes/encabezado.php';
?>

<div class="min-vh-100 gradient-bg d-flex align-items-center justify-content-center py-5">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card-epco p-4 fade-in">
                    <div class="text-center mb-4">
                        <div class="rounded-circle mx-auto d-flex align-items-center justify-content-center mb-3" style="width:64px;height:64px;background:#1a6591;">
                            <i class="bi bi-key text-white fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-dark">Cambio de Contraseña</h4>
                        <p class="text-muted small">Por seguridad debes establecer una nueva contraseña antes de continuar.</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger small">
                        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
                    </div>
                    <?php endif; ?>

                    <form method="POST">
                        <?= csrfInput() ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Contraseña Actual</label>
                            <input type="password" name="current_password" class="form-control" required autofocus autocomplete="current-password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-dark">Nueva Contraseña</label>
                            <input type="password" name="new_password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
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
                            <label class="form-label fw-semibold small text-dark">Confirmar Contraseña</label>
                            <input type="password" name="confirm_password" class="form-control" required autocomplete="new-password">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-epco">
                                <i class="bi bi-check-circle me-2"></i>Guardar Contraseña
                            </button>
                        </div>
                    </form>
                    <div class="text-center mt-3">
                        <a href="/cerrar_sesion" class="text-muted small">Salir</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
