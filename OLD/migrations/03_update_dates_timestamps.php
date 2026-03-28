<?php
/**
 * Migration: Update Dates and Timestamps
 * Converts year columns to DATE and adds timestamps to tables
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "Running migration: Update Dates and Timestamps\n";
echo str_repeat("=", 60) . "\n\n";

try {
    // Change Project Years to DATE
    echo "1. Converting year columns to DATE type...\n";
    try {
        $db->query("ALTER TABLE projects 
                    ALTER COLUMN construction_year TYPE DATE USING TO_DATE(construction_year::text, 'YYYY'),
                    ALTER COLUMN renovation_year TYPE DATE USING TO_DATE(renovation_year::text, 'YYYY')");
        $db->execute();
        echo "   ✅ Year columns converted to DATE\n\n";
    } catch (Exception $e) {
        echo "   ⚠️  Year columns may already be DATE type or not exist: " . $e->getMessage() . "\n\n";
    }

    // Add Timestamps to tables
    echo "2. Adding created_at and updated_at to tables...\n";
    $tables = [
        'role_permissions',
        'permissions',
        'user_roles',
        'menu_items',
        'roles',
        'price_catalogs',
        'element_media',
        'input_locks',
        'system_settings',
        'project_members',
        'custom_field_values',
        'system_constants',
        'budget_items'
    ];

    $successCount = 0;
    $skipCount = 0;

    foreach ($tables as $table) {
        try {
            // Check if table exists
            $db->query("SELECT to_regclass('public.$table')");
            $result = $db->single();

            if ($result['to_regclass'] !== null) {
                $db->query("ALTER TABLE $table 
                           ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                           ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
                $db->execute();
                echo "   ✅ Added timestamps to: $table\n";
                $successCount++;
            } else {
                echo "   ⏭️  Skipped (table doesn't exist): $table\n";
                $skipCount++;
            }
        } catch (Exception $e) {
            echo "   ⚠️  Error on $table: " . $e->getMessage() . "\n";
        }
    }

    echo "\n   Summary: $successCount tables updated, $skipCount tables skipped\n";

    echo "\n" . str_repeat("=", 60) . "\n";
    echo "✅ Migration completed successfully!\n";

} catch (Exception $e) {
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
