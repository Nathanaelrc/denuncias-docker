<?php
/**
 * Portal Ciudadano de Denuncias - Gestión de Usuarios
 */
$pageTitle = 'Gestión de Usuarios';
require_once __DIR__ . '/../includes/bootstrap.php';
requireUserManagement();

$user = getCurrentUser();
$isSuperAdmin = $user['role'] === ROLE_SUPERADMIN;
$hasAreaSupport = hasAreaAssignmentSupport();
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
        $investigatorArea = $hasAreaSupport ? normalizeInvestigationArea($_POST['investigator_area'] ?? null) : null;
        $department = sanitize($_POST['department'] ?? '');
        $position = sanitize($_POST['position'] ?? '');

        $allowedRoles = [ROLE_ADMIN, ROLE_INVESTIGADOR, ROLE_VIEWER, ROLE_AUDITOR];
        if ($isSuperAdmin) {
            $allowedRoles[] = ROLE_SUPERADMIN;
        }

        if (empty($name) || empty($username) || empty($email) || empty($password)) {
            $errors[] = 'Todos los campos obligatorios deben completarse.';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $errors[] = 'El rol seleccionado no es válido para tu perfil.';
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
                if ($hasAreaSupport) {
                    $pdo->prepare("INSERT INTO users (name, username, email, password, role, investigator_area, department, position, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)")
                        ->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $investigatorArea, $department, $position]);
                } else {
                    $pdo->prepare("INSERT INTO users (name, username, email, password, role, department, position, must_change_password) VALUES (?, ?, ?, ?, ?, ?, ?, 1)")
                        ->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $department, $position]);
                }
                logActivity($_SESSION['user_id'], 'crear_usuario', 'user', $pdo->lastInsertId(), "Usuario: $username, Rol: $role");
                $success = "Usuario '$username' creado exitosamente.";
            }
        }
    }

    if ($action === 'toggle_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId && $userId !== $user['id']) {
            $targetStmt = $pdo->prepare("SELECT role, is_active FROM users WHERE id = ?");
            $targetStmt->execute([$userId]);
            $target = $targetStmt->fetch();

            if (!$target) {
                $errors[] = 'Usuario no encontrado.';
            }

            if (empty($errors)) {
                $pdo->prepare("UPDATE users SET is_active = NOT is_active WHERE id = ?")->execute([$userId]);
                logActivity($_SESSION['user_id'], 'toggle_usuario', 'user', $userId, '');
                $success = 'Estado del usuario actualizado.';
            }
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

        $targetRole = null;
        if ($userId) {
            $targetStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $targetStmt->execute([$userId]);
            $targetRole = $targetStmt->fetchColumn();
        }

        if ($targetRole === false) {
            $errors[] = 'Usuario no encontrado.';
        } elseif (!$isSuperAdmin && $targetRole === ROLE_SUPERADMIN) {
            $errors[] = 'Solo el superadmin puede resetear la contraseña de otro superadmin.';
        } elseif ($userId && empty($passErrors)) {
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
        $investigatorArea = $hasAreaSupport ? normalizeInvestigationArea($_POST['investigator_area'] ?? null) : null;
        $department = sanitize($_POST['department'] ?? '');
        $position = sanitize($_POST['position'] ?? '');

        $targetUser = null;
        if ($userId > 0) {
            $targetStmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ?');
            $targetStmt->execute([$userId]);
            $targetUser = $targetStmt->fetch();
        }

        // Un admin puede editar usuarios, pero no crear/promover superadmin.
        // Si edita a un superadmin, se conserva ese rol para evitar cambios accidentales.
        if (!$isSuperAdmin && $targetUser && $targetUser['role'] === ROLE_SUPERADMIN) {
            $role = ROLE_SUPERADMIN;
        }

        $allowedRoles = [ROLE_ADMIN, ROLE_INVESTIGADOR, ROLE_VIEWER, ROLE_AUDITOR];
        if ($isSuperAdmin) {
            $allowedRoles[] = ROLE_SUPERADMIN;
        } elseif ($targetUser && $targetUser['role'] === ROLE_SUPERADMIN) {
            $allowedRoles[] = ROLE_SUPERADMIN;
        }

        if (!$isSuperAdmin && $role === ROLE_SUPERADMIN && (!$targetUser || $targetUser['role'] !== ROLE_SUPERADMIN)) {
            $errors[] = 'Solo el superadmin puede asignar el rol superadmin.';
        }

        if ($userId && !empty($name) && !empty($email) && in_array($role, $allowedRoles, true)) {
            $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check->execute([$email, $userId]);
            if ($check->fetch()) {
                $errors[] = 'El email ya está en uso por otro usuario.';
            } elseif (!empty($errors)) {
                // Errores de validación acumulados
            } else {
                if ($hasAreaSupport) {
                    $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, investigator_area = ?, department = ?, position = ? WHERE id = ?")
                        ->execute([$name, $email, $role, $investigatorArea, $department, $position, $userId]);
                } else {
                    $pdo->prepare("UPDATE users SET name = ?, email = ?, role = ?, department = ?, position = ? WHERE id = ?")
                        ->execute([$name, $email, $role, $department, $position, $userId]);
                }
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
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1")->fetchColumn();
            $superAdminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'superadmin' AND is_active = 1")->fetchColumn();
            $targetUser = $pdo->prepare("SELECT role, username FROM users WHERE id = ?");
            $targetUser->execute([$userId]);
            $target = $targetUser->fetch();

            if ($target && $target['role'] === ROLE_SUPERADMIN && !$isSuperAdmin) {
                $errors[] = 'Solo el superadmin puede eliminar a otro superadmin.';
            } elseif ($target && $target['role'] === 'superadmin' && $superAdminCount <= 1) {
                $errors[] = 'No se puede eliminar el último superadmin activo.';
            } elseif ($target && $target['role'] === 'admin' && $adminCount <= 1) {
                $errors[] = 'No se puede eliminar el último administrador activo.';
            } elseif ($target) {
                $pdo->prepare("UPDATE complaints SET investigator_id = NULL WHERE investigator_id = ?")->execute([$userId]);
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

$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['is_active']));
$totalAdmins = count(array_filter($users, fn($u) => in_array($u['role'], [ROLE_ADMIN, ROLE_SUPERADMIN], true)));
$totalInvestigadores = count(array_filter($users, fn($u) => $u['role'] === 'investigador'));
$totalViewers = count(array_filter($users, fn($u) => $u['role'] === 'viewer'));

function getUserGradient(string $role): string {
    return match($role) {
        'admin' => 'linear-gradient(135deg, #dc2626, #991b1b)',
        'investigador' => 'linear-gradient(135deg, #f59e0b, #d97706)',
        default => 'linear-gradient(135deg, #1a6591, #2380b0)',
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
    background: #fff; border-radius: 16px; padding: 20px;
    border: 1px solid #e2e8f0; transition: all 0.3s;
    display: flex; align-items: center; gap: 16px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.user-stats-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.08); transform: translateY(-2px); }
.stats-icon {
    width: 52px; height: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem; color: white; flex-shrink: 0;
}
.stats-value { font-size: 1.6rem; font-weight: 800; color: #1a6591; line-height: 1; }
.stats-label { font-size: 0.8rem; color: #64748b; font-weight: 500; }
.u-card {
    background: #fff; border-radius: 16px; border: 1px solid #e2e8f0;
    overflow: hidden; transition: all 0.3s; position: relative;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.u-card:hover { box-shadow: 0 8px 25px rgba(0,0,0,0.1); transform: translateY(-3px); }
.u-card .u-header {
    padding: 20px 20px 0; display: flex; align-items: flex-start; gap: 14px;
}
.u-avatar {
    width: 52px; height: 52px; min-width: 52px; border-radius: 14px;
    display: flex; align-items: center; justify-content: center;
    color: white; font-weight: 800; font-size: 1.2rem; position: relative;
}
.u-avatar .status-dot {
    position: absolute; bottom: -2px; right: -2px;
    width: 14px; height: 14px; border-radius: 50%; border: 2px solid white;
}
.u-body { padding: 16px 20px 20px; }
.u-info-row {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.82rem; color: #64748b; margin-bottom: 6px;
}
.u-info-row i { width: 16px; text-align: center; font-size: 0.85rem; color: #1a6591; }
.u-role-badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 8px; font-size: 0.75rem;
    font-weight: 700; text-transform: uppercase; letter-spacing: 0.3px;
}
.u-role-badge.admin { background: rgba(220,38,38,0.1); color: #dc2626; }
.u-role-badge.investigador { background: rgba(245,158,11,0.12); color: #d97706; }
.u-role-badge.viewer { background: #e8f0f6; color: #1a6591; }
.search-box {
    border: 1px solid #d1d5db; border-radius: 14px; padding: 10px 16px;
    font-size: 0.9rem; transition: all 0.3s; background: #fff; color: #1e293b;
}
.search-box::placeholder { color: #9ca3af; }
.search-box:focus { border-color: #1a6591; box-shadow: 0 0 0 3px rgba(26,101,145,0.1); outline: none; background: #fff; }
.filter-chip {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 6px 14px; border-radius: 10px; font-size: 0.82rem;
    font-weight: 600; cursor: pointer; transition: all 0.2s;
    border: 1px solid #d1d5db; background: #fff; color: #64748b;
}
.filter-chip.active { border-color: #1a6591; background: #1a6591; color: white; }
.filter-chip:hover:not(.active) { border-color: #94a3b8; color: #334155; }
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(12px); }
    to { opacity: 1; transform: translateY(0); }
}
.u-card { animation: fadeInUp 0.4s ease forwards; }
.no-results { text-align: center; padding: 60px 20px; }
.no-results i { font-size: 3rem; color: #cbd5e1; }
.u-body .border-top { border-color: #f1f5f9 !important; }
.dropdown-menu { background: #fff; border: 1px solid #e2e8f0; }
.dropdown-item { color: #334155; }
.dropdown-item:hover { background: #f1f5f9; color: #1e293b; }

/* Corrige contraste de los modales de usuarios frente al tema global oscuro */
#createUserModal .modal-content,
#editUserModal .modal-content,
#deleteUserModal .modal-content {
    background: #ffffff !important;
    color: #0f172a !important;
    border: 1px solid #dbe4ee !important;
}
#createUserModal .modal-header,
#editUserModal .modal-header,
#createUserModal .modal-footer,
#editUserModal .modal-footer {
    border-color: #e2e8f0 !important;
}
#createUserModal .btn-close,
#editUserModal .btn-close,
#deleteUserModal .btn-close {
    filter: none;
}
#createUserModal .text-muted,
#editUserModal .text-muted,
#deleteUserModal .text-muted,
#createUserModal .form-text,
#editUserModal .form-text {
    color: #64748b !important;
}
#createUserModal .modal-title,
#editUserModal .modal-title,
#deleteUserModal .modal-title,
#createUserModal .form-label,
#editUserModal .form-label,
#deleteUserModal .form-label,
#createUserModal .fw-bold,
#editUserModal .fw-bold,
#deleteUserModal .fw-bold {
    color: #0f172a !important;
}
#createUserModal .form-control,
#createUserModal .form-select,
#editUserModal .form-control,
#editUserModal .form-select {
    background: #ffffff !important;
    border: 1px solid #cbd5e1 !important;
    color: #0f172a !important;
}
#createUserModal .form-control:disabled,
#editUserModal .form-control:disabled,
#createUserModal .form-control[readonly],
#editUserModal .form-control[readonly] {
    background: #f1f5f9 !important;
    color: #334155 !important;
    opacity: 1;
}
#createUserModal .form-control::placeholder,
#editUserModal .form-control::placeholder {
    color: #94a3b8;
}
#createUserModal .form-control:focus,
#createUserModal .form-select:focus,
#editUserModal .form-control:focus,
#editUserModal .form-select:focus {
    background: #ffffff !important;
    color: #0f172a !important;
    border-color: #1a6591;
    box-shadow: 0 0 0 0.2rem rgba(26,101,145,0.18);
}
</style>

<div class="main-content">
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
    <div class="alert alert-success alert-dismissible fade show" style="border-radius: 12px; border: none; background: #e8f0f6; color: #1a6591;">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger" style="border-radius: 12px; border: none; background: rgba(220,38,38,0.1); color: #fca5a5;">
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
                    <i class="bi bi-person-check-fill"></i>
                </div>
                <div>
                    <div class="stats-value"><?= $totalInvestigadores ?></div>
                    <div class="stats-label">Delegados</div>
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
                <i class="bi bi-person-check"></i> Delegado
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
                        <div class="status-dot" style="background: <?= $u['is_active'] ? '#2d9ad0' : '#ef4444' ?>;"></div>
                    </div>
                    <div style="min-width: 0; flex: 1;">
                        <div class="fw-bold text-truncate" style="font-size: 1rem; color: #1a6591;">
                            <?= htmlspecialchars($u['name']) ?>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="u-role-badge <?= $u['role'] ?>">
                                <i class="bi <?= getUserRoleIcon($u['role']) ?>"></i>
                                <?= $u['role'] === 'investigador' ? 'Delegado' : ucfirst($u['role']) ?>
                            </span>
                            <?php if (!empty($u['investigator_area'])): ?>
                            <span class="badge" style="background: rgba(37,99,235,0.12); color: #1d4ed8; font-size: 0.68rem; border-radius: 6px;">
                                <?= htmlspecialchars(getInvestigationAreaLabel($u['investigator_area'] ?? null)) ?>
                            </span>
                            <?php endif; ?>
                            <span class="badge <?= $u['is_active'] ? '' : 'bg-danger' ?>" style="<?= $u['is_active'] ? 'background: #e8f0f6; color: #1a6591;' : '' ?> font-size: 0.7rem; border-radius: 6px;">
                                <?= $u['is_active'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                            <?php if ($u['must_change_password'] ?? false): ?>
                            <span class="badge" style="background: rgba(245,158,11,0.12); color: #fbbf24; font-size: 0.68rem; border-radius: 6px;" title="Debe cambiar contraseña">
                                <i class="bi bi-key"></i>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($u['id'] !== $user['id']): ?>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown"
                                style="border-radius: 10px; width: 34px; height: 34px; padding: 0; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" style="border-radius: 12px; border: 1px solid rgba(255,255,255,0.1); box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
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
                                        <i class="bi bi-toggle-<?= $u['is_active'] ? 'on text-info' : 'off text-muted' ?> me-2"></i>
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
                    <?php if (!empty($u['investigator_area'])): ?>
                    <div class="u-info-row">
                        <i class="bi bi-diagram-3 text-primary"></i>
                        <span class="text-truncate">Área: <?= htmlspecialchars(getInvestigationAreaLabel($u['investigator_area'] ?? null)) ?></span>
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
                        <h5 class="modal-title fw-bold" style="font-size: 1.2rem;">Nuevo Usuario</h5>
                        <p class="text-muted small mb-0">Complete los datos del nuevo usuario del portal</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 24px 28px;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nombre Completo *</label>
                            <input type="text" name="name" class="form-control" required style="border-radius: 10px;" placeholder="Ej: María González López">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Usuario *</label>
                            <input type="text" name="username" class="form-control" required style="border-radius: 10px;" placeholder="Ej: maria.gonzalez">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email *</label>
                            <input type="email" name="email" class="form-control" required style="border-radius: 10px;" placeholder="correo@organismo.cl">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Contraseña *</label>
                            <input type="password" name="password" class="form-control" required minlength="<?= PASSWORD_MIN_LENGTH ?>" style="border-radius: 10px;">
                            <div class="form-text">Mínimo <?= PASSWORD_MIN_LENGTH ?> caracteres. Se pedirá cambio al primer acceso.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Rol *</label>
                            <select name="role" class="form-select" style="border-radius: 10px;">
                                <?php if ($isSuperAdmin): ?>
                                <option value="superadmin">Superadmin</option>
                                <?php endif; ?>
                                <option value="viewer">Viewer</option>
                                <option value="investigador">Delegado</option>
                                <option value="auditor">Auditor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6" id="create_area_wrapper">
                            <label class="form-label fw-semibold small">Área (opcional)</label>
                            <select name="investigator_area" id="create_investigator_area" class="form-select" style="border-radius: 10px;">
                                <option value="">Seleccionar área</option>
                                <?php foreach (INVESTIGATION_AREAS as $areaKey => $areaLabel): ?>
                                <option value="<?= htmlspecialchars($areaKey) ?>"><?= htmlspecialchars($areaLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Organismo / Departamento</label>
                            <input type="text" name="department" class="form-control" style="border-radius: 10px;" placeholder="Ej: SERNAC">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Cargo</label>
                            <input type="text" name="position" class="form-control" style="border-radius: 10px;" placeholder="Ej: Analista">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" style="padding: 0 28px 24px; gap: 10px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600;">Crear Usuario</button>
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
                        <h5 class="modal-title fw-bold" style="font-size: 1.2rem;">Editar Usuario</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" style="padding: 24px 28px;">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small">Nombre Completo *</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Usuario</label>
                            <input type="text" id="edit_username" class="form-control" disabled style="border-radius: 10px; background: #f1f5f9;">
                            <div class="form-text" style="font-size: 0.7rem;">No se puede cambiar</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small">Email *</label>
                            <input type="email" name="email" id="edit_email" class="form-control" required style="border-radius: 10px;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Rol *</label>
                            <select name="role" id="edit_role" class="form-select" style="border-radius: 10px;">
                                <?php if ($isSuperAdmin): ?>
                                <option value="superadmin">Superadmin</option>
                                <?php endif; ?>
                                <option value="viewer">Viewer</option>
                                <option value="investigador">Delegado</option>
                                <option value="auditor">Auditor</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-4" id="edit_area_wrapper">
                            <label class="form-label fw-semibold small">Área (opcional)</label>
                            <select name="investigator_area" id="edit_investigator_area" class="form-select" style="border-radius: 10px;">
                                <option value="">Seleccionar área</option>
                                <?php foreach (INVESTIGATION_AREAS as $areaKey => $areaLabel): ?>
                                <option value="<?= htmlspecialchars($areaKey) ?>"><?= htmlspecialchars($areaLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Organismo</label>
                            <input type="text" name="department" id="edit_department" class="form-control" style="border-radius: 10px;">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold small">Cargo</label>
                            <input type="text" name="position" id="edit_position" class="form-control" style="border-radius: 10px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0" style="padding: 0 28px 24px; gap: 10px;">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Cancelar</button>
                    <button type="submit" class="btn btn-primary" style="border-radius: 10px; font-weight: 600;">Guardar Cambios</button>
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
                    <h5 class="fw-bold mb-2">Eliminar Usuario</h5>
                    <p class="text-muted small mb-1">¿Estás seguro de que deseas eliminar a:</p>
                    <p class="fw-bold text-danger mb-3" id="delete_username_display" style="font-size: 1.1rem;"></p>
                    <div class="alert py-2 mb-3" style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 10px; font-size: 0.8rem; color: #92400e;">
                        Acción <strong>irreversible</strong>. Las denuncias se desvincularán.
                    </div>
                    <div class="d-flex gap-2 justify-content-center">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" style="border-radius: 10px; font-weight: 600;">Cancelar</button>
                        <button type="submit" class="btn btn-danger" style="border-radius: 10px; font-weight: 600;">Eliminar</button>
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
    document.getElementById('edit_investigator_area').value = userData.investigator_area || '';
    document.getElementById('edit_department').value = userData.department || '';
    document.getElementById('edit_position').value = userData.position || '';
    new bootstrap.Modal(document.getElementById('editUserModal')).show();
}

function resetPassword(userId, username) {
    const newPass = prompt(`Nueva contraseña para "${username}" (mín. <?= PASSWORD_MIN_LENGTH ?> caracteres):`);
    if (newPass && newPass.length >= <?= PASSWORD_MIN_LENGTH ?>) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `<?= csrfInput() ?><input type="hidden" name="action" value="reset_password">`;

        const userIdInput = document.createElement('input');
        userIdInput.type = 'hidden';
        userIdInput.name = 'user_id';
        userIdInput.value = String(userId);
        form.appendChild(userIdInput);

        const passwordInput = document.createElement('input');
        passwordInput.type = 'hidden';
        passwordInput.name = 'new_password';
        passwordInput.value = newPass;
        form.appendChild(passwordInput);

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
