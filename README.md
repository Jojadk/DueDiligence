# DueDiligence v2.0

**Modern Project Management System for Building Inspections**

## 🎯 Project Overview

DueDiligence v2.0 er et nyt projekt management system bygget med en enkel, template-baseret arkitektur. Systemet bruger JSON/YAML konfiguration til dynamiske forms og modal-baseret brugerinterface.

## 📁 Fil Struktur

```
DueDiligence/
├── index.php                    # Hoved entry point
├── core/
│   ├── core.php                # Konstanter, funktioner, utilities
│   ├── security.php            # CSRF, authentication, input validation
│   └── icons.php               # SVG icon system
├── modules/
│   ├── project/
│   │   ├── index.php          # Project modul logic
│   │   └── template.tpl       # Project template
│   ├── customer/
│   │   ├── index.php
│   │   └── template.tpl
│   ├── building/
│   │   ├── index.php
│   │   └── template.tpl
│   └── admin/
│       ├── index.php
│       └── template.tpl
├── reports/
│   ├── templates/
│   │   ├── report_all.tpl    # Fuld rapport template
│   │   └── report_excel.tpl  # Excel rapport template
│   └── output/                 # Genererede rapporter
├── projects/
│   └── {unitID}/              # Project-specific data
│       ├── uploads/           # Project uploads
│       └── snapshots/         # Project snapshots
├── uploads/                    # Globale uploads
├── logs/                       # System logs
├── config/
│   └── forms.json             # Form struktur konfiguration
├── OLD/                        # Gamle kodebase (backup)
└── .gitignore

```

## ✨ Hovedfunktioner

### 1. JSON/YAML Driven Forms

Alle forms er defineret i `config/forms.json` med følgende struktur:

```json
{
  "forms": {
    "project": {
      "title": "Projekt",
      "fields": [
        {
          "name": "name",
          "label": "Projekt Navn",
          "type": "text",
          "required": true,
          "locked": true
        }
      ],
      "custom_fields": []
    }
  }
}
```

**Felter kan være:**
- `required`: true/false - Påkrævet felt
- `locked`: true/false - Kan ikke slettes, kun flyttes
- `type`: text, textarea, number, date, select, checkbox, email, tel

### 2. Custom Fields System

Custom fields kan tilføjes dynamisk til enhver entity:
- Globale custom fields (gælder alle projekter)
- Project-specifikke custom fields
- Validering baseret på field type
- Automatisk rendering i forms

### 3. Modal-baseret UI

- Hoveds ide med modal windows til CRUD operationer
- Ingen page reloads
- AJAX-baseret datahentning
- Smooth user experience

### 4. Sikkerhed

- ✅ CSRF beskyttelse på alle forms
- ✅ Input sanitization og validation
- ✅ SQL injection beskyttelse (prepared statements)
- ✅ XSS beskyttelse (output escaping)
- ✅ Rate limiting på login
- ✅ Secure file uploads
- ✅ Session security

## 🚀 Installation

### Krav

- PHP >= 8.0
- PostgreSQL >= 14
- Web server (Apache/Nginx)

### Opsætning

1. **Klon repository**
   ```bash
   git clone https://github.com/Jojadk/DueDiligence.git
   cd DueDiligence
   ```

2. **Opret database**
   ```bash
   createdb duediligence
   psql duediligence < OLD/schema_pgsql.sql
   ```

3. **Konfigurer database**

   Sæt environment variables eller rediger `core/core.php`:
   ```bash
   export DB_HOST=localhost
   export DB_NAME=duediligence
   export DB_USER=postgres
   export DB_PASS=yourpassword
   ```

4. **Sæt permissions**
   ```bash
   chmod -R 775 projects/
   chmod -R 775 uploads/
   chmod -R 775 logs/
   ```

5. **Start server**
   ```bash
   php -S localhost:8000
   ```

6. **Åbn i browser**
   ```
   http://localhost:8000
   ```

**Standard login:** `admin` / `admin123` (SKIFT I PRODUKTION!)

## 📝 Brug af Forms

### Render et form

```php
// I et modul (modules/project/index.php)
$formHtml = render_form('project', $data, $errors);
echo $formHtml;
```

### Validere form data

```php
$errors = validate_form('project', $_POST);

if (empty($errors)) {
    // Gem data
    db_insert('projects', $_POST);
} else {
    // Vis fejl
    echo render_form('project', $_POST, $errors);
}
```

### Tilføje custom fields

Custom fields administreres via admin interface eller direkte i databasen:

