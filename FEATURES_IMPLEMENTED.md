# Implemented Features - Code Review Optimization

This document describes all features implemented during the code review optimization session.

## 1. Backend-Controlled API System ✅

**Location:** `api.php`

### Features:
- Centralized API controller with role-based access control
- All permission checks happen on backend (NEVER trust frontend)
- CSRF token validation on all write operations
- Ownership verification for user-created content

### API Endpoints:
- `get_dashboard_stats` - Dashboard statistics with role-based filtering
- `get_dashboard_widgets` - Widgets based on user permissions
- `upload_image` - Image upload with ownership verification
- `delete_image` - Delete image with permission check
- `get_images` - List images with access control
- `update_order` - Drag-drop sorting with ownership check
- `create_snapshot` - Save project state (admin or owner only)
- `restore_snapshot` - Restore project from snapshot
- `list_snapshots` - List available snapshots
- `copy_project` - Clone entire project with all data
- `create_template` - Create reusable templates (admin only)
- `apply_template` - Apply template to project/prices
- `list_templates` - List available templates
- `list_users` - User management (admin only)
- `update_user_role` - Change user roles (admin only)
- `get_notifications` - User notifications
- `mark_notification_read` - Mark notification as read
- `search` - Global search with permission filtering
- `refresh_csrf_token` - CSRF token refresh

## 2. Role-Based Permission System ✅

**Location:** `core/security.php`

### User Roles:
1. **Admin** - Full access to everything
   - Can manage users and change roles
   - Can create templates
   - Can view all projects regardless of ownership
   - Can perform all operations

2. **User** - Standard user with limited access
   - Can create and edit own projects
   - Can upload images to own projects
   - Can create snapshots of own projects
   - Cannot manage other users
   - Cannot create templates (can only apply them)

3. **Viewer** - Read-only access
   - Can only view assigned projects
   - Cannot create, edit, or delete anything
   - Cannot upload images or create snapshots

### Permission Functions:
- `get_user_permissions(int $userId): array` - Get all permissions for user
- `has_permission(array $user, string $permission): bool` - Check single permission
- `user_owns_project(int $userId, int $projectId): bool` - Verify ownership

### Security Principle:
**ALL admin content is controlled by backend** - Frontend only displays what backend provides. Example:
```php
// ❌ WRONG - User can manipulate frontend
{if user.role = 'admin'}
    Show admin content
{/if}

// ✅ CORRECT - Backend controls visibility
<?php if ($permissions['admin']): ?>
    Show admin content
<?php endif; ?>
```

## 3. Drag-and-Drop Sorting ✅

**Location:**
- `assets/js/drag-drop.js` - Frontend drag-drop handler
- `modules/building_element/template.tpl` - Integrated into building elements
- `api.php` - `updateOrder()` backend handler

### Features:
- Drag handles appear only for users with `edit_elements` permission
- Real-time visual feedback during drag operations
- Backend validates ownership before saving new order
- Automatic revert to original order on failure
- Uses database transactions for consistency

### Database:
- Requires `sort_order` column on `building_elements` table
- Migration: `migrations/add_sort_order_to_building_elements.sql`
- Run with: `php migrations/run_migration.php add_sort_order_to_building_elements.sql`

### Usage:
```javascript
DragDrop.init('elementsTable', {
    handle: '.drag-handle',
    buildingId: 123,
    onUpdate: (itemIds) => {
        console.log('New order:', itemIds);
    }
});
```

## 4. Image Upload and Processing ✅

**Location:**
- `assets/js/image-upload.js` - Frontend upload component
- `assets/css/image-upload.css` - Styling
- `api.php` - Backend upload/delete/list handlers
- `core/security.php` - Image processing functions

### Features:
- Drag-and-drop file upload
- Multi-file selection
- Client-side preview before upload
- File type validation (JPEG, PNG, WebP)
- File size limit (10MB per file)
- Backend automatic image processing:
  - Resize to max 1920px width/height
  - Generate 300px thumbnails
  - Optimize compression
  - Secure file storage with ownership tracking

