# Modal Builder Guide - Standardized Modal Creation

## Overview

The Modal Builder system provides a consistent way to create modals across both PHP and JavaScript, ensuring uniform styling, structure, and behavior.

## Features

- ✅ **Consistent Structure** - Standardized header, body, footer layout
- ✅ **Dual Implementation** - PHP backend & JavaScript frontend builders
- ✅ **Flexible Sizing** - Small, medium, large, xlarge options
- ✅ **Button Management** - Standardized button placement and styling
- ✅ **Type Helpers** - Pre-configured form, confirm, alert, table modals
- ✅ **Accessibility** - ARIA labels, keyboard navigation, focus management
- ✅ **Responsive** - Mobile-friendly responsive design

## Architecture

```
┌─────────────────────────────────┐
│       Modal Builder API         │
│                                 │
│  PHP: core/modal-builder.php   │
│  JS:  assets/js/modal-builder.js│
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│      Modal System               │
│                                 │
│  - Modal.open()                 │
│  - Modal.close()                │
│  - Event handling               │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│        Rendered Modal           │
│                                 │
│  ┌───────────────────────┐     │
│  │   Header + Close Btn  │     │
│  ├───────────────────────┤     │
│  │         Body          │     │
│  ├───────────────────────┤     │
│  │   Footer + Buttons    │     │
│  └───────────────────────┘     │
└─────────────────────────────────┘
```

## PHP Usage

### Basic Modal

```php
<?php
require_once __DIR__ . '/core/modal-builder.php';

$modal = ModalBuilder::create('my-modal')
    ->title('Modal Title')
    ->body('<p>Modal content here</p>')
    ->footer([
        ['text' => 'Cancel', 'class' => 'btn-secondary', 'action' => 'close'],
        ['text' => 'Save', 'class' => 'btn-primary', 'action' => 'save']
    ])
    ->size('medium')
    ->build();

echo $modal;
```

### Form Modal

```php
$formHtml = '
<form id="project-form">
    <input type="text" name="name" required>
    <textarea name="description"></textarea>
</form>
';

$modal = ModalBuilder::form(
    'project-modal',
    'Create Project',
    $formHtml,
    'project-form',
    [
        'size' => 'large',
        'submitText' => 'Create',
        'submitClass' => 'btn-primary'
    ]
);

echo $modal;
```

### Confirmation Modal

```php
$modal = ModalBuilder::confirm(
    'delete-confirm',
    'Confirm Deletion',
    'Are you sure you want to delete this project?',
    [
        'confirmText' => 'Delete',
        'confirmClass' => 'btn-danger',
        'cancelText' => 'Cancel',
        'icon' => '<svg>...</svg>' // Optional warning icon
    ]
);

echo $modal;
```

### Alert Modal

```php
$modal = ModalBuilder::alert(
    'success-alert',
    'Success',
    'Project created successfully!',
    [
        'type' => 'success',
        'buttonText' => 'OK',
        'buttonClass' => 'btn-primary',
        'icon' => '<svg>...</svg>' // Optional success icon
    ]
);

echo $modal;
```

### Table Modal

```php
$tableHtml = '
<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Project A</td>
            <td>Active</td>
        </tr>
    </tbody>
</table>
';

$modal = ModalBuilder::table(
    'projects-table',
    'All Projects',
    $tableHtml,
    [
        'size' => 'xlarge',
        'showActions' => true
    ]
);

echo $modal;
```

## JavaScript Usage

### Basic Modal

```javascript
ModalBuilder.create('my-modal')
    .title('Modal Title')
    .body('<p>Modal content here</p>')
    .footer([
        { text: 'Cancel', class: 'btn-secondary', action: 'close' },
        { text: 'Save', class: 'btn-primary', action: 'save', onClick: handleSave }
    ])
    .size('medium')
    .show();
```

### Form Modal

```javascript
const formHtml = `
<form id="project-form">
    <input type="text" name="name" required>
    <textarea name="description"></textarea>
</form>
`;

ModalBuilder.form(
    'project-modal',
    'Create Project',
    formHtml,
    'project-form',
    {
        size: 'large',
        submitText: 'Create',
        submitClass: 'btn-primary',
        onSubmit: async () => {
            // Handle form submission
            const formData = new FormData(document.getElementById('project-form'));
            const result = await API.post('/api.php?module=project&action=create', formData, true);
            if (result.success) {
                Modal.close();
                Toast.success('Project created!');
            }
        },
        onShow: () => {
            // Focus first input
            document.querySelector('#project-form input').focus();
        }
    }
);
```

### Confirmation Modal (Promise-based)

```javascript
const confirmed = await ModalBuilder.confirm(
    'delete-confirm',
    'Confirm Deletion',
    'Are you sure you want to delete this project?',
    {
        confirmText: 'Delete',
        confirmClass: 'btn-danger',
        icon: '<svg>...</svg>'
    }
);

if (confirmed) {
    // User clicked confirm
    await deleteProject(projectId);
} else {
    // User clicked cancel or closed modal
    console.log('Deletion cancelled');
}
```

### Alert Modal (Promise-based)

