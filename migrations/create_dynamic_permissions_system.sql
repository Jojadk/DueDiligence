-- ========================================
-- Dynamic Permission System Migration
-- ========================================
-- Creates database-driven permission system with:
-- - Groups (teams)
-- - Module-based permissions
-- - Project-level permissions
-- - User and group inheritance
-- - No hardcoded roles
-- ========================================

-- ========================================
-- 1. GROUPS (Teams/Departments)
-- ========================================

CREATE TABLE IF NOT EXISTS groups (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    updated_at TIMESTAMP
);

COMMENT ON TABLE groups IS 'Grupper/teams - brugere tilhører en eller flere grupper';

-- ========================================
-- 2. USER-GROUP MEMBERSHIP
-- ========================================

CREATE TABLE IF NOT EXISTS user_groups (
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    group_id INTEGER REFERENCES groups(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER REFERENCES users(id),
    PRIMARY KEY (user_id, group_id)
);

COMMENT ON TABLE user_groups IS 'Brugere tilhører grupper - many-to-many relation';

-- ========================================
-- 3. MODULES (System components)
-- ========================================

CREATE TABLE IF NOT EXISTS modules (
    id SERIAL PRIMARY KEY,
    module_key VARCHAR(100) NOT NULL UNIQUE, -- 'dashboard', 'project', 'opex', etc.
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50), -- Icon identifier
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    parent_module_id INTEGER REFERENCES modules(id), -- For sub-modules
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE modules IS 'System moduler - definerer systemets funktionalitet';

-- ========================================
-- 4. PERMISSIONS (Actions within modules)
-- ========================================

CREATE TABLE IF NOT EXISTS permissions (
    id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES modules(id) ON DELETE CASCADE,
    permission_key VARCHAR(100) NOT NULL, -- 'view', 'create', 'edit', 'delete', 'export', etc.
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(module_id, permission_key)
);

COMMENT ON TABLE permissions IS 'Rettigheder indenfor moduler - hvad kan man gøre?';

-- ========================================
-- 5. GROUP PERMISSIONS
-- ========================================

CREATE TABLE IF NOT EXISTS group_permissions (
    id SERIAL PRIMARY KEY,
    group_id INTEGER REFERENCES groups(id) ON DELETE CASCADE,
    permission_id INTEGER REFERENCES permissions(id) ON DELETE CASCADE,
    granted BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    UNIQUE(group_id, permission_id)
);

COMMENT ON TABLE group_permissions IS 'Gruppers rettigheder - arves af gruppens brugere';

-- ========================================
-- 6. USER PERMISSIONS (Overrides)
-- ========================================

CREATE TABLE IF NOT EXISTS user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    permission_id INTEGER REFERENCES permissions(id) ON DELETE CASCADE,
    granted BOOLEAN, -- NULL = inherit from group, true = granted, false = denied
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    UNIQUE(user_id, permission_id)
);

COMMENT ON TABLE user_permissions IS 'Bruger-specifikke rettigheder - overskriver gruppe rettigheder';

-- ========================================
-- 7. PROJECT PERMISSIONS (Entity-level)
-- ========================================

CREATE TABLE IF NOT EXISTS project_permissions (
    id SERIAL PRIMARY KEY,
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    entity_type VARCHAR(20) NOT NULL CHECK (entity_type IN ('user', 'group')),
    entity_id INTEGER NOT NULL, -- user_id or group_id
    permission_level VARCHAR(20) NOT NULL CHECK (permission_level IN ('owner', 'editor', 'viewer', 'none')),
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INTEGER REFERENCES users(id),
    UNIQUE(project_id, entity_type, entity_id)
);

COMMENT ON TABLE project_permissions IS 'Projekt-niveau rettigheder - hvem har adgang til hvilke projekter';

-- ========================================
-- 8. BUILDING PERMISSIONS (Optional - granular)
-- ========================================

