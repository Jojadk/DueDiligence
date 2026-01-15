<?php
namespace Modules\Report;

use Core\Controller;
use Core\Database;
use Core\Auth;

class ReportController extends Controller
{
    // Auth handled by parent constructor

    public function index()
    {
        // Redirect to first project or show selector
        $projectId = $_GET['project_id'] ?? null;
        if (!$projectId) {
            $this->handleNotFound('Vælg et projekt først.');
        }

        $this->generate($projectId);
    }

    // --- Template System ---

    public function setupTemplates()
    {
        $db = Database::getInstance();
        $sql = "CREATE TABLE IF NOT EXISTS report_templates (
            id SERIAL PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            content TEXT,
            css TEXT,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        try {
            $db->query($sql);
            $db->execute();
            echo "Table created/exists. <a href='?module=Report&action=editor'>Go to Editor</a>";
        } catch (\Exception $e) {
            echo $e->getMessage();
        }
    }

    public function editor()
    {
        $this->db->query("SELECT * FROM report_templates ORDER BY id");
        $templates = $this->db->resultSet();
        require __DIR__ . '/views/editor_list.php';
    }

    public function editTemplate()
    {
        $id = $_GET['id'] ?? null;
        $template = null;
        if ($id) {
            $db = Database::getInstance();
            $db->query("SELECT * FROM report_templates WHERE id = :id");
            $db->bind(':id', $id);
            $template = $db->single();
        }
        require __DIR__ . '/views/editor_form.php';
    }

    public function saveTemplate()
    {
        $id = $_POST['id'] ?? null;
        $name = $_POST['name'];
        $content = $_POST['content'];
        $css = $_POST['css'] ?? '';

        $db = Database::getInstance();
        if ($id) {
            $db->query("UPDATE report_templates SET name=:name, content=:content, css=:css, updated_at=NOW() WHERE id=:id");
            $db->bind(':id', $id);
        } else {
            $db->query("INSERT INTO report_templates (name, content, css) VALUES (:name, :content, :css)");
        }
        $db->bind(':name', $name);
        $db->bind(':content', $content);
        $db->bind(':css', $css);
        $db->execute();

        $this->redirect('?module=Report&action=editor');
    }

    public function renderFromTemplate()
    {
        $projectId = $_GET['project_id'] ?? null;
        $templateId = $_GET['template_id'] ?? null;

        if (!$projectId || !$templateId) {
            $this->handleNotFound('Missing project_id or template_id');
        }

        $data = $this->getReportData($projectId);

        // Add today's date
        $data['today'] = date('Y-m-d');

        // Calculate totals for budget overview
        $grandTotals = ['0_1' => 0, '1_2' => 0, '3_5' => 0, '6_10' => 0, 'grand' => 0];
        $categoryIndex = 2; // 1 is Introduction

        foreach ($data['tree'] as &$category) {
            $category['index'] = $categoryIndex++;
            $catTotals = ['0_1' => 0, '1_2' => 0, '3_5' => 0, '6_10' => 0, 'total' => 0];

            // Calculate category totals recursively
            $this->calculateNodeTotals($category, $catTotals);

            $category['totals'] = $catTotals;

            // Add to grand totals
            $grandTotals['0_1'] += $catTotals['0_1'];
            $grandTotals['1_2'] += $catTotals['1_2'];
            $grandTotals['3_5'] += $catTotals['3_5'];
            $grandTotals['6_10'] += $catTotals['6_10'];
            $grandTotals['grand'] += $catTotals['total'];

            // Add subindex to children
            $subIdx = 1;
            if (!empty($category['children'])) {
                foreach ($category['children'] as &$child) {
                    $child['subindex'] = $subIdx++;
                    $child['image'] = !empty($child['media']) ? $child['media'][0] : null;

                    // Element budget
                    $elBudget = ['0_1' => 0, '1_2' => 0, '3_5' => 0, '6_10' => 0];
                    foreach ($child['budget_items'] ?? [] as $item) {
                        $elBudget['0_1'] += $item['amount_0_1'] ?? 0;
                        $elBudget['1_2'] += $item['amount_1_2'] ?? 0;
                        $elBudget['3_5'] += $item['amount_3_5'] ?? 0;
                        $elBudget['6_10'] += $item['amount_5_10'] ?? 0;
                    }
                    $child['budget'] = $elBudget;
                }
            }
        }
        unset($category, $child);

        $data['totals'] = $grandTotals;

        $this->db->query("SELECT * FROM report_templates WHERE id = :id");
        $this->db->bind(':id', $templateId);
        $tpl = $this->db->single();

        if (!$tpl) {
            $this->handleNotFound('Template not found');
        }

        require_once __DIR__ . '/TemplateEngine.php';
        $engine = new TemplateEngine();

        // Render
        $htmlBody = $engine->render($tpl['content'], $data);
        $css = $tpl['css'];

        // Wrap in basic structure
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Report - " . htmlspecialchars($data['project']['name']) . "</title><style>$css</style></head><body>$htmlBody</body></html>";
    }

