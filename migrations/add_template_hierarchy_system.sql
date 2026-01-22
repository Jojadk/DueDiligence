-- =====================================================
-- 3-Level Template Hierarchy System
-- =====================================================
-- Adds support for Global, Customer, and Project scoped templates
-- Priority: Project > Customer > Global
-- =====================================================

-- Add scope columns to existing report_templates table
ALTER TABLE report_templates
ADD COLUMN IF NOT EXISTS template_scope VARCHAR(20) DEFAULT 'global',
ADD COLUMN IF NOT EXISTS customer_id BIGINT REFERENCES customers(id) ON DELETE CASCADE,
ADD COLUMN IF NOT EXISTS project_id BIGINT REFERENCES projects(id) ON DELETE CASCADE,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS parent_template_id BIGINT REFERENCES report_templates(id) ON DELETE SET NULL;

-- Add constraints to ensure scope integrity
ALTER TABLE report_templates
ADD CONSTRAINT chk_template_scope CHECK (template_scope IN ('global', 'customer', 'project'));

-- Ensure scope consistency
ALTER TABLE report_templates
ADD CONSTRAINT chk_global_scope CHECK (
    (template_scope = 'global' AND customer_id IS NULL AND project_id IS NULL) OR
    (template_scope = 'customer' AND customer_id IS NOT NULL AND project_id IS NULL) OR
    (template_scope = 'project' AND project_id IS NOT NULL)
);

-- Add indexes for performance
CREATE INDEX IF NOT EXISTS idx_report_templates_scope ON report_templates(template_scope);
CREATE INDEX IF NOT EXISTS idx_report_templates_customer ON report_templates(customer_id);
CREATE INDEX IF NOT EXISTS idx_report_templates_project ON report_templates(project_id);
CREATE INDEX IF NOT EXISTS idx_report_templates_active ON report_templates(is_active);
CREATE INDEX IF NOT EXISTS idx_report_templates_parent ON report_templates(parent_template_id);

-- Composite index for efficient scope queries
CREATE INDEX IF NOT EXISTS idx_report_templates_scope_lookup
ON report_templates(template_scope, customer_id, project_id, is_active);

-- Comments
COMMENT ON COLUMN report_templates.template_scope IS 'Scope level: global (all), customer (customer-specific), project (project-specific)';
COMMENT ON COLUMN report_templates.customer_id IS 'Customer ID for customer-scoped templates';
COMMENT ON COLUMN report_templates.project_id IS 'Project ID for project-scoped templates';
COMMENT ON COLUMN report_templates.parent_template_id IS 'Parent template this was derived from (for tracking inheritance)';

-- =====================================================
-- Template Brand Settings (Customer-level)
-- =====================================================
-- Store customer branding preferences for templates
CREATE TABLE IF NOT EXISTS template_brand_settings (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES customers(id) ON DELETE CASCADE,

    -- Branding
    logo_url VARCHAR(500),
    primary_color VARCHAR(7) DEFAULT '#1e40af',      -- Hex color
    secondary_color VARCHAR(7) DEFAULT '#3b82f6',
    accent_color VARCHAR(7) DEFAULT '#60a5fa',
    text_color VARCHAR(7) DEFAULT '#1f2937',

    -- Typography
    font_family VARCHAR(100) DEFAULT 'Arial, sans-serif',
    heading_font VARCHAR(100),
    body_font_size INTEGER DEFAULT 11,               -- Points

    -- Layout
    page_margin_top INTEGER DEFAULT 25,              -- mm
    page_margin_bottom INTEGER DEFAULT 25,
    page_margin_left INTEGER DEFAULT 20,
    page_margin_right INTEGER DEFAULT 20,

    -- Header/Footer
    header_template TEXT,                            -- HTML template for header
    footer_template TEXT,                            -- HTML template for footer
    show_page_numbers BOOLEAN DEFAULT TRUE,
    page_number_format VARCHAR(50) DEFAULT 'Page {page} of {total}',

    -- Legal/Compliance
    disclaimer_text TEXT,
    copyright_text VARCHAR(255),
    confidentiality_statement TEXT,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Ensure one setting per customer
    CONSTRAINT uq_brand_settings_customer UNIQUE (customer_id)
);

CREATE INDEX IF NOT EXISTS idx_brand_settings_customer ON template_brand_settings(customer_id);

COMMENT ON TABLE template_brand_settings IS 'Customer-specific branding settings for report templates';

-- =====================================================
-- Template Usage Tracking
-- =====================================================
-- Track template usage for analytics and optimization
CREATE TABLE IF NOT EXISTS template_usage_log (
    id BIGSERIAL PRIMARY KEY,
    template_id BIGINT NOT NULL REFERENCES report_templates(id) ON DELETE CASCADE,
    report_id BIGINT REFERENCES reports(id) ON DELETE SET NULL,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    project_id BIGINT REFERENCES projects(id) ON DELETE CASCADE,
    customer_id BIGINT REFERENCES customers(id) ON DELETE CASCADE,

    -- Metrics
    render_duration_ms INTEGER,                      -- How long did rendering take?
    error_count INTEGER DEFAULT 0,                   -- Silent fail errors during render
    output_size_kb INTEGER,                          -- Size of generated output

    -- Timestamp
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_usage_template (template_id),
    INDEX idx_usage_user (user_id),
    INDEX idx_usage_project (project_id),
    INDEX idx_usage_timestamp (used_at DESC)
);

COMMENT ON TABLE template_usage_log IS 'Tracks template usage for analytics and optimization';

