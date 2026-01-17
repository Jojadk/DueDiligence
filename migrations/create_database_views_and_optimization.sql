-- Database Optimization: Views, Indexes, and Module Separation
-- Date: 2026-01-17
-- Purpose: Create materialized views for performance and data aggregation

-- ========================================
-- VIEWS FOR PROJECT OVERVIEW
-- ========================================

-- Project summary view with all key metrics
CREATE OR REPLACE VIEW v_project_summary AS
SELECT
    p.id as project_id,
    p.name as project_name,
    p.user_id,
    p.status,
    p.created_at as project_created,

    -- Building counts
    COUNT(DISTINCT b.id) as building_count,
    COALESCE(SUM(b.area), 0) as total_area,

    -- Element counts
    COUNT(DISTINCT be.id) as element_count,

    -- CAPEX aggregation
    COALESCE(SUM(be.capex), 0) as total_capex,
    COALESCE(AVG(be.capex), 0) as avg_capex,
    COALESCE(MAX(be.capex), 0) as max_capex,

    -- Urgency counts
    COUNT(DISTINCT CASE WHEN be.urgency = 'critical' THEN be.id END) as critical_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'high' THEN be.id END) as high_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'normal' THEN be.id END) as normal_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'low' THEN be.id END) as low_count,

    -- CAPEX by urgency
    COALESCE(SUM(CASE WHEN be.urgency = 'critical' THEN be.capex ELSE 0 END), 0) as critical_capex,
    COALESCE(SUM(CASE WHEN be.urgency = 'high' THEN be.capex ELSE 0 END), 0) as high_capex,

    -- Image counts
    COUNT(DISTINCT img.id) as image_count,

    -- Last updated
    MAX(GREATEST(
        COALESCE(p.updated_at, p.created_at),
        COALESCE(b.updated_at, b.created_at),
        COALESCE(be.updated_at, be.created_at)
    )) as last_updated

FROM projects p
LEFT JOIN buildings b ON p.id = b.project_id
LEFT JOIN building_elements be ON b.id = be.building_id
LEFT JOIN images img ON (img.entity_type = 'project' AND img.entity_id = p.id)
    OR (img.entity_type = 'building' AND img.entity_id = b.id)
    OR (img.entity_type = 'building_element' AND img.entity_id = be.id)
GROUP BY p.id, p.name, p.user_id, p.status, p.created_at;

-- Building summary view with CAPEX, OPEX, and TCO
CREATE OR REPLACE VIEW v_building_summary AS
SELECT
    b.id as building_id,
    b.name as building_name,
    b.project_id,
    b.area as building_area,

    -- Element counts
    COUNT(DISTINCT be.id) as element_count,

    -- CAPEX aggregation
    COALESCE(SUM(be.capex), 0) as total_capex,

    -- OPEX aggregation (from building_opex)
    COALESCE(SUM(bo.custom_rate_per_sqm * b.area), 0) as custom_opex_yearly,
    COALESCE(SUM(oc.rate_per_sqm * b.area), 0) as standard_opex_yearly,
    COALESCE(SUM(
        COALESCE(bo.custom_rate_per_sqm, oc.rate_per_sqm) * b.area
    ), 0) as effective_opex_yearly,

    -- OPEX category count
    COUNT(DISTINCT bo.id) as opex_category_count,

    -- Urgency breakdown
    COUNT(DISTINCT CASE WHEN be.urgency = 'critical' THEN be.id END) as critical_elements,
    COUNT(DISTINCT CASE WHEN be.urgency = 'high' THEN be.id END) as high_elements,

    -- Image count
    COUNT(DISTINCT img.id) as image_count,

    -- Budget lines count
    COUNT(DISTINCT bl.id) as budget_lines_count

FROM buildings b
LEFT JOIN building_elements be ON b.id = be.building_id
LEFT JOIN building_opex bo ON b.id = bo.building_id
LEFT JOIN opex_categories oc ON bo.opex_category_id = oc.id
LEFT JOIN images img ON (img.entity_type = 'building' AND img.entity_id = b.id)
    OR (img.entity_type = 'building_element' AND img.entity_id = be.id)
LEFT JOIN budget_lines bl ON be.id = bl.element_id
GROUP BY b.id, b.name, b.project_id, b.area;

