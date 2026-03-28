<?php
/**
 * Report Generator Module
 * Generates comprehensive project reports with CAPEX/OPEX/TCO summaries
 */

if (!defined('CORE_LOADED')) {
    die('Direct access not permitted');
}

// Get user permissions
$permissions = get_user_permissions($currentUser['id']);

// Get project ID (required for reports)
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

if (!$projectId) {
    header('Location: /index.php?module=project');
    exit;
}

// Get project and verify access
$project = db_fetch("SELECT * FROM projects WHERE id = :id", ['id' => $projectId]);

if (!$project) {
    header('Location: /index.php?module=project');
    exit;
}

// Check permissions
if (!$permissions['admin'] && !user_owns_project($currentUser['id'], $projectId)) {
    header('Location: /index.php?module=dashboard');
    exit;
}

// Get all buildings for this project
$buildings = db_fetch_all(
    "SELECT * FROM buildings WHERE project_id = :project_id ORDER BY name",
    ['project_id' => $projectId]
);

// Get TCO configuration
$tcoConfig = db_fetch_all("SELECT config_key, config_value FROM tco_config");
$tcoConfigMap = [];
foreach ($tcoConfig as $config) {
    $tcoConfigMap[$config['config_key']] = (float)$config['config_value'];
}

// Pass data to template
$templateData = [
    'permissions' => $permissions,
    'project' => $project,
    'buildings' => $buildings,
    'tcoConfig' => $tcoConfigMap,
    'currentUser' => $currentUser
];

// Load template
require __DIR__ . '/template.tpl';
