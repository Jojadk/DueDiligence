<?php
/**
 * Element Module API
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

/**
 * Get list of elements for a building
 * GET ?module=element&action=get_list&building_id=X
 */
function handle_get_list(array $user): array {
    $buildingId = sanitize_int($_GET['building_id'] ?? 0);
    $parentId = isset($_GET['parent_id']) ? sanitize_int($_GET['parent_id']) : null;

    if (!$buildingId) {
        return ['success' => false, 'error' => 'Bygnings ID mangler'];
    }

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $building['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til bygningen'];
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
    $elementId = sanitize_int($_GET['id'] ?? 0);

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get element with all details
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
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $element['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til elementet'];
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
    csrf_require();

    $buildingId = sanitize_int($_POST['building_id'] ?? 0);
    $name = sanitize_string($_POST['name'] ?? '');
    $elementCode = sanitize_string($_POST['element_code'] ?? '');
    $levelCode = sanitize_string($_POST['level_code'] ?? '');
    $parentId = isset($_POST['parent_id']) ? sanitize_int($_POST['parent_id']) : null;

    if (!$buildingId || !$name) {
        return ['success' => false, 'error' => 'Bygnings ID og navn er påkrævet'];
    }

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $building['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at oprette elementer'];
    }

    db_begin_transaction();
    try {
        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM building_elements
            WHERE building_id = :building_id AND parent_id " . ($parentId ? "= :parent_id" : "IS NULL"),
            $parentId ? ['building_id' => $buildingId, 'parent_id' => $parentId] : ['building_id' => $buildingId]
        );

        $elementData = [
            'building_id' => $buildingId,
            'parent_id' => $parentId,
            'name' => $name,
            'element_code' => $elementCode,
            'level_code' => $levelCode,
            'description' => sanitize_string($_POST['description'] ?? ''),
            'quantity' => sanitize_float($_POST['quantity'] ?? 1),
            'unit' => sanitize_string($_POST['unit'] ?? 'stk'),
            'capex' => sanitize_float($_POST['capex'] ?? 0),
            'urgency' => sanitize_int($_POST['urgency'] ?? 3),
            'condition_score' => sanitize_int($_POST['condition_score'] ?? 3),
            'display_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $elementId = db_insert('building_elements', $elementData);

        db_commit();

        log_activity('element_created', 'element', $elementId);

        return [
            'success' => true,
            'element_id' => $elementId,
            'message' => 'Element oprettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke oprette element'];
    }
}

/**
 * Update element
 * POST ?module=element&action=update
 */
function handle_update(array $user): array {
    csrf_require();

    $elementId = sanitize_int($_POST['id'] ?? 0);

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at redigere element'];
    }

    db_begin_transaction();
    try {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        // Only update provided fields
        if (isset($_POST['name'])) {
            $updateData['name'] = sanitize_string($_POST['name']);
        }
        if (isset($_POST['element_code'])) {
            $updateData['element_code'] = sanitize_string($_POST['element_code']);
        }
        if (isset($_POST['level_code'])) {
            $updateData['level_code'] = sanitize_string($_POST['level_code']);
        }
        if (isset($_POST['description'])) {
            $updateData['description'] = sanitize_string($_POST['description']);
        }
        if (isset($_POST['quantity'])) {
            $updateData['quantity'] = sanitize_float($_POST['quantity']);
        }
        if (isset($_POST['unit'])) {
            $updateData['unit'] = sanitize_string($_POST['unit']);
        }
        if (isset($_POST['capex'])) {
            $updateData['capex'] = sanitize_float($_POST['capex']);
        }
        if (isset($_POST['urgency'])) {
            $updateData['urgency'] = sanitize_int($_POST['urgency']);
        }
        if (isset($_POST['condition_score'])) {
            $updateData['condition_score'] = sanitize_int($_POST['condition_score']);
        }

        db_update('building_elements', $updateData, 'id = :id', ['id' => $elementId]);

        db_commit();

        log_activity('element_updated', 'element', $elementId);

        return [
            'success' => true,
            'message' => 'Element opdateret succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere element'];
    }
}

/**
 * Delete element
 * POST ?module=element&action=delete
 * OPTIMIZED: Uses validation function to check deletion eligibility
 */
