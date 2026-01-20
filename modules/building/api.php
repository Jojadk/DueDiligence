<?php
/**
 * Building Module API - Refactored with API Helpers
 *
 * Handles building CRUD operations and element retrieval
 *
 * Actions:
 * - get_list: Get buildings for a project
 * - get_details: Get building details with summary stats
 * - create: Create new building
 * - update: Update building
 * - delete: Delete building
 * - get_elements: Get elements for building with hierarchy
 * - update_order: Reorder buildings
 */

require_once __DIR__ . '/../../core/permissions.php';
require_once __DIR__ . '/../../core/api-helpers.php';

/**
 * Get list of buildings for a project
 * GET ?module=building&action=get_list&project_id=X
 */
function handle_get_list(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'project_id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $projectId = $validation['data']['project_id'];

    // Check project access
    $accessCheck = api_require_project_access($user, $projectId, 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Get buildings with pre-calculated stats from view
    $buildings = db_fetch_all("
        SELECT
            bs.building_id,
            bs.building_name,
            bs.building_number,
            bs.building_type,
            bs.gross_area,
            bs.element_count,
            bs.total_capex,
            bs.avg_condition,
            bs.critical_count,
            bs.high_count,
            bs.display_order,
            bs.created_at,
            bs.updated_at
        FROM v_building_summary bs
        WHERE bs.project_id = :project_id
        ORDER BY bs.display_order ASC, bs.building_number ASC
    ", ['project_id' => $projectId]);

    return [
        'success' => true,
        'buildings' => $buildings
    ];
}

/**
 * Get detailed building information
 * GET ?module=building&action=get_details&id=X
 */
function handle_get_details(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $buildingId = $validation['data']['id'];

    // Get building with permission check
    $buildingResult = api_get_entity(
        'buildings',
        $buildingId,
        function($building) use ($user) {
            return can_access_project($user, $building['project_id'], 'viewer');
        },
        'Bygning ikke fundet',
        'Ingen adgang til bygningen'
    );

    if (!$buildingResult['success']) {
        return $buildingResult;
    }

    $building = $buildingResult['entity'];

    // Get additional summary stats from view
    $summary = db_fetch("
        SELECT element_count, total_capex, avg_condition,
               critical_count, high_count
        FROM v_building_summary
        WHERE building_id = :id
    ", ['id' => $buildingId]);

    if ($summary) {
        $building = array_merge($building, $summary);
    }

    // Get OPEX summary if available
    $opexSummary = db_fetch("
        SELECT
            total_opex_yearly,
            effective_opex_yearly,
            active_category_count,
            opex_per_sqm
        FROM v_building_opex_summary
        WHERE building_id = :building_id
    ", ['building_id' => $buildingId]);

    if ($opexSummary) {
        $building['opex'] = $opexSummary;
    }

    return [
        'success' => true,
        'building' => $building
    ];
}

/**
 * Create new building
 * POST ?module=building&action=create
 */
function handle_create(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate all parameters
    $validation = api_validate_params([
        'project_id' => ['int', 'POST', true],
        'name' => ['string', 'POST', true],
        'building_number' => ['string', 'POST', false, ''],
        'building_type' => ['string', 'POST', false, ''],
        'gross_area' => ['float', 'POST', false, 0],
        'floors' => ['int', 'POST', false, 1],
        'year_built' => ['int', 'POST', false, null],
        'address' => ['string', 'POST', false, ''],
        'description' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $data = $validation['data'];
    $projectId = $data['project_id'];

    // Check project access (editor required for creation)
    $accessCheck = api_require_project_access($user, $projectId, 'editor');
    if (!$accessCheck['success']) {
        return api_error('Ingen adgang til at oprette bygninger i dette projekt');
    }

    // Use api_crud_create with custom logic for display_order
    return api_crud_create(
        'buildings',
        $data,
        function(&$buildingData) use ($projectId) {
            // Get next display order
            $maxOrder = db_value("
                SELECT COALESCE(MAX(display_order), 0)
                FROM buildings
                WHERE project_id = :project_id
            ", ['project_id' => $projectId]);

            $buildingData['display_order'] = $maxOrder + 1;
            $buildingData['created_at'] = date('Y-m-d H:i:s');
            $buildingData['updated_at'] = date('Y-m-d H:i:s');
        },
        function($buildingId) {
            log_activity('building_created', 'building', $buildingId);
        },
        'Bygning oprettet succesfuldt',
        'Kunne ikke oprette bygning'
    );
}

/**
 * Update building
 * POST ?module=building&action=update
 */
function handle_update(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate ID
    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $buildingId = $validation['data']['id'];

    // Validate update fields (all optional)
    $updateValidation = api_validate_params([
        'name' => ['string', 'POST', false],
        'building_number' => ['string', 'POST', false],
        'building_type' => ['string', 'POST', false],
        'gross_area' => ['float', 'POST', false],
        'floors' => ['int', 'POST', false],
        'year_built' => ['int', 'POST', false],
        'address' => ['string', 'POST', false],
        'description' => ['string', 'POST', false]
    ]);

    if (!$updateValidation['success']) {
        return api_error($updateValidation['errors']);
    }

    // Filter out null values (only update provided fields)
    $updateData = array_filter(
        $updateValidation['data'],
        fn($value) => $value !== null
    );

    if (empty($updateData)) {
        return api_error('Ingen felter at opdatere');
    }

    // Use api_crud_update
    return api_crud_update(
        'buildings',
        $buildingId,
        $updateData,
        function($building) use ($user) {
            return can_access_project($user, $building['project_id'], 'editor');
        },
        function($buildingId) {
            log_activity('building_updated', 'building', $buildingId);
        },
        'Bygning opdateret succesfuldt',
        'Kunne ikke opdatere bygning',
        'Bygning ikke fundet',
        'Ingen adgang til at redigere denne bygning'
    );
}

/**
 * Delete building
 * POST ?module=building&action=delete
 */
function handle_delete(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate ID
    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $buildingId = $validation['data']['id'];

    // Use api_crud_delete with custom validation
    return api_crud_delete(
        'buildings',
        $buildingId,
        function($building) use ($user) {
            return can_access_project($user, $building['project_id'], 'editor');
        },
        function($buildingId) {
            // Check if building has elements
            $elementCount = db_value("
                SELECT COUNT(*) FROM building_elements WHERE building_id = :id
            ", ['id' => $buildingId]);

            if ($elementCount > 0) {
                throw new Exception('Kan ikke slette bygning med elementer. Slet elementerne først.');
            }

            // Delete related OPEX assignments
            db_execute("DELETE FROM building_opex WHERE building_id = :id", ['id' => $buildingId]);
        },
        function($buildingId) {
            log_activity('building_deleted', 'building', $buildingId);
        },
        'Bygning slettet succesfuldt',
        'Kunne ikke slette bygning',
        'Bygning ikke fundet',
        'Ingen adgang til at slette denne bygning'
    );
}

/**
 * Get elements for building with hierarchy
 * GET ?module=building&action=get_elements&id=X
 * OPTIMIZED: Uses recursive CTE function - 1 query instead of 10+
 */
function handle_get_elements(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $buildingId = $validation['data']['id'];

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return api_error('Bygning ikke fundet');
    }

    // Check project access
    $accessCheck = api_require_project_access($user, $building['project_id'], 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // OPTIMIZED: Use recursive CTE function - single query with full hierarchy and stats
    $elements = db_fetch_all("
        SELECT * FROM get_element_hierarchy(:building_id)
    ", ['building_id' => $buildingId]);

    return [
        'success' => true,
        'elements' => $elements
    ];
}

/**
 * Update building display order
 * POST ?module=building&action=update_order
 */
function handle_update_order(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'project_id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $projectId = $validation['data']['project_id'];
    $buildingIds = $_POST['building_ids'] ?? [];

    if (!is_array($buildingIds) || empty($buildingIds)) {
        return api_error('Bygnings ID liste er påkrævet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $projectId, 'editor');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Use api_transaction for the update operation
    return api_transaction(
        function() use ($buildingIds, $projectId) {
            foreach ($buildingIds as $order => $buildingId) {
                $buildingId = sanitize_int($buildingId);
                db_update('buildings',
                    ['display_order' => $order + 1],
                    'id = :id AND project_id = :project_id',
                    ['id' => $buildingId, 'project_id' => $projectId]
                );
            }

            log_activity('building_reordered', 'project', $projectId);

            return ['display_order_updated' => count($buildingIds)];
        },
        'Bygnings rækkefølge opdateret',
        'Kunne ikke opdatere rækkefølge'
    );
}

/**
 * Helper function to build element hierarchy from flat list
 * NOTE: This is kept for backwards compatibility but may not be needed
 * if using the recursive CTE function get_element_hierarchy()
 */
function build_element_hierarchy(array $elements): array {
    $indexed = [];
    $tree = [];

    // First pass: index by ID
    foreach ($elements as $element) {
        $element['children'] = [];
        $indexed[$element['element_id']] = $element;
    }

    // Second pass: build tree
    foreach ($indexed as $id => $element) {
        if ($element['parent_id'] === null) {
            $tree[] = &$indexed[$id];
        } else {
            if (isset($indexed[$element['parent_id']])) {
                $indexed[$element['parent_id']]['children'][] = &$indexed[$id];
            }
        }
    }

    return $tree;
}
