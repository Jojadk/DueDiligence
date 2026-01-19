# API Code Consolidation

**Status**: In Progress
**Created**: 2026-01-19
**Goal**: Reduce PHP API code duplication by 2,000-3,000 lines

## Problem Statement

Current state:
- **9,072 lines** of PHP code across 16 module API files
- **Heavy duplication** of common patterns:
  - Input validation and sanitization
  - Permission checking
  - Error response formatting
  - Transaction management
  - CRUD operations

### Identified Patterns

Across all module APIs, these patterns repeat:

#### 1. Input Sanitization (Every handler function)
```php
$id = sanitize_int($_GET['id'] ?? 0);
$name = sanitize_string($_POST['name'] ?? '');
$email = sanitize_email($_POST['email'] ?? '');
```

**Occurrence**: ~100+ times across all APIs
**Lines per occurrence**: 3-10 lines
**Total duplication**: ~400-500 lines

#### 2. Permission Checking
```php
if (!can_access_project($user, $projectId, 'viewer')) {
    return ['success' => false, 'error' => 'Ingen adgang'];
}
```

**Occurrence**: ~80+ times
**Lines per occurrence**: 3-5 lines
**Total duplication**: ~300 lines

#### 3. Entity Not Found Check
```php
$entity = db_fetch("SELECT * FROM table WHERE id = :id", ['id' => $id]);
if (!$entity) {
    return ['success' => false, 'error' => 'Not found'];
}
```

**Occurrence**: ~70+ times
**Lines per occurrence**: 4-6 lines
**Total duplication**: ~350 lines

#### 4. Transaction Management
```php
db_begin_transaction();
try {
    // operations
    db_commit();
    return ['success' => true, ...];
} catch (Exception $e) {
    db_rollback();
    return ['success' => false, 'error' => '...'];
}
```

**Occurrence**: ~50+ times
**Lines per occurrence**: 8-12 lines
**Total duplication**: ~500 lines

#### 5. CSRF Validation
```php
csrf_require();
```

**Occurrence**: Every POST/PUT/DELETE handler (~60 times)
**Lines per occurrence**: 1 line + try/catch (4 lines when handled properly)
**Total duplication**: ~60 lines

#### 6. Activity Logging
```php
log_activity('action', 'entity_type', $entityId);
```

**Occurrence**: ~40+ times
**Lines per occurrence**: 1 line (but often missing where it should be)

#### 7. Response Formatting
```php
return ['success' => true, 'data' => $data, 'message' => '...'];
return ['success' => false, 'error' => '...'];
```

**Occurrence**: Every handler function (~150+ times)
**Lines per occurrence**: 1-2 lines
**Total duplication**: ~200 lines

**TOTAL IDENTIFIED DUPLICATION**: ~2,300 lines

## Solution: API Helper Library

Created `core/api-helpers.php` with consolidated helper functions.

### Helper Functions

#### 1. `api_validate_params(array $rules): array`
Validates and sanitizes multiple parameters in one call.

**Before** (8 lines):
```php
$projectId = sanitize_int($_POST['project_id'] ?? 0);
$name = sanitize_string($_POST['name'] ?? '');
$email = sanitize_email($_POST['email'] ?? '');

if (!$projectId || !$name) {
    return ['success' => false, 'error' => 'Required fields missing'];
}
```

**After** (5 lines):
```php
$validation = api_validate_params([
    'project_id' => ['int', 'POST', true],
    'name' => ['string', 'POST', true],
    'email' => ['email', 'POST', false, '']
]);

if (!$validation['success']) {
    return api_error($validation['errors']);
}

extract($validation['data']);
```

**Savings**: 3 lines per handler, better error messages

#### 2. `api_get_entity(string $table, int $id, array $user, ?callable $permissionCheck): array`
Fetches entity with automatic existence and permission checks.

**Before** (10 lines):
```php
$id = sanitize_int($_GET['id'] ?? 0);
if (!$id) {
    return ['success' => false, 'error' => 'ID missing'];
}

$entity = db_fetch("SELECT * FROM table WHERE id = :id", ['id' => $id]);
if (!$entity) {
    return ['success' => false, 'error' => 'Not found'];
}

if (!can_access_project($user, $entity['project_id'], 'viewer')) {
    return ['success' => false, 'error' => 'No access'];
}
```

**After** (2 lines):
```php
$result = api_get_entity('table', $_GET['id'] ?? 0, $user,
    fn($e, $u) => can_access_project($u, $e['project_id'], 'viewer'));

if (!$result['success']) return $result;
$entity = $result['data'];
```

**Savings**: 8 lines per handler

#### 3. `api_transaction(callable $operation, string $successMsg, string $errorMsg): array`
Automatic transaction management with error handling.

**Before** (12 lines):
```php
db_begin_transaction();
try {
    $id = db_insert('table', $data);
    db_commit();
    log_activity('created', 'entity', $id);
    return ['success' => true, 'id' => $id, 'message' => 'Created'];
} catch (Exception $e) {
    db_rollback();
    return ['success' => false, 'error' => 'Failed to create'];
}
```

**After** (5 lines):
```php
return api_transaction(function() use ($data) {
    $id = db_insert('table', $data);
    log_activity('created', 'entity', $id);
    return ['id' => $id];
}, 'Created', 'Failed to create');
```

**Savings**: 7 lines per handler

#### 4. `api_crud_create/update/delete()`
Complete CRUD operations with one function call.

