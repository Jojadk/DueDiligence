<?php
namespace Modules\Dashboard;

use Core\Controller;
use Core\Auth;
use Core\Database;

class DashboardController extends Controller
{
    // Auth is handled by parent constructor

    public function index()
    {
        // Fetch Stats for Dashboard
        // Use $this->db from parent

        // Count Projects
        $this->db->query("SELECT COUNT(*) as count FROM projects");
        $projectCount = $this->db->single()['count'];

        // Count Elements
        $this->db->query("SELECT COUNT(*) as count FROM building_elements");
        $elementCount = $this->db->single()['count'];

        // Count Users
        $this->db->query("SELECT COUNT(*) as count FROM users");
        $userCount = $this->db->single()['count'];

        $this->view('Dashboard/index', [
            'projectCount' => $projectCount,
            'elementCount' => $elementCount,
            'userCount' => $userCount
        ]);
    }
    public function poll()
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }

    public function acquireLock()
    {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}
