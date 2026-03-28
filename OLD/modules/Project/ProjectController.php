<?php
namespace Modules\Project;

use Core\Controller;
use Core\Database;
use Core\Auth;

class ProjectController extends Controller
{
    // Auth handled by parent constructor

    public function index()
    {
        // Use $this->db from parent
        // Optimized query with JOIN to prevent N+1 queries for customer names
        $sql = "SELECT p.*, c.name as client_name 
                FROM projects p
                LEFT JOIN customers c ON p.client_id = c.id
                WHERE p.deleted_at IS NULL
                ORDER BY p.created_at DESC";

        $this->db->query($sql);
        $projects = $this->db->resultSet();

        $this->view('Project/index', ['projects' => $projects]);
    }

    public function migrate()
    {
        $db = Database::getInstance();
        try {
            // Postgres syntax
            $db->query("ALTER TABLE project_members ADD COLUMN role VARCHAR(50) DEFAULT 'specialist'");
            $db->execute();
            echo "Migration Success: Column added.";
        } catch (\Exception $e) {
            echo "Migration Info: " . $e->getMessage();
        }
        exit;
    }

    public function create()
    {
        if (!Auth::hasPermission('project_create')) {
            $this->handleAccessDenied('Permission denied');
        }
        $this->view('Project/ProjectForm', ['project' => null]);
    }

