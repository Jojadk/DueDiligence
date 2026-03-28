<?php
namespace Modules\BuildingElement;

use Core\Controller;
use Core\Database;
use Core\Auth;
use Core\InputLock;

class BuildingElementController extends Controller
{
    // Auth handled by parent constructor

    public function index()
    {
        $projectId = $_GET['project_id'] ?? null;
        if (!$projectId) {
            $this->redirect('?module=Project&action=index');
        }

        if (isset($_GET['action']) && $_GET['action'] === 'textReview') {
            $this->textReview($projectId);
            return;
        }

        // ... rest of index

        // --- AJAX: Node Content ---
        if (isset($_GET['ajax_content']) && isset($_GET['id'])) {
            $this->showNodeContent($projectId, $_GET['id']);
            return;
        }

        $db = Database::getInstance();

        // 0. Fetch Buildings
        $db->query("SELECT * FROM project_buildings WHERE project_id = :pid ORDER BY id ASC");
        $db->bind(':pid', $projectId);
        $buildings = $db->resultSet();

        $allowedBuildingIds = array_column($buildings, 'id');
        $activeBuildingId = $_GET['building_id'] ?? null;

        // 1. Fetch all elements for project (Optional: Filter by Building)
        $sql = "SELECT be.*, COUNT(em.id) as media_count
            FROM building_elements be
            LEFT JOIN element_media em ON be.id = em.element_id
            WHERE be.project_id = :pid";

        if ($activeBuildingId && in_array($activeBuildingId, $allowedBuildingIds)) {
            $sql .= " AND be.building_id = :bid";
        } else if (!empty($buildings) && empty($activeBuildingId)) {
            // If buildings exist but none selected, maybe selected first?
            // Or show all? Let's show all that are NULL + First?
            // User requested dropdown management.
            // Let's support "View All" if ID is empty.
        }

        $sql .= " GROUP BY be.id ORDER BY be.sort_order ASC, be.id ASC";

        $db->query($sql);
        $db->bind(':pid', $projectId);
        if ($activeBuildingId && in_array($activeBuildingId, $allowedBuildingIds)) {
            $db->bind(':bid', $activeBuildingId);
        }
        $elements = $db->resultSet();

        // 2. Build Tree Structure
        $byId = [];
        foreach ($elements as $el) {
            $el['children'] = [];
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
        unset($node); // Break ref

        // Determine active element
        $selectedId = $_GET['id'] ?? null;
        $activeElement = null;
        if ($selectedId && isset($byId[$selectedId])) {
            $activeElement = $byId[$selectedId];
        } elseif (!empty($tree)) {
            $activeElement = $tree[0];
        }

        if ($activeElement) {
            $db->query("SELECT 
                COALESCE(SUM(amount_0_1), 0) as sum_0_1, 
                COALESCE(SUM(amount_1_2), 0) as sum_1_2, 
                COALESCE(SUM(amount_3_5), 0) as sum_3_5, 
                COALESCE(SUM(amount_5_10), 0) as sum_5_10 
                FROM budget_items WHERE element_id = :eid");
            $db->bind(':eid', $activeElement['id']);
            $sums = $db->single();
            if ($sums) {
                $activeElement = array_merge($activeElement, $sums);
            }
        }

        // Fetch Project Details
        $db->query("SELECT * FROM projects WHERE id = :pid");
        $db->bind(':pid', $projectId);
        $project = $db->single();

        // Fetch Custom Fields Definitions
        // (Same logic as in ProjectController, but for building_element)
        $db->query("SELECT * FROM custom_field_definitions 
                    WHERE entity_type = 'building_element' 
                    AND (scope='global' OR (scope='project' AND project_id=:pid))
                    ORDER BY sort_order ASC");
        $db->bind(':pid', $projectId);
        $customFields = $db->resultSet();

        // Fetch Price Catalogs for Dropdown
        $db->query("SELECT * FROM price_catalogs ORDER BY description ASC");
        $prices = $db->resultSet();

        // Fetch media for active element (to prevent null in ContentPartial)
        $media = [];
        if ($activeElement) {
            $db->query("SELECT * FROM element_media WHERE element_id = :eid ORDER BY sort_order ASC");
            $db->bind(':eid', $activeElement['id']);
            $media = $db->resultSet();
        }

        // Render Main View (Split Layout)
        // If it's a normal request, we render everything.
        // We pass $tree and $activeElement
        $this->view('BuildingElement/index', [
            'project' => $project,
            'tree' => $tree,
            'activeElement' => $activeElement,
            'customFields' => $customFields,
            'prices' => $prices,
            'media' => $media,
            'buildings' => $buildings, // New
            'no_layout' => true
        ]);
    }

    private function showNodeContent($projectId, $elementId)
    {
        $db = Database::getInstance();
        $db->query("SELECT * FROM building_elements WHERE id = :id AND project_id = :pid");
        $db->bind(':id', $elementId);
        $db->bind(':pid', $projectId);
        $activeElement = $db->single();

        if ($activeElement) {
            $db->query("SELECT 
                COALESCE(SUM(amount_0_1), 0) as sum_0_1, 
                COALESCE(SUM(amount_1_2), 0) as sum_1_2, 
                COALESCE(SUM(amount_3_5), 0) as sum_3_5, 
                COALESCE(SUM(amount_5_10), 0) as sum_5_10 
                FROM budget_items WHERE element_id = :eid");
            $db->bind(':eid', $activeElement['id']);
            $sums = $db->single();
            if ($sums) {
                $activeElement = array_merge($activeElement, $sums);
            }
        }

        if (!$activeElement) {
            echo "Element not found";
            return;
        }

        // Fetch project for ContentPartial  
        $db->query("SELECT * FROM projects WHERE id = :pid");
        $db->bind(':pid', $projectId);
        $project = $db->single();

        // Fetch related data (Media, Budget, Custom Fields)
        $db->query("SELECT * FROM element_media WHERE element_id = :eid ORDER BY sort_order ASC");
        $db->bind(':eid', $elementId);
        $media = $db->resultSet();

        // Custom Fields Definitions
        // (Simplified: we might want to cache this or pass it differently)
        $db->query("SELECT * FROM custom_field_definitions WHERE entity_type = 'building_element' AND (scope='global' OR project_id=:pid)");
        $db->bind(':pid', $projectId);
        $customFields = $db->resultSet();

        // Render ONLY the content partial
        include MODULES_DIR . '/BuildingElement/ContentPartial.php';
    }

    public function create()
    {
        $projectId = $_GET['project_id'];
        // Fetch Custom Fields
        $db = Database::getInstance();
        $db->query("SELECT * FROM custom_field_definitions 
                    WHERE entity_type = 'building_element' 
                    AND (scope = 'global' OR (scope = 'project' AND project_id = :pid))");
        $db->bind(':pid', $projectId);
        $customFields = $db->resultSet();

        // Fetch Price Catalog
        $db->query("SELECT * FROM price_catalogs ORDER BY description ASC");
        $prices = $db->resultSet();

        // Fetch Dynamic Categories (Constants)
        $db->query("SELECT value FROM system_constants 
                    WHERE key_name = 'CATEGORY_LIST_DK' 
                    AND (project_id = :pid OR project_id IS NULL) 
                    ORDER BY project_id DESC LIMIT 1");
        $db->bind(':pid', $projectId);
        $catRow = $db->single();
        $categories = $catRow ? json_decode($catRow['value'], true) : [
            "1. Udendørsarealer",
            "2. Bygningsskal",
            "3. Indvendige bygningsdele",
            "4. Tekniske installationer"
        ]; // Fallback defaults

        $this->view('BuildingElement/BuildingElementForm', [
            'element' => null,
            'project_id' => $projectId,
            'customFields' => $customFields,
            'prices' => $prices
        ]);
    }

    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $pid = $_POST['project_id'];
            $name = $_POST['name'];
            $loc = $_POST['location'];

            // Map Condition Text to Integer
            $condMap = [
                'God' => 5,
                'Fornuftig' => 4,
                'Rimelig' => 3,
                'Dårlig' => 2,
                'Kritisk' => 1
            ];
            $condText = $_POST['condition_rating'] ?? 'Rimelig';
            $cond = $condMap[$condText] ?? 3; // Default to 3

            $qty = $_POST['quantity'];
            $priceId = $_POST['price_catalog_id']; // Handle empty

            $db = Database::getInstance();
            $db->query("INSERT INTO building_elements (project_id, name, location, condition_rating, quantity, price_catalog_id) 
                        VALUES (:pid, :name, :loc, :cond, :qty, :priceId)");
            $db->bind(':pid', $pid);
            $db->bind(':name', $name);
            $db->bind(':loc', $loc);
            $db->bind(':cond', $cond);
            $db->bind(':qty', $qty);
            $db->bind(':priceId', $priceId ?: null);
            $db->execute();

            $elementId = $db->lastInsertId();

            // Handle Custom Fields
            if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                $sqlIns = "INSERT INTO custom_field_values (definition_id, entity_id, value) VALUES (:did, :eid, :val)";

                foreach ($_POST['custom_fields'] as $defId => $val) {
                    $db->query($sqlIns);
                    $db->bind(':did', $defId);
                    $db->bind(':eid', $elementId);
                    $db->bind(':val', $val);
                    $db->execute();
                }
            }

            $this->redirect("?module=BuildingElement&action=index&project_id=$pid");
        }
    }

    public function update()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'];
            $pid = $_POST['project_id'];
            $name = $_POST['name'];
            $loc = $_POST['location'];

            // Map Condition Text to Integer
            $condMap = [
                'God' => 5,
                'Fornuftig' => 4,
                'Rimelig' => 3,
                'Dårlig' => 2,
                'Kritisk' => 1
            ];
            $condText = $_POST['condition_rating'] ?? 'Rimelig';
            $cond = $condMap[$condText] ?? 3;

            $qty = $_POST['quantity'];
            $priceId = $_POST['price_catalog_id']; // Handle empty

            $db = Database::getInstance();
            $db->query("UPDATE building_elements SET 
                        name = :name, location = :loc, condition_rating = :cond, 
                        quantity = :qty, price_catalog_id = :priceId 
                        WHERE id = :id");
            $db->bind(':name', $name);
            $db->bind(':loc', $loc);
            $db->bind(':cond', $cond);
            $db->bind(':qty', $qty);
            $db->bind(':priceId', $priceId ?: null);
            $db->bind(':id', $id);
            $db->execute();

            if (isset($_POST['custom_fields']) && is_array($_POST['custom_fields'])) {
                // Simplified: Delete and re-insert
                $db->query("DELETE FROM custom_field_values WHERE entity_id = :eid");
                $db->bind(':eid', $id);
                $db->execute();

                $sqlIns = "INSERT INTO custom_field_values (definition_id, entity_id, value) VALUES (:did, :eid, :val)";
                foreach ($_POST['custom_fields'] as $defId => $val) {
                    $db->query($sqlIns);
                    $db->bind(':did', $defId);
                    $db->bind(':eid', $id);
                    $db->bind(':val', $val);
                    $db->execute();
                }
            }

            $this->redirect("?module=BuildingElement&action=index&project_id=$pid&id=$id");
        }
    }

    public function poll()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->json(['status' => 'error', 'message' => 'No ID']);
        }

        $db = Database::getInstance();
        $user_id = Auth::id();

        // 1. Fetch Element Data
        $db->query("SELECT * FROM building_elements WHERE id = :id");
        $db->bind(':id', $id);
        $element = $db->single();

        if (!$element) {
            $this->json(['status' => 'error', 'message' => 'Not found']);
        }

        // 2. Fetch Active Locks for this element
        // We include ALL locks, even our own, so that other tabs of the same user see them.
        // The frontend handles "ignoring" locks that match the current active field of that specific tab.
        $db->query("SELECT l.field_name, u.username 
                    FROM input_locks l 
                    JOIN users u ON l.user_id = u.id 
                    WHERE l.table_name = 'building_elements' 
                    AND l.row_id = :id 
                    AND l.locked_at > (NOW() - INTERVAL '60 seconds')");
        $db->bind(':id', $id);
        //$db->bind(':uid', $user_id); // Removed to allow multi-tab testing
        $locksResult = $db->resultSet();

        $locks = [];
        foreach ($locksResult as $lock) {
            $locks[$lock['field_name']] = $lock['username'];
        }

        // 3. Clean up expired locks (housekeeping)
        // Check local locks for refresh maybe? 
        // Ideally we assume frontend calls acquireLock often enough or we add a refresh here.

        $this->json([
            'status' => 'success',
            'data' => [
                'element' => $element,
                'locks' => $locks
            ]
        ]);
    }

    // Lock methods moved to end


    public function getForm()
    {
        $projectId = $_GET['project_id'] ?? null;
        $id = $_GET['id'] ?? null;
        $db = Database::getInstance();

        if (!$projectId && $id) {
            // Fetch Element to get Project ID
            $db->query("SELECT * FROM building_elements WHERE id = :id");
            $db->bind(':id', $id);
            $element = $db->single();
            if ($element) {
                $projectId = $element['project_id'];
            }
        }

        if (!$projectId) {
            echo "Missing Project ID";
            return;
        }

        // Fetch Element Data (if Edit)
        $element = null;
        if ($id) {
            $db->query("SELECT be.*, 
                        (CASE 
                            WHEN condition_rating = 5 THEN 'God'
                            WHEN condition_rating = 4 THEN 'Fornuftig'
                            WHEN condition_rating = 3 THEN 'Rimelig'
                            WHEN condition_rating = 2 THEN 'Dårlig'
                            WHEN condition_rating = 1 THEN 'Kritisk'
                            ELSE 'Rimelig' 
                        END) as condition_rating_text
                        FROM building_elements be WHERE id = :id");
            $db->bind(':id', $id);
            $element = $db->single();

            // Fetch Custom Field Values
            $db->query("SELECT definition_id, value FROM custom_field_values WHERE entity_id = :id");
            $db->bind(':id', $id);
            $vals = $db->resultSet();
            $element['custom_fields_data'] = [];
            foreach ($vals as $v) {
                $element['custom_fields_data'][$v['definition_id']] = $v['value'];
            }
        }

        // Fetch Prices
        $db->query("SELECT * FROM price_catalogs ORDER BY description ASC");
        $prices = $db->resultSet();

        // Fetch Custom Fields
        $db->query("SELECT * FROM custom_field_definitions 
                    WHERE entity_type = 'building_element' 
                    AND (scope = 'global' OR (scope = 'project' AND project_id = :pid))");
        $db->bind(':pid', $projectId);
        $customFields = $db->resultSet();

        // Fetch Project for View
        $db->query("SELECT * FROM projects WHERE id = :pid");
        $db->bind(':pid', $projectId);
        $project = $db->single();

        require __DIR__ . '/views/FormPartial.php';
    }

    public function getVersionForm()
    {
        require __DIR__ . '/views/VersionSavePartial.php';
    }

    public function heartbeat()
    {
        // Refresh strategy would go here
        $this->json(['status' => 'ok']);
    }

    public function getbudget()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->json(['status' => 'error', 'message' => 'Missing ID']);
        }

        $db = Database::getInstance();
        try {
            // Updated to fetch price catalog name if possible, though raw items are usually enough
            $db->query("SELECT * FROM budget_items WHERE element_id = :eid ORDER BY id ASC");
            $db->bind(':eid', $id);
            $items = $db->resultSet();

            $this->json(['status' => 'success', 'items' => $items]);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function savebudget()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $eid = $_POST['element_id'] ?? $input['element_id'] ?? null;
        $items = $input['items'] ?? [];

        if (!$eid) {
            $this->json(['status' => 'error', 'message' => 'No element ID provided']);
            return;
        }

        if (!Auth::hasPermission('project_edit')) {
            $this->json(['status' => 'error', 'message' => 'Permission denied']);
            return;
        }

        $db = Database::getInstance();

        try {
            $db->beginTransaction();

            // Self-healing: Ensure column exists
            try {
                $db->query("ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS price_catalog_id INTEGER");
                $db->execute();
            } catch (\Exception $ex) { /* Ignore */
            }

            $db->query("DELETE FROM budget_items WHERE element_id = :eid");
            $db->bind(':eid', $eid);
            $db->execute();

            $totalCapex = 0;
            $sum_0_1 = 0;
            $sum_1_2 = 0;
            $sum_3_5 = 0;
            $sum_5_10 = 0;

            foreach ($items as $item) {
                $qty = $item['quantity'] ?? 0;
                $price = $item['unit_price'] ?? 0;
                $total = $qty * $price;
                $totalCapex += $total;

                $a1 = $item['amount_0_1'] ?? 0;
                $a2 = $item['amount_1_2'] ?? 0;
                $a3 = $item['amount_3_5'] ?? 0;
                $a4 = $item['amount_5_10'] ?? 0;

                $sum_0_1 += $a1;
                $sum_1_2 += $a2;
                $sum_3_5 += $a3;
                $sum_5_10 += $a4;

                $db->query("INSERT INTO budget_items (element_id, description, quantity, unit, unit_price, total_calculated, amount_0_1, amount_1_2, amount_3_5, amount_5_10, price_catalog_id) 
                            VALUES (:eid, :desc, :qty, :unit, :price, :total, :a1, :a2, :a3, :a4, :pcid)");
                $db->bind(':eid', $eid);
                $db->bind(':desc', $item['description']);
                $db->bind(':qty', $qty);
                $db->bind(':unit', $item['unit']);
                $db->bind(':price', $price);
                $db->bind(':total', $total);
                $db->bind(':a1', $a1);
                $db->bind(':a2', $a2);
                $db->bind(':a3', $a3);
                $db->bind(':a4', $a4);
                $valPcid = isset($item['price_catalog_id']) && $item['price_catalog_id'] !== '' ? $item['price_catalog_id'] : null;
                $db->bind(':pcid', $valPcid);
                $db->execute();
            }

            // Use exact total
            $finalTotal = $totalCapex;

            $db->query("UPDATE building_elements SET capex = :total WHERE id = :eid");
            $db->bind(':total', $finalTotal);
            $db->bind(':eid', $eid);
            $db->execute();

            $db->commit();

            $this->json([
                'status' => 'success',
                'total' => $totalCapex,
                'total_formatted' => number_format($totalCapex, 2, ',', '.'),
                'breakdown' => [
                    'sum_0_1' => number_format($sum_0_1, 2, ',', '.'),
                    'sum_1_2' => number_format($sum_1_2, 2, ',', '.'),
                    'sum_3_5' => number_format($sum_3_5, 2, ',', '.'),
                    'sum_5_10' => number_format($sum_5_10, 2, ',', '.')
                ]
            ]);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function search_catalog()
    {
        $q = $_GET['q'] ?? '';
        if (strlen($q) < 1) {
            $this->json(['items' => []]);
            return;
        }

        $db = Database::getInstance();
        // Postgres ILIKE for case-insensitive
        $db->query("SELECT id, item_code, name, unit, unit_price FROM price_catalogs 
                    WHERE name ILIKE :q OR item_code ILIKE :q OR description ILIKE :q 
                    ORDER BY name ASC LIMIT 20");
        $term = "%$q%";
        $db->bind(':q', $term);
        $items = $db->resultSet();

        $this->json(['items' => $items]);
    }

    public function run_migration()
    {
        $db = Database::getInstance();
        $out = "";
        try {
            // 1. Table
            $db->query("CREATE TABLE IF NOT EXISTS price_catalogs (
                id SERIAL PRIMARY KEY,
                item_code VARCHAR(50),
                name VARCHAR(255),
                description TEXT,
                unit VARCHAR(20),
                unit_price DECIMAL(15,2),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            $db->execute();
            $out .= "Table price_catalogs checked.\n";

            // Ensure 'name' column exists (if table existed previously)
            try {
                $db->query("ALTER TABLE price_catalogs ADD COLUMN IF NOT EXISTS name VARCHAR(255)");
                $db->execute();
                $out .= "Column name verified in price_catalogs.\n";
            } catch (\Exception $e) {
            }

            // 2. Column (Handle if exists)
            try {
                $db->query("ALTER TABLE budget_items ADD COLUMN IF NOT EXISTS price_catalog_id INTEGER");
                $db->execute();
                $out .= "Column price_catalog_id added.\n";
            } catch (\Exception $e) {
                $out .= "Col error (ignored): " . $e->getMessage() . "\n";
            }

            // 3. Constraint
            try {
                $db->query("ALTER TABLE budget_items ADD CONSTRAINT fk_budget_items_catalog FOREIGN KEY (price_catalog_id) REFERENCES price_catalogs(id) ON DELETE SET NULL");
                $db->execute();
                $out .= "Constraint added.\n";
            } catch (\Exception $e) {
                $out .= "Constraint error (ignored/exists): " . $e->getMessage() . "\n";
            }

        } catch (\Exception $e) {
            $out .= "Fatal Error: " . $e->getMessage();
        }
        $this->json(['status' => 'success', 'output' => $out]);
    }

    public function updatefield()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $this->json(['status' => 'error', 'message' => 'Invalid JSON']);
        }

        $id = $input['id'];
        $field = $input['field'];
        $value = $input['value'];

        // Normalize numeric fields (Capex)
        if ($field === 'capex') {
            // Replace thousands separator (dot) with empty, and decimal comma with dot
            // Expected format: "1.000,00" -> "1000.00"
            if (is_string($value)) {
                $value = str_replace('.', '', $value); // Remove thousands sep
                $value = str_replace(',', '.', $value); // Convert comma to dot
            }
        }

        // Normalize boolean fields (Checkboxes)
        if ($field === 'is_bcl') {
            // Convert checkbox "on" to integer 1, empty/false to 0
            if ($value === 'on' || $value === true || $value === '1') {
                $value = 1;
            } else {
                $value = 0;
            }
        }

        $allowed = ['risk_level', 'capex', 'recommendation', 'condition_rating', 'observation', 'description', 'is_bcl'];
        if (!in_array($field, $allowed)) {
            $this->json(['status' => 'error', 'message' => 'Field not allowed']);
        }
        $db = Database::getInstance();
        $db->query("UPDATE building_elements SET $field = :value WHERE id = :id");
        $db->bind(':value', $value);
        $db->bind(':id', $id);
        $db->execute();
        $this->json(['status' => 'success']);
    }

    public function createBuilding()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST; // Fallback
        }

        $pid = $input['project_id'] ?? null;
        $name = $input['name'] ?? null;

        if (!$pid || !$name) {
            $this->json(['status' => 'error', 'message' => 'Missing project_id or name']);
        }

        $db = Database::getInstance();
        try {
            $db->query("INSERT INTO project_buildings (project_id, name) VALUES (:pid, :name)");
            $db->bind(':pid', $pid);
            $db->bind(':name', $name);
            $db->execute();
            $this->json(['status' => 'success']);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    // ---------- Image Upload ----------
    public function addimage()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->json(['status' => 'error', 'message' => 'Invalid method']);
        }

        $id = $_POST['id'];
        $projectId = $_POST['project_id'] ?? null;
        $db = Database::getInstance();

        // Verify element exists
        $db->query('SELECT id FROM building_elements WHERE id = :eid');
        $db->bind(':eid', $id);
        $element = $db->single();

        if (!$element) {
            $this->json(['status' => 'error', 'message' => 'Element not found']);
        }

        $uploadedCount = 0;
        if (!empty($_FILES['images']['name'])) {
            // Normalize files array if single or multiple
            $names = is_array($_FILES['images']['name']) ? $_FILES['images']['name'] : [$_FILES['images']['name']];
            $tmps = is_array($_FILES['images']['tmp_name']) ? $_FILES['images']['tmp_name'] : [$_FILES['images']['tmp_name']];

            foreach ($names as $idx => $name) {
                $tmp = $tmps[$idx];
                // Simple check
                if (empty($tmp))
                    continue;

                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $newName = uniqid('img_') . '.' . $ext;

                // Use assets/uploads directory for consistency
                $uploadDir = __DIR__ . '/../../assets/uploads/';
                $dest = $uploadDir . $newName;

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if (move_uploaded_file($tmp, $dest)) {
                    $db->query('INSERT INTO element_media (element_id, file_path, media_type) VALUES (:eid, :path, :type)');
                    $db->bind(':eid', $id);
                    // Store relative path from assets/
                    $db->bind(':path', $newName);
                    $db->bind(':type', 'image');
                    $db->execute();
                    $uploadedCount++;
                }
            }
        }

        $this->json(['status' => 'success', 'uploaded' => $uploadedCount]);
    }

    public function reorder()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['structure'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid data']);
        }

        $structure = $input['structure'];
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            foreach ($structure as $item) {
                $db->query("UPDATE building_elements SET parent_id = :pid, sort_order = :ord WHERE id = :id");
                $db->bind(':pid', $item['parent_id'] ?: null);
                $db->bind(':ord', $item['sort_order']);
                $db->bind(':id', $item['id']);
                $db->execute();
            }
            $db->commit();
            $this->json(['status' => 'success']);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function reorderMedia()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input || !isset($input['media_order'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid data']);
        }

        $order = $input['media_order'];
        $db = Database::getInstance();
        $db->beginTransaction();
        try {
            foreach ($order as $item) {
                $db->query("UPDATE element_media SET sort_order = :ord WHERE id = :id");
                $db->bind(':id', $item['id']);
                $db->bind(':ord', $item['sort_order']);
                $db->execute();
            }
            $db->commit();
            $this->json(['status' => 'success']);
        } catch (\Exception $e) {
            $db->rollBack();
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function lockcheck()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $elementId = $input['element_id'] ?? null;
        $field = $input['field_name'] ?? null;
        $clientId = $input['client_id'] ?? null;

        if (!$elementId || !$field) {
            $this->json(['success' => false, 'message' => 'Missing params']);
        }

        $result = InputLock::acquireLock('building_elements', $elementId, $field, $clientId);

        if ($result['success']) {
            $this->json(['success' => true]);
        } else {
            $this->json(['success' => false, 'locked_by' => $result['locked_by'] ?? 'ukendt']);
        }
    }



    public function get()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->json(['status' => 'error', 'message' => 'No ID']);
        }
        $db = Database::getInstance();
        $db->query("SELECT * FROM building_elements WHERE id = :id");
        $db->bind(':id', $id);
        $el = $db->single();

        if (!$el) {
            $this->json(['status' => 'error', 'message' => 'Not found']);
        }

        // Add calculated/mapped fields if necessary (e.g. condition rating text)
        $condMap = [
            5 => 'God',
            4 => 'Fornuftig',
            3 => 'Rimelig',
            2 => 'Dårlig',
            1 => 'Kritisk'
        ];
        $el['condition_rating_text'] = $condMap[$el['condition_rating']] ?? 'Rimelig';

        // Fetch Custom Field Values for this Element
        $db->query("SELECT definition_id, value FROM custom_field_values WHERE entity_id = :eid");
        $db->bind(':eid', $id);
        $valuesRaw = $db->resultSet();
        $el['custom_fields_data'] = [];
        foreach ($valuesRaw as $r) {
            $el['custom_fields_data'][$r['definition_id']] = $r['value'];
        }

        $this->json(['status' => 'success', 'data' => $el]);
    }

    public function delete()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;
        if (!$id) {
            $this->json(['status' => 'error', 'message' => 'No ID provided']);
        }

        if (!Auth::hasPermission('project_edit')) {
            $this->json(['status' => 'error', 'message' => 'Permission denied']);
        }

        $db = Database::getInstance();
        try {
            $db->query("DELETE FROM building_elements WHERE id = :id");
            $db->bind(':id', $id);
            $db->execute();
            $this->json(['status' => 'success']);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function deleteMedia()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = $_POST['id'] ?? $input['id'] ?? null;

        if (!$id) {
            $this->json(['status' => 'error', 'message' => 'No media ID provided']);
            return;
        }

        if (!Auth::hasPermission('project_edit')) {
            $this->json(['status' => 'error', 'message' => 'Permission denied']);
            return;
        }

        $db = Database::getInstance();

        try {
            // First, get the file paths to delete from filesystem
            $db->query("SELECT file_path, original_file_path FROM element_media WHERE id = :id");
            $db->bind(':id', $id);
            $media = $db->single();

            if (!$media) {
                $this->json(['status' => 'error', 'message' => 'Media not found']);
                return;
            }

            // Delete database record
            $db->query("DELETE FROM element_media WHERE id = :id");
            $db->bind(':id', $id);
            $db->execute();

            // Delete files from filesystem
            $baseDir = $_SERVER['DOCUMENT_ROOT'] ?? __DIR__ . '/../..';

            if (!empty($media['file_path'])) {
                $filePath = $media['file_path'];
                // Handle both /assets/uploads/ and /uploads/ paths
                if (strpos($filePath, 'assets/') === 0) {
                    $fullPath = $baseDir . '/' . $filePath;
                } else if (strpos($filePath, '/') === 0) {
                    $fullPath = $baseDir . $filePath;
                } else {
                    $fullPath = $baseDir . '/assets/uploads/' . $filePath;
                }

                if (file_exists($fullPath)) {
                    @unlink($fullPath); // Suppress errors if file doesn't exist
                }
            }

            // Delete original file if it exists and is different
            if (!empty($media['original_file_path']) && $media['original_file_path'] !== $media['file_path']) {
                $origPath = $media['original_file_path'];
                if (strpos($origPath, 'assets/') === 0) {
                    $fullPath = $baseDir . '/' . $origPath;
                } else if (strpos($origPath, '/') === 0) {
                    $fullPath = $baseDir . $origPath;
                } else {
                    $fullPath = $baseDir . '/assets/uploads/' . $origPath;
                }

                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            }

            $this->json(['status' => 'success', 'message' => 'Media deleted successfully']);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => 'Error deleting media: ' . $e->getMessage()]);
        }
    }

    public function saveVersion()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $projectId = $input['project_id'] ?? null;
        $versionNumber = $input['version_number'] ?? null;
        $versionNote = $input['version_note'] ?? '';
        $internalRemark = $input['internal_note'] ?? ($input['internal_remark'] ?? '');
        $createdByName = $input['created_by_name'] ?? 'System';

        if (!$projectId || !$versionNumber) {
            $this->json(['status' => 'error', 'message' => 'Project ID and version number required']);
        }

        $db = Database::getInstance();

        // Fetch complete project snapshot
        $db->query("SELECT * FROM projects WHERE id = :pid");
        $db->bind(':pid', $projectId);
        $project = $db->single();

        // Fetch all building elements
        $db->query("SELECT * FROM building_elements WHERE project_id = :pid ORDER BY sort_order ASC");
        $db->bind(':pid', $projectId);
        $elements = $db->resultSet();

        // Fetch media for each element
        foreach ($elements as &$element) {
            $db->query("SELECT * FROM element_media WHERE element_id = :eid");
            $db->bind(':eid', $element['id']);
            $element['media'] = $db->resultSet();

            // Fetch budget items
            $db->query("SELECT * FROM budget_items WHERE element_id = :eid");
            $db->bind(':eid', $element['id']);
            $element['budget_items'] = $db->resultSet();
        }

        // Create snapshot
        $snapshot = [
            'project' => $project,
            'elements' => $elements,
            'saved_at' => date('Y-m-d H:i:s'),
            'element_count' => count($elements)
        ];

        // Save version
        $db->query("INSERT INTO project_versions 
                    (project_id, version_number, version_note, internal_remark, 
                     created_by_name, created_by_user_id, snapshot_data) 
                    VALUES (:pid, :vnum, :vnote, :iremark, :createdby, :userid, :snapshot)");

        $db->bind(':pid', $projectId);
        $db->bind(':vnum', $versionNumber);
        $db->bind(':vnote', $versionNote);
        $db->bind(':iremark', $internalRemark);
        $db->bind(':createdby', $createdByName);
        $db->bind(':userid', Auth::user()['id'] ?? null);
        $db->bind(':snapshot', json_encode($snapshot));

        try {
            $db->execute();
            $this->json([
                'status' => 'success',
                'message' => 'Version saved successfully',
                'version_id' => $db->lastInsertId()
            ]);
        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => 'Failed to save version: ' . $e->getMessage()]);
        }
    }

    public function getVersions()
    {
        $projectId = $_GET['project_id'] ?? null;

        if (!$projectId) {
            $this->json(['status' => 'error', 'message' => 'Project ID required']);
        }

        $db = Database::getInstance();
        $db->query("SELECT id, project_id, version_number, version_note, internal_remark, 
                           created_by_name, created_at, 
                           (snapshot_data->>'element_count')::int as element_count
                    FROM project_versions 
                    WHERE project_id = :pid 
                    ORDER BY created_at DESC");
        $db->bind(':pid', $projectId);
        $versions = $db->resultSet();

        // Format dates
        foreach ($versions as &$v) {
            $v['created_at'] = date('d/m-Y H:i', strtotime($v['created_at']));
        }

        $this->json(['status' => 'success', 'versions' => $versions]);
    }

    public function restoreVersion()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        $versionId = $input['version_id'] ?? null;
        $projectId = $input['project_id'] ?? null;

        if (!$versionId || !$projectId) {
            $this->json(['status' => 'error', 'message' => 'Version ID and Project ID required']);
        }

        if (!Auth::hasPermission('project_edit')) {
            $this->json(['status' => 'error', 'message' => 'Permission denied']);
        }

        $db = Database::getInstance();

        // Fetch version snapshot
        $db->query("SELECT snapshot_data FROM project_versions WHERE id = :vid AND project_id = :pid");
        $db->bind(':vid', $versionId);
        $db->bind(':pid', $projectId);
        $version = $db->single();

        if (!$version) {
            $this->json(['status' => 'error', 'message' => 'Version not found']);
        }

        $snapshot = json_decode($version['snapshot_data'], true);

        if (!$snapshot || !isset($snapshot['elements'])) {
            $this->json(['status' => 'error', 'message' => 'Invalid snapshot data']);
        }

        try {
            // Delete current building elements (CASCADE will handle media and budget items)
            $db->query("DELETE FROM building_elements WHERE project_id = :pid");
            $db->bind(':pid', $projectId);
            $db->execute();

            // Restore elements from snapshot
            foreach ($snapshot['elements'] as $el) {
                $db->query("INSERT INTO building_elements 
                           (project_id, name, location, description, recommendation, 
                            condition_rating, risk_level, capex, observation, 
                            parent_id, sort_order, quantity, price_catalog_id, unit) 
                           VALUES 
                           (:pid, :name, :location, :desc, :rec, :cond, :risk, :capex, :obs, 
                            :parent, :sort, :qty, :price_id, :unit)");

                $db->bind(':pid', $projectId);
                $db->bind(':name', $el['name'] ?? '');
                $db->bind(':location', $el['location'] ?? '');
                $db->bind(':desc', $el['description'] ?? '');
                $db->bind(':rec', $el['recommendation'] ?? '');
                $db->bind(':cond', $el['condition_rating'] ?? 'Rimelig');
                $db->bind(':risk', $el['risk_level'] ?? 'Ikke relevant');
                $db->bind(':capex', $el['capex'] ?? 0);
                $db->bind(':obs', $el['observation'] ?? '');
                $db->bind(':parent', $el['parent_id'] ?? null);
                $db->bind(':sort', $el['sort_order'] ?? 0);
                $db->bind(':qty', $el['quantity'] ?? 1);
                $db->bind(':price_id', $el['price_catalog_id'] ?? null);
                $db->bind(':unit', $el['unit'] ?? 'stk');

                $db->execute();
                $newElementId = $db->lastInsertId();

                // Restore media (file paths will still be valid)
                if (isset($el['media']) && is_array($el['media'])) {
                    foreach ($el['media'] as $media) {
                        $db->query("INSERT INTO element_media 
                                   (element_id, file_path, file_type, caption) 
                                   VALUES (:eid, :path, :type, :caption)");
                        $db->bind(':eid', $newElementId);
                        $db->bind(':path', $media['file_path'] ?? '');
                        $db->bind(':type', $media['file_type'] ?? 'image/jpeg');
                        $db->bind(':caption', $media['caption'] ?? '');
                        $db->execute();
                    }
                }

                // Restore budget items
                if (isset($el['budget_items']) && is_array($el['budget_items'])) {
                    foreach ($el['budget_items'] as $item) {
                        $db->query("INSERT INTO budget_items 
                                   (element_id, description, quantity, unit, unit_price) 
                                   VALUES (:eid, :desc, :qty, :unit, :price)");
                        $db->bind(':eid', $newElementId);
                        $db->bind(':desc', $item['description'] ?? '');
                        $db->bind(':qty', $item['quantity'] ?? 0);
                        $db->bind(':unit', $item['unit'] ?? 'stk');
                        $db->bind(':price', $item['unit_price'] ?? 0);
                        $db->execute();
                    }
                }
            }

            $this->json([
                'status' => 'success',
                'message' => 'Version restored successfully',
                'elements_restored' => count($snapshot['elements'])
            ]);

        } catch (\Exception $e) {
            $this->json(['status' => 'error', 'message' => 'Restore failed: ' . $e->getMessage()]);
        }
    }

    public function textReview($projectId = null)
    {
        if (!$projectId) {
            $projectId = $_GET['project_id'] ?? null;
        }
        if (!$projectId) {
            $this->redirect('?module=Project&action=index');
        }
        $db = Database::getInstance();

        // Fetch project info
        $db->query("SELECT * FROM projects WHERE id = :id");
        $db->bind(':id', $projectId);
        $project = $db->single();

        // Fetch all elements
        $db->query("SELECT * FROM building_elements WHERE project_id = :pid ORDER BY sort_order ASC, id ASC");
        $db->bind(':pid', $projectId);
        $elements = $db->resultSet();

        $viewData = [
            'project' => $project,
            'elements' => $elements
        ];

        // Manually include the view file
        extract($viewData);
        // Create directory if not exists
        if (!is_dir(MODULES_DIR . '/BuildingElement/Report')) {
            mkdir(MODULES_DIR . '/BuildingElement/Report', 0755, true);
        }
        include MODULES_DIR . '/BuildingElement/Report/TextReview.php';
    }

    public function applyTemplate()
    {
        $projectId = $_GET['project_id'] ?? null;
        if (!$projectId) {
            $this->redirect('?module=Project&action=index');
        }

        if (!Auth::hasPermission('project_edit')) {
            die('Permission denied');
        }

        $db = Database::getInstance();

        // Check if elements exist
        $db->query("SELECT COUNT(*) as count FROM building_elements WHERE project_id = :pid");
        $db->bind(':pid', $projectId);
        $res = $db->single();
        if ($res['count'] > 0) {
            // Append
        }

        $structure = [
            'std.1' => [
                'location' => 'std.1',
                'children' => ['std.1.1', 'std.1.2', 'std.1.3', 'std.1.4', 'std.1.5', 'std.1.6']
            ],
            'std.2' => [
                'location' => 'std.2',
                'children' => ['std.2.1', 'std.2.2', 'std.2.3', 'std.2.4', 'std.2.5', 'std.2.6']
            ],
            'std.3' => [
                'location' => 'std.3',
                'children' => ['std.3.1', 'std.3.2', 'std.3.3', 'std.3.4', 'std.3.5', 'std.3.6']
            ],
            'std.4' => [
                'location' => 'std.4',
                'children' => ['std.4.1', 'std.4.2', 'std.4.3', 'std.4.4', 'std.4.5', 'std.4.6', 'std.4.7', 'std.4.8', 'std.4.9', 'std.4.10']
            ]
        ];

        $sortOrder = 0;
        foreach ($structure as $mainKey => $data) {
            // Create Main Category
            $db->query("INSERT INTO building_elements (project_id, name, location, sort_order) VALUES (:pid, :name, :loc, :sort)");
            $db->bind(':pid', $projectId);
            $db->bind(':name', $mainKey); // Storing the KEY
            $db->bind(':loc', $data['location']);
            $db->bind(':sort', $sortOrder++);
            $db->execute();
            $parentId = $db->lastInsertId();

            // Create Children
            foreach ($data['children'] as $childKey) {
                $db->query("INSERT INTO building_elements (project_id, name, location, parent_id, sort_order) VALUES (:pid, :name, :loc, :parent, :sort)");
                $db->bind(':pid', $projectId);
                $db->bind(':name', $childKey); // Storing the KEY
                $db->bind(':loc', $data['location']);
                $db->bind(':parent', $parentId);
                $db->bind(':sort', $sortOrder++);
                $db->execute();
            }
        }

        $this->redirect("?module=BuildingElement&action=index&project_id=$projectId");
    }
    public function getMedia()
    {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            $this->json(['status' => 'error', 'message' => 'Missing ID']);
            return;
        }

        $db = Database::getInstance();
        $db->query("SELECT * FROM element_media WHERE id = :id");
        $db->bind(':id', $id);
        $media = $db->single();

        if ($media) {
            $src = $media['file_path'];
            if (!empty($media['original_file_path'])) {
                $src = $media['original_file_path'];
            }

            // Handle path prefix correctly
            if (strpos($src, 'assets/') === 0) {
                // Already has assets/ prefix, add leading /
                $fullPath = '/' . $src;
            } elseif (strpos($src, '/') === 0) {
                // Already absolute path
                $fullPath = $src;
            } else {
                // Relative path, add /assets/uploads/
                $fullPath = '/assets/uploads/' . $src;
            }

            $this->json([
                'status' => 'success',
                'data' => [
                    'id' => $media['id'],
                    'src' => $fullPath,
                    'annotations' => $media['annotations'] ?? '[]'
                ]
            ]);
        } else {
            $this->json(['status' => 'error', 'message' => 'Not found']);
        }
    }

    public function saveImageAnnotation()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'] ?? null;
            $json = $_POST['annotations'] ?? '[]';

            if (!$id) {
                $this->json(['status' => 'error', 'message' => 'Missing ID']);
                return;
            }

            $db = Database::getInstance();

            // Schema update
            try {
                $db->query("ALTER TABLE element_media ADD COLUMN IF NOT EXISTS annotations TEXT");
                $db->execute();
                $db->query("ALTER TABLE element_media ADD COLUMN IF NOT EXISTS original_file_path VARCHAR(255)");
                $db->execute();
            } catch (\Exception $e) {
            }

            $db->query("SELECT * FROM element_media WHERE id = :id");
            $db->bind(':id', $id);
            $media = $db->single();

            if (!$media) {
                $this->json(['status' => 'error', 'message' => 'Media not found']);
                return;
            }

            $newFilename = $media['file_path'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                // Use consistent upload path - assets/uploads
                $uploadDir = __DIR__ . '/../../assets/uploads/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';

                if (empty($media['original_file_path'])) {
                    $originalPath = $media['file_path'];
                    // Handle different path formats
                    if (strpos($originalPath, 'assets/') === 0) {
                        $currentFile = __DIR__ . '/../../' . $originalPath;
                    } else if (strpos($originalPath, '/') === 0) {
                        $currentFile = __DIR__ . '/../..' . $originalPath;
                    } else {
                        $currentFile = $uploadDir . $originalPath;
                    }

                    if (file_exists($currentFile)) {
                        $backupName = 'orig_' . time() . '_' . basename($originalPath);
                        copy($currentFile, $uploadDir . $backupName);

                        $db->query("UPDATE element_media SET original_file_path = :orig WHERE id = :id");
                        $db->bind(':orig', $backupName);
                        $db->bind(':id', $id);
                        $db->execute();
                    }
                }

                $newFilename = 'annotated_' . time() . '_' . uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $newFilename);
            }

            $db->query("UPDATE element_media SET file_path = :fp, annotations = :json WHERE id = :id");
            $db->bind(':fp', $newFilename);
            $db->bind(':json', $json);
            $db->bind(':id', $id);
            $db->execute();

            $this->json(['status' => 'success']);
        }
    }

    public function saveMediaCaption()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = $_POST['id'] ?? null;
            $caption = $_POST['caption'] ?? '';

            if (!$id) {
                $this->json(['status' => 'error', 'message' => 'Missing ID']);
                return;
            }

            $db = Database::getInstance();

            // Schema update
            try {
                $db->query("ALTER TABLE element_media ADD COLUMN IF NOT EXISTS caption TEXT");
                $db->execute();
            } catch (\Exception $e) {
            }

            $db->query("UPDATE element_media SET caption = :caption WHERE id = :id");
            $db->bind(':caption', $caption);
            $db->bind(':id', $id);
            $db->execute();

        }
    }

    public function acquireLock()
    {
        $this->json(['status' => 'success']);
    }
    public function lockrelease()
    {
        $this->json(['status' => 'success']);
    }


}
