<?php
/**
 * WYSIWYG Editor Module API
 *
 * Handles rich text editing configuration and content processing
 *
 * Actions:
 * - get_config: Get editor configuration
 * - upload_image: Upload image for editor
 * - process_content: Process and sanitize WYSIWYG content
 * - get_templates: Get content templates
 */

require_once __DIR__ . '/../../core/permissions.php';

/**
 * Get WYSIWYG editor configuration
 * GET ?module=wysiwyg&action=get_config
 */
function handle_get_config(array $user): array {
    $config = [
        'toolbar' => [
            ['bold', 'italic', 'underline', 'strikethrough'],
            ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
            ['unorderedlist', 'orderedlist'],
            ['indent', 'outdent'],
            ['link', 'image'],
            ['alignleft', 'aligncenter', 'alignright', 'alignjustify'],
            ['table'],
            ['removeformat', 'code'],
            ['undo', 'redo']
        ],
        'plugins' => [
            'lists',
            'link',
            'image',
            'table',
            'code'
        ],
        'menubar' => false,
        'statusbar' => true,
        'resize' => true,
        'autoresize' => true,
        'content_style' => 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; font-size: 14px; line-height: 1.5; }',
        'paste_as_text' => false,
        'paste_webkit_styles' => 'all',
        'formats' => [
            'bold' => ['inline' => 'strong'],
            'italic' => ['inline' => 'em'],
            'underline' => ['inline' => 'u'],
            'strikethrough' => ['inline' => 's']
        ]
    ];

    return [
        'success' => true,
        'config' => $config
    ];
}

/**
 * Upload image for WYSIWYG editor
 * POST ?module=wysiwyg&action=upload_image
 */
function handle_upload_image(array $user): array {
    csrf_require();

    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Ingen fil uploadet'];
    }

    $file = $_FILES['image'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Ugyldig filtype'];
    }

    // Validate file size (max 5MB for editor images)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Fil for stor (max 5MB)'];
    }

    try {
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('editor_') . '_' . time() . '.' . $extension;

        $uploadDir = __DIR__ . '/../../uploads/editor/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filepath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception('Kunne ikke gemme fil');
        }

        log_activity('editor_image_uploaded', 'wysiwyg', 0);

        return [
            'success' => true,
            'url' => '/uploads/editor/' . $filename,
            'filename' => $filename
        ];

    } catch (Exception $e) {
        return ['success' => false, 'error' => 'Upload fejlede: ' . $e->getMessage()];
    }
}

/**
 * Process and sanitize WYSIWYG content
 * POST ?module=wysiwyg&action=process_content
 */
function handle_process_content(array $user): array {
    csrf_require();

    $content = $_POST['content'] ?? '';

    if (empty($content)) {
        return ['success' => false, 'error' => 'Intet indhold'];
    }

    // Sanitize HTML content
    $sanitized = sanitize_html($content);

    return [
        'success' => true,
        'content' => $sanitized
    ];
}

/**
 * Get content templates
 * GET ?module=wysiwyg&action=get_templates
 */
function handle_get_templates(array $user): array {
    $templates = [
        [
            'name' => 'Tom side',
            'content' => ''
        ],
        [
            'name' => 'Overskrift med tekst',
            'content' => '<h2>Overskrift</h2><p>Indtast din tekst her...</p>'
        ],
        [
            'name' => 'Punktopstilling',
            'content' => '<h2>Overskrift</h2><ul><li>Punkt 1</li><li>Punkt 2</li><li>Punkt 3</li></ul>'
        ],
        [
            'name' => 'Nummereret liste',
            'content' => '<h2>Overskrift</h2><ol><li>Punkt 1</li><li>Punkt 2</li><li>Punkt 3</li></ol>'
        ],
        [
            'name' => 'Tabel',
            'content' => '<table style="width: 100%; border-collapse: collapse;"><thead><tr><th style="border: 1px solid #ddd; padding: 8px;">Kolonne 1</th><th style="border: 1px solid #ddd; padding: 8px;">Kolonne 2</th></tr></thead><tbody><tr><td style="border: 1px solid #ddd; padding: 8px;">Data 1</td><td style="border: 1px solid #ddd; padding: 8px;">Data 2</td></tr></tbody></table>'
        ]
    ];

    return [
        'success' => true,
        'templates' => $templates
    ];
}

/**
 * Helper: Sanitize HTML content
 */
function sanitize_html(string $html): string {
    // Allow safe HTML tags
    $allowed_tags = '<p><br><strong><em><u><s><h1><h2><h3><h4><h5><h6><ul><ol><li><table><thead><tbody><tr><th><td><a><img><blockquote><code><pre><hr>';

    // Strip dangerous tags
    $cleaned = strip_tags($html, $allowed_tags);

    // Remove javascript: and data: from links and images
    $cleaned = preg_replace('/(<a[^>]*href\s*=\s*["\'])(?:javascript|data):/i', '$1#', $cleaned);
    $cleaned = preg_replace('/(<img[^>]*src\s*=\s*["\'])(?:javascript|data):/i', '$1#', $cleaned);

    // Remove event handlers
    $cleaned = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $cleaned);

    return $cleaned;
}