CREATE TABLE IF NOT EXISTS building_permissions (
    id SERIAL PRIMARY KEY,
    building_id INTEGER REFERENCES buildings(id) ON DELETE CASCADE,
    entity_type VARCHAR(20) NOT NULL CHECK (entity_type IN ('user', 'group')),
    entity_id INTEGER NOT NULL,
    permission_level VARCHAR(20) NOT NULL CHECK (permission_level IN ('editor', 'viewer', 'none')),
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INTEGER REFERENCES users(id),
    UNIQUE(building_id, entity_type, entity_id)
);

COMMENT ON TABLE building_permissions IS 'Bygnings-niveau rettigheder - granulær adgangskontrol';

-- ========================================
-- 9. PERMISSION AUDIT LOG
-- ========================================

CREATE TABLE IF NOT EXISTS permission_audit_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    action VARCHAR(100) NOT NULL, -- 'grant', 'revoke', 'check'
    entity_type VARCHAR(50), -- 'group', 'user', 'project'
    entity_id INTEGER,
    permission_id INTEGER REFERENCES permissions(id),
    granted BOOLEAN,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE permission_audit_log IS 'Audit log for alle rettigheds ændringer og checks';

-- ========================================
-- INDEXES for Performance
-- ========================================

-- User-Group lookups
CREATE INDEX idx_user_groups_user ON user_groups(user_id);
CREATE INDEX idx_user_groups_group ON user_groups(group_id);

-- Module lookups
CREATE INDEX idx_modules_active ON modules(is_active) WHERE is_active = true;
CREATE INDEX idx_modules_parent ON modules(parent_module_id);

-- Permission lookups
CREATE INDEX idx_permissions_module ON permissions(module_id);
CREATE INDEX idx_permissions_active ON permissions(is_active) WHERE is_active = true;

-- Group permission lookups (critical for performance)
CREATE INDEX idx_group_permissions_group ON group_permissions(group_id);
CREATE INDEX idx_group_permissions_permission ON group_permissions(permission_id);
CREATE INDEX idx_group_permissions_granted ON group_permissions(granted) WHERE granted = true;

-- User permission lookups
CREATE INDEX idx_user_permissions_user ON user_permissions(user_id);
CREATE INDEX idx_user_permissions_permission ON user_permissions(permission_id);

-- Project permissions (critical for authorization)
CREATE INDEX idx_project_permissions_project ON project_permissions(project_id);
CREATE INDEX idx_project_permissions_entity ON project_permissions(entity_type, entity_id);
CREATE INDEX idx_project_permissions_user ON project_permissions(entity_id) WHERE entity_type = 'user';
CREATE INDEX idx_project_permissions_group ON project_permissions(entity_id) WHERE entity_type = 'group';

-- Building permissions
CREATE INDEX idx_building_permissions_building ON building_permissions(building_id);
CREATE INDEX idx_building_permissions_entity ON building_permissions(entity_type, entity_id);

-- Audit log
CREATE INDEX idx_permission_audit_user ON permission_audit_log(user_id);
CREATE INDEX idx_permission_audit_created ON permission_audit_log(created_at);

-- ========================================
-- VIEWS for Easy Permission Checking
-- ========================================

-- View: User's effective permissions (from groups + user overrides)
CREATE OR REPLACE VIEW v_user_effective_permissions AS
WITH user_group_permissions AS (
    -- Permissions inherited from groups
    SELECT DISTINCT
        ug.user_id,
        gp.permission_id,
        gp.granted,
        'group' as source,
        g.name as source_name
    FROM user_groups ug
    JOIN group_permissions gp ON ug.group_id = gp.group_id
    JOIN groups g ON ug.group_id = g.id
    WHERE g.is_active = true AND gp.granted = true
),
combined_permissions AS (
    -- Start with group permissions
    SELECT * FROM user_group_permissions

    UNION ALL

    -- Add user-specific permissions
    SELECT
        up.user_id,
        up.permission_id,
        up.granted,
        'user' as source,
        'User Override' as source_name
    FROM user_permissions up
    WHERE up.granted IS NOT NULL
)
SELECT
    cp.user_id,
    cp.permission_id,
    p.module_id,
    m.module_key,
    p.permission_key,
    m.display_name as module_name,
    p.display_name as permission_name,
    -- User overrides win over group permissions
    BOOL_OR(cp.granted) as has_permission,
    STRING_AGG(DISTINCT cp.source_name, ', ') as granted_by
