<?php
/**
 * OPEX Module
 * Manages operational expenses with experience values based on square meters
 */

if (!defined('CORE_LOADED')) {
    die('Direct access not permitted');
}

// Get user permissions
$permissions = get_user_permissions($currentUser['id']);

// Get building ID if provided (for building-specific OPEX view)
$buildingId = isset($_GET['building_id']) ? (int)$_GET['building_id'] : null;

// Get project info for building if building_id provided
$building = null;
$project = null;
if ($buildingId) {
    $building = db_fetch(
        "SELECT b.*, p.id as project_id, p.name as project_name, p.user_id as project_owner
         FROM buildings b
         JOIN projects p ON b.project_id = p.id
         WHERE b.id = :id",
        ['id' => $buildingId]
    );

    // Check permissions
    if ($building) {
        if (!$permissions['admin'] && !user_owns_project($currentUser['id'], $building['project_id'])) {
            header('Location: /index.php?module=dashboard');
            exit;
        }

        $project = [
            'id' => $building['project_id'],
            'name' => $building['project_name'],
            'user_id' => $building['project_owner']
        ];
    }
}

// Get all active OPEX categories grouped by type
$opexCategories = db_fetch_all(
    "SELECT * FROM opex_categories
     WHERE is_active = true
     ORDER BY category_type, name"
);

// Group categories by type
$categoriesByType = [];
foreach ($opexCategories as $cat) {
    $type = $cat['category_type'];
    if (!isset($categoriesByType[$type])) {
        $categoriesByType[$type] = [];
    }
    $categoriesByType[$type][] = $cat;
}

// Get TCO configuration
$tcoConfig = db_fetch_all("SELECT * FROM tco_config ORDER BY config_key");
$tcoConfigMap = [];
foreach ($tcoConfig as $config) {
    $tcoConfigMap[$config['config_key']] = $config;
}

// If building ID provided, get assigned OPEX categories
$assignedOpex = [];
if ($buildingId) {
    $assignedOpex = db_fetch_all(
        "SELECT bo.*, oc.name, oc.rate_per_sqm as default_rate, oc.category_type,
                COALESCE(bo.custom_rate_per_sqm, oc.rate_per_sqm) as effective_rate
         FROM building_opex bo
         JOIN opex_categories oc ON bo.opex_category_id = oc.id
         WHERE bo.building_id = :building_id
         ORDER BY oc.category_type, oc.name",
        ['building_id' => $buildingId]
    );

    // Calculate total OPEX per year
    $totalOpexPerYear = 0;
    $buildingArea = (float)($building['area'] ?? 0);

    foreach ($assignedOpex as $opex) {
        $rate = (float)$opex['effective_rate'];
        $totalOpexPerYear += $rate * $buildingArea;
    }
}

// Category type labels (Danish)
$categoryTypeLabels = [
    'maintenance' => 'Vedligeholdelse',
    'energy' => 'Energi',
    'utilities' => 'Forsyning',
    'insurance' => 'Forsikring',
    'tax' => 'Skatter og afgifter',
    'security' => 'Sikkerhed',
    'waste' => 'Affald',
    'admin' => 'Administration'
];

// Pass data to template
$templateData = [
    'permissions' => $permissions,
    'building' => $building,
    'project' => $project,
    'opexCategories' => $opexCategories,
    'categoriesByType' => $categoriesByType,
    'categoryTypeLabels' => $categoryTypeLabels,
    'assignedOpex' => $assignedOpex,
    'totalOpexPerYear' => $totalOpexPerYear ?? 0,
    'tcoConfig' => $tcoConfigMap,
    'currentUser' => $currentUser
];

// Load template
require __DIR__ . '/template.tpl';
