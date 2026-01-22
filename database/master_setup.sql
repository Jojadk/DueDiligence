-- =====================================================
-- DueDiligence Master Database Setup
-- =====================================================
-- Version: 2.0.0
-- Date: 2026-01-22
-- Description: Complete database setup for advanced report engine
-- Includes: Tables, Indexes, Views, Functions, Triggers, Seed Data
-- =====================================================

-- Set timezone
SET TIME ZONE 'Europe/Copenhagen';

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm"; -- For fuzzy search

-- =====================================================
-- CLEANUP (for fresh install)
-- =====================================================
-- Uncomment for fresh install:
-- DROP TABLE IF EXISTS silent_fail_logs CASCADE;
-- DROP TABLE IF EXISTS template_usage_log CASCADE;
-- DROP TABLE IF EXISTS template_brand_settings CASCADE;
-- DROP VIEW IF EXISTS v_available_templates CASCADE;
-- DROP VIEW IF EXISTS v_silent_fail_statistics CASCADE;
-- DROP VIEW IF EXISTS v_template_statistics CASCADE;

-- =====================================================
-- PART 1: SILENT FAIL LOGGING SYSTEM
-- =====================================================

CREATE TABLE IF NOT EXISTS silent_fail_logs (
    id BIGSERIAL PRIMARY KEY,

    -- Context (indexed for fast queries)
    module VARCHAR(100) NOT NULL,
    operation VARCHAR(255) NOT NULL,
    error_type VARCHAR(100) NOT NULL,

    -- Error Details
    error_message TEXT NOT NULL,
    error_context JSONB,                             -- JSONB for better querying
    rendered_output TEXT,

    -- Request Context (nullable for background jobs)
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    project_id BIGINT REFERENCES projects(id) ON DELETE CASCADE,
    customer_id BIGINT REFERENCES customers(id) ON DELETE CASCADE,

    -- Technical Details (lazy loaded)
    stack_trace TEXT,
    request_url VARCHAR(500),
    request_method VARCHAR(10),
    user_agent TEXT,
    ip_address INET,                                 -- INET for IP validation

    -- Metadata
    severity VARCHAR(20) DEFAULT 'warning' CHECK (severity IN ('info', 'warning', 'error', 'critical')),
    is_resolved BOOLEAN DEFAULT FALSE,
    resolved_at TIMESTAMP,
    resolved_by_user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    resolution_notes TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Optimized indexes
CREATE INDEX IF NOT EXISTS idx_silent_fail_module_type ON silent_fail_logs(module, error_type);
CREATE INDEX IF NOT EXISTS idx_silent_fail_unresolved ON silent_fail_logs(is_resolved, severity) WHERE is_resolved = FALSE;
CREATE INDEX IF NOT EXISTS idx_silent_fail_created ON silent_fail_logs(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_silent_fail_project ON silent_fail_logs(project_id) WHERE project_id IS NOT NULL;

-- JSONB index for fast context queries
CREATE INDEX IF NOT EXISTS idx_silent_fail_context ON silent_fail_logs USING GIN (error_context);

-- Comments
COMMENT ON TABLE silent_fail_logs IS 'Centralized error logging with silent fail pattern - never crashes, always logs';
COMMENT ON COLUMN silent_fail_logs.error_context IS 'JSONB for flexible context storage (variable paths, line numbers, etc.)';
COMMENT ON COLUMN silent_fail_logs.severity IS 'Error severity: info < warning < error < critical';

-- =====================================================
-- PART 2: TEMPLATE HIERARCHY SYSTEM
-- =====================================================

-- Update existing report_templates table
DO $$
BEGIN
    -- Add columns if they don't exist
    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'report_templates' AND column_name = 'template_scope') THEN
        ALTER TABLE report_templates ADD COLUMN template_scope VARCHAR(20) DEFAULT 'global';
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'report_templates' AND column_name = 'customer_id') THEN
        ALTER TABLE report_templates ADD COLUMN customer_id BIGINT REFERENCES customers(id) ON DELETE CASCADE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'report_templates' AND column_name = 'project_id') THEN
        ALTER TABLE report_templates ADD COLUMN project_id BIGINT REFERENCES projects(id) ON DELETE CASCADE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'report_templates' AND column_name = 'is_active') THEN
        ALTER TABLE report_templates ADD COLUMN is_active BOOLEAN DEFAULT TRUE;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'report_templates' AND column_name = 'parent_template_id') THEN
        ALTER TABLE report_templates ADD COLUMN parent_template_id BIGINT REFERENCES report_templates(id) ON DELETE SET NULL;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'report_templates' AND column_name = 'compiled_template') THEN
        ALTER TABLE report_templates ADD COLUMN compiled_template TEXT;
    END IF;

    IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name = 'report_templates' AND column_name = 'template_hash') THEN
        ALTER TABLE report_templates ADD COLUMN template_hash VARCHAR(64);
    END IF;
