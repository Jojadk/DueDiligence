-- Add Recursive CTE Optimizations for Element Hierarchies
-- This migration adds database functions using recursive CTEs to optimize element hierarchy queries
-- Performance improvement: 10+ queries → 1 query for full hierarchy with stats

-- Function to get element hierarchy with stats using recursive CTE
CREATE OR REPLACE FUNCTION get_element_hierarchy(p_building_id INTEGER)
RETURNS TABLE (
    element_id INTEGER,
    element_name VARCHAR(255),
    element_code VARCHAR(100),
    parent_id INTEGER,
    level_code VARCHAR(50),
    capex DECIMAL(12,2),
    urgency INTEGER,
    condition_score INTEGER,
    red_flag_score INTEGER,
    severity VARCHAR(20),
    display_order INTEGER,
    depth INTEGER,
    path TEXT,
    has_children BOOLEAN
) AS $$
BEGIN
    RETURN QUERY
    WITH RECURSIVE element_tree AS (
        -- Base case: root elements
        SELECT
            es.element_id,
            es.element_name,
            es.element_code,
            es.parent_id,
            es.level_code,
            es.capex,
            es.urgency,
            es.condition_score,
            es.red_flag_score,
            es.severity,
            es.display_order,
            0 as depth,
            es.element_name::TEXT as path,
            EXISTS(
                SELECT 1 FROM building_elements be2
                WHERE be2.parent_id = es.element_id
            ) as has_children
        FROM v_element_summary es
        WHERE es.building_id = p_building_id
            AND es.parent_id IS NULL

        UNION ALL

        -- Recursive case: child elements
        SELECT
            es.element_id,
            es.element_name,
            es.element_code,
            es.parent_id,
            es.level_code,
            es.capex,
            es.urgency,
            es.condition_score,
            es.red_flag_score,
            es.severity,
            es.display_order,
            et.depth + 1,
            et.path || ' > ' || es.element_name,
            EXISTS(
                SELECT 1 FROM building_elements be2
                WHERE be2.parent_id = es.element_id
            ) as has_children
        FROM v_element_summary es
        INNER JOIN element_tree et ON es.parent_id = et.element_id
        WHERE es.building_id = p_building_id
    )
    SELECT * FROM element_tree
    ORDER BY path, display_order;
END;
$$ LANGUAGE plpgsql STABLE;

-- Function to get element ancestry path (for breadcrumbs)
CREATE OR REPLACE FUNCTION get_element_path(p_element_id INTEGER)
RETURNS TABLE (
    element_id INTEGER,
    element_name VARCHAR(255),
    level_code VARCHAR(50),
    depth INTEGER
) AS $$
BEGIN
    RETURN QUERY
    WITH RECURSIVE ancestry AS (
        -- Start with the given element
        SELECT
            be.id,
            be.name,
            be.level_code,
            be.parent_id,
            0 as depth
        FROM building_elements be
        WHERE be.id = p_element_id

        UNION ALL

        -- Get parent elements
        SELECT
            be.id,
            be.name,
            be.level_code,
            be.parent_id,
            a.depth + 1
        FROM building_elements be
        INNER JOIN ancestry a ON be.id = a.parent_id
    )
    SELECT
        a.id,
        a.name,
        a.level_code,
        a.depth
    FROM ancestry a
    ORDER BY a.depth DESC;
END;
$$ LANGUAGE plpgsql STABLE;

-- Function to get all descendants of an element
CREATE OR REPLACE FUNCTION get_element_descendants(p_element_id INTEGER)
RETURNS TABLE (
    element_id INTEGER,
    element_name VARCHAR(255),
    element_code VARCHAR(100),
    depth INTEGER,
    total_capex DECIMAL(12,2),
    critical_count INTEGER,
    high_count INTEGER
) AS $$
BEGIN
    RETURN QUERY
    WITH RECURSIVE descendants AS (
        -- Start with the given element
        SELECT
            be.id,
            be.name,
            be.element_code,
            be.parent_id,
            0 as depth
        FROM building_elements be
        WHERE be.id = p_element_id

        UNION ALL

        -- Get child elements
        SELECT
            be.id,
            be.name,
            be.element_code,
            be.parent_id,
            d.depth + 1
        FROM building_elements be
        INNER JOIN descendants d ON be.parent_id = d.id
    )
    SELECT
        d.id,
        d.name,
        d.element_code,
        d.depth,
        COALESCE(SUM(es.capex), 0) as total_capex,
        COUNT(*) FILTER (WHERE es.is_critical_urgency = 1) as critical_count,
        COUNT(*) FILTER (WHERE es.urgency <= 2) as high_count
    FROM descendants d
    LEFT JOIN v_element_summary es ON es.element_id = d.id
    WHERE d.id != p_element_id  -- Exclude the parent itself
    GROUP BY d.id, d.name, d.element_code, d.depth
    ORDER BY d.depth, d.element_code;
END;
$$ LANGUAGE plpgsql STABLE;

