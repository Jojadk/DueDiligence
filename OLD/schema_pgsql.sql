-- PostgreSQL Schema

CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  email VARCHAR(100) NOT NULL UNIQUE,
  otp_secret VARCHAR(32) NULL,
  otp_enabled SMALLINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active SMALLINT DEFAULT 1
);

CREATE TABLE IF NOT EXISTS roles (
  id SERIAL PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS permissions (
  id SERIAL PRIMARY KEY,
  slug VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255)
);

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT NOT NULL,
  permission_id INT NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS user_roles (
  user_id INT NOT NULL,
  role_id INT NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS menu_items (
  id SERIAL PRIMARY KEY,
  parent_id INT DEFAULT NULL,
  title VARCHAR(50) NOT NULL,
  module VARCHAR(50) DEFAULT NULL,
  action VARCHAR(50) DEFAULT NULL,
  icon VARCHAR(50) DEFAULT 'fa-circle',
  sort_order INT DEFAULT 0,
  required_permission VARCHAR(50) DEFAULT NULL,
  FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS projects (
  id SERIAL PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  client_name VARCHAR(100),
  address TEXT,
  status VARCHAR(20) DEFAULT 'planning',
  start_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS price_catalogs (
  id SERIAL PRIMARY KEY,
  item_code VARCHAR(50) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL,
  unit VARCHAR(20) NOT NULL,
  unit_price DECIMAL(10, 2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'DKK'
);

CREATE TABLE IF NOT EXISTS building_elements (
  id SERIAL PRIMARY KEY,
  project_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  description TEXT,
  location VARCHAR(100),
  condition_rating INT,
  price_catalog_id INT DEFAULT NULL,
  quantity DECIMAL(10, 2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
  FOREIGN KEY (price_catalog_id) REFERENCES price_catalogs(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS element_media (
  id SERIAL PRIMARY KEY,
  element_id INT NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  media_type VARCHAR(20) DEFAULT 'image',
  canvas_json TEXT DEFAULT NULL,
  sort_order INT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (element_id) REFERENCES building_elements(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS budget_items (
  id SERIAL PRIMARY KEY,
  element_id INT NOT NULL,
  price_catalog_id INT DEFAULT NULL,
  description VARCHAR(255) NOT NULL,
  quantity DECIMAL(10, 2) DEFAULT 0,
  unit VARCHAR(20) DEFAULT 'stk',
  unit_price DECIMAL(10, 2) DEFAULT 0,
  amount_0_1 DECIMAL(10, 2) DEFAULT 0,
  amount_1_2 DECIMAL(10, 2) DEFAULT 0,
  amount_3_5 DECIMAL(10, 2) DEFAULT 0,
  amount_5_10 DECIMAL(10, 2) DEFAULT 0,
  total_calculated DECIMAL(10, 2) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (element_id) REFERENCES building_elements(id) ON DELETE CASCADE,
  FOREIGN KEY (price_catalog_id) REFERENCES price_catalogs(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS input_locks (
  id SERIAL PRIMARY KEY,
  table_name VARCHAR(50) NOT NULL,
  row_id INT NOT NULL,
  field_name VARCHAR(50) DEFAULT NULL,
  user_id INT NOT NULL,
  locked_at TIMESTAMP NOT NULL,
  ip_address VARCHAR(45),
  UNIQUE (table_name, row_id, field_name),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS system_settings (
  id SERIAL PRIMARY KEY,
  setting_key VARCHAR(50) NOT NULL UNIQUE,
  setting_value TEXT
);

CREATE TABLE IF NOT EXISTS custom_field_definitions (
  id SERIAL PRIMARY KEY,
  scope VARCHAR(20) NOT NULL DEFAULT 'global',
  project_id INT DEFAULT NULL,
  entity_type VARCHAR(20) NOT NULL,
  label VARCHAR(100) NOT NULL,
  field_type VARCHAR(20) NOT NULL,
  options TEXT DEFAULT NULL,
  default_value TEXT DEFAULT NULL,
  is_required SMALLINT DEFAULT 0,
  sort_order INT DEFAULT 0,
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS custom_field_values (
  id SERIAL PRIMARY KEY,
  definition_id INT NOT NULL,
  entity_id INT NOT NULL,
  value TEXT,
  FOREIGN KEY (definition_id) REFERENCES custom_field_definitions(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS system_constants (
  id SERIAL PRIMARY KEY,
  project_id INT DEFAULT NULL,
  key_name VARCHAR(50) NOT NULL,
  value TEXT NOT NULL,
  description VARCHAR(255),
  UNIQUE (project_id, key_name),
  FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- Seed Data
INSERT INTO users (username, password_hash, email) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');

INSERT INTO roles (name, description) VALUES ('Administrator', 'Full system access'), ('Project Manager', 'Manage projects and elements'), ('Viewer', 'Read-only access');

INSERT INTO permissions (slug, description) VALUES 
('admin_access', 'Access to admin panel'),
('project_create', 'Create new projects'),
('project_edit', 'Edit projects'),
('element_edit', 'Edit building elements'),
('report_generate', 'Generate reports');

INSERT INTO user_roles (user_id, role_id) VALUES (1, 1);

INSERT INTO menu_items (title, module, action, icon, sort_order, required_permission) VALUES 
('Dashboard', 'Dashboard', 'index', 'fa-tachometer-alt', 1, NULL),
('Projects', 'Project', 'index', 'fa-building', 2, 'project_create'),
('Elements', 'BuildingElement', 'index', 'fa-cubes', 3, 'element_edit'),
('Reports', 'Report', 'index', 'fa-file-pdf', 4, 'report_generate'),
('Administration', 'Admin', 'index', 'fa-cogs', 99, 'admin_access');

INSERT INTO menu_items (parent_id, title, module, action, icon, sort_order) 
SELECT id, 'Users', 'Admin', 'users', 'fa-users', 1 FROM menu_items WHERE title = 'Administration';

INSERT INTO system_constants (key_name, value, description) VALUES 
('COMPANY_NAME', 'Construction Co.', 'Global Company Name'),
('VAT_RATE', '25', 'VAT Percentage');