    /**
     * Recursively calculate budget totals for a node
     */
    private function calculateNodeTotals(&$node, &$totals)
    {
        // Add budget items from this node
        foreach ($node['budget_items'] ?? [] as $item) {
            $totals['0_1'] += $item['amount_0_1'] ?? 0;
            $totals['1_2'] += $item['amount_1_2'] ?? 0;
            $totals['3_5'] += $item['amount_3_5'] ?? 0;
            $totals['6_10'] += $item['amount_5_10'] ?? 0;
            $totals['total'] += $item['total_calculated'] ?? 0;
        }

        // Recurse into children
        if (!empty($node['children'])) {
            foreach ($node['children'] as &$child) {
                $this->calculateNodeTotals($child, $totals);
            }
        }
    }

    public function generator()
    {
        $db = Database::getInstance();

        // Fetch Projects
        $db->query("SELECT id, name, created_at FROM projects ORDER BY created_at DESC");
        $projects = $db->resultSet();

        // Fetch Templates
        $db->query("SELECT id, name FROM report_templates WHERE is_active = TRUE ORDER BY name ASC");
        $templates = $db->resultSet();

        require __DIR__ . '/views/generator.php';
    }

    public function generate($projectId)
    {
        // Legacy Support: Calls getReportData but uses old view
        $data = $this->getReportData($projectId);
        extract($data);
        require __DIR__ . '/views/old/full_report.php';
    }