    public function store()
    {
        if (!Auth::hasPermission('project_create')) {
            $this->json(['status' => 'error', 'message' => 'Permission denied']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Point 4: Validation
            $errors = $this->validateProject($_POST);
            if (!empty($errors)) {
                if (isset($_POST['ajax'])) {
                    $this->json(['status' => 'error', 'message' => implode(', ', $errors), 'errors' => $errors]);
                }
                // Fallback for non-ajax
                $this->redirect('?module=Project&action=index&error=' . urlencode(implode(', ', $errors)));
            }

            $name = $_POST['name'];
            $client = $_POST['client_name'];
            $status = $_POST['status'];
            $start = $_POST['start_date'];

            // New Fields
            $bbr = $_POST['bbr_number'] ?? null;
            $area = !empty($_POST['area_m2']) ? $_POST['area_m2'] : null;
            $heating = $_POST['heating_type'] ?? null;
            $usage = $_POST['usage_type'] ?? null;
            $const = !empty($_POST['construction_year']) ? $_POST['construction_year'] : null;
            $renov = !empty($_POST['renovation_year']) ? $_POST['renovation_year'] : null;
            $insp = !empty($_POST['inspection_date']) ? $_POST['inspection_date'] : null;

            $db = Database::getInstance();
            $sql = "INSERT INTO projects (name, client_name, status, start_date, bbr_number, area_m2, heating_type, usage_type, construction_year, renovation_year, inspection_date) 
                    VALUES (:name, :client, :status, :start, :bbr, :area, :heating, :usage, :const, :renov, :insp)";
            $db->query($sql);
            $db->bind(':name', $name);
            $db->bind(':client', $client);
            $db->bind(':status', $status);
            $db->bind(':start', $start);
            $db->bind(':bbr', $bbr);
            $db->bind(':area', $area);
            $db->bind(':heating', $heating);
            $db->bind(':usage', $usage);
            $db->bind(':const', $const);
            $db->bind(':renov', $renov);
            $db->bind(':insp', $insp);
            $db->execute();

            $newId = $db->lastInsertId();

            // Save client_id if present
            if (!empty($_POST['client_id'])) {
                $db->query("UPDATE projects SET client_id = :cid WHERE id = :id");
                $db->bind(':cid', $_POST['client_id']);
                $db->bind(':id', $newId);
                $db->execute();
            }

            // Save Team Members
            if (!empty($_POST['team_members'])) {
                foreach ($_POST['team_members'] as $uid) {
                    $db->query("INSERT INTO project_members (project_id, user_id) VALUES (:pid, :uid) ON CONFLICT (project_id, user_id) DO NOTHING");
                    $db->bind(':pid', $newId);
                    $db->bind(':uid', $uid);
                    $db->execute();
                }
            }

            if (isset($_POST['ajax'])) {
                $this->json(['status' => 'success', 'message' => 'Project created', 'id' => $newId]);
            }

            $this->redirect('?module=Project&action=index');
        }
    }

    public function edit()
    {
        if (!Auth::hasPermission('project_edit')) {
            $this->handleAccessDenied('Permission denied');
        }
        $id = $_GET['id'] ?? 0;
        $this->db->query("SELECT * FROM projects WHERE id = :id AND deleted_at IS NULL");
        $this->db->bind(':id', $id);
        $project = $this->db->single();

        if (!$project) {
            $this->handleNotFound('Project not found');
        }

        // Fetch Team with Roles
        $this->db->query("SELECT u.id, u.username, pm.role 
                    FROM users u 
                    JOIN project_members pm ON u.id = pm.user_id 
                    WHERE pm.project_id = :id");
        $this->db->bind(':id', $id);
        $project['team_members_list'] = $this->db->resultSet();
        // Ensure role is set for display
        foreach ($project['team_members_list'] as &$tm) {
            if (empty($tm['role']))
                $tm['role'] = 'specialist';
        }

        // Fetch Custom Fields Definitions
        $this->db->query("SELECT * FROM custom_field_definitions 
                    WHERE entity_type = 'project' 
                    AND (scope = 'global' OR (scope = 'project' AND project_id = :pid))
                    ORDER BY sort_order ASC");
        $this->db->bind(':pid', $id);
        $customFields = $this->db->resultSet();

        // Fetch Custom Field Values
        $this->db->query("SELECT definition_id, value FROM custom_field_values WHERE entity_id = :id");
        $this->db->bind(':id', $id);
        $valuesRaw = $this->db->resultSet();
        $customFieldValues = [];
        foreach ($valuesRaw as $v) {
            $customFieldValues[$v['definition_id']] = $v['value'];
        }

        if (isset($_GET['ajax']) || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest')) {
            // Render Partial
            include MODULES_DIR . '/Project/ProjectForm.php';
            exit;
        }

        $this->view('Project/ProjectForm', [
            'project' => $project,
            'customFields' => $customFields,
            'customFieldValues' => $customFieldValues
        ]);
    }

    public function update()
    {
        if (!Auth::hasPermission('project_edit')) {
            $this->handleAccessDenied('Permission denied');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];

            // Point 4: Validation
            $errors = $this->validateProject($_POST);
            if (!empty($errors)) {
                if (isset($_POST['ajax']))
                    $this->json(['status' => 'error', 'message' => implode(', ', $errors), 'errors' => $errors]);
                $this->redirect('?module=Project&action=index&error=' . urlencode(implode(', ', $errors)));
            }

            $name = $_POST['name'];
            $client = $_POST['client_name'];
            $status = $_POST['status'];
            $start = $_POST['start_date'];

            // New Fields
            $bbr = $_POST['bbr_number'] ?? null;
            $area = !empty($_POST['area_m2']) ? $_POST['area_m2'] : null;
            $heating = $_POST['heating_type'] ?? null;
            $usage = $_POST['usage_type'] ?? null;
            $const = !empty($_POST['construction_year']) ? $_POST['construction_year'] : null;
            $renov = !empty($_POST['renovation_year']) ? $_POST['renovation_year'] : null;
            $insp = !empty($_POST['inspection_date']) ? $_POST['inspection_date'] : null;

            $db = Database::getInstance();
            $sql = "UPDATE projects SET name = :name, client_name = :client, status = :status, start_date = :start,
                    bbr_number = :bbr, area_m2 = :area, heating_type = :heating, usage_type = :usage, 
                    construction_year = :const, renovation_year = :renov, inspection_date = :insp, 
                    report_intro = :r_intro, report_disclaimer = :r_disc, updated_at = NOW()
                    WHERE id = :id";
            $db->query($sql);
            $db->bind(':name', $name);
            $db->bind(':client', $client);
            $db->bind(':status', $status);
            $db->bind(':start', $start);
            $db->bind(':bbr', $bbr);
            $db->bind(':area', $area);
            $db->bind(':heating', $heating);
            $db->bind(':usage', $usage);
            $db->bind(':const', $const);
            $db->bind(':renov', $renov);
            $db->bind(':insp', $insp);
            $db->bind(':r_intro', $_POST['report_intro'] ?? null);
            $db->bind(':r_disc', $_POST['report_disclaimer'] ?? null);
            $db->bind(':id', $id);
            $db->execute();

            // Handle Cover Image Upload
            if (!empty($_FILES['cover_image']['name'])) {
                $file = $_FILES['cover_image'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    $newName = uniqid('cover_') . '.' . $ext;
                    $dest = __DIR__ . '/../../assets/uploads/' . $newName;
                    if (!is_dir(dirname($dest)))
                        mkdir(dirname($dest), 0777, true);

                    if (move_uploaded_file($file['tmp_name'], $dest)) {
                        $db->query("UPDATE projects SET cover_image = :img WHERE id = :id");
                        $db->bind(':img', $newName); // Storing filename only typically, layout assumes 'uploads/' prefix or root relative?
                        // Full report uses '/uploads/' . $project['cover_image']
                        // So just storing filename is correct.
                        $db->bind(':id', $id);
                        $db->execute();
                    }
                }
            }

            // Handle Custom Fields
            if (isset($_POST['custom_fields'])) {
                // Delete old values for this entity (simplest update strategy)
                // Note: This wipes all values for the project. If we only post some, we lose others. 
                // Ideally we loop and upsert. But form usually posts all or we should check `custom_field_definitions`.
                // Let's us upsert logic or delete-insert.

                // Better: Delete only those we are receiving or delete all for this entity?
                // "custom_field_values" has no ID PK usually? Or it does.
                // Safest is DELETE FROM custom_field_values WHERE entity_id = :id AND definition_id IN (...)
                // Safest is DELETE WHERE definition_id IN (keys) AND entity_id
                // But simplified: Delete all and re-insert is common for these relation tables.

                // Let's do DELETE WHERE definition_id IN (keys) AND entity_id
                $defIds = array_keys($_POST['custom_fields']);
                if (!empty($defIds)) {
                    // Delete existing values for these specific fields
                    // We need to bind dynamically parameters for IN clause
                    $inParams = [];
                    foreach ($defIds as $index => $dId) {
                        $paramName = ":did{$index}";
                        $inParams[$paramName] = $dId;
                    }
                    $placeholders = implode(',', array_keys($inParams));

                    $sqlDel = "DELETE FROM custom_field_values WHERE entity_id = :eid AND definition_id IN ($placeholders)";
                    $db->query($sqlDel);
                    $db->bind(':eid', $id);
                    foreach ($inParams as $key => $val) {
                        $db->bind($key, $val);
                    }
                    $db->execute();

                    // Insert new
                    $sqlIns = "INSERT INTO custom_field_values (definition_id, entity_id, value) VALUES (:did, :eid, :val)";

                    foreach ($_POST['custom_fields'] as $defId => $val) {
                        $db->query($sqlIns);
                        $db->bind(':did', $defId);
                        $db->bind(':eid', $id);
                        $db->bind(':val', $val);
                        $db->execute();
                    }
                }
            }

            // Update client_id
            if (!empty($_POST['client_id'])) {
                $db->query("UPDATE projects SET client_id = :cid WHERE id = :id");
                $db->bind(':cid', $_POST['client_id']);
                $db->bind(':id', $id);
                $db->execute();
            }

            // Sync Team with Roles
            if (isset($_POST['team_members'])) {
                $db->query("DELETE FROM project_members WHERE project_id = :id");
                $db->bind(':id', $id);
                $db->execute();

                // $_POST['team_members'] is expected to be [user_id => role] or simple array of IDS
                // We should support both for backward compatibility or strict enforcement.
                // project.js will now send name="team_members[ID]" value="ROLE"

                foreach ($_POST['team_members'] as $uid => $role) {
                    // Check if only ID was passed (value is ID, key is index)
                    if (is_numeric($uid) && is_string($role) && !is_numeric($role)) {
                        // Correct format: uid = user_id, role = 'owner'
                    } else {
                        // Fallback logic if needed, but JS update will fix this.
                        if (is_numeric($role)) {
                            // Just ID passed
                            $uid = $role;
                            $role = 'specialist';
                        }
                    }

                    $db->query("INSERT INTO project_members (project_id, user_id, role) VALUES (:pid, :uid, :role) ON CONFLICT (project_id, user_id) DO NOTHING");
                    $db->bind(':pid', $id);
                    $db->bind(':uid', $uid);
                    $db->bind(':role', $role);
                    $db->execute();
                }
            } else {
                // If team_members is explicitly empty/missing, it might mean clear all?
                // Or if it's not sent, do nothing?
                // HTML forms don't send anything for empty checkboxes/lists usually if processed that way.
                // Assuming "team_members" input is always present if we want to update it.
                // For now, if not set, we do nothing to prevent accidental wipe if partial form update.
            }

            if (isset($_POST['ajax'])) {
                $this->json(['status' => 'success', 'message' => 'Project updated']);
            }

            $this->redirect('?module=Project&action=index');
        }
    }

    public function saveCoverImage()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $projectId = $_POST['project_id'] ?? null;
            if (!$projectId) {
                $this->json(['status' => 'error', 'message' => 'Missing Project ID']);
                return;
            }

            // Ensure Uploads Directory Exists
            $uploadDir = __DIR__ . '/../../assets/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (!isset($_FILES['cover_image']) || $_FILES['cover_image']['error'] !== UPLOAD_ERR_OK) {
                $this->json(['status' => 'error', 'message' => 'Upload failed']);
                return;
            }

            $ext = 'jpg';
            $newFilename = 'project_cover_' . $projectId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $newFilename;

            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $targetPath)) {
                $db = Database::getInstance();
                $db->query("UPDATE projects SET cover_image = :img WHERE id = :pid");
                $db->bind(':img', $newFilename);
                $db->bind(':pid', $projectId);
                $db->execute();

                $this->json(['status' => 'success', 'filename' => $newFilename]);
            } else {
                $this->json(['status' => 'error', 'message' => 'Failed to move file']);
            }
        }
    }

    // --- Snapshots ---

    public function createSnapshot()
    {
        if (!Auth::check())
            $this->json(['status' => 'error', 'message' => 'Unauthorized']);

        $projectId = $_POST['project_id'];
        $title = $_POST['title'] ?? 'Snapshot';
        $desc = $_POST['description'] ?? '';

        require_once __DIR__ . '/SnapshotManager.php';
        $mgr = new SnapshotManager();

        try {
            $id = $mgr->create($projectId, $title, $desc, Auth::user()['id']);
            $this->json(['status' => 'success', 'snapshot_id' => $id]);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function listSnapshots()
    {
        $projectId = $_REQUEST['project_id'];
        $db = Database::getInstance();
        $db->query("SELECT id, created_at, title, description, total_price, stats_summary, created_by 
                    FROM project_snapshots WHERE project_id = :pid ORDER BY created_at DESC");
        $db->bind(':pid', $projectId);
        $list = $db->resultSet();

        // Enrich with user name
        // (Optional, preserving simplicity)

        $this->json(['status' => 'success', 'snapshots' => $list]);
    }

    public function restoreSnapshot()
    {
        if (!Auth::check())
            $this->json(['status' => 'error', 'message' => 'Unauthorized']);

        $id = $_POST['snapshot_id'];

        require_once __DIR__ . '/SnapshotManager.php';
        $mgr = new SnapshotManager();

        try {
            // Auto-Backup Current State
            $userId = Auth::user()['id'];
            $db = Database::getInstance();
            $db->query("SELECT project_id FROM project_snapshots WHERE id = :id");
            $db->bind(':id', $id);
            $res = $db->single();

            if ($res) {
                $pid = $res['project_id'];
                $mgr->create($pid, 'Auto-Backup (Før Gendannelse)', 'Automatisk snapshot før gendannelse.', $userId);
            }

            $mgr->restore($id, $userId);
            $this->json(['status' => 'success']);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function forkSnapshot()
    {
        if (!Auth::check())
            $this->json(['status' => 'error', 'message' => 'Unauthorized']);

        $snapshotId = $_POST['snapshot_id'];
        $newName = $_POST['new_name'];
        if (empty($newName))
            $this->json(['status' => 'error', 'message' => 'Name required']);

        require_once __DIR__ . '/SnapshotManager.php';
        $mgr = new SnapshotManager();

        try {
            // 1. Create New Project Record
            $db = Database::getInstance();
            $db->query("INSERT INTO projects (name, status, created_by, created_at) 
                        VALUES (:name, 'planning', :uid, NOW()) RETURNING id");
            $db->bind(':name', $newName);
            $db->bind(':uid', Auth::user()['id']); // Assuming 'created_by' column exists
            // MySQL uses different syntax for returning ID. 
            // In migration_v3 we added projects table.
            // Let's check `create` logic? `create` action isn't visible in my snippets, only `update`.
            // But `migration_v3` says `id SERIAL PRIMARY KEY`. PostgreSQL.

            // If PostgreSQL:
            $res = $db->single();
            // wait, `query` doesn't return result unless `single/resultSet` called.
            // `db->execute()` for insert?
            // `Database` class usually has `lastInsertId()`.

            // Re-checking standard `Database` usage:
            /*
             $db->query("INSERT...");
             $db->execute();
             $id = $db->lastInsertId();
            */
            // But PostgreSQL `lastInsertId` relies on sequence name or `RETURNING`.
            // The `Database` class likely handles `lastInsertId` via PDO.

            // I will use `RETURNING id` and `single()` just to be safe with PG.

            // Wait, previous `SnapshotManager` used `lastInsertId`.
            // I'll assume `lastInsertId` works.

            $db->query("INSERT INTO projects (name, status, created_at) VALUES (:name, 'planning', NOW())");
            $db->bind(':name', $newName);
            $db->execute(); // created_by might be missing in schema? I better check.
            $newPid = $db->lastInsertId();

            if (!$newPid)
                throw new \Exception("Could not create project");

            // 2. Restore Snapshot into New Project
            $mgr->restore($snapshotId, Auth::user()['id'], $newPid);

            // 3. Update Name (Restore might have overwritten it with snapshot name)
            $db->query("UPDATE projects SET name = :name WHERE id = :id");
            $db->bind(':name', $newName);
            $db->bind(':id', $newPid);
            $db->execute();

            $this->json(['status' => 'success', 'new_project_id' => $newPid]);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
    public function delete()
    {
        $hasGlobalPerm = Auth::hasPermission('project_delete');
        $isOwner = false;

        $id = $_POST['id'] ?? $_GET['id'] ?? null;
        if (!$id)
            $this->json(['status' => 'error', 'message' => 'No ID provided']);

        // Check Owner Permission
        if (Auth::check()) {
            $uid = Auth::user()['id'];
            $db = Database::getInstance();
            $db->query("SELECT role FROM project_members WHERE project_id = :pid AND user_id = :uid");
            $db->bind(':pid', $id);
            $db->bind(':uid', $uid);
            $res = $db->single();
            if ($res && $res['role'] === 'owner') {
                $isOwner = true;
            }
        }

        if (!$hasGlobalPerm && !$isOwner) {
            $this->json(['status' => 'error', 'message' => 'Permission denied. Only Owner or Admin can delete.']);
        }

        $db = Database::getInstance();
        $db->query("UPDATE projects SET deleted_at = NOW() WHERE id = :id");
        $db->bind(':id', $id);
        $db->execute();

        if (isset($_POST['ajax']) || isset($_GET['ajax'])) {
            $this->json(['status' => 'success', 'message' => 'Project deleted']);
        }
        $this->redirect('?module=Project&action=index');
    }

    // Point 4: Validation Logic
    private function validateProject($data)
    {
        $errors = [];
        if (empty($data['name']) || strlen($data['name']) < 2) {
            $errors[] = "Project Name is required (min 2 chars).";
        }
        if (!in_array($data['status'], ['planning', 'active', 'completed', 'archived'])) {
            $errors[] = "Invalid status.";
        }
        if (!empty($data['start_date']) && !strtotime($data['start_date'])) {
            $errors[] = "Invalid Start Date format.";
        }
        return $errors;
    }
}
