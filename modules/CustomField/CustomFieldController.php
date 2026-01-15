<?php
namespace Modules\CustomField;

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
        $this->redirect('?module=Admin&action=customfields');
    }

    public function create()
    {
        // View for creating a new custom field
        $db = Database::getInstance();
        $db->query("SELECT id, name FROM projects ORDER BY name ASC");
        $projects = $db->resultSet();

        $this->view('CustomField/create', ['projects' => $projects]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $db->query("INSERT INTO custom_field_definitions (label, field_type, entity_type, scope, project_id, options) 
                        VALUES (:label, :type, :entity, :scope, :pid, :options)");
            $db->bind(':label', $_POST['label']);
            $db->bind(':type', $_POST['field_type']);
            $db->bind(':entity', 'building_element'); // Default or dynamic
            $db->bind(':scope', $_POST['scope']);
            $db->bind(':pid', !empty($_POST['project_id']) ? $_POST['project_id'] : null);
            $db->bind(':options', $_POST['options'] ?? null); // JSON stored as text

            $db->execute();
        }
        $this->redirect('?module=Admin&action=customfields');
    }

    public function edit()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->redirect('?module=Admin&action=customfields');
        }

        $db = Database::getInstance();

        // Fetch field
        $db->query("SELECT * FROM custom_field_definitions WHERE id = :id");
        $db->bind(':id', $id);
        $field = $db->single();

        if (!$field) {
            $this->redirect('?module=Admin&action=customfields');
        }

        // Fetch projects
        $db->query("SELECT id, name FROM projects ORDER BY name ASC");
        $projects = $db->resultSet();

        $this->view('CustomField/edit', ['field' => $field, 'projects' => $projects]);
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $db = Database::getInstance();

            // Allow updating Label, Options, Scope, Project, Field Type
            // Note: Changing Field Type might affect display of existing values but won't delete them (stored as text/numeric columns).

            $db->query("UPDATE custom_field_definitions 
                        SET label = :label, 
                            field_type = :type, 
                            scope = :scope, 
                            project_id = :pid, 
                            options = :options, 
                            updated_at = NOW() 
                        WHERE id = :id");

            $db->bind(':label', $_POST['label']);
            $db->bind(':type', $_POST['field_type']);
            $db->bind(':scope', $_POST['scope']);
            $db->bind(':pid', !empty($_POST['project_id']) ? $_POST['project_id'] : null);
            $db->bind(':options', $_POST['options'] ?? null);
            $db->bind(':id', $id);
            $db->execute();
        }
        $this->redirect('?module=Admin&action=customfields');
    }

    public function delete()
    {
        if (isset($_GET['id'])) {
            $db = Database::getInstance();
            // Soft Delete implementation
            $db->query("UPDATE custom_field_definitions SET deleted_at = NOW() WHERE id = :id");
            $db->bind(':id', $_GET['id']);
            $db->execute();
        }
        $this->redirect('?module=Admin&action=customfields');
    }
}
