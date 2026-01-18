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
            'label' => 'Formatering',
            'examples' => [
                '{{format_number(project.total_capex)}}' => 'Formatér tal (1.000.000)',
                '{{format_date(project.created_at)}}' => 'Formatér dato (01-01-2024)',
                '{{format_currency(element.capex)}}' => 'Formatér valuta (kr. 1.000.000)'
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
    // Replace simple variables
    $rendered = $template;

    // Project variables
    foreach ($data['project'] as $key => $value) {
        $rendered = str_replace("{{project.$key}}", htmlspecialchars((string)$value), $rendered);
    }

    // Handle loops - buildings
    $rendered = handle_loop($rendered, 'buildings', $data['buildings']);

    // Handle loops - elements
    $rendered = handle_loop($rendered, 'elements', $data['elements']);

    // Handle loops - red_flags
    $rendered = handle_loop($rendered, 'red_flags', $data['red_flags']);

    // Handle conditionals
    $rendered = handle_conditionals($rendered, $data);

    // Handle formatting functions
    $rendered = handle_formatting($rendered);

    return $rendered;
}

/**
 * Helper: Handle loop constructs
 */
function handle_loop(string $template, string $loopName, array $items): string {
    $pattern = '/\{\{for\s+(\w+)\s+in\s+' . preg_quote($loopName) . '\}\}(.*?)\{\{endfor\}\}/s';

    return preg_replace_callback($pattern, function($matches) use ($items) {
        $itemVar = $matches[1];
        $loopTemplate = $matches[2];
        $output = '';

        foreach ($items as $item) {
            $itemOutput = $loopTemplate;
            foreach ($item as $key => $value) {
                $itemOutput = str_replace("{{{$itemVar}.$key}}", htmlspecialchars((string)$value), $itemOutput);
            }
            $output .= $itemOutput;
        }

        return $output;
    }, $template);
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
 * Helper: Handle formatting functions
 */
function handle_formatting(string $template): string {
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
