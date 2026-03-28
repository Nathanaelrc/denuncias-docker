<?php
/**
 * Portal de Denuncias Empresa Portuaria Coquimbo - Gestión de Notificaciones
 * Admin: gestionar grupos y suscripciones de usuarios
 * Todos: ver sus notificaciones
 */
$pageTitle = 'Notificaciones';
require_once __DIR__ . '/../includes/bootstrap.php';
requireAuth();

$userId = $_SESSION['user_id'];
$userRole = getUserRole();
$isAdmin = $userRole === ROLE_ADMIN;

// =============================================
// AJAX: Marcar notificación como leída
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        echo json_encode(['ok' => false, 'error' => 'Token inválido']);
        exit;
    }

    $ajaxAction = $_POST['ajax_action'];

    if ($ajaxAction === 'mark_read') {
        $nid = (int)($_POST['notification_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([$nid, $userId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($ajaxAction === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        echo json_encode(['ok' => true, 'count' => $stmt->rowCount()]);
        exit;
    }

    if ($ajaxAction === 'update_email' && $isAdmin) {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $newEmail = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);

        if (!$targetUserId) {
            echo json_encode(['ok' => false, 'error' => 'Usuario inválido']);
            exit;
        }
        if (!$newEmail) {
            echo json_encode(['ok' => false, 'error' => 'Email inválido']);
            exit;
        }

        // Verificar que no esté en uso por otro usuario
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check->execute([$newEmail, $targetUserId]);
        if ($check->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Este email ya está en uso por otro usuario']);
            exit;
        }

        $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$newEmail, $targetUserId]);
        logActivity($userId, 'actualizar_email', 'user', $targetUserId, "Email actualizado a: $newEmail");
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($ajaxAction === 'test_email' && $isAdmin) {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT email, name FROM users WHERE id = ?");
        $stmt->execute([$targetUserId]);
        $targetUser = $stmt->fetch();

        if (!$targetUser || empty($targetUser['email'])) {
            echo json_encode(['ok' => false, 'error' => 'Usuario sin email configurado']);
            exit;
        }

        try {
            $testContent = '
                <p style="color: #374151; font-size: 14px; line-height: 1.7;">Hola <strong>' . htmlspecialchars($targetUser['name']) . '</strong>,</p>
                <p style="color: #374151; font-size: 14px; line-height: 1.7;">Este es un correo de prueba del Canal de Denuncias Empresa Portuaria Coquimbo para verificar que las notificaciones por email están funcionando correctamente.</p>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px; margin: 15px 0; text-align: center;">
                    <p style="color: #166534; font-weight: 700; font-size: 16px; margin: 0;">Correo configurado correctamente</p>
                </div>
                <p style="color: #9ca3af; font-size: 12px; margin-top: 20px;">Enviado desde el panel de notificaciones el ' . date('d/m/Y H:i') . '</p>';
            $result = sendEmail($targetUser['email'], 'Prueba de Notificación - Canal de Denuncias', emailTemplate('Prueba de Correo', $testContent));
            echo json_encode(['ok' => $result, 'error' => $result ? null : 'Error al enviar el correo']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    if ($ajaxAction === 'save_subscriptions' && $isAdmin) {
        $targetUserId = (int)($_POST['target_user_id'] ?? 0);
        $groupIds = json_decode($_POST['group_ids'] ?? '[]', true);

        if (!$targetUserId || !is_array($groupIds)) {
            echo json_encode(['ok' => false, 'error' => 'Datos inválidos']);
            exit;
        }

        // Verificar que el usuario existe
        $userExists = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $userExists->execute([$targetUserId]);
        if (!$userExists->fetch()) {
            echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado']);
            exit;
        }

        // Validar que los group_ids sean enteros válidos
        $validGroupIds = $pdo->query("SELECT id FROM notification_groups WHERE is_active = 1")->fetchAll(PDO::FETCH_COLUMN);
        $groupIds = array_filter($groupIds, function($gid) use ($validGroupIds) {
            return in_array((int)$gid, $validGroupIds);
        });

        $pdo->beginTransaction();
        try {
            $pdo->prepare("DELETE FROM notification_subscriptions WHERE user_id = ?")->execute([$targetUserId]);
            $ins = $pdo->prepare("INSERT INTO notification_subscriptions (user_id, group_id) VALUES (?, ?)");
            foreach ($groupIds as $gid) {
                $ins->execute([$targetUserId, (int)$gid]);
            }
            $pdo->commit();
            logActivity($userId, 'notificaciones_actualizadas', 'user', $targetUserId, 'Grupos: ' . implode(',', $groupIds));
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['ok' => false, 'error' => 'Error al guardar']);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Acción no válida']);
    exit;
}

// =============================================
// Datos para la página
// =============================================

// Mis notificaciones
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$unreadCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unreadCount->execute([$userId]);
$unreadCount = $unreadCount->fetchColumn();

$totalNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$totalNotif->execute([$userId]);
$totalNotif = $totalNotif->fetchColumn();
$totalPages = max(1, ceil($totalNotif / $perPage));

$stmtNotif = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ?
    ORDER BY created_at DESC 
    LIMIT $perPage OFFSET $offset
");
$stmtNotif->execute([$userId]);
$myNotifications = $stmtNotif->fetchAll();

// Admin: grupos y usuarios
$groups = [];
$panelUsers = [];
$userSubscriptions = [];
if ($isAdmin) {
    $groups = $pdo->query("SELECT * FROM notification_groups WHERE is_active = 1 ORDER BY id")->fetchAll();
    $panelUsers = $pdo->query("SELECT id, name, username, email, role FROM users WHERE is_active = 1 ORDER BY role, name")->fetchAll();

    // Cargar suscripciones de todos los usuarios
    $allSubs = $pdo->query("SELECT user_id, group_id FROM notification_subscriptions")->fetchAll();
    foreach ($allSubs as $sub) {
        $userSubscriptions[$sub['user_id']][] = $sub['group_id'];
    }
}

// Iconos y colores por grupo
function getGroupStyle($slug) {
    $map = [
        'todas' => ['icon' => 'bi-bell-fill', 'color' => '#8b5cf6', 'bg' => 'rgba(139,92,246,0.1)'],
        'denuncia_creada' => ['icon' => 'bi-plus-circle-fill', 'color' => '#3b82f6', 'bg' => 'rgba(59,130,246,0.1)'],
        'asignacion' => ['icon' => 'bi-person-check-fill', 'color' => '#0ea5e9', 'bg' => 'rgba(14,165,233,0.1)'],
        'investigacion' => ['icon' => 'bi-search', 'color' => '#f59e0b', 'bg' => 'rgba(245,158,11,0.1)'],
        'resuelta' => ['icon' => 'bi-check-circle-fill', 'color' => '#2d9ad0', 'bg' => 'rgba(26,101,145,0.1)'],
        'cerrada' => ['icon' => 'bi-archive-fill', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)'],
    ];
    return $map[$slug] ?? ['icon' => 'bi-bell', 'color' => '#6b7280', 'bg' => 'rgba(107,114,128,0.1)'];
}

function getRoleBadge($role) {
    $map = [
        'admin' => '<span class="badge" style="background:#ef4444; font-size:0.7rem;">Admin</span>',
        'investigador' => '<span class="badge" style="background:#f59e0b; color:#000; font-size:0.7rem;">Investigador</span>',
        'viewer' => '<span class="badge" style="background:#6b7280; font-size:0.7rem;">Viewer</span>',
    ];
    return $map[$role] ?? '<span class="badge bg-secondary">' . htmlspecialchars($role) . '</span>';
}

$activeTab = sanitize($_GET['tab'] ?? 'mis_notificaciones');
if (!in_array($activeTab, ['mis_notificaciones', 'gestionar'])) $activeTab = 'mis_notificaciones';

require_once __DIR__ . '/../includes/encabezado.php';
require_once __DIR__ . '/../includes/barra_lateral.php';
?>

<style>
.notif-tabs .nav-link {
    border: none; border-radius: 12px; padding: 10px 20px;
    font-weight: 600; color: #64748b; font-size: 0.9rem;
    transition: all 0.2s;
}
.notif-tabs .nav-link.active {
    background: #1a6591; color: white;
}
.notif-tabs .nav-link:hover:not(.active) {
    background: #f1f5f9; color: #1a6591;
}
.notif-item {
    display: flex; gap: 14px; padding: 16px 20px;
    border-bottom: 1px solid #f1f5f9; transition: all 0.2s;
    cursor: pointer; position: relative;
}
.notif-item:hover { background: #f8fafc; }
.notif-item.unread { background: rgba(59,130,246,0.03); border-left: 3px solid #3b82f6; }
.notif-item.unread .notif-title { font-weight: 700; }
.notif-icon-wrap {
    width: 42px; height: 42px; min-width: 42px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 1.1rem;
}
.notif-title { font-size: 0.9rem; color: #1e293b; font-weight: 500; }
.notif-message { font-size: 0.82rem; color: #64748b; margin-top: 2px; }
.notif-time { font-size: 0.75rem; color: #94a3b8; white-space: nowrap; min-width: 90px; text-align: right; }
.notif-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; position: absolute; top: 20px; left: 8px; }
.user-card {
    background: white; border-radius: 16px; border: 1px solid #e5e7eb;
    padding: 20px; transition: all 0.3s;
}
.user-card:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
.group-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; border-radius: 10px; font-size: 0.8rem;
    font-weight: 500; cursor: pointer; transition: all 0.2s;
    border: 2px solid transparent; user-select: none;
}
.group-chip.active { border-color: currentColor; font-weight: 700; }
.group-chip:hover { transform: scale(1.03); }
.group-chip input { display: none; }
.empty-notif { text-align: center; padding: 60px 20px; }
.empty-notif i { font-size: 3rem; color: #cbd5e1; }
.save-btn-wrap {
    opacity: 0; transform: translateY(10px);
    transition: all 0.3s; pointer-events: none;
}
.save-btn-wrap.visible {
    opacity: 1; transform: translateY(0); pointer-events: all;
}
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(8px); }
    to { opacity: 1; transform: translateY(0); }
}
.notif-item, .user-card { animation: fadeIn 0.3s ease forwards; }
</style>

<div class="main-content">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="bi bi-bell me-2"></i>Notificaciones
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger ms-2" style="font-size: 0.7rem; border-radius: 8px;"><?= $unreadCount ?> nueva(s)</span>
                <?php endif; ?>
            </h4>
            <p class="text-muted mb-0">Gestiona tus notificaciones y suscripciones</p>
        </div>
        <?php if ($unreadCount > 0): ?>
        <button class="btn btn-sm btn-outline-primary mt-2 mt-md-0" style="border-radius: 10px;" onclick="markAllRead()">
            <i class="bi bi-check2-all me-1"></i>Marcar todas como leídas
        </button>
        <?php endif; ?>
    </div>

    <!-- Tabs -->
    <ul class="nav notif-tabs mb-4 gap-2">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'mis_notificaciones' ? 'active' : '' ?>" href="?tab=mis_notificaciones">
                <i class="bi bi-inbox me-1"></i>Mis Notificaciones
                <?php if ($unreadCount > 0): ?><span class="badge bg-danger ms-1" style="font-size:0.65rem;"><?= $unreadCount ?></span><?php endif; ?>
            </a>
        </li>
        <?php if ($isAdmin): ?>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'gestionar' ? 'active' : '' ?>" href="?tab=gestionar">
                <i class="bi bi-gear me-1"></i>Gestionar Suscripciones
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- TAB: Mis Notificaciones -->
    <?php if ($activeTab === 'mis_notificaciones'): ?>
    <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
        <?php if (empty($myNotifications)): ?>
        <div class="empty-notif">
            <i class="bi bi-bell-slash"></i>
            <h6 class="text-muted mt-3">Sin notificaciones</h6>
            <p class="text-muted small">Cuando haya actividad relevante, aparecerá aquí</p>
        </div>
        <?php else: ?>
        <div>
            <?php foreach ($myNotifications as $i => $notif):
                $gs = getGroupStyle($notif['group_slug']);
            ?>
            <div class="notif-item <?= !$notif['is_read'] ? 'unread' : '' ?>"
                 data-id="<?= $notif['id'] ?>"
                 onclick="markRead(this, <?= $notif['id'] ?>)"
                 style="animation-delay: <?= $i * 0.03 ?>s">
                <?php if (!$notif['is_read']): ?><div class="notif-dot"></div><?php endif; ?>
                <div class="notif-icon-wrap" style="background: <?= $gs['bg'] ?>; color: <?= $gs['color'] ?>;">
                    <i class="bi <?= $gs['icon'] ?>"></i>
                </div>
                <div style="flex:1; min-width:0;">
                    <div class="notif-title"><?= htmlspecialchars($notif['title']) ?></div>
                    <?php if ($notif['message']): ?>
                    <div class="notif-message"><?= htmlspecialchars($notif['message']) ?></div>
                    <?php endif; ?>
                    <?php if ($notif['entity_type'] && $notif['entity_id']): ?>
                    <a href="/detalle_denuncia?id=<?= (int)$notif['entity_id'] ?>" class="small text-primary text-decoration-none" onclick="event.stopPropagation();">
                        <i class="bi bi-arrow-right-short"></i>Ver detalle
                    </a>
                    <?php endif; ?>
                </div>
                <div class="notif-time">
                    <?= timeAgo($notif['created_at']) ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="p-3 bg-white border-top text-center">
            <nav>
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                    <li class="page-item"><a class="page-link" href="?tab=mis_notificaciones&page=<?= $page - 1 ?>" style="border-radius:8px;"><i class="bi bi-chevron-left"></i></a></li>
                    <?php endif; ?>
                    <?php for ($i = max(1, $page - 3); $i <= min($totalPages, $page + 3); $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?tab=mis_notificaciones&page=<?= $i ?>" style="border-radius:8px;"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="?tab=mis_notificaciones&page=<?= $page + 1 ?>" style="border-radius:8px;"><i class="bi bi-chevron-right"></i></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- TAB: Gestionar Suscripciones (solo admin) -->
    <?php elseif ($activeTab === 'gestionar' && $isAdmin): ?>

    <!-- Leyenda de grupos -->
    <div class="card border-0 shadow-sm mb-4" style="border-radius: 16px;">
        <div class="card-body">
            <h6 class="fw-bold mb-3"><i class="bi bi-collection me-2"></i>Grupos de Notificación</h6>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($groups as $g):
                    $gs = getGroupStyle($g['slug']);
                ?>
                <div class="d-flex align-items-center gap-2 px-3 py-2 rounded-3" style="background: <?= $gs['bg'] ?>; font-size: 0.85rem;">
                    <i class="bi <?= $gs['icon'] ?>" style="color: <?= $gs['color'] ?>;"></i>
                    <span class="fw-semibold" style="color: <?= $gs['color'] ?>;"><?= htmlspecialchars($g['name']) ?></span>
                    <small class="text-muted ms-1">&mdash; <?= htmlspecialchars($g['description']) ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Usuarios y sus suscripciones -->
    <div class="row g-3">
        <?php foreach ($panelUsers as $idx => $pu):
            $subs = $userSubscriptions[$pu['id']] ?? [];
            $hasEmail = !empty($pu['email']);
        ?>
        <div class="col-md-6 col-xl-4" style="animation-delay: <?= $idx * 0.05 ?>s">
            <div class="user-card" data-user-id="<?= $pu['id'] ?>">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <div style="width:44px; height:44px; border-radius:12px; background: linear-gradient(135deg, #1a6591, #2380b0); display:flex; align-items:center; justify-content:center; color:white; font-weight:700; font-size:1.1rem;">
                        <?= strtoupper(mb_substr($pu['name'], 0, 1)) ?>
                    </div>
                    <div style="min-width:0; flex:1;">
                        <div class="fw-bold text-truncate"><?= htmlspecialchars($pu['name']) ?></div>
                        <div class="d-flex align-items-center gap-2">
                            <small class="text-muted"><?= htmlspecialchars($pu['username']) ?></small>
                            <?= getRoleBadge($pu['role']) ?>
                        </div>
                    </div>
                </div>

                <!-- Email editable -->
                <div class="email-row mb-3" data-user-id="<?= $pu['id'] ?>">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-envelope<?= $hasEmail ? '-fill text-primary' : ' text-muted' ?>" style="font-size: 0.9rem;"></i>
                        <input type="email" class="email-input form-control form-control-sm"
                               value="<?= htmlspecialchars($pu['email'] ?? '') ?>"
                               placeholder="Agregar email..."
                               data-original="<?= htmlspecialchars($pu['email'] ?? '') ?>"
                               oninput="onEmailChange(this)"
                               style="border: 1px solid #e5e7eb; border-radius: 8px; font-size: 0.82rem; padding: 6px 10px;">
                        <button class="btn btn-sm email-save-btn" style="display:none; border-radius: 8px; padding: 4px 10px;"
                                onclick="saveEmail(<?= $pu['id'] ?>, this)">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <?php if ($hasEmail): ?>
                        <button class="btn btn-sm btn-outline-secondary" style="border-radius: 8px; padding: 4px 8px; font-size: 0.75rem;"
                                onclick="testEmail(<?= $pu['id'] ?>, this)" title="Enviar correo de prueba">
                            <i class="bi bi-send"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="email-feedback mt-1" style="font-size: 0.75rem; display: none;"></div>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($groups as $g):
                        $gs = getGroupStyle($g['slug']);
                        $isSubscribed = in_array($g['id'], $subs);
                    ?>
                    <label class="group-chip <?= $isSubscribed ? 'active' : '' ?>"
                           style="background: <?= $gs['bg'] ?>; color: <?= $gs['color'] ?>;"
                           title="<?= htmlspecialchars($g['description']) ?>">
                        <input type="checkbox" name="groups[]" value="<?= $g['id'] ?>" <?= $isSubscribed ? 'checked' : '' ?>
                               onchange="onGroupChange(this)">
                        <i class="bi <?= $gs['icon'] ?>" style="font-size: 0.85rem;"></i>
                        <?= htmlspecialchars($g['slug'] === 'todas' ? 'Todas' : $g['name']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="save-btn-wrap mt-3" id="save-<?= $pu['id'] ?>">
                    <button class="btn btn-primary btn-sm w-100" style="border-radius: 10px;" onclick="saveSubscriptions(<?= $pu['id'] ?>)">
                        <i class="bi bi-check-lg me-1"></i>Guardar suscripciones
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
const csrfToken = '<?= generateCsrfToken() ?>';

function markRead(el, id) {
    if (el.classList.contains('unread')) {
        fetch('/notificaciones', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `ajax_action=mark_read&notification_id=${id}&<?= CSRF_TOKEN_NAME ?>=${csrfToken}`
        }).then(r => r.json()).then(d => {
            if (d.ok) {
                el.classList.remove('unread');
                const dot = el.querySelector('.notif-dot');
                if (dot) dot.remove();
                updateBadgeCount(-1);
            }
        });
    }
}

function markAllRead() {
    fetch('/notificaciones', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=mark_all_read&<?= CSRF_TOKEN_NAME ?>=${csrfToken}`
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            document.querySelectorAll('.notif-item.unread').forEach(el => {
                el.classList.remove('unread');
                const dot = el.querySelector('.notif-dot');
                if (dot) dot.remove();
            });
            updateBadgeCount(-999);
        }
    });
}

function updateBadgeCount(delta) {
    const badges = document.querySelectorAll('.badge.bg-danger');
    badges.forEach(b => {
        let val = parseInt(b.textContent) + delta;
        if (val <= 0) b.style.display = 'none';
        else b.textContent = val + ' nueva(s)';
    });
}

function onGroupChange(checkbox) {
    const card = checkbox.closest('.user-card');
    const userId = card.dataset.userId;
    const chip = checkbox.closest('.group-chip');
    chip.classList.toggle('active', checkbox.checked);
    document.getElementById('save-' + userId).classList.add('visible');
}

function saveSubscriptions(userId) {
    const card = document.querySelector(`.user-card[data-user-id="${userId}"]`);
    const checkboxes = card.querySelectorAll('input[name="groups[]"]:checked');
    const groupIds = Array.from(checkboxes).map(cb => cb.value);
    const btn = card.querySelector('.save-btn-wrap button');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando...';
    btn.disabled = true;

    fetch('/notificaciones', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=save_subscriptions&target_user_id=${userId}&group_ids=${JSON.stringify(groupIds)}&<?= CSRF_TOKEN_NAME ?>=${csrfToken}`
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>¡Guardado!';
            btn.classList.replace('btn-primary', 'btn-success');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.replace('btn-success', 'btn-primary');
                btn.disabled = false;
                document.getElementById('save-' + userId).classList.remove('visible');
            }, 2000);
        } else {
            btn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Error';
            btn.classList.replace('btn-primary', 'btn-danger');
            btn.disabled = false;
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.replace('btn-danger', 'btn-primary');
            }, 2000);
        }
    });
}