### Image Gallery:
- Grid display with hover effects
- Click to view full-size in modal
- Delete with confirmation
- Integrated into building_element edit form with tabs
- Lazy loading - images load only when tab is opened

### Usage:
```javascript
// Open upload modal
ImageUpload.openUploadModal('building_element', 123);

// Show gallery in container
ImageUpload.showGallery('building_element', 123, '#galleryContainer');

// Delete image
ImageUpload.deleteImage(456, 'building_element', 123, '#galleryContainer');
```

### Integration Example (Building Elements):
When editing a building element, users see two tabs:
1. **Detaljer** - Form fields for element data
2. **Billeder** - Image gallery with upload functionality

## 5. Dashboard with Backend-Controlled Widgets ✅

**Location:**
- `modules/dashboard/template.tpl` - Frontend dashboard template
- `modules/dashboard/index.php` - Module entry point
- `api.php` - Dashboard data endpoints

### Features:
- All data loaded via API calls (no direct DB queries in frontend)
- Stats grid with role-based filtering:
  - Admins see all data
  - Users see only their own projects
  - Viewers see limited statistics
- Widget system with permission-based visibility
- Auto-refresh every 5 minutes
- Admin-only sections only rendered if user has admin permission (checked on backend)

### Widget Types:
1. **Stats Cards** - Clickable cards showing key metrics
2. **Recent Projects** - List of latest projects with status badges
3. **Urgent Elements** - Building elements requiring attention
4. **Admin Widgets** (admin only):
   - Recent users
   - System health (database size, record counts)

## 6. Project Snapshots (API Ready, UI Pending)

**Location:** `api.php` - Backend implementation complete

### Features:
- Save entire project state as JSON snapshot
- Includes all buildings, elements, and file references
- Restore project from any previous snapshot
- List all snapshots for a project
- Permission check: admin or project owner only
- Uses database transactions for consistency

### API Endpoints:
- `create_snapshot` - Create new snapshot
- `restore_snapshot` - Restore from snapshot
- `list_snapshots` - List available snapshots

**Status:** Backend complete, UI pending implementation

## 7. Project Copying (API Ready, UI Pending)

**Location:** `api.php` - Backend implementation complete

### Features:
- Clone entire project with all related data
- Copies all buildings and their elements
- Duplicates file attachments
- Maintains relationships between entities
- Creates new ownership for copied project
- Uses database transactions for data consistency

### API Endpoint:
- `copy_project` - Clone project with all data

**Status:** Backend complete, UI pending implementation

## 8. Template System (API Ready, UI Pending)

**Location:** `api.php` - Backend implementation complete

### Features:
- Admin-only template creation
- All users can apply templates
- Template types:
  - Project templates (structure, defaults)
  - Price catalog templates (pricing data)
- JSON-based template storage
- Permission-controlled access

### API Endpoints:
- `create_template` - Create template (admin only)
- `apply_template` - Apply template to entity
- `list_templates` - List available templates

**Status:** Backend complete, UI pending implementation

## Files Created/Modified

### New Files:
- `api.php` - Centralized API controller
- `assets/js/drag-drop.js` - Drag-drop sorting component
- `assets/js/image-upload.js` - Image upload component
- `assets/css/image-upload.css` - Image upload styling
- `migrations/add_sort_order_to_building_elements.sql` - Database migration
- `migrations/run_migration.php` - Migration runner script

### Modified Files:
- `core/security.php` - Extended with permission system and helpers
- `modules/dashboard/index.php` - Updated to use API
- `modules/dashboard/template.tpl` - Completely rewritten for backend control
- `modules/building_element/index.php` - Added permissions and sort order
- `modules/building_element/template.tpl` - Added drag-drop and image upload
- `templates/main.tpl` - Added new CSS and JS includes

