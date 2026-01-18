<?php
/**
 * Price Catalog Module API
 *
 * Handles price catalog management with categories, items, and pricing
 *
 * Actions:
 * - get_categories: Get price categories
 * - create_category: Create category
 * - update_category: Update category
 * - delete_category: Delete category
 * - reorder_categories: Reorder categories (drag-and-drop)
 *
 * - get_items: Get catalog items
 * - search_items: Search catalog items
 * - get_item: Get single item
 * - create_item: Create catalog item
 * - update_item: Update item
 * - delete_item: Delete item
 * - reorder_items: Reorder items (drag-and-drop)
 *
 * - import_catalog: Import catalog from file
 * - export_catalog: Export catalog
 * - get_price_history: Get price history for item
 */

require_once __DIR__ . '/../../core/permissions.php';

/**
 * Get price categories
 * GET ?module=price_catalog&action=get_categories
 */
function handle_get_categories(array $user): array {
    $categories = db_fetch_all("
        SELECT
            pc.*,
            COUNT(pci.id) as item_count
        FROM price_categories pc
        LEFT JOIN price_catalog_items pci ON pci.category_id = pc.id
        GROUP BY pc.id
        ORDER BY pc.display_order ASC, pc.name ASC
    ");

    return [
        'success' => true,
        'categories' => $categories
    ];
}

/**
 * Create category
 * POST ?module=price_catalog&action=create_category
 */
function handle_create_category(array $user): array {
    csrf_require();

    $name = sanitize_string($_POST['name'] ?? '');
    $description = sanitize_string($_POST['description'] ?? '');
    $parentId = isset($_POST['parent_id']) ? sanitize_int($_POST['parent_id']) : null;

    if (!$name) {
        return ['success' => false, 'error' => 'Kategori navn er påkrævet'];
    }

    db_begin_transaction();
    try {
        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM price_categories
            WHERE parent_id " . ($parentId ? "= :parent_id" : "IS NULL"),
            $parentId ? ['parent_id' => $parentId] : []
        );

        $categoryData = [
            'name' => $name,
            'description' => $description,
            'parent_id' => $parentId,
            'display_order' => $maxOrder + 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $categoryId = db_insert('price_categories', $categoryData);

        db_commit();

        log_activity('price_category_created', 'price_category', $categoryId);

        return [
            'success' => true,
            'category_id' => $categoryId,
            'message' => 'Kategori oprettet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke oprette kategori'];
    }
}

/**
 * Update category
 * POST ?module=price_catalog&action=update_category
 */
function handle_update_category(array $user): array {
    csrf_require();

    $categoryId = sanitize_int($_POST['id'] ?? 0);

    if (!$categoryId) {
        return ['success' => false, 'error' => 'Kategori ID mangler'];
    }

    db_begin_transaction();
    try {
        $updateData = [];

        if (isset($_POST['name'])) {
            $updateData['name'] = sanitize_string($_POST['name']);
        }

        if (isset($_POST['description'])) {
            $updateData['description'] = sanitize_string($_POST['description']);
        }

        if (!empty($updateData)) {
            db_update('price_categories', $updateData, 'id = :id', ['id' => $categoryId]);
        }

        db_commit();

        log_activity('price_category_updated', 'price_category', $categoryId);

        return [
            'success' => true,
            'message' => 'Kategori opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere kategori'];
    }
}

/**
 * Delete category
 * POST ?module=price_catalog&action=delete_category
 */
function handle_delete_category(array $user): array {
    csrf_require();

    $categoryId = sanitize_int($_POST['id'] ?? 0);

    if (!$categoryId) {
        return ['success' => false, 'error' => 'Kategori ID mangler'];
    }

    // Check if category has items
    $itemCount = db_value("
        SELECT COUNT(*) FROM price_catalog_items WHERE category_id = :id
    ", ['id' => $categoryId]);

    if ($itemCount > 0) {
        return [
            'success' => false,
            'error' => 'Kan ikke slette kategori med elementer. Flyt eller slet elementerne først.'
        ];
    }

    db_begin_transaction();
    try {
        db_delete('price_categories', 'id = :id', ['id' => $categoryId]);

        db_commit();

        log_activity('price_category_deleted', 'price_category', $categoryId);

        return [
            'success' => true,
            'message' => 'Kategori slettet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette kategori'];
    }
}

/**
 * Reorder categories (drag-and-drop)
 * POST ?module=price_catalog&action=reorder_categories
 */
function handle_reorder_categories(array $user): array {
    csrf_require();

    $categoryIds = $_POST['category_ids'] ?? [];
    $parentId = isset($_POST['parent_id']) ? sanitize_int($_POST['parent_id']) : null;

    if (!is_array($categoryIds)) {
        return ['success' => false, 'error' => 'Kategori ID liste er påkrævet'];
    }

    db_begin_transaction();
    try {
        foreach ($categoryIds as $order => $categoryId) {
            $categoryId = sanitize_int($categoryId);
            $whereClause = 'id = :id AND parent_id ' . ($parentId ? '= :parent_id' : 'IS NULL');
            $params = ['id' => $categoryId];
            if ($parentId) {
                $params['parent_id'] = $parentId;
            }

            db_update('price_categories',
                ['display_order' => $order + 1],
                $whereClause,
                $params
            );
        }

        db_commit();

        log_activity('price_categories_reordered', 'system', 0);

        return [
            'success' => true,
            'message' => 'Kategori rækkefølge opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rækkefølge'];
    }
}

/**
 * Get catalog items
 * GET ?module=price_catalog&action=get_items&category_id=X
 */
function handle_get_items(array $user): array {
    $categoryId = isset($_GET['category_id']) ? sanitize_int($_GET['category_id']) : null;
    $page = sanitize_int($_GET['page'] ?? 1);
    $limit = sanitize_int($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    $query = "
        SELECT
            pci.*,
            pc.name as category_name
        FROM price_catalog_items pci
        LEFT JOIN price_categories pc ON pc.id = pci.category_id
    ";

    $params = ['limit' => $limit, 'offset' => $offset];

    if ($categoryId !== null) {
        $query .= " WHERE pci.category_id = :category_id";
        $params['category_id'] = $categoryId;
    }

    $query .= " ORDER BY pci.display_order ASC, pci.item_code ASC
                LIMIT :limit OFFSET :offset";

    $items = db_fetch_all($query, $params);

    // Get total count
    $countQuery = "SELECT COUNT(*) FROM price_catalog_items";
    if ($categoryId !== null) {
        $countQuery .= " WHERE category_id = :category_id";
    }
    $totalCount = db_value($countQuery, $categoryId !== null ? ['category_id' => $categoryId] : []);

    return [
        'success' => true,
        'items' => $items,
        'pagination' => [
            'total' => (int)$totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ]
    ];
}

/**
 * Search catalog items
 * GET ?module=price_catalog&action=search_items&query=X
 */
function handle_search_items(array $user): array {
    $searchQuery = sanitize_string($_GET['query'] ?? '');
    $categoryId = isset($_GET['category_id']) ? sanitize_int($_GET['category_id']) : null;
    $limit = sanitize_int($_GET['limit'] ?? 20);

    if (strlen($searchQuery) < 2) {
        return ['success' => false, 'error' => 'Søgeforespørgsel skal være mindst 2 tegn'];
    }

    $query = "
        SELECT
            pci.*,
            pc.name as category_name
        FROM price_catalog_items pci
        LEFT JOIN price_categories pc ON pc.id = pci.category_id
        WHERE (
            pci.item_code ILIKE :search
            OR pci.name ILIKE :search
            OR pci.description ILIKE :search
        )
    ";

    $params = ['search' => '%' . $searchQuery . '%', 'limit' => $limit];

    if ($categoryId !== null) {
        $query .= " AND pci.category_id = :category_id";
        $params['category_id'] = $categoryId;
    }

    $query .= " ORDER BY
                    CASE
                        WHEN pci.item_code ILIKE :exact THEN 1
                        WHEN pci.name ILIKE :exact THEN 2
                        ELSE 3
                    END,
                    pci.item_code ASC
                LIMIT :limit";

    $params['exact'] = $searchQuery . '%';

    $items = db_fetch_all($query, $params);

    return [
        'success' => true,
        'items' => $items,
        'count' => count($items)
    ];
}

/**
 * Get single catalog item
 * GET ?module=price_catalog&action=get_item&id=X
 */
function handle_get_item(array $user): array {
    $itemId = sanitize_int($_GET['id'] ?? 0);

    if (!$itemId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    $item = db_fetch("
        SELECT
            pci.*,
            pc.name as category_name
        FROM price_catalog_items pci
        LEFT JOIN price_categories pc ON pc.id = pci.category_id
        WHERE pci.id = :id
    ", ['id' => $itemId]);

    if (!$item) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Get price history
    $priceHistory = db_fetch_all("
        SELECT * FROM price_history
        WHERE catalog_item_id = :item_id
        ORDER BY effective_date DESC
        LIMIT 10
    ", ['item_id' => $itemId]);

    $item['price_history'] = $priceHistory;

    return [
        'success' => true,
        'item' => $item
    ];
}

/**
 * Create catalog item
 * POST ?module=price_catalog&action=create_item
 */
function handle_create_item(array $user): array {
    csrf_require();

    $categoryId = isset($_POST['category_id']) ? sanitize_int($_POST['category_id']) : null;
    $itemCode = sanitize_string($_POST['item_code'] ?? '');
    $name = sanitize_string($_POST['name'] ?? '');
    $unit = sanitize_string($_POST['unit'] ?? 'stk');
    $price = sanitize_float($_POST['price'] ?? 0);

    if (!$name) {
        return ['success' => false, 'error' => 'Navn er påkrævet'];
    }

    db_begin_transaction();
    try {
        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM price_catalog_items
            WHERE category_id " . ($categoryId ? "= :category_id" : "IS NULL"),
            $categoryId ? ['category_id' => $categoryId] : []
        );

        $itemData = [
            'category_id' => $categoryId,
            'item_code' => $itemCode,
            'name' => $name,
            'description' => sanitize_string($_POST['description'] ?? ''),
            'unit' => $unit,
            'price' => $price,
            'display_order' => $maxOrder + 1,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $itemId = db_insert('price_catalog_items', $itemData);

        // Create price history entry
        db_insert('price_history', [
            'catalog_item_id' => $itemId,
            'price' => $price,
            'effective_date' => date('Y-m-d'),
            'changed_by_user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        db_commit();

        log_activity('catalog_item_created', 'catalog_item', $itemId);

        return [
            'success' => true,
            'item_id' => $itemId,
            'message' => 'Katalog element oprettet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke oprette element'];
    }
}

/**
 * Update catalog item
 * POST ?module=price_catalog&action=update_item
 */
function handle_update_item(array $user): array {
    csrf_require();

    $itemId = sanitize_int($_POST['id'] ?? 0);

    if (!$itemId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get current item
    $currentItem = db_fetch("SELECT * FROM price_catalog_items WHERE id = :id", ['id' => $itemId]);

    if (!$currentItem) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    db_begin_transaction();
    try {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($_POST['category_id'])) {
            $updateData['category_id'] = isset($_POST['category_id']) && $_POST['category_id'] !== ''
                ? sanitize_int($_POST['category_id'])
                : null;
        }

        if (isset($_POST['item_code'])) {
            $updateData['item_code'] = sanitize_string($_POST['item_code']);
        }

        if (isset($_POST['name'])) {
            $updateData['name'] = sanitize_string($_POST['name']);
        }

        if (isset($_POST['description'])) {
            $updateData['description'] = sanitize_string($_POST['description']);
        }

        if (isset($_POST['unit'])) {
            $updateData['unit'] = sanitize_string($_POST['unit']);
        }

        if (isset($_POST['price'])) {
            $newPrice = sanitize_float($_POST['price']);

            // If price changed, create price history entry
            if ($newPrice != $currentItem['price']) {
                db_insert('price_history', [
                    'catalog_item_id' => $itemId,
                    'price' => $newPrice,
                    'effective_date' => date('Y-m-d'),
                    'changed_by_user_id' => $user['id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            $updateData['price'] = $newPrice;
        }

        if (isset($_POST['is_active'])) {
            $updateData['is_active'] = (bool)$_POST['is_active'];
        }

        if (!empty($updateData)) {
            db_update('price_catalog_items', $updateData, 'id = :id', ['id' => $itemId]);
        }

        db_commit();

        log_activity('catalog_item_updated', 'catalog_item', $itemId);

        return [
            'success' => true,
            'message' => 'Element opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere element'];
    }
}

/**
 * Delete catalog item
 * POST ?module=price_catalog&action=delete_item
 */
function handle_delete_item(array $user): array {
    csrf_require();

    $itemId = sanitize_int($_POST['id'] ?? 0);

    if (!$itemId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    db_begin_transaction();
    try {
        // Delete price history
        db_execute("DELETE FROM price_history WHERE catalog_item_id = :id", ['id' => $itemId]);

        // Delete item
        db_delete('price_catalog_items', 'id = :id', ['id' => $itemId]);

        db_commit();

        log_activity('catalog_item_deleted', 'catalog_item', $itemId);

        return [
            'success' => true,
            'message' => 'Element slettet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette element'];
    }
}

/**
 * Reorder catalog items (drag-and-drop)
 * POST ?module=price_catalog&action=reorder_items
 */
function handle_reorder_items(array $user): array {
    csrf_require();

    $itemIds = $_POST['item_ids'] ?? [];
    $categoryId = isset($_POST['category_id']) ? sanitize_int($_POST['category_id']) : null;

    if (!is_array($itemIds)) {
        return ['success' => false, 'error' => 'Element ID liste er påkrævet'];
    }

    db_begin_transaction();
    try {
        foreach ($itemIds as $order => $itemId) {
            $itemId = sanitize_int($itemId);
            $whereClause = 'id = :id AND category_id ' . ($categoryId ? '= :category_id' : 'IS NULL');
            $params = ['id' => $itemId];
            if ($categoryId) {
                $params['category_id'] = $categoryId;
            }

            db_update('price_catalog_items',
                ['display_order' => $order + 1],
                $whereClause,
                $params
            );
        }

        db_commit();

        log_activity('catalog_items_reordered', 'system', 0);

        return [
            'success' => true,
            'message' => 'Element rækkefølge opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rækkefølge'];
    }
}

/**
 * Get price history for item
 * GET ?module=price_catalog&action=get_price_history&item_id=X
 */
function handle_get_price_history(array $user): array {
    $itemId = sanitize_int($_GET['item_id'] ?? 0);

    if (!$itemId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    $history = db_fetch_all("
        SELECT
            ph.*,
            u.name as changed_by_name
        FROM price_history ph
        LEFT JOIN users u ON u.id = ph.changed_by_user_id
        WHERE ph.catalog_item_id = :item_id
        ORDER BY ph.effective_date DESC, ph.created_at DESC
    ", ['item_id' => $itemId]);

    return [
        'success' => true,
        'history' => $history
    ];
}

/**
 * Import catalog from CSV file
 * POST ?module=price_catalog&action=import_catalog
 */
function handle_import_catalog(array $user): array {
    csrf_require();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Ingen fil uploadet'];
    }

    $file = $_FILES['file'];

    // Validate file type
    if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        return ['success' => false, 'error' => 'Kun CSV filer er tilladt'];
    }

    db_begin_transaction();
    try {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            throw new Exception('Kunne ikke åbne fil');
        }

        // Skip header row
        fgetcsv($handle);

        $imported = 0;
        $errors = [];

        while (($data = fgetcsv($handle)) !== FALSE) {
            try {
                // Expected format: category_name, item_code, name, description, unit, price
                $categoryName = trim($data[0] ?? '');
                $itemCode = trim($data[1] ?? '');
                $name = trim($data[2] ?? '');
                $description = trim($data[3] ?? '');
                $unit = trim($data[4] ?? 'stk');
                $price = floatval($data[5] ?? 0);

                if (empty($name)) {
                    continue;
                }

                // Find or create category
                $categoryId = null;
                if (!empty($categoryName)) {
                    $category = db_fetch("
                        SELECT id FROM price_categories WHERE name = :name
                    ", ['name' => $categoryName]);

                    if (!$category) {
                        $categoryId = db_insert('price_categories', [
                            'name' => $categoryName,
                            'display_order' => 999,
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                    } else {
                        $categoryId = $category['id'];
                    }
                }

                // Create item
                $itemId = db_insert('price_catalog_items', [
                    'category_id' => $categoryId,
                    'item_code' => $itemCode,
                    'name' => $name,
                    'description' => $description,
                    'unit' => $unit,
                    'price' => $price,
                    'is_active' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);

                // Create price history
                db_insert('price_history', [
                    'catalog_item_id' => $itemId,
                    'price' => $price,
                    'effective_date' => date('Y-m-d'),
                    'changed_by_user_id' => $user['id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);

                $imported++;

            } catch (Exception $e) {
                $errors[] = "Linje " . ($imported + 2) . ": " . $e->getMessage();
            }
        }

        fclose($handle);
        db_commit();

        log_activity('catalog_imported', 'system', 0);

        return [
            'success' => true,
            'imported' => $imported,
            'errors' => $errors,
            'message' => "$imported elementer importeret" . (empty($errors) ? '' : ' med ' . count($errors) . ' fejl')
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Import fejlede: ' . $e->getMessage()];
    }
}

/**
 * Export catalog to CSV
 * GET ?module=price_catalog&action=export_catalog
 */
function handle_export_catalog(array $user): array {
    $items = db_fetch_all("
        SELECT
            pc.name as category_name,
            pci.item_code,
            pci.name,
            pci.description,
            pci.unit,
            pci.price
        FROM price_catalog_items pci
        LEFT JOIN price_categories pc ON pc.id = pci.category_id
        ORDER BY pc.display_order ASC, pci.display_order ASC
    ");

    // Generate CSV
    $csv = "Kategori,Varekode,Navn,Beskrivelse,Enhed,Pris\n";
    foreach ($items as $item) {
        $csv .= implode(',', [
            '"' . str_replace('"', '""', $item['category_name'] ?? '') . '"',
            '"' . str_replace('"', '""', $item['item_code'] ?? '') . '"',
            '"' . str_replace('"', '""', $item['name']) . '"',
            '"' . str_replace('"', '""', $item['description'] ?? '') . '"',
            '"' . str_replace('"', '""', $item['unit']) . '"',
            $item['price']
        ]) . "\n";
    }

    // Save to temporary file
    $filename = 'priskatalog_' . date('Y-m-d_His') . '.csv';
    $filepath = '/tmp/' . $filename;
    file_put_contents($filepath, $csv);

    log_activity('catalog_exported', 'system', 0);

    return [
        'success' => true,
        'filename' => $filename,
        'filepath' => $filepath,
        'count' => count($items),
        'message' => count($items) . ' elementer eksporteret'
    ];
}
