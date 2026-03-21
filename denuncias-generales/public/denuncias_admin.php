<?php
/**
 * Portal Denuncias Ciudadanas - Gestión de Denuncias
 */
$pageTitle = 'Gestión de Denuncias';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN, ROLE_INVESTIGADOR, ROLE_VIEWER]);

$filterStatus = sanitize($_GET['status'] ?? '');
$filterType   = sanitize($_GET['type'] ?? '');
$search       = sanitize($_GET['q'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 15;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];
if ($filterStatus && array_key_exists($filterStatus, COMPLAINT_STATUSES)) { $where[] = "c.status = ?"; $params[] = $filterStatus; }
if ($filterType   && array_key_exists($filterType, COMPLAINT_TYPES))      { $where[] = "c.complaint_type = ?"; $params[] = $filterType; }
if ($search)                                                               { $where[] = "c.complaint_number LIKE ?"; $params[] = "%$search%"; }

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM complaints c $whereClause");
$countStmt->execute($params);
$totalRows  = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

$stmt = $pdo->prepare("SELECT c.*, u.name as investigator_name FROM complaints c LEFT JOIN users u ON c.investigator_id = u.id $whereClause ORDER BY c.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$complaints = $stmt->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1"><i class="bi bi-folder2-open me-2"></i>Denuncias</h4>
            <p class="text-muted mb-0"><?= $totalRows ?> denuncia(s) encontrada(s)</p>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="/denuncias_admin" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Buscar por código</label>
                    <input type="text" name="q" class="form-control" placeholder="DC-..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Estado</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (COMPLAINT_STATUSES as $k => $s): ?>
                        <option value="<?= $k ?>" <?= $filterStatus === $k ? 'selected' : '' ?>><?= $s['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold">Tipo</label>
                    <select name="type" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach (COMPLAINT_TYPES as $k => $t): ?>
                        <option value="<?= $k ?>" <?= $filterType === $k ? 'selected' : '' ?>><?= $t['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-search me-1"></i>Filtrar</button>
                    <a href="/denuncias_admin" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabla -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr><th>Número</th><th>Tipo</th><th>Estado</th><th>Prioridad</th><th>Modalidad</th><th>Delegado</th><th>Fecha</th><th></th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($complaints)): ?>
                        <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay denuncias</td></tr>
                        <?php else: ?>
                        <?php foreach ($complaints as $c): ?>
                        <tr>
                            <td><code class="fw-bold"><?= htmlspecialchars($c['complaint_number']) ?></code></td>
                            <td><?= getTypeBadge($c['complaint_type']) ?></td>
                            <td><?= getStatusBadge($c['status']) ?></td>
                            <td><span class="badge bg-<?= $c['priority'] === 'urgente' ? 'danger' : ($c['priority'] === 'alta' ? 'warning text-dark' : 'secondary') ?>"><?= ucfirst($c['priority']) ?></span></td>
                            <td><span class="badge bg-<?= $c['is_anonymous'] ? 'secondary' : 'info' ?>"><?= $c['is_anonymous'] ? 'Anónima' : 'Identificada' ?></span></td>
                            <td class="text-muted small"><?= htmlspecialchars($c['investigator_name'] ?? 'Sin asignar') ?></td>
                            <td class="text-muted small"><?= formatDateTime($c['created_at']) ?></td>
                            <td><?php if (canDecrypt()): ?>
                                <a href="/detalle_denuncia?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                            <?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer bg-dark-blue border-0 d-flex justify-content-between align-items-center">
            <span class="text-muted small">Página <?= $page ?> de <?= $totalPages ?></span>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">«</a></li>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">»</a></li>
                <?php endif; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
