<?php
/**
 * Modular API Router - On-demand module loading
 *
 * Routes API requests to module-specific handlers
 * Structure: /modules/{module}/api.php
 *
 * Request format:
 * - api.php?module=project&action=get_list
 * - api.php?module=dashboard&action=get_stats
 * - api.php?module=opex&action=calculate_tco&building_id=123
 */

require_once __DIR__ . '/core/core.php';
require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/core/permissions.php';

header('Content-Type: application/json');
set_security_headers();

// Require login for ALL API calls
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized',
        'redirect' => '/?module=auth&action=login'
    ]);
    exit;
}

$currentUser = current_user();

// Parse request
$module = sanitize_string($_GET['module'] ?? $_POST['module'] ?? 'dashboard');
$action = sanitize_string($_GET['action'] ?? $_POST['action'] ?? 'index');

// Validate module name (security: prevent directory traversal)
if (!preg_match('/^[a-z_]+$/', $module)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid module name']);
    exit;
}

// Validate action name (security: prevent arbitrary function calls)
if (!preg_match('/^[a-z_]+$/', $action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid action name']);
    exit;
}

// Check if module exists and is active
$moduleInfo = db_fetch("
    SELECT id, module_key, display_name, is_active
    FROM permission_modules
    WHERE module_key = :module_key
", ['module_key' => $module]);

if (!$moduleInfo) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Module not found']);
    exit;
}

if (!$moduleInfo['is_active']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Module is disabled']);
    exit;
}

// Module API file path
$modulePath = __DIR__ . "/modules/{$module}/api.php";

if (!file_exists($modulePath)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => "Module '{$module}' has no API handler",
        'details' => "Expected file: {$modulePath}"
    ]);
    exit;
}

// Check module permissions BEFORE loading module
// Most actions require at least 'view' permission
$requiredPermission = get_required_permission_for_action($action);

if (!has_module_permission($currentUser, $module, $requiredPermission)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'No permission for this module',
        'required_permission' => "{$module}.{$requiredPermission}"
    ]);

    // Log unauthorized access attempt
    log_activity('unauthorized_access_attempt', 'module', $moduleInfo['id'], [
        'module' => $module,
        'action' => $action,
        'user_id' => $currentUser['id']
    ]);

    exit;
}

// Load module API
try {
    require_once $modulePath;

    // Build function name: handle_{action}
    $functionName = "handle_{$action}";

    if (!function_exists($functionName)) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => "Action '{$action}' not found in module '{$module}'",
            'available_actions' => get_module_actions($modulePath)
        ]);
        exit;
    }

    // Call the module handler
    $result = $functionName($currentUser);

    // Ensure result is an array
    if (!is_array($result)) {
        $result = ['success' => false, 'error' => 'Invalid response from module'];
    }

    // Add metadata
    $result['_meta'] = [
        'module' => $module,
        'action' => $action,
        'timestamp' => time(),
        'user_id' => $currentUser['id']
    ];

    echo json_encode($result);

} catch (Exception $e) {
    log_error("API error in {$module}.{$action}: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'module' => $module,
        'action' => $action,
        'details' => (DEVELOPMENT_MODE ?? false) ? $e->getMessage() : null
    ]);
}

/**
 * Determine required permission based on action name
 *
 * @param string $action Action name
 * @return string Permission key (view, create, edit, delete, export)
 */
function get_required_permission_for_action(string $action): string {
    // Map action patterns to permissions
    if (preg_match('/^(get|list|view|show|fetch|search|calculate)/', $action)) {
        return 'view';
    }

    if (preg_match('/^(create|add|new|insert)/', $action)) {
        return 'create';
    }

    if (preg_match('/^(update|edit|modify|save|set)/', $action)) {
        return 'edit';
    }

    if (preg_match('/^(delete|remove|destroy)/', $action)) {
        return 'delete';
    }

    if (preg_match('/^(export|download|generate_report)/', $action)) {
        return 'export';
    }

    // Default: require view permission
    return 'view';
}

/**
 * Get available actions from module API file
 *
 * @param string $filePath Path to module API file
 * @return array List of action names
 */
function get_module_actions(string $filePath): array {
    $content = file_get_contents($filePath);
    preg_match_all('/function handle_([a-z_]+)\s*\(/i', $content, $matches);
    return $matches[1] ?? [];
}
