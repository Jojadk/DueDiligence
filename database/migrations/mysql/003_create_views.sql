-- MySQL Database Views Migration
-- Performance-optimized views for common queries

-- Project Summary View
CREATE OR REPLACE VIEW v_project_summary AS
SELECT
    p.id as project_id,
    p.name as project_name,
    p.status,
    p.user_id,
    p.customer_id,
    p.created_at,
    COUNT(DISTINCT b.id) as building_count,
    COUNT(DISTINCT be.id) as element_count,
    COALESCE(SUM(be.capex), 0) as total_capex,
    SUM(CASE WHEN be.urgency = 'critical' THEN 1 ELSE 0 END) as critical_count,
    SUM(CASE WHEN be.urgency = 'high' THEN 1 ELSE 0 END) as high_count,
    SUM(CASE WHEN be.urgency = 'medium' THEN 1 ELSE 0 END) as medium_count,
    SUM(CASE WHEN be.urgency = 'low' THEN 1 ELSE 0 END) as low_count
FROM projects p
LEFT JOIN buildings b ON p.id = b.project_id
LEFT JOIN building_elements be ON b.id = be.building_id
GROUP BY p.id, p.name, p.status, p.user_id, p.customer_id, p.created_at;

-- Building Summary View
CREATE OR REPLACE VIEW v_building_summary AS
SELECT
    b.id as building_id,
    b.name as building_name,
    b.project_id,
    p.name as project_name,
    COUNT(be.id) as element_count,
    COALESCE(SUM(be.capex), 0) as total_capex,
    SUM(CASE WHEN be.urgency = 'critical' THEN 1 ELSE 0 END) as critical_elements,
    SUM(CASE WHEN be.urgency = 'high' THEN 1 ELSE 0 END) as high_elements,
    b.created_at
FROM buildings b
JOIN projects p ON b.project_id = p.id
LEFT JOIN building_elements be ON b.id = be.building_id
GROUP BY b.id, b.name, b.project_id, p.name, b.created_at;

-- Element Summary View
CREATE OR REPLACE VIEW v_element_summary AS
SELECT
    be.id as element_id,
    be.name as element_name,
    be.building_id,
    b.name as building_name,
    b.project_id,
    p.name as project_name,
    be.urgency,
    be.condition_score,
    be.capex,
    be.element_type,
    be.location,
    be.parent_id,
    COUNT(child.id) as child_count,
    COALESCE(SUM(child.capex), 0) as children_capex
FROM building_elements be
JOIN buildings b ON be.building_id = b.id
JOIN projects p ON b.project_id = p.id
LEFT JOIN building_elements child ON be.id = child.parent_id
GROUP BY be.id, be.name, be.building_id, b.name, b.project_id, p.name,
         be.urgency, be.condition_score, be.capex, be.element_type, be.location, be.parent_id;

-- Red Flags View (Pre-calculated indicators)
CREATE OR REPLACE VIEW v_red_flags AS
SELECT
    be.id as element_id,
    be.name as element_name,
    be.description,
    be.quantity,
    be.urgency,
    be.condition_score as `condition`,
    be.capex,
    b.id as building_id,
    b.name as building_name,
    p.id as project_id,
    p.name as project_name,
    p.user_id as project_owner,

    -- Pre-calculated indicators (MySQL uses CASE for conditional logic)
    CASE WHEN be.urgency = 'critical' THEN 1 ELSE 0 END as is_critical_urgency,
    CASE WHEN be.urgency = 'high' THEN 1 ELSE 0 END as is_high_urgency,
    CASE WHEN be.condition_score <= 2 THEN 1 ELSE 0 END as is_poor_condition,
    CASE WHEN be.capex > 500000 THEN 1 ELSE 0 END as is_high_cost,
    CASE WHEN be.description IS NULL OR be.description = '' THEN 1 ELSE 0 END as is_missing_description,
    CASE WHEN be.quantity IS NULL OR be.quantity = 0 THEN 1 ELSE 0 END as is_missing_quantity,

    -- Severity calculation
    CASE
        WHEN be.urgency = 'critical' AND be.condition_score <= 2 THEN 'critical'
        WHEN be.urgency = 'critical' OR be.condition_score <= 2 THEN 'high'
        WHEN be.urgency = 'high' OR be.capex > 500000 THEN 'high'
        WHEN be.urgency = 'medium' THEN 'normal'
        ELSE 'low'
    END as severity,

    -- Red flag score (higher = more urgent)
    (
        CASE WHEN be.urgency = 'critical' THEN 100 ELSE 0 END +
        CASE WHEN be.urgency = 'high' THEN 50 ELSE 0 END +
        CASE WHEN be.condition_score <= 2 THEN 40 ELSE 0 END +
        CASE WHEN be.capex > 500000 THEN 20 ELSE 0 END +
        CASE WHEN be.description IS NULL OR be.description = '' THEN 10 ELSE 0 END +
        CASE WHEN be.quantity IS NULL OR be.quantity = 0 THEN 5 ELSE 0 END
    ) as red_flag_score

FROM building_elements be
JOIN buildings b ON be.building_id = b.id
JOIN projects p ON b.project_id = p.id
WHERE
    be.urgency IN ('critical', 'high')
    OR be.condition_score <= 2
    OR be.capex > 500000
    OR be.description IS NULL
    OR be.description = ''
    OR be.quantity IS NULL
    OR be.quantity = 0;

-- Building OPEX Summary View
CREATE OR REPLACE VIEW v_building_opex_summary AS
SELECT
    b.id as building_id,
    b.name as building_name,
    b.total_area,
    COUNT(bo.id) as opex_category_count,
    SUM(
        COALESCE(bo.custom_rate_per_sqm, oc.rate_per_sqm) * b.total_area
    ) as effective_opex_yearly,
    GROUP_CONCAT(oc.name ORDER BY oc.name SEPARATOR ', ') as opex_categories
FROM buildings b
LEFT JOIN building_opex bo ON b.id = bo.building_id
LEFT JOIN opex_categories oc ON bo.opex_category_id = oc.id
WHERE oc.is_active = 1 OR oc.id IS NULL
GROUP BY b.id, b.name, b.total_area;

-- Budget Totals View
CREATE OR REPLACE VIEW v_budget_totals AS
SELECT
    bl.element_id,
    bl.budget_type,
    COUNT(bl.id) as line_count,
    SUM(bl.quantity * bl.price_per_unit) as total_budget,
    SUM(bl.year_0_1) as total_year_0_1,
    SUM(bl.year_1_2) as total_year_1_2,
    SUM(bl.year_3_5) as total_year_3_5,
    SUM(bl.year_5_10) as total_year_5_10,
    SUM(bl.year_10_plus) as total_year_10_plus
FROM budget_lines bl
GROUP BY bl.element_id, bl.budget_type;