```sql
INSERT INTO custom_field_definitions (
    entity_type, field_name, field_label, field_type, required, scope
) VALUES (
    'project', 'environmental_class', 'Miljøklasse', 'select', false, 'global'
);
```

## 🎨 Template System

Templates bruger PHP med adskillelse af logik og præsentation:

### Eksempel Template (modules/project/template.tpl)

```php
<?php
// Template variabler er tilgængelige via extract()
?>
<!DOCTYPE html>
<html>
<head>
    <title><?= esc_html($title) ?></title>
</head>
<body>
    <h1><?= echo_icon('folder', 32) ?> <?= esc_html($title) ?></h1>

    <div class="form-container">
        <?= $formHtml ?>
    </div>

    <?= csrf_field() ?>
</body>
</html>
```

### Load Template

```php
load_template(template_path('project', 'template'), [
    'title' => 'Projekt',
    'formHtml' => render_form('project', $data)
]);
```

## 🔧 Core Funktioner

### Database

```php
// Query med results
$projects = db_query("SELECT * FROM projects WHERE active = :active", ['active' => true]);

// Single row
$project = db_fetch("SELECT * FROM projects WHERE id = :id", ['id' => 1]);

// Insert
$projectId = db_insert('projects', ['name' => 'Test', 'status' => 'active']);

// Update
db_update('projects', ['status' => 'completed'], 'id = :id', ['id' => 1]);

// Delete
db_delete('projects', 'id = :id', ['id' => 1]);
```

### Security

```php
// Generate CSRF token
$token = csrf_token();

// Validate CSRF
if (csrf_validate()) {
    // Process form
}

// Sanitize input
$clean = sanitize_string($_POST['name']);
$email = sanitize_email($_POST['email']);
$int = sanitize_int($_POST['id']);

// Upload file
$result = save_upload($_FILES['file'], UPLOADS_DIR);
```

### Utilities

```php
// Format money (Danish)
echo $format_money(1000); // 1.000,00 kr.

// Format date (Danish)
echo $format_date('2026-01-15'); // 15-01-2026

// Generate unique ID
$id = $generate_id(); // Hex string

// Get project directory
$dir = get_project_dir(123); // /path/to/projects/123/
```

## 📊 Database Schema

Brug det eksisterende PostgreSQL schema fra OLD system:

```bash
psql duediligence < OLD/schema_pgsql.sql
```

Schema inkluderer:
- users, roles, permissions
- projects, customers
- building_elements (hierarchical)
- custom_field_definitions & values
- budget_items
- element_media
- activity_logs

## 🔐 Sikkerhed Best Practices

1. **Altid brug CSRF beskyttelse**
   ```php
   <?= csrf_field() ?>
   ```

2. **Escape alt output**
   ```php
   <?= esc_html($userInput) ?>
   <?= esc_attr($attribute) ?>
   ```

3. **Brug prepared statements**
   ```php
   db_query($sql, $params); // Automatisk prepared
   ```

4. **Validate alle inputs**
   ```php
   $errors = validate_form('entity', $_POST);
   ```

5. **Check permissions**
   ```php
   require_permission('project.edit');
   ```

## 📖 Næste Skridt

1. **Implementer modules/**
   - project/index.php & template.tpl
   - customer/index.php & template.tpl
   - building/index.php & template.tpl
   - admin/index.php & template.tpl

2. **Opret report templates**
   - reports/templates/report_all.tpl
   - reports/templates/report_excel.tpl

3. **Byg frontend**
   - CSS styling
   - JavaScript for modals
   - AJAX forms

4. **Test og deploy**

## 🆚 Forskelle fra OLD System

| Feature | OLD | NEW |
|---------|-----|-----|
| Struktur | Complex MVC | Simpel template-baseret |
| Forms | Hardcoded PHP | JSON konfiguration |
| UI | Multi-page | Modal-baseret |
| Database | Mixed (MySQL/PostgreSQL) | PostgreSQL only |
| Routing | Complex Router class | Simpel module loader |
| Config | PHP constants | JSON files |

## 📚 Dokumentation

- **Code Review:** Se `OLD/COMPREHENSIVE_CODE_REVIEW_2026.md`
- **Old Architecture:** Se `OLD/` directory
- **Forms Config:** Se `config/forms.json`

## 🐛 Debugging

Logs gemmes i:
- `logs/error.log` - Application errors
- `logs/php_errors.log` - PHP errors

Enable debug mode i `core/core.php`:
```php
ini_set('display_errors', '1'); // VIGTIGT: Slå fra i produktion!
```

## 📄 Licens

MIT License

---

**Version:** 2.0.0
**Last Updated:** January 15, 2026
**Status:** 🚧 Under Development
