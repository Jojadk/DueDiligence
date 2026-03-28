-- ========================================
-- Dynamic Permission System Migration V2
-- Med modul-præfix struktur
-- ========================================
-- Tabeller grupperet efter modul:
-- - permission_* : Rettigheds system
-- - project_* : Projekt relateret (permissions)
-- ========================================

-- ========================================
-- PERMISSION MODUL: Grupper og Rettigheder
-- ========================================

-- Grupper/Teams
CREATE TABLE IF NOT EXISTS permission_groups (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    updated_at TIMESTAMP
);

COMMENT ON TABLE permission_groups IS 'Grupper/teams - brugere tilhører en eller flere grupper';

-- Bruger → Gruppe relation
CREATE TABLE IF NOT EXISTS permission_user_groups (
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    group_id INTEGER REFERENCES permission_groups(id) ON DELETE CASCADE,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_by INTEGER REFERENCES users(id),
    PRIMARY KEY (user_id, group_id)
);

COMMENT ON TABLE permission_user_groups IS 'Brugere tilhører grupper - many-to-many';

-- System moduler
CREATE TABLE IF NOT EXISTS permission_modules (
    id SERIAL PRIMARY KEY,
    module_key VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    icon VARCHAR(50),
    sort_order INTEGER DEFAULT 0,
    is_active BOOLEAN DEFAULT true,
    parent_module_id INTEGER REFERENCES permission_modules(id),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE permission_modules IS 'System moduler - definerer funktionalitet';

-- Rettigheder indenfor moduler
CREATE TABLE IF NOT EXISTS permission_permissions (
    id SERIAL PRIMARY KEY,
    module_id INTEGER REFERENCES permission_modules(id) ON DELETE CASCADE,
    permission_key VARCHAR(100) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(module_id, permission_key)
);

COMMENT ON TABLE permission_permissions IS 'Rettigheder per modul (view, create, edit, delete, export)';

-- Gruppe rettigheder
CREATE TABLE IF NOT EXISTS permission_group_permissions (
    id SERIAL PRIMARY KEY,
    group_id INTEGER REFERENCES permission_groups(id) ON DELETE CASCADE,
    permission_id INTEGER REFERENCES permission_permissions(id) ON DELETE CASCADE,
    granted BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    UNIQUE(group_id, permission_id)
);

COMMENT ON TABLE permission_group_permissions IS 'Gruppers rettigheder - arves af medlemmer';

-- Bruger rettigheds overrides
CREATE TABLE IF NOT EXISTS permission_user_permissions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id) ON DELETE CASCADE,
    permission_id INTEGER REFERENCES permission_permissions(id) ON DELETE CASCADE,
    granted BOOLEAN,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES users(id),
    UNIQUE(user_id, permission_id)
);

COMMENT ON TABLE permission_user_permissions IS 'Bruger-specifikke rettigheds overrides';

-- Audit log for rettigheder
CREATE TABLE IF NOT EXISTS permission_audit_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INTEGER,
    permission_id INTEGER REFERENCES permission_permissions(id),
    granted BOOLEAN,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE permission_audit_log IS 'Audit trail for rettigheds ændringer';

-- ========================================
-- PROJECT MODUL: Projekt-niveau rettigheder
-- ========================================

-- Projekt adgang (bruger eller gruppe niveau)
CREATE TABLE IF NOT EXISTS project_permissions (
    id SERIAL PRIMARY KEY,
    project_id INTEGER REFERENCES projects(id) ON DELETE CASCADE,
    entity_type VARCHAR(20) NOT NULL CHECK (entity_type IN ('user', 'group')),
    entity_id INTEGER NOT NULL,
    permission_level VARCHAR(20) NOT NULL CHECK (permission_level IN ('owner', 'editor', 'viewer', 'none')),
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INTEGER REFERENCES users(id),
    UNIQUE(project_id, entity_type, entity_id)
);

COMMENT ON TABLE project_permissions IS 'Projekt-niveau adgang (owner/editor/viewer)';

-- Bygnings adgang (granulær)
CREATE TABLE IF NOT EXISTS project_building_permissions (
    id SERIAL PRIMARY KEY,
    building_id INTEGER REFERENCES buildings(id) ON DELETE CASCADE,
    entity_type VARCHAR(20) NOT NULL CHECK (entity_type IN ('user', 'group')),
    entity_id INTEGER NOT NULL,
    permission_level VARCHAR(20) NOT NULL CHECK (permission_level IN ('editor', 'viewer', 'none')),
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    granted_by INTEGER REFERENCES users(id),
    UNIQUE(building_id, entity_type, entity_id)
);

