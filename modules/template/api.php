<?php
/**
 * Template Module API
 *
 * Handles budget templates for CAPEX, OPEX, and Reinstatement
 *
 * Actions:
 * - get_templates: Get all templates
 * - get_template: Get single template with items
 * - create_template: Create new template
 * - update_template: Update template
 * - delete_template: Delete template
 * - duplicate_template: Duplicate existing template
 *
 * - add_item: Add item to template
 * - update_item: Update template item
 * - delete_item: Delete template item
 * - reorder_items: Reorder template items (drag-and-drop)
 *
 * - apply_template: Apply template to element
 * - get_categories: Get template categories
 */

require_once __DIR__ . '/../../core/permissions.php';

/**
 * Get all templates
 * GET ?module=template&action=get_templates
 */
function handle_get_templates(array $user): array {
    $budgetType = sanitize_string($_GET['budget_type'] ?? '');

    $query = "
        SELECT
            bt.*,
            COUNT(bti.id) as item_count,
            SUM(bti.quantity * bti.price_per_unit) as total_value
        FROM budget_templates bt
        LEFT JOIN budget_template_items bti ON bti.template_id = bt.id
    ";

    $params = [];

    if ($budgetType && in_array($budgetType, ['capex', 'opex', 'reinstatement'])) {
        $query .= " WHERE bt.budget_type = :budget_type";
        $params['budget_type'] = $budgetType;
    }

    $query .= " GROUP BY bt.id
                ORDER BY bt.category ASC, bt.name ASC";

    $templates = db_fetch_all($query, $params);

    return [
        'success' => true,
        'templates' => $templates
    ];
}

/**
 * Get single template with items
 * GET ?module=template&action=get_template&id=X
 */
