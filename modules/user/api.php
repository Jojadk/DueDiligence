<?php
/**
 * User Management Module API
 *
 * Handles user, group, and permission management
 *
 * Actions:
 * - get_users: Get list of users
 * - get_user: Get user details
 * - create_user: Create new user
 * - update_user: Update user
 * - delete_user: Delete user
 * - get_groups: Get list of groups
 * - create_group: Create new group
 * - update_group: Update group
 * - delete_group: Delete group
 * - assign_user_to_group: Assign user to group
 * - remove_user_from_group: Remove user from group
 * - get_group_permissions: Get permissions for a group
 * - set_group_permissions: Set permissions for a group
 * - get_user_permissions: Get permissions for a user
 * - set_user_permissions: Set permission overrides for a user
 * - grant_project_access: Grant project access to user/group
 * - revoke_project_access: Revoke project access
 */

require_once __DIR__ . '/../../core/permissions.php';

/**
 * Get list of users
 * GET ?module=user&action=get_users
 */
function handle_get_users(array $user): array {
    // Get users with their groups
    $users = db_fetch_all("
        SELECT
            u.id,
            u.name,
            u.email,
            u.is_active,
            u.created_at,
            u.last_login,
            COALESCE(
                json_agg(
                    json_build_object(
                        'group_id', pg.id,
                        'group_name', pg.name
                    )
                ) FILTER (WHERE pg.id IS NOT NULL),
                '[]'
            ) as groups
        FROM users u
        LEFT JOIN permission_user_groups pug ON pug.user_id = u.id
        LEFT JOIN permission_groups pg ON pg.id = pug.group_id
        GROUP BY u.id, u.name, u.email, u.is_active, u.created_at, u.last_login
        ORDER BY u.name ASC
    ");

    // Decode JSON groups
    foreach ($users as &$userItem) {
        $userItem['groups'] = json_decode($userItem['groups'], true);
    }

    return [
        'success' => true,
        'users' => $users
    ];
}

/**
 * Get user details
 * GET ?module=user&action=get_user&id=X
 */
function handle_get_user(array $user): array {
    $userId = sanitize_int($_GET['id'] ?? 0);

    if (!$userId) {
        return ['success' => false, 'error' => 'Bruger ID mangler'];
    }

    // Get user with groups
    $userDetails = db_fetch("
        SELECT
            u.id,
            u.name,
            u.email,
            u.is_active,
            u.created_at,
            u.last_login
        FROM users u
        WHERE u.id = :id
    ", ['id' => $userId]);

    if (!$userDetails) {
        return ['success' => false, 'error' => 'Bruger ikke fundet'];
    }

    // Get user groups
    $groups = db_fetch_all("
        SELECT pg.id, pg.name, pg.description
        FROM permission_groups pg
        JOIN permission_user_groups pug ON pug.group_id = pg.id
        WHERE pug.user_id = :user_id
        ORDER BY pg.name ASC
    ", ['user_id' => $userId]);

    $userDetails['groups'] = $groups;

    // Get user's effective permissions
    $permissions = db_fetch_all("
        SELECT DISTINCT
            pm.module_key,
            pm.display_name,
            pp.permission_key
        FROM v_user_effective_permissions uep
        JOIN permission_permissions pp ON pp.id = uep.permission_id
        JOIN permission_modules pm ON pm.id = pp.module_id
        WHERE uep.user_id = :user_id
        ORDER BY pm.module_key, pp.permission_key
    ", ['user_id' => $userId]);

    $userDetails['permissions'] = $permissions;

    // Get project access
    $projectAccess = db_fetch_all("
        SELECT
            p.id as project_id,
            p.name as project_name,
            pp.permission_level
        FROM project_permissions pp
        JOIN projects p ON p.id = pp.project_id
        WHERE pp.entity_type = 'user' AND pp.entity_id = :user_id
        ORDER BY p.name ASC
    ", ['user_id' => $userId]);

    $userDetails['project_access'] = $projectAccess;

    return [
        'success' => true,
        'user' => $userDetails
    ];
}

/**
 * Create new user
 * POST ?module=user&action=create_user
 */
function handle_create_user(array $user): array {
    csrf_require();

    $name = sanitize_string($_POST['name'] ?? '');
    $email = sanitize_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$name || !$email || !$password) {
        return ['success' => false, 'error' => 'Navn, email og password er påkrævet'];
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Ugyldig email'];
    }

    // Check if email already exists
    $existingUser = db_fetch("SELECT id FROM users WHERE email = :email", ['email' => $email]);

    if ($existingUser) {
        return ['success' => false, 'error' => 'Email er allerede i brug'];
    }

    db_begin_transaction();
    try {
        $userData = [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $newUserId = db_insert('users', $userData);

        // Assign to default group if specified
        $defaultGroupId = sanitize_int($_POST['default_group_id'] ?? 0);
        if ($defaultGroupId) {
            db_insert('permission_user_groups', [
                'user_id' => $newUserId,
                'group_id' => $defaultGroupId
            ]);
        }

        db_commit();

        log_activity('user_created', 'user', $newUserId);

        return [
            'success' => true,
            'user_id' => $newUserId,
            'message' => 'Bruger oprettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke oprette bruger'];
    }
}

/**
 * Update user
 * POST ?module=user&action=update_user
 */
function handle_update_user(array $user): array {
    csrf_require();

    $userId = sanitize_int($_POST['id'] ?? 0);

    if (!$userId) {
        return ['success' => false, 'error' => 'Bruger ID mangler'];
    }

    // Get existing user
    $existingUser = db_fetch("SELECT * FROM users WHERE id = :id", ['id' => $userId]);

    if (!$existingUser) {
        return ['success' => false, 'error' => 'Bruger ikke fundet'];
    }

    db_begin_transaction();
    try {
        $updateData = [];

        if (isset($_POST['name'])) {
            $updateData['name'] = sanitize_string($_POST['name']);
        }

        if (isset($_POST['email'])) {
            $email = sanitize_string($_POST['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                db_rollback();
                return ['success' => false, 'error' => 'Ugyldig email'];
            }

            // Check if email is already used by another user
            $emailExists = db_fetch("
                SELECT id FROM users WHERE email = :email AND id != :user_id
            ", ['email' => $email, 'user_id' => $userId]);

            if ($emailExists) {
                db_rollback();
                return ['success' => false, 'error' => 'Email er allerede i brug'];
            }

            $updateData['email'] = $email;
        }

        if (isset($_POST['is_active'])) {
            $updateData['is_active'] = (bool)$_POST['is_active'];
        }

        if (isset($_POST['password']) && !empty($_POST['password'])) {
            $updateData['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
        }

        if (!empty($updateData)) {
            db_update('users', $updateData, 'id = :id', ['id' => $userId]);
        }

        db_commit();

        log_activity('user_updated', 'user', $userId);

        return [
            'success' => true,
            'message' => 'Bruger opdateret succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere bruger'];
    }
}

/**
 * Delete user
 * POST ?module=user&action=delete_user
 */
function handle_delete_user(array $user): array {
    csrf_require();

    $userId = sanitize_int($_POST['id'] ?? 0);

    if (!$userId) {
        return ['success' => false, 'error' => 'Bruger ID mangler'];
    }

    // Don't allow deleting yourself
    if ($userId == $user['id']) {
        return ['success' => false, 'error' => 'Du kan ikke slette dig selv'];
    }

    db_begin_transaction();
    try {
        // Remove from groups
        db_execute("DELETE FROM permission_user_groups WHERE user_id = :id", ['id' => $userId]);

        // Remove user permissions
        db_execute("DELETE FROM permission_user_permissions WHERE user_id = :id", ['id' => $userId]);

        // Remove project permissions
        db_execute("DELETE FROM project_permissions WHERE entity_type = 'user' AND entity_id = :id", ['id' => $userId]);

        // Deactivate instead of deleting (to preserve audit trail)
        db_update('users', ['is_active' => false], 'id = :id', ['id' => $userId]);

        db_commit();

        log_activity('user_deleted', 'user', $userId);

        return [
            'success' => true,
            'message' => 'Bruger deaktiveret succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette bruger'];
    }
}

/**
 * Get list of groups
 * GET ?module=user&action=get_groups
 */
function handle_get_groups(array $user): array {
    $groups = db_fetch_all("
        SELECT
            pg.id,
            pg.name,
            pg.description,
            pg.is_active,
            COUNT(pug.user_id) as user_count
        FROM permission_groups pg
        LEFT JOIN permission_user_groups pug ON pug.group_id = pg.id
        GROUP BY pg.id, pg.name, pg.description, pg.is_active
        ORDER BY pg.name ASC
    ");

    return [
        'success' => true,
        'groups' => $groups
    ];
}

/**
 * Create new group
 * POST ?module=user&action=create_group
 */
function handle_create_group(array $user): array {
    csrf_require();

    $name = sanitize_string($_POST['name'] ?? '');
    $description = sanitize_string($_POST['description'] ?? '');

    if (!$name) {
        return ['success' => false, 'error' => 'Gruppe navn er påkrævet'];
    }

    db_begin_transaction();
    try {
        $groupData = [
            'name' => $name,
            'description' => $description,
            'is_active' => true
        ];

        $groupId = db_insert('permission_groups', $groupData);

        db_commit();

        log_activity('group_created', 'group', $groupId);

        return [
            'success' => true,
            'group_id' => $groupId,
            'message' => 'Gruppe oprettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke oprette gruppe'];
    }
}

/**
 * Update group
 * POST ?module=user&action=update_group
 */
function handle_update_group(array $user): array {
    csrf_require();

    $groupId = sanitize_int($_POST['id'] ?? 0);

    if (!$groupId) {
        return ['success' => false, 'error' => 'Gruppe ID mangler'];
    }

    db_begin_transaction();
    try {
        $updateData = [];

        if (isset($_POST['name'])) {
            $updateData['name'] = sanitize_string($_POST['name']);
        }

        if (isset($_POST['description'])) {
            $updateData['description'] = sanitize_string($_POST['description']);
        }

        if (isset($_POST['is_active'])) {
            $updateData['is_active'] = (bool)$_POST['is_active'];
        }

        if (!empty($updateData)) {
            db_update('permission_groups', $updateData, 'id = :id', ['id' => $groupId]);
        }

        db_commit();

        log_activity('group_updated', 'group', $groupId);

        return [
            'success' => true,
            'message' => 'Gruppe opdateret succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere gruppe'];
    }
}

/**
 * Delete group
 * POST ?module=user&action=delete_group
 */
function handle_delete_group(array $user): array {
    csrf_require();

    $groupId = sanitize_int($_POST['id'] ?? 0);

    if (!$groupId) {
        return ['success' => false, 'error' => 'Gruppe ID mangler'];
    }

    db_begin_transaction();
    try {
        // Remove user assignments
        db_execute("DELETE FROM permission_user_groups WHERE group_id = :id", ['id' => $groupId]);

        // Remove group permissions
        db_execute("DELETE FROM permission_group_permissions WHERE group_id = :id", ['id' => $groupId]);

        // Remove project permissions
        db_execute("DELETE FROM project_permissions WHERE entity_type = 'group' AND entity_id = :id", ['id' => $groupId]);

        // Delete group
        db_delete('permission_groups', 'id = :id', ['id' => $groupId]);

        db_commit();

        log_activity('group_deleted', 'group', $groupId);

        return [
            'success' => true,
            'message' => 'Gruppe slettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette gruppe'];
    }
}

/**
 * Assign user to group
 * POST ?module=user&action=assign_user_to_group
 */
function handle_assign_user_to_group(array $user): array {
    csrf_require();

    $userId = sanitize_int($_POST['user_id'] ?? 0);
    $groupId = sanitize_int($_POST['group_id'] ?? 0);

    if (!$userId || !$groupId) {
        return ['success' => false, 'error' => 'Bruger ID og gruppe ID er påkrævet'];
    }

    db_begin_transaction();
    try {
        // Check if already assigned
        $existing = db_fetch("
            SELECT * FROM permission_user_groups
            WHERE user_id = :user_id AND group_id = :group_id
        ", ['user_id' => $userId, 'group_id' => $groupId]);

        if ($existing) {
            db_rollback();
            return ['success' => false, 'error' => 'Bruger er allerede i gruppen'];
        }

        db_insert('permission_user_groups', [
            'user_id' => $userId,
            'group_id' => $groupId
        ]);

        db_commit();

        log_activity('user_assigned_to_group', 'user', $userId);

        return [
            'success' => true,
            'message' => 'Bruger tilføjet til gruppe'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke tilføje bruger til gruppe'];
    }
}

/**
 * Remove user from group
 * POST ?module=user&action=remove_user_from_group
 */
function handle_remove_user_from_group(array $user): array {
    csrf_require();

    $userId = sanitize_int($_POST['user_id'] ?? 0);
    $groupId = sanitize_int($_POST['group_id'] ?? 0);

    if (!$userId || !$groupId) {
        return ['success' => false, 'error' => 'Bruger ID og gruppe ID er påkrævet'];
    }

    db_begin_transaction();
    try {
        db_execute("
            DELETE FROM permission_user_groups
            WHERE user_id = :user_id AND group_id = :group_id
        ", ['user_id' => $userId, 'group_id' => $groupId]);

        db_commit();

        log_activity('user_removed_from_group', 'user', $userId);

        return [
            'success' => true,
            'message' => 'Bruger fjernet fra gruppe'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke fjerne bruger fra gruppe'];
    }
}

/**
 * Get permissions for a group
 * GET ?module=user&action=get_group_permissions&group_id=X
 */
function handle_get_group_permissions(array $user): array {
    $groupId = sanitize_int($_GET['group_id'] ?? 0);

    if (!$groupId) {
        return ['success' => false, 'error' => 'Gruppe ID mangler'];
    }

    $permissions = db_fetch_all("
        SELECT
            pm.id as module_id,
            pm.module_key,
            pm.display_name,
            pp.id as permission_id,
            pp.permission_key,
            CASE WHEN pgp.group_id IS NOT NULL THEN true ELSE false END as granted
        FROM permission_modules pm
        CROSS JOIN permission_permissions pp
        LEFT JOIN permission_group_permissions pgp ON
            pgp.group_id = :group_id AND
            pgp.permission_id = pp.id
        WHERE pp.module_id = pm.id
        ORDER BY pm.module_key, pp.permission_key
    ", ['group_id' => $groupId]);

    return [
        'success' => true,
        'permissions' => $permissions
    ];
}

/**
 * Set permissions for a group
 * POST ?module=user&action=set_group_permissions
 */
function handle_set_group_permissions(array $user): array {
    csrf_require();

    $groupId = sanitize_int($_POST['group_id'] ?? 0);
    $permissions = $_POST['permissions'] ?? [];

    if (!$groupId || !is_array($permissions)) {
        return ['success' => false, 'error' => 'Gruppe ID og rettigheder er påkrævet'];
    }

    db_begin_transaction();
    try {
        // Remove all existing permissions for group
        db_execute("DELETE FROM permission_group_permissions WHERE group_id = :id", ['id' => $groupId]);

        // Add new permissions
        foreach ($permissions as $permissionId) {
            $permissionId = sanitize_int($permissionId);
            if ($permissionId > 0) {
                db_insert('permission_group_permissions', [
                    'group_id' => $groupId,
                    'permission_id' => $permissionId
                ]);
            }
        }

        db_commit();

        log_activity('group_permissions_updated', 'group', $groupId);

        return [
            'success' => true,
            'message' => 'Gruppe rettigheder opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rettigheder'];
    }
}

/**
 * Grant project access to user or group
 * POST ?module=user&action=grant_project_access
 */
function handle_grant_project_access(array $user): array {
    csrf_require();

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $entityType = sanitize_string($_POST['entity_type'] ?? ''); // 'user' or 'group'
    $entityId = sanitize_int($_POST['entity_id'] ?? 0);
    $permissionLevel = sanitize_string($_POST['permission_level'] ?? 'viewer'); // owner, editor, viewer

    if (!$projectId || !$entityType || !$entityId) {
        return ['success' => false, 'error' => 'Projekt ID, entity type og entity ID er påkrævet'];
    }

    if (!in_array($entityType, ['user', 'group'])) {
        return ['success' => false, 'error' => 'Ugyldig entity type'];
    }

    if (!in_array($permissionLevel, ['owner', 'editor', 'viewer', 'none'])) {
        return ['success' => false, 'error' => 'Ugyldig rettigheds niveau'];
    }

    // Check if current user has owner access to project
    if (!can_access_project($user, $projectId, 'owner')) {
        return ['success' => false, 'error' => 'Kun projekt ejere kan tildele adgang'];
    }

    db_begin_transaction();
    try {
        // Check if permission already exists
        $existing = db_fetch("
            SELECT id FROM project_permissions
            WHERE project_id = :project_id AND entity_type = :entity_type AND entity_id = :entity_id
        ", ['project_id' => $projectId, 'entity_type' => $entityType, 'entity_id' => $entityId]);

        if ($existing) {
            // Update existing
            db_update('project_permissions',
                ['permission_level' => $permissionLevel, 'granted_by_user_id' => $user['id']],
                'id = :id',
                ['id' => $existing['id']]
            );
        } else {
            // Insert new
            db_insert('project_permissions', [
                'project_id' => $projectId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'permission_level' => $permissionLevel,
                'granted_by_user_id' => $user['id']
            ]);
        }

        db_commit();

        log_activity('project_access_granted', 'project', $projectId);

        return [
            'success' => true,
            'message' => 'Projekt adgang tildelt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke tildele projekt adgang'];
    }
}

/**
 * Revoke project access
 * POST ?module=user&action=revoke_project_access
 */
function handle_revoke_project_access(array $user): array {
    csrf_require();

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $entityType = sanitize_string($_POST['entity_type'] ?? '');
    $entityId = sanitize_int($_POST['entity_id'] ?? 0);

    if (!$projectId || !$entityType || !$entityId) {
        return ['success' => false, 'error' => 'Projekt ID, entity type og entity ID er påkrævet'];
    }

    // Check if current user has owner access to project
    if (!can_access_project($user, $projectId, 'owner')) {
        return ['success' => false, 'error' => 'Kun projekt ejere kan fjerne adgang'];
    }

    db_begin_transaction();
    try {
        db_execute("
            DELETE FROM project_permissions
            WHERE project_id = :project_id AND entity_type = :entity_type AND entity_id = :entity_id
        ", ['project_id' => $projectId, 'entity_type' => $entityType, 'entity_id' => $entityId]);

        db_commit();

        log_activity('project_access_revoked', 'project', $projectId);

        return [
            'success' => true,
            'message' => 'Projekt adgang fjernet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke fjerne projekt adgang'];
    }
}
