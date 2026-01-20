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

require_once __DIR__ . '/../../core/api-helpers.php';

/**
 * Get available OPEX categories
 * GET /api.php?module=opex&action=get_categories&building_id=123
 */
function handle_get_categories(array $user): array {
    $validation = api_validate_params([
        'building_id' => ['int', 'GET', false, 0]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $buildingId = $validation['data']['building_id'];

    if ($buildingId) {
        // Verify building access via project
        $building = db_fetch("
            SELECT b.project_id, p.user_id
            FROM buildings b
            JOIN projects p ON b.project_id = p.id
            WHERE b.id = :id
        ", ['id' => $buildingId]);

        if (!$building) {
            return api_error('Bygning ikke fundet');
        }

        if (!can_access_project($user, $building['project_id'], 'viewer')) {
            return api_error('Ingen adgang');
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
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $categoryId = $validation['data']['id'];

    $category = db_fetch("
        SELECT * FROM opex_categories WHERE id = :id
    ", ['id' => $categoryId]);

    if (!$category) {
        return api_error('Kategori ikke fundet');
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
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    // Admin only action - router already checked permission

    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'description' => ['string', 'POST', false, ''],
        'category_type' => ['string', 'POST', true],
        'rate_per_sqm' => ['float', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    if ($params['rate_per_sqm'] <= 0) {
        return api_error('Rate per m² skal være større end 0');
    }

    return api_crud_create(
        'opex_categories',
        [
            'name' => $params['name'],
            'description' => $params['description'],
            'category_type' => $params['category_type'],
            'rate_per_sqm' => $params['rate_per_sqm'],
            'is_active' => true
        ],
        null,
        function($id) {
            log_activity('opex_category_created', 'opex_category', $id);
        }
    );
}

/**
 * Update OPEX category (admin only)
 * POST /api.php {module: 'opex', action: 'update_category', id: 123, ...}
 */
function handle_update_category(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'name' => ['string', 'POST', false],
        'description' => ['string', 'POST', false],
        'category_type' => ['string', 'POST', false],
        'rate_per_sqm' => ['float', 'POST', false]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $categoryId = $params['id'];

    $updates = [];
    if (isset($params['name'])) {
        $updates['name'] = $params['name'];
    }
    if (isset($params['description'])) {
        $updates['description'] = $params['description'];
    }
    if (isset($params['category_type'])) {
        $updates['category_type'] = $params['category_type'];
    }
    if (isset($params['rate_per_sqm'])) {
        $updates['rate_per_sqm'] = $params['rate_per_sqm'];
    }

    if (empty($updates)) {
        return api_error('Ingen opdateringer');
    }

    return api_crud_update(
        'opex_categories',
        $categoryId,
        $updates,
        null,
        function($id) {
            log_activity('opex_category_updated', 'opex_category', $id);
        }
    );
}

/**
 * Toggle OPEX category active status (admin only)
 * POST /api.php {module: 'opex', action: 'toggle_category', id: 123, is_active: true/false}
 */
function handle_toggle_category(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'is_active' => ['bool', 'POST', false, true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $categoryId = $params['id'];
    $isActive = $params['is_active'];

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
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'building_id' => ['int', 'POST', true],
        'category_id' => ['int', 'POST', true],
        'custom_rate' => ['float', 'POST', false, null],
        'notes' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // Verify building access
    $building = db_fetch("
        SELECT b.project_id
        FROM buildings b
        WHERE b.id = :id
    ", ['id' => $params['building_id']]);

    if (!$building) {
        return api_error('Bygning ikke fundet');
    }

    if (!can_access_project($user, $building['project_id'], 'editor')) {
        return api_error('Ingen redigerings adgang');
    }

    return api_crud_create(
        'building_opex',
        [
            'building_id' => $params['building_id'],
            'opex_category_id' => $params['category_id'],
            'custom_rate_per_sqm' => $params['custom_rate'],
            'notes' => $params['notes']
        ],
        function($data) {
            // Verify category exists
            $category = db_fetch("SELECT * FROM opex_categories WHERE id = :id", ['id' => $data['opex_category_id']]);
            if (!$category) {
                throw new Exception('Kategori ikke fundet');
            }
        },
        function($id) use ($params) {
            log_activity('opex_assigned', 'building', $params['building_id']);
        }
    );
}

/**
 * Get OPEX assignment details
 * GET /api.php?module=opex&action=get_assignment&id=123
 */
function handle_get_assignment(array $user): array {
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $assignmentId = $validation['data']['id'];

    return api_get_entity(
        'building_opex',
        $assignmentId,
        function($assignment) use ($user) {
            // Verify access
            if (!can_access_project($user, $assignment['user_id'], 'viewer')) {
                throw new Exception('Ingen adgang');
            }

            return $assignment;
        },
        "SELECT bo.*, oc.name, oc.rate_per_sqm as default_rate, oc.category_type,
               p.user_id
        FROM building_opex bo
        JOIN opex_categories oc ON bo.opex_category_id = oc.id
        JOIN buildings b ON bo.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE bo.id = :id"
    );
}

/**
 * Update OPEX assignment
 * POST /api.php {module: 'opex', action: 'update_assignment', id: 123, ...}
 */
function handle_update_assignment(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'custom_rate' => ['float', 'POST', false, null],
        'notes' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $assignmentId = $params['id'];

    return api_crud_update(
        'building_opex',
        $assignmentId,
        [
            'custom_rate_per_sqm' => $params['custom_rate'],
            'notes' => $params['notes']
        ],
        function($assignment) use ($user) {
            // Verify ownership
            $data = db_fetch("
                SELECT bo.building_id, p.id as project_id
                FROM building_opex bo
                JOIN buildings b ON bo.building_id = b.id
                JOIN projects p ON b.project_id = p.id
                WHERE bo.id = :id
            ", ['id' => $assignment['id']]);

            if (!$data) {
                throw new Exception('Tildeling ikke fundet');
            }

            if (!can_access_project($user, $data['project_id'], 'editor')) {
                throw new Exception('Ingen redigerings adgang');
            }
        },
        function($id) {
            log_activity('opex_updated', 'building_opex', $id);
        }
    );
}

/**
 * Remove OPEX assignment from building
 * POST /api.php {module: 'opex', action: 'remove_assignment', id: 123}
 */
function handle_remove_assignment(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $assignmentId = $validation['data']['id'];

    return api_crud_delete(
        'building_opex',
        $assignmentId,
        function($assignment) use ($user) {
            // Verify ownership
            $data = db_fetch("
                SELECT bo.building_id, p.id as project_id
                FROM building_opex bo
                JOIN buildings b ON bo.building_id = b.id
                JOIN projects p ON b.project_id = p.id
                WHERE bo.id = :id
            ", ['id' => $assignment['id']]);

            if (!$data) {
                throw new Exception('Tildeling ikke fundet');
            }

            if (!can_access_project($user, $data['project_id'], 'editor')) {
                throw new Exception('Ingen redigerings adgang');
            }
        },
        function($id) {
            log_activity('opex_removed', 'building_opex', $id);
        }
    );
}

/**
 * Calculate Total Cost of Ownership for building
 * OPTIMIZED: Uses v_building_opex_summary view
 *
 * GET /api.php?module=opex&action=calculate_tco&building_id=123
 */
function handle_calculate_tco(array $user): array {
    $validation = api_validate_params([
        'building_id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $buildingId = $validation['data']['building_id'];

    // Get building and verify access
    $building = db_fetch("
        SELECT b.*, p.id as project_id
        FROM buildings b
        JOIN projects p ON b.project_id = p.id
        WHERE b.id = :id
    ", ['id' => $buildingId]);

    if (!$building) {
        return api_error('Bygning ikke fundet');
    }

    if (!can_access_project($user, $building['project_id'], 'viewer')) {
        return api_error('Ingen adgang');
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
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    // Admin only - router already checked permission

    $configs = $_POST['config'] ?? [];

    if (empty($configs) || !is_array($configs)) {
        return api_error('Ingen konfiguration angivet');
    }

    return api_transaction(
        function() use ($configs) {
            foreach ($configs as $key => $value) {
                db_update('tco_config', [
                    'config_value' => sanitize_float($value)
                ], 'config_key = :key', ['key' => sanitize_string($key)]);
            }

            log_activity('tco_config_updated', 'tco_config', 0);
        },
        'TCO konfiguration opdateret',
        'Kunne ikke opdatere konfiguration'
    );
}
