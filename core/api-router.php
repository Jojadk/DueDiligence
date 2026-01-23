<?php
/**
 * Central API Router
 *
 * Routes API requests to appropriate module handlers
 * Provides consistent response format and error handling
 *
 * Usage: require_once __DIR__ . '/core/api-router.php';
 * Then call: route_api_request($module, $action, $user);
 */

require_once __DIR__ . '/api-helpers.php';
require_once __DIR__ . '/permissions.php';

/**
 * Route API request to module handler
 *
 * @param string $module Module name (e.g., 'project', 'building')
 * @param string $action Action name (e.g., 'get_list', 'create')
 * @param array $user Current user
 * @param bool $returnResult If true, return result instead of outputting JSON
 * @return array|void Result array if $returnResult is true
 */
function route_api_request(string $module, string $action, array $user, bool $returnResult = false) {
    // Validate module name (security)
    if (!preg_match('/^[a-z_]+$/', $module)) {
        $result = api_error('Invalid module name');
        if ($returnResult) return $result;
        output_json($result, 400);
    }

    // Validate action name (security)
    if (!preg_match('/^[a-z_]+$/', $action)) {
        $result = api_error('Invalid action name');
        if ($returnResult) return $result;
        output_json($result, 400);
    }

    // Check if module API exists
    $modulePath = MODULES_DIR . '/' . $module . '/api.php';
    if (!file_exists($modulePath)) {
        $result = api_error("Module not found: $module");
        if ($returnResult) return $result;
        output_json($result, 404);
    }

    // Load module API
    require_once $modulePath;

    // Build handler function name
    $handlerName = 'handle_' . $action;

    // Check if handler exists
    if (!function_exists($handlerName)) {
        $result = api_error("Action not found: $action");
        if ($returnResult) return $result;
        output_json($result, 404);
    }

    // Execute handler with error handling
    try {
        $result = $handlerName($user);

        // Ensure result is array
        if (!is_array($result)) {
            $result = ['success' => false, 'error' => 'Invalid handler response'];
        }

        // Add execution metadata in development
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $result['_meta'] = [
                'module' => $module,
                'action' => $action,
                'handler' => $handlerName,
                'execution_time' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms'
            ];
        }

        if ($returnResult) {
            return $result;
        }

        output_json($result);

    } catch (Exception $e) {
        // Log error
        if (function_exists('log_error')) {
            log_error("API Error [$module.$action]: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => $user['id'] ?? null
            ]);
        }

        $result = api_error('Internal server error');

        // Include error details in development
        if (defined('APP_ENV') && APP_ENV === 'development') {
            $result['_debug'] = [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString())
            ];
        }

        if ($returnResult) {
            return $result;
        }

        output_json($result, 500);
    }
}

/**
 * Output JSON response and exit
 *
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 */
function output_json(array $data, int $statusCode = 200): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Route API request from GET/POST parameters
 *
 * Convenience function for common routing pattern
 *
 * @param array $user Current user
 */
function route_api_from_request(array $user): void {
    $module = $_GET['module'] ?? $_POST['module'] ?? null;
    $action = $_GET['action'] ?? $_POST['action'] ?? null;

    if (!$module || !$action) {
        output_json(api_error('Missing module or action'), 400);
    }

    route_api_request($module, $action, $user);
}

/**
 * Get available API modules
 *
 * @return array List of available modules with their actions
 */
function get_available_api_modules(): array {
    $modules = [];
    $modulesDir = MODULES_DIR;

    if (!is_dir($modulesDir)) {
        return $modules;
    }

    $dirs = scandir($modulesDir);

    foreach ($dirs as $dir) {
        if ($dir === '.' || $dir === '..') continue;

        $apiFile = $modulesDir . '/' . $dir . '/api.php';

        if (file_exists($apiFile)) {
            // Parse API file to extract handlers
            $content = file_get_contents($apiFile);
            preg_match_all('/function handle_([a-z_]+)\s*\(/i', $content, $matches);

            if (!empty($matches[1])) {
                $modules[$dir] = [
                    'name' => $dir,
                    'api_file' => $apiFile,
                    'actions' => $matches[1]
                ];
            }
        }
    }

    return $modules;
}

/**
 * Validate API request parameters
 *
 * Wraps api_validate_params with automatic error response
 *
 * @param array $rules Validation rules
 * @param bool $returnResult If true, return result instead of outputting error
 * @return array|null Validated data or null if validation failed (and not returning result)
 */
function validate_api_params(array $rules, bool $returnResult = false): ?array {
    $validation = api_validate_params($rules);

    if (!$validation['success']) {
        if ($returnResult) {
            return null;
        }
        output_json(api_error($validation['errors']), 400);
    }

    return $validation['data'];
}

/**
 * Require CSRF token for API request
 *
 * @param bool $returnResult If true, return error instead of outputting
 * @return array|null Error array if validation failed (and returning result), null if valid
 */
function require_api_csrf(bool $returnResult = false): ?array {
    $csrfResult = api_require_csrf();

    if ($csrfResult !== null && !$csrfResult['success']) {
        if ($returnResult) {
            return $csrfResult;
        }
        output_json($csrfResult, 403);
    }

    return null;
}

/**
 * Require project access for API request
 *
 * @param array $user Current user
 * @param int $projectId Project ID
 * @param string $level Access level required
 * @param bool $returnResult If true, return error instead of outputting
 * @return array|null Error array if access denied (and returning result), null if access granted
 */
function require_api_project_access(array $user, int $projectId, string $level = 'viewer', bool $returnResult = false): ?array {
    $accessResult = api_require_project_access($user, $projectId, $level);

    if ($accessResult !== null && !$accessResult['success']) {
        if ($returnResult) {
            return $accessResult;
        }
        output_json($accessResult, 403);
    }

    return null;
}

/**
 * Execute API transaction
 *
 * Wraps api_transaction with automatic error response
 *
 * @param callable $operation Operation to execute
 * @param string $successMessage Success message
 * @param string $errorMessage Error message
 * @param bool $returnResult If true, return result instead of outputting
 * @return array Transaction result
 */
function execute_api_transaction(
    callable $operation,
    string $successMessage = 'Operation successful',
    string $errorMessage = 'Operation failed',
    bool $returnResult = false
): array {
    $result = api_transaction($operation, $successMessage, $errorMessage);

    if (!$returnResult && !$result['success']) {
        output_json($result, 500);
    }

    return $result;
}

/**
 * Get standardized API response metadata
 *
 * @param array $user Current user
 * @return array Metadata array
 */
function get_api_metadata(array $user): array {
    return [
        'timestamp' => date('Y-m-d H:i:s'),
        'user_id' => $user['id'] ?? null,
        'request_id' => uniqid('req_', true)
    ];
}
