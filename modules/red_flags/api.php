<?php
/**
 * Red Flags Module API
 *
 * Handles red flag detection and reporting
 * OPTIMIZED: Uses v_red_flags view with pre-calculated scores
 *
 * Available actions:
 * - get_list: Get red flags with details
 * - get_summary: Get red flags summary statistics
 */

/**
 * Get red flags list
 * OPTIMIZED: Uses v_red_flags view with pre-calculated scores
 *
 * GET /api.php?module=red_flags&action=get_list&project_id=123
 */
function handle_get_list(array $user): array {
    $projectId = isset($_GET['project_id']) ? sanitize_int($_GET['project_id']) : null;

    // Build WHERE clause based on permissions
    $where = [];
    $params = [];

    if ($projectId) {
        // Verify project access
        if (!can_access_project($user, $projectId, 'viewer')) {
            return ['success' => false, 'error' => 'No access to this project'];
        }

        $where[] = 'project_id = :project_id';
        $params['project_id'] = $projectId;
    } else {
        // Get all accessible projects
        $accessibleProjects = get_accessible_projects($user, 'viewer');
        $projectIds = array_column($accessibleProjects, 'project_id');

        if (empty($projectIds)) {
            return [
                'success' => true,
                'red_flags' => [],
                'total_count' => 0
            ];
        }

        $placeholders = [];
        foreach ($projectIds as $idx => $pid) {
            $key = "pid{$idx}";
            $placeholders[] = ":{$key}";
            $params[$key] = $pid;
        }
        $where[] = 'project_id IN (' . implode(',', $placeholders) . ')';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // OPTIMIZED: Use v_red_flags view - scores and severity pre-calculated
    $redFlagElements = db_fetch_all("
        SELECT *
        FROM v_red_flags
        $whereClause
        ORDER BY red_flag_score DESC, capex DESC
    ", $params);

    $redFlags = [];

    // Build flag details array from pre-calculated indicators
    foreach ($redFlagElements as $element) {
        $flags = [];

        // Add flags based on indicators
        if ($element['is_critical_urgency'] == 1) {
            $flags[] = [
                'type' => 'urgency',
                'label' => 'Kritisk prioritet',
                'description' => 'Element markeret som kritisk prioritet',
                'severity' => 'critical'
            ];
        } elseif ($element['is_high_urgency'] == 1) {
            $flags[] = [
                'type' => 'urgency',
                'label' => 'Høj prioritet',
                'description' => 'Element markeret som høj prioritet',
                'severity' => 'high'
            ];
        }

        if ($element['is_poor_condition'] == 1) {
            $flags[] = [
                'type' => 'condition',
                'label' => 'Dårlig tilstand',
                'description' => 'Element i dårlig eller kritisk tilstand',
                'severity' => 'high'
            ];
        }

        if ($element['is_high_cost'] == 1) {
            $flags[] = [
                'type' => 'high_cost',
                'label' => 'Høj omkostning',
                'description' => 'CAPEX over 500.000 kr',
                'severity' => 'normal'
            ];
        }

        if ($element['is_missing_description'] == 1) {
            $flags[] = [
                'type' => 'missing_data',
                'label' => 'Manglende beskrivelse',
                'description' => 'Element mangler detaljeret beskrivelse',
                'severity' => 'low'
            ];
        }

        if ($element['is_missing_quantity'] == 1) {
            $flags[] = [
                'type' => 'missing_data',
                'label' => 'Manglende mængde',
                'description' => 'Element mangler mængdeangivelse',
                'severity' => 'normal'
            ];
        }

        $redFlags[] = [
            'element' => [
                'id' => $element['element_id'],
                'name' => $element['element_name'],
                'building_id' => $element['building_id'],
                'building_name' => $element['building_name'],
                'project_id' => $element['project_id'],
                'project_name' => $element['project_name'],
                'urgency' => $element['urgency'],
                'condition' => $element['condition'],
                'capex' => $element['capex'],
                'description' => $element['description'],
                'quantity' => $element['quantity']
            ],
            'flags' => $flags,
            'severity' => $element['severity'], // Pre-calculated in view
            'score' => (int)$element['red_flag_score'] // Pre-calculated in view
        ];
    }

    return [
        'success' => true,
        'red_flags' => $redFlags,
        'total_count' => count($redFlags)
    ];
}

/**
 * Get red flags summary statistics
 * OPTIMIZED: Uses v_red_flags view - single query instead of 6
 *
 * GET /api.php?module=red_flags&action=get_summary&project_id=123
 */
function handle_get_summary(array $user): array {
    $projectId = isset($_GET['project_id']) ? sanitize_int($_GET['project_id']) : null;

    // Build WHERE clause
    $where = [];
    $params = [];

    if ($projectId) {
        // Verify project access
        if (!can_access_project($user, $projectId, 'viewer')) {
            return ['success' => false, 'error' => 'No access to this project'];
        }

        $where[] = 'project_id = :project_id';
        $params['project_id'] = $projectId;
    } else {
        // Get all accessible projects
        $accessibleProjects = get_accessible_projects($user, 'viewer');
        $projectIds = array_column($accessibleProjects, 'project_id');

        if (empty($projectIds)) {
            return [
                'success' => true,
                'summary' => [
                    'urgency' => ['critical' => ['count' => 0, 'capex' => 0], 'high' => ['count' => 0, 'capex' => 0]],
                    'condition' => ['poor_condition_count' => 0, 'poor_condition_capex' => 0],
                    'costs' => ['high_cost_count' => 0, 'high_cost_capex' => 0],
                    'data_quality' => ['missing_description' => 0, 'missing_quantity' => 0],
                    'totals' => ['total_urgent_items' => 0, 'total_urgent_capex' => 0]
                ]
            ];
        }

        $placeholders = [];
        foreach ($projectIds as $idx => $pid) {
            $key = "pid{$idx}";
            $placeholders[] = ":{$key}";
            $params[$key] = $pid;
        }
        $where[] = 'project_id IN (' . implode(',', $placeholders) . ')';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // OPTIMIZED: Single query to v_red_flags view aggregates all statistics
    $stats = db_fetch("
        SELECT
            -- Urgency counts
            SUM(CASE WHEN is_critical_urgency = 1 THEN 1 ELSE 0 END) as critical_count,
            SUM(CASE WHEN is_critical_urgency = 1 THEN capex ELSE 0 END) as critical_capex,
            SUM(CASE WHEN is_high_urgency = 1 THEN 1 ELSE 0 END) as high_count,
            SUM(CASE WHEN is_high_urgency = 1 THEN capex ELSE 0 END) as high_capex,

            -- Condition counts
            SUM(CASE WHEN is_poor_condition = 1 THEN 1 ELSE 0 END) as poor_condition_count,
            SUM(CASE WHEN is_poor_condition = 1 THEN capex ELSE 0 END) as poor_condition_capex,

            -- High cost counts
            SUM(CASE WHEN is_high_cost = 1 THEN 1 ELSE 0 END) as high_cost_count,
            SUM(CASE WHEN is_high_cost = 1 THEN capex ELSE 0 END) as high_cost_capex,

            -- Data quality counts
            SUM(CASE WHEN is_missing_description = 1 THEN 1 ELSE 0 END) as missing_description,
            SUM(CASE WHEN is_missing_quantity = 1 THEN 1 ELSE 0 END) as missing_quantity

        FROM v_red_flags
        $whereClause
    ", $params);

    $summary = [
        'urgency' => [
            'critical' => [
                'count' => (int)($stats['critical_count'] ?? 0),
                'capex' => (float)($stats['critical_capex'] ?? 0)
            ],
            'high' => [
                'count' => (int)($stats['high_count'] ?? 0),
                'capex' => (float)($stats['high_capex'] ?? 0)
            ]
        ],
        'condition' => [
            'poor_condition_count' => (int)($stats['poor_condition_count'] ?? 0),
            'poor_condition_capex' => (float)($stats['poor_condition_capex'] ?? 0)
        ],
        'costs' => [
            'high_cost_count' => (int)($stats['high_cost_count'] ?? 0),
            'high_cost_capex' => (float)($stats['high_cost_capex'] ?? 0)
        ],
        'data_quality' => [
            'missing_description' => (int)($stats['missing_description'] ?? 0),
            'missing_quantity' => (int)($stats['missing_quantity'] ?? 0)
        ],
        'totals' => [
            'total_urgent_items' => (int)($stats['critical_count'] ?? 0) + (int)($stats['high_count'] ?? 0),
            'total_urgent_capex' => (float)($stats['critical_capex'] ?? 0) + (float)($stats['high_capex'] ?? 0)
        ]
    ];

    return [
        'success' => true,
        'summary' => $summary
    ];
}
