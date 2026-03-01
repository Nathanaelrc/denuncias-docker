<?php
/**
 * Portal de Denuncias EPCO - Gestión de Usuarios
 */
$pageTitle = 'Gestión de Usuarios';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN]);

$user = getCurrentUser();
$errors = [];
$success = '';

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_user') {
        $name = sanitize($_POST['name'] ?? '');
        $username = sanitize($_POST['username'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = sanitize($_POST['role'] ?? ROLE_VIEWER);
        $department = sanitize($_POST['department'] ?? '');
        $position = sanitize($_POST['position'] ?? '');

        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            $errors[] = 'Todos los campos obligatorios deben completarse.';
        } elseif (strlen($password) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'La contraseña debe tener al menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                $errors[] = 'El usuario o email ya existe.';
            } else {
                $pdo->prepare("INSERT INTO users (name, username, email, password, role, department, position) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $department, $position]);
                logActivity($_SESSION['user_id'], 'crear_usuario', 'user', $pdo->lastInsertId(), "Usuario: $username, Rol: $role");
                $success = "Usuario '$username' creado exitosamente.";
            }
        }
    }

    if ($action === 'toggle_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId && $userId !== $user['id']) {
            $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$userId]);
            logActivity($_SESSION['user_id'], 'toggle_usuario', 'user', $userId, '');
            $success = 'Estado del usuario actualizado.';
        }
    }

    if ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPass = $_POST['new_password'] ?? '';
        if ($userId && strlen($newPass) >= PASSWORD_MIN_LENGTH) {
            $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
            logActivity($_SESSION['user_id'], 'reset_password', 'user', $userId, '');
            $success = 'Contraseña actualizada.';
        }
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, name")->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill me-2"></i>Usuarios</h4>
            <p class="text-muted mb-0"><?= count($users) ?> usuario(s) registrado(s)</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
            <i class="bi bi-person-plus me-2"></i>Nuevo Usuario
        </button>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th>
                            <th>Usuario</th>
                            <th>Email</th>
                            <th>Rol</th>
                            <th>Departamento</th>
                            <th>Estado</th>
                            <th>Último login</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="fw-semibold"><?= htmlspecialchars($u['name']) ?></td>
                            <td><code><?= htmlspecialchars($u['username']) ?></code></td>
                            <td class="small"><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : ($u['role'] === 'investigador' ? 'warning text-dark' : 'secondary') ?>">
                                    <?= ucfirst($u['role']) ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= htmlspecialchars($u['department'] ?? '-') ?></td>
                            <td>
                                <span class="badge bg-<?= $u['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $u['is_active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                            <td class="small text-muted"><?= $u['last_login'] ? timeAgo($u['last_login']) : 'Nunca' ?></td>
                            <td>
                                <?php if ($u['id'] !== $user['id']): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <form method="POST" class="d-inline">
                                                <?= csrfInput() ?>
                                                <input type="hidden" name="action" value="toggle_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="dropdown-item">
                                                    <i class="bi bi-toggle-<?= $u['is_active'] ? 'on' : 'off' ?> me-2"></i>
                                                    <?= $u['is_active'] ? 'Desactivar' : 'Activar' ?>
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" onclick="resetPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                                                <i class="bi bi-key me-2"></i>Resetear Contraseña
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="create_user">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Nuevo Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nombre Completo *</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Usuario *</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Contraseña *</label>
                            <input type="password" name="password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Rol *</label>
                            <select name="role" class="form-select">
                                <option value="viewer">Viewer</option>
                                <option value="investigador">Investigador</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Departamento</label>
                            <input type="text" name="department" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Cargo</label>
                            <input type="text" name="position" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i>Crear Usuario</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetPassword(userId, username) {
    const newPass = prompt(`Nueva contraseña para "${username}" (mín. <?= PASSWORD_MIN_LENGTH ?> caracteres):`);
    if (newPass && newPass.length >= <?= PASSWORD_MIN_LENGTH ?>) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<?= csrfInput() ?><input type="hidden" name="action" value="reset_password"><input type="hidden" name="user_id" value="${userId}"><input type="hidden" name="new_password" value="${newPass}">`;
        document.body.appendChild(form);
        form.submit();
    } else if (newPass) {
        alert('La contraseña debe tener al menos <?= PASSWORD_MIN_LENGTH ?> caracteres.');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
