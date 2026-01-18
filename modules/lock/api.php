<?php
/**
 * Lock Management Module API
 *
 * Handles record locking for multi-user collaborative editing
 * Auto-releases locks after 120 seconds of inactivity
 *
 * Actions:
 * - acquire: Acquire lock on a record/field
 * - release: Release lock on a record/field
 * - heartbeat: Update lock activity timestamp
 * - check: Check if record is locked
 * - get_locks: Get all locks for a record
 * - cleanup: Manually trigger stale lock cleanup
 */

require_once __DIR__ . '/../../core/permissions.php';

/**
 * Acquire lock on a record
 * POST ?module=lock&action=acquire
 */
function handle_acquire(array $user): array {
    csrf_require();

    $recordType = sanitize_string($_POST['record_type'] ?? '');
    $recordId = sanitize_int($_POST['record_id'] ?? 0);
    $fieldName = isset($_POST['field_name']) ? sanitize_string($_POST['field_name']) : null;
    $clientId = sanitize_string($_POST['client_id'] ?? '');

    if (!$recordType || !$recordId) {
        return ['success' => false, 'error' => 'Record type og ID er påkrævet'];
    }

    // Check if user has access to this record
    if (!can_access_record($user, $recordType, $recordId, 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at redigere denne record'];
    }

    try {
        $result = db_fetch("
            SELECT * FROM acquire_record_lock(
                :record_type,
                :record_id,
                :user_id,
                :field_name,
                :client_id
            )
        ", [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'user_id' => $user['id'],
            'field_name' => $fieldName,
            'client_id' => $clientId
        ]);

        if ($result['success']) {
            return [
                'success' => true,
                'message' => $result['message'],
                'locked_by' => 'you'
            ];
        } else {
            return [
                'success' => false,
                'locked' => true,
                'locked_by_user_id' => $result['locked_by_user_id'],
                'locked_by_user_name' => $result['locked_by_user_name'],
                'locked_since' => $result['locked_since'],
                'message' => $result['message']
            ];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Kunne ikke acquire lock: ' . $e->getMessage()];
    }
}

/**
 * Release lock on a record
 * POST ?module=lock&action=release
 */
function handle_release(array $user): array {
    csrf_require();

    $recordType = sanitize_string($_POST['record_type'] ?? '');
    $recordId = sanitize_int($_POST['record_id'] ?? 0);
    $fieldName = isset($_POST['field_name']) ? sanitize_string($_POST['field_name']) : null;
    $clientId = sanitize_string($_POST['client_id'] ?? '');

    if (!$recordType || !$recordId) {
        return ['success' => false, 'error' => 'Record type og ID er påkrævet'];
    }

    try {
        $released = db_value("
            SELECT release_record_lock(
                :record_type,
                :record_id,
                :user_id,
                :field_name,
                :client_id
            )
        ", [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'user_id' => $user['id'],
            'field_name' => $fieldName,
            'client_id' => $clientId
        ]);

        return [
            'success' => (bool)$released,
            'message' => $released ? 'Lock frigivet' : 'Ingen lock at frigive'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Kunne ikke frigive lock: ' . $e->getMessage()];
    }
}

/**
 * Update lock heartbeat (keep-alive)
 * POST ?module=lock&action=heartbeat
 */
function handle_heartbeat(array $user): array {
    csrf_require();

    $recordType = sanitize_string($_POST['record_type'] ?? '');
    $recordId = sanitize_int($_POST['record_id'] ?? 0);
    $fieldName = isset($_POST['field_name']) ? sanitize_string($_POST['field_name']) : null;
    $clientId = sanitize_string($_POST['client_id'] ?? '');

    if (!$recordType || !$recordId) {
        return ['success' => false, 'error' => 'Record type og ID er påkrævet'];
    }

    try {
        $updated = db_value("
            SELECT update_lock_heartbeat(
                :record_type,
                :record_id,
                :user_id,
                :field_name,
                :client_id
            )
        ", [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'user_id' => $user['id'],
            'field_name' => $fieldName,
            'client_id' => $clientId
        ]);

        return [
            'success' => (bool)$updated,
            'message' => $updated ? 'Heartbeat opdateret' : 'Ingen aktiv lock'
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Kunne ikke opdatere heartbeat: ' . $e->getMessage()];
    }
}

/**
 * Check if record is locked
 * GET ?module=lock&action=check&record_type=X&record_id=Y
 */
function handle_check(array $user): array {
    $recordType = sanitize_string($_GET['record_type'] ?? '');
    $recordId = sanitize_int($_GET['record_id'] ?? 0);
    $fieldName = isset($_GET['field_name']) ? sanitize_string($_GET['field_name']) : null;

    if (!$recordType || !$recordId) {
        return ['success' => false, 'error' => 'Record type og ID er påkrævet'];
    }

    // Check if user has access to this record
    if (!can_access_record($user, $recordType, $recordId, 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til denne record'];
    }

    try {
        $lock = db_fetch("
            SELECT
                rl.user_id,
                u.name as user_name,
                rl.locked_at,
                rl.last_activity,
                EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - rl.last_activity))::INTEGER as seconds_since_activity
            FROM record_locks rl
            JOIN users u ON u.id = rl.user_id
            WHERE rl.record_type = :record_type
                AND rl.record_id = :record_id
                AND (:field_name IS NULL OR rl.field_name = :field_name)
        ", [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'field_name' => $fieldName
        ]);

        if ($lock) {
            // Check if lock is stale
            if ($lock['seconds_since_activity'] > 120) {
                // Stale lock, clean it up
                db_execute("SELECT cleanup_stale_locks()");

                return [
                    'success' => true,
                    'locked' => false,
                    'message' => 'Ingen aktiv lock (stale lock ryddet)'
                ];
            }

            return [
                'success' => true,
                'locked' => true,
                'locked_by_user_id' => (int)$lock['user_id'],
                'locked_by_user_name' => $lock['user_name'],
                'locked_since' => $lock['locked_at'],
                'seconds_active' => (int)$lock['seconds_since_activity'],
                'locked_by_self' => ($lock['user_id'] == $user['id'])
            ];
        }

        return [
            'success' => true,
            'locked' => false
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Kunne ikke tjekke lock: ' . $e->getMessage()];
    }
}

