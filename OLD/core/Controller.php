<?php
namespace Core;

/**
 * Base Controller
 * All module controllers should extend this class.
 * 
 * Features:
 * - Database singleton access via $this->db
 * - Authentication middleware via $requiresAuth
 * - Permission checking via $requiredPermission
 * - Standardized JSON response format
 * - Proper error/access denied handling
 */
abstract class Controller
{
    /**
     * Database instance - available in all child controllers
     */
    protected $db;

    /**
     * Set to true to require authentication (default: true)
     */
    protected $requiresAuth = true;

    /**
     * Set to a permission string to require that permission
     * e.g., 'admin_access', 'project_edit'
     */
    protected $requiredPermission = null;

    /**
     * Actions that don't require authentication
     * e.g., ['login', 'register']
     */
    protected $publicActions = [];

    /**
     * Constructor - handles auth and sets up database
     */
    public function __construct()
    {
        // Initialize database connection
        $this->db = Database::getInstance();

        // Get current action
        $currentAction = $_GET['action'] ?? 'index';

        // Check if this action is public
        $isPublicAction = in_array($currentAction, $this->publicActions);

        // Authentication check
        if ($this->requiresAuth && !$isPublicAction && !Auth::check()) {
            if ($this->isAjaxRequest()) {
                $this->jsonError('Authentication required', 401);
            }
            $this->redirect('?module=Auth');
        }

        // Permission check
        if ($this->requiredPermission && !$isPublicAction && !Auth::hasPermission($this->requiredPermission)) {
            $this->handleAccessDenied();
        }
    }

    /**
     * Load model by class name
     */
    public function model($model)
    {
        if (class_exists($model)) {
            return new $model();
        }
        return null;
    }

    /**
     * Load view with optional layout
     */
    public function view($view, $data = [])
    {
        extract($data);

        $viewFileBase = MODULES_DIR . '/' . $view . '.php';

        if (file_exists($viewFileBase)) {
            if (isset($data['no_layout']) && $data['no_layout']) {
                $viewFile = $viewFileBase;
                require $viewFile;
            } else {
                $viewFile = $viewFileBase;
                require MODULES_DIR . '/Shared/layout.php';
            }
        } else {
            throw new \Exception("View does not exist: " . $viewFileBase);
        }
    }

    /**
     * Redirect to URL
     */
    public function redirect($url)
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Send JSON response (backward compatible)
     */
    public function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Standardized JSON success response
     */
    public function jsonSuccess($data = null, $message = null)
    {
        $response = ['status' => 'success'];
        if ($message)
            $response['message'] = $message;
        if ($data !== null)
            $response['data'] = $data;

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * Standardized JSON error response with logging
     */
    public function jsonError($message, $statusCode = 400, $errors = null, $logLevel = 'warning')
    {
        // Log the error
        $this->logError($message, $logLevel, [
            'status_code' => $statusCode,
            'errors' => $errors,
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'user_id' => $this->userId()
        ]);

        http_response_code($statusCode);

        $response = [
            'status' => 'error',
            'message' => $this->getErrorMessage($message)
        ];

        if ($errors)
            $response['errors'] = $errors;

        // Include debug info in DEV_MODE
        if (defined('DEV_MODE') && DEV_MODE) {
            $response['debug'] = [
                'original_message' => $message,
                'file' => debug_backtrace()[1]['file'] ?? 'unknown',
                'line' => debug_backtrace()[1]['line'] ?? 0
            ];
        }

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    /**
     * Handle access denied - replaces die() calls
     */
    protected function handleAccessDenied($message = 'Access denied')
    {
        // Log the access attempt
        $this->logError($message, 'security', [
            'type' => 'access_denied',
            'user_id' => $this->userId(),
            'url' => $_SERVER['REQUEST_URI'] ?? '',
            'referer' => $_SERVER['HTTP_REFERER'] ?? ''
        ]);

        if ($this->isAjaxRequest()) {
            $this->jsonError($message, 403);
        }

        // Store flash message
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash']['error'] = $this->getErrorMessage($message);

        // Redirect to dashboard or previous page
        $referer = $_SERVER['HTTP_REFERER'] ?? '?module=Dashboard';
        $this->redirect($referer);
    }

    /**
     * Handle not found - replaces die() calls
     */
    protected function handleNotFound($message = 'Resource not found')
    {
        // Log the 404
        $this->logError($message, 'warning', [
            'type' => 'not_found',
            'url' => $_SERVER['REQUEST_URI'] ?? ''
        ]);

        if ($this->isAjaxRequest()) {
            $this->jsonError($message, 404);
        }

        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash']['error'] = $this->getErrorMessage($message);
        $this->redirect('?module=Dashboard');
    }

    /**
     * Log error to system log
     */
    protected function logError($message, $level = 'error', array $context = []): void
    {
        $logFile = ROOT_DIR . '/logs/system_errors.log';

        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => strtoupper($level),
            'message' => $message,
            'user_id' => $context['user_id'] ?? $this->userId(),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'cli',
            'url' => $context['url'] ?? ($_SERVER['REQUEST_URI'] ?? ''),
            'context' => $context
        ];

        // Add stack trace in DEV_MODE
        if (defined('DEV_MODE') && DEV_MODE && $level === 'error') {
            $logEntry['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        }

        $logLine = '[' . $logEntry['timestamp'] . '] ' .
            $logEntry['level'] . ': ' .
            $logEntry['message'] . ' | ' .
            json_encode($logEntry['context']) . PHP_EOL;

        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get user-friendly error message
     * In production, hide technical details
     */
    protected function getErrorMessage($technicalMessage): string
    {
        // In DEV_MODE, show the full message
        if (defined('DEV_MODE') && DEV_MODE) {
            return $technicalMessage;
        }

        // In production, map to user-friendly messages
        $friendlyMessages = [
            'Access denied' => 'Du har ikke adgang til denne side.',
            'Permission denied' => 'Du har ikke tilladelse til denne handling.',
            'Resource not found' => 'Den ønskede ressource blev ikke fundet.',
            'Invalid security token' => 'Ugyldig anmodning. Prøv igen.',
            'Authentication required' => 'Du skal logge ind for at fortsætte.'
        ];

        return $friendlyMessages[$technicalMessage] ?? 'Der opstod en fejl. Prøv igen senere.';
    }

    /**
     * Check if current request is AJAX
     */
    protected function isAjaxRequest(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Get current user ID
     */
    protected function userId(): ?int
    {
        return Auth::id();
    }

    /**
     * Get current user
     */
    protected function user(): ?array
    {
        return Auth::user();
    }

    /**
     * Validate CSRF token for POST requests
     */
    protected function validateCsrf(): bool
    {
        return Security::validateCSRFToken();
    }

    /**
     * Require CSRF validation, error if invalid
     */
    protected function requireCsrf(): void
    {
        if (!$this->validateCsrf()) {
            Security::logSecurityEvent('csrf_validation_failed', [
                'user_id' => $this->userId(),
                'url' => $_SERVER['REQUEST_URI'] ?? ''
            ]);
            $this->jsonError('Invalid security token', 403);
        }
    }
}
