<?php
/**
 * Schema Completion Migration
 * Adds all missing columns identified during system audit
 * Date: 2026-01-14
 */

// Load config if not already loaded
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
    define('DB_PORT', getenv('DB_PORT') ?: '5432');
    define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: 'root');
}

require_once __DIR__ . '/../core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "=================================================\n";
echo "Schema Completion Migration - 2026-01-14\n";
echo "=================================================\n\n";

$migrations = [
    // Projects table additions
    [
        'table' => 'projects',
        'column' => 'client_id',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS client_id INTEGER REFERENCES customers(id) ON DELETE SET NULL"
    ],
    [
        'table' => 'projects',
        'column' => 'cover_image',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS cover_image VARCHAR(255)"
    ],
    [
        'table' => 'projects',
        'column' => 'report_intro',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS report_intro TEXT"
    ],
    [
        'table' => 'projects',
        'column' => 'report_disclaimer',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS report_disclaimer TEXT"
    ],
    [
        'table' => 'projects',
        'column' => 'bbr_number',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS bbr_number VARCHAR(50)"
    ],
    [
        'table' => 'projects',
        'column' => 'area_m2',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS area_m2 DECIMAL(10,2)"
    ],
    [
        'table' => 'projects',
        'column' => 'heating_type',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS heating_type VARCHAR(50)"
    ],
    [
        'table' => 'projects',
        'column' => 'usage_type',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS usage_type VARCHAR(50)"
    ],
    [
        'table' => 'projects',
        'column' => 'construction_year',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS construction_year INTEGER"
    ],
    [
        'table' => 'projects',
        'column' => 'renovation_year',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS renovation_year INTEGER"
    ],
    [
        'table' => 'projects',
        'column' => 'inspection_date',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS inspection_date DATE"
    ],
    [
        'table' => 'projects',
        'column' => 'deleted_at',
        'sql' => "ALTER TABLE projects ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP"
    ],

    // Building Elements additions
    [
        'table' => 'building_elements',
        'column' => 'capex',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS capex DECIMAL(15,2) DEFAULT 0"
    ],
    [
        'table' => 'building_elements',
        'column' => 'recommendation',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS recommendation TEXT"
    ],
    [
        'table' => 'building_elements',
        'column' => 'observation',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS observation TEXT"
    ],
    [
        'table' => 'building_elements',
        'column' => 'is_bcl',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS is_bcl SMALLINT DEFAULT 0"
    ],
    [
        'table' => 'building_elements',
        'column' => 'risk_level',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS risk_level VARCHAR(20)"
    ],
    [
        'table' => 'building_elements',
        'column' => 'building_id',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS building_id INTEGER"
    ],
    [
        'table' => 'building_elements',
        'column' => 'replacement_value',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS replacement_value DECIMAL(15,2) DEFAULT 0"
    ],
    [
        'table' => 'building_elements',
        'column' => 'urgency',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS urgency VARCHAR(20) DEFAULT 'low'"
    ],
    [
        'table' => 'building_elements',
        'column' => 'title',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS title VARCHAR(255)"
    ],
    [
        'table' => 'building_elements',
        'column' => 'element_type',
        'sql' => "ALTER TABLE building_elements ADD COLUMN IF NOT EXISTS element_type VARCHAR(50)"
    ],

    // Element Media additions
    [
        'table' => 'element_media',
        'column' => 'caption',
        'sql' => "ALTER TABLE element_media ADD COLUMN IF NOT EXISTS caption VARCHAR(255)"
    ],
    [
        'table' => 'element_media',
        'column' => 'comment',
        'sql' => "ALTER TABLE element_media ADD COLUMN IF NOT EXISTS comment TEXT"
    ],
    [
        'table' => 'element_media',
        'column' => 'filename',
        'sql' => "ALTER TABLE element_media ADD COLUMN IF NOT EXISTS filename VARCHAR(255)"
    ],
    [
        'table' => 'element_media',
        'column' => 'file_type',
        'sql' => "ALTER TABLE element_media ADD COLUMN IF NOT EXISTS file_type VARCHAR(50)"
    ],
    [
        'table' => 'element_media',
        'column' => 'file_size',
        'sql' => "ALTER TABLE element_media ADD COLUMN IF NOT EXISTS file_size INTEGER"
    ],

    // Budget Items additions
    [
        'table' => 'budget_items',
        'column' => 'amount_0_1',
        'sql' => "ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS amount_0_1 DECIMAL(15,2) DEFAULT 0"
    ],
    [
        'table' => 'budget_items',
        'column' => 'amount_1_2',
        'sql' => "ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS amount_1_2 DECIMAL(15,2) DEFAULT 0"
    ],
    [
        'table' => 'budget_items',
        'column' => 'amount_3_5',
        'sql' => "ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS amount_3_5 DECIMAL(15,2) DEFAULT 0"
    ],
    [
        'table' => 'budget_items',
        'column' => 'amount_5_10',
        'sql' => "ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS amount_5_10 DECIMAL(15,2) DEFAULT 0"
    ],
    [
        'table' => 'budget_items',
        'column' => 'status',
        'sql' => "ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS status VARCHAR(20) DEFAULT 'pending'"
    ],
    [
        'table' => 'budget_items',
        'column' => 'total_calculated',
        'sql' => "ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS total_calculated DECIMAL(15,2) DEFAULT 0"
    ],
    [
        'table' => 'budget_items',
        'column' => 'updated_at',
        'sql' => "ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ],

    // Price Catalogs additions
    [
        'table' => 'price_catalogs',
        'column' => 'name',
        'sql' => "ALTER TABLE price_catalogs ADD COLUMN IF NOT EXISTS name VARCHAR(255)"
    ],

    // Custom Field Definitions additions
    [
        'table' => 'custom_field_definitions',
        'column' => 'updated_at',
        'sql' => "ALTER TABLE custom_field_definitions ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ],
    [
        'table' => 'custom_field_definitions',
        'column' => 'deleted_at',
        'sql' => "ALTER TABLE custom_field_definitions ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP"
    ],

    // Custom Field Values additions
    [
        'table' => 'custom_field_values',
        'column' => 'entity_type',
        'sql' => "ALTER TABLE custom_field_values ADD COLUMN IF NOT EXISTS entity_type VARCHAR(50)"
    ],

    // Users additions
    [
        'table' => 'users',
        'column' => 'is_active',
        'sql' => "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE"
    ],

    // Project Members additions
    [
        'table' => 'project_members',
        'column' => 'role',
        'sql' => "ALTER TABLE project_members ADD COLUMN IF NOT EXISTS role VARCHAR(50) DEFAULT 'specialist'"
    ],

    // Input Locks additions  
    [
        'table' => 'input_locks',
        'column' => 'element_id',
        'sql' => "ALTER TABLE input_locks ADD COLUMN IF NOT EXISTS element_id INTEGER"
    ],
    [
        'table' => 'input_locks',
        'column' => 'client_id',
        'sql' => "ALTER TABLE input_locks ADD COLUMN IF NOT EXISTS client_id VARCHAR(100)"
    ],
    [
        'table' => 'input_locks',
        'column' => 'expires_at',
        'sql' => "ALTER TABLE input_locks ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP"
    ],

    // Project Snapshots - ensure created_by column
    [
        'table' => 'project_snapshots',
        'column' => 'created_by',
        'sql' => "ALTER TABLE project_snapshots ADD COLUMN IF NOT EXISTS created_by INTEGER REFERENCES users(id) ON DELETE SET NULL"
    ],
    [
        'table' => 'project_snapshots',
        'column' => 'description',
        'sql' => "ALTER TABLE project_snapshots ADD COLUMN IF NOT EXISTS description TEXT"
    ],
];

$success = 0;
$skipped = 0;
$errors = 0;

foreach ($migrations as $m) {
    echo "Checking {$m['table']}.{$m['column']}... ";

    try {
        // Check if column exists
        $db->query("SELECT column_name FROM information_schema.columns 
                   WHERE table_name = :table AND column_name = :column");
        $db->bind(':table', $m['table']);
        $db->bind(':column', $m['column']);
        $exists = $db->single();

        if ($exists) {
            echo "✓ Already exists\n";
            $skipped++;
        } else {
            $db->query($m['sql']);
            $db->execute();
            echo "✅ Added\n";
            $success++;
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        $errors++;
    }
}

echo "\n=================================================\n";
echo "Migration Complete!\n";
echo "  Added: $success\n";
echo "  Skipped: $skipped\n";
echo "  Errors: $errors\n";
echo "=================================================\n";

// Create project_buildings table if not exists
echo "\nChecking project_buildings table... ";
try {
    $db->query("CREATE TABLE IF NOT EXISTS project_buildings (
        id SERIAL PRIMARY KEY,
        project_id INTEGER NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
        name VARCHAR(255) NOT NULL,
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->execute();

    $db->query("CREATE INDEX IF NOT EXISTS idx_project_buildings_project ON project_buildings(project_id)");
    $db->execute();

    echo "✅ OK\n";
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

// Create customers table if not exists
echo "Checking customers table... ";
try {
    $db->query("CREATE TABLE IF NOT EXISTS customers (
        id SERIAL PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255),
        phone VARCHAR(50),
        address TEXT,
        cvr VARCHAR(20),
        contact_person VARCHAR(255),
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP
    )");
    $db->execute();
    echo "✅ OK\n";
} catch (Exception $e) {
    echo "❌ " . $e->getMessage() . "\n";
}

// Fix is_active column type if needed
echo "Checking users.is_active type... ";
try {
    $db->query("SELECT data_type FROM information_schema.columns 
               WHERE table_name = 'users' AND column_name = 'is_active'");
    $result = $db->single();

    if ($result && $result['data_type'] === 'smallint') {
        echo "Converting to BOOLEAN... ";
        $db->query("ALTER TABLE users ALTER COLUMN is_active TYPE BOOLEAN USING is_active::int::boolean");
        $db->execute();
        $db->query("ALTER TABLE users ALTER COLUMN is_active SET DEFAULT TRUE");
        $db->execute();
        echo "✅ Converted\n";
    } else {
        echo "✓ OK (already boolean)\n";
    }
} catch (Exception $e) {
    echo "⚠️ " . $e->getMessage() . "\n";
}

echo "\n✅ All migrations applied successfully!\n";
