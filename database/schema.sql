-- DueDiligence v2.0 Database Schema
-- PostgreSQL 14+
-- Generated: 2026-01-15

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ======================================
-- USERS AND AUTHENTICATION
-- ======================================

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(50),
    avatar VARCHAR(255),
    active BOOLEAN DEFAULT true,
    email_verified_at TIMESTAMP,
    remember_token VARCHAR(100),
    last_login_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_active ON users(active);

CREATE TABLE roles (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_roles (
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    role_id INT REFERENCES roles(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE permissions (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL,
    description TEXT,
    module VARCHAR(50) NOT NULL,
    action VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE role_permissions (
    role_id INT REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INT REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

-- ======================================
-- CUSTOMERS
-- ======================================

CREATE TABLE customers (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100) DEFAULT 'Denmark',
    cvr_number VARCHAR(50),
    notes TEXT,
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_customers_name ON customers(name);
CREATE INDEX idx_customers_deleted ON customers(deleted_at);

-- ======================================
-- PROJECTS
-- ======================================

CREATE TABLE projects (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    customer_id INT REFERENCES customers(id) ON DELETE SET NULL,
    status VARCHAR(50) DEFAULT 'planning',
    description TEXT,

    -- Building Information
    bbr_number VARCHAR(50),
    address TEXT,
    city VARCHAR(100),
    postal_code VARCHAR(20),
    area_m2 NUMERIC(10,2),
    construction_year INT,
    renovation_year INT,
    heating_type VARCHAR(100),
    usage_type VARCHAR(100),

    -- Dates
    start_date DATE,
    end_date DATE,
    inspection_date DATE,

    -- Report Configuration
    report_intro TEXT,
    report_disclaimer TEXT,
    cover_image VARCHAR(255),

    -- Metadata
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL
);

CREATE INDEX idx_projects_customer ON projects(customer_id);
CREATE INDEX idx_projects_status ON projects(status);
CREATE INDEX idx_projects_deleted ON projects(deleted_at);
CREATE INDEX idx_projects_dates ON projects(start_date, end_date);

CREATE TABLE project_members (
    id SERIAL PRIMARY KEY,
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,
    user_id INT REFERENCES users(id) ON DELETE CASCADE,
    role VARCHAR(50) DEFAULT 'member', -- 'owner', 'manager', 'member', 'viewer'
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(project_id, user_id)
);

CREATE INDEX idx_project_members ON project_members(project_id, user_id);

CREATE TABLE project_snapshots (
    id SERIAL PRIMARY KEY,
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    snapshot_data JSONB,
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_snapshots_project ON project_snapshots(project_id, created_at DESC);

-- ======================================
-- BUILDING ELEMENTS (Hierarchical)
-- ======================================

CREATE TABLE building_elements (
    id SERIAL PRIMARY KEY,
    uuid UUID DEFAULT uuid_generate_v4() UNIQUE NOT NULL,
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,
    parent_id INT REFERENCES building_elements(id) ON DELETE CASCADE,

    -- Basic Info
    name VARCHAR(255) NOT NULL,
    element_type VARCHAR(100),
    description TEXT,
    location VARCHAR(255),

    -- Assessment
    condition_score INT CHECK (condition_score BETWEEN 0 AND 10),
    urgency VARCHAR(50), -- 'low', 'medium', 'high', 'critical'
    time_horizon VARCHAR(50), -- '0-1', '1-2', '3-5', '5-10', '10+'

    -- Financial
    capex NUMERIC(12,2),
    replacement_value NUMERIC(12,2),

    -- Technical Details
    technical_data JSONB,

    -- Ordering
    sort_order INT DEFAULT 0,

    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_elements_project ON building_elements(project_id);
CREATE INDEX idx_elements_parent ON building_elements(parent_id);
CREATE INDEX idx_elements_sort ON building_elements(project_id, parent_id, sort_order);
CREATE INDEX idx_elements_urgency ON building_elements(urgency);

-- ======================================
-- CUSTOM FIELDS SYSTEM (QDPM-inspired)
-- ======================================

CREATE TABLE custom_field_definitions (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL, -- 'project', 'building_element', 'customer', 'user'
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(255) NOT NULL,
    field_type VARCHAR(50) NOT NULL, -- 'text', 'textarea', 'number', 'date', 'datetime', 'select', 'multi_select', 'checkbox', 'radio', 'email', 'url', 'phone'

    -- Options for select/multi-select/radio
    options JSONB, -- [{"value":"val1","label":"Label 1"},...]

    -- Validation
    default_value TEXT,
    required BOOLEAN DEFAULT false,
    max_length INT,
    min_value NUMERIC,
    max_value NUMERIC,
    validation_regex VARCHAR(255),
    validation_message VARCHAR(255),

    -- Scope
    scope VARCHAR(20) DEFAULT 'global', -- 'global' or 'project'
    project_id INT REFERENCES projects(id) ON DELETE CASCADE,

    -- Display
    placeholder TEXT,
    help_text TEXT,
    sort_order INT DEFAULT 0,
    active BOOLEAN DEFAULT true,
    show_in_list BOOLEAN DEFAULT false,

    -- Metadata
    created_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_custom_fields_entity ON custom_field_definitions(entity_type, active);
CREATE INDEX idx_custom_fields_project ON custom_field_definitions(project_id);
CREATE INDEX idx_custom_fields_sort ON custom_field_definitions(entity_type, sort_order);

-- Unique constraint: field_name must be unique per entity_type/scope/project combination
CREATE UNIQUE INDEX idx_custom_fields_unique_global
    ON custom_field_definitions(entity_type, field_name)
    WHERE scope = 'global';

CREATE UNIQUE INDEX idx_custom_fields_unique_project
    ON custom_field_definitions(entity_type, field_name, project_id)
    WHERE scope = 'project';

CREATE TABLE custom_field_values (
    id SERIAL PRIMARY KEY,
    definition_id INT REFERENCES custom_field_definitions(id) ON DELETE CASCADE,
    entity_id INT NOT NULL,
    value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(definition_id, entity_id)
);

CREATE INDEX idx_custom_values_entity ON custom_field_values(entity_id);
CREATE INDEX idx_custom_values_definition ON custom_field_values(definition_id);

-- ======================================
-- BUDGET MANAGEMENT
-- ======================================

CREATE TABLE price_catalogs (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50),
    unit_price NUMERIC(10,2),
    category VARCHAR(100),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_price_catalog_category ON price_catalogs(category, active);
CREATE INDEX idx_price_catalog_name ON price_catalogs(name);

CREATE TABLE budget_items (
    id SERIAL PRIMARY KEY,
    element_id INT REFERENCES building_elements(id) ON DELETE CASCADE,
    catalog_item_id INT REFERENCES price_catalogs(id) ON DELETE SET NULL,

    description TEXT NOT NULL,
    quantity NUMERIC(10,2),
    unit VARCHAR(50),
    unit_price NUMERIC(10,2),
    total_price NUMERIC(12,2) GENERATED ALWAYS AS (quantity * unit_price) STORED,

    time_horizon VARCHAR(50), -- '0-1', '1-2', '3-5', '5-10', '10+'
    status VARCHAR(50) DEFAULT 'pending', -- 'pending', 'approved', 'rejected', 'completed'

    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_budget_element ON budget_items(element_id);
CREATE INDEX idx_budget_catalog ON budget_items(catalog_item_id);
CREATE INDEX idx_budget_horizon ON budget_items(time_horizon);

-- ======================================
-- MEDIA MANAGEMENT
-- ======================================

CREATE TABLE element_media (
    id SERIAL PRIMARY KEY,
    element_id INT REFERENCES building_elements(id) ON DELETE CASCADE,

    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    mime_type VARCHAR(100),

    title VARCHAR(255),
    caption TEXT,
    annotation_data JSONB, -- Canvas annotations: {shapes:[...], text:[...]}

    sort_order INT DEFAULT 0,

    uploaded_by INT REFERENCES users(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_media_element ON element_media(element_id, sort_order);

-- ======================================
-- LOCKING SYSTEM (Concurrent Editing)
-- ======================================

CREATE TABLE input_locks (
    id SERIAL PRIMARY KEY,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    locked_by INT REFERENCES users(id) ON DELETE CASCADE,
    acquired_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    UNIQUE(entity_type, entity_id, field_name)
);

CREATE INDEX idx_locks_entity ON input_locks(entity_type, entity_id);
CREATE INDEX idx_locks_expires ON input_locks(expires_at);

-- ======================================
-- ACTIVITY LOGGING
-- ======================================

CREATE TABLE activity_logs (
    id SERIAL PRIMARY KEY,
    user_id INT REFERENCES users(id) ON DELETE SET NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    action VARCHAR(50), -- 'created', 'updated', 'deleted', 'viewed', 'exported'
    description TEXT,
    changes JSONB, -- Before/after values
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_activity_user ON activity_logs(user_id, created_at DESC);
CREATE INDEX idx_activity_entity ON activity_logs(entity_type, entity_id, created_at DESC);
CREATE INDEX idx_activity_date ON activity_logs(created_at DESC);

-- ======================================
-- SYSTEM SETTINGS
-- ======================================

CREATE TABLE system_settings (
    id SERIAL PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'string', -- 'string', 'integer', 'boolean', 'json'
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ======================================
-- RATE LIMITING
-- ======================================

CREATE TABLE rate_limits (
    id SERIAL PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL, -- User ID or IP address
    endpoint VARCHAR(255) NOT NULL,
    attempts INT DEFAULT 1,
    reset_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(identifier, endpoint)
);

CREATE INDEX idx_rate_limits_reset ON rate_limits(reset_at);

-- ======================================
-- FUNCTIONS AND TRIGGERS
-- ======================================

-- Auto-update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply to all tables with updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_customers_updated_at BEFORE UPDATE ON customers
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_projects_updated_at BEFORE UPDATE ON projects
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_building_elements_updated_at BEFORE UPDATE ON building_elements
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_custom_field_definitions_updated_at BEFORE UPDATE ON custom_field_definitions
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_custom_field_values_updated_at BEFORE UPDATE ON custom_field_values
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_budget_items_updated_at BEFORE UPDATE ON budget_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_element_media_updated_at BEFORE UPDATE ON element_media
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ======================================
-- VIEWS FOR COMMON QUERIES
-- ======================================

-- Project Overview with counts
CREATE OR REPLACE VIEW project_overview AS
SELECT
    p.*,
    c.name as customer_name,
    u.username as created_by_username,
    COUNT(DISTINCT be.id) as element_count,
    COUNT(DISTINCT pm.user_id) as member_count,
    COALESCE(SUM(bi.total_price), 0) as total_budget
FROM projects p
LEFT JOIN customers c ON p.customer_id = c.id
LEFT JOIN users u ON p.created_by = u.id
LEFT JOIN building_elements be ON p.id = be.project_id
LEFT JOIN project_members pm ON p.id = pm.project_id
LEFT JOIN budget_items bi ON be.id = bi.element_id
WHERE p.deleted_at IS NULL
GROUP BY p.id, c.name, u.username;

-- Building Elements with budget summary
CREATE OR REPLACE VIEW elements_with_budget AS
SELECT
    be.*,
    COUNT(DISTINCT bi.id) as budget_item_count,
    COALESCE(SUM(bi.total_price), 0) as total_budget,
    COUNT(DISTINCT em.id) as media_count
FROM building_elements be
LEFT JOIN budget_items bi ON be.id = bi.element_id
LEFT JOIN element_media em ON be.id = em.element_id
GROUP BY be.id;

-- ======================================
-- INITIAL DATA
-- ======================================

-- Default Roles
INSERT INTO roles (name, description) VALUES
('admin', 'Full system access'),
('manager', 'Can manage projects and users'),
('inspector', 'Can create and edit building inspections'),
('viewer', 'Read-only access'),
('customer', 'Customer portal access');

-- Default Permissions
INSERT INTO permissions (name, description, module, action) VALUES
-- Project permissions
('project.create', 'Create new projects', 'project', 'create'),
('project.view', 'View projects', 'project', 'view'),
('project.edit', 'Edit projects', 'project', 'edit'),
('project.delete', 'Delete projects', 'project', 'delete'),
-- Building element permissions
('element.create', 'Create building elements', 'element', 'create'),
('element.view', 'View building elements', 'element', 'view'),
('element.edit', 'Edit building elements', 'element', 'edit'),
('element.delete', 'Delete building elements', 'element', 'delete'),
-- Budget permissions
('budget.view', 'View budget', 'budget', 'view'),
('budget.edit', 'Edit budget', 'budget', 'edit'),
-- User management
('user.manage', 'Manage users', 'user', 'manage'),
-- System settings
('settings.manage', 'Manage system settings', 'settings', 'manage');

-- Assign permissions to roles
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'admin'; -- Admin gets all permissions

-- Manager permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'manager'
AND p.name IN ('project.create', 'project.view', 'project.edit', 'element.create',
               'element.view', 'element.edit', 'budget.view', 'budget.edit');

-- Inspector permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'inspector'
AND p.name IN ('project.view', 'element.create', 'element.view',
               'element.edit', 'budget.view', 'budget.edit');

-- Viewer permissions
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'viewer'
AND p.name IN ('project.view', 'element.view', 'budget.view');

-- Default admin user (password: admin123 - CHANGE IN PRODUCTION!)
-- Password hash generated with: password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 12])
INSERT INTO users (username, email, password_hash, first_name, last_name, active)
VALUES ('admin', 'admin@duediligence.local',
        '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5Fqeqv/oB4kFy',
        'Admin', 'User', true);

-- Assign admin role to admin user
INSERT INTO user_roles (user_id, role_id)
SELECT u.id, r.id
FROM users u
CROSS JOIN roles r
WHERE u.username = 'admin' AND r.name = 'admin';

-- ======================================
-- CLEANUP FUNCTION
-- ======================================

-- Function to clean up expired locks (should be run periodically)
CREATE OR REPLACE FUNCTION cleanup_expired_locks()
RETURNS INT AS $$
DECLARE
    deleted_count INT;
BEGIN
    DELETE FROM input_locks WHERE expires_at < NOW();
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;

-- Function to clean up old rate limit entries
CREATE OR REPLACE FUNCTION cleanup_rate_limits()
RETURNS INT AS $$
DECLARE
    deleted_count INT;
BEGIN
    DELETE FROM rate_limits WHERE reset_at < NOW() - INTERVAL '1 day';
    GET DIAGNOSTICS deleted_count = ROW_COUNT;
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;

-- ======================================
-- COMMENTS
-- ======================================

COMMENT ON TABLE users IS 'System users with authentication';
COMMENT ON TABLE custom_field_definitions IS 'Dynamic field definitions inspired by QDPM Extra Fields';
COMMENT ON TABLE custom_field_values IS 'Actual values for custom fields per entity instance';
COMMENT ON TABLE building_elements IS 'Hierarchical building inspection elements';
COMMENT ON TABLE project_snapshots IS 'Project version snapshots for history tracking';

-- ======================================
-- END OF SCHEMA
-- ======================================
