<?php
/**
 * Portal Denuncias Ciudadanas Empresa Portuaria Coquimbo - Barra Lateral Admin/Investigador
 */

if (!isset($user)) {
    $user = isLoggedIn() ? getCurrentUser() : null;
}
if (!$user) { header('Location: ' . APP_BASE_PATH . '/iniciar_sesion'); exit; }

$isAdmin        = $user['role'] === ROLE_ADMIN;
$isInvestigador = $user['role'] === ROLE_INVESTIGADOR;
$currentPage    = basename($_SERVER['PHP_SELF'], '.php');

// Estadísticas para badges
global $pdo;
$_cf = getConflictFilter($user, '');
$_sidebarStmt = $pdo->prepare("
    SELECT COUNT(*) as total,
        SUM(CASE WHEN status = 'recibida' THEN 1 ELSE 0 END) as nuevas,
        SUM(CASE WHEN status = 'en_investigacion' THEN 1 ELSE 0 END) as en_investigacion
    FROM complaints " . ($_cf['where_sql'] ?: '') . "
");
$_sidebarStmt->execute($_cf['params']);
$stats = $_sidebarStmt->fetch();

$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmtUnread->execute([$user['id']]);
$unreadNotifs = $stmtUnread->fetchColumn();

?>
<style>
    .epco-topbar {
        position: fixed; top: 0; left: 0; right: 0; height: 60px;
        background: linear-gradient(135deg, #1a6591 0%, #2380b0 100%);
        z-index: 1001; display: flex; align-items: center;
        justify-content: space-between; padding: 0 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.15);
    }
    .epco-logo-btn {
        display: flex; align-items: center; gap: 12px;
        background: rgba(255,255,255,0.1); border: none;
        padding: 8px 16px; border-radius: 12px; cursor: pointer;
        transition: all 0.3s ease; color: white;
    }
    .epco-logo-btn:hover { background: rgba(255,255,255,0.2); transform: scale(1.02); }
    .epco-logo-btn .menu-icon { font-size: 1.2rem; transition: transform 0.3s ease; }
    .epco-logo-btn.active .menu-icon { transform: rotate(90deg); }
    .topbar-right { display: flex; align-items: center; gap: 15px; }
    .topbar-clock {
        display: flex; align-items: center; gap: 8px;
        background: rgba(255,255,255,0.15); padding: 8px 15px;
        border-radius: 10px; color: white; font-size: 0.9rem;
    }
    .topbar-user { display: flex; align-items: center; gap: 10px; color: white; }
    .topbar-avatar {
        width: 38px; height: 38px; background: rgba(255,255,255,0.2);
        border-radius: 10px; display: flex; align-items: center;
        justify-content: center; font-weight: 600;
    }
    .epco-sidebar {
        position: fixed; top: 60px; left: -300px; width: 300px;
        height: calc(100vh - 60px);
        background: linear-gradient(180deg, #1a6591 0%, #2380b0 100%);
        z-index: 1000; transition: left 0.35s cubic-bezier(.17,.67,.36,.99);
        overflow-y: auto; box-shadow: 4px 0 20px rgba(0,0,0,0.2); padding: 20px 0;
    }
    .epco-sidebar.open { left: 0; }
    .sidebar-overlay {
        position: fixed; top: 60px; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); z-index: 999; display: none; opacity: 0; transition: opacity 0.3s;
    }
    .sidebar-overlay.show { display: block; opacity: 1; }
    .sidebar-section { padding: 0 15px; margin-bottom: 20px; }
    .sidebar-section-title {
        color: rgba(255,255,255,0.4); font-size: 0.7rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: 1.5px; padding: 0 15px; margin-bottom: 8px;
    }
    .sidebar-link {
        display: flex; align-items: center; gap: 12px; padding: 12px 15px;
        color: rgba(255,255,255,0.75); text-decoration: none; border-radius: 10px;
        transition: all 0.2s; font-weight: 500; font-size: 0.95rem;
    }
    .sidebar-link:hover { background: rgba(255,255,255,0.12); color: white; transform: translateX(5px); }
    .sidebar-link.active { background: rgba(255,255,255,0.18); color: white; font-weight: 600; }
    .sidebar-link .badge { margin-left: auto; }
    .sidebar-link i { font-size: 1.1rem; width: 24px; text-align: center; }
    .main-content {
        margin-top: 60px; padding: 30px; min-height: calc(100vh - 60px);
        background: #f1f5f9; transition: margin-left 0.35s cubic-bezier(.17,.67,.36,.99);
        color: #1e293b;
    }
    /* Light theme overrides inside main-content */
    .main-content .card { background: #fff; border: 1px solid #e2e8f0; color: #1e293b; box-shadow: 0 1px 3px rgba(0,0,0,0.08); }
    .main-content .card .card-header { background: #f8fafc !important; border-color: #e2e8f0; color: #1e293b; }
    .main-content .card .card-body { color: #1e293b; }
    .main-content .card .card-footer { background: #f8fafc !important; border-color: #e2e8f0; color: #1e293b; }
    .main-content .text-dark { color: #1e293b !important; }
    .main-content .text-muted { color: #64748b !important; }
    .main-content .fw-bold { color: #1e293b; }
    .main-content h4, .main-content h5, .main-content h6 { color: #1e293b; }
    .main-content .table { color: #1e293b; --bs-table-bg: transparent; }
    .main-content .table th { color: #64748b; border-color: #e2e8f0; background: #f8fafc; }
    .main-content .table td { border-color: #f1f5f9; color: #334155; }
    .main-content .table-hover tbody tr:hover { background: #f8fafc; }
    .main-content .table-light { --bs-table-bg: #f8fafc; }
    .main-content .form-control, .main-content .form-select { background: #fff; border-color: #d1d5db; color: #1e293b; }
    .main-content .form-control::placeholder { color: #9ca3af; }
    .main-content .form-control:focus, .main-content .form-select:focus { background: #fff; border-color: #1a6591; color: #1e293b; box-shadow: 0 0 0 0.2rem rgba(26,101,145,0.15); }
    .main-content .input-group-text { background: #f1f5f9; border-color: #d1d5db; color: #64748b; }
    .main-content .modal-content { background: #fff; border-color: #e2e8f0; color: #1e293b; }
    .main-content .modal-header { border-color: #e2e8f0; color: #1e293b; }
    .main-content .modal-footer { border-color: #e2e8f0; }
    .main-content .btn-close { filter: none; }
    .main-content .alert-success { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
    .main-content .alert-info { background: #eff6ff; border-color: #bfdbfe; color: #1e40af; }
    .main-content .alert-warning { background: #fffbeb; border-color: #fde68a; color: #92400e; }
    .main-content .alert-danger { background: #fef2f2; border-color: #fecaca; color: #991b1b; }
    .main-content .border { border-color: #e2e8f0 !important; }
    .main-content .border-top, .main-content .border-bottom { border-color: #e2e8f0 !important; }
    .main-content a { color: #1a6591; }
    .main-content a:hover { color: #145275; }
    .main-content .btn-outline-secondary { color: #64748b; border-color: #cbd5e1; }
    .main-content .btn-outline-secondary:hover { background: #f1f5f9; color: #1e293b; border-color: #94a3b8; }
    .main-content .pagination .page-link { background: #fff; border-color: #e2e8f0; color: #1a6591; }
    .main-content .pagination .page-item.active .page-link { background: #1a6591; border-color: #1a6591; color: #fff; }
    .main-content .pagination .page-link:hover { background: #f1f5f9; color: #145275; }
    .main-content .badge.bg-primary,
    .main-content .badge.bg-success,
    .main-content .badge.bg-danger,
    .main-content .badge.bg-dark,
    .main-content .badge.bg-info,
    .main-content .badge.bg-secondary { color: #fff !important; }
    .main-content .badge.bg-warning,
    .main-content .badge.bg-light { color: #212529 !important; }
    .main-content .bg-dark-blue { background: #f8fafc !important; }
    .main-content code { color: #1a6591; background: #f1f5f9; padding: 2px 6px; border-radius: 4px; }
    .main-content .shadow-sm { box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important; }
    @media (max-width: 768px) { .topbar-clock { display: none; } .topbar-user span { display: none; } }
</style>

<div class="epco-topbar">
    <button class="epco-logo-btn" id="sidebarToggle" onclick="toggleSidebar()">
        <i class="bi bi-list menu-icon"></i>
        <span class="logo-text"><i class="bi bi-globe2 me-1"></i>Denuncias Ciudadanas</span>
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
                <span class="badge bg-<?= $isAdmin ? 'danger' : ($isInvestigador ? 'warning' : 'secondary') ?> ms-1 small">
                    <?= ucfirst($user['role']) ?>
                </span>
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

    <?php if ($isAdmin || $isInvestigador): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Revisión</div>
        <a href="/mis_investigaciones" class="sidebar-link <?= $currentPage === 'mis_investigaciones' ? 'active' : '' ?>">
            <i class="bi bi-search"></i> Mis Revisiones
            <?php if ($stats['en_investigacion'] > 0): ?>
            <span class="badge bg-warning text-dark"><?= $stats['en_investigacion'] ?></span>
            <?php endif; ?>
        </a>
        <a href="/detalle_denuncia" class="sidebar-link <?= $currentPage === 'detalle_denuncia' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-lock"></i> Ver Denuncia
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

    <?php if ($isAdmin): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Administración</div>
        <a href="/admin_usuarios" class="sidebar-link <?= $currentPage === 'admin_usuarios' ? 'active' : '' ?>">
            <i class="bi bi-people-fill"></i> Usuarios
        </a>
        <a href="/registro_actividad" class="sidebar-link <?= $currentPage === 'registro_actividad' ? 'active' : '' ?>">
            <i class="bi bi-journal-text"></i> Registro de Actividad
        </a>
        <a href="/reportes" class="sidebar-link <?= $currentPage === 'reportes' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> Reportes
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
            <small class="text-white-50" style="font-size:0.75rem;">AES-256 · Solo admin e investigadores pueden desencriptar</small>
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
