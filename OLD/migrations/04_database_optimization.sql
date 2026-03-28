-- ========================================
-- DATABASE OPTIMIZATION SCRIPT
-- Phase 4: Views, Procedures, and Constraints
-- Date: 2026-01-13
-- ========================================

-- ========================================
-- PART 1: DATABASE VIEWS
-- Complex read queries optimized as views
-- ========================================

-- View: Project Overview with Statistics
CREATE OR REPLACE VIEW v_project_overview AS
SELECT 
    p.id,
    p.name,
    p.status,
    p.client_id,
    p.construction_year,
    p.renovation_year,
    p.created_at,
    p.updated_at,
    COUNT(DISTINCT be.id) as element_count,
    COUNT(DISTINCT em.id) as media_count,
    COALESCE(SUM(be.capex), 0) as total_capex,
    COALESCE(SUM(be.replacement_value), 0) as total_replacement_value,
    COUNT(DISTINCT CASE WHEN be.urgency = 'high' THEN be.id END) as high_urgency_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'medium' THEN be.id END) as medium_urgency_count,
    COUNT(DISTINCT CASE WHEN be.urgency = 'low' THEN be.id END) as low_urgency_count,
    MAX(be.updated_at) as last_element_update
FROM projects p
LEFT JOIN building_elements be ON p.id = be.project_id
LEFT JOIN element_media em ON be.id = em.element_id
GROUP BY p.id, p.name, p.status, p.client_id, p.construction_year, p.renovation_year, p.created_at, p.updated_at;

COMMENT ON VIEW v_project_overview IS 'Project overview with aggregated statistics for dashboard';

-- View: Building Elements with Hierarchy
CREATE OR REPLACE VIEW v_building_elements_hierarchy AS
WITH RECURSIVE element_tree AS (
    -- Base case: root elements (no parent)
    SELECT 
        id,
        parent_id,
        project_id,
        title,
        element_type,
        sort_order,
        capex,
        urgency,
        0 as level,
        ARRAY[id] as path,
        title as full_path
    FROM building_elements
    WHERE parent_id IS NULL
    
    UNION ALL
    
    -- Recursive case: children
    SELECT 
        be.id,
        be.parent_id,
        be.project_id,
        be.title,
        be.element_type,
        be.sort_order,
        be.capex,
        be.urgency,
        et.level + 1,
        et.path || be.id,
        et.full_path || ' > ' || be.title
    FROM building_elements be
    INNER JOIN element_tree et ON be.parent_id = et.id
)
SELECT * FROM element_tree
ORDER BY path;

COMMENT ON VIEW v_building_elements_hierarchy IS 'Building elements with parent-child hierarchy and full path';

-- View: Budget Summary by Project
CREATE OR REPLACE VIEW v_budget_summary AS
SELECT 
    p.id as project_id,
    p.name as project_name,
    be.id as element_id,
    be.title as element_title,
    COUNT(bi.id) as budget_item_count,
    COALESCE(SUM(bi.quantity * bi.unit_price), 0) as total_budget,
    COALESCE(SUM(CASE WHEN bi.status = 'approved' THEN bi.quantity * bi.unit_price ELSE 0 END), 0) as approved_budget,
    COALESCE(SUM(CASE WHEN bi.status = 'pending' THEN bi.quantity * bi.unit_price ELSE 0 END), 0) as pending_budget
FROM projects p
LEFT JOIN building_elements be ON p.id = be.project_id
LEFT JOIN budget_items bi ON be.id = bi.element_id
GROUP BY p.id, p.name, be.id, be.title;

COMMENT ON VIEW v_budget_summary IS 'Budget summary aggregated by project and element';

-- View: Media Overview
CREATE OR REPLACE VIEW v_media_overview AS
SELECT 
    em.id,
    em.element_id,
    em.filename,
    em.file_path,
    em.file_type,
    em.file_size,
    em.caption,
    em.sort_order,
    em.created_at,
    be.title as element_title,
    be.project_id,
    p.name as project_name
FROM element_media em
INNER JOIN building_elements be ON em.element_id = be.id
INNER JOIN projects p ON be.project_id = p.id;

