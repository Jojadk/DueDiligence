<?php
/**
 * Customer Module
 * CRUD operations for customers
 */

// Get action
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? null;

    // Validate form data
    $errors = validate_form('customer', $_POST);

    if (empty($errors)) {
        // Prepare data
        $data = [
            'name' => sanitize_string($_POST['name']),
            'cvr_number' => sanitize_string($_POST['cvr_number'] ?? ''),
            'contact_person' => sanitize_string($_POST['contact_person'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? ''),
            'phone' => sanitize_string($_POST['phone'] ?? ''),
            'address' => sanitize_string($_POST['address'] ?? ''),
            'postal_code' => sanitize_string($_POST['postal_code'] ?? ''),
            'city' => sanitize_string($_POST['city'] ?? ''),
            'notes' => sanitize_string($_POST['notes'] ?? '')
        ];

        try {
            if ($action === 'create') {
                // Insert new customer
                $customerId = db_insert('customers', $data);

                log_activity('customer_created', 'customer', $customerId);

                // Return JSON response for AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $customerId, 'message' => 'Kunde oprettet']);
                    exit;
                }

                redirect('/?module=customer&success=created');

            } elseif ($action === 'update' && $id) {
                // Update existing customer
                db_update('customers', $data, 'id = :id', ['id' => $id]);

                log_activity('customer_updated', 'customer', $id);

                // Return JSON response for AJAX
                if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'id' => $id, 'message' => 'Kunde opdateret']);
                    exit;
                }

                redirect('/?module=customer&success=updated');
            }
        } catch (Exception $e) {
            log_error('Customer save error: ' . $e->getMessage());
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
        // Check if customer has projects
        $projectCount = db_value("SELECT COUNT(*) FROM projects WHERE customer_id = :id", ['id' => $id]);

        if ($projectCount > 0) {
            $error = "Kan ikke slette kunde med $projectCount projekt(er)";

            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $error]);
                exit;
            }

            redirect('/?module=customer&error=' . urlencode($error));
        }

        db_delete('customers', 'id = :id', ['id' => $id]);

        log_activity('customer_deleted', 'customer', $id);

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Kunde slettet']);
            exit;
        }

        redirect('/?module=customer&success=deleted');

    } catch (Exception $e) {
        log_error('Customer delete error: ' . $e->getMessage());
        $error = 'Der opstod en fejl ved sletning';

        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error]);
            exit;
        }

        redirect('/?module=customer&error=' . urlencode($error));
    }
}

// Handle get single customer (for AJAX edit)
if ($action === 'get' && $id) {
    $customer = db_fetch("SELECT * FROM customers WHERE id = :id", ['id' => $id]);

    if ($customer) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'data' => $customer]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Kunde ikke fundet']);
    }
    exit;
}

// Get list of customers
$searchTerm = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

$whereClauses = [];
$params = [];

if ($searchTerm) {
    $whereClauses[] = "(name ILIKE :search OR cvr_number ILIKE :search OR contact_person ILIKE :search OR email ILIKE :search)";
    $params['search'] = '%' . $searchTerm . '%';
}

$where = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Get total count
$totalCustomers = db_value("SELECT COUNT(*) FROM customers $where", $params);
$totalPages = ceil($totalCustomers / $perPage);

// Get customers
$customers = db_query("
    SELECT
        c.*,
        COUNT(DISTINCT p.id) as project_count
    FROM customers c
    LEFT JOIN projects p ON c.id = p.customer_id
    $where
    GROUP BY c.id
    ORDER BY c.name ASC
    LIMIT :limit OFFSET :offset
", array_merge($params, ['limit' => $perPage, 'offset' => $offset]));

// Success/error messages
$successMessage = '';
$errorMessage = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $successMessage = 'Kunde oprettet succesfuldt';
            break;
        case 'updated':
            $successMessage = 'Kunde opdateret succesfuldt';
            break;
        case 'deleted':
            $successMessage = 'Kunde slettet succesfuldt';
            break;
    }
}

if (isset($_GET['error'])) {
    $errorMessage = $_GET['error'];
}

// Load template
load_template(template_path('customer', 'template'), [
    'customers' => $customers,
    'totalCustomers' => $totalCustomers,
    'page' => $page,
    'totalPages' => $totalPages,
    'searchTerm' => $searchTerm,
    'successMessage' => $successMessage,
    'errorMessage' => $errorMessage
]);
