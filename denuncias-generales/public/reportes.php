<?php
/**
 * Portal Ciudadano de Denuncias - Reportes
 */
$pageTitle = 'Reportes';
require_once __DIR__ . '/../includes/bootstrap.php';
requireComplaintAccess();

$user = getCurrentUser();
$cf = getConflictFilter($user, '');
$conflictWhere = $cf['where_sql'];
$conflictWhereAnd = $cf['and_sql'];
$conflictParams = $cf['params'];

// Exportación CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $stmt = $pdo->prepare("
        SELECT
            complaint_number   AS 'Número',
            complaint_type     AS 'Tipo',
            status             AS 'Estado',
            priority           AS 'Prioridad',
            CASE is_anonymous WHEN 1 THEN 'Sí' ELSE 'No' END AS 'Anónima',
            DATE_FORMAT(incident_date,  '%d/%m/%Y')           AS 'Fecha Incidente',
            DATE_FORMAT(created_at,     '%d/%m/%Y %H:%i')     AS 'Recibida',
            DATE_FORMAT(assigned_at,    '%d/%m/%Y %H:%i')     AS 'Asignada',
            DATE_FORMAT(resolved_at,    '%d/%m/%Y %H:%i')     AS 'Resuelta'
        FROM complaints $conflictWhere
        ORDER BY created_at DESC
    ");
    $stmt->execute($conflictParams);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $filename = 'denuncias_ciudadanas_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM para Excel
    if (!empty($rows)) {
        fputcsv($out, array_keys($rows[0]), ';');
        foreach ($rows as $row) { fputcsv($out, $row, ';'); }
    }
    fclose($out);
    logActivity($_SESSION['user_id'], 'exportar_csv', 'complaint', null, 'Exportación CSV denuncias ciudadanas');
    exit;
}

// Estadísticas
$overviewStmt = $pdo->prepare("
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
    FROM complaints $conflictWhere
");
$overviewStmt->execute($conflictParams);
$overview = $overviewStmt->fetch();

$byTypeStmt = $pdo->prepare("SELECT complaint_type, COUNT(*) as total FROM complaints $conflictWhere GROUP BY complaint_type ORDER BY total DESC");
$byTypeStmt->execute($conflictParams);
$byType = $byTypeStmt->fetchAll();

$byMonthStmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total
    FROM complaints 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH) $conflictWhereAnd
    GROUP BY month ORDER BY month
");
$byMonthStmt->execute($conflictParams);
$byMonth = $byMonthStmt->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-graph-up me-2"></i>Reportes</h4>
            <p class="text-muted mb-0">Estadísticas del Canal de Denuncias</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="generarPDF()" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-file-earmark-pdf me-1"></i>Exportar PDF
            </button>
            <a href="/reportes?export=csv" class="btn btn-outline-success btn-sm">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar CSV
            </a>
        </div>
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
                <h3 class="fw-bold text-info mb-0"><?= $overview['resueltas'] ?></h3>
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
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0">Denuncias por Tipo / Legislación</h6>
                </div>
                <div class="card-body d-flex align-items-center justify-content-center">
                    <canvas id="chartType" style="max-height: 320px;"></canvas>
                </div>
            </div>
        </div>
        <!-- Tendencia mensual -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0">Tendencia Mensual (12 meses)</h6>
                </div>
                <div class="card-body">
                    <canvas id="chartMonthly" style="max-height: 320px;"></canvas>
                </div>
            </div>
        </div>
        <!-- Por Estado -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-dark-blue border-0 py-3">
                    <h6 class="fw-bold mb-0">Por Estado</h6>
                </div>
                <div class="card-body">
                    <?php foreach (COMPLAINT_STATUSES as $key => $st):
                        $val = $overview[$key] ?? 0;
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
                <div class="card-header bg-dark-blue border-0 py-3">
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
// Paleta verde para tipos de denuncia ciudadana
const typeColors = ['#1a6591','#2380b0','#2d9ad0','#2a7ab5','#2d9ad0','#4db8e0','#60a5fa','#93c5fd','#f59e0b','#ef4444','#6b7280'];