FROM combined_permissions cp
JOIN permissions p ON cp.permission_id = p.id
JOIN modules m ON p.module_id = m.id
WHERE p.is_active = true AND m.is_active = true
GROUP BY cp.user_id, cp.permission_id, p.module_id, m.module_key,
         p.permission_key, m.display_name, p.display_name;

COMMENT ON VIEW v_user_effective_permissions IS 'Brugers effektive rettigheder (gruppe + user overrides)';

-- View: User's project access (via user or group)
CREATE OR REPLACE VIEW v_user_project_access AS
SELECT DISTINCT
    p.id as project_id,
    p.name as project_name,
    COALESCE(
        -- Direct user permission
        (SELECT permission_level FROM project_permissions
         WHERE project_id = p.id AND entity_type = 'user' AND entity_id = u.id),
        -- Or best group permission
        (SELECT MAX(permission_level) FROM project_permissions pp
         JOIN user_groups ug ON pp.entity_id = ug.group_id
         WHERE pp.project_id = p.id AND pp.entity_type = 'group' AND ug.user_id = u.id),
        -- Default: no access
        'none'
    ) as permission_level,
    u.id as user_id,
    u.name as user_name
FROM projects p
CROSS JOIN users u
WHERE EXISTS (
    -- User has direct access
    SELECT 1 FROM project_permissions
    WHERE project_id = p.id AND entity_type = 'user' AND entity_id = u.id

    UNION

    -- Or user's group has access
    SELECT 1 FROM project_permissions pp
    JOIN user_groups ug ON pp.entity_id = ug.group_id
    WHERE pp.project_id = p.id AND pp.entity_type = 'group' AND ug.user_id = u.id
);

COMMENT ON VIEW v_user_project_access IS 'Hvilke projekter har hver bruger adgang til';

-- ========================================
-- SEED DATA: Default Modules and Permissions
-- ========================================

-- Insert default modules
INSERT INTO modules (module_key, display_name, description, sort_order, icon) VALUES
('dashboard', 'Dashboard', 'Oversigt og statistik', 1, '📊'),
('project', 'Projekter', 'Projekt management', 2, '📁'),
('building', 'Bygninger', 'Bygnings data', 3, '🏢'),
('element', 'Bygningsdele', 'Bygningselement detaljer', 4, '🔧'),
('opex', 'OPEX', 'Driftsomkostninger', 5, '💰'),
('budget', 'Budget', 'Budget linjer og total', 6, '💵'),
('red_flags', 'Red Flags', 'Advarsler og kritiske punkter', 7, '🚩'),
('report', 'Rapporter', 'Rapportgenerering', 8, '📄'),
('admin', 'Administration', 'System administration', 9, '⚙️'),
('user_management', 'Brugerstyring', 'Bruger og gruppe administration', 10, '👥')
ON CONFLICT (module_key) DO NOTHING;

-- Insert permissions for each module
INSERT INTO permissions (module_id, permission_key, display_name, description)
SELECT m.id, p.pkey, p.pname, p.pdesc FROM modules m
CROSS JOIN (VALUES
    ('view', 'Se', 'Kan se data i modulet'),
    ('create', 'Oprette', 'Kan oprette nye records'),
    ('edit', 'Redigere', 'Kan redigere eksisterende records'),
    ('delete', 'Slette', 'Kan slette records'),
    ('export', 'Eksportere', 'Kan eksportere data')
) AS p(pkey, pname, pdesc)
WHERE m.is_active = true
ON CONFLICT (module_id, permission_key) DO NOTHING;

