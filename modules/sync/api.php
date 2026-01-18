<?php
/**
 * Sync API - Unified Collaboration Endpoint
 *
 * Consolidates lock management, live updates, and notifications
 * into a single efficient API for multi-user collaboration.
 *
 * Single unified endpoint returns:
 * - Record changes since last sync
 * - Lock status for requested records
 * - Notifications for user
 *
 * Reduces number of API calls from 3+ separate requests to 1 unified request.
 */

require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/db.php';
require_once __DIR__ . '/../../core/utils.php';

/**
 * Get unified state: changes + locks + notifications
 */
function handle_get_state(array $user): array {
    $clientId = sanitize_string($_POST['client_id'] ?? '');
    $recordType = sanitize_string($_POST['record_type'] ?? '');
    $recordId = isset($_POST['record_id']) ? sanitize_int($_POST['record_id']) : null;
    $since = sanitize_string($_POST['since'] ?? '');
    $activeLocks = $_POST['active_locks'] ?? [];

    if (empty($clientId)) {
        return error_response('client_id er påkrævet');
    }

    $response = [
        'success' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'changes' => [],
        'locks' => [],
        'notifications' => []
    ];

    // Get changes since last sync
    if ($recordType && $since) {
        $response['changes'] = get_changes_since($recordType, $recordId, $since);
    }

    // Get lock status for active locks
    if (!empty($activeLocks) && is_array($activeLocks)) {
        $response['locks'] = get_lock_status($activeLocks, $user['id'], $clientId);
    }

    // Get recent notifications for user
    $response['notifications'] = get_recent_notifications($user['id'], $since);

    return $response;
}

/**
 * Get changes since timestamp
 */
function get_changes_since(string $recordType, ?int $recordId, string $since): array {
    $params = ['since' => $since, 'record_type' => $recordType];
    $sql = "
        SELECT
            rc.id,
            rc.record_type,
            rc.record_id,
            rc.field_name,
            rc.changed_by_user_id,
            rc.changed_at,
            rc.change_type,
            u.name as changed_by_name
        FROM record_changes rc
        LEFT JOIN users u ON u.id = rc.changed_by_user_id
        WHERE rc.changed_at > :since
        AND rc.record_type = :record_type
    ";

    if ($recordId !== null) {
        $sql .= " AND rc.record_id = :record_id";
        $params['record_id'] = $recordId;
    }

    $sql .= " ORDER BY rc.changed_at ASC LIMIT 100";

    return db_fetch_all($sql, $params);
}

/**
 * Get lock status for multiple locks
 *
 * @param array $lockKeys Array of lock keys in format "record_type:record_id:field_name"
 * @param int $userId Current user ID
 * @param string $clientId Current client ID
 * @return array Lock status for each requested lock
 */
function get_lock_status(array $lockKeys, int $userId, string $clientId): array {
    $locks = [];

    foreach ($lockKeys as $lockKey) {
        $parts = explode(':', $lockKey);
        if (count($parts) < 2) continue;

        $recordType = $parts[0];
        $recordId = (int)$parts[1];
        $fieldName = $parts[2] ?? null;

        $params = [
            'record_type' => $recordType,
            'record_id' => $recordId
        ];

        $sql = "
            SELECT
                rl.*,
                u.name as locked_by_name
            FROM record_locks rl
            LEFT JOIN users u ON u.id = rl.user_id
            WHERE rl.record_type = :record_type
            AND rl.record_id = :record_id
        ";

        if ($fieldName !== null) {
            $sql .= " AND rl.field_name = :field_name";
            $params['field_name'] = $fieldName;
        } else {
            $sql .= " AND rl.field_name IS NULL";
        }

        $lock = db_fetch($sql, $params);

        $locks[$lockKey] = [
            'locked' => $lock !== null,
            'by_self' => $lock && $lock['user_id'] == $userId && $lock['client_id'] == $clientId,
            'by_user' => $lock ? $lock['locked_by_name'] : null,
            'locked_at' => $lock ? $lock['locked_at'] : null,
            'is_stale' => $lock ? (strtotime($lock['last_activity']) < strtotime('-120 seconds')) : false
        ];
    }

    return $locks;
}

/**
 * Get recent notifications for user
 */
