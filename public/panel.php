<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Dashboard Admin
 */
$pageTitle = 'Panel de Administración';
require_once __DIR__ . '/../includes/bootstrap.php';
requireComplaintAccess();

$user = getCurrentUser();
$canModifyComplaints = canModifyComplaints(getCurrentUser());
$isInvestigador = hasRole([ROLE_INVESTIGADOR]);

// Filtro de conflicto de interés para investigadores
$cf = getConflictFilter($user, '');
$conflictWhere    = $cf['where_sql'];
$conflictWhereAnd = $cf['and_sql'];
$conflictParams   = $cf['params'];

// Estadísticas generales
$stmtStats = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'recibida' THEN 1 ELSE 0 END) as recibidas,
        SUM(CASE WHEN status = 'en_investigacion' THEN 1 ELSE 0 END) as en_investigacion,
        SUM(CASE WHEN status = 'resuelta' THEN 1 ELSE 0 END) as resueltas,
        SUM(CASE WHEN status = 'desestimada' THEN 1 ELSE 0 END) as desestimadas,
        SUM(CASE WHEN status = 'archivada' THEN 1 ELSE 0 END) as archivadas,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as semana,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as mes
    FROM complaints $conflictWhere
");
$stmtStats->execute($conflictParams);
$stats = $stmtStats->fetch();

// Por tipo
$stmtType = $pdo->prepare("
    SELECT complaint_type, COUNT(*) as total 
    FROM complaints $conflictWhere
    GROUP BY complaint_type 
    ORDER BY total DESC
");
$stmtType->execute($conflictParams);
$byType = $stmtType->fetchAll();

// Paginación de denuncias
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));

$stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM complaints $conflictWhere");
$stmtTotal->execute($conflictParams);
$totalComplaints = (int)$stmtTotal->fetchColumn();
$totalPages = max(1, (int)ceil($totalComplaints / $perPage));
$page   = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmtRecent = $pdo->prepare("
    SELECT id, complaint_number, complaint_type, status, is_anonymous, created_at
    FROM complaints $conflictWhere
    ORDER BY created_at DESC
    LIMIT $perPage OFFSET $offset
");
$stmtRecent->execute($conflictParams);
$recent = $stmtRecent->fetchAll();

// Por mes (últimos 6 meses)
$stmtMonthly = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total
    FROM complaints 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) $conflictWhereAnd
    GROUP BY month
    ORDER BY month ASC
");
$stmtMonthly->execute($conflictParams);
$monthly = $stmtMonthly->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
            <p class="text-muted mb-0">Bienvenido, <?= htmlspecialchars($user['name']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <span class="badge bg-light text-dark p-2"><i class="bi bi-calendar me-1"></i><?= date('d/m/Y') ?></span>
        </div>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Total Denuncias</p>
                            <h3 class="fw-bold mb-0"><?= $stats['total'] ?></h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: #dbeafe;">
                            <i class="bi bi-folder2-open text-primary fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Nuevas (Recibidas)</p>
                            <h3 class="fw-bold text-danger mb-0"><?= $stats['recibidas'] ?></h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: #fee2e2;">
                            <i class="bi bi-inbox text-danger fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">En Investigación</p>
                            <h3 class="fw-bold text-warning mb-0"><?= $stats['en_investigacion'] ?></h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: #fef3c7;">
                            <i class="bi bi-search text-warning fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <p class="text-muted small mb-1">Resueltas</p>
                            <h3 class="fw-bold text-success mb-0"><?= $stats['resueltas'] ?></h3>
                        </div>
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background: #dcfce7;">
                            <i class="bi bi-check-circle text-success fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Denuncias con paginación -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0">
                <i class="bi bi-list-ul me-2"></i>Denuncias
                <span class="text-muted fw-normal small ms-2"><?= $totalComplaints ?> en total</span>
            </h6>
            <a href="/denuncias_admin" class="btn btn-sm btn-outline-primary">Ver Todas</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Número</th>
                            <th>Tipo</th>
                            <th>Estado</th>
                            <th>Modalidad</th>
                            <th>Fecha</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay denuncias registradas</td></tr>
                        <?php else: ?>
                        <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($row['complaint_number']) ?></code></td>
                            <td><?= getTypeBadge($row['complaint_type']) ?></td>
                            <td><?= getStatusBadge($row['status']) ?></td>
                            <td>
                                <span class="badge bg-<?= $row['is_anonymous'] ? 'secondary' : 'info' ?>">
                                    <?= $row['is_anonymous'] ? 'Anónima' : 'Identificada' ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?= timeAgo($row['created_at']) ?></td>
                            <td>
                                <?php if ($isInvestigador): ?>
                                <a href="/detalle_denuncia?id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-eye"></i>
                                </a>
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
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-pie-chart me-2"></i>Por Tipo</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartType" style="max-height: 280px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Gráfico mensual -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart me-2"></i>Últimos 6 Meses</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartMonthly" style="max-height: 280px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Gráfico por tipo
const typeLabels = <?= json_encode(array_map(fn($t) => COMPLAINT_TYPES[$t['complaint_type']]['label'] ?? $t['complaint_type'], $byType)) ?>;
const typeData = <?= json_encode(array_map(fn($t) => (int)$t['total'], $byType)) ?>;
const typeColors = ['#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'];

new Chart(document.getElementById('chartType'), {
    type: 'doughnut',
    data: {
        labels: typeLabels,
        datasets: [{
            data: typeData,
            backgroundColor: typeColors.slice(0, typeData.length),
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom', labels: { padding: 15 } } }
    }
});

// Gráfico mensual
const monthLabels = <?= json_encode(array_map(fn($m) => $m['month'], $monthly)) ?>;
const monthData = <?= json_encode(array_map(fn($m) => (int)$m['total'], $monthly)) ?>;

new Chart(document.getElementById('chartMonthly'), {
    type: 'bar',
    data: {
        labels: monthLabels,
        datasets: [{
            label: 'Denuncias',
            data: monthData,
            backgroundColor: '#0369a1',
            borderRadius: 6,
            borderSkipped: false
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
