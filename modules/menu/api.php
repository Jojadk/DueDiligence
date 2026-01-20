<?php
/**
 * Menu Module API - Refactored with API Helpers
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
require_once __DIR__ . '/../../core/api-helpers.php';

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
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'label' => ['string', 'POST', true],
        'url' => ['string', 'POST', false, ''],
        'icon' => ['string', 'POST', false, ''],
        'parent_id' => ['int', 'POST', false, null],
        'required_module' => ['string', 'POST', false, ''],
        'required_permission' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $data = $validation['data'];
    $parentId = $data['parent_id'];

    // Use api_crud_create with display_order logic
    return api_crud_create(
        'menu_items',
        $data,
        function(&$itemData) use ($parentId) {
            // Get next display order
            $maxOrder = db_value("
                SELECT COALESCE(MAX(display_order), 0)
                FROM menu_items
                WHERE parent_id " . ($parentId ? "= :parent_id" : "IS NULL"),
                $parentId ? ['parent_id' => $parentId] : []
            );

            $itemData['display_order'] = $maxOrder + 1;
            $itemData['is_active'] = true;
            $itemData['created_at'] = date('Y-m-d H:i:s');
        },
        function($itemId) {
            log_activity('menu_item_created', 'menu_item', $itemId);
        },
        'Menu punkt oprettet',
        'Kunne ikke oprette menu punkt'
    );
}

/**
 * Update menu item
 * POST ?module=menu&action=update_item
 */
function handle_update_item(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate ID
    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $itemId = $validation['data']['id'];

    // Validate update fields (all optional)
    $updateValidation = api_validate_params([
        'label' => ['string', 'POST', false],
        'url' => ['string', 'POST', false],
        'icon' => ['string', 'POST', false],
        'required_module' => ['string', 'POST', false],
        'required_permission' => ['string', 'POST', false]
    ]);

    if (!$updateValidation['success']) {
        return api_error($updateValidation['errors']);
    }

    // Filter out null values
    $updateData = array_filter(
        $updateValidation['data'],
        fn($value) => $value !== null
    );

    // Handle is_active separately (boolean)
    if (isset($_POST['is_active'])) {
        $updateData['is_active'] = (bool)$_POST['is_active'];
    }

    if (empty($updateData)) {
        return api_error('Ingen felter at opdatere');
    }

    // Use api_crud_update
    return api_crud_update(
        'menu_items',
        $itemId,
        $updateData,
        null, // No permission check needed
        function($itemId) {
            log_activity('menu_item_updated', 'menu_item', $itemId);
        },
        'Menu punkt opdateret',
        'Kunne ikke opdatere menu punkt',
        'Menu punkt ikke fundet',
        'Ingen adgang til at redigere menu punkt'
    );
}

/**
 * Delete menu item
 * POST ?module=menu&action=delete_item
 */
function handle_delete_item(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate ID
    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $itemId = $validation['data']['id'];

    // Use api_crud_delete with child check
    return api_crud_delete(
        'menu_items',
        $itemId,
        null, // No permission check
        function($itemId) {
            // Check if has children
            $childCount = db_value("
                SELECT COUNT(*) FROM menu_items WHERE parent_id = :id
            ", ['id' => $itemId]);

            if ($childCount > 0) {
                throw new Exception('Kan ikke slette menu punkt med underpunkter');
            }
        },
        function($itemId) {
            log_activity('menu_item_deleted', 'menu_item', $itemId);
        },
        'Menu punkt slettet',
        'Kunne ikke slette menu punkt',
        'Menu punkt ikke fundet',
        'Ingen adgang til at slette menu punkt'
    );
}

/**
 * Reorder menu items (drag-and-drop)
 * POST ?module=menu&action=reorder
 */
function handle_reorder(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'parent_id' => ['int', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $parentId = $validation['data']['parent_id'];
    $itemIds = $_POST['item_ids'] ?? [];

    if (!is_array($itemIds) || empty($itemIds)) {
        return api_error('Menu ID liste er påkrævet');
    }

    // Use api_transaction for reordering
    return api_transaction(
        function() use ($itemIds, $parentId) {
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

            log_activity('menu_items_reordered', 'system', 0);

            return ['reordered_count' => count($itemIds)];
        },
        'Menu rækkefølge opdateret',
        'Kunne ikke opdatere rækkefølge'
    );
}

/**
 * Move item to new parent (drag-and-drop between levels)
 * POST ?module=menu&action=move_item
 */
function handle_move_item(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'new_parent_id' => ['int', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $itemId = $validation['data']['id'];
    $newParentId = $validation['data']['new_parent_id'];

    // Prevent moving to self
    if ($newParentId === $itemId) {
        return api_error('Menu punkt kan ikke flyttes til sig selv');
    }

    // Check for circular reference
    if ($newParentId !== null && is_menu_descendant($newParentId, $itemId)) {
        return api_error('Menu punkt kan ikke flyttes til et af sine underpunkter');
    }

    // Use api_transaction for move operation
    return api_transaction(
        function() use ($itemId, $newParentId) {
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

            log_activity('menu_item_moved', 'menu_item', $itemId);

            return ['item_id' => $itemId];
        },
        'Menu punkt flyttet',
        'Kunne ikke flytte menu punkt'
    );
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
