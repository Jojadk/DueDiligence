<?php
/**
 * Shared Utilities Module
 *
 * Common functions used across all modules
 * Reduces code duplication and ensures consistency
 */

/**
 * Get project ID from various record types
 * Used for permission checks
 */
function get_project_id_for_record(string $recordType, int $recordId): ?int {
    static $queries = [
        'building_elements' => "
            SELECT b.project_id
            FROM building_elements be
            JOIN buildings b ON b.id = be.building_id
            WHERE be.id = :id
        ",
        'buildings' => "
            SELECT project_id FROM buildings WHERE id = :id
        ",
        'projects' => "
            SELECT id as project_id FROM projects WHERE id = :id
        ",
        'budget_template_items' => "
            SELECT bt.project_id
            FROM budget_template_items bti
            JOIN budget_templates bt ON bt.id = bti.template_id
            WHERE bti.id = :id
        ",
        'budget_templates' => "
            SELECT project_id FROM budget_templates WHERE id = :id
        ",
        'element_images' => "
            SELECT b.project_id
            FROM element_images ei
            JOIN building_elements be ON be.id = ei.element_id
            JOIN buildings b ON b.id = be.building_id
            WHERE ei.id = :id
        ",
        'reports' => "
            SELECT project_id FROM reports WHERE id = :id
        "
    ];

    $query = $queries[$recordType] ?? null;
    if (!$query) {
        return null;
    }

    try {
        return (int)db_value($query, ['id' => $recordId]) ?: null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Verify project access and return project ID
 * Throws exception if no access
 */
function require_project_access(array $user, string $recordType, int $recordId, string $requiredRole = 'viewer'): int {
    $projectId = get_project_id_for_record($recordType, $recordId);

    if (!$projectId) {
        throw new Exception('Record ikke fundet');
    }

    if (!can_access_project($user, $projectId, $requiredRole)) {
        throw new Exception('Ingen adgang til projektet');
    }

    return $projectId;
}

/**
 * Standard success response
 */
function success_response(string $message = null, array $data = []): array {
    $response = ['success' => true];

    if ($message) {
        $response['message'] = $message;
    }

    return array_merge($response, $data);
}

/**
 * Standard error response
 */
function error_response(string $message, array $data = []): array {
    $response = [
        'success' => false,
        'error' => $message
    ];

    return array_merge($response, $data);
}

/**
 * Validate required POST parameters
 */
function require_params(array $params, array $required): void {
    foreach ($required as $field) {
        if (!isset($params[$field]) || $params[$field] === '') {
            throw new Exception("Feltet '$field' er påkrævet");
        }
    }
}

/**
 * Get pagination parameters from request
 */
function get_pagination_params(array $params = null): array {
    $params = $params ?? $_GET;

    $page = sanitize_int($params['page'] ?? 1);
    $limit = sanitize_int($params['limit'] ?? 50);

    // Enforce limits
    $page = max(1, $page);
    $limit = max(1, min(1000, $limit)); // Max 1000 per page

    $offset = ($page - 1) * $limit;

    return [
        'page' => $page,
        'limit' => $limit,
        'offset' => $offset
    ];
}

/**
 * Build pagination response
 */
function build_pagination_response(int $total, array $paginationParams): array {
    return [
        'total' => $total,
        'page' => $paginationParams['page'],
        'limit' => $paginationParams['limit'],
        'pages' => (int)ceil($total / $paginationParams['limit'])
    ];
}

/**
 * Format number for Danish locale
 */
function format_number_dk(float $number, int $decimals = 0): string {
    return number_format($number, $decimals, ',', '.');
}

/**
 * Format currency for Danish locale
 */
function format_currency_dk(float $amount, bool $includeCurrency = true): string {
    $formatted = format_number_dk($amount, 0);
    return $includeCurrency ? "kr. $formatted" : $formatted;
}

/**
 * Format date for Danish locale
 */
function format_date_dk($date, string $format = 'd-m-Y'): string {
    if (is_string($date)) {
        $date = strtotime($date);
    }

    if (!$date) {
        return '';
    }

    return date($format, $date);
}

/**
 * Sanitize and validate email
 */
function sanitize_email(string $email): ?string {
    $email = filter_var($email, FILTER_SANITIZE_EMAIL);
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
}

/**
 * Generate unique filename for upload
 */
function generate_upload_filename(string $originalFilename, string $prefix = ''): string {
    $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
    $prefix = $prefix ? $prefix . '_' : '';
    return $prefix . uniqid() . '_' . time() . '.' . $extension;
}

/**
 * Validate file upload
 */
function validate_upload(array $file, array $options = []): array {
    $maxSize = $options['max_size'] ?? 10 * 1024 * 1024; // 10MB default
    $allowedTypes = $options['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowedExtensions = $options['allowed_extensions'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    // Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['valid' => false, 'error' => 'Upload fejl: ' . $file['error']];
    }

    // Check file size
    if ($file['size'] > $maxSize) {
        $maxSizeMB = round($maxSize / 1024 / 1024, 1);
        return ['valid' => false, 'error' => "Filen er for stor. Max {$maxSizeMB}MB"];
    }

    // Validate mime type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['valid' => false, 'error' => 'Ugyldig filtype'];
    }

    // Validate extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $allowedExtensions)) {
        return ['valid' => false, 'error' => 'Ugyldig filendelse'];
    }

    return [
        'valid' => true,
        'mime_type' => $mimeType,
        'extension' => $extension
    ];
}

