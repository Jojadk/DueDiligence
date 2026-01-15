<?php
namespace Core;

class Router
{
    /**
     * Sub-controller mappings
     * Maps module/action prefixes to specialized controllers
     */
    private static $subControllers = [
        'BuildingElement' => [
            'addimage' => 'MediaController',
            'deleteMedia' => 'MediaController',
            'reorderMedia' => 'MediaController',
            'saveMediaCaption' => 'MediaController',
            'saveImageAnnotation' => 'MediaController',
            'getMedia' => 'MediaController',
            'acquireLock' => 'LockController',
            'lockrelease' => 'LockController',
            'lockcheck' => 'LockController',
            'heartbeat' => 'LockController',
            'poll' => 'LockController',
            'getbudget' => 'BudgetController',
            'savebudget' => 'BudgetController',
            'search_catalog' => 'BudgetController',
            'saveVersion' => 'VersionController',
            'getVersions' => 'VersionController',
            'restoreVersion' => 'VersionController',
        ]
    ];

    public function dispatch()
    {
        // Simple routing: index.php?module=User&action=login
        $module = isset($_GET['module']) ? $_GET['module'] : 'Dashboard';
        $action = isset($_GET['action']) ? $_GET['action'] : 'index';

        // Sanitize
        $module = preg_replace('/[^a-zA-Z0-9]/', '', $module);
        $action = preg_replace('/[^a-zA-Z0-9_]/', '', $action);

        // Determine controller class
        $controllerName = $this->resolveController($module, $action);

        // Check if class exists
        if (class_exists($controllerName)) {
            try {
                $controller = new $controllerName();

                // Map action names for sub-controllers
                $methodName = $this->resolveMethod($module, $action);

                if (method_exists($controller, $methodName)) {
                    $controller->$methodName();
                } else {
                    $this->handleError("Action '$action' not found in module '$module'.", 404);
                }
            } catch (\Exception $e) {
                $this->handleError($e->getMessage(), 500);
            }
        } else {
            $this->handleError("Module '$module' not found.", 404);
        }
    }

    /**
     * Resolve which controller to use
     */
    private function resolveController(string $module, string $action): string
    {
        // Check if there's a sub-controller mapping
        if (isset(self::$subControllers[$module][$action])) {
            $subController = self::$subControllers[$module][$action];
            return 'Modules\\' . $module . '\\' . $subController;
        }

        // Default controller
        return 'Modules\\' . $module . '\\' . $module . 'Controller';
    }

    /**
     * Resolve which method to call
     * Sub-controllers may have different method names
     */
    private function resolveMethod(string $module, string $action): string
    {
        // Map action names to method names for sub-controllers
        $methodMappings = [
            'addimage' => 'addimage',
            'deleteMedia' => 'delete',
            'reorderMedia' => 'reorder',
            'saveMediaCaption' => 'updateCaption',
            'saveImageAnnotation' => 'saveAnnotation',
            'getMedia' => 'getMedia',
            'acquireLock' => 'acquire',
            'lockrelease' => 'release',
            'lockcheck' => 'check',
            'getbudget' => 'get',
            'savebudget' => 'save',
            'search_catalog' => 'searchCatalog',
            'saveVersion' => 'save',
            'getVersions' => 'list',
            'restoreVersion' => 'restore',
        ];

        return $methodMappings[$action] ?? $action;
    }

    /**
     * Handle routing errors
     */
    private function handleError(string $message, int $code = 500): void
    {
        // Log the error
        $logFile = ROOT_DIR . '/logs/system_errors.log';
        $logLine = '[' . date('Y-m-d H:i:s') . '] ROUTING ERROR: ' . $message .
            ' | URL: ' . ($_SERVER['REQUEST_URI'] ?? '') . PHP_EOL;
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

        // Check if AJAX request
        if (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
        ) {
            http_response_code($code);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => defined('DEV_MODE') && DEV_MODE ? $message : 'An error occurred'
            ]);
            exit;
        }

        // Display error page or throw exception
        if (defined('DEV_MODE') && DEV_MODE) {
            throw new \Exception($message);
        } else {
            http_response_code($code);
            echo "<h1>Error $code</h1><p>The requested page could not be found.</p>";
            exit;
        }
    }
}
