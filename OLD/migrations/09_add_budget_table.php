<?php
use Core\Database;

$db = Database::getInstance();

$sql = "
CREATE TABLE IF NOT EXISTS budget_items (
    id SERIAL PRIMARY KEY,
    element_id INT NOT NULL,
    price_catalog_id INT DEFAULT NULL,
    description VARCHAR(255) NOT NULL,
    quantity DECIMAL(10, 2) DEFAULT 0,
    unit VARCHAR(20) DEFAULT 'stk',
    unit_price DECIMAL(10, 2) DEFAULT 0,
    amount_0_1 DECIMAL(10, 2) DEFAULT 0,
    amount_1_2 DECIMAL(10, 2) DEFAULT 0,
    amount_3_5 DECIMAL(10, 2) DEFAULT 0,
    amount_5_10 DECIMAL(10, 2) DEFAULT 0,
    total_calculated DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (element_id) REFERENCES building_elements(id) ON DELETE CASCADE,
    FOREIGN KEY (price_catalog_id) REFERENCES price_catalogs(id) ON DELETE SET NULL
);
";

try {
    $db->query($sql);
    $db->execute();
    echo "Migration 09: budget_items table created/verified.<br>";
} catch (Exception $e) {
    echo "Migration 09 Error: " . $e->getMessage() . "<br>";
}
