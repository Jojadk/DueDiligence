# Database Migrations

Dette bibliotek indeholder alle database migrations for TDD System.

## 📁 Struktur

```
migrations/
├── README.md                              # Denne fil
├── run_all_migrations.php                 # Master script - kør ALLE migrations
├── 01_add_building_elements_hierarchy.php # Tilføjer hierarki til bygningselementer
├── 02_add_customers_table.php             # Opretter customers tabel
├── 03_update_dates_timestamps.php         # Opdaterer dato/timestamp kolonner
├── fix_errors_2026_01_13.php              # Retter fejl identificeret i error log
└── [deprecated]/                          # Gamle SQL filer (ikke i brug)
    ├── add_building_elements_hierarchy.sql
    ├── add_customers_table.sql
    └── update_dates_timestamps.sql
```

## 🚀 Sådan Køres Migrations

### Metode 1: Kør Alle Migrations (Anbefalet)

**Via Browser:**
```
https://tdd.bjerg.me/migrations/run_all_migrations.php
```

**Via SSH:**
```bash
cd /volume1/web/sys_tdd
php migrations/run_all_migrations.php
```

Dette script:
- ✅ Kører alle migrations i korrekt rækkefølge
- ✅ Springer allerede kørte migrations over
- ✅ Holder styr på hvilke migrations der er kørt
- ✅ Viser detaljeret output for hver migration

### Metode 2: Kør Enkelt Migration

**Via Browser:**
```
https://tdd.bjerg.me/migrations/01_add_building_elements_hierarchy.php
```

**Via SSH:**
```bash
cd /volume1/web/sys_tdd
php migrations/01_add_building_elements_hierarchy.php
```

## 📋 Migration Oversigt

### 01 - Building Elements Hierarchy
**Fil:** `01_add_building_elements_hierarchy.php`
**Formål:** Tilføjer `parent_id` og `sort_order` kolonner til `building_elements` tabellen for at understøtte hierarkisk struktur.

**Ændringer:**
- Tilføjer `parent_id` kolonne (INT NULL)
- Tilføjer `sort_order` kolonne (INT DEFAULT 0)
- Opretter indexes på `parent_id` og `project_id`

### 02 - Customers Table
**Fil:** `02_add_customers_table.php`
**Formål:** Opretter `customers` tabel og linker den til `projects`.

**Ændringer:**
- Opretter `customers` tabel med felter: id, name, email, phone, address, timestamps
- Tilføjer `client_id` kolonne til `projects` tabellen
- Opretter index på `client_id`

### 03 - Date and Timestamp Updates
**Fil:** `03_update_dates_timestamps.php`
**Formål:** Standardiserer dato felter og tilføjer timestamp tracking til alle tabeller.

**Ændringer:**
- Konverterer `construction_year` og `renovation_year` til DATE type i `projects`
- Tilføjer `created_at` og `updated_at` til 13 tabeller der mangler dem

### 04 - Error Fixes (2026-01-13)
**Fil:** `fix_errors_2026_01_13.php`
**Formål:** Retter specifikke fejl identificeret i system error log.

**Ændringer:**
- Opretter `project_snapshots` tabel
- Tilføjer `is_active` kolonne til `report_templates`
- Verificerer `is_bcl` kolonne type i `building_elements`
- Sikrer `parent_id` og `sort_order` eksisterer

## 🔒 Database Forbindelse

Alle migrations bruger følgende forbindelses format:

```php
define('DB_HOST', getenv('DB_HOST') ?: '172.17.0.2');
define('DB_PORT', getenv('DB_PORT') ?: '5432');
define('DB_NAME', getenv('DB_NAME') ?: 'TDD_System');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: 'root');
require_once '/volume1/web/sys_tdd/core/Database.php';
use Core\Database;

$db = Database::getInstance();
```

## 📊 Migration Tracking

Systemet holder automatisk styr på hvilke migrations der er kørt ved hjælp af `migrations` tabellen:

```sql
CREATE TABLE migrations (
    id SERIAL PRIMARY KEY,
    migration_name VARCHAR(255) NOT NULL UNIQUE,
    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## ⚠️ Vigtige Noter

1. **Idempotent:** Alle migrations er designet til at kunne køres flere gange uden problemer (bruger `IF NOT EXISTS`, `ADD COLUMN IF NOT EXISTS`, etc.)

2. **Rækkefølge:** Migrations skal køres i numerisk rækkefølge (01, 02, 03...)

3. **Backup:** Det anbefales at tage database backup før større migrations køres

4. **Test:** Test altid migrations i development miljø først

## 🗑️ Deprecated Filer

Følgende SQL filer er konverteret til PHP og kan slettes:
- ❌ `add_building_elements_hierarchy.sql` → Nu: `01_add_building_elements_hierarchy.php`
- ❌ `add_customers_table.sql` → Nu: `02_add_customers_table.php`
- ❌ `update_dates_timestamps.sql` → Nu: `03_update_dates_timestamps.sql`

De gamle SQL filer fungerer ikke længere da de ikke har korrekt database forbindelse.

## 🆘 Fejlhåndtering

Hvis en migration fejler:
1. Læs fejlmeddelelsen nøje
2. Check at database forbindelsen er korrekt
3. Verificer at tabeller/kolonner ikke allerede eksisterer
4. Kontakt udvikler hvis problemet fortsætter

## 📝 Tilføjelse af Nye Migrations

Når du skal tilføje en ny migration:

1. **Navngivning:** Brug format `XX_beskrivelse.php` (f.eks. `04_add_user_roles.php`)
2. **Include Header:** Brug standard database forbindelses blok
3. **Idempotent:** Brug altid `IF NOT EXISTS` / `IF EXISTS` checks
4. **Output:** Echo progress beskeder for brugeren
5. **Error Handling:** Wrap i try/catch blokke
6. **Opdater README:** Tilføj beskrivelse af migration her
7. **Test:** Kør lokalt først

## 📞 Support

Ved spørgsmål eller problemer, kontakt udviklingsteamet.

---
**Sidste opdatering:** 2026-01-13  
**Version:** 1.0
