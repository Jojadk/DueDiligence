<?php
/**
 * Migration: Add Building Elements Hierarchy
 * Adds parent_id and sort_order columns to building_elements
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "Running migration: Add Building Elements Hierarchy\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Add parent_id and sort_order columns
    echo "1. Adding parent_id and sort_order columns...\n";
    $db->query("ALTER TABLE building_elements 
                ADD COLUMN IF NOT EXISTS parent_id INT NULL,
                ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0");
    $db->execute();
    echo "   ✅ Columns added\n\n";

    // Create indexes
    echo "2. Creating indexes...\n";
    $db->query("CREATE INDEX IF NOT EXISTS idx_building_elements_parent_id ON building_elements(parent_id)");
    $db->execute();
    echo "   ✅ Index on parent_id created\n";

    $db->query("CREATE INDEX IF NOT EXISTS idx_building_elements_project_id ON building_elements(project_id)");
    $db->execute();
    echo "   ✅ Index on project_id created\n";

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
