<?php
/**
 * Portal Ciudadano de Denuncias - Reportes
 */
$pageTitle = 'Reportes';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN]);

// Exportación CSV
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = $pdo->query("
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
        FROM complaints
        ORDER BY created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

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

$byType = $pdo->query("SELECT complaint_type, COUNT(*) as total FROM complaints GROUP BY complaint_type ORDER BY total DESC")->fetchAll();

$byMonth = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total
    FROM complaints 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY month ORDER BY month
")->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-graph-up me-2"></i>Reportes</h4>
            <p class="text-muted mb-0">Estadísticas del Portal Ciudadano de Denuncias</p>
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

<script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.4/dist/jspdf.plugin.autotable.min.js"></script>
<script>
function generarPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const pageWidth = doc.internal.pageSize.getWidth();
    let y = 20;

    // Título
    doc.setFontSize(18);
    doc.setTextColor(26, 101, 145);
    doc.text('Reporte de Métricas - Denuncias Ciudadanas', pageWidth / 2, y, { align: 'center' });
    y += 10;
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text('Generado: ' + new Date().toLocaleString('es-CL'), pageWidth / 2, y, { align: 'center' });
    y += 15;

    // KPIs
    doc.setFontSize(13);
    doc.setTextColor(30, 41, 59);
    doc.text('Resumen General', 14, y);
    y += 8;

    doc.autoTable({
        startY: y,
        head: [['Indicador', 'Valor']],
        body: [
            ['Total Denuncias', '<?= (int)$overview['total'] ?>'],
            ['Resueltas', '<?= (int)$overview['resueltas'] ?>'],
            ['Anónimas', '<?= (int)$overview['anonimas'] ?>'],
            ['Identificadas', '<?= (int)$overview['identificadas'] ?>'],
            ['Días prom. resolución', '<?= $overview['avg_resolution_days'] ? round($overview['avg_resolution_days'], 1) : '-' ?>']
        ],
        theme: 'striped',
        headStyles: { fillColor: [26, 101, 145], textColor: 255 },
        styles: { fontSize: 10 },
        margin: { left: 14, right: 14 }
    });
    y = doc.lastAutoTable.finalY + 15;

    // Por Tipo
    doc.setFontSize(13);
    doc.setTextColor(30, 41, 59);
    doc.text('Denuncias por Tipo', 14, y);
    y += 8;

    const typesData = <?= json_encode(array_map(fn($t) => [
        COMPLAINT_TYPES[$t['complaint_type']]['label'] ?? $t['complaint_type'],
        (int)$t['total']
    ], $byType)) ?>;

    doc.autoTable({
        startY: y,
        head: [['Tipo de Denuncia', 'Cantidad']],
        body: typesData.map(t => [t[0], String(t[1])]),
        theme: 'striped',
        headStyles: { fillColor: [26, 101, 145], textColor: 255 },
        styles: { fontSize: 10 },
        margin: { left: 14, right: 14 }
    });
    y = doc.lastAutoTable.finalY + 15;

    // Por Estado
    doc.setFontSize(13);
    doc.setTextColor(30, 41, 59);
    doc.text('Denuncias por Estado', 14, y);
    y += 8;

    const statusData = <?= json_encode(array_map(fn($key, $st) => [
        $st['label'],
        (int)($overview[$key] ?? 0)
    ], array_keys(COMPLAINT_STATUSES), array_values(COMPLAINT_STATUSES))) ?>;

    doc.autoTable({
        startY: y,
        head: [['Estado', 'Cantidad']],
        body: statusData.map(s => [s[0], String(s[1])]),
        theme: 'striped',
        headStyles: { fillColor: [26, 101, 145], textColor: 255 },
        styles: { fontSize: 10 },
        margin: { left: 14, right: 14 }
    });

    // Pie de página
    const totalPages = doc.internal.getNumberOfPages();
    for (let i = 1; i <= totalPages; i++) {
        doc.setPage(i);
        doc.setFontSize(8);
        doc.setTextColor(150);
        doc.text('Portal Ciudadano de Denuncias - Página ' + i + ' de ' + totalPages,
            pageWidth / 2, doc.internal.pageSize.getHeight() - 10, { align: 'center' });
    }

    doc.save('reporte_denuncias_ciudadanas_' + new Date().toISOString().slice(0, 10) + '.pdf');
}
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
