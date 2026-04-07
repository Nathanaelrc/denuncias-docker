<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Gestión de Usuarios
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
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'La contraseña debe incluir al menos una letra mayúscula.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'La contraseña debe incluir al menos una letra minúscula.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'La contraseña debe incluir al menos un número.';
        } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'La contraseña debe incluir al menos un carácter especial (!@#$%^&* etc).';
        } else {
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            if ($check->fetch()) {
                $errors[] = 'El usuario o email ya existe.';
            } else {
                $pdo->prepare("INSERT INTO users (name, username, email, password, role, department, position, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, 1)")
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
        $passErrors = [];
        if (strlen($newPass) < PASSWORD_MIN_LENGTH)       $passErrors[] = 'mín. ' . PASSWORD_MIN_LENGTH . ' caracteres';
        if (!preg_match('/[A-Z]/', $newPass))             $passErrors[] = 'una mayúscula';
        if (!preg_match('/[a-z]/', $newPass))             $passErrors[] = 'una minúscula';
        if (!preg_match('/[0-9]/', $newPass))             $passErrors[] = 'un número';
        if (!preg_match('/[^A-Za-z0-9]/', $newPass))     $passErrors[] = 'un carácter especial';
        if ($userId && empty($passErrors)) {
            $pdo->prepare("UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?")->execute([password_hash($newPass, PASSWORD_DEFAULT), $userId]);
            logActivity($_SESSION['user_id'], 'reset_password', 'user', $userId, '');
            $success = 'Contraseña actualizada. El usuario deberá cambiarla al iniciar sesión.';
        } elseif (!empty($passErrors)) {
            $errors[] = 'Contraseña inválida: requiere ' . implode(', ', $passErrors) . '.';
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

// Stats
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['is_active']));
$totalAdmins = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$totalInvestigadores = count(array_filter($users, fn($u) => $u['role'] === 'investigador'));
$totalViewers = count(array_filter($users, fn($u) => $u['role'] === 'viewer'));

// Gradientes para avatares según rol
function getUserGradient(string $role): string {
    return match($role) {
        'admin' => 'linear-gradient(135deg, #dc2626, #991b1b)',
        'investigador' => 'linear-gradient(135deg, #f59e0b, #d97706)',
        default => 'linear-gradient(135deg, #6366f1, #4f46e5)',
    };
}

function getUserRoleIcon(string $role): string {
    return match($role) {
        'admin' => 'bi-shield-lock-fill',
        'investigador' => 'bi-search',
        default => 'bi-eye-fill',
    };
}

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<style>
.user-stats-card {
    background: white; border-radius: 16px; padding: 20px;
    border: 1px solid #e5e7eb; transition: all 0.3s;
    display: flex; align-items: center; gap: 16px;
}
.user-stats-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.06); transform: translateY(-2px); }
.stats-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: white; flex-shrink: 0;
}
.stats-value { font-size: 1.6rem; font-weight: 800; color: #1a6591; line-height: 1; }
.stats-label { font-size: 0.8rem; color: #64748b; font-weight: 500; }

.u-card {
    background: white; border-radius: 16px; border: 1px solid #e5e7eb;
    overflow: hidden; transition: all 0.3s; position: relative;
}
.u-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.08); transform: translateY(-3px); }
.u-card .u-header {
    padding: 20px 20px 0; display: flex; align-items: flex-start; gap: 14px;
}
.u-avatar {
    width: 52px; height: 52px; min-width: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 800; font-size: 1.2rem;
    position: relative;
}
.u-avatar .status-dot {
    position: absolute; bottom: -2px; right: -2px;
    width: 14px; height: 14px; border-radius: 50%;
    border: 2px solid white;
}
.u-body { padding: 16px 20px 20px; }
.u-info-row {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.82rem; color: #64748b; margin-bottom: 6px;
}
.u-info-row i { width: 16px; text-align: center; font-size: 0.85rem; }
.u-role-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 8px; font-size: 0.75rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
}
.u-role-badge.admin { background: #fef2f2; color: #dc2626; }
.u-role-badge.investigador { background: #fffbeb; color: #d97706; }
.u-role-badge.viewer { background: #eef2ff; color: #4f46e5; }
.u-actions {
    display: flex; gap: 6px; padding: 0 20px 16px;
    flex-wrap: wrap;
}
.u-actions .btn {
    border-radius: 10px; font-size: 0.78rem; padding: 6px 12px;
    font-weight: 600; display: inline-flex; align-items: center; gap: 5px;
}
.search-box {
    border: 2px solid #e5e7eb; border-radius: 14px; padding: 10px 16px;
    font-size: 0.9rem; transition: all 0.3s; background: white;
}
.search-box:focus { border-color: #1a6591; box-shadow: 0 0 0 3px rgba(10,37,64,0.1); outline: none; }
.filter-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 10px; font-size: 0.82rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s;
    border: 2px solid #e5e7eb; background: white; color: #64748b;
}
.filter-chip.active { border-color: #1a6591; background: #1a6591; color: white; }
.filter-chip:hover:not(.active) { border-color: #94a3b8; }
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
.u-card { animation: fadeInUp 0.4s ease forwards; }
.no-results { text-align: center; padding: 60px 20px; }
.no-results i { font-size: 3rem; color: #cbd5e1; }
</style>

<div class="main-content">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-people-fill me-2"></i>Gestión de Usuarios</h4>
            <p class="text-muted mb-0"><?= $totalUsers ?> usuario(s) · <?= $activeUsers ?> activo(s)</p>
        </div>
        <button class="btn btn-primary mt-2 mt-md-0" data-bs-toggle="modal" data-bs-target="#createUserModal"
                style="border-radius: 12px; padding: 10px 20px; font-weight: 600;">
            <i class="bi bi-person-plus-fill me-2"></i>Nuevo Usuario
        </button>
    </div>

    <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" style="border-radius: 12px; border: none; background: #f0fdf4; color: #166534;">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="border-radius: 12px; border: none; background: #fef2f2; color: #991b1b;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="user-stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, #1a6591, #2380b0);">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div>
                    <div class="stats-value"><?= $totalUsers ?></div>
                    <div class="stats-label">Total Usuarios</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="user-stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, #dc2626, #991b1b);">
                    <i class="bi bi-shield-lock-fill"></i>
                </div>
                <div>
                    <div class="stats-value"><?= $totalAdmins ?></div>
                    <div class="stats-label">Administradores</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="user-stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="bi bi-search"></i>
                </div>
                <div>
                    <div class="stats-value"><?= $totalInvestigadores ?></div>
                    <div class="stats-label">Investigadores</div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="user-stats-card">
                <div class="stats-icon" style="background: linear-gradient(135deg, #6366f1, #4f46e5);">
                    <i class="bi bi-eye-fill"></i>
                </div>
                <div>
                    <div class="stats-value"><?= $totalViewers ?></div>
                    <div class="stats-label">Visualizadores</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filters -->
    <div class="d-flex flex-wrap gap-3 align-items-center mb-4">
        <div class="flex-grow-1" style="max-width: 400px;">
            <div class="position-relative">
                <i class="bi bi-search position-absolute" style="left: 14px; top: 50%; transform: translateY(-50%); color: #94a3b8;"></i>
                <input type="text" class="search-box w-100" id="userSearch" placeholder="Buscar por nombre, usuario o email..."
                       style="padding-left: 40px;" oninput="filterUsers()">
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="filter-chip active" data-filter="all" onclick="setFilter(this, 'all')">
                <i class="bi bi-grid-fill"></i> Todos
            </button>
            <button class="filter-chip" data-filter="admin" onclick="setFilter(this, 'admin')">
                <i class="bi bi-shield-lock"></i> Admin
            </button>
            <button class="filter-chip" data-filter="investigador" onclick="setFilter(this, 'investigador')">
                <i class="bi bi-search"></i> Investigador
            </button>
            <button class="filter-chip" data-filter="viewer" onclick="setFilter(this, 'viewer')">
                <i class="bi bi-eye"></i> Viewer
            </button>
        </div>
    </div>

    <!-- User Cards Grid -->
    <div class="row g-3" id="usersGrid">
        <?php foreach ($users as $idx => $u): ?>
        <div class="col-md-6 col-xl-4 user-item" data-role="<?= $u['role'] ?>"
             data-name="<?= htmlspecialchars(strtolower($u['name'] . ' ' . $u['username'] . ' ' . $u['email'])) ?>"
             style="animation-delay: <?= $idx * 0.05 ?>s">
            <div class="u-card">
                <div class="u-header">
                    <div class="u-avatar" style="background: <?= getUserGradient($u['role']) ?>;">
                        <?= strtoupper(mb_substr($u['name'], 0, 1)) ?>
                        <div class="status-dot" style="background: <?= $u['is_active'] ? '#22c55e' : '#ef4444' ?>;"></div>
                    </div>
                    <div style="min-width: 0; flex: 1;">
                        <div class="fw-bold text-truncate" style="font-size: 1rem; color: #1a6591;">
                            <?= htmlspecialchars($u['name']) ?>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="u-role-badge <?= $u['role'] ?>">
                                <i class="bi <?= getUserRoleIcon($u['role']) ?>"></i>
                                <?= ucfirst($u['role']) ?>
                            </span>
                            <span class="badge <?= $u['is_active'] ? '' : 'bg-danger' ?>" style="<?= $u['is_active'] ? 'background: #dcfce7; color: #166534;' : '' ?> font-size: 0.7rem; border-radius: 6px;">
                                <?= $u['is_active'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </div>
                    </div>
                    <?php if ($u['id'] !== $user['id']): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"
                                style="border-radius: 10px; width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; border: 1px solid #e5e7eb; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                            <li>
                                <button class="dropdown-item py-2" onclick="editUser(<?= htmlspecialchars(json_encode($u)) ?>)">
                                    <i class="bi bi-pencil-square me-2 text-primary"></i>Editar Usuario
                                </button>
                            </li>
                            <li>
                                <form method="POST" class="d-inline">
                                    <?= csrfInput() ?>
                                    <input type="hidden" name="action" value="toggle_user">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button type="submit" class="dropdown-item py-2">
                                        <i class="bi bi-toggle-<?= $u['is_active'] ? 'on text-success' : 'off text-muted' ?> me-2"></i>
                                        <?= $u['is_active'] ? 'Desactivar' : 'Activar' ?>
                                    </button>
                                </form>
                            </li>
                            <li>
                                <button class="dropdown-item py-2" onclick="resetPassword(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                                    <i class="bi bi-key-fill me-2 text-warning"></i>Resetear Contraseña
                                </button>
                            </li>
                            <li><hr class="dropdown-divider my-1"></li>
                            <li>
                                <button class="dropdown-item py-2 text-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                                    <i class="bi bi-trash3-fill me-2"></i>Eliminar Usuario
                                </button>
                            </li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="u-body">
                    <div class="u-info-row">
                        <i class="bi bi-person text-primary"></i>
                        <span class="text-truncate"><?= htmlspecialchars($u['username']) ?></span>
                    </div>
                    <div class="u-info-row">
                        <i class="bi bi-envelope text-primary"></i>
                        <span class="text-truncate"><?= htmlspecialchars($u['email'] ?: 'Sin email') ?></span>
                    </div>
                    <?php if ($u['department']): ?>
                    <div class="u-info-row">
                        <i class="bi bi-building text-primary"></i>
                        <span class="text-truncate"><?= htmlspecialchars($u['department']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($u['position']): ?>
                    <div class="u-info-row">
                        <i class="bi bi-briefcase text-primary"></i>
                        <span class="text-truncate"><?= htmlspecialchars($u['position']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="u-info-row mt-2 pt-2" style="border-top: 1px solid #f1f5f9;">
                        <i class="bi bi-clock-history text-muted"></i>
                        <span class="text-muted"><small>Último login: <?= $u['last_login'] ? timeAgo($u['last_login']) : '<em>Nunca</em>' ?></small></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="no-results" id="noResults" style="display: none;">
        <i class="bi bi-person-x"></i>
        <h6 class="text-muted mt-3">Sin resultados</h6>
        <p class="text-muted small">No se encontraron usuarios con ese criterio</p>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
            <form method="POST">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="create_user">
                <div class="modal-header border-0 pb-0" style="padding: 28px 28px 0;">
                    <div>
                        <h5 class="modal-title fw-bold" style="font-size: 1.2rem;"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Nuevo Usuario</h5>
                        <p class="text-muted small mb-0">Complete los datos del nuevo usuario</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 24px 28px;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nombre Completo *</label>
                            <input type="text" name="name" class="form-control" required style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;" placeholder="Ej: Juan Pérez López">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Usuario *</label>
                            <input type="text" name="username" class="form-control" required style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;" placeholder="Ej: juan.perez">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email *</label>
                            <input type="email" name="email" class="form-control" required style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;" placeholder="correo@epco.cl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contraseña *</label>
                            <input type="password" name="password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>" style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Rol *</label>
                            <select name="role" class="form-select" style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;">
                                <option value="viewer">👁️ Viewer</option>
                                <option value="investigador">🔍 Investigador</option>
                                <option value="admin">🛡️ Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Departamento</label>
                            <input type="text" name="department" class="form-control" style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;" placeholder="Ej: Recursos Humanos">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Cargo</label>
                            <input type="text" name="position" class="form-control" style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;" placeholder="Ej: Analista">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" style="padding: 0 28px 24px; gap: 10px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; padding: 10px 20px; font-weight: 600;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 10px 24px; font-weight: 600;">
                        <i class="bi bi-check-lg me-1"></i>Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
            <form method="POST" id="editUserForm">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="modal-header border-0 pb-0" style="padding: 28px 28px 0;">
                    <div>
                        <h5 class="modal-title fw-bold" style="font-size: 1.2rem;"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Usuario</h5>
                        <p class="text-muted small mb-0">Modifique los datos del usuario</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 24px 28px;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nombre Completo *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Usuario</label>
                            <input type="text" id="edit_username" class="form-control" disabled style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px; background: #f8fafc;">
                            <div class="form-text" style="font-size: 0.7rem;">No se puede cambiar</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Rol *</label>
                            <select name="role" id="edit_role" class="form-select" style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;">
                                <option value="viewer">👁️ Viewer</option>
                                <option value="investigador">🔍 Investigador</option>
                                <option value="admin">🛡️ Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Departamento</label>
                            <input type="text" name="department" id="edit_department" class="form-control" style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Cargo</label>
                            <input type="text" name="position" id="edit_position" class="form-control" style="border-radius: 10px; border: 2px solid #e5e7eb; padding: 10px 14px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" style="padding: 0 28px 24px; gap: 10px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; padding: 10px 20px; font-weight: 600;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; padding: 10px 24px; font-weight: 600;">
                        <i class="bi bi-check-lg me-1"></i>Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Confirmar Eliminación -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none; overflow: hidden;">
            <form method="POST" id="deleteUserForm">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="modal-body text-center" style="padding: 32px;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: #fef2f2; display: flex; align-items: center; justify-content: center; margin: 0 auto 16px;">
                        <i class="bi bi-trash3-fill text-danger" style="font-size: 1.5rem;"></i>
                    </div>
                    <h5 class="fw-bold mb-2">Eliminar Usuario</h5>
                    <p class="text-muted small mb-1">¿Estás seguro de que deseas eliminar a:</p>
                    <p class="fw-bold text-danger mb-3" id="delete_username_display" style="font-size: 1.1rem;"></p>
                    <div class="alert py-2 mb-3" style="background: #fffbeb; border: 1px solid #fcd34d; border-radius: 10px; font-size: 0.8rem; color: #92400e;">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        Acción <strong>irreversible</strong>. Las denuncias se desvincularán.
                    </div>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; padding: 10px 20px; font-weight: 600;">Cancelar</button>
                        <button type="submit" class="btn btn-danger" style="border-radius: 10px; padding: 10px 24px; font-weight: 600;">
                            <i class="bi bi-trash3 me-1"></i>Eliminar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentFilter = 'all';

function filterUsers() {
    const search = document.getElementById('userSearch').value.toLowerCase();
    const items = document.querySelectorAll('.user-item');
    let visible = 0;

    items.forEach(item => {
        const name = item.dataset.name;
        const role = item.dataset.role;
        const matchSearch = !search || name.includes(search);
        const matchFilter = currentFilter === 'all' || role === currentFilter;
        const show = matchSearch && matchFilter;
        item.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('noResults').style.display = visible === 0 ? '' : 'none';
}

function setFilter(btn, filter) {
    currentFilter = filter;
    document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    filterUsers();
}

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
