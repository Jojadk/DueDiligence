<?php
/* Building Element Module with Hierarchy */

$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;
$buildingId = $_GET['building_id'] ?? null;

// AJAX searches
if ($action === 'search_buildings') {
    $q = $_GET['q'] ?? '';
    $results = db_query("SELECT b.id, b.name, p.name as project_name FROM buildings b LEFT JOIN projects p ON b.project_id = p.id WHERE " . db_ilike('b.name', ':q') . " ORDER BY b.name LIMIT 20", ['q' => '%'.$q.'%']);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'] . ' (' . $r['project_name'] . ')'], $results)]);
    exit;
}

if ($action === 'search_parents') {
    $bid = $_GET['building_id'] ?? null;
    $results = db_query("SELECT id, name, location FROM building_elements WHERE building_id = :bid ORDER BY name LIMIT 20", ['bid' => $bid]);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'] . ($r['location'] ? ' - ' . $r['location'] : '')], $results)]);
    exit;
}

if ($action === 'search_price_catalog') {
    $q = $_GET['q'] ?? '';
    $results = db_query("SELECT id, name, category, unit_price, unit FROM price_catalogs WHERE (" . db_ilike('name', ':q') . " OR " . db_ilike('category', ':q') . ") AND active = true ORDER BY category, name LIMIT 20", ['q' => '%'.$q.'%']);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => array_map(fn($r) => ['id' => $r['id'], 'label' => $r['name'] . ' - ' . $r['category'] . ' (' . number_format($r['unit_price'], 2, ',', '.') . ' kr/' . $r['unit'] . ')'], $results)]);
    exit;
}

// POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? null;

    $errors = [];
    $data = [
        'building_id' => sanitize_int($_POST['building_id']),
        'parent_id' => !empty($_POST['parent_id']) ? sanitize_int($_POST['parent_id']) : null,
        'price_catalog_id' => !empty($_POST['price_catalog_id']) ? sanitize_int($_POST['price_catalog_id']) : null,
        'name' => sanitize_string($_POST['name']),
        'element_type' => sanitize_string($_POST['element_type'] ?? ''),
        'location' => sanitize_string($_POST['location'] ?? ''),
        'description' => sanitize_string($_POST['description'] ?? ''),
        'condition_score' => sanitize_int($_POST['condition_score'] ?? 0),
        'urgency' => sanitize_string($_POST['urgency'] ?? 'normal'),
        'time_horizon' => sanitize_int($_POST['time_horizon'] ?? 0),
        'capex' => (float)($_POST['capex'] ?? 0),
        'replacement_value' => (float)($_POST['replacement_value'] ?? 0)
    ];

    if (empty($errors)) {
        try {
            if ($action === 'create') {
                $elemId = db_insert('building_elements', $data);
                log_activity('element_created', 'building_element', $elemId);
                if (defined('AJAX_REQUEST')) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $elemId, 'message' => 'Bygningsdel oprettet']);
                    exit;
                }
                redirect('/?module=building_element&success=created');
            } elseif ($action === 'update' && $id) {
                db_update('building_elements', $data, 'id = :id', ['id' => $id]);
                log_activity('element_updated', 'building_element', $id);
                if (defined('AJAX_REQUEST')) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Bygningsdel opdateret']);
                    exit;
                }
                redirect('/?module=building_element&success=updated');
            }
        } catch (Exception $e) {
            log_error('Element save error: ' . $e->getMessage());
            $errors[] = 'Lagringsfejl';
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
        $childCount = db_value("SELECT COUNT(*) FROM building_elements WHERE parent_id = :id", ['id' => $id]);
        if ($childCount > 0) {
            $error = "Kan ikke slette element med $childCount underordnet(e) element(er)";
            if (defined('AJAX_REQUEST')) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }
            redirect('/?module=building_element&error=' . urlencode($error));
        }

        db_delete('building_elements', 'id = :id', ['id' => $id]);
        log_activity('element_deleted', 'building_element', $id);

        if (defined('AJAX_REQUEST')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Bygningsdel slettet']);
            exit;
        }
        redirect('/?module=building_element&success=deleted');
    } catch (Exception $e) {
        log_error('Element delete error: ' . $e->getMessage());
        if (defined('AJAX_REQUEST')) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Sletningsfejl']);
            exit;
        }
        redirect('/?module=building_element&error=Sletningsfejl');
    }
}

// GET single
if ($action === 'get' && $id) {
    $element = db_fetch("SELECT be.*, b.name as building_label, p.name as parent_label, pc.name as price_catalog_label FROM building_elements be LEFT JOIN buildings b ON be.building_id = b.id LEFT JOIN building_elements p ON be.parent_id = p.id LEFT JOIN price_catalogs pc ON be.price_catalog_id = pc.id WHERE be.id = :id", ['id' => $id]);
    if ($element) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $element]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Element ikke fundet']);
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
    $whereClauses[] = "(" . db_ilike('be.name', ':search') . " OR " . db_ilike('be.location', ':search') . " OR " . db_ilike('b.name', ':search') . ")";
    $params['search'] = '%' . $searchTerm . '%';
}

if ($buildingId) {
    $whereClauses[] = "be.building_id = :building_id";
    $params['building_id'] = $buildingId;
}

$where = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$totalElements = db_value("SELECT COUNT(*) FROM building_elements be LEFT JOIN buildings b ON be.building_id = b.id $where", $params);
$totalPages = ceil($totalElements / $perPage);

$elements = db_query("
    SELECT be.*, b.name as building_name, p.name as parent_name, pc.name as price_catalog_name
    FROM building_elements be
    LEFT JOIN buildings b ON be.building_id = b.id
    LEFT JOIN building_elements p ON be.parent_id = p.id
    LEFT JOIN price_catalogs pc ON be.price_catalog_id = pc.id
    $where
    ORDER BY b.name, COALESCE(be.sort_order, 999999), be.name
    LIMIT :limit OFFSET :offset
", array_merge($params, ['limit' => $perPage, 'offset' => $offset]));

$successMessage = '';
$errorMessage = '';

if (isset($_GET['success'])) {
    $successMessage = match($_GET['success']) {
        'created' => 'Bygningsdel oprettet succesfuldt',
        'updated' => 'Bygningsdel opdateret succesfuldt',
        'deleted' => 'Bygningsdel slettet succesfuldt',
        default => ''
    };
}

if (isset($_GET['error'])) {
    $errorMessage = $_GET['error'];
}

$permissions = get_user_permissions(current_user()['id']);

load_template(template_path('building_element', 'template'), compact('elements', 'totalElements', 'page', 'totalPages', 'searchTerm', 'buildingId', 'successMessage', 'errorMessage', 'permissions'));
