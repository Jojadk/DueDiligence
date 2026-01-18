# Unified Collaboration System

## Overview

The DueDiligence collaboration system has been consolidated from multiple separate files into a unified, efficient system that handles multi-user collaboration with a single API endpoint.

## Architecture

### Before Consolidation

**Frontend:**
- `js/lock-manager.js` (550 lines) - Record locking
- `js/live-update.js` (450 lines) - Live updates
- `js/modal-manager.js` (120 lines) - Modal state

**Backend:**
- `modules/lock/api.php` - Lock management API

**Problems:**
- 3+ separate API calls per sync cycle
- Code duplication across files
- Complex initialization

### After Consolidation

**Frontend:**
- `js/collaboration.js` (785 lines) - Unified system

**Backend:**
- `modules/sync/api.php` - Unified sync API

**Benefits:**
- Single API call per sync (60-70% reduction in requests)
- Cleaner codebase with less duplication
- Simpler initialization and integration
- Better performance

## Unified Sync API

### Single Endpoint Design

Instead of multiple separate calls:
```javascript
// OLD approach (3+ requests)
fetch('/api.php?module=lock&action=get_changes&since=...')
fetch('/api.php?module=lock&action=check&record_type=...')
fetch('/api.php?module=notification&action=get_recent')
```

New unified approach (1 request):
```javascript
// NEW approach (1 request)
fetch('/api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        module: 'sync',
        action: 'get_state',
        client_id: 'client_123',
        record_type: 'budget_template_items',
        record_id: 456,
        since: '2024-01-15 12:30:00',
        active_locks: ['budget_template_items:456:quantity']
    })
})
```

### Response Format

```json
{
  "success": true,
  "timestamp": "2024-01-15 12:30:45",
  "changes": [
    {
      "id": 123,
      "record_type": "budget_template_items",
      "record_id": 456,
      "field_name": "quantity",
      "changed_by_user_id": 5,
      "changed_by_name": "John Doe",
      "changed_at": "2024-01-15 12:30:40",
      "change_type": "update"
    }
  ],
  "locks": {
    "budget_template_items:456:quantity": {
      "locked": true,
      "by_self": false,
      "by_user": "Jane Smith",
      "locked_at": "2024-01-15 12:25:00",
      "is_stale": false
    }
  },
  "notifications": [
    {
      "id": 78,
      "message": "Budget approved",
      "type": "success",
      "created_at": "2024-01-15 12:28:00",
      "is_read": false
    }
  ]
}
```

## Usage

### Basic Usage

```javascript
// Initialize collaboration manager
const collab = new CollaborationManager({
    recordType: 'budget_template_items',
    recordId: templateId,
    onUpdate: (data) => {
        console.log('Changes:', data.changes);
        console.log('Lock status:', data.locks);
        console.log('Notifications:', data.notifications);
    }
});

// Enable locking on form fields
collab.enableLocking('.editable');

// Start sync and heartbeat
collab.start();

// Cleanup on navigation
window.addEventListener('beforeunload', () => collab.destroy());
```

### Budget-Specific Usage

```javascript
// Use specialized budget collaboration
const budgetCollab = new BudgetCollaboration(templateId);

// Listen for total updates
budgetCollab.onTotalUpdate((hierarchy) => {
    console.log('New total:', hierarchy.total);
});

// Start
budgetCollab.enableLocking('.editable');
budgetCollab.start();
```

### Auto-Initialization

The system auto-initializes on pages with the correct data attribute:

```html
<div data-budget-template-id="123">
    <!-- Budget content -->
    <input type="text" class="editable" name="quantity"
           data-record-id="456" data-field="quantity" />
</div>
```

The collaboration system will automatically:
- Initialize on DOMContentLoaded
- Enable locking on `.editable` elements
- Start sync/heartbeat timers
- Clean up on page unload

## Features

### 1. Record Locking

**Automatic field-level locking:**
- User focuses input → acquire lock
- User types → reset inactivity timer
- User blurs input → release lock
- 120 seconds inactivity → auto-release

**Visual feedback:**
```css
.locked-by-self {
    border-left: 3px solid #3B82F6;
    background-color: #EFF6FF;
}

.locked-by-other {
    border-left: 3px solid #EF4444;
    background-color: #FEE2E2;
    cursor: not-allowed;
    opacity: 0.7;
}
```

### 2. Live Updates

**Non-intrusive updates:**
- Polls every 3 seconds (active window)
- Polls every 30 seconds (inactive window/modal)
- Filters out changes to focused fields
- Visual flash animation on update

**Adaptive intervals:**
- Active window + no modal = fast (3s sync, 30s heartbeat)
- Inactive window OR modal = slow (30s sync, 60s heartbeat)
- Immediate sync when window/modal becomes active

### 3. Modal Awareness

**Automatic detection:**
- Bootstrap modals (auto-detected via jQuery events)
- Native `<dialog>` elements (auto-detected via MutationObserver)
- Custom modals (manual integration via ModalManager)