COMMENT ON TABLE project_building_permissions IS 'Bygnings-niveau adgang (granulær kontrol)';

-- ========================================
-- INDEXES for Performance
-- ========================================

-- Permission group indexes
CREATE INDEX IF NOT EXISTS idx_perm_user_groups_user ON permission_user_groups(user_id);
CREATE INDEX IF NOT EXISTS idx_perm_user_groups_group ON permission_user_groups(group_id);

-- Module indexes
CREATE INDEX IF NOT EXISTS idx_perm_modules_active ON permission_modules(is_active) WHERE is_active = true;
CREATE INDEX IF NOT EXISTS idx_perm_modules_parent ON permission_modules(parent_module_id);
CREATE INDEX IF NOT EXISTS idx_perm_modules_key ON permission_modules(module_key);

-- Permission indexes
CREATE INDEX IF NOT EXISTS idx_perm_permissions_module ON permission_permissions(module_id);
CREATE INDEX IF NOT EXISTS idx_perm_permissions_active ON permission_permissions(is_active) WHERE is_active = true;
CREATE INDEX IF NOT EXISTS idx_perm_permissions_key ON permission_permissions(module_id, permission_key);

-- Group permission indexes (critical)
CREATE INDEX IF NOT EXISTS idx_perm_group_perms_group ON permission_group_permissions(group_id);
CREATE INDEX IF NOT EXISTS idx_perm_group_perms_perm ON permission_group_permissions(permission_id);
CREATE INDEX IF NOT EXISTS idx_perm_group_perms_granted ON permission_group_permissions(granted) WHERE granted = true;
CREATE INDEX IF NOT EXISTS idx_perm_group_perms_composite ON permission_group_permissions(group_id, permission_id) WHERE granted = true;

-- User permission indexes
CREATE INDEX IF NOT EXISTS idx_perm_user_perms_user ON permission_user_permissions(user_id);
CREATE INDEX IF NOT EXISTS idx_perm_user_perms_perm ON permission_user_permissions(permission_id);
CREATE INDEX IF NOT EXISTS idx_perm_user_perms_composite ON permission_user_permissions(user_id, permission_id);

