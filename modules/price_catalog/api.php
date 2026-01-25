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
require_once __DIR__ . '/../../core/api-helpers.php';

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
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'description' => ['string', 'POST', false, ''],
        'parent_id' => ['int', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    return api_transaction(
        function() use ($params) {
            // Get next display order
            $maxOrder = db_value("
                SELECT COALESCE(MAX(display_order), 0)
                FROM price_categories
                WHERE parent_id " . ($params['parent_id'] ? "= :parent_id" : "IS NULL"),
                $params['parent_id'] ? ['parent_id' => $params['parent_id']] : []
            );

            $categoryData = [
                'name' => $params['name'],
                'description' => $params['description'],
                'parent_id' => $params['parent_id'],
                'display_order' => $maxOrder + 1,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $categoryId = db_insert('price_categories', $categoryData);

            log_activity('price_category_created', 'price_category', $categoryId);

            return ['category_id' => $categoryId];
        },
        'Kategori oprettet',
        'Kunne ikke oprette kategori'
    );
}

/**
 * Update category
 * POST ?module=price_catalog&action=update_category
 */
function handle_update_category(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'name' => ['string', 'POST', false],
        'description' => ['string', 'POST', false]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $categoryId = $params['id'];

    $updateData = [];
    if (isset($params['name'])) {
        $updateData['name'] = $params['name'];
    }
    if (isset($params['description'])) {
        $updateData['description'] = $params['description'];
    }

    if (empty($updateData)) {
        return api_error('Ingen opdateringer');
    }

    return api_crud_update(
        'price_categories',
        $categoryId,
        $updateData,
        null,
        function($id) {
            log_activity('price_category_updated', 'price_category', $id);
        }
    );
}

/**
 * Delete category
 * POST ?module=price_catalog&action=delete_category
 */
function handle_delete_category(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $categoryId = $validation['data']['id'];

    return api_crud_delete(
        'price_categories',
        $categoryId,
        function($category) {
            // Check if category has items
            $itemCount = db_value("
                SELECT COUNT(*) FROM price_catalog_items WHERE category_id = :id
            ", ['id' => $category['id']]);

            if ($itemCount > 0) {
                throw new Exception('Kan ikke slette kategori med elementer. Flyt eller slet elementerne først.');
            }
        },
        function($id) {
            log_activity('price_category_deleted', 'price_category', $id);
        }
    );
}

/**
 * Reorder categories (drag-and-drop)
 * POST ?module=price_catalog&action=reorder_categories
 */
function handle_reorder_categories(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'parent_id' => ['int', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $categoryIds = $_POST['category_ids'] ?? [];
    $parentId = $validation['data']['parent_id'];

    if (!is_array($categoryIds)) {
        return api_error('Kategori ID liste er påkrævet');
    }

    return api_transaction(
        function() use ($categoryIds, $parentId) {
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

            log_activity('price_categories_reordered', 'system', 0);
        },
        'Kategori rækkefølge opdateret',
        'Kunne ikke opdatere rækkefølge'
    );
}

/**
 * Get catalog items
 * GET ?module=price_catalog&action=get_items&category_id=X
 */
function handle_get_items(array $user): array {
    $validation = api_validate_params([
        'category_id' => ['int', 'GET', false, null],
        'page' => ['int', 'GET', false, 1],
        'limit' => ['int', 'GET', false, 50]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $offset = ($params['page'] - 1) * $params['limit'];

    $query = "
        SELECT
            pci.*,
            pc.name as category_name
        FROM price_catalog_items pci
        LEFT JOIN price_categories pc ON pc.id = pci.category_id
    ";

    $queryParams = ['limit' => $params['limit'], 'offset' => $offset];

    if ($params['category_id'] !== null) {
        $query .= " WHERE pci.category_id = :category_id";
        $queryParams['category_id'] = $params['category_id'];
    }

    $query .= " ORDER BY pci.display_order ASC, pci.item_code ASC
                LIMIT :limit OFFSET :offset";

    $items = db_fetch_all($query, $queryParams);

    // Get total count
    $countQuery = "SELECT COUNT(*) FROM price_catalog_items";
    if ($params['category_id'] !== null) {
        $countQuery .= " WHERE category_id = :category_id";
    }
    $totalCount = db_value($countQuery, $params['category_id'] !== null ? ['category_id' => $params['category_id']] : []);

    return [
        'success' => true,
        'items' => $items,
        'pagination' => [
            'total' => (int)$totalCount,
            'page' => $params['page'],
            'limit' => $params['limit'],
            'pages' => ceil($totalCount / $params['limit'])
        ]
    ];
}

/**
 * Search catalog items
 * GET ?module=price_catalog&action=search_items&query=X
 */
function handle_search_items(array $user): array {
    $validation = api_validate_params([
        'query' => ['string', 'GET', true],
        'category_id' => ['int', 'GET', false, null],
        'limit' => ['int', 'GET', false, 20]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    if (strlen($params['query']) < 2) {
        return api_error('Søgeforespørgsel skal være mindst 2 tegn');
    }

    $query = "
        SELECT
            pci.*,
            pc.name as category_name
        FROM price_catalog_items pci
        LEFT JOIN price_categories pc ON pc.id = pci.category_id
        WHERE (
            " . db_ilike('pci.item_code', ':search') . "
            OR " . db_ilike('pci.name', ':search') . "
            OR " . db_ilike('pci.description', ':search') . "
        )
    ";

    $queryParams = [
        'search' => '%' . $params['query'] . '%',
        'exact' => $params['query'] . '%',
        'limit' => $params['limit']
    ];

    if ($params['category_id'] !== null) {
        $query .= " AND pci.category_id = :category_id";
        $queryParams['category_id'] = $params['category_id'];
    }

    $query .= " ORDER BY
                    CASE
                        WHEN " . db_ilike('pci.item_code', ':exact') . " THEN 1
                        WHEN " . db_ilike('pci.name', ':exact') . " THEN 2
                        ELSE 3
                    END,
                    pci.item_code ASC
                LIMIT :limit";

    $items = db_fetch_all($query, $queryParams);

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
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $itemId = $validation['data']['id'];

    return api_get_entity(
        'price_catalog_items',
        $itemId,
        function($item) {
            // Get price history
            $priceHistory = db_fetch_all("
                SELECT * FROM price_history
                WHERE catalog_item_id = :item_id
                ORDER BY effective_date DESC
                LIMIT 10
            ", ['item_id' => $item['id']]);

            $item['price_history'] = $priceHistory;

            return $item;
        },
        "SELECT
            pci.*,
            pc.name as category_name
        FROM price_catalog_items pci
        LEFT JOIN price_categories pc ON pc.id = pci.category_id
        WHERE pci.id = :id"
    );
}

/**
 * Create catalog item
 * POST ?module=price_catalog&action=create_item
 */
function handle_create_item(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'category_id' => ['int', 'POST', false, null],
        'item_code' => ['string', 'POST', false, ''],
        'name' => ['string', 'POST', true],
        'description' => ['string', 'POST', false, ''],
        'unit' => ['string', 'POST', false, 'stk'],
        'price' => ['float', 'POST', false, 0]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    return api_transaction(
        function() use ($params, $user) {
            // Get next display order
            $maxOrder = db_value("
                SELECT COALESCE(MAX(display_order), 0)
                FROM price_catalog_items
                WHERE category_id " . ($params['category_id'] ? "= :category_id" : "IS NULL"),
                $params['category_id'] ? ['category_id' => $params['category_id']] : []
            );

            $itemData = [
                'category_id' => $params['category_id'],
                'item_code' => $params['item_code'],
                'name' => $params['name'],
                'description' => $params['description'],
                'unit' => $params['unit'],
                'price' => $params['price'],
                'display_order' => $maxOrder + 1,
                'is_active' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $itemId = db_insert('price_catalog_items', $itemData);

            // Create price history entry
            db_insert('price_history', [
                'catalog_item_id' => $itemId,
                'price' => $params['price'],
                'effective_date' => date('Y-m-d'),
                'changed_by_user_id' => $user['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            log_activity('catalog_item_created', 'catalog_item', $itemId);

            return ['item_id' => $itemId];
        },
        'Katalog element oprettet',
        'Kunne ikke oprette element'
    );
}

/**
 * Update catalog item
 * POST ?module=price_catalog&action=update_item
 */
function handle_update_item(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'category_id' => ['int', 'POST', false],
        'item_code' => ['string', 'POST', false],
        'name' => ['string', 'POST', false],
        'description' => ['string', 'POST', false],
        'unit' => ['string', 'POST', false],
        'price' => ['float', 'POST', false],
        'is_active' => ['bool', 'POST', false]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $itemId = $params['id'];

    return api_transaction(
        function() use ($itemId, $params, $user) {
            // Get current item
            $currentItem = db_fetch("SELECT * FROM price_catalog_items WHERE id = :id", ['id' => $itemId]);

            if (!$currentItem) {
                throw new Exception('Element ikke fundet');
            }

            $updateData = ['updated_at' => date('Y-m-d H:i:s')];

            if (isset($params['category_id'])) {
                $updateData['category_id'] = $params['category_id'] !== '' ? $params['category_id'] : null;
            }
            if (isset($params['item_code'])) {
                $updateData['item_code'] = $params['item_code'];
            }
            if (isset($params['name'])) {
                $updateData['name'] = $params['name'];
            }
            if (isset($params['description'])) {
                $updateData['description'] = $params['description'];
            }
            if (isset($params['unit'])) {
                $updateData['unit'] = $params['unit'];
            }
            if (isset($params['price'])) {
                $newPrice = $params['price'];

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
            if (isset($params['is_active'])) {
                $updateData['is_active'] = $params['is_active'];
            }

            if (count($updateData) > 1) { // More than just updated_at
                db_update('price_catalog_items', $updateData, 'id = :id', ['id' => $itemId]);
            }

            log_activity('catalog_item_updated', 'catalog_item', $itemId);
        },
        'Element opdateret',
        'Kunne ikke opdatere element'
    );
}

/**
 * Delete catalog item
 * POST ?module=price_catalog&action=delete_item
 */
function handle_delete_item(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $itemId = $validation['data']['id'];

    return api_transaction(
        function() use ($itemId) {
            // Delete price history
            db_execute("DELETE FROM price_history WHERE catalog_item_id = :id", ['id' => $itemId]);

            // Delete item
            db_delete('price_catalog_items', 'id = :id', ['id' => $itemId]);

            log_activity('catalog_item_deleted', 'catalog_item', $itemId);
        },
        'Element slettet',
        'Kunne ikke slette element'
    );
}

/**
 * Reorder catalog items (drag-and-drop)
 * POST ?module=price_catalog&action=reorder_items
 */
function handle_reorder_items(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'category_id' => ['int', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $itemIds = $_POST['item_ids'] ?? [];
    $categoryId = $validation['data']['category_id'];

    if (!is_array($itemIds)) {
        return api_error('Element ID liste er påkrævet');
    }

    return api_transaction(
        function() use ($itemIds, $categoryId) {
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

            log_activity('catalog_items_reordered', 'system', 0);
        },
        'Element rækkefølge opdateret',
        'Kunne ikke opdatere rækkefølge'
    );
}

/**
 * Get price history for item
 * GET ?module=price_catalog&action=get_price_history&item_id=X
 */
function handle_get_price_history(array $user): array {
    $validation = api_validate_params([
        'item_id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $itemId = $validation['data']['item_id'];

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
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        return api_error('Ingen fil uploadet');
    }

    $file = $_FILES['file'];

    // Validate file type
    if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        return api_error('Kun CSV filer er tilladt');
    }

    return api_transaction(
        function() use ($file, $user) {
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

            log_activity('catalog_imported', 'system', 0);

            return [
                'imported' => $imported,
                'errors' => $errors
            ];
        },
        function($result) {
            return $result['imported'] . ' elementer importeret' . (empty($result['errors']) ? '' : ' med ' . count($result['errors']) . ' fejl');
        },
        'Import fejlede'
    );
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
