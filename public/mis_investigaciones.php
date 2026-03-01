<?php
/**
 * Portal de Denuncias EPCO - Mis Investigaciones
 */
$pageTitle = 'Mis Investigaciones';
require_once __DIR__ . '/../includes/bootstrap.php';
requireRole([ROLE_ADMIN, ROLE_INVESTIGADOR]);

$user = getCurrentUser();

$stmt = $pdo->prepare("
    SELECT c.*, u.name as investigator_name 
    FROM complaints c 
    LEFT JOIN users u ON c.investigator_id = u.id 
    WHERE c.investigator_id = ? 
    ORDER BY 
        CASE c.status 
            WHEN 'recibida' THEN 1 
            WHEN 'en_investigacion' THEN 2 
            ELSE 3 
        END,
        c.created_at DESC
");
$stmt->execute([$user['id']]);
$myComplaints = $stmt->fetchAll();

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<div class="main-content">
    <div class="mb-4">
        <h4 class="fw-bold text-dark mb-1"><i class="bi bi-search me-2"></i>Mis Investigaciones</h4>
        <p class="text-muted mb-0"><?= count($myComplaints) ?> caso(s) asignado(s)</p>
    </div>

    <?php if (empty($myComplaints)): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
            <h5 class="text-muted mt-3">No tienes casos asignados</h5>
            <p class="text-muted">Cuando se te asigne una investigación, aparecerá aquí.</p>
        </div>
    </div>
    <?php else: ?>

    <div class="row g-3">
        <?php foreach ($myComplaints as $c): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <code class="fw-bold"><?= htmlspecialchars($c['complaint_number']) ?></code>
                        <?= getStatusBadge($c['status']) ?>
                    </div>
                    <div class="mb-2"><?= getTypeBadge($c['complaint_type']) ?></div>
                    <div class="d-flex gap-2 mb-3">
                        <span class="badge bg-<?= $c['priority'] === 'urgente' ? 'danger' : ($c['priority'] === 'alta' ? 'warning text-dark' : 'secondary') ?>">
                            <?= ucfirst($c['priority']) ?>
                        </span>
                        <span class="badge bg-<?= $c['is_anonymous'] ? 'secondary' : 'info' ?>">
                            <?= $c['is_anonymous'] ? 'Anónima' : 'Identificada' ?>
                        </span>
                    </div>
                    <small class="text-muted d-block">Recibida: <?= formatDateTime($c['created_at']) ?></small>
                    <a href="/detalle_denuncia?id=<?= $c['id'] ?>" class="btn btn-outline-primary btn-sm w-100 mt-3">
                        <i class="bi bi-eye me-1"></i>Ver Detalle
                    </a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
