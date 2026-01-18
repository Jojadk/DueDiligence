-- Migration: Add record locking and live update support
-- Purpose: Enable multi-user collaborative editing with automatic lock release

-- ============================================================================
-- Record Locking System
-- ============================================================================

-- Table to track which records are currently being edited
CREATE TABLE IF NOT EXISTS record_locks (
    id SERIAL PRIMARY KEY,
    record_type VARCHAR(50) NOT NULL,  -- e.g., 'building_element', 'budget_template', 'project'
    record_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    field_name VARCHAR(100),  -- Optional: specific field being edited
    client_id VARCHAR(100),   -- Browser tab/session identifier
    UNIQUE (record_type, record_id, field_name)
);

-- Indexes for fast lookups
CREATE INDEX idx_record_locks_record ON record_locks(record_type, record_id);
CREATE INDEX idx_record_locks_user ON record_locks(user_id);
CREATE INDEX idx_record_locks_activity ON record_locks(last_activity);

-- ============================================================================
-- Change Log for Live Updates
-- ============================================================================

-- Track changes to records for broadcasting to other users
CREATE TABLE IF NOT EXISTS record_changes (
    id SERIAL PRIMARY KEY,
    record_type VARCHAR(50) NOT NULL,
    record_id INTEGER NOT NULL,
    field_name VARCHAR(100),
    old_value TEXT,
    new_value TEXT,
    changed_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_type VARCHAR(20) DEFAULT 'update' -- 'insert', 'update', 'delete'
);

-- Index for polling recent changes
CREATE INDEX idx_record_changes_timestamp ON record_changes(changed_at DESC);
CREATE INDEX idx_record_changes_record ON record_changes(record_type, record_id);

-- Partition by date for better performance (optional, for high-volume systems)
-- Keep only last 7 days of changes
CREATE INDEX idx_record_changes_cleanup ON record_changes(changed_at)
    WHERE changed_at < CURRENT_TIMESTAMP - INTERVAL '7 days';

-- ============================================================================
-- Functions for Lock Management
-- ============================================================================

-- Function to acquire a lock on a record
CREATE OR REPLACE FUNCTION acquire_record_lock(
    p_record_type VARCHAR(50),
    p_record_id INTEGER,
    p_user_id INTEGER,
    p_field_name VARCHAR(100) DEFAULT NULL,
    p_client_id VARCHAR(100) DEFAULT NULL
)
RETURNS TABLE (
    success BOOLEAN,
    message TEXT,
    locked_by_user_id INTEGER,
    locked_by_user_name VARCHAR(255),
    locked_since TIMESTAMP
) AS $$
DECLARE
    v_existing_lock RECORD;
    v_lock_timeout INTEGER := 120; -- seconds
BEGIN
    -- Check for existing lock
    SELECT
        rl.*,
        u.name as user_name,
        EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - rl.last_activity)) as seconds_since_activity
    INTO v_existing_lock
    FROM record_locks rl
    JOIN users u ON u.id = rl.user_id
    WHERE rl.record_type = p_record_type
        AND rl.record_id = p_record_id
        AND (p_field_name IS NULL OR rl.field_name = p_field_name OR rl.field_name IS NULL);

    -- If lock exists
    IF FOUND THEN
        -- If lock is stale (>120 seconds), release it
        IF v_existing_lock.seconds_since_activity > v_lock_timeout THEN
            DELETE FROM record_locks WHERE id = v_existing_lock.id;

            -- Acquire new lock
            INSERT INTO record_locks (record_type, record_id, user_id, field_name, client_id)
            VALUES (p_record_type, p_record_id, p_user_id, p_field_name, p_client_id);

            RETURN QUERY SELECT TRUE, 'Lock acquired (stale lock released)'::TEXT, NULL::INTEGER, NULL::VARCHAR, NULL::TIMESTAMP;
            RETURN;
        END IF;

        -- If locked by same user and client, update activity
        IF v_existing_lock.user_id = p_user_id AND
           (v_existing_lock.client_id = p_client_id OR v_existing_lock.client_id IS NULL) THEN
            UPDATE record_locks
            SET last_activity = CURRENT_TIMESTAMP
            WHERE id = v_existing_lock.id;

            RETURN QUERY SELECT TRUE, 'Lock refreshed'::TEXT, NULL::INTEGER, NULL::VARCHAR, NULL::TIMESTAMP;
            RETURN;
        END IF;

        -- Lock held by another user
        RETURN QUERY SELECT
            FALSE,
            'Record is locked by another user'::TEXT,
            v_existing_lock.user_id,
            v_existing_lock.user_name,
            v_existing_lock.locked_at;
        RETURN;
    END IF;

    -- No existing lock, acquire it
    INSERT INTO record_locks (record_type, record_id, user_id, field_name, client_id)
    VALUES (p_record_type, p_record_id, p_user_id, p_field_name, p_client_id);

    RETURN QUERY SELECT TRUE, 'Lock acquired'::TEXT, NULL::INTEGER, NULL::VARCHAR, NULL::TIMESTAMP;
