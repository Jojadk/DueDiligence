<?php
/**
 * Report Builder Module API
 *
 * Handles report building with WYSIWYG editor and template variables
 *
 * Actions:
 * - get_variables: Get available template variables
 * - preview: Preview report with variables replaced
 * - save: Save report template
 * - render: Render final report
 * - get_template: Get report template
 * - get_templates: Get all report templates
 * - create_template: Create new report template
 * - update_template: Update report template
 * - delete_template: Delete report template
 */

require_once __DIR__ . '/../../core/permissions.php';
require_once __DIR__ . '/../../core/api-helpers.php';

/**
 * Get available template variables
 * GET ?module=report_builder&action=get_variables&project_id=X
 */
function handle_get_variables(array $user): array {
    $variables = [
        'project' => [
            'label' => 'Projekt',
            'variables' => [
                '{{project.name}}' => 'Projekt navn',
                '{{project.description}}' => 'Projekt beskrivelse',
                '{{project.customer_name}}' => 'Kunde navn',
                '{{project.building_count}}' => 'Antal bygninger',
                '{{project.element_count}}' => 'Antal elementer',
                '{{project.total_capex}}' => 'Total CAPEX',
                '{{project.critical_count}}' => 'Kritiske elementer',
                '{{project.high_count}}' => 'Høj prioritet elementer',
                '{{project.created_at}}' => 'Oprettelsesdato'
            ]
        ],
        'buildings' => [
            'label' => 'Bygninger',
            'loop' => '{{for building in buildings}}...{{endfor}}',
            'variables' => [
                '{{building.name}}' => 'Bygnings navn',
                '{{building.building_number}}' => 'Bygnings nummer',
                '{{building.building_type}}' => 'Bygnings type',
                '{{building.gross_area}}' => 'Bruttoareal',
                '{{building.element_count}}' => 'Antal elementer',
                '{{building.total_capex}}' => 'Total CAPEX'
            ]
        ],
        'elements' => [
            'label' => 'Elementer',
            'loop' => '{{for element in elements}}...{{endfor}}',
            'variables' => [
                '{{element.name}}' => 'Element navn',
                '{{element.element_code}}' => 'Element kode',
                '{{element.capex}}' => 'CAPEX',
                '{{element.urgency}}' => 'Prioritet',
                '{{element.condition_score}}' => 'Tilstandsscore',
                '{{element.red_flag_score}}' => 'Rød flag score'
            ]
        ],
        'red_flags' => [
            'label' => 'Røde Flag',
            'loop' => '{{for flag in red_flags}}...{{endfor}}',
            'variables' => [
                '{{flag.element_name}}' => 'Element navn',
                '{{flag.building_name}}' => 'Bygning',
                '{{flag.red_flag_score}}' => 'Score',
                '{{flag.severity}}' => 'Alvorlighed',
                '{{flag.capex}}' => 'CAPEX'
            ]
        ],
        'conditionals' => [
            'label' => 'Betingelser',
            'examples' => [
                '{{if project.critical_count > 0}}...{{endif}}' => 'Hvis kritiske elementer',
                '{{if project.total_capex > 1000000}}...{{endif}}' => 'Hvis CAPEX over beløb',
                '{{if building.element_count > 10}}...{{else}}...{{endif}}' => 'Hvis/ellers',
            ]
        ],
        'formatting' => [
            'label' => 'Formatering (Pipe Filters)',
            'examples' => [
                '{{project.total_capex | number}}' => 'Formatér tal (1.000.000)',
                '{{project.created_at | date}}' => 'Formatér dato (01-01-2024)',
                '{{element.capex | currency}}' => 'Formatér valuta (kr. 1.000.000)',
                '{{project.name | uppercase}}' => 'Store bogstaver (PROJEKT)',
                '{{project.name | lowercase}}' => 'Små bogstaver (projekt)',
                '{{project.name | capitalize}}' => 'Stort forbogstav (Projekt)',
                '{{project.description | truncate:100}}' => 'Afkort til 100 tegn'
            ]
        ],
        'red_flags_display' => [
            'label' => 'Røde Flag Visning',
            'description' => 'Vis alle 5 flag typer med highlighting af valgte',
            'examples' => [
                '{{red_flags_display(element)}}' => 'Vis flag badges for element',
                '{{for element in elements}}{{red_flags_display(element)}}{{endfor}}' => 'Flag for alle elementer'
            ]
        ]
    ];

    return [
        'success' => true,
        'variables' => $variables
    ];
}