## Next Steps (Pending UI Implementation)

1. **Project Snapshots UI**
   - Add "Create Snapshot" button to project view
   - Show snapshot history with restore functionality
   - Display snapshot metadata (date, creator, description)

2. **Project Copying UI**
   - Add "Copy Project" button to project actions
   - Modal for entering new project name and settings
   - Progress indicator during copy operation

3. **Template System UI**
   - Admin interface for creating/managing templates
   - Template browser for users to apply templates
   - Template preview before applying

4. **Table of Contents Generation**
   - Auto-generate TOC for project reports
   - Hierarchical structure based on buildings/elements
   - Exportable formats (PDF, Word)

5. **Enhanced Paging**
   - Improved pagination controls
   - Items per page selection
   - Jump to page functionality

## Security Best Practices Implemented

1. ✅ All permission checks on backend
2. ✅ CSRF protection on all write operations
3. ✅ Ownership verification for user data
4. ✅ Input sanitization on all user inputs
5. ✅ SQL injection prevention via parameterized queries
6. ✅ File upload validation (type, size, ownership)
7. ✅ Session security with proper headers
8. ✅ Error messages don't leak sensitive information
9. ✅ Transaction rollback on failures
10. ✅ Activity logging for audit trail

## Running Migrations

To add the sort_order column to building_elements table:

```bash
php migrations/run_migration.php add_sort_order_to_building_elements.sql
```

## Testing Checklist

