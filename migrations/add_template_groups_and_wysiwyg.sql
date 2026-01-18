-- Add Template Groups/Folders and WYSIWYG Support
-- This migration adds:
-- 1. Hierarkisk struktur til budget_template_items (mapper/grupper)
-- 2. Projects display_order for drag-and-drop sortering
-- 3. WYSIWYG indhold support

-- ====================================
-- PROJECTS DRAG-AND-DROP
-- ====================================

-- Add display_order to projects if not exists
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'projects' AND column_name = 'display_order'
    ) THEN
        ALTER TABLE projects ADD COLUMN display_order INTEGER DEFAULT NULL;
    END IF;
END $$;

-- Index for sorting
CREATE INDEX IF NOT EXISTS idx_projects_display_order ON projects(display_order) WHERE display_order IS NOT NULL;

-- ====================================
-- TEMPLATE GROUPS/FOLDERS
-- ====================================

-- Add parent_id to budget_template_items for hierarchical structure
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'budget_template_items' AND column_name = 'parent_id'
    ) THEN
        ALTER TABLE budget_template_items ADD COLUMN parent_id INTEGER REFERENCES budget_template_items(id) ON DELETE CASCADE;
    END IF;
END $$;

-- Add is_group flag to identify folder/group items
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'budget_template_items' AND column_name = 'is_group'
    ) THEN
        ALTER TABLE budget_template_items ADD COLUMN is_group BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- Add multiplier to store calculation factor
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'budget_template_items' AND column_name = 'multiplier'
    ) THEN
        ALTER TABLE budget_template_items ADD COLUMN multiplier DECIMAL(12,2) DEFAULT 1;
    END IF;
END $$;

-- Index for parent lookups
CREATE INDEX IF NOT EXISTS idx_template_items_parent ON budget_template_items(parent_id) WHERE parent_id IS NOT NULL;

-- ====================================
-- WYSIWYG CONTENT SUPPORT
-- ====================================

-- Add rich_text_content to reports for WYSIWYG content
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'reports' AND column_name = 'rich_text_content'
    ) THEN
        ALTER TABLE reports ADD COLUMN rich_text_content TEXT;
    END IF;
END $$;

-- Add wysiwyg_enabled flag
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_name = 'reports' AND column_name = 'wysiwyg_enabled'
    ) THEN
        ALTER TABLE reports ADD COLUMN wysiwyg_enabled BOOLEAN DEFAULT FALSE;
    END IF;
END $$;

-- ====================================
-- REPORT TEMPLATES
-- ====================================

-- Create report_templates table for report builder
CREATE TABLE IF NOT EXISTS report_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    template_content TEXT NOT NULL,
    report_type VARCHAR(50) DEFAULT 'custom',
    created_by_user_id INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Index for report templates
CREATE INDEX IF NOT EXISTS idx_report_templates_type ON report_templates(report_type);

-- ====================================
-- HELPER FUNCTIONS FOR TEMPLATES
-- ====================================

-- Function to calculate total for template item including children
CREATE OR REPLACE FUNCTION calculate_template_item_total(p_item_id INTEGER)
RETURNS DECIMAL(12,2) AS $$
DECLARE
    v_total DECIMAL(12,2);
    v_item RECORD;
BEGIN
    -- Get the item
    SELECT * INTO v_item FROM budget_template_items WHERE id = p_item_id;

    IF NOT FOUND THEN
        RETURN 0;
    END IF;

    -- If it's a group, sum children
    IF v_item.is_group THEN
        SELECT COALESCE(SUM(
            CASE
                WHEN bti.is_group THEN calculate_template_item_total(bti.id)
                ELSE bti.quantity * bti.price_per_unit * COALESCE(bti.multiplier, 1)
            END
        ), 0) INTO v_total
        FROM budget_template_items bti
        WHERE bti.parent_id = p_item_id;
    ELSE
        -- Regular item
        v_total := v_item.quantity * v_item.price_per_unit * COALESCE(v_item.multiplier, 1);
    END IF;

    RETURN v_total;
END;
$$ LANGUAGE plpgsql STABLE;

-- Function to get template hierarchy
CREATE OR REPLACE FUNCTION get_template_hierarchy(p_template_id INTEGER)
RETURNS TABLE (
    item_id INTEGER,
    item_description TEXT,
    parent_id INTEGER,
    is_group BOOLEAN,
    quantity DECIMAL(12,2),
    unit VARCHAR(50),
    price_per_unit DECIMAL(12,2),
    multiplier DECIMAL(12,2),
    timeline VARCHAR(50),
    display_order INTEGER,
    depth INTEGER,
    path TEXT,
    total_price DECIMAL(12,2)
) AS $$
BEGIN
    RETURN QUERY
    WITH RECURSIVE item_tree AS (
        -- Base case: root items
        SELECT
            bti.id,
            bti.description,
            bti.parent_id,
            bti.is_group,
            bti.quantity,
            bti.unit,
            bti.price_per_unit,
            COALESCE(bti.multiplier, 1) as multiplier,
            bti.timeline,
            bti.display_order,
            0 as depth,
            bti.description::TEXT as path,
            CASE
                WHEN bti.is_group THEN calculate_template_item_total(bti.id)
                ELSE bti.quantity * bti.price_per_unit * COALESCE(bti.multiplier, 1)
            END as total_price
        FROM budget_template_items bti
        WHERE bti.template_id = p_template_id
            AND bti.parent_id IS NULL

        UNION ALL

        -- Recursive case: child items
        SELECT
            bti.id,
            bti.description,
            bti.parent_id,
            bti.is_group,
            bti.quantity,
            bti.unit,
            bti.price_per_unit,
            COALESCE(bti.multiplier, 1) as multiplier,
            bti.timeline,
            bti.display_order,
            it.depth + 1,
            it.path || ' > ' || bti.description,
            CASE
                WHEN bti.is_group THEN calculate_template_item_total(bti.id)
                ELSE bti.quantity * bti.price_per_unit * COALESCE(bti.multiplier, 1)
            END as total_price
        FROM budget_template_items bti
        INNER JOIN item_tree it ON bti.parent_id = it.id
        WHERE bti.template_id = p_template_id
    )
    SELECT * FROM item_tree
    ORDER BY path, display_order;
