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
require_once __DIR__ . '/../../core/api-helpers.php';

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
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $userId = $validation['data']['id'];

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
        return api_error('Bruger ikke fundet');
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
function handle_create_user(array $currentUser): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'email' => ['string', 'POST', true],
        'password' => ['string', 'POST', true],
        'default_group_id' => ['int', 'POST', false, 0]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // Validate email
    if (!filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
        return api_error('Ugyldig email');
    }

    // Check if email already exists
    $existingUser = db_fetch("SELECT id FROM users WHERE email = :email", ['email' => $params['email']]);
    if ($existingUser) {
        return api_error('Email er allerede i brug');
    }

    return api_transaction(
        function() use ($params) {
            $userData = [
                'name' => $params['name'],
                'email' => $params['email'],
                'password' => password_hash($params['password'], PASSWORD_DEFAULT),
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $newUserId = db_insert('users', $userData);

            // Assign to default group if specified
            if ($params['default_group_id']) {
                db_insert('permission_user_groups', [
                    'user_id' => $newUserId,
                    'group_id' => $params['default_group_id']
                ]);
            }

            log_activity('user_created', 'user', $newUserId);

            return ['user_id' => $newUserId];
        },
        'Bruger oprettet succesfuldt',
        'Kunne ikke oprette bruger'
    );
}

/**
 * Update user
 * POST ?module=user&action=update_user
 */
function handle_update_user(array $currentUser): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'name' => ['string', 'POST', false],
        'email' => ['string', 'POST', false],
        'is_active' => ['bool', 'POST', false],
        'password' => ['string', 'POST', false]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $userId = $params['id'];

    // Build update data with validation
    $updateData = [];

    if (isset($params['name'])) {
        $updateData['name'] = $params['name'];
    }

    if (isset($params['email'])) {
        if (!filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
            return api_error('Ugyldig email');
        }

        // Check if email is already used by another user
        $emailExists = db_fetch("
            SELECT id FROM users WHERE email = :email AND id != :user_id
        ", ['email' => $params['email'], 'user_id' => $userId]);

        if ($emailExists) {
            return api_error('Email er allerede i brug');
        }

        $updateData['email'] = $params['email'];
    }

    if (isset($params['is_active'])) {
        $updateData['is_active'] = $params['is_active'];
    }

    if (isset($params['password']) && !empty($params['password'])) {
        $updateData['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
    }

    if (empty($updateData)) {
        return api_error('Ingen data at opdatere');
    }

    return api_crud_update(
        'users',
        $userId,
        $updateData,
        null,
        function($id) {
            log_activity('user_updated', 'user', $id);
        }
    );
}

/**
 * Delete user
 * POST ?module=user&action=delete_user
 */
function handle_delete_user(array $currentUser): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $userId = $validation['data']['id'];

    // Don't allow deleting yourself
    if ($userId == $currentUser['id']) {
        return api_error('Du kan ikke slette dig selv');
    }

    return api_transaction(
        function() use ($userId) {
            // Remove from groups
            db_execute("DELETE FROM permission_user_groups WHERE user_id = :id", ['id' => $userId]);

            // Remove user permissions
            db_execute("DELETE FROM permission_user_permissions WHERE user_id = :id", ['id' => $userId]);

            // Remove project permissions
            db_execute("DELETE FROM project_permissions WHERE entity_type = 'user' AND entity_id = :id", ['id' => $userId]);

            // Deactivate instead of deleting (to preserve audit trail)
            db_update('users', ['is_active' => false], 'id = :id', ['id' => $userId]);

            log_activity('user_deleted', 'user', $userId);
        },
        'Bruger deaktiveret succesfuldt',
        'Kunne ikke slette bruger'
    );
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
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'description' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    return api_crud_create(
        'permission_groups',
        [
            'name' => $params['name'],
            'description' => $params['description'],
            'is_active' => true
        ],
        null,
        function($groupId) {
            log_activity('group_created', 'group', $groupId);
        }
    );
}

/**
 * Update group
 * POST ?module=user&action=update_group
 */
function handle_update_group(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'name' => ['string', 'POST', false],
        'description' => ['string', 'POST', false],
        'is_active' => ['bool', 'POST', false]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $groupId = $params['id'];

    $updateData = [];
    if (isset($params['name'])) {
        $updateData['name'] = $params['name'];
    }
    if (isset($params['description'])) {
        $updateData['description'] = $params['description'];
    }
    if (isset($params['is_active'])) {
        $updateData['is_active'] = $params['is_active'];
    }

    if (empty($updateData)) {
        return api_error('Ingen opdateringer');
    }

    return api_crud_update(
        'permission_groups',
        $groupId,
        $updateData,
        null,
        function($id) {
            log_activity('group_updated', 'group', $id);
        }
    );
}

/**
 * Delete group
 * POST ?module=user&action=delete_group
 */
function handle_delete_group(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $groupId = $validation['data']['id'];

    return api_transaction(
        function() use ($groupId) {
            // Remove user assignments
            db_execute("DELETE FROM permission_user_groups WHERE group_id = :id", ['id' => $groupId]);

            // Remove group permissions
            db_execute("DELETE FROM permission_group_permissions WHERE group_id = :id", ['id' => $groupId]);

            // Remove project permissions
            db_execute("DELETE FROM project_permissions WHERE entity_type = 'group' AND entity_id = :id", ['id' => $groupId]);

            // Delete group
            db_delete('permission_groups', 'id = :id', ['id' => $groupId]);

            log_activity('group_deleted', 'group', $groupId);
        },
        'Gruppe slettet succesfuldt',
        'Kunne ikke slette gruppe'
    );
}

