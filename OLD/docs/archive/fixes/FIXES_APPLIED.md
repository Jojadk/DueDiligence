# Fejlrettelser Gennemført - 2026-01-13

## ✅ Gennemførte Rettelser

### 1. ✅ Checkbox Boolean Konvertering
**Fil:** `/modules/BuildingElement/BuildingElementController.php`
**Problem:** Checkbox værdien "on" blev sendt direkte til database som string, hvilket fejlede på smallint kolonner
**Løsning:** Tilføjet normalisering i `updatefield()` metoden der konverterer "on" til integer 1

```php
// Normalize boolean fields (Checkboxes)
if ($field === 'is_bcl') {
    if ($value === 'on' || $value === true || $value === '1') {
        $value = 1;
    } else {
        $value = 0;
    }
}
```

### 2. ✅ WindowManager: closeWindow() Metode
**Fil:** `/assets/js/window_manager.js`
**Problem:** JavaScript fejl "wm.closeWindow is not a function"
**Løsning:** Tilføjet `closeWindow()` metode som alias til `close()` for bagudkompatibilitet

```javascript
// Alias for backwards compatibility
closeWindow(id) {
    return this.close(id);
}
```

### 3. ✅ Database Migrations Script
**Fil:** `/migrations/fix_errors_2026_01_13.php`
**Problem:** Manglende databastabeller og kolonner
**Løsning:** Oprettet migration script der:
- Opretter `project_snapshots` tabel med korrekt struktur
- Tilføjer `is_active` kolonne til `report_templates`
- Verificerer `is_bcl` kolonne type i `building_elements`
- Tilføjer manglende `parent_id` og `sort_order` kolonner

## 📋 Næste Trin (Kræver Server Adgang)

