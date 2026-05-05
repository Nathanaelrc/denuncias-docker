<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Barra Lateral Admin/Investigador
 */

if (!isset($user)) {
    $user = isLoggedIn() ? getCurrentUser() : null;
}
if (!$user) { header('Location: ' . APP_BASE_PATH . '/iniciar_sesion'); exit; }

$isAdmin        = $user['role'] === ROLE_ADMIN;
$isSuperAdmin   = $user['role'] === ROLE_SUPERADMIN;
$isInvestigador = $user['role'] === ROLE_INVESTIGADOR;
$isViewer       = $user['role'] === ROLE_VIEWER;
$isAuditor      = $user['role'] === ROLE_AUDITOR;
$hasComplaintAccess = canAccessComplaints($user);
$canModify      = canModifyComplaints($user);
$canManageUsers = canManageUsers($user);
$canAudit       = canViewAuditLogs($user);
$currentPage    = basename($_SERVER['PHP_SELF'], '.php');

// Estadísticas para badges
global $pdo;
$stats = ['total' => 0, 'nuevas' => 0, 'en_investigacion' => 0];
if ($hasComplaintAccess) {
    $_cf = getConflictFilter($user, '');
    $_sidebarStmt = $pdo->prepare("
        SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'recibida' THEN 1 ELSE 0 END) as nuevas,
            SUM(CASE WHEN status = 'en_investigacion' THEN 1 ELSE 0 END) as en_investigacion
        FROM complaints " . ($_cf['where_sql'] ?: '') . "
    ");
    $_sidebarStmt->execute($_cf['params']);
    $stats = $_sidebarStmt->fetch();
}

$notifFilter = getComplaintNotificationVisibilityFilter($user, 'n', 'c');
$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications n WHERE n.user_id = ? AND n.is_read = 0 {$notifFilter['and_sql']}");
$stmtUnread->execute(array_merge([$user['id']], $notifFilter['params']));
$unreadNotifs = $stmtUnread->fetchColumn();

