<?php
require_once __DIR__ . '/index.php.prev'; // Using index.php context logic/bootstrap
// Or simpler, just require database class if I know where it is.
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/config.php'; // DB Credentials

use Core\Database;

try {
    $db = Database::getInstance();
    $db->query("SELECT id, title, parent_id FROM menu_items");
    $items = $db->resultSet();
    echo "Current Menu Items:\n";
    foreach ($items as $item) {
        echo "ID: " . $item['id'] . " | Title: " . $item['title'] . " | Parent: " . ($item['parent_id'] ?: 'NULL') . "\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>