-- Migration: Create Budget System with Templates and Prices
-- Date: 2026-01-17
-- Purpose: Line-by-line budget builder for CAPEX/OPEX/Reinstatement

-- Price Catalog (templates/standard prices)
CREATE TABLE IF NOT EXISTS price_catalog (
    id SERIAL PRIMARY KEY,
    category VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50) NOT NULL DEFAULT 'stk',
    price_per_unit DECIMAL(12,2) NOT NULL,
    price_date DATE DEFAULT CURRENT_DATE,
    is_active BOOLEAN DEFAULT true,
    tags TEXT[], -- Array of tags for search
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Budget Templates (reusable budget structures)
CREATE TABLE IF NOT EXISTS budget_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100), -- 'facade', 'hvac', 'structure', etc.
    template_data JSONB NOT NULL, -- Array of budget line items
    created_by INTEGER REFERENCES users(id),
    is_public BOOLEAN DEFAULT false, -- Public templates visible to all
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Budget Lines (actual budget items for building elements)
CREATE TABLE IF NOT EXISTS budget_lines (
    id SERIAL PRIMARY KEY,
    element_id INTEGER NOT NULL REFERENCES building_elements(id) ON DELETE CASCADE,
    budget_type VARCHAR(50) NOT NULL DEFAULT 'capex', -- 'capex', 'opex', 'reinstatement'
    line_number INTEGER NOT NULL DEFAULT 0, -- Order in list

    -- Item details
    description TEXT NOT NULL,
    quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
    unit VARCHAR(50) NOT NULL DEFAULT 'stk',
    price_per_unit DECIMAL(12,2) NOT NULL DEFAULT 0,

    -- Time horizons (for phased costs)
    year_0_1 DECIMAL(12,2) DEFAULT 0, -- < 1 år
    year_1_2 DECIMAL(12,2) DEFAULT 0, -- 1-2 år
    year_3_5 DECIMAL(12,2) DEFAULT 0, -- 3-5 år
    year_5_10 DECIMAL(12,2) DEFAULT 0, -- 5-10 år
    year_10_plus DECIMAL(12,2) DEFAULT 0, -- 10+ år

    -- Reference to price catalog (optional)
    price_catalog_id INTEGER REFERENCES price_catalog(id) ON DELETE SET NULL,

    -- Metadata
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE(element_id, budget_type, line_number)
);

-- Insert default price catalog items
INSERT INTO price_catalog (category, name, description, unit, price_per_unit, tags) VALUES
    -- Facade items
    ('Facade', 'Mur reparation - mindre', 'Reparation af mindre murskader', 'm2', 850.00, ARRAY['facade', 'mur', 'repair']),
    ('Facade', 'Mur reparation - større', 'Reparation af større murskader med udtørring', 'm2', 1450.00, ARRAY['facade', 'mur', 'repair']),
    ('Facade', 'Pudsereparation', 'Udbedring af pudsede flader', 'm2', 650.00, ARRAY['facade', 'puds', 'repair']),
    ('Facade', 'Fugereparation', 'Udbedring af fuger i facade', 'm', 320.00, ARRAY['facade', 'fuger', 'repair']),
    ('Facade', 'Vinduesudskiftning', 'Komplet vinduesudskiftning inkl. montering', 'stk', 8500.00, ARRAY['facade', 'vinduer', 'replacement']),

    -- Roof items
    ('Tag', 'Tagbelægning - tegl', 'Ny tagbelægning med teglsten', 'm2', 1250.00, ARRAY['tag', 'tegl', 'replacement']),
    ('Tag', 'Tagbelægning - zink', 'Ny zinktagbelægning', 'm2', 1850.00, ARRAY['tag', 'zink', 'replacement']),
    ('Tag', 'Tagrende udskiftning', 'Ny tagrende inkl. montering', 'm', 450.00, ARRAY['tag', 'rende', 'replacement']),
    ('Tag', 'Nedløbsrør udskiftning', 'Nye nedløbsrør', 'm', 380.00, ARRAY['tag', 'nedløb', 'replacement']),

    -- HVAC items
    ('HVAC', 'Radiator udskiftning', 'Ny radiator inkl. montering', 'stk', 3500.00, ARRAY['hvac', 'radiator', 'replacement']),
    ('HVAC', 'Ventilationsanlæg', 'Mekanisk ventilationsanlæg', 'm2', 850.00, ARRAY['hvac', 'ventilation', 'installation']),
    ('Facade', 'Mur reparation - omfattende', 'Omfattende murreparation med genopføring', 'm2', 2200.00, ARRAY['facade', 'mur', 'repair']),

    -- Flooring
    ('Gulve', 'Trægulv - lakeret', 'Nyt lakeret trægulv', 'm2', 950.00, ARRAY['floor', 'træ', 'installation']),
    ('Gulve', 'Fliser - keramiske', 'Keramiske fliser inkl. lægning', 'm2', 680.00, ARRAY['floor', 'fliser', 'installation']),
    ('Gulve', 'Linoleum', 'Linoleum gulvbelægning', 'm2', 420.00, ARRAY['floor', 'linoleum', 'installation']),

    -- Electrical
    ('El', 'Elinstallation - komplet', 'Komplet elinstallation pr. m2', 'm2', 450.00, ARRAY['electrical', 'installation']),
    ('El', 'Stikkontakt', 'Installation af stikkontakt', 'stk', 580.00, ARRAY['electrical', 'outlet', 'installation']),
    ('El', 'Belysningsarmatur', 'LED belysningsarmatur inkl. montering', 'stk', 1250.00, ARRAY['electrical', 'lighting', 'installation']),

    -- Plumbing
    ('VVS', 'Rørudskiftning', 'Udskiftning af rør', 'm', 680.00, ARRAY['plumbing', 'pipes', 'replacement']),
    ('VVS', 'Toilet udskiftning', 'Nyt toilet inkl. montering', 'stk', 4200.00, ARRAY['plumbing', 'toilet', 'replacement']),
    ('VVS', 'Håndvask udskiftning', 'Ny håndvask inkl. montering', 'stk', 2800.00, ARRAY['plumbing', 'sink', 'replacement'])
