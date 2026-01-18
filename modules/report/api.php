<?php
/**
 * Report Module API
 *
 * Handles report generation, management, and export
 *
 * Actions:
 * - generate: Generate new report
 * - get_list: Get reports for a project
 * - get_details: Get report details with data
 * - update: Update report metadata
 * - delete: Delete report
 * - export_pdf: Export report as PDF
 * - get_templates: Get available report templates
 */

require_once __DIR__ . '/../../core/permissions.php';

/**
 * Generate new report
 * POST ?module=report&action=generate
 */
function handle_generate(array $user): array {
    csrf_require();

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $reportType = sanitize_string($_POST['report_type'] ?? 'due_diligence');
    $title = sanitize_string($_POST['title'] ?? '');
    $includeImages = isset($_POST['include_images']) ? (bool)$_POST['include_images'] : true;
    $includeBudgets = isset($_POST['include_budgets']) ? (bool)$_POST['include_budgets'] : true;

    if (!$projectId || !$title) {
        return ['success' => false, 'error' => 'Projekt ID og titel er påkrævet'];
    }

    // Check project access (viewer required)
    if (!can_access_project($user, $projectId, 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til projektet'];
    }

    db_begin_transaction();
    try {
        // Get project data with summary
        $project = db_fetch("
            SELECT
                ps.project_id,
                ps.project_name,
                ps.building_count,
                ps.element_count,
                ps.total_capex,
                ps.critical_count,
                ps.high_count,
                ps.poor_count,
                ps.created_at
            FROM v_project_summary ps
            WHERE ps.project_id = :project_id
        ", ['project_id' => $projectId]);

        if (!$project) {
            db_rollback();
            return ['success' => false, 'error' => 'Projekt ikke fundet'];
        }

        // Collect report data based on type
        $reportData = [
            'project' => $project,
            'generated_by' => $user['name'],
            'generated_at' => date('Y-m-d H:i:s'),
            'include_images' => $includeImages,
            'include_budgets' => $includeBudgets
        ];

        // Get buildings with elements
        $buildings = db_fetch_all("
            SELECT
                bs.building_id,
                bs.building_name,
                bs.building_number,
                bs.building_type,
                bs.gross_area,
                bs.element_count,
                bs.total_capex,
                bs.avg_condition,
                bs.critical_count,
                bs.high_count
            FROM v_building_summary bs
            WHERE bs.project_id = :project_id
            ORDER BY bs.display_order ASC, bs.building_number ASC
        ", ['project_id' => $projectId]);

        foreach ($buildings as &$building) {
            // Get elements for building
            $elements = db_fetch_all("
                SELECT
                    es.element_id,
                    es.element_name,
                    es.element_code,
                    es.parent_id,
                    es.capex,
                    es.urgency,
                    es.condition_score,
                    es.red_flag_score,
                    es.severity
                FROM v_element_summary es
                WHERE es.building_id = :building_id
                ORDER BY es.display_order ASC, es.element_code ASC
            ", ['building_id' => $building['building_id']]);

            $building['elements'] = build_report_element_tree($elements);

            // Get OPEX if available
            if ($includeBudgets) {
                $opex = db_fetch("
                    SELECT effective_opex_yearly, opex_per_sqm
                    FROM v_building_opex_summary
                    WHERE building_id = :building_id
                ", ['building_id' => $building['building_id']]);

                $building['opex'] = $opex;
            }
        }

        $reportData['buildings'] = $buildings;

        // Get red flags summary
        $redFlagsSummary = db_fetch("
            SELECT
                SUM(CASE WHEN is_critical_urgency = 1 THEN 1 ELSE 0 END) as critical_urgency_count,
                SUM(CASE WHEN is_critical_urgency = 1 THEN capex ELSE 0 END) as critical_urgency_capex,
                SUM(CASE WHEN is_poor_condition = 1 THEN 1 ELSE 0 END) as poor_condition_count,
                SUM(CASE WHEN is_poor_condition = 1 THEN capex ELSE 0 END) as poor_condition_capex,
                SUM(CASE WHEN is_high_cost = 1 THEN 1 ELSE 0 END) as high_cost_count,
                SUM(CASE WHEN is_high_cost = 1 THEN capex ELSE 0 END) as high_cost_capex,
                COUNT(*) as total_elements
            FROM v_red_flags
            WHERE project_id = :project_id
        ", ['project_id' => $projectId]);

        $reportData['red_flags_summary'] = $redFlagsSummary;

        // Get top red flags
        $topRedFlags = db_fetch_all("
            SELECT
                element_id,
                element_name,
                building_name,
                red_flag_score,
                severity,
                capex,
                urgency,
                condition_score
            FROM v_red_flags
            WHERE project_id = :project_id
            ORDER BY red_flag_score DESC
            LIMIT 20
        ", ['project_id' => $projectId]);

        $reportData['top_red_flags'] = $topRedFlags;

        // Save report
        $reportRecord = [
            'project_id' => $projectId,
            'title' => $title,
            'report_type' => $reportType,
            'generated_by_user_id' => $user['id'],
            'report_data' => json_encode($reportData),
            'include_images' => $includeImages,
            'include_budgets' => $includeBudgets,
            'created_at' => date('Y-m-d H:i:s')
        ];

        $reportId = db_insert('reports', $reportRecord);

        db_commit();

        log_activity('report_generated', 'report', $reportId);

        return [
            'success' => true,
            'report_id' => $reportId,
            'message' => 'Rapport genereret succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke generere rapport'];
    }
}

/**
 * Get list of reports for a project
 * GET ?module=report&action=get_list&project_id=X
 */
function handle_get_list(array $user): array {
    $projectId = sanitize_int($_GET['project_id'] ?? 0);

    if (!$projectId) {
        return ['success' => false, 'error' => 'Projekt ID mangler'];
    }

    // Check project access
    if (!can_access_project($user, $projectId, 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til projektet'];
    }

    $reports = db_fetch_all("
        SELECT
            r.id,
            r.title,
            r.report_type,
            r.generated_by_user_id,
            u.name as generated_by_name,
            r.include_images,
            r.include_budgets,
            r.created_at
        FROM reports r
        LEFT JOIN users u ON u.id = r.generated_by_user_id
        WHERE r.project_id = :project_id
        ORDER BY r.created_at DESC
    ", ['project_id' => $projectId]);

    return [
        'success' => true,
        'reports' => $reports
    ];
}

/**
 * Get detailed report information with data
 * GET ?module=report&action=get_details&id=X
 */
function handle_get_details(array $user): array {
    $reportId = sanitize_int($_GET['id'] ?? 0);

    if (!$reportId) {
        return ['success' => false, 'error' => 'Rapport ID mangler'];
    }

    // Get report
    $report = db_fetch("
        SELECT
            r.*,
            u.name as generated_by_name
        FROM reports r
        LEFT JOIN users u ON u.id = r.generated_by_user_id
        WHERE r.id = :id
    ", ['id' => $reportId]);

    if (!$report) {
        return ['success' => false, 'error' => 'Rapport ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $report['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til rapporten'];
    }

    // Decode report data
    if ($report['report_data']) {
        $report['data'] = json_decode($report['report_data'], true);
        unset($report['report_data']); // Remove raw JSON
    }

    return [
        'success' => true,
        'report' => $report
    ];
}

/**
 * Update report metadata
 * POST ?module=report&action=update
 */
function handle_update(array $user): array {
    csrf_require();

    $reportId = sanitize_int($_POST['id'] ?? 0);

    if (!$reportId) {
        return ['success' => false, 'error' => 'Rapport ID mangler'];
    }

    // Get report
    $report = db_fetch("SELECT project_id FROM reports WHERE id = :id", ['id' => $reportId]);

    if (!$report) {
        return ['success' => false, 'error' => 'Rapport ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $report['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at redigere rapport'];
    }

    db_begin_transaction();
    try {
        $updateData = [];

        if (isset($_POST['title'])) {
            $updateData['title'] = sanitize_string($_POST['title']);
        }

        if (!empty($updateData)) {
            db_update('reports', $updateData, 'id = :id', ['id' => $reportId]);
        }

        db_commit();

        log_activity('report_updated', 'report', $reportId);

        return [
            'success' => true,
            'message' => 'Rapport opdateret succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rapport'];
    }
}

/**
 * Delete report
 * POST ?module=report&action=delete
 */
function handle_delete(array $user): array {
    csrf_require();

    $reportId = sanitize_int($_POST['id'] ?? 0);

    if (!$reportId) {
        return ['success' => false, 'error' => 'Rapport ID mangler'];
    }

    // Get report
    $report = db_fetch("SELECT project_id FROM reports WHERE id = :id", ['id' => $reportId]);

    if (!$report) {
        return ['success' => false, 'error' => 'Rapport ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $report['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at slette rapport'];
    }

    db_begin_transaction();
    try {
        db_delete('reports', 'id = :id', ['id' => $reportId]);

        db_commit();

        log_activity('report_deleted', 'report', $reportId);

        return [
            'success' => true,
            'message' => 'Rapport slettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette rapport'];
    }
}

/**
 * Export report as PDF
 * GET ?module=report&action=export_pdf&id=X
 */
function handle_export_pdf(array $user): array {
    $reportId = sanitize_int($_GET['id'] ?? 0);

    if (!$reportId) {
        return ['success' => false, 'error' => 'Rapport ID mangler'];
    }

    // Get report
    $report = db_fetch("
        SELECT r.*, u.name as generated_by_name
        FROM reports r
        LEFT JOIN users u ON u.id = r.generated_by_user_id
        WHERE r.id = :id
    ", ['id' => $reportId]);

    if (!$report) {
        return ['success' => false, 'error' => 'Rapport ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $report['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til rapporten'];
    }

    // Decode report data
    $reportData = json_decode($report['report_data'], true);

    // Generate PDF (placeholder - actual PDF generation would use a library like TCPDF or mPDF)
    $pdfUrl = generate_report_pdf($reportId, $report, $reportData);

    log_activity('report_exported', 'report', $reportId);

    return [
        'success' => true,
        'pdf_url' => $pdfUrl,
        'message' => 'Rapport eksporteret som PDF'
    ];
}

/**
 * Get available report templates
 * GET ?module=report&action=get_templates
 */
function handle_get_templates(array $user): array {
    $templates = [
        [
            'type' => 'due_diligence',
            'name' => 'Due Diligence Rapport',
            'description' => 'Komplet analyse af bygninger og elementer med røde flag',
            'sections' => ['summary', 'buildings', 'elements', 'red_flags', 'budgets']
        ],
        [
            'type' => 'executive_summary',
            'name' => 'Executive Summary',
            'description' => 'Kort opsummering af nøgletal og kritiske punkter',
            'sections' => ['summary', 'key_metrics', 'top_red_flags']
        ],
        [
            'type' => 'budget_overview',
            'name' => 'Budget Oversigt',
            'description' => 'Detaljeret gennemgang af CAPEX, OPEX og reinstatement budgetter',
            'sections' => ['summary', 'capex', 'opex', 'reinstatement', 'timeline']
        ],
        [
            'type' => 'condition_assessment',
            'name' => 'Tilstandsvurdering',
            'description' => 'Fokus på tilstandsscorer og anbefalede handlinger',
            'sections' => ['summary', 'condition_scores', 'urgency_matrix', 'recommendations']
        ]
    ];

    return [
        'success' => true,
        'templates' => $templates
    ];
}

/**
 * Helper function to build element tree for report
 */
function build_report_element_tree(array $elements): array {
    $indexed = [];
    $tree = [];

    // First pass: index by ID
    foreach ($elements as $element) {
        $element['children'] = [];
        $indexed[$element['element_id']] = $element;
    }

    // Second pass: build tree
    foreach ($indexed as $id => $element) {
        if ($element['parent_id'] === null) {
            $tree[] = &$indexed[$id];
        } else {
            if (isset($indexed[$element['parent_id']])) {
                $indexed[$element['parent_id']]['children'][] = &$indexed[$id];
            }
        }
    }

    return $tree;
}

/**
 * Helper function to generate PDF (placeholder)
 */
function generate_report_pdf(int $reportId, array $report, array $reportData): string {
    // This is a placeholder. In a real implementation, you would use a library like:
    // - TCPDF
    // - mPDF
    // - Dompdf
    // - wkhtmltopdf

    // For now, return a placeholder URL
    return "/reports/pdf/{$reportId}/" . urlencode($report['title']) . ".pdf";
}
