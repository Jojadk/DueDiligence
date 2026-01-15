<?php
require_once __DIR__ . '/core/bootstrap.php';

use Core\Database;

$db = Database::getInstance();

echo "Starting migration v2...<br>";

// Check if column exists
try {
    $db->query("SELECT is_bcl FROM building_elements LIMIT 1");
    $db->execute();
    echo "Column 'is_bcl' already exists.<br>";
} catch (\Exception $e) {
    echo "Column 'is_bcl' not found (Error: " . $e->getMessage() . "). Adding it...<br>";
    try {
        // Postgres syntax
        $db->query("ALTER TABLE building_elements ADD COLUMN is_bcl SMALLINT DEFAULT 0");
        $db->execute();
        echo "Column added successfully.<br>";
    } catch (\Exception $ex) {
        echo "Error adding column: " . $ex->getMessage() . "<br>";
    }
}
