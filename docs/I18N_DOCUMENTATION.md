# i18n (Internationalization) System

## Overview
The TDD system now supports multiple languages (Danish and English) through the `i18n.js` module.

## Supported Languages
- 🇩🇰 **Danish (da)** - Default language
- 🇬🇧 **English (en)** - Secondary language

## Usage

### In JavaScript
```javascript
// Get translation
const text = i18n.t('common.save'); // Returns "Gem" or "Save"

// With replacements
const msg = i18n.t('error.min_length', [5]); // "Minimum 5 tegn påkrævet"

// Change language
i18n.setLanguage('en');

// Get current language
const lang = i18n.getLanguage(); // Returns 'da' or 'en'
```

### In HTML
Use data attributes for automatic translation:

```html
<!-- Text content -->
<button data-i18n="common.save">Gem</button>

<!-- Placeholder -->
<input data-i18n-placeholder="common.search">

<!-- Title attribute -->
<span data-i18n-title="common.help"></span>

<!-- With replacements -->
<p data-i18n="error.min_length" data-i18n-replace='[5]'></p>
```

## Translation Keys

### Common
- `common.save`, `common.cancel`, `common.delete`, `common.edit`
- `common.close`, `common.loading`, `common.error`, `common.success`
- `common.search`, `common.add`, `common.update`, `common.create`

### Navigation
- `nav.projects`, `nav.customers`, `nav.dashboard`
- `nav.settings`, `nav.logout`, `nav.profile`

### Building Elements
- `element.name`, `element.location`, `element.description`
- `element.observation`, `element.internal_notes`
- `element.loading_content`, `element.load_failed`

### Projects
- `project.name`, `project.client`, `project.status`
- `project.save_success`, `project.delete_confirm`

### Errors
- `error.network`, `error.system`, `error.not_found`
- `error.permission_denied`, `error.validation`
- `error.required`, `error.min_length`

## Adding New Translations

Edit `/assets/js/i18n.js`:

```javascript
translations: {
    da: {
        'my.new.key': 'Min danske tekst',
    },
    en: {
        'my.new.key': 'My English text',
    }
}
```

## Language Persistence
The selected language is saved to `localStorage` and persists across sessions.

## Event System
Listen for language changes:

```javascript
document.addEventListener('languageChanged', (e) => {
    console.log('Language changed to:', e.detail.language);
    // Update your component
});
```

## Current Implementation Status

✅ **Completed:**
- Core i18n engine with fallback support
- Language switcher in topbar (🇩🇰 DA / 🇬🇧 EN buttons)
- Translations in BuildingElement module
- localStorage persistence
- Auto-update on language switch

⏳ **TODO:**
- Add translations to all PHP templates
- Translate modal content
- Translate form labels
- Translate error messages from backend
- Add more languages (Swedish, German, etc.)

## Best Practices

1. **Always use translation keys** - Never hardcode text in UI
2. **Use semantic keys** - `element.name` not `element_name_label`
3. **Keep translations short** - UI space is limited
4. **Test both languages** - Switch and verify all screens
5. **Use placeholders** for dynamic content - `{0}`, `{1}`, etc.

## Integration with Existing Code

The i18n module is loaded **before** other scripts in `layout.php`:
```html
<script src="assets/js/i18n.js"></script>
<script src="assets/js/modules/utils.js"></script>
<script src="assets/js/core.js"></script>
```

This ensures `i18n` is available globally before any module loads.
