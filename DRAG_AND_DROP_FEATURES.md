# Drag-and-Drop Funktioner - Komplet Oversigt

**Dato:** 2026-01-18
**Branch:** claude/code-review-optimization-6Y6Su

## Oversigt

Dette dokument beskriver alle implementerede drag-and-drop og sorteringsfunktioner i systemet.

---

## 🎯 Implementerede Moduler med Drag-and-Drop

### 1. **Building Module** - Bygnings Sortering

**Endpoint:** `POST /api_new.php?module=building&action=update_order`

**Funktionalitet:**
- Sortér bygninger inden for et projekt
- Opdater `display_order` for hver bygning

**Request:**
```json
{
  "project_id": 123,
  "building_ids": [5, 3, 8, 1, 2]
}
```

**Anvendelse:**
- Drag-and-drop sortering af bygninger i projekt oversigt
- Organiser bygninger efter prioritet eller fysisk placering

---

### 2. **Element Module** - Element Hierarki Sortering

**Endpoint:** `POST /api_new.php?module=element&action=update_order`

**Funktionalitet:**
- Sortér elementer på samme niveau
- Bevar hierarki struktur

**Request:**
```json
{
  "building_id": 10,
  "parent_id": null,  // eller element ID
  "element_ids": [12, 15, 11, 14]
}
```

**Endpoint:** `POST /api_new.php?module=element&action=move`

**Funktionalitet:**
- Flyt element til ny forælder
- Circular reference check
- Automatisk opdatering af `display_order`

**Request:**
```json
{
  "id": 15,
  "new_parent_id": 12  // eller null for root level
}
```

**Anvendelse:**
- Reorganiser element hierarki
- Flyt elementer mellem niveauer
- Drag-and-drop i træ-struktur

---

### 3. **Image Module** - Billede Galleri Sortering

**Fil:** `modules/image/api.php`

**Endpoint:** `POST /api_new.php?module=image&action=reorder`

**Funktionalitet:**
- Sortér billeder i galleri
- Opdater visningsrækkefølge
- Primært billede prioritering

**Request:**
```json
{
  "element_id": 20,
  "image_ids": [45, 42, 48, 43]
}
```

**Endpoint:** `POST /api_new.php?module=image&action=set_primary`

**Funktionalitet:**
- Sæt primært billede
- Automatisk unset af tidligere primært

**Request:**
```json
{
  "id": 45
}
```

**Features:**
- Upload enkelt/bulk billeder
- Drag-and-drop sortering
- Primær billede valg
- Pagination (50 per side)
- Metadata (description, tags)

**Understøttede formater:**
- JPEG, PNG, GIF, WebP
- Max 10MB per billede

---

### 4. **Price Catalog Module** - Priskatalog Sortering

**Fil:** `modules/price_catalog/api.php`

**Endpoint (Kategorier):** `POST /api_new.php?module=price_catalog&action=reorder_categories`

**Funktionalitet:**
- Sortér pris kategorier
- Hierarkisk struktur support

**Request:**
```json
{
  "parent_id": null,  // eller kategori ID
  "category_ids": [3, 1, 5, 2]
}
```

**Endpoint (Items):** `POST /api_new.php?module=price_catalog&action=reorder_items`

**Funktionalitet:**
- Sortér katalog elementer inden for kategori
- Opdater visningsrækkefølge

**Request:**
```json
{
  "category_id": 5,
  "item_ids": [101, 105, 103, 102]
}
```

**Features:**
- Kategori hierarki med drag-and-drop
- Item sortering per kategori
- Pris historik
- Import/export CSV
- Søgning med relevans sortering

---

### 5. **Template Module** - Budget Template Sortering

**Fil:** `modules/template/api.php`

**Endpoint:** `POST /api_new.php?module=template&action=reorder_items`

**Funktionalitet:**
- Sortér budget linjer i template
- Opdater display order

**Request:**
```json
{
  "template_id": 8,
  "item_ids": [52, 55, 51, 54, 53]
}
```

**Features:**
- CRUD for templates (CAPEX, OPEX, Reinstatement)
- Drag-and-drop linjer i template
- Duplicate templates
- Apply template til element
- Kategori organisering

---

### 6. **Menu Module** - Navigation Menu Sortering

**Fil:** `modules/menu/api.php`

**Endpoint:** `POST /api_new.php?module=menu&action=reorder`

**Funktionalitet:**
- Sortér menu punkter på samme niveau
- Hierarkisk menu support

