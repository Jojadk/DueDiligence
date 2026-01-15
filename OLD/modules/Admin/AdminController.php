<?php
namespace Modules\Admin;

use Core\Controller;
use Core\Auth;

class AdminController extends Controller
{
    // Require admin permission for all actions
    protected $requiredPermission = 'admin_access';

    public function __construct()
    {
        parent::__construct();
        // Additional admin-specific setup can go here
    }

    public function index()
    {
        // Show Admin Dashboard / Menu
        $this->view('Admin/index');
    }

    public function users()
    {
        $db = \Core\Database::getInstance();
        $db->query("SELECT u.*, r.name as role_name 
                    FROM users u 
                    LEFT JOIN user_roles ur ON u.id = ur.user_id 
                    LEFT JOIN roles r ON ur.role_id = r.id 
                    ORDER BY u.created_at DESC");
        $users = $db->resultSet();
        $this->view('Admin/users_index', ['users' => $users]);
    }

    public function customfields()
    {
        $db = \Core\Database::getInstance();
        $db->query("SELECT * FROM custom_field_definitions WHERE deleted_at IS NULL ORDER BY entity_type, created_at DESC");
        $fields = $db->resultSet();
        $this->view('Admin/custom_fields_index', ['fields' => $fields]);
    }

    public function constants()
    {
        $db = \Core\Database::getInstance();
        $db->query("SELECT * FROM system_constants ORDER BY key_name ASC");
        $constants = $db->resultSet();
        $this->view('Admin/constants_index', ['constants' => $constants]);
    }

    public function search()
    {
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) {
            $this->json([]);
            exit;
        }

        $db = \Core\Database::getInstance();
        $db->query("SELECT id, username, email FROM users WHERE username LIKE :q OR email LIKE :q LIMIT 10");
        $db->bind(':q', "%$q%");
        $users = $db->resultSet();

        $this->json($users);
        exit;
    }

    public function create()
    {
        $db = \Core\Database::getInstance();
        $db->query("SELECT * FROM roles ORDER BY name ASC");
        $roles = $db->resultSet();
        $this->view('Admin/users_form', ['roles' => $roles, 'user' => null]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $role_id = $_POST['role_id'];

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $db = \Core\Database::getInstance();
            try {
                $db->query("INSERT INTO users (username, email, password_hash) VALUES (:name, :email, :pass)");
                $db->bind(':name', $username);
                $db->bind(':email', $email);
                $db->bind(':pass', $password_hash);
                $db->execute();

                $userId = $db->lastInsertId();

                if ($role_id) {
                    $db->query("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
                    $db->bind(':uid', $userId);
                    $db->bind(':rid', $role_id);
                    $db->execute();
                }

                $this->redirect('?module=Admin&action=users');
            } catch (\Exception $e) {
                echo "Error: " . $e->getMessage();
            }
        }
    }

    public function edit()
    {
        $id = $_GET['id'] ?? 0;
        $db = \Core\Database::getInstance();

        // Fetch user
        $db->query("SELECT * FROM users WHERE id = :id");
        $db->bind(':id', $id);
        $user = $db->single();

        if (!$user) {
            $this->redirect('?module=Admin&action=users');
        }

        // Fetch current role
        $db->query("SELECT role_id FROM user_roles WHERE user_id = :id");
        $db->bind(':id', $id);
        $role = $db->single();
        $user['role_id'] = $role ? $role['role_id'] : null;

        // Fetch all roles
        $db->query("SELECT * FROM roles ORDER BY name ASC");
        $roles = $db->resultSet();

        $this->view('Admin/users_form', ['user' => $user, 'roles' => $roles]);
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $username = $_POST['username'];
            $email = $_POST['email'];
            $role_id = $_POST['role_id'];
            $password = $_POST['password'];

            $db = \Core\Database::getInstance();

            // Update User details
            $sql = "UPDATE users SET username = :name, email = :email";
            if (!empty($password)) {
                $sql .= ", password_hash = :pass";
            }
            $sql .= " WHERE id = :id";

            $db->query($sql);
            $db->bind(':name', $username);
            $db->bind(':email', $email);
            $db->bind(':id', $id);
            if (!empty($password)) {
                $db->bind(':pass', password_hash($password, PASSWORD_DEFAULT));
            }
            $db->execute();

            // Update Role
            $db->query("DELETE FROM user_roles WHERE user_id = :id");
            $db->bind(':id', $id);
            $db->execute();

            if ($role_id) {
                $db->query("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
                $db->bind(':uid', $id);
                $db->bind(':rid', $role_id);
                $db->execute();
            }

            $this->redirect('?module=Admin&action=users');
        }
    }

    public function delete()
    {
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $db = \Core\Database::getInstance();
            $db->query("DELETE FROM users WHERE id = :id");
            $db->bind(':id', $id);
            $db->execute();
        }
        $this->redirect('?module=Admin&action=users');
    }

    public function searchClients()
    {
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 1) {
            $this->json([]);
            exit;
        }

        $db = \Core\Database::getInstance();

        // Search in new customers table
        // We also check existing project client_names for backward compatibility or migration?
        // User requested Customer Module, so let's check 'customers' table.
        // If table doesn't exist yet (migration fail), we might fall back?
        // Let's assume migration will run.

        try {
            $db->query("SELECT id, name FROM customers WHERE name LIKE :q LIMIT 10");
            $db->bind(':q', "%$q%");
            $clients = $db->resultSet();

            // If empty, maybe fallback to searching projects distinct client_name?
            if (empty($clients)) {
                $db->query("SELECT DISTINCT client_name as name FROM projects WHERE client_name LIKE :q LIMIT 10");
                $db->bind(':q', "%$q%");
                $clients = $db->resultSet();
            }

            $this->json($clients);
        } catch (\Exception $e) {
            // Fallback if table missing
            $db->query("SELECT DISTINCT client_name as name FROM projects WHERE client_name LIKE :q LIMIT 10");
            $db->bind(':q', "%$q%");
            $clients = $db->resultSet();
            $this->json($clients);
        }
        exit;
    }

    public function clearCache()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Verify CSRF
            if (!isset($_POST['_csrf']) || $_POST['_csrf'] !== ($_SESSION['csrf_token'] ?? '')) {
                die("CSRF Validation Failed");
            }

            \Core\Asset::clearCache();

            // Redirect back with success message (could be improved with a flash message system later)
            header('Location: ?module=Admin&msg=Cache+Cleared');
            exit;
        }
        $this->redirect('?module=Admin');
    }


}
