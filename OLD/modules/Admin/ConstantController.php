<?php
namespace Modules\Admin;

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
        $db = Database::getInstance();
        $db->query("SELECT * FROM system_constants WHERE project_id IS NULL");
        $constants = $db->resultSet();
        $this->view('Admin/constants_index', ['constants' => $constants]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $key = strtoupper($_POST['key_name']);
            $val = $_POST['value'];
            $desc = $_POST['description'];

            $db = Database::getInstance();
            $db->query("INSERT INTO system_constants (key_name, value, description) VALUES (:key, :val, :desc)");
            $db->bind(':key', $key);
            $db->bind(':val', $val);
            $db->bind(':desc', $desc);
            $db->execute();

            $this->redirect('?module=Constant&action=index'); // Assuming route mapping or use Admin&action=constants if preferred
        }
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $val = $_POST['value'];
            $desc = $_POST['description'];

            $db = Database::getInstance();
            $db->query("UPDATE system_constants SET value = :val, description = :desc WHERE id = :id");
            $db->bind(':val', $val);
            $db->bind(':desc', $desc);
            $db->bind(':id', $id);
            $db->execute();

            $this->redirect('?module=Constant&action=index');
        }
    }
}