END;
$$ LANGUAGE plpgsql STABLE;

-- ====================================
-- PERMISSION MODULE FOR WYSIWYG
-- ====================================

INSERT INTO permission_modules (module_key, display_name, is_active) VALUES
    ('wysiwyg', 'WYSIWYG Editor', TRUE)
ON CONFLICT (module_key) DO NOTHING;

-- Insert permissions for WYSIWYG
DO $$
DECLARE
    v_wysiwyg_module_id INTEGER;
BEGIN
    SELECT id INTO v_wysiwyg_module_id FROM permission_modules WHERE module_key = 'wysiwyg';

    IF v_wysiwyg_module_id IS NOT NULL THEN
        INSERT INTO permission_permissions (module_id, permission_key) VALUES
            (v_wysiwyg_module_id, 'view'),
            (v_wysiwyg_module_id, 'edit'),
            (v_wysiwyg_module_id, 'upload')
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- Grant WYSIWYG permissions to admin group
DO $$
DECLARE
    v_admin_group_id INTEGER;
    v_permission_id INTEGER;
BEGIN
    SELECT id INTO v_admin_group_id FROM permission_groups WHERE name = 'Administratorer';

    IF v_admin_group_id IS NOT NULL THEN
        FOR v_permission_id IN
            SELECT p.id
            FROM permission_permissions p
            JOIN permission_modules m ON m.id = p.module_id
            WHERE m.module_key = 'wysiwyg'
        LOOP
            INSERT INTO permission_group_permissions (group_id, permission_id)
            VALUES (v_admin_group_id, v_permission_id)
            ON CONFLICT DO NOTHING;
        END LOOP;
    END IF;
END $$;

-- ====================================
-- PERMISSION MODULE FOR REPORT BUILDER
-- ====================================

INSERT INTO permission_modules (module_key, display_name, is_active) VALUES
    ('report_builder', 'Rapport Builder', TRUE)
ON CONFLICT (module_key) DO NOTHING;

-- Insert permissions for Report Builder
DO $$
DECLARE
    v_builder_module_id INTEGER;
BEGIN
    SELECT id INTO v_builder_module_id FROM permission_modules WHERE module_key = 'report_builder';

    IF v_builder_module_id IS NOT NULL THEN
        INSERT INTO permission_permissions (module_id, permission_key) VALUES
            (v_builder_module_id, 'view'),
            (v_builder_module_id, 'create'),
            (v_builder_module_id, 'edit'),
            (v_builder_module_id, 'delete'),
            (v_builder_module_id, 'render')
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- Grant Report Builder permissions to admin and project manager groups
DO $$
DECLARE
    v_admin_group_id INTEGER;
    v_pm_group_id INTEGER;
    v_permission_id INTEGER;
BEGIN
    SELECT id INTO v_admin_group_id FROM permission_groups WHERE name = 'Administratorer';
    SELECT id INTO v_pm_group_id FROM permission_groups WHERE name = 'Projektledere';

    -- Grant to admins
    IF v_admin_group_id IS NOT NULL THEN
        FOR v_permission_id IN
            SELECT p.id
            FROM permission_permissions p
            JOIN permission_modules m ON m.id = p.module_id
            WHERE m.module_key = 'report_builder'
        LOOP
            INSERT INTO permission_group_permissions (group_id, permission_id)
            VALUES (v_admin_group_id, v_permission_id)
            ON CONFLICT DO NOTHING;
        END LOOP;
    END IF;

    -- Grant view and render to project managers
    IF v_pm_group_id IS NOT NULL THEN
        FOR v_permission_id IN
            SELECT p.id
            FROM permission_permissions p
            JOIN permission_modules m ON m.id = p.module_id
            WHERE m.module_key = 'report_builder'
                AND p.permission_key IN ('view', 'render')
        LOOP
            INSERT INTO permission_group_permissions (group_id, permission_id)
            VALUES (v_pm_group_id, v_permission_id)
            ON CONFLICT DO NOTHING;
        END LOOP;
    END IF;
END $$;

-- Comments
COMMENT ON COLUMN budget_template_items.parent_id IS 'Parent item ID for hierarchical template structure (groups/folders)';
COMMENT ON COLUMN budget_template_items.is_group IS 'True if this is a group/folder item that contains other items';
COMMENT ON COLUMN budget_template_items.multiplier IS 'Multiplier factor - when parent quantity changes, children are multiplied by this value';
COMMENT ON COLUMN projects.display_order IS 'Display order for drag-and-drop sorting';
COMMENT ON COLUMN reports.rich_text_content IS 'WYSIWYG formatted content for reports';
COMMENT ON COLUMN reports.wysiwyg_enabled IS 'Whether this report uses WYSIWYG editor';
COMMENT ON FUNCTION calculate_template_item_total IS 'Recursively calculates total price for template item including all children';
COMMENT ON FUNCTION get_template_hierarchy IS 'Returns full template hierarchy with calculated totals';
