<?php
/**
 * Image/Gallery Module API
 *
 * Handles image upload, management, and gallery organization with drag-and-drop
 *
 * Actions:
 * - upload: Upload new image(s)
 * - get_list: Get images for an element
 * - get_image: Get single image details
 * - update: Update image metadata
 * - delete: Delete image
 * - reorder: Reorder images (drag-and-drop)
 * - set_primary: Set primary image
 * - bulk_upload: Upload multiple images
 * - get_gallery: Get formatted gallery for element
 * - rotate: Rotate image
 * - crop: Crop image
 */

require_once __DIR__ . '/../../core/permissions.php';

/**
 * Upload new image(s)
 * POST ?module=image&action=upload
 */
function handle_upload(array $user): array {
    csrf_require();

    $elementId = sanitize_int($_POST['element_id'] ?? 0);
    $description = sanitize_string($_POST['description'] ?? '');

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at uploade billeder'];
    }

    // Check if file was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Ingen fil uploadet eller upload fejl'];
    }

    $file = $_FILES['image'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Ugyldig filtype. Kun billeder tilladt.'];
    }

    // Validate file size (max 10MB)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'Filen er for stor. Max 10MB'];
    }

    db_begin_transaction();
    try {
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('img_') . '_' . time() . '.' . $extension;

        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../../uploads/images/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filepath = $uploadDir . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Kunne ikke gemme fil');
        }

        // Get image dimensions
        $imageInfo = getimagesize($filepath);
        $width = $imageInfo[0] ?? 0;
        $height = $imageInfo[1] ?? 0;

        // Get next display order
        $maxOrder = db_value("
            SELECT COALESCE(MAX(display_order), 0)
            FROM element_images
            WHERE element_id = :element_id
        ", ['element_id' => $elementId]);

        // Check if this should be primary (first image)
        $isPrimary = ($maxOrder == 0);

        // Save to database
        $imageData = [
            'element_id' => $elementId,
            'filename' => $filename,
            'original_filename' => $file['name'],
            'filepath' => '/uploads/images/' . $filename,
            'mime_type' => $mimeType,
            'file_size' => $file['size'],
            'width' => $width,
            'height' => $height,
            'description' => $description,
            'is_primary' => $isPrimary,
            'display_order' => $maxOrder + 1,
            'uploaded_by_user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s')
        ];

        $imageId = db_insert('element_images', $imageData);

        db_commit();

        log_activity('image_uploaded', 'image', $imageId);

        return [
            'success' => true,
            'image_id' => $imageId,
            'filepath' => '/uploads/images/' . $filename,
            'message' => 'Billede uploadet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        // Delete file if database insert failed
        if (isset($filepath) && file_exists($filepath)) {
            unlink($filepath);
        }
        return ['success' => false, 'error' => 'Kunne ikke uploade billede: ' . $e->getMessage()];
    }
}

/**
 * Get list of images for an element
 * GET ?module=image&action=get_list&element_id=X
 */
function handle_get_list(array $user): array {
    $elementId = sanitize_int($_GET['element_id'] ?? 0);
    $page = sanitize_int($_GET['page'] ?? 1);
    $limit = sanitize_int($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $element['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til elementet'];
    }

    // Get images with pagination
    $images = db_fetch_all("
        SELECT
            ei.*,
            u.name as uploaded_by_name
        FROM element_images ei
        LEFT JOIN users u ON u.id = ei.uploaded_by_user_id
        WHERE ei.element_id = :element_id
        ORDER BY ei.display_order ASC, ei.created_at ASC
        LIMIT :limit OFFSET :offset
    ", [
        'element_id' => $elementId,
        'limit' => $limit,
        'offset' => $offset
    ]);

    // Get total count
    $totalCount = db_value("
        SELECT COUNT(*) FROM element_images WHERE element_id = :element_id
    ", ['element_id' => $elementId]);

    return [
        'success' => true,
        'images' => $images,
        'pagination' => [
            'total' => (int)$totalCount,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($totalCount / $limit)
        ]
    ];
}

/**
 * Get single image details
 * GET ?module=image&action=get_image&id=X
 */