END;
$$ LANGUAGE plpgsql;

-- Function to release a lock
CREATE OR REPLACE FUNCTION release_record_lock(
    p_record_type VARCHAR(50),
    p_record_id INTEGER,
    p_user_id INTEGER,
    p_field_name VARCHAR(100) DEFAULT NULL,
    p_client_id VARCHAR(100) DEFAULT NULL
)
RETURNS BOOLEAN AS $$
DECLARE
    v_deleted INTEGER;
BEGIN
    DELETE FROM record_locks
    WHERE record_type = p_record_type
        AND record_id = p_record_id
        AND user_id = p_user_id
        AND (p_field_name IS NULL OR field_name = p_field_name)
        AND (p_client_id IS NULL OR client_id = p_client_id);

    GET DIAGNOSTICS v_deleted = ROW_COUNT;
    RETURN v_deleted > 0;
END;
$$ LANGUAGE plpgsql;

-- Function to update lock heartbeat
CREATE OR REPLACE FUNCTION update_lock_heartbeat(
    p_record_type VARCHAR(50),
    p_record_id INTEGER,
    p_user_id INTEGER,
    p_field_name VARCHAR(100) DEFAULT NULL,
    p_client_id VARCHAR(100) DEFAULT NULL
)
RETURNS BOOLEAN AS $$
BEGIN
    UPDATE record_locks
    SET last_activity = CURRENT_TIMESTAMP
    WHERE record_type = p_record_type
        AND record_id = p_record_id
        AND user_id = p_user_id
        AND (p_field_name IS NULL OR field_name = p_field_name)
        AND (p_client_id IS NULL OR client_id = p_client_id);

    RETURN FOUND;
END;
$$ LANGUAGE plpgsql;

-- Function to clean up stale locks (can be called via cron or trigger)
CREATE OR REPLACE FUNCTION cleanup_stale_locks()
RETURNS INTEGER AS $$
DECLARE
    v_deleted INTEGER;
BEGIN
    DELETE FROM record_locks
    WHERE last_activity < CURRENT_TIMESTAMP - INTERVAL '120 seconds';

    GET DIAGNOSTICS v_deleted = ROW_COUNT;
    RETURN v_deleted;
END;
$$ LANGUAGE plpgsql;

-- Function to get all locks for a record
CREATE OR REPLACE FUNCTION get_record_locks(
    p_record_type VARCHAR(50),
    p_record_id INTEGER
)
RETURNS TABLE (
    user_id INTEGER,
    user_name VARCHAR(255),
    field_name VARCHAR(100),
    locked_since TIMESTAMP,
    seconds_active INTEGER
) AS $$
BEGIN
    RETURN QUERY
    SELECT
        rl.user_id,
        u.name as user_name,
        rl.field_name,
        rl.locked_at as locked_since,
        EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - rl.last_activity))::INTEGER as seconds_active
    FROM record_locks rl
    JOIN users u ON u.id = rl.user_id
    WHERE rl.record_type = p_record_type
        AND rl.record_id = p_record_id
    ORDER BY rl.locked_at ASC;
END;
$$ LANGUAGE plpgsql;

-- ============================================================================
-- Triggers for Change Logging
-- ============================================================================

-- Generic trigger function to log changes
CREATE OR REPLACE FUNCTION log_record_change()
RETURNS TRIGGER AS $$
DECLARE
    v_record_type VARCHAR(50);
    v_user_id INTEGER;
BEGIN
    -- Determine record type from table name
    v_record_type := TG_TABLE_NAME;

    -- Get current user from session (if set via application)
    -- This assumes application sets a session variable
    BEGIN
        v_user_id := current_setting('app.current_user_id')::INTEGER;
    EXCEPTION
        WHEN OTHERS THEN
            v_user_id := NULL;
    END;

    -- Log the change
    IF TG_OP = 'UPDATE' THEN
        -- Only log if values actually changed
        IF NEW IS DISTINCT FROM OLD THEN
            INSERT INTO record_changes (record_type, record_id, changed_by_user_id, change_type)
            VALUES (v_record_type, NEW.id, v_user_id, 'update');
        END IF;
    ELSIF TG_OP = 'INSERT' THEN
        INSERT INTO record_changes (record_type, record_id, changed_by_user_id, change_type)
        VALUES (v_record_type, NEW.id, v_user_id, 'insert');
    ELSIF TG_OP = 'DELETE' THEN
        INSERT INTO record_changes (record_type, record_id, changed_by_user_id, change_type)
        VALUES (v_record_type, OLD.id, v_user_id, 'delete');
    END IF;

    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    ELSE
        RETURN NEW;
    END IF;
