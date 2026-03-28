# API Router Guide - Central API Request Routing

## Overview

The `core/api-router.php` provides centralized API routing for all module endpoints with consistent error handling, response formatting, and security features.

## Features

### 1. **Centralized Routing**
- Single entry point for all API requests
- Automatic module detection and loading
- Function name validation and security checks
- Consistent error responses

### 2. **Error Handling**
- Try-catch wrapper around all handlers
- Automatic error logging
- Development mode debugging
- Stack traces in development

### 3. **Request Timing & Metadata**
- Execution time tracking
- Request metadata (module, action, user)
- Performance monitoring

### 4. **Security**
- Module and action name validation (prevents injection)
- CSRF token validation helpers
- Permission checking helpers
- Input sanitization wrappers

### 5. **Response Consistency**
- Standardized JSON output format
- HTTP status code mapping
- Success/error response patterns
- Optional metadata in development

## Architecture

```
┌─────────────────┐
│   API Request   │
│  api_new.php    │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────┐
│   Central API Router        │
│  core/api-router.php        │
│                             │
│  - Validate module/action   │
│  - Load module API          │
│  - Call handler function    │
│  - Format response          │
│  - Error handling           │
└────────┬────────────────────┘
         │
         ▼
┌───────────────────────────┐
│  Module API Handler       │
│  modules/{module}/api.php │
│                           │
│  function handle_action() │
│  {                        │
│    // Business logic      │
│    return [...];          │
│  }                        │
└───────────────────────────┘
```

## Usage

### Basic Routing

```php
<?php
require_once __DIR__ . '/core/api-router.php';

// Route request from GET/POST parameters
$user = current_user();
route_api_from_request($user);
```

### Manual Routing

```php
<?php
// Route specific module/action
$result = route_api_request('project', 'get_list', $user, true);
if ($result['success']) {
    // Handle result
}
```

### Module API Handler

Create handlers in `modules/{module}/api.php`:

```php
<?php
/**
 * Get list of projects
 * GET ?module=project&action=get_list
 */
function handle_get_list(array $user): array {
    // Validate parameters
    $params = validate_api_params([
        'search' => ['string', 'GET', false, ''],
        'limit' => ['int', 'GET', false', 50]
    ]);

    // Check permissions
    if ($error = require_api_project_access($user, $projectId, 'viewer')) {
        return $error;
    }

    // Business logic
    $projects = db_fetch_all("...", $params);

    return api_success([
        'projects' => $projects,
        'total' => count($projects)
    ]);
}
```

## Helper Functions

### 1. **route_api_request()**
Routes API request to module handler.

```php
route_api_request(
    string $module,    // Module name
    string $action,    // Action name
    array $user,       // Current user
    bool $returnResult = false  // Return vs output
): array|void
```

### 2. **route_api_from_request()**
Routes based on GET/POST parameters.

```php
route_api_from_request(array $user): void
```

### 3. **validate_api_params()**
Validates and returns parameters or outputs error.

```php
$params = validate_api_params([
    'project_id' => ['int', 'GET', true],
    'name' => ['string', 'POST', true],
    'status' => ['string', 'POST', false, 'active']
]);
```

### 4. **require_api_csrf()**
Validates CSRF token or outputs error.

```php
if ($error = require_api_csrf()) {
    return $error;
}
```

### 5. **require_api_project_access()**
Validates project access or outputs error.

```php
if ($error = require_api_project_access($user, $projectId, 'editor')) {
    return $error;
}
```

### 6. **execute_api_transaction()**
Executes database transaction with error handling.

```php
return execute_api_transaction(function() use ($data) {
    $id = db_insert('projects', $data);
    return ['id' => $id];
}, 'Project created', 'Failed to create project');
```

## Response Format

### Success Response

```json
{
  "success": true,
  "data": { ... },
  "_meta": {
    "module": "project",
    "action": "get_list",
    "handler": "handle_get_list",
    "execution_time": "45.23ms"
  }
}
```

### Error Response

```json
{
  "success": false,
  "error": "Invalid parameters",
  "_debug": {
    "message": "Missing required field: project_id",
    "file": "/path/to/file.php",
    "line": 123,
    "trace": [ ... ]
  }
}
```

## Error Handling

### Automatic Error Logging

All exceptions are automatically logged:

```php
try {
    $result = $handler($user);
} catch (Exception $e) {
    log_error("API Error [$module.$action]: " . $e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'user_id' => $user['id']
    ]);
}
```

