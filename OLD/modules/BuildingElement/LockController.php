<?php
namespace Modules\BuildingElement;

use Core\InputLock;

/**
 * Handles all locking operations for building elements
 * - Acquire locks
 * - Release locks
 * - Check lock status
 * - Heartbeat to maintain lock
 * - Polling for collaborative editing
 */
class LockController extends BaseElementController
{
    /**
     * Acquire lock on an element field
     * POST: element_id, field, client_id
     */
    public function acquire()
    {
        $input = $this->getJsonInput();

        $elementId = $input['element_id'] ?? $input['id'] ?? $_POST['element_id'] ?? $_POST['id'] ?? null;
        $field = $input['field'] ?? $_POST['field'] ?? null;
        $clientId = $input['client_id'] ?? $_POST['client_id'] ?? null;

        if (!$elementId || !$clientId) {
            $this->jsonError('Element ID and Client ID required', 400);
        }

        $result = InputLock::acquireLock(
            'building_elements',
            $elementId,
            $field,
            $clientId
        );

        if ($result['success']) {
            $this->jsonSuccess(['locked' => true], 'Lock acquired');
        } else {
            $this->jsonError($result['message'] ?? 'Lock already held', 409);
        }
    }

    /**
     * Release lock on an element
     * POST: element_id, field, client_id
     */
    public function release()
    {
        $input = $this->getJsonInput();

        $elementId = $input['element_id'] ?? $input['id'] ?? $_POST['element_id'] ?? $_POST['id'] ?? null;
        $field = $input['field'] ?? $_POST['field'] ?? null;

        if (!$elementId) {
            $this->jsonError('Element ID required', 400);
        }

        // releaseLock takes (table, rowId, field)
        InputLock::releaseLock(
            'building_elements',
            $elementId,
            $field
        );

        $this->jsonSuccess(null, 'Lock released');
    }

    /**
     * Check if element/field is locked
     * GET: element_id, field, client_id
     */
    public function check()
    {
        $elementId = $_GET['element_id'] ?? $_GET['id'] ?? null;
        $field = $_GET['field'] ?? null;
        $clientId = $_GET['client_id'] ?? null;

        if (!$elementId) {
            $this->jsonError('Element ID required', 400);
        }

        // Get all active locks for this element
        $this->db->query("SELECT * FROM input_locks 
                         WHERE table_name = 'building_elements' 
                         AND row_id = :eid 
                         AND locked_at > (NOW() - INTERVAL '60 seconds')");
        $this->db->bind(':eid', $elementId);
        $locks = $this->db->resultSet();

        $isLocked = false;
        $lockedBy = null;
        $lockedFields = [];

        foreach ($locks as $lock) {
            // Check if another user has the lock
            if ($lock['client_id'] !== $clientId) {
                $isLocked = true;
                $lockedBy = $lock['user_id'];
                $lockedFields[] = $lock['field_name'];
            }
        }

        $this->jsonSuccess([
            'locked' => $isLocked,
            'locked_by' => $lockedBy,
            'locked_fields' => $lockedFields,
            'can_edit' => !$isLocked
        ]);
    }

    /**
     * Heartbeat to refresh lock
     * POST: element_id, client_id
     */
    public function heartbeat()
    {
        $input = $this->getJsonInput();

        $elementId = $input['element_id'] ?? $input['id'] ?? $_POST['element_id'] ?? $_POST['id'] ?? null;
        $clientId = $input['client_id'] ?? $_POST['client_id'] ?? null;

        if (!$elementId || !$clientId) {
            $this->jsonError('Element ID and Client ID required', 400);
        }

        // Find the lock ID and refresh it
        $this->db->query("SELECT id FROM input_locks 
                         WHERE table_name = 'building_elements' 
                         AND row_id = :eid 
                         AND client_id = :cid");
        $this->db->bind(':eid', $elementId);
        $this->db->bind(':cid', $clientId);
        $lock = $this->db->single();

        if ($lock) {
            InputLock::refreshLock($lock['id']);
        }

        $this->jsonSuccess(['refreshed' => true]);
    }

    /**
     * Poll for changes and locks
     * Used for real-time collaboration
     * GET: element_id, client_id, last_updated
     */
    public function poll()
    {
        $elementId = $_GET['element_id'] ?? $_GET['id'] ?? null;
        $clientId = $_GET['client_id'] ?? null;
        $lastUpdated = $_GET['last_updated'] ?? null;

        if (!$elementId) {
            $this->jsonError('Element ID required', 400);
        }

        // Get current element data
        $this->db->query('SELECT * FROM building_elements WHERE id = :id');
        $this->db->bind(':id', $elementId);
        $element = $this->db->single();

        if (!$element) {
            $this->jsonError('Element not found', 404);
        }

        // Get active locks (by other users)
        $this->db->query("SELECT il.*, u.username 
                         FROM input_locks il 
                         LEFT JOIN users u ON il.user_id = u.id
                         WHERE il.table_name = 'building_elements' 
                         AND il.row_id = :eid 
                         AND il.client_id != :cid
                         AND il.locked_at > (NOW() - INTERVAL '60 seconds')");
        $this->db->bind(':eid', $elementId);
        $this->db->bind(':cid', $clientId ?? '');
        $locks = $this->db->resultSet();

        // Check if data has changed since last poll
        $hasChanges = false;
        if ($lastUpdated && isset($element['updated_at'])) {
            $hasChanges = strtotime($element['updated_at']) > strtotime($lastUpdated);
        }

        $this->jsonSuccess([
            'element' => $hasChanges ? $element : null,
            'has_changes' => $hasChanges,
            'locks' => $locks,
            'server_time' => date('Y-m-d H:i:s')
        ]);
    }
}