-- Create default admin group
INSERT INTO groups (name, description, is_active) VALUES
('Administratorer', 'Fuld adgang til alle moduler', true),
('Projektledere', 'Kan administrere projekter og bygninger', true),
('Rådgivere', 'Kan se og redigere projekter de er tildelt', true),
('Læsere', 'Kun læseadgang til tildelte projekter', true)
ON CONFLICT (name) DO NOTHING;

-- Grant all permissions to Admin group
INSERT INTO group_permissions (group_id, permission_id, granted)
SELECT g.id, p.id, true
FROM groups g
CROSS JOIN permissions p
WHERE g.name = 'Administratorer'
ON CONFLICT (group_id, permission_id) DO NOTHING;

-- Grant limited permissions to Projektledere
INSERT INTO group_permissions (group_id, permission_id, granted)
SELECT g.id, p.id, true
FROM groups g
JOIN permissions p ON true
JOIN modules m ON p.module_id = m.id
WHERE g.name = 'Projektledere'
AND m.module_key IN ('dashboard', 'project', 'building', 'element', 'opex', 'budget', 'red_flags', 'report')
AND p.permission_key IN ('view', 'create', 'edit', 'export')
ON CONFLICT (group_id, permission_id) DO NOTHING;

-- Grant view/edit permissions to Rådgivere
INSERT INTO group_permissions (group_id, permission_id, granted)
SELECT g.id, p.id, true
FROM groups g
JOIN permissions p ON true
JOIN modules m ON p.module_id = m.id
WHERE g.name = 'Rådgivere'
AND m.module_key IN ('dashboard', 'project', 'building', 'element', 'opex', 'budget', 'red_flags', 'report')
AND p.permission_key IN ('view', 'edit')
ON CONFLICT (group_id, permission_id) DO NOTHING;

-- Grant only view permissions to Læsere
INSERT INTO group_permissions (group_id, permission_id, granted)
SELECT g.id, p.id, true
FROM groups g
JOIN permissions p ON true
JOIN modules m ON p.module_id = m.id
WHERE g.name = 'Læsere'
AND m.module_key IN ('dashboard', 'project', 'building', 'element', 'report')
AND p.permission_key = 'view'
ON CONFLICT (group_id, permission_id) DO NOTHING;

-- ========================================
-- MIGRATE EXISTING USERS
-- ========================================

-- Add existing admin users to Administratorer group
INSERT INTO user_groups (user_id, group_id)
SELECT u.id, g.id
FROM users u
JOIN groups g ON g.name = 'Administratorer'
WHERE u.role = 'admin'
ON CONFLICT (user_id, group_id) DO NOTHING;

-- Add regular users to Rådgivere group
INSERT INTO user_groups (user_id, group_id)
SELECT u.id, g.id
FROM users u
JOIN groups g ON g.name = 'Rådgivere'
WHERE u.role = 'user'
ON CONFLICT (user_id, group_id) DO NOTHING;

-- Add viewer users to Læsere group
INSERT INTO user_groups (user_id, group_id)
SELECT u.id, g.id
FROM users u
JOIN groups g ON g.name = 'Læsere'
WHERE u.role = 'viewer'
ON CONFLICT (user_id, group_id) DO NOTHING;

-- ========================================
-- MIGRATE PROJECT OWNERSHIP TO PERMISSIONS
-- ========================================

-- Project owners get 'owner' permission
INSERT INTO project_permissions (project_id, entity_type, entity_id, permission_level)
SELECT id, 'user', user_id, 'owner'
FROM projects
WHERE user_id IS NOT NULL
ON CONFLICT (project_id, entity_type, entity_id) DO NOTHING;

-- ========================================
-- ANALYZE for Query Optimization
-- ========================================

