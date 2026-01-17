<?php
/**
 * Budget Module API
 *
 * Handles budget lines (CAPEX/OPEX/Reinstatement)
 * OPTIMIZED: Uses v_budget_totals view
 *
 * Available actions:
 * - search_catalog: Search price catalog
 * - get_templates: Get budget templates
 * - load_template: Load template to element
 * - get_lines: Get budget lines for element
 * - save_lines: Save budget lines (batch)
 * - delete_line: Delete single budget line
 * - calculate_total: Calculate budget total (OPTIMIZED)
 */

/**
 * Search price catalog
 * GET /api.php?module=budget&action=search_catalog&q=search&category=X
 */
function handle_search_catalog(array $user): array {
    $query = sanitize_string($_GET['q'] ?? '');
    $category = sanitize_string($_GET['category'] ?? '');

    $where = ['is_active = true'];
    $params = [];

    if (!empty($query)) {
        // Search in name, description, or tags
        $where[] = "(name ILIKE :query OR description ILIKE :query OR :query_tag = ANY(tags))";
        $params['query'] = '%' . $query . '%';
        $params['query_tag'] = $query;
    }

    if (!empty($category)) {
        $where[] = "category = :category";
        $params['category'] = $category;
    }

    $whereClause = implode(' AND ', $where);

    $items = db_fetch_all("
        SELECT *
        FROM price_catalog
        WHERE $whereClause
        ORDER BY category, name
        LIMIT 50
    ", $params);

    return [
        'success' => true,
        'items' => $items
    ];
}

/**
 * Get budget templates
 * GET /api.php?module=budget&action=get_templates&category=X
 */
function handle_get_templates(array $user): array {
    $category = sanitize_string($_GET['category'] ?? '');

    $where = ['(is_public = true OR created_by = :user_id)'];
    $params = ['user_id' => $user['id']];

    if (!empty($category)) {
        $where[] = "category = :category";
        $params['category'] = $category;
    }

    $whereClause = implode(' AND ', $where);

    $templates = db_fetch_all("
        SELECT *
        FROM budget_templates
        WHERE $whereClause
        ORDER BY is_public DESC, name
    ", $params);

    return [
        'success' => true,
        'templates' => $templates
    ];
}

/**
 * Load budget template to element
 * POST /api.php {module: 'budget', action: 'load_template', template_id: 123, element_id: 456, budget_type: 'capex'}
 */
function handle_load_template(array $user): array {
    csrf_require();

    $templateId = sanitize_int($_POST['template_id'] ?? 0);
    $elementId = sanitize_int($_POST['element_id'] ?? 0);
    $budgetType = sanitize_string($_POST['budget_type'] ?? 'capex');

    if (!$templateId || !$elementId) {
        return ['success' => false, 'error' => 'Template ID og Element ID påkrævet'];
    }

    // Get template
    $template = db_fetch("
        SELECT *
        FROM budget_templates
        WHERE id = :id AND (is_public = true OR created_by = :user_id)
    ", ['id' => $templateId, 'user_id' => $user['id']]);

    if (!$template) {
        return ['success' => false, 'error' => 'Template ikke fundet'];
    }

    // Verify element access
    $element = db_fetch("
        SELECT be.*, p.id as project_id
        FROM building_elements be
        JOIN buildings b ON be.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen redigerings adgang'];
    }

    // Parse template data
    $templateData = json_decode($template['template_data'], true);

    if (!is_array($templateData)) {
        return ['success' => false, 'error' => 'Ugyldig template data'];
    }

    // Get current max line number
    $maxLine = db_value("
        SELECT COALESCE(MAX(line_number), -1)
        FROM budget_lines
        WHERE element_id = :element_id AND budget_type = :budget_type
    ", ['element_id' => $elementId, 'budget_type' => $budgetType]);

    // Insert template lines
    $insertedCount = 0;
    foreach ($templateData as $index => $line) {
        $lineNumber = $maxLine + 1 + $index;

        db_insert('budget_lines', [
            'element_id' => $elementId,
            'budget_type' => $budgetType,
            'line_number' => $lineNumber,
            'description' => $line['description'] ?? '',
            'quantity' => $line['quantity'] ?? 0,
            'unit' => $line['unit'] ?? 'stk',
            'price_per_unit' => $line['price_per_unit'] ?? 0,
            'year_0_1' => $line['year_0_1'] ?? 0,
            'year_1_2' => $line['year_1_2'] ?? 0,
            'year_3_5' => $line['year_3_5'] ?? 0,
            'year_5_10' => $line['year_5_10'] ?? 0,
            'year_10_plus' => $line['year_10_plus'] ?? 0
        ]);

        $insertedCount++;
    }

    log_activity('budget_template_loaded', 'building_element', $elementId);

    return [
        'success' => true,
        'message' => "$insertedCount linjer indlæst",
        'inserted_count' => $insertedCount
    ];
}

/**
 * Get budget lines for element
 * GET /api.php?module=budget&action=get_lines&element_id=123&budget_type=capex
 */
function handle_get_lines(array $user): array {
    $elementId = sanitize_int($_GET['element_id'] ?? 0);
    $budgetType = sanitize_string($_GET['budget_type'] ?? 'capex');

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Verify access
    $element = db_fetch("
        SELECT be.*, p.id as project_id
        FROM building_elements be
        JOIN buildings b ON be.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    if (!can_access_project($user, $element['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    // Get budget lines
    $lines = db_fetch_all("
        SELECT bl.*, pc.name as catalog_item_name
        FROM budget_lines bl
        LEFT JOIN price_catalog pc ON bl.price_catalog_id = pc.id
        WHERE bl.element_id = :element_id AND bl.budget_type = :budget_type
        ORDER BY bl.line_number
    ", ['element_id' => $elementId, 'budget_type' => $budgetType]);

    return [
        'success' => true,
        'lines' => $lines
    ];
}

/**
 * Save budget lines (batch update/insert)
 * POST /api.php {module: 'budget', action: 'save_lines', element_id: 123, budget_type: 'capex', lines: [...]}
 */
function handle_save_lines(array $user): array {
    csrf_require();

    $elementId = sanitize_int($_POST['element_id'] ?? 0);
    $budgetType = sanitize_string($_POST['budget_type'] ?? 'capex');
    $lines = json_decode($_POST['lines'] ?? '[]', true);

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    if (!is_array($lines)) {
        return ['success' => false, 'error' => 'Ugyldige linjedata'];
    }

    // Verify access
    $element = db_fetch("
        SELECT be.*, p.id as project_id
        FROM building_elements be
        JOIN buildings b ON be.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen redigerings adgang'];
    }

    db_begin_transaction();
    try {
        foreach ($lines as $index => $line) {
            $lineData = [
                'element_id' => $elementId,
                'budget_type' => $budgetType,
                'line_number' => $index,
                'description' => sanitize_string($line['description'] ?? ''),
                'quantity' => sanitize_float($line['quantity'] ?? 0),
                'unit' => sanitize_string($line['unit'] ?? 'stk'),
                'price_per_unit' => sanitize_float($line['price_per_unit'] ?? 0),
                'year_0_1' => sanitize_float($line['year_0_1'] ?? 0),
                'year_1_2' => sanitize_float($line['year_1_2'] ?? 0),
                'year_3_5' => sanitize_float($line['year_3_5'] ?? 0),
                'year_5_10' => sanitize_float($line['year_5_10'] ?? 0),
                'year_10_plus' => sanitize_float($line['year_10_plus'] ?? 0),
                'price_catalog_id' => !empty($line['price_catalog_id']) ? sanitize_int($line['price_catalog_id']) : null,
                'notes' => sanitize_string($line['notes'] ?? '')
            ];

            if (!empty($line['id'])) {
                // Update existing line
                $id = sanitize_int($line['id']);
                db_update('budget_lines', $lineData, 'id = :id AND element_id = :element_id', [
                    'id' => $id,
                    'element_id' => $elementId
                ]);
            } else {
                // Insert new line
                db_insert('budget_lines', $lineData);
            }
        }

        // Update element's CAPEX if budget type is capex
        if ($budgetType === 'capex') {
            $total = 0;
            foreach ($lines as $line) {
                $qty = (float)($line['quantity'] ?? 0);
                $price = (float)($line['price_per_unit'] ?? 0);
                $total += $qty * $price;
            }

            db_update('building_elements', ['capex' => $total], 'id = :id', ['id' => $elementId]);
        }

        db_commit();
        log_activity('budget_lines_saved', 'building_element', $elementId);

        return [
            'success' => true,
            'message' => 'Budget linjer gemt'
        ];
    } catch (Exception $e) {
        db_rollback();
        log_error('Budget save error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke gemme budget'];
    }
}

/**
 * Delete single budget line
 * POST /api.php {module: 'budget', action: 'delete_line', id: 123}
 */
function handle_delete_line(array $user): array {
    csrf_require();

    $lineId = sanitize_int($_POST['id'] ?? 0);

    if (!$lineId) {
        return ['success' => false, 'error' => 'Line ID mangler'];
    }

    // Get line and verify access
    $line = db_fetch("
        SELECT bl.*, p.id as project_id
        FROM budget_lines bl
        JOIN building_elements be ON bl.element_id = be.id
        JOIN buildings b ON be.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE bl.id = :id
    ", ['id' => $lineId]);

    if (!$line) {
        return ['success' => false, 'error' => 'Linje ikke fundet'];
    }

    if (!can_access_project($user, $line['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen redigerings adgang'];
    }

    db_delete('budget_lines', 'id = :id', ['id' => $lineId]);

    log_activity('budget_line_deleted', 'budget_line', $lineId);

    return [
        'success' => true,
        'message' => 'Linje slettet'
    ];
}

/**
 * Calculate budget total for element
 * OPTIMIZED: Uses v_budget_totals view
 *
 * GET /api.php?module=budget&action=calculate_total&element_id=123&budget_type=capex
 */
function handle_calculate_total(array $user): array {
    $elementId = sanitize_int($_GET['element_id'] ?? 0);
    $budgetType = sanitize_string($_GET['budget_type'] ?? 'capex');

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Verify access
    $element = db_fetch("
        SELECT be.*, p.id as project_id
        FROM building_elements be
        JOIN buildings b ON be.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    if (!can_access_project($user, $element['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    // OPTIMIZED: Use v_budget_totals view
    $result = db_fetch("
        SELECT
            line_count,
            total_budget as total,
            total_year_0_1,
            total_year_1_2,
            total_year_3_5,
            total_year_5_10,
            total_year_10_plus
        FROM v_budget_totals
        WHERE element_id = :element_id AND budget_type = :budget_type
    ", ['element_id' => $elementId, 'budget_type' => $budgetType]);

    // If no budget lines exist
    if (!$result) {
        $result = [
            'line_count' => 0,
            'total' => 0,
            'total_year_0_1' => 0,
            'total_year_1_2' => 0,
            'total_year_3_5' => 0,
            'total_year_5_10' => 0,
            'total_year_10_plus' => 0
        ];
    }

    return [
        'success' => true,
        'totals' => [
            'line_count' => (int)$result['line_count'],
            'total' => (float)$result['total'],
            'year_0_1' => (float)$result['total_year_0_1'],
            'year_1_2' => (float)$result['total_year_1_2'],
            'year_3_5' => (float)$result['total_year_3_5'],
            'year_5_10' => (float)$result['total_year_5_10'],
            'year_10_plus' => (float)$result['total_year_10_plus']
        ]
    ];
}
