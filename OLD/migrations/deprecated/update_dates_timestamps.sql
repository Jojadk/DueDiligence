-- Change Project Years to DATE
ALTER TABLE projects 
    ALTER COLUMN construction_year TYPE DATE USING TO_DATE(construction_year::text, 'YYYY'),
    ALTER COLUMN renovation_year TYPE DATE USING TO_DATE(renovation_year::text, 'YYYY');

-- Add Timestamps to all tables missing them
DO $$
DECLARE
    tbl text;
    tables text[] := ARRAY['role_permissions', 'permissions', 'user_roles', 'menu_items', 'roles', 'price_catalogs', 'element_media', 'input_locks', 'system_settings', 'project_members', 'custom_field_values', 'system_constants', 'budget_items'];
BEGIN
    FOREACH tbl IN ARRAY tables LOOP
        EXECUTE format('ALTER TABLE %I ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP', tbl);
        EXECUTE format('ALTER TABLE %I ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP', tbl);
    END LOOP;
END $$;