```javascript
await ModalBuilder.alert(
    'success-alert',
    'Success',
    'Project created successfully!',
    {
        type: 'success',
        buttonText: 'OK',
        buttonClass: 'btn-primary'
    }
);

// Continues after user closes alert
console.log('User acknowledged alert');
```

### Table Modal

```javascript
const tableHtml = `
<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Project A</td>
            <td>Active</td>
        </tr>
    </tbody>
</table>
`;

ModalBuilder.table(
    'projects-table',
    'All Projects',
    tableHtml,
    {
        size: 'xlarge',
        showActions: true,
        onShow: (modalId) => {
            // Initialize table features (sorting, filtering, etc.)
            initializeTable();
        }
    }
);
```

## Configuration Options

### Modal Sizes

| Size | Class | Width | Best For |
|------|-------|-------|----------|
| `small` | `modal-sm` | 400px | Alerts, confirmations |
| `medium` | `modal-md` | 600px | Simple forms (default) |
| `large` | `modal-lg` | 800px | Complex forms |
| `xlarge` | `modal-xl` | 1200px | Tables, dashboards |

### Button Configuration

```javascript
{
    text: 'Button Text',      // Button label
    class: 'btn-primary',     // CSS class
    action: 'submit',         // Action type: submit, close, custom
    form: 'form-id',          // Form ID (for submit buttons)
    icon: '<svg>...</svg>',   // Optional icon
    onClick: () => {},        // Click handler (JS only)
    data: {                   // Custom data attributes
        projectId: 123
    }
}
```

### Footer Button Order

Buttons are displayed left-to-right in array order. Standard pattern:

```javascript
[
    { text: 'Cancel', class: 'btn-secondary' },  // Left
    { text: 'Save', class: 'btn-primary' }        // Right
]
```

For multi-action modals:

```javascript
[
    { text: 'Cancel', class: 'btn-secondary' },     // Left
    { text: 'Save Draft', class: 'btn-outline' },  // Middle
    { text: 'Publish', class: 'btn-primary' }       // Right
]
```

## Standard Modal Types

### 1. Form Modal
- **Purpose**: Create/edit forms
- **Size**: Medium or Large
- **Footer**: Cancel + Submit buttons
- **Features**: Form validation, auto-focus

### 2. Confirmation Modal
- **Purpose**: Destructive actions
- **Size**: Small
- **Footer**: Cancel + Confirm buttons
- **Features**: Warning icon, danger styling

### 3. Alert Modal
- **Purpose**: Notifications, messages
- **Size**: Small
- **Footer**: Single OK button
- **Features**: Type-based styling (info, success, warning, error)

### 4. Table Modal
- **Purpose**: Display data lists
- **Size**: Large or XLarge
- **Footer**: Close button
- **Features**: Scrollable table wrapper

### 5. Wizard Modal
- **Purpose**: Multi-step processes
- **Size**: Large
- **Footer**: Back + Next + Finish buttons
- **Features**: Step indicators

## Styling Guidelines

### Modal Structure

```html
<div class="modal-content modal-md">
    <div class="modal-header">
        <h2 class="modal-title">Title</h2>
        <div class="modal-header-buttons">
            <!-- Optional header buttons -->
        </div>
        <button class="btn-modal-close">×</button>
    </div>
    <div class="modal-body">
        <!-- Content -->
    </div>
    <div class="modal-footer">
        <button class="btn btn-secondary">Cancel</button>
        <button class="btn btn-primary">Save</button>
    </div>
</div>
```

### CSS Classes

**Modal Content:**
- `.modal-content` - Main container
- `.modal-sm`, `.modal-md`, `.modal-lg`, `.modal-xl` - Size modifiers

**Header:**
- `.modal-header` - Header container
- `.modal-title` - Title text
- `.modal-header-buttons` - Optional header button group
- `.btn-modal-close` - Close button

**Body:**
- `.modal-body` - Body container
- `.modal-table-wrapper` - Table wrapper (scrollable)
- `.modal-form` - Form styling
- `.modal-alert` - Alert styling
- `.modal-confirm-message` - Confirmation message

**Footer:**
- `.modal-footer` - Footer container
- `.modal-footer-left` - Left-aligned buttons
- `.modal-footer-right` - Right-aligned buttons (default)

## Best Practices

### 1. Modal Sizing

```javascript
// ✅ Good - Appropriate size for content
ModalBuilder.form('user-form', 'Edit User', formHtml, 'user-form', { size: 'medium' });

// ❌ Bad - Too large for simple form
ModalBuilder.form('user-form', 'Edit User', formHtml, 'user-form', { size: 'xlarge' });
```

### 2. Button Placement

```javascript
// ✅ Good - Cancel left, primary action right
.footer([
    { text: 'Cancel', class: 'btn-secondary', action: 'close' },
    { text: 'Save', class: 'btn-primary', action: 'submit' }
])

// ❌ Bad - Reversed order
.footer([
    { text: 'Save', class: 'btn-primary', action: 'submit' },
    { text: 'Cancel', class: 'btn-secondary', action: 'close' }
])
```

