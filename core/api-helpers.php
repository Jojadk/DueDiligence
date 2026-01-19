<?php
/**
 * API Helper Functions
 *
 * Consolidates common patterns used across all module APIs:
 * - Input validation and sanitization
 * - Permission checking
 * - Error response formatting
 * - Transaction management
 * - Success response formatting
 *
 * Usage: require_once __DIR__ . '/api-helpers.php';
 */

/**
 * Validate and extract required parameters from request
 *
 * @param array $rules Array of param name => [type, source, required, default]
 *                     Example: ['id' => ['int', 'GET', true], 'name' => ['string', 'POST', true]]
 * @return array ['success' => bool, 'data' => array, 'errors' => array]
 */
function api_validate_params(array $rules): array {
    $data = [];
    $errors = [];

    foreach ($rules as $param => $config) {
        [$type, $source, $required, $default] = array_pad($config, 4, null);

        $sourceData = $source === 'GET' ? $_GET : $_POST;
        $value = $sourceData[$param] ?? $default;

        // Check if required parameter is missing
        if ($required && ($value === null || $value === '')) {
            $errors[] = ucfirst($param) . " er påkrævet";
            continue;
        }

        // Sanitize based on type
        switch ($type) {
            case 'int':
                $data[$param] = sanitize_int($value);
                break;
            case 'float':
                $data[$param] = sanitize_float($value);
                break;
            case 'string':
                $data[$param] = sanitize_string($value);
                break;
            case 'email':
                $data[$param] = sanitize_email($value);
                break;
            case 'date':
                $data[$param] = $value; // Assume already validated
                break;
            case 'array':
                $data[$param] = is_array($value) ? $value : [];
                break;
            default:
                $data[$param] = $value;
        }
    }

    return [
        'success' => empty($errors),
        'data' => $data,
        'errors' => $errors
    ];
}

/**
 * Get entity by ID with permission check
 *
 * @param string $table Table name
 * @param int $id Entity ID
 * @param array $user Current user
 * @param callable|null $permissionCheck Function(entity, user) => bool
 * @param string $notFoundMessage Error message if not found
 * @return array ['success' => bool, 'data' => array|null, 'error' => string|null]
 */
function api_get_entity(string $table, int $id, array $user, ?callable $permissionCheck = null, string $notFoundMessage = 'Entity not found'): array {
    if (!$id) {
        return ['success' => false, 'error' => 'ID mangler'];
    }

    $entity = db_fetch("SELECT * FROM {$table} WHERE id = :id", ['id' => $id]);

    if (!$entity) {
        return ['success' => false, 'error' => $notFoundMessage];
    }

    // Check permissions if callback provided
    if ($permissionCheck && !$permissionCheck($entity, $user)) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    return ['success' => true, 'data' => $entity];
}

/**
 * Check project access and return error if denied
 *
 * @param array $user Current user
 * @param int $projectId Project ID
 * @param string $level Required permission level ('viewer', 'editor', 'admin')
 * @return array|null Returns error array if access denied, null if access granted
 */
function api_require_project_access(array $user, int $projectId, string $level = 'viewer'): ?array {
    if (!can_access_project($user, $projectId, $level)) {
        return ['success' => false, 'error' => 'Ingen adgang til projektet'];
    }
    return null;
}

/**
 * Check building access via project and return error if denied
 *
 * @param array $user Current user
 * @param int $buildingId Building ID
 * @param string $level Required permission level
 * @return array|null Returns error array if denied, null if granted
 */
function api_require_building_access(array $user, int $buildingId, string $level = 'viewer'): ?array {
    $building = db_fetch("SELECT project_id FROM buildings WHERE id = :id", ['id' => $buildingId]);

    if (!$building) {
        return ['success' => false, 'error' => 'Bygning ikke fundet'];
    }

    return api_require_project_access($user, $building['project_id'], $level);
}

/**
 * Execute database operation with automatic transaction management
 *
 * @param callable $operation Function to execute within transaction
 * @param string $successMessage Success message
 * @param string $errorMessage Error message on failure
 * @return array ['success' => bool, 'message' => string, ...]
 */
function api_transaction(callable $operation, string $successMessage = 'Operation successful', string $errorMessage = 'Operation failed'): array {
    db_begin_transaction();

    try {
        $result = $operation();
        db_commit();

        // If operation returns array, merge with success response
        if (is_array($result)) {
            return array_merge(['success' => true, 'message' => $successMessage], $result);
        }

        return ['success' => true, 'message' => $successMessage];

    } catch (Exception $e) {
        db_rollback();

        // Log error for debugging
        if (function_exists('log_error')) {
            log_error($e->getMessage(), ['trace' => $e->getTraceAsString()]);
        }

        return ['success' => false, 'error' => $errorMessage];
    }
}

/**
 * Create standardized success response
 *
 * @param mixed $data Data to return
 * @param string $message Success message
 * @return array
 */
function api_success($data = null, string $message = ''): array {
    $response = ['success' => true];

    if ($message) {
        $response['message'] = $message;
    }

    if ($data !== null) {
        // If data is array with single key, use that key
        if (is_array($data) && count($data) === 1) {
            $key = array_keys($data)[0];
            $response[$key] = $data[$key];
        } else {
            $response['data'] = $data;
        }
    }

    return $response;
}

/**
 * Create standardized error response
 *
 * @param string|array $error Error message or array of messages
 * @return array
 */
function api_error($error): array {
    if (is_array($error)) {
        return ['success' => false, 'errors' => $error];
    }

    return ['success' => false, 'error' => $error];
}

/**
 * Require CSRF token and return error if invalid
 *
 * @return array|null Returns error array if invalid, null if valid
 */
function api_require_csrf(): ?array {
    try {
        csrf_require();
        return null;
    } catch (Exception $e) {
        return api_error('CSRF token invalid');
    }
}

