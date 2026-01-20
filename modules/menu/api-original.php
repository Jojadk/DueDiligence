<?php
/**
 * Menu Module API
 *
 * Handles navigation menu management with drag-and-drop sorting
 *
 * Actions:
 * - get_menu: Get menu structure
 * - get_user_menu: Get menu for current user (with permissions)
 * - create_item: Create menu item
 * - update_item: Update menu item
 * - delete_item: Delete menu item
 * - reorder: Reorder menu items (drag-and-drop)
 * - move_item: Move item to new parent (drag-and-drop between levels)
 */

require_once __DIR__ . '/../../core/permissions.php';

/**
 * Get full menu structure
 * GET ?module=menu&action=get_menu
 */
function handle_get_menu(array $user): array {
    $menuItems = db_fetch_all("
        SELECT
            mi.*
        FROM menu_items mi
        ORDER BY mi.display_order ASC, mi.label ASC
    ");

    // Build hierarchy
    $menu = build_menu_tree($menuItems);

    return [
        'success' => true,
        'menu' => $menu
    ];
}

/**
 * Get menu for current user (filtered by permissions)
 * GET ?module=menu&action=get_user_menu
 */
function handle_get_user_menu(array $user): array {
    $menuItems = db_fetch_all("
        SELECT
            mi.*
        FROM menu_items mi
        WHERE mi.is_active = TRUE
        ORDER BY mi.display_order ASC, mi.label ASC
    ");

    // Filter by permissions
    $filteredItems = [];
    foreach ($menuItems as $item) {
        // If menu item requires a module permission, check it
        if ($item['required_module'] && $item['required_permission']) {
            if (!has_module_permission($user, $item['required_module'], $item['required_permission'])) {
                continue;
            }
        }

        $filteredItems[] = $item;
    }

    // Build hierarchy
    $menu = build_menu_tree($filteredItems);

    return [
        'success' => true,
        'menu' => $menu
    ];
}

/**
 * Create menu item
 * POST ?module=menu&action=create_item
 */
function handle_create_item(array $user): array {
    csrf_require();

    $label = sanitize_string($_POST['label'] ?? '');
    $url = sanitize_string($_POST['url'] ?? '');
    $icon = sanitize_string($_POST['icon'] ?? '');
    $parentId = isset($_POST['parent_id']) ? sanitize_int($_POST['parent_id']) : null;

    if (!$label) {
        return ['success' => false, 'error' => 'Label er påkrævet'];
    }

    db_begin_transaction();
    try {
        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM menu_items
            WHERE parent_id " . ($parentId ? "= :parent_id" : "IS NULL"),
            $parentId ? ['parent_id' => $parentId] : []
        );

        $itemData = [
            'parent_id' => $parentId,
            'label' => $label,
            'url' => $url,
            'icon' => $icon,
            'required_module' => sanitize_string($_POST['required_module'] ?? ''),
            'required_permission' => sanitize_string($_POST['required_permission'] ?? ''),
            'display_order' => $maxOrder + 1,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $itemId = db_insert('menu_items', $itemData);

        db_commit();

        log_activity('menu_item_created', 'menu_item', $itemId);

        return [
            'success' => true,
            'item_id' => $itemId,
            'message' => 'Menu punkt oprettet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke oprette menu punkt'];
    }
}

/**
 * Update menu item
 * POST ?module=menu&action=update_item
 */
function handle_update_item(array $user): array {
    csrf_require();

    $itemId = sanitize_int($_POST['id'] ?? 0);

    if (!$itemId) {
        return ['success' => false, 'error' => 'Menu ID mangler'];
    }

    db_begin_transaction();
    try {
        $updateData = [];

        if (isset($_POST['label'])) {
            $updateData['label'] = sanitize_string($_POST['label']);
        }

        if (isset($_POST['url'])) {
            $updateData['url'] = sanitize_string($_POST['url']);
        }

        if (isset($_POST['icon'])) {
            $updateData['icon'] = sanitize_string($_POST['icon']);
        }

        if (isset($_POST['required_module'])) {
            $updateData['required_module'] = sanitize_string($_POST['required_module']);
        }

        if (isset($_POST['required_permission'])) {
            $updateData['required_permission'] = sanitize_string($_POST['required_permission']);
        }

        if (isset($_POST['is_active'])) {
            $updateData['is_active'] = (bool)$_POST['is_active'];
        }

        if (!empty($updateData)) {
            db_update('menu_items', $updateData, 'id = :id', ['id' => $itemId]);
        }

        db_commit();

        log_activity('menu_item_updated', 'menu_item', $itemId);

        return [
            'success' => true,
            'message' => 'Menu punkt opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere menu punkt'];
    }
}

/**
 * Delete menu item
 * POST ?module=menu&action=delete_item
 */
function handle_delete_item(array $user): array {
    csrf_require();

    $itemId = sanitize_int($_POST['id'] ?? 0);

    if (!$itemId) {
        return ['success' => false, 'error' => 'Menu ID mangler'];
    }

    // Check if has children
    $childCount = db_value("
        SELECT COUNT(*) FROM menu_items WHERE parent_id = :id
    ", ['id' => $itemId]);

    if ($childCount > 0) {
        return [
            'success' => false,
            'error' => 'Kan ikke slette menu punkt med underpunkter'
        ];
    }

    db_begin_transaction();
    try {
        db_delete('menu_items', 'id = :id', ['id' => $itemId]);

        db_commit();

        log_activity('menu_item_deleted', 'menu_item', $itemId);

        return [
            'success' => true,
            'message' => 'Menu punkt slettet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette menu punkt'];
    }
}

/**
 * Reorder menu items (drag-and-drop)
 * POST ?module=menu&action=reorder
 */
function handle_reorder(array $user): array {
    csrf_require();

    $itemIds = $_POST['item_ids'] ?? [];
    $parentId = isset($_POST['parent_id']) ? sanitize_int($_POST['parent_id']) : null;

    if (!is_array($itemIds)) {
        return ['success' => false, 'error' => 'Menu ID liste er påkrævet'];
    }

    db_begin_transaction();
    try {
        foreach ($itemIds as $order => $itemId) {
            $itemId = sanitize_int($itemId);
            $whereClause = 'id = :id AND parent_id ' . ($parentId ? '= :parent_id' : 'IS NULL');
            $params = ['id' => $itemId];
            if ($parentId) {
                $params['parent_id'] = $parentId;
            }

            db_update('menu_items',
                ['display_order' => $order + 1],
                $whereClause,
                $params
            );
        }

        db_commit();

        log_activity('menu_items_reordered', 'system', 0);

        return [
            'success' => true,
            'message' => 'Menu rækkefølge opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rækkefølge'];
    }
}

/**
 * Move item to new parent (drag-and-drop between levels)
 * POST ?module=menu&action=move_item
 */
function handle_move_item(array $user): array {
    csrf_require();

    $itemId = sanitize_int($_POST['id'] ?? 0);
    $newParentId = isset($_POST['new_parent_id']) ? sanitize_int($_POST['new_parent_id']) : null;

    if (!$itemId) {
        return ['success' => false, 'error' => 'Menu ID mangler'];
    }

    // Prevent moving to self
    if ($newParentId === $itemId) {
        return ['success' => false, 'error' => 'Menu punkt kan ikke flyttes til sig selv'];
    }

    // Check for circular reference (is new parent a descendant?)
    if ($newParentId !== null && is_menu_descendant($newParentId, $itemId)) {
        return ['success' => false, 'error' => 'Menu punkt kan ikke flyttes til et af sine underpunkter'];
    }

    db_begin_transaction();
    try {
        // Get next display order in new location
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM menu_items
            WHERE parent_id " . ($newParentId ? "= :parent_id" : "IS NULL"),
            $newParentId ? ['parent_id' => $newParentId] : []
        );

        db_update('menu_items',
            [
                'parent_id' => $newParentId,
                'display_order' => $maxOrder + 1
            ],
            'id = :id',
            ['id' => $itemId]
        );

        db_commit();

        log_activity('menu_item_moved', 'menu_item', $itemId);

        return [
            'success' => true,
            'message' => 'Menu punkt flyttet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke flytte menu punkt'];
    }
}

/**
 * Helper function to build menu tree
 */
function build_menu_tree(array $items): array {
    $indexed = [];
    $tree = [];

    // First pass: index by ID
    foreach ($items as $item) {
        $item['children'] = [];
        $indexed[$item['id']] = $item;
    }

    // Second pass: build tree
    foreach ($indexed as $id => $item) {
        if ($item['parent_id'] === null) {
            $tree[] = &$indexed[$id];
        } else {
            if (isset($indexed[$item['parent_id']])) {
                $indexed[$item['parent_id']]['children'][] = &$indexed[$id];
            }
        }
    }

    return $tree;
}

/**
 * Helper function to check if item is descendant
 */
function is_menu_descendant(int $itemId, int $ancestorId): bool {
    $parent = db_fetch("
        SELECT parent_id FROM menu_items WHERE id = :id
    ", ['id' => $itemId]);

    if (!$parent || !$parent['parent_id']) {
        return false;
    }

    if ($parent['parent_id'] == $ancestorId) {
        return true;
    }

    return is_menu_descendant($parent['parent_id'], $ancestorId);
}
