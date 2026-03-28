<?php
namespace Modules\Canvas;

use Core\Controller;
use Core\Database;

class CanvasController extends Controller
{
    public function get_media()
    {
        $elementId = $_GET['element_id'] ?? null;
        if (!$elementId) {
            http_response_code(400);
            $this->json(['error' => 'Missing element_id']);
            return;
        }

        $db = Database::getInstance();
        $db->query("SELECT * FROM element_media WHERE element_id = :id ORDER BY sort_order ASC, id ASC LIMIT 1");
        $db->bind(':id', $elementId);
        $media = $db->single();

        if ($media) {
            // Add a flag or path for the frontend to load the script
            // This assumes the frontend (app.js) will interpret this key to load the script.
            $media['script_to_load'] = 'assets/js/canvas-engine.js'; // for app.js compatibility
            // Alias annotations to canvas_json for app.js compatibility
            $media['canvas_json'] = $media['annotations'];

            // Fix path if needed (logic borrowed from Report/BuildingElement)
            if (strpos($media['file_path'], 'assets/') !== 0 && strpos($media['file_path'], '/') !== 0) {
                $media['file_path'] = 'assets/uploads/' . $media['file_path'];
            }

            $this->json($media);
        } else {
            http_response_code(404);
            $this->json(['error' => 'No media found']);
        }
    }

    public function save()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $mediaId = $input['media_id'] ?? null;
        $json = $input['canvas_json'] ?? null;

        if (!$mediaId) {
            http_response_code(400);
            $this->json(['status' => 'error', 'message' => 'Missing data']);
            return;
        }

        // Logic to ensure annotations column exists (Self-healing)
        $db = Database::getInstance();
        try {
            $db->query("ALTER TABLE element_media ADD COLUMN IF NOT EXISTS annotations TEXT");
            $db->execute();
        } catch (\Exception $e) {
        }

        // Handle JSON being object/array or string
        $jsonStr = is_string($json) ? $json : json_encode($json);

        $db->query("UPDATE element_media SET annotations = :ann, updated_at = NOW() WHERE id = :id");
        $db->bind(':ann', $jsonStr);
        $db->bind(':id', $mediaId);

        if ($db->execute()) {
            $this->json(['status' => 'success']);
        } else {
            http_response_code(500);
            $this->json(['status' => 'error', 'message' => 'DB Error']);
        }
    }
}
