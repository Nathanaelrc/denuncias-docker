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

    if ($action === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $role = sanitize($_POST['role'] ?? '');
        $department = sanitize($_POST['department'] ?? '');
        $position = sanitize($_POST['position'] ?? '');

        if ($userId && !empty($name) && !empty($email) && in_array($role, ['admin', 'investigador', 'viewer'])) {
            // Verificar que el email no esté en uso por otro usuario
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $userId]);
            if ($check->fetch()) {
                $errors[] = 'El email ya está en uso por otro usuario.';
            } else {
                $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, department = ?, position = ? WHERE id = ?")
                    ->execute([$name, $email, $role, $department, $position, $userId]);
                logActivity($_SESSION['user_id'], 'actualizar_usuario', 'user', $userId, "Datos actualizados");
                $success = 'Usuario actualizado correctamente.';
            }
        } else {
            $errors[] = 'Datos inválidos para actualizar el usuario.';
        }
    }

    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId && $userId !== $user['id']) {
            // Verificar que no sea el último admin
            $adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            $targetUser = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
            $targetUser->execute([$userId]);
            $target = $targetUser->fetch();

            if ($target && $target['role'] === 'admin' && $adminCount <= 1) {
                $errors[] = 'No se puede eliminar el último administrador activo.';
            } elseif ($target) {
                // Reasignar denuncias del investigador a null
                $pdo->prepare("UPDATE complaints SET investigator_id = NULL WHERE investigator_id = ?")->execute([$userId]);
                // Eliminar usuario
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
                logActivity($_SESSION['user_id'], 'eliminar_usuario', 'user', $userId, "Usuario eliminado: {$target['username']}");
                $success = "Usuario '{$target['username']}' eliminado permanentemente.";
            }
        } else {
            $errors[] = 'No puedes eliminar tu propia cuenta.';
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
                                            <button class="dropdown-item" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                                                <i class="bi bi-pencil me-2 text-primary"></i>Editar Usuario
                                            </button>
                                        </li>
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
                                                <i class="bi bi-key me-2 text-warning"></i>Resetear Contraseña
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                                                <i class="bi bi-trash me-2"></i>Eliminar Usuario
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

<!-- Modal Editar Usuario -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editUserForm">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Editar Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">Nombre Completo *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Usuario</label>
                            <input type="text" id="edit_username" class="form-control" disabled>
                            <div class="form-text">El nombre de usuario no se puede cambiar.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rol *</label>
                            <select name="role" id="edit_role" class="form-select">
                                <option value="viewer">Viewer</option>
                                <option value="investigador">Investigador</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Departamento</label>
                            <input type="text" name="department" id="edit_department" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Cargo</label>
                            <input type="text" name="position" id="edit_position" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminación -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="POST" id="deleteUserForm">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-exclamation-triangle me-2"></i>Eliminar Usuario</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-trash text-danger" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-1">¿Estás seguro de que deseas eliminar al usuario:</p>
                    <p class="fw-bold text-danger" id="delete_username_display"></p>
                    <div class="alert alert-warning small text-start">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Esta acción es <strong>irreversible</strong>. Las denuncias asignadas a este usuario serán desvinculadas.
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editUser(userData) {
    document.getElementById('edit_user_id').value = userData.id;
    document.getElementById('edit_name').value = userData.name;
    document.getElementById('edit_username').value = userData.username;
    document.getElementById('edit_email').value = userData.email;
    document.getElementById('edit_role').value = userData.role;
    document.getElementById('edit_department').value = userData.department || '';
    document.getElementById('edit_position').value = userData.position || '';

    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

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

function deleteUser(userId, username) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username_display').textContent = username;
    new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
}
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