### Development Mode Debugging

Set `APP_ENV=development` for detailed error information:

```php
define('APP_ENV', 'development');
```

Response will include:
- Exception message
- File and line number
- Stack trace
- Request parameters

## Security Features

### 1. **Input Validation**

Module and action names are validated with regex:

```php
if (!preg_match('/^[a-z_]+$/', $module)) {
    return api_error('Invalid module name');
}
```

### 2. **CSRF Protection**

```php
// Require CSRF token for POST/PUT/DELETE
if ($error = require_api_csrf()) {
    return $error;
}
```

### 3. **Permission Checking**

```php
// Check project access before operations
if ($error = require_api_project_access($user, $projectId, 'editor')) {
    return $error;
}
```

### 4. **Rate Limiting** (via api-helpers.php)

```php
if ($error = api_require_rate_limit($user['id'], 60, 60)) {
    return $error;
}
```

## Performance

### Request Timing

Execution time is tracked and included in response metadata:

```json
{
  "success": true,
  "_meta": {
    "execution_time": "23.45ms"
  }
}
```

### Caching (Future)

Response caching can be added:

```php
function handle_get_list(array $user): array {
    $cacheKey = "project_list:{$user['id']}";

    if ($cached = cache_get($cacheKey)) {
        return $cached;
    }

    $result = /* ... fetch data ... */;
    cache_set($cacheKey, $result, 300); // 5 minutes

    return $result;
}
```

## Migration Guide

### Old Pattern (Direct API)

```php
// modules/project/index.php
if ($action === 'get_list') {
    $projects = db_fetch_all("SELECT * FROM projects");
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'projects' => $projects]);
    exit;
}
```

### New Pattern (API Router)

```php
// modules/project/api.php
function handle_get_list(array $user): array {
    $params = validate_api_params([
        'search' => ['string', 'GET', false, '']
    ]);

    $projects = db_fetch_all("SELECT * FROM projects WHERE name ILIKE :search", [
        'search' => "%{$params['search']}%"
    ]);

    return api_success(['projects' => $projects]);
}

// modules/project/index.php
require_once __DIR__ . '/api.php';
if ($isApiRequest) {
    route_api_request('project', $action, $user);
}
```

## Available Modules

To see all available modules and their actions:

```php
$modules = get_available_api_modules();
foreach ($modules as $module) {
    echo "{$module['name']}: " . implode(', ', $module['actions']) . "\n";
}
```

## Best Practices

### 1. **One Handler Per Action**

```php
// ✅ Good
function handle_get_list() { ... }
function handle_create() { ... }
function handle_update() { ... }

// ❌ Bad - Don't use switch statements
function handle($action) {
    switch ($action) { ... }
}
```

### 2. **Always Return Arrays**

```php
// ✅ Good
return api_success(['data' => $result]);

// ❌ Bad
echo json_encode(['success' => true]);
exit;
```

### 3. **Use Helper Functions**

```php
// ✅ Good
$params = validate_api_params([...]);
if ($error = require_api_csrf()) return $error;

// ❌ Bad
if (!isset($_POST['name'])) {
    return ['success' => false, 'error' => 'Missing name'];
}
csrf_require();
```

### 4. **Consistent Response Format**

```php
// ✅ Good
return api_success(['items' => $data, 'total' => $count]);
return api_error('Not found');

// ❌ Bad
return ['status' => 'ok', 'result' => $data];
return ['error_message' => 'Not found'];
```

### 5. **Use Transactions**

```php
// ✅ Good
return execute_api_transaction(function() {
    $id = db_insert('projects', $data);
    db_insert('audit_log', ['project_id' => $id]);
    return ['id' => $id];
});

// ❌ Bad
$id = db_insert('projects', $data);
db_insert('audit_log', ['project_id' => $id]);
return ['success' => true, 'id' => $id];
```

## Testing

### Unit Testing Handler

```php
function test_handle_get_list() {
    $user = ['id' => 1, 'role' => 'admin'];
    $_GET = ['search' => 'test', 'limit' => 10];

    $result = handle_get_list($user);

    assert($result['success'] === true);
    assert(isset($result['projects']));
}
```

### Integration Testing Router

```php
function test_api_router() {
    $_GET = ['module' => 'project', 'action' => 'get_list'];
    $user = current_user();

    ob_start();
    route_api_from_request($user);
    $output = ob_get_clean();

    $result = json_decode($output, true);
    assert($result['success'] === true);
}
```

