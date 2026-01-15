<?php
/**
 * DueDiligence v2.0
 * Entry Point
 */

// Load core system
require_once __DIR__ . '/core/core.php';
require_once __DIR__ . '/core/security.php';
require_once __DIR__ . '/core/icons.php';

// Set security headers
set_security_headers();

// Check session timeout for logged in users
if (is_logged_in()) {
    check_session_timeout();
}

// Get requested module and action
$module = $_GET['module'] ?? 'project';
$action = $_GET['action'] ?? 'index';

// Sanitize module and action names
$module = preg_replace('/[^a-zA-Z0-9]/', '', $module);
$action = preg_replace('/[^a-zA-Z0-9]/', '', $action);

// Check if module file exists
$modulePath = MODULES_DIR . '/' . $module . '/index.php';

if (!file_exists($modulePath)) {
    http_response_code(404);
    die('Module not found: ' . htmlspecialchars($module));
}

// Require login for all modules except auth
if ($module !== 'auth' && !is_logged_in()) {
    redirect('/?module=auth&action=login');
}

// Load the module
require_once $modulePath;