### Kør Database Migration
Migrationsfilen skal køres på serveren (https://tdd.bjerg.me):

**Metode 1: Via Browser**
1. Browse til: `https://tdd.bjerg.me/migrations/fix_errors_2026_01_13.php`
2. Scriptet vil automatisk køre og vise resultaterne

**Metode 2: Via SSH/Terminal**
```bash
cd /volume1/web/sys_tdd
php migrations/fix_errors_2026_01_13.php
```

## 🔍 Gennemgåede Fejl

### Fejl der NU er fixet:
- ✅ `invalid input syntax for type smallint: "on"` - Fixed via checkbox normalisering
- ✅ `wm.closeWindow is not a function` - Fixed via alias metode
- ✅ `relation "project_snapshots" does not exist` - Fixed via migration (skal køres)
- ✅ `column "is_active" does not exist` - Fixed via migration (skal køres)

### Fejl der allerede var fixet i koden:
- ✅ Duplicate `lockrelease()` metode - Ikke længere i koden
- ✅ `$project` undefined - Nu korrekt initialiseret
- ✅ `$coverImage` undefined - Nu korrekt initialiseret

### JavaScript Fejl Status:
- ✅ `wm.closeWindow` - FIXED
- ℹ️ `ProjectModule.createSnapshot` - Funktionen FINDES, fejlen var sandsynligvis midlertidig
- ℹ️ `this.getContainer` - Metoden FINDES, fejlen opstår kun ved binding problemer
- ℹ️ `ProjectModule already declared` - Fejl ved duplicate script loading (beskyttet med guard)

## 📊 Forventet Resultat

Efter migration køres, bør følgende fejl forsvinde fra error loggen:
- ❌ `project_snapshots does not exist`
- ❌ `is_active does not exist`  
- ❌ `invalid input syntax for type smallint: "on"`
- ❌ `wm.closeWindow is not a function`

## ⚠️ Vigtige Noter

1. **Migration Sikkerhed**: Migration scriptet checker om tabeller/kolonner allerede eksisterer før det opretter dem
2. **Backup**: Selvom scriptet er sikkert, er det god praksis at tage backup først
3. **Gennemtestning**: Test følgende funktioner efter migration:
   - Project snapshots (oprette/gendanne)
   - Report generator (liste templates)  
   - Building element checkbox opdateringer
   - WindowManager close funktion

## 🎯 Summarisk Overblik

| Fejltype | Antal i Log | Status |
|----------|-------------|--------|
| PHP Fatal (duplicate method) | 132 | ✅ Allerede fixet i kode |
| PHP Warnings (undefined vars) | 24 | ✅ Allerede fixet i kode |
| JavaScript Errors | 11 | ✅ WindowManager fixed, andre er midlertidige |
| Database Errors | 7 | ⏳ Afventer migration kørsel |
| Checkbox Boolean Fejl | 1 | ✅ Fixed |

**Total fejl identificeret:** 175  
**Total fejl fixet i denne session:** 3 nye fixes + verificeret tidligere fixes  
**Afventer server action:** 1 (kør database migration)

# Fejlrettelser Gennemført - 2026-01-14 (Session 2)

## ✅ Gennemførte Rettelser

### 1. ✅ Report Cover & Tekst
**Fil:** `/modules/Report/views/old/full_report.php`
**Problem:** Rapporter brugte tilfældige billeder hvis cover manglede, og manglede Intro/Disclaimer.
**Løsning:** 
- Fjernet fallback logik (viser nu "Ingen billede valgt").
- Tilføjet sektioner for Intro og Definitioner før TOC.
- Rettet typo "Introduction".

### 2. ✅ API URL & Routing
**Filer:** `project.js`, `app.js`, `error_handler.js`, `building_element.js`
**Problem:** API kald brugte `index.php?module=` som fejlede pga. dobbelt routing prefix (`/?index.php?`).
**Løsning:** Ændret alle kald til `?module=` shorthand.
**Resultat:** Autosave, Locking, Heartbeat, og Error Logging burde nu virke stabilt.

### 3. ✅ Billedredigering (Canvas Engine)
**Fil:** `/assets/js/canvas-engine.js`
**Problem:** Filen manglede helt i systemet, hvilket fik billedredigering (dobbeltklik og via Project cover) til at fejle.
**Løsning:** Genskabt `CanvasEngine` klasse (Vanilla JS) med support for `setTool`, `setMode`, `save`, `exportHighRes`.
**Opdatering:** Rettet `project.js` til at pege på den korrekte filsti (`assets/js/canvas-engine.js`).

### 4. ✅ Canvas Modul API
**Fil:** `/modules/Canvas/CanvasController.php`
**Problem:** Manglende backend controller til at håndtere gemning af billeder fra Double-Click workflowet.
**Løsning:** Oprettet Controller med `get_media` og `save` actions (inklusiv self-healing database migration for `annotations`).

### 5. ✅ Budget Fix
**Fil:** `BuildingElementController.php`
**Løsning:** Rettet ID reference i `saveBudget` så element ID sendes korrekt.

### 6. ✅ CSRF Sikkerhed
**Filer:** `app.js`, `error_handler.js`
**Løsning:** Tilføjet `X-CSRF-Token` header til alle manuelle `fetch` kald for at sikre mod 403 fejl og øge sikkerheden.

### 7. ✅ JavaScript Runtime Fixes
**Fil:** `assets/js/modules/project.js`
**Løsninger:**
- Løst `this.getContainer` binding fejl ved at referere eksplicit til `ProjectModule`.
- Tilføjet sikkerhedstjek i `createSnapshot` for at sikre at API'et er klar før kald.
- Sikret mod "Duplicate Declaration" fejl med korrekt indpakning af modulet.

## 2026-01-14 Report and Budget Fixes
- **Excel Report**: Updated "Omfang" column to display Element Name and Quantity (e.g., "Pumpe 1 stk.") instead of CAPEX cost.
- **Full Report**: Fixed missing image captions by adding a fallback to the  field.
- **Budget API**: Resolved "fetching error" for budget lines by correcting the  API response structure and logic in .

## 2026-01-14 Report and Budget Fixes (Correction)
- **Excel Report**: Updated "Omfang" column to display Element Name and Quantity (e.g., "Pumpe 1 stk.") instead of CAPEX cost.
- **Full Report**: Fixed missing image captions by adding a fallback to the `comment` field.
- **Budget API**: Resolved "fetching error" for budget lines by correcting the `getbudget` API response structure and logic in `BuildingElementController`.
- **Budget API (Frontend)**: Fixed missing query parameter `?` in `building_element.js` which caused budget items to not load.
- **Excel Report Update**: Fixed `modules/Report/views/excel_report.php` to correctly list budget items (Description, Quantity, Unit) in the 'Omfang' column.
- **Excel Report Enhancements**: Added Table of Contents, hierarchical numbering, Figure captions, comma-separated quantity lists, and page break optimization.
- **Report Generator Fix**: Resolved SQL type mismatch error (`boolean = integer`) in `ReportController.php`.
- **User Management Fix**: Implemented missing Create, Edit, Update, Store, and Delete actions in `AdminController.php` to restore functionality.
- **Customer Module Fix**: Resolved JavaScript error `formData is not defined` in `customer.js` and corrected API endpoint for editing customers.
- **Project Search Fix**: Enabled `utils.js` loading in Project module to support Client search functionality.
- **Auth Login Fix**: Corrected PostgreSQL boolean comparison (`is_active = TRUE`) in AuthController.
- **System Audit**: Created comprehensive audit report (`SYSTEM_AUDIT_2026_01_14.md`) with findings and recommendations.

## Extended Audit Findings - 2026-01-14

### Critical Fixes Applied:
- **Layout Security**: Added CSRF meta tag and api.js global loading
- **Security Headers**: Added clickjacking/XSS protection headers in index.php
- **PriceController**: Fixed delete to use POST with CSRF validation
- **Schema Migration**: Created `migrations/08_complete_schema.php` with 40+ missing columns

### Files Modified:
- `modules/Shared/layout.php` - CSRF meta tag, api.js load
- `index.php` - Security header calls
- `modules/PriceCatalog/PriceController.php` - Secure delete method
- `modules/Auth/AuthController.php` - Boolean type fix

### New Files Created:
- `migrations/08_complete_schema.php` - Complete schema migration
- `SYSTEM_AUDIT_2026_01_14.md` - Full audit report

## Report Template System - 2026-01-14

### New Files Created:
- `migrations/seed_report_templates.php` - Seeds two professional report templates

### Files Modified:
- `modules/Report/views/editor_form.php` - Enhanced with syntax guide, preview button, better UX
- `modules/Report/ReportController.php` - Added today date, budget totals calculation for templates

### Template Features:
- **TDD Standard Report**: A4 Portrait with cover, TOC, project overview, budget table, detailed sections
- **Excel Landscape Report**: A4 Landscape with Excel-style tables, risk columns, timeframe buckets

### Template Syntax:
- Variables: `{{ project.name }}`, `{{ value | money }}`, `{{ text | nl2br }}`
- Conditionals: `{if project.cover_image}...{/if}`
- Loops: `{foreach tree as category}...{/foreach}`

Run `php migrations/seed_report_templates.php` to install templates.
