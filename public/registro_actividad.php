<?php
/**
 * Portal de Denuncias EPCO - Registro de Actividad
 */
$pageTitle = 'Registro de Actividad';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN]);

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$totalRows = $pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$logs = $pdo->query("
    SELECT al.*, u.name as user_name 
    FROM activity_logs al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT $perPage OFFSET $offset
")->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1"><i class="bi bi-journal-text me-2"></i>Registro de Actividad</h4>
        <p class="text-muted mb-0"><?= $totalRows ?> evento(s) registrado(s)</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Acción</th>
                            <th>Entidad</th>
                            <th>IP</th>
                            <th>Detalles</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="small text-muted"><?= formatDateTime($log['created_at']) ?></td>
                            <td class="small fw-semibold"><?= htmlspecialchars($log['user_name'] ?? 'Sistema') ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($log['action']) ?></span></td>
                            <td class="small text-muted"><?= $log['entity_type'] ? htmlspecialchars($log['entity_type']) . '#' . $log['entity_id'] : '-' ?></td>
                            <td class="small text-muted"><?= htmlspecialchars($log['ip_address'] ?? '-') ?></td>
                            <td class="small text-muted" title="<?= htmlspecialchars($log['details'] ?? '') ?>"><?= htmlspecialchars(substr($log['details'] ?? '-', 0, 60)) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-white border-0 py-3">
            <nav><ul class="pagination pagination-sm justify-content-center mb-0">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
                <?php endfor; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
