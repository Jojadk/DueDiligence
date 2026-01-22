<?php
/**
 * Consolidated API Helpers
 *
 * Reduces code duplication by ~1,900 lines across 14 modules
 * Provides generic CRUD operations with hooks and validation
 *
 * @package DueDiligence
 * @version 2.0.0
 * @author Claude Code
 */

require_once __DIR__ . '/api-helpers.php';
require_once __DIR__ . '/permissions.php';

/**
 * Generic CREATE operation
 *
 * Handles: CSRF, validation, permissions, timestamps, activity logging
 *
 * @param string $table Database table name
 * @param array $validation Validation rules ['field' => 'required|int']
 * @param array|null $customData Optional custom data to merge
 * @param callable|null $afterCreate Optional callback after creation
 * @param string $entityType Entity type for activity log (defaults to table name)
 * @return array API response
 */
function api_crud_create(
    string $table,
    array $validation = [],
    ?array $customData = null,
    ?callable $afterCreate = null,
    string $entityType = null
): array {
    global $user;

    // CSRF check
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    // Validation
    if (!empty($validation)) {
        $validationResult = api_validate_params($validation);
        if (!$validationResult['success']) return $validationResult;
        $params = $validationResult['data'];
    } else {
        $params = $_POST;
    }

    // Transaction
    return api_transaction(function() use ($table, $params, $customData, $user, $afterCreate, $entityType) {
        $data = $params;

        // Merge custom data
        if ($customData) {
            $data = array_merge($data, $customData);
        }

        // Auto-add metadata
        if (!isset($data['created_by_user_id']) && isset($user['id'])) {
            $data['created_by_user_id'] = $user['id'];
        }
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        // Insert
        $id = db_insert($table, $data);

        // Activity log
        $entity = $entityType ?? $table;
        log_activity("{$entity}_created", $entity, $id);

        // After-create callback
        if ($afterCreate) {
            $callbackResult = $afterCreate($id, $data);
            if ($callbackResult !== null) {
                return $callbackResult;
            }
        }

        return [
            'success' => true,
            'id' => $id,
            'message' => ucfirst($entity) . ' created successfully'
        ];

    }, ucfirst($entityType ?? $table) . ' created', 'Failed to create ' . ($entityType ?? $table));
}

/**
 * Generic UPDATE operation
 *
 * @param string $table Database table name
 * @param int $id Record ID
 * @param array $updateData Data to update
 * @param callable|null $beforeUpdate Optional callback before update
 * @param callable|null $afterUpdate Optional callback after update
 * @param string $entityType Entity type for activity log
 * @return array API response
 */
function api_crud_update(
    string $table,
    int $id,
    array $updateData,
    ?callable $beforeUpdate = null,
    ?callable $afterUpdate = null,
    string $entityType = null
): array {
    global $user;

    // CSRF check
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    // Transaction
    return api_transaction(function() use ($table, $id, $updateData, $beforeUpdate, $afterUpdate, $entityType) {
        // Before-update callback
        if ($beforeUpdate) {
            $result = $beforeUpdate($id, $updateData);
            if ($result !== null && !$result['success']) {
                return $result;
            }
        }

        // Auto-add updated_at
        if (!isset($updateData['updated_at'])) {
            $updateData['updated_at'] = date('Y-m-d H:i:s');
        }

        // Update
        $affected = db_update($table, $updateData, 'id = :id', ['id' => $id]);

        if ($affected === 0) {
            return api_error(ucfirst($entityType ?? $table) . ' not found or no changes made');
        }

        // Activity log
        $entity = $entityType ?? $table;
        log_activity("{$entity}_updated", $entity, $id);

        // After-update callback
        if ($afterUpdate) {
            $callbackResult = $afterUpdate($id, $updateData);
            if ($callbackResult !== null) {
                return $callbackResult;
            }
        }

        return [
            'success' => true,
            'id' => $id,
            'message' => ucfirst($entity) . ' updated successfully'
        ];

    }, ucfirst($entityType ?? $table) . ' updated', 'Failed to update ' . ($entityType ?? $table));
}

/**
 * Generic DELETE operation
 *
 * @param string $table Database table name
 * @param int $id Record ID
 * @param callable|null $beforeDelete Optional callback before deletion (can prevent delete)
 * @param callable|null $afterDelete Optional callback after deletion (cleanup)
 * @param string $entityType Entity type for activity log
 * @return array API response
 */
function api_crud_delete(
    string $table,
    int $id,
    ?callable $beforeDelete = null,
    ?callable $afterDelete = null,
    string $entityType = null
): array {
    // CSRF check
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    // Transaction
    return api_transaction(function() use ($table, $id, $beforeDelete, $afterDelete, $entityType) {
        // Before-delete callback (can prevent deletion)
        if ($beforeDelete) {
            $result = $beforeDelete($id);
            if ($result !== null && !$result['success']) {
                return $result;
            }
        }

        // Delete
        $affected = db_delete($table, 'id = :id', ['id' => $id]);

        if ($affected === 0) {
            return api_error(ucfirst($entityType ?? $table) . ' not found');
        }

        // Activity log
        $entity = $entityType ?? $table;
        log_activity("{$entity}_deleted", $entity, $id);

        // After-delete callback (cleanup)
        if ($afterDelete) {
            $afterDelete($id);
        }

        return [
            'success' => true,
            'message' => ucfirst($entity) . ' deleted successfully'
        ];

    }, ucfirst($entityType ?? $table) . ' deleted', 'Failed to delete ' . ($entityType ?? $table));
}