**Request:**
```json
{
  "parent_id": null,  // eller menu item ID
  "item_ids": [1, 3, 2, 5, 4]
}
```

**Endpoint:** `POST /api_new.php?module=menu&action=move_item`

**Funktionalitet:**
- Flyt menu punkt til ny forælder
- Drag-and-drop mellem niveauer
- Circular reference prevention

**Request:**
```json
{
  "id": 8,
  "new_parent_id": 3  // eller null
}
```

**Features:**
- Hierarkisk menu struktur
- Permission-baseret filtrering
- Aktiv/inaktiv status
- Icon support
- URL routing

---

## 🗄️ Database Tabeller med Display Order

Alle følgende tabeller har `display_order` felt for sortering:

| Tabel | Display Order Scope | Related Actions |
|-------|---------------------|-----------------|
| `buildings` | Per projekt | `building.update_order` |
| `building_elements` | Per parent/building | `element.update_order`, `element.move` |
| `element_images` | Per element | `image.reorder`, `image.set_primary` |
| `price_categories` | Per parent/root | `price_catalog.reorder_categories` |
| `price_catalog_items` | Per kategori | `price_catalog.reorder_items` |
| `budget_template_items` | Per template | `template.reorder_items` |
| `menu_items` | Per parent/root | `menu.reorder`, `menu.move_item` |

---

## 🔄 Common Pattern for Drag-and-Drop

Alle drag-and-drop endpoints følger dette pattern:

### 1. **Reorder (samme niveau)**

```php
function handle_reorder(array $user): array {
    csrf_require();

    $parentId = isset($_POST['parent_id']) ? sanitize_int($_POST['parent_id']) : null;
    $itemIds = $_POST['item_ids'] ?? [];

    db_begin_transaction();
    try {
        foreach ($itemIds as $order => $itemId) {
            $itemId = sanitize_int($itemId);
            db_update('table_name',
                ['display_order' => $order + 1],
                'id = :id AND parent_id ' . ($parentId ? '= :parent_id' : 'IS NULL'),
                $parentId ? ['id' => $itemId, 'parent_id' => $parentId] : ['id' => $itemId]
            );
        }
        db_commit();
        return ['success' => true];
    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Failed'];
    }
}
```

### 2. **Move (skifte niveau)**

```php
function handle_move(array $user): array {
    csrf_require();

    $itemId = sanitize_int($_POST['id'] ?? 0);
    $newParentId = isset($_POST['new_parent_id']) ? sanitize_int($_POST['new_parent_id']) : null;

    // Validate (check circular reference, etc.)

    db_begin_transaction();
    try {
        // Get next order in new location
        $maxOrder = db_value("SELECT COALESCE(MAX(display_order), 0) FROM table_name WHERE parent_id ...");

        db_update('table_name',
            ['parent_id' => $newParentId, 'display_order' => $maxOrder + 1],
            'id = :id',
            ['id' => $itemId]
        );

        db_commit();
        return ['success' => true];
    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Failed'];
    }
}
```

---

## 📱 Frontend Implementation Guide

### Drag-and-Drop Libraries

Anbefalet: **Sortable.js** eller **React DnD**

### Example (Sortable.js):

```javascript
// Bygnings sortering
const sortable = new Sortable(document.getElementById('buildings-list'), {
    animation: 150,
    onEnd: function(evt) {
        const buildingIds = Array.from(evt.to.children).map(el => el.dataset.id);

        fetch('/api_new.php?module=building&action=update_order', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                project_id: currentProjectId,
                building_ids: buildingIds
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('Rækkefølge opdateret');
            }
        });
    }
});
```

### Example (React DnD):

```jsx
// Image gallery drag-and-drop
import { DndProvider, useDrag, useDrop } from 'react-dnd';

function ImageGallery({ elementId, images }) {
    const [imageList, setImageList] = useState(images);

    const moveImage = (dragIndex, hoverIndex) => {
        const newImages = [...imageList];
        const dragImage = newImages[dragIndex];
        newImages.splice(dragIndex, 1);
        newImages.splice(hoverIndex, 0, dragImage);
        setImageList(newImages);
    };

    const saveOrder = () => {
        const imageIds = imageList.map(img => img.id);

        fetch('/api_new.php?module=image&action=reorder', {
            method: 'POST',
            body: JSON.stringify({
                element_id: elementId,
                image_ids: imageIds
            })
        });
    };

    return (
        <DndProvider backend={HTML5Backend}>
            <div className="image-gallery">
                {imageList.map((image, index) => (
                    <DraggableImage
                        key={image.id}
                        image={image}
                        index={index}
                        moveImage={moveImage}
                    />
                ))}
            </div>
            <button onClick={saveOrder}>Gem rækkefølge</button>
        </DndProvider>
    );
}
```

