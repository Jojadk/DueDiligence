<?php
/**
 * DueDiligence v2.0 - Core System
 *
 * Contains all constants, basic functions, arrow functions and utilities
 */

// ============================================================================
// CONSTANTS
// ============================================================================

define('VERSION', '2.0.0');
define('APP_NAME', 'DueDiligence');
define('ROOT_DIR', dirname(__DIR__));
define('CORE_DIR', ROOT_DIR . '/core');
define('MODULES_DIR', ROOT_DIR . '/modules');
define('CONFIG_DIR', ROOT_DIR . '/config');
define('PROJECTS_DIR', ROOT_DIR . '/projects');
define('UPLOADS_DIR', ROOT_DIR . '/uploads');
define('LOGS_DIR', ROOT_DIR . '/logs');
define('REPORTS_DIR', ROOT_DIR . '/reports');

// Database constants (loaded from config)
define('DB_TYPE', 'pgsql');
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'duediligence');
define('DB_USER', getenv('DB_USER') ?: 'postgres');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Security constants
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 7200); // 2 hours
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutes

// File upload constants
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png']);

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

/**
 * Get database connection (singleton pattern)
 */
function db(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = DB_TYPE . ':host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            log_error('Database connection failed: ' . $e->getMessage());
            die('Database connection failed');
        }
    }

    return $pdo;
}

/**
 * Query result cache
 */
class QueryCache {
    private static $cache = [];
    private static $ttl = 300; // 5 minutes default
    private static $enabled = true;

    public static function get(string $key) {
        if (!self::$enabled) return null;

        if (isset(self::$cache[$key])) {
            $cached = self::$cache[$key];
            if ($cached['expires'] > time()) {
                return $cached['data'];
            }
            unset(self::$cache[$key]);
        }
        return null;
    }

    public static function set(string $key, $data, int $ttl = null): void {
        if (!self::$enabled) return;

        self::$cache[$key] = [
            'data' => $data,
            'expires' => time() + ($ttl ?? self::$ttl)
        ];
    }

    public static function clear(): void {
        self::$cache = [];
    }

    public static function disable(): void {
        self::$enabled = false;
    }

    public static function enable(): void {
        self::$enabled = true;
    }

    public static function generateKey(string $sql, array $params): string {
        return md5($sql . serialize($params));
    }
}

/**
 * Execute a query and return results (alias for db_fetch_all)
 */
function db_query(string $sql, array $params = []): array {
    return db_fetch_all($sql, $params);
}

/**
 * Execute a query and return all results with caching
 */
