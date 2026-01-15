<?php
namespace Modules\Admin;

use Core\Controller;
use Core\Database;
use Core\Auth;

class CustomFieldController extends Controller
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
        $db->query("SELECT * FROM custom_field_definitions ORDER BY entity_type, sort_order ASC");
        $fields = $db->resultSet();
        $this->view('Admin/custom_fields_index', ['fields' => $fields]);
    }

    public function create()
    {
        $this->view('Admin/custom_fields_form', ['field' => null]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $scope = $_POST['scope'];
            $projectId = !empty($_POST['project_id']) ? $_POST['project_id'] : null;
            $entity = $_POST['entity_type'];
            $label = $_POST['label'];
            $type = $_POST['field_type'];
            $options = $_POST['options']; // JSON or CSV
            $sort = $_POST['sort_order'];

            $db = Database::getInstance();
            $db->query("INSERT INTO custom_field_definitions (scope, project_id, entity_type, label, field_type, options, sort_order) 
                        VALUES (:scope, :pid, :entity, :label, :type, :opts, :sort)");
            $db->bind(':scope', $scope);
            $db->bind(':pid', $projectId);
            $db->bind(':entity', $entity);
            $db->bind(':label', $label);
            $db->bind(':type', $type);
            $db->bind(':opts', $options);
            $db->bind(':sort', $sort);
            $db->execute();

            $this->redirect('?module=CustomField&action=index');
        }
    }

    // Add Edit/Delete actions in real implementation...
    public function delete()
    {
        $id = $_GET['id'];
        $db = Database::getInstance();
        $db->query("DELETE FROM custom_field_definitions WHERE id = :id");
        $db->bind(':id', $id);
        $db->execute();
        $this->redirect('?module=CustomField&action=index');
    }
}