-- =====================================================
-- View: Available Templates for Project
-- =====================================================
-- Returns all templates available to a project (Global + Customer + Project)
-- Ordered by priority (Project > Customer > Global)
CREATE OR REPLACE VIEW v_available_templates AS
SELECT
    rt.id,
    rt.name,
    rt.template_content,
    rt.report_type,
    rt.template_scope,
    rt.customer_id,
    rt.project_id,
    rt.is_active,
    rt.parent_template_id,
    rt.created_at,
    rt.updated_at,

    -- Priority: 1 = Project, 2 = Customer, 3 = Global
    CASE
        WHEN rt.template_scope = 'project' THEN 1
        WHEN rt.template_scope = 'customer' THEN 2
        WHEN rt.template_scope = 'global' THEN 3
    END as priority,

    -- Parent template info
    pt.name as parent_template_name,

    -- Creator info
    u.name as created_by_name,

    -- Brand settings (if customer template)
    tbs.logo_url,
    tbs.primary_color,
    tbs.secondary_color

FROM report_templates rt
LEFT JOIN report_templates pt ON rt.parent_template_id = pt.id
LEFT JOIN users u ON rt.created_by_user_id = u.id
LEFT JOIN template_brand_settings tbs ON rt.customer_id = tbs.customer_id
WHERE rt.is_active = TRUE
ORDER BY priority ASC, rt.created_at DESC;

COMMENT ON VIEW v_available_templates IS 'All active templates with scope priority';

-- =====================================================
-- View: Template Usage Statistics
-- =====================================================
CREATE OR REPLACE VIEW v_template_statistics AS
SELECT
    rt.id as template_id,
    rt.name as template_name,
    rt.template_scope,
    COUNT(tul.id) as usage_count,
    COUNT(DISTINCT tul.user_id) as unique_users,
    COUNT(DISTINCT tul.project_id) as unique_projects,
    AVG(tul.render_duration_ms) as avg_render_ms,
    SUM(tul.error_count) as total_errors,
    MAX(tul.used_at) as last_used,
    MIN(tul.used_at) as first_used
FROM report_templates rt
LEFT JOIN template_usage_log tul ON rt.id = tul.template_id
WHERE rt.is_active = TRUE
GROUP BY rt.id, rt.name, rt.template_scope
ORDER BY usage_count DESC;

COMMENT ON VIEW v_template_statistics IS 'Template usage statistics for analytics';

-- =====================================================
-- Function: Get Templates for Project
-- =====================================================
-- Returns templates available for a specific project
-- with proper scope resolution
CREATE OR REPLACE FUNCTION get_templates_for_project(
    p_project_id BIGINT,
    p_report_type VARCHAR DEFAULT NULL
)
RETURNS TABLE (
    id BIGINT,
    name VARCHAR,
    template_scope VARCHAR,
    priority INTEGER,
    has_brand_settings BOOLEAN
) AS $$
BEGIN
    RETURN QUERY
    SELECT DISTINCT ON (rt.name, rt.report_type)
        rt.id,
        rt.name,
        rt.template_scope,
        CASE
            WHEN rt.template_scope = 'project' THEN 1
            WHEN rt.template_scope = 'customer' THEN 2
            WHEN rt.template_scope = 'global' THEN 3
        END as priority,
        tbs.id IS NOT NULL as has_brand_settings
    FROM report_templates rt
    LEFT JOIN projects p ON p.id = p_project_id
    LEFT JOIN template_brand_settings tbs ON rt.customer_id = tbs.customer_id
    WHERE rt.is_active = TRUE
        AND (p_report_type IS NULL OR rt.report_type = p_report_type)
        AND (
            -- Global templates
            (rt.template_scope = 'global') OR
            -- Customer templates (match project's customer)
            (rt.template_scope = 'customer' AND rt.customer_id = p.customer_id) OR
            -- Project templates (exact match)
            (rt.template_scope = 'project' AND rt.project_id = p_project_id)
        )
    ORDER BY rt.name, rt.report_type, priority ASC;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION get_templates_for_project IS 'Get all templates available for a project with scope resolution';

-- =====================================================
-- Function: Clone Template
-- =====================================================
-- Clone a template to a different scope (e.g., Global -> Customer)
CREATE OR REPLACE FUNCTION clone_template(
    p_source_template_id BIGINT,
    p_new_scope VARCHAR,
    p_customer_id BIGINT DEFAULT NULL,
    p_project_id BIGINT DEFAULT NULL,
    p_user_id BIGINT DEFAULT NULL
)
RETURNS BIGINT AS $$
DECLARE
    v_new_template_id BIGINT;
BEGIN
    INSERT INTO report_templates (
        name,
        template_content,
        report_type,
        template_scope,
        customer_id,
        project_id,
        parent_template_id,
        created_by_user_id
    )
    SELECT
        name || ' (Copy)',
        template_content,
        report_type,
        p_new_scope,
        p_customer_id,
        p_project_id,
        p_source_template_id,
        p_user_id
    FROM report_templates
    WHERE id = p_source_template_id
    RETURNING id INTO v_new_template_id;

    RETURN v_new_template_id;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION clone_template IS 'Clone a template to a different scope level';

-- =====================================================
-- Update existing templates to global scope
-- =====================================================
UPDATE report_templates
SET template_scope = 'global'
WHERE template_scope IS NULL OR template_scope = '';

-- =====================================================
-- Sample Data: Brand Settings
-- =====================================================
-- Add example brand settings (uncomment for seeding)
/*
INSERT INTO template_brand_settings (customer_id, primary_color, secondary_color, logo_url, disclaimer_text)
SELECT
    id,
    '#1e40af',
    '#3b82f6',
    '/uploads/customer-logos/' || id || '.png',
    'This report is confidential and intended solely for the use of ' || name || '. Unauthorized distribution is prohibited.'
FROM customers
WHERE id IN (SELECT DISTINCT customer_id FROM projects)
ON CONFLICT (customer_id) DO NOTHING;
*/