function get_recent_notifications(int $userId, string $since): array {
    // Check if notifications table exists
    $tableExists = db_value("
        SELECT EXISTS (
            SELECT FROM information_schema.tables
            WHERE table_schema = 'public'
            AND table_name = 'notifications'
        )
    ");

    if (!$tableExists) {
        return [];
    }

    $sql = "
        SELECT
            id,
            user_id,
            message,
            type,
            created_at,
            is_read
        FROM notifications
        WHERE user_id = :user_id
    ";

    $params = ['user_id' => $userId];

    if (!empty($since)) {
        $sql .= " AND created_at > :since";
        $params['since'] = $since;
    }

    $sql .= " ORDER BY created_at DESC LIMIT 20";

    return db_fetch_all($sql, $params);
}

/**
 * Acquire lock on record
 */
function handle_acquire(array $user): array {
    csrf_require();

    $recordType = sanitize_string($_POST['record_type'] ?? '');
    $recordId = sanitize_int($_POST['record_id'] ?? 0);
    $fieldName = isset($_POST['field_name']) ? sanitize_string($_POST['field_name']) : null;
    $clientId = sanitize_string($_POST['client_id'] ?? '');

    if (empty($recordType) || empty($recordId) || empty($clientId)) {
        return error_response('record_type, record_id og client_id er påkrævet');
    }

    // Check project access
    try {
        require_project_access($user, $recordType, $recordId, 'editor');
    } catch (Exception $e) {
        return error_response($e->getMessage());
    }

    // Attempt to acquire lock
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

    if ($result && isset($result['success'])) {
        return [
            'success' => $result['success'],
            'locked' => $result['success'],
            'locked_by' => $result['success'] ? $user['name'] : ($result['locked_by_name'] ?? null),
            'locked_at' => $result['locked_at'] ?? null
        ];
    }

    return error_response('Kunne ikke låse record');
}

/**
 * Release lock on record
 */
function handle_release(array $user): array {
    csrf_require();

    $recordType = sanitize_string($_POST['record_type'] ?? '');
    $recordId = sanitize_int($_POST['record_id'] ?? 0);
    $fieldName = isset($_POST['field_name']) ? sanitize_string($_POST['field_name']) : null;
    $clientId = sanitize_string($_POST['client_id'] ?? '');

    if (empty($recordType) || empty($recordId)) {
        return error_response('record_type og record_id er påkrævet');
    }

    $params = [
        'record_type' => $recordType,
        'record_id' => $recordId,
        'user_id' => $user['id']
    ];

    $sql = "
        DELETE FROM record_locks
        WHERE record_type = :record_type
        AND record_id = :record_id
        AND user_id = :user_id
    ";

    if ($fieldName !== null) {
        $sql .= " AND field_name = :field_name";
        $params['field_name'] = $fieldName;
    } else {
        $sql .= " AND field_name IS NULL";
    }

    if (!empty($clientId)) {
        $sql .= " AND client_id = :client_id";
        $params['client_id'] = $clientId;
    }

    db_execute($sql, $params);

    return success_response('Lock frigivet');
}

/**
 * Batch heartbeat for multiple locks
 */
function handle_heartbeat(array $user): array {
    csrf_require();

    $locks = $_POST['locks'] ?? [];
    $clientId = sanitize_string($_POST['client_id'] ?? '');

    if (!is_array($locks) || empty($locks)) {
        return error_response('locks array er påkrævet');
    }

    if (empty($clientId)) {
        return error_response('client_id er påkrævet');
    }

    $updated = 0;

    foreach ($locks as $lock) {
        $recordType = sanitize_string($lock['record_type'] ?? '');
        $recordId = sanitize_int($lock['record_id'] ?? 0);
        $fieldName = isset($lock['field_name']) ? sanitize_string($lock['field_name']) : null;

        if (empty($recordType) || empty($recordId)) {
            continue;
        }

        $params = [
            'record_type' => $recordType,
            'record_id' => $recordId,
            'user_id' => $user['id'],
            'client_id' => $clientId
        ];

        $sql = "
            UPDATE record_locks
            SET last_activity = CURRENT_TIMESTAMP
            WHERE record_type = :record_type
            AND record_id = :record_id
            AND user_id = :user_id
            AND client_id = :client_id
        ";

        if ($fieldName !== null) {
            $sql .= " AND field_name = :field_name";
            $params['field_name'] = $fieldName;
        } else {
            $sql .= " AND field_name IS NULL";
        }

        $result = db_execute($sql, $params);
        if ($result) {
            $updated++;
        }
    }

    return success_response("Opdateret $updated locks");
}

/**
 * Cleanup stale locks (can be called periodically)
 */
function handle_cleanup(array $user): array {
    // Only allow admins or system to cleanup
    if ($user['role'] !== 'admin') {
        return error_response('Ikke autoriseret');
    }

    $count = db_value("SELECT cleanup_stale_locks()");

    return success_response("Ryddet $count forældede locks");
}

/**
 * Log a record change
 */
function handle_log_change(array $user): array {
    csrf_require();

    $recordType = sanitize_string($_POST['record_type'] ?? '');
    $recordId = sanitize_int($_POST['record_id'] ?? 0);
    $fieldName = isset($_POST['field_name']) ? sanitize_string($_POST['field_name']) : null;
    $changeType = sanitize_string($_POST['change_type'] ?? 'update');

    if (empty($recordType) || empty($recordId)) {
        return error_response('record_type og record_id er påkrævet');
    }

    // Check project access
    try {
        require_project_access($user, $recordType, $recordId, 'editor');
    } catch (Exception $e) {
        return error_response($e->getMessage());
    }

    db_execute("
        INSERT INTO record_changes (
            record_type,
            record_id,
            field_name,
            changed_by_user_id,
            change_type
        ) VALUES (
            :record_type,
            :record_id,
            :field_name,
            :user_id,
            :change_type
        )
    ", [
        'record_type' => $recordType,
        'record_id' => $recordId,
        'field_name' => $fieldName,
        'user_id' => $user['id'],
        'change_type' => $changeType
    ]);

    return success_response('Ændring logget');
}

// Route request
$user = auth_require();
$action = sanitize_string($_REQUEST['action'] ?? '');

try {
    switch ($action) {
        case 'get_state':
            $result = handle_get_state($user);
            break;

        case 'acquire':
            $result = handle_acquire($user);
            break;

        case 'release':
            $result = handle_release($user);
            break;

        case 'heartbeat':
            $result = handle_heartbeat($user);
            break;

        case 'cleanup':
            $result = handle_cleanup($user);
            break;

        case 'log_change':
            $result = handle_log_change($user);
            break;

        default:
            $result = error_response('Ukendt action');
    }
} catch (Exception $e) {
    $result = error_response($e->getMessage());
}

header('Content-Type: application/json');
echo json_encode($result);