/**
 * Get all locks for a record
 * GET ?module=lock&action=get_locks&record_type=X&record_id=Y
 */
function handle_get_locks(array $user): array {
    $recordType = sanitize_string($_GET['record_type'] ?? '');
    $recordId = sanitize_int($_GET['record_id'] ?? 0);

    if (!$recordType || !$recordId) {
        return ['success' => false, 'error' => 'Record type og ID er påkrævet'];
    }

    // Check if user has access to this record
    if (!can_access_record($user, $recordType, $recordId, 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til denne record'];
    }

    try {
        $locks = db_fetch_all("
            SELECT * FROM get_record_locks(:record_type, :record_id)
        ", [
            'record_type' => $recordType,
            'record_id' => $recordId
        ]);

        return [
            'success' => true,
            'locks' => $locks
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Kunne ikke hente locks: ' . $e->getMessage()];
    }
}

/**
 * Manually trigger cleanup of stale locks
 * POST ?module=lock&action=cleanup (admin only)
 */
function handle_cleanup(array $user): array {
    csrf_require();

    // Require admin permission
    if (!has_permission($user, 'admin', 'admin')) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    try {
        $cleaned = db_value("SELECT cleanup_stale_locks()");

        return [
            'success' => true,
            'cleaned' => (int)$cleaned,
            'message' => "Ryddet $cleaned stale locks"
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Kunne ikke rydde locks: ' . $e->getMessage()];
    }
}

/**
 * Get recent changes for live updates
 * GET ?module=lock&action=get_changes&since=TIMESTAMP
 */
function handle_get_changes(array $user): array {
    $since = sanitize_string($_GET['since'] ?? '');
    $recordType = sanitize_string($_GET['record_type'] ?? '');
    $recordId = isset($_GET['record_id']) ? sanitize_int($_GET['record_id']) : null;

    if (!$since) {
        $since = date('Y-m-d H:i:s', strtotime('-5 seconds'));
    }

    try {
        $query = "
            SELECT
                rc.id,
                rc.record_type,
                rc.record_id,
                rc.change_type,
                rc.changed_at,
                u.name as changed_by_user
            FROM record_changes rc
            LEFT JOIN users u ON u.id = rc.changed_by_user_id
            WHERE rc.changed_at > :since
        ";

        $params = ['since' => $since];

        if ($recordType) {
            $query .= " AND rc.record_type = :record_type";
            $params['record_type'] = $recordType;
        }

        if ($recordId) {
            $query .= " AND rc.record_id = :record_id";
            $params['record_id'] = $recordId;
        }

        $query .= " ORDER BY rc.changed_at ASC LIMIT 100";

        $changes = db_fetch_all($query, $params);

        return [
            'success' => true,
            'changes' => $changes,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Kunne ikke hente changes: ' . $e->getMessage()];
    }
}

/**
 * Helper: Check if user can access a record type
 */
function can_access_record(array $user, string $recordType, int $recordId, string $requiredRole = 'viewer'): bool {
    // Map record types to their project lookup
    $projectQueries = [
        'building_elements' => "
            SELECT b.project_id
            FROM building_elements be
            JOIN buildings b ON b.id = be.building_id
            WHERE be.id = :record_id
        ",
        'buildings' => "
            SELECT project_id FROM buildings WHERE id = :record_id
        ",
        'projects' => "
            SELECT id as project_id FROM projects WHERE id = :record_id
        ",
        'budget_template_items' => "
            SELECT bt.project_id
            FROM budget_template_items bti
            JOIN budget_templates bt ON bt.id = bti.template_id
            WHERE bti.id = :record_id
        ",
        'budget_templates' => "
            SELECT project_id FROM budget_templates WHERE id = :record_id
        "
    ];

    $query = $projectQueries[$recordType] ?? null;

    if (!$query) {
        // Unknown record type, deny access
        return false;
    }

    try {
        $projectId = db_value($query, ['record_id' => $recordId]);

        if (!$projectId) {
            return false;
        }

        return can_access_project($user, $projectId, $requiredRole);
    } catch (Exception $e) {
        return false;
    }
}
