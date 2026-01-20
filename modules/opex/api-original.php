<?php
/**
 * OPEX Module API
 *
 * Handles OPEX (Operating Expenses) management
 * OPTIMIZED: Uses v_building_opex_summary view
 *
 * Available actions:
 * - get_categories: Get available OPEX categories
 * - get_category: Get specific category
 * - create_category: Create new category (admin)
 * - update_category: Update category (admin)
 * - toggle_category: Enable/disable category (admin)
 * - assign_to_building: Assign OPEX category to building
 * - get_assignment: Get OPEX assignment details
 * - update_assignment: Update OPEX assignment
 * - remove_assignment: Remove OPEX assignment
 * - calculate_tco: Calculate Total Cost of Ownership
 * - update_tco_config: Update TCO configuration (admin)
 */

/**
 * Get available OPEX categories
 * GET /api.php?module=opex&action=get_categories&building_id=123
 */
function handle_get_categories(array $user): array {
    $buildingId = sanitize_int($_GET['building_id'] ?? 0);

    if ($buildingId) {
        // Verify building access via project
        $building = db_fetch("
            SELECT b.project_id, p.user_id
            FROM buildings b
            JOIN projects p ON b.project_id = p.id
            WHERE b.id = :id
        ", ['id' => $buildingId]);

        if (!$building) {
            return ['success' => false, 'error' => 'Bygning ikke fundet'];
        }

        if (!can_access_project($user, $building['project_id'], 'viewer')) {
            return ['success' => false, 'error' => 'Ingen adgang'];
        }

        // Get categories not already assigned
        $categories = db_fetch_all("
            SELECT oc.*
            FROM opex_categories oc
            WHERE oc.is_active = true
            AND oc.id NOT IN (
                SELECT opex_category_id
                FROM building_opex
                WHERE building_id = :building_id
            )
            ORDER BY oc.category_type, oc.name
        ", ['building_id' => $buildingId]);
    } else {
        // Get all active categories
        $categories = db_fetch_all("
            SELECT *
            FROM opex_categories
            WHERE is_active = true
            ORDER BY category_type, name
        ");
    }

    return [
        'success' => true,
        'categories' => $categories
    ];
}

/**
 * Get specific OPEX category
 * GET /api.php?module=opex&action=get_category&id=123
 */
function handle_get_category(array $user): array {
    $categoryId = sanitize_int($_GET['id'] ?? 0);

    if (!$categoryId) {
        return ['success' => false, 'error' => 'Category ID mangler'];
    }

    $category = db_fetch("
        SELECT * FROM opex_categories WHERE id = :id
    ", ['id' => $categoryId]);

    if (!$category) {
        return ['success' => false, 'error' => 'Kategori ikke fundet'];
    }

    return [
        'success' => true,
        'category' => $category
    ];
}

/**
 * Create new OPEX category (admin only)
 * POST /api.php {module: 'opex', action: 'create_category', ...}
 */
function handle_create_category(array $user): array {
    csrf_require();

    // Admin only action - router already checked permission

    $name = sanitize_string($_POST['name'] ?? '');
    $description = sanitize_string($_POST['description'] ?? '');
    $categoryType = sanitize_string($_POST['category_type'] ?? '');
    $ratePerSqm = sanitize_float($_POST['rate_per_sqm'] ?? 0);

    if (empty($name) || empty($categoryType) || $ratePerSqm <= 0) {
        return ['success' => false, 'error' => 'Udfyld venligst alle påkrævede felter'];
    }

    try {
        $id = db_insert('opex_categories', [
            'name' => $name,
            'description' => $description,
            'category_type' => $categoryType,
            'rate_per_sqm' => $ratePerSqm,
            'is_active' => true
        ]);

        log_activity('opex_category_created', 'opex_category', $id);

        return [
            'success' => true,
            'message' => 'OPEX kategori oprettet',
            'id' => $id
        ];
    } catch (Exception $e) {
        log_error('OPEX category creation error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke oprette kategori'];
    }
}

/**
 * Update OPEX category (admin only)
 * POST /api.php {module: 'opex', action: 'update_category', id: 123, ...}
 */
function handle_update_category(array $user): array {
    csrf_require();

    $categoryId = sanitize_int($_POST['id'] ?? 0);

    if (!$categoryId) {
        return ['success' => false, 'error' => 'Category ID mangler'];
    }

    $updates = [];

    if (isset($_POST['name'])) {
        $updates['name'] = sanitize_string($_POST['name']);
    }
    if (isset($_POST['description'])) {
        $updates['description'] = sanitize_string($_POST['description']);
    }
    if (isset($_POST['category_type'])) {
        $updates['category_type'] = sanitize_string($_POST['category_type']);
    }
    if (isset($_POST['rate_per_sqm'])) {
        $updates['rate_per_sqm'] = sanitize_float($_POST['rate_per_sqm']);
    }

    if (empty($updates)) {
        return ['success' => false, 'error' => 'Ingen opdateringer'];
    }

    db_update('opex_categories', $updates, 'id = :id', ['id' => $categoryId]);

    log_activity('opex_category_updated', 'opex_category', $categoryId);

    return [
        'success' => true,
        'message' => 'Kategori opdateret'
    ];
}

/**
 * Toggle OPEX category active status (admin only)
 * POST /api.php {module: 'opex', action: 'toggle_category', id: 123, is_active: true/false}
 */
function handle_toggle_category(array $user): array {
    csrf_require();

    $categoryId = sanitize_int($_POST['id'] ?? 0);
    $isActive = filter_var($_POST['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);

    if (!$categoryId) {
        return ['success' => false, 'error' => 'Category ID mangler'];
    }

    db_update('opex_categories', [
        'is_active' => $isActive
    ], 'id = :id', ['id' => $categoryId]);

    log_activity('opex_category_toggled', 'opex_category', $categoryId);

    return [
        'success' => true,
        'message' => $isActive ? 'Kategori aktiveret' : 'Kategori deaktiveret'
    ];
}

/**
 * Assign OPEX category to building
 * POST /api.php {module: 'opex', action: 'assign_to_building', building_id: 123, category_id: 456, ...}
 */
function handle_assign_to_building(array $user): array {
    csrf_require();

    $buildingId = sanitize_int($_POST['building_id'] ?? 0);
    $categoryId = sanitize_int($_POST['category_id'] ?? 0);
    $customRate = !empty($_POST['custom_rate']) ? sanitize_float($_POST['custom_rate']) : null;
    $notes = sanitize_string($_POST['notes'] ?? '');

    if (!$buildingId || !$categoryId) {
        return ['success' => false, 'error' => 'Building ID og Category ID påkrævet'];
    }

    // Verify building access
    $building = db_fetch("
        SELECT b.project_id
        FROM buildings b
        WHERE b.id = :id
    ", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    if (!can_access_project($user, $building['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen redigerings adgang'];
    }

    // Verify category exists
    $category = db_fetch("SELECT * FROM opex_categories WHERE id = :id", ['id' => $categoryId]);
    if (!$category) {
        return ['success' => false, 'error' => 'Kategori ikke fundet'];
    }

    try {
        db_insert('building_opex', [
            'building_id' => $buildingId,
            'opex_category_id' => $categoryId,
            'custom_rate_per_sqm' => $customRate,
            'notes' => $notes
        ]);

        log_activity('opex_assigned', 'building', $buildingId);

        return [
            'success' => true,
            'message' => 'OPEX kategori tilføjet'
        ];
    } catch (Exception $e) {
        log_error('OPEX assignment error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke tilføje OPEX kategori'];
    }
}

/**
 * Get OPEX assignment details
 * GET /api.php?module=opex&action=get_assignment&id=123
 */
function handle_get_assignment(array $user): array {
    $assignmentId = sanitize_int($_GET['id'] ?? 0);

    if (!$assignmentId) {
        return ['success' => false, 'error' => 'Assignment ID mangler'];
    }

    $assignment = db_fetch("
        SELECT bo.*, oc.name, oc.rate_per_sqm as default_rate, oc.category_type,
               p.user_id
        FROM building_opex bo
        JOIN opex_categories oc ON bo.opex_category_id = oc.id
        JOIN buildings b ON bo.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE bo.id = :id
    ", ['id' => $assignmentId]);

    if (!$assignment) {
        return ['success' => false, 'error' => 'Tildeling ikke fundet'];
    }

    // Verify access
    if (!can_access_project($user, $assignment['user_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    return [
        'success' => true,
        'assignment' => $assignment
    ];
}

/**
 * Update OPEX assignment
 * POST /api.php {module: 'opex', action: 'update_assignment', id: 123, ...}
 */
function handle_update_assignment(array $user): array {
    csrf_require();

    $assignmentId = sanitize_int($_POST['id'] ?? 0);
    $customRate = !empty($_POST['custom_rate']) ? sanitize_float($_POST['custom_rate']) : null;
    $notes = sanitize_string($_POST['notes'] ?? '');

    if (!$assignmentId) {
        return ['success' => false, 'error' => 'Assignment ID mangler'];
    }

    // Verify ownership
    $assignment = db_fetch("
        SELECT bo.building_id, p.id as project_id
        FROM building_opex bo
        JOIN buildings b ON bo.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE bo.id = :id
    ", ['id' => $assignmentId]);

    if (!$assignment) {
        return ['success' => false, 'error' => 'Tildeling ikke fundet'];
    }

    if (!can_access_project($user, $assignment['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen redigerings adgang'];
    }

    db_update('building_opex', [
        'custom_rate_per_sqm' => $customRate,
        'notes' => $notes
    ], 'id = :id', ['id' => $assignmentId]);

    log_activity('opex_updated', 'building_opex', $assignmentId);

    return [
        'success' => true,
        'message' => 'OPEX opdateret'
    ];
}

/**
 * Remove OPEX assignment from building
 * POST /api.php {module: 'opex', action: 'remove_assignment', id: 123}
 */
function handle_remove_assignment(array $user): array {
    csrf_require();

    $assignmentId = sanitize_int($_POST['id'] ?? 0);

    if (!$assignmentId) {
        return ['success' => false, 'error' => 'Assignment ID mangler'];
    }

    // Verify ownership
    $assignment = db_fetch("
        SELECT bo.building_id, p.id as project_id
        FROM building_opex bo
        JOIN buildings b ON bo.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE bo.id = :id
    ", ['id' => $assignmentId]);

    if (!$assignment) {
        return ['success' => false, 'error' => 'Tildeling ikke fundet'];
    }

    if (!can_access_project($user, $assignment['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen redigerings adgang'];
    }

    db_delete('building_opex', 'id = :id', ['id' => $assignmentId]);

    log_activity('opex_removed', 'building_opex', $assignmentId);

    return [
        'success' => true,
        'message' => 'OPEX kategori fjernet'
    ];
}

/**
 * Calculate Total Cost of Ownership for building
 * OPTIMIZED: Uses v_building_opex_summary view
 *
 * GET /api.php?module=opex&action=calculate_tco&building_id=123
 */
function handle_calculate_tco(array $user): array {
    $buildingId = sanitize_int($_GET['building_id'] ?? 0);

    if (!$buildingId) {
        return ['success' => false, 'error' => 'Building ID mangler'];
    }

    // Get building and verify access
    $building = db_fetch("
        SELECT b.*, p.id as project_id
        FROM buildings b
        JOIN projects p ON b.project_id = p.id
        WHERE b.id = :id
    ", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    if (!can_access_project($user, $building['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    // Get TCO configuration
    $tcoConfig = db_fetch_all("SELECT config_key, config_value FROM tco_config");
    $config = [];
    foreach ($tcoConfig as $item) {
        $config[$item['config_key']] = (float)$item['config_value'];
    }

    $lifecycleYears = $config['lifecycle_years'] ?? 30;
    $discountRate = $config['discount_rate'] ?? 0.03;
    $inflationRate = $config['inflation_rate'] ?? 0.02;
    $capexContingency = $config['capex_contingency'] ?? 0.10;
    $opexEscalation = $config['opex_escalation'] ?? 0.025;

    // Calculate CAPEX
    $capex = db_value("
        SELECT COALESCE(SUM(capex), 0)
        FROM building_elements
        WHERE building_id = :building_id
    ", ['building_id' => $buildingId]);

    $capexWithContingency = $capex * (1 + $capexContingency);

    // OPTIMIZED: Get OPEX from view
    $opexSummary = db_fetch("
        SELECT effective_opex_yearly
        FROM v_building_opex_summary
        WHERE building_id = :building_id
    ", ['building_id' => $buildingId]);

    $opexPerYear = (float)($opexSummary['effective_opex_yearly'] ?? 0);

    // Calculate NPV of OPEX
    $opexNpv = 0;
    for ($year = 1; $year <= $lifecycleYears; $year++) {
        $yearOpex = $opexPerYear * pow(1 + $opexEscalation, $year - 1);
        $discountFactor = pow(1 + $discountRate, $year);
        $opexNpv += $yearOpex / $discountFactor;
    }

    // Total Cost of Ownership
    $tco = $capexWithContingency + $opexNpv;

    return [
        'success' => true,
        'tco' => [
            'capex' => $capex,
            'capex_with_contingency' => $capexWithContingency,
            'opex_per_year' => $opexPerYear,
            'opex_npv' => $opexNpv,
            'tco' => $tco,
            'lifecycle_years' => $lifecycleYears,
            'discount_rate' => $discountRate,
            'inflation_rate' => $inflationRate,
            'capex_contingency' => $capexContingency,
            'opex_escalation' => $opexEscalation
        ]
    ];
}

/**
 * Update TCO configuration (admin only)
 * POST /api.php {module: 'opex', action: 'update_tco_config', config: {...}}
 */
function handle_update_tco_config(array $user): array {
    csrf_require();

    // Admin only - router already checked permission

    $configs = $_POST['config'] ?? [];

    if (empty($configs)) {
        return ['success' => false, 'error' => 'Ingen konfiguration angivet'];
    }

    try {
        foreach ($configs as $key => $value) {
            db_update('tco_config', [
                'config_value' => sanitize_float($value)
            ], 'config_key = :key', ['key' => sanitize_string($key)]);
        }

        log_activity('tco_config_updated', 'tco_config', 0);

        return [
            'success' => true,
            'message' => 'TCO konfiguration opdateret'
        ];
    } catch (Exception $e) {
        log_error('TCO config update error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke opdatere konfiguration'];
    }
}
