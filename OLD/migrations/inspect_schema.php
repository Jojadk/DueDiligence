<?php
/**
 * Database Schema Inspector
 * Discovers actual table structure
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "<h1>🔍 Database Schema Inspector</h1>";
echo "<pre style='background:#f5f5f5; padding:20px; border-radius:8px;'>";

$tables = ['projects', 'building_elements', 'budget_items', 'element_media', 'input_locks'];

foreach ($tables as $table) {
    echo "\n" . str_repeat("=", 70) . "\n";
    echo "📋 TABLE: $table\n";
    echo str_repeat("=", 70) . "\n";

    try {
        $db->query("
            SELECT column_name, data_type, character_maximum_length, is_nullable, column_default
            FROM information_schema.columns
            WHERE table_name = :table AND table_schema = 'public'
            ORDER BY ordinal_position
        ");
        $db->bind(':table', $table);
        $columns = $db->resultSet();

        if ($columns) {
            foreach ($columns as $col) {
                $nullable = $col['is_nullable'] === 'YES' ? 'NULL' : 'NOT NULL';
                $type = $col['data_type'];
                if ($col['character_maximum_length']) {
                    $type .= "({$col['character_maximum_length']})";
                }
                echo sprintf("  %-30s %-20s %s\n", $col['column_name'], $type, $nullable);
            }
        } else {
            echo "  (Table not found or has no columns)\n";
        }
    } catch (Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
}

echo "\n" . str_repeat("=", 70) . "\n";
echo "✅ Schema inspection complete!\n";
echo "</pre>";
