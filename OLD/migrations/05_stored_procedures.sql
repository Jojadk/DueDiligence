-- ========================================
-- STORED PROCEDURES FOR COMPLEX LOGIC
-- Phase 4: Business Logic Optimization
-- Date: 2026-01-13
-- ========================================

-- ========================================
-- PROCEDURE: Clone Project with All Elements
-- ========================================

CREATE OR REPLACE FUNCTION sp_clone_project(
    p_source_project_id INT,
    p_new_project_name VARCHAR,
    p_user_id INT DEFAULT NULL
)
RETURNS INT
LANGUAGE plpgsql
AS $$
DECLARE
    v_new_project_id INT;
    v_element RECORD;
    v_new_element_id INT;
    v_element_map JSONB := '{}';
    v_old_id INT;
    v_new_id INT;
BEGIN
    -- 1. Clone project base record
    INSERT INTO projects (
        name, 
        status, 
        client_id, 
        construction_year, 
        renovation_year,
        created_at,
        updated_at
    )
    SELECT 
        p_new_project_name,
        status,
        client_id,
        construction_year,
        renovation_year,
        NOW(),
        NOW()
    FROM projects
    WHERE id = p_source_project_id
    RETURNING id INTO v_new_project_id;

    IF v_new_project_id IS NULL THEN
        RAISE EXCEPTION 'Failed to clone project %', p_source_project_id;
    END IF;

    -- 2. Clone building elements (first pass - root elements only)
    FOR v_element IN 
        SELECT * FROM building_elements 
        WHERE project_id = p_source_project_id 
        AND parent_id IS NULL
        ORDER BY sort_order
    LOOP
        INSERT INTO building_elements (
            project_id, parent_id, title, description, element_type,
            capex, replacement_value, urgency, time_horizon, is_bcl,
            sort_order, created_at, updated_at
        )
        VALUES (
            v_new_project_id, NULL, v_element.title, v_element.description,
            v_element.element_type, v_element.capex, v_element.replacement_value,
            v_element.urgency, v_element.time_horizon, v_element.is_bcl,
            v_element.sort_order, NOW(), NOW()
        )
        RETURNING id INTO v_new_element_id;

        -- Store mapping of old ID → new ID
        v_element_map := jsonb_set(
            v_element_map,
            ARRAY[v_element.id::text],
            to_jsonb(v_new_element_id)
        );
    END LOOP;

    -- 3. Clone child elements (recursive)
    FOR v_element IN 
        SELECT * FROM building_elements 
        WHERE project_id = p_source_project_id 
        AND parent_id IS NOT NULL
        ORDER BY sort_order
    LOOP
        -- Get new parent ID from mapping
        v_new_id := (v_element_map->>v_element.parent_id::text)::INT;
        
        INSERT INTO building_elements (
            project_id, parent_id, title, description, element_type,
            capex, replacement_value, urgency, time_horizon, is_bcl,
            sort_order, created_at, updated_at
        )
        VALUES (
            v_new_project_id, v_new_id, v_element.title, v_element.description,
            v_element.element_type, v_element.capex, v_element.replacement_value,
            v_element.urgency, v_element.time_horizon, v_element.is_bcl,
            v_element.sort_order, NOW(), NOW()
        )
        RETURNING id INTO v_new_element_id;

        -- Store mapping
        v_element_map := jsonb_set(
            v_element_map,
            ARRAY[v_element.id::text],
            to_jsonb(v_new_element_id)
        );
    END LOOP;

    -- 4. Clone budget items
    FOR v_old_id, v_new_id IN 
        SELECT key::INT, value::INT 
        FROM jsonb_each_text(v_element_map)
    LOOP
        INSERT INTO budget_items (
            element_id, description, quantity, unit_price, 
            unit, status, created_at, updated_at
        )
        SELECT 
            v_new_id, description, quantity, unit_price,
            unit, 'pending', NOW(), NOW()
        FROM budget_items
        WHERE element_id = v_old_id;
    END LOOP;

    -- Note: Media files are NOT cloned (files would need copying)
    -- Note: Custom field values could be cloned if needed

    -- 5. Create snapshot of source project
    INSERT INTO project_snapshots (
        project_id, title, snapshot_data, created_at
    )
    VALUES (
        p_source_project_id,
        'Cloned to: ' || p_new_project_name,
        jsonb_build_object(
            'cloned_to_id', v_new_project_id,
            'cloned_at', NOW(),
            'user_id', p_user_id
        ),
        NOW()
    );

    RETURN v_new_project_id;