function handle_get_image(array $user): array {
    $imageId = sanitize_int($_GET['id'] ?? 0);

    if (!$imageId) {
        return ['success' => false, 'error' => 'Billede ID mangler'];
    }

    // Get image with element and project info
    $image = db_fetch("
        SELECT
            ei.*,
            be.name as element_name,
            b.project_id,
            u.name as uploaded_by_name
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        LEFT JOIN users u ON u.id = ei.uploaded_by_user_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return ['success' => false, 'error' => 'Billede ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $image['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til billedet'];
    }

    return [
        'success' => true,
        'image' => $image
    ];
}

/**
 * Update image metadata
 * POST ?module=image&action=update
 */
function handle_update(array $user): array {
    csrf_require();

    $imageId = sanitize_int($_POST['id'] ?? 0);

    if (!$imageId) {
        return ['success' => false, 'error' => 'Billede ID mangler'];
    }

    // Get image to check project access
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return ['success' => false, 'error' => 'Billede ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $image['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at redigere billede'];
    }

    db_begin_transaction();
    try {
        $updateData = [];

        if (isset($_POST['description'])) {
            $updateData['description'] = sanitize_string($_POST['description']);
        }

        if (isset($_POST['tags'])) {
            $updateData['tags'] = sanitize_string($_POST['tags']);
        }

        if (!empty($updateData)) {
            db_update('element_images', $updateData, 'id = :id', ['id' => $imageId]);
        }

        db_commit();

        log_activity('image_updated', 'image', $imageId);

        return [
            'success' => true,
            'message' => 'Billede opdateret succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere billede'];
    }
}

/**
 * Delete image
 * POST ?module=image&action=delete
 */
function handle_delete(array $user): array {
    csrf_require();

    $imageId = sanitize_int($_POST['id'] ?? 0);

    if (!$imageId) {
        return ['success' => false, 'error' => 'Billede ID mangler'];
    }

    // Get image to check project access and get file path
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return ['success' => false, 'error' => 'Billede ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $image['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at slette billede'];
    }

    db_begin_transaction();
    try {
        // Delete from database
        db_delete('element_images', 'id = :id', ['id' => $imageId]);

        // If was primary, set next image as primary
        if ($image['is_primary']) {
            db_execute("
                UPDATE element_images
                SET is_primary = TRUE
                WHERE element_id = :element_id
                    AND id != :deleted_id
                ORDER BY display_order ASC
                LIMIT 1
            ", ['element_id' => $image['element_id'], 'deleted_id' => $imageId]);
        }

        db_commit();

        // Delete physical file
        $fullPath = __DIR__ . '/../../' . ltrim($image['filepath'], '/');
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        log_activity('image_deleted', 'image', $imageId);

        return [
            'success' => true,
            'message' => 'Billede slettet succesfuldt'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke slette billede'];
    }
}

/**
 * Reorder images (drag-and-drop)
 * POST ?module=image&action=reorder
 */
function handle_reorder(array $user): array {
    csrf_require();

    $elementId = sanitize_int($_POST['element_id'] ?? 0);
    $imageIds = $_POST['image_ids'] ?? [];

    if (!$elementId || !is_array($imageIds)) {
        return ['success' => false, 'error' => 'Element ID og billede ID liste er påkrævet'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at ændre rækkefølge'];
    }

    db_begin_transaction();
    try {
        foreach ($imageIds as $order => $imageId) {
            $imageId = sanitize_int($imageId);
            db_update('element_images',
                ['display_order' => $order + 1],
                'id = :id AND element_id = :element_id',
                ['id' => $imageId, 'element_id' => $elementId]
            );
        }

        db_commit();

        log_activity('images_reordered', 'element', $elementId);

        return [
            'success' => true,
            'message' => 'Billede rækkefølge opdateret'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke opdatere rækkefølge'];
    }
}

/**
 * Set primary image
 * POST ?module=image&action=set_primary
 */
function handle_set_primary(array $user): array {
    csrf_require();

    $imageId = sanitize_int($_POST['id'] ?? 0);

    if (!$imageId) {
        return ['success' => false, 'error' => 'Billede ID mangler'];
    }

    // Get image to check project access
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return ['success' => false, 'error' => 'Billede ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $image['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at sætte primært billede'];
    }

    db_begin_transaction();
    try {
        // Unset all primary for this element
        db_execute("
            UPDATE element_images
            SET is_primary = FALSE
            WHERE element_id = :element_id
        ", ['element_id' => $image['element_id']]);

        // Set this image as primary
        db_update('element_images',
            ['is_primary' => true],
            'id = :id',
            ['id' => $imageId]
        );

        db_commit();

        log_activity('image_set_primary', 'image', $imageId);

        return [
            'success' => true,
            'message' => 'Primært billede sat'
        ];

    } catch (Exception $e) {
        db_rollback();
        return ['success' => false, 'error' => 'Kunne ikke sætte primært billede'];
    }
}

/**
 * Bulk upload multiple images
 * POST ?module=image&action=bulk_upload
 */
function handle_bulk_upload(array $user): array {
    csrf_require();

    $elementId = sanitize_int($_POST['element_id'] ?? 0);

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access (editor required)
    if (!can_access_project($user, $element['project_id'], 'editor')) {
        return ['success' => false, 'error' => 'Ingen adgang til at uploade billeder'];
    }

    if (!isset($_FILES['images']) || empty($_FILES['images']['name'])) {
        return ['success' => false, 'error' => 'Ingen filer uploadet'];
    }

    $uploadedImages = [];
    $errors = [];

    // Get starting display order
    $maxOrder = db_value("
        SELECT COALESCE(MAX(display_order), 0)
        FROM element_images
        WHERE element_id = :element_id
    ", ['element_id' => $elementId]);

    $isPrimary = ($maxOrder == 0);

    // Process each file
    $fileCount = count($_FILES['images']['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) {
            $errors[] = $_FILES['images']['name'][$i] . ': Upload fejl';
            continue;
        }

        $file = [
            'name' => $_FILES['images']['name'][$i],
            'type' => $_FILES['images']['type'][$i],
            'tmp_name' => $_FILES['images']['tmp_name'][$i],
            'size' => $_FILES['images']['size'][$i]
        ];

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            $errors[] = $file['name'] . ': Ugyldig filtype';
            continue;
        }

        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = $file['name'] . ': For stor (max 10MB)';
            continue;
        }

        db_begin_transaction();
        try {
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('img_') . '_' . time() . '_' . $i . '.' . $extension;
            $uploadDir = __DIR__ . '/../../uploads/images/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filepath = $uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Kunne ikke gemme fil');
            }

            $imageInfo = getimagesize($filepath);

            $imageData = [
                'element_id' => $elementId,
                'filename' => $filename,
                'original_filename' => $file['name'],
                'filepath' => '/uploads/images/' . $filename,
                'mime_type' => $mimeType,
                'file_size' => $file['size'],
                'width' => $imageInfo[0] ?? 0,
                'height' => $imageInfo[1] ?? 0,
                'is_primary' => $isPrimary,
                'display_order' => ++$maxOrder,
                'uploaded_by_user_id' => $user['id'],
                'created_at' => date('Y-m-d H:i:s')
            ];

            $imageId = db_insert('element_images', $imageData);

            db_commit();

            $uploadedImages[] = [
                'id' => $imageId,
                'filename' => $filename,
                'filepath' => '/uploads/images/' . $filename
            ];

            $isPrimary = false; // Only first image is primary

        } catch (Exception $e) {
            db_rollback();
            if (isset($filepath) && file_exists($filepath)) {
                unlink($filepath);
            }
            $errors[] = $file['name'] . ': ' . $e->getMessage();
        }
    }

    log_activity('images_bulk_uploaded', 'element', $elementId);

    return [
        'success' => true,
        'uploaded' => count($uploadedImages),
        'images' => $uploadedImages,
        'errors' => $errors,
        'message' => count($uploadedImages) . ' billeder uploadet' . (empty($errors) ? '' : ' med ' . count($errors) . ' fejl')
    ];
}

/**
 * Get formatted gallery for element
 * GET ?module=image&action=get_gallery&element_id=X
 */
function handle_get_gallery(array $user): array {
    $elementId = sanitize_int($_GET['element_id'] ?? 0);

    if (!$elementId) {
        return ['success' => false, 'error' => 'Element ID mangler'];
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return ['success' => false, 'error' => 'Element ikke fundet'];
    }

    // Check project access
    if (!can_access_project($user, $element['project_id'], 'viewer')) {
        return ['success' => false, 'error' => 'Ingen adgang til elementet'];
    }

    // Get all images
    $images = db_fetch_all("
        SELECT
            id,
            filename,
            filepath,
            description,
            width,
            height,
            is_primary,
            display_order,
            created_at
        FROM element_images
        WHERE element_id = :element_id
        ORDER BY display_order ASC, created_at ASC
    ", ['element_id' => $elementId]);

    // Separate primary and other images
    $primary = null;
    $gallery = [];

    foreach ($images as $image) {
        if ($image['is_primary']) {
            $primary = $image;
        } else {
            $gallery[] = $image;
        }
    }

    return [
        'success' => true,
        'primary' => $primary,
        'gallery' => $gallery,
        'total' => count($images)
    ];
}
