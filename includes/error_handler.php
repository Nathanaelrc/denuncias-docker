<?php
/**
 * Error_handler.php - Centralized error & exception handling
 * Catches PHP errors, exceptions, and fatal errors for structured logging
 */

/**
 * Convert PHP errors to exceptions for consistent handling
 */
function handleError($errno, $errstr, $errfile, $errline) {
    // Skip notices and warnings in production
    if ($errno === E_NOTICE || $errno === E_WARNING || $errno === E_DEPRECATED) {
        log_warning("PHP: $errstr", ['file' => $errfile, 'line' => $errline], 'PHP_ERROR');
        return true; // Suppress display
    }
    
    // Treat errors as exceptions
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
}

/**
 * Handle uncaught exceptions
 */
function handleException(Throwable $exception) {
    $statusCode = 500;
    $message = 'Error interno del servidor';
    
    // Determine HTTP status code based on exception type
    if ($exception instanceof InvalidArgumentException) {
        $statusCode = 400;
        $message = 'Solicitud inválida';
    } elseif ($exception instanceof RuntimeException) {
        $statusCode = 500;
        $message = 'Error al procesar la solicitud';
    }
    
    // Log the exception
    log_critical(
        'Uncaught exception: ' . get_class($exception),
        [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => substr($exception->getTraceAsString(), 0, 500)
        ],
        'EXCEPTION'
    );
    
    // Display appropriate error page
    displayErrorPage($statusCode, $message);
}

/**
 * Handle fatal errors
 */
function handleFatalError() {
    $error = error_get_last();
    
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        log_critical(
            'Fatal error: ' . $error['type'],
            [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line']
            ],
            'FATAL'
        );
        
        displayErrorPage(500, 'Error fatal del sistema');
    }
}

/**
 * Display error page based on HTTP status code
 */
function displayErrorPage($statusCode = 500, $message = 'Error interno del servidor') {
    // Set HTTP response code
    http_response_code($statusCode);
    
    // Check if custom error page exists
    $errorPagePath = __DIR__ . '/../public/' . $statusCode . '.php';
    if (file_exists($errorPagePath) && !headers_sent()) {
        include $errorPagePath;
    } else {
        // Fallback HTML error page
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error <?php echo $statusCode; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .error-container { background: white; border-radius: 12px; padding: 48px 32px; max-width: 500px; text-align: center; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
        .error-code { font-size: 72px; font-weight: bold; color: #667eea; line-height: 1; }
        .error-title { font-size: 24px; font-weight: 600; margin-top: 16px; color: #1f2937; }
        .error-message { color: #6b7280; margin-top: 12px; font-size: 16px; }
        .error-action { margin-top: 32px; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: 600; transition: background 0.2s; }
        .btn:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code"><?php echo $statusCode; ?></div>
        <div class="error-title"><?php echo htmlspecialchars($message); ?></div>
        <div class="error-message">Lo sentimos, algo salió mal. Por favor intenta de nuevo.</div>
        <div class="error-action">
            <a href="/" class="btn">Volver al Inicio</a>
        </div>
    </div>
</body>
</html>
        <?php
    }
    
    exit(1);
}

// Register error handlers
set_error_handler('handleError');
set_exception_handler('handleException');
register_shutdown_function('handleFatalError');

?>
