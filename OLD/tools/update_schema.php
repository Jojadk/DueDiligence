<?php
require_once __DIR__ . '/core/Autoloader.php';
use Core\Database;

try {
    $db = Database::getInstance();
    // Check if column exists
    // catch exception if not
    try {
        $db->query("SELECT role FROM project_members LIMIT 1");
        $db->execute();
        echo "Column 'role' already exists.\n";
    } catch (Exception $e) {
        echo "Adding 'role' column...\n";
        // Attempt Postgres/SQLite syntax
        // If MySQL this might vary slightly depending on version but ADD COLUMN is standard
        $db->query("ALTER TABLE project_members ADD COLUMN role VARCHAR(50) DEFAULT 'specialist'");
        $db->execute();
        echo "Column 'role' added.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