ANALYZE groups;
ANALYZE user_groups;
ANALYZE modules;
ANALYZE permissions;
ANALYZE group_permissions;
ANALYZE user_permissions;
ANALYZE project_permissions;
ANALYZE building_permissions;

-- ========================================
-- FUNCTIONS for Permission Checking
-- ========================================

-- Function: Check if user has module permission
CREATE OR REPLACE FUNCTION user_has_permission(
    p_user_id INTEGER,
    p_module_key VARCHAR,
    p_permission_key VARCHAR
) RETURNS BOOLEAN AS $$
BEGIN
    RETURN EXISTS (
        SELECT 1 FROM v_user_effective_permissions
        WHERE user_id = p_user_id
        AND module_key = p_module_key
        AND permission_key = p_permission_key
        AND has_permission = true
    );
END;
$$ LANGUAGE plpgsql STABLE;

COMMENT ON FUNCTION user_has_permission IS 'Check if user has specific module permission';

-- Function: Check user project access level
CREATE OR REPLACE FUNCTION user_project_access(
    p_user_id INTEGER,
    p_project_id INTEGER
) RETURNS VARCHAR AS $$
DECLARE
    v_level VARCHAR;
BEGIN
    -- Check direct user permission first
    SELECT permission_level INTO v_level
    FROM project_permissions
    WHERE project_id = p_project_id
    AND entity_type = 'user'
    AND entity_id = p_user_id;

    IF v_level IS NOT NULL THEN
        RETURN v_level;
    END IF;

    -- Check group permissions (take highest level)
    SELECT MAX(pp.permission_level) INTO v_level
    FROM project_permissions pp
    JOIN user_groups ug ON pp.entity_id = ug.group_id
    WHERE pp.project_id = p_project_id
    AND pp.entity_type = 'group'
    AND ug.user_id = p_user_id;

    RETURN COALESCE(v_level, 'none');
END;
$$ LANGUAGE plpgsql STABLE;

COMMENT ON FUNCTION user_project_access IS 'Get user access level for project (owner/editor/viewer/none)';

-- Function: Get user's accessible projects
CREATE OR REPLACE FUNCTION user_accessible_projects(p_user_id INTEGER)
RETURNS TABLE(project_id INTEGER, permission_level VARCHAR) AS $$
BEGIN
    RETURN QUERY
    SELECT DISTINCT
        p.id,
        COALESCE(
            -- Direct user permission
            (SELECT pp.permission_level FROM project_permissions pp
             WHERE pp.project_id = p.id AND pp.entity_type = 'user' AND pp.entity_id = p_user_id),
            -- Best group permission
            (SELECT MAX(pp.permission_level) FROM project_permissions pp
             JOIN user_groups ug ON pp.entity_id = ug.group_id
             WHERE pp.project_id = p.id AND pp.entity_type = 'group' AND ug.user_id = p_user_id),
            'none'
        )::VARCHAR as access_level
    FROM projects p
    WHERE EXISTS (
        SELECT 1 FROM project_permissions pp
        WHERE pp.project_id = p.id
        AND (
            (pp.entity_type = 'user' AND pp.entity_id = p_user_id)
            OR
            (pp.entity_type = 'group' AND pp.entity_id IN (
                SELECT group_id FROM user_groups WHERE user_id = p_user_id
            ))
        )
    )
    AND COALESCE(
        (SELECT pp.permission_level FROM project_permissions pp
         WHERE pp.project_id = p.id AND pp.entity_type = 'user' AND pp.entity_id = p_user_id),
        (SELECT MAX(pp.permission_level) FROM project_permissions pp
         JOIN user_groups ug ON pp.entity_id = ug.group_id
         WHERE pp.project_id = p.id AND pp.entity_type = 'group' AND ug.user_id = p_user_id)
    ) != 'none';
END;
$$ LANGUAGE plpgsql STABLE;

COMMENT ON FUNCTION user_accessible_projects IS 'Get all projects user has access to with their permission level';