/**
 * Set current user ID for database session
 * Used by triggers to log changes
 */
function set_db_session_user(int $userId): void {
    try {
        db_execute("SELECT set_config('app.current_user_id', :user_id, false)", [
            'user_id' => (string)$userId
        ]);
    } catch (Exception $e) {
        // Silently fail - not critical
    }
}

/**
 * Calculate memory usage
 */
function get_memory_usage(): array {
    $usage = memory_get_usage(true);
    $peak = memory_get_peak_usage(true);

    return [
        'current' => $usage,
        'current_mb' => round($usage / 1024 / 1024, 2),
        'peak' => $peak,
        'peak_mb' => round($peak / 1024 / 1024, 2)
    ];
}

/**
 * Measure execution time
 */
class Timer {
    private $startTime;
    private $marks = [];

    public function __construct() {
        $this->startTime = microtime(true);
    }

    public function mark(string $label): void {
        $this->marks[$label] = microtime(true) - $this->startTime;
    }

    public function getMarks(): array {
        return $this->marks;
    }

    public function getElapsed(): float {
        return microtime(true) - $this->startTime;
    }

    public function getElapsedMs(): int {
        return (int)round($this->getElapsed() * 1000);
    }
}

/**
 * Cache wrapper for frequently accessed data
 */
class SimpleCache {
    private static $cache = [];
    private static $ttl = [];

    public static function get(string $key) {
        // Check if cached and not expired
        if (isset(self::$cache[$key])) {
            if (!isset(self::$ttl[$key]) || self::$ttl[$key] > time()) {
                return self::$cache[$key];
            }

            // Expired, remove
            unset(self::$cache[$key]);
            unset(self::$ttl[$key]);
        }

        return null;
    }

    public static function set(string $key, $value, int $ttlSeconds = 300): void {
        self::$cache[$key] = $value;
        self::$ttl[$key] = time() + $ttlSeconds;
    }

    public static function has(string $key): bool {
        return self::get($key) !== null;
    }

    public static function delete(string $key): void {
        unset(self::$cache[$key]);
        unset(self::$ttl[$key]);
    }

    public static function clear(): void {
        self::$cache = [];
        self::$ttl = [];
    }

    public static function cleanup(): void {
        $now = time();
        foreach (self::$ttl as $key => $expiry) {
            if ($expiry <= $now) {
                unset(self::$cache[$key]);
                unset(self::$ttl[$key]);
            }
        }
    }
}

/**
 * Batch database operations for better performance
 */
class BatchInsert {
    private $table;
    private $rows = [];
    private $batchSize;

    public function __construct(string $table, int $batchSize = 100) {
        $this->table = $table;
        $this->batchSize = $batchSize;
    }

    public function add(array $row): void {
        $this->rows[] = $row;

        if (count($this->rows) >= $this->batchSize) {
            $this->flush();
        }
    }

    public function flush(): array {
        if (empty($this->rows)) {
            return [];
        }

        // Get column names from first row
        $columns = array_keys($this->rows[0]);

        // Build VALUES clause
        $placeholders = [];
        $values = [];
        $paramIndex = 0;

        foreach ($this->rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $col) {
                $param = "p{$paramIndex}";
                $rowPlaceholders[] = ":$param";
                $values[$param] = $row[$col] ?? null;
                $paramIndex++;
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        // Execute batch insert
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s RETURNING id",
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $ids = db_fetch_all($sql, $values);
        $this->rows = [];

        return array_column($ids, 'id');
    }

    public function __destruct() {
        $this->flush();
    }
}

/**
 * Query builder helper for dynamic WHERE clauses
 */
class QueryBuilder {
    private $conditions = [];
    private $params = [];
    private $paramIndex = 0;

    public function where(string $field, $value, string $operator = '='): self {
        if ($value === null) {
            if ($operator === '=') {
                $this->conditions[] = "$field IS NULL";
            } else {
                $this->conditions[] = "$field IS NOT NULL";
            }
        } else {
            $param = "qb_p{$this->paramIndex}";
            $this->conditions[] = "$field $operator :$param";
            $this->params[$param] = $value;
            $this->paramIndex++;
        }

        return $this;
    }

    public function whereIn(string $field, array $values): self {
        if (empty($values)) {
            // Empty IN clause is always false
            $this->conditions[] = "1 = 0";
            return $this;
        }

        $placeholders = [];
        foreach ($values as $value) {
            $param = "qb_p{$this->paramIndex}";
            $placeholders[] = ":$param";
            $this->params[$param] = $value;
            $this->paramIndex++;
        }

        $this->conditions[] = "$field IN (" . implode(', ', $placeholders) . ")";
        return $this;
    }

    public function whereLike(string $field, string $value): self {
        $param = "qb_p{$this->paramIndex}";
        $this->conditions[] = "$field LIKE :$param";
        $this->params[$param] = "%$value%";
        $this->paramIndex++;

        return $this;
    }

    public function getWhereClause(): string {
        return empty($this->conditions) ? '' : 'WHERE ' . implode(' AND ', $this->conditions);
    }

    public function getParams(): array {
        return $this->params;
    }
}
