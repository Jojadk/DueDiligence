-- Migration: Create OPEX tables
-- Date: 2026-01-17
-- Purpose: Create separate OPEX module with experience values based on square meters

-- OPEX Categories table (experience values per m² per year)
CREATE TABLE IF NOT EXISTS opex_categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    rate_per_sqm DECIMAL(10,2) NOT NULL DEFAULT 0, -- kr per m² per year
    category_type VARCHAR(50) NOT NULL, -- 'maintenance', 'energy', 'insurance', etc.
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- OPEX assignments to buildings
CREATE TABLE IF NOT EXISTS building_opex (
    id SERIAL PRIMARY KEY,
    building_id INTEGER NOT NULL REFERENCES buildings(id) ON DELETE CASCADE,
    opex_category_id INTEGER NOT NULL REFERENCES opex_categories(id) ON DELETE CASCADE,
    custom_rate_per_sqm DECIMAL(10,2), -- Override default rate if needed
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(building_id, opex_category_id)
);

-- TCO Configuration constants
CREATE TABLE IF NOT EXISTS tco_config (
    id SERIAL PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value DECIMAL(10,4) NOT NULL,
    description TEXT,
    unit VARCHAR(50), -- 'years', 'percent', 'currency'
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default OPEX categories
INSERT INTO opex_categories (name, description, rate_per_sqm, category_type) VALUES
    ('Rengøring', 'Daglig rengøring og vedligeholdelse', 75.00, 'maintenance'),
    ('Energi', 'El, varme og ventilation', 120.00, 'energy'),
    ('Vand og afløb', 'Vand, afløb og kloakering', 25.00, 'utilities'),
    ('Forsikring', 'Bygningsforsikring', 15.00, 'insurance'),
    ('Ejendomsskat', 'Kommunal ejendomsskat', 30.00, 'tax'),
    ('Sikkerhed og overvågning', 'Alarmanlæg og adgangskontrol', 10.00, 'security'),
    ('Affaldshåndtering', 'Affald og genanvendelse', 20.00, 'waste'),
    ('Mindre reparationer', 'Løbende vedligehold og reparationer', 40.00, 'maintenance'),
    ('Administration', 'Ejendomsadministration', 25.00, 'admin'),
    ('Udvendig vedligehold', 'Facader, tag, vinduer', 50.00, 'maintenance')
ON CONFLICT DO NOTHING;

-- Insert default TCO configuration
INSERT INTO tco_config (config_key, config_value, description, unit) VALUES
    ('lifecycle_years', 30.0000, 'Standard beregningsperiode for TCO', 'years'),
    ('discount_rate', 0.0300, 'Diskonteringsrente (3%)', 'percent'),
    ('inflation_rate', 0.0200, 'Forventet inflation (2%)', 'percent'),
    ('capex_contingency', 0.1000, 'Sikkerhedsmargin for CAPEX (10%)', 'percent'),
    ('opex_escalation', 0.0250, 'Årlig OPEX stigning (2.5%)', 'percent')
ON CONFLICT (config_key) DO NOTHING;

-- Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_building_opex_building ON building_opex(building_id);
CREATE INDEX IF NOT EXISTS idx_building_opex_category ON building_opex(opex_category_id);
CREATE INDEX IF NOT EXISTS idx_opex_categories_active ON opex_categories(is_active);
CREATE INDEX IF NOT EXISTS idx_opex_categories_type ON opex_categories(category_type);

-- Add trigger to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_opex_categories_updated_at BEFORE UPDATE ON opex_categories
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_building_opex_updated_at BEFORE UPDATE ON building_opex
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_tco_config_updated_at BEFORE UPDATE ON tco_config
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
