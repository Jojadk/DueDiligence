CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  PRIMARY KEY (`role_id`, `permission_id`),
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` INT NOT NULL,
  `role_id` INT NOT NULL,
  PRIMARY KEY (`user_id`, `role_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `menu_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT DEFAULT NULL,
  `title` VARCHAR(50) NOT NULL,
  `module` VARCHAR(50) DEFAULT NULL,
  `action` VARCHAR(50) DEFAULT NULL,
  `icon` VARCHAR(50) DEFAULT 'fa-circle',
  `sort_order` INT DEFAULT 0,
  `required_permission` VARCHAR(50) DEFAULT NULL,
  FOREIGN KEY (`parent_id`) REFERENCES `menu_items`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `client_name` VARCHAR(100),
  `address` TEXT,
  `status` ENUM('planning', 'active', 'completed', 'archived') DEFAULT 'planning',
  `start_date` DATE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `price_catalogs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `item_code` VARCHAR(50) NOT NULL UNIQUE,
  `description` VARCHAR(255) NOT NULL,
  `unit` VARCHAR(20) NOT NULL,
  `unit_price` DECIMAL(10, 2) NOT NULL,
  `currency` VARCHAR(3) DEFAULT 'DKK'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `building_elements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `location` VARCHAR(100),
  `condition_rating` INT CHECK (condition_rating BETWEEN 1 AND 5),
  `price_catalog_id` INT DEFAULT NULL,
  `quantity` DECIMAL(10, 2) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`price_catalog_id`) REFERENCES `price_catalogs`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `element_media` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `element_id` INT NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `media_type` ENUM('image', 'canvas', 'video', 'document') DEFAULT 'image',
  `canvas_json` JSON DEFAULT NULL,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`element_id`) REFERENCES `building_elements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `input_locks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `table_name` VARCHAR(50) NOT NULL,
  `row_id` INT NOT NULL,
  `field_name` VARCHAR(50) DEFAULT NULL,
  `user_id` INT NOT NULL,
  `locked_at` DATETIME NOT NULL,
  `ip_address` VARCHAR(45),
  UNIQUE KEY `unique_lock` (`table_name`, `row_id`, `field_name`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(50) NOT NULL UNIQUE,
  `setting_value` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Data --

-- Default Admin User (password: admin123)
INSERT INTO `users` (`username`, `password_hash`, `email`) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');

INSERT INTO `roles` (`name`, `description`) VALUES ('Administrator', 'Full system access'), ('Project Manager', 'Manage projects and elements'), ('Viewer', 'Read-only access');

INSERT INTO `permissions` (`slug`, `description`) VALUES 
('admin_access', 'Access to admin panel'),
('project_create', 'Create new projects'),
('project_edit', 'Edit projects'),
('element_edit', 'Edit building elements'),
('report_generate', 'Generate reports');

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES (1, 1);

-- Default Menu Structure
INSERT INTO `menu_items` (`title`, `module`, `action`, `icon`, `sort_order`, `required_permission`) VALUES 
('Dashboard', 'Dashboard', 'index', 'fa-tachometer-alt', 1, NULL),
('Projects', 'Project', 'index', 'fa-building', 2, 'project_create'),
('Elements', 'BuildingElement', 'index', 'fa-cubes', 3, 'element_edit'),
('Reports', 'Report', 'index', 'fa-file-pdf', 4, 'report_generate'),
('Administration', 'Admin', 'index', 'fa-cogs', 99, 'admin_access');

INSERT INTO `menu_items` (`parent_id`, `title`, `module`, `action`, `icon`, `sort_order`) 
SELECT id, 'Users', 'Admin', 'users', 'fa-users', 1 FROM `menu_items` WHERE title = 'Administration';

-- OTP Updates for Users
ALTER TABLE `users` ADD COLUMN `otp_secret` VARCHAR(32) NULL AFTER `password_hash`;
ALTER TABLE `users` ADD COLUMN `otp_enabled` TINYINT(1) DEFAULT 0 AFTER `otp_secret`;

-- Custom Fields System
CREATE TABLE IF NOT EXISTS `custom_field_definitions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `scope` ENUM('global', 'project') NOT NULL DEFAULT 'global',
  `project_id` INT DEFAULT NULL, -- If scope is project
  `entity_type` ENUM('project', 'building_element') NOT NULL,
  `label` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text', 'number', 'date', 'dropdown', 'checkbox') NOT NULL,
  `options` TEXT DEFAULT NULL, -- JSON array for dropdown options
  `default_value` TEXT DEFAULT NULL,
  `is_required` TINYINT(1) DEFAULT 0,
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `custom_field_values` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `definition_id` INT NOT NULL,
  `entity_id` INT NOT NULL, -- ID of the project or building_element
  `value` TEXT,
  FOREIGN KEY (`definition_id`) REFERENCES `custom_field_definitions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Constants System (Global and Project)
CREATE TABLE IF NOT EXISTS `system_constants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT DEFAULT NULL, -- NULL = Global, Value = Project Specific
  `key_name` VARCHAR(50) NOT NULL,
  `value` TEXT NOT NULL,
  `description` VARCHAR(255),
  UNIQUE KEY `unique_constant` (`project_id`, `key_name`),
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Data for Constants
INSERT INTO `system_constants` (`key_name`, `value`, `description`) VALUES 
('COMPANY_NAME', 'Construction Co.', 'Global Company Name'),
('VAT_RATE', '25', 'VAT Percentage');

