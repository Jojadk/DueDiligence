<?php
namespace Core;

class InputLock
{

    /**
     * Attempt to lock a field.
     * @param string $table
     * @param int $rowId
     * @param string $field (optional, if locking the whole row, leave null or handle logic)
     * @return array ['success' => bool, 'locked_by' => string]
     */
    public static function acquireLock($table, $rowId, $field = null, $clientId = null)
    {
        $db = Database::getInstance();
        $userId = Auth::id();

        // 1. Clean up old locks (timeout > 60 seconds)
        // If client_id is provided, maybe we trust it more? But existing logic is fine.
        $db->query("DELETE FROM input_locks WHERE locked_at < (NOW() - INTERVAL '60 seconds')");
        $db->execute();

        // 2. Check if locked by someone else (OR same user but different client)
        $sql = "SELECT l.*, u.username FROM input_locks l JOIN users u ON l.user_id = u.id 
                WHERE table_name = :table AND row_id = :row";
        if ($field) {
            $sql .= " AND field_name = :field";
        }
        $db->query($sql);
        $db->bind(':table', $table);
        $db->bind(':row', $rowId);
        if ($field)
            $db->bind(':field', $field);

        $existing = $db->single();

        if ($existing) {
            // Check collision
            // If locked by different user => Block
            if ($existing['user_id'] != $userId) {
                return ['success' => false, 'locked_by' => $existing['username']];
            }

            // If locked by SAME user...
            // Check Client ID if available in DB and Request
            // (Assumes we store client_id now)
            if ($clientId && isset($existing['client_id']) && $existing['client_id']) {
                if ($existing['client_id'] !== $clientId) {
                    // Same user, different window => Treated as locked to avoid overwrite/conflict
                    return ['success' => false, 'locked_by' => 'dig selv i et andet vindue'];
                }
            }

            // Same user, same client (or no client tracking) => Refresh
            self::refreshLock($existing['id']);
            return ['success' => true];
        }

        // 3. Create Lock
        $sql = "INSERT INTO input_locks (table_name, row_id, field_name, user_id, locked_at, ip_address, client_id) 
                VALUES (:table, :row, :field, :uid, NOW(), :ip, :cid)";
        try {
            $db->query($sql);
            $db->bind(':table', $table);
            $db->bind(':row', $rowId);
            $db->bind(':field', $field);
            $db->bind(':uid', $userId);
            $db->bind(':ip', $_SERVER['REMOTE_ADDR'] ?? '');
            $db->bind(':cid', $clientId);
            $db->execute();
            return ['success' => true];
        } catch (\PDOException $e) {
            // Race condition check
            return ['success' => false, 'error' => 'Could not acquire lock'];
        }
    }

    public static function refreshLock($lockId)
    {
        $db = Database::getInstance();
        $db->query("UPDATE input_locks SET locked_at = NOW() WHERE id = :id");
        $db->bind(':id', $lockId);
        $db->execute();
    }

    public static function releaseLock($table, $rowId, $field = null)
    {
        $db = Database::getInstance();
        $userId = Auth::id();

        $sql = "DELETE FROM input_locks WHERE table_name = :table AND row_id = :row AND user_id = :uid";
        if ($field) {
            $sql .= " AND field_name = :field";
        }
        $db->query($sql);
        $db->bind(':table', $table);
        $db->bind(':row', $rowId);
        if ($field)
            $db->bind(':field', $field);
        $db->bind(':uid', $userId);
        $db->execute();
    }

    // Heartbeat for frontend to keep locks alive
    public static function heartbeat($locks)
    {
        // $locks is array of IDs or composite keys
        // Keep it simple
    }
}