ON CONFLICT DO NOTHING;

-- Insert default budget templates
INSERT INTO budget_templates (name, description, category, is_public, template_data) VALUES
    (
        'Facade renovation - standard',
        'Standard facade renovation package',
        'facade',
        true,
        '[
            {"description": "Murværksreparation", "quantity": 50, "unit": "m2", "price_per_unit": 1450},
            {"description": "Pudsereparation", "quantity": 120, "unit": "m2", "price_per_unit": 650},
            {"description": "Fugereparation", "quantity": 80, "unit": "m", "price_per_unit": 320},
            {"description": "Vinduesudskiftning", "quantity": 12, "unit": "stk", "price_per_unit": 8500}
        ]'::jsonb
    ),
    (
        'Tag renovation - komplet',
        'Complete roof renovation package',
        'roof',
        true,
        '[
            {"description": "Tagbelægning udskiftning", "quantity": 200, "unit": "m2", "price_per_unit": 1250},
            {"description": "Tagrende", "quantity": 45, "unit": "m", "price_per_unit": 450},
            {"description": "Nedløbsrør", "quantity": 20, "unit": "m", "price_per_unit": 380}
        ]'::jsonb
    ),
    (
        'VVS renovation - badeværelse',
        'Complete bathroom renovation',
        'plumbing',
        true,
        '[
            {"description": "Toilet udskiftning", "quantity": 1, "unit": "stk", "price_per_unit": 4200},
            {"description": "Håndvask udskiftning", "quantity": 1, "unit": "stk", "price_per_unit": 2800},
            {"description": "Rørudskiftning", "quantity": 15, "unit": "m", "price_per_unit": 680}
        ]'::jsonb
    )
ON CONFLICT DO NOTHING;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_price_catalog_category ON price_catalog(category);
CREATE INDEX IF NOT EXISTS idx_price_catalog_active ON price_catalog(is_active);
CREATE INDEX IF NOT EXISTS idx_price_catalog_tags ON price_catalog USING GIN(tags);
CREATE INDEX IF NOT EXISTS idx_budget_templates_category ON budget_templates(category);
CREATE INDEX IF NOT EXISTS idx_budget_templates_public ON budget_templates(is_public);
CREATE INDEX IF NOT EXISTS idx_budget_lines_element ON budget_lines(element_id);
CREATE INDEX IF NOT EXISTS idx_budget_lines_type ON budget_lines(budget_type);

-- Add trigger to update updated_at timestamp
CREATE TRIGGER update_price_catalog_updated_at BEFORE UPDATE ON price_catalog
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_budget_templates_updated_at BEFORE UPDATE ON budget_templates
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_budget_lines_updated_at BEFORE UPDATE ON budget_lines
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