function db_fetch_all(string $sql, array $params = []): array {
    // Check cache for SELECT queries
    $isSelect = stripos(trim($sql), 'SELECT') === 0;

    if ($isSelect) {
        $cacheKey = QueryCache::generateKey($sql, $params);
        $cached = QueryCache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchAll();

    // Cache SELECT results
    if ($isSelect) {
        QueryCache::set($cacheKey, $result);
    }

    return $result;
}

/**
 * Execute a query and return single row with caching
 */
function db_fetch(string $sql, array $params = []): ?array {
    // Check cache for SELECT queries
    $isSelect = stripos(trim($sql), 'SELECT') === 0;

    if ($isSelect) {
        $cacheKey = QueryCache::generateKey($sql, $params);
        $cached = QueryCache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    $result = $result ?: null;

    // Cache SELECT results
    if ($isSelect && $result !== null) {
        QueryCache::set($cacheKey, $result);
    }

    return $result;
}

/**
 * Execute a query and return first column of first row with caching
 */
function db_value(string $sql, array $params = []) {
    // Check cache for SELECT queries
    $isSelect = stripos(trim($sql), 'SELECT') === 0;

    if ($isSelect) {
        $cacheKey = QueryCache::generateKey($sql, $params);
        $cached = QueryCache::get($cacheKey);

        if ($cached !== null) {
            return $cached;
        }
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchColumn();

    // Cache SELECT results
    if ($isSelect) {
        QueryCache::set($cacheKey, $result);
    }

    return $result;
}

/**
 * Execute a non-query statement (INSERT, UPDATE, DELETE) and return affected rows
 */
function db_execute(string $sql, array $params = []): int {
    // Clear cache on data modifications
    QueryCache::clear();

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Insert a record and return the last insert ID
 */
function db_insert(string $table, array $data): int {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));

    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    $stmt = db()->prepare($sql);
    $stmt->execute($data);

    return (int) db()->lastInsertId();
}

/**
 * Update a record
 */
function db_update(string $table, array $data, string $where, array $whereParams = []): int {
    $set = [];
    foreach (array_keys($data) as $key) {
        $set[] = "{$key} = :{$key}";
    }

    $sql = "UPDATE {$table} SET " . implode(', ', $set) . " WHERE {$where}";
    $stmt = db()->prepare($sql);
    $stmt->execute(array_merge($data, $whereParams));

    return $stmt->rowCount();
}

/**
 * Delete a record
 */
function db_delete(string $table, string $where, array $params = []): int {
    $sql = "DELETE FROM {$table} WHERE {$where}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

// ============================================================================
// CONFIGURATION
// ============================================================================

/**
 * Load JSON configuration file
 */
function load_config(string $file = 'forms'): array {
    $path = CONFIG_DIR . '/' . $file . '.json';

    if (!file_exists($path)) {
        log_error("Config file not found: {$path}");
        return [];
    }

    $content = file_get_contents($path);
    $config = json_decode($content, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        log_error("Invalid JSON in config file: {$path}");
        return [];
    }

    return $config;
}

/**
 * Get form configuration for entity type
 */
function get_form_config(string $entity): ?array {
    static $config = null;

    if ($config === null) {
        $config = load_config('forms');
    }

    return $config['forms'][$entity] ?? null;
}

/**
 * Get custom fields for an entity
 */
function get_custom_fields(string $entity, int $projectId = null): array {
    $sql = "SELECT * FROM custom_field_definitions
            WHERE entity_type = :entity
            AND active = true
            AND (scope = 'global' OR (scope = 'project' AND project_id = :project_id))
            ORDER BY sort_order ASC";

    return db_query($sql, [
        'entity' => $entity,
        'project_id' => $projectId
    ]);
}

// ============================================================================
// FORM RENDERING
// ============================================================================

/**
 * Render a form field based on configuration
 */
function render_field(array $field, $value = null, array $errors = []): string {
    $name = $field['name'];
    $label = $field['label'];
    $type = $field['type'];
    $required = $field['required'] ?? false;
    $locked = $field['locked'] ?? false;
    $help = $field['help'] ?? '';

    $value = $value ?? ($field['default'] ?? '');
    $error = $errors[$name] ?? '';

    $requiredAttr = $required ? 'required' : '';
    $disabledAttr = $locked ? 'data-locked="true"' : '';
    $errorClass = $error ? 'error' : '';

    $html = '<div class="form-field ' . $errorClass . '">';
    $html .= '<label for="' . $name . '">';
    $html .= htmlspecialchars($label);
    if ($required) $html .= ' <span class="required">*</span>';
    if ($locked) $html .= ' <span class="locked" title="Kan ikke slettes">🔒</span>';
    $html .= '</label>';

    switch ($type) {
        case 'text':
        case 'email':
        case 'tel':
        case 'number':
        case 'date':
            $html .= '<input type="' . $type . '" ';
            $html .= 'id="' . $name . '" ';
            $html .= 'name="' . $name . '" ';
            $html .= 'value="' . htmlspecialchars($value) . '" ';
            $html .= $requiredAttr . ' ' . $disabledAttr;
            if (isset($field['maxlength'])) $html .= ' maxlength="' . $field['maxlength'] . '"';
            if (isset($field['min'])) $html .= ' min="' . $field['min'] . '"';
            if (isset($field['max'])) $html .= ' max="' . $field['max'] . '"';
            if (isset($field['step'])) $html .= ' step="' . $field['step'] . '"';
            if (isset($field['pattern'])) $html .= ' pattern="' . $field['pattern'] . '"';
            if (isset($field['placeholder'])) $html .= ' placeholder="' . htmlspecialchars($field['placeholder']) . '"';
            $html .= '>';
            break;

        case 'textarea':
            $rows = $field['rows'] ?? 5;
            $html .= '<textarea ';
            $html .= 'id="' . $name . '" ';
            $html .= 'name="' . $name . '" ';
            $html .= 'rows="' . $rows . '" ';
            $html .= $requiredAttr . ' ' . $disabledAttr;
            if (isset($field['maxlength'])) $html .= ' maxlength="' . $field['maxlength'] . '"';
            $html .= '>' . htmlspecialchars($value) . '</textarea>';
            break;

        case 'select':
            $html .= '<select ';
            $html .= 'id="' . $name . '" ';
            $html .= 'name="' . $name . '" ';
            $html .= $requiredAttr . ' ' . $disabledAttr;
            $html .= '>';
            $html .= '<option value="">Vælg...</option>';
            foreach ($field['options'] as $option) {
                $selected = ($value == $option['value']) ? 'selected' : '';
                $html .= '<option value="' . htmlspecialchars($option['value']) . '" ' . $selected . '>';
                $html .= htmlspecialchars($option['label']);
                $html .= '</option>';
            }
            $html .= '</select>';
            break;

        case 'checkbox':
            $checked = $value ? 'checked' : '';
            $html .= '<input type="checkbox" ';
            $html .= 'id="' . $name . '" ';
            $html .= 'name="' . $name . '" ';
            $html .= 'value="1" ';
            $html .= $checked . ' ' . $requiredAttr . ' ' . $disabledAttr;
            $html .= '>';
            break;
    }

    if ($help) {
        $html .= '<small class="help-text">' . htmlspecialchars($help) . '</small>';
    }

    if ($error) {
        $html .= '<span class="error-message">' . htmlspecialchars($error) . '</span>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Render entire form from configuration
 */
function render_form(string $entity, array $data = [], array $errors = []): string {
    $config = get_form_config($entity);
    if (!$config) return '';

    $html = '';
    foreach ($config['fields'] as $field) {
        $value = $data[$field['name']] ?? null;
        $html .= render_field($field, $value, $errors);
    }

    // Add custom fields
    $projectId = $data['project_id'] ?? null;
    $customFields = get_custom_fields($entity, $projectId);

    if ($customFields) {
        $html .= '<div class="custom-fields-section">';
        $html .= '<h3>Tilpassede Felter</h3>';

        foreach ($customFields as $customField) {
            $fieldConfig = [
                'name' => 'custom_' . $customField['id'],
                'label' => $customField['field_label'],
                'type' => $customField['field_type'],
                'required' => $customField['required'],
                'locked' => false
            ];

            // Add options for select fields
            if ($customField['options']) {
                $fieldConfig['options'] = json_decode($customField['options'], true);
            }

            $value = $data['custom_' . $customField['id']] ?? $customField['default_value'];
            $html .= render_field($fieldConfig, $value, $errors);
        }

        $html .= '</div>';
    }

    return $html;
}

// ============================================================================
// VALIDATION
// ============================================================================

/**
 * Validate form data against configuration
 */
function validate_form(string $entity, array $data): array {
    $config = get_form_config($entity);
    if (!$config) return ['_general' => 'Invalid entity type'];

    $errors = [];

    foreach ($config['fields'] as $field) {
        $name = $field['name'];
        $value = $data[$name] ?? null;

        // Required field check
        if ($field['required'] && empty($value)) {
            $errors[$name] = $field['label'] . ' er påkrævet';
            continue;
        }

        // Skip validation if empty and not required
        if (empty($value)) continue;

        // Type-specific validation
        switch ($field['type']) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$name] = 'Ugyldig email adresse';
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    $errors[$name] = 'Skal være et tal';
                } else {
                    if (isset($field['min']) && $value < $field['min']) {
                        $errors[$name] = 'Minimum værdi er ' . $field['min'];
                    }
                    if (isset($field['max']) && $value > $field['max']) {
                        $errors[$name] = 'Maksimum værdi er ' . $field['max'];
                    }
                }
                break;

            case 'text':
            case 'textarea':
                if (isset($field['maxlength']) && strlen($value) > $field['maxlength']) {
                    $errors[$name] = 'Maksimum længde er ' . $field['maxlength'] . ' tegn';
                }
                if (isset($field['validation']['min']) && strlen($value) < $field['validation']['min']) {
                    $errors[$name] = 'Minimum længde er ' . $field['validation']['min'] . ' tegn';
                }
                if (isset($field['validation']['pattern'])) {
                    if (!preg_match('/' . $field['validation']['pattern'] . '/', $value)) {
                        $errors[$name] = 'Ugyldig format';
                    }
                }
                break;
        }
    }

    return $errors;
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Sanitize input
 */
$sanitize = fn($input) => htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');

/**
 * Format money (Danish format)
 */
$format_money = fn($amount) => number_format($amount, 2, ',', '.') . ' kr.';

/**
 * Format date (Danish format)
 */
$format_date = fn($date) => date('d-m-Y', strtotime($date));

/**
 * Format datetime (Danish format)
 */
$format_datetime = fn($datetime) => date('d-m-Y H:i', strtotime($datetime));

/**
 * Generate unique ID
 */
$generate_id = fn() => bin2hex(random_bytes(16));

/**
 * Create directory if not exists
 */
function ensure_dir(string $path): bool {
    if (!is_dir($path)) {
        return mkdir($path, 0775, true);
    }
    return true;
}

/**
 * Get file extension
 */
$get_extension = fn($filename) => strtolower(pathinfo($filename, PATHINFO_EXTENSION));

/**
 * Check if file extension is allowed
 */
$is_allowed_file = fn($filename) => in_array($get_extension($filename), ALLOWED_EXTENSIONS);

/**
 * Get project directory path
 */
function get_project_dir(int $projectId): string {
    $path = PROJECTS_DIR . '/' . $projectId;
    ensure_dir($path);
    ensure_dir($path . '/uploads');
    ensure_dir($path . '/snapshots');
    return $path;
}

// ============================================================================
// LOGGING
// ============================================================================

/**
 * Log error to file
 */
function log_error(string $message, array $context = []): void {
    $logFile = LOGS_DIR . '/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = $context ? ' | ' . json_encode($context) : '';
    $line = "[{$timestamp}] ERROR: {$message}{$contextStr}\n";
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}

/**
 * Log activity
 */
function log_activity(string $action, string $entity, int $entityId, int $userId = null): void {
    db_insert('activity_logs', [
        'user_id' => $userId ?? ($_SESSION['user_id'] ?? null),
        'entity_type' => $entity,
        'entity_id' => $entityId,
        'action' => $action,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
    ]);
}

// ============================================================================
// RESPONSE HELPERS
// ============================================================================

/**
 * Send JSON response
 */
function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send success JSON response
 */
$json_success = fn($message, $data = []) => json_response([
    'success' => true,
    'message' => $message,
    'data' => $data
]);

/**
 * Send error JSON response
 */
$json_error = fn($message, $code = 400) => json_response([
    'success' => false,
    'error' => $message
], $code);

/**
 * Redirect to URL
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ============================================================================
// SESSION MANAGEMENT
// ============================================================================

/**
 * Start session if not already started
 */
function start_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    start_session();
    return isset($_SESSION['user_id']);
}

/**
 * Require login
 */
function require_login(): void {
    if (!is_logged_in()) {
        redirect('/?module=auth&action=login');
    }
}

/**
 * Get current user ID
 */
function current_user_id(): ?int {
    start_session();
    return $_SESSION['user_id'] ?? null;
}

/**
 * Get current user data
 */
function current_user(): ?array {
    $userId = current_user_id();
    if (!$userId) return null;

    return db_fetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);
}

// ============================================================================
// TEMPLATE LOADING
// ============================================================================

/**
 * Load and render a template
 */
function load_template(string $path, array $vars = []): void {
    extract($vars);
    include $path;
}

/**
 * Get template path for module
 */
function template_path(string $module, string $file = 'template'): string {
    return MODULES_DIR . '/' . $module . '/' . $file . '.tpl';
}

// ============================================================================
// INITIALIZATION
// ============================================================================

// Set timezone
date_default_timezone_set('Europe/Copenhagen');

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', LOGS_DIR . '/php_errors.log');

// Start session
start_session();
