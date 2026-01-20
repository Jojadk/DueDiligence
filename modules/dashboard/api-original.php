<?php
/**
 * Dashboard Module API
 *
 * Handles dashboard statistics and widgets
 * All functions must be named: handle_{action}
 *
 * Available actions:
 * - get_stats: Dashboard statistics (optimized)
 * - get_widgets: Dashboard widgets (recent projects, urgent items)
 */

/**
 * Get dashboard statistics
 * OPTIMIZED: Uses v_project_summary view
 *
 * GET /api.php?module=dashboard&action=get_stats
 */
function handle_get_stats(array $user): array {
    $stats = [
        'customers' => 0,
        'projects' => 0,
        'buildings' => 0,
        'elements' => 0,
        'active_projects' => 0,
        'total_capex' => 0,
        'pending_items' => 0,
        'urgent_items' => 0
    ];

    // Admin sees all, users see only their accessible projects
    $accessibleProjects = get_accessible_projects($user, 'viewer');
    $projectIds = array_column($accessibleProjects, 'project_id');

    if (empty($projectIds)) {
        // No accessible projects
        return ['success' => true, 'stats' => $stats];
    }

    // Build IN clause for project filter
    $placeholders = [];
    $params = [];
    foreach ($projectIds as $idx => $pid) {
        $key = "pid{$idx}";
        $placeholders[] = ":{$key}";
        $params[$key] = $pid;
    }
    $inClause = implode(',', $placeholders);

    // Use view for aggregation
    $summary = db_fetch("
        SELECT
            COUNT(*) as project_count,
            COALESCE(SUM(building_count), 0) as building_count,
            COALESCE(SUM(element_count), 0) as element_count,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
            COALESCE(SUM(total_capex), 0) as total_capex,
            COALESCE(SUM(critical_count + high_count), 0) as urgent_count
        FROM v_project_summary
        WHERE project_id IN ($inClause)
    ", $params);

    $stats['customers'] = db_value("
        SELECT COUNT(DISTINCT customer_id)
        FROM projects
        WHERE id IN ($inClause)
    ", $params);

    $stats['projects'] = (int)($summary['project_count'] ?? 0);
    $stats['buildings'] = (int)($summary['building_count'] ?? 0);
    $stats['elements'] = (int)($summary['element_count'] ?? 0);
    $stats['active_projects'] = (int)($summary['active_count'] ?? 0);
    $stats['total_capex'] = (float)($summary['total_capex'] ?? 0);
    $stats['urgent_items'] = (int)($summary['urgent_count'] ?? 0);

    return ['success' => true, 'stats' => $stats];
}

/**
 * Get dashboard widgets
 * OPTIMIZED: Uses v_project_summary and v_red_flags views
 *
 * GET /api.php?module=dashboard&action=get_widgets
 */
function handle_get_widgets(array $user): array {
    $widgets = [];

    // Get accessible projects
    $accessibleProjects = get_accessible_projects($user, 'viewer');
    $projectIds = array_column($accessibleProjects, 'project_id');

    if (empty($projectIds)) {
        return [
            'success' => true,
            'widgets' => [
                'recent_projects' => [],
                'urgent_elements' => []
            ],
            'permissions' => get_user_permissions($user['id'])
        ];
    }

    // Build project filter
    $placeholders = [];
    $params = [];
    foreach ($projectIds as $idx => $pid) {
        $key = "pid{$idx}";
        $placeholders[] = ":{$key}";
        $params[$key] = $pid;
    }
    $inClause = implode(',', $placeholders);

    // Recent projects with pre-calculated stats
    $widgets['recent_projects'] = db_query("
        SELECT ps.project_id as id, ps.project_name as name, ps.status,
               ps.building_count, ps.element_count, ps.total_capex,
               ps.critical_count, ps.high_count, ps.created_at,
               c.name as customer_name
        FROM v_project_summary ps
        LEFT JOIN projects p ON ps.project_id = p.id
        LEFT JOIN customers c ON p.customer_id = c.id
        WHERE ps.project_id IN ($inClause)
        ORDER BY ps.created_at DESC
        LIMIT 5
    ", $params);

    // Urgent elements from v_red_flags (already filtered and scored)
    $widgets['urgent_elements'] = db_query("
        SELECT element_id as id, element_name as name, building_name,
               project_name, urgency, capex, red_flag_score, severity
        FROM v_red_flags
        WHERE project_id IN ($inClause)
        ORDER BY red_flag_score DESC, capex DESC
        LIMIT 10
    ", $params);

    return [
        'success' => true,
        'widgets' => $widgets,
        'permissions' => get_user_permissions($user['id'])
    ];
}

/**
 * Get recent activity for user
 *
 * GET /api.php?module=dashboard&action=get_activity
 */
function handle_get_activity(array $user): array {
    $limit = sanitize_int($_GET['limit'] ?? 20);
    $offset = sanitize_int($_GET['offset'] ?? 0);

    $activity = db_fetch_all("
        SELECT *
        FROM activity_log
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ", [
        'user_id' => $user['id'],
        'limit' => $limit,
        'offset' => $offset
    ]);

    $total = db_value("
        SELECT COUNT(*) FROM activity_log WHERE user_id = :user_id
    ", ['user_id' => $user['id']]);

    return [
        'success' => true,
        'activity' => $activity,
        'total' => (int)$total,
        'limit' => $limit,
        'offset' => $offset
    ];
}
