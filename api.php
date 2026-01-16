<?php
/**
 * API Controller - Centralized API with Role-Based Access Control
 * ALL permissions checked on backend - NEVER trust frontend
 */

require_once __DIR__ . '/core/core.php';
require_once __DIR__ . '/core/security.php';

header('Content-Type: application/json');
set_security_headers();

// Require login for ALL API calls
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'redirect' => '/?module=auth&action=login']);
    exit;
}

$currentUser = current_user();
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit;
}

// Route to appropriate handler
try {
    switch ($action) {
        // Dashboard Widgets (backend-controlled data)
        case 'get_dashboard_stats':
            echo json_encode(getDashboardStats($currentUser));
            break;

        case 'get_dashboard_widgets':
            echo json_encode(getDashboardWidgets($currentUser));
            break;

        // Image Upload/Management (with permissions)
        case 'upload_image':
            csrf_require();
            echo json_encode(uploadImage($currentUser));
            break;

        case 'delete_image':
            csrf_require();
            echo json_encode(deleteImage($currentUser));
            break;

        case 'get_images':
            echo json_encode(getImages($currentUser));
            break;

        // Drag-and-Drop Sorting (with ownership check)
        case 'update_order':
            csrf_require();
            echo json_encode(updateOrder($currentUser));
            break;

        // Project Snapshots (admin or project owner only)
        case 'create_snapshot':
            csrf_require();
            echo json_encode(createSnapshot($currentUser));
            break;

        case 'restore_snapshot':
            csrf_require();
            echo json_encode(restoreSnapshot($currentUser));
            break;

        case 'list_snapshots':
            echo json_encode(listSnapshots($currentUser));
            break;

        case 'delete_snapshot':
            csrf_require();
            echo json_encode(deleteSnapshot($currentUser));
            break;

        // Project Copying (with permissions)
        case 'copy_project':
            csrf_require();
            echo json_encode(copyProject($currentUser));
            break;

        // Templates (admin-only creation, all can use)
        case 'create_template':
            csrf_require();
            echo json_encode(createTemplate($currentUser));
            break;

        case 'apply_template':
            csrf_require();
            echo json_encode(applyTemplate($currentUser));
            break;

        case 'list_templates':
            echo json_encode(listTemplates($currentUser));
            break;

        // User Management (admin only)
        case 'list_users':
            echo json_encode(listUsers($currentUser));
            break;

        case 'update_user_role':
            csrf_require();
            echo json_encode(updateUserRole($currentUser));
            break;

        // Notifications
        case 'get_notifications':
            echo json_encode(getNotifications($currentUser));
            break;

        case 'mark_notification_read':
            csrf_require();
            echo json_encode(markNotificationRead($currentUser));
            break;

        // Global Search
        case 'search':
            echo json_encode(globalSearch($currentUser));
            break;

        // CSRF Token Refresh
        case 'refresh_csrf_token':
            echo json_encode(['success' => true, 'token' => csrf_token()]);
            break;

        default:
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Action not found']);
    }
} catch (Exception $e) {
    log_error('API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

/* ========================================
   DASHBOARD WIDGETS (Backend-Controlled)
   ======================================== */

function getDashboardStats(array $user): array {
    $stats = [
        'customers' => 0,
        'projects' => 0,
        'buildings' => 0,
        'elements' => 0,
        'active_projects' => 0,
        'total_capex' => 0,
        'pending_items' => 0,
        'urgent_items' => 0
    ];

    // Admin sees all, users see only their data
    if (has_permission($user, 'admin')) {
        $stats['customers'] = db_value("SELECT COUNT(*) FROM customers");
        $stats['projects'] = db_value("SELECT COUNT(*) FROM projects");
        $stats['buildings'] = db_value("SELECT COUNT(*) FROM buildings");
        $stats['elements'] = db_value("SELECT COUNT(*) FROM building_elements");
        $stats['active_projects'] = db_value("SELECT COUNT(*) FROM projects WHERE status = 'active'");
        $stats['total_capex'] = db_value("SELECT COALESCE(SUM(capex), 0) FROM building_elements");
        $stats['urgent_items'] = db_value("SELECT COUNT(*) FROM building_elements WHERE urgency IN ('high', 'critical')");
    } else {
        // Regular users see only their assigned projects
        $userId = $user['id'];
        $stats['projects'] = db_value("SELECT COUNT(*) FROM projects WHERE user_id = :uid", ['uid' => $userId]);
        $stats['buildings'] = db_value("SELECT COUNT(*) FROM buildings b JOIN projects p ON b.project_id = p.id WHERE p.user_id = :uid", ['uid' => $userId]);
        $stats['elements'] = db_value("SELECT COUNT(*) FROM building_elements be JOIN buildings b ON be.building_id = b.id JOIN projects p ON b.project_id = p.id WHERE p.user_id = :uid", ['uid' => $userId]);
        $stats['active_projects'] = db_value("SELECT COUNT(*) FROM projects WHERE user_id = :uid AND status = 'active'", ['uid' => $userId]);
        $stats['total_capex'] = db_value("SELECT COALESCE(SUM(be.capex), 0) FROM building_elements be JOIN buildings b ON be.building_id = b.id JOIN projects p ON b.project_id = p.id WHERE p.user_id = :uid", ['uid' => $userId]);
        $stats['urgent_items'] = db_value("SELECT COUNT(*) FROM building_elements be JOIN buildings b ON be.building_id = b.id JOIN projects p ON b.project_id = p.id WHERE p.user_id = :uid AND be.urgency IN ('high', 'critical')", ['uid' => $userId]);
    }

    return ['success' => true, 'stats' => $stats];
}

function getDashboardWidgets(array $user): array {
    $widgets = [];

    // Admin gets admin widgets, users get user widgets
    if (has_permission($user, 'admin')) {
        $widgets['recent_users'] = db_query("SELECT id, name, email, created_at FROM users ORDER BY created_at DESC LIMIT 5");
        $widgets['system_health'] = [
            'database_size' => db_value("SELECT pg_database_size(current_database())"),
            'total_records' => db_value("SELECT COUNT(*) FROM customers") + db_value("SELECT COUNT(*) FROM projects"),
            'uptime' => sys_getloadavg()
        ];
    }

    // All users get these widgets
    $userId = has_permission($user, 'admin') ? null : $user['id'];
    $where = $userId ? "WHERE p.user_id = :uid" : "";
    $params = $userId ? ['uid' => $userId] : [];

    $widgets['recent_projects'] = db_query("SELECT p.*, c.name as customer_name FROM projects p LEFT JOIN customers c ON p.customer_id = c.id $where ORDER BY p.created_at DESC LIMIT 5", $params);
    
    $whereUrgent = $userId ? "WHERE p.user_id = :uid AND be.urgency IN ('high', 'critical')" : "WHERE be.urgency IN ('high', 'critical')";
    $widgets['urgent_elements'] = db_query("SELECT be.*, b.name as building_name FROM building_elements be LEFT JOIN buildings b ON be.building_id = b.id LEFT JOIN projects p ON b.project_id = p.id $whereUrgent ORDER BY CASE be.urgency WHEN 'critical' THEN 1 WHEN 'high' THEN 2 END, be.time_horizon LIMIT 10", $params);

    return ['success' => true, 'widgets' => $widgets, 'permissions' => get_user_permissions($user['id'])];
}

/* ========================================
   IMAGE UPLOAD/MANAGEMENT
   ======================================== */

function uploadImage(array $user): array {
    if (!has_permission($user, 'upload_images')) {
        return ['success' => false, 'error' => 'Ingen tilladelse til at uploade billeder'];
    }

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $buildingId = sanitize_int($_POST['building_id'] ?? 0);
    $elementId = sanitize_int($_POST['element_id'] ?? 0);

    // Verify ownership if not admin
    if (!has_permission($user, 'admin')) {
        if ($projectId && !user_owns_project($user['id'], $projectId)) {
            return ['success' => false, 'error' => 'Ingen adgang til dette projekt'];
        }
    }

    if (!isset($_FILES['file'])) {
        return ['success' => false, 'error' => 'Ingen fil uploaded'];
    }

    $file = $_FILES['file'];
    $validation = validate_upload($file);

    if (!$validation['valid']) {
        return ['success' => false, 'error' => $validation['error']];
    }

    // Determine upload path
    if ($projectId) {
        $uploadPath = get_project_dir($projectId) . '/uploads';
    } else {
        $uploadPath = UPLOADS_DIR . '/general';
    }

    ensure_dir($uploadPath);

    // Generate safe filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('img_') . '.' . $ext;
    $fullPath = $uploadPath . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
        return ['success' => false, 'error' => 'Kunne ikke gemme fil'];
    }

    // Process image (resize, optimize)
    process_image($fullPath);

    // Save to database
    $imageId = db_insert('images', [
        'project_id' => $projectId ?: null,
        'building_id' => $buildingId ?: null,
        'element_id' => $elementId ?: null,
        'filename' => $filename,
        'original_name' => sanitize_filename($file['name']),
        'file_size' => $file['size'],
        'mime_type' => $file['type'],
        'uploaded_by' => $user['id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);

    log_activity('image_uploaded', 'image', $imageId);

    return [
        'success' => true,
        'image' => [
            'id' => $imageId,
            'filename' => $filename,
            'url' => get_image_url($projectId, $filename)
        ]
    ];
}

function deleteImage(array $user): array {
    $imageId = sanitize_int($_POST['image_id'] ?? 0);

    $image = db_fetch("SELECT * FROM images WHERE id = :id", ['id' => $imageId]);
    if (!$image) {
        return ['success' => false, 'error' => 'Billede ikke fundet'];
    }

    // Check permissions
    if (!has_permission($user, 'admin')) {
        if ($image['project_id'] && !user_owns_project($user['id'], $image['project_id'])) {
            return ['success' => false, 'error' => 'Ingen adgang'];
        }
        if ($image['uploaded_by'] != $user['id']) {
            return ['success' => false, 'error' => 'Du kan kun slette dine egne billeder'];
        }
    }

    // Delete physical file
    $filePath = get_image_path($image['project_id'], $image['filename']);
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete from database
    db_delete('images', 'id = :id', ['id' => $imageId]);

    log_activity('image_deleted', 'image', $imageId);

    return ['success' => true];
}

function getImages(array $user): array {
    $projectId = sanitize_int($_GET['project_id'] ?? 0);
    $buildingId = sanitize_int($_GET['building_id'] ?? 0);
    $elementId = sanitize_int($_GET['element_id'] ?? 0);

    $where = [];
    $params = [];

    if ($projectId) {
        $where[] = 'project_id = :pid';
        $params['pid'] = $projectId;

        // Check access
        if (!has_permission($user, 'admin') && !user_owns_project($user['id'], $projectId)) {
            return ['success' => false, 'error' => 'Ingen adgang'];
        }
    }

    if ($buildingId) {
        $where[] = 'building_id = :bid';
        $params['bid'] = $buildingId;
    }

    if ($elementId) {
        $where[] = 'element_id = :eid';
        $params['eid'] = $elementId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $images = db_query("SELECT * FROM images $whereClause ORDER BY created_at DESC", $params);

    // Add URLs
    foreach ($images as &$img) {
        $img['url'] = get_image_url($img['project_id'], $img['filename']);
        $img['thumbnail_url'] = get_image_url($img['project_id'], 'thumb_' . $img['filename']);
    }

    return ['success' => true, 'images' => $images];
}

/* ========================================
   DRAG-AND-DROP SORTING
   ======================================== */

function updateOrder(array $user): array {
    if (!has_permission($user, 'edit_elements')) {
        return ['success' => false, 'error' => 'Ingen tilladelse'];
    }

    $items = $_POST['items'] ?? [];
    $buildingId = sanitize_int($_POST['building_id'] ?? 0);

    // Verify ownership
    if (!has_permission($user, 'admin') && $buildingId) {
        $project = db_fetch("SELECT p.* FROM projects p JOIN buildings b ON p.id = b.project_id WHERE b.id = :bid", ['bid' => $buildingId]);
        if (!$project || !user_owns_project($user['id'], $project['id'])) {
            return ['success' => false, 'error' => 'Ingen adgang'];
        }
    }

    // Update sort orders
    db_begin_transaction();
    try {
        foreach ($items as $index => $itemId) {
            db_update('building_elements', ['sort_order' => $index], 'id = :id', ['id' => sanitize_int($itemId)]);
        }
        db_commit();
        log_activity('elements_reordered', 'building', $buildingId);
        return ['success' => true];
    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rækkefølge'];
    }
}

/* ========================================
   PROJECT SNAPSHOTS
   ======================================== */

function createSnapshot(array $user): array {
    if (!has_permission($user, 'create_snapshots')) {
        return ['success' => false, 'error' => 'Ingen tilladelse til snapshots'];
    }

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $description = sanitize_string($_POST['description'] ?? '');

    // Verify ownership
    if (!has_permission($user, 'admin') && !user_owns_project($user['id'], $projectId)) {
        return ['success' => false, 'error' => 'Ingen adgang til projekt'];
    }

    // Collect all project data
    $project = db_fetch("SELECT * FROM projects WHERE id = :id", ['id' => $projectId]);
    $buildings = db_query("SELECT * FROM buildings WHERE project_id = :id", ['id' => $projectId]);
    $elements = db_query("SELECT be.* FROM building_elements be JOIN buildings b ON be.building_id = b.id WHERE b.project_id = :id", ['id' => $projectId]);

    $snapshotData = [
        'project' => $project,
        'buildings' => $buildings,
        'elements' => $elements,
        'timestamp' => date('Y-m-d H:i:s'),
        'created_by' => $user['id']
    ];

    // Save snapshot
    $snapshotId = db_insert('snapshots', [
        'project_id' => $projectId,
        'description' => $description,
        'data' => json_encode($snapshotData),
        'created_by' => $user['id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);

    // Copy uploads folder
    $snapshotDir = get_project_dir($projectId) . '/snapshots/' . $snapshotId;
    $uploadsDir = get_project_dir($projectId) . '/uploads';
    if (is_dir($uploadsDir)) {
        recursive_copy($uploadsDir, $snapshotDir . '/uploads');
    }

    log_activity('snapshot_created', 'project', $projectId);

    return ['success' => true, 'snapshot_id' => $snapshotId];
}

function restoreSnapshot(array $user): array {
    if (!has_permission($user, 'restore_snapshots')) {
        return ['success' => false, 'error' => 'Ingen tilladelse til at gendanne snapshots'];
    }

    $snapshotId = sanitize_int($_POST['snapshot_id'] ?? 0);

    $snapshot = db_fetch("SELECT * FROM snapshots WHERE id = :id", ['id' => $snapshotId]);
    if (!$snapshot) {
        return ['success' => false, 'error' => 'Snapshot ikke fundet'];
    }

    // Verify ownership
    if (!has_permission($user, 'admin') && !user_owns_project($user['id'], $snapshot['project_id'])) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    $data = json_decode($snapshot['data'], true);

    db_begin_transaction();
    try {
        // Delete current project data
        db_query("DELETE FROM building_elements WHERE building_id IN (SELECT id FROM buildings WHERE project_id = :id)", ['id' => $snapshot['project_id']]);
        db_query("DELETE FROM buildings WHERE project_id = :id", ['id' => $snapshot['project_id']]);
        
        // Restore project
        db_update('projects', $data['project'], 'id = :id', ['id' => $snapshot['project_id']]);

        // Restore buildings
        foreach ($data['buildings'] as $building) {
            $oldId = $building['id'];
            unset($building['id']);
            $newId = db_insert('buildings', $building);
            $buildingIdMap[$oldId] = $newId;
        }

        // Restore elements
        foreach ($data['elements'] as $element) {
            unset($element['id']);
            $element['building_id'] = $buildingIdMap[$element['building_id']] ?? $element['building_id'];
            db_insert('building_elements', $element);
        }

        db_commit();
        log_activity('snapshot_restored', 'project', $snapshot['project_id']);

        return ['success' => true];
    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke gendanne snapshot'];
    }
}

function listSnapshots(array $user): array {
    $projectId = sanitize_int($_GET['project_id'] ?? 0);

    // Verify ownership
    if (!has_permission($user, 'admin') && !user_owns_project($user['id'], $projectId)) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    $snapshots = db_query("
        SELECT s.id, s.name, s.description, s.snapshot_data, s.created_at, s.created_by,
               u.name as created_by_name
        FROM snapshots s
        LEFT JOIN users u ON s.created_by = u.id
        WHERE s.project_id = :id
        ORDER BY s.created_at DESC
    ", ['id' => $projectId]);

    // Parse stats from snapshot_data for each snapshot
    foreach ($snapshots as &$snapshot) {
        $data = json_decode($snapshot['snapshot_data'], true);
        $snapshot['stats'] = [
            'buildings' => count($data['buildings'] ?? []),
            'elements' => count($data['elements'] ?? []),
            'files' => count($data['files'] ?? [])
        ];
        unset($snapshot['snapshot_data']); // Don't send full data in list
    }

    return ['success' => true, 'snapshots' => $snapshots];
}

function deleteSnapshot(array $user): array {
    if (!has_permission($user, 'create_snapshots')) {
        return ['success' => false, 'error' => 'Ingen tilladelse til at slette snapshots'];
    }

    $snapshotId = sanitize_int($_POST['snapshot_id'] ?? 0);

    // Get snapshot and verify ownership
    $snapshot = db_fetch("SELECT s.*, p.user_id FROM snapshots s JOIN projects p ON s.project_id = p.id WHERE s.id = :id", ['id' => $snapshotId]);

    if (!$snapshot) {
        return ['success' => false, 'error' => 'Snapshot ikke fundet'];
    }

    // Verify ownership
    if (!has_permission($user, 'admin') && $snapshot['user_id'] != $user['id']) {
        return ['success' => false, 'error' => 'Ingen adgang til dette snapshot'];
    }

    try {
        db_delete('snapshots', 'id = :id', ['id' => $snapshotId]);
        log_activity('snapshot_deleted', 'snapshot', $snapshotId);
        return ['success' => true];
    } catch (Exception $e) {
        log_error('Snapshot delete error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Kunne ikke slette snapshot'];
    }
}

/* ========================================
   PROJECT COPYING
   ======================================== */

function copyProject(array $user): array {
    if (!has_permission($user, 'copy_projects')) {
        return ['success' => false, 'error' => 'Ingen tilladelse til at kopiere projekter'];
    }

    $projectId = sanitize_int($_POST['project_id'] ?? 0);
    $newName = sanitize_string($_POST['new_name'] ?? '');

    // Verify ownership
    if (!has_permission($user, 'admin') && !user_owns_project($user['id'], $projectId)) {
        return ['success' => false, 'error' => 'Ingen adgang'];
    }

    $project = db_fetch("SELECT * FROM projects WHERE id = :id", ['id' => $projectId]);
    if (!$project) {
        return ['success' => false, 'error' => 'Projekt ikke fundet'];
    }

    db_begin_transaction();
    try {
        // Copy project
        unset($project['id']);
        $project['name'] = $newName ?: $project['name'] . ' (Kopi)';
        $project['user_id'] = $user['id'];
        $project['created_at'] = date('Y-m-d H:i:s');
        $newProjectId = db_insert('projects', $project);

        // Copy buildings
        $buildings = db_query("SELECT * FROM buildings WHERE project_id = :id", ['id' => $projectId]);
        foreach ($buildings as $building) {
            $oldBuildingId = $building['id'];
            unset($building['id']);
            $building['project_id'] = $newProjectId;
            $building['created_at'] = date('Y-m-d H:i:s');
            $newBuildingId = db_insert('buildings', $building);
            $buildingMap[$oldBuildingId] = $newBuildingId;

            // Copy elements
            $elements = db_query("SELECT * FROM building_elements WHERE building_id = :id", ['id' => $oldBuildingId]);
            foreach ($elements as $element) {
                unset($element['id']);
                $element['building_id'] = $newBuildingId;
                $element['created_at'] = date('Y-m-d H:i:s');
                db_insert('building_elements', $element);
            }
        }

        // Copy uploads
        $sourceDir = get_project_dir($projectId) . '/uploads';
        $targetDir = get_project_dir($newProjectId) . '/uploads';
        if (is_dir($sourceDir)) {
            recursive_copy($sourceDir, $targetDir);
        }

        db_commit();
        log_activity('project_copied', 'project', $newProjectId);

        return ['success' => true, 'project_id' => $newProjectId];
    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke kopiere projekt'];
    }
}

/* ========================================
   TEMPLATES (Admin creates, all can use)
   ======================================== */

function createTemplate(array $user): array {
    if (!has_permission($user, 'admin')) {
        return ['success' => false, 'error' => 'Kun administratorer kan oprette templates'];
    }

    $type = sanitize_string($_POST['type'] ?? ''); // 'project' or 'price'
    $name = sanitize_string($_POST['name'] ?? '');
    $data = $_POST['data'] ?? [];

    $templateId = db_insert('templates', [
        'type' => $type,
        'name' => $name,
        'data' => json_encode($data),
        'created_by' => $user['id'],
        'created_at' => date('Y-m-d H:i:s')
    ]);

    log_activity('template_created', 'template', $templateId);

    return ['success' => true, 'template_id' => $templateId];
}

function applyTemplate(array $user): array {
    $templateId = sanitize_int($_POST['template_id'] ?? 0);
    $targetId = sanitize_int($_POST['target_id'] ?? 0);

    $template = db_fetch("SELECT * FROM templates WHERE id = :id", ['id' => $templateId]);
    if (!$template) {
        return ['success' => false, 'error' => 'Template ikke fundet'];
    }

    $data = json_decode($template['data'], true);

    // Apply template based on type
    if ($template['type'] === 'project' && has_permission($user, 'create_projects')) {
        // Apply project template logic
        return ['success' => true, 'message' => 'Projekt template anvendt'];
    } elseif ($template['type'] === 'price' && has_permission($user, 'edit_prices')) {
        // Apply price template logic
        return ['success' => true, 'message' => 'Pris template anvendt'];
    }

    return ['success' => false, 'error' => 'Kunne ikke anvende template'];
}

function listTemplates(array $user): array {
    $type = sanitize_string($_GET['type'] ?? '');

    $where = $type ? "WHERE type = :type" : "";
    $params = $type ? ['type' => $type] : [];

    $templates = db_query("SELECT id, type, name, created_at FROM templates $where ORDER BY name", $params);

    return ['success' => true, 'templates' => $templates];
}

/* ========================================
   USER MANAGEMENT (Admin Only)
   ======================================== */

function listUsers(array $user): array {
    if (!has_permission($user, 'admin')) {
        return ['success' => false, 'error' => 'Ingen tilladelse'];
    }

    $users = db_query("SELECT id, name, email, role, created_at, last_login FROM users ORDER BY name");

    return ['success' => true, 'users' => $users];
}

function updateUserRole(array $user): array {
    if (!has_permission($user, 'admin')) {
        return ['success' => false, 'error' => 'Kun administratorer kan ændre roller'];
    }

    $userId = sanitize_int($_POST['user_id'] ?? 0);
    $newRole = sanitize_string($_POST['role'] ?? '');

    if (!in_array($newRole, ['admin', 'user', 'viewer'])) {
        return ['success' => false, 'error' => 'Ugyldig rolle'];
    }

    db_update('users', ['role' => $newRole], 'id = :id', ['id' => $userId]);

    log_activity('user_role_updated', 'user', $userId);

    return ['success' => true];
}

/* ========================================
   NOTIFICATIONS
   ======================================== */

function getNotifications(array $user): array {
    $notifications = db_query("SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 50", ['uid' => $user['id']]);
    $unread = db_value("SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND read = false", ['uid' => $user['id']]);

    return ['success' => true, 'data' => $notifications, 'unread' => $unread];
}

function markNotificationRead(array $user): array {
    $notifId = sanitize_int($_POST['id'] ?? 0);

    db_update('notifications', ['read' => true], 'id = :id AND user_id = :uid', ['id' => $notifId, 'uid' => $user['id']]);

    return ['success' => true];
}

/* ========================================
   GLOBAL SEARCH
   ======================================== */

function globalSearch(array $user): array {
    $query = sanitize_string($_GET['q'] ?? '');
    if (strlen($query) < 2) {
        return ['success' => true, 'data' => []];
    }

    $results = [];
    $search = '%' . $query . '%';

    // Search customers (if has permission)
    if (has_permission($user, 'view_customers')) {
        $customers = db_query("SELECT id, name, 'customer' as type FROM customers WHERE name ILIKE :q LIMIT 5", ['q' => $search]);
        $results = array_merge($results, $customers);
    }

    // Search projects (only owned if not admin)
    $projectWhere = has_permission($user, 'admin') ? '' : 'AND user_id = :uid';
    $projectParams = has_permission($user, 'admin') ? ['q' => $search] : ['q' => $search, 'uid' => $user['id']];
    $projects = db_query("SELECT id, name, 'project' as type FROM projects WHERE name ILIKE :q $projectWhere LIMIT 5", $projectParams);
    $results = array_merge($results, $projects);

    // Format results
    foreach ($results as &$result) {
        $result['module'] = $result['type'];
        $result['title'] = $result['name'];
        $result['subtitle'] = ucfirst($result['type']);
        $result['icon'] = match($result['type']) {
            'customer' => '👤',
            'project' => '📁',
            'building' => '🏢',
            default => '📄'
        };
    }

    return ['success' => true, 'data' => $results];
}