END;
$$;

COMMENT ON FUNCTION sp_clone_project IS 'Clones a project with all elements and budget items';

-- ========================================
-- PROCEDURE: Calculate Project Totals
-- ========================================

CREATE OR REPLACE FUNCTION sp_calculate_project_totals(p_project_id INT)
RETURNS TABLE(
    total_elements INT,
    total_capex NUMERIC,
    total_replacement_value NUMERIC,
    total_budget NUMERIC,
    high_urgency_count INT,
    medium_urgency_count INT,
    low_urgency_count INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    RETURN QUERY
    SELECT 
        COUNT(DISTINCT be.id)::INT as total_elements,
        COALESCE(SUM(be.capex), 0) as total_capex,
        COALESCE(SUM(be.replacement_value), 0) as total_replacement_value,
        COALESCE(SUM(bi.quantity * bi.unit_price), 0) as total_budget,
        COUNT(DISTINCT CASE WHEN be.urgency = 'high' THEN be.id END)::INT as high_urgency_count,
        COUNT(DISTINCT CASE WHEN be.urgency = 'medium' THEN be.id END)::INT as medium_urgency_count,
        COUNT(DISTINCT CASE WHEN be.urgency = 'low' THEN be.id END)::INT as low_urgency_count
    FROM building_elements be
    LEFT JOIN budget_items bi ON be.id = bi.element_id
    WHERE be.project_id = p_project_id;
END;
$$;

COMMENT ON FUNCTION sp_calculate_project_totals IS 'Calculates aggregated totals for a project';

-- ========================================
-- PROCEDURE: Bulk Update Building Elements
-- ========================================

CREATE OR REPLACE FUNCTION sp_bulk_update_elements(
    p_element_ids INT[],
    p_field VARCHAR,
    p_value TEXT
)
RETURNS INT
LANGUAGE plpgsql
AS $$
DECLARE
    v_updated_count INT;
BEGIN
    -- Validate field name (prevent SQL injection)
    IF p_field NOT IN ('urgency', 'time_horizon', 'status', 'is_bcl', 'element_type') THEN
        RAISE EXCEPTION 'Invalid field name: %', p_field;
    END IF;

    -- Execute dynamic update
    EXECUTE format(
        'UPDATE building_elements SET %I = $1, updated_at = NOW() WHERE id = ANY($2)',
        p_field
    ) USING p_value, p_element_ids;

    GET DIAGNOSTICS v_updated_count = ROW_COUNT;
    
    RETURN v_updated_count;
END;
$$;

COMMENT ON FUNCTION sp_bulk_update_elements IS 'Bulk updates a single field for multiple elements';

-- ========================================
-- PROCEDURE: Clean Expired Locks
-- ========================================

CREATE OR REPLACE FUNCTION sp_clean_expired_locks()
RETURNS INT
LANGUAGE plpgsql
AS $$
DECLARE
    v_deleted_count INT;
BEGIN
    DELETE FROM input_locks
    WHERE expires_at < NOW() - INTERVAL '1 hour';

    GET DIAGNOSTICS v_deleted_count = ROW_COUNT;
    
    RETURN v_deleted_count;
END;
$$;

COMMENT ON FUNCTION sp_clean_expired_locks IS 'Removes locks expired more than 1 hour ago';

-- ========================================
-- PROCEDURE: Get Element Hierarchy Path
-- ========================================

CREATE OR REPLACE FUNCTION sp_get_element_path(p_element_id INT)
RETURNS TEXT
LANGUAGE plpgsql
AS $$
DECLARE
    v_path TEXT;
BEGIN
    WITH RECURSIVE element_path AS (
        SELECT id, parent_id, title, 1 as level
        FROM building_elements
        WHERE id = p_element_id
        
        UNION ALL
        
        SELECT be.id, be.parent_id, be.title, ep.level + 1
        FROM building_elements be
        INNER JOIN element_path ep ON be.id = ep.parent_id
    )
    SELECT string_agg(title, ' > ' ORDER BY level DESC)
    INTO v_path
    FROM element_path;
    
    RETURN v_path;
END;
$$;

COMMENT ON FUNCTION sp_get_element_path IS 'Returns full hierarchical path for an element';

-- ========================================
-- PROCEDURE: Archive Old Projects
-- ========================================

CREATE OR REPLACE FUNCTION sp_archive_old_projects(p_days_old INT DEFAULT 365)
RETURNS INT
LANGUAGE plpgsql
AS $$
DECLARE
    v_archived_count INT;
BEGIN
    UPDATE projects
    SET status = 'archived', updated_at = NOW()
    WHERE status NOT IN ('archived', 'deleted')
    AND updated_at < NOW() - (p_days_old || ' days')::INTERVAL
    AND id NOT IN (
        -- Don't archive if recently active
        SELECT DISTINCT project_id 
        FROM building_elements 
        WHERE updated_at > NOW() - INTERVAL '30 days'
    );

    GET DIAGNOSTICS v_archived_count = ROW_COUNT;
    
    RETURN v_archived_count;
END;
$$;

COMMENT ON FUNCTION sp_archive_old_projects IS 'Archives projects not updated in specified days';

-- ========================================
-- PROCEDURE: Recalculate Element Sort Order
-- ========================================

CREATE OR REPLACE FUNCTION sp_recalculate_sort_order(p_project_id INT)
RETURNS VOID
LANGUAGE plpgsql
AS $$
BEGIN
    -- Recalculate sort order for root elements
    WITH numbered_elements AS (
        SELECT id, ROW_NUMBER() OVER (ORDER BY sort_order, id) - 1 as new_order
        FROM building_elements
        WHERE project_id = p_project_id AND parent_id IS NULL
    )
    UPDATE building_elements be
    SET sort_order = ne.new_order, updated_at = NOW()
    FROM numbered_elements ne
    WHERE be.id = ne.id;

    -- Recalculate for each parent's children
    WITH numbered_children AS (
        SELECT 
            id, 
            parent_id,
            ROW_NUMBER() OVER (PARTITION BY parent_id ORDER BY sort_order, id) - 1 as new_order
        FROM building_elements
        WHERE project_id = p_project_id AND parent_id IS NOT NULL
    )
    UPDATE building_elements be
    SET sort_order = nc.new_order, updated_at = NOW()
    FROM numbered_children nc
    WHERE be.id = nc.id;
END;
$$;

COMMENT ON FUNCTION sp_recalculate_sort_order IS 'Recalculates and normalizes sort order for project elements';

-- ========================================
-- VERIFICATION
-- ========================================

-- List all created procedures
SELECT 
    routine_name,
    routine_type,
    data_type as return_type,
    pg_get_functiondef(p.oid) as definition_preview
FROM information_schema.routines r
JOIN pg_proc p ON p.proname = r.routine_name
WHERE routine_schema = 'public' 
AND routine_name LIKE 'sp_%'
ORDER BY routine_name;

-- Test examples (commented out - uncomment to test)
/*
-- Test clone project
SELECT sp_clone_project(1, 'Cloned Project Test', 1);

-- Test calculate totals
SELECT * FROM sp_calculate_project_totals(1);

-- Test bulk update
SELECT sp_bulk_update_elements(ARRAY[1,2,3], 'urgency', 'high');

-- Test clean locks
SELECT sp_clean_expired_locks();

-- Test element path
SELECT sp_get_element_path(1);

-- Test archive
SELECT sp_archive_old_projects(365);

-- Test sort recalculation
SELECT sp_recalculate_sort_order(1);
*/

-- ========================================
-- SUCCESS MESSAGE
-- ========================================

DO $$
BEGIN
    RAISE NOTICE '✅ Stored procedures created successfully!';
    RAISE NOTICE '';
    RAISE NOTICE 'Available procedures:';
    RAISE NOTICE '  - sp_clone_project(source_id, new_name, user_id)';
    RAISE NOTICE '  - sp_calculate_project_totals(project_id)';
    RAISE NOTICE '  - sp_bulk_update_elements(ids[], field, value)';
    RAISE NOTICE '  - sp_clean_expired_locks()';
    RAISE NOTICE '  - sp_get_element_path(element_id)';
    RAISE NOTICE '  - sp_archive_old_projects(days_old)';
    RAISE NOTICE '  - sp_recalculate_sort_order(project_id)';
    RAISE NOTICE '';
    RAISE NOTICE 'Test by uncommenting examples at bottom of file.';
END $$;
