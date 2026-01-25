# Database Migrations

DueDiligence supports both MySQL and PostgreSQL databases. The migration system automatically selects the correct SQL files based on your database configuration.

## Database Support

- **MySQL 5.7+** (default, recommended for maximum compatibility)
- **PostgreSQL 12+** (advanced features, better for large datasets)

## Configuration

Set your database type in `.env`:

```env
# For MySQL (default)
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=duediligence
DB_USER=root
DB_PASS=

# For PostgreSQL
DB_TYPE=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=duediligence
DB_USER=postgres
DB_PASS=
```

## Migration Structure

```
database/migrations/
├── mysql/          # MySQL-specific migrations
├── pgsql/          # PostgreSQL-specific migrations
└── common/         # Database-agnostic migrations
```

The migration runner checks for database-specific migrations first, then falls back to common migrations.

## Running Migrations

### Initialize Migration System

```bash
php core/migration-runner.php init
```

### Check Migration Status

```bash
php core/migration-runner.php status
```

### Run All Pending Migrations

```bash
php core/migration-runner.php run-all
```

### Run Specific Migration

```bash
php core/migration-runner.php run <migration_name>
```

## Available Migrations

### MySQL Migrations

1. `001_create_base_tables.sql` - Core application tables
2. `002_create_opex_budget_tables.sql` - OPEX and budget system
3. `003_create_views.sql` - Performance views

### PostgreSQL Migrations

(To be created as needed - currently uses existing migrations)

## Key Differences

### Data Types

| Feature | MySQL | PostgreSQL |
|---------|-------|------------|
| Auto-increment | `INT AUTO_INCREMENT` | `SERIAL` |
| Boolean | `TINYINT(1)` | `BOOLEAN` |
| JSON | `JSON` | `JSONB` |
| IP Address | `VARCHAR(45)` | `INET` |
| Arrays | `JSON` | `TEXT[]` |

### Search

| Feature | MySQL | PostgreSQL |
|---------|-------|------------|
| Case-insensitive | `LOWER(col) LIKE LOWER(?)` | `col ILIKE ?` |

### Upsert

| Feature | MySQL | PostgreSQL |
|---------|-------|------------|
| Insert/Update | `ON DUPLICATE KEY UPDATE` | `ON CONFLICT DO UPDATE` |

## Best Practices

1. **Always use the database abstraction layer** - Use `db_ilike()`, `DatabaseAbstraction` methods
2. **Test on both databases** - Ensure compatibility when making schema changes
3. **Use migrations for all schema changes** - Never modify tables directly
4. **Keep migrations idempotent** - Use `IF NOT EXISTS` where possible
5. **Version migrations** - Prefix with numbers (001_, 002_, etc.)

## Troubleshooting

### MySQL-specific Issues

- **Character set errors**: Ensure utf8mb4 is used for all tables
- **Case sensitivity**: MySQL is case-insensitive by default
- **Reserved keywords**: Use backticks for column names like `read`

### PostgreSQL-specific Issues

- **Extensions not loaded**: Some migrations require extensions
- **Case sensitivity**: PostgreSQL is case-sensitive for identifiers
- **Array operations**: Use proper array syntax

## Adding New Migrations

1. Create migration file in `mysql/` or `pgsql/` directory
2. Prefix with next number (e.g., `004_`)
3. Use database-specific syntax
4. Test thoroughly
5. Run migration: `php core/migration-runner.php run <name>`
