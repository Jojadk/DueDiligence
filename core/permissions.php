<?php
/**
 * Dynamic Permission System - Database-driven permissions
 *
 * Replaces hardcoded roles with:
 * - Groups (teams)
 * - Module-based permissions
 * - Project-level permissions
 * - User and group inheritance
 */

/**
 * Check if user has a specific module permission
 *
 * @param array $user Current user
 * @param string $module Module key (e.g., 'project', 'opex', 'dashboard')
 * @param string $permission Permission key (e.g., 'view', 'create', 'edit', 'delete')
 * @return bool
 */
function has_module_permission(array $user, string $module, string $permission): bool {
    static $cache = [];

    $cacheKey = "{$user['id']}:{$module}:{$permission}";

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    // Use database function for permission check
    $result = db_value("
        SELECT user_has_permission(:user_id, :module_key, :permission_key)
    ", [
        'user_id' => $user['id'],
        'module_key' => $module,
        'permission_key' => $permission
    ]);

    $cache[$cacheKey] = (bool)$result;

    // Log permission check for audit
    if (ENABLE_PERMISSION_AUDIT ?? false) {
        log_permission_check($user['id'], $module, $permission, $cache[$cacheKey]);
    }

    return $cache[$cacheKey];
}

/**
 * Check if user has ANY of the specified permissions for a module
 *
 * @param array $user Current user
 * @param string $module Module key
 * @param array $permissions Array of permission keys
 * @return bool
 */
function has_any_module_permission(array $user, string $module, array $permissions): bool {
    foreach ($permissions as $permission) {
        if (has_module_permission($user, $module, $permission)) {
            return true;
        }
    }
    return false;
}

/**
 * Check if user has ALL of the specified permissions for a module
 *
 * @param array $user Current user
 * @param string $module Module key
 * @param array $permissions Array of permission keys
 * @return bool
 */
function has_all_module_permissions(array $user, string $module, array $permissions): bool {
    foreach ($permissions as $permission) {
        if (!has_module_permission($user, $module, $permission)) {
            return false;
        }
    }
    return true;
}

/**
 * Get user's permission level for a project
 *
 * @param array $user Current user
 * @param int $projectId Project ID
 * @return string 'owner', 'editor', 'viewer', or 'none'
 */
function get_project_permission(array $user, int $projectId): string {
    static $cache = [];

    $cacheKey = "{$user['id']}:{$projectId}";

    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }

    $level = db_value("
        SELECT user_project_access(:user_id, :project_id)
    ", [
        'user_id' => $user['id'],
        'project_id' => $projectId
    ]);

    $cache[$cacheKey] = $level ?? 'none';

    return $cache[$cacheKey];
}

/**
 * Check if user can access project with minimum permission level
 *
 * @param array $user Current user
 * @param int $projectId Project ID
 * @param string $minLevel Minimum required level ('owner', 'editor', or 'viewer')
 * @return bool
 */
function can_access_project(array $user, int $projectId, string $minLevel = 'viewer'): bool {
    $userLevel = get_project_permission($user, $projectId);

    $levels = ['none' => 0, 'viewer' => 1, 'editor' => 2, 'owner' => 3];

    $userLevelValue = $levels[$userLevel] ?? 0;
    $minLevelValue = $levels[$minLevel] ?? 1;

    return $userLevelValue >= $minLevelValue;
}

/**
 * Get all projects user has access to
 *
 * @param array $user Current user
 * @param string $minLevel Minimum permission level required
 * @return array Array of project IDs with permission levels
 */
