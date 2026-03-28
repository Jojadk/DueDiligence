<?php
// Database Configuration (User provided)
define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once __DIR__ . '/core/Database.php';
use Core\Database;

$db = Database::getInstance();
$db->query("SELECT id, location, name FROM building_elements WHERE location LIKE 'std.%' ORDER BY location");
$rows = $db->resultSet();

echo "Current Elements:\n";
foreach ($rows as $r) {
    echo $r['location'] . " | " . $r['name'] . "\n";
}
?>