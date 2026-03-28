<?php
define('APPROOT', dirname(__DIR__));
require_once APPROOT . '/core/Database.php';
require_once APPROOT . '/config/config.php'; // Ensure config is loaded for DB constants

use Core\Database;

try {
    $db = Database::getInstance();

    // 1. Create price_catalogs table
    $sql1 = "
        CREATE TABLE IF NOT EXISTS price_catalogs (
            id SERIAL PRIMARY KEY,
            item_code VARCHAR(50),
            name VARCHAR(255),
            description TEXT,
            unit VARCHAR(20),
            unit_price DECIMAL(15,2),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";
    $db->query($sql1);
    $db->execute();
    echo "Checked/Created price_catalogs table.\n";

    // 2. Add price_catalog_id column to budget_items
    // Using Postgres syntax specifically
    $sql2 = "
        DO $$
        BEGIN
            IF NOT EXISTS (SELECT 1 FROM information_schema.columns WHERE table_name='budget_items' AND column_name='price_catalog_id') THEN
                ALTER TABLE budget_items ADD COLUMN price_catalog_id INTEGER;
            END IF;
        END
        $$;
    ";
    $db->query($sql2);
    $db->execute();
    echo "Checked/Added price_catalog_id column.\n";

    // 3. Add Foreign Key
    // We assume if column exists, we might need FK. 
    // Duplicate FK name throws error, so we wrap in block or just catch.
    try {
        $sql3 = "ALTER TABLE budget_items ADD CONSTRAINT fk_budget_items_catalog FOREIGN KEY (price_catalog_id) REFERENCES price_catalogs(id) ON DELETE SET NULL";
        $db->query($sql3);
        $db->execute();
        echo "Added FK constraint.\n";
    } catch (Exception $e) {
        echo "FK Constraint might already exist (Ignored).\n";
    }

} catch (Exception $e) {
    echo "Migration Error: " . $e->getMessage() . "\n";
}
