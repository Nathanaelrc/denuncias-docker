<?php
/**
 * Logging.php - Centralized structured logging system
 * Replaces ad-hoc error_log() calls with standardized logging
 */

// Log file configuration
define('LOG_DIR', __DIR__ . '/../storage/logs');
define('LOG_LEVEL_DEBUG', 'DEBUG');
define('LOG_LEVEL_INFO', 'INFO');
define('LOG_LEVEL_WARNING', 'WARNING');
define('LOG_LEVEL_ERROR', 'ERROR');
define('LOG_LEVEL_CRITICAL', 'CRITICAL');

// Ensure log directory exists
if (!is_dir(LOG_DIR)) {
    @mkdir(LOG_DIR, 0755, true);
}

/**
 * Main logging function - writes structured logs
 * 
 * @param string $level Log level (DEBUG, INFO, WARNING, ERROR, CRITICAL)
 * @param string $message Log message
 * @param array $context Additional context data
 * @param string $category Category/module name (e.g., 'AUTH', 'COMPLAINT', 'EMAIL')
 */
function log_message($level, $message, $context = [], $category = 'APP') {
    // Get current timestamp in ISO 8601 format
    $timestamp = date('Y-m-d\TH:i:s.000P');
    
    // Build context string
    $contextStr = '';
    if (!empty($context)) {
        $contextStr = ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    // Get caller info (file, line)
    $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
    $caller = $trace[1] ?? [];
    $file = basename($caller['file'] ?? 'unknown');
    $line = $caller['line'] ?? 0;
    
    // Format log entry
    $logEntry = "[{$timestamp}] [{$level}] [{$category}] {$file}:{$line} {$message}{$contextStr}\n";
    
    // Write to rotating log file (daily rotation)
    $logFile = LOG_DIR . '/app-' . date('Y-m-d') . '.log';
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    
    // Also write errors to error-specific log
    if ($level === LOG_LEVEL_ERROR || $level === LOG_LEVEL_CRITICAL) {
        $errorLogFile = LOG_DIR . '/error-' . date('Y-m-d') . '.log';
        @file_put_contents($errorLogFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    // Send CRITICAL errors to system error_log for alerting
    if ($level === LOG_LEVEL_CRITICAL) {
        error_log($logEntry);
    }
}

/**
 * Log debug message
 */
function log_debug($message, $context = [], $category = 'APP') {
    log_message(LOG_LEVEL_DEBUG, $message, $context, $category);
}

/**
 * Log info message
 */
function log_info($message, $context = [], $category = 'APP') {
    log_message(LOG_LEVEL_INFO, $message, $context, $category);
}

/**
 * Log warning message
 */
function log_warning($message, $context = [], $category = 'APP') {
    log_message(LOG_LEVEL_WARNING, $message, $context, $category);
}

/**
 * Log error message
 */
function log_error($message, $context = [], $category = 'APP') {
    log_message(LOG_LEVEL_ERROR, $message, $context, $category);
}

/**
 * Log critical message (highest priority)
 */
function log_critical($message, $context = [], $category = 'APP') {
    log_message(LOG_LEVEL_CRITICAL, $message, $context, $category);
}

/**
 * Log authentication events
 */
function log_auth($action, $email, $success, $reason = '') {
    $context = ['email' => $email, 'success' => $success];
    if ($reason) {
        $context['reason'] = $reason;
    }
    $level = $success ? LOG_LEVEL_INFO : LOG_LEVEL_WARNING;
    log_message($level, "AUTH: {$action}", $context, 'AUTH');
}

/**
 * Log complaint operations
 */
function log_complaint($action, $complaintId, $complaintNumber = '', $details = []) {
    $context = array_merge(['complaint_id' => $complaintId, 'complaint_number' => $complaintNumber], $details);
    log_message(LOG_LEVEL_INFO, "COMPLAINT: {$action}", $context, 'COMPLAINT');
}

/**
 * Log email operations
 */
function log_email($recipient, $subject, $success, $reason = '') {
    $context = ['recipient' => $recipient, 'subject' => substr($subject, 0, 100), 'success' => $success];
    if ($reason) {
        $context['reason'] = $reason;
    }
    $level = $success ? LOG_LEVEL_INFO : LOG_LEVEL_ERROR;
    log_message($level, "EMAIL: Send to {$recipient}", $context, 'EMAIL');
}

/**
 * Log database errors
 */
function log_db_error($query, $error, $context = []) {
    $fullContext = array_merge(['query' => substr($query, 0, 200), 'error' => $error], $context);
    log_message(LOG_LEVEL_ERROR, "DB: Database error", $fullContext, 'DB');
}

/**
 * Log security events (CSRF, captcha, rate limit, etc.)
 */
function log_security($event, $details = []) {
    log_message(LOG_LEVEL_WARNING, "SECURITY: {$event}", $details, 'SECURITY');
}

/**
 * Cleanup old log files (older than 30 days)
 * Should be called periodically (e.g., from a cron job)
 */
function cleanup_old_logs($daysOld = 30) {
    $now = time();
    $cutoff = $now - ($daysOld * 24 * 60 * 60);
    
    $files = glob(LOG_DIR . '/*.log');
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            @unlink($file);
            log_debug("Removed old log file: " . basename($file), [], 'SYSTEM');
        }
    }
}

/**
 * Get recent logs (for admin dashboard)
 * 
 * @param string $logFile Log file name (e.g., 'app' or 'error')
 * @param int $lines Number of lines to retrieve
 * @return array Array of log entries
 */
function get_recent_logs($logFile = 'app', $lines = 50) {
    $file = LOG_DIR . '/' . $logFile . '-' . date('Y-m-d') . '.log';
    
    if (!file_exists($file)) {
        return [];
    }
    
    $allLines = file($file, FILE_IGNORE_NEW_LINES);
    return array_slice($allLines, max(0, count($allLines) - $lines));
}

?>
