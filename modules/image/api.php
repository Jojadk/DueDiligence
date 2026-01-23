<?php
/**
 * Image/Gallery Module API
 *
 * Handles image upload, management, and gallery organization with drag-and-drop
 * Supports WebP compression and image annotations (overlay-based, non-destructive)
 *
 * Actions:
 * - upload: Upload new image(s) with WebP conversion
 * - get_list: Get images for an element
 * - get_image: Get single image details
 * - update: Update image metadata
 * - delete: Delete image
 * - reorder: Reorder images (drag-and-drop)
 * - set_primary: Set primary image
 * - bulk_upload: Upload multiple images
 * - get_gallery: Get formatted gallery for element
 *
 * Annotation Actions (Non-destructive - preserves original image):
 * - save_annotations: Save annotations as JSON (for frontend overlay rendering)
 * - get_annotations: Get annotations for image
 * - get_annotation_tools: Get available annotation tools configuration
 * - export_annotated: Export image with annotations baked in (optional, for reports)
 *
 * Supported Annotation Types:
 * - text: Text with background
 * - line: Simple line
 * - rectangle: Rectangle (filled or outline)
 * - circle: Circle (filled or outline)
 * - arrow: Arrow line
 * - blur: Blur region
 * - flag: Flag marker (red, yellow, green, etc.)
 * - measurement: Measurement tool with text label and line (e.g., "1 mtr")
 */

require_once __DIR__ . '/../../core/permissions.php';
require_once __DIR__ . '/../../core/api-helpers.php';

/**
 * Upload new image(s)
 * POST ?module=image&action=upload
 */
function handle_upload(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'element_id' => ['int', 'POST', true],
        'description' => ['string', 'POST', false, '']
    ]);
    if (!$validation['success']) return $validation;

    $elementId = $validation['data']['element_id'];
    $description = $validation['data']['description'];

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $element['project_id'], 'editor');
    if (!$accessCheck['success']) return $accessCheck;

    // Check if file was uploaded
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return api_error('Ingen fil uploadet eller upload fejl');
    }

    $file = $_FILES['image'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return api_error('Ugyldig filtype. Kun billeder tilladt.');
    }

    // Validate file size (max 10MB)
    $maxSize = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $maxSize) {
        return api_error('Filen er for stor. Max 10MB');
    }

    return api_transaction(function() use ($user, $elementId, $description, $file, $mimeType) {
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

        log_activity('image_uploaded', 'image', $imageId);

        return [
            'success' => true,
            'image_id' => $imageId,
            'filepath' => '/uploads/images/' . $filename,
            'message' => 'Billede uploadet succesfuldt'
        ];

    }, function($filepath) {
        // Cleanup callback on rollback - delete file if database insert failed
        if (isset($filepath) && file_exists($filepath)) {
            unlink($filepath);
        }
    });
}

/**
 * Get list of images for an element
 * GET ?module=image&action=get_list&element_id=X
 */
function handle_get_list(array $user): array {
    $validation = api_validate_params([
        'element_id' => ['int', 'GET', true],
        'page' => ['int', 'GET', false, 1],
        'limit' => ['int', 'GET', false, 50]
    ]);
    if (!$validation['success']) return $validation;

    $elementId = $validation['data']['element_id'];
    $page = $validation['data']['page'];
    $limit = $validation['data']['limit'];
    $offset = ($page - 1) * $limit;

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    // Check project access
    $accessCheck = api_require_project_access($user, $element['project_id'], 'viewer');
    if (!$accessCheck['success']) return $accessCheck;

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
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);
    if (!$validation['success']) return $validation;

    $imageId = $validation['data']['id'];

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
        return api_error('Billede ikke fundet');
    }

    // Check project access
    $accessCheck = api_require_project_access($user, $image['project_id'], 'viewer');
    if (!$accessCheck['success']) return $accessCheck;

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
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true],
        'description' => ['string', 'POST', false]
    ]);
    if (!$validation['success']) return $validation;

    $imageId = $validation['data']['id'];

    // Get image to check project access
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return api_error('Billede ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $image['project_id'], 'editor');
    if (!$accessCheck['success']) return $accessCheck;

    $updateData = [];
    if (isset($validation['data']['description'])) {
        $updateData['description'] = $validation['data']['description'];
    }

    if (empty($updateData)) {
        return api_error('Ingen data at opdatere');
    }

    return api_transaction(function() use ($imageId, $updateData) {
        db_update('element_images', $updateData, 'id = :id', ['id' => $imageId]);

        log_activity('image_updated', 'image', $imageId);

        return [
            'success' => true,
            'message' => 'Billede opdateret succesfuldt'
        ];
    });
}

