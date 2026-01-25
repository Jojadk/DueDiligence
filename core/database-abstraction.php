<?php
/**
 * Database Abstraction Layer
 *
 * Provides database-agnostic functions for MySQL and PostgreSQL compatibility
 */

class DatabaseAbstraction {
    private static $dbType = null;

    /**
     * Get current database type
     */
    public static function getType(): string {
        if (self::$dbType === null) {
            self::$dbType = defined('DB_TYPE') ? DB_TYPE : 'mysql';
        }
        return self::$dbType;
    }

    /**
     * Check if using MySQL
     */
    public static function isMySQL(): bool {
        return in_array(self::getType(), ['mysql', 'mysqli']);
    }

    /**
     * Check if using PostgreSQL
     */
    public static function isPostgreSQL(): bool {
        return self::getType() === 'pgsql';
    }

    /**
     * Get SERIAL/AUTO_INCREMENT syntax
     */
    public static function autoIncrementSyntax(string $type = 'INT'): string {
        if (self::isMySQL()) {
            return $type === 'BIGINT' ? 'BIGINT AUTO_INCREMENT' : 'INT AUTO_INCREMENT';
        }
        return $type === 'BIGINT' ? 'BIGSERIAL' : 'SERIAL';
    }

    /**
     * Get case-insensitive LIKE operator
     * PostgreSQL: ILIKE
     * MySQL: LIKE with COLLATE or LOWER()
     */
    public static function ilike(string $column, string $placeholder): string {
        if (self::isPostgreSQL()) {
            return "{$column} ILIKE {$placeholder}";
        }
        // MySQL: use LOWER() for case-insensitive search
        return "LOWER({$column}) LIKE LOWER({$placeholder})";
    }

    /**
     * Get JSON column type
     * PostgreSQL: JSONB
     * MySQL: JSON
     */
    public static function jsonType(): string {
        return self::isPostgreSQL() ? 'JSONB' : 'JSON';
    }

    /**
     * Get IP address column type
     * PostgreSQL: INET
     * MySQL: VARCHAR(45) for IPv4/IPv6
     */
    public static function inetType(): string {
        return self::isPostgreSQL() ? 'INET' : 'VARCHAR(45)';
    }

    /**
     * Get array column type or JSON alternative
     * PostgreSQL: TEXT[]
     * MySQL: JSON (no native array type)
     */
    public static function arrayType(): string {
        return self::isPostgreSQL() ? 'TEXT[]' : 'JSON';
    }

    /**
     * Get CAST syntax
     * PostgreSQL: field::TYPE
     * MySQL: CAST(field AS TYPE)
     */
    public static function cast(string $field, string $type): string {
        if (self::isPostgreSQL()) {
            return "{$field}::{$type}";
        }
        return "CAST({$field} AS {$type})";
    }

    /**
     * Get INTERVAL syntax for date operations
     * PostgreSQL: NOW() - INTERVAL '30 days'
     * MySQL: DATE_SUB(NOW(), INTERVAL 30 DAY)
     */
    public static function intervalSub(string $interval, string $unit = 'DAY'): string {
        if (self::isPostgreSQL()) {
            return "NOW() - INTERVAL '{$interval} {$unit}s'";
        }
        return "DATE_SUB(NOW(), INTERVAL {$interval} {$unit})";
    }

    /**
     * Get INTERVAL syntax for date addition
     * PostgreSQL: NOW() + INTERVAL '30 days'
     * MySQL: DATE_ADD(NOW(), INTERVAL 30 DAY)
     */
    public static function intervalAdd(string $interval, string $unit = 'DAY'): string {
        if (self::isPostgreSQL()) {
            return "NOW() + INTERVAL '{$interval} {$unit}s'";
        }
        return "DATE_ADD(NOW(), INTERVAL {$interval} {$unit})";
    }

    /**
     * Get UPSERT syntax (INSERT ... ON CONFLICT / ON DUPLICATE KEY UPDATE)
     *
     * @param string $table Table name
     * @param array $columns Column names
     * @param array $conflictColumns Columns to check for conflicts
     * @param array $updateColumns Columns to update on conflict
     * @return string SQL syntax
     */
    public static function upsert(string $table, array $columns, array $conflictColumns, array $updateColumns): string {
        $columnList = implode(', ', $columns);
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO {$table} ({$columnList}) VALUES ({$placeholders})";

        if (self::isPostgreSQL()) {
            $conflictList = implode(', ', $conflictColumns);
            $updateList = implode(', ', array_map(fn($col) => "{$col} = EXCLUDED.{$col}", $updateColumns));
            $sql .= " ON CONFLICT ({$conflictList}) DO UPDATE SET {$updateList}";
        } else {
            $updateList = implode(', ', array_map(fn($col) => "{$col} = VALUES({$col})", $updateColumns));
            $sql .= " ON DUPLICATE KEY UPDATE {$updateList}";
        }

        return $sql;
    }

