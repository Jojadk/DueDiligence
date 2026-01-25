<?php
/**
 * Budget Module API - Refactored with API Helpers
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

require_once __DIR__ . '/../../core/api-helpers.php';

/**
 * Search price catalog
 * GET /api.php?module=budget&action=search_catalog&q=search&category=X
 */
function handle_search_catalog(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'q' => ['string', 'GET', false, ''],
        'category' => ['string', 'GET', false, '']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $query = $validation['data']['q'];
    $category = $validation['data']['category'];

    $where = ['is_active = true'];
    $params = [];

    if (!empty($query)) {
        // Search in name, description, or tags
        // Note: tags array search works differently in MySQL (JSON) vs PostgreSQL (TEXT[])
        if (DatabaseAbstraction::isPostgreSQL()) {
            $where[] = "(" . db_ilike('name', ':query') . " OR " . db_ilike('description', ':query') . " OR :query_tag = ANY(tags))";
            $params['query'] = '%' . $query . '%';
            $params['query_tag'] = $query;
        } else {
            // MySQL uses JSON for tags
            $where[] = "(" . db_ilike('name', ':query') . " OR " . db_ilike('description', ':query') . " OR JSON_CONTAINS(tags, JSON_QUOTE(:query_tag)))";
            $params['query'] = '%' . $query . '%';
            $params['query_tag'] = $query;
        }
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
    // Validate parameters
    $validation = api_validate_params([
        'category' => ['string', 'GET', false, '']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $category = $validation['data']['category'];

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
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'template_id' => ['int', 'POST', true],
        'element_id' => ['int', 'POST', true],
        'budget_type' => ['string', 'POST', false, 'capex']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $templateId = $validation['data']['template_id'];
    $elementId = $validation['data']['element_id'];
    $budgetType = $validation['data']['budget_type'];

    // Get template
    $template = db_fetch("
        SELECT *
        FROM budget_templates
        WHERE id = :id AND (is_public = true OR created_by = :user_id)
    ", ['id' => $templateId, 'user_id' => $user['id']]);

    if (!$template) {
        return api_error('Template ikke fundet');
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
        return api_error('Element ikke fundet');
    }

    $accessCheck = api_require_project_access($user, $element['project_id'], 'editor');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Parse template data
    $templateData = json_decode($template['template_data'], true);

    if (!is_array($templateData)) {
        return api_error('Ugyldig template data');
    }

    // Use transaction for batch insert
    return api_transaction(
        function() use ($elementId, $budgetType, $templateData) {
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

            return ['inserted_count' => $insertedCount];
        },
        function($result) {
            return "{$result['inserted_count']} linjer indlæst";
        },
        'Kunne ikke indlæse template'
    );
}

/**
 * Get budget lines for element
 * GET /api.php?module=budget&action=get_lines&element_id=123&budget_type=capex
 */
function handle_get_lines(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'element_id' => ['int', 'GET', true],
        'budget_type' => ['string', 'GET', false, 'capex']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $elementId = $validation['data']['element_id'];
    $budgetType = $validation['data']['budget_type'];

    // Verify access
    $element = db_fetch("
        SELECT be.*, p.id as project_id
        FROM building_elements be
        JOIN buildings b ON be.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    $accessCheck = api_require_project_access($user, $element['project_id'], 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
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
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'element_id' => ['int', 'POST', true],
        'budget_type' => ['string', 'POST', false, 'capex']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $elementId = $validation['data']['element_id'];
    $budgetType = $validation['data']['budget_type'];
    $lines = json_decode($_POST['lines'] ?? '[]', true);

    if (!is_array($lines)) {
        return api_error('Ugyldige linjedata');
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
        return api_error('Element ikke fundet');
    }

    $accessCheck = api_require_project_access($user, $element['project_id'], 'editor');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Use transaction for batch save
    return api_transaction(
        function() use ($elementId, $budgetType, $lines) {
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
            $warning = null;
            if ($budgetType === 'capex') {
                $total = 0;
                foreach ($lines as $line) {
                    $qty = (float)($line['quantity'] ?? 0);
                    $price = (float)($line['price_per_unit'] ?? 0);
                    $total += $qty * $price;
                }

                // CAPEX Validation Warning - check for significant deviation
                $currentCapex = (float)($element['capex'] ?? 0);
                if ($currentCapex > 0) {
                    $difference = abs($total - $currentCapex);
                    $threshold = $currentCapex * 0.10; // 10% threshold

                    if ($difference > $threshold) {
                        $variancePct = round(($difference / $currentCapex) * 100, 1);
                        $warning = "CAPEX afviger med {$variancePct}% fra forventet værdi (forventet: " .
                                   number_format($currentCapex, 0, ',', '.') . " kr, beregnet: " .
                                   number_format($total, 0, ',', '.') . " kr)";

                        // Log variance for audit trail
                        log_activity('capex_variance_detected', 'building_element', $elementId, [
                            'expected' => $currentCapex,
                            'calculated' => $total,
                            'variance_pct' => $variancePct,
                            'budget_type' => $budgetType
                        ]);
                    }
                }

                db_update('building_elements', ['capex' => $total], 'id = :id', ['id' => $elementId]);
            }

            log_activity('budget_lines_saved', 'building_element', $elementId);

            $result = ['lines_saved' => count($lines)];
            if ($warning) {
                $result['warning'] = $warning;
            }

            return $result;
        },
        'Budget linjer gemt',
        'Kunne ikke gemme budget'
    );
}

/**
 * Delete single budget line
 * POST /api.php {module: 'budget', action: 'delete_line', id: 123}
 */
function handle_delete_line(array $user): array {
    // Validate CSRF token
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) {
        return $csrfCheck;
    }

    // Validate parameters
    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $lineId = $validation['data']['id'];

    // Use api_crud_delete with project access check
    return api_crud_delete(
        'budget_lines',
        $lineId,
        function($line) use ($user) {
            // Get project_id via joins
            $fullLine = db_fetch("
                SELECT p.id as project_id
                FROM budget_lines bl
                JOIN building_elements be ON bl.element_id = be.id
                JOIN buildings b ON be.building_id = b.id
                JOIN projects p ON b.project_id = p.id
                WHERE bl.id = :id
            ", ['id' => $line['id']]);

            return $fullLine && can_access_project($user, $fullLine['project_id'], 'editor');
        },
        null, // No before delete callback
        function($lineId) {
            log_activity('budget_line_deleted', 'budget_line', $lineId);
        },
        'Linje slettet',
        'Kunne ikke slette linje',
        'Linje ikke fundet',
        'Ingen redigerings adgang'
    );
}

/**
 * Calculate budget total for element
 * OPTIMIZED: Uses v_budget_totals view
 *
 * GET /api.php?module=budget&action=calculate_total&element_id=123&budget_type=capex
 */
function handle_calculate_total(array $user): array {
    // Validate parameters
    $validation = api_validate_params([
        'element_id' => ['int', 'GET', true],
        'budget_type' => ['string', 'GET', false, 'capex']
    ]);

    if (!$validation['success']) {
        return api_error($validation['errors']);
    }

    $elementId = $validation['data']['element_id'];
    $budgetType = $validation['data']['budget_type'];

    // Verify access
    $element = db_fetch("
        SELECT be.*, p.id as project_id
        FROM building_elements be
        JOIN buildings b ON be.building_id = b.id
        JOIN projects p ON b.project_id = p.id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    $accessCheck = api_require_project_access($user, $element['project_id'], 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
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
