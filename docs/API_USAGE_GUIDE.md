# 🚀 API Usage Guide
**Version:** 2.0  
**Last Updated:** 2026-01-13

---

## 📖 Overview

The centralized API layer (`/assets/js/api.js`) provides standardized, type-safe methods for all backend interactions with built-in error handling, CSRF protection, and retry logic.

---

## 🎯 Quick Start

### Basic Usage

```javascript
// GET request
const projects = await API.projects.getAll();

// GET single item
const project = await API.projects.get(1);

// CREATE
const newProject = await API.projects.create({
    name: 'New Project',
    status: 'active'
});

// UPDATE
const updated = await API.projects.update(1, {
    name: 'Updated Name'
});

// DELETE
await API.projects.delete(1);
```

### Error Handling

```javascript
try {
    const project = await API.projects.get(999);
} catch (error) {
    console.error('Failed to load project:', error);
    // Toast already shown automatically
}
```

---

## 📚 Available Entities

### 1. Projects

```javascript
// List all projects
const projects = await API.projects.getAll();

// Get single project
const project = await API.projects.get(projectId);

// Create project
const newProject = await API.projects.create({
    name: 'Project Name',
    status: 'active',
    client_id: 123
});

// Update project
await API.projects.update(projectId, { status: 'completed' });

// Delete project
await API.projects.delete(projectId);

// --- Specialized Methods ---

// List snapshots
const snapshots = await API.projects.listSnapshots(projectId);

// Create snapshot
await API.projects.createSnapshot(projectId, 'Snapshot Title');

// Restore snapshot
await API.projects.restoreSnapshot(snapshotId);

// Save cover image (as Blob)
await API.projects.saveCoverImage(projectId, imageBlob);
```

### 2. Building Elements

```javascript
// List elements for project
const elements = await API.buildingElements.getAll(projectId);

// Get single element
const element = await API.buildingElements.get(elementId);

// Create element
const newElement = await API.buildingElements.create({
    project_id: projectId,
    title: 'Element Title',
    description: 'Description'
});

// Update element
await API.buildingElements.update(elementId, { title: 'New Title' });

// Delete element
await API.buildingElements.delete(elementId);

// --- Specialized Methods ---

// Get form HTML
const formHtml = await API.buildingElements.getForm(elementId);
// Or for new element:
const formHtml = await API.buildingElements.getForm(null, projectId);

// Update single field
await API.buildingElements.updateField(elementId, 'capex', 50000);

// Update sort order
await API.buildingElements.updateSort([
    { id: 1, sort_order: 0 },
    { id: 2, sort_order: 1 }
]);

// Lock check
const lockStatus = await API.buildingElements.lockCheck(elementId, clientId);

// Release lock
await API.buildingElements.lockRelease(elementId, clientId);

// Poll for changes
const updates = await API.buildingElements.poll(elementId, clientId);
```

### 3. Customers

```javascript
// List all customers
const customers = await API.customers.getAll();

// Get customer
const customer = await API.customers.get(customerId);

// Create customer
const newCustomer = await API.customers.create({
    name: 'Customer Name',
    email: 'email@example.com',
    phone: '12345678'
});

// Update customer
await API.customers.update(customerId, { email: 'newemail@example.com' });

// Delete customer
await API.customers.delete(customerId);

// Search customers
const results = await API.customers.search('search term');
```

### 4. Media / Files

```javascript
// Upload files
const fileInput = document.querySelector('#file-upload');
const files = fileInput.files;
await API.media.upload(elementId, files);

// Delete media
await API.media.delete(mediaId);

// Update sort order
await API.media.updateSort([
    { id: 1, sort_order: 0 },
    { id: 2, sort_order: 1 }
]);

// Update caption
await API.media.updateCaption(mediaId, 'New caption');
```

### 5. Notifications

```javascript
// Get all notifications
const notifications = await API.notifications.getAll();

// Get unread only
const unread = await API.notifications.getUnread();

// Mark as read
await API.notifications.markRead(notificationId);

// Mark all as read
await API.notifications.markAllRead();

// Delete notification
await API.notifications.delete(notificationId);
```

### 6. Reports

```javascript
// Generate report
const reportHtml = await API.reports.generate(projectId);

// Generate from template
const reportHtml = await API.reports.generate(projectId, templateId);

// Excel export
const excelData = await API.reports.excel(projectId);

// --- Templates ---

// List templates
const templates = await API.reports.templates.getAll();

// Get template
const template = await API.reports.templates.get(templateId);

// Save template
await API.reports.templates.save({
    id: templateId,
    name: 'Template Name',
    content: '<html>...</html>'
});
```

### 7. Budget Items

```javascript
// List budget items for element
const items = await API.budgetItems.getAll(elementId);

// Get item
const item = await API.budgetItems.get(itemId);

// Create item
await API.budgetItems.create({
    element_id: elementId,
    description: 'Item description',
    amount: 10000
});

// Update item
await API.budgetItems.update(itemId, { amount: 15000 });

// Delete item
await API.budgetItems.delete(itemId);
```

### 8. Authentication

```javascript
// Login
const response = await API.auth.login('username', 'password');

// Logout
await API.auth.logout();

// Forgot password
await API.auth.forgotPassword('email@example.com');

// Reset password
await API.auth.resetPassword('token', 'newPassword');
```

### 9. Users