END $$;

-- Add constraints
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_template_scope') THEN
        ALTER TABLE report_templates ADD CONSTRAINT chk_template_scope
            CHECK (template_scope IN ('global', 'customer', 'project'));
    END IF;

    IF NOT EXISTS (SELECT 1 FROM pg_constraint WHERE conname = 'chk_global_scope') THEN
        ALTER TABLE report_templates ADD CONSTRAINT chk_global_scope CHECK (
            (template_scope = 'global' AND customer_id IS NULL AND project_id IS NULL) OR
            (template_scope = 'customer' AND customer_id IS NOT NULL AND project_id IS NULL) OR
            (template_scope = 'project' AND project_id IS NOT NULL)
        );
    END IF;
END $$;

-- Optimized indexes
CREATE INDEX IF NOT EXISTS idx_report_templates_scope_lookup
    ON report_templates(template_scope, customer_id, project_id, is_active);
CREATE INDEX IF NOT EXISTS idx_report_templates_hash
    ON report_templates(template_hash) WHERE template_hash IS NOT NULL;

-- =====================================================
-- PART 3: BRAND SETTINGS
-- =====================================================

CREATE TABLE IF NOT EXISTS template_brand_settings (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL UNIQUE REFERENCES customers(id) ON DELETE CASCADE,

    -- Branding
    logo_url VARCHAR(500),
    primary_color VARCHAR(7) DEFAULT '#1e40af' CHECK (primary_color ~ '^#[0-9A-Fa-f]{6}$'),
    secondary_color VARCHAR(7) DEFAULT '#3b82f6' CHECK (secondary_color ~ '^#[0-9A-Fa-f]{6}$'),
    accent_color VARCHAR(7) DEFAULT '#60a5fa' CHECK (accent_color ~ '^#[0-9A-Fa-f]{6}$'),
    text_color VARCHAR(7) DEFAULT '#1f2937' CHECK (text_color ~ '^#[0-9A-Fa-f]{6}$'),

    -- Typography
    font_family VARCHAR(100) DEFAULT 'Arial, sans-serif',
    heading_font VARCHAR(100),
    body_font_size INTEGER DEFAULT 11 CHECK (body_font_size BETWEEN 8 AND 20),

    -- Layout (in mm)
    page_margin_top INTEGER DEFAULT 25 CHECK (page_margin_top BETWEEN 0 AND 100),
    page_margin_bottom INTEGER DEFAULT 25 CHECK (page_margin_bottom BETWEEN 0 AND 100),
    page_margin_left INTEGER DEFAULT 20 CHECK (page_margin_left BETWEEN 0 AND 100),
    page_margin_right INTEGER DEFAULT 20 CHECK (page_margin_right BETWEEN 0 AND 100),

    -- Header/Footer
    header_template TEXT,
    footer_template TEXT,
    show_page_numbers BOOLEAN DEFAULT TRUE,
    page_number_format VARCHAR(50) DEFAULT 'Side {page} af {total}',

    -- Legal/Compliance
    disclaimer_text TEXT,
    copyright_text VARCHAR(255),
    confidentiality_statement TEXT,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_brand_settings_customer ON template_brand_settings(customer_id);

COMMENT ON TABLE template_brand_settings IS 'Customer-specific branding for reports - logos, colors, fonts, disclaimers';
COMMENT ON COLUMN template_brand_settings.primary_color IS 'Hex color validated by CHECK constraint';

-- =====================================================
-- PART 4: TEMPLATE USAGE TRACKING
-- =====================================================

CREATE TABLE IF NOT EXISTS template_usage_log (
    id BIGSERIAL PRIMARY KEY,
    template_id BIGINT NOT NULL REFERENCES report_templates(id) ON DELETE CASCADE,
    report_id BIGINT REFERENCES reports(id) ON DELETE SET NULL,
    user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
    project_id BIGINT REFERENCES projects(id) ON DELETE CASCADE,
    customer_id BIGINT REFERENCES customers(id) ON DELETE CASCADE,

    -- Metrics
    render_duration_ms INTEGER CHECK (render_duration_ms >= 0),
    error_count INTEGER DEFAULT 0 CHECK (error_count >= 0),
    output_size_kb INTEGER CHECK (output_size_kb >= 0),

    -- Timestamp
    used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Partition by month for performance (optional, for high volume)
-- CREATE TABLE template_usage_log_y2026m01 PARTITION OF template_usage_log
-- FOR VALUES FROM ('2026-01-01') TO ('2026-02-01');

-- Indexes
CREATE INDEX IF NOT EXISTS idx_usage_template_time ON template_usage_log(template_id, used_at DESC);
CREATE INDEX IF NOT EXISTS idx_usage_project ON template_usage_log(project_id, used_at DESC);

COMMENT ON TABLE template_usage_log IS 'Tracks template usage for analytics and optimization';

-- =====================================================
-- PART 5: OPTIMIZED VIEWS
-- =====================================================

-- Available templates with scope priority
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
    CASE
        WHEN rt.template_scope = 'project' THEN 1
        WHEN rt.template_scope = 'customer' THEN 2
        WHEN rt.template_scope = 'global' THEN 3
    END as priority,
    pt.name as parent_template_name,
    u.name as created_by_name,
    tbs.logo_url,
    tbs.primary_color,
    tbs.secondary_color
FROM report_templates rt
LEFT JOIN report_templates pt ON rt.parent_template_id = pt.id
LEFT JOIN users u ON rt.created_by_user_id = u.id
LEFT JOIN template_brand_settings tbs ON rt.customer_id = tbs.customer_id
WHERE rt.is_active = TRUE
ORDER BY priority ASC, rt.created_at DESC;

-- Silent fail statistics (optimized for dashboard)
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
    ROUND(AVG(CASE WHEN is_resolved THEN EXTRACT(EPOCH FROM (resolved_at - created_at)) END), 2) as avg_resolution_time_seconds
FROM silent_fail_logs
WHERE created_at >= NOW() - INTERVAL '30 days'
GROUP BY module, error_type, severity
HAVING COUNT(*) > 1  -- Only show recurring errors
ORDER BY occurrence_count DESC;

-- Template usage statistics (with caching hint)
CREATE MATERIALIZED VIEW IF NOT EXISTS mv_template_statistics AS
SELECT
    rt.id as template_id,
    rt.name as template_name,
    rt.template_scope,
    COUNT(tul.id) as usage_count,
    COUNT(DISTINCT tul.user_id) as unique_users,
    COUNT(DISTINCT tul.project_id) as unique_projects,
    ROUND(AVG(tul.render_duration_ms), 2) as avg_render_ms,
    SUM(tul.error_count) as total_errors,
    MAX(tul.used_at) as last_used,
    MIN(tul.used_at) as first_used
FROM report_templates rt
LEFT JOIN template_usage_log tul ON rt.id = tul.template_id
    AND tul.used_at >= NOW() - INTERVAL '90 days'
WHERE rt.is_active = TRUE
GROUP BY rt.id, rt.name, rt.template_scope
ORDER BY usage_count DESC;

-- Create index on materialized view
CREATE UNIQUE INDEX IF NOT EXISTS idx_mv_template_stats_id ON mv_template_statistics(template_id);

-- Refresh function (call daily via cron)
CREATE OR REPLACE FUNCTION refresh_template_statistics()
RETURNS void AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY mv_template_statistics;
END;
$$ LANGUAGE plpgsql;

COMMENT ON MATERIALIZED VIEW mv_template_statistics IS 'Cached statistics - refresh daily with refresh_template_statistics()';

-- =====================================================
-- PART 6: STORED FUNCTIONS
-- =====================================================

-- Get templates for project with scope resolution
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
        rt.name::VARCHAR,
        rt.template_scope::VARCHAR,
        CASE
            WHEN rt.template_scope = 'project' THEN 1
            WHEN rt.template_scope = 'customer' THEN 2
            WHEN rt.template_scope = 'global' THEN 3
        END as priority,
        (tbs.id IS NOT NULL) as has_brand_settings
    FROM report_templates rt
    LEFT JOIN projects p ON p.id = p_project_id
    LEFT JOIN template_brand_settings tbs ON rt.customer_id = tbs.customer_id
    WHERE rt.is_active = TRUE
        AND (p_report_type IS NULL OR rt.report_type = p_report_type)
        AND (
            (rt.template_scope = 'global') OR
            (rt.template_scope = 'customer' AND rt.customer_id = p.customer_id) OR
            (rt.template_scope = 'project' AND rt.project_id = p_project_id)
        )
    ORDER BY rt.name, rt.report_type, priority ASC;
END;
$$ LANGUAGE plpgsql STABLE;

-- Clone template function
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
        created_by_user_id,
        is_active
    )
    SELECT
        name || ' (Kopi)',
        template_content,
        report_type,
        p_new_scope,
        p_customer_id,
        p_project_id,
        p_source_template_id,
        p_user_id,
        TRUE
    FROM report_templates
    WHERE id = p_source_template_id
    RETURNING id INTO v_new_template_id;

    RETURN v_new_template_id;
END;
$$ LANGUAGE plpgsql;

-- =====================================================
-- PART 7: TRIGGERS
-- =====================================================

-- Auto-update timestamps
CREATE OR REPLACE FUNCTION update_timestamp()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_template_brand_settings_updated ON template_brand_settings;
CREATE TRIGGER trg_template_brand_settings_updated
    BEFORE UPDATE ON template_brand_settings
    FOR EACH ROW
    EXECUTE FUNCTION update_timestamp();

-- Auto-calculate template hash for caching
CREATE OR REPLACE FUNCTION calculate_template_hash()
RETURNS TRIGGER AS $$
BEGIN
    NEW.template_hash = encode(digest(NEW.template_content, 'sha256'), 'hex');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trg_report_templates_hash ON report_templates;
CREATE TRIGGER trg_report_templates_hash
    BEFORE INSERT OR UPDATE OF template_content ON report_templates
    FOR EACH ROW
    EXECUTE FUNCTION calculate_template_hash();

-- =====================================================
-- PART 8: MAINTENANCE FUNCTIONS
-- =====================================================

-- Cleanup old silent fail logs
CREATE OR REPLACE FUNCTION cleanup_silent_fail_logs(p_days INTEGER DEFAULT 90)
RETURNS INTEGER AS $$
DECLARE
    v_deleted_count INTEGER;
BEGIN
    DELETE FROM silent_fail_logs
    WHERE is_resolved = TRUE
        AND resolved_at < NOW() - (p_days || ' days')::INTERVAL;

    GET DIAGNOSTICS v_deleted_count = ROW_COUNT;
    RETURN v_deleted_count;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION cleanup_silent_fail_logs IS 'Cleanup resolved errors older than N days - run weekly via cron';

-- =====================================================
-- PART 9: SEED DATA (Eksempelprojekt)
-- =====================================================

-- Insert eksempel customer
INSERT INTO customers (id, name, created_at)
VALUES (9999, 'Demo Kunde A/S', CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert eksempel project
INSERT INTO projects (id, name, customer_id, status, created_at)
VALUES (9999, 'Renovering 2026 - Demo', 9999, 'active', CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert eksempel buildings
INSERT INTO buildings (id, project_id, name, building_number, building_type, gross_area, created_at)
VALUES
    (99991, 9999, 'Hovedbygning', 'A', 'Kontorbyggeri', 1200, CURRENT_TIMESTAMP),
    (99992, 9999, 'Anneks', 'B', 'Lagerbyggeri', 500, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert eksempel elements
INSERT INTO building_elements (id, building_id, name, element_code, category, capex, urgency, condition_score, created_at)
VALUES
    (999911, 99991, 'Tag - Tagbelægning', '2.1.1', 'Klimaskærm', 250000, 'critical', 1, CURRENT_TIMESTAMP),
    (999912, 99991, 'Facade - Murværk', '2.2.1', 'Klimaskærm', 180000, 'high', 2, CURRENT_TIMESTAMP),
    (999913, 99991, 'Vinduer', '2.3.1', 'Klimaskærm', 320000, 'medium', 3, CURRENT_TIMESTAMP),
    (999921, 99992, 'Tag - Tagbelægning', '2.1.1', 'Klimaskærm', 120000, 'low', 4, CURRENT_TIMESTAMP)
ON CONFLICT (id) DO NOTHING;

-- Insert budget lines (CAPEX fordelt over tid)
INSERT INTO budget_lines (element_id, budget_type, year_0_1, year_1_2, year_3_5, year_5_10, year_10_plus)
VALUES
    (999911, 'capex', 250000, 0, 0, 0, 0),  -- Kritisk: < 1 år
    (999912, 'capex', 0, 180000, 0, 0, 0),  -- Høj: 1-2 år
    (999913, 'capex', 0, 0, 320000, 0, 0),  -- Middel: 3-5 år
    (999921, 'capex', 0, 0, 0, 120000, 0)   -- Lav: 5-10 år
ON CONFLICT (element_id, budget_type) DO NOTHING;

-- Insert brand settings for demo customer
INSERT INTO template_brand_settings (
    customer_id,
    logo_url,
    primary_color,
    secondary_color,
    disclaimer_text,
    copyright_text
)
VALUES (
    9999,
    '/uploads/demo-logo.png',
    '#1e40af',
    '#3b82f6',
    'Denne rapport er fortrolig og kun beregnet til intern brug hos Demo Kunde A/S.',
    '© 2026 Demo Kunde A/S. Alle rettigheder forbeholdes.'
)
ON CONFLICT (customer_id) DO UPDATE SET
    logo_url = EXCLUDED.logo_url,
    primary_color = EXCLUDED.primary_color,
    disclaimer_text = EXCLUDED.disclaimer_text;

-- Insert global template
INSERT INTO report_templates (
    name,
    template_content,
    report_type,
    template_scope,
    is_active,
    created_by_user_id,
    created_at
)
VALUES (
    'Due Diligence Standard (Demo)',
    '<h1>{{project.name}}</h1>
<p><strong>Kunde:</strong> {{project.customer_name}}</p>
<p><strong>Total CAPEX:</strong> {{project.total_capex | currency}}</p>

<h2>Bygninger</h2>
<ul>
{{for building in buildings}}
    <li><strong>{{building.name}}</strong> - {{building.gross_area}} m²
        <ul>
            <li>CAPEX: {{building.total_capex | currency}}</li>
            <li>Elementer: {{building.element_count}}</li>
        </ul>
    </li>
{{endfor}}
</ul>

<h2>Kritiske Elementer</h2>
{{if project.critical_count > 0}}
    <div class="alert" style="background: #fee2e2; padding: 16px; border-left: 4px solid #dc2626;">
        ⚠️ <strong>{{project.critical_count}}</strong> kritiske elementer kræver øjeblikkelig handling!
    </div>
{{else}}
    <div class="success" style="background: #d1fae5; padding: 16px; border-left: 4px solid #10b981;">
        ✅ Ingen kritiske elementer registreret.
    </div>
{{endif}}

<footer style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; font-size: 12px; color: #6b7280;">
    <p>{{brand.disclaimer ?? ''Rapport genereret af DueDiligence Platform''}}</p>
    <p>{{brand.copyright ?? ''© 2026 DueDiligence''}}</p>
</footer>',
    'due_diligence',
    'global',
    TRUE,
    NULL,
    CURRENT_TIMESTAMP
)
ON CONFLICT DO NOTHING;

-- =====================================================
-- PART 10: HEALTH CHECK VIEW
-- =====================================================

CREATE OR REPLACE VIEW v_system_health AS
SELECT
    'templates' as component,
    COUNT(*) as count,
    COUNT(*) FILTER (WHERE is_active = TRUE) as active_count
FROM report_templates
UNION ALL
SELECT
    'silent_fail_logs',
    COUNT(*),
    COUNT(*) FILTER (WHERE is_resolved = FALSE)
FROM silent_fail_logs
UNION ALL
SELECT
    'brand_settings',
    COUNT(*),
    COUNT(*)
FROM template_brand_settings
UNION ALL
SELECT
    'usage_logs',
    COUNT(*),
    COUNT(*) FILTER (WHERE used_at >= NOW() - INTERVAL '24 hours')
FROM template_usage_log;

-- =====================================================
-- VERIFICATION
-- =====================================================

DO $$
BEGIN
    RAISE NOTICE '✅ Database setup complete!';
    RAISE NOTICE 'Tables created: silent_fail_logs, template_brand_settings, template_usage_log';
    RAISE NOTICE 'Views created: v_available_templates, v_silent_fail_statistics, mv_template_statistics';
    RAISE NOTICE 'Functions created: get_templates_for_project, clone_template, cleanup_silent_fail_logs';
    RAISE NOTICE 'Demo project created with ID: 9999';
    RAISE NOTICE 'Run: SELECT * FROM v_system_health; to verify';
END $$;

-- Print system health
SELECT * FROM v_system_health;