-- Element summary view with all related data
CREATE OR REPLACE VIEW v_element_summary AS
SELECT
    be.id as element_id,
    be.name as element_name,
    be.building_id,
    be.category,
    be.urgency,
    be.condition,
    be.capex,
    be.quantity,
    be.unit,
    be.parent_id,
    be.sort_order,

    -- Building info
    b.name as building_name,
    b.project_id,
    b.area as building_area,

    -- Project info
    p.name as project_name,
    p.user_id as project_owner,

    -- Budget aggregation
    COUNT(DISTINCT bl_capex.id) as capex_lines_count,
    COUNT(DISTINCT bl_opex.id) as opex_lines_count,
    COUNT(DISTINCT bl_reinst.id) as reinstatement_lines_count,

    COALESCE(SUM(bl_capex.quantity * bl_capex.price_per_unit), 0) as budget_capex_total,
    COALESCE(SUM(bl_opex.quantity * bl_opex.price_per_unit), 0) as budget_opex_total,
    COALESCE(SUM(bl_reinst.quantity * bl_reinst.price_per_unit), 0) as budget_reinstatement_total,

    -- Time phase totals (CAPEX)
    COALESCE(SUM(bl_capex.year_0_1), 0) as capex_year_0_1,
    COALESCE(SUM(bl_capex.year_1_2), 0) as capex_year_1_2,
    COALESCE(SUM(bl_capex.year_3_5), 0) as capex_year_3_5,
    COALESCE(SUM(bl_capex.year_5_10), 0) as capex_year_5_10,
    COALESCE(SUM(bl_capex.year_10_plus), 0) as capex_year_10_plus,

    -- Image count
    COUNT(DISTINCT img.id) as image_count,

    -- Child element count
    COUNT(DISTINCT children.id) as child_element_count

FROM building_elements be
JOIN buildings b ON be.building_id = b.id
JOIN projects p ON b.project_id = p.id
LEFT JOIN budget_lines bl_capex ON be.id = bl_capex.element_id AND bl_capex.budget_type = 'capex'
LEFT JOIN budget_lines bl_opex ON be.id = bl_opex.element_id AND bl_opex.budget_type = 'opex'
LEFT JOIN budget_lines bl_reinst ON be.id = bl_reinst.element_id AND bl_reinst.budget_type = 'reinstatement'
LEFT JOIN images img ON img.entity_type = 'building_element' AND img.entity_id = be.id
LEFT JOIN building_elements children ON children.parent_id = be.id
GROUP BY
    be.id, be.name, be.building_id, be.category, be.urgency, be.condition,
    be.capex, be.quantity, be.unit, be.parent_id, be.sort_order,
    b.name, b.project_id, b.area,
    p.name, p.user_id;

-- Red flags view for quick access
CREATE OR REPLACE VIEW v_red_flags AS
SELECT
    be.id as element_id,
    be.name as element_name,
    be.building_id,
    b.name as building_name,
    b.project_id,
    p.name as project_name,
    p.user_id as project_owner,

    be.urgency,
    be.condition,
    be.capex,
    be.description,
    be.quantity,

    -- Red flag indicators
    CASE
        WHEN be.urgency = 'critical' THEN 1
        ELSE 0
    END as is_critical_urgency,

    CASE
        WHEN be.urgency = 'high' THEN 1
        ELSE 0
    END as is_high_urgency,

    CASE
        WHEN LOWER(be.condition) IN ('dårlig', 'kritisk', 'poor', 'critical') THEN 1
        ELSE 0
    END as is_poor_condition,

    CASE
        WHEN be.capex > 500000 THEN 1
        ELSE 0
    END as is_high_cost,

    CASE
        WHEN be.description IS NULL OR LENGTH(TRIM(be.description)) < 10 THEN 1
        ELSE 0
    END as is_missing_description,

    CASE
        WHEN be.quantity IS NULL OR be.quantity <= 0 THEN 1
        ELSE 0
    END as is_missing_quantity,

    -- Calculate score
    (
        CASE WHEN be.urgency = 'critical' THEN 10 ELSE 0 END +
        CASE WHEN be.urgency = 'high' THEN 7 ELSE 0 END +
        CASE WHEN LOWER(be.condition) IN ('dårlig', 'kritisk', 'poor', 'critical') THEN 8 ELSE 0 END +
        CASE WHEN be.capex > 500000 THEN 5 ELSE 0 END +
        CASE WHEN be.description IS NULL OR LENGTH(TRIM(be.description)) < 10 THEN 2 ELSE 0 END +
        CASE WHEN be.quantity IS NULL OR be.quantity <= 0 THEN 3 ELSE 0 END
    ) as red_flag_score,

    -- Severity based on score
    CASE
        WHEN (
            CASE WHEN be.urgency = 'critical' THEN 10 ELSE 0 END +
            CASE WHEN be.urgency = 'high' THEN 7 ELSE 0 END +
            CASE WHEN LOWER(be.condition) IN ('dårlig', 'kritisk', 'poor', 'critical') THEN 8 ELSE 0 END +
            CASE WHEN be.capex > 500000 THEN 5 ELSE 0 END +
            CASE WHEN be.description IS NULL OR LENGTH(TRIM(be.description)) < 10 THEN 2 ELSE 0 END +
            CASE WHEN be.quantity IS NULL OR be.quantity <= 0 THEN 3 ELSE 0 END
        ) >= 10 THEN 'critical'
        WHEN (
            CASE WHEN be.urgency = 'critical' THEN 10 ELSE 0 END +
            CASE WHEN be.urgency = 'high' THEN 7 ELSE 0 END +
            CASE WHEN LOWER(be.condition) IN ('dårlig', 'kritisk', 'poor', 'critical') THEN 8 ELSE 0 END +
            CASE WHEN be.capex > 500000 THEN 5 ELSE 0 END +
            CASE WHEN be.description IS NULL OR LENGTH(TRIM(be.description)) < 10 THEN 2 ELSE 0 END +
            CASE WHEN be.quantity IS NULL OR be.quantity <= 0 THEN 3 ELSE 0 END
        ) >= 7 THEN 'high'
        WHEN (
            CASE WHEN be.urgency = 'critical' THEN 10 ELSE 0 END +
            CASE WHEN be.urgency = 'high' THEN 7 ELSE 0 END +
            CASE WHEN LOWER(be.condition) IN ('dårlig', 'kritisk', 'poor', 'critical') THEN 8 ELSE 0 END +
            CASE WHEN be.capex > 500000 THEN 5 ELSE 0 END +
            CASE WHEN be.description IS NULL OR LENGTH(TRIM(be.description)) < 10 THEN 2 ELSE 0 END +
            CASE WHEN be.quantity IS NULL OR be.quantity <= 0 THEN 3 ELSE 0 END
        ) >= 3 THEN 'normal'
        ELSE 'low'
    END as severity

