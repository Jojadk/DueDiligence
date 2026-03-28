<?php
/**
 * DueDiligence v2.0
 * Entry Point - SPA Architecture with On-Demand Loading
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

// Detect AJAX requests
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Get requested module and action
$module = $_GET['module'] ?? 'dashboard';
$action = $_GET['action'] ?? 'index';

// Sanitize module and action names
$module = preg_replace('/[^a-zA-Z0-9_]/', '', $module);
$action = preg_replace('/[^a-zA-Z0-9_]/', '', $action);

// Special handling for auth module (login/logout)
if ($module === 'auth') {
    $modulePath = MODULES_DIR . '/auth/index.php';
    if (file_exists($modulePath)) {
        require_once $modulePath;
        exit;
    }
}

// Require login for all other modules
if (!is_logged_in()) {
    if ($isAjax) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized',
            'redirect' => '/?module=auth&action=login'
        ]);
        exit;
    }
    redirect('/?module=auth&action=login');
}

// Get current user
$currentUser = current_user();
$permissions = get_user_permissions($currentUser['id'] ?? 0);

// AJAX Request Handling - Load module content only
if ($isAjax) {
    // Check if module file exists
    $modulePath = MODULES_DIR . '/' . $module . '/index.php';

    if (!file_exists($modulePath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Module not found: ' . htmlspecialchars($module)
        ]);
        exit;
    }

    // Set flag to indicate we want partial content
    define('AJAX_REQUEST', true);

    // Capture output from module
    ob_start();
    require_once $modulePath;
    $moduleOutput = ob_get_clean();

    // If module already sent JSON response, we're done
    if (headers_sent()) {
        echo $moduleOutput;
        exit;
    }

    // Otherwise, return HTML content
    header('Content-Type: text/html; charset=UTF-8');
    echo $moduleOutput;
    exit;
}

// Full Page Request - Load main template
// The main template will handle initial page load, then JavaScript takes over

// Page title based on module
$pageTitles = [
    'dashboard' => 'Dashboard - DueDiligence',
    'customer' => 'Kunder - DueDiligence',
    'project' => 'Projekter - DueDiligence',
    'building' => 'Bygninger - DueDiligence',
    'building_element' => 'Bygningsdele - DueDiligence',
    'price_catalog' => 'Priskatalog - DueDiligence',
    'reports' => 'Rapporter - DueDiligence',
    'users' => 'Brugere - DueDiligence',
    'settings' => 'Indstillinger - DueDiligence'
];

$pageTitle = $pageTitles[$module] ?? 'DueDiligence v2.0';

// Load main template
load_template(__DIR__ . '/templates/main', [
    'pageTitle' => $pageTitle,
    'currentUser' => $currentUser,
    'permissions' => $permissions,
    'initialModule' => $module,
    'initialAction' => $action
]);
