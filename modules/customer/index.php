<?php
/**
 * Customer Module - Refactored
 * CRUD operations for customers using API helpers
 */

require_once __DIR__ . '/../../core/api-helpers.php';

// Helper function to check if request is AJAX
function is_ajax_request(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Helper function to send JSON response
function json_response(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Get action
$action = $_GET['action'] ?? 'list';
$id = $_GET['id'] ?? null;

// ============================================
// HANDLE FORM SUBMISSIONS (CREATE/UPDATE)
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF
    if ($error = api_require_csrf()) {
        if (is_ajax_request()) {
            json_response($error);
        }
        redirect('/?module=customer&error=' . urlencode($error['error']));
    }

    $action = $_POST['action'] ?? 'create';
    $id = $_POST['id'] ?? null;

    // Validate and sanitize all parameters
    $validation = api_validate_params([
        'name' => ['string', 'POST', true],
        'cvr_number' => ['string', 'POST', false, ''],
        'contact_person' => ['string', 'POST', false, ''],
        'email' => ['email', 'POST', false, ''],
        'phone' => ['string', 'POST', false, ''],
        'address' => ['string', 'POST', false, ''],
        'postal_code' => ['string', 'POST', false, ''],
        'city' => ['string', 'POST', false, ''],
        'notes' => ['string', 'POST', false, '']
    ]);

    if (!$validation['success']) {
        if (is_ajax_request()) {
            json_response(api_error($validation['errors']));
        }

        redirect('/?module=customer&error=' . urlencode(implode(', ', $validation['errors'])));
    }

    $data = $validation['data'];

    // CREATE
    if ($action === 'create') {
        $result = api_crud_create(
            'customers',
            $data,
            null, // No before insert callback needed
            function($customerId) {
                log_activity('customer_created', 'customer', $customerId);
            }
        );

        if (is_ajax_request()) {
            json_response($result);
        }

        if ($result['success']) {
            redirect('/?module=customer&success=created');
        } else {
            redirect('/?module=customer&error=' . urlencode($result['error']));
        }
    }

    // UPDATE
    elseif ($action === 'update' && $id) {
        $result = api_crud_update(
            'customers',
            $id,
            $data,
            null, // No permission check needed (handled by base system)
            function($customerId) {
                log_activity('customer_updated', 'customer', $customerId);
            }
        );

        if (is_ajax_request()) {
            json_response($result);
        }

        if ($result['success']) {
            redirect('/?module=customer&success=updated');
        } else {
            redirect('/?module=customer&error=' . urlencode($result['error']));
        }
    }
}

// ============================================
// HANDLE DELETE
// ============================================

if ($action === 'delete' && $id) {
    if ($error = api_require_csrf()) {
        if (is_ajax_request()) {
            json_response($error);
        }
        redirect('/?module=customer&error=' . urlencode($error['error']));
    }

    $result = api_crud_delete(
        'customers',
        $id,
        null, // No permission check
        function($customerId) {
            // Check if customer has projects (before delete callback)
            $projectCount = db_value(
                "SELECT COUNT(*) FROM projects WHERE customer_id = :id",
                ['id' => $customerId]
            );

            if ($projectCount > 0) {
                return api_error("Kan ikke slette kunde med $projectCount projekt(er)");
            }

            return ['success' => true];
        }
    );

    if (is_ajax_request()) {
        json_response($result);
    }

    if ($result['success']) {
        redirect('/?module=customer&success=deleted');
    } else {
        redirect('/?module=customer&error=' . urlencode($result['error']));
    }
}

// ============================================
// HANDLE GET SINGLE CUSTOMER (AJAX)
// ============================================

if ($action === 'get' && $id) {
    $result = api_get_entity(
        'customers',
        $id,
        $currentUser,
        null, // No permission check
        'Kunde ikke fundet'
    );

    if ($result['success']) {
        json_response(api_success(['data' => $result['data']]));
    } else {
        json_response($result);
    }
}

// ============================================
// GET LIST OF CUSTOMERS WITH PAGINATION
// ============================================

$searchTerm = sanitize_string($_GET['search'] ?? '');
$page = max(1, sanitize_int($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Build query with filters
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

// Get customers with project count
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
    $successMessages = [
        'created' => 'Kunde oprettet succesfuldt',
        'updated' => 'Kunde opdateret succesfuldt',
        'deleted' => 'Kunde slettet succesfuldt'
    ];
    $successMessage = $successMessages[$_GET['success']] ?? '';
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