new Chart(document.getElementById('chartType'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_map(fn($t) => COMPLAINT_TYPES[$t['complaint_type']]['label'] ?? $t['complaint_type'], $byType)) ?>,
        datasets: [{ data: <?= json_encode(array_map(fn($t) => (int)$t['total'], $byType)) ?>, backgroundColor: typeColors.slice(0, <?= count($byType) ?>), borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

new Chart(document.getElementById('chartMonthly'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_map(fn($m) => $m['month'], $byMonth)) ?>,
        datasets: [{ label: 'Denuncias', data: <?= json_encode(array_map(fn($m) => (int)$m['total'], $byMonth)) ?>, borderColor: '#1a6591', backgroundColor: 'rgba(26,101,145,0.1)', fill: true, tension: 0.4 }]
    },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});

new Chart(document.getElementById('chartAnon'), {
    type: 'pie',
    data: {
        labels: ['Anónimas', 'Identificadas'],
        datasets: [{ data: [<?= (int)$overview['anonimas'] ?>, <?= (int)$overview['identificadas'] ?>], backgroundColor: ['#6b7280', '#1a6591'], borderWidth: 0 }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
});
</script>

<style media="print">
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
    .print-only { display: block !important; }
    .main-content { margin: 0 !important; padding: 1cm !important; }
    .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #dee2e6 !important; }
    @page { margin: 1.5cm; size: A4; }
</style>

<div class="print-only" style="display:none; margin-bottom: 1.5rem;">
    <div style="text-align:center; border-bottom: 2px solid #1a6591; padding-bottom: 0.75rem; margin-bottom: 1rem;">
        <strong style="font-size:1.15rem; color:#1a6591;">Reporte de Métricas — Canal de Denuncias</strong><br>
        <small style="color:#6b7280;">Empresa Portuaria Coquimbo &mdash; Generado: <?= date('d/m/Y H:i') ?></small>
    </div>
    <table style="width:100%; border-collapse:collapse; font-size:0.85rem; margin-bottom:1.5rem;">
        <thead><tr style="background:#1a6591; color:#fff;">
            <th style="padding:6px 10px; text-align:left;">Indicador</th>
            <th style="padding:6px 10px; text-align:right;">Valor</th>
        </tr></thead>
        <tbody>
            <tr style="background:#f8fafc;"><td style="padding:5px 10px;">Total Denuncias</td><td style="padding:5px 10px; text-align:right;"><?= (int)$overview['total'] ?></td></tr>
            <tr><td style="padding:5px 10px;">Resueltas</td><td style="padding:5px 10px; text-align:right;"><?= (int)$overview['resueltas'] ?></td></tr>
            <tr style="background:#f8fafc;"><td style="padding:5px 10px;">Anónimas</td><td style="padding:5px 10px; text-align:right;"><?= (int)$overview['anonimas'] ?></td></tr>
            <tr><td style="padding:5px 10px;">Identificadas</td><td style="padding:5px 10px; text-align:right;"><?= (int)$overview['identificadas'] ?></td></tr>
            <tr style="background:#f8fafc;"><td style="padding:5px 10px;">Días prom. resolución</td><td style="padding:5px 10px; text-align:right;"><?= $overview['avg_resolution_days'] ? round($overview['avg_resolution_days'], 1) : '-' ?></td></tr>
        </tbody>
    </table>
    <?php if (!empty($byType)): ?>
    <p style="font-weight:700; color:#1e293b; margin-bottom:0.4rem;">Denuncias por Tipo</p>
    <table style="width:100%; border-collapse:collapse; font-size:0.85rem; margin-bottom:1.5rem;">
        <thead><tr style="background:#1a6591; color:#fff;">
            <th style="padding:6px 10px; text-align:left;">Tipo</th>
            <th style="padding:6px 10px; text-align:right;">Cantidad</th>
        </tr></thead>
        <tbody>
        <?php foreach ($byType as $i => $t): ?>
            <tr<?= $i % 2 === 0 ? ' style="background:#f8fafc;"' : '' ?>>
                <td style="padding:5px 10px;"><?= htmlspecialchars(COMPLAINT_TYPES[$t['complaint_type']]['label'] ?? $t['complaint_type']) ?></td>
                <td style="padding:5px 10px; text-align:right;"><?= (int)$t['total'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    <p style="font-weight:700; color:#1e293b; margin-bottom:0.4rem;">Denuncias por Estado</p>
    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
        <thead><tr style="background:#1a6591; color:#fff;">
            <th style="padding:6px 10px; text-align:left;">Estado</th>
            <th style="padding:6px 10px; text-align:right;">Cantidad</th>
        </tr></thead>
        <tbody>
        <?php $si = 0; foreach (COMPLAINT_STATUSES as $skey => $st): $val = $overview[$skey] ?? 0; ?>
            <tr<?= $si % 2 === 0 ? ' style="background:#f8fafc;"' : '' ?>>
                <td style="padding:5px 10px;"><?= htmlspecialchars($st['label']) ?></td>
                <td style="padding:5px 10px; text-align:right;"><?= (int)$val ?></td>
            </tr>
        <?php $si++; endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:0.72rem; color:#9ca3af; text-align:center; margin-top:2rem; border-top:1px solid #e5e7eb; padding-top:0.5rem;">
        Canal de Denuncias — Empresa Portuaria Coquimbo
    </p>
</div>

<script>
function generarPDF() {
    window.print();
}
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
