<?php
/**
 * Worker CLI de cola de correos - Portal Ley Karin
 * Uso: php scripts/process-email-queue-karin.php [limite]
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este script debe ejecutarse por CLI.\n");
    exit(1);
}

require_once __DIR__ . '/../includes/bootstrap.php';

$limitArg = $argv[1] ?? getenv('EMAIL_QUEUE_BATCH') ?: '25';
$limit = (int)$limitArg;
$limit = max(1, min($limit, 200));

$stats = processEmailQueue($limit);

echo json_encode([
    'portal' => 'karin',
    'limit' => $limit,
    'picked' => $stats['picked'] ?? 0,
    'sent' => $stats['sent'] ?? 0,
    'failed' => $stats['failed'] ?? 0,
    'timestamp' => date('c'),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