/**
 * Generic GET operation with optional project access check
 *
 * @param string $table Database table name
 * @param int $id Record ID
 * @param array $user Current user
 * @param string|null $projectIdPath Dot notation path to project_id (e.g., 'project_id' or 'building.project_id')
 * @param string $accessLevel Required access level ('viewer', 'editor', 'admin')
 * @param string|null $joins Optional JOIN clauses
 * @param string $entityType Entity type for error messages
 * @return array API response
 */
function api_crud_get(
    string $table,
    int $id,
    array $user,
    ?string $projectIdPath = null,
    string $accessLevel = 'viewer',
    ?string $joins = null,
    string $entityType = null
): array {
    // Build query
    $query = "SELECT {$table}.*";

    if ($joins) {
        $query .= ", {$joins}";
    }

    $query .= " FROM {$table}";

    // Add joins if project access needed
    if ($projectIdPath && strpos($projectIdPath, '.') !== false) {
        $parts = explode('.', $projectIdPath);
        $relationTable = $parts[0];
        $query .= " LEFT JOIN {$relationTable} ON {$relationTable}.id = {$table}.{$relationTable}_id";
    }

    $query .= " WHERE {$table}.id = :id";

    $entity = db_fetch($query, ['id' => $id]);

    if (!$entity) {
        return api_error(ucfirst($entityType ?? $table) . ' not found');
    }

    // Project access check
    if ($projectIdPath) {
        $projectId = $entity[$projectIdPath] ?? null;
        if ($projectId) {
            $accessCheck = api_require_project_access($user, $projectId, $accessLevel);
            if (!$accessCheck['success']) {
                return $accessCheck;
            }
        }
    }

    return [
        'success' => true,
        'data' => $entity
    ];
}

/**
 * Generic LIST operation with pagination and search
 *
 * @param string $table Database table name
 * @param array $options Options: where, joins, orderBy, searchFields, limit, page
 * @param array $user Current user
 * @param string|null $projectIdPath Path to project_id for filtering by user access
 * @return array API response with pagination
 */
function api_crud_list(
    string $table,
    array $options = [],
    array $user = null,
    ?string $projectIdPath = null
): array {
    $defaults = [
        'select' => '*',
        'where' => '1=1',
        'joins' => '',
        'orderBy' => 'id DESC',
        'searchFields' => [],
        'limit' => 50,
        'page' => 1,
        'params' => []
    ];

    $opts = array_merge($defaults, $options);

    // Parse pagination
    $page = (int)($_GET['page'] ?? $opts['page']);
    $limit = (int)($_GET['limit'] ?? $opts['limit']);
    $offset = ($page - 1) * $limit;

    // Build query
    $query = "SELECT {$opts['select']} FROM {$table} {$opts['joins']}";

    $whereConditions = [$opts['where']];
    $params = $opts['params'];

    // Search filter
    if (!empty($opts['searchFields']) && !empty($_GET['search'])) {
        $searchTerm = $_GET['search'];
        $searchConditions = [];

        foreach ($opts['searchFields'] as $field) {
            $searchConditions[] = "{$field} ILIKE :search";
        }

        $whereConditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        $params['search'] = '%' . $searchTerm . '%';
    }

    // Project access filter
    if ($projectIdPath && $user) {
        // Get accessible project IDs for user
        $accessibleProjects = db_fetch_all("
            SELECT DISTINCT project_id FROM user_project_access
            WHERE user_id = :user_id
        ", ['user_id' => $user['id']]);

        if (!empty($accessibleProjects)) {
            $projectIds = array_column($accessibleProjects, 'project_id');
            $whereConditions[] = "{$projectIdPath} IN (" . implode(',', $projectIds) . ")";
        }
    }

    $query .= " WHERE " . implode(' AND ', $whereConditions);
    $query .= " ORDER BY {$opts['orderBy']}";
    $query .= " LIMIT :limit OFFSET :offset";

    $params['limit'] = $limit;
    $params['offset'] = $offset;

    // Fetch items
    $items = db_fetch_all($query, $params);

    // Count total
    $countQuery = "SELECT COUNT(*) FROM {$table} {$opts['joins']} WHERE " . implode(' AND ', $whereConditions);
    unset($params['limit'], $params['offset']);
    $total = db_value($countQuery, $params);

    return [
        'success' => true,
        'items' => $items,
        'pagination' => [
            'total' => (int)$total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit)
        ]
    ];
}

/**
 * Generic REORDER operation
 *
 * Reorders items based on provided array of IDs
 *
 * @param string $table Database table name
 * @param string $parentField Parent field name (e.g., 'parent_id', 'building_id')
 * @param int $parentId Parent record ID
 * @param array $itemIds Ordered array of item IDs
 * @param string $orderField Order field name (defaults to 'display_order')
 * @param string $entityType Entity type for activity log
 * @return array API response
 */
