<?php
namespace Modules\Admin;

use Core\Controller;
use Core\Database;
use Core\Auth;

class UserController extends Controller
{

    public function __construct()
    {
        if (!Auth::check() || !Auth::hasPermission('admin_access')) {
            $this->redirect('?module=Auth');
        }
    }

    public function index()
    {
        $db = Database::getInstance();
        $db->query("SELECT u.*, r.name as role_name 
                    FROM users u 
                    LEFT JOIN user_roles ur ON u.id = ur.user_id 
                    LEFT JOIN roles r ON ur.role_id = r.id 
                    ORDER BY u.created_at DESC");
        $users = $db->resultSet();

        $this->view('Admin/users_index', ['users' => $users]);
    }

    public function create()
    {
        // Get roles for dropdown
        $db = Database::getInstance();
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

            $db = Database::getInstance();
            try {
                $db->query("INSERT INTO users (username, email, password_hash) VALUES (:name, :email, :pass)");
                $db->bind(':name', $username);
                $db->bind(':email', $email);
                $db->bind(':pass', $password_hash);
                $db->execute();

                $userId = $db->lastInsertId();

                // Assign role
                $db->query("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
                $db->bind(':uid', $userId);
                $db->bind(':rid', $role_id);
                $db->execute();

                $this->redirect('?module=Admin&action=users');
            } catch (\Exception $e) {
                // Handle duplicate entry etc.
                echo "Error: " . $e->getMessage();
            }
        }
    }

    public function edit()
    {
        $id = $_GET['id'] ?? 0;
        $db = Database::getInstance();

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
            $password = $_POST['password']; // Optional

            $db = Database::getInstance();

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

            // Update Role (Delete old, Insert new - simplistic)
            // Or Update if exists.
            $db->query("DELETE FROM user_roles WHERE user_id = :id");
            $db->bind(':id', $id);
            $db->execute();

            $db->query("INSERT INTO user_roles (user_id, role_id) VALUES (:uid, :rid)");
            $db->bind(':uid', $id);
            $db->bind(':rid', $role_id);
            $db->execute();

            $this->redirect('?module=Admin&action=users');
        }
    }

    public function delete()
    {
        $id = $_GET['id'] ?? 0;
        if ($id) {
            $db = Database::getInstance();
            $db->query("DELETE FROM users WHERE id = :id");
            $db->bind(':id', $id);
            $db->execute();
        }
        $this->redirect('?module=Admin&action=users');
    }

    // Alias for menu action 'users' -> index
    public function users()
    {
        $this->index();
    }
    // AJAX Search for Project Team
    public function search()
    {
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) {
            $this->json([]);
        }

        $db = Database::getInstance();
        $db->query("SELECT id, username, email FROM users WHERE username LIKE :q OR email LIKE :q LIMIT 10");
        $db->bind(':q', "%$q%");
        $users = $db->resultSet();


        $this->json($users);
        exit;
    }

    public function searchClients()
    {
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 2) {
            $this->json([]);
            exit;
        }

        $db = Database::getInstance();
        $db->query("SELECT DISTINCT client_name FROM projects WHERE client_name LIKE :q ORDER BY client_name ASC LIMIT 10");
        $db->bind(':q', "%$q%");
        $clients = $db->resultSet();

        // Reformat for frontend
        $data = array_map(function ($c) {
            return ['name' => $c['client_name']]; }, $clients);

        $this->json($data);
        exit;
    }
}
