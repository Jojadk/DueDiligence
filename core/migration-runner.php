<?php
/**
 * Database Migration Runner
 *
 * Handles execution of database migrations for both MySQL and PostgreSQL
 */

require_once __DIR__ . '/core.php';

class MigrationRunner {
    private $db;
    private $dbType;
    private $migrationsDir;

    public function __construct() {
        $this->db = db();
        $this->dbType = DatabaseAbstraction::getType();
        $this->migrationsDir = ROOT_DIR . '/database/migrations';
    }

    /**
     * Get path to migration file for current database type
     */
    private function getMigrationPath(string $migrationName): ?string {
        // Try database-specific migration first
        $specificPath = $this->migrationsDir . '/' . $this->dbType . '/' . $migrationName . '.sql';
        if (file_exists($specificPath)) {
            return $specificPath;
        }

        // Fall back to common migration
        $commonPath = $this->migrationsDir . '/common/' . $migrationName . '.sql';
        if (file_exists($commonPath)) {
            return $commonPath;
        }

        // Fall back to root migrations directory
        $rootPath = $this->migrationsDir . '/' . $migrationName . '.sql';
        if (file_exists($rootPath)) {
            return $rootPath;
        }

        return null;
    }

    /**
     * Initialize migrations tracking table
     */
    public function initMigrationsTable(): void {
        if (DatabaseAbstraction::isMySQL()) {
            $sql = "
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration_name VARCHAR(255) NOT NULL UNIQUE,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    db_type VARCHAR(10) NOT NULL,
                    INDEX idx_migration_name (migration_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
        } else {
            $sql = "
                CREATE TABLE IF NOT EXISTS schema_migrations (
                    id SERIAL PRIMARY KEY,
                    migration_name VARCHAR(255) NOT NULL UNIQUE,
                    executed_at TIMESTAMP DEFAULT NOW(),
                    db_type VARCHAR(10) NOT NULL
                );
                CREATE INDEX IF NOT EXISTS idx_migration_name ON schema_migrations(migration_name);
            ";
        }

        $this->db->exec($sql);
    }

    /**
     * Check if migration has been run
     */
    public function isMigrationExecuted(string $migrationName): bool {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM schema_migrations WHERE migration_name = :name");
        $stmt->execute(['name' => $migrationName]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Mark migration as executed
     */
    public function markMigrationExecuted(string $migrationName): void {
        $stmt = $this->db->prepare("
            INSERT INTO schema_migrations (migration_name, db_type)
            VALUES (:name, :db_type)
        ");
        $stmt->execute([
            'name' => $migrationName,
            'db_type' => $this->dbType
        ]);
    }

    /**
     * Execute a single migration
     */
    public function executeMigration(string $migrationName, bool $force = false): array {
        // Check if already executed
        if (!$force && $this->isMigrationExecuted($migrationName)) {
            return [
                'success' => false,
                'message' => "Migration '{$migrationName}' already executed",
                'skipped' => true
            ];
        }

        // Get migration file path
        $path = $this->getMigrationPath($migrationName);
        if (!$path) {
            return [
                'success' => false,
                'message' => "Migration file '{$migrationName}' not found",
                'error' => true
            ];
        }

        // Read migration file
        $sql = file_get_contents($path);
        if ($sql === false) {
            return [
                'success' => false,
                'message' => "Failed to read migration file '{$migrationName}'",
                'error' => true
            ];
        }

        // Execute migration
        try {
            $this->db->beginTransaction();

            // Split by semicolons and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                fn($stmt) => !empty($stmt) && !preg_match('/^\\s*--/', $stmt)
            );

            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    $this->db->exec($statement);
                }
            }

            // Mark as executed
            if (!$force) {
                $this->markMigrationExecuted($migrationName);
            }

            $this->db->commit();

            return [
                'success' => true,
                'message' => "Migration '{$migrationName}' executed successfully",
                'statements' => count($statements)
            ];
        } catch (PDOException $e) {
            $this->db->rollBack();

            return [
                'success' => false,
                'message' => "Migration '{$migrationName}' failed: " . $e->getMessage(),
                'error' => true,
                'exception' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute all pending migrations
     */
    public function executeAll(): array {
        $results = [];
        $migrations = $this->getAvailableMigrations();

        foreach ($migrations as $migration) {
            $results[$migration] = $this->executeMigration($migration);
        }

        return $results;
    }

    /**
     * Get list of available migrations
     */
    public function getAvailableMigrations(): array {
        $migrations = [];

        // Scan database-specific directory
        $specificDir = $this->migrationsDir . '/' . $this->dbType;
        if (is_dir($specificDir)) {
            $files = scandir($specificDir);
            foreach ($files as $file) {
                if (preg_match('/^(.+)\.sql$/', $file, $matches)) {
                    $migrations[] = $matches[1];
                }
            }
        }

        // Scan common directory
        $commonDir = $this->migrationsDir . '/common';
        if (is_dir($commonDir)) {
            $files = scandir($commonDir);
            foreach ($files as $file) {
                if (preg_match('/^(.+)\.sql$/', $file, $matches)) {
                    $name = $matches[1];
                    if (!in_array($name, $migrations)) {
                        $migrations[] = $name;
                    }
                }
            }
        }

        // Sort migrations by name (should be prefixed with numbers/dates)
        sort($migrations);

        return $migrations;
    }

    /**
     * Get migration status
     */
    public function getStatus(): array {
        $available = $this->getAvailableMigrations();
        $executed = [];

        $stmt = $this->db->query("SELECT migration_name, executed_at FROM schema_migrations ORDER BY executed_at");
        while ($row = $stmt->fetch()) {
            $executed[$row['migration_name']] = $row['executed_at'];
        }

        $status = [];
        foreach ($available as $migration) {
            $status[] = [
                'name' => $migration,
                'status' => isset($executed[$migration]) ? 'executed' : 'pending',
                'executed_at' => $executed[$migration] ?? null
            ];
        }

        return $status;
    }
}

// CLI execution
if (php_sapi_name() === 'cli' && isset($argv[0]) && basename($argv[0]) === 'migration-runner.php') {
    $runner = new MigrationRunner();

    $command = $argv[1] ?? 'status';

    switch ($command) {
        case 'init':
            echo "Initializing migrations table...\n";
            $runner->initMigrationsTable();
            echo "Done!\n";
            break;

        case 'status':
            echo "Migration Status (DB Type: " . DatabaseAbstraction::getType() . ")\n";
            echo str_repeat('=', 80) . "\n";
            $status = $runner->getStatus();
            foreach ($status as $migration) {
                printf("%-50s %s\n",
                    $migration['name'],
                    $migration['status'] === 'executed'
                        ? '✓ Executed at ' . $migration['executed_at']
                        : '○ Pending'
                );
            }
            break;

        case 'run':
            $migrationName = $argv[2] ?? null;
            if (!$migrationName) {
                echo "Usage: php migration-runner.php run <migration_name>\n";
                exit(1);
            }

            echo "Executing migration: {$migrationName}...\n";
            $result = $runner->executeMigration($migrationName);
            echo $result['message'] . "\n";
            exit($result['success'] ? 0 : 1);

        case 'run-all':
            echo "Executing all pending migrations...\n";
            $results = $runner->executeAll();
            foreach ($results as $name => $result) {
                echo "{$name}: {$result['message']}\n";
            }
            break;

        default:
            echo "Unknown command: {$command}\n";
            echo "Available commands:\n";
            echo "  init      - Initialize migrations table\n";
            echo "  status    - Show migration status\n";
            echo "  run       - Run a specific migration\n";
            echo "  run-all   - Run all pending migrations\n";
            exit(1);
    }
}
