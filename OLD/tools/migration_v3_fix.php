<?php
require_once __DIR__ . '/core/bootstrap.php';
use Core\Database;

$db = Database::getInstance();
echo "Starting Migration V3 (Budget Timeframes & Buildings) - Postgres Fix...<br>";

// 1. Update budget_items
echo "Checking budget_items columns...<br>";
try {
    $db->query("SELECT amount_0_1 FROM budget_items LIMIT 1");
    $db->execute();
    echo "Budget columns already exist.<br>";
} catch (\Exception $e) {
    echo "Adding budget timeframe columns...<br>";
    $cols = [
        "amount_0_1 DECIMAL(15,2) DEFAULT 0.00",
        "amount_1_2 DECIMAL(15,2) DEFAULT 0.00",
        "amount_3_5 DECIMAL(15,2) DEFAULT 0.00",
        "amount_5_10 DECIMAL(15,2) DEFAULT 0.00"
    ];
    foreach ($cols as $colDef) {
        try {
            $db->query("ALTER TABLE budget_items ADD COLUMN $colDef");
            $db->execute();
        } catch (\Exception $ex) {
            echo "Error adding col: " . $ex->getMessage() . "<br>";
        }
    }
}

// 2. Create project_buildings table
echo "Checking project_buildings table...<br>";
try {
    $db->query("SELECT 1 FROM project_buildings LIMIT 1");
    $db->execute();
    echo "project_buildings table already exists.<br>";
} catch (\Exception $e) {
    echo "Creating project_buildings table...<br>";
    // Postgres syntax for Auto Increment -> SERIAL
    $sql = "CREATE TABLE IF NOT EXISTS project_buildings (
        id SERIAL PRIMARY KEY,
        project_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->query($sql);
    $db->execute();

    // Index
    $db->query("CREATE INDEX idx_pb_project_id ON project_buildings(project_id)");
    $db->execute();
}

// 3. Add building_id to building_elements
echo "Checking building_elements.building_id...<br>";
try {
    $db->query("SELECT building_id FROM building_elements LIMIT 1");
    $db->execute();
    echo "building_id column already exists.<br>";
} catch (\Exception $e) {
    echo "Adding building_id to building_elements...<br>";
    $db->query("ALTER TABLE building_elements ADD COLUMN building_id INT DEFAULT NULL");
    $db->execute();
    $db->query("CREATE INDEX idx_building_id ON building_elements(building_id)");
    $db->execute();
}

echo "Migration V3 Complete.<br>";
