-- Performance Optimizations Migration
-- Purpose: Add indexes and optimizations for multi-user scenarios

-- ============================================================================
-- Missing Indexes for Foreign Keys and Lookups
-- ============================================================================

-- Buildings
CREATE INDEX IF NOT EXISTS idx_buildings_project ON buildings(project_id);
CREATE INDEX IF NOT EXISTS idx_buildings_display_order ON buildings(display_order) WHERE display_order IS NOT NULL;

-- Building Elements
CREATE INDEX IF NOT EXISTS idx_elements_building ON building_elements(building_id);
CREATE INDEX IF NOT EXISTS idx_elements_urgency ON building_elements(urgency);
CREATE INDEX IF NOT EXISTS idx_elements_display_order ON building_elements(display_order) WHERE display_order IS NOT NULL;

-- Element Images
CREATE INDEX IF NOT EXISTS idx_images_element ON element_images(element_id);
CREATE INDEX IF NOT EXISTS idx_images_primary ON element_images(is_primary) WHERE is_primary = TRUE;

-- Budget Templates
CREATE INDEX IF NOT EXISTS idx_budget_templates_project ON budget_templates(project_id);

-- Budget Template Items
CREATE INDEX IF NOT EXISTS idx_template_items_template ON budget_template_items(template_id);
CREATE INDEX IF NOT EXISTS idx_template_items_parent ON budget_template_items(parent_id) WHERE parent_id IS NOT NULL;

-- Reports
CREATE INDEX IF NOT EXISTS idx_reports_project ON reports(project_id);
CREATE INDEX IF NOT EXISTS idx_reports_type ON reports(report_type);

-- Project Members
CREATE INDEX IF NOT EXISTS idx_project_members_project ON project_members(project_id);
CREATE INDEX IF NOT EXISTS idx_project_members_user ON project_members(user_id);
CREATE INDEX IF NOT EXISTS idx_project_members_composite ON project_members(project_id, user_id);

-- Activity Log (if exists)
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.tables WHERE table_name = 'activity_log') THEN
        CREATE INDEX IF NOT EXISTS idx_activity_log_user ON activity_log(user_id);
        CREATE INDEX IF NOT EXISTS idx_activity_log_timestamp ON activity_log(created_at DESC);
        CREATE INDEX IF NOT EXISTS idx_activity_log_record ON activity_log(record_type, record_id);
    END IF;
END $$;

-- ============================================================================
-- Composite Indexes for Common Queries
-- ============================================================================

-- Elements by building and urgency (for filtering/sorting)
CREATE INDEX IF NOT EXISTS idx_elements_building_urgency
ON building_elements(building_id, urgency DESC);

-- Elements by building and display order (for sorting)
CREATE INDEX IF NOT EXISTS idx_elements_building_order
ON building_elements(building_id, display_order ASC NULLS LAST);

-- Images by element and order (for galleries)
CREATE INDEX IF NOT EXISTS idx_images_element_order
ON element_images(element_id, display_order ASC, created_at DESC);

-- Template items for hierarchy queries
CREATE INDEX IF NOT EXISTS idx_template_items_hierarchy
ON budget_template_items(template_id, parent_id, display_order);

-- ============================================================================
-- Partial Indexes (for boolean columns)
-- ============================================================================

-- Index only active/enabled records
CREATE INDEX IF NOT EXISTS idx_projects_active
ON projects(id) WHERE is_archived = FALSE OR is_archived IS NULL;

-- Index only group items in templates
CREATE INDEX IF NOT EXISTS idx_template_items_groups
ON budget_template_items(template_id) WHERE is_group = TRUE;

-- ============================================================================
-- Expression Indexes
-- ============================================================================

-- Case-insensitive search on project names
CREATE INDEX IF NOT EXISTS idx_projects_name_lower
ON projects(LOWER(name));

-- Case-insensitive search on building names
CREATE INDEX IF NOT EXISTS idx_buildings_name_lower
ON buildings(LOWER(name));

-- Case-insensitive search on element names
CREATE INDEX IF NOT EXISTS idx_elements_name_lower
ON building_elements(LOWER(name));

-- ============================================================================
-- Materialized Views for Heavy Aggregations
-- ============================================================================

-- Drop and recreate with indexes
DROP MATERIALIZED VIEW IF EXISTS mv_project_statistics CASCADE;

CREATE MATERIALIZED VIEW mv_project_statistics AS
SELECT
    p.id as project_id,
    p.name as project_name,
    COUNT(DISTINCT b.id) as building_count,
    COUNT(DISTINCT be.id) as element_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'critical' THEN be.id END) as critical_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'high' THEN be.id END) as high_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'medium' THEN be.id END) as medium_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'low' THEN be.id END) as low_count,
    COALESCE(SUM(be.capex), 0) as total_capex,
    COALESCE(AVG(be.condition_score), 0) as avg_condition_score,
    COUNT(DISTINCT ei.id) as image_count,
    MAX(be.updated_at) as last_element_update
FROM projects p
LEFT JOIN buildings b ON b.project_id = p.id
LEFT JOIN building_elements be ON be.building_id = b.id
LEFT JOIN element_images ei ON ei.element_id = be.id
GROUP BY p.id, p.name;

-- Add indexes to materialized view
CREATE UNIQUE INDEX idx_mv_project_stats_id ON mv_project_statistics(project_id);
CREATE INDEX idx_mv_project_stats_capex ON mv_project_statistics(total_capex DESC);
CREATE INDEX idx_mv_project_stats_critical ON mv_project_statistics(critical_count DESC);

