-- Add Support Tables for New Modules
-- This migration adds database tables for:
-- 1. Price Catalog with categories and price history
-- 2. Budget Templates
-- 3. Menu Management
-- 4. Enhanced element_images table

-- ====================================
-- PRICE CATALOG SYSTEM
-- ====================================

-- Price categories for organizing catalog
CREATE TABLE IF NOT EXISTS price_categories (
    id SERIAL PRIMARY KEY,
    parent_id INTEGER REFERENCES price_categories(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Price catalog items
CREATE TABLE IF NOT EXISTS price_catalog_items (
    id SERIAL PRIMARY KEY,
    category_id INTEGER REFERENCES price_categories(id) ON DELETE SET NULL,
    item_code VARCHAR(100),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50) DEFAULT 'stk',
    price DECIMAL(12,2) DEFAULT 0,
    display_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Price history tracking
CREATE TABLE IF NOT EXISTS price_history (
    id SERIAL PRIMARY KEY,
    catalog_item_id INTEGER REFERENCES price_catalog_items(id) ON DELETE CASCADE,
    price DECIMAL(12,2) NOT NULL,
    effective_date DATE NOT NULL,
    changed_by_user_id INTEGER REFERENCES users(id),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for price catalog
CREATE INDEX IF NOT EXISTS idx_price_categories_parent ON price_categories(parent_id);
CREATE INDEX IF NOT EXISTS idx_price_categories_order ON price_categories(display_order);
CREATE INDEX IF NOT EXISTS idx_price_catalog_category ON price_catalog_items(category_id);
CREATE INDEX IF NOT EXISTS idx_price_catalog_code ON price_catalog_items(item_code);
CREATE INDEX IF NOT EXISTS idx_price_catalog_name ON price_catalog_items(name);
CREATE INDEX IF NOT EXISTS idx_price_catalog_active ON price_catalog_items(is_active) WHERE is_active = TRUE;
CREATE INDEX IF NOT EXISTS idx_price_history_item ON price_history(catalog_item_id, effective_date DESC);

-- ====================================
-- BUDGET TEMPLATES
-- ====================================

-- Budget templates for quick budget creation
CREATE TABLE IF NOT EXISTS budget_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    budget_type VARCHAR(20) CHECK (budget_type IN ('capex', 'opex', 'reinstatement')),
    category VARCHAR(100), -- For grouping templates (e.g., 'HVAC', 'Facade', etc.)
    is_active BOOLEAN DEFAULT TRUE,
    created_by_user_id INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Template items/lines
CREATE TABLE IF NOT EXISTS budget_template_items (
    id SERIAL PRIMARY KEY,
    template_id INTEGER REFERENCES budget_templates(id) ON DELETE CASCADE,
    description TEXT NOT NULL,
    quantity DECIMAL(12,2) DEFAULT 1,
    unit VARCHAR(50) DEFAULT 'stk',
    price_per_unit DECIMAL(12,2) DEFAULT 0,
    timeline VARCHAR(50), -- e.g., '0-1', '1-2', '3-5', '5-10', '10+'
    notes TEXT,
    display_order INTEGER DEFAULT 0
);

-- Indexes for templates
CREATE INDEX IF NOT EXISTS idx_budget_templates_type ON budget_templates(budget_type);
CREATE INDEX IF NOT EXISTS idx_budget_templates_category ON budget_templates(category);
CREATE INDEX IF NOT EXISTS idx_budget_templates_active ON budget_templates(is_active) WHERE is_active = TRUE;
CREATE INDEX IF NOT EXISTS idx_template_items_template ON budget_template_items(template_id);
CREATE INDEX IF NOT EXISTS idx_template_items_order ON budget_template_items(template_id, display_order);

-- ====================================
-- MENU MANAGEMENT
-- ====================================

-- Navigation menu items
CREATE TABLE IF NOT EXISTS menu_items (
    id SERIAL PRIMARY KEY,
    parent_id INTEGER REFERENCES menu_items(id) ON DELETE CASCADE,
    label VARCHAR(100) NOT NULL,
    url VARCHAR(255),
    icon VARCHAR(100), -- Icon class or name
    required_module VARCHAR(100), -- Module key that user must have access to
    required_permission VARCHAR(50), -- Permission level required (view, edit, etc.)
    display_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for menu
CREATE INDEX IF NOT EXISTS idx_menu_items_parent ON menu_items(parent_id);
CREATE INDEX IF NOT EXISTS idx_menu_items_order ON menu_items(display_order);
CREATE INDEX IF NOT EXISTS idx_menu_items_active ON menu_items(is_active) WHERE is_active = TRUE;

-- ====================================
-- ENHANCED ELEMENT IMAGES
-- ====================================

-- Ensure element_images table exists with all needed fields
CREATE TABLE IF NOT EXISTS element_images (
    id SERIAL PRIMARY KEY,
    element_id INTEGER REFERENCES building_elements(id) ON DELETE CASCADE,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    filepath VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100),
    file_size INTEGER,
    width INTEGER,
    height INTEGER,
    description TEXT,
    tags VARCHAR(500), -- Comma-separated tags
    is_primary BOOLEAN DEFAULT FALSE,
    display_order INTEGER DEFAULT 0,
    uploaded_by_user_id INTEGER REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Indexes for images
CREATE INDEX IF NOT EXISTS idx_element_images_element ON element_images(element_id);
CREATE INDEX IF NOT EXISTS idx_element_images_order ON element_images(element_id, display_order);
CREATE INDEX IF NOT EXISTS idx_element_images_primary ON element_images(element_id, is_primary) WHERE is_primary = TRUE;

-- ====================================
-- DEFAULT DATA
-- ====================================

-- Insert default menu structure
INSERT INTO menu_items (label, url, icon, required_module, required_permission, display_order, is_active) VALUES
    ('Dashboard', '/dashboard', 'dashboard', 'dashboard', 'view', 1, TRUE),
    ('Projekter', '/projects', 'folder', 'project', 'view', 2, TRUE),
    ('Røde Flag', '/red-flags', 'flag', 'red_flags', 'view', 3, TRUE),
    ('Budgetter', NULL, 'attach_money', NULL, NULL, 4, TRUE),
    ('Rapporter', '/reports', 'description', 'report', 'view', 5, TRUE),
    ('Administration', NULL, 'settings', NULL, NULL, 6, TRUE)
ON CONFLICT DO NOTHING;

-- Get parent menu IDs
DO $$
DECLARE
    v_budget_menu_id INTEGER;
    v_admin_menu_id INTEGER;
BEGIN
    SELECT id INTO v_budget_menu_id FROM menu_items WHERE label = 'Budgetter' AND parent_id IS NULL;
    SELECT id INTO v_admin_menu_id FROM menu_items WHERE label = 'Administration' AND parent_id IS NULL;

    -- Budget submenu
    IF v_budget_menu_id IS NOT NULL THEN
        INSERT INTO menu_items (parent_id, label, url, icon, required_module, required_permission, display_order, is_active) VALUES
            (v_budget_menu_id, 'CAPEX', '/budgets/capex', 'trending_up', 'budget', 'view', 1, TRUE),
            (v_budget_menu_id, 'OPEX', '/budgets/opex', 'trending_down', 'opex', 'view', 2, TRUE),
            (v_budget_menu_id, 'Reinstatement', '/budgets/reinstatement', 'restore', 'budget', 'view', 3, TRUE),
            (v_budget_menu_id, 'Templates', '/budgets/templates', 'content_copy', 'template', 'view', 4, TRUE),
            (v_budget_menu_id, 'Priskatalog', '/price-catalog', 'list_alt', 'price_catalog', 'view', 5, TRUE)
        ON CONFLICT DO NOTHING;
    END IF;

    -- Admin submenu
    IF v_admin_menu_id IS NOT NULL THEN
        INSERT INTO menu_items (parent_id, label, url, icon, required_module, required_permission, display_order, is_active) VALUES
            (v_admin_menu_id, 'Brugere', '/admin/users', 'people', 'user', 'view', 1, TRUE),
            (v_admin_menu_id, 'Grupper', '/admin/groups', 'group', 'user', 'view', 2, TRUE),
            (v_admin_menu_id, 'Rettigheder', '/admin/permissions', 'lock', 'user', 'view', 3, TRUE),
            (v_admin_menu_id, 'Menu', '/admin/menu', 'menu', 'menu', 'view', 4, TRUE),
            (v_admin_menu_id, 'Indstillinger', '/admin/settings', 'settings', NULL, NULL, 5, TRUE)
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- Insert default permission modules for new modules
INSERT INTO permission_modules (module_key, display_name, is_active) VALUES
    ('image', 'Billeder', TRUE),
    ('price_catalog', 'Priskatalog', TRUE),
    ('template', 'Budget Templates', TRUE),
    ('menu', 'Menu Administration', TRUE)
ON CONFLICT (module_key) DO NOTHING;

-- Insert default permissions for new modules
DO $$
DECLARE
    v_image_module_id INTEGER;
    v_catalog_module_id INTEGER;
    v_template_module_id INTEGER;
    v_menu_module_id INTEGER;
BEGIN
    SELECT id INTO v_image_module_id FROM permission_modules WHERE module_key = 'image';
    SELECT id INTO v_catalog_module_id FROM permission_modules WHERE module_key = 'price_catalog';
    SELECT id INTO v_template_module_id FROM permission_modules WHERE module_key = 'template';
    SELECT id INTO v_menu_module_id FROM permission_modules WHERE module_key = 'menu';

    -- Image module permissions
    IF v_image_module_id IS NOT NULL THEN
        INSERT INTO permission_permissions (module_id, permission_key) VALUES
            (v_image_module_id, 'view'),
            (v_image_module_id, 'create'),
            (v_image_module_id, 'edit'),
            (v_image_module_id, 'delete')
        ON CONFLICT DO NOTHING;
    END IF;

    -- Price Catalog permissions
    IF v_catalog_module_id IS NOT NULL THEN
        INSERT INTO permission_permissions (module_id, permission_key) VALUES
            (v_catalog_module_id, 'view'),
            (v_catalog_module_id, 'create'),
            (v_catalog_module_id, 'edit'),
            (v_catalog_module_id, 'delete'),
            (v_catalog_module_id, 'import'),
            (v_catalog_module_id, 'export')
        ON CONFLICT DO NOTHING;
    END IF;

    -- Template permissions
    IF v_template_module_id IS NOT NULL THEN
        INSERT INTO permission_permissions (module_id, permission_key) VALUES
            (v_template_module_id, 'view'),
            (v_template_module_id, 'create'),
            (v_template_module_id, 'edit'),
            (v_template_module_id, 'delete'),
            (v_template_module_id, 'apply')
        ON CONFLICT DO NOTHING;
    END IF;

    -- Menu permissions
    IF v_menu_module_id IS NOT NULL THEN
        INSERT INTO permission_permissions (module_id, permission_key) VALUES
            (v_menu_module_id, 'view'),
            (v_menu_module_id, 'create'),
            (v_menu_module_id, 'edit'),
            (v_menu_module_id, 'delete')
        ON CONFLICT DO NOTHING;
    END IF;
END $$;

-- Grant new permissions to admin group
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
            WHERE m.module_key IN ('image', 'price_catalog', 'template', 'menu')
        LOOP
            INSERT INTO permission_group_permissions (group_id, permission_id)
            VALUES (v_admin_group_id, v_permission_id)
            ON CONFLICT DO NOTHING;
        END LOOP;
    END IF;
END $$;

-- ====================================
-- SAMPLE DATA (Optional)
-- ====================================

-- Insert some sample price catalog categories
INSERT INTO price_categories (name, description, display_order) VALUES
    ('HVAC', 'Heating, Ventilation and Air Conditioning', 1),
    ('Facade', 'External building facade elements', 2),
    ('Roof', 'Roof systems and materials', 3),
    ('Interior', 'Interior finishes and fixtures', 4),
    ('Structure', 'Structural elements', 5),
    ('MEP', 'Mechanical, Electrical, Plumbing', 6)
ON CONFLICT DO NOTHING;

-- Insert sample budget templates
DO $$
DECLARE
    v_hvac_category_id INTEGER;
    v_template_id INTEGER;
BEGIN
    SELECT id INTO v_hvac_category_id FROM price_categories WHERE name = 'HVAC';

    -- HVAC CAPEX Template
    INSERT INTO budget_templates (name, description, budget_type, category, is_active)
    VALUES ('HVAC System Renovation', 'Standard HVAC system renovation package', 'capex', 'HVAC', TRUE)
    RETURNING id INTO v_template_id;

    IF v_template_id IS NOT NULL AND v_hvac_category_id IS NOT NULL THEN
        INSERT INTO budget_template_items (template_id, description, quantity, unit, price_per_unit, timeline, display_order) VALUES
            (v_template_id, 'Heat pump installation', 1, 'stk', 150000, '0-1', 1),
            (v_template_id, 'Ventilation system', 1, 'stk', 200000, '0-1', 2),
            (v_template_id, 'Control system', 1, 'stk', 75000, '0-1', 3),
            (v_template_id, 'Commissioning and testing', 1, 'stk', 25000, '0-1', 4);
    END IF;
END $$;

-- Comments
COMMENT ON TABLE price_categories IS 'Hierarchical categories for price catalog';
COMMENT ON TABLE price_catalog_items IS 'Price catalog items with current pricing';
COMMENT ON TABLE price_history IS 'Price change history for catalog items';
COMMENT ON TABLE budget_templates IS 'Reusable budget templates';
COMMENT ON TABLE budget_template_items IS 'Line items in budget templates';
COMMENT ON TABLE menu_items IS 'Hierarchical navigation menu with permission filtering';
COMMENT ON TABLE element_images IS 'Images attached to building elements with drag-and-drop ordering';
