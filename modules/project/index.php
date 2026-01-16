<?php
/**
 * Project Module
 * CRUD operations for projects
 */

// Get action
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$customerId = $_GET['customer_id'] ?? null;

// Handle AJAX search for customers (searchable_select)
if ($action === 'search_customers') {
    $searchTerm = $_GET['q'] ?? '';
    $limit = min(50, (int)($_GET['limit'] ?? 20));

    $customers = db_query("
        SELECT id, name, cvr_number
        FROM customers
        WHERE name ILIKE :search OR cvr_number ILIKE :search
        ORDER BY name ASC
        LIMIT :limit
    ", [
        'search' => '%' . $searchTerm . '%',
        'limit' => $limit
    ]);

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'data' => array_map(function($c) {
            return [
                'id' => $c['id'],
                'label' => $c['name'] . ($c['cvr_number'] ? ' (CVR: ' . $c['cvr_number'] . ')' : '')
            ];
        }, $customers)
    ]);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? null;

    // Validate form data
    $errors = validate_form('project', $_POST);

    if (empty($errors)) {
        // Prepare data
        $data = [
            'name' => sanitize_string($_POST['name']),
            'customer_id' => sanitize_int($_POST['customer_id']),
            'address' => sanitize_string($_POST['address']),
            'postal_code' => sanitize_string($_POST['postal_code']),
            'city' => sanitize_string($_POST['city']),
            'bbr_number' => sanitize_string($_POST['bbr_number'] ?? ''),
            'inspection_date' => $_POST['inspection_date'] ?: null,
            'description' => sanitize_string($_POST['description'] ?? ''),
            'status' => sanitize_string($_POST['status'] ?? 'planning')
        ];

        try {
            if ($action === 'create') {
                // Insert new project
                $projectId = db_insert('projects', $data);

                // Create project directories
                $projectDir = get_project_dir($projectId);
                ensure_dir($projectDir . '/uploads');
                ensure_dir($projectDir . '/snapshots');

                log_activity('project_created', 'project', $projectId);

                // Return JSON response for AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $projectId, 'message' => 'Projekt oprettet']);
                    exit;
                }

                redirect('/?module=project&success=created');

            } elseif ($action === 'update' && $id) {
                // Update existing project
                db_update('projects', $data, 'id = :id', ['id' => $id]);

                log_activity('project_updated', 'project', $id);

                // Return JSON response for AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Projekt opdateret']);
                    exit;
                }

                redirect('/?module=project&success=updated');
            }
        } catch (Exception $e) {
            log_error('Project save error: ' . $e->getMessage());
            $errors[] = 'Der opstod en fejl ved lagring';
        }
    }

    // Return errors for AJAX
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
}

// Handle delete action
if ($action === 'delete' && $id) {
    csrf_require();

    try {
        // Check if project has buildings
        $buildingCount = db_value("SELECT COUNT(*) FROM buildings WHERE project_id = :id", ['id' => $id]);

        if ($buildingCount > 0) {
            $error = "Kan ikke slette projekt med $buildingCount bygning(er)";

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }

            redirect('/?module=project&error=' . urlencode($error));
        }

        db_delete('projects', 'id = :id', ['id' => $id]);

        log_activity('project_deleted', 'project', $id);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Projekt slettet']);
            exit;
        }

        redirect('/?module=project&success=deleted');

    } catch (Exception $e) {
        log_error('Project delete error: ' . $e->getMessage());
        $error = 'Der opstod en fejl ved sletning';

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }

        redirect('/?module=project&error=' . urlencode($error));
    }
}

// Handle get single project (for AJAX edit)
if ($action === 'get' && $id) {
    $project = db_fetch("SELECT * FROM projects WHERE id = :id", ['id' => $id]);

    if ($project) {
        // Also get customer info for display
        $customer = db_fetch("SELECT id, name FROM customers WHERE id = :id", ['id' => $project['customer_id']]);
        $project['customer_label'] = $customer ? $customer['name'] : '';

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $project]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Projekt ikke fundet']);
    }
    exit;
}

// Get list of projects
$searchTerm = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($searchTerm) {
    $whereClauses[] = "(p.name ILIKE :search OR p.address ILIKE :search OR p.city ILIKE :search OR c.name ILIKE :search)";
    $params['search'] = '%' . $searchTerm . '%';
}

if ($customerId) {
    $whereClauses[] = "p.customer_id = :customer_id";
    $params['customer_id'] = $customerId;
}

$where = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count
$totalProjects = db_value("
    SELECT COUNT(*)
    FROM projects p
    LEFT JOIN customers c ON p.customer_id = c.id
    $where
", $params);
$totalPages = ceil($totalProjects / $perPage);

// Get projects
$projects = db_query("
    SELECT
        p.*,
        c.name as customer_name,
        COUNT(DISTINCT b.id) as building_count,
        COUNT(DISTINCT be.id) as element_count
    FROM projects p
    LEFT JOIN customers c ON p.customer_id = c.id
    LEFT JOIN buildings b ON p.id = b.project_id
    LEFT JOIN building_elements be ON b.id = be.building_id
    $where
    GROUP BY p.id, c.name
    ORDER BY p.created_at DESC
    LIMIT :limit OFFSET :offset
", array_merge($params, ['limit' => $perPage, 'offset' => $offset]));

// Success/error messages
$successMessage = '';
$errorMessage = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Projekt oprettet succesfuldt';
            break;
        case 'updated':
            $successMessage = 'Projekt opdateret succesfuldt';
            break;
        case 'deleted':
            $successMessage = 'Projekt slettet succesfuldt';
            break;
    }
}

if (isset($_GET['error'])) {
    $errorMessage = $_GET['error'];
}

// Load template
load_template(template_path('project', 'template'), [
    'projects' => $projects,
    'totalProjects' => $totalProjects,
    'page' => $page,
    'totalPages' => $totalPages,
    'searchTerm' => $searchTerm,
    'customerId' => $customerId,
    'successMessage' => $successMessage,
    'errorMessage' => $errorMessage
]);