-- ============================================================================
-- Function to Refresh Materialized Views
-- ============================================================================

CREATE OR REPLACE FUNCTION refresh_project_statistics()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_project_statistics;
END;
$$ LANGUAGE plpgsql;

-- ============================================================================
-- Automatic View Refresh on Changes
-- ============================================================================

-- Trigger function to mark views for refresh
CREATE OR REPLACE FUNCTION mark_stats_for_refresh()
RETURNS TRIGGER AS $$
BEGIN
    -- Set a flag in a temporary table or just refresh immediately
    -- For now, we'll refresh immediately (can be optimized with background job)
    PERFORM refresh_project_statistics();
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Apply triggers (commented out by default - enable if needed)
-- Refreshing on every change can be expensive, better to use cron job
/*
CREATE TRIGGER trg_refresh_stats_on_element_change
AFTER INSERT OR UPDATE OR DELETE ON building_elements
FOR EACH STATEMENT EXECUTE FUNCTION mark_stats_for_refresh();

CREATE TRIGGER trg_refresh_stats_on_building_change
AFTER INSERT OR UPDATE OR DELETE ON buildings
FOR EACH STATEMENT EXECUTE FUNCTION mark_stats_for_refresh();
*/

-- ============================================================================
-- Query Optimization Settings
-- ============================================================================

-- Increase statistics target for frequently queried columns
ALTER TABLE projects ALTER COLUMN name SET STATISTICS 1000;
ALTER TABLE buildings ALTER COLUMN name SET STATISTICS 1000;
ALTER TABLE building_elements ALTER COLUMN name SET STATISTICS 1000;

-- ============================================================================
-- Caching Table for Expensive Calculations
-- ============================================================================

CREATE TABLE IF NOT EXISTS calculation_cache (
    id SERIAL PRIMARY KEY,
    cache_key VARCHAR(255) UNIQUE NOT NULL,
    cache_value JSONB NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_cache_key ON calculation_cache(cache_key);
CREATE INDEX idx_cache_expiry ON calculation_cache(expires_at);

-- Function to get from cache
CREATE OR REPLACE FUNCTION get_cached(p_key VARCHAR(255))
RETURNS JSONB AS $$
DECLARE
    v_value JSONB;
BEGIN
    SELECT cache_value INTO v_value
    FROM calculation_cache
    WHERE cache_key = p_key
        AND expires_at > CURRENT_TIMESTAMP;

    RETURN v_value;
END;
$$ LANGUAGE plpgsql STABLE;

-- Function to set cache
CREATE OR REPLACE FUNCTION set_cached(
    p_key VARCHAR(255),
    p_value JSONB,
    p_ttl_seconds INTEGER DEFAULT 300
)
RETURNS void AS $$
BEGIN
    INSERT INTO calculation_cache (cache_key, cache_value, expires_at)
    VALUES (p_key, p_value, CURRENT_TIMESTAMP + (p_ttl_seconds || ' seconds')::INTERVAL)
    ON CONFLICT (cache_key)
    DO UPDATE SET
        cache_value = EXCLUDED.cache_value,
        expires_at = EXCLUDED.expires_at,
        created_at = CURRENT_TIMESTAMP;
END;
$$ LANGUAGE plpgsql;

-- Function to clear expired cache entries
CREATE OR REPLACE FUNCTION cleanup_expired_cache()
RETURNS INTEGER AS $$
DECLARE
    v_deleted INTEGER;
BEGIN
    DELETE FROM calculation_cache
    WHERE expires_at < CURRENT_TIMESTAMP;

    GET DIAGNOSTICS v_deleted = ROW_COUNT;
    RETURN v_deleted;
END;
$$ LANGUAGE plpgsql;

-- ============================================================================
-- Database Maintenance Functions
-- ============================================================================

-- Vacuum and analyze frequently updated tables
CREATE OR REPLACE FUNCTION maintain_hot_tables()
RETURNS void AS $$
BEGIN
    -- Vacuum and analyze tables with high write activity
    VACUUM ANALYZE record_locks;
    VACUUM ANALYZE record_changes;
    VACUUM ANALYZE building_elements;
    VACUUM ANALYZE budget_template_items;
    VACUUM ANALYZE calculation_cache;
END;
$$ LANGUAGE plpgsql;

-- ============================================================================
-- Scheduled Maintenance (if pg_cron is available)
-- ============================================================================

-- Uncomment if pg_cron extension is enabled:
/*
-- Refresh materialized views every 5 minutes
SELECT cron.schedule(
    'refresh-project-stats',
    '*/5 * * * *',
    'SELECT refresh_project_statistics();'
);

-- Clean up expired cache every 10 minutes
SELECT cron.schedule(
    'cleanup-cache',
    '*/10 * * * *',
    'SELECT cleanup_expired_cache();'
);

-- Maintain hot tables every hour
SELECT cron.schedule(
    'maintain-tables',
    '0 * * * *',
    'SELECT maintain_hot_tables();'
);
*/

-- ============================================================================
-- Comments
-- ============================================================================

COMMENT ON MATERIALIZED VIEW mv_project_statistics IS 'Cached project statistics for dashboard performance';
COMMENT ON TABLE calculation_cache IS 'Generic cache for expensive calculations (5min TTL default)';
COMMENT ON FUNCTION refresh_project_statistics IS 'Refresh project statistics materialized view';
COMMENT ON FUNCTION get_cached IS 'Retrieve value from cache if not expired';
COMMENT ON FUNCTION set_cached IS 'Store value in cache with TTL';
COMMENT ON FUNCTION cleanup_expired_cache IS 'Remove expired cache entries';