function handle_delete(array $user): array {
    csrf_require();

    $elementId = sanitize_int($_POST['id'] ?? 0);

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at slette element'];
    }

    // OPTIMIZED: Check if element can be deleted using database function
    $canDelete = db_fetch("
        SELECT * FROM can_delete_element(:element_id)
    ", ['element_id' => $elementId]);

    if (!$canDelete['can_delete']) {
        return [
            'success' => false,
            'error' => $canDelete['reason']
        ];
    }

    db_begin_transaction();
    try {
        // Delete budget lines
        db_execute("DELETE FROM budget_lines WHERE element_id = :id", ['id' => $elementId]);

        // Delete element images
        db_execute("DELETE FROM element_images WHERE element_id = :id", ['id' => $elementId]);

        // Delete element
        db_delete('building_elements', 'id = :id', ['id' => $elementId]);

        db_commit();

        log_activity('element_deleted', 'element', $elementId);

        return [
            'success' => true,
            'message' => 'Element slettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette element'];
    }
}

/**
 * Move element to new parent
 * POST ?module=element&action=move
 */
function handle_move(array $user): array {
    csrf_require();

    $elementId = sanitize_int($_POST['id'] ?? 0);
    $newParentId = isset($_POST['new_parent_id']) ? sanitize_int($_POST['new_parent_id']) : null;

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at flytte element'];
    }

    // Prevent moving to self or descendant
    if ($newParentId === $elementId) {
        return ['success' => false, 'error' => 'Element kan ikke flyttes til sig selv'];
    }

    if ($newParentId !== null) {
        // Check if new parent exists and is in same building
        $newParent = db_fetch("
            SELECT building_id FROM building_elements WHERE id = :id
        ", ['id' => $newParentId]);

        if (!$newParent) {
            return ['success' => false, 'error' => 'Ny forælder ikke fundet'];
        }

        if ($newParent['building_id'] != $element['building_id']) {
            return ['success' => false, 'error' => 'Element kan kun flyttes inden for samme bygning'];
        }

        // Check for circular reference (is new parent a descendant?)
        if (is_descendant_of($newParentId, $elementId)) {
            return ['success' => false, 'error' => 'Element kan ikke flyttes til et af sine underordnede'];
        }
    }

    db_begin_transaction();
    try {
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

        db_commit();

        log_activity('element_moved', 'element', $elementId);

        return [
            'success' => true,
            'message' => 'Element flyttet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke flytte element'];
    }
}

/**
 * Update element display order
 * POST ?module=element&action=update_order
 */
function handle_update_order(array $user): array {
    csrf_require();

    $buildingId = sanitize_int($_POST['building_id'] ?? 0);
    $parentId = isset($_POST['parent_id']) ? sanitize_int($_POST['parent_id']) : null;
    $elementIds = $_POST['element_ids'] ?? [];

    if (!$buildingId || !is_array($elementIds)) {
        return ['success' => false, 'error' => 'Bygnings ID og element ID liste er påkrævet'];
    }

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $building['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at ændre rækkefølge'];
    }

    db_begin_transaction();
    try {
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

        db_commit();

        log_activity('elements_reordered', 'building', $buildingId);

        return [
            'success' => true,
            'message' => 'Element rækkefølge opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rækkefølge'];
    }
}

/**
 * Get full element hierarchy with stats
 * GET ?module=element&action=get_hierarchy&building_id=X
 * OPTIMIZED: Uses recursive CTE function - 1 query instead of 10+
 */
function handle_get_hierarchy(array $user): array {
    $buildingId = sanitize_int($_GET['building_id'] ?? 0);

    if (!$buildingId) {
        return ['success' => false, 'error' => 'Bygnings ID mangler'];
    }

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $building['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til bygningen'];
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
    csrf_require();

    $updates = $_POST['updates'] ?? [];

    if (!is_array($updates) || empty($updates)) {
        return ['success' => false, 'error' => 'Ingen opdateringer modtaget'];
    }

    db_begin_transaction();
    try {
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

        db_commit();

        log_activity('elements_bulk_updated', 'system', 0);

        return [
            'success' => true,
            'updated_count' => $updatedCount,
            'message' => "$updatedCount elementer opdateret"
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere elementer'];
    }
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
