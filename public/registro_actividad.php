<?php
/**
 * Portal de Denuncias EPCO - Registro de Actividad
 */
$pageTitle = 'Registro de Actividad';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN]);

// Filtros
$filterAction = sanitize($_GET['action'] ?? '');
$filterUser = sanitize($_GET['user'] ?? '');
$filterDateFrom = sanitize($_GET['from'] ?? '');
$filterDateTo = sanitize($_GET['to'] ?? '');
$search = sanitize($_GET['q'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Construir WHERE dinámico
$where = [];
$params = [];
if ($filterAction !== '') {
    $where[] = "al.action = ?";
    $params[] = $filterAction;
}
if ($filterUser !== '') {
    $where[] = "al.user_id = ?";
    $params[] = (int)$filterUser;
}
if ($filterDateFrom !== '') {
    $where[] = "al.created_at >= ?";
    $params[] = $filterDateFrom . ' 00:00:00';
}
if ($filterDateTo !== '') {
    $where[] = "al.created_at <= ?";
    $params[] = $filterDateTo . ' 23:59:59';
}
if ($search !== '') {
    $where[] = "(al.details LIKE ? OR al.action LIKE ? OR al.ip_address LIKE ?)";
    $searchParam = '%' . $search . '%';
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al $whereSQL");
$stmtCount->execute($params);
$totalRows = $stmtCount->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmtLogs = $pdo->prepare("
    SELECT al.*, u.name as user_name, u.role as user_role
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    $whereSQL
    ORDER BY al.created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmtLogs->execute($params);
$logs = $stmtLogs->fetchAll();

// Obtener lista de acciones únicas y usuarios para filtros
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

// Stats para tarjetas
$todayCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$weekCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$loginCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$uniqueUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();

// Mapeo de acciones a iconos y colores
function getActionStyle($action) {
    $map = [
        'login' => ['icon' => 'bi-box-arrow-in-right', 'color' => '#10b981', 'bg' => 'rgba(16,185,129,0.1)', 'label' => 'Inicio de sesión'],
        'logout' => ['icon' => 'bi-box-arrow-left', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)', 'label' => 'Cierre de sesión'],
        'login_fallido' => ['icon' => 'bi-shield-x', 'color' => '#ef4444', 'bg' => 'rgba(239,68,68,0.1)', 'label' => 'Login fallido'],
        'denuncia_creada' => ['icon' => 'bi-plus-circle', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)', 'label' => 'Denuncia creada'],
        'denuncia_actualizada' => ['icon' => 'bi-pencil-square', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)', 'label' => 'Denuncia actualizada'],
        'estado_cambiado' => ['icon' => 'bi-arrow-repeat', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)', 'label' => 'Estado cambiado'],
        'asignacion' => ['icon' => 'bi-person-check', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)', 'label' => 'Asignación'],
        'nota_investigacion' => ['icon' => 'bi-journal-text', 'color' => '#d946ef', 'bg' => 'rgba(217,70,239,0.1)', 'label' => 'Nota de investigación'],
        'usuario_creado' => ['icon' => 'bi-person-plus', 'color' => '#14b8a6', 'bg' => 'rgba(20,184,166,0.1)', 'label' => 'Usuario creado'],
        'usuario_editado' => ['icon' => 'bi-person-gear', 'color' => '#f97316', 'bg' => 'rgba(249,115,22,0.1)', 'label' => 'Usuario editado'],
        'seguimiento_consultado' => ['icon' => 'bi-search', 'color' => '#64748b', 'bg' => 'rgba(100,116,139,0.1)', 'label' => 'Seguimiento consultado'],
        'evidencia_subida' => ['icon' => 'bi-paperclip', 'color' => '#06b6d4', 'bg' => 'rgba(6,182,212,0.1)', 'label' => 'Evidencia subida'],
    ];
    return $map[$action] ?? ['icon' => 'bi-activity', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)', 'label' => $action];
}

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<style>
.stat-card-activity {
    background: white;
    border-radius: 16px;
    padding: 20px;
    border: 1px solid #e5e7eb;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}
.stat-card-activity:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.08);
}
.stat-card-activity .stat-icon {
    width: 48px; height: 48px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
}
.stat-card-activity .stat-number {
    font-size: 1.8rem; font-weight: 700; line-height: 1;
}
.activity-timeline {
    position: relative;
}
.activity-item {
    display: flex;
    gap: 16px;
    padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
    cursor: default;
}
.activity-item:hover {
    background: #f8fafc;
}
.activity-item:last-child {
    border-bottom: none;
}
.activity-icon-wrap {
    width: 42px; height: 42px; min-width: 42px;
    border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.1rem;
    transition: transform 0.2s ease;
}
.activity-item:hover .activity-icon-wrap {
    transform: scale(1.1);
}
.activity-details {
    flex: 1; min-width: 0;
}
.activity-action-label {
    font-weight: 600; font-size: 0.9rem; color: #1e293b;
}
.activity-meta {
    font-size: 0.8rem; color: #94a3b8; margin-top: 2px;
}
.activity-detail-text {
    font-size: 0.82rem; color: #64748b; margin-top: 4px;
    background: #f8fafc; padding: 6px 10px; border-radius: 8px;
    border-left: 3px solid #e2e8f0;
}
.activity-time {
    font-size: 0.78rem; color: #94a3b8; white-space: nowrap;
    text-align: right; min-width: 100px;
}
.filter-panel {
    background: white;
    border-radius: 16px;
    border: 1px solid #e5e7eb;
    padding: 20px;
    margin-bottom: 24px;
}
.filter-panel .form-control, .filter-panel .form-select {
    border-radius: 10px; font-size: 0.88rem;
    border-color: #e2e8f0;
}
.filter-panel .form-control:focus, .filter-panel .form-select:focus {
    border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
}
.badge-action {
    font-size: 0.72rem; font-weight: 600; padding: 4px 10px;
    border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;
}
.pagination-modern .page-link {
    border: none; border-radius: 10px; margin: 0 2px;
    color: #64748b; font-weight: 500; font-size: 0.88rem;
    padding: 8px 14px;
}
.pagination-modern .page-item.active .page-link {
    background: #0a2540; color: white;
}
.pagination-modern .page-link:hover {
    background: #f1f5f9; color: #0a2540;
}
.empty-state {
    text-align: center; padding: 60px 20px;
}
.empty-state i {
    font-size: 3rem; color: #cbd5e1;
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.activity-item {
    animation: fadeInUp 0.3s ease forwards;
}
</style>

<div class="main-content">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-activity me-2"></i>Registro de Actividad</h4>
            <p class="text-muted mb-0">Auditoría completa del sistema &middot; <?= number_format($totalRows) ?> evento(s)</p>
        </div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <button class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('filterPanel').classList.toggle('d-none')" style="border-radius:10px;">
                <i class="bi bi-funnel me-1"></i>Filtros
            </button>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="stat-card-activity">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(59,130,246,0.1); color: #3b82f6;">
                        <i class="bi bi-calendar-day"></i>
                    </div>
                    <div>
                        <div class="stat-number text-dark"><?= $todayCount ?></div>
                        <div class="text-muted small">Hoy</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-activity">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(139,92,246,0.1); color: #8b5cf6;">
                        <i class="bi bi-calendar-week"></i>
                    </div>
                    <div>
                        <div class="stat-number text-dark"><?= $weekCount ?></div>
                        <div class="text-muted small">Última semana</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-activity">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(16,185,129,0.1); color: #10b981;">
                        <i class="bi bi-box-arrow-in-right"></i>
                    </div>
                    <div>
                        <div class="stat-number text-dark"><?= $loginCount ?></div>
                        <div class="text-muted small">Logins (24h)</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="stat-card-activity">
                <div class="d-flex align-items-center gap-3">
                    <div class="stat-icon" style="background: rgba(249,115,22,0.1); color: #f97316;">
                        <i class="bi bi-people"></i>
                    </div>
                    <div>
                        <div class="stat-number text-dark"><?= $uniqueUsers ?></div>
                        <div class="text-muted small">Usuarios activos (24h)</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Panel -->
    <div id="filterPanel" class="filter-panel <?= ($filterAction || $filterUser || $filterDateFrom || $filterDateTo || $search) ? '' : 'd-none' ?>">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-muted">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0" style="border-radius:10px 0 0 10px;"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="q" class="form-control border-start-0" placeholder="Buscar en detalles, IP..." value="<?= htmlspecialchars($search) ?>" style="border-radius:0 10px 10px 0;">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Acción</label>
                <select name="action" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($actions as $a): $style = getActionStyle($a); ?>
                    <option value="<?= htmlspecialchars($a) ?>" <?= $filterAction === $a ? 'selected' : '' ?>><?= htmlspecialchars($style['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Usuario</label>
                <select name="user" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($users as $u): ?>
                    <option value="<?= $u['id'] ?>" <?= $filterUser == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Desde</label>
                <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filterDateFrom) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-semibold text-muted">Hasta</label>
                <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filterDateTo) ?>">
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm w-100" style="border-radius:10px; height:38px;">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="/registro_actividad" class="btn btn-outline-secondary btn-sm" style="border-radius:10px; height:38px; display:flex; align-items:center;">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Activity Timeline -->
    <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
        <?php if (empty($logs)): ?>
        <div class="empty-state">
            <i class="bi bi-inbox"></i>
            <h6 class="text-muted mt-3">No hay actividad registrada</h6>
            <p class="text-muted small">Ajusta los filtros o espera nuevas acciones</p>
        </div>
        <?php else: ?>
        <div class="activity-timeline">
            <?php foreach ($logs as $i => $log):
                $style = getActionStyle($log['action']);
                $delay = $i * 0.04;
            ?>
            <div class="activity-item" style="animation-delay: <?= $delay ?>s">
                <div class="activity-icon-wrap" style="background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>;">
                    <i class="bi <?= $style['icon'] ?>"></i>
                </div>
                <div class="activity-details">
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="activity-action-label"><?= htmlspecialchars($style['label']) ?></span>
                        <span class="badge-action" style="background: <?= $style['bg'] ?>; color: <?= $style['color'] ?>;">
                            <?= htmlspecialchars($log['action']) ?>
                        </span>
                        <?php if ($log['entity_type']): ?>
                        <span class="badge bg-light text-dark border" style="font-size: 0.72rem; border-radius: 6px;">
                            <?= htmlspecialchars($log['entity_type']) ?> #<?= $log['entity_id'] ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div class="activity-meta">
                        <i class="bi bi-person-circle me-1"></i>
                        <strong><?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?></strong>
                        <?php if (!empty($log['user_role'])): ?>
                            <span class="text-muted">&middot; <?= htmlspecialchars(ucfirst($log['user_role'])) ?></span>
                        <?php endif; ?>
                        &middot;
                        <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($log['ip_address'] ?? '-') ?>
                    </div>
                    <?php if (!empty($log['details'])): ?>
                    <div class="activity-detail-text">
                        <?= htmlspecialchars($log['details']) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="activity-time">
                    <div><?= timeAgo($log['created_at']) ?></div>
                    <div class="mt-1" style="font-size: 0.72rem;"><?= date('d/m H:i', strtotime($log['created_at'])) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
        <div class="p-3 bg-white border-top">
            <nav>
                <ul class="pagination pagination-modern justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>&action=<?= urlencode($filterAction) ?>&user=<?= urlencode($filterUser) ?>&from=<?= urlencode($filterDateFrom) ?>&to=<?= urlencode($filterDateTo) ?>&q=<?= urlencode($search) ?>">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 3);
                    $end = min($totalPages, $page + 3);
                    if ($start > 1): ?>
                        <li class="page-item"><a class="page-link" href="?page=1&action=<?= urlencode($filterAction) ?>&user=<?= urlencode($filterUser) ?>&from=<?= urlencode($filterDateFrom) ?>&to=<?= urlencode($filterDateTo) ?>&q=<?= urlencode($search) ?>">1</a></li>
                        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>&action=<?= urlencode($filterAction) ?>&user=<?= urlencode($filterUser) ?>&from=<?= urlencode($filterDateFrom) ?>&to=<?= urlencode($filterDateTo) ?>&q=<?= urlencode($search) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
                        <li class="page-item"><a class="page-link" href="?page=<?= $totalPages ?>&action=<?= urlencode($filterAction) ?>&user=<?= urlencode($filterUser) ?>&from=<?= urlencode($filterDateFrom) ?>&to=<?= urlencode($filterDateTo) ?>&q=<?= urlencode($search) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>&action=<?= urlencode($filterAction) ?>&user=<?= urlencode($filterUser) ?>&from=<?= urlencode($filterDateFrom) ?>&to=<?= urlencode($filterDateTo) ?>&q=<?= urlencode($search) ?>">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <div class="text-center mt-2">
                <small class="text-muted">Mostrando <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRows) ?> de <?= number_format($totalRows) ?></small>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
