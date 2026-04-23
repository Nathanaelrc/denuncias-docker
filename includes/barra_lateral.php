<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Barra Lateral Admin/Investigador
 */

if (!isset($user)) {
    $user = isLoggedIn() ? getCurrentUser() : null;
}
if (!$user) {
    header('Location: ' . APP_BASE_PATH . '/acceso');
    exit;
}

$isAdmin = $user['role'] === ROLE_ADMIN;
$isInvestigador = $user['role'] === ROLE_INVESTIGADOR;
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Estadísticas para badges
global $pdo;
$_cf = getConflictFilter($user, '');
$_sidebarStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'recibida' THEN 1 ELSE 0 END) as nuevas,
        SUM(CASE WHEN status = 'en_investigacion' THEN 1 ELSE 0 END) as en_investigacion
    FROM complaints " . ($_cf['where_sql'] ?: '') . "
");
$_sidebarStmt->execute($_cf['params']);
$stats = $_sidebarStmt->fetch();

// Notificaciones no leídas
$stmtUnread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmtUnread->execute([$user['id']]);
$unreadNotifs = $stmtUnread->fetchColumn();

?>

<!-- Topbar -->
<style>
    .epco-topbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 60px;
        background: linear-gradient(135deg, #0c5a8a 0%, #094a72 100%);
        z-index: 1001;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.1);
    }

    .epco-logo-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(255,255,255,0.1);
        border: none;
        padding: 8px 16px;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        color: white;
    }

    .epco-logo-btn:hover {
        background: rgba(255,255,255,0.2);
        transform: scale(1.02);
    }

    .epco-logo-btn .logo-text {
        font-size: 1.1rem;
        font-weight: 700;
    }

    .epco-logo-btn .menu-icon {
        font-size: 1.2rem;
        transition: transform 0.3s ease;
    }

    .epco-logo-btn.active .menu-icon {
        transform: rotate(90deg);
    }

    .topbar-right {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .topbar-clock {
        display: flex;
        align-items: center;
        gap: 8px;
        background: rgba(255,255,255,0.15);
        padding: 8px 15px;
        border-radius: 10px;
        color: white;
        font-size: 0.9rem;
    }

    .topbar-user {
        display: flex;
        align-items: center;
        gap: 10px;
        color: white;
    }

    .topbar-avatar {
        width: 38px;
        height: 38px;
        background: rgba(255,255,255,0.2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
    }

    /* Sidebar */
    .epco-sidebar {
        position: fixed;
        top: 60px;
        left: -300px;
        width: 300px;
        height: calc(100vh - 60px);
        background: linear-gradient(180deg, #1a6591 0%, #0c3d5f 100%);
        z-index: 1000;
        transition: left 0.35s cubic-bezier(.17,.67,.36,.99);
        overflow-y: auto;
        box-shadow: 4px 0 20px rgba(0,0,0,0.2);
        padding: 20px 0;
    }

    .epco-sidebar.open {
        left: 0;
    }

    .sidebar-overlay {
        position: fixed;
        top: 60px;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        display: none;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .sidebar-overlay.show {
        display: block;
        opacity: 1;
    }

    .sidebar-section {
        padding: 0 15px;
        margin-bottom: 20px;
    }

    .sidebar-section-title {
        color: rgba(255,255,255,0.4);
        font-size: 0.7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        padding: 0 15px;
        margin-bottom: 8px;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 15px;
        color: rgba(255,255,255,0.7);
        text-decoration: none;
        border-radius: 10px;
        transition: all 0.2s;
        font-weight: 500;
        font-size: 0.95rem;
    }

    .sidebar-link:hover {
        background: rgba(255,255,255,0.1);
        color: white;
        transform: translateX(5px);
    }

    .sidebar-link.active {
        background: rgba(255,255,255,0.15);
        color: white;
        font-weight: 600;
    }

    .sidebar-link .badge {
        margin-left: auto;
    }

    .sidebar-link i {
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
    }

    /* Content push */
    .main-content {
        margin-top: 60px;
        padding: 30px;
        min-height: calc(100vh - 60px);
        background: #f1f5f9;
        transition: margin-left 0.35s cubic-bezier(.17,.67,.36,.99);
    }

    @media (max-width: 768px) {
        .topbar-clock { display: none; }
        .topbar-user span { display: none; }
    }
</style>

<!-- Top Bar -->
<div class="epco-topbar">
    <button class="epco-logo-btn" id="sidebarToggle" onclick="toggleSidebar()">
        <i class="bi bi-list menu-icon"></i>
        <span class="logo-text">
            <i class="bi bi-shield-lock me-1"></i>Denuncias Empresa Portuaria Coquimbo
        </span>
    </button>

    <div class="topbar-right">
        <div class="topbar-clock d-none d-md-flex">
            <i class="bi bi-clock"></i>
            <span id="topbarClock">--:--</span>
        </div>
        <a href="/notificaciones" class="position-relative text-white text-decoration-none me-3" title="Notificaciones" style="font-size:1.2rem;">
            <i class="bi bi-bell"></i>
            <?php if ($unreadNotifs > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.6rem;">
                <?= $unreadNotifs > 99 ? '99+' : $unreadNotifs ?>
            </span>
            <?php endif; ?>
        </a>
        <div class="topbar-user">
            <span class="d-none d-md-inline small">
                <?= htmlspecialchars($user['name']) ?>
                <span class="badge bg-<?= $isAdmin ? 'danger' : ($isInvestigador ? 'warning' : 'secondary') ?> ms-1 small">
                    <?= ucfirst($user['role']) ?>
                </span>
            </span>
            <div class="topbar-avatar">
                <?= strtoupper(substr($user['name'], 0, 1)) ?>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- Sidebar -->
<nav class="epco-sidebar" id="epcoSidebar">
    <!-- Perfil -->
    <div class="sidebar-section">
        <div class="d-flex align-items-center gap-3 p-3 rounded-3" style="background: rgba(255,255,255,0.05);">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px; background: rgba(255,255,255,0.15);">
                <i class="bi bi-person text-white fs-5"></i>
            </div>
            <div>
                <div class="text-white fw-semibold" style="font-size: 0.95rem;"><?= htmlspecialchars($user['name']) ?></div>
                <div class="text-white-50 small"><?= htmlspecialchars($user['position'] ?? $user['role']) ?></div>
            </div>
        </div>
    </div>

    <!-- Navegación principal -->
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

    <!-- Investigación -->
    <?php if ($isAdmin || $isInvestigador): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title">Investigación</div>
        <a href="/mis_investigaciones" class="sidebar-link <?= $currentPage === 'mis_investigaciones' ? 'active' : '' ?>">
            <i class="bi bi-search"></i> Mis Investigaciones
            <?php if ($stats['en_investigacion'] > 0): ?>
            <span class="badge bg-warning text-dark"><?= $stats['en_investigacion'] ?></span>
            <?php endif; ?>
        </a>
        <a href="/detalle_denuncia" class="sidebar-link <?= $currentPage === 'detalle_denuncia' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-lock"></i> Ver Denuncia
        </a>
    </div>
    <?php endif; ?>

    <!-- Notificaciones -->
    <div class="sidebar-section">
        <div class="sidebar-section-title">Comunicaciones</div>
        <a href="/notificaciones" class="sidebar-link <?= $currentPage === 'notificaciones' ? 'active' : '' ?>">
            <i class="bi bi-bell"></i> Notificaciones
            <?php if ($unreadNotifs > 0): ?>
            <span class="badge bg-danger"><?= $unreadNotifs > 99 ? '99+' : $unreadNotifs ?></span>
            <?php endif; ?>
        </a>
    </div>

    <!-- Administración -->
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

    <!-- Acciones -->
    <div class="sidebar-section mt-auto">
        <div class="sidebar-section-title">Sesión</div>
        <a href="/" class="sidebar-link">
            <i class="bi bi-globe"></i> Portal Público
        </a>
        <a href="/cerrar_sesion" class="sidebar-link" style="color: #f87171;">
            <i class="bi bi-box-arrow-left"></i> Cerrar Sesión
        </a>
    </div>

    <!-- Info encriptación -->
    <div class="sidebar-section">
        <div class="p-3 rounded-3" style="background: rgba(5, 150, 105, 0.15); border: 1px solid rgba(5, 150, 105, 0.3);">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="bi bi-shield-lock text-success"></i>
                <small class="text-success fw-semibold">Datos Encriptados</small>
            </div>
            <small class="text-white-50" style="font-size: 0.75rem;">Alta Seguridad · Solo admin e investigadores pueden desencriptar</small>
        </div>
    </div>
</nav>

<script>
function toggleSidebar() {
    document.getElementById('epcoSidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
    document.getElementById('sidebarToggle').classList.toggle('active');
}

// Reloj
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    document.getElementById('topbarClock').textContent = h + ':' + m;
}
updateClock();
setInterval(updateClock, 30000);
</script>
