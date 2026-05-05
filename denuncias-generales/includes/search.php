<?php
/**
 * Search.php - Fulltext search for complaints
 * Provides search functionality with relevance scoring
 */

/**
 * Search complaints by keywords
 * Uses FULLTEXT INDEX for performance or falls back to LIKE
 * 
 * @param PDO $pdo Database connection
 * @param string $query Search query
 * @param array $filters Additional filters (status, type, user_id)
 * @param int $page Pagination page
 * @param int $perPage Items per page
 * @return array Paginated search results
 */
function searchComplaints($pdo, $query, $filters = [], $page = 1, $perPage = 20) {
    if (empty(trim($query))) {
        return ['data' => [], 'total' => 0, 'page' => $page, 'pages' => 0];
    }

    // Normalizar término para búsquedas LIKE parametrizadas.
    $query = trim($query);
    $searchTerm = '%' . $query . '%';
    
    // Base SQL with UNION for multiple fields (title/description/witness search)
    $sql = "
        SELECT id, complaint_number, description, complaint_type, status, 
               reporter_name, created_at, updated_at,
               CASE 
                   WHEN description LIKE :search THEN 3
                   WHEN involved_persons LIKE :search THEN 2
                   WHEN witnesses LIKE :search THEN 1
                   ELSE 0
               END as relevance
        FROM complaints
        WHERE (
            description LIKE :search 
            OR involved_persons LIKE :search 
            OR witnesses LIKE :search 
            OR complaint_type LIKE :search
            OR reporter_name LIKE :search
        )
        AND deleted_at IS NULL
    ";

    $params = ['search' => $searchTerm];
    
    // Add filters
    if (!empty($filters['status'])) {
        $sql .= " AND status = :status";
        $params['status'] = $filters['status'];
    }
    
    if (!empty($filters['type'])) {
        $sql .= " AND complaint_type = :type";
        $params['type'] = $filters['type'];
    }
    
    if (!empty($filters['user_id']) && $filters['user_id'] !== 'all') {
        $sql .= " AND created_by_user_id = :user_id";
        $params['user_id'] = $filters['user_id'];
    }
    
    // Order by relevance + date
    $sql .= " ORDER BY relevance DESC, created_at DESC";
    
    return getPaginatedResults($pdo, $sql, $params, $page, $perPage);
}

/**
 * Get search suggestions based on partial input
 * Returns recent complaints matching prefix
 * 
 * @param PDO $pdo
 * @param string $prefix Partial search term
 * @param int $limit Max suggestions
 * @return array Suggestions
 */
function getSearchSuggestions($pdo, $prefix, $limit = 5) {
    if (strlen(trim($prefix)) < 2) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT complaint_number, description, complaint_type
            FROM complaints
            WHERE (
                complaint_number LIKE :prefix 
                OR description LIKE :prefix 
                OR complaint_type LIKE :prefix
            )
            AND deleted_at IS NULL
            ORDER BY created_at DESC
            LIMIT :limit
        ");
        
        $prefix = $prefix . '%';
        $stmt->bindParam(':prefix', $prefix);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    } catch (Exception $e) {
        log_db_error("Search suggestions query", $e->getMessage());
        return [];
    }
}

/**
 * Advanced search with date range
 * 
 * @param PDO $pdo
 * @param string $query Search keywords
 * @param string $dateFrom Start date (Y-m-d)
 * @param string $dateTo End date (Y-m-d)
 * @param array $filters Additional filters
 * @param int $page Page number
 * @return array Results
 */
function searchComplaintsAdvanced($pdo, $query, $dateFrom = null, $dateTo = null, $filters = [], $page = 1) {
    $sql = "
        SELECT id, complaint_number, description, complaint_type, status, 
               reporter_name, created_at, updated_at,
               CASE 
                   WHEN description LIKE :search THEN 3
                   WHEN involved_persons LIKE :search THEN 2
                   WHEN witnesses LIKE :search THEN 1
                   ELSE 0
               END as relevance
        FROM complaints
        WHERE (
            description LIKE :search 
            OR involved_persons LIKE :search 
            OR witnesses LIKE :search 
            OR complaint_type LIKE :search
        )
        AND deleted_at IS NULL
    ";
    
    $searchTerm = '%' . trim((string)$query) . '%';
    $params = ['search' => $searchTerm];

    // Date range filter (sin aplicar funciones sobre la columna para preservar índices)
    if (!empty($dateFrom)) {
        $sql .= " AND created_at >= :dateFrom";
        $params['dateFrom'] = $dateFrom . ' 00:00:00';
    }

    if (!empty($dateTo)) {
        $sql .= " AND created_at <= :dateTo";
        $params['dateTo'] = $dateTo . ' 23:59:59';
    }
    
    // Status filter
    if (!empty($filters['status'])) {
        $sql .= " AND status = :status";
        $params['status'] = $filters['status'];
    }
    
    // Type filter
    if (!empty($filters['type'])) {
        $sql .= " AND complaint_type = :type";
        $params['type'] = $filters['type'];
    }
    
    $sql .= " ORDER BY relevance DESC, created_at DESC";
    
    return getPaginatedResults($pdo, $sql, $params, $page, 20);
}

/**
 * Index search statistics
 * Returns search trends for dashboard
 * 
 * @param PDO $pdo
 * @return array Statistics
 */
function getSearchStatistics($pdo) {
    try {
        $stats = [];
        
        // Most common types
        $stmt = $pdo->query("
            SELECT complaint_type, COUNT(*) as count
            FROM complaints
            WHERE deleted_at IS NULL
            GROUP BY complaint_type
            ORDER BY count DESC
            LIMIT 10
        ");
        $stats['types'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Most common status
        $stmt = $pdo->query("
            SELECT status, COUNT(*) as count
            FROM complaints
            WHERE deleted_at IS NULL
            GROUP BY status
            ORDER BY count DESC
        ");
        $stats['status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $stats;
    } catch (Exception $e) {
        log_db_error("Search statistics query", $e->getMessage());
        return ['types' => [], 'status' => []];
    }
}

?>
