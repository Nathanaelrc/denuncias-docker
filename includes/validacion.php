<?php
/**
 * Validacion.php - Centralized input validation rules
 * Reusable validation functions for both portals
 */

/**
 * Validates a complaint submission
 * 
 * @param array $data The POST data to validate
 * @return array ['valid' => bool, 'errors' => array of error messages]
 */
function validateComplaintSubmission($data) {
    $errors = [];
    
    // Nombre Denunciante
    if (empty($data['nombre_denunciante'] ?? '')) {
        $errors['nombre_denunciante'] = 'El nombre del denunciante es requerido.';
    } elseif (strlen($data['nombre_denunciante']) < 3) {
        $errors['nombre_denunciante'] = 'El nombre debe tener al menos 3 caracteres.';
    } elseif (strlen($data['nombre_denunciante']) > 200) {
        $errors['nombre_denunciante'] = 'El nombre no puede exceder 200 caracteres.';
    }
    
    // Email
    if (empty($data['email'] ?? '')) {
        $errors['email'] = 'El email es requerido.';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El formato del email no es válido.';
    }
    
    // Teléfono (optional but validate if provided)
    if (!empty($data['telefono'] ?? '')) {
        if (!preg_match('/^[0-9\s\-\+()]{8,20}$/', $data['telefono'])) {
            $errors['telefono'] = 'El formato del teléfono no es válido.';
        }
    }
    
    // Tipo de Denuncia
    $validTypes = ['acoso_laboral', 'represalia', 'discriminacion', 'otro'];
    if (empty($data['tipo_denuncia'] ?? '')) {
        $errors['tipo_denuncia'] = 'El tipo de denuncia es requerido.';
    } elseif (!in_array($data['tipo_denuncia'], $validTypes, true)) {
        $errors['tipo_denuncia'] = 'Tipo de denuncia inválido.';
    }
    
    // Descripción
    if (empty($data['descripcion'] ?? '')) {
        $errors['descripcion'] = 'La descripción es requerida.';
    } elseif (strlen($data['descripcion']) < 10) {
        $errors['descripcion'] = 'La descripción debe tener al menos 10 caracteres.';
    } elseif (strlen($data['descripcion']) > 5000) {
        $errors['descripcion'] = 'La descripción no puede exceder 5000 caracteres.';
    }
    
    // Persona Acusada (optional)
    if (!empty($data['persona_acusada'] ?? '')) {
        if (strlen($data['persona_acusada']) > 500) {
            $errors['persona_acusada'] = 'Datos de persona acusada: máximo 500 caracteres.';
        }
    }
    
    // Privacidad Checkbox
    if (empty($data['privacidad'] ?? '') || $data['privacidad'] !== 'on') {
        $errors['privacidad'] = 'Debes aceptar la política de privacidad.';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validates login credentials
 * 
 * @param string $email
 * @param string $password
 * @return array ['valid' => bool, 'errors' => array]
 */
function validateLoginCredentials($email, $password) {
    $errors = [];
    
    if (empty($email)) {
        $errors['email'] = 'El email es requerido.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'El formato del email no es válido.';
    }
    
    if (empty($password)) {
        $errors['password'] = 'La contraseña es requerida.';
    } elseif (strlen($password) < 6) {
        $errors['password'] = 'La contraseña debe tener al menos 6 caracteres.';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Validates investigation note submission
 * 
 * @param string $content
 * @return array ['valid' => bool, 'errors' => array]
 */
function validateInvestigationNote($content) {
    $errors = [];
    
    if (empty($content)) {
        $errors['content'] = 'La nota de investigación no puede estar vacía.';
    } elseif (strlen($content) < 5) {
        $errors['content'] = 'La nota debe tener al menos 5 caracteres.';
    } elseif (strlen($content) > 2000) {
        $errors['content'] = 'La nota no puede exceder 2000 caracteres.';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Sanitizes string input - removes dangerous characters while preserving accents
 * 
 * @param string $input
 * @return string Sanitized input
 */
function sanitizeString($input) {
    $input = trim($input);
    // Remove null bytes
    $input = str_replace("\0", '', $input);
    // Remove potentially dangerous script tags (XSS prevention)
    $input = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $input);
    // Preserve accents, allow basic punctuation
    return $input;
}

/**
 * Sanitizes email address
 * 
 * @param string $email
 * @return string|false Sanitized email or false if invalid
 */
function sanitizeEmail($email) {
    $email = trim($email);
    $email = strtolower($email);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
}

/**
 * Validates and sanitizes complaint data
 * 
 * @param array $data
 * @return array ['valid' => bool, 'data' => sanitized array, 'errors' => array]
 */
function sanitizeComplaintData($data) {
    $validation = validateComplaintSubmission($data);
    
    if (!$validation['valid']) {
        return [
            'valid' => false,
            'data' => null,
            'errors' => $validation['errors']
        ];
    }
    
    $sanitized = [
        'nombre_denunciante' => sanitizeString($data['nombre_denunciante'] ?? ''),
        'email' => sanitizeEmail($data['email'] ?? ''),
        'telefono' => sanitizeString($data['telefono'] ?? ''),
        'tipo_denuncia' => strtolower($data['tipo_denuncia'] ?? ''),
        'descripcion' => sanitizeString($data['descripcion'] ?? ''),
        'persona_acusada' => sanitizeString($data['persona_acusada'] ?? ''),
        'ubicacion' => sanitizeString($data['ubicacion'] ?? ''),
    ];
    
    return [
        'valid' => true,
        'data' => $sanitized,
        'errors' => []
    ];
}

/**
 * Validates file uploads
 * 
 * @param array $file $_FILES array element
 * @param int $maxSizeMB Maximum file size in MB
 * @param array $allowedMimes Allowed MIME types
 * @return array ['valid' => bool, 'error' => string or null]
 */
function validateFileUpload($file, $maxSizeMB = 5, $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'text/plain']) {
    if (!isset($file['name']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Error en la carga del archivo.'];
    }
    
    // Check file size
    $maxBytes = $maxSizeMB * 1024 * 1024;
    if ($file['size'] > $maxBytes) {
        return ['valid' => false, 'error' => "El archivo no puede exceder {$maxSizeMB}MB."];
    }
    
    // Check MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, $allowedMimes, true)) {
        return ['valid' => false, 'error' => "Tipo de archivo no permitido. (detectado: {$mimeType})"];
    }
    
    return ['valid' => true, 'error' => null];
}

?>
