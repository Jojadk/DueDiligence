#!/usr/bin/env php
<?php
/**
 * Migration Runner
 * Executes SQL migrations against the database
 *
 * Usage: php migrations/run_migration.php <migration_file.sql>
 */

require_once __DIR__ . '/../core/core.php';

if ($argc < 2) {
    echo "Usage: php migrations/run_migration.php <migration_file.sql>\n";
    echo "Example: php migrations/run_migration.php add_sort_order_to_building_elements.sql\n";
    exit(1);
}

$migrationFile = $argv[1];

// If only filename provided, prepend migrations directory
if (!str_contains($migrationFile, '/')) {
    $migrationFile = __DIR__ . '/' . $migrationFile;
}

if (!file_exists($migrationFile)) {
    echo "Error: Migration file not found: $migrationFile\n";
    exit(1);
}

echo "Running migration: $migrationFile\n";
echo str_repeat('-', 60) . "\n";

$sql = file_get_contents($migrationFile);

try {
    $pdo = get_pdo();
    $pdo->exec($sql);
    echo "\n✓ Migration completed successfully!\n";
} catch (PDOException $e) {
    echo "\n✗ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
