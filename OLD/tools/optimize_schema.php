<?php
define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once __DIR__ . '/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "<h1>System Schema Optimizer & Verifier</h1>";
echo "<pre>";

// --- 1. TABLES (Create if not exists) ---
$tables = [
    'project_snapshots' => "
        CREATE TABLE IF NOT EXISTS project_snapshots (
            id SERIAL PRIMARY KEY,
            project_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT,
            title VARCHAR(255),
            description TEXT,
            total_price DECIMAL(15,2) DEFAULT 0,
            stats_summary TEXT,
            db_dump TEXT,
            files_path VARCHAR(255)
        )
    ",
    'report_templates' => "
        CREATE TABLE IF NOT EXISTS report_templates (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            content TEXT,
            css TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'budget_items' => "
        CREATE TABLE IF NOT EXISTS budget_items (
            id SERIAL PRIMARY KEY,
            element_id INT NOT NULL,
            description VARCHAR(255),
            quantity DECIMAL(10,2),
            unit VARCHAR(50),
            unit_price DECIMAL(15,2),
            total_calculated DECIMAL(15,2),
            amount_0_1 DECIMAL(15,2) DEFAULT 0,
            amount_1_2 DECIMAL(15,2) DEFAULT 0,
            amount_3_5 DECIMAL(15,2) DEFAULT 0,
            amount_5_10 DECIMAL(15,2) DEFAULT 0,
            year INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'customers' => "
        CREATE TABLE IF NOT EXISTS customers (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            address TEXT,
            email VARCHAR(255),
            phone VARCHAR(50),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'project_buildings' => "
        CREATE TABLE IF NOT EXISTS project_buildings (
            id SERIAL PRIMARY KEY,
            project_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ",
    'system_logs' => "
        CREATE TABLE IF NOT EXISTS system_logs (
            id SERIAL PRIMARY KEY,
            level VARCHAR(20),
            message TEXT,
            context TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            user_id INT,
            ip_address VARCHAR(45)
        )
    "
];

foreach ($tables as $name => $sql) {
    try {
        $db->query($sql);
        $db->execute();
        echo "[TABLE] Ensured table '$name' exists.\n";
    } catch (Exception $e) {
        echo "[ERROR] Table '$name': " . $e->getMessage() . "\n";
    }
}

// --- 2. COLUMNS (Add if not exists) ---
// Format: table => [col_name => definition]
$columns = [
    'projects' => [
        'cover_image' => 'VARCHAR(255)',
        'bbr_number' => 'VARCHAR(50)',
        'area_m2' => 'DECIMAL(10,2)',
        'heating_type' => 'VARCHAR(100)',
        'usage_type' => 'VARCHAR(100)',
        'construction_year' => 'INT',
        'renovation_year' => 'INT',
        'inspection_date' => 'DATE',
        'client_id' => 'INT',
        'report_intro' => 'TEXT',
        'report_disclaimer' => 'TEXT'
    ],
    'building_elements' => [
        'parent_id' => 'INT DEFAULT NULL',
        'sort_order' => 'INT DEFAULT 0',
        'risk_level' => 'VARCHAR(50)',
        'recommendation' => 'TEXT',
        'observation' => 'TEXT',
        'capex' => 'DECIMAL(15,2) DEFAULT 0',
        'is_bcl' => 'BOOLEAN DEFAULT FALSE',
        'building_id' => 'INT DEFAULT NULL'
    ],
    'element_media' => [
        'original_file_path' => 'VARCHAR(255)',
        'caption' => 'TEXT',
        'annotations' => 'TEXT',
        'file_type' => "VARCHAR(50) DEFAULT 'image'"
    ]
];

foreach ($columns as $table => $cols) {
    echo "\n[CHECK] Checking columns for '$table'...\n";
    foreach ($cols as $col => $def) {
        // Postgres specific check
        try {
            // Brute force ADD COLUMN IF NOT EXISTS logic via exception handling usually safer cross-DB, 
            // but for Postgres we can query information_schema or use DO block. 
            // To be simple and robust: we try to ADD, catch exception if exists.

            $db->query("ALTER TABLE $table ADD COLUMN $col $def");
            $db->execute();
            echo "  + Added column '$col'\n";
        } catch (Exception $e) {
            // If error contains "exists", ignore.
            if (strpos($e->getMessage(), 'exists') !== false || strpos($e->getMessage(), 'Duplicate column') !== false) {
                echo "  = Column '$col' already exists.\n";
            } else {
                echo "  ! Error adding '$col': " . $e->getMessage() . "\n";
            }
        }
    }
}

// --- 3. INDEXES ---
$indexes = [
    'project_snapshots' => ['project_id'],
    'budget_items' => ['element_id'],
    'building_elements' => ['project_id', 'parent_id', 'building_id'],
    'element_media' => ['element_id'],
    'project_buildings' => ['project_id'],
    'input_locks' => ['table_name', 'row_id']
];

echo "\n[INDEXES] verifying indexes...\n";
foreach ($indexes as $table => $cols) {
    foreach ($cols as $col) {
        $idxName = "idx_{$table}_{$col}";
        try {
            $db->query("CREATE INDEX IF NOT EXISTS $idxName ON $table ($col)");
            $db->execute();
            echo "  + Ensured index '$idxName'\n";
        } catch (Exception $e) {
            echo "  ! Error index '$idxName': " . $e->getMessage() . "\n";
        }
    }
}

// --- 4. VIEWS ---
echo "\n[VIEWS] Updating Views...\n";

// View: Element Costs Summary
try {
    $db->query("
        CREATE OR REPLACE VIEW view_element_costs AS
        SELECT 
            element_id, 
            SUM(total_calculated) as total_cost,
            SUM(amount_0_1) as cost_0_1,
            SUM(amount_1_2) as cost_1_2,
            SUM(amount_3_5) as cost_3_5,
            SUM(amount_5_10) as cost_5_10
        FROM budget_items
        GROUP BY element_id
    ");
    $db->execute();
    echo "  + View 'view_element_costs' updated.\n";
} catch (Exception $e) {
    echo "  ! Error creating view: " . $e->getMessage() . "\n";
}

echo "\nDone.</pre>";
echo "<a href='/'>Go Home</a>";