---

## 🔒 Sikkerhed

Alle drag-and-drop endpoints har:

✅ **CSRF Protection** - `csrf_require()` på alle POST requests
✅ **Permission Checks** - Project access validation
✅ **Input Sanitization** - `sanitize_int()` på alle IDs
✅ **Transaction Safety** - Database rollback ved fejl
✅ **Circular Reference Prevention** - Ved move operations

---

## 📊 Performance Optimering

**Database queries:**
- Batch updates i single transaction
- Indexed `display_order` felter
- Efficient parent_id lookups

**Frontend:**
- Optimistic UI updates
- Debounced save (optional)
- Skeleton loaders under drag

---

## 🎨 UX Best Practices

### Visual Feedback
```css
.dragging {
    opacity: 0.5;
    cursor: move;
}

.drag-over {
    border: 2px dashed #4CAF50;
}
```

### Drag Handle
```html
<div class="item">
    <span class="drag-handle">⋮⋮</span>
    <span class="content">Item content</span>
</div>
```

### Undo/Redo (Optional)
```javascript
const history = [];

function saveState() {
    history.push(JSON.stringify(currentOrder));
}

function undo() {
    if (history.length > 0) {
        const previousState = JSON.parse(history.pop());
        restoreOrder(previousState);
    }
}
```

---

## 📋 Modul Oversigt

| Modul | Fil | Drag-and-Drop Actions | Status |
|-------|-----|------------------------|--------|
| Building | modules/building/api.php | update_order | ✅ |
| Element | modules/element/api.php | update_order, move | ✅ |
| Image | modules/image/api.php | reorder, set_primary | ✅ |
| Price Catalog | modules/price_catalog/api.php | reorder_categories, reorder_items | ✅ |
| Template | modules/template/api.php | reorder_items | ✅ |
| Menu | modules/menu/api.php | reorder, move_item | ✅ |

**Total:** 6 moduler med 10 drag-and-drop actions

---

## 🧪 Testing Checklist

### Backend Testing
- [ ] Reorder within same parent
- [ ] Move to different parent
- [ ] Circular reference prevention
- [ ] Permission checks
- [ ] Transaction rollback on error

### Frontend Testing
- [ ] Visual drag feedback
- [ ] Drop zones highlighting
- [ ] Auto-scroll on drag near edge
- [ ] Touch device support
- [ ] Keyboard accessibility

### Edge Cases
- [ ] Empty lists
- [ ] Single item
- [ ] Nested hierarchies (max depth)
- [ ] Concurrent updates
- [ ] Network errors

---

## 🚀 Migration Notes

Database migration fil: `migrations/add_module_support_tables.sql`

**Inkluderer:**
- `element_images` tabel med `display_order` og `is_primary`
- `price_categories` tabel med hierarki support
- `price_catalog_items` tabel med sortering
- `budget_template_items` tabel med `display_order`
- `menu_items` tabel med hierarkisk struktur
- Default menu struktur
- Permission modules for nye moduler

---

## 📝 API Endpoints Reference

### Building
```
POST /api_new.php?module=building&action=update_order
  project_id, building_ids[]
```

### Element
```
POST /api_new.php?module=element&action=update_order
  building_id, parent_id?, element_ids[]

POST /api_new.php?module=element&action=move
  id, new_parent_id?
```

### Image
```
POST /api_new.php?module=image&action=reorder
  element_id, image_ids[]

POST /api_new.php?module=image&action=set_primary
  id
```

### Price Catalog
```
POST /api_new.php?module=price_catalog&action=reorder_categories
  parent_id?, category_ids[]

POST /api_new.php?module=price_catalog&action=reorder_items
  category_id?, item_ids[]
```

### Template
```
POST /api_new.php?module=template&action=reorder_items
  template_id, item_ids[]
```

### Menu
```
POST /api_new.php?module=menu&action=reorder
  parent_id?, item_ids[]

POST /api_new.php?module=menu&action=move_item
  id, new_parent_id?
```

---

**Implementeret af:** Claude Code
**Session:** claude/code-review-optimization-6Y6Su
**Dato:** 2026-01-18
