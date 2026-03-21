<?php
/**
 * Portal Denuncias Ciudadanas - Dashboard
 */
$pageTitle = 'Panel';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN, ROLE_INVESTIGADOR, ROLE_VIEWER]);

$user           = getCurrentUser();
$isAdmin        = hasRole([ROLE_ADMIN]);
$isInvestigador = hasRole([ROLE_INVESTIGADOR]);

$stats = $pdo->query("
    SELECT COUNT(*) as total,
        SUM(CASE WHEN status='recibida' THEN 1 ELSE 0 END) as recibidas,
        SUM(CASE WHEN status='en_investigacion' THEN 1 ELSE 0 END) as en_investigacion,
        SUM(CASE WHEN status='resuelta' THEN 1 ELSE 0 END) as resueltas,
        SUM(CASE WHEN status='desestimada' THEN 1 ELSE 0 END) as desestimadas,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as semana,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as mes
    FROM complaints
")->fetch();

$byType = $pdo->query("SELECT complaint_type, COUNT(*) as total FROM complaints GROUP BY complaint_type ORDER BY total DESC")->fetchAll();

$recent = $pdo->query("SELECT id, complaint_number, complaint_type, status, is_anonymous, created_at FROM complaints ORDER BY created_at DESC LIMIT 10")->fetchAll();

$monthly = $pdo->query("
    SELECT DATE_FORMAT(created_at,'%Y-%m') as month, COUNT(*) as total
    FROM complaints WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month ORDER BY month ASC
")->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h4>
            <p class="text-muted mb-0">Bienvenido/a, <?= htmlspecialchars($user['name']) ?></p>
        </div>
        <span class="badge bg-light text-dark p-2"><i class="bi bi-calendar me-1"></i><?= date('d/m/Y') ?></span>
    </div>

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

    <!-- Últimas denuncias -->
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-header bg-dark-blue border-0 py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2"></i>Últimas Denuncias</h6>
            <a href="/denuncias_admin" class="btn btn-sm btn-outline-secondary">Ver Todas</a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Número</th><th>Tipo</th><th>Estado</th><th>Modalidad</th><th>Hace</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay denuncias registradas</td></tr>
                        <?php else: ?>
                        <?php foreach ($recent as $row): ?>
                        <tr>
                            <td><code class="fw-bold"><?= htmlspecialchars($row['complaint_number']) ?></code></td>
                            <td><?= getTypeBadge($row['complaint_type']) ?></td>
                            <td><?= getStatusBadge($row['status']) ?></td>
                            <td><span class="badge bg-<?= $row['is_anonymous'] ? 'secondary' : 'info' ?>"><?= $row['is_anonymous'] ? 'Anónima' : 'Identificada' ?></span></td>
                            <td class="text-muted small"><?= timeAgo($row['created_at']) ?></td>
                            <td>
                                <?php if ($isAdmin || $isInvestigador): ?>
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
    </div>
</div>

<script>
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