```javascript
// List users
const users = await API.users.getAll();

// Get user
const user = await API.users.get(userId);

// Create user
await API.users.create({
    username: 'newuser',
    email: 'user@example.com',
    role: 'user'
});

// Update user
await API.users.update(userId, { role: 'admin' });

// Delete user
await API.users.delete(userId);

// Search users
const results = await API.users.search('search term');
```

### 10. Custom Fields

```javascript
// Get field definitions
const definitions = await API.customFields.getDefinitions('building_element', projectId);

// Save field value
await API.customFields.saveValue(entityId, definitionId, 'value');
```

### 11. Price Catalog

```javascript
// Search catalog
const results = await API.priceCatalog.search('search term');

// Get catalog item
const item = await API.priceCatalog.get(itemId);

// Import catalog (Excel file)
const fileInput = document.querySelector('#catalog-file');
const file = fileInput.files[0];
await API.priceCatalog.import(file);
```

---

## ⚙️ Advanced Usage

### Custom Options

```javascript
// Disable automatic toast notifications
await API.request('module=Project&action=index', 'GET', null, {
    showToast: false
});

// Custom timeout (default: 30s)
await API.request('module=Report&action=generate', 'GET', null, {
    timeout: 60000 // 60 seconds
});

// Disable retry (default: 1 retry)
await API.request('module=Project&action=get&id=1', 'GET', null, {
    retries: 0
});
```

### Manual Request

```javascript
// For custom endpoints not in API entities
const response = await API.request(
    'module=Custom&action=specialEndpoint&param=value',
    'POST',
    { data: 'value' }
);
```

### FormData Requests

```javascript
// FormData is automatically detected
const fd = new FormData();
fd.append('file', fileBlob);
fd.append('title', 'File title');

await API.request('module=Upload&action=process', 'POST', fd);
```

---

## 🔒 Security Features

### Automatic CSRF Token

All POST/PUT/DELETE requests automatically include CSRF token:
```javascript
// CSRF token added automatically
await API.projects.create({ name: 'Project' });
```

### Error Handling

```javascript
// Network errors
try {
    await API.projects.get(1);
} catch (error) {
    // Error already logged
    // Toast shown to user
    // Error object available for custom handling
}
```

---

## 🎨 UI Integration Examples

### Form Submission

```javascript
document.querySelector('#project-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const data = Object.fromEntries(formData);
    
    try {
        if (data.id) {
            await API.projects.update(data.id, data);
        } else {
            await API.projects.create(data);
        }
        
        // Reload list
        loadProjects();
        
        // Close modal
        wm.close('project-form-window');
    } catch (error) {
        // Error already shown to user
    }
});
```

### Loading Lists

```javascript
async function loadProjects() {
    const container = document.querySelector('#project-list');
    container.innerHTML = '<div class="loading">Loading...</div>';
    
    try {
        const projects = await API.projects.getAll();
        
        container.innerHTML = projects.map(p => `
            <div class="project-card">
                <h3>${p.name}</h3>
                <button onclick="editProject(${p.id})">Edit</button>
                <button onclick="deleteProject(${p.id})">Delete</button>
            </div>
        `).join('');
    } catch (error) {
        container.innerHTML = '<div class="error">Failed to load projects</div>';
    }
}
```

### Delete Confirmation

```javascript
async function deleteProject(id) {
    App.confirm('Are you sure you want to delete this project?', async () => {
        await API.projects.delete(id);
        loadProjects(); // Reload list
    });
}
```

---

## 🔄 Migration from Legacy Code

### Before (Old Way)

```javascript
// Old App.api() method
App.api('?module=Project&action=get&id=1', 'GET')
    .then(response => {
        // Handle response
    })
    .catch(error => {
        App.toast('Error', 'error');
    });
```

### After (New Way)

```javascript
// New standardized API
const project = await API.projects.get(1);
// Error handling and toast automatic!
```

---

## 💡 Best Practices

1. **Always use async/await**
```javascript
// ✅ Good
const project = await API.projects.get(1);

// ❌ Avoid
API.projects.get(1).then(...)
```

2. **Handle errors explicitly when needed**
```javascript
// ✅ Good
try {
    await API.projects.delete(id);
    closeModal();
} catch (error) {
    // Custom error handling
}
```

3. **Use entity methods over raw requests**
```javascript
// ✅ Good
await API.projects.get(1);

// ❌ Avoid
await API.request('module=Project&action=get&id=1');
```

4. **Leverage TypeScript-style patterns**
```javascript
// ✅ Good - explicit, self-documenting
await API.buildingElements.updateField(id, 'capex', 50000);

// ❌ Avoid - unclear what's being updated
await API.request('...updatefield', 'POST', { id, field: 'capex', value: 50000 });
```

---

## 🐛 Troubleshooting

### Request Timeout
```javascript
// Increase timeout for long operations
await API.request('module=Report&action=generate', 'GET', null, {
    timeout: 120000 // 2 minutes
});
```

### CSRF Token Missing
- Ensure `<meta name="csrf-token">` exists in layout
- Or add `<input name="_csrf">` to forms

### FormData Not Working
```javascript
// ✅ Correct - pass FormData directly
const fd = new FormData();
fd.append('file', blob);
await API.media.upload(elementId, [blob]);

// ❌ Wrong - don't convert to JSON
await API.request('...', 'POST', JSON.stringify(fd));
```

---

## 📞 Support

For questions or issues:
1. Check this guide
2. Review `/assets/js/api.js` source
3. Check browser console for errors
4. Contact development team

---

**Happy Coding!** 🚀