-- Function to calculate aggregate stats for element and all descendants
CREATE OR REPLACE FUNCTION get_element_aggregate_stats(p_element_id INTEGER)
RETURNS TABLE (
    total_elements INTEGER,
    total_capex DECIMAL(12,2),
    avg_condition DECIMAL(5,2),
    critical_count INTEGER,
    high_urgency_count INTEGER,
    poor_condition_count INTEGER,
    total_red_flag_score INTEGER
) AS $$
BEGIN
    RETURN QUERY
    WITH RECURSIVE descendants AS (
        SELECT id, parent_id
        FROM building_elements
        WHERE id = p_element_id

        UNION ALL

        SELECT be.id, be.parent_id
        FROM building_elements be
        INNER JOIN descendants d ON be.parent_id = d.id
    )
    SELECT
        COUNT(*)::INTEGER as total_elements,
        COALESCE(SUM(es.capex), 0) as total_capex,
        COALESCE(AVG(es.condition_score), 0)::DECIMAL(5,2) as avg_condition,
        COUNT(*) FILTER (WHERE es.is_critical_urgency = 1)::INTEGER as critical_count,
        COUNT(*) FILTER (WHERE es.urgency <= 2)::INTEGER as high_urgency_count,
        COUNT(*) FILTER (WHERE es.is_poor_condition = 1)::INTEGER as poor_condition_count,
        COALESCE(SUM(es.red_flag_score), 0)::INTEGER as total_red_flag_score
    FROM descendants d
    LEFT JOIN v_element_summary es ON es.element_id = d.id;
END;
$$ LANGUAGE plpgsql STABLE;

-- Function to check if element can be deleted (no children, no budget lines)
CREATE OR REPLACE FUNCTION can_delete_element(p_element_id INTEGER)
RETURNS TABLE (
    can_delete BOOLEAN,
    reason TEXT
) AS $$
DECLARE
    v_child_count INTEGER;
    v_budget_count INTEGER;
BEGIN
    -- Check for children
    SELECT COUNT(*) INTO v_child_count
    FROM building_elements
    WHERE parent_id = p_element_id;

    IF v_child_count > 0 THEN
        RETURN QUERY SELECT FALSE, 'Element har ' || v_child_count || ' underordnede elementer';
        RETURN;
    END IF;

    -- Check for budget lines
    SELECT COUNT(*) INTO v_budget_count
    FROM budget_lines
    WHERE element_id = p_element_id;

    IF v_budget_count > 0 THEN
        RETURN QUERY SELECT FALSE, 'Element har ' || v_budget_count || ' budget linjer';
        RETURN;
    END IF;

    -- Can delete
    RETURN QUERY SELECT TRUE, 'Element kan slettes'::TEXT;
END;
$$ LANGUAGE plpgsql STABLE;

-- Function to move element and all descendants to new parent
CREATE OR REPLACE FUNCTION move_element_subtree(
    p_element_id INTEGER,
    p_new_parent_id INTEGER,
    p_new_building_id INTEGER
) RETURNS BOOLEAN AS $$
DECLARE
    v_current_building_id INTEGER;
    v_is_circular BOOLEAN;
BEGIN
    -- Get current building
    SELECT building_id INTO v_current_building_id
    FROM building_elements
    WHERE id = p_element_id;

    -- Check for circular reference
    IF p_new_parent_id IS NOT NULL THEN
        WITH RECURSIVE ancestry AS (
            SELECT id, parent_id
            FROM building_elements
            WHERE id = p_new_parent_id

            UNION ALL

            SELECT be.id, be.parent_id
            FROM building_elements be
            INNER JOIN ancestry a ON be.id = a.parent_id
        )
        SELECT EXISTS(SELECT 1 FROM ancestry WHERE id = p_element_id)
        INTO v_is_circular;

        IF v_is_circular THEN
            RAISE EXCEPTION 'Cirkulær reference: Element kan ikke flyttes til et af sine underordnede';
        END IF;
    END IF;

    -- Move the element
    UPDATE building_elements
    SET
        parent_id = p_new_parent_id,
        building_id = p_new_building_id,
        updated_at = NOW()
    WHERE id = p_element_id;

    -- If moving to different building, move all descendants too
    IF v_current_building_id != p_new_building_id THEN
        WITH RECURSIVE descendants AS (
            SELECT id
            FROM building_elements
            WHERE parent_id = p_element_id

            UNION ALL

            SELECT be.id
            FROM building_elements be
            INNER JOIN descendants d ON be.parent_id = d.id
        )
        UPDATE building_elements
        SET
            building_id = p_new_building_id,
            updated_at = NOW()
        WHERE id IN (SELECT id FROM descendants);
    END IF;

    RETURN TRUE;
END;
$$ LANGUAGE plpgsql;

-- Index optimizations for recursive queries
CREATE INDEX IF NOT EXISTS idx_building_elements_parent_id_building_id
    ON building_elements(parent_id, building_id)
    WHERE parent_id IS NOT NULL;

CREATE INDEX IF NOT EXISTS idx_building_elements_building_id_parent_null
    ON building_elements(building_id)
    WHERE parent_id IS NULL;

-- Comments
COMMENT ON FUNCTION get_element_hierarchy IS 'Returns full element hierarchy for a building with stats using recursive CTE - optimizes 10+ queries to 1';
COMMENT ON FUNCTION get_element_path IS 'Returns ancestry path for an element (for breadcrumbs)';
COMMENT ON FUNCTION get_element_descendants IS 'Returns all descendants of an element with aggregate stats';
COMMENT ON FUNCTION get_element_aggregate_stats IS 'Calculates aggregate statistics for element and all descendants';
COMMENT ON FUNCTION can_delete_element IS 'Checks if element can be deleted (validation function)';
COMMENT ON FUNCTION move_element_subtree IS 'Moves element and all descendants to new parent/building with circular reference check';
