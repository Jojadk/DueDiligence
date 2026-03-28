<?php
namespace Core;

class ErrorHandler
{
    private static $logFile;

    public static function register()
    {
        self::$logFile = ROOT_DIR . '/logs/system_errors.log';

        // Set handlers
        set_error_handler([__CLASS__, 'handleError']);
        set_exception_handler([__CLASS__, 'handleException']);
        register_shutdown_function([__CLASS__, 'handleShutdown']);

        // Ensure log directory exists
        if (!is_dir(dirname(self::$logFile))) {
            mkdir(dirname(self::$logFile), 0777, true);
        }
    }

    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        if (!(error_reporting() & $errno)) {
            // This error code is not included in error_reporting
            return false;
        }

        $type = self::getErrorTypeString($errno);
        $message = "$type: $errstr";

        self::log('PHP_ERROR', $message, [
            'file' => $errfile,
            'line' => $errline,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ]);

        return true; // Don't execute PHP internal error handler
    }

    public static function handleException(\Throwable $exception)
    {
        $message = "Uncaught Exception: " . $exception->getMessage();

        self::log('PHP_EXCEPTION', $message, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString()
        ]);

        // If it's an AJAX request, return JSON error
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['status' => 'error', 'message' => 'Internal Server Error (Logged)']);
        } else {
            // For user facing pages, maybe show a nice error page? For now custom generic msg
            if (ini_get('display_errors')) { // Dev mode
                echo "<h1>Critical Error</h1><p>" . nl2br($exception->getMessage()) . "</p><pre>" . $exception->getTraceAsString() . "</pre>";
            } else {
                echo "<h1>System Error</h1><p>An unexpected error occurred. It has been logged.</p>";
            }
        }
    }

    public static function handleShutdown()
    {
        $error = error_get_last();
        if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_COMPILE_ERROR || $error['type'] === E_CORE_ERROR)) {
            self::log('PHP_FATAL', $error['message'], [
                'file' => $error['file'],
                'line' => $error['line']
            ]);
        }
    }

    public static function log($type, $message, $context = [])
    {
        $timestamp = date('Y-m-d H:i:s');
        $userId = $_SESSION['user_id'] ?? 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $uri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $method = $_SERVER['REQUEST_METHOD'] ?? 'CLI';

        // Format context
        if (isset($context['trace']) && is_array($context['trace'])) {
            // Simplify array trace
            $context['trace'] = json_encode($context['trace']);
        }
        $contextStr = json_encode($context);

        // [TIME] [TYPE] Message | Context | User | Request
        $logLine = sprintf(
            "[%s] [%s] %s (User: %s, IP: %s) | Request: %s %s | Site: %s:%s | Meta: %s\n",
            $timestamp,
            strtoupper($type),
            str_replace(["\r", "\n"], ' ', $message),
            $userId,
            $ip,
            $method,
            $uri,
            $context['file'] ?? '-',
            $context['line'] ?? '-',
            $contextStr
        );

        file_put_contents(self::$logFile, $logLine, FILE_APPEND);
    }

    private static function getErrorTypeString($type)
    {
        switch ($type) {
            case E_ERROR:
                return 'E_ERROR';
            case E_WARNING:
                return 'E_WARNING';
            case E_PARSE:
                return 'E_PARSE';
            case E_NOTICE:
                return 'E_NOTICE';
            case E_CORE_ERROR:
                return 'E_CORE_ERROR';
            case E_CORE_WARNING:
                return 'E_CORE_WARNING';
            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';
            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';
            case E_USER_ERROR:
                return 'E_USER_ERROR';
            case E_USER_WARNING:
                return 'E_USER_WARNING';
            case E_USER_NOTICE:
                return 'E_USER_NOTICE';
            case E_STRICT:
                return 'E_STRICT';
            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';
            case E_DEPRECATED:
                return 'E_DEPRECATED';
            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';
        }
        return "UNKNOWN($type)";
    }
}