**Manual integration:**
```javascript
// When opening custom modal
ModalManager.open();

// When closing custom modal
ModalManager.close();
```

### 4. Unified Sync

**Single API call includes:**
- Record changes since last sync
- Lock status for active locks
- Recent notifications
- Updated timestamp

**Performance improvement:**
```
Before: 3+ API calls × 20 req/min × 10 users = 600+ req/min
After:  1 API call × 20 req/min × 10 users = 200 req/min
Reduction: 66%
```

With inactive windows:
```
Active tabs: 10 × 20 req/min = 200 req/min
Inactive tabs: 20 × 2 req/min = 40 req/min
Total: 240 req/min (60% reduction from baseline 600)
```

## API Reference

### CollaborationManager

#### Constructor Options

```javascript
new CollaborationManager({
    recordType: 'budget_template_items',  // Required
    recordId: 123,                        // Optional, can be per-field
    apiBase: '/api.php',                  // Optional, default: '/api.php'

    // Intervals
    syncInterval: 3000,                   // Active sync (ms)
    syncIntervalInactive: 30000,          // Inactive sync (ms)
    heartbeatInterval: 30000,             // Active heartbeat (ms)
    heartbeatIntervalInactive: 60000,     // Inactive heartbeat (ms)
    inactivityTimeout: 120000,            // Auto-release (ms)

    // Callbacks
    onUpdate: (data) => {},               // Sync updates received
    onLockAcquired: (element, result) => {}, // Lock acquired
    onLockReleased: (element) => {},      // Lock released
    onLockDenied: (element, result) => {}, // Lock denied
    onNotification: (type, message) => {} // Notification received
})
```

#### Methods

**enableLocking(fields, options)**
```javascript
// Selector string
collab.enableLocking('.editable');

// NodeList or array
collab.enableLocking(document.querySelectorAll('input.editable'));

// Single element
collab.enableLocking(document.getElementById('my-field'));

// With options
collab.enableLocking('.editable', {
    recordId: 456,        // Override default record ID
    fieldName: 'custom'   // Override field name (default: element.name)
});
```

**start()**
```javascript
collab.start(); // Starts sync and heartbeat timers
```

**stop()**
```javascript
collab.stop(); // Stops timers (keeps locks active)
```

**destroy()**
```javascript
collab.destroy(); // Releases all locks and stops timers
```

**syncNow()**
```javascript
await collab.syncNow(); // Force immediate sync
```

### BudgetCollaboration

Extends `CollaborationManager` with budget-specific features.

```javascript
const budget = new BudgetCollaboration(templateId, options);

// Listen for total updates
budget.onTotalUpdate((hierarchy) => {
    console.log('Total:', hierarchy.total);
});

budget.start();
```

**Additional features:**
- Automatic total recalculation on changes
- Item row updates with visual feedback
- Danish number formatting (DKK currency)
- Change notification badges

### ModalManager

Static class for modal state management.

```javascript
ModalManager.open();   // Increment modal count, dispatch event
ModalManager.close();  // Decrement modal count, dispatch event
ModalManager.isOpen(); // Check if any modal is open
ModalManager.reset();  // Reset counter to 0
```

**Events dispatched:**
- `modalOpen` - First modal opened
- `modalClose` - All modals closed

## Backend API

### Endpoints

All endpoints use `module=sync`:

**get_state** - Unified sync endpoint
```php
POST /api.php
{
    "module": "sync",
    "action": "get_state",
    "client_id": "client_123",
    "record_type": "budget_template_items",
    "record_id": 456,
    "since": "2024-01-15 12:30:00",
    "active_locks": ["budget_template_items:456:quantity"]
}
```

**acquire** - Acquire lock
```php
POST /api.php
{
    "module": "sync",
    "action": "acquire",
    "record_type": "budget_template_items",
    "record_id": 456,
    "field_name": "quantity",
    "client_id": "client_123",
    "csrf_token": "..."
}
```

**release** - Release lock
```php
POST /api.php
{
    "module": "sync",
    "action": "release",
    "record_type": "budget_template_items",
    "record_id": 456,
    "field_name": "quantity",
    "client_id": "client_123",
    "csrf_token": "..."
}
```

**heartbeat** - Batch heartbeat for multiple locks
```php
POST /api.php
{
    "module": "sync",
    "action": "heartbeat",
    "client_id": "client_123",
    "locks": [
        {
            "record_type": "budget_template_items",
            "record_id": 456,
            "field_name": "quantity"
        }
    ],
    "csrf_token": "..."
}
```

**log_change** - Manually log a change
```php
POST /api.php
{
    "module": "sync",
    "action": "log_change",
    "record_type": "budget_template_items",
    "record_id": 456,
    "field_name": "quantity",
    "change_type": "update",
    "csrf_token": "..."
}
```

**cleanup** - Remove stale locks (admin only)
```php
POST /api.php
{
    "module": "sync",
    "action": "cleanup"
}
```

