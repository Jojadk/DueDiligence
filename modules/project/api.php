<?php
/**
 * Project Module API
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
 */

/**
 * Get list of accessible projects
 *
 * GET /api.php?module=project&action=get_list
 */
function handle_get_list(array $user): array {
    $search = sanitize_string($_GET['search'] ?? '');
    $status = sanitize_string($_GET['status'] ?? '');
    $limit = sanitize_int($_GET['limit'] ?? 50);
    $offset = sanitize_int($_GET['offset'] ?? 0);

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
            p.id, p.name, p.description, p.status, p.created_at,
            c.name as customer_name,
            ps.building_count, ps.element_count, ps.total_capex,
            ps.critical_count, ps.high_count
        FROM projects p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN v_project_summary ps ON p.id = ps.project_id
        $whereClause
        ORDER BY p.created_at DESC
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
    $projectId = sanitize_int($_GET['id'] ?? 0);

    if (!$projectId) {
        return ['success' => false, 'error' => 'Project ID required'];
    }

    // Check access
    if (!can_access_project($user, $projectId, 'viewer')) {
        return ['success' => false, 'error' => 'No access to this project'];
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
        return ['success' => false, 'error' => 'Project not found'];
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
    csrf_require();

    $name = sanitize_string($_POST['name'] ?? '');
    $description = sanitize_string($_POST['description'] ?? '');
    $customerId = sanitize_int($_POST['customer_id'] ?? 0);
    $status = sanitize_string($_POST['status'] ?? 'draft');

    if (!$name) {
        return ['success' => false, 'error' => 'Project name required'];
    }

    try {
        db_begin_transaction();

        // Create project
        $projectId = db_insert('projects', [
            'name' => $name,
            'description' => $description,
            'customer_id' => $customerId ?: null,
            'status' => $status,
            'user_id' => $user['id'], // Still track creator
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Grant owner permission to creator
        grant_project_access($projectId, 'user', $user['id'], 'owner', $user['id']);

        db_commit();

        log_activity('project_created', 'project', $projectId);

        return [
            'success' => true,
            'project_id' => $projectId,
            'message' => 'Project created'
        ];

    } catch (Exception $e) {
        db_rollback();
        log_error('Project creation error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to create project'];
    }
}

/**
 * Update project
 *
 * POST /api.php
 * {module: 'project', action: 'update', id: 123, name: '...', ...}
 */
function handle_update(array $user): array {
    csrf_require();

    $projectId = sanitize_int($_POST['id'] ?? 0);

    if (!$projectId) {
        return ['success' => false, 'error' => 'Project ID required'];
    }

    // Check edit permission
    if (!can_access_project($user, $projectId, 'editor')) {
        return ['success' => false, 'error' => 'No edit permission for this project'];
    }

    $updates = [];

    if (isset($_POST['name'])) {
        $updates['name'] = sanitize_string($_POST['name']);
    }
    if (isset($_POST['description'])) {
        $updates['description'] = sanitize_string($_POST['description']);
    }
    if (isset($_POST['status'])) {
        $updates['status'] = sanitize_string($_POST['status']);
    }
    if (isset($_POST['customer_id'])) {
        $updates['customer_id'] = sanitize_int($_POST['customer_id']) ?: null;
    }

    $updates['updated_at'] = date('Y-m-d H:i:s');

    if (empty($updates)) {
        return ['success' => false, 'error' => 'No updates provided'];
    }

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
    csrf_require();

    $projectId = sanitize_int($_POST['id'] ?? 0);

    if (!$projectId) {
        return ['success' => false, 'error' => 'Project ID required'];
    }

    // Only owner can delete
    if (!can_access_project($user, $projectId, 'owner')) {
        return ['success' => false, 'error' => 'Only project owner can delete'];
    }

    try {
        db_begin_transaction();

        // Delete all related data (cascade should handle this, but be explicit)
        db_query("DELETE FROM building_elements WHERE building_id IN (SELECT id FROM buildings WHERE project_id = :id)", ['id' => $projectId]);
        db_query("DELETE FROM buildings WHERE project_id = :id", ['id' => $projectId]);
        db_query("DELETE FROM project_permissions WHERE project_id = :id", ['id' => $projectId]);
        db_delete('projects', 'id = :id', ['id' => $projectId]);

        db_commit();

        log_activity('project_deleted', 'project', $projectId);

        return [
            'success' => true,
            'message' => 'Project deleted'
        ];

    } catch (Exception $e) {
        db_rollback();
        log_error('Project deletion error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to delete project'];
    }
}

/**
 * Copy/clone project
 *
 * POST /api.php
 * {module: 'project', action: 'copy', id: 123, new_name: '...'}
 */
function handle_copy(array $user): array {
    csrf_require();

    $projectId = sanitize_int($_POST['id'] ?? 0);
    $newName = sanitize_string($_POST['new_name'] ?? '');

    if (!$projectId) {
        return ['success' => false, 'error' => 'Project ID required'];
    }

    // Check access to source project
    if (!can_access_project($user, $projectId, 'viewer')) {
        return ['success' => false, 'error' => 'No access to source project'];
    }

    $project = db_fetch("SELECT * FROM projects WHERE id = :id", ['id' => $projectId]);

    if (!$project) {
        return ['success' => false, 'error' => 'Project not found'];
    }

    try {
        db_begin_transaction();

        // Copy project
        unset($project['id']);
        $project['name'] = $newName ?: $project['name'] . ' (Copy)';
        $project['user_id'] = $user['id'];
        $project['created_at'] = date('Y-m-d H:i:s');
        $newProjectId = db_insert('projects', $project);

        // Grant owner permission to copier
        grant_project_access($newProjectId, 'user', $user['id'], 'owner', $user['id']);

        // Copy buildings and elements (simplified - use INSERT...SELECT for better performance)
        $buildings = db_query("SELECT * FROM buildings WHERE project_id = :id", ['id' => $projectId]);
        foreach ($buildings as $building) {
            $oldBuildingId = $building['id'];
            unset($building['id']);
            $building['project_id'] = $newProjectId;
            $building['created_at'] = date('Y-m-d H:i:s');
            $newBuildingId = db_insert('buildings', $building);
            $buildingMap[$oldBuildingId] = $newBuildingId;

            // Copy elements
            $elements = db_query("SELECT * FROM building_elements WHERE building_id = :id", ['id' => $oldBuildingId]);
            foreach ($elements as $element) {
                unset($element['id']);
                $element['building_id'] = $newBuildingId;
                $element['created_at'] = date('Y-m-d H:i:s');
                db_insert('building_elements', $element);
            }
        }

        db_commit();

        log_activity('project_copied', 'project', $newProjectId, ['source_project_id' => $projectId]);

        return [
            'success' => true,
            'project_id' => $newProjectId,
            'message' => 'Project copied'
        ];

    } catch (Exception $e) {
        db_rollback();
        log_error('Project copy error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Failed to copy project'];
    }
}

/**
 * Get hierarchical project tree
 * OPTIMIZED: Uses v_building_summary
 *
 * GET /api.php?module=project&action=get_tree&id=123
 */
function handle_get_tree(array $user): array {
    $projectId = sanitize_int($_GET['id'] ?? 0);

    if (!$projectId) {
        return ['success' => false, 'error' => 'Project ID required'];
    }

    // Check access
    if (!can_access_project($user, $projectId, 'viewer')) {
        return ['success' => false, 'error' => 'No access to this project'];
    }

    $project = db_fetch("SELECT name FROM projects WHERE id = :id", ['id' => $projectId]);

    if (!$project) {
        return ['success' => false, 'error' => 'Project not found'];
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