- [ ] Test drag-drop sorting as admin user
- [ ] Test drag-drop sorting as regular user (own projects only)
- [ ] Test drag-drop sorting as viewer (should not see drag handles)
- [ ] Test image upload with various file types
- [ ] Test image upload with oversized files (should reject)
- [ ] Test image gallery display and deletion
- [ ] Test dashboard as admin (should see all data)
- [ ] Test dashboard as user (should see only own projects)
- [ ] Test dashboard as viewer (should see limited data)
- [ ] Test permission boundaries (try to access other user's data)
- [ ] Test API endpoints directly (should require login)
- [ ] Test CSRF protection (requests without token should fail)

## Performance Considerations

1. **Image Processing**: Automatic resize and thumbnail generation happens during upload
2. **Drag-Drop**: Uses optimistic UI updates with rollback on failure
3. **Dashboard**: Auto-refresh limited to 5-minute intervals
4. **Database**: Indexes added for sort_order column
5. **API**: Response caching where appropriate
6. **Transactions**: Used for multi-step operations to ensure consistency

## 9. Hierarchical Tree Structure for Reports ✅

**Location:**
- `assets/js/report-tree.js` - Tree view component
- `assets/css/report-tree.css` - Tree styling  
- `api.php` - Tree data endpoints (get_project_tree, update_element_hierarchy)

### Features:
- Hierarchical tree view with buildings at the top
- Building elements organized in parent-child relationships
- Multiple buildings supported (Building 1, Building 2, etc.)
- Drag-and-drop sorting within tree structure  
- Move elements between parents via drag-drop
- Automatic cost summation up the hierarchy
- Units, quantities, and subtotals displayed for each element
- Building totals calculated from all child elements
- Project-wide total CAPEX summary in header

### Tree Structure Example:
```
Project Name (Total CAPEX: 5.000.000 kr)
├── Bygning 1 (2.500.000 kr) [12 elementer]
│   ├── Udendørsarealer
│   │   └── Belægning (500.000 kr)
│   └── Overflader  
│       ├── Trægulve (800.000 kr)
│       └── Klinker (400.000 kr)
└── Bygning 2 (2.500.000 kr) [8 elementer]
    └── Facader (1.200.000 kr)
```

### API Endpoints:
- `get_project_tree` - Get hierarchical tree structure with calculated totals
- `update_element_hierarchy` - Update parent/child relationships and sort order

### Key Features:
1. **Hierarchical Display** - Nested tree with unlimited depth
2. **Expandable/Collapsible** - Click chevron to expand/collapse branches  
3. **Drag-Drop Reordering** - Drag elements to reorder within same parent
4. **Move Between Parents** - Drag elements to different parents in tree
5. **Automatic Calculations** - Element costs roll up to parent subtotals
6. **Visual Indicators** - Building icons, element icons, urgency badges
7. **Inline Actions** - Edit button on hover for quick access
8. **Responsive Design** - Mobile-friendly tree layout

### Cost Summation Logic:
- Each element displays its CAPEX value
- Parent elements show subtotal = own CAPEX + sum of all children  
- Buildings show total = sum of all root elements and their descendants
- Project header shows grand total across all buildings
- Real-time recalculation after drag-drop hierarchy changes

### Visual Elements:
- **Buildings** - Blue header with white text, building icon
- **Elements** - Nested with indentation, left border, box icon
- **Drag Handles** - Visible on hover for sorting
- **Urgency Badges** - Color-coded (low/normal/high/critical)
- **Stats Display** - Units, quantities, and costs aligned right
- **Toggle Buttons** - Chevron icons for expand/collapse

### Responsive Behavior:
- Desktop: Full stats inline with labels
- Mobile: Stats stacked vertically, reduced indentation
- Print: Drag handles and buttons hidden automatically

### Usage Example:
```javascript
// Initialize tree for a project
ReportTree.init(123, '.report-tree-container');

// Tree will:
// 1. Load hierarchical data from API
// 2. Calculate all subtotals automatically  
// 3. Render expandable tree view
// 4. Enable drag-drop sorting
// 5. Update backend on hierarchy changes
```

### Database Support:
Uses existing schema features:
- `building_elements.parent_id` - Parent-child relationships
- `building_elements.sort_order` - Ordering within parent
- `building_elements.capex` - Element costs for summation
- `building_elements.unit` - Unit type (m², stk, etc.)
- `building_elements.quantity` - Quantity for calculations

### Integration Points:
- Can be embedded in any project/building view page
- Automatically handles permissions and ownership
- Updates sync with main building_element module
- Click edit opens element in main module form

## Testing Checklist Additions

- [ ] Test tree view loads correctly with multiple buildings
- [ ] Test expand/collapse functionality
- [ ] Test drag-drop reordering within same parent
- [ ] Test drag-drop moving element to different parent
- [ ] Test cost totals calculate correctly up hierarchy
- [ ] Test tree view on mobile devices
- [ ] Test edit button opens element form
- [ ] Test tree view with viewer permissions (read-only)
- [ ] Test tree updates after element edits in main module

## 10. OPEX Module with Experience Values ✅

**Location:**
- `modules/opex/index.php` - OPEX module controller
- `modules/opex/template.tpl` - OPEX module template
- `api.php` - OPEX API endpoints (11 new endpoints)
- `migrations/create_opex_tables.sql` - Database schema for OPEX

### Features:
**OPEX is a separate module** (NOT just fields in building_elements) with experience values based on square meters (m²).

### Database Schema:
1. **opex_categories** - Experience values per m² per year
   - Categories: Rengøring, Energi, Vand og afløb, Forsikring, Ejendomsskat, Sikkerhed, Affaldshåndtering, Mindre reparationer, Administration, Udvendig vedligehold
   - Default rates ranging from 10-120 kr/m²/år
   - Grouped by type: maintenance, energy, utilities, insurance, tax, security, waste, admin

2. **building_opex** - OPEX assignments to buildings
   - Links OPEX categories to specific buildings
   - Supports custom rates per building (override defaults)
   - Notes field for documentation

3. **tco_config** - TCO configuration constants
   - lifecycle_years: 30 years (default)
   - discount_rate: 3% (default)
   - inflation_rate: 2% (default)
   - capex_contingency: 10% (default)
   - opex_escalation: 2.5% annual increase (default)

### OPEX Calculation:
- Annual OPEX = Σ(rate_per_sqm × building_area) for all assigned categories
- Supports custom rates per building to override defaults
- Automatic calculation based on building area (m²)

### TCO Calculation:
- **CAPEX** = Sum of all building elements' capex values
- **CAPEX with contingency** = CAPEX × (1 + capex_contingency)
- **OPEX NPV** = Net Present Value of OPEX over lifecycle with escalation and discount
- **TCO** = CAPEX with contingency + OPEX NPV

Formula for OPEX NPV:
```
For each year 1 to lifecycle_years:
  year_opex = opex_per_year × (1 + opex_escalation)^(year-1)
  discount_factor = (1 + discount_rate)^year
  opex_npv += year_opex / discount_factor
```

### API Endpoints (11 new):
- `get_available_opex_categories` - Get categories not yet assigned to building
- `assign_opex_to_building` - Assign OPEX category to building
- `get_opex_assignment` - Get assignment details
- `update_opex_assignment` - Update custom rate and notes
- `remove_opex_assignment` - Remove OPEX category from building
- `create_opex_category` - Create new category (admin only)
- `get_opex_category` - Get category details
- `update_opex_category` - Update category (admin only)
- `toggle_opex_category` - Activate/deactivate category (admin only)
- `update_tco_config` - Update TCO constants (admin only)
- `calculate_building_tco` - Calculate full TCO breakdown for building

### User Interface:

#### Building-Specific OPEX View:
- Access via: `?module=opex&building_id=X`
- **OPEX Overview Card:**
  - Building area (m²)
  - Total OPEX per year
  - OPEX per m² per year
  - OPEX over lifecycle (e.g., 30 years)
- **Assigned Categories Table:**
  - Category name and type
  - Rate per m²/år (shows if custom or default)
  - Total per year = rate × building area
  - Notes field
  - Edit/Remove actions (permission-controlled)
- **Add Category Button** - Opens modal to assign new OPEX categories

#### Admin OPEX Management View:
- Access via: `?module=opex`
- **Tab 1: OPEX Categories**
  - Grouped by type (Vedligeholdelse, Energi, Forsyning, etc.)
  - Table showing: Name, Description, Rate per m²/år, Active status
  - Create/Edit/Toggle active status (admin only)
  - Default categories pre-populated

- **Tab 2: TCO Configuration** (admin only)
  - Editable form for all TCO constants:
    - lifecycle_years (years)
    - discount_rate (percent)
    - inflation_rate (percent)
    - capex_contingency (percent)
    - opex_escalation (percent)
  - Save all changes at once

### Permission Controls:
- **View OPEX:** Users can view OPEX for buildings in their projects
- **Edit OPEX:** Users can assign/update/remove OPEX for their buildings
- **Manage Categories:** Only admins can create/edit/toggle OPEX categories
- **TCO Config:** Only admins can update TCO configuration constants

### Integration Points:
1. **Building Module:** Add "OPEX" button/link to building view
2. **Project Reports:** TCO calculations available for project summaries
3. **Dashboard:** Can add OPEX/TCO widgets showing total operational costs

### Running Migration:
```bash
php migrations/run_migration.php create_opex_tables.sql
```

This creates:
- opex_categories table with 10 default categories
- building_opex table for assignments
- tco_config table with 5 default constants
- Necessary indexes and triggers

### Default OPEX Categories:
1. **Rengøring** - 75 kr/m²/år (Maintenance)
2. **Energi** - 120 kr/m²/år (Energy)
3. **Vand og afløb** - 25 kr/m²/år (Utilities)
4. **Forsikring** - 15 kr/m²/år (Insurance)
5. **Ejendomsskat** - 30 kr/m²/år (Tax)
6. **Sikkerhed og overvågning** - 10 kr/m²/år (Security)
7. **Affaldshåndtering** - 20 kr/m²/år (Waste)
8. **Mindre reparationer** - 40 kr/m²/år (Maintenance)
9. **Administration** - 25 kr/m²/år (Admin)
10. **Udvendig vedligehold** - 50 kr/m²/år (Maintenance)

**Total if all categories assigned:** ~410 kr/m²/år

### Example Calculation:
Building: 1000 m², All categories assigned
- Annual OPEX: 410,000 kr/år
- OPEX over 30 years (NPV with 2.5% escalation, 3% discount): ~9.8M kr
- If CAPEX = 5M kr, then TCO = 5M × 1.10 + 9.8M = **15.3M kr**

### Testing Checklist:
- [ ] Test assigning OPEX categories to building
- [ ] Test custom rate override for specific building
- [ ] Test removing OPEX category from building
- [ ] Test OPEX calculations with different building sizes
- [ ] Test TCO calculation endpoint
- [ ] Test admin category management (create/edit/toggle)
- [ ] Test admin TCO config updates
- [ ] Test permission boundaries (user vs admin)
- [ ] Test OPEX summary display
- [ ] Test that non-owners cannot access other users' buildings
- [ ] Test NPV calculation with different config values

## 11. Red Flags Detection and Reporting System ✅

**Location:**
- `modules/red_flags/index.php` - Red Flags module controller
- `modules/red_flags/template.tpl` - Red Flags module template
- `api.php` - Red Flags API endpoints (2 new endpoints)

### Features:
Automatic detection and reporting of critical items requiring attention based on multiple criteria.

### Detection Criteria:

1. **Urgency Level (10 points for critical, 7 for high)**
   - Detects elements marked as `critical` or `high` urgency
   - Highest priority red flags

2. **Condition (8 points)**
   - Detects poor or critical condition ratings
   - Keywords: "dårlig", "kritisk", "poor", "critical"

3. **High Cost (5 points)**
   - Flags elements with CAPEX > 500,000 kr
   - Indicates significant financial impact

4. **Missing Description (2 points)**
   - Flags elements with no description or < 10 characters
   - Data quality issue

5. **Missing Quantity (3 points)**
   - Flags elements without quantity or quantity ≤ 0
   - Data completeness issue

### Scoring System:
Each element gets a **severity score** based on the sum of all detected issues:
- Score 10+: Critical severity
- Score 7-9: High severity
- Score 3-6: Normal severity
- Score 1-2: Low severity

Red flags are sorted by score (highest first) to prioritize the most critical items.

### API Endpoints (2 new):

#### `get_red_flags`
Returns detailed list of all red flags with:
- Element details (name, CAPEX, quantity, condition, etc.)
- Building and project information
- List of detected flags with descriptions
- Severity level and score
- Sorted by priority score

Parameters:
- `project_id` (optional): Filter by specific project

Response:
```json
{
  "success": true,
  "red_flags": [
    {
      "element": {
        "id": 123,
        "name": "Facade renovation",
        "capex": 750000,
        "urgency": "critical",
        "condition": "poor",
        "project_name": "Building A",
        "building_name": "Main Building"
      },
      "flags": [
        {
          "type": "urgency",
          "label": "Høj prioritet",
          "description": "Element markeret som kritisk prioritet",
          "severity": "critical"
        },
        {
          "type": "condition",
          "label": "Dårlig tilstand",
          "description": "Element i dårlig eller kritisk tilstand",
          "severity": "high"
        },
        {
          "type": "high_cost",
          "label": "Høj omkostning",
          "description": "CAPEX over 500.000 kr",
          "severity": "normal"
        }
      ],
      "severity": "critical",
      "score": 23
    }
  ],
  "total_count": 15
}
```

#### `get_red_flags_summary`
Returns statistical summary of red flags:
- Urgency statistics (critical/high counts and CAPEX)
- Condition statistics (poor condition items and CAPEX)
- High cost items (count and total CAPEX)
- Data quality metrics (missing descriptions, missing quantities)

Response:
```json
{
  "success": true,
  "summary": {
    "urgency": {
      "critical": {"count": 5, "capex": 2500000},
      "high": {"count": 12, "capex": 3800000}
    },
    "condition": {
      "poor_condition_count": 8,
      "poor_condition_capex": 1200000
    },
    "costs": {
      "high_cost_count": 15,
      "high_cost_capex": 12000000
    },
    "data_quality": {
      "missing_description": 23,
      "missing_quantity": 17
    },
    "totals": {
      "total_urgent_items": 17,
      "total_urgent_capex": 6300000
    }
  }
}
```

### User Interface:

#### Summary Cards Section:
- **Kritiske elementer** - Count and CAPEX of critical items (red)
- **Høj prioritet** - Count and CAPEX of high priority items (orange)
- **Dårlig tilstand** - Count and CAPEX of poor condition items (blue)
- **Høje omkostninger** - Count and CAPEX of items > 500k
- **Manglende beskrivelse** - Data quality metric
- **Manglende mængde** - Data quality metric

#### Filters:
- **Alvorlighed:** Filter by severity (critical, high, normal, low)
- **Flag type:** Filter by specific issue type (urgency, condition, high_cost, missing_data)
- **Sortering:** Sort by score, CAPEX, or project

#### Red Flags List:
Each red flag item displays:
- **Header:** Severity badge, element name, project/building breadcrumb
- **Score badge:** Visual indicator of priority (higher = more urgent)
- **Flags section:** List of all detected issues with descriptions
- **Details section:** CAPEX, quantity, unit, condition
- **Actions:** "Rediger element" button linking to element editor

Visual design:
- Critical items: Red left border
- High items: Orange left border
- Hover effect for better UX
- Color-coded flag badges matching severity

### Permission Controls:
- **View Red Flags:** Users can view red flags for their own projects
- **Admin:** Can view red flags across all projects
- Filtering respects ownership and admin permissions

### Integration Points:
1. **Dashboard:** Can add Red Flags widget showing critical items count
2. **Project View:** Add "Red Flags" button to show project-specific issues
3. **Building View:** Show red flags count for specific building
4. **Reports:** Include red flags summary in executive reports

### Use Cases:

1. **Daily Monitoring:**
   - Check red flags dashboard each morning
   - Address critical items first (highest scores)

2. **Project Health Check:**
   - Filter red flags by project
   - Review before client meetings
   - Track resolution progress

3. **Data Quality:**
   - Use missing data filters
   - Assign team to complete descriptions/quantities
   - Improve overall data completeness

4. **Budget Planning:**
   - Filter by high cost items
   - Review critical + high cost combination
   - Prioritize funding allocation

### Example Scenario:
Project has 100 building elements:
- 5 marked as critical urgency → 5 red flags (score 10 each)
- 10 with poor condition → 10 red flags (score 8 each)
- 20 with CAPEX > 500k → 20 red flags (score 5 each)
- 15 missing descriptions → 15 red flags (score 2 each)

Total: 50 red flags detected automatically
Critical items (score 10+) appear at top of list
User can focus on highest priority items first

### Testing Checklist:
- [ ] Test red flags detection for critical urgency
- [ ] Test red flags detection for high urgency
- [ ] Test red flags detection for poor condition
- [ ] Test red flags detection for high CAPEX
- [ ] Test red flags detection for missing data
- [ ] Test scoring system prioritization
- [ ] Test filtering by severity
- [ ] Test filtering by flag type
- [ ] Test sorting options (score, CAPEX, project)
- [ ] Test project-specific red flags view
- [ ] Test permission boundaries (users see only their projects)
- [ ] Test summary statistics accuracy
- [ ] Test red flags with multiple flags on same element
- [ ] Test empty state when no red flags exist
