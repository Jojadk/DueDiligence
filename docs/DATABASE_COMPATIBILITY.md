# Database Compatibility Guide

DueDiligence er designet til at køre på både **MySQL** og **PostgreSQL** databaser. MySQL er standard for maksimal kompatibilitet.

## 🎯 Hurtig Start

### MySQL Setup (Anbefalet)

```bash
# 1. Kopier environment fil
cp .env.example .env

# 2. Rediger .env (MySQL er standard)
DB_TYPE=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=duediligence
DB_USER=root
DB_PASS=your_password

# 3. Kør migrations
php core/migration-runner.php init
php core/migration-runner.php run-all
```

### PostgreSQL Setup (Avanceret)

```bash
# 1. Kopier environment fil
cp .env.example .env

# 2. Rediger .env
DB_TYPE=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=duediligence
DB_USER=postgres
DB_PASS=your_password

# 3. Kør migrations
php core/migration-runner.php init
php core/migration-runner.php run-all
```

## ⚙️ Hvordan Det Virker

### Automatisk Database-detektion

Systemet detekterer automatisk hvilken database der bruges og tilpasser SQL syntaksen:

```php
// core.php indlæser automatisk korrekt database-type
define('DB_TYPE', getenv('DB_TYPE') ?: 'mysql');

// DatabaseAbstraction klassen håndterer forskelle
if (DatabaseAbstraction::isMySQL()) {
    // MySQL-specifik kode
} else {
    // PostgreSQL-specifik kode
}
```

### Database Abstraction Layer

`core/database-abstraction.php` tilbyder helper-funktioner:

```php
// Case-insensitive søgning
$sql = "WHERE " . db_ilike('name', ':search');
// MySQL: LOWER(name) LIKE LOWER(:search)
// PostgreSQL: name ILIKE :search

// Auto-increment
DatabaseAbstraction::autoIncrementSyntax()
// MySQL: INT AUTO_INCREMENT
// PostgreSQL: SERIAL

// JSON type
DatabaseAbstraction::jsonType()
// MySQL: JSON
// PostgreSQL: JSONB

// IP-adresse type
DatabaseAbstraction::inetType()
// MySQL: VARCHAR(45)
// PostgreSQL: INET
```

## 📊 Type Mapping

| Feature | MySQL | PostgreSQL |
|---------|-------|------------|
| **Auto Increment** | `INT AUTO_INCREMENT` | `SERIAL` |
| **Big Auto Increment** | `BIGINT AUTO_INCREMENT` | `BIGSERIAL` |
| **Boolean** | `TINYINT(1)` | `BOOLEAN` |
| **JSON** | `JSON` | `JSONB` |
| **IP Address** | `VARCHAR(45)` | `INET` |
| **Arrays** | `JSON` | `TEXT[]` |
| **Text Search** | `LOWER(col) LIKE LOWER(?)` | `col ILIKE ?` |

## 🔍 Søgefunktionalitet

### Case-insensitive Søgning

**Forkert:**
```php
// Dette virker KUN i PostgreSQL
$sql = "SELECT * FROM users WHERE name ILIKE :search";
```

**Korrekt:**
```php
// Dette virker i BEGGE databaser
$sql = "SELECT * FROM users WHERE " . db_ilike('name', ':search');
```

### Array Søgning

**PostgreSQL:**
```php
$sql = "WHERE :tag = ANY(tags)";
```

**MySQL:**
```php
$sql = "WHERE JSON_CONTAINS(tags, JSON_QUOTE(:tag))";
```

**Database-agnostisk:**
```php
if (DatabaseAbstraction::isPostgreSQL()) {
    $where[] = ":tag = ANY(tags)";
} else {
    $where[] = "JSON_CONTAINS(tags, JSON_QUOTE(:tag))";
}
```

## 📝 Migration System

### Directory Struktur

```
database/migrations/
├── mysql/              # MySQL-specifikke migrations
│   ├── 001_create_base_tables.sql
│   ├── 002_create_opex_budget_tables.sql
│   └── 003_create_views.sql
├── pgsql/              # PostgreSQL-specifikke migrations
│   └── (as needed)
└── common/             # Database-agnostiske migrations
    └── (shared migrations)
```

### Migration Runner

```bash
# Tjek status
php core/migration-runner.php status

# Kør alle pending migrations
php core/migration-runner.php run-all

# Kør specifik migration
php core/migration-runner.php run 001_create_base_tables
```

## ⚡ Performance Optimeringer

### Views (Pre-calculated Data)

Begge databaser bruger views for performance:

```sql
-- v_project_summary: Aggregeret projekt-data
-- v_building_summary: Aggregeret bygnings-data
-- v_element_summary: Aggregeret element-data
-- v_red_flags: Pre-beregnede red flags
-- v_building_opex_summary: OPEX beregninger
-- v_budget_totals: Budget totaler
```

### Indexes

Alle tabeller har passende indexes:

```sql
-- MySQL
CREATE INDEX idx_name ON users(name);

-- PostgreSQL (samme syntax)
CREATE INDEX idx_name ON users(name);
```

## 🚀 Best Practices

### ✅ DO

```php
// Brug database abstraction
$sql = "WHERE " . db_ilike('name', ':search');

// Brug helper-funktioner
$type = DatabaseAbstraction::jsonType();

// Tjek database-type for specifikke features
if (DatabaseAbstraction::isMySQL()) {
    // MySQL-specifik kode
}

// Brug migrations for schema ændringer
php core/migration-runner.php run my_migration
```

### ❌ DON'T

```php
// Hardcode database-specifik syntax
$sql = "WHERE name ILIKE :search"; // Virker kun i PostgreSQL!

// Antag specifik database-type
$sql = "SELECT JSON_EXTRACT(...)"; // MySQL-specifik!

// Modificer tables manuelt
ALTER TABLE users ADD COLUMN...; // Brug migrations!

// Brug PostgreSQL extensions uden check
CREATE EXTENSION IF NOT EXISTS "uuid-ossp"; // Virker ikke i MySQL!
```

## 🔄 Skift Database

### Fra MySQL til PostgreSQL

1. Eksporter data fra MySQL
2. Opdater `.env`: `DB_TYPE=pgsql`
3. Kør PostgreSQL migrations
4. Importer data

### Fra PostgreSQL til MySQL

1. Eksporter data fra PostgreSQL
2. Opdater `.env`: `DB_TYPE=mysql`
3. Kør MySQL migrations
4. Importer data (konverter arrays til JSON)

## 🛠️ Troubleshooting

### MySQL Problemer

**Problem:** Character set fejl
```sql
-- Løsning: Brug utf8mb4
CREATE TABLE ... DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Problem:** Reserved keyword `read`
```sql
-- Løsning: Brug backticks
SELECT `read` FROM notifications;
```

### PostgreSQL Problemer

**Problem:** Extension mangler
```bash
# Løsning: Installer extension
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
```

**Problem:** Case-sensitivity
```sql
-- PostgreSQL er case-sensitive
SELECT * FROM Users; -- Fejl!
SELECT * FROM users; -- OK
```

## 📚 Yderligere Ressourcer

- [Migration System README](../database/migrations/README.md)
- [Database Abstraction Source](../core/database-abstraction.php)
- [Migration Runner Source](../core/migration-runner.php)

## 🤝 Bidrag

Når du tilføjer nye features:

1. Brug altid database abstraction layer
2. Test på BÅDE MySQL og PostgreSQL
3. Opdater migrations for begge databaser
4. Dokumenter database-specifikke ændringer
