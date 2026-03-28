<?php
/**
 * Red Flags Module
 * Displays critical items and issues requiring attention
 */

if (!defined('CORE_LOADED')) {
    die('Direct access not permitted');
}

// Get user permissions
$permissions = get_user_permissions($currentUser['id']);

// Get project ID if provided
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

// Get project info if project_id provided
$project = null;
if ($projectId) {
    $project = db_fetch(
        "SELECT * FROM projects WHERE id = :id",
        ['id' => $projectId]
    );

    // Check permissions
    if ($project) {
        if (!$permissions['admin'] && !user_owns_project($currentUser['id'], $projectId)) {
            header('Location: /index.php?module=dashboard');
            exit;
        }
    }
}

// Pass data to template
$templateData = [
    'permissions' => $permissions,
    'project' => $project,
    'currentUser' => $currentUser
];

// Load template
require __DIR__ . '/template.tpl';
