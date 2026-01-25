# Template Parser Strategy

## Overview
This document describes the official template parsing strategy for the DueDiligence system.

## Official Implementation

**File:** `/core/advanced_template_parser.php`

**Class:** `AdvancedTemplateParser`

**Used by:** Report Builder module (`modules/report_builder/api.php`)

## Features

The Advanced Template Parser provides:
- Variable substitution with filters
- Conditional statements (`{if}`, `{else}`, `{endif}`)
- Ternary operators (`{condition ? value1 : value2}`)
- While loops (`{while}`)
- Nested template support
- Error handling with silent fail pattern
- Performance tracking

## Usage Example

```php
require_once __DIR__ . '/core/advanced_template_parser.php';

$data = [
    'project' => ['name' => 'My Project', 'status' => 'active'],
    'buildings' => [/* ... */],
    'elements' => [/* ... */]
];

$context = [
    'project_id' => 123,
    'customer_id' => 456,
    'template_id' => 789
];

$parser = new AdvancedTemplateParser($data, $context);
$rendered = $parser->parse($templateContent);
```

## Template Syntax

### Variables
```
{project.name}
{project.name|uppercase}
{project.total_capex|number_format}
```

### Conditionals
```
{if project.status == 'active'}
    Project is active
{else}
    Project is inactive
{endif}
```

### Ternary
```
{project.status == 'active' ? 'Active' : 'Inactive'}
```

### Loops
```
{while buildings as building}
    {building.name}
{endwhile}
```

## Removed Implementations

Previously, there were 3 template parser implementations:
1. ✅ **advanced_template_parser.php** - OFFICIAL (kept)
2. ❌ **template_parser.php** - Removed (unused, simpler version)
3. ❌ **template_compiler.php** - Removed (unused, compile-to-PHP version)

These were removed on 2026-01-25 to reduce codebase complexity.

## Future Development

All template parsing development should focus on `AdvancedTemplateParser`.

If additional features are needed:
- Add them to the existing `AdvancedTemplateParser` class
- Maintain backward compatibility
- Update this documentation

## Performance Considerations

The Advanced Template Parser includes:
- Parse time tracking
- Error context preservation
- Silent fail pattern (never crashes, always logs)
- JSONB-based error logging for debugging

## Related Files

- `/core/advanced_template_parser.php` - Parser implementation
- `/core/silent_fail_handler.php` - Error handling
- `/modules/report_builder/api.php` - Primary usage
- `/database/master_setup.sql` - Silent fail logging schema
