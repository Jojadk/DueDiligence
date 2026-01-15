<?php
/**
 * Stored Procedures Migration
 * Creates all stored procedures for business logic
 * Run via: https://tdd.bjerg.me/migrations/run_stored_procedures.php
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "<h1>⚙️ Stored Procedures Migration</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border-radius:8px;'>";
echo str_repeat("=", 70) . "\n";
echo "Creating stored procedures...\n\n";

$errors = [];
$success_count = 0;

// Define all procedures
$procedures = [];

// 1. Clone Project
$procedures['sp_clone_project'] = "
CREATE OR REPLACE FUNCTION sp_clone_project(
    p_source_project_id INT,
    p_new_project_name VARCHAR,
    p_user_id INT DEFAULT NULL
)
RETURNS INT
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_new_project_id INT;
    v_element RECORD;
    v_new_element_id INT;
    v_element_map JSONB := '{}';
    v_old_id INT;
    v_new_id INT;
BEGIN
    INSERT INTO projects (name, status, client_id, construction_year, renovation_year, created_at, updated_at)
    SELECT p_new_project_name, status, client_id, construction_year, renovation_year, NOW(), NOW()
    FROM projects WHERE id = p_source_project_id
    RETURNING id INTO v_new_project_id;

    IF v_new_project_id IS NULL THEN
        RAISE EXCEPTION 'Failed to clone project %', p_source_project_id;
    END IF;

    FOR v_element IN 
        SELECT * FROM building_elements 
        WHERE project_id = p_source_project_id AND parent_id IS NULL
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

        v_element_map := jsonb_set(v_element_map, ARRAY[v_element.id::text], to_jsonb(v_new_element_id));
    END LOOP;

    FOR v_element IN 
        SELECT * FROM building_elements 
        WHERE project_id = p_source_project_id AND parent_id IS NOT NULL
        ORDER BY sort_order
    LOOP
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

        v_element_map := jsonb_set(v_element_map, ARRAY[v_element.id::text], to_jsonb(v_new_element_id));
    END LOOP;

    RETURN v_new_project_id;
END;
\$\$
";

// 2. Calculate Project Totals
$procedures['sp_calculate_project_totals'] = "
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
AS \$\$
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
\$\$
";

// 3. Bulk Update Elements
$procedures['sp_bulk_update_elements'] = "
CREATE OR REPLACE FUNCTION sp_bulk_update_elements(
    p_element_ids INT[],
    p_field VARCHAR,
    p_value TEXT
)
RETURNS INT
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_updated_count INT;
BEGIN
    IF p_field NOT IN ('urgency', 'time_horizon', 'status', 'is_bcl', 'element_type') THEN
        RAISE EXCEPTION 'Invalid field name: %', p_field;
    END IF;

    EXECUTE format('UPDATE building_elements SET %I = \$1, updated_at = NOW() WHERE id = ANY(\$2)', p_field)
    USING p_value, p_element_ids;

    GET DIAGNOSTICS v_updated_count = ROW_COUNT;
    RETURN v_updated_count;
END;
\$\$
";

// 4. Clean Expired Locks
$procedures['sp_clean_expired_locks'] = "
CREATE OR REPLACE FUNCTION sp_clean_expired_locks()
RETURNS INT
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_deleted_count INT;
BEGIN
    DELETE FROM input_locks WHERE expires_at < NOW() - INTERVAL '1 hour';
    GET DIAGNOSTICS v_deleted_count = ROW_COUNT;
    RETURN v_deleted_count;
END;
\$\$
";

// 5. Get Element Path
$procedures['sp_get_element_path'] = "
CREATE OR REPLACE FUNCTION sp_get_element_path(p_element_id INT)
RETURNS TEXT
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_path TEXT;
BEGIN
    WITH RECURSIVE element_path AS (
        SELECT id, parent_id, title, 1 as level
        FROM building_elements WHERE id = p_element_id
        UNION ALL
        SELECT be.id, be.parent_id, be.title, ep.level + 1
        FROM building_elements be
        INNER JOIN element_path ep ON be.id = ep.parent_id
    )
    SELECT string_agg(title, ' > ' ORDER BY level DESC)
    INTO v_path FROM element_path;
    RETURN v_path;
END;
\$\$
";

// 6. Archive Old Projects
$procedures['sp_archive_old_projects'] = "
CREATE OR REPLACE FUNCTION sp_archive_old_projects(p_days_old INT DEFAULT 365)
RETURNS INT
LANGUAGE plpgsql
AS \$\$
DECLARE
    v_archived_count INT;
BEGIN
    UPDATE projects
    SET status = 'archived', updated_at = NOW()
    WHERE status NOT IN ('archived', 'deleted')
    AND updated_at < NOW() - (p_days_old || ' days')::INTERVAL
    AND id NOT IN (
        SELECT DISTINCT project_id 
        FROM building_elements 
        WHERE updated_at > NOW() - INTERVAL '30 days'
    );
    GET DIAGNOSTICS v_archived_count = ROW_COUNT;
    RETURN v_archived_count;
END;
\$\$
";

// 7. Recalculate Sort Order
$procedures['sp_recalculate_sort_order'] = "
CREATE OR REPLACE FUNCTION sp_recalculate_sort_order(p_project_id INT)
RETURNS VOID
LANGUAGE plpgsql
AS \$\$
BEGIN
    WITH numbered_elements AS (
        SELECT id, ROW_NUMBER() OVER (ORDER BY sort_order, id) - 1 as new_order
        FROM building_elements
        WHERE project_id = p_project_id AND parent_id IS NULL
    )
    UPDATE building_elements be
    SET sort_order = ne.new_order, updated_at = NOW()
    FROM numbered_elements ne
    WHERE be.id = ne.id;

    WITH numbered_children AS (
        SELECT id, parent_id,
            ROW_NUMBER() OVER (PARTITION BY parent_id ORDER BY sort_order, id) - 1 as new_order
        FROM building_elements
        WHERE project_id = p_project_id AND parent_id IS NOT NULL
    )
    UPDATE building_elements be
    SET sort_order = nc.new_order, updated_at = NOW()
    FROM numbered_children nc
    WHERE be.id = nc.id;
END;
\$\$
";

// Execute all procedures
foreach ($procedures as $name => $sql) {
    try {
        $db->query($sql);
        $db->execute();
        echo "✅ <Created stored procedure: $name\n";
        $success_count++;
    } catch (Exception $e) {
        $msg = "Failed to create procedure $name: " . $e->getMessage();
        $errors[] = $msg;
        echo "❌ $msg\n";
    }
}

// Summary
echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 MIGRATION SUMMARY\n";
echo str_repeat("=", 70) . "\n";
echo "✅ Successfully created: $success_count procedures\n";
echo "❌ Errors: " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\n⚠️  ERRORS:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
} else {
    echo "\n🎉 All stored procedures created successfully!\n";
}

echo "\n✅ Stored procedures migration complete!\n";
echo str_repeat("=", 70) . "\n";
echo "</pre>";
