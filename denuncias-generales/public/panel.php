<?php
/**
 * Portal Denuncias Ciudadanas - Dashboard
 */
$pageTitle = 'Panel';
require_once __DIR__ . '/../includes/bootstrap.php';
requireComplaintAccess();

$user           = getCurrentUser();
$canRunChannelTest = hasRole([ROLE_ADMIN, ROLE_SUPERADMIN]);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_channel_test') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        redirect('/panel', 'Token de seguridad invalido. Intenta nuevamente.', 'danger');
    }

    if (!$canRunChannelTest) {
        redirect('/panel', 'No tienes permisos para ejecutar pruebas del canal.', 'danger');
    }

    $daysAgo = random_int(0, 30);
    $incidentDate = (new DateTimeImmutable('today'))->sub(new DateInterval('P' . $daysAgo . 'D'))->format('Y-m-d');
    $typeKeys = array_keys(COMPLAINT_TYPES);
    $randomType = !empty($typeKeys) ? $typeKeys[array_rand($typeKeys)] : 'general';
    $token = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    $result = createComplaint([
        'complaint_type'       => $randomType,
        'description'          => 'TEST AUTOMATICO DE CANAL [' . $token . ']. Verificacion interna del flujo completo desde creacion hasta envio.',
        'is_anonymous'         => 1,
        'involved_persons'     => 'Prueba automatizada desde dashboard',
        'evidence_description' => 'Sin evidencia adjunta',
        'reporter_name'        => null,
        'reporter_lastname'    => null,
        'reporter_email'       => null,
        'reporter_phone'       => null,
        'reporter_department'  => null,
        'accused_name'         => 'Prueba de canal',
        'accused_department'   => 'Sistema',
        'accused_position'     => 'Validacion operativa',
        'witnesses'            => null,
        'incident_date'        => $incidentDate,
        'incident_location'    => 'Dashboard administrativo',
    ]);

    if ($result['success']) {
        logActivity((int)$user['id'], 'prueba_canal_generada', 'complaint', (int)$result['id'], 'Token: ' . $token . ' | Dias aleatorios: ' . $daysAgo);
        redirect('/panel', 'Prueba ejecutada. Denuncia de test creada: ' . $result['complaint_number'] . ' (' . $daysAgo . ' dias atras).', 'success');
    } else {
        redirect('/panel', 'No se pudo ejecutar la prueba del canal. ' . ($result['message'] ?? ''), 'danger');
    }
}

$hasComplaintAccess = canAccessComplaints($user);
$hasAreaSupport = hasAreaAssignmentSupport();
$cf = getConflictFilter($user, 'c');
$conflictWhereAnd = $cf['and_sql'];
$conflictParams   = $cf['params'];
$whereDashboard   = 'WHERE c.deleted_at IS NULL';
if ($conflictWhereAnd) {
    $whereDashboard .= ' ' . $conflictWhereAnd;
}

