# TDD System - Code Organization

## JavaScript Module Structure

### Core Files
- **`assets/js/core.js`** - Core application utilities (App object, AJAX, modals, toasts)
- **`assets/js/modules/utils.js`** - Shared module utilities (NEW - reduces duplication)

### Module Files
- **`assets/js/modules/building_element.js`** - Building Element tree navigation, content loading, budget, media
- **`assets/js/modules/project.js`** - Project CRUD, team management, client search
- **`assets/js/modules/customer.js`** - Customer CRUD operations

## Shared Utilities (ModuleUtils)

All modules now use `ModuleUtils` for common patterns:

```javascript
// Get container (window manager or document body)
ModuleUtils.getContainer(element)

// Switch tabs
ModuleUtils.switchTab(tabElement, targetId)

// Form validation
ModuleUtils.validateForm(form, rules)

// Date formatting
ModuleUtils.formatDate(date)

// Debounce function calls
ModuleUtils.debounce(func, wait)

// Safe JSON parsing
ModuleUtils.safeJsonParse(str, fallback)
```

## Error Handling Pattern

All AJAX calls use this pattern:

```javascript
try {
    App.api(url, method, data)
        .then(response => {
            // Handle success
            console.log('Success:', response);
        })
        .catch(err => {
            console.error('API Error:', err);
            App.toast('Fejl ved operation', 'error');
        });
} catch (error) {
    console.error('Exception:', error);
    App.toast('Systemfejl: ' + error.message, 'error');
}
```

## Module Controllers

### BuildingElement
- `index()` - Main view with tree + content
- `showNodeContent()` - AJAX partial content load
- `get()` - Get single element data
- `store()` / `update()` / `delete()` - CRUD
- `lockcheck()` / `lockrelease()` - Real-time locking
- `getbudget()` / `savebudget()` - Budget management
- `addimage()` - Media upload
- `reorder()` - Drag & drop sorting
- `saveVersion()` / `getVersions()` / `restoreVersion()` - Version control

### Project
- `index()` - List all projects
- `store()` / `update()` / `delete()` - CRUD operations

### Customer
- `index()` - List all customers
- `store()` / `update()` / `delete()` - CRUD operations

### Admin
- `search()` - User search
- `searchClients()` - Customer/client search

## Database Optimization

### Indexes Created
- `projects(client_id, deleted_at, status, created_at)`
- `building_elements(project_id, parent_id, sort_order)`
- `element_media(element_id, created_at)`
- `budget_items(element_id)`
- `customers(deleted_at)`
- `project_versions(project_id, created_at)`

### JOIN Optimization
- ProjectController uses JOIN to fetch customer names
- BuildingElementController uses JOIN + COUNT for media counts
- Eliminates N+1 query problems


## Error Handling & Logging

A centralized error handling system captures both backend exceptions and frontend JavaScript errors.

- **Frontend**: `assets/js/error_handler.js` captures global errors and unhandled promise rejections.
- **Backend**: `modules/System/SystemController.php` receives logs and writes to `logs/js_errors.log`.
- **Documentation**: See `LOG_MANAGEMENT.md` for workflow details.

## Internationalization (i18n)

The system now supports multi-language (DA/EN).

- **Implementation**: `assets/js/i18n.js` handles client-side translations.
- **Usage**: Use `data-i18n="key"` in HTML or `i18n.t('key')` in JavaScript.
- **Documentation**: See `I18N_DOCUMENTATION.md` for keys and usage.

## Code Cleanup Completed

### Refactoring
- Removed inline event handlers (`onclick`, etc.) from:
    - `modules/BuildingElement/index.php`
    - `modules/Project/index.php` & `ProjectForm.php`
    - `modules/Customer/index.php` & `CustomerForm.php`
- Implemented **Event Delegation** in all module JS files using `setupEventDelegation()`.
- Centralized global functions into module objects.

### Consolidated Code
- Removed duplicate `getContainer()` and `switchTab()` from modules
- Created shared `ModuleUtils` for common patterns
- Reduced total JavaScript by ~50 lines

## Next Steps

### High Priority
1. Implement Content Security Policy (CSP) headers (Now feasible as inline handlers are removed)
2. Standardize modal system (Current mix of `wm.createWindow` and `App.modal`)

### Medium Priority
3. Create CSS methodology documentation (BEM naming)
4. Add loading state indicators globally
5. Backend API i18n translations

### Low Priority
6. Unit tests for critical modules
7. Performance profiling
8. Accessibility audit
