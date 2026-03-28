<?php
/**
 * Migration Runner
 * Executes SQL migration files
 */

require_once __DIR__ . '/core/core.php';

if ($argc < 2) {
    echo "Usage: php run_migration.php <migration_file.sql>\n";
    exit(1);
}

$migrationFile = $argv[1];

// Make path absolute if relative
if (!file_exists($migrationFile)) {
    $migrationFile = __DIR__ . '/migrations/' . basename($migrationFile);
}

if (!file_exists($migrationFile)) {
    echo "Error: Migration file not found: {$migrationFile}\n";
    exit(1);
}

echo "Running migration: " . basename($migrationFile) . "\n";

$sql = file_get_contents($migrationFile);
$db = db();

try {
    // Execute the entire SQL file
    $db->exec($sql);
    echo "✓ Migration completed successfully!\n";
    exit(0);
} catch (PDOException $e) {
    echo "✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