COMMENT ON VIEW v_media_overview IS 'Media files with element and project context';

-- View: Active Locks
CREATE OR REPLACE VIEW v_active_locks AS
SELECT 
    il.id,
    il.element_id,
    il.user_id,
    il.client_id,
    il.locked_at,
    il.expires_at,
    be.title as element_title,
    be.project_id,
    p.name as project_name,
    u.username,
    CASE 
        WHEN il.expires_at > NOW() THEN 'active'
        ELSE 'expired'
    END as lock_status
FROM input_locks il
INNER JOIN building_elements be ON il.element_id = be.id
INNER JOIN projects p ON be.project_id = p.id
LEFT JOIN users u ON il.user_id = u.id
WHERE il.expires_at > NOW() - INTERVAL '1 hour';

COMMENT ON VIEW v_active_locks IS 'Currently active or recently expired locks';

-- View: Recent Activity
CREATE OR REPLACE VIEW v_recent_activity AS
SELECT 
    'project' as entity_type,
    id as entity_id,
    name as entity_name,
    updated_at,
    NULL as project_id
FROM projects
WHERE updated_at > NOW() - INTERVAL '7 days'

UNION ALL

SELECT 
    'building_element' as entity_type,
    be.id as entity_id,
    be.title as entity_name,
    be.updated_at,
    be.project_id
FROM building_elements be
WHERE be.updated_at > NOW() - INTERVAL '7 days'

UNION ALL

SELECT 
    'budget_item' as entity_type,
    bi.id as entity_id,
    bi.description as entity_name,
    bi.updated_at,
    be.project_id
FROM budget_items bi
INNER JOIN building_elements be ON bi.element_id = be.id
WHERE bi.updated_at > NOW() - INTERVAL '7 days'

ORDER BY updated_at DESC;

COMMENT ON VIEW v_recent_activity IS 'Recent changes across all entities (last 7 days)';

-- ========================================
-- PART 2: FOREIGN KEY CONSTRAINTS
-- Add CASCADE deletes for data integrity
-- ========================================

-- Projects → Building Elements
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_building_elements_project'
    ) THEN
        ALTER TABLE building_elements
        ADD CONSTRAINT fk_building_elements_project
        FOREIGN KEY (project_id) 
        REFERENCES projects(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- Building Elements → Budget Items
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_budget_items_element'
    ) THEN
        ALTER TABLE budget_items
        ADD CONSTRAINT fk_budget_items_element
        FOREIGN KEY (element_id) 
        REFERENCES building_elements(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- Building Elements → Element Media
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_element_media_element'
    ) THEN
        ALTER TABLE element_media
        ADD CONSTRAINT fk_element_media_element
        FOREIGN KEY (element_id) 
        REFERENCES building_elements(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- Building Elements → Input Locks
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_input_locks_element'
    ) THEN
        ALTER TABLE input_locks
        ADD CONSTRAINT fk_input_locks_element
        FOREIGN KEY (element_id) 
        REFERENCES building_elements(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- Building Elements → Parent (Self-referencing)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_building_elements_parent'
    ) THEN
        ALTER TABLE building_elements
        ADD CONSTRAINT fk_building_elements_parent
        FOREIGN KEY (parent_id) 
        REFERENCES building_elements(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- Projects → Project Members
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_project_members_project'
    ) THEN
        ALTER TABLE project_members
        ADD CONSTRAINT fk_project_members_project
        FOREIGN KEY (project_id) 
        REFERENCES projects(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- Users → Project Members
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_project_members_user'
    ) THEN
        ALTER TABLE project_members
        ADD CONSTRAINT fk_project_members_user
        FOREIGN KEY (user_id) 
        REFERENCES users(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- Projects → Project Snapshots
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_project_snapshots_project'
    ) THEN
        ALTER TABLE project_snapshots
        ADD CONSTRAINT fk_project_snapshots_project
        FOREIGN KEY (project_id) 
        REFERENCES projects(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- Building Elements → Custom Field Values
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint 
        WHERE conname = 'fk_custom_field_values_definition'
    ) THEN
        ALTER TABLE custom_field_values
        ADD CONSTRAINT fk_custom_field_values_definition
        FOREIGN KEY (definition_id) 
        REFERENCES custom_field_definitions(id) 
        ON DELETE CASCADE
        ON UPDATE CASCADE;
    END IF;
