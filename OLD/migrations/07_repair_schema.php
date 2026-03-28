<?php
// DB Repair Script
// Run via terminal: php migrations/07_repair_schema.php
// Or ensure Database class is loadable. Assuming bootstrapping or manual include.

// Locate Database.php. 
// Standard path: /Volumes/web/sys_tdd/core/Database.php

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();
try {
    $db = Database::getInstance();
    echo "Connected to DB. Starting Repair...\n";

    // 1. Table price_catalogs
    $db->query("CREATE TABLE IF NOT EXISTS price_catalogs (
        id SERIAL PRIMARY KEY,
        item_code VARCHAR(50),
        name VARCHAR(255),
        description TEXT,
        unit VARCHAR(20),
        unit_price DECIMAL(15,2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->execute();
    echo "[OK] price_catalogs table.\n";

    // 2. budget_items columns
    $db->query("ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS price_catalog_id INTEGER");
    $db->execute();
    echo "[OK] price_catalog_id column.\n";

    // 3. budget_items sort_order
    $db->query("ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS sort_order INTEGER DEFAULT 0");
    $db->execute();
    echo "[OK] sort_order column.\n";

    // 4. FK Constraint
    try {
        $db->query("ALTER TABLE budget_items ADD CONSTRAINT fk_budget_items_catalog FOREIGN KEY (price_catalog_id) REFERENCES price_catalogs(id) ON DELETE SET NULL");
        $db->execute();
        echo "[OK] FK constraint.\n";
    } catch (Exception $e) {
        // Likely exists
        echo "[INFO] FK constraint likely exists.\n";
    }

    echo "Repair Completed Successfully.\n";

} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
}