FROM building_elements be
JOIN buildings b ON be.building_id = b.id
JOIN projects p ON b.project_id = p.id
WHERE (
    -- Only include elements with at least one red flag
    be.urgency IN ('critical', 'high')
    OR LOWER(be.condition) IN ('dårlig', 'kritisk', 'poor', 'critical')
    OR be.capex > 500000
    OR be.description IS NULL OR LENGTH(TRIM(be.description)) < 10
    OR be.quantity IS NULL OR be.quantity <= 0
);

-- Budget totals view
CREATE OR REPLACE VIEW v_budget_totals AS
SELECT
    bl.element_id,
    bl.budget_type,

    COUNT(*) as line_count,

    -- Total by quantity * price
    COALESCE(SUM(bl.quantity * bl.price_per_unit), 0) as total_budget,

    -- Phase totals
    COALESCE(SUM(bl.year_0_1), 0) as total_year_0_1,
    COALESCE(SUM(bl.year_1_2), 0) as total_year_1_2,
    COALESCE(SUM(bl.year_3_5), 0) as total_year_3_5,
    COALESCE(SUM(bl.year_5_10), 0) as total_year_5_10,
    COALESCE(SUM(bl.year_10_plus), 0) as total_year_10_plus,

    -- Unit breakdown
    COALESCE(SUM(CASE WHEN bl.unit = 'm2' THEN bl.quantity ELSE 0 END), 0) as total_m2,
    COALESCE(SUM(CASE WHEN bl.unit = 'm' THEN bl.quantity ELSE 0 END), 0) as total_m,
    COALESCE(SUM(CASE WHEN bl.unit = 'stk' THEN bl.quantity ELSE 0 END), 0) as total_stk

FROM budget_lines bl
GROUP BY bl.element_id, bl.budget_type;

-- OPEX summary per building view
CREATE OR REPLACE VIEW v_building_opex_summary AS
SELECT
    b.id as building_id,
    b.name as building_name,
    b.project_id,
    b.area as building_area,

    COUNT(bo.id) as opex_categories_count,

    -- Annual OPEX
    COALESCE(SUM(
        COALESCE(bo.custom_rate_per_sqm, oc.rate_per_sqm) * b.area
    ), 0) as annual_opex,

    -- OPEX per m2
    CASE
        WHEN b.area > 0 THEN COALESCE(SUM(
            COALESCE(bo.custom_rate_per_sqm, oc.rate_per_sqm)
        ), 0)
        ELSE 0
    END as opex_per_sqm,

    -- Category breakdown
    STRING_AGG(oc.name, ', ' ORDER BY oc.name) as opex_categories,
    STRING_AGG(oc.category_type, ', ' ORDER BY oc.category_type) as opex_types

