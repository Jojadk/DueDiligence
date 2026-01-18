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

/**
 * Get available template variables
 * GET ?module=report_builder&action=get_variables&project_id=X
 */
function handle_get_variables(array $user): array {
    $projectId = isset($_GET['project_id']) ? sanitize_int($_GET['project_id']) : null;

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
    csrf_require();

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $template = $_POST['template'] ?? '';

    if (!$projectId || !$template) {
        return ['success' => false, 'error' => 'Projekt ID og template er påkrævet'];
    }

    // Check project access
    if (!can_access_project($user, $projectId, 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til projektet'];
    }

    // Get project data
    $data = get_report_data($projectId);

    // Render template
    $rendered = render_template($template, $data);

    return [
        'success' => true,
        'rendered' => $rendered
    ];
}

/**
 * Save report template
 * POST ?module=report_builder&action=save
 */
function handle_save(array $user): array {
    csrf_require();

    $id = isset($_POST['id']) ? sanitize_int($_POST['id']) : null;
    $name = sanitize_string($_POST['name'] ?? '');
    $template = $_POST['template'] ?? '';
    $reportType = sanitize_string($_POST['report_type'] ?? 'custom');

    if (!$name || !$template) {
        return ['success' => false, 'error' => 'Navn og template er påkrævet'];
    }

    db_begin_transaction();
    try {
        if ($id) {
            // Update existing
            db_update('report_templates', [
                'name' => $name,
                'template_content' => $template,
                'report_type' => $reportType,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $id]);

            $templateId = $id;
        } else {
            // Create new
            $templateId = db_insert('report_templates', [
                'name' => $name,
                'template_content' => $template,
                'report_type' => $reportType,
                'created_by_user_id' => $user['id'],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        db_commit();

        log_activity('report_template_saved', 'report_template', $templateId);

        return [
            'success' => true,
            'template_id' => $templateId,
            'message' => 'Template gemt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke gemme template'];
    }
}

/**
 * Render final report
 * POST ?module=report_builder&action=render
 */
function handle_render(array $user): array {
    csrf_require();

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $templateId = sanitize_int($_POST['template_id'] ?? 0);

    if (!$projectId || !$templateId) {
        return ['success' => false, 'error' => 'Projekt ID og template ID er påkrævet'];
    }

    // Check project access
    if (!can_access_project($user, $projectId, 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til projektet'];
    }

    // Get template
    $template = db_fetch("
        SELECT * FROM report_templates WHERE id = :id
    ", ['id' => $templateId]);

    if (!$template) {
        return ['success' => false, 'error' => 'Template ikke fundet'];
    }

    // Get project data
    $data = get_report_data($projectId);

    // Render template
    $rendered = render_template($template['template_content'], $data);

    db_begin_transaction();
    try {
        // Save rendered report
        $reportId = db_insert('reports', [
            'project_id' => $projectId,
            'title' => $data['project']['name'] . ' - ' . $template['name'],
            'report_type' => $template['report_type'],
            'rich_text_content' => $rendered,
            'wysiwyg_enabled' => true,
            'generated_by_user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);

        db_commit();

        log_activity('report_rendered', 'report', $reportId);

        return [
            'success' => true,
            'report_id' => $reportId,
            'rendered' => $rendered,
            'message' => 'Rapport genereret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke gemme rapport'];
    }
}

/**
 * Get report template
 * GET ?module=report_builder&action=get_template&id=X
 */
function handle_get_template(array $user): array {
    $templateId = sanitize_int($_GET['id'] ?? 0);

    if (!$templateId) {
        return ['success' => false, 'error' => 'Template ID mangler'];
    }

    $template = db_fetch("
        SELECT * FROM report_templates WHERE id = :id
    ", ['id' => $templateId]);

    if (!$template) {
        return ['success' => false, 'error' => 'Template ikke fundet'];
    }

    return [
        'success' => true,
        'template' => $template
    ];
}

/**
 * Get all report templates
 * GET ?module=report_builder&action=get_templates
 */
function handle_get_templates(array $user): array {
    $reportType = sanitize_string($_GET['report_type'] ?? '');

    $query = "SELECT * FROM report_templates";
    $params = [];

    if ($reportType) {
        $query .= " WHERE report_type = :report_type";
        $params['report_type'] = $reportType;
    }

    $query .= " ORDER BY name ASC";

    $templates = db_fetch_all($query, $params);

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
    csrf_require();

    $templateId = sanitize_int($_POST['id'] ?? 0);

    if (!$templateId) {
        return ['success' => false, 'error' => 'Template ID mangler'];
    }

    db_begin_transaction();
    try {
        db_delete('report_templates', 'id = :id', ['id' => $templateId]);

        db_commit();

        log_activity('report_template_deleted', 'report_template', $templateId);

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

    // Handle pipe filters BEFORE replacing simple variables
    $rendered = handle_pipe_filters($rendered, $data);

    // Replace simple variables (without filters)
    foreach ($data['project'] as $key => $value) {
        $rendered = str_replace("{{project.$key}}", htmlspecialchars((string)$value), $rendered);
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

            // Handle pipe filters inside loop
            $itemOutput = handle_loop_pipe_filters($itemOutput, $itemVar, $item);

            // Handle red_flags_display inside loop
            $itemOutput = str_replace("{{REDFLAG_DISPLAY:$itemVar}}", generate_red_flags_display($item), $itemOutput);

            // Replace simple variables
            foreach ($item as $key => $value) {
                $itemOutput = str_replace("{{{$itemVar}.$key}}", htmlspecialchars((string)$value), $itemOutput);
            }

            $output .= $itemOutput;
        }

        return $output;
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
 */
function generate_red_flags_display(array $item): string {
    // Define all 5 flag types
    $allFlags = [
        1 => ['label' => 'Kritisk', 'color' => '#DC2626', 'icon' => '🚩'],
        2 => ['label' => 'Alvorlig', 'color' => '#F97316', 'icon' => '⚠️'],
        3 => ['label' => 'Moderat', 'color' => '#FBBF24', 'icon' => '⚡'],
        4 => ['label' => 'Mindre', 'color' => '#10B981', 'icon' => 'ℹ️'],
        5 => ['label' => 'Info', 'color' => '#3B82F6', 'icon' => '💡']
    ];

    // Get element's red flag score (1-5)
    $score = (int)($item['red_flag_score'] ?? 0);

    $html = '<span class="red-flags-display" style="display: inline-flex; gap: 4px;">';

    foreach ($allFlags as $flagValue => $flag) {
        $isActive = ($flagValue == $score);
        $opacity = $isActive ? '1' : '0.2';
        $border = $isActive ? '2px solid ' . $flag['color'] : '1px solid #e5e7eb';

        $html .= sprintf(
            '<span class="flag-badge" style="display: inline-flex; align-items: center; gap: 2px; padding: 2px 6px; border: %s; border-radius: 4px; opacity: %s; background: %s; font-size: 12px;">
                <span>%s</span>
                <span style="color: %s; font-weight: %s;">%s</span>
            </span>',
            $border,
            $opacity,
            $isActive ? $flag['color'] . '20' : '#f9fafb',
            $flag['icon'],
            $flag['color'],
            $isActive ? 'bold' : 'normal',
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
 * Helper: Handle pipe filters ({{variable | filter}})
 */
function handle_pipe_filters(string $template, array $data): string {
    // Match {{variable | filter}} or {{variable | filter:param}}
    $pattern = '/\{\{([a-z_]+\.[a-z_]+)\s*\|\s*([a-z_]+)(?::(\d+))?\}\}/i';

    return preg_replace_callback($pattern, function($matches) use ($data) {
        $variable = $matches[1]; // e.g., "project.name"
        $filter = $matches[2];   // e.g., "uppercase"
        $param = $matches[3] ?? null; // e.g., "100" for truncate:100

        // Get variable value
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