$stmtStats = $pdo->prepare("
    SELECT COUNT(*) as total,
        SUM(CASE WHEN status='recibida' THEN 1 ELSE 0 END) as recibidas,
        SUM(CASE WHEN status='en_investigacion' THEN 1 ELSE 0 END) as en_investigacion,
        SUM(CASE WHEN status='resuelta' THEN 1 ELSE 0 END) as resueltas,
        SUM(CASE WHEN status='desestimada' THEN 1 ELSE 0 END) as desestimadas,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as semana,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as mes
    FROM complaints c
    $whereDashboard
");
$stmtStats->execute($conflictParams);
$stats = $stmtStats->fetch();

$stmtType = $pdo->prepare("SELECT complaint_type, COUNT(*) as total FROM complaints c $whereDashboard GROUP BY complaint_type ORDER BY total DESC");
$stmtType->execute($conflictParams);
$byType = $stmtType->fetchAll();

$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM complaints c $whereDashboard");
$stmtTotal->execute($conflictParams);
$totalComplaints = (int)$stmtTotal->fetchColumn();
$totalPages = max(1, (int)ceil($totalComplaints / $perPage));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$assignedAreaSelect = $hasAreaSupport ? 'assigned_area' : 'NULL AS assigned_area';
$stmtRecent = $pdo->prepare("SELECT id, complaint_number, complaint_type, status, $assignedAreaSelect, is_anonymous, created_at FROM complaints c $whereDashboard ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmtRecent->execute($conflictParams);
$recent = $stmtRecent->fetchAll();

$stmtMonthly = $pdo->prepare("
    SELECT DATE_FORMAT(c.created_at,'%Y-%m') as month, COUNT(*) as total
    FROM complaints c
    WHERE c.deleted_at IS NULL
      AND c.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
      $conflictWhereAnd
    GROUP BY month ORDER BY month ASC
");
$stmtMonthly->execute($conflictParams);
$monthly = $stmtMonthly->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<style>
.dashboard-page {
    color: #1e293b;
    opacity: 1 !important;
    filter: none !important;
}
.dashboard-page .card {
    background: #ffffff !important;
    border: 1px solid #e2e8f0 !important;
    color: #1e293b !important;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06) !important;
}
.dashboard-page .card-header {
    background: #ffffff !important;
    border-color: #e2e8f0 !important;
    color: #0f172a !important;
}
.dashboard-page .bg-dark-blue {
    background: linear-gradient(135deg, #0f5f8f 0%, #1a6591 100%) !important;
    color: #ffffff !important;
}
.dashboard-page .bg-dark-blue .text-muted,
.dashboard-page .bg-dark-blue .small,
.dashboard-page .bg-dark-blue .fw-normal {
    color: rgba(255,255,255,0.8) !important;
}
.dashboard-page .text-dark {
    color: #0f172a !important;
}
.dashboard-page .text-muted {
    color: #64748b !important;
}
.dashboard-page .table,
.dashboard-page .table td,
.dashboard-page .table th {
    color: #1e293b !important;
    border-color: #e2e8f0 !important;
}
.dashboard-page .table-light,
.dashboard-page .table-light th {
    background: #f8fafc !important;
    color: #64748b !important;
}
.dashboard-page .table-hover tbody tr:hover {
    background: #f8fafc !important;
}
.dashboard-page .btn-outline-secondary {
    color: #475569 !important;
    border-color: #cbd5e1 !important;
    background: #ffffff !important;
}
.dashboard-page .bg-dark-blue .btn-outline-secondary {
    color: #ffffff !important;
    background: transparent !important;
    border-color: rgba(255,255,255,0.4) !important;
}
.dashboard-page .bg-dark-blue .btn-outline-secondary:hover {
    background: rgba(255,255,255,0.14) !important;
    color: #ffffff !important;
}
.dashboard-page .pagination .page-link {
    background: #ffffff !important;
    border-color: #cbd5e1 !important;
    color: #1a6591 !important;
}
.dashboard-page .pagination .page-item.active .page-link {
    background: #1a6591 !important;
    border-color: #1a6591 !important;
    color: #ffffff !important;
}
.dashboard-page code {
    color: #1d4ed8 !important;
    background: #eff6ff;
    padding: 0.1rem 0.35rem;
    border-radius: 0.35rem;
}
</style>

<div class="main-content dashboard-page">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
            <p class="text-muted mb-0">Bienvenido/a, <?= htmlspecialchars($user['name']) ?></p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if ($canRunChannelTest): ?>
            <form method="POST" action="/panel" class="m-0" onsubmit="return confirm('Se creara una denuncia de prueba con fecha aleatoria. ¿Continuar?');">
                <?= csrfInput() ?>
                <input type="hidden" name="action" value="run_channel_test">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-bezier2 me-1"></i>Probar canal
                </button>
            </form>
            <?php endif; ?>
            <span class="badge bg-light text-dark p-2"><i class="bi bi-calendar me-1"></i><?= date('d/m/Y') ?></span>
        </div>
    </div>

    <?php $flash = getFlashMessage(); if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
    <?php endif; ?>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <?php
        $kpis = [
            ['label' => 'Total Denuncias',   'val' => $stats['total'],          'color' => 'primary',  'bg' => '#dbeafe', 'icon' => 'bi-folder2-open'],
            ['label' => 'Pendientes',         'val' => $stats['recibidas'],      'color' => 'danger',   'bg' => '#fee2e2', 'icon' => 'bi-inbox'],
            ['label' => 'En Revisión',        'val' => $stats['en_investigacion'],'color' => 'warning','bg' => '#fef3c7', 'icon' => 'bi-search'],
            ['label' => 'Resueltas',          'val' => $stats['resueltas'],      'color' => 'success',  'bg' => '#e8f0f6', 'icon' => 'bi-check-circle'],
        ];
        foreach ($kpis as $kpi): ?>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1"><?= $kpi['label'] ?></p>
                            <h3 class="fw-bold text-<?= $kpi['color'] ?> mb-0"><?= $kpi['val'] ?></h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:50px;height:50px;background:<?= $kpi['bg'] ?>;">
                            <i class="bi <?= $kpi['icon'] ?> text-<?= $kpi['color'] ?> fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Denuncias con paginación -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-dark-blue border-0 py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-list-ul me-2"></i>Denuncias
                <span class="text-muted fw-normal small ms-2"><?= $totalComplaints ?> en total</span>
            </h6>
            <a href="/denuncias_admin" class="btn btn-sm btn-outline-secondary">Ver Todas</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Número</th><th>Tipo</th><th>Estado</th><th>Área</th><th>Modalidad</th><th>Hace</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay denuncias registradas</td></tr>
                        <?php else: ?>
                        <?php foreach ($recent as $row): ?>
                        <tr class="complaint-row-clickable" data-href="/detalle_denuncia?id=<?= (int)$row['id'] ?>">
                            <td><code class="fw-bold"><?= htmlspecialchars($row['complaint_number']) ?></code></td>
                            <td><?= getTypeBadge($row['complaint_type']) ?></td>
                            <td><?= getStatusBadge($row['status']) ?></td>
                            <td class="text-muted small"><?= htmlspecialchars(getInvestigationAreaLabel($row['assigned_area'] ?? null)) ?></td>
                            <td><span class="badge bg-<?= $row['is_anonymous'] ? 'secondary' : 'info' ?>"><?= $row['is_anonymous'] ? 'Anónima' : 'Identificada' ?></span></td>
                            <td class="text-muted small"><?= timeAgo($row['created_at']) ?></td>
                            <td>
                                <?php if ($hasComplaintAccess): ?>
                                <a href="/detalle_denuncia?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-top d-flex justify-content-between align-items-center py-2 px-3">
            <small class="text-muted">
                Mostrando <?= (($page - 1) * $perPage) + 1 ?>–<?= min($page * $perPage, $totalComplaints) ?> de <?= $totalComplaints ?>
            </small>
            <nav aria-label="Paginación">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">&lsaquo;</a>
                    </li>
                    <?php
                    $pStart = max(1, $page - 2);
                    $pEnd   = min($totalPages, $page + 2);
                    if ($pStart > 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif;
                    for ($i = $pStart; $i <= $pEnd; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor;
                    if ($pEnd < $totalPages): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">&rsaquo;</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>

    <div class="row g-4">
        <!-- Gráfico por tipo -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart me-2"></i>Por Tipo de Denuncia</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartType" style="max-height:280px;"></canvas>
                </div>
            </div>
        </div>
        <!-- Gráfico mensual -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2"></i>Últimos 6 Meses</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartMonthly" style="max-height:280px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('tr.complaint-row-clickable').forEach((row) => {
    row.addEventListener('click', (event) => {
        const target = event.target;
        if (target.closest('a, button, input, select, textarea, label, form')) {
            return;
        }
        const href = row.getAttribute('data-href');
        if (href) {
            window.location.href = href;
        }
    });
});

// Chart: Por tipo
const typeLabels = <?= json_encode(array_map(fn($r) => COMPLAINT_TYPES[$r['complaint_type']]['label'] ?? $r['complaint_type'], $byType)) ?>;
const typeData   = <?= json_encode(array_column($byType, 'total')) ?>;
const blues = ['#1a6591','#2380b0','#2d9ad0','#4db8e0','#7dcbea','#a3d9f0','#145275','#0f3d58','#2a7ab5','#5ba8d4','#93c5fd'];
new Chart(document.getElementById('chartType'), {
    type: 'doughnut',
    data: { labels: typeLabels, datasets: [{ data: typeData, backgroundColor: blues.slice(0, typeData.length), borderWidth: 2, borderColor: '#fff' }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 8, color: '#64748b' } } } }
});

// Chart: Mensual
const months = <?= json_encode(array_column($monthly, 'month')) ?>;
const totals = <?= json_encode(array_column($monthly, 'total')) ?>;
new Chart(document.getElementById('chartMonthly'), {
    type: 'bar',
    data: { labels: months, datasets: [{ label: 'Denuncias', data: totals, backgroundColor: '#1a6591', borderRadius: 6 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0, color: '#64748b' }, grid: { color: '#e2e8f0' } }, x: { ticks: { color: '#64748b' }, grid: { color: '#f1f5f9' } } } }
});
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