## Troubleshooting

### Common Issues

**Issue: "Module not found"**
- Ensure `modules/{module}/api.php` exists
- Check module name spelling
- Verify MODULES_DIR constant is defined

**Issue: "Action not found"**
- Ensure function `handle_{action}` exists in module API
- Check function name spelling (must be lowercase with underscores)
- Verify function is defined before router call

**Issue: "Invalid module name"**
- Module names must be lowercase letters and underscores only
- No numbers, spaces, or special characters
- Regex pattern: `/^[a-z_]+$/`

**Issue: CSRF token errors**
- Ensure CSRF token is included in POST requests
- Check `window.CSRF_TOKEN` is available in frontend
- Verify session is active

## Examples

### Complete CRUD Module

```php
<?php
// modules/task/api.php

require_once __DIR__ . '/../../core/api-helpers.php';

function handle_get_list(array $user): array {
    $params = validate_api_params([
        'project_id' => ['int', 'GET', true],
        'status' => ['string', 'GET', false, '']
    ]);

    if ($error = require_api_project_access($user, $params['project_id'], 'viewer')) {
        return $error;
    }

    $where = ['project_id = :project_id'];
    $sqlParams = ['project_id' => $params['project_id']];

    if ($params['status']) {
        $where[] = 'status = :status';
        $sqlParams['status'] = $params['status'];
    }

    $tasks = db_fetch_all(
        "SELECT * FROM tasks WHERE " . implode(' AND ', $where) . " ORDER BY created_at DESC",
        $sqlParams
    );

    return api_success(['tasks' => $tasks]);
}

function handle_create(array $user): array {
    if ($error = require_api_csrf()) return $error;

    $params = validate_api_params([
        'project_id' => ['int', 'POST', true],
        'title' => ['string', 'POST', true],
        'description' => ['string', 'POST', false, '']
    ]);

    if ($error = require_api_project_access($user, $params['project_id'], 'editor')) {
        return $error;
    }

    return execute_api_transaction(function() use ($params, $user) {
        $id = db_insert('tasks', [
            'project_id' => $params['project_id'],
            'title' => $params['title'],
            'description' => $params['description'],
            'created_by_user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        log_activity('task_created', 'task', $id);

        return ['id' => $id];
    }, 'Task created', 'Failed to create task');
}

function handle_update(array $user): array {
    if ($error = require_api_csrf()) return $error;

    $params = validate_api_params([
        'id' => ['int', 'POST', true],
        'title' => ['string', 'POST', false],
        'status' => ['string', 'POST', false]
    ]);

    // Get task to check project access
    $task = db_fetch("SELECT * FROM tasks WHERE id = :id", ['id' => $params['id']]);
    if (!$task) {
        return api_error('Task not found');
    }

    if ($error = require_api_project_access($user, $task['project_id'], 'editor')) {
        return $error;
    }

    return execute_api_transaction(function() use ($params, $task) {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($params['title'])) $updateData['title'] = $params['title'];
        if (isset($params['status'])) $updateData['status'] = $params['status'];

        db_update('tasks', $updateData, 'id = :id', ['id' => $params['id']]);

        log_activity('task_updated', 'task', $params['id']);

        return [];
    }, 'Task updated', 'Failed to update task');
}

function handle_delete(array $user): array {
    if ($error = require_api_csrf()) return $error;

    $params = validate_api_params([
        'id' => ['int', 'POST', true]
    ]);

    // Get task to check project access
    $task = db_fetch("SELECT * FROM tasks WHERE id = :id", ['id' => $params['id']]);
    if (!$task) {
        return api_error('Task not found');
    }

    if ($error = require_api_project_access($user, $task['project_id'], 'editor')) {
        return $error;
    }

    return execute_api_transaction(function() use ($params) {
        db_delete('tasks', 'id = :id', ['id' => $params['id']]);
        log_activity('task_deleted', 'task', $params['id']);

        return [];
    }, 'Task deleted', 'Failed to delete task');
}
```

## Conclusion

The API Router provides:
- ✅ Consistent error handling
- ✅ Centralized security
- ✅ Reduced boilerplate
- ✅ Better maintainability
- ✅ Improved debugging
- ✅ Standardized responses
- ✅ Performance monitoring

Use it for all new API endpoints and migrate existing endpoints gradually.