/**
 * Delete image
 * POST ?module=image&action=delete
 */
function handle_delete(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;

    $imageId = $validation['data']['id'];

    // Get image to check project access and get file path
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return api_error('Billede ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $image['project_id'], 'editor');
    if (!$accessCheck['success']) return $accessCheck;

    return api_transaction(function() use ($imageId, $image) {
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
    });
}

/**
 * Reorder images (drag-and-drop)
 * POST ?module=image&action=reorder
 */
function handle_reorder(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'element_id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;

    $elementId = $validation['data']['element_id'];
    $imageIds = $_POST['image_ids'] ?? [];

    if (!is_array($imageIds)) {
        return api_error('Billede ID liste er påkrævet');
    }

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $element['project_id'], 'editor');
    if (!$accessCheck['success']) return $accessCheck;

    return api_transaction(function() use ($elementId, $imageIds) {
        foreach ($imageIds as $order => $imageId) {
            $imageId = sanitize_int($imageId);
            db_update('element_images',
                ['display_order' => $order + 1],
                'id = :id AND element_id = :element_id',
                ['id' => $imageId, 'element_id' => $elementId]
            );
        }

        log_activity('images_reordered', 'element', $elementId);

        return [
            'success' => true,
            'message' => 'Billede rækkefølge opdateret'
        ];
    });
}

/**
 * Set primary image
 * POST ?module=image&action=set_primary
 */
