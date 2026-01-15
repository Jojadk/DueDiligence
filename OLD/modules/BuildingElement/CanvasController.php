<?php
namespace Modules\BuildingElement;

use Core\Controller;
use Core\Database;

class CanvasController extends Controller
{

    public function get_media()
    {
        $eid = $_GET['element_id'];
        $db = Database::getInstance();
        // Get most recent image
        $db->query("SELECT * FROM element_media WHERE element_id = :eid AND media_type = 'image' ORDER BY created_at DESC LIMIT 1");
        $db->bind(':eid', $eid);
        $media = $db->single();

        $this->json($media ?: []);
    }

    public function save()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $mediaId = $input['media_id'];
        $json = $input['canvas_json'];

        $db = Database::getInstance();
        $db->query("UPDATE element_media SET canvas_json = :json WHERE id = :mid");
        $db->bind(':json', $json);
        $db->bind(':mid', $mediaId);
        $db->execute();

        // Also Generate Flattened Image (Stub - requires GD/Imagick)
        // trigger_flattening($mediaId, $json);

        $this->json(['success' => true]);
    }
}