### 3. Destructive Actions

```javascript
// ✅ Good - Danger styling for destructive actions
ModalBuilder.confirm('delete-modal', 'Delete Project', '...', {
    confirmClass: 'btn-danger',
    confirmText: 'Delete'
});

// ❌ Bad - Primary styling for destructive action
ModalBuilder.confirm('delete-modal', 'Delete Project', '...', {
    confirmClass: 'btn-primary',
    confirmText: 'Delete'
});
```

### 4. Form Submission

```javascript
// ✅ Good - Handle validation and errors
onSubmit: async () => {
    const form = document.getElementById('project-form');
    if (!form.checkValidity()) {
        form.reportValidity();
        return;
    }

    try {
        const result = await API.post('/api.php', new FormData(form), true);
        if (result.success) {
            Modal.close();
            Toast.success('Saved!');
        } else {
            Toast.error(result.error);
        }
    } catch (error) {
        Toast.error('Network error');
    }
}

// ❌ Bad - No validation or error handling
onSubmit: async () => {
    await API.post('/api.php', new FormData(form), true);
    Modal.close();
}
```

### 5. Accessibility

```javascript
// ✅ Good - Set focus on open
.onShow(() => {
    document.querySelector('#project-form input').focus();
})

// ✅ Good - Use semantic HTML
.body('<form><fieldset><legend>Project Details</legend>...</fieldset></form>')

// ✅ Good - Add ARIA labels
.body('<input type="text" aria-label="Project name" required>')
```

## Migration Guide

### Old Pattern (Direct Modal.open)

```javascript
// ❌ Old way - Manual HTML construction
const html = `
    <div class="modal-header">
        <h2>Create Project</h2>
        <button onclick="Modal.close()">×</button>
    </div>
    <div class="modal-body">
        <form id="project-form">...</form>
    </div>
    <div class="modal-footer">
        <button onclick="Modal.close()">Cancel</button>
        <button type="submit" form="project-form">Save</button>
    </div>
`;
Modal.open(html, { size: 'large' });
```

### New Pattern (ModalBuilder)

```javascript
// ✅ New way - ModalBuilder
ModalBuilder.form(
    'project-modal',
    'Create Project',
    '<form id="project-form">...</form>',
    'project-form',
    {
        size: 'large',
        onSubmit: handleSubmit
    }
);
```

## Examples

### Complete Form Modal with Validation

```javascript
async function showProjectForm(projectId = null) {
    // Fetch project data if editing
    let project = null;
    if (projectId) {
        const result = await API.get(`/api.php?module=project&action=get&id=${projectId}`);
        project = result.data;
    }

    // Build form HTML
    const formHtml = `
    <form id="project-form" class="modal-form">
        <div class="form-group">
            <label for="name">Project Name *</label>
            <input type="text" id="name" name="name" value="${project?.name || ''}" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea id="description" name="description">${project?.description || ''}</textarea>
        </div>
    </form>
    `;

    // Show modal
    ModalBuilder.form(
        'project-form-modal',
        projectId ? 'Edit Project' : 'Create Project',
        formHtml,
        'project-form',
        {
            size: 'large',
            submitText: projectId ? 'Update' : 'Create',
            onSubmit: async () => {
                const form = document.getElementById('project-form');

                // Validate
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                // Submit
                const formData = new FormData(form);
                const action = projectId ? 'update' : 'create';
                if (projectId) formData.append('id', projectId);

                try {
                    const result = await API.post(
                        `/api.php?module=project&action=${action}`,
                        formData,
                        true
                    );

                    if (result.success) {
                        Modal.close();
                        Toast.success(result.message);
                        // Reload project list
                        loadProjects();
                    } else {
                        Toast.error(result.error);
                    }
                } catch (error) {
                    Toast.error('Network error');
                }
            },
            onShow: () => {
                // Focus first input
                document.getElementById('name').focus();

                // Initialize any rich editors, date pickers, etc.
                initializeFormComponents();
            }
        }
    );
}
```

### Confirm Before Delete

```javascript
async function deleteProject(projectId) {
    const confirmed = await ModalBuilder.confirm(
        'delete-project',
        'Delete Project',
        'Are you sure you want to delete this project? This action cannot be undone.',
        {
            confirmText: 'Delete',
            confirmClass: 'btn-danger',
            cancelText: 'Cancel',
            icon: `<svg class="icon-warning">...</svg>`
        }
    );

    if (!confirmed) {
        return;
    }

    try {
        const result = await API.post(
            `/api.php?module=project&action=delete`,
            { id: projectId }
        );

        if (result.success) {
            Toast.success('Project deleted');
            loadProjects();
        } else {
            Toast.error(result.error);
        }
    } catch (error) {
        Toast.error('Failed to delete project');
    }
}
```

## Conclusion

The Modal Builder system provides:
- ✅ Consistent modal structure
- ✅ Standardized button placement
- ✅ Simplified modal creation
- ✅ Better maintainability
- ✅ Accessibility compliance
- ✅ Responsive design

Use ModalBuilder for all new modals and migrate existing modals gradually.
