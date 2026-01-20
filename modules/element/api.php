<?php
/**
 * Element Module API - Refactored with API Helpers
 *
 * Handles building element CRUD operations and hierarchy management
 *
 * Actions:
 * - get_list: Get elements for a building
 * - get_details: Get element details
 * - create: Create new element
 * - update: Update element
 * - delete: Delete element
 * - move: Move element to new parent
 * - update_order: Reorder elements
 * - get_hierarchy: Get full element hierarchy with stats
 * - bulk_update: Update multiple elements at once
 */

require_once __DIR__ . '/../../core/permissions.php';
require_once __DIR__ . '/../../core/api-helpers.php';

/**
 * Get list of elements for a building
 * GET ?module=element&action=get_list&building_id=X
 */
function handle_get_list(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'building_id' => ['int', 'GET', true],
        'parent_id' => ['int', 'GET', false, null]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $buildingId = $validation['data']['building_id'];
    $parentId = $validation['data']['parent_id'];

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

    // Get elements with pre-calculated stats from view
    $query = "
        SELECT
            es.element_id,
            es.element_name,
            es.element_code,
            es.parent_id,
            es.level_code,
            es.capex,
            es.urgency,
            es.condition_score,
            es.is_critical_urgency,
            es.is_poor_condition,
            es.is_high_cost,
            es.red_flag_score,
            es.severity,
            es.display_order,
            es.description,
            es.quantity,
            es.unit,
            es.created_at,
            es.updated_at
        FROM v_element_summary es
        WHERE es.building_id = :building_id
    ";

    $params = ['building_id' => $buildingId];

    // Filter by parent if specified
    if ($parentId === 0) {
        $query .= " AND es.parent_id IS NULL";
    } elseif ($parentId !== null) {
        $query .= " AND es.parent_id = :parent_id";
        $params['parent_id'] = $parentId;
    }

    $query .= " ORDER BY es.display_order ASC, es.element_code ASC";

    $elements = db_fetch_all($query, $params);

    return [
        'success' => true,
        'elements' => $elements
    ];
}

/**
 * Get detailed element information
 * GET ?module=element&action=get_details&id=X
 * OPTIMIZED: Uses aggregate stats function for descendants
 */