-- Project permission indexes (critical for authorization)
CREATE INDEX IF NOT EXISTS idx_proj_perms_project ON project_permissions(project_id);
CREATE INDEX IF NOT EXISTS idx_proj_perms_entity ON project_permissions(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_proj_perms_user ON project_permissions(entity_id) WHERE entity_type = 'user';
CREATE INDEX IF NOT EXISTS idx_proj_perms_group ON project_permissions(entity_id) WHERE entity_type = 'group';
CREATE INDEX IF NOT EXISTS idx_proj_perms_composite ON project_permissions(project_id, entity_type, entity_id);

-- Building permission indexes
CREATE INDEX IF NOT EXISTS idx_build_perms_building ON project_building_permissions(building_id);
CREATE INDEX IF NOT EXISTS idx_build_perms_entity ON project_building_permissions(entity_type, entity_id);

-- Audit log indexes
CREATE INDEX IF NOT EXISTS idx_perm_audit_user ON permission_audit_log(user_id);
CREATE INDEX IF NOT EXISTS idx_perm_audit_created ON permission_audit_log(created_at);
CREATE INDEX IF NOT EXISTS idx_perm_audit_action ON permission_audit_log(action);

-- ========================================
-- VIEWS for Fast Permission Lookup
-- ========================================

-- Brugers effektive rettigheder (gruppe + user overrides)
CREATE OR REPLACE VIEW v_user_effective_permissions AS
WITH user_group_permissions AS (
    -- Rettigheder fra grupper
    SELECT DISTINCT
        ug.user_id,
        gp.permission_id,
        gp.granted,
        'group' as source,
        g.name as source_name
    FROM permission_user_groups ug
    JOIN permission_group_permissions gp ON ug.group_id = gp.group_id
    JOIN permission_groups g ON ug.group_id = g.id
    WHERE g.is_active = true AND gp.granted = true
),
combined_permissions AS (
    -- Start med gruppe rettigheder
    SELECT * FROM user_group_permissions

    UNION ALL

    -- Tilføj user overrides
    SELECT
        up.user_id,
        up.permission_id,
        up.granted,
        'user' as source,
        'User Override' as source_name
    FROM permission_user_permissions up
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
    -- User overrides vinder
    BOOL_OR(cp.granted) as has_permission,
    STRING_AGG(DISTINCT cp.source_name, ', ') as granted_by
FROM combined_permissions cp
JOIN permission_permissions p ON cp.permission_id = p.id
JOIN permission_modules m ON p.module_id = m.id
WHERE p.is_active = true AND m.is_active = true
GROUP BY cp.user_id, cp.permission_id, p.module_id, m.module_key,
         p.permission_key, m.display_name, p.display_name;

-- Brugers projekt adgang
CREATE OR REPLACE VIEW v_user_project_access AS
SELECT DISTINCT
    p.id as project_id,
    p.name as project_name,
    COALESCE(
        -- Direkte bruger permission
        (SELECT permission_level FROM project_permissions
         WHERE project_id = p.id AND entity_type = 'user' AND entity_id = u.id),
        -- Bedste gruppe permission
        (SELECT MAX(permission_level) FROM project_permissions pp
         JOIN permission_user_groups ug ON pp.entity_id = ug.group_id
         WHERE pp.project_id = p.id AND pp.entity_type = 'group' AND ug.user_id = u.id),
        'none'
    ) as permission_level,
    u.id as user_id,
    u.name as user_name
FROM projects p
CROSS JOIN users u
WHERE EXISTS (
    SELECT 1 FROM project_permissions
    WHERE project_id = p.id AND entity_type = 'user' AND entity_id = u.id

    UNION

    SELECT 1 FROM project_permissions pp
    JOIN permission_user_groups ug ON pp.entity_id = ug.group_id
    WHERE pp.project_id = p.id AND pp.entity_type = 'group' AND ug.user_id = u.id
);

-- ========================================
-- DATABASE FUNCTIONS
-- ========================================

-- Check om bruger har modul permission
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

-- Hent brugers projekt adgang niveau
CREATE OR REPLACE FUNCTION user_project_access(
    p_user_id INTEGER,
    p_project_id INTEGER
) RETURNS VARCHAR AS $$
DECLARE
    v_level VARCHAR;
BEGIN
    -- Check direkte bruger permission
    SELECT permission_level INTO v_level
    FROM project_permissions
    WHERE project_id = p_project_id
    AND entity_type = 'user'
    AND entity_id = p_user_id;

    IF v_level IS NOT NULL THEN
        RETURN v_level;
    END IF;

    -- Check gruppe permissions (tag højeste)
    SELECT MAX(pp.permission_level) INTO v_level
    FROM project_permissions pp
    JOIN permission_user_groups ug ON pp.entity_id = ug.group_id
    WHERE pp.project_id = p_project_id
    AND pp.entity_type = 'group'
    AND ug.user_id = p_user_id;

    RETURN COALESCE(v_level, 'none');
END;
$$ LANGUAGE plpgsql STABLE;

-- Hent brugers tilgængelige projekter
CREATE OR REPLACE FUNCTION user_accessible_projects(p_user_id INTEGER)
RETURNS TABLE(project_id INTEGER, permission_level VARCHAR) AS $$
BEGIN
    RETURN QUERY
    SELECT DISTINCT
        p.id,
        COALESCE(
            (SELECT pp.permission_level FROM project_permissions pp
             WHERE pp.project_id = p.id AND pp.entity_type = 'user' AND pp.entity_id = p_user_id),
            (SELECT MAX(pp.permission_level) FROM project_permissions pp
             JOIN permission_user_groups ug ON pp.entity_id = ug.group_id
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
                SELECT group_id FROM permission_user_groups WHERE user_id = p_user_id
            ))
        )
    )
    AND COALESCE(
        (SELECT pp.permission_level FROM project_permissions pp
         WHERE pp.project_id = p.id AND pp.entity_type = 'user' AND pp.entity_id = p_user_id),
        (SELECT MAX(pp.permission_level) FROM project_permissions pp
         JOIN permission_user_groups ug ON pp.entity_id = ug.group_id
         WHERE pp.project_id = p.id AND pp.entity_type = 'group' AND ug.user_id = p_user_id)
    ) != 'none';
END;
$$ LANGUAGE plpgsql STABLE;

-- ========================================
-- SEED DATA
-- ========================================

-- Insert standard moduler
INSERT INTO permission_modules (module_key, display_name, description, sort_order, icon) VALUES
('dashboard', 'Dashboard', 'Oversigt og statistik', 1, '📊'),
('project', 'Projekter', 'Projekt management', 2, '📁'),
('building', 'Bygninger', 'Bygnings data', 3, '🏢'),
('element', 'Bygningsdele', 'Bygningselement detaljer', 4, '🔧'),
('opex', 'OPEX', 'Driftsomkostninger', 5, '💰'),
('budget', 'Budget', 'Budget linjer og total', 6, '💵'),
('red_flags', 'Red Flags', 'Advarsler og kritiske punkter', 7, '🚩'),
('report', 'Rapporter', 'Rapportgenerering', 8, '📄'),
('admin', 'Administration', 'System administration', 9, '⚙️'),
('user_management', 'Brugerstyring', 'Bruger og gruppe admin', 10, '👥')
ON CONFLICT (module_key) DO NOTHING;