function get_accessible_projects(array $user, string $minLevel = 'viewer'): array {
    $projects = db_fetch_all("
        SELECT project_id, permission_level
        FROM user_accessible_projects(:user_id)
    ", ['user_id' => $user['id']]);

    if ($minLevel === 'viewer') {
        return $projects;
    }

    // Filter by minimum level
    $levels = ['viewer' => 1, 'editor' => 2, 'owner' => 3];
    $minValue = $levels[$minLevel] ?? 1;

    return array_filter($projects, function($p) use ($levels, $minValue) {
        $pValue = $levels[$p['permission_level']] ?? 0;
        return $pValue >= $minValue;
    });
}

/**
 * Get user's groups
 *
 * @param int $userId User ID
 * @return array Array of group records
 */
function get_user_groups(int $userId): array {
    return db_fetch_all("
        SELECT g.*
        FROM groups g
        JOIN user_groups ug ON g.id = ug.group_id
        WHERE ug.user_id = :user_id
        AND g.is_active = true
        ORDER BY g.name
    ", ['user_id' => $userId]);
}

/**
 * Get all effective permissions for user
 *
 * @param int $userId User ID
 * @return array Array of permissions with module info
 */
function get_user_permissions(int $userId): array {
    return db_fetch_all("
        SELECT DISTINCT
            module_key,
            permission_key,
            module_name,
            permission_name,
            has_permission
        FROM v_user_effective_permissions
        WHERE user_id = :user_id
        AND has_permission = true
        ORDER BY module_key, permission_key
    ", ['user_id' => $userId]);
}

/**
 * Grant permission to user
 *
 * @param int $userId User ID to grant permission to
 * @param string $module Module key
 * @param string $permission Permission key
 * @param int $grantedBy User ID granting the permission
 * @return bool Success
 */
function grant_user_permission(int $userId, string $module, string $permission, int $grantedBy): bool {
    // Get permission ID
    $permissionId = db_value("
        SELECT p.id
        FROM permissions p
        JOIN modules m ON p.module_id = m.id
        WHERE m.module_key = :module_key
        AND p.permission_key = :permission_key
    ", ['module_key' => $module, 'permission_key' => $permission]);

    if (!$permissionId) {
        return false;
    }

    // Insert or update user permission
    db_query("
        INSERT INTO user_permissions (user_id, permission_id, granted, created_by)
        VALUES (:user_id, :permission_id, true, :created_by)
        ON CONFLICT (user_id, permission_id)
        DO UPDATE SET granted = true, created_by = :created_by
    ", [
        'user_id' => $userId,
        'permission_id' => $permissionId,
        'created_by' => $grantedBy
    ]);

    log_permission_change($userId, 'grant', $module, $permission, $grantedBy);

    return true;
}

/**
 * Revoke permission from user
 *
 * @param int $userId User ID to revoke permission from
 * @param string $module Module key
 * @param string $permission Permission key
 * @param int $revokedBy User ID revoking the permission
 * @return bool Success
 */
function revoke_user_permission(int $userId, string $module, string $permission, int $revokedBy): bool {
    $permissionId = db_value("
        SELECT p.id
        FROM permissions p
        JOIN modules m ON p.module_id = m.id
        WHERE m.module_key = :module_key
        AND p.permission_key = :permission_key
    ", ['module_key' => $module, 'permission_key' => $permission]);

    if (!$permissionId) {
        return false;
    }

    db_query("
        INSERT INTO user_permissions (user_id, permission_id, granted, created_by)
        VALUES (:user_id, :permission_id, false, :created_by)
        ON CONFLICT (user_id, permission_id)
        DO UPDATE SET granted = false, created_by = :created_by
    ", [
        'user_id' => $userId,
        'permission_id' => $permissionId,
        'created_by' => $revokedBy
    ]);

    log_permission_change($userId, 'revoke', $module, $permission, $revokedBy);

    return true;
}

/**
 * Grant project access to user or group
 *
 * @param int $projectId Project ID
 * @param string $entityType 'user' or 'group'
 * @param int $entityId User ID or Group ID
 * @param string $level Permission level ('owner', 'editor', 'viewer')
 * @param int $grantedBy User ID granting access
 * @return bool Success
 */
function grant_project_access(int $projectId, string $entityType, int $entityId, string $level, int $grantedBy): bool {
    if (!in_array($entityType, ['user', 'group']) || !in_array($level, ['owner', 'editor', 'viewer'])) {
        return false;
    }

    db_query("
        INSERT INTO project_permissions (project_id, entity_type, entity_id, permission_level, granted_by)
        VALUES (:project_id, :entity_type, :entity_id, :permission_level, :granted_by)
        ON CONFLICT (project_id, entity_type, entity_id)
        DO UPDATE SET permission_level = :permission_level, granted_by = :granted_by
    ", [
        'project_id' => $projectId,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'permission_level' => $level,
        'granted_by' => $grantedBy
    ]);

    log_activity('project_access_granted', 'project', $projectId, [
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'level' => $level
    ]);

    return true;
}

/**
 * Revoke project access from user or group
 *
 * @param int $projectId Project ID
 * @param string $entityType 'user' or 'group'
 * @param int $entityId User ID or Group ID
 * @return bool Success
 */
function revoke_project_access(int $projectId, string $entityType, int $entityId): bool {
    db_query("
        DELETE FROM project_permissions
        WHERE project_id = :project_id
        AND entity_type = :entity_type
        AND entity_id = :entity_id
    ", [
        'project_id' => $projectId,
        'entity_type' => $entityType,
        'entity_id' => $entityId
    ]);

    log_activity('project_access_revoked', 'project', $projectId, [
        'entity_type' => $entityType,
        'entity_id' => $entityId
    ]);

    return true;
}

/**
 * Add user to group
 *
 * @param int $userId User ID
 * @param int $groupId Group ID
 * @param int $assignedBy User ID assigning to group
 * @return bool Success
 */
function add_user_to_group(int $userId, int $groupId, int $assignedBy): bool {
    try {
        db_insert('user_groups', [
            'user_id' => $userId,
            'group_id' => $groupId,
            'assigned_by' => $assignedBy
        ]);

        log_activity('user_added_to_group', 'group', $groupId, [
            'user_id' => $userId,
            'assigned_by' => $assignedBy
        ]);

        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Remove user from group
 *
 * @param int $userId User ID
 * @param int $groupId Group ID
 * @return bool Success
 */
function remove_user_from_group(int $userId, int $groupId): bool {
    db_query("
        DELETE FROM user_groups
        WHERE user_id = :user_id AND group_id = :group_id
    ", ['user_id' => $userId, 'group_id' => $groupId]);

    log_activity('user_removed_from_group', 'group', $groupId, [
        'user_id' => $userId
    ]);

    return true;
}

/**
 * Log permission check for audit
 *
 * @param int $userId User ID
 * @param string $module Module key
 * @param string $permission Permission key
 * @param bool $granted Whether permission was granted
 */
function log_permission_check(int $userId, string $module, string $permission, bool $granted): void {
    $permissionId = db_value("
        SELECT p.id
        FROM permissions p
        JOIN modules m ON p.module_id = m.id
        WHERE m.module_key = :module_key
        AND p.permission_key = :permission_key
    ", ['module_key' => $module, 'permission_key' => $permission]);

    if ($permissionId) {
        db_insert('permission_audit_log', [
            'user_id' => $userId,
            'action' => 'check',
            'permission_id' => $permissionId,
            'granted' => $granted,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

/**
 * Log permission change for audit
 *
 * @param int $userId User ID affected
 * @param string $action 'grant' or 'revoke'
 * @param string $module Module key
 * @param string $permission Permission key
 * @param int $changedBy User ID making the change
 */
function log_permission_change(int $userId, string $action, string $module, string $permission, int $changedBy): void {
    $permissionId = db_value("
        SELECT p.id
        FROM permissions p
        JOIN modules m ON p.module_id = m.id
        WHERE m.module_key = :module_key
        AND p.permission_key = :permission_key
    ", ['module_key' => $module, 'permission_key' => $permission]);

    if ($permissionId) {
        db_insert('permission_audit_log', [
            'user_id' => $changedBy,
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => $userId,
            'permission_id' => $permissionId,
            'granted' => ($action === 'grant'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}

/**
 * BACKWARD COMPATIBILITY: Map old has_permission() calls to new system
 *
 * @deprecated Use has_module_permission() instead
 */
function has_permission(array $user, string $permission): bool {
    // Map old permission names to new module.permission format
    $mapping = [
        'admin' => ['admin', 'view'], // Admin can view admin module
        'view_customers' => ['project', 'view'],
        'create_projects' => ['project', 'create'],
        'edit_projects' => ['project', 'edit'],
        'delete_projects' => ['project', 'delete'],
        'edit_buildings' => ['building', 'edit'],
        'edit_elements' => ['element', 'edit'],
        'upload_images' => ['element', 'edit'],
        'create_snapshots' => ['project', 'edit'],
        'restore_snapshots' => ['project', 'edit'],
        'copy_projects' => ['project', 'create'],
        'edit_prices' => ['budget', 'edit'],
    ];

    if (isset($mapping[$permission])) {
        list($module, $perm) = $mapping[$permission];
        return has_module_permission($user, $module, $perm);
    }

    // Unknown permission - deny by default
    return false;
}

/**
 * Check if user owns project (backward compatibility)
 *
 * @deprecated Use can_access_project() instead
 */
function user_owns_project(int $userId, int $projectId): bool {
    $user = ['id' => $userId];
    return can_access_project($user, $projectId, 'editor');
}
