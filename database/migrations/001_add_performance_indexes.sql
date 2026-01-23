-- Performance Indexes Migration
-- Adds critical indexes to improve query performance by 5-50x
-- Created: 2026-01-23
-- Part of: Phase 1 Quick Wins

-- Building Elements - most frequently queried
CREATE INDEX IF NOT EXISTS idx_building_elements_building_id
ON building_elements(building_id);

CREATE INDEX IF NOT EXISTS idx_building_elements_urgency
ON building_elements(urgency);

CREATE INDEX IF NOT EXISTS idx_building_elements_status
ON building_elements(status);

CREATE INDEX IF NOT EXISTS idx_building_elements_created_at
ON building_elements(created_at DESC);

-- Budget Lines - critical for budget operations
CREATE INDEX IF NOT EXISTS idx_budget_lines_element_id
ON budget_lines(element_id);

CREATE INDEX IF NOT EXISTS idx_budget_lines_budget_type
ON budget_lines(budget_type);

CREATE INDEX IF NOT EXISTS idx_budget_lines_category
ON budget_lines(category);

-- Building OPEX - for TCO calculations
CREATE INDEX IF NOT EXISTS idx_building_opex_building_id
ON building_opex(building_id);

CREATE INDEX IF NOT EXISTS idx_building_opex_category
ON building_opex(category);

-- Reports - for project reporting
CREATE INDEX IF NOT EXISTS idx_reports_project_id
ON reports(project_id);

CREATE INDEX IF NOT EXISTS idx_reports_created_at
ON reports(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_reports_status
ON reports(status);

-- Buildings - for project queries
CREATE INDEX IF NOT EXISTS idx_buildings_project_id
ON buildings(project_id);

CREATE INDEX IF NOT EXISTS idx_buildings_status
ON buildings(status);

-- Activity Log - for audit trail
CREATE INDEX IF NOT EXISTS idx_activity_log_entity_type_id
ON activity_log(entity_type, entity_id);

CREATE INDEX IF NOT EXISTS idx_activity_log_user_id
ON activity_log(user_id);

CREATE INDEX IF NOT EXISTS idx_activity_log_created_at
ON activity_log(created_at DESC);

-- Usage Logs - for analytics
CREATE INDEX IF NOT EXISTS idx_usage_logs_user_id
ON usage_logs(user_id);

CREATE INDEX IF NOT EXISTS idx_usage_logs_action
ON usage_logs(action);

CREATE INDEX IF NOT EXISTS idx_usage_logs_created_at
ON usage_logs(created_at DESC);

-- Silent Fail Logs - for error monitoring
CREATE INDEX IF NOT EXISTS idx_silent_fail_logs_severity
ON silent_fail_logs(severity);

CREATE INDEX IF NOT EXISTS idx_silent_fail_logs_created_at
ON silent_fail_logs(created_at DESC);

CREATE INDEX IF NOT EXISTS idx_silent_fail_logs_endpoint
ON silent_fail_logs(endpoint);

-- Project Access - for permission checks
CREATE INDEX IF NOT EXISTS idx_project_access_project_id
ON project_access(project_id);

CREATE INDEX IF NOT EXISTS idx_project_access_user_id
ON project_access(user_id);

CREATE INDEX IF NOT EXISTS idx_project_access_role
ON project_access(role);

-- Composite indexes for common query patterns
CREATE INDEX IF NOT EXISTS idx_building_elements_building_status
ON building_elements(building_id, status);

CREATE INDEX IF NOT EXISTS idx_building_elements_building_urgency
ON building_elements(building_id, urgency);

CREATE INDEX IF NOT EXISTS idx_budget_lines_element_type
ON budget_lines(element_id, budget_type);

-- Comments
COMMENT ON INDEX idx_building_elements_building_id IS 'Speeds up element lookups by building';
COMMENT ON INDEX idx_budget_lines_element_id IS 'Speeds up budget line lookups by element';
COMMENT ON INDEX idx_building_opex_building_id IS 'Speeds up OPEX lookups for TCO calculations';
COMMENT ON INDEX idx_reports_project_id IS 'Speeds up report listings by project';
COMMENT ON INDEX idx_buildings_project_id IS 'Speeds up building listings by project';

-- Analyze tables to update statistics after index creation
ANALYZE building_elements;
ANALYZE budget_lines;
ANALYZE building_opex;
ANALYZE reports;
ANALYZE buildings;
ANALYZE activity_log;
ANALYZE usage_logs;
ANALYZE silent_fail_logs;
ANALYZE project_access;
