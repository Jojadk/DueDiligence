<?php
/* Building Module CRUD */

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$projectId = $_GET['project_id'] ?? null;

// AJAX search projects
if ($action === 'search_projects') {
    $q = $_GET['q'] ?? '';
    $results = db_query("SELECT id, name, address FROM projects WHERE " . db_ilike('name', ':q') . " OR " . db_ilike('address', ':q') . " ORDER BY name LIMIT 20", ['q' => '%'.$q.'%']);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'] . ' - ' . $r['address']], $results)]);
    exit;
}

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? null;

    $errors = validate_form('building', $_POST);
    if (empty($errors)) {
        $data = [
            'project_id' => sanitize_int($_POST['project_id']),
            'name' => sanitize_string($_POST['name']),
            'building_number' => sanitize_string($_POST['building_number'] ?? ''),
            'area_m2' => (float)($_POST['area_m2'] ?? 0),
            'construction_year' => sanitize_int($_POST['construction_year'] ?? null),
            'renovation_year' => sanitize_int($_POST['renovation_year'] ?? null),
            'heating_type' => sanitize_string($_POST['heating_type'] ?? ''),
            'usage_type' => sanitize_string($_POST['usage_type'] ?? ''),
            'floors' => sanitize_int($_POST['floors'] ?? 1),
            'description' => sanitize_string($_POST['description'] ?? '')
        ];

        try {
            if ($action === 'create') {
                $buildingId = db_insert('buildings', $data);
                log_activity('building_created', 'building', $buildingId);
                if (defined('AJAX_REQUEST')) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $buildingId, 'message' => 'Bygning oprettet']);
                    exit;
                }
                redirect('/?module=building&success=created');
            } elseif ($action === 'update' && $id) {
                db_update('buildings', $data, 'id = :id', ['id' => $id]);
                log_activity('building_updated', 'building', $id);
                if (defined('AJAX_REQUEST')) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Bygning opdateret']);
                    exit;
                }
                redirect('/?module=building&success=updated');
            }
        } catch (Exception $e) {
            log_error('Building save error: ' . $e->getMessage());
            $errors[] = 'Der opstod en fejl ved lagring';
        }
    }

    if (defined('AJAX_REQUEST')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'errors' => $errors]);
        exit;
    }
}

// DELETE
if ($action === 'delete' && $id) {
    csrf_require();
    try {
        $elementCount = db_value("SELECT COUNT(*) FROM building_elements WHERE building_id = :id", ['id' => $id]);
        if ($elementCount > 0) {
            $error = "Kan ikke slette bygning med $elementCount element(er)";
            if (defined('AJAX_REQUEST')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
            redirect('/?module=building&error=' . urlencode($error));
        }

        db_delete('buildings', 'id = :id', ['id' => $id]);
        log_activity('building_deleted', 'building', $id);

        if (defined('AJAX_REQUEST')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Bygning slettet']);
            exit;
        }
        redirect('/?module=building&success=deleted');
    } catch (Exception $e) {
        log_error('Building delete error: ' . $e->getMessage());
        if (defined('AJAX_REQUEST')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Sletningsfejl']);
            exit;
        }
        redirect('/?module=building&error=Sletningsfejl');
    }
}

// GET single
if ($action === 'get' && $id) {
    $building = db_fetch("SELECT b.*, p.name as project_label FROM buildings b LEFT JOIN projects p ON b.project_id = p.id WHERE b.id = :id", ['id' => $id]);
    if ($building) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $building]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Bygning ikke fundet']);
    }
    exit;
}

// LIST
$searchTerm = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($searchTerm) {
    $whereClauses[] = "(" . db_ilike('b.name', ':search') . " OR " . db_ilike('b.building_number', ':search') . " OR " . db_ilike('p.name', ':search') . ")";
    $params['search'] = '%' . $searchTerm . '%';
}

if ($projectId) {
    $whereClauses[] = "b.project_id = :project_id";
    $params['project_id'] = $projectId;
}

$where = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$totalBuildings = db_value("SELECT COUNT(*) FROM buildings b LEFT JOIN projects p ON b.project_id = p.id $where", $params);
$totalPages = ceil($totalBuildings / $perPage);

$buildings = db_query("
    SELECT b.*, p.name as project_name, COUNT(DISTINCT be.id) as element_count
    FROM buildings b
    LEFT JOIN projects p ON b.project_id = p.id
    LEFT JOIN building_elements be ON b.id = be.building_id
    $where
    GROUP BY b.id, p.name
    ORDER BY b.created_at DESC
    LIMIT :limit OFFSET :offset
", array_merge($params, ['limit' => $perPage, 'offset' => $offset]));

$successMessage = '';
$errorMessage = '';

if (isset($_GET['success'])) {
    $successMessage = match($_GET['success']) {
        'created' => 'Bygning oprettet succesfuldt',
        'updated' => 'Bygning opdateret succesfuldt',
        'deleted' => 'Bygning slettet succesfuldt',
        default => ''
    };
}

if (isset($_GET['error'])) {
    $errorMessage = $_GET['error'];
}

load_template(template_path('building', 'template'), compact('buildings', 'totalBuildings', 'page', 'totalPages', 'searchTerm', 'projectId', 'successMessage', 'errorMessage'));
