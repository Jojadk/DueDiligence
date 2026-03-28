-- =====================================================
-- Performance Optimization: Missing Indexes
-- =====================================================
-- Date: 2026-01-25
-- Description: Add missing indexes for frequently queried columns
-- =====================================================

-- Images table indexes
CREATE INDEX IF NOT EXISTS idx_images_entity ON images(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_images_project_id ON images(project_id) WHERE project_id IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_images_sort_order ON images(entity_type, entity_id, sort_order);

-- Notifications table indexes
CREATE INDEX IF NOT EXISTS idx_notifications_user_id ON notifications(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, read) WHERE read = FALSE;

-- Projects table indexes for ownership queries
CREATE INDEX IF NOT EXISTS idx_projects_user_id ON projects(user_id, created_at DESC);
CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status) WHERE status IS NOT NULL;

-- Customers table for search
CREATE INDEX IF NOT EXISTS idx_customers_name_trgm ON customers USING GIN (name gin_trgm_ops);

-- Buildings cache optimization
CREATE INDEX IF NOT EXISTS idx_buildings_project_created ON buildings(project_id, created_at DESC);

-- Snapshots for quick project lookup
CREATE INDEX IF NOT EXISTS idx_snapshots_project_id ON snapshots(project_id, created_at DESC);

-- Activity log optimization
CREATE INDEX IF NOT EXISTS idx_activity_log_compound ON activity_log(entity_type, entity_id, created_at DESC);

COMMENT ON INDEX idx_images_entity IS 'Optimize image queries by entity type and ID';
COMMENT ON INDEX idx_notifications_user_read IS 'Fast unread notification queries';
COMMENT ON INDEX idx_projects_user_id IS 'Optimize user project listing';
COMMENT ON INDEX idx_customers_name_trgm IS 'Fuzzy search on customer names using trigram index';