END;
$$ LANGUAGE plpgsql;

-- Apply change logging to key tables
DROP TRIGGER IF EXISTS trg_log_building_elements_changes ON building_elements;
CREATE TRIGGER trg_log_building_elements_changes
    AFTER INSERT OR UPDATE OR DELETE ON building_elements
    FOR EACH ROW EXECUTE FUNCTION log_record_change();

DROP TRIGGER IF EXISTS trg_log_budget_template_items_changes ON budget_template_items;
CREATE TRIGGER trg_log_budget_template_items_changes
    AFTER INSERT OR UPDATE OR DELETE ON budget_template_items
    FOR EACH ROW EXECUTE FUNCTION log_record_change();

DROP TRIGGER IF EXISTS trg_log_projects_changes ON projects;
CREATE TRIGGER trg_log_projects_changes
    AFTER INSERT OR UPDATE OR DELETE ON projects
    FOR EACH ROW EXECUTE FUNCTION log_record_change();

DROP TRIGGER IF EXISTS trg_log_buildings_changes ON buildings;
CREATE TRIGGER trg_log_buildings_changes
    AFTER INSERT OR UPDATE OR DELETE ON buildings
    FOR EACH ROW EXECUTE FUNCTION log_record_change();

-- ============================================================================
-- Cleanup Job (PostgreSQL Extension - pg_cron)
-- ============================================================================

-- If pg_cron is available, schedule automatic cleanup every minute
-- Uncomment if pg_cron extension is enabled:
/*
SELECT cron.schedule(
    'cleanup-stale-locks',
    '* * * * *',  -- Every minute
    'SELECT cleanup_stale_locks();'
);

-- Cleanup old change logs every hour
SELECT cron.schedule(
    'cleanup-old-changes',
    '0 * * * *',  -- Every hour
    'DELETE FROM record_changes WHERE changed_at < CURRENT_TIMESTAMP - INTERVAL ''7 days'';'
);
*/

-- ============================================================================
-- Views for Monitoring
-- ============================================================================

-- View of currently active locks
CREATE OR REPLACE VIEW v_active_locks AS
SELECT
    rl.id,
    rl.record_type,
    rl.record_id,
    u.name as locked_by_user,
    u.email as locked_by_email,
    rl.field_name,
    rl.locked_at,
    rl.last_activity,
    EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - rl.last_activity))::INTEGER as seconds_since_activity,
    CASE
        WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - rl.last_activity)) > 120 THEN 'STALE'
        WHEN EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - rl.last_activity)) > 60 THEN 'WARNING'
        ELSE 'ACTIVE'
    END as lock_status
FROM record_locks rl
JOIN users u ON u.id = rl.user_id
ORDER BY rl.last_activity DESC;

-- View of recent changes
CREATE OR REPLACE VIEW v_recent_changes AS
SELECT
    rc.id,
    rc.record_type,
    rc.record_id,
    rc.change_type,
    u.name as changed_by_user,
    rc.changed_at,
    EXTRACT(EPOCH FROM (CURRENT_TIMESTAMP - rc.changed_at))::INTEGER as seconds_ago
FROM record_changes rc
LEFT JOIN users u ON u.id = rc.changed_by_user_id
WHERE rc.changed_at > CURRENT_TIMESTAMP - INTERVAL '1 hour'
ORDER BY rc.changed_at DESC;

COMMENT ON TABLE record_locks IS 'Tracks active editing locks to prevent concurrent modifications';
COMMENT ON TABLE record_changes IS 'Logs all changes for live update broadcasting';
COMMENT ON FUNCTION acquire_record_lock IS 'Attempts to acquire an editing lock on a record';
COMMENT ON FUNCTION release_record_lock IS 'Releases an editing lock';
COMMENT ON FUNCTION update_lock_heartbeat IS 'Updates the last activity timestamp for a lock (prevents timeout)';
COMMENT ON FUNCTION cleanup_stale_locks IS 'Removes locks inactive for >120 seconds';