/**
 * Standard CRUD: Get single entity
 * Combines entity fetch, existence check, and permission check
 *
 * @param string $table Table name
 * @param array $user Current user
 * @param callable|null $permissionCheck Permission check function
 * @return array
 */
function api_crud_get(string $table, array $user, ?callable $permissionCheck = null): array {
    $id = sanitize_int($_GET['id'] ?? 0);
    $result = api_get_entity($table, $id, $user, $permissionCheck);

    if (!$result['success']) {
        return $result;
    }

    return api_success([$table => $result['data']]);
}

/**
 * Standard CRUD: Create entity
 *
 * @param string $table Table name
 * @param array $data Data to insert
 * @param callable|null $beforeInsert Callback before insert (for validation)
 * @param callable|null $afterInsert Callback after insert (for logging)
 * @return array
 */
function api_crud_create(string $table, array $data, ?callable $beforeInsert = null, ?callable $afterInsert = null): array {
    if ($error = api_require_csrf()) {
        return $error;
    }

    return api_transaction(function() use ($table, $data, $beforeInsert, $afterInsert) {
        // Pre-insert callback
        if ($beforeInsert) {
            $result = $beforeInsert($data);
            if (is_array($result) && !$result['success']) {
                throw new Exception($result['error']);
            }
        }

        // Add timestamps
        $data['created_at'] = $data['created_at'] ?? date('Y-m-d H:i:s');
        $data['updated_at'] = $data['updated_at'] ?? date('Y-m-d H:i:s');

        $id = db_insert($table, $data);

        // Post-insert callback
        if ($afterInsert) {
            $afterInsert($id, $data);
        }

        return ['id' => $id];

    }, 'Oprettet succesfuldt', 'Kunne ikke oprette');
}

/**
 * Standard CRUD: Update entity
 *
 * @param string $table Table name
 * @param int $id Entity ID
 * @param array $data Data to update
 * @param callable|null $permissionCheck Permission check function
 * @param callable|null $afterUpdate Callback after update
 * @return array
 */
function api_crud_update(string $table, int $id, array $data, ?callable $permissionCheck = null, ?callable $afterUpdate = null): array {
    if ($error = api_require_csrf()) {
        return $error;
    }

    return api_transaction(function() use ($table, $id, $data, $permissionCheck, $afterUpdate) {
        // Check entity exists and permissions
        $entity = db_fetch("SELECT * FROM {$table} WHERE id = :id", ['id' => $id]);

        if (!$entity) {
            throw new Exception('Entity not found');
        }

        if ($permissionCheck && !$permissionCheck($entity)) {
            throw new Exception('No access');
        }

        // Add updated timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');

        db_update($table, $data, ['id' => $id]);

        // Post-update callback
        if ($afterUpdate) {
            $afterUpdate($id, $data, $entity);
        }

        return [];

    }, 'Opdateret succesfuldt', 'Kunne ikke opdatere');
}

/**
 * Standard CRUD: Delete entity
 *
 * @param string $table Table name
 * @param int $id Entity ID
 * @param callable|null $permissionCheck Permission check function
 * @param callable|null $beforeDelete Callback before delete (for cascade checks)
 * @return array
 */
function api_crud_delete(string $table, int $id, ?callable $permissionCheck = null, ?callable $beforeDelete = null): array {
    if ($error = api_require_csrf()) {
        return $error;
    }

    return api_transaction(function() use ($table, $id, $permissionCheck, $beforeDelete) {
        // Check entity exists and permissions
        $entity = db_fetch("SELECT * FROM {$table} WHERE id = :id", ['id' => $id]);

        if (!$entity) {
            throw new Exception('Entity not found');
        }

        if ($permissionCheck && !$permissionCheck($entity)) {
            throw new Exception('No access');
        }

        // Pre-delete callback
        if ($beforeDelete) {
            $result = $beforeDelete($id, $entity);
            if (is_array($result) && !$result['success']) {
                throw new Exception($result['error']);
            }
        }

        db_delete($table, ['id' => $id]);

        return [];

    }, 'Slettet succesfuldt', 'Kunne ikke slette');
}

/**
 * Get list with pagination, search, and filtering
 *
 * @param string $query Base SQL query
 * @param array $filters Array of filter definitions
 * @param array $params Default query parameters
 * @return array
 */
function api_get_list(string $query, array $filters = [], array $params = []): array {
    $limit = sanitize_int($_GET['limit'] ?? 50);
    $offset = sanitize_int($_GET['offset'] ?? 0);
    $search = sanitize_string($_GET['search'] ?? '');

    $where = [];

    // Apply filters
    foreach ($filters as $filterName => $filterConfig) {
        $value = $_GET[$filterName] ?? null;

        if ($value !== null && $value !== '') {
            $where[] = $filterConfig['condition'];
            $params[$filterName] = $filterConfig['sanitize']($value);
        }
    }

    // Apply search if provided
    if ($search && isset($filters['_search'])) {
        $where[] = $filters['_search']['condition'];
        $params['search'] = "%{$search}%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $fullQuery = str_replace('__WHERE__', $whereClause, $query);

    // Add pagination
    $params['limit'] = $limit;
    $params['offset'] = $offset;

    $items = db_fetch_all($fullQuery, $params);

    // Get total count
    $countQuery = preg_replace('/SELECT .* FROM/i', 'SELECT COUNT(*) FROM', $query);
    $countQuery = str_replace('__WHERE__', $whereClause, $countQuery);
    $countQuery = preg_replace('/ORDER BY .*/i', '', $countQuery);
    $total = db_value($countQuery, array_diff_key($params, ['limit' => '', 'offset' => '']));

    return api_success([
        'items' => $items,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}
