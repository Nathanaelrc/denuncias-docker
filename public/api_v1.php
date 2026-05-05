<?php
/**
 * REST API v1 endpoints for the complaints portal
 * Provides JSON API for mobile apps and frontend integrations
 * 
 * Usage:
 * GET    /api/v1/complaints?page=1&limit=20
 * POST   /api/v1/complaints (create new)
 * GET    /api/v1/complaints/{id}
 * POST   /api/v1/complaints/{id}/status (update status)
 * GET    /api/v1/search?q=keywords&type=acoso_laboral
 */

// Minimal bootstrap for API (no UI includes needed)
header('Content-Type: application/json; charset=UTF-8');
header('X-API-Version: 1.0');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// CORS (restrict to trusted domains in production)
$allowedOrigins = explode(',', getenv('API_ALLOWED_ORIGINS') ?: 'localhost');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins, true)) {
    header("Access-Control-Allow-Origin: $origin");
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load configuration
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/logging.php';
require_once __DIR__ . '/../includes/error_handler.php';
require_once __DIR__ . '/../includes/autenticacion.php';
require_once __DIR__ . '/../includes/denuncias.php';
require_once __DIR__ . '/../includes/search.php';
require_once __DIR__ . '/../includes/query_optimization.php';

/**
 * Parse request and return JSON response
 */
function apiResponse($data = null, $statusCode = 200, $message = null) {
    http_response_code($statusCode);
    
    $response = [
        'success' => ($statusCode >= 200 && $statusCode < 300),
        'status' => $statusCode,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ];
    
    // Remove null message if not provided
    if ($message === null) {
        unset($response['message']);
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Verify API token or session
 */
function apiAuth() {
    global $pdo;
    
    // Check session first
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isLoggedIn()) {
        return $_SESSION['user_id'];
    }
    
    // Check API token header
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    if (!$token) {
        apiResponse(null, 401, 'Unauthorized - no token provided');
    }
    
    // Extract token from "Bearer TOKEN" format
    if (strpos($token, 'Bearer ') === 0) {
        $token = substr($token, 7);
    }
    
    // Verify token (implement your token validation logic)
    // For now, basic placeholder - implement actual JWT/token logic in production
    $tokenHash = hash('sha256', $token);
    
    apiResponse(null, 401, 'Unauthorized - invalid token');
}

// Parse request path
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/v1', '', $path);
$parts = array_filter(explode('/', $path));
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? $_GET;

log_info("API Request: $method $path", ['user_id' => $_SESSION['user_id'] ?? null], 'API');

// ============================================================
// ROUTES
// ============================================================

// GET /api/v1/complaints - List complaints with pagination
if ($method === 'GET' && empty($parts)) {
    $page = $input['page'] ?? 1;
    $limit = $input['limit'] ?? 20;
    $status = $input['status'] ?? null;
    $type = $input['type'] ?? null;
    
    $sql = "
        SELECT id, complaint_number, complaint_type, status, 
               reporter_name, created_at, updated_at, priority
        FROM complaints
        WHERE deleted_at IS NULL
    ";
    
    $params = [];
    if ($status) {
        $sql .= " AND status = :status";
        $params['status'] = $status;
    }
    if ($type) {
        $sql .= " AND complaint_type = :type";
        $params['type'] = $type;
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    $results = getPaginatedResults($pdo, $sql, $params, $page, $limit);
    apiResponse($results);
}

// POST /api/v1/complaints - Create new complaint
if ($method === 'POST' && empty($parts)) {
    $userId = apiAuth();
    
    // Validate required fields
    $required = ['complaint_type', 'description'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            apiResponse(null, 400, "Missing required field: $field");
        }
    }
    
    $data = [
        'complaint_type' => $input['complaint_type'],
        'description' => $input['description'],
        'is_anonymous' => $input['is_anonymous'] ?? true,
        'reporter_name' => $input['reporter_name'] ?? null,
        'reporter_email' => $input['reporter_email'] ?? null,
        'reporter_phone' => $input['reporter_phone'] ?? null,
        'involved_persons' => $input['involved_persons'] ?? null,
        'accused_name' => $input['accused_name'] ?? null,
        'witnesses' => $input['witnesses'] ?? null,
        'incident_date' => $input['incident_date'] ?? null,
    ];
    
    $result = createComplaint($data);
    
    if ($result['success']) {
        log_info("API: Complaint created", ['complaint_id' => $result['id'], 'number' => $result['complaint_number']], 'API');
        apiResponse(['complaint_id' => $result['id'], 'number' => $result['complaint_number']], 201);
    } else {
        apiResponse(null, 400, $result['message'] ?? 'Failed to create complaint');
    }
}

// GET /api/v1/search - Search complaints
if ($method === 'GET' && $parts[0] === 'search') {
    $query = $input['q'] ?? '';
    $page = $input['page'] ?? 1;
    
    if (strlen($query) < 2) {
        apiResponse(null, 400, 'Search query too short (min 2 characters)');
    }
    
    $filters = [
        'status' => $input['status'] ?? null,
        'type' => $input['type'] ?? null,
    ];
    
    $results = searchComplaints($pdo, $query, $filters, $page, 20);
    apiResponse($results);
}

// GET /api/v1/complaints/{id} - Get complaint detail
if ($method === 'GET' && count($parts) === 1 && is_numeric($parts[0])) {
    $id = (int)$parts[0];
    
    $stmt = $pdo->prepare("
        SELECT id, complaint_number, complaint_type, status, priority,
               reporter_name, reporter_email, description, created_at, updated_at
        FROM complaints
        WHERE id = :id AND deleted_at IS NULL
    ");
    $stmt->execute(['id' => $id]);
    $complaint = $stmt->fetch();
    
    if (!$complaint) {
        apiResponse(null, 404, 'Complaint not found');
    }
    
    apiResponse($complaint);
}

// POST /api/v1/complaints/{id}/status - Update complaint status
if ($method === 'POST' && count($parts) === 2 && is_numeric($parts[0]) && $parts[1] === 'status') {
    apiAuth(); // Require authentication
    
    $id = (int)$parts[0];
    $status = $input['status'] ?? null;
    
    $validStatuses = ['recibida', 'en_investigacion', 'resuelta', 'desestimada', 'archivada'];
    if (!in_array($status, $validStatuses, true)) {
        apiResponse(null, 400, 'Invalid status value');
    }
    
    try {
        $stmt = $pdo->prepare("
            UPDATE complaints
            SET status = :status, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL
        ");
        $stmt->execute(['status' => $status, 'id' => $id]);
        
        if ($stmt->rowCount() > 0) {
            log_info("API: Complaint status updated", ['complaint_id' => $id, 'status' => $status], 'API');
            apiResponse(['id' => $id, 'status' => $status]);
        } else {
            apiResponse(null, 404, 'Complaint not found');
        }
    } catch (Exception $e) {
        log_error("API: Status update failed", ['error' => $e->getMessage()], 'API');
        apiResponse(null, 500, 'Database error');
    }
}

// 404 - Unknown endpoint
apiResponse(null, 404, 'API endpoint not found');

?>
