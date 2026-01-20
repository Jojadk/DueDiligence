<?php
/**
 * Project Module API - Refactored with API Helpers
 *
 * Handles project management operations
 *
 * Available actions:
 * - get_list: Get all accessible projects
 * - get_details: Get project details
 * - create: Create new project
 * - update: Update project
 * - delete: Delete project
 * - copy: Copy project
 * - get_tree: Get hierarchical project tree
 * - reorder: Reorder projects (drag-and-drop)
 */

require_once __DIR__ . '/../../core/api-helpers.php';

/**
 * Get list of accessible projects
 *
 * GET /api.php?module=project&action=get_list
 */
function handle_get_list(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'search' => ['string', 'GET', false, ''],
        'status' => ['string', 'GET', false, ''],
        'limit' => ['int', 'GET', false, 50],
        'offset' => ['int', 'GET', false, 0]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $search = $validation['data']['search'];
    $status = $validation['data']['status'];
    $limit = $validation['data']['limit'];
    $offset = $validation['data']['offset'];

    // Get accessible project IDs
    $accessibleProjects = get_accessible_projects($user, 'viewer');
    $projectIds = array_column($accessibleProjects, 'project_id');

    if (empty($projectIds)) {
        return [
            'success' => true,
            'projects' => [],
            'total' => 0
        ];
    }

    // Build query with filters
    $where = [];
    $params = [];

    // Project ID filter
    $placeholders = [];
    foreach ($projectIds as $idx => $pid) {
        $key = "pid{$idx}";
        $placeholders[] = ":{$key}";
        $params[$key] = $pid;
    }
    $where[] = "p.id IN (" . implode(',', $placeholders) . ")";

    // Search filter
    if ($search) {
        $where[] = "(p.name ILIKE :search OR p.description ILIKE :search)";
        $params['search'] = "%{$search}%";
    }

    // Status filter
    if ($status) {
        $where[] = "p.status = :status";
        $params['status'] = $status;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Fetch projects with stats from view
    $projects = db_fetch_all("
        SELECT
            p.id, p.name, p.description, p.status, p.created_at, p.display_order,
            c.name as customer_name,
            ps.building_count, ps.element_count, ps.total_capex,
            ps.critical_count, ps.high_count
        FROM projects p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN v_project_summary ps ON p.id = ps.project_id
        $whereClause
        ORDER BY COALESCE(p.display_order, 999999) ASC, p.created_at DESC
        LIMIT :limit OFFSET :offset
    ", array_merge($params, ['limit' => $limit, 'offset' => $offset]));

    $total = db_value("SELECT COUNT(*) FROM projects p $whereClause", $params);

    return [
        'success' => true,
        'projects' => $projects,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ];
}

/**
 * Get project details
 *
 * GET /api.php?module=project&action=get_details&id=123
 */
function handle_get_details(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $projectId = $validation['data']['id'];

    // Check access
    $accessCheck = api_require_project_access($user, $projectId, 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    $project = db_fetch("
        SELECT p.*, c.name as customer_name,
               ps.building_count, ps.element_count, ps.total_capex,
               ps.critical_count, ps.high_count, ps.last_updated
        FROM projects p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN v_project_summary ps ON p.id = ps.project_id
        WHERE p.id = :id
    ", ['id' => $projectId]);

    if (!$project) {
        return api_error('Project not found');
    }

    // Get user's permission level
    $project['permission_level'] = get_project_permission($user, $projectId);

    return [
        'success' => true,
        'project' => $project
    ];
}

/**
 * Create new project
 *
 * POST /api.php
 * {module: 'project', action: 'create', name: '...', customer_id: 123, ...}
 */
function handle_create(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'description' => ['string', 'POST', false, ''],
        'customer_id' => ['int', 'POST', false, 0],
        'status' => ['string', 'POST', false, 'draft']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $data = $validation['data'];

    // Use api_transaction for project creation with permission grant
    return api_transaction(
        function() use ($data, $user) {
            // Create project
            $projectData = [
                'name' => $data['name'],
                'description' => $data['description'],
                'customer_id' => $data['customer_id'] ?: null,
                'status' => $data['status'],
                'user_id' => $user['id'], // Still track creator
                'created_at' => date('Y-m-d H:i:s')
            ];

            $projectId = db_insert('projects', $projectData);

            // Grant owner permission to creator
            grant_project_access($projectId, 'user', $user['id'], 'owner', $user['id']);

            log_activity('project_created', 'project', $projectId);

            return ['project_id' => $projectId];
        },
        'Project created',
        'Failed to create project'
    );
}

/**
 * Update project
 *
 * POST /api.php
 * {module: 'project', action: 'update', id: 123, name: '...', ...}
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

    $projectId = $validation['data']['id'];

    // Check edit permission
    $accessCheck = api_require_project_access($user, $projectId, 'editor');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Validate optional update fields
    $updateValidation = api_validate_params([
        'name' => ['string', 'POST', false],
        'description' => ['string', 'POST', false],
        'status' => ['string', 'POST', false],
        'customer_id' => ['int', 'POST', false]
    ]);

    if (!$updateValidation['success']) {
        return api_error($updateValidation['errors']);
    }

    // Filter out null values and handle customer_id = 0 as null
    $updates = array_filter(
        $updateValidation['data'],
        fn($value) => $value !== null
    );

    if (isset($updates['customer_id']) && $updates['customer_id'] === 0) {
        $updates['customer_id'] = null;
    }

    if (empty($updates)) {
        return api_error('No updates provided');
    }

    $updates['updated_at'] = date('Y-m-d H:i:s');

    db_update('projects', $updates, 'id = :id', ['id' => $projectId]);

    log_activity('project_updated', 'project', $projectId);

    return [
        'success' => true,
        'message' => 'Project updated'
    ];
}

/**
 * Delete project
 *
 * POST /api.php
 * {module: 'project', action: 'delete', id: 123}
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

    $projectId = $validation['data']['id'];

    // Use api_crud_delete with owner permission check
    return api_crud_delete(
        'projects',
        $projectId,
        function($project) use ($user) {
            // Only owner can delete
            return can_access_project($user, $project['id'], 'owner');
        },
        function($projectId) {
            // Delete all related data (cascade should handle this, but be explicit)
            db_query("DELETE FROM building_elements WHERE building_id IN (SELECT id FROM buildings WHERE project_id = :id)", ['id' => $projectId]);
            db_query("DELETE FROM buildings WHERE project_id = :id", ['id' => $projectId]);
            db_query("DELETE FROM project_permissions WHERE project_id = :id", ['id' => $projectId]);
        },
        function($projectId) {
            log_activity('project_deleted', 'project', $projectId);
        },
        'Project deleted',
        'Failed to delete project',
        'Project not found',
        'Only project owner can delete'
    );
}

/**
 * Copy/clone project
 *
 * POST /api.php
 * {module: 'project', action: 'copy', id: 123, new_name: '...'}
 */
function handle_copy(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'new_name' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $projectId = $validation['data']['id'];
    $newName = $validation['data']['new_name'];

    // Check access to source project
    $accessCheck = api_require_project_access($user, $projectId, 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    $project = db_fetch("SELECT * FROM projects WHERE id = :id", ['id' => $projectId]);

    if (!$project) {
        return api_error('Project not found');
    }

    // Use api_transaction for complex copy operation
    return api_transaction(
        function() use ($project, $projectId, $newName, $user) {
            // Copy project
            unset($project['id']);
            $project['name'] = $newName ?: $project['name'] . ' (Copy)';
            $project['user_id'] = $user['id'];
            $project['created_at'] = date('Y-m-d H:i:s');
            $newProjectId = db_insert('projects', $project);

            // Grant owner permission to copier
            grant_project_access($newProjectId, 'user', $user['id'], 'owner', $user['id']);

            // Copy buildings and elements
            $buildingMap = [];
            $buildings = db_query("SELECT * FROM buildings WHERE project_id = :id", ['id' => $projectId]);

            foreach ($buildings as $building) {
                $oldBuildingId = $building['id'];
                unset($building['id']);
                $building['project_id'] = $newProjectId;
                $building['created_at'] = date('Y-m-d H:i:s');
                $newBuildingId = db_insert('buildings', $building);
                $buildingMap[$oldBuildingId] = $newBuildingId;

                // Copy elements for this building
                $elements = db_query("SELECT * FROM building_elements WHERE building_id = :id", ['id' => $oldBuildingId]);
                foreach ($elements as $element) {
                    unset($element['id']);
                    $element['building_id'] = $newBuildingId;
                    $element['created_at'] = date('Y-m-d H:i:s');
                    db_insert('building_elements', $element);
                }
            }

            log_activity('project_copied', 'project', $newProjectId, ['source_project_id' => $projectId]);

            return ['project_id' => $newProjectId];
        },
        'Project copied',
        'Failed to copy project'
    );
}

/**
 * Get hierarchical project tree
 * OPTIMIZED: Uses v_building_summary
 *
 * GET /api.php?module=project&action=get_tree&id=123
 */
function handle_get_tree(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $projectId = $validation['data']['id'];

    // Check access
    $accessCheck = api_require_project_access($user, $projectId, 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    $project = db_fetch("SELECT name FROM projects WHERE id = :id", ['id' => $projectId]);

    if (!$project) {
        return api_error('Project not found');
    }

    // Get buildings with pre-calculated stats
    $buildings = db_query("
        SELECT bs.building_id as id, bs.building_name as name,
               bs.element_count, bs.total_capex, bs.critical_elements, bs.high_elements
        FROM v_building_summary bs
        JOIN buildings b ON bs.building_id = b.id
        WHERE bs.project_id = :pid
        ORDER BY b.sort_order, bs.building_name
    ", ['pid' => $projectId]);

    $projectTotal = 0;

    // For each building, get hierarchical elements
    foreach ($buildings as &$building) {
        $building['elements'] = getElementHierarchy($building['id']);
        $hierarchyTotal = calculateBuildingTotal($building['elements']);
        $building['total_capex'] = $hierarchyTotal;
        $projectTotal += $hierarchyTotal;
    }

    return [
        'success' => true,
        'tree' => [
            'project_name' => $project['name'],
            'buildings' => $buildings,
            'total_capex' => $projectTotal
        ]
    ];
}

/**
 * Reorder projects (drag-and-drop)
 * POST /api.php?module=project&action=reorder
 */
function handle_reorder(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    $projectIds = $_POST['project_ids'] ?? [];

    if (!is_array($projectIds) || empty($projectIds)) {
        return api_error('Projekt ID liste er påkrævet');
    }

    // Use api_transaction for reordering
    return api_transaction(
        function() use ($projectIds, $user) {
            $updated = 0;

            foreach ($projectIds as $order => $projectId) {
                $projectId = sanitize_int($projectId);

                // Check if user has access to this project
                if (!can_access_project($user, $projectId, 'viewer')) {
                    continue; // Skip projects user doesn't have access to
                }

                db_update('projects',
                    ['display_order' => $order + 1],
                    'id = :id',
                    ['id' => $projectId]
                );

                $updated++;
            }

            log_activity('projects_reordered', 'system', 0);

            return ['updated' => $updated];
        },
        function($result) {
            return "{$result['updated']} projekter opdateret";
        },
        'Kunne ikke opdatere rækkefølge'
    );
}

/**
 * Helper: Get hierarchical elements for a building
 */
function getElementHierarchy(int $buildingId, ?int $parentId = null): array {
    $query = "
        SELECT id, name, parent_id, element_type, location, condition_score,
               urgency, time_horizon, capex, replacement_value, unit, quantity,
               sort_order
        FROM building_elements
        WHERE building_id = :bid AND " . ($parentId ? "parent_id = :pid" : "parent_id IS NULL") . "
        ORDER BY COALESCE(sort_order, 999999), name
    ";

    $params = ['bid' => $buildingId];
    if ($parentId) {
        $params['pid'] = $parentId;
    }

    $elements = db_query($query, $params);

    // Recursively get children
    foreach ($elements as &$element) {
        $element['children'] = getElementHierarchy($buildingId, $element['id']);

        // Calculate total CAPEX including children
        $childrenTotal = 0;
        foreach ($element['children'] as $child) {
            $childrenTotal += $child['total_capex'] ?? $child['capex'] ?? 0;
        }
        $element['total_capex'] = ($element['capex'] ?? 0) + $childrenTotal;
    }

    return $elements;
}

/**
 * Helper: Calculate total CAPEX for building from element hierarchy
 */
function calculateBuildingTotal(array $elements): float {
    $total = 0;
    foreach ($elements as $element) {
        $total += $element['total_capex'] ?? $element['capex'] ?? 0;
    }
    return $total;
}
