<?php
/**
 * Migration: Add Customers Table
 * Creates customers table and links to projects
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "Running migration: Add Customers Table\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Create customers table
    echo "1. Creating customers table...\n";
    $db->query("CREATE TABLE IF NOT EXISTS customers (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phone VARCHAR(50),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL
    )");
    $db->execute();
    echo "   ✅ Customers table created\n\n";

    // Add client_id to projects
    echo "2. Adding client_id to projects...\n";
    $db->query("ALTER TABLE projects ADD COLUMN IF NOT EXISTS client_id INT NULL");
    $db->execute();
    echo "   ✅ Column added\n\n";

    // Create index
    echo "3. Creating index on client_id...\n";
    $db->query("CREATE INDEX IF NOT EXISTS idx_projects_client_id ON projects(client_id)");
    $db->execute();
    echo "   ✅ Index created\n";

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