## Migration Guide

### From Old Lock System

**Before:**
```javascript
// Old separate managers
const lockManager = new LockManager();
const liveUpdate = new LiveUpdateManager({ ... });

lockManager.enableLocking('.editable', ...);
liveUpdate.start();

window.addEventListener('beforeunload', () => {
    lockManager.destroy();
    liveUpdate.stop();
});
```

**After:**
```javascript
// New unified manager
const collab = new CollaborationManager({
    recordType: 'budget_template_items',
    recordId: templateId,
    onUpdate: (data) => handleUpdates(data)
});

collab.enableLocking('.editable');
collab.start();

// Auto-cleanup on beforeunload (built-in)
```

### Updating API Endpoints

Update any direct API calls from:
```javascript
fetch('/api.php?module=lock&action=...')
```

To:
```javascript
fetch('/api.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        module: 'sync',
        action: '...',
        ...
    })
})
```

### HTML Changes

No HTML changes needed! The system uses the same data attributes:
```html
<input class="editable"
       name="quantity"
       data-record-id="456"
       data-field="quantity" />
```

## Database Schema

The collaboration system uses these tables:

**record_locks** - Active locks
```sql
CREATE TABLE record_locks (
    id SERIAL PRIMARY KEY,
    record_type VARCHAR(50) NOT NULL,
    record_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id),
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    field_name VARCHAR(100),
    client_id VARCHAR(100),
    UNIQUE (record_type, record_id, field_name)
);
```

**record_changes** - Change log
```sql
CREATE TABLE record_changes (
    id SERIAL PRIMARY KEY,
    record_type VARCHAR(50) NOT NULL,
    record_id INTEGER NOT NULL,
    field_name VARCHAR(100),
    changed_by_user_id INTEGER REFERENCES users(id),
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    change_type VARCHAR(20) DEFAULT 'update'
);
```

**Indexes:**
```sql
CREATE INDEX idx_locks_record ON record_locks(record_type, record_id);
CREATE INDEX idx_locks_activity ON record_locks(last_activity);
CREATE INDEX idx_changes_record ON record_changes(record_type, record_id);
CREATE INDEX idx_changes_timestamp ON record_changes(changed_at);
```

## Troubleshooting

### Locks not releasing

**Check:**
1. Are heartbeats being sent? (Check network tab)
2. Is the client_id consistent?
3. Are there JavaScript errors preventing blur handlers?

**Solution:**
```javascript
// Manually release all locks
await collab.destroy();
```

### Sync not updating

**Check:**
1. Is window active? (Check console for "Window inactive")
2. Is modal open? (Check `ModalManager.isOpen()`)
3. Are there API errors? (Check network tab)

**Solution:**
```javascript
// Force immediate sync
await collab.syncNow();
```

### Performance issues

**Check:**
1. How many active tabs? (Each tab polls independently)
2. What are the intervals? (Slow down if needed)
3. Is cleanup running? (Stale locks consume resources)

**Solution:**
```javascript
// Reduce polling frequency
const collab = new CollaborationManager({
    syncInterval: 5000,         // 5 sec instead of 3
    syncIntervalInactive: 60000 // 60 sec instead of 30
});

// Or run cleanup
fetch('/api.php', {
    method: 'POST',
    body: JSON.stringify({
        module: 'sync',
        action: 'cleanup'
    })
});
```

## Best Practices

### 1. Use Unified Sync API

Always use the new `module=sync` endpoints. The old `module=lock` will be deprecated.

### 2. Set Appropriate Intervals

Choose intervals based on your needs:

**High-frequency (stock trading, real-time chat):**
```javascript
syncInterval: 1000,         // 1 sec
syncIntervalInactive: 5000  // 5 sec
```

**Normal (budget editing, forms):**
```javascript
syncInterval: 3000,          // 3 sec (default)
syncIntervalInactive: 30000  // 30 sec (default)
```

**Low-frequency (reports, dashboards):**
```javascript
syncInterval: 10000,         // 10 sec
syncIntervalInactive: 60000  // 60 sec
```

### 3. Always Cleanup

The system auto-cleanups on `beforeunload`, but for single-page apps:

```javascript
// React/Vue component
useEffect(() => {
    const collab = new CollaborationManager({ ... });
    collab.start();

    return () => collab.destroy(); // Cleanup on unmount
}, []);
```

### 4. Use Callbacks for Custom Behavior

```javascript
const collab = new CollaborationManager({
    onUpdate: (data) => {
        // Custom update logic
    },
    onLockDenied: (element, result) => {
        // Custom notification
        showToast(`Locked by ${result.locked_by_user_name}`);
    },
    onNotification: (type, message) => {
        // Custom notification system
        myNotificationSystem.show(type, message);
    }
});
```

## Further Reading

- [Adaptive Intervals Documentation](./ADAPTIVE_INTERVALS.md)
- [API Module System](./API_MODULES.md)
- [Database Optimizations](../migrations/add_performance_optimizations.sql)
