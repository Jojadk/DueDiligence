<?php
namespace Modules\Customer;

use Core\Controller;
use Core\Database;

class CustomerController extends Controller
{
    public function index()
    {
        $db = Database::getInstance();
        $db->query("SELECT * FROM customers WHERE deleted_at IS NULL ORDER BY name ASC");
        $customers = $db->resultSet();

        $this->view('Customer/index', ['customers' => $customers]);
    }

    public function create()
    {
        // For partial view (AJAX modal)
        if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
            $customer = null;
            include MODULES_DIR . '/Customer/CustomerForm.php';
            exit;
        }

        // Full page view
        $this->view('Customer/CustomerForm', ['customer' => null]);
    }

    public function store()
    {
        $name = $_POST['name'] ?? '';

        if (strlen($name) < 2) {
            $this->json(['status' => 'error', 'message' => 'Name too short']);
            exit;
        }

        $db = Database::getInstance();
        $db->query("INSERT INTO customers (name, email, phone, address) VALUES (:name, :email, :phone, :address)");
        $db->bind(':name', $name);
        $db->bind(':email', $_POST['email'] ?? '');
        $db->bind(':phone', $_POST['phone'] ?? '');
        $db->bind(':address', $_POST['address'] ?? '');

        if ($db->execute()) {
            $this->json(['status' => 'success']);
        } else {
            $this->json(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    public function edit()
    {
        $id = $_GET['id'] ?? 0;
        $db = Database::getInstance();
        $db->query("SELECT * FROM customers WHERE id = :id");
        $db->bind(':id', $id);
        $customer = $db->single();

        // Fetch Projects
        $db->query("SELECT * FROM projects WHERE client_id = :id OR client_name = :name ORDER BY created_at DESC");
        $db->bind(':id', $id);
        $db->bind(':name', $customer['name'] ?? '');
        $projects = $db->resultSet();
        $customer['projects'] = $projects;

        if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
            include MODULES_DIR . '/Customer/CustomerForm.php';
            exit;
        }

        $this->view('Customer/CustomerForm', ['customer' => $customer]);
    }

    public function update()
    {
        $id = $_POST['id'] ?? 0;
        $name = $_POST['name'] ?? '';

        if (strlen($name) < 2) {
            $this->json(['status' => 'error', 'message' => 'Name too short']);
            exit;
        }

        $db = Database::getInstance();
        $db->query("UPDATE customers SET name=:name, email=:email, phone=:phone, address=:address, updated_at=NOW() WHERE id=:id");
        $db->bind(':id', $id);
        $db->bind(':name', $name);
        $db->bind(':email', $_POST['email'] ?? '');
        $db->bind(':phone', $_POST['phone'] ?? '');
        $db->bind(':address', $_POST['address'] ?? '');

        if ($db->execute()) {
            $this->json(['status' => 'success']);
        } else {
            $this->json(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }

    public function delete()
    {
        if (!\Core\Auth::hasPermission('customer_delete')) {
            $this->json(['status' => 'error', 'message' => 'Access Denied']);
            exit;
        }

        $id = $_POST['id'] ?? 0;
        $db = Database::getInstance();
        // Soft delete
        $db->query("UPDATE customers SET deleted_at=NOW() WHERE id=:id");
        $db->bind(':id', $id);

        if ($db->execute()) {
            $this->json(['status' => 'success']);
        } else {
            $this->json(['status' => 'error', 'message' => 'Database error']);
        }
        exit;
    }
}
