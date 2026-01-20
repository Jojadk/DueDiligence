<?php
/**
 * Building Module API
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

/**
 * Get list of buildings for a project
 * GET ?module=building&action=get_list&project_id=X
 */
function handle_get_list(array $user): array {
    $projectId = sanitize_int($_GET['project_id'] ?? 0);

    if (!$projectId) {
        return ['success' => false, 'error' => 'Projekt ID mangler'];
    }

    // Check project access
    if (!can_access_project($user, $projectId, 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til projektet'];
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
    $buildingId = sanitize_int($_GET['id'] ?? 0);

    if (!$buildingId) {
        return ['success' => false, 'error' => 'Bygnings ID mangler'];
    }

    // Get building with project ID
    $building = db_fetch("
        SELECT b.*, bs.element_count, bs.total_capex, bs.avg_condition,
               bs.critical_count, bs.high_count
        FROM buildings b
        LEFT JOIN v_building_summary bs ON bs.building_id = b.id
        WHERE b.id = :id
    ", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $building['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til bygningen'];
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
    csrf_require();

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $name = sanitize_string($_POST['name'] ?? '');
    $buildingNumber = sanitize_string($_POST['building_number'] ?? '');
    $buildingType = sanitize_string($_POST['building_type'] ?? '');
    $grossArea = sanitize_float($_POST['gross_area'] ?? 0);
    $floors = sanitize_int($_POST['floors'] ?? 1);
    $yearBuilt = sanitize_int($_POST['year_built'] ?? null);
    $address = sanitize_string($_POST['address'] ?? '');
    $description = sanitize_string($_POST['description'] ?? '');

    if (!$projectId || !$name) {
        return ['success' => false, 'error' => 'Projekt ID og navn er påkrævet'];
    }

    // Check project access (editor required for creation)
    if (!can_access_project($user, $projectId, 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at oprette bygninger i dette projekt'];
    }

    db_begin_transaction();
    try {
        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM buildings
            WHERE project_id = :project_id
        ", ['project_id' => $projectId]);

        $buildingData = [
            'project_id' => $projectId,
            'name' => $name,
            'building_number' => $buildingNumber,
            'building_type' => $buildingType,
            'gross_area' => $grossArea,
            'floors' => $floors,
            'year_built' => $yearBuilt,
            'address' => $address,
            'description' => $description,
            'display_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $buildingId = db_insert('buildings', $buildingData);

        db_commit();

        log_activity('building_created', 'building', $buildingId);

        return [
            'success' => true,
            'building_id' => $buildingId,
            'message' => 'Bygning oprettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke oprette bygning'];
    }
}

/**
 * Update building
 * POST ?module=building&action=update
 */
function handle_update(array $user): array {
    csrf_require();

    $buildingId = sanitize_int($_POST['id'] ?? 0);

    if (!$buildingId) {
        return ['success' => false, 'error' => 'Bygnings ID mangler'];
    }

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $building['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at redigere denne bygning'];
    }

    db_begin_transaction();
    try {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        // Only update provided fields
        if (isset($_POST['name'])) {
            $updateData['name'] = sanitize_string($_POST['name']);
        }
        if (isset($_POST['building_number'])) {
            $updateData['building_number'] = sanitize_string($_POST['building_number']);
        }
        if (isset($_POST['building_type'])) {
            $updateData['building_type'] = sanitize_string($_POST['building_type']);
        }
        if (isset($_POST['gross_area'])) {
            $updateData['gross_area'] = sanitize_float($_POST['gross_area']);
        }
        if (isset($_POST['floors'])) {
            $updateData['floors'] = sanitize_int($_POST['floors']);
        }
        if (isset($_POST['year_built'])) {
            $updateData['year_built'] = sanitize_int($_POST['year_built']);
        }
        if (isset($_POST['address'])) {
            $updateData['address'] = sanitize_string($_POST['address']);
        }
        if (isset($_POST['description'])) {
            $updateData['description'] = sanitize_string($_POST['description']);
        }

        db_update('buildings', $updateData, 'id = :id', ['id' => $buildingId]);

        db_commit();

        log_activity('building_updated', 'building', $buildingId);

        return [
            'success' => true,
            'message' => 'Bygning opdateret succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere bygning'];
    }
}

/**
 * Delete building
 * POST ?module=building&action=delete
 */
function handle_delete(array $user): array {
    csrf_require();

    $buildingId = sanitize_int($_POST['id'] ?? 0);

    if (!$buildingId) {
        return ['success' => false, 'error' => 'Bygnings ID mangler'];
    }

    // Get building to check project access
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $building['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at slette denne bygning'];
    }

    // Check if building has elements
    $elementCount = db_value("
        SELECT COUNT(*) FROM building_elements WHERE building_id = :id
    ", ['id' => $buildingId]);

    if ($elementCount > 0) {
        return [
            'success' => false,
            'error' => 'Kan ikke slette bygning med elementer. Slet elementerne først.'
        ];
    }

    db_begin_transaction();
    try {
        // Delete building OPEX assignments
        db_execute("DELETE FROM building_opex WHERE building_id = :id", ['id' => $buildingId]);

        // Delete building
        db_delete('buildings', 'id = :id', ['id' => $buildingId]);

        db_commit();

        log_activity('building_deleted', 'building', $buildingId);

        return [
            'success' => true,
            'message' => 'Bygning slettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette bygning'];
    }
}

/**
 * Get elements for building with hierarchy
 * GET ?module=building&action=get_elements&id=X
 * OPTIMIZED: Uses recursive CTE function - 1 query instead of 10+
 */
function handle_get_elements(array $user): array {
    $buildingId = sanitize_int($_GET['id'] ?? 0);

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
    csrf_require();

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $buildingIds = $_POST['building_ids'] ?? [];

    if (!$projectId || !is_array($buildingIds)) {
        return ['success' => false, 'error' => 'Projekt ID og bygnings ID liste er påkrævet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $projectId, 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at ændre rækkefølge'];
    }

    db_begin_transaction();
    try {
        foreach ($buildingIds as $order => $buildingId) {
            $buildingId = sanitize_int($buildingId);
            db_update('buildings',
                ['display_order' => $order + 1],
                'id = :id AND project_id = :project_id',
                ['id' => $buildingId, 'project_id' => $projectId]
            );
        }

        db_commit();

        log_activity('building_reordered', 'project', $projectId);

        return [
            'success' => true,
            'message' => 'Bygnings rækkefølge opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rækkefølge'];
    }
}

/**
 * Helper function to build element hierarchy from flat list
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