    /**
     * Get FILTER clause or CASE alternative
     * PostgreSQL: COUNT(*) FILTER (WHERE condition)
     * MySQL: SUM(CASE WHEN condition THEN 1 ELSE 0 END)
     */
    public static function filterCount(string $condition): string {
        if (self::isPostgreSQL()) {
            return "COUNT(*) FILTER (WHERE {$condition})";
        }
        return "SUM(CASE WHEN {$condition} THEN 1 ELSE 0 END)";
    }

    /**
     * Get DISTINCT ON alternative
     * PostgreSQL: DISTINCT ON (columns)
     * MySQL: Use ROW_NUMBER() window function or GROUP BY
     */
    public static function distinctOn(array $columns): string {
        if (self::isPostgreSQL()) {
            return 'DISTINCT ON (' . implode(', ', $columns) . ')';
        }
        // MySQL: use window function
        return 'DISTINCT';
    }

    /**
     * Get BOOLEAN type
     * PostgreSQL: BOOLEAN
     * MySQL: TINYINT(1)
     */
    public static function booleanType(): string {
        return self::isPostgreSQL() ? 'BOOLEAN' : 'TINYINT(1)';
    }

    /**
     * Get TRUE value
     */
    public static function true(): string {
        return self::isPostgreSQL() ? 'TRUE' : '1';
    }

    /**
     * Get FALSE value
     */
    public static function false(): string {
        return self::isPostgreSQL() ? 'FALSE' : '0';
    }

    /**
     * Get NOW() with timezone support
     */
    public static function now(): string {
        return 'NOW()';
    }

    /**
     * Get random function
     * PostgreSQL: RANDOM()
     * MySQL: RAND()
     */
    public static function random(): string {
        return self::isPostgreSQL() ? 'RANDOM()' : 'RAND()';
    }

    /**
     * Get string concatenation operator
     * PostgreSQL: ||
     * MySQL: CONCAT()
     */
    public static function concat(array $fields): string {
        if (self::isPostgreSQL()) {
            return implode(' || ', $fields);
        }
        return 'CONCAT(' . implode(', ', $fields) . ')';
    }

    /**
     * Get LIMIT/OFFSET syntax
     * Both support same syntax, but this normalizes it
     */
    public static function limitOffset(int $limit, int $offset = 0): string {
        if ($offset > 0) {
            return "LIMIT {$limit} OFFSET {$offset}";
        }
        return "LIMIT {$limit}";
    }

    /**
     * Escape identifier (table/column name)
     */
    public static function escapeIdentifier(string $identifier): string {
        if (self::isPostgreSQL()) {
            return '"' . str_replace('"', '""', $identifier) . '"';
        }
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    /**
     * Get schema introspection query for tables
     */
    public static function getTablesQuery(): string {
        if (self::isPostgreSQL()) {
            return "SELECT table_name FROM information_schema.tables
                    WHERE table_schema = 'public'
                    ORDER BY table_name";
        }
        return "SHOW TABLES";
    }

    /**
     * Get schema introspection query for columns
     */
    public static function getColumnsQuery(string $table): string {
        if (self::isPostgreSQL()) {
            return "SELECT column_name, data_type, is_nullable, column_default
                    FROM information_schema.columns
                    WHERE table_name = '{$table}'
                    ORDER BY ordinal_position";
        }
        return "SHOW COLUMNS FROM `{$table}`";
    }

    /**
     * Build search WHERE clause with ILIKE/LIKE
     */
    public static function buildSearchWhere(array $columns, string $paramName = 'search'): string {
        $conditions = array_map(
            fn($col) => self::ilike($col, ":{$paramName}"),
            $columns
        );
        return '(' . implode(' OR ', $conditions) . ')';
    }

    /**
     * Convert array to SQL array literal
     * PostgreSQL: ARRAY['a', 'b', 'c']
     * MySQL: JSON_ARRAY('a', 'b', 'c')
     */
    public static function arrayLiteral(array $values): string {
        $escaped = array_map(fn($v) => "'" . addslashes($v) . "'", $values);

        if (self::isPostgreSQL()) {
            return 'ARRAY[' . implode(', ', $escaped) . ']';
        }
        return 'JSON_ARRAY(' . implode(', ', $escaped) . ')';
    }
}

/**
 * Helper function for case-insensitive search
 */
function db_ilike(string $column, string $placeholder): string {
    return DatabaseAbstraction::ilike($column, $placeholder);
}

/**
 * Helper function for building search conditions
 */
function db_search_where(array $columns, string $paramName = 'search'): string {
    return DatabaseAbstraction::buildSearchWhere($columns, $paramName);
}