?>
<script>
document.body.classList.add('epco-admin-layout');
</script>
<div class="epco-topbar">
    <button class="epco-logo-btn" id="sidebarToggle" onclick="toggleSidebar()">
        <i class="bi bi-list menu-icon"></i>
        <span class="logo-text"><i class="bi bi-globe2 me-1"></i>Canal de Denuncias</span>
    </button>
    <div class="topbar-right">
        <div class="topbar-clock d-none d-md-flex">
            <i class="bi bi-clock"></i><span id="topbarClock">--:--</span>
        </div>
        <a href="/notificaciones" class="position-relative text-white text-decoration-none me-3" title="Notificaciones" style="font-size:1.2rem;">
            <i class="bi bi-bell"></i>
            <?php if ($unreadNotifs > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                <?= $unreadNotifs > 99 ? '99+' : $unreadNotifs ?>
            </span><?php endif; ?>
        </a>
        <div class="topbar-user">
            <span class="d-none d-md-inline small">
                <?= htmlspecialchars($user['name']) ?>
                <?php
                $roleBadge = match($user['role']) {
                    ROLE_SUPERADMIN   => ['color' => 'danger',  'label' => 'Superadmin'],
                    ROLE_ADMIN        => ['color' => 'warning', 'label' => 'Admin'],
                    ROLE_INVESTIGADOR => ['color' => 'info',    'label' => 'Investigador'],
                    ROLE_VIEWER       => ['color' => 'success', 'label' => 'Viewer'],
                    ROLE_AUDITOR      => ['color' => 'secondary','label' => 'Auditor'],
                    default           => ['color' => 'secondary','label' => ucfirst($user['role'])],
                };
                ?>
                <span class="badge bg-<?= $roleBadge['color'] ?> ms-1 small"><?= $roleBadge['label'] ?></span>
            </span>
            <div class="topbar-avatar"><?= strtoupper(substr($user['name'], 0, 1)) ?></div>
        </div>
    </div>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<nav class="epco-sidebar" id="epcoSidebar">
    <div class="sidebar-section">
        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background:rgba(255,255,255,0.07);">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:45px;height:45px;background:rgba(255,255,255,0.15);">
                <i class="bi bi-person text-white fs-5"></i>
            </div>
            <div>
                <div class="text-white fw-semibold" style="font-size:0.95rem;"><?= htmlspecialchars($user['name']) ?></div>
                <div class="text-white-50 small"><?= htmlspecialchars($user['position'] ?? $user['role']) ?></div>
            </div>
        </div>
    </div>

    <?php if ($hasComplaintAccess): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Principal</div>
        <a href="/panel" class="sidebar-link <?= $currentPage === 'panel' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>
        <a href="/denuncias_admin" class="sidebar-link <?= $currentPage === 'denuncias_admin' ? 'active' : '' ?>">
            <i class="bi bi-folder2-open"></i> Denuncias
            <?php if ($stats['nuevas'] > 0): ?>
            <span class="badge bg-danger"><?= $stats['nuevas'] ?></span>
            <?php endif; ?>
        </a>
    </div>
    <?php endif; ?>

    <?php if ($hasComplaintAccess): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Revisión</div>
        <?php if ($canModify): ?>
        <a href="/mis_investigaciones" class="sidebar-link <?= $currentPage === 'mis_investigaciones' ? 'active' : '' ?>">
            <i class="bi bi-search"></i> Mis Revisiones
            <?php if ($stats['en_investigacion'] > 0): ?>
            <span class="badge bg-warning text-dark"><?= $stats['en_investigacion'] ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="/detalle_denuncia" class="sidebar-link <?= $currentPage === 'detalle_denuncia' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-<?= $canModify ? 'lock' : 'text' ?>"></i> <?= $canModify ? 'Ver Denuncia' : 'Consultar Denuncia' ?>
        </a>
        <a href="/reportes" class="sidebar-link <?= $currentPage === 'reportes' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> Reportes
        </a>
    </div>
    <?php endif; ?>

    <div class="sidebar-section">
        <div class="sidebar-section-title">Comunicaciones</div>
        <a href="/notificaciones" class="sidebar-link <?= $currentPage === 'notificaciones' ? 'active' : '' ?>">
            <i class="bi bi-bell"></i> Notificaciones
            <?php if ($unreadNotifs > 0): ?>
            <span class="badge bg-danger"><?= $unreadNotifs > 99 ? '99+' : $unreadNotifs ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Administración: usuarios (superadmin + admin) -->
    <?php if ($canManageUsers): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Administración</div>
        <a href="/admin_usuarios" class="sidebar-link <?= $currentPage === 'admin_usuarios' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Usuarios
        </a>
        <?php if ($canAudit): ?>
        <a href="/registro_actividad" class="sidebar-link <?= $currentPage === 'registro_actividad' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Registro de Actividad
        </a>
        <?php endif; ?>
    </div>
    <?php elseif ($canAudit): ?>
    <!-- Solo auditor -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">Auditoría</div>
        <a href="/registro_actividad" class="sidebar-link <?= $currentPage === 'registro_actividad' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Registro de Actividad
        </a>
    </div>
    <?php endif; ?>

    <div class="sidebar-section mt-auto">
        <div class="sidebar-section-title">Sesión</div>
        <a href="/" class="sidebar-link"><i class="bi bi-globe"></i> Portal Público</a>
        <a href="/cerrar_sesion" class="sidebar-link" style="color:#f87171;"><i class="bi bi-box-arrow-left"></i> Cerrar Sesión</a>
    </div>

    <div class="sidebar-section">
        <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.08);border:1px solid rgba(255,255,255,0.12);">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-shield-lock" style="color:#93c5fd;"></i>
                <small class="fw-semibold" style="color:#93c5fd;">Datos Encriptados</small>
            </div>
            <small class="text-white-50" style="font-size:0.75rem;">Alta Seguridad · Solo investigadores pueden desencriptar</small>
        </div>
    </div>
</nav>

<script>
function toggleSidebar() {
    document.getElementById('epcoSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
    document.getElementById('sidebarToggle').classList.toggle('active');
}
function updateClock() {
    const now = new Date();
    document.getElementById('topbarClock').textContent = String(now.getHours()).padStart(2,'0') + ':' + String(now.getMinutes()).padStart(2,'0');
}
updateClock(); setInterval(updateClock, 30000);
</script>
