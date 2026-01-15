<?php
/**
 * Master Migration Runner
 * Runs all pending migrations in order
 */

define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║           DATABASE MIGRATION RUNNER                        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Create migrations tracking table if it doesn't exist
try {
    $db->query("CREATE TABLE IF NOT EXISTS migrations (
        id SERIAL PRIMARY KEY,
        migration_name VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $db->execute();
} catch (Exception $e) {
    echo "❌ Failed to create migrations table: " . $e->getMessage() . "\n";
    exit(1);
}

// Get list of executed migrations
$db->query("SELECT migration_name FROM migrations ORDER BY id");
$executedMigrations = array_column($db->resultSet() ?: [], 'migration_name');

// Define migrations in order
$migrations = [
    '01_add_building_elements_hierarchy.php',
    '02_add_customers_table.php',
    '03_update_dates_timestamps.php',
    'fix_errors_2026_01_13.php'
];

$executed = 0;
$skipped = 0;
$failed = 0;

foreach ($migrations as $migration) {
    $migrationPath = __DIR__ . '/' . $migration;

    if (!file_exists($migrationPath)) {
        echo "⚠️  Migration file not found: $migration\n";
        continue;
    }

    // Check if already executed
    if (in_array($migration, $executedMigrations)) {
        echo "⏭️  Skipped (already executed): $migration\n";
        $skipped++;
        continue;
    }

    echo "\n▶️  Running migration: $migration\n";
    echo str_repeat("-", 60) . "\n";

    // Execute migration
    ob_start();
    try {
        include $migrationPath;
        $output = ob_get_clean();
        echo $output;

        // Mark as executed
        $db->query("INSERT INTO migrations (migration_name) VALUES (:name)");
        $db->bind(':name', $migration);
        $db->execute();

        echo "✅ Migration completed and recorded\n";
        $executed++;

    } catch (Exception $e) {
        $output = ob_get_clean();
        echo $output;
        echo "❌ Migration failed: " . $e->getMessage() . "\n";
        $failed++;
    }
}

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                   MIGRATION SUMMARY                        ║\n";
echo "╠════════════════════════════════════════════════════════════╣\n";
printf("║  ✅ Executed: %-3d                                        ║\n", $executed);
printf("║  ⏭️  Skipped:  %-3d                                        ║\n", $skipped);
printf("║  ❌ Failed:   %-3d                                        ║\n", $failed);
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

if ($failed > 0) {
    echo "⚠️  Some migrations failed. Please check the output above.\n";
    exit(1);
} else {
    echo "🎉 All migrations completed successfully!\n";
}
