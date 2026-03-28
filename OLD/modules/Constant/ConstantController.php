<?php
namespace Modules\Constant;

use Core\Controller;
use Core\Database;
use Core\Auth;

class ConstantController extends Controller
{
    public function __construct()
    {
        if (!Auth::check() || !Auth::hasPermission('admin_access')) {
            $this->redirect('?module=Auth');
        }
    }

    public function index()
    {
        // Redirect to Admin controller's list view to keep UI consistent
        $this->redirect('?module=Admin&action=constants');
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $db->query("INSERT INTO system_constants (key_name, value, description) VALUES (:key, :val, :desc)");
            $db->bind(':key', $_POST['key_name']);
            $db->bind(':val', $_POST['value']);
            $db->bind(':desc', $_POST['description']);

            if ($db->execute()) {
                // Success
            }
        }
        $this->redirect('?module=Admin&action=constants');
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $db->query("UPDATE system_constants SET value = :val, description = :desc WHERE id = :id");
            $db->bind(':val', $_POST['value']);
            $db->bind(':desc', $_POST['description']);
            $db->bind(':id', $_POST['id']);
            $db->execute();
        }
        $this->redirect('?module=Admin&action=constants');
    }
}
