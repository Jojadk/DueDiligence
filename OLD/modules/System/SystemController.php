<?php
namespace Modules\System;

use Core\Controller;
use Core\Database;

class SystemController extends Controller
{
    public function logError()
    {
        // Only accept POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            // Try regular POST
            $input = $_POST;
        }

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'type' => $input['type'] ?? 'error',
            'message' => $input['message'] ?? 'Unknown error',
            'url' => $input['url'] ?? '',
            'line' => $input['line'] ?? '',
            'col' => $input['col'] ?? '',
            'stack' => $input['stack'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_id' => $_SESSION['user_id'] ?? 'guest',
            'meta' => $input['meta'] ?? []
        ];

        // Use centralized logger
        \Core\ErrorHandler::log(
            'JS_' . strtoupper($input['type'] ?? 'ERROR'),
            $input['message'] ?? 'Unknown JS error',
            [
                'file' => $input['url'] ?? 'unknown.js',
                'line' => $input['line'] ?? '-',
                'col' => $input['col'] ?? '-',
                'trace' => $input['stack'] ?? '',
                'meta' => $input['meta'] ?? []
            ]
        );

        // Optional: Store in DB if needed later
        /*
        $db = Database::getInstance();
        $stmt = $db->prepare("INSERT INTO system_logs (type, message, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute(['js_error', $logEntry['message'], json_encode($logEntry)]);
        */

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}
