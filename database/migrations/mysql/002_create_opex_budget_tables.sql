-- MySQL OPEX and Budget Tables Migration

-- OPEX Categories table
CREATE TABLE IF NOT EXISTS opex_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_type VARCHAR(50) NOT NULL,
    rate_per_sqm DECIMAL(10,2) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_type (category_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Building OPEX assignments
CREATE TABLE IF NOT EXISTS building_opex (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    opex_category_id INT NOT NULL,
    custom_rate_per_sqm DECIMAL(10,2) NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE,
    FOREIGN KEY (opex_category_id) REFERENCES opex_categories(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_building_opex (building_id, opex_category_id),
    INDEX idx_building (building_id),
    INDEX idx_category (opex_category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- TCO Configuration table
CREATE TABLE IF NOT EXISTS tco_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value DECIMAL(10,4) NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default TCO config values
INSERT INTO tco_config (config_key, config_value, description) VALUES
    ('lifecycle_years', 30.0000, 'Lifecycle period for TCO calculation in years'),
    ('discount_rate', 0.0300, 'Discount rate for NPV calculation'),
    ('inflation_rate', 0.0200, 'Expected inflation rate'),
    ('capex_contingency', 0.1000, 'CAPEX contingency percentage (10%)'),
    ('opex_escalation', 0.0250, 'OPEX yearly escalation rate (2.5%)')
ON DUPLICATE KEY UPDATE config_value=VALUES(config_value);

-- Price Catalog Categories
CREATE TABLE IF NOT EXISTS price_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Price Catalog Items
CREATE TABLE IF NOT EXISTS price_catalog_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    item_code VARCHAR(50),
    name VARCHAR(255) NOT NULL,
    description TEXT,
    unit VARCHAR(50),
    unit_price DECIMAL(12,2) NOT NULL,
    tags JSON,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES price_categories(id) ON DELETE RESTRICT,
    INDEX idx_category (category_id),
    INDEX idx_item_code (item_code),
    INDEX idx_name (name),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budget Templates
CREATE TABLE IF NOT EXISTS budget_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    template_data LONGTEXT,
    is_public TINYINT(1) DEFAULT 0,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_category (category),
    INDEX idx_is_public (is_public),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Budget Lines
CREATE TABLE IF NOT EXISTS budget_lines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    element_id INT NOT NULL,
    budget_type VARCHAR(50) NOT NULL,
    line_number INT NOT NULL,
    description TEXT,
    quantity DECIMAL(10,2) DEFAULT 0,
    unit VARCHAR(50),
    price_per_unit DECIMAL(12,2) DEFAULT 0,
    year_0_1 DECIMAL(12,2) DEFAULT 0,
    year_1_2 DECIMAL(12,2) DEFAULT 0,
    year_3_5 DECIMAL(12,2) DEFAULT 0,
    year_5_10 DECIMAL(12,2) DEFAULT 0,
    year_10_plus DECIMAL(12,2) DEFAULT 0,
    price_catalog_id INT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (element_id) REFERENCES building_elements(id) ON DELETE CASCADE,
    FOREIGN KEY (price_catalog_id) REFERENCES price_catalog_items(id) ON DELETE SET NULL,
    INDEX idx_element (element_id),
    INDEX idx_budget_type (budget_type),
    INDEX idx_line_number (element_id, budget_type, line_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