**Before** (30 lines for create):
```php
function handle_create(array $user): array {
    csrf_require();

    $name = sanitize_string($_POST['name'] ?? '');
    $description = sanitize_string($_POST['description'] ?? '');

    if (!$name) {
        return ['success' => false, 'error' => 'Name required'];
    }

    // Permission check...
    if (!can_access_project($user, $projectId, 'editor')) {
        return ['success' => false, 'error' => 'No access'];
    }

    db_begin_transaction();
    try {
        $data = [
            'name' => $name,
            'description' => $description,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $id = db_insert('entities', $data);
        db_commit();
        log_activity('entity_created', 'entity', $id);

        return ['success' => true, 'id' => $id, 'message' => 'Created'];
    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Failed'];
    }
}
```

**After** (15 lines):
```php
function handle_create(array $user): array {
    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'description' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    return api_crud_create('entities', $validation['data'],
        // Before insert: check permissions
        function($data) use ($user) {
            if (!can_access_project($user, $data['project_id'], 'editor')) {
                return api_error('No access');
            }
        },
        // After insert: log activity
        function($id) {
            log_activity('entity_created', 'entity', $id);
        }
    );
}
```

**Savings**: 15 lines per CRUD handler (×3 = 45 lines per entity)

#### 5. `api_require_project_access()` / `api_require_building_access()`
Simplified permission checking with early returns.

**Before** (5 lines):
```php
if (!can_access_project($user, $projectId, 'editor')) {
    return ['success' => false, 'error' => 'No access'];
}
```

**After** (2 lines):
```php
if ($error = api_require_project_access($user, $projectId, 'editor')) {
    return $error;
}
```

**Savings**: 3 lines per check

### Usage Example: Customer API (Before/After)

**Before** (customer/api.php excerpt - ~80 lines):
```php
function handle_create(array $user): array {
    csrf_require();

    $name = sanitize_string($_POST['name'] ?? '');
    $cvr = sanitize_string($_POST['cvr_number'] ?? '');
    $contact = sanitize_string($_POST['contact_person'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $phone = sanitize_string($_POST['phone'] ?? '');

    if (!$name) {
        return ['success' => false, 'error' => 'Name required'];
    }

    if (!has_permission($user, 'customers.create')) {
        return ['success' => false, 'error' => 'No permission'];
    }

    db_begin_transaction();
    try {
        $data = [
            'name' => $name,
            'cvr_number' => $cvr,
            'contact_person' => $contact,
            'email' => $email,
            'phone' => $phone,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $id = db_insert('customers', $data);
        db_commit();
        log_activity('customer_created', 'customer', $id);

        return ['success' => true, 'customer_id' => $id, 'message' => 'Created'];
    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Failed to create'];
    }
}
```

**After** (using helpers - ~25 lines):
```php
function handle_create(array $user): array {
    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'cvr_number' => ['string', 'POST', false, ''],
        'contact_person' => ['string', 'POST', false, ''],
        'email' => ['email', 'POST', false, ''],
        'phone' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    return api_crud_create('customers', $validation['data'],
        fn($data) => has_permission($user, 'customers.create') ?: api_error('No permission'),
        fn($id) => log_activity('customer_created', 'customer', $id)
    );
}
```

**Savings**: 55 lines (69% reduction)

## Implementation Plan

### Phase 1: Infrastructure (Completed)
- ✅ Create `core/api-helpers.php` with all helper functions
- ✅ Document patterns and usage

### Phase 2: Module Refactoring (Next)
Priority order (by size and duplication):

1. **customer/api.php** (simple CRUD, good example)
2. **building/api.php** (417 lines, moderate complexity)
3. **project/api.php** (506 lines, includes copy functionality)
4. **element/api.php** (701 lines, hierarchical data)
5. **user/api.php** (756 lines, authentication)
6. **Others**: red_flags, budget, report, etc.

### Phase 3: Verification
- Test each refactored module thoroughly
- Ensure no behavioral changes
- Verify all error messages preserved
- Check permission logic unchanged

## Expected Results

### Code Reduction
- **Current**: 9,072 lines
- **Target**: 6,500-7,000 lines
- **Reduction**: 2,000-2,500 lines (22-28%)

### Specific Improvements
- **Consistency**: All APIs use same patterns
- **Maintainability**: Bug fixes in one place affect all modules
- **Readability**: Less boilerplate, clearer business logic
- **Error Handling**: Standardized and improved
- **Security**: Centralized CSRF and permission checks

### Module-by-Module Targets
| Module | Current Lines | Target Lines | Reduction |
|--------|--------------|-------------|-----------|
| image | 1,434 | 1,000 | -434 (30%) |
| template | 925 | 650 | -275 (30%) |
| price_catalog | 794 | 550 | -244 (31%) |
| user | 756 | 550 | -206 (27%) |
| element | 701 | 500 | -201 (29%) |
| report_builder | 672 | 475 | -197 (29%) |
| project | 506 | 350 | -156 (31%) |
| opex | 505 | 350 | -155 (31%) |
| report | 486 | 350 | -136 (28%) |
| budget | 426 | 300 | -126 (30%) |
| building | 417 | 280 | -137 (33%) |
| **Total** | **9,072** | **6,500** | **-2,572 (28%)** |

## Next Steps

1. Refactor customer API as proof of concept
2. Test thoroughly
3. Apply same pattern to building and project APIs
4. Continue with remaining modules
5. Commit incrementally with clear documentation

## Benefits

### Short Term
- Less code to maintain
- Easier to find and fix bugs
- Consistent error messages

### Long Term
- Faster development of new modules
- Easier onboarding for new developers
- Better code quality across the board
- Single source of truth for API patterns

## Notes

- All helpers are backward compatible
- No breaking changes to API responses
- Existing error messages preserved
- Permission logic unchanged
- Can be adopted incrementally (module by module)