/**
 * Assign user to group
 * POST ?module=user&action=assign_user_to_group
 */
function handle_assign_user_to_group(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'user_id' => ['int', 'POST', true],
        'group_id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    return api_crud_create(
        'permission_user_groups',
        [
            'user_id' => $params['user_id'],
            'group_id' => $params['group_id']
        ],
        function($data) {
            // Check if already assigned
            $existing = db_fetch("
                SELECT * FROM permission_user_groups
                WHERE user_id = :user_id AND group_id = :group_id
            ", ['user_id' => $data['user_id'], 'group_id' => $data['group_id']]);

            if ($existing) {
                throw new Exception('Bruger er allerede i gruppen');
            }
        },
        function($id) use ($params) {
            log_activity('user_assigned_to_group', 'user', $params['user_id']);
        }
    );
}

/**
 * Remove user from group
 * POST ?module=user&action=remove_user_from_group
 */
function handle_remove_user_from_group(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'user_id' => ['int', 'POST', true],
        'group_id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    return api_transaction(
        function() use ($params) {
            db_execute("
                DELETE FROM permission_user_groups
                WHERE user_id = :user_id AND group_id = :group_id
            ", ['user_id' => $params['user_id'], 'group_id' => $params['group_id']]);

            log_activity('user_removed_from_group', 'user', $params['user_id']);
        },
        'Bruger fjernet fra gruppe',
        'Kunne ikke fjerne bruger fra gruppe'
    );
}

/**
 * Get permissions for a group
 * GET ?module=user&action=get_group_permissions&group_id=X
 */
function handle_get_group_permissions(array $user): array {
    $validation = api_validate_params([
        'group_id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $groupId = $validation['data']['group_id'];

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
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'group_id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $groupId = $validation['data']['group_id'];
    $permissions = $_POST['permissions'] ?? [];

    if (!is_array($permissions)) {
        return api_error('Rettigheder skal være en array');
    }

    return api_transaction(
        function() use ($groupId, $permissions) {
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

            log_activity('group_permissions_updated', 'group', $groupId);
        },
        'Gruppe rettigheder opdateret',
        'Kunne ikke opdatere rettigheder'
    );
}

/**
 * Grant project access to user or group
 * POST ?module=user&action=grant_project_access
 */
function handle_grant_project_access(array $currentUser): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'project_id' => ['int', 'POST', true],
        'entity_type' => ['string', 'POST', true],
        'entity_id' => ['int', 'POST', true],
        'permission_level' => ['string', 'POST', false, 'viewer']
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    if (!in_array($params['entity_type'], ['user', 'group'])) {
        return api_error('Ugyldig entity type');
    }

    if (!in_array($params['permission_level'], ['owner', 'editor', 'viewer', 'none'])) {
        return api_error('Ugyldig rettigheds niveau');
    }

    // Check if current user has owner access to project
    $accessCheck = api_require_project_access($currentUser, $params['project_id'], 'owner');
    if (!$accessCheck['success']) {
        return api_error('Kun projekt ejere kan tildele adgang');
    }

    return api_transaction(
        function() use ($params, $currentUser) {
            // Check if permission already exists
            $existing = db_fetch("
                SELECT id FROM project_permissions
                WHERE project_id = :project_id AND entity_type = :entity_type AND entity_id = :entity_id
            ", [
                'project_id' => $params['project_id'],
                'entity_type' => $params['entity_type'],
                'entity_id' => $params['entity_id']
            ]);

            if ($existing) {
                // Update existing
                db_update('project_permissions',
                    ['permission_level' => $params['permission_level'], 'granted_by_user_id' => $currentUser['id']],
                    'id = :id',
                    ['id' => $existing['id']]
                );
            } else {
                // Insert new
                db_insert('project_permissions', [
                    'project_id' => $params['project_id'],
                    'entity_type' => $params['entity_type'],
                    'entity_id' => $params['entity_id'],
                    'permission_level' => $params['permission_level'],
                    'granted_by_user_id' => $currentUser['id']
                ]);
            }

            log_activity('project_access_granted', 'project', $params['project_id']);
        },
        'Projekt adgang tildelt',
        'Kunne ikke tildele projekt adgang'
    );
}

/**
 * Revoke project access
 * POST ?module=user&action=revoke_project_access
 */
function handle_revoke_project_access(array $currentUser): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'project_id' => ['int', 'POST', true],
        'entity_type' => ['string', 'POST', true],
        'entity_id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // Check if current user has owner access to project
    $accessCheck = api_require_project_access($currentUser, $params['project_id'], 'owner');
    if (!$accessCheck['success']) {
        return api_error('Kun projekt ejere kan fjerne adgang');
    }

    return api_transaction(
        function() use ($params) {
            db_execute("
                DELETE FROM project_permissions
                WHERE project_id = :project_id AND entity_type = :entity_type AND entity_id = :entity_id
            ", [
                'project_id' => $params['project_id'],
                'entity_type' => $params['entity_type'],
                'entity_id' => $params['entity_id']
            ]);

            log_activity('project_access_revoked', 'project', $params['project_id']);
        },
        'Projekt adgang fjernet',
        'Kunne ikke fjerne projekt adgang'
    );
}