function handle_set_primary(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;

    $imageId = $validation['data']['id'];

    // Get image to check project access
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return api_error('Billede ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $image['project_id'], 'editor');
    if (!$accessCheck['success']) return $accessCheck;

    return api_transaction(function() use ($imageId, $image) {
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

        log_activity('image_set_primary', 'image', $imageId);

        return [
            'success' => true,
            'message' => 'Primært billede sat'
        ];
    });
}

/**
 * Bulk upload multiple images
 * POST ?module=image&action=bulk_upload
 */
function handle_bulk_upload(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'element_id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;

    $elementId = $validation['data']['element_id'];

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $element['project_id'], 'editor');
    if (!$accessCheck['success']) return $accessCheck;

    if (!isset($_FILES['images']) || empty($_FILES['images']['name'])) {
        return api_error('Ingen filer uploadet');
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
    $validation = api_validate_params([
        'element_id' => ['int', 'GET', true]
    ]);
    if (!$validation['success']) return $validation;

    $elementId = $validation['data']['element_id'];

    // Get element to check project access
    $element = db_fetch("
        SELECT be.*, b.project_id
        FROM building_elements be
        JOIN buildings b ON b.id = be.building_id
        WHERE be.id = :id
    ", ['id' => $elementId]);

    if (!$element) {
        return api_error('Element ikke fundet');
    }

    // Check project access
    $accessCheck = api_require_project_access($user, $element['project_id'], 'viewer');
    if (!$accessCheck['success']) return $accessCheck;

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

/**
 * Save annotations for image
 * POST ?module=image&action=save_annotations
 */
function handle_save_annotations(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;

    $imageId = $validation['data']['id'];
    $annotations = $_POST['annotations'] ?? '';

    // Get image to check project access
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return api_error('Billede ikke fundet');
    }

    // Check project access (editor required)
    $accessCheck = api_require_project_access($user, $image['project_id'], 'editor');
    if (!$accessCheck['success']) return $accessCheck;

    return api_transaction(function() use ($imageId, $annotations) {
        // Validate JSON
        $annotationsData = json_decode($annotations, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Ugyldig JSON');
        }

        db_update('element_images',
            ['annotations' => $annotations],
            'id = :id',
            ['id' => $imageId]
        );

        log_activity('image_annotations_saved', 'image', $imageId);

        return [
            'success' => true,
            'message' => 'Annotations gemt'
        ];
    });
}

/**
 * Get annotations for image
 * GET ?module=image&action=get_annotations&id=X
 */
function handle_get_annotations(array $user): array {
    $validation = api_validate_params([
        'id' => ['int', 'GET', true]
    ]);
    if (!$validation['success']) return $validation;

    $imageId = $validation['data']['id'];

    // Get image
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return api_error('Billede ikke fundet');
    }

    // Check project access
    $accessCheck = api_require_project_access($user, $image['project_id'], 'viewer');
    if (!$accessCheck['success']) return $accessCheck;

    $annotations = $image['annotations'] ? json_decode($image['annotations'], true) : [];

    return [
        'success' => true,
        'annotations' => $annotations
    ];
}

/**
 * Get annotation tools configuration
 * GET ?module=image&action=get_annotation_tools
 */
function handle_get_annotation_tools(array $user): array {
    $tools = [
        [
            'id' => 'text',
            'name' => 'Tekst',
            'icon' => 'text',
            'settings' => [
                'color' => ['type' => 'color', 'default' => '#000000', 'label' => 'Tekstfarve'],
                'backgroundColor' => ['type' => 'color', 'default' => '#FFFF00', 'label' => 'Baggrundsfarve'],
                'size' => ['type' => 'number', 'default' => 16, 'min' => 8, 'max' => 72, 'label' => 'Skriftstørrelse'],
                'text' => ['type' => 'text', 'default' => '', 'label' => 'Tekst']
            ]
        ],
        [
            'id' => 'line',
            'name' => 'Linje',
            'icon' => 'line',
            'settings' => [
                'color' => ['type' => 'color', 'default' => '#FF0000', 'label' => 'Farve'],
                'width' => ['type' => 'number', 'default' => 2, 'min' => 1, 'max' => 20, 'label' => 'Tykkelse']
            ]
        ],
        [
            'id' => 'arrow',
            'name' => 'Pil',
            'icon' => 'arrow',
            'settings' => [
                'color' => ['type' => 'color', 'default' => '#FF0000', 'label' => 'Farve'],
                'width' => ['type' => 'number', 'default' => 2, 'min' => 1, 'max' => 20, 'label' => 'Tykkelse']
            ]
        ],
        [
            'id' => 'rectangle',
            'name' => 'Firkant',
            'icon' => 'rectangle',
            'settings' => [
                'color' => ['type' => 'color', 'default' => '#FF0000', 'label' => 'Farve'],
                'filled' => ['type' => 'boolean', 'default' => false, 'label' => 'Udfyldt'],
                'lineWidth' => ['type' => 'number', 'default' => 2, 'min' => 1, 'max' => 20, 'label' => 'Tykkelse']
            ]
        ],
        [
            'id' => 'circle',
            'name' => 'Cirkel',
            'icon' => 'circle',
            'settings' => [
                'color' => ['type' => 'color', 'default' => '#FF0000', 'label' => 'Farve'],
                'filled' => ['type' => 'boolean', 'default' => false, 'label' => 'Udfyldt'],
                'lineWidth' => ['type' => 'number', 'default' => 2, 'min' => 1, 'max' => 20, 'label' => 'Tykkelse']
            ]
        ],
        [
            'id' => 'blur',
            'name' => 'Blur',
            'icon' => 'blur',
            'settings' => [
                'intensity' => ['type' => 'number', 'default' => 10, 'min' => 1, 'max' => 50, 'label' => 'Intensitet']
            ]
        ],
        [
            'id' => 'flag',
            'name' => 'Flag',
            'icon' => 'flag',
            'settings' => [
                'flagType' => [
                    'type' => 'select',
                    'default' => 'red',
                    'options' => [
                        ['value' => 'red', 'label' => 'Rød (Kritisk)', 'color' => '#DC2626'],
                        ['value' => 'yellow', 'label' => 'Gul (Alvorlig)', 'color' => '#FBBF24'],
                        ['value' => 'orange', 'label' => 'Orange (Moderat)', 'color' => '#F97316'],
                        ['value' => 'green', 'label' => 'Grøn (Mindre)', 'color' => '#10B981'],
                        ['value' => 'blue', 'label' => 'Blå (Info)', 'color' => '#3B82F6']
                    ],
                    'label' => 'Flag type'
                ],
                'size' => ['type' => 'number', 'default' => 24, 'min' => 16, 'max' => 64, 'label' => 'Størrelse'],
                'label' => ['type' => 'text', 'default' => '', 'label' => 'Etiket (valgfri)']
            ]
        ],
        [
            'id' => 'measurement',
            'name' => 'Måleværktøj',
            'icon' => 'ruler',
            'settings' => [
                'color' => ['type' => 'color', 'default' => '#2563EB', 'label' => 'Farve'],
                'width' => ['type' => 'number', 'default' => 2, 'min' => 1, 'max' => 10, 'label' => 'Linjetykkelse'],
                'text' => ['type' => 'text', 'default' => '1 mtr', 'label' => 'Måling'],
                'textSize' => ['type' => 'number', 'default' => 14, 'min' => 8, 'max' => 32, 'label' => 'Tekststørrelse'],
                'showEnds' => ['type' => 'boolean', 'default' => true, 'label' => 'Vis endepunkter (|—|)']
            ]
        ]
    ];

    return [
        'success' => true,
        'tools' => $tools,
        'responsive' => [
            'mobile' => [
                'touch_optimized' => true,
                'min_touch_size' => 44, // 44x44 pixels for touch targets
                'gesture_support' => ['pan', 'pinch_zoom', 'tap', 'long_press']
            ],
            'tablet' => [
                'stylus_support' => true,
                'precision_mode' => true
            ],
            'desktop' => [
                'keyboard_shortcuts' => true,
                'mouse_precision' => true
            ]
        ]
    ];
}

/**
 * Export image with annotations baked in (for reports/export)
 * POST ?module=image&action=export_annotated
 * Note: This creates a NEW image file. Original image is preserved.
 */
function handle_export_annotated(array $user): array {
    $csrfCheck = api_require_csrf();
    if (!$csrfCheck['success']) return $csrfCheck;

    $validation = api_validate_params([
        'id' => ['int', 'POST', true]
    ]);
    if (!$validation['success']) return $validation;

    $imageId = $validation['data']['id'];

    // Get image
    $image = db_fetch("
        SELECT ei.*, be.id as element_id, b.project_id
        FROM element_images ei
        JOIN building_elements be ON be.id = ei.element_id
        JOIN buildings b ON b.id = be.building_id
        WHERE ei.id = :id
    ", ['id' => $imageId]);

    if (!$image) {
        return api_error('Billede ikke fundet');
    }

    // Check project access
    $accessCheck = api_require_project_access($user, $image['project_id'], 'editor');
    if (!$accessCheck['success']) return $accessCheck;

    if (!$image['annotations']) {
        return api_error('Ingen annotations at rendre');
    }

    try {
        $annotations = json_decode($image['annotations'], true);
        $sourcePath = __DIR__ . '/../../' . ltrim($image['filepath'], '/');

        // Render annotations on image
        $renderedPath = render_annotations_on_image($sourcePath, $annotations);

        // Convert to WebP
        $webpPath = convert_to_webp($renderedPath);

        // Create NEW database entry for exported image (preserves original)
        $filename = basename($webpPath);
        $filesize = filesize($webpPath);
        $imageInfo = getimagesize($webpPath);

        $exportedImageData = [
            'element_id' => $image['element_id'],
            'filename' => $filename,
            'original_filename' => 'annotated_' . $image['original_filename'],
            'filepath' => '/uploads/images/' . $filename,
            'mime_type' => 'image/webp',
            'file_size' => $filesize,
            'width' => $imageInfo[0] ?? 0,
            'height' => $imageInfo[1] ?? 0,
            'description' => ($image['description'] ?? '') . ' (Med annotations)',
            'is_primary' => false,
            'display_order' => 9999,
            'uploaded_by_user_id' => $user['id'],
            'created_at' => date('Y-m-d H:i:s'),
            'annotations_rendered' => true
        ];

        $exportedImageId = db_insert('element_images', $exportedImageData);

        // Delete temporary rendered file if different from webp
        if ($renderedPath !== $webpPath && file_exists($renderedPath)) {
            unlink($renderedPath);
        }

        log_activity('image_annotations_exported', 'image', $imageId);

        return [
            'success' => true,
            'exported_image_id' => $exportedImageId,
            'filepath' => '/uploads/images/' . $filename,
            'message' => 'Billede eksporteret med annotations (original bevaret)'
        ];

    } catch (Exception $e) {
        return api_error('Kunne ikke eksportere billede: ' . $e->getMessage());
    }
}

/**
 * Helper: Render annotations on image
 */
function render_annotations_on_image(string $sourcePath, array $annotations): string {
    // Load source image
    $imageInfo = getimagesize($sourcePath);
    $mimeType = $imageInfo['mime'];

    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            $source = imagecreatefromwebp($sourcePath);
            break;
        default:
            throw new Exception('Unsupported image format');
    }

    // Enable alpha blending
    imagealphablending($source, true);
    imagesavealpha($source, true);

    // Process each annotation
    foreach ($annotations as $annotation) {
        $type = $annotation['type'] ?? '';

        switch ($type) {
            case 'text':
                render_text_annotation($source, $annotation);
                break;
            case 'line':
                render_line_annotation($source, $annotation);
                break;
            case 'rectangle':
                render_rectangle_annotation($source, $annotation);
                break;
            case 'circle':
                render_circle_annotation($source, $annotation);
                break;
            case 'arrow':
                render_arrow_annotation($source, $annotation);
                break;
            case 'blur':
                render_blur_annotation($source, $annotation);
                break;
            case 'flag':
                render_flag_annotation($source, $annotation);
                break;
            case 'measurement':
                render_measurement_annotation($source, $annotation);
                break;
        }
    }

    // Save rendered image
    $outputPath = pathinfo($sourcePath, PATHINFO_DIRNAME) . '/annotated_' . uniqid() . '.png';
    imagepng($source, $outputPath);
    imagedestroy($source);

    return $outputPath;
}

/**
 * Helper: Render text annotation
 */
function render_text_annotation($image, array $data) {
    $x = $data['x'] ?? 0;
    $y = $data['y'] ?? 0;
    $text = $data['text'] ?? '';
    $color = $data['color'] ?? '#000000';
    $size = $data['size'] ?? 12;
    $bgColor = $data['backgroundColor'] ?? null;

    // Parse color
    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
    $textColor = imagecolorallocate($image, $r, $g, $b);

    // Background if specified
    if ($bgColor) {
        list($bgR, $bgG, $bgB) = sscanf($bgColor, "#%02x%02x%02x");
        $bgColorAllocated = imagecolorallocatealpha($image, $bgR, $bgG, $bgB, 50);

        $bbox = imagettfbbox($size, 0, __DIR__ . '/../../fonts/arial.ttf', $text);
        $textWidth = abs($bbox[4] - $bbox[0]);
        $textHeight = abs($bbox[5] - $bbox[1]);

        imagefilledrectangle($image, $x - 2, $y - $textHeight - 2, $x + $textWidth + 2, $y + 2, $bgColorAllocated);
    }

    // Render text
    imagettftext($image, $size, 0, $x, $y, $textColor, __DIR__ . '/../../fonts/arial.ttf', $text);
}

/**
 * Helper: Render line annotation
 */
function render_line_annotation($image, array $data) {
    $x1 = $data['x1'] ?? 0;
    $y1 = $data['y1'] ?? 0;
    $x2 = $data['x2'] ?? 100;
    $y2 = $data['y2'] ?? 100;
    $color = $data['color'] ?? '#FF0000';
    $width = $data['width'] ?? 2;

    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
    $lineColor = imagecolorallocate($image, $r, $g, $b);

    imagesetthickness($image, $width);
    imageline($image, $x1, $y1, $x2, $y2, $lineColor);
    imagesetthickness($image, 1);
}

/**
 * Helper: Render rectangle annotation
 */
function render_rectangle_annotation($image, array $data) {
    $x = $data['x'] ?? 0;
    $y = $data['y'] ?? 0;
    $width = $data['width'] ?? 100;
    $height = $data['height'] ?? 100;
    $color = $data['color'] ?? '#FF0000';
    $filled = $data['filled'] ?? false;
    $lineWidth = $data['lineWidth'] ?? 2;

    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");

    if ($filled) {
        $fillColor = imagecolorallocatealpha($image, $r, $g, $b, 80);
        imagefilledrectangle($image, $x, $y, $x + $width, $y + $height, $fillColor);
    } else {
        $lineColor = imagecolorallocate($image, $r, $g, $b);
        imagesetthickness($image, $lineWidth);
        imagerectangle($image, $x, $y, $x + $width, $y + $height, $lineColor);
        imagesetthickness($image, 1);
    }
}

/**
 * Helper: Render circle annotation
 */
function render_circle_annotation($image, array $data) {
    $x = $data['x'] ?? 50;
    $y = $data['y'] ?? 50;
    $radius = $data['radius'] ?? 50;
    $color = $data['color'] ?? '#FF0000';
    $filled = $data['filled'] ?? false;
    $lineWidth = $data['lineWidth'] ?? 2;

    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");

    if ($filled) {
        $fillColor = imagecolorallocatealpha($image, $r, $g, $b, 80);
        imagefilledellipse($image, $x, $y, $radius * 2, $radius * 2, $fillColor);
    } else {
        $lineColor = imagecolorallocate($image, $r, $g, $b);
        imagesetthickness($image, $lineWidth);
        imageellipse($image, $x, $y, $radius * 2, $radius * 2, $lineColor);
        imagesetthickness($image, 1);
    }
}

/**
 * Helper: Render arrow annotation
 */
function render_arrow_annotation($image, array $data) {
    // Draw line first
    render_line_annotation($image, $data);

    // Draw arrowhead
    $x2 = $data['x2'] ?? 100;
    $y2 = $data['y2'] ?? 100;
    $x1 = $data['x1'] ?? 0;
    $y1 = $data['y1'] ?? 0;
    $color = $data['color'] ?? '#FF0000';

    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
    $arrowColor = imagecolorallocate($image, $r, $g, $b);

    // Calculate arrow angle
    $angle = atan2($y2 - $y1, $x2 - $x1);
    $arrowLength = 15;
    $arrowAngle = M_PI / 6;

    $points = [
        $x2, $y2,
        $x2 - $arrowLength * cos($angle - $arrowAngle), $y2 - $arrowLength * sin($angle - $arrowAngle),
        $x2 - $arrowLength * cos($angle + $arrowAngle), $y2 - $arrowLength * sin($angle + $arrowAngle)
    ];

    imagefilledpolygon($image, $points, 3, $arrowColor);
}

/**
 * Helper: Render blur annotation
 */
function render_blur_annotation($image, array $data) {
    $x = $data['x'] ?? 0;
    $y = $data['y'] ?? 0;
    $width = $data['width'] ?? 100;
    $height = $data['height'] ?? 100;
    $intensity = $data['intensity'] ?? 10;

    // Create region to blur
    $region = imagecreatetruecolor($width, $height);
    imagecopy($region, $image, 0, 0, $x, $y, $width, $height);

    // Apply blur
    for ($i = 0; $i < $intensity; $i++) {
        imagefilter($region, IMG_FILTER_GAUSSIAN_BLUR);
    }

    // Copy blurred region back
    imagecopy($image, $region, $x, $y, 0, 0, $width, $height);
    imagedestroy($region);
}

/**
 * Helper: Render flag annotation
 */
function render_flag_annotation($image, array $data) {
    $x = $data['x'] ?? 50;
    $y = $data['y'] ?? 50;
    $flagType = $data['flagType'] ?? 'red';
    $size = $data['size'] ?? 24;
    $label = $data['label'] ?? '';

    // Map flag types to colors
    $flagColors = [
        'red' => '#DC2626',
        'yellow' => '#FBBF24',
        'orange' => '#F97316',
        'green' => '#10B981',
        'blue' => '#3B82F6'
    ];

    $colorHex = $flagColors[$flagType] ?? '#DC2626';
    list($r, $g, $b) = sscanf($colorHex, "#%02x%02x%02x");
    $flagColor = imagecolorallocate($image, $r, $g, $b);

    // Draw flag shape (triangular flag on pole)
    $poleHeight = $size * 1.5;
    $flagWidth = $size;
    $flagHeight = $size * 0.6;

    // Draw pole (line)
    imagesetthickness($image, 2);
    imageline($image, $x, $y, $x, $y + $poleHeight, $flagColor);

    // Draw flag (triangle)
    $flagPoints = [
        $x, $y,                          // Top of pole
        $x + $flagWidth, $y + $flagHeight / 2,  // Right point
        $x, $y + $flagHeight             // Bottom of flag
    ];
    imagefilledpolygon($image, $flagPoints, 3, $flagColor);

    // Add dark border to flag
    $borderColor = imagecolorallocate($image, 0, 0, 0);
    imagesetthickness($image, 1);
    imagepolygon($image, $flagPoints, 3, $borderColor);

    // Reset thickness
    imagesetthickness($image, 1);

    // Draw label if specified
    if ($label) {
        $textSize = 10;
        $textColor = imagecolorallocate($image, 0, 0, 0);
        $bgColor = imagecolorallocatealpha($image, 255, 255, 255, 30);

        // Background rectangle for text
        $textX = $x + $flagWidth + 5;
        $textY = $y + 15;
        $textWidth = strlen($label) * $textSize * 0.6;
        $textHeight = $textSize + 4;

        imagefilledrectangle($image, $textX - 2, $textY - $textHeight, $textX + $textWidth, $textY + 2, $bgColor);
        imagestring($image, 3, $textX, $textY - $textHeight + 2, $label, $textColor);
    }
}

/**
 * Helper: Render measurement annotation
 */
function render_measurement_annotation($image, array $data) {
    $x1 = $data['x1'] ?? 0;
    $y1 = $data['y1'] ?? 0;
    $x2 = $data['x2'] ?? 100;
    $y2 = $data['y2'] ?? 100;
    $color = $data['color'] ?? '#2563EB';
    $width = $data['width'] ?? 2;
    $text = $data['text'] ?? '1 mtr';
    $textSize = $data['textSize'] ?? 14;
    $showEnds = $data['showEnds'] ?? true;

    list($r, $g, $b) = sscanf($color, "#%02x%02x%02x");
    $lineColor = imagecolorallocate($image, $r, $g, $b);
    $textColor = imagecolorallocate($image, $r, $g, $b);
    $bgColor = imagecolorallocatealpha($image, 255, 255, 255, 30);

    // Draw main measurement line
    imagesetthickness($image, $width);
    imageline($image, $x1, $y1, $x2, $y2, $lineColor);

    // Draw end markers if enabled (perpendicular lines at both ends)
    if ($showEnds) {
        $angle = atan2($y2 - $y1, $x2 - $x1);
        $perpAngle = $angle + M_PI / 2;
        $endLength = 10;

        // Start end marker
        $sx1 = $x1 + $endLength * cos($perpAngle);
        $sy1 = $y1 + $endLength * sin($perpAngle);
        $sx2 = $x1 - $endLength * cos($perpAngle);
        $sy2 = $y1 - $endLength * sin($perpAngle);
        imageline($image, $sx1, $sy1, $sx2, $sy2, $lineColor);

        // End end marker
        $ex1 = $x2 + $endLength * cos($perpAngle);
        $ey1 = $y2 + $endLength * sin($perpAngle);
        $ex2 = $x2 - $endLength * cos($perpAngle);
        $ey2 = $y2 - $endLength * sin($perpAngle);
        imageline($image, $ex1, $ey1, $ex2, $ey2, $lineColor);
    }

    imagesetthickness($image, 1);

    // Draw text above the line (centered)
    $midX = ($x1 + $x2) / 2;
    $midY = ($y1 + $y2) / 2;

    // Calculate text position above line
    $angle = atan2($y2 - $y1, $x2 - $x1);
    $perpAngle = $angle - M_PI / 2;
    $textOffset = 15;
    $textX = $midX + $textOffset * cos($perpAngle);
    $textY = $midY + $textOffset * sin($perpAngle);

    // Draw text background
    $textWidth = strlen($text) * $textSize * 0.6;
    $textHeight = $textSize + 4;
    imagefilledrectangle(
        $image,
        $textX - $textWidth / 2 - 2,
        $textY - $textHeight - 2,
        $textX + $textWidth / 2 + 2,
        $textY + 2,
        $bgColor
    );

    // Draw text (centered)
    imagestring($image, 3, $textX - strlen($text) * 3, $textY - $textHeight + 2, $text, $textColor);
}

/**
 * Helper: Convert image to WebP
 */
function convert_to_webp(string $sourcePath, int $quality = 80): string {
    $imageInfo = getimagesize($sourcePath);
    $mimeType = $imageInfo['mime'];

    switch ($mimeType) {
        case 'image/jpeg':
        case 'image/jpg':
            $source = imagecreatefromjpeg($sourcePath);
            break;
        case 'image/png':
            $source = imagecreatefrompng($sourcePath);
            break;
        case 'image/gif':
            $source = imagecreatefromgif($sourcePath);
            break;
        case 'image/webp':
            // Already WebP, return as is
            return $sourcePath;
        default:
            throw new Exception('Unsupported image format for WebP conversion');
    }

    // Generate WebP filename
    $webpPath = preg_replace('/\.[^.]+$/', '.webp', $sourcePath);

    // Convert to WebP
    imagewebp($source, $webpPath, $quality);
    imagedestroy($source);

    // Delete original if different
    if ($sourcePath !== $webpPath && file_exists($sourcePath)) {
        unlink($sourcePath);
    }

    return $webpPath;
}
