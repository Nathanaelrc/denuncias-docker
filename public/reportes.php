<?php
/**
 * Portal de Denuncias EPCO - Reportes
 */
$pageTitle = 'Reportes';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN]);

// Estadísticas generales
$overview = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'recibida' THEN 1 ELSE 0 END) as recibidas,
        SUM(CASE WHEN status = 'en_investigacion' THEN 1 ELSE 0 END) as en_investigacion,
        SUM(CASE WHEN status = 'resuelta' THEN 1 ELSE 0 END) as resueltas,
        SUM(CASE WHEN status = 'desestimada' THEN 1 ELSE 0 END) as desestimadas,
        SUM(CASE WHEN status = 'archivada' THEN 1 ELSE 0 END) as archivadas,
        SUM(CASE WHEN is_anonymous = 1 THEN 1 ELSE 0 END) as anonimas,
        SUM(CASE WHEN is_anonymous = 0 THEN 1 ELSE 0 END) as identificadas,
        AVG(CASE WHEN resolved_at IS NOT NULL THEN DATEDIFF(resolved_at, created_at) END) as avg_resolution_days
    FROM complaints
")->fetch();

// Por tipo
$byType = $pdo->query("SELECT complaint_type, COUNT(*) as total FROM complaints GROUP BY complaint_type ORDER BY total DESC")->fetchAll();

// Por mes
$byMonth = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total
    FROM complaints 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month
")->fetchAll();

// Por prioridad
$byPriority = $pdo->query("SELECT priority, COUNT(*) as total FROM complaints GROUP BY priority")->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1"><i class="bi bi-graph-up me-2"></i>Reportes</h4>
        <p class="text-muted mb-0">Estadísticas del Canal de Denuncias</p>
    </div>

    <!-- KPIs -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h3 class="fw-bold mb-0"><?= $overview['total'] ?></h3>
                <small class="text-muted">Total Denuncias</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h3 class="fw-bold text-success mb-0"><?= $overview['resueltas'] ?></h3>
                <small class="text-muted">Resueltas</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h3 class="fw-bold mb-0"><?= $overview['anonimas'] ?></h3>
                <small class="text-muted">Anónimas</small>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm text-center py-3">
                <h3 class="fw-bold mb-0"><?= $overview['avg_resolution_days'] ? round($overview['avg_resolution_days'], 1) : '-' ?></h3>
                <small class="text-muted">Días prom. resolución</small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Por tipo -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0">Denuncias por Tipo</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartType" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
        <!-- Tendencia mensual -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0">Tendencia Mensual (12 meses)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartMonthly" style="max-height: 300px;"></canvas>
                </div>
            </div>
        </div>
        <!-- Estado -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0">Por Estado</h6>
                </div>
                <div class="card-body">
                    <?php foreach (COMPLAINT_STATUSES as $key => $st):
                        $val = $overview[$key] ?? $overview[str_replace(' ', '_', $key)] ?? 0;
                        $pct = $overview['total'] > 0 ? round(($val / $overview['total']) * 100) : 0;
                    ?>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small"><?= getStatusBadge($key) ?></span>
                        <span class="small fw-bold"><?= $val ?> (<?= $pct ?>%)</span>
                    </div>
                    <div class="progress mb-3" style="height: 6px;">
                        <div class="progress-bar bg-<?= $st['color'] ?>" style="width: <?= $pct ?>%"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <!-- Anónimas vs Identificadas -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 py-3">
                    <h6 class="fw-bold mb-0">Modalidad</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartAnon" style="max-height: 250px;"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const typeColors = ['#ef4444', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#64748b'];

new Chart(document.getElementById('chartType'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($t) => COMPLAINT_TYPES[$t['complaint_type']]['label'] ?? $t['complaint_type'], $byType)) ?>,
        datasets: [{ data: <?= json_encode(array_map(fn($t) => (int)$t['total'], $byType)) ?>, backgroundColor: typeColors, borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});

new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($m) => $m['month'], $byMonth)) ?>,
        datasets: [{ label: 'Denuncias', data: <?= json_encode(array_map(fn($m) => (int)$m['total'], $byMonth)) ?>, borderColor: '#0369a1', backgroundColor: 'rgba(3,105,161,0.1)', fill: true, tension: 0.4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('chartAnon'), {
    type: 'pie',
    data: {
        labels: ['Anónimas', 'Identificadas'],
        datasets: [{ data: [<?= $overview['anonimas'] ?>, <?= $overview['identificadas'] ?>], backgroundColor: ['#64748b', '#0ea5e9'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