/**
 * Preview report with variables replaced
 * POST ?module=report_builder&action=preview
 */
function handle_preview(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'project_id' => ['int', 'POST', true],
        'template' => ['string', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // Check project access
    $accessCheck = api_require_project_access($user, $params['project_id'], 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Get project data
    $data = get_report_data($params['project_id']);

    // Render template
    $rendered = render_template($params['template'], $data);

    return [
        'success' => true,
        'rendered' => $rendered
    ];
}

/**
 * Save report template (with scope support)
 * POST ?module=report_builder&action=save
 */
function handle_save(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', false, null],
        'name' => ['string', 'POST', true],
        'template' => ['string', 'POST', true],
        'report_type' => ['string', 'POST', false, 'custom'],
        'template_scope' => ['string', 'POST', false, 'global'],
        'customer_id' => ['int', 'POST', false, null],
        'project_id' => ['int', 'POST', false, null],
        'parent_template_id' => ['int', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // Validate scope consistency
    $scope = $params['template_scope'];
    if ($scope === 'customer' && !$params['customer_id']) {
        return api_error('customer_id påkrævet for customer-scoped templates');
    }
    if ($scope === 'project' && !$params['project_id']) {
        return api_error('project_id påkrævet for project-scoped templates');
    }

    $templateData = [
        'name' => $params['name'],
        'template_content' => $params['template'],
        'report_type' => $params['report_type'],
        'template_scope' => $params['template_scope'],
        'customer_id' => $params['customer_id'],
        'project_id' => $params['project_id'],
        'parent_template_id' => $params['parent_template_id'],
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($params['id']) {
        // Update existing
        return api_crud_update(
            'report_templates',
            $params['id'],
            $templateData,
            null,
            function($templateId) {
                log_activity('report_template_saved', 'report_template', $templateId);
                return ['template_id' => $templateId, 'message' => 'Template opdateret'];
            }
        );
    } else {
        // Create new
        $templateData['created_by_user_id'] = $user['id'];
        $templateData['created_at'] = date('Y-m-d H:i:s');
        $templateData['is_active'] = true;

        $result = api_crud_create(
            'report_templates',
            $templateData,
            null,
            function($templateId) {
                log_activity('report_template_saved', 'report_template', $templateId);
            }
        );

        if ($result['success']) {
            $result['template_id'] = $result['id'];
            $result['message'] = 'Template oprettet';
        }

        return $result;
    }
}

/**
 * Render final report
 * POST ?module=report_builder&action=render
 */
function handle_render(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'project_id' => ['int', 'POST', true],
        'template_id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // Check project access
    $accessCheck = api_require_project_access($user, $params['project_id'], 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Get template
    $template = db_fetch("
        SELECT * FROM report_templates WHERE id = :id
    ", ['id' => $params['template_id']]);

    if (!$template) {
        return api_error('Template ikke fundet');
    }

    // Get project data
    $data = get_report_data($params['project_id']);

    // Render template
    $rendered = render_template($template['template_content'], $data);

    return api_transaction(
        function() use ($params, $data, $template, $rendered, $user) {
            // Save rendered report
            $reportId = db_insert('reports', [
                'project_id' => $params['project_id'],
                'title' => $data['project']['name'] . ' - ' . $template['name'],
                'report_type' => $template['report_type'],
                'rich_text_content' => $rendered,
                'wysiwyg_enabled' => true,
                'generated_by_user_id' => $user['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            log_activity('report_rendered', 'report', $reportId);

            return [
                'report_id' => $reportId,
                'rendered' => $rendered
            ];
        },
        'Rapport genereret',
        'Kunne ikke gemme rapport'
    );
}

/**
 * Get report template
 * GET ?module=report_builder&action=get_template&id=X
 */
function handle_get_template(array $user): array {
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $templateId = $validation['data']['id'];

    $template = db_fetch("
        SELECT * FROM report_templates WHERE id = :id
    ", ['id' => $templateId]);

    if (!$template) {
        return api_error('Template ikke fundet');
    }

    return [
        'success' => true,
        'template' => $template
    ];
}

/**
 * Get all report templates (with scope filtering)
 * GET ?module=report_builder&action=get_templates&project_id=X&scope=global|customer|project
 */
function handle_get_templates(array $user): array {
    $validation = api_validate_params([
        'report_type' => ['string', 'GET', false, ''],
        'project_id' => ['int', 'GET', false, null],
        'scope' => ['string', 'GET', false, ''], // Filter by scope: global, customer, project
        'include_inactive' => ['bool', 'GET', false, false]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // If project_id provided, use scope-aware function
    if ($params['project_id']) {
        $templates = db_fetch_all("
            SELECT * FROM get_templates_for_project(:project_id, :report_type)
        ", [
            'project_id' => $params['project_id'],
            'report_type' => $params['report_type'] ?: null
        ]);

        return [
            'success' => true,
            'templates' => $templates
        ];
    }

    // Otherwise, manual filtering
    $query = "SELECT
        rt.*,
        u.name as created_by_name,
        c.name as customer_name,
        p.name as project_name,
        CASE
            WHEN rt.template_scope = 'project' THEN 1
            WHEN rt.template_scope = 'customer' THEN 2
            WHEN rt.template_scope = 'global' THEN 3
        END as priority
    FROM report_templates rt
    LEFT JOIN users u ON rt.created_by_user_id = u.id
    LEFT JOIN customers c ON rt.customer_id = c.id
    LEFT JOIN projects p ON rt.project_id = p.id
    WHERE 1=1";

    $sqlParams = [];

    // Filter by report type
    if ($params['report_type']) {
        $query .= " AND rt.report_type = :report_type";
        $sqlParams['report_type'] = $params['report_type'];
    }

    // Filter by scope
    if ($params['scope']) {
        $query .= " AND rt.template_scope = :scope";
        $sqlParams['scope'] = $params['scope'];
    }

    // Filter by active status
    if (!$params['include_inactive']) {
        $query .= " AND rt.is_active = TRUE";
    }

    // Order by priority and name
    $query .= " ORDER BY priority ASC, rt.name ASC";

    $templates = db_fetch_all($query, $sqlParams);

    return [
        'success' => true,
        'templates' => $templates
    ];
}

/**
 * Delete report template
 * POST ?module=report_builder&action=delete_template
 */
function handle_delete_template(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $templateId = $validation['data']['id'];

    return api_crud_delete(
        'report_templates',
        $templateId,
        null,
        function($id) {
            log_activity('report_template_deleted', 'report_template', $id);
        }
    );
}

/**
 * Helper: Get report data for project
 */
function get_report_data(int $projectId): array {
    // Get project data with summary
    $project = db_fetch("
        SELECT
            p.*,
            c.name as customer_name,
            ps.building_count,
            ps.element_count,
            ps.total_capex,
            ps.critical_count,
            ps.high_count,
            ps.poor_count
        FROM projects p
        LEFT JOIN customers c ON p.customer_id = c.id
        LEFT JOIN v_project_summary ps ON p.id = ps.project_id
        WHERE p.id = :project_id
    ", ['project_id' => $projectId]);

    // Get buildings
    $buildings = db_fetch_all("
        SELECT *
        FROM v_building_summary
        WHERE project_id = :project_id
        ORDER BY display_order ASC, building_number ASC
    ", ['project_id' => $projectId]);

    // Get elements
    $elements = db_fetch_all("
        SELECT *
        FROM v_element_summary
        WHERE project_id = :project_id
        ORDER BY building_id, display_order ASC
    ", ['project_id' => $projectId]);

    // Get red flags
    $redFlags = db_fetch_all("
        SELECT *
        FROM v_red_flags
        WHERE project_id = :project_id
        ORDER BY red_flag_score DESC
        LIMIT 20
    ", ['project_id' => $projectId]);

    return [
        'project' => $project,
        'buildings' => $buildings,
        'elements' => $elements,
        'red_flags' => $redFlags
    ];
}

/**
 * Helper: Render template with variables
 */
function render_template(string $template, array $data): string {
    $rendered = $template;

    // Handle loops first (so filters work inside loops)
    $rendered = handle_loop($rendered, 'buildings', $data['buildings']);
    $rendered = handle_loop($rendered, 'elements', $data['elements']);
    $rendered = handle_loop($rendered, 'red_flags', $data['red_flags']);

    // Handle conditionals
    $rendered = handle_conditionals($rendered, $data);

    // Handle red_flags_display function
    $rendered = handle_red_flags_display($rendered);

    // Handle ternary operators BEFORE pipe filters
    $rendered = handle_ternary_operators($rendered, $data);

    // Handle nullish coalescing BEFORE pipe filters
    $rendered = handle_nullish_coalescing($rendered, $data);

    // Handle pipe filters BEFORE replacing simple variables
    $rendered = handle_pipe_filters($rendered, $data);

    // Replace simple variables (without filters) with safe fallback
    foreach ($data['project'] as $key => $value) {
        $rendered = str_replace("{{project.$key}}", htmlspecialchars((string)($value ?? '')), $rendered);
    }

    // Legacy formatting functions (keep for backwards compatibility)
    $rendered = handle_legacy_formatting($rendered);

    return $rendered;
}

/**
 * Helper: Handle loop constructs
 */
function handle_loop(string $template, string $loopName, array $items): string {
    $pattern = '/\{\{for\s+(\w+)\s+in\s+' . preg_quote($loopName) . '\}\}(.*?)\{\{endfor\}\}/s';

    return preg_replace_callback($pattern, function($matches) use ($items) {
        $itemVar = $matches[1]; // e.g., "building" (singular)
        $loopTemplate = $matches[2];
        $output = '';

        foreach ($items as $item) {
            $itemOutput = $loopTemplate;

            // Handle ternary operators inside loop
            $itemOutput = handle_loop_ternary($itemOutput, $itemVar, $item);

            // Handle nullish coalescing inside loop
            $itemOutput = handle_loop_nullish($itemOutput, $itemVar, $item);

            // Handle pipe filters inside loop
            $itemOutput = handle_loop_pipe_filters($itemOutput, $itemVar, $item);

            // Handle red_flags_display inside loop with (red) highlighting
            $itemOutput = preg_replace_callback(
                '/\{\{' . preg_quote($itemVar) . '\.flags\s*\(([^)]+)\)\}\}/',
                function($m) use ($item) {
                    $highlightColor = trim($m[1]);
                    return generate_red_flags_display($item, $highlightColor);
                },
                $itemOutput
            );

            // Handle red_flags_display inside loop (legacy)
            $itemOutput = str_replace("{{REDFLAG_DISPLAY:$itemVar}}", generate_red_flags_display($item), $itemOutput);

            // Replace simple variables with safe fallback
            foreach ($item as $key => $value) {
                $itemOutput = str_replace("{{{$itemVar}.$key}}", htmlspecialchars((string)($value ?? '')), $itemOutput);
            }

            $output .= $itemOutput;
        }

        return $output;
    }, $template);
}

/**
 * Helper: Handle nullish coalescing inside loops
 */
function handle_loop_nullish(string $template, string $itemVar, array $item): string {
    // Match {{itemVar.field??default}}
    $pattern = '/\{\{' . preg_quote($itemVar) . '\.([a-z_]+)\?\?([^}]+)\}\}/i';

    return preg_replace_callback($pattern, function($matches) use ($item) {
        $field = $matches[1];
        $default = trim($matches[2]);

        $value = $item[$field] ?? null;

        if ($value === null || $value === '' || $value === false) {
            return htmlspecialchars($default);
        }

        return htmlspecialchars((string)$value);
    }, $template);
}

/**
 * Helper: Handle ternary operators inside loops
 */
function handle_loop_ternary(string $template, string $itemVar, array $item): string {
    // Match {{itemVar.field?true:false}} or {{itemVar.field > 0?Yes:No}}
    $pattern = '/\{\{' . preg_quote($itemVar) . '\.([a-z_]+)\s*([><=!]+)?\s*(\d+)?\?([^:}]+):([^}]+)\}\}/i';

    return preg_replace_callback($pattern, function($matches) use ($item) {
        $field = $matches[1];
        $operator = $matches[2] ?? null;
        $compareValue = $matches[3] ?? null;
        $trueValue = trim($matches[4]);
        $falseValue = trim($matches[5]);

        $value = $item[$field] ?? null;

        if ($operator && $compareValue !== null) {
            // Comparison
            $result = false;
            switch ($operator) {
                case '>': $result = $value > $compareValue; break;
                case '<': $result = $value < $compareValue; break;
                case '>=': $result = $value >= $compareValue; break;
                case '<=': $result = $value <= $compareValue; break;
                case '==': $result = $value == $compareValue; break;
                case '!=': $result = $value != $compareValue; break;
            }
        } else {
            // Simple truthy check
            $result = !empty($value);
        }

        return htmlspecialchars($result ? $trueValue : $falseValue);
    }, $template);
}

/**
 * Helper: Handle pipe filters inside loops
 */
function handle_loop_pipe_filters(string $template, string $itemVar, array $item): string {
    // Match {{itemVar.field | filter}} or {{itemVar.field | filter:param}}
    $pattern = '/\{\{' . preg_quote($itemVar) . '\.([a-z_]+)\s*\|\s*([a-z_]+)(?::(\d+))?\}\}/i';

    return preg_replace_callback($pattern, function($matches) use ($item) {
        $field = $matches[1];    // e.g., "name"
        $filter = $matches[2];   // e.g., "uppercase"
        $param = $matches[3] ?? null;

        $value = $item[$field] ?? '';
        return apply_filter($value, $filter, $param);
    }, $template);
}

/**
 * Helper: Generate red flags display HTML
 * @param array $item Element data with red_flag_score
 * @param string|null $highlightColor Color name to highlight (e.g., 'red', 'orange', 'yellow')
 */
function generate_red_flags_display(array $item, ?string $highlightColor = null): string {
    // Define all 5 flag types
    $allFlags = [
        1 => ['label' => 'Kritisk', 'color' => '#DC2626', 'icon' => '🚩', 'name' => 'red'],
        2 => ['label' => 'Alvorlig', 'color' => '#F97316', 'icon' => '⚠️', 'name' => 'orange'],
        3 => ['label' => 'Moderat', 'color' => '#FBBF24', 'icon' => '⚡', 'name' => 'yellow'],
        4 => ['label' => 'Mindre', 'color' => '#10B981', 'icon' => 'ℹ️', 'name' => 'green'],
        5 => ['label' => 'Info', 'color' => '#3B82F6', 'icon' => '💡', 'name' => 'blue']
    ];

    // Get element's red flag score (1-5)
    $score = (int)($item['red_flag_score'] ?? 0);

    $html = '<span class="red-flags-display" style="display: inline-flex; gap: 4px;">';

    foreach ($allFlags as $flagValue => $flag) {
        // Check if this flag is active (selected score) OR if we're highlighting this color
        $isActive = ($flagValue == $score);
        $isHighlighted = ($highlightColor && $flag['name'] === strtolower($highlightColor));

        // Special highlighting for requested color
        if ($isHighlighted) {
            $opacity = '1';
            $border = '3px solid ' . $flag['color'];
            $background = $flag['color'] . '30';
        } elseif ($isActive) {
            $opacity = '1';
            $border = '2px solid ' . $flag['color'];
            $background = $flag['color'] . '20';
        } else {
            $opacity = '0.2';
            $border = '1px solid #e5e7eb';
            $background = '#f9fafb';
        }

        $html .= sprintf(
            '<span class="flag-badge" style="display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px; border: %s; border-radius: 4px; opacity: %s; background: %s; font-size: 12px;">
                <span>%s</span>
                <span style="color: %s; font-weight: %s;">%s</span>
            </span>',
            $border,
            $opacity,
            $background,
            $flag['icon'],
            $flag['color'],
            ($isActive || $isHighlighted) ? 'bold' : 'normal',
            $flag['label']
        );
    }

    $html .= '</span>';

    return $html;
}

/**
 * Helper: Handle conditional constructs
 */
function handle_conditionals(string $template, array $data): string {
    // Handle {{if condition}}...{{endif}}
    $pattern = '/\{\{if\s+(.*?)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{endif\}\}/s';

    return preg_replace_callback($pattern, function($matches) use ($data) {
        $condition = $matches[1];
        $ifContent = $matches[2];
        $elseContent = $matches[3] ?? '';

        // Evaluate condition
        $result = evaluate_condition($condition, $data);

        return $result ? $ifContent : $elseContent;
    }, $template);
}

/**
 * Helper: Evaluate condition
 */
function evaluate_condition(string $condition, array $data): bool {
    // Simple condition evaluation
    // Supports: project.field > value, project.field < value, project.field == value

    if (preg_match('/(\w+\.\w+)\s*([><=!]+)\s*(\d+)/', $condition, $matches)) {
        $variable = $matches[1];
        $operator = $matches[2];
        $value = $matches[3];

        // Get variable value
        $parts = explode('.', $variable);
        $actualValue = $data[$parts[0]][$parts[1]] ?? 0;

        switch ($operator) {
            case '>':
                return $actualValue > $value;
            case '<':
                return $actualValue < $value;
            case '>=':
                return $actualValue >= $value;
            case '<=':
                return $actualValue <= $value;
            case '==':
                return $actualValue == $value;
            case '!=':
                return $actualValue != $value;
        }
    }

    return false;
}

/**
 * Helper: Handle nullish coalescing operator ({{variable??default}})
 */
function handle_nullish_coalescing(string $template, array $data): string {
    // Match {{variable??default}} - returns default if variable is null/undefined/empty
    $pattern = '/\{\{([a-z_]+\.[a-z_]+)\?\?([^}]+)\}\}/i';

    return preg_replace_callback($pattern, function($matches) use ($data) {
        $variable = $matches[1]; // e.g., "project.description"
        $default = trim($matches[2]); // e.g., "0" or "N/A"

        // Get variable value safely
        $parts = explode('.', $variable);
        $value = $data[$parts[0]][$parts[1]] ?? null;

        // Return default if value is null, empty string, or not set
        if ($value === null || $value === '' || $value === false) {
            return htmlspecialchars($default);
        }

        return htmlspecialchars((string)$value);
    }, $template);
}

/**
 * Helper: Handle ternary operators ({{variable?true:false}})
 */
function handle_ternary_operators(string $template, array $data): string {
    // Match {{variable?trueValue:falseValue}} or {{variable > 0?Yes:No}}
    $pattern = '/\{\{([^?}]+)\?([^:}]+):([^}]+)\}\}/';

    return preg_replace_callback($pattern, function($matches) use ($data) {
        $condition = trim($matches[1]); // e.g., "project.critical_count > 0" or "project.name"
        $trueValue = trim($matches[2]);
        $falseValue = trim($matches[3]);

        // Check if condition contains comparison operator
        if (preg_match('/([a-z_]+\.[a-z_]+)\s*([><=!]+)\s*(\d+)/i', $condition, $condMatches)) {
            // Complex condition like "project.critical_count > 0"
            $variable = $condMatches[1];
            $operator = $condMatches[2];
            $compareValue = $condMatches[3];

            $parts = explode('.', $variable);
            $actualValue = $data[$parts[0]][$parts[1]] ?? 0;

            $result = false;
            switch ($operator) {
                case '>': $result = $actualValue > $compareValue; break;
                case '<': $result = $actualValue < $compareValue; break;
                case '>=': $result = $actualValue >= $compareValue; break;
                case '<=': $result = $actualValue <= $compareValue; break;
                case '==': $result = $actualValue == $compareValue; break;
                case '!=': $result = $actualValue != $compareValue; break;
            }
        } else {
            // Simple truthy check like "project.name"
            $parts = explode('.', $condition);
            $value = $data[$parts[0]][$parts[1]] ?? null;
            $result = !empty($value);
        }

        return htmlspecialchars($result ? $trueValue : $falseValue);
    }, $template);
}

/**
 * Helper: Handle pipe filters ({{variable | filter}})
 */
function handle_pipe_filters(string $template, array $data): string {
    // Match {{variable | filter}} or {{variable | filter:param}}
    $pattern = '/\{\{([a-z_]+\.[a-z_]+)\s*\|\s*([a-z_]+)(?::(\d+))?\}\}/i';

    return preg_replace_callback($pattern, function($matches) use ($data) {
        $variable = $matches[1]; // e.g., "project.name"
        $filter = $matches[2];   // e.g., "uppercase"
        $param = $matches[3] ?? null; // e.g., "100" for truncate:100

        // Get variable value safely with fallback
        $parts = explode('.', $variable);
        $value = $data[$parts[0]][$parts[1]] ?? '';

        // Apply filter
        return apply_filter($value, $filter, $param);
    }, $template);
}

/**
 * Helper: Apply filter to value
 */
function apply_filter($value, string $filter, $param = null): string {
    switch ($filter) {
        case 'number':
            return number_format((float)$value, 0, ',', '.');

        case 'currency':
            return 'kr. ' . number_format((float)$value, 0, ',', '.');

        case 'date':
            $date = strtotime($value);
            return $date ? date('d-m-Y', $date) : $value;

        case 'uppercase':
            return strtoupper($value);

        case 'lowercase':
            return strtolower($value);

        case 'capitalize':
            return ucfirst(strtolower($value));

        case 'truncate':
            $length = (int)$param ?: 100;
            return strlen($value) > $length ? substr($value, 0, $length) . '...' : $value;

        default:
            return htmlspecialchars((string)$value);
    }
}

/**
 * Helper: Handle red_flags_display function
 */
function handle_red_flags_display(string $template): string {
    // Match {{red_flags_display(element)}} or similar
    $pattern = '/\{\{red_flags_display\((\w+)\)\}\}/';

    return preg_replace_callback($pattern, function($matches) {
        $varName = $matches[1]; // e.g., "element"

        // Generate HTML for flag display
        // This returns a placeholder that will be replaced in the loop with actual data
        return "{{REDFLAG_DISPLAY:$varName}}";
    }, $template);
}

/**
 * Helper: Handle legacy formatting functions (backwards compatibility)
 */
function handle_legacy_formatting(string $template): string {
    // Format numbers: format_number(123456) -> 123.456
    $template = preg_replace_callback('/format_number\(([^)]+)\)/', function($matches) {
        $value = trim($matches[1]);
        return number_format((float)$value, 0, ',', '.');
    }, $template);

    // Format currency: format_currency(123456) -> kr. 123.456
    $template = preg_replace_callback('/format_currency\(([^)]+)\)/', function($matches) {
        $value = trim($matches[1]);
        return 'kr. ' . number_format((float)$value, 0, ',', '.');
    }, $template);

    // Format date: format_date(2024-01-01) -> 01-01-2024
    $template = preg_replace_callback('/format_date\(([^)]+)\)/', function($matches) {
        $value = trim($matches[1]);
        $date = strtotime($value);
        return $date ? date('d-m-Y', $date) : $value;
    }, $template);

    return $template;
}

/**
 * Clone template to different scope
 * POST ?module=report_builder&action=clone_template
 */
function handle_clone_template(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'source_template_id' => ['int', 'POST', true],
        'new_scope' => ['string', 'POST', true],
        'customer_id' => ['int', 'POST', false, null],
        'project_id' => ['int', 'POST', false, null],
        'new_name' => ['string', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // Validate scope consistency
    if ($params['new_scope'] === 'customer' && !$params['customer_id']) {
        return api_error('customer_id påkrævet for customer scope');
    }
    if ($params['new_scope'] === 'project' && !$params['project_id']) {
        return api_error('project_id påkrævet for project scope');
    }

    try {
        $newTemplateId = db()->query("
            SELECT clone_template(:source_id, :new_scope, :customer_id, :project_id, :user_id) as id
        ", [
            'source_id' => $params['source_template_id'],
            'new_scope' => $params['new_scope'],
            'customer_id' => $params['customer_id'],
            'project_id' => $params['project_id'],
            'user_id' => $user['id']
        ])->fetchColumn();

        // Update name if provided
        if ($params['new_name']) {
            db()->exec("UPDATE report_templates SET name = :name WHERE id = :id", [
                'name' => $params['new_name'],
                'id' => $newTemplateId
            ]);
        }

        log_activity('report_template_cloned', 'report_template', $newTemplateId);

        return [
            'success' => true,
            'template_id' => $newTemplateId,
            'message' => 'Template klone oprettet'
        ];

    } catch (Exception $e) {
        return api_error('Kunne ikke klone template: ' . $e->getMessage());
    }
}

/**
 * Get brand settings for customer
 * GET ?module=report_builder&action=get_brand_settings&customer_id=X
 */
function handle_get_brand_settings(array $user): array {
    $validation = api_validate_params([
        'customer_id' => ['int', 'GET', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $customerId = $validation['data']['customer_id'];

    $settings = db_fetch("
        SELECT * FROM template_brand_settings WHERE customer_id = :customer_id
    ", ['customer_id' => $customerId]);

    return [
        'success' => true,
        'settings' => $settings ?: [
            'customer_id' => $customerId,
            'primary_color' => '#1e40af',
            'secondary_color' => '#3b82f6',
            'accent_color' => '#60a5fa',
            'text_color' => '#1f2937',
            'font_family' => 'Arial, sans-serif',
            'show_page_numbers' => true
        ]
    ];
}

/**
 * Save brand settings for customer
 * POST ?module=report_builder&action=save_brand_settings
 */
function handle_save_brand_settings(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'customer_id' => ['int', 'POST', true],
        'logo_url' => ['string', 'POST', false, null],
        'primary_color' => ['string', 'POST', false, '#1e40af'],
        'secondary_color' => ['string', 'POST', false, '#3b82f6'],
        'accent_color' => ['string', 'POST', false, '#60a5fa'],
        'text_color' => ['string', 'POST', false, '#1f2937'],
        'font_family' => ['string', 'POST', false, 'Arial, sans-serif'],
        'header_template' => ['string', 'POST', false, null],
        'footer_template' => ['string', 'POST', false, null],
        'disclaimer_text' => ['string', 'POST', false, null],
        'copyright_text' => ['string', 'POST', false, null]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];
    $customerId = $params['customer_id'];
    unset($params['customer_id']);

    // Upsert brand settings
    $existing = db_fetch("SELECT id FROM template_brand_settings WHERE customer_id = :customer_id", [
        'customer_id' => $customerId
    ]);

    if ($existing) {
        // Update
        $params['updated_at'] = date('Y-m-d H:i:s');
        $result = api_crud_update('template_brand_settings', $existing['id'], $params);
    } else {
        // Insert
        $params['customer_id'] = $customerId;
        $params['created_at'] = date('Y-m-d H:i:s');
        $params['updated_at'] = date('Y-m-d H:i:s');
        $result = api_crud_create('template_brand_settings', $params);
    }

    if ($result['success']) {
        log_activity('brand_settings_saved', 'customer', $customerId);
        $result['message'] = 'Brand indstillinger gemt';
    }

    return $result;
}

/**
 * Render with Advanced Template Parser
 * POST ?module=report_builder&action=render_advanced
 */
function handle_render_advanced(array $user): array {
    require_once __DIR__ . '/../../core/advanced_template_parser.php';
    require_once __DIR__ . '/../../core/silent_fail_handler.php';

    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'project_id' => ['int', 'POST', true],
        'template_id' => ['int', 'POST', true]
    ]);

    if (!$validation['success']) {
        return $validation;
    }

    $params = $validation['data'];

    // Check project access
    $accessCheck = api_require_project_access($user, $params['project_id'], 'viewer');
    if (!$accessCheck['success']) {
        return $accessCheck;
    }

    // Get template with brand settings
    $template = db_fetch("
        SELECT
            rt.*,
            tbs.primary_color,
            tbs.secondary_color,
            tbs.logo_url,
            tbs.header_template,
            tbs.footer_template,
            tbs.disclaimer_text
        FROM report_templates rt
        LEFT JOIN projects p ON rt.project_id = p.id OR rt.customer_id = p.customer_id
        LEFT JOIN template_brand_settings tbs ON rt.customer_id = tbs.customer_id
        WHERE rt.id = :id
    ", ['id' => $params['template_id']]);

    if (!$template) {
        return api_error('Template ikke fundet');
    }

    // Get project data
    $data = get_report_data($params['project_id']);

    // Add brand settings to data
    $data['brand'] = [
        'primary_color' => $template['primary_color'] ?? '#1e40af',
        'secondary_color' => $template['secondary_color'] ?? '#3b82f6',
        'logo_url' => $template['logo_url'] ?? '',
        'disclaimer' => $template['disclaimer_text'] ?? ''
    ];

    // Create advanced parser with context
    $startTime = microtime(true);
    $parser = new AdvancedTemplateParser($data, [
        'project_id' => $params['project_id'],
        'customer_id' => $data['project']['customer_id'] ?? null,
        'template_id' => $params['template_id']
    ]);

    // Render template
    $rendered = $parser->parse($template['template_content']);
    $parser->flush(); // Flush error logs

    $renderDuration = (int)((microtime(true) - $startTime) * 1000); // milliseconds

    // Calculate output size
    $outputSizeKb = (int)(strlen($rendered) / 1024);

    return api_transaction(
        function() use ($params, $data, $template, $rendered, $user, $renderDuration, $outputSizeKb, $parser) {
            // Save rendered report
            $reportId = db_insert('reports', [
                'project_id' => $params['project_id'],
                'title' => $data['project']['name'] . ' - ' . $template['name'],
                'report_type' => $template['report_type'],
                'rich_text_content' => $rendered,
                'wysiwyg_enabled' => true,
                'generated_by_user_id' => $user['id'],
                'created_at' => date('Y-m-d H:i:s')
            ]);

            // Log template usage
            db_insert('template_usage_log', [
                'template_id' => $params['template_id'],
                'report_id' => $reportId,
                'user_id' => $user['id'],
                'project_id' => $params['project_id'],
                'customer_id' => $data['project']['customer_id'] ?? null,
                'render_duration_ms' => $renderDuration,
                'error_count' => $parser->getErrorCount(),
                'output_size_kb' => $outputSizeKb,
                'used_at' => date('Y-m-d H:i:s')
            ]);

            log_activity('report_rendered_advanced', 'report', $reportId);

            return [
                'report_id' => $reportId,
                'rendered' => $rendered,
                'metrics' => [
                    'render_duration_ms' => $renderDuration,
                    'error_count' => $parser->getErrorCount(),
                    'output_size_kb' => $outputSizeKb
                ]
            ];
        },
        'Rapport genereret',
        'Kunne ikke gemme rapport'
    );
}
