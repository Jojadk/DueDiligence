<?php
namespace Modules\BuildingElement;

use Core\Database;
use Core\Security;

/**
 * Handles all media operations for building elements
 * - Image upload
 * - Media deletion
 * - Media reordering
 * - Captions and annotations
 */
class MediaController extends BaseElementController
{
    /**
     * Upload images for an element
     * POST: id, project_id, images[]
     */
    public function addimage()
    {
        $this->requirePost();

        $id = $_POST['element_id'] ?? $_POST['id'] ?? null;
        $projectId = $_POST['project_id'] ?? null;

        if (!$id) {
            $this->jsonError('Element ID required', 400);
        }

        // Verify element exists
        $element = $this->getElementOrFail($id);

        $uploadedCount = 0;
        $uploadedFiles = [];

        if (!empty($_FILES['images']['name'])) {
            // Normalize files array if single or multiple
            $names = is_array($_FILES['images']['name'])
                ? $_FILES['images']['name']
                : [$_FILES['images']['name']];
            $tmps = is_array($_FILES['images']['tmp_name'])
                ? $_FILES['images']['tmp_name']
                : [$_FILES['images']['tmp_name']];
            $types = is_array($_FILES['images']['type'])
                ? $_FILES['images']['type']
                : [$_FILES['images']['type']];

            foreach ($names as $idx => $name) {
                $tmp = $tmps[$idx];
                $mimeType = $types[$idx] ?? '';

                if (empty($tmp))
                    continue;

                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($mimeType, $allowedTypes)) {
                    $this->logError("Invalid file type: $mimeType", 'warning', [
                        'element_id' => $id,
                        'filename' => $name
                    ]);
                    continue;
                }

                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $newName = uniqid('img_') . '.' . $ext;

                $uploadDir = ROOT_DIR . '/assets/uploads/';
                $dest = $uploadDir . $newName;

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                if (move_uploaded_file($tmp, $dest)) {
                    // Get next sort order
                    $this->db->query('SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order 
                                     FROM element_media WHERE element_id = :eid');
                    $this->db->bind(':eid', $id);
                    $order = $this->db->single()['next_order'];

                    $this->db->query('INSERT INTO element_media 
                                     (element_id, file_path, media_type, sort_order) 
                                     VALUES (:eid, :path, :type, :order)');
                    $this->db->bind(':eid', $id);
                    $this->db->bind(':path', $newName);
                    $this->db->bind(':type', 'image');
                    $this->db->bind(':order', $order);
                    $this->db->execute();

                    $uploadedFiles[] = [
                        'id' => $this->db->lastInsertId(),
                        'path' => $newName
                    ];
                    $uploadedCount++;
                }
            }
        }

        $this->jsonSuccess([
            'uploaded' => $uploadedCount,
            'files' => $uploadedFiles
        ], "Uploaded $uploadedCount file(s)");
    }

    /**
     * Delete media item
     * POST: media_id
     */
    public function delete()
    {
        $this->requirePost();

        $mediaId = $_POST['media_id'] ?? $_GET['media_id'] ?? null;

        if (!$mediaId) {
            $this->jsonError('Media ID required', 400);
        }

        // Get media info
        $this->db->query('SELECT * FROM element_media WHERE id = :id');
        $this->db->bind(':id', $mediaId);
        $media = $this->db->single();

        if (!$media) {
            $this->jsonError('Media not found', 404);
        }

        // Delete file from disk
        $filePath = ROOT_DIR . '/assets/uploads/' . $media['file_path'];
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        // Delete from database
        $this->db->query('DELETE FROM element_media WHERE id = :id');
        $this->db->bind(':id', $mediaId);
        $this->db->execute();

        $this->jsonSuccess(null, 'Media deleted');
    }

    /**
     * Reorder media items
     * POST JSON: { items: [{id: 1, order: 1}, ...] }
     */
    public function reorder()
    {
        $input = $this->getJsonInput();

        // Note: frontend sends items directly, so no element_id check needed here usually
        // But if needed in future, we can add it.
        // The items contain 'id' which is media ID.

        if (empty($input['items'])) {
            $this->jsonError('Invalid data', 400);
        }

        $this->db->beginTransaction();
        try {
            foreach ($input['items'] as $item) {
                $this->db->query('UPDATE element_media 
                                 SET sort_order = :order 
                                 WHERE id = :id');
                $this->db->bind(':order', $item['order']);
                $this->db->bind(':id', $item['id']);
                $this->db->execute();
            }
            $this->db->commit();
            $this->jsonSuccess(null, 'Order updated');
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->logError('Media reorder failed: ' . $e->getMessage(), 'error');
            $this->jsonError('Failed to update order', 500);
        }
    }

    /**
     * Update media caption
     * POST: media_id, caption
     */
    public function updateCaption()
    {
        $this->requirePost();

        $mediaId = $_POST['media_id'] ?? null;
        $caption = $_POST['caption'] ?? '';

        if (!$mediaId) {
            $this->jsonError('Media ID required', 400);
        }

        $this->db->query('UPDATE element_media 
                         SET caption = :caption, 
                             comment = :caption 
                         WHERE id = :id');
        $this->db->bind(':caption', $caption);
        $this->db->bind(':id', $mediaId);
        $this->db->execute();

        $this->jsonSuccess(null, 'Caption updated');
    }

    /**
     * Save image annotation
     * POST JSON: { media_id, annotation_data }
     */
    public function saveAnnotation()
    {
        $input = $this->getJsonInput();

        $mediaId = $input['media_id'] ?? null;
        $annotationData = $input['annotation_data'] ?? null;

        if (!$mediaId) {
            $this->jsonError('Media ID required', 400);
        }

        $this->db->query('UPDATE element_media 
                         SET annotation_data = :data 
                         WHERE id = :id');
        $this->db->bind(':data', json_encode($annotationData));
        $this->db->bind(':id', $mediaId);
        $this->db->execute();

        $this->jsonSuccess(null, 'Annotation saved');
    }

    /**
     * Get all media for an element
     * GET: element_id
     */
    public function getMedia()
    {
        $elementId = $_GET['element_id'] ?? $_GET['id'] ?? null;

        if (!$elementId) {
            $this->jsonError('Element ID required', 400);
        }

        $this->db->query('SELECT * FROM element_media 
                         WHERE element_id = :eid 
                         ORDER BY sort_order ASC, id ASC');
        $this->db->bind(':eid', $elementId);
        $media = $this->db->resultSet();

        $this->jsonSuccess($media);
    }
}
