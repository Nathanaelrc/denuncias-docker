<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Registro de Actividad
 */
$pageTitle = 'Registro de Actividad';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuditAccess();

// Filtros
$filterAction = sanitize($_GET['action'] ?? '');
$filterUser = sanitize($_GET['user'] ?? '');
$filterDateFrom = sanitize($_GET['from'] ?? '');
$filterDateTo = sanitize($_GET['to'] ?? '');
$search = sanitize($_GET['q'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset = ($page - 1) * $perPage;

$baseWhere = "(al.entity_type IS NULL OR al.entity_type <> 'complaint')";

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

$whereSQL = 'WHERE ' . $baseWhere;
if ($where) {
    $whereSQL .= ' AND ' . implode(' AND ', $where);
}

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
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs WHERE entity_type IS NULL OR entity_type <> 'complaint' ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);
$users = $pdo->query("SELECT id, name FROM users ORDER BY name")->fetchAll();

// Stats para tarjetas
$todayCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE (entity_type IS NULL OR entity_type <> 'complaint') AND DATE(created_at) = CURDATE()")->fetchColumn();
$weekCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE (entity_type IS NULL OR entity_type <> 'complaint') AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$loginCount = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE action = 'login' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();
$uniqueUsers = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE (entity_type IS NULL OR entity_type <> 'complaint') AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetchColumn();

// Todos los registros para el informe (sin paginación, máx. 2000)
$stmtReport = $pdo->prepare("
    SELECT al.*, u.name as user_name, u.role as user_role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $whereSQL
    ORDER BY al.created_at DESC
    LIMIT 2000
");
$stmtReport->execute($params);
$reportLogs = $stmtReport->fetchAll();

// Mapeo de acciones a iconos y colores
function getActionStyle($action) {
    $map = [
        'login' => ['icon' => 'bi-box-arrow-in-right', 'color' => '#2d9ad0', 'bg' => 'rgba(26,101,145,0.1)', 'label' => 'Inicio de sesión'],
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
    background: #1a6591; color: white;
}
.pagination-modern .page-link:hover {
    background: #f1f5f9; color: #1a6591;
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
@media print {
    @page { size: A4 landscape; margin: 1.5cm; }
    html, body { height: auto !important; min-height: 0 !important; }
    .print-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
            <button class="btn btn-sm btn-outline-primary" onclick="generarInforme()" style="border-radius:10px;">
                <i class="bi bi-printer me-1"></i>Generar Informe
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
                    <div class="stat-icon" style="background: rgba(26,101,145,0.1); color: #2d9ad0;">
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

<!-- INFORME DE AUDITORÍA (solo impresión) -->
<div id="informe-print" style="display:none;">
    <div style="font-family: Arial, sans-serif; color: #1e293b;">
        <div style="border-bottom: 3px solid #1a6591; padding-bottom: 16px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-end;">
            <div>
                <div style="font-size: 1.1rem; font-weight: 700; color: #1a6591;">Empresa Portuaria Coquimbo</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #1e293b; margin-top: 4px;">Informe de Auditoría</div>
                <div style="font-size: 0.9rem; color: #64748b; margin-top: 2px;">Registro de Actividad &mdash; Portal Ley Karin</div>
            </div>
            <div style="text-align: right; font-size: 0.8rem; color: #64748b;">
                <div>Generado: <?= date('d/m/Y H:i') ?></div>
                <div>Por: <?= htmlspecialchars($user['name']) ?></div>
                <div>Total eventos: <?= number_format($totalRows) ?></div>
            </div>
        </div>
        <?php if ($filterAction || $filterUser || $filterDateFrom || $filterDateTo || $search): ?>
        <div style="background: #f1f5f9; padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; font-size: 0.82rem;">
            <strong>Filtros aplicados:</strong>
            <?php if ($filterAction): ?> Acci&oacute;n: <em><?= htmlspecialchars(getActionStyle($filterAction)['label']) ?></em><?php endif; ?>
            <?php if ($filterUser): $uName = array_column($users, 'name', 'id')[$filterUser] ?? $filterUser; ?> &middot; Usuario: <em><?= htmlspecialchars($uName) ?></em><?php endif; ?>
            <?php if ($filterDateFrom): ?> &middot; Desde: <em><?= htmlspecialchars($filterDateFrom) ?></em><?php endif; ?>
            <?php if ($filterDateTo): ?> &middot; Hasta: <em><?= htmlspecialchars($filterDateTo) ?></em><?php endif; ?>
            <?php if ($search): ?> &middot; B&uacute;squeda: <em>"<?= htmlspecialchars($search) ?>"</em><?php endif; ?>
        </div>
        <?php endif; ?>
        <div style="display: flex; gap: 16px; margin-bottom: 20px;">
            <div style="flex: 1; background: #dbeafe; padding: 12px; border-radius: 8px; text-align: center;">
                <div style="font-size: 1.4rem; font-weight: 700; color: #1a6591;"><?= $todayCount ?></div>
                <div style="font-size: 0.75rem; color: #475569;">Hoy</div>
            </div>
            <div style="flex: 1; background: #ede9fe; padding: 12px; border-radius: 8px; text-align: center;">
                <div style="font-size: 1.4rem; font-weight: 700; color: #8b5cf6;"><?= $weekCount ?></div>
                <div style="font-size: 0.75rem; color: #475569;">Última semana</div>
            </div>
            <div style="flex: 1; background: #dcfce7; padding: 12px; border-radius: 8px; text-align: center;">
                <div style="font-size: 1.4rem; font-weight: 700; color: #16a34a;"><?= $loginCount ?></div>
                <div style="font-size: 0.75rem; color: #475569;">Logins (24h)</div>
            </div>
            <div style="flex: 1; background: #fff7ed; padding: 12px; border-radius: 8px; text-align: center;">
                <div style="font-size: 1.4rem; font-weight: 700; color: #ea580c;"><?= $uniqueUsers ?></div>
                <div style="font-size: 0.75rem; color: #475569;">Usuarios activos (24h)</div>
            </div>
        </div>
        <table class="print-table" style="width: 100%; border-collapse: collapse; font-size: 0.78rem;">
            <thead>
                <tr style="background: #1a6591; color: white;">
                    <th style="padding: 8px 10px; text-align: left;">#</th>
                    <th style="padding: 8px 10px; text-align: left;">Fecha y Hora</th>
                    <th style="padding: 8px 10px; text-align: left;">Acci&oacute;n</th>
                    <th style="padding: 8px 10px; text-align: left;">Usuario</th>
                    <th style="padding: 8px 10px; text-align: left;">Rol</th>
                    <th style="padding: 8px 10px; text-align: left;">IP</th>
                    <th style="padding: 8px 10px; text-align: left;">Entidad</th>
                    <th style="padding: 8px 10px; text-align: left;">Detalles</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reportLogs as $i => $r): $rs = getActionStyle($r['action']); ?>
                <tr style="background: <?= $i % 2 === 0 ? '#ffffff' : '#f8fafc' ?>; border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 7px 10px; color: #94a3b8;"><?= $i + 1 ?></td>
                    <td style="padding: 7px 10px; white-space: nowrap;"><?= date('d/m/Y H:i:s', strtotime($r['created_at'])) ?></td>
                    <td style="padding: 7px 10px; font-weight: 600; color: <?= $rs['color'] ?>;"><?= htmlspecialchars($rs['label']) ?></td>
                    <td style="padding: 7px 10px;"><?= htmlspecialchars($r['user_name'] ?? 'Sistema') ?></td>
                    <td style="padding: 7px 10px; color: #64748b;"><?= htmlspecialchars(ucfirst($r['user_role'] ?? '-')) ?></td>
                    <td style="padding: 7px 10px; font-family: monospace; font-size: 0.73rem;"><?= htmlspecialchars($r['ip_address'] ?? '-') ?></td>
                    <td style="padding: 7px 10px; color: #64748b;"><?= $r['entity_type'] ? htmlspecialchars($r['entity_type'] . ' #' . $r['entity_id']) : '-' ?></td>
                    <td style="padding: 7px 10px; color: #475569; max-width: 200px;"><?= htmlspecialchars($r['details'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($reportLogs)): ?>
                <tr><td colspan="8" style="text-align:center; padding: 20px; color: #94a3b8;">No hay eventos registrados</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
        <?php if (count($reportLogs) === 2000): ?>
        <p style="margin-top: 10px; font-size: 0.78rem; color: #94a3b8; text-align: center;">* Informe limitado a los 2.000 eventos m&aacute;s recientes. Use los filtros para acotar el per&iacute;odo.</p>
        <?php endif; ?>
        <div style="margin-top: 24px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 0.72rem; color: #94a3b8; text-align: center;">
            Canal de Denuncias &mdash; Empresa Portuaria Coquimbo &middot; Documento confidencial &middot; Generado el <?= date('d/m/Y \a\l\a\s H:i') ?>
        </div>
    </div>
</div>

<script>
function generarInforme() {
    const informe = document.getElementById('informe-print');
    const saved = [];
    for (const el of document.body.children) {
        if (el === informe || el.tagName === 'SCRIPT') continue;
        saved.push({ el, display: el.style.display });
        el.style.display = 'none';
    }
    // Eliminar min-height del body/html para que no genere página en blanco
    const origBodyHeight = document.body.style.height;
    const origBodyMinHeight = document.body.style.minHeight;
    const origHtmlHeight = document.documentElement.style.height;
    document.body.style.height = 'auto';
    document.body.style.minHeight = '0';
    document.documentElement.style.height = 'auto';
    informe.style.display = 'block';
    window.print();
    informe.style.display = 'none';
    document.body.style.height = origBodyHeight;
    document.body.style.minHeight = origBodyMinHeight;
    document.documentElement.style.height = origHtmlHeight;
    saved.forEach(s => { s.el.style.display = s.display; });
}
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