    private function getReportData($projectId)
    {
        // 1. Fetch Project
        $this->db->query("SELECT * FROM projects WHERE id = :pid");
        $this->db->bind(':pid', $projectId);
        $project = $this->db->single();

        if (!$project) {
            $this->handleNotFound('Projekt ikke fundet');
        }

        // 2. Fetch Client
        $client = null;
        if ($project['client_id']) {
            $this->db->query("SELECT * FROM customers WHERE id = :cid");
            $this->db->bind(':cid', $project['client_id']);
            $client = $this->db->single();
        }

        // 3. Fetch Elements (Tree)
        $this->db->query("SELECT * FROM building_elements WHERE project_id = :pid ORDER BY sort_order ASC, id ASC");
        $this->db->bind(':pid', $projectId);
        $elements = $this->db->resultSet();

        // 4. Eager Load Media & Budgets
        // Get all element IDs
        $elementIds = array_column($elements, 'id');
        $mediaMap = [];
        $budgetMap = [];
        $customValuesMap = [];
        $totalCapex = 0;

        // Custom Definitions
        $this->db->query("SELECT * FROM custom_field_definitions 
                    WHERE entity_type = 'building_element' 
                    AND (scope='global' OR (scope='project' AND project_id=:pid))
                    ORDER BY sort_order ASC");
        $this->db->bind(':pid', $projectId);
        $customFieldDefs = $this->db->resultSet();

        if (!empty($elementIds)) {
            $idList = implode(',', $elementIds);

            // Media
            $this->db->query("SELECT * FROM element_media WHERE element_id IN ($idList) ORDER BY sort_order ASC");
            $allMedia = $this->db->resultSet();
            foreach ($allMedia as $m) {
                $mediaMap[$m['element_id']][] = $m;
            }

            // Budget
            $this->db->query("SELECT * FROM budget_items WHERE element_id IN ($idList)");
            $allBudget = $this->db->resultSet();
            foreach ($allBudget as $b) {
                $budgetMap[$b['element_id']][] = $b;
                $totalCapex += $b['total_calculated'];
            }

            // Custom Values
            $this->db->query("SELECT * FROM custom_field_values WHERE entity_id IN ($idList)");
            $allValues = $this->db->resultSet();
            foreach ($allValues as $v) {
                $customValuesMap[$v['entity_id']][$v['definition_id']] = $v['value'];
            }
        }

        // 5. Build Recursive Tree
        $byId = [];
        foreach ($elements as $el) {
            $el['children'] = [];
            $el['media'] = $mediaMap[$el['id']] ?? [];
            $el['budget_items'] = $budgetMap[$el['id']] ?? [];
            $el['custom_values'] = $customValuesMap[$el['id']] ?? [];
            $byId[$el['id']] = $el;
        }

        $tree = [];
        foreach ($byId as $id => &$node) {
            if ($node['parent_id'] && isset($byId[$node['parent_id']])) {
                $byId[$node['parent_id']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        return compact('project', 'client', 'tree', 'totalCapex', 'customFieldDefs', 'budgetMap');
    }
    public function fixMenu()
    {
        $db = Database::getInstance();

        // 1. Find 'Administration' Parent (create if missing)
        $db->query("SELECT id FROM menu_items WHERE title = 'Administration'");
        $parent = $db->single();

        $parentId = null;
        if ($parent) {
            $parentId = $parent['id'];
        } else {
            // Create Parent
            $db->query("INSERT INTO menu_items (title, module, action, icon, sort_order) VALUES ('Administration', 'Admin', 'index', 'fa-cogs', 99)");
            $db->execute();
            $parentId = $db->lastInsertId();
        }

        // 2. Ensure Rapport Generator is UNDER this parent
        $db->query("SELECT id FROM menu_items WHERE module = 'Report' AND action = 'generator'");
        $item = $db->single();

        if ($item) {
            // Update parent
            $db->query("UPDATE menu_items SET parent_id = :pid, title = 'Rapport Generator', icon = 'fa-file-alt' WHERE id = :id");
            $db->bind(':pid', $parentId);
            $db->bind(':id', $item['id']);
            $db->execute();
            echo "Menu item updated to be under Administration.";
        } else {
            // Insert
            $db->query("INSERT INTO menu_items (title, module, action, icon, parent_id, sort_order) VALUES ('Rapport Generator', 'Report', 'generator', 'fa-file-alt', :pid, 10)");
            $db->bind(':pid', $parentId);
            $db->execute();
            echo "Menu item created under Administration.";
        }
    }

    public function excel($projectId = null)
    {
        // Wrapper for index call or direct
        $pid = $projectId ?? $_GET['project_id'] ?? null;
        if (!$pid)
            die("Missing Project ID");

        // Use the new HTML view, NOT actual Excel
        $this->printReport($pid);
    }

    private function printReport($projectId)
    {
        $data = $this->getReportData($projectId);
        extract($data);
        require __DIR__ . '/views/excel_report.php';
    }

    private function generateExcel($projectId)
    {
        $db = Database::getInstance();
        $db->query("SELECT * FROM projects WHERE id = :pid");
        $db->bind(':pid', $projectId);
        $project = $db->single();

        if (!$project)
            die("Projekt ikke fundet");

        if ($project['client_id']) {
            $db->query("SELECT * FROM customers WHERE id = :cid");
            $db->bind(':cid', $project['client_id']);
            $client = $db->single();
        } else {
            $client = null;
        }

        // Fetch Elements (Tree)
        $db->query("SELECT * FROM building_elements WHERE project_id = :pid ORDER BY sort_order ASC, id ASC");
        $db->bind(':pid', $projectId);
        $elements = $db->resultSet();

        // Eager Load Data
        $elementIds = array_column($elements, 'id');
        $mediaMap = [];
        $budgetMap = [];
        $customValuesMap = [];
        $totalCapex = 0;

        // Custom Definitions
        $db->query("SELECT * FROM custom_field_definitions 
         WHERE entity_type = 'building_element' 
         AND (scope='global' OR (scope='project' AND project_id=:pid))
         ORDER BY sort_order ASC");
        $db->bind(':pid', $projectId);
        $customFieldDefs = $db->resultSet();

        if (!empty($elementIds)) {
            $idList = implode(',', $elementIds);

            // Media
            $db->query("SELECT * FROM element_media WHERE element_id IN ($idList) ORDER BY sort_order ASC");
            $allMedia = $db->resultSet();
            foreach ($allMedia as $m)
                $mediaMap[$m['element_id']][] = $m;

            // Budget
            $db->query("SELECT * FROM budget_items WHERE element_id IN ($idList)");
            $allBudget = $db->resultSet();
            foreach ($allBudget as $b) {
                $budgetMap[$b['element_id']][] = $b;
                $totalCapex += $b['total_calculated'];
            }

            // Custom Values
            $db->query("SELECT * FROM custom_field_values WHERE entity_id IN ($idList)");
            $allValues = $db->resultSet();
            foreach ($allValues as $v)
                $customValuesMap[$v['entity_id']][$v['definition_id']] = $v['value'];
        }

        // Build Tree
        $byId = [];
        foreach ($elements as $el) {
            $el['children'] = [];
            $el['media'] = $mediaMap[$el['id']] ?? [];
            $el['budget_items'] = $budgetMap[$el['id']] ?? [];
            $el['custom_values'] = $customValuesMap[$el['id']] ?? [];
            $byId[$el['id']] = $el;
        }

        $tree = [];
        foreach ($byId as $id => &$node) {
            if ($node['parent_id'] && isset($byId[$node['parent_id']])) {
                $byId[$node['parent_id']]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);

        $this->view('Report/views/excel_report', [
            'project' => $project,
            'client' => $client,
            'tree' => $tree,
            'totalCapex' => $totalCapex,
            'customFieldDefs' => $customFieldDefs,
            'no_layout' => true
        ]);
    }

}
