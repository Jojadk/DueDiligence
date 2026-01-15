<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/config.php';

use Core\Database;

try {
    $db = Database::getInstance();
    $db->query("SELECT id, title, parent_id, sort_order FROM menu_items ORDER BY parent_id, sort_order");
    $items = $db->resultSet();
    echo "ID | Title | Parent | Sort\n";
    foreach ($items as $item) {
        echo "{$item['id']} | {$item['title']} | " . ($item['parent_id'] ?: 'NULL') . " | {$item['sort_order']}\n";
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
