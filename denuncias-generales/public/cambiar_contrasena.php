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
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (strlen($newPass) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        }
        if ($newPass !== $confirmPass) {
            $errors[] = 'Las contraseñas no coinciden.';
        }

        if (empty($errors)) {
            $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
            $pdo->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?")
                ->execute([$hash, $_SESSION['user_id']]);
            $_SESSION['must_change_password'] = false;
            logActivity($_SESSION['user_id'], 'password_changed', 'user', $_SESSION['user_id'], 'Cambio de contraseña obligatorio completado');
            redirect('/panel', 'Contraseña actualizada correctamente.');
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
                            <label class="form-label fw-semibold small text-dark">Nueva Contraseña</label>
                            <input type="password" name="new_password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>" autocomplete="new-password">
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