-- Insert standard rettigheder for hvert modul
INSERT INTO permission_permissions (module_id, permission_key, display_name, description)
SELECT m.id, p.pkey, p.pname, p.pdesc FROM permission_modules m
CROSS JOIN (VALUES
    ('view', 'Se', 'Kan se data i modulet'),
    ('create', 'Oprette', 'Kan oprette nye records'),
    ('edit', 'Redigere', 'Kan redigere eksisterende records'),
    ('delete', 'Slette', 'Kan slette records'),
    ('export', 'Eksportere', 'Kan eksportere data')
) AS p(pkey, pname, pdesc)
WHERE m.is_active = true
ON CONFLICT (module_id, permission_key) DO NOTHING;

-- Opret standard grupper
INSERT INTO permission_groups (name, description, is_active) VALUES
('Administratorer', 'Fuld adgang til alle moduler', true),
('Projektledere', 'Kan administrere projekter og bygninger', true),
('Rådgivere', 'Kan se og redigere tildelte projekter', true),
('Læsere', 'Kun læseadgang til tildelte projekter', true)
ON CONFLICT (name) DO NOTHING;

-- Giv alle rettigheder til Administratorer
INSERT INTO permission_group_permissions (group_id, permission_id, granted)
SELECT g.id, p.id, true
FROM permission_groups g
CROSS JOIN permission_permissions p
WHERE g.name = 'Administratorer'
ON CONFLICT (group_id, permission_id) DO NOTHING;

-- Projektledere rettigheder
INSERT INTO permission_group_permissions (group_id, permission_id, granted)
SELECT g.id, p.id, true
FROM permission_groups g
JOIN permission_permissions p ON true
JOIN permission_modules m ON p.module_id = m.id
WHERE g.name = 'Projektledere'
AND m.module_key IN ('dashboard', 'project', 'building', 'element', 'opex', 'budget', 'red_flags', 'report')
AND p.permission_key IN ('view', 'create', 'edit', 'export')
ON CONFLICT (group_id, permission_id) DO NOTHING;

-- Rådgivere rettigheder
INSERT INTO permission_group_permissions (group_id, permission_id, granted)
SELECT g.id, p.id, true
FROM permission_groups g
JOIN permission_permissions p ON true
JOIN permission_modules m ON p.module_id = m.id
WHERE g.name = 'Rådgivere'
AND m.module_key IN ('dashboard', 'project', 'building', 'element', 'opex', 'budget', 'red_flags', 'report')
AND p.permission_key IN ('view', 'edit')
ON CONFLICT (group_id, permission_id) DO NOTHING;

-- Læsere rettigheder
INSERT INTO permission_group_permissions (group_id, permission_id, granted)
SELECT g.id, p.id, true
FROM permission_groups g
JOIN permission_permissions p ON true
JOIN permission_modules m ON p.module_id = m.id
WHERE g.name = 'Læsere'
AND m.module_key IN ('dashboard', 'project', 'building', 'element', 'report')
AND p.permission_key = 'view'
ON CONFLICT (group_id, permission_id) DO NOTHING;

-- ========================================
-- MIGRER EKSISTERENDE DATA
-- ========================================

-- Migrer brugere til grupper baseret på rolle
INSERT INTO permission_user_groups (user_id, group_id)
SELECT u.id, g.id
FROM users u
JOIN permission_groups g ON
    CASE
        WHEN u.role = 'admin' THEN g.name = 'Administratorer'
        WHEN u.role = 'user' THEN g.name = 'Rådgivere'
        WHEN u.role = 'viewer' THEN g.name = 'Læsere'
        ELSE false
    END
ON CONFLICT (user_id, group_id) DO NOTHING;

-- Migrer projekt ejerskab til permissions
INSERT INTO project_permissions (project_id, entity_type, entity_id, permission_level)
SELECT id, 'user', user_id, 'owner'
FROM projects
WHERE user_id IS NOT NULL
ON CONFLICT (project_id, entity_type, entity_id) DO NOTHING;

-- ========================================
-- ANALYZE
-- ========================================

ANALYZE permission_groups;
ANALYZE permission_user_groups;
ANALYZE permission_modules;
ANALYZE permission_permissions;
ANALYZE permission_group_permissions;
ANALYZE permission_user_permissions;
ANALYZE project_permissions;
ANALYZE project_building_permissions;
ANALYZE permission_audit_log;