function onEmailChange(input) {
    const original = input.dataset.original;
    const saveBtn = input.parentElement.querySelector('.email-save-btn');
    if (input.value !== original) {
        saveBtn.style.display = 'inline-flex';
        saveBtn.className = 'btn btn-sm btn-primary email-save-btn';
    } else {
        saveBtn.style.display = 'none';
    }
}

function saveEmail(userId, btn) {
    const row = btn.closest('.email-row');
    const input = row.querySelector('.email-input');
    const feedback = row.querySelector('.email-feedback');
    const email = input.value.trim();

    if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        feedback.style.display = 'block';
        feedback.className = 'email-feedback mt-1 text-danger';
        feedback.style.fontSize = '0.75rem';
        feedback.textContent = 'Email inválido';
        return;
    }

    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    btn.disabled = true;

    fetch('/notificaciones', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=update_email&target_user_id=${userId}&email=${encodeURIComponent(email)}&<?= CSRF_TOKEN_NAME ?>=${csrfToken}`
    }).then(r => r.json()).then(d => {
        feedback.style.display = 'block';
        feedback.style.fontSize = '0.75rem';
        if (d.ok) {
            feedback.className = 'email-feedback mt-1 text-success';
            feedback.textContent = '✓ Email guardado';
            input.dataset.original = email;
            btn.style.display = 'none';
            // Update envelope icon
            const icon = row.querySelector('.bi-envelope, .bi-envelope-fill');
            if (icon) { icon.className = 'bi bi-envelope-fill text-primary'; icon.style.fontSize = '0.9rem'; }
            // Show test button if not present
            let testBtn = row.querySelector('[onclick^="testEmail"]');
            if (!testBtn) {
                testBtn = document.createElement('button');
                testBtn.className = 'btn btn-sm btn-outline-secondary';
                testBtn.style.cssText = 'border-radius: 8px; padding: 4px 8px; font-size: 0.75rem;';
                testBtn.setAttribute('onclick', `testEmail(${userId}, this)`);
                testBtn.title = 'Enviar correo de prueba';
                testBtn.innerHTML = '<i class="bi bi-send"></i>';
                input.parentElement.appendChild(testBtn);
            }
            setTimeout(() => { feedback.style.display = 'none'; }, 3000);
        } else {
            feedback.className = 'email-feedback mt-1 text-danger';
            feedback.textContent = d.error || 'Error al guardar';
        }
        btn.innerHTML = '<i class="bi bi-check-lg"></i>';
        btn.disabled = false;
    });
}

function testEmail(userId, btn) {
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    btn.disabled = true;

    const row = btn.closest('.email-row');
    const feedback = row.querySelector('.email-feedback');

    fetch('/notificaciones', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `ajax_action=test_email&target_user_id=${userId}&<?= CSRF_TOKEN_NAME ?>=${csrfToken}`
    }).then(r => r.json()).then(d => {
        feedback.style.display = 'block';
        feedback.style.fontSize = '0.75rem';
        if (d.ok) {
            feedback.className = 'email-feedback mt-1 text-success';
            feedback.textContent = '✓ Correo de prueba enviado';
        } else {
            feedback.className = 'email-feedback mt-1 text-danger';
            feedback.textContent = d.error || 'Error al enviar';
        }
        btn.innerHTML = original;
        btn.disabled = false;
        setTimeout(() => { feedback.style.display = 'none'; }, 4000);
    });
}
</script>

<?php require_once __DIR__ . '/../includes/pie_pagina.php'; ?>
