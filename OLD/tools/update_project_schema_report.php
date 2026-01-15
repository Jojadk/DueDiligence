<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/config.php';

use Core\Database;

$db = Database::getInstance();
try {
    // Add columns if not exist. 
    // MySQL 5.7+ supports IF NOT EXISTS in some syntax but standard ALTER doesn't
    // We'll just try and catch
    $sqls = [
        "ALTER TABLE projects ADD COLUMN report_header TEXT DEFAULT NULL",
        "ALTER TABLE projects ADD COLUMN report_intro TEXT DEFAULT NULL",
        "ALTER TABLE projects ADD COLUMN report_disclaimer TEXT DEFAULT NULL"
    ];

    foreach ($sqls as $sql) {
        try {
            $db->query($sql);
            $db->execute();
            echo "Executed: $sql\n";
        } catch (Exception $e) {
            echo "Skipped/Error: " . $e->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    echo "Fatal: " . $e->getMessage();
}
?>