<?php
/**
 * Template Module API
 *
 * Handles budget templates for CAPEX, OPEX, and Reinstatement
 * Supports hierarchical structure with groups/folders
 *
 * Actions:
 * - get_templates: Get all templates
 * - get_template: Get single template with items
 * - get_hierarchy: Get template with hierarchical structure
 * - create_template: Create new template
 * - update_template: Update template
 * - delete_template: Delete template
 * - duplicate_template: Duplicate existing template
 *
 * - add_item: Add item to template
 * - create_group: Create group/folder item
 * - update_item: Update template item
 * - update_multiplier: Update multiplier for group
 * - delete_item: Delete template item
 * - reorder_items: Reorder template items (drag-and-drop)
 * - move_item: Move item to new parent
 *
 * - apply_template: Apply template to element
 * - get_categories: Get template categories
 */

require_once __DIR__ . '/../../core/permissions.php';
require_once __DIR__ . '/../../core/api-helpers.php';

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
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);
    if (!$validation['success']) return $validation;
    $templateId = $validation['data']['id'];

    $template = db_fetch("
        SELECT * FROM budget_templates WHERE id = :id
    ", ['id' => $templateId]);

    if (!$template) {
        return api_error('Template ikke fundet');
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
    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'budget_type' => ['string', 'POST', true],
        'description' => ['string', 'POST', false, ''],
        'category' => ['string', 'POST', false, '']
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    if (!in_array($params['budget_type'], ['capex', 'opex', 'reinstatement'])) {
        return api_error('Ugyldig budget type');
    }

    return api_crud_create(
        'budget_templates',
        [],
        [
            'name' => $params['name'],
            'description' => $params['description'],
            'budget_type' => $params['budget_type'],
            'category' => $params['category'],
            'is_active' => true
        ],
        function($templateId) {
            log_activity('template_created', 'template', $templateId);
            return [
                'template_id' => $templateId,
                'message' => 'Template oprettet'
            ];
        },
        'template'
    );
}

/**
 * Update template
 * POST ?module=template&action=update_template
 */
function handle_update_template(array $user): array {
    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'name' => ['string', 'POST', false],
        'description' => ['string', 'POST', false],
        'category' => ['string', 'POST', false],
        'is_active' => ['bool', 'POST', false]
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    $templateId = $params['id'];
    unset($params['id']);

    if (empty($params)) {
        return api_error('Ingen data at opdatere');
    }

    return api_crud_update(
        'budget_templates',
        $templateId,
        $params,
        null,
        function($templateId) {
            log_activity('template_updated', 'template', $templateId);
        }
    );
}

/**
 * Delete template
 * POST ?module=template&action=delete_template
 */
function handle_delete_template(array $user): array {
    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;
    $templateId = $validation['data']['id'];

    return api_crud_delete(
        'budget_templates',
        $templateId,
        null,
        function($templateId) {
            // Delete template items
            db_execute("DELETE FROM budget_template_items WHERE template_id = :id", ['id' => $templateId]);
            log_activity('template_deleted', 'template', $templateId);
        }
    );
}

/**
 * Duplicate template
 * POST ?module=template&action=duplicate_template
 */
function handle_duplicate_template(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'new_name' => ['string', 'POST', false]
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    // Get original template
    $original = db_fetch("SELECT * FROM budget_templates WHERE id = :id", ['id' => $params['id']]);

    if (!$original) {
        return api_error('Template ikke fundet');
    }

    return api_transaction(function() use ($user, $params, $original) {
        // Create new template
        $newTemplateData = [
            'name' => $params['new_name'] ?? ($original['name'] . ' (Kopi)'),
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
        ", ['template_id' => $params['id']]);

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

        log_activity('template_duplicated', 'template', $newTemplateId);

        return [
            'success' => true,
            'template_id' => $newTemplateId,
            'message' => 'Template kopieret'
        ];
    }, 'Kunne ikke kopiere template');
}

/**
 * Add item to template
 * POST ?module=template&action=add_item
 */
function handle_add_item(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'template_id' => ['int', 'POST', true],
        'description' => ['string', 'POST', true],
        'quantity' => ['float', 'POST', false, 1],
        'unit' => ['string', 'POST', false, 'stk'],
        'price_per_unit' => ['float', 'POST', false, 0],
        'timeline' => ['string', 'POST', false, ''],
        'notes' => ['string', 'POST', false, '']
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    return api_transaction(function() use ($params) {
        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM budget_template_items
            WHERE template_id = :template_id
        ", ['template_id' => $params['template_id']]);

        $itemData = [
            'template_id' => $params['template_id'],
            'description' => $params['description'],
            'quantity' => $params['quantity'],
            'unit' => $params['unit'],
            'price_per_unit' => $params['price_per_unit'],
            'timeline' => $params['timeline'],
            'notes' => $params['notes'],
            'display_order' => $maxOrder + 1
        ];

        $itemId = db_insert('budget_template_items', $itemData);

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $params['template_id']]
        );

        log_activity('template_item_added', 'template', $params['template_id']);

        return [
            'success' => true,
            'item_id' => $itemId,
            'message' => 'Element tilføjet til template'
        ];
    }, 'Kunne ikke tilføje element');
}

/**
 * Update template item
 * POST ?module=template&action=update_item
 */
function handle_update_item(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'description' => ['string', 'POST', false],
        'quantity' => ['float', 'POST', false],
        'unit' => ['string', 'POST', false],
        'price_per_unit' => ['float', 'POST', false],
        'timeline' => ['string', 'POST', false],
        'notes' => ['string', 'POST', false]
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];
    $itemId = $params['id'];

    // Get item to get template_id
    $item = db_fetch("SELECT template_id FROM budget_template_items WHERE id = :id", ['id' => $itemId]);

    if (!$item) {
        return api_error('Element ikke fundet');
    }

    return api_transaction(function() use ($itemId, $item, $params) {
        $updateData = $params;
        unset($updateData['id']);

        if (!empty($updateData)) {
            db_update('budget_template_items', $updateData, 'id = :id', ['id' => $itemId]);

            // Update template timestamp
            db_update('budget_templates',
                ['updated_at' => date('Y-m-d H:i:s')],
                'id = :id',
                ['id' => $item['template_id']]
            );
        }

        log_activity('template_item_updated', 'template_item', $itemId);

        return [
            'success' => true,
            'message' => 'Element opdateret'
        ];
    }, 'Kunne ikke opdatere element');
}

/**
 * Delete template item
 * POST ?module=template&action=delete_item
 */
function handle_delete_item(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;
    $itemId = $validation['data']['id'];

    // Get item to get template_id
    $item = db_fetch("SELECT template_id FROM budget_template_items WHERE id = :id", ['id' => $itemId]);

    if (!$item) {
        return api_error('Element ikke fundet');
    }

    return api_transaction(function() use ($itemId, $item) {
        db_delete('budget_template_items', 'id = :id', ['id' => $itemId]);

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $item['template_id']]
        );

        log_activity('template_item_deleted', 'template_item', $itemId);

        return [
            'success' => true,
            'message' => 'Element slettet'
        ];
    }, 'Kunne ikke slette element');
}

/**
 * Reorder template items (drag-and-drop)
 * POST ?module=template&action=reorder_items
 */
function handle_reorder_items(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'template_id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    // item_ids is an array from POST
    $params['item_ids'] = $_POST['item_ids'] ?? [];

    if (!is_array($params['item_ids'])) {
        return api_error('Element ID liste er påkrævet');
    }

    return api_transaction(function() use ($params) {
        foreach ($params['item_ids'] as $order => $itemId) {
            $itemId = sanitize_int($itemId);
            db_update('budget_template_items',
                ['display_order' => $order + 1],
                'id = :id AND template_id = :template_id',
                ['id' => $itemId, 'template_id' => $params['template_id']]
            );
        }

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $params['template_id']]
        );

        log_activity('template_items_reordered', 'template', $params['template_id']);

        return [
            'success' => true,
            'message' => 'Element rækkefølge opdateret'
        ];
    }, 'Kunne ikke opdatere rækkefølge');
}

/**
 * Apply template to element
 * POST ?module=template&action=apply_template
 */
function handle_apply_template(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'template_id' => ['int', 'POST', true],
        'element_id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    // Get template
    $template = db_fetch("SELECT * FROM budget_templates WHERE id = :id", ['id' => $params['template_id']]);

    if (!$template) {
        return api_error('Template ikke fundet');
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $params['element_id']]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    // Check project access (editor required)
    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return api_error('Ingen adgang til at anvende template');
    }

    // Get template items
    $items = db_fetch_all("
        SELECT * FROM budget_template_items
        WHERE template_id = :template_id
        ORDER BY display_order ASC
    ", ['template_id' => $params['template_id']]);

    return api_transaction(function() use ($params, $template, $items) {
        $createdCount = 0;

        foreach ($items as $item) {
            db_insert('budget_lines', [
                'element_id' => $params['element_id'],
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
                ['id' => $params['element_id']]
            );
        }

        log_activity('template_applied', 'template', $params['template_id']);

        return [
            'success' => true,
            'created_count' => $createdCount,
            'message' => "$createdCount budget linjer oprettet fra template"
        ];
    }, 'Kunne ikke anvende template');
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

/**
 * Get template with hierarchical structure
 * GET ?module=template&action=get_hierarchy&id=X
 */
function handle_get_hierarchy(array $user): array {
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);
    if (!$validation['success']) return $validation;
    $templateId = $validation['data']['id'];

    $template = db_fetch("
        SELECT * FROM budget_templates WHERE id = :id
    ", ['id' => $templateId]);

    if (!$template) {
        return api_error('Template ikke fundet');
    }

    // Get hierarchical structure using database function
    $hierarchy = db_fetch_all("
        SELECT * FROM get_template_hierarchy(:template_id)
    ", ['template_id' => $templateId]);

    return [
        'success' => true,
        'template' => $template,
        'hierarchy' => $hierarchy
    ];
}

/**
 * Create group/folder item
 * POST ?module=template&action=create_group
 */
function handle_create_group(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'template_id' => ['int', 'POST', true],
        'description' => ['string', 'POST', true],
        'parent_id' => ['int', 'POST', false],
        'quantity' => ['float', 'POST', false, 1]
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    return api_transaction(function() use ($params) {
        $parentId = $params['parent_id'] ?? null;

        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM budget_template_items
            WHERE template_id = :template_id AND parent_id " . ($parentId ? "= :parent_id" : "IS NULL"),
            $parentId ? ['template_id' => $params['template_id'], 'parent_id' => $parentId] : ['template_id' => $params['template_id']]
        );

        $itemData = [
            'template_id' => $params['template_id'],
            'parent_id' => $parentId,
            'description' => $params['description'],
            'is_group' => true,
            'quantity' => $params['quantity'] ?? 1,
            'unit' => 'stk',
            'price_per_unit' => 0,
            'multiplier' => 1,
            'display_order' => $maxOrder + 1
        ];

        $itemId = db_insert('budget_template_items', $itemData);

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $params['template_id']]
        );

        log_activity('template_group_created', 'template', $params['template_id']);

        return [
            'success' => true,
            'item_id' => $itemId,
            'message' => 'Gruppe oprettet'
        ];
    }, 'Kunne ikke oprette gruppe');
}

/**
 * Update multiplier for group (updates all children)
 * POST ?module=template&action=update_multiplier
 */
function handle_update_multiplier(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'quantity' => ['float', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    // Get item
    $item = db_fetch("
        SELECT * FROM budget_template_items WHERE id = :id
    ", ['id' => $params['id']]);

    if (!$item) {
        return api_error('Element ikke fundet');
    }

    if (!$item['is_group']) {
        return api_error('Kun grupper kan have multiplier');
    }

    return api_transaction(function() use ($params, $item) {
        $oldQuantity = (float)$item['quantity'];
        $multiplierChange = $oldQuantity > 0 ? ($params['quantity'] / $oldQuantity) : 1;

        // Update group quantity
        db_update('budget_template_items',
            ['quantity' => $params['quantity']],
            'id = :id',
            ['id' => $params['id']]
        );

        // Update all children multipliers
        db_execute("
            UPDATE budget_template_items
            SET multiplier = multiplier * :multiplier_change
            WHERE parent_id = :parent_id
        ", [
            'multiplier_change' => $multiplierChange,
            'parent_id' => $params['id']
        ]);

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $item['template_id']]
        );

        log_activity('template_multiplier_updated', 'template_item', $params['id']);

        return [
            'success' => true,
            'message' => 'Multiplier opdateret',
            'multiplier_change' => $multiplierChange
        ];
    }, 'Kunne ikke opdatere multiplier');
}

/**
 * Move item to new parent
 * POST ?module=template&action=move_item
 */
function handle_move_item(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'new_parent_id' => ['int', 'POST', false]
    ]);
    if (!$validation['success']) return $validation;
    $params = $validation['data'];

    // Get item
    $item = db_fetch("SELECT * FROM budget_template_items WHERE id = :id", ['id' => $params['id']]);

    if (!$item) {
        return api_error('Element ikke fundet');
    }

    $newParentId = $params['new_parent_id'] ?? null;

    // Prevent moving to self
    if ($newParentId === $params['id']) {
        return api_error('Element kan ikke flyttes til sig selv');
    }

    // Check for circular reference
    if ($newParentId !== null && is_template_item_descendant($newParentId, $params['id'])) {
        return api_error('Element kan ikke flyttes til et af sine underordnede');
    }

    return api_transaction(function() use ($params, $item, $newParentId) {
        // Get next display order in new location
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM budget_template_items
            WHERE template_id = :template_id AND parent_id " . ($newParentId ? "= :parent_id" : "IS NULL"),
            $newParentId ?
                ['template_id' => $item['template_id'], 'parent_id' => $newParentId] :
                ['template_id' => $item['template_id']]
        );

        db_update('budget_template_items',
            [
                'parent_id' => $newParentId,
                'display_order' => $maxOrder + 1
            ],
            'id = :id',
            ['id' => $params['id']]
        );

        // Update template timestamp
        db_update('budget_templates',
            ['updated_at' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $item['template_id']]
        );

        log_activity('template_item_moved', 'template_item', $params['id']);

        return [
            'success' => true,
            'message' => 'Element flyttet'
        ];
    }, 'Kunne ikke flytte element');
}

/**
 * Helper: Check if item is descendant of another
 */
function is_template_item_descendant(int $itemId, int $ancestorId): bool {
    $parent = db_fetch("
        SELECT parent_id FROM budget_template_items WHERE id = :id
    ", ['id' => $itemId]);

    if (!$parent || !$parent['parent_id']) {
        return false;
    }

    if ($parent['parent_id'] == $ancestorId) {
        return true;
    }

    return is_template_item_descendant($parent['parent_id'], $ancestorId);
}