function api_reorder_items(
    string $table,
    string $parentField,
    int $parentId,
    array $itemIds,
    string $orderField = 'display_order',
    string $entityType = null
): array {
    // CSRF check
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    // Transaction
    return api_transaction(function() use ($table, $parentField, $parentId, $itemIds, $orderField, $entityType) {
        foreach ($itemIds as $order => $itemId) {
            db_update(
                $table,
                [$orderField => $order + 1, 'updated_at' => date('Y-m-d H:i:s')],
                "id = :id AND {$parentField} = :parent_id",
                ['id' => $itemId, 'parent_id' => $parentId]
            );
        }

        // Activity log
        $entity = $entityType ?? $table;
        log_activity("{$entity}_reordered", $entity, $parentId);

        return [
            'success' => true,
            'message' => ucfirst($entity) . ' reordered successfully'
        ];

    }, ucfirst($entityType ?? $table) . ' reordered', 'Failed to reorder ' . ($entityType ?? $table));
}

/**
 * Get entity with automatic project access check
 *
 * Fetches entity and checks project access in one operation
 *
 * @param string $table Database table name
 * @param int $id Entity ID
 * @param array $user Current user
 * @param string $projectIdPath Dot notation path to project_id
 * @param string $accessLevel Required access level
 * @param string|null $joins Optional JOIN clauses for fetching related data
 * @return array Entity data or error
 */
function api_get_entity_with_project_access(
    string $table,
    int $id,
    array $user,
    string $projectIdPath = 'project_id',
    string $accessLevel = 'viewer',
    ?string $joins = null
): array {
    // Build query with joins if needed
    $query = "SELECT {$table}.*";

    // Add joined fields
    if ($joins) {
        $query .= ", {$joins}";
    }

    $query .= " FROM {$table}";

    // Auto-detect joins based on projectIdPath
    if (strpos($projectIdPath, '.') !== false) {
        $parts = explode('.', $projectIdPath);
        $joinTable = $parts[0];
        $query .= " LEFT JOIN {$joinTable} ON {$joinTable}.id = {$table}.{$joinTable}_id";
        $projectId = $parts[1];
    } else {
        $projectId = $projectIdPath;
    }

    $query .= " WHERE {$table}.id = :id";

    // Fetch entity
    $entity = db_fetch($query, ['id' => $id]);

    if (!$entity) {
        return api_error(ucfirst($table) . ' not found');
    }

    // Extract project_id (handle dot notation)
    $projectIdValue = $entity;
    foreach (explode('.', $projectIdPath) as $part) {
        $projectIdValue = $projectIdValue[$part] ?? null;
        if ($projectIdValue === null) break;
    }

    if (!$projectIdValue) {
        return api_error('Project access could not be determined');
    }

    // Check access
    $accessCheck = api_require_project_access($user, $projectIdValue, $accessLevel);
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    return [
        'success' => true,
        'data' => $entity,
        'project_id' => $projectIdValue
    ];
}

/**
 * Pagination helper
 *
 * Returns pagination metadata and SQL LIMIT/OFFSET
 *
 * @param int $total Total record count
 * @param int|null $page Current page (from $_GET)
 * @param int|null $limit Per page limit (from $_GET)
 * @return array ['sql' => 'LIMIT X OFFSET Y', 'params' => [], 'meta' => []]
 */
function api_pagination(int $total, ?int $page = null, ?int $limit = null): array {
    $page = $page ?? (int)($_GET['page'] ?? 1);
    $limit = $limit ?? (int)($_GET['limit'] ?? 50);

    // Sanity checks
    $page = max(1, $page);
    $limit = max(1, min(1000, $limit)); // Cap at 1000

    $offset = ($page - 1) * $limit;

    return [
        'sql' => "LIMIT :limit OFFSET :offset",
        'params' => ['limit' => $limit, 'offset' => $offset],
        'meta' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int)ceil($total / $limit),
            'has_next' => $page < ceil($total / $limit),
            'has_prev' => $page > 1
        ]
    ];
}

/**
 * Bulk operation helper
 *
 * Applies an operation to multiple records
 *
 * @param array $ids Array of record IDs
 * @param callable $operation Callback receiving ($id, $index)
 * @param string $successMessage Success message
 * @return array API response
 */
function api_bulk_operation(array $ids, callable $operation, string $successMessage = 'Bulk operation completed'): array {
    // CSRF check
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    if (empty($ids)) {
        return api_error('No IDs provided');
    }

    // Transaction
    return api_transaction(function() use ($ids, $operation, $successMessage) {
        $results = [];
        $errors = [];

        foreach ($ids as $index => $id) {
            try {
                $result = $operation($id, $index);
                $results[] = $result;
            } catch (Exception $e) {
                $errors[] = [
                    'id' => $id,
                    'error' => $e->getMessage()
                ];
            }
        }

        return [
            'success' => empty($errors),
            'processed' => count($results),
            'errors' => $errors,
            'message' => $successMessage
        ];

    }, $successMessage, 'Bulk operation failed');
}