FROM buildings b
LEFT JOIN building_opex bo ON b.id = bo.building_id
LEFT JOIN opex_categories oc ON bo.opex_category_id = oc.id AND oc.is_active = true
GROUP BY b.id, b.name, b.project_id, b.area;

-- User activity summary view
CREATE OR REPLACE VIEW v_user_activity AS
SELECT
    u.id as user_id,
    u.username,
    u.role,

    -- Project counts
    COUNT(DISTINCT p.id) as project_count,
    COUNT(DISTINCT CASE WHEN p.status = 'active' THEN p.id END) as active_projects,

    -- Building and element counts
    COUNT(DISTINCT b.id) as building_count,
    COUNT(DISTINCT be.id) as element_count,

    -- Total CAPEX managed
    COALESCE(SUM(be.capex), 0) as total_capex_managed,

    -- Last activity
    MAX(GREATEST(
        COALESCE(p.updated_at, p.created_at),
        COALESCE(b.updated_at, b.created_at),
        COALESCE(be.updated_at, be.created_at)
    )) as last_activity

FROM users u
LEFT JOIN projects p ON u.id = p.user_id
LEFT JOIN buildings b ON p.id = b.project_id
LEFT JOIN building_elements be ON b.id = be.building_id
GROUP BY u.id, u.username, u.role;

-- ========================================
-- PERFORMANCE INDEXES
-- ========================================

-- Project indexes
CREATE INDEX IF NOT EXISTS idx_projects_user_status ON projects(user_id, status);
CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status);
CREATE INDEX IF NOT EXISTS idx_projects_created ON projects(created_at DESC);

-- Building indexes
CREATE INDEX IF NOT EXISTS idx_buildings_project ON buildings(project_id);
CREATE INDEX IF NOT EXISTS idx_buildings_area ON buildings(area) WHERE area IS NOT NULL;

-- Building elements indexes
CREATE INDEX IF NOT EXISTS idx_elements_building ON building_elements(building_id);
CREATE INDEX IF NOT EXISTS idx_elements_category ON building_elements(category);
CREATE INDEX IF NOT EXISTS idx_elements_urgency ON building_elements(urgency);
CREATE INDEX IF NOT EXISTS idx_elements_parent ON building_elements(parent_id);
CREATE INDEX IF NOT EXISTS idx_elements_building_sort ON building_elements(building_id, sort_order);
CREATE INDEX IF NOT EXISTS idx_elements_capex ON building_elements(capex DESC) WHERE capex > 0;
CREATE INDEX IF NOT EXISTS idx_elements_condition ON building_elements(condition) WHERE condition IS NOT NULL;

-- Budget lines indexes
CREATE INDEX IF NOT EXISTS idx_budget_element_type ON budget_lines(element_id, budget_type);
CREATE INDEX IF NOT EXISTS idx_budget_catalog ON budget_lines(price_catalog_id) WHERE price_catalog_id IS NOT NULL;

-- Images indexes
CREATE INDEX IF NOT EXISTS idx_images_entity ON images(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_images_sort ON images(entity_type, entity_id, sort_order);

-- Activity log indexes (if exists)
-- CREATE INDEX IF NOT EXISTS idx_activity_user_time ON activity_log(user_id, created_at DESC);
-- CREATE INDEX IF NOT EXISTS idx_activity_entity ON activity_log(entity_type, entity_id);

-- ========================================
-- COMPOSITE INDEXES FOR COMMON QUERIES
-- ========================================

-- For project tree queries
CREATE INDEX IF NOT EXISTS idx_elements_building_parent_sort
ON building_elements(building_id, parent_id, sort_order);

-- For red flags queries
CREATE INDEX IF NOT EXISTS idx_elements_urgency_capex
ON building_elements(urgency, capex DESC)
WHERE urgency IN ('critical', 'high');

-- For budget queries
CREATE INDEX IF NOT EXISTS idx_budget_element_type_line
ON budget_lines(element_id, budget_type, line_number);

-- For OPEX queries
CREATE INDEX IF NOT EXISTS idx_building_opex_building_category
ON building_opex(building_id, opex_category_id);

-- ========================================
-- STATISTICS UPDATE
-- ========================================

-- Update statistics for better query planning
ANALYZE projects;
ANALYZE buildings;
ANALYZE building_elements;
ANALYZE budget_lines;
ANALYZE building_opex;
ANALYZE opex_categories;
ANALYZE images;
ANALYZE price_catalog;
ANALYZE budget_templates;