function handle_get_details(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $elementId = $validation['data']['id'];

    // Get element with all details and check project access
    $element = db_fetch("
        SELECT be.*, b.project_id, b.name as building_name,
               es.red_flag_score, es.severity, es.is_critical_urgency,
               es.is_poor_condition, es.is_high_cost
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        LEFT JOIN v_element_summary es ON es.element_id = be.id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    // Check project access
    $accessCheck = api_require_project_access($user, $element['project_id'], 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Get budget totals for all types
    $budgetTypes = ['capex', 'opex', 'reinstatement'];
    $budgets = [];

    foreach ($budgetTypes as $type) {
        $budget = db_fetch("
            SELECT
                line_count,
                total_budget,
                total_year_0_1,
                total_year_1_2,
                total_year_3_5,
                total_year_5_10,
                total_year_10_plus
            FROM v_budget_totals
            WHERE element_id = :element_id AND budget_type = :budget_type
        ", ['element_id' => $elementId, 'budget_type' => $type]);

        $budgets[$type] = $budget ?: [
            'line_count' => 0,
            'total_budget' => 0,
            'total_year_0_1' => 0,
            'total_year_1_2' => 0,
            'total_year_3_5' => 0,
            'total_year_5_10' => 0,
            'total_year_10_plus' => 0
        ];
    }

    $element['budgets'] = $budgets;

    // OPTIMIZED: Get child elements count and aggregate stats using recursive CTE
    $childStats = db_fetch("
        SELECT * FROM get_element_aggregate_stats(:element_id)
    ", ['element_id' => $elementId]);

    $element['child_count'] = (int)($childStats['total_elements'] ?? 0) - 1; // Exclude self
    $element['aggregate_stats'] = $childStats;

    // Get breadcrumb path
    $path = db_fetch_all("
        SELECT * FROM get_element_path(:element_id)
    ", ['element_id' => $elementId]);

    $element['path'] = $path;

    return [
        'success' => true,
        'element' => $element
    ];
}

/**
 * Create new element
 * POST ?module=element&action=create
 */
function handle_create(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'building_id' => ['int', 'POST', true],
        'name' => ['string', 'POST', true],
        'element_code' => ['string', 'POST', false, ''],
        'level_code' => ['string', 'POST', false, ''],
        'parent_id' => ['int', 'POST', false, null],
        'description' => ['string', 'POST', false, ''],
        'quantity' => ['float', 'POST', false, 1],
        'unit' => ['string', 'POST', false, 'stk'],
        'capex' => ['float', 'POST', false, 0],
        'urgency' => ['int', 'POST', false, 3],
        'condition_score' => ['int', 'POST', false, 3]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $data = $validation['data'];
    $buildingId = $data['building_id'];
    $parentId = $data['parent_id'];

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return api_error('Bygning ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $building['project_id'], 'editor');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Use api_crud_create with custom logic for display_order
    return api_crud_create(
        'building_elements',
        $data,
        function(&$elementData) use ($buildingId, $parentId) {
            // Get next display order
            $maxOrder = db_value("
                SELECT COALESCE(MAX(display_order), 0)
                FROM building_elements
                WHERE building_id = :building_id AND parent_id " . ($parentId ? "= :parent_id" : "IS NULL"),
                $parentId ? ['building_id' => $buildingId, 'parent_id' => $parentId] : ['building_id' => $buildingId]
            );

            $elementData['display_order'] = $maxOrder + 1;
            $elementData['created_at'] = date('Y-m-d H:i:s');
            $elementData['updated_at'] = date('Y-m-d H:i:s');
        },
        function($elementId) {
            log_activity('element_created', 'element', $elementId);
        },
        'Element oprettet succesfuldt',
        'Kunne ikke oprette element'
    );
}

/**
 * Update element
 * POST ?module=element&action=update
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

    $elementId = $validation['data']['id'];

    // Validate update fields (all optional)
    $updateValidation = api_validate_params([
        'name' => ['string', 'POST', false],
        'element_code' => ['string', 'POST', false],
        'level_code' => ['string', 'POST', false],
        'description' => ['string', 'POST', false],
        'quantity' => ['float', 'POST', false],
        'unit' => ['string', 'POST', false],
        'capex' => ['float', 'POST', false],
        'urgency' => ['int', 'POST', false],
        'condition_score' => ['int', 'POST', false]
    ]);

    if (!$updateValidation['success']) {
        return api_error($updateValidation['errors']);
    }

    // Filter out null values
    $updateData = array_filter(
        $updateValidation['data'],
        fn($value) => $value !== null
    );

    if (empty($updateData)) {
        return api_error('Ingen felter at opdatere');
    }

    // Use api_crud_update with project access check
    return api_crud_update(
        'building_elements',
        $elementId,
        $updateData,
        function($element) use ($user) {
            // Get project_id from building
            $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $element['building_id']]);
            return $building && can_access_project($user, $building['project_id'], 'editor');
        },
        function($elementId) {
            log_activity('element_updated', 'element', $elementId);
        },
        'Element opdateret succesfuldt',
        'Kunne ikke opdatere element',
        'Element ikke fundet',
        'Ingen adgang til at redigere element'
    );
}

/**
 * Delete element
 * POST ?module=element&action=delete
 * OPTIMIZED: Uses validation function to check deletion eligibility
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

    $elementId = $validation['data']['id'];

    // Use api_crud_delete with custom validation
    return api_crud_delete(
        'building_elements',
        $elementId,
        function($element) use ($user) {
            // Get project_id from building
            $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $element['building_id']]);
            return $building && can_access_project($user, $building['project_id'], 'editor');
        },
        function($elementId) {
            // OPTIMIZED: Check if element can be deleted using database function
            $canDelete = db_fetch("
                SELECT * FROM can_delete_element(:element_id)
            ", ['element_id' => $elementId]);

            if (!$canDelete['can_delete']) {
                throw new Exception($canDelete['reason']);
            }

            // Delete related data
            db_execute("DELETE FROM budget_lines WHERE element_id = :id", ['id' => $elementId]);
            db_execute("DELETE FROM element_images WHERE element_id = :id", ['id' => $elementId]);
        },
        function($elementId) {
            log_activity('element_deleted', 'element', $elementId);
        },
        'Element slettet succesfuldt',
        'Kunne ikke slette element',
        'Element ikke fundet',
        'Ingen adgang til at slette element'
    );
}

/**
 * Move element to new parent
 * POST ?module=element&action=move
 */
function handle_move(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'new_parent_id' => ['int', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $elementId = $validation['data']['id'];
    $newParentId = $validation['data']['new_parent_id'];

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $element['project_id'], 'editor');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Prevent moving to self
    if ($newParentId === $elementId) {
        return api_error('Element kan ikke flyttes til sig selv');
    }

    // Validate new parent
    if ($newParentId !== null) {
        $newParent = db_fetch("
            SELECT building_id FROM building_elements WHERE id = :id
        ", ['id' => $newParentId]);

        if (!$newParent) {
            return api_error('Ny forælder ikke fundet');
        }

        if ($newParent['building_id'] != $element['building_id']) {
            return api_error('Element kan kun flyttes inden for samme bygning');
        }

        // Check for circular reference
        if (is_descendant_of($newParentId, $elementId)) {
            return api_error('Element kan ikke flyttes til et af sine underordnede');
        }
    }

    // Use api_transaction for the move operation
    return api_transaction(
        function() use ($elementId, $newParentId, $element) {
            // Get next display order in new location
            $maxOrder = db_value("
                SELECT COALESCE(MAX(display_order), 0)
                FROM building_elements
                WHERE building_id = :building_id AND parent_id " . ($newParentId ? "= :parent_id" : "IS NULL"),
                $newParentId ? ['building_id' => $element['building_id'], 'parent_id' => $newParentId] : ['building_id' => $element['building_id']]
            );

            db_update('building_elements',
                [
                    'parent_id' => $newParentId,
                    'display_order' => $maxOrder + 1,
                    'updated_at' => date('Y-m-d H:i:s')
                ],
                'id = :id',
                ['id' => $elementId]
            );

            log_activity('element_moved', 'element', $elementId);

            return ['element_id' => $elementId];
        },
        'Element flyttet succesfuldt',
        'Kunne ikke flytte element'
    );
}

/**
 * Update element display order
 * POST ?module=element&action=update_order
 */
function handle_update_order(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'building_id' => ['int', 'POST', true],
        'parent_id' => ['int', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $buildingId = $validation['data']['building_id'];
    $parentId = $validation['data']['parent_id'];
    $elementIds = $_POST['element_ids'] ?? [];

    if (!is_array($elementIds) || empty($elementIds)) {
        return api_error('Element ID liste er påkrævet');
    }

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return api_error('Bygning ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $building['project_id'], 'editor');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Use api_transaction for reordering
    return api_transaction(
        function() use ($elementIds, $buildingId, $parentId) {
            foreach ($elementIds as $order => $elementId) {
                $elementId = sanitize_int($elementId);
                $whereClause = 'id = :id AND building_id = :building_id AND parent_id ' .
                               ($parentId ? '= :parent_id' : 'IS NULL');
                $params = ['id' => $elementId, 'building_id' => $buildingId];
                if ($parentId) {
                    $params['parent_id'] = $parentId;
                }

                db_update('building_elements',
                    ['display_order' => $order + 1],
                    $whereClause,
                    $params
                );
            }

            log_activity('elements_reordered', 'building', $buildingId);

            return ['reordered_count' => count($elementIds)];
        },
        'Element rækkefølge opdateret',
        'Kunne ikke opdatere rækkefølge'
    );
}

/**
 * Get full element hierarchy with stats
 * GET ?module=element&action=get_hierarchy&building_id=X
 * OPTIMIZED: Uses recursive CTE function - 1 query instead of 10+
 */
function handle_get_hierarchy(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'building_id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $buildingId = $validation['data']['building_id'];

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
    $hierarchy = db_fetch_all("
        SELECT * FROM get_element_hierarchy(:building_id)
    ", ['building_id' => $buildingId]);

    return [
        'success' => true,
        'hierarchy' => $hierarchy
    ];
}

/**
 * Bulk update multiple elements
 * POST ?module=element&action=bulk_update
 */
function handle_bulk_update(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    $updates = $_POST['updates'] ?? [];

    if (!is_array($updates) || empty($updates)) {
        return api_error('Ingen opdateringer modtaget');
    }

    // Use api_transaction for bulk update
    return api_transaction(
        function() use ($updates, $user) {
            $updatedCount = 0;

            foreach ($updates as $update) {
                $elementId = sanitize_int($update['id'] ?? 0);
                if (!$elementId) {
                    continue;
                }

                // Get element to check project access
                $element = db_fetch("
                    SELECT be.*, b.project_id
                    FROM building_elements be
                    JOIN buildings b ON b.id = be.building_id
                    WHERE be.id = :id
                ", ['id' => $elementId]);

                if (!$element) {
                    continue;
                }

                // Check project access (editor required)
                if (!can_access_project($user, $element['project_id'], 'editor')) {
                    continue;
                }

                $updateData = ['updated_at' => date('Y-m-d H:i:s')];

                // Apply updates
                if (isset($update['urgency'])) {
                    $updateData['urgency'] = sanitize_int($update['urgency']);
                }
                if (isset($update['condition_score'])) {
                    $updateData['condition_score'] = sanitize_int($update['condition_score']);
                }
                if (isset($update['capex'])) {
                    $updateData['capex'] = sanitize_float($update['capex']);
                }

                db_update('building_elements', $updateData, 'id = :id', ['id' => $elementId]);
                $updatedCount++;
            }

            log_activity('elements_bulk_updated', 'system', 0);

            return ['updated_count' => $updatedCount];
        },
        function($result) {
            return "{$result['updated_count']} elementer opdateret";
        },
        'Kunne ikke opdatere elementer'
    );
}

/**
 * Helper function to check if element is descendant of another
 */
function is_descendant_of(int $elementId, int $ancestorId): bool {
    $parent = db_fetch("
        SELECT parent_id FROM building_elements WHERE id = :id
    ", ['id' => $elementId]);

    if (!$parent || !$parent['parent_id']) {
        return false;
    }

    if ($parent['parent_id'] == $ancestorId) {
        return true;
    }

    return is_descendant_of($parent['parent_id'], $ancestorId);
}

/**
 * Helper function to build element tree from flat list
 */
function build_element_tree(array $elements): array {
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
