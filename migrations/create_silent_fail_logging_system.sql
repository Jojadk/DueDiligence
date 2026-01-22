-- =====================================================
-- Silent Fail Logging System
-- =====================================================
-- Purpose: Track all silent failures across the platform
-- for debugging, monitoring, and proactive error resolution
-- =====================================================

-- Drop existing table if exists (for clean reinstall)
DROP TABLE IF EXISTS silent_fail_logs CASCADE;

-- Main silent fail logs table
CREATE TABLE silent_fail_logs (
    id BIGSERIAL PRIMARY KEY,

    -- Context Information
    module VARCHAR(100) NOT NULL,                    -- e.g., 'template_parser', 'report_builder', 'budget'
    operation VARCHAR(255) NOT NULL,                 -- e.g., 'parse_variable', 'render_template', 'calculate_capex'
    error_type VARCHAR(100) NOT NULL,                -- e.g., 'missing_variable', 'parse_error', 'division_by_zero'

    -- Error Details
    error_message TEXT NOT NULL,                     -- Human-readable error message
    error_context JSON,                              -- Detailed context (variable path, line number, etc.)
    rendered_output TEXT,                            -- What was shown to user: [Missing Variable: project.name]

    -- Request Context
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    project_id BIGINT REFERENCES projects(id) ON DELETE CASCADE,
    customer_id BIGINT REFERENCES customers(id) ON DELETE CASCADE,

    -- Technical Details
    stack_trace TEXT,                                -- Optional PHP stack trace
    request_url VARCHAR(500),                        -- URL where error occurred
    request_method VARCHAR(10),                      -- GET, POST, etc.
    user_agent TEXT,                                 -- Browser info
    ip_address VARCHAR(45),                          -- IPv4 or IPv6

    -- Metadata
    severity VARCHAR(20) DEFAULT 'warning',          -- 'info', 'warning', 'error', 'critical'
    is_resolved BOOLEAN DEFAULT FALSE,               -- Has this been fixed?
    resolved_at TIMESTAMP,
    resolved_by_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    resolution_notes TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes for performance
    INDEX idx_silent_fail_module (module),
    INDEX idx_silent_fail_error_type (error_type),
    INDEX idx_silent_fail_user (user_id),
    INDEX idx_silent_fail_project (project_id),
    INDEX idx_silent_fail_severity (severity),
    INDEX idx_silent_fail_resolved (is_resolved),
    INDEX idx_silent_fail_created (created_at DESC)
);

-- Comment on table
COMMENT ON TABLE silent_fail_logs IS 'Tracks all silent failures across the platform for debugging and monitoring';

-- Comment on columns
COMMENT ON COLUMN silent_fail_logs.module IS 'Module where the error occurred (e.g., template_parser, report_builder)';
COMMENT ON COLUMN silent_fail_logs.operation IS 'Specific operation that failed (e.g., parse_variable, calculate_total)';
COMMENT ON COLUMN silent_fail_logs.error_type IS 'Category of error (e.g., missing_variable, parse_error)';
COMMENT ON COLUMN silent_fail_logs.error_context IS 'JSON with detailed context about the error';
COMMENT ON COLUMN silent_fail_logs.rendered_output IS 'What was displayed to the user (e.g., [Missing Variable: project.name])';
COMMENT ON COLUMN silent_fail_logs.severity IS 'Error severity: info, warning, error, critical';

-- =====================================================
-- Statistics View for Monitoring
-- =====================================================
CREATE OR REPLACE VIEW v_silent_fail_statistics AS
SELECT
    module,
    error_type,
    severity,
    COUNT(*) as occurrence_count,
    COUNT(DISTINCT user_id) as affected_users,
    COUNT(DISTINCT project_id) as affected_projects,
    MAX(created_at) as last_occurrence,
    MIN(created_at) as first_occurrence,
    ROUND(AVG(EXTRACT(EPOCH FROM (COALESCE(resolved_at, NOW()) - created_at))), 2) as avg_resolution_time_seconds
FROM silent_fail_logs
WHERE created_at >= NOW() - INTERVAL '30 days'
GROUP BY module, error_type, severity
ORDER BY occurrence_count DESC;

COMMENT ON VIEW v_silent_fail_statistics IS 'Statistics on silent failures for the last 30 days';

-- =====================================================
-- Unresolved Errors View (for admin dashboard)
-- =====================================================
CREATE OR REPLACE VIEW v_silent_fail_unresolved AS
SELECT
    sfl.id,
    sfl.module,
    sfl.operation,
    sfl.error_type,
    sfl.error_message,
    sfl.severity,
    sfl.created_at,
    u.name as user_name,
    p.name as project_name,
    c.name as customer_name,
    COUNT(*) OVER (PARTITION BY sfl.module, sfl.error_type) as similar_error_count
FROM silent_fail_logs sfl
LEFT JOIN users u ON sfl.user_id = u.id
LEFT JOIN projects p ON sfl.project_id = p.id
LEFT JOIN customers c ON sfl.customer_id = c.id
WHERE sfl.is_resolved = FALSE
ORDER BY sfl.severity DESC, sfl.created_at DESC;

COMMENT ON VIEW v_silent_fail_unresolved IS 'All unresolved silent failures with context';

-- =====================================================
-- Cleanup Old Resolved Logs (keep 90 days)
-- =====================================================
-- Run this periodically via cron or scheduled task
-- DELETE FROM silent_fail_logs
-- WHERE is_resolved = TRUE
-- AND resolved_at < NOW() - INTERVAL '90 days';
