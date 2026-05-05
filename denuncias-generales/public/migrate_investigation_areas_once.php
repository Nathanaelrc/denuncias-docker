<?php
/**
 * Script temporal de migracion para areas de investigacion (denuncias-generales).
 * Ejecutar via HTTP local con token: ?token=...
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$providedToken = $_GET['token'] ?? '';
$allowedToken = 'aplicar-migracion-areas-2026';
$remoteIp = $_SERVER['REMOTE_ADDR'] ?? '';

if (!in_array($remoteIp, ['127.0.0.1', '::1'], true) || !hash_equals($allowedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

require_once __DIR__ . '/../includes/bootstrap.php';

function columnExists(PDO $pdo, string $schema, string $table, string $column): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?');
    $stmt->execute([$schema, $table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function indexExists(PDO $pdo, string $schema, string $table, string $index): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?');
    $stmt->execute([$schema, $table, $index]);
    return (int)$stmt->fetchColumn() > 0;
}

$schema = DB_NAME;
$executed = [];

try {
    $pdo->beginTransaction();

    if (!columnExists($pdo, $schema, 'users', 'investigator_area')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN investigator_area ENUM('concesiones', 'ingenieria', 'finanzas', 'sostenibilidad') NULL AFTER role");
        $executed[] = 'users.investigator_area added';
    }

    if (!columnExists($pdo, $schema, 'complaints', 'assigned_area')) {
        $pdo->exec("ALTER TABLE complaints ADD COLUMN assigned_area ENUM('concesiones', 'ingenieria', 'finanzas', 'sostenibilidad') NULL AFTER incident_location_nonce");
        $executed[] = 'complaints.assigned_area added';
    }

    if (!indexExists($pdo, $schema, 'complaints', 'idx_assigned_area')) {
        $pdo->exec('ALTER TABLE complaints ADD INDEX idx_assigned_area (assigned_area)');
        $executed[] = 'idx_assigned_area added';
    }

    if (!indexExists($pdo, $schema, 'complaints', 'idx_area_status')) {
        $pdo->exec('ALTER TABLE complaints ADD INDEX idx_area_status (assigned_area, status)');
        $executed[] = 'idx_area_status added';
    }

    // Backfill de area para investigadores existentes sin area definida.
    $stmtUsers = $pdo->prepare("UPDATE users
        SET investigator_area = CASE
            WHEN LOWER(COALESCE(department, '')) LIKE '%ingenier%' OR LOWER(COALESCE(position, '')) LIKE '%ingenier%' THEN 'ingenieria'
            WHEN LOWER(COALESCE(department, '')) LIKE '%finanza%' OR LOWER(COALESCE(position, '')) LIKE '%finanza%' OR LOWER(COALESCE(position, '')) LIKE '%contab%' THEN 'finanzas'
            WHEN LOWER(COALESCE(department, '')) LIKE '%sosten%' OR LOWER(COALESCE(department, '')) LIKE '%ambient%' OR LOWER(COALESCE(position, '')) LIKE '%sosten%' OR LOWER(COALESCE(position, '')) LIKE '%ambient%' THEN 'sostenibilidad'
            ELSE 'concesiones'
        END
        WHERE role = 'investigador' AND (investigator_area IS NULL OR investigator_area = '')");
    $stmtUsers->execute();
    $usersUpdated = $stmtUsers->rowCount();

    // Backfill de area para denuncias existentes sin area asignada.
    $stmtComplaints = $pdo->prepare("UPDATE complaints
        SET assigned_area = CASE
            WHEN complaint_type IN ('infraestructura', 'seguridad') THEN 'ingenieria'
            WHEN complaint_type IN ('medioambiente', 'impacto_comunidad') THEN 'sostenibilidad'
            WHEN complaint_type IN ('corrupcion', 'servicios') THEN 'finanzas'
            ELSE 'concesiones'
        END
        WHERE assigned_area IS NULL");
    $stmtComplaints->execute();
    $complaintsUpdated = $stmtComplaints->rowCount();

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'schema' => $schema,
        'executed' => $executed,
        'users_backfilled' => $usersUpdated,
        'complaints_backfilled' => $complaintsUpdated,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
        'executed' => $executed,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
