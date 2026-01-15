<?php
/**
 * Database Optimization Migration v2 (Corrected)
 * Matches actual database schema
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "<h1>🚀 Database Optimization Migration v2</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border-radius:8px;'>";
echo str_repeat("=", 70) . "\n";
echo "Starting database optimization (schema-corrected)...\n\n";

$errors = [];
$success_count = 0;

// ========================================
// PART 1: CREATE VIEWS (Corrected for actual schema)
// ========================================

echo "📊 PART 1: Creating Database Views\n";
echo str_repeat("-", 70) . "\n\n";

$views = [
    'v_project_overview' => "
        CREATE OR REPLACE VIEW v_project_overview AS
        SELECT 
            p.id,
            p.name,
            p.status,
            p.client_id,
            p.client_name,
            p.construction_year,
            p.renovation_year,
            p.created_at,
            p.updated_at,
            COUNT(DISTINCT be.id) as element_count,
            COUNT(DISTINCT em.id) as media_count,
            COALESCE(SUM(be.capex), 0) as total_capex,
            COUNT(DISTINCT CASE WHEN be.risk_level = 'high' THEN be.id END) as high_risk_count,
            COUNT(DISTINCT CASE WHEN be.risk_level = 'medium' THEN be.id END) as medium_risk_count,
            COUNT(DISTINCT CASE WHEN be.risk_level = 'low' THEN be.id END) as low_risk_count,
            MAX(be.updated_at) as last_element_update
        FROM projects p
        LEFT JOIN building_elements be ON p.id = be.project_id
        LEFT JOIN element_media em ON be.id = em.element_id
        GROUP BY p.id, p.name, p.status, p.client_id, p.client_name, p.construction_year, p.renovation_year, p.created_at, p.updated_at
    ",

    'v_building_elements_hierarchy' => "
        CREATE OR REPLACE VIEW v_building_elements_hierarchy AS
        WITH RECURSIVE element_tree AS (
            SELECT 
                id, parent_id, project_id, name, description, sort_order,
                capex, risk_level, 0 as level,
                ARRAY[id] as path,
                name::TEXT as full_path
            FROM building_elements
            WHERE parent_id IS NULL
            
            UNION ALL
            
            SELECT 
                be.id, be.parent_id, be.project_id, be.name, be.description, be.sort_order,
                be.capex, be.risk_level, et.level + 1,
                et.path || be.id,
                et.full_path || ' > ' || be.name
            FROM building_elements be
            INNER JOIN element_tree et ON be.parent_id = et.id
        )
        SELECT * FROM element_tree ORDER BY path
    ",

    'v_budget_summary' => "
        CREATE OR REPLACE VIEW v_budget_summary AS
        SELECT 
            p.id as project_id,
            p.name as project_name,
            be.id as element_id,
            be.name as element_name,
            COUNT(bi.id) as budget_item_count,
            COALESCE(SUM(bi.quantity * bi.unit_price), 0) as total_budget,
            COALESCE(SUM(bi.amount_0_1), 0) as amount_year_0_1,
            COALESCE(SUM(bi.amount_1_2), 0) as amount_year_1_2,
            COALESCE(SUM(bi.amount_3_5), 0) as amount_year_3_5,
            COALESCE(SUM(bi.amount_5_10), 0) as amount_year_5_10
        FROM projects p
        LEFT JOIN building_elements be ON p.id = be.project_id
        LEFT JOIN budget_items bi ON be.id = bi.element_id
        GROUP BY p.id, p.name, be.id, be.name
    ",

    'v_media_overview' => "
        CREATE OR REPLACE VIEW v_media_overview AS
        SELECT 
            em.id,
            em.element_id,
            em.file_path,
            em.media_type,
            em.caption,
            em.sort_order,
            em.created_at,
            be.name as element_name,
            be.project_id,
            p.name as project_name
        FROM element_media em
        INNER JOIN building_elements be ON em.element_id = be.id
        INNER JOIN projects p ON be.project_id = p.id
    ",

    'v_active_locks' => "
        CREATE OR REPLACE VIEW v_active_locks AS
        SELECT 
            il.id,
            il.table_name,
            il.row_id,
            il.field_name,
            il.user_id,
            il.client_id,
            il.locked_at,
            il.ip_address,
            u.username,
            CASE 
                WHEN table_name = 'building_elements' THEN be.name
                ELSE NULL
            END as element_name,
            CASE 
                WHEN table_name = 'building_elements' THEN be.project_id
                ELSE NULL
            END as project_id
        FROM input_locks il
        LEFT JOIN users u ON il.user_id = u.id
        LEFT JOIN building_elements be ON il.table_name = 'building_elements' AND il.row_id = be.id
        WHERE il.locked_at > NOW() - INTERVAL '24 hours'
    "
];

foreach ($views as $name => $sql) {
    try {
        $db->query($sql);
        $db->execute();
        echo "✅ Created view: $name\n";
        $success_count++;
    } catch (Exception $e) {
        $msg = "Failed to create view $name: " . $e->getMessage();
        $errors[] = $msg;
        echo "❌ $msg\n";
    }
}

// ========================================
// PART 2: ADD PERFORMANCE INDEXES
// ========================================

echo "\n📑 PART 2: Creating Performance Indexes\n";
echo str_repeat("-", 70) . "\n\n";

$indexes = [
    'idx_building_elements_project_id' => 'CREATE INDEX IF NOT EXISTS idx_building_elements_project_id ON building_elements(project_id)',
    'idx_building_elements_parent_id' => 'CREATE INDEX IF NOT EXISTS idx_building_elements_parent_id ON building_elements(parent_id)',
    'idx_building_elements_risk_level' => 'CREATE INDEX IF NOT EXISTS idx_building_elements_risk_level ON building_elements(risk_level)',
    'idx_building_elements_updated_at' => 'CREATE INDEX IF NOT EXISTS idx_building_elements_updated_at ON building_elements(updated_at DESC)',
    'idx_budget_items_element_id' => 'CREATE INDEX IF NOT EXISTS idx_budget_items_element_id ON budget_items(element_id)',
    'idx_element_media_element_id' => 'CREATE INDEX IF NOT EXISTS idx_element_media_element_id ON element_media(element_id)',
    'idx_element_media_sort_order' => 'CREATE INDEX IF NOT EXISTS idx_element_media_sort_order ON element_media(element_id, sort_order)',
    'idx_input_locks_row_id' => 'CREATE INDEX IF NOT EXISTS idx_input_locks_row_id ON input_locks(table_name, row_id)',
    'idx_input_locks_locked_at' => 'CREATE INDEX IF NOT EXISTS idx_input_locks_locked_at ON input_locks(locked_at DESC)',
    'idx_input_locks_user_id' => 'CREATE INDEX IF NOT EXISTS idx_input_locks_user_id ON input_locks(user_id)',
    'idx_projects_status' => 'CREATE INDEX IF NOT EXISTS idx_projects_status ON projects(status)',
    'idx_projects_updated_at' => 'CREATE INDEX IF NOT EXISTS idx_projects_updated_at ON projects(updated_at DESC)',
    'idx_projects_client_id' => 'CREATE INDEX IF NOT EXISTS idx_projects_client_id ON projects(client_id)',
];

foreach ($indexes as $name => $sql) {
    try {
        $db->query($sql);
        $db->execute();
        echo "✅ Created index: $name\n";
        $success_count++;
    } catch (Exception $e) {
        $msg = "Failed to create index $name: " . $e->getMessage();
        $errors[] = $msg;
        echo "⚠️  $msg\n";
    }
}

// ========================================
// PART 3: VERIFY FOREIGN KEYS
// ========================================

echo "\n🔗 PART 3: Verifying Foreign Key Constraints\n";
echo str_repeat("-", 70) . "\n\n";

$db->query("
    SELECT constraint_name, table_name
    FROM information_schema.table_constraints
    WHERE constraint_type = 'FOREIGN KEY'
    AND constraint_schema = 'public'
    ORDER BY table_name
");
$fks = $db->resultSet();

if ($fks) {
    foreach ($fks as $fk) {
        echo "✅ Verified FK: {$fk['constraint_name']} on {$fk['table_name']}\n";
    }
} else {
    echo "⚠️  No foreign keys found\n";
}

// ========================================
// SUMMARY
// ========================================

echo "\n" . str_repeat("=", 70) . "\n";
echo "📊 MIGRATION SUMMARY\n";
echo str_repeat("=", 70) . "\n";
echo "✅ Successful operations: $success_count\n";
echo "❌ Errors: " . count($errors) . "\n";

if (count($errors) > 0) {
    echo "\n⚠️  ERRORS ENCOUNTERED:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
} else {
    echo "\n🎉 All operations completed successfully!\n";
    echo "\nCreated:\n";
    echo "  - 5 database views for optimized queries\n";
    echo "  - 13 performance indexes\n";
    echo "  - Verified all foreign key constraints\n";
}

echo "\n✅ Database optimization migration complete!\n";
echo str_repeat("=", 70) . "\n";
echo "</pre>";
