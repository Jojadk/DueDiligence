<?php
namespace Modules\BuildingElement;

use Core\Controller;
use Core\Database;
use Core\Auth;

class UploadController extends Controller
{

    public function __construct()
    {
        if (!Auth::check()) {
            http_response_code(403);
            exit;
        }
    }

    public function upload()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
            $elementId = $_POST['element_id'] ?? null;
            if (!$elementId) {
                $this->json(['success' => false, 'error' => 'No element ID']);
            }

            $file = $_FILES['file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'mp4'];

            if (!in_array($ext, $allowed)) {
                $this->json(['success' => false, 'error' => 'Invalid file type']);
            }

            // Create media dir if not exists
            $dir = ASSETS_DIR . '/uploads';
            if (!is_dir($dir))
                mkdir($dir, 0777, true);

            // Hash name
            $filename = uniqid() . '.' . $ext;
            $path = $dir . '/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $path)) {
                // Determine type
                $type = 'document';
                if (in_array($ext, ['jpg', 'jpeg', 'png']))
                    $type = 'image';
                if ($ext == 'mp4')
                    $type = 'video';

                // Save to DB
                $db = Database::getInstance();
                $db->query("INSERT INTO element_media (element_id, file_path, media_type) VALUES (:eid, :path, :type)");
                $db->bind(':eid', $elementId);
                $db->bind(':path', 'assets/uploads/' . $filename);
                $db->bind(':type', $type);
                $db->execute();

                $this->json(['success' => true, 'path' => 'assets/uploads/' . $filename]);
            } else {
                $this->json(['success' => false, 'error' => 'Upload failed']);
            }
        }
    }
}