function handle_get_template(array $user): array {
    $templateId = sanitize_int($_GET['id'] ?? 0);

    if (!$templateId) {
        return ['success' => false, 'error' => 'Template ID mangler'];
    }

    $template = db_fetch("
        SELECT * FROM budget_templates WHERE id = :id
    ", ['id' => $templateId]);

    if (!$template) {
        return ['success' => false, 'error' => 'Template ikke fundet'];
    }

    // Get template items
    $items = db_fetch_all("
        SELECT * FROM budget_template_items
        WHERE template_id = :template_id
        ORDER BY display_order ASC, description ASC
    ", ['template_id' => $templateId]);

    $template['items'] = $items;

    // Calculate total
    $total = 0;
    foreach ($items as $item) {
        $total += $item['quantity'] * $item['price_per_unit'];
    }
    $template['total_value'] = $total;

    return [
        'success' => true,
        'template' => $template
    ];
}

/**
 * Create new template
 * POST ?module=template&action=create_template
 */
function handle_create_template(array $user): array {
    csrf_require();

    $name = sanitize_string($_POST['name'] ?? '');
    $budgetType = sanitize_string($_POST['budget_type'] ?? 'capex');
    $category = sanitize_string($_POST['category'] ?? '');

    if (!$name) {
        return ['success' => false, 'error' => 'Navn er påkrævet'];
    }

    if (!in_array($budgetType, ['capex', 'opex', 'reinstatement'])) {
        return ['success' => false, 'error' => 'Ugyldig budget type'];
    }

    db_begin_transaction();
    try {
        $templateData = [
            'name' => $name,
            'description' => sanitize_string($_POST['description'] ?? ''),
            'budget_type' => $budgetType,
            'category' => $category,
            'is_active' => true,
            'created_by_user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $templateId = db_insert('budget_templates', $templateData);

        db_commit();

        log_activity('template_created', 'template', $templateId);

        return [
            'success' => true,
            'template_id' => $templateId,
            'message' => 'Template oprettet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke oprette template'];
    }
}

/**
 * Update template
 * POST ?module=template&action=update_template
 */
function handle_update_template(array $user): array {
    csrf_require();

    $templateId = sanitize_int($_POST['id'] ?? 0);

    if (!$templateId) {
        return ['success' => false, 'error' => 'Template ID mangler'];
    }

    db_begin_transaction();
    try {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        if (isset($_POST['name'])) {
            $updateData['name'] = sanitize_string($_POST['name']);
        }

        if (isset($_POST['description'])) {
            $updateData['description'] = sanitize_string($_POST['description']);
        }

        if (isset($_POST['category'])) {
            $updateData['category'] = sanitize_string($_POST['category']);
        }

        if (isset($_POST['is_active'])) {
            $updateData['is_active'] = (bool)$_POST['is_active'];
        }

        if (!empty($updateData)) {
            db_update('budget_templates', $updateData, 'id = :id', ['id' => $templateId]);
        }

        db_commit();

        log_activity('template_updated', 'template', $templateId);

        return [
            'success' => true,
            'message' => 'Template opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere template'];
    }
}

/**
 * Delete template
 * POST ?module=template&action=delete_template
 */
function handle_delete_template(array $user): array {
    csrf_require();

    $templateId = sanitize_int($_POST['id'] ?? 0);

    if (!$templateId) {
        return ['success' => false, 'error' => 'Template ID mangler'];
    }

    db_begin_transaction();
    try {
        // Delete template items
        db_execute("DELETE FROM budget_template_items WHERE template_id = :id", ['id' => $templateId]);

        // Delete template
        db_delete('budget_templates', 'id = :id', ['id' => $templateId]);

        db_commit();

        log_activity('template_deleted', 'template', $templateId);

        return [
            'success' => true,
            'message' => 'Template slettet'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette template'];
    }
}

/**
 * Duplicate template
 * POST ?module=template&action=duplicate_template
 */
function handle_duplicate_template(array $user): array {
    csrf_require();

    $templateId = sanitize_int($_POST['id'] ?? 0);
    $newName = sanitize_string($_POST['new_name'] ?? '');

    if (!$templateId) {
        return ['success' => false, 'error' => 'Template ID mangler'];
    }

    // Get original template
    $original = db_fetch("SELECT * FROM budget_templates WHERE id = :id", ['id' => $templateId]);

    if (!$original) {
        return ['success' => false, 'error' => 'Template ikke fundet'];
    }

    db_begin_transaction();
    try {
        // Create new template
        $newTemplateData = [
            'name' => $newName ?: ($original['name'] . ' (Kopi)'),
            'description' => $original['description'],
            'budget_type' => $original['budget_type'],
            'category' => $original['category'],
            'is_active' => $original['is_active'],
            'created_by_user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $newTemplateId = db_insert('budget_templates', $newTemplateData);

        // Copy items
        $items = db_fetch_all("
            SELECT * FROM budget_template_items WHERE template_id = :template_id
        ", ['template_id' => $templateId]);

        foreach ($items as $item) {
            db_insert('budget_template_items', [
                'template_id' => $newTemplateId,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'price_per_unit' => $item['price_per_unit'],
                'timeline' => $item['timeline'],
                'notes' => $item['notes'],
                'display_order' => $item['display_order']
            ]);
        }

        db_commit();

        log_activity('template_duplicated', 'template', $newTemplateId);

        return [
            'success' => true,
            'template_id' => $newTemplateId,
            'message' => 'Template kopieret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke kopiere template'];
    }
}

/**
 * Add item to template
 * POST ?module=template&action=add_item
 */
function handle_add_item(array $user): array {
    csrf_require();

    $templateId = sanitize_int($_POST['template_id'] ?? 0);
    $description = sanitize_string($_POST['description'] ?? '');
    $quantity = sanitize_float($_POST['quantity'] ?? 1);
    $unit = sanitize_string($_POST['unit'] ?? 'stk');
    $pricePerUnit = sanitize_float($_POST['price_per_unit'] ?? 0);

    if (!$templateId || !$description) {
        return ['success' => false, 'error' => 'Template ID og beskrivelse er påkrævet'];
    }

    db_begin_transaction();
    try {
        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM budget_template_items
            WHERE template_id = :template_id
        ", ['template_id' => $templateId]);

        $itemData = [
            'template_id' => $templateId,
            'description' => $description,
            'quantity' => $quantity,
            'unit' => $unit,
            'price_per_unit' => $pricePerUnit,
            'timeline' => sanitize_string($_POST['timeline'] ?? ''),
            'notes' => sanitize_string($_POST['notes'] ?? ''),
            'display_order' => $maxOrder + 1
        ];

        $itemId = db_insert('budget_template_items', $itemData);

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $templateId]
        );

        db_commit();

        log_activity('template_item_added', 'template', $templateId);

        return [
            'success' => true,
            'item_id' => $itemId,
            'message' => 'Element tilføjet til template'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke tilføje element'];
    }
}

/**
 * Update template item
 * POST ?module=template&action=update_item
 */
function handle_update_item(array $user): array {
    csrf_require();

    $itemId = sanitize_int($_POST['id'] ?? 0);

    if (!$itemId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get item to get template_id
    $item = db_fetch("SELECT template_id FROM budget_template_items WHERE id = :id", ['id' => $itemId]);

    if (!$item) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    db_begin_transaction();
    try {
        $updateData = [];

        if (isset($_POST['description'])) {
            $updateData['description'] = sanitize_string($_POST['description']);
        }

        if (isset($_POST['quantity'])) {
            $updateData['quantity'] = sanitize_float($_POST['quantity']);
        }

        if (isset($_POST['unit'])) {
            $updateData['unit'] = sanitize_string($_POST['unit']);
        }

        if (isset($_POST['price_per_unit'])) {
            $updateData['price_per_unit'] = sanitize_float($_POST['price_per_unit']);
        }

        if (isset($_POST['timeline'])) {
            $updateData['timeline'] = sanitize_string($_POST['timeline']);
        }

        if (isset($_POST['notes'])) {
            $updateData['notes'] = sanitize_string($_POST['notes']);
        }

        if (!empty($updateData)) {
            db_update('budget_template_items', $updateData, 'id = :id', ['id' => $itemId]);

            // Update template timestamp
            db_update('budget_templates',
                ['updated_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $item['template_id']]
            );
        }

        db_commit();

        log_activity('template_item_updated', 'template_item', $itemId);

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
 * Delete template item
 * POST ?module=template&action=delete_item
 */
function handle_delete_item(array $user): array {
    csrf_require();

    $itemId = sanitize_int($_POST['id'] ?? 0);

    if (!$itemId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get item to get template_id
    $item = db_fetch("SELECT template_id FROM budget_template_items WHERE id = :id", ['id' => $itemId]);

    if (!$item) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    db_begin_transaction();
    try {
        db_delete('budget_template_items', 'id = :id', ['id' => $itemId]);

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $item['template_id']]
        );

        db_commit();

        log_activity('template_item_deleted', 'template_item', $itemId);

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
 * Reorder template items (drag-and-drop)
 * POST ?module=template&action=reorder_items
 */
function handle_reorder_items(array $user): array {
    csrf_require();

    $templateId = sanitize_int($_POST['template_id'] ?? 0);
    $itemIds = $_POST['item_ids'] ?? [];

    if (!$templateId || !is_array($itemIds)) {
        return ['success' => false, 'error' => 'Template ID og element ID liste er påkrævet'];
    }

    db_begin_transaction();
    try {
        foreach ($itemIds as $order => $itemId) {
            $itemId = sanitize_int($itemId);
            db_update('budget_template_items',
                ['display_order' => $order + 1],
                'id = :id AND template_id = :template_id',
                ['id' => $itemId, 'template_id' => $templateId]
            );
        }

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $templateId]
        );

        db_commit();

        log_activity('template_items_reordered', 'template', $templateId);

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
 * Apply template to element
 * POST ?module=template&action=apply_template
 */
function handle_apply_template(array $user): array {
    csrf_require();

    $templateId = sanitize_int($_POST['template_id'] ?? 0);
    $elementId = sanitize_int($_POST['element_id'] ?? 0);

    if (!$templateId || !$elementId) {
        return ['success' => false, 'error' => 'Template ID og element ID er påkrævet'];
    }

    // Get template
    $template = db_fetch("SELECT * FROM budget_templates WHERE id = :id", ['id' => $templateId]);

    if (!$template) {
        return ['success' => false, 'error' => 'Template ikke fundet'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at anvende template'];
    }

    // Get template items
    $items = db_fetch_all("
        SELECT * FROM budget_template_items
        WHERE template_id = :template_id
        ORDER BY display_order ASC
    ", ['template_id' => $templateId]);

    db_begin_transaction();
    try {
        $createdCount = 0;

        foreach ($items as $item) {
            db_insert('budget_lines', [
                'element_id' => $elementId,
                'budget_type' => $template['budget_type'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'price_per_unit' => $item['price_per_unit'],
                'timeline' => $item['timeline'],
                'notes' => $item['notes'],
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $createdCount++;
        }

        // Update element CAPEX if template is CAPEX
        if ($template['budget_type'] === 'capex') {
            $total = 0;
            foreach ($items as $item) {
                $total += $item['quantity'] * $item['price_per_unit'];
            }
            db_update('building_elements',
                ['capex' => $total],
                'id = :id',
                ['id' => $elementId]
            );
        }

        db_commit();

        log_activity('template_applied', 'template', $templateId);

        return [
            'success' => true,
            'created_count' => $createdCount,
            'message' => "$createdCount budget linjer oprettet fra template"
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke anvende template'];
    }
}

/**
 * Get template categories
 * GET ?module=template&action=get_categories
 */
function handle_get_categories(array $user): array {
    $budgetType = sanitize_string($_GET['budget_type'] ?? '');

    $query = "
        SELECT DISTINCT category
        FROM budget_templates
    ";

    $params = [];

    if ($budgetType && in_array($budgetType, ['capex', 'opex', 'reinstatement'])) {
        $query .= " WHERE budget_type = :budget_type";
        $params['budget_type'] = $budgetType;
    }

    $query .= " ORDER BY category ASC";

    $result = db_fetch_all($query, $params);

    $categories = array_filter(array_column($result, 'category'));

    return [
        'success' => true,
        'categories' => array_values($categories)
    ];
}
