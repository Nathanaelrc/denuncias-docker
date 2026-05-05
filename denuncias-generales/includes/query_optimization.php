<?php
/**
 * Query_Optimization.php - Query optimization helpers
 * Provides pagination, caching, and query optimization patterns
 */

/**
 * Paginate results for large datasets
 * 
 * @param int $page Current page (1-indexed)
 * @param int $perPage Items per page
 * @return array ['offset' => int, 'limit' => int, 'page' => int]
 */
function getPaginationParams($page = 1, $perPage = 20) {
    $page = max(1, (int)$page);
    $perPage = max(1, min(100, (int)$perPage)); // Cap at 100
    $offset = ($page - 1) * $perPage;
    
    return [
        'offset' => $offset,
        'limit' => $perPage,
        'page' => $page
    ];
}

/**
 * Get paginated results with total count
 * 
 * @param PDO $pdo Database connection
 * @param string $query SQL query (will add LIMIT/OFFSET)
 * @param array $params Query parameters
 * @param int $page Current page
 * @param int $perPage Items per page
 * @return array ['data' => array, 'total' => int, 'page' => int, 'pages' => int]
 */
function getPaginatedResults($pdo, $query, $params = [], $page = 1, $perPage = 20) {
    $pagination = getPaginationParams($page, $perPage);
    
    // Get count (remove ORDER BY for count query optimization)
    $countQuery = preg_replace('/ORDER BY.*$/i', '', $query);
    $countQuery = preg_replace('/LIMIT.*$/i', '', $countQuery);
    $countQuery = "SELECT COUNT(*) as total FROM (" . $countQuery . ") as count_table";
    
    try {
        $countStmt = $pdo->prepare($countQuery);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetch()['total'];
    } catch (Exception $e) {
        log_db_error($countQuery, $e->getMessage(), ['params' => count($params)]);
        $total = 0;
    }
    
    // Get paginated data
    $dataQuery = $query . " LIMIT :offset, :limit";
    $params['offset'] = $pagination['offset'];
    $params['limit'] = $pagination['limit'];
    
    try {
        $stmt = $pdo->prepare($dataQuery);
        // Bind offset and limit as integers
        $stmt->bindParam(':offset', $params['offset'], PDO::PARAM_INT);
        $stmt->bindParam(':limit', $params['limit'], PDO::PARAM_INT);
        // Bind other params
        foreach ($params as $key => $value) {
            if ($key !== 'offset' && $key !== 'limit') {
                $stmt->bindValue(':' . $key, $value);
            }
        }
        $stmt->execute();
        $data = $stmt->fetchAll();
    } catch (Exception $e) {
        log_db_error($dataQuery, $e->getMessage(), ['params' => count($params)]);
        $data = [];
    }
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $pagination['page'],
        'pages' => ceil($total / $pagination['limit']),
        'perPage' => $pagination['limit']
    ];
}

/**
 * Simple cache wrapper using session (no external dependencies)
 * Caches query results for 5 minutes by default
 */
class QueryCache {
    private static $cache = [];
    private static $timestamps = [];
    const DEFAULT_TTL = 300; // 5 minutes
    
    /**
     * Get cached result if exists and not expired
     */
    public static function get($key) {
        if (isset(self::$cache[$key])) {
            $ttl = self::$timestamps[$key] ?? 0;
            if (time() - $ttl < self::DEFAULT_TTL) {
                log_debug("Cache HIT for key: $key", [], 'CACHE');
                return self::$cache[$key];
            }
            unset(self::$cache[$key], self::$timestamps[$key]);
        }
        return null;
    }
    
    /**
     * Cache a result
     */
    public static function set($key, $value) {
        self::$cache[$key] = $value;
        self::$timestamps[$key] = time();
        log_debug("Cache SET for key: $key", [], 'CACHE');
    }
    
    /**
     * Invalidate cache by key pattern
     */
    public static function invalidate($pattern = null) {
        if ($pattern === null) {
            self::$cache = [];
            self::$timestamps = [];
        } else {
            foreach (array_keys(self::$cache) as $key) {
                if (strpos($key, $pattern) === 0) {
                    unset(self::$cache[$key], self::$timestamps[$key]);
                }
            }
        }
    }
}

/**
 * Detect N+1 query patterns (for debugging)
 * Returns true if likely N+1 pattern detected
 */
function detectN1Pattern($queryCount, $threshold = 10) {
    // This is a simple heuristic - in production use more sophisticated tools
    if ($queryCount > $threshold) {
        log_warning("Possible N+1 query pattern detected ($queryCount queries)", [], 'PERF');
        return true;
    }
    return false;
}

?>