END $$;

-- ========================================
-- PART 3: INDEXES FOR PERFORMANCE
-- Add missing indexes for frequently queried columns
-- ========================================

-- Building Elements
CREATE INDEX IF NOT EXISTS idx_building_elements_project_id ON building_elements(project_id);
CREATE INDEX IF NOT EXISTS idx_building_elements_parent_id ON building_elements(parent_id);
CREATE INDEX IF NOT EXISTS idx_building_elements_urgency ON building_elements(urgency);
CREATE INDEX IF NOT EXISTS idx_building_elements_updated_at ON building_elements(updated_at DESC);

-- Budget Items
CREATE INDEX IF NOT EXISTS idx_budget_items_element_id ON budget_items(element_id);
CREATE INDEX IF NOT EXISTS idx_budget_items_status ON budget_items(status);

-- Element Media
CREATE INDEX IF NOT EXISTS idx_element_media_element_id ON element_media(element_id);
CREATE INDEX IF NOT EXISTS idx_element_media_sort_order ON element_media(element_id, sort_order);

-- Input Locks
CREATE INDEX IF NOT EXISTS idx_input_locks_element_id ON input_locks(element_id);
CREATE INDEX IF NOT EXISTS idx_input_locks_expires_at ON input_locks(expires_at DESC);
CREATE INDEX IF NOT EXISTS idx_input_locks_user_id ON input_locks(user_id);

-- Projects
CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status);
CREATE INDEX IF NOT EXISTS idx_projects_client_id ON projects(client_id);
CREATE INDEX IF NOT EXISTS idx_projects_updated_at ON projects(updated_at DESC);

-- Project Snapshots
CREATE INDEX IF NOT EXISTS idx_project_snapshots_project_id ON project_snapshots(project_id);
CREATE INDEX IF NOT EXISTS idx_project_snapshots_created_at ON project_snapshots(created_at DESC);

-- Custom Field Values
CREATE INDEX IF NOT EXISTS idx_custom_field_values_entity ON custom_field_values(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_custom_field_values_definition ON custom_field_values(definition_id);

-- ========================================
-- VERIFICATION QUERIES
-- Run these to verify the optimizations
-- ========================================

-- Check views created
SELECT table_name, table_type 
FROM information_schema.tables 
WHERE table_schema = 'public' AND table_type = 'VIEW'
ORDER BY table_name;

-- Check foreign key constraints
SELECT 
    tc.constraint_name,
    tc.table_name,
    kcu.column_name,
    ccu.table_name AS foreign_table_name,
    ccu.column_name AS foreign_column_name,
    rc.delete_rule,
    rc.update_rule
FROM information_schema.table_constraints AS tc
JOIN information_schema.key_column_usage AS kcu
    ON tc.constraint_name = kcu.constraint_name
JOIN information_schema.constraint_column_usage AS ccu
    ON ccu.constraint_name = tc.constraint_name
JOIN information_schema.referential_constraints AS rc
    ON tc.constraint_name = rc.constraint_name
WHERE tc.constraint_type = 'FOREIGN KEY'
ORDER BY tc.table_name, tc.constraint_name;

-- Check indexes created
SELECT 
    tablename,
    indexname,
    indexdef
FROM pg_indexes
WHERE schemaname = 'public'
ORDER BY tablename, indexname;

-- ========================================
-- SUCCESS MESSAGE
-- ========================================

DO $$
BEGIN
    RAISE NOTICE '✅ Database optimization complete!';
    RAISE NOTICE '';
    RAISE NOTICE 'Created:';
    RAISE NOTICE '  - 6 database views';
    RAISE NOTICE '  - 9 foreign key constraints (CASCADE)';
    RAISE NOTICE '  - 15+ performance indexes';
    RAISE NOTICE '';
    RAISE NOTICE 'Run verification queries above to confirm.';
END $$;
