<?php
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/config.php';

use Core\Database;

$db = Database::getInstance();

echo "--- BUDGET ITEMS ---\n";
$db->query("DESCRIBE budget_items");
foreach ($db->resultSet() as $col) {
    echo $col['Field'] . " | " . $col['Type'] . "\n";
}

echo "\n--- BUILDING ELEMENTS ---\n";
$db->query("DESCRIBE building_elements");
foreach ($db->resultSet() as $col) {
    echo $col['Field'] . " | " . $col['Type'] . "\n";
}
?>