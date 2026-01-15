<?php
/**
 * Database Migration Script
 * Fixes missing tables and columns identified in error log
 * Run this file once to update the database schema
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "Starting database migrations...\n\n";

// Migration 1: Ensure report_templates table has is_active column
echo "1. Checking report_templates table...\n";
try {
    // Check if table exists
    $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'report_templates'");
    $tableExists = $db->single();

    if ($tableExists['count'] > 0) {
        // Check if is_active column exists
        $db->query("SELECT COUNT(*) FROM information_schema.columns 
                   WHERE table_name = 'report_templates' AND column_name = 'is_active'");
        $columnExists = $db->single();

        if ($columnExists['count'] == 0) {
            echo "   Adding is_active column to report_templates...\n";
            $db->query("ALTER TABLE report_templates ADD COLUMN is_active BOOLEAN DEFAULT TRUE");
            $db->execute();
            echo "   ✅ Added is_active column\n";
        } else {
            echo "   ✅ is_active column already exists\n";
        }
    } else {
        echo "   Creating report_templates table...\n";
        $db->query("CREATE TABLE report_templates (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            content TEXT,
            css TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $db->execute();
        echo "   ✅ Created report_templates table\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Migration 2: Create project_snapshots table
echo "\n2. Checking project_snapshots table...\n";
try {
    $db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_name = 'project_snapshots'");
    $tableExists = $db->single();

    if ($tableExists['count'] == 0) {
        echo "   Creating project_snapshots table...\n";
        $db->query("CREATE TABLE project_snapshots (
            id SERIAL PRIMARY KEY,
            project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
            title VARCHAR(255) NOT NULL,
            snapshot_data JSONB NOT NULL,
            stats_summary JSONB,
            total_price NUMERIC(12,2) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL
        )");
        $db->execute();

        // Add index for better performance
        $db->query("CREATE INDEX idx_project_snapshots_project_id ON project_snapshots(project_id)");
        $db->execute();

        echo "   ✅ Created project_snapshots table with indexes\n";
    } else {
        echo "   ✅ project_snapshots table already exists\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Migration 3: Verify building_elements.is_bcl is correct type
echo "\n3. Checking building_elements.is_bcl column type...\n";
try {
    $db->query("SELECT data_type FROM information_schema.columns 
               WHERE table_name = 'building_elements' AND column_name = 'is_bcl'");
    $result = $db->single();

    if ($result) {
        echo "   Current type: " . $result['data_type'] . "\n";

        if ($result['data_type'] !== 'smallint' && $result['data_type'] !== 'integer' && $result['data_type'] !== 'boolean') {
            echo "   Converting is_bcl to SMALLINT...\n";
            $db->query("ALTER TABLE building_elements ALTER COLUMN is_bcl TYPE SMALLINT USING is_bcl::smallint");
            $db->execute();
            echo "   ✅ Converted is_bcl to SMALLINT\n";
        } else {
            echo "   ✅ is_bcl column type is acceptable\n";
        }
    } else {
        echo "   ⚠️  is_bcl column not found, might be added later\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Migration 4: Add missing columns if needed
echo "\n4. Verifying other essential columns...\n";
try {
    // Check if building_elements has parent_id
    $db->query("SELECT COUNT(*) FROM information_schema.columns 
               WHERE table_name = 'building_elements' AND column_name = 'parent_id'");
    $hasParent = $db->single();

    if ($hasParent['count'] == 0) {
        echo "   Adding parent_id to building_elements...\n";
        $db->query("ALTER TABLE building_elements ADD COLUMN parent_id INTEGER REFERENCES building_elements(id) ON DELETE CASCADE");
        $db->execute();
        echo "   ✅ Added parent_id column\n";
    } else {
        echo "   ✅ parent_id column exists\n";
    }

    // Check if building_elements has sort_order
    $db->query("SELECT COUNT(*) FROM information_schema.columns 
               WHERE table_name = 'building_elements' AND column_name = 'sort_order'");
    $hasSort = $db->single();

    if ($hasSort['count'] == 0) {
        echo "   Adding sort_order to building_elements...\n";
        $db->query("ALTER TABLE building_elements ADD COLUMN sort_order INTEGER DEFAULT 0");
        $db->execute();
        echo "   ✅ Added sort_order column\n";
    } else {
        echo "   ✅ sort_order column exists\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ Database migrations completed!\n";
echo "Please check the error log to verify issues are resolved.\n";
echo str_repeat("=", 50) . "\n";
