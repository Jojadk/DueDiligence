<?php
/**
 * Silent Fail Handler
 *
 * Centralized system for handling and logging silent failures across the platform.
 * Ensures that errors never crash the system but are logged for later resolution.
 *
 * @package DueDiligence
 * @version 1.0.0
 * @author Claude Code
 */

class SilentFailHandler {

    private $db;
    private $config;
    private $buffer = [];
    private $bufferSize = 50; // Batch insert for performance
    private $isEnabled = true;

    /**
     * Constructor
     *
     * @param PDO $db Database connection
     * @param array $config Configuration options
     */
    public function __construct($db = null, $config = []) {
        $this->db = $db ?? $this->getDefaultConnection();
        $this->config = array_merge([
            'log_to_database' => true,
            'log_to_file' => true,
            'file_path' => __DIR__ . '/../logs/silent_fails.log',
            'min_severity' => 'info', // Log all severities by default
            'batch_insert' => true
        ], $config);

        // Register shutdown function to flush buffer
        register_shutdown_function([$this, 'flush']);
    }

    /**
     * Log a silent failure
     *
     * @param string $module Module where error occurred (e.g., 'template_parser')
     * @param string $operation Specific operation that failed (e.g., 'parse_variable')
     * @param string $errorType Category of error (e.g., 'missing_variable')
     * @param string $errorMessage Human-readable error message
     * @param array $context Additional context (variable path, data, etc.)
     * @param string $renderedOutput What was shown to user
     * @param string $severity Severity level: 'info', 'warning', 'error', 'critical'
     * @return bool Success status
     */
    public function log(
        string $module,
        string $operation,
        string $errorType,
        string $errorMessage,
        array $context = [],
        string $renderedOutput = '',
        string $severity = 'warning'
    ): bool {

        if (!$this->isEnabled) {
            return false;
        }

        // Skip if severity is below threshold
        if (!$this->shouldLog($severity)) {
            return false;
        }

        $logEntry = [
            'module' => $module,
            'operation' => $operation,
            'error_type' => $errorType,
            'error_message' => $errorMessage,
            'error_context' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'rendered_output' => $renderedOutput,
            'severity' => $severity,
            'user_id' => $this->getCurrentUserId(),
            'project_id' => $context['project_id'] ?? null,
            'customer_id' => $context['customer_id'] ?? null,
            'stack_trace' => $this->getStackTrace(),
            'request_url' => $_SERVER['REQUEST_URI'] ?? null,
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $this->getClientIp(),
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Log to file immediately (for critical errors)
        if ($this->config['log_to_file'] && in_array($severity, ['error', 'critical'])) {
            $this->logToFile($logEntry);
        }

        // Buffer for batch insert (performance optimization)
        if ($this->config['batch_insert']) {
            $this->buffer[] = $logEntry;
            if (count($this->buffer) >= $this->bufferSize) {
                return $this->flush();
            }
            return true;
        }

        // Immediate database insert
        return $this->insertToDatabase([$logEntry]);
    }

    /**
     * Handle an exception silently and return fallback output
     *
     * @param Exception $exception The exception to handle
     * @param string $module Module where exception occurred
     * @param string $operation Operation that failed
     * @param array $context Additional context
     * @param string $fallback Fallback output to return
     * @return string Fallback output
     */
    public function handle(
        Exception $exception,
        string $module,
        string $operation,
        array $context = [],
        string $fallback = '[Error occurred]'
    ): string {

        $this->log(
            $module,
            $operation,
            get_class($exception),
            $exception->getMessage(),
            array_merge($context, [
                'exception_code' => $exception->getCode(),
                'exception_file' => $exception->getFile(),
                'exception_line' => $exception->getLine()
            ]),
            $fallback,
            'error'
        );

        return $fallback;
    }

    /**
     * Create a missing variable placeholder
     *
     * @param string $variablePath The variable path that was missing (e.g., 'project.name')
     * @param string $module Module where variable was missing
     * @param array $context Additional context
     * @return string Formatted error message for user
     */
    public function missingVariable(
        string $variablePath,
        string $module = 'template_parser',
        array $context = []
    ): string {

        $output = "[Missing Variable: {$variablePath}]";

        $this->log(
            $module,
            'variable_resolution',
            'missing_variable',
            "Variable '{$variablePath}' not found in data context",
            array_merge($context, ['variable_path' => $variablePath]),
            $output,
            'warning'
        );

        return $output;
    }

    /**
     * Create a parse error placeholder
     *
     * @param string $expression The expression that failed to parse
     * @param string $errorMessage Error details
     * @param string $module Module where parse error occurred
     * @param array $context Additional context
     * @return string Formatted error message for user
     */
    public function parseError(
        string $expression,
        string $errorMessage,
        string $module = 'template_parser',
        array $context = []
    ): string {

        $output = "[Parse Error: {$expression}]";

        $this->log(
            $module,
            'expression_parsing',
            'parse_error',
            "Failed to parse expression '{$expression}': {$errorMessage}",
            array_merge($context, ['expression' => $expression]),
            $output,
            'error'
        );

        return $output;
    }

    /**
     * Flush buffer to database
     *
     * @return bool Success status
     */
    public function flush(): bool {
        if (empty($this->buffer)) {
            return true;
        }

        $success = $this->insertToDatabase($this->buffer);
        $this->buffer = [];
        return $success;
    }

    /**
     * Insert log entries to database
     *
     * @param array $entries Array of log entries
     * @return bool Success status
     */
    private function insertToDatabase(array $entries): bool {
        if (!$this->config['log_to_database'] || empty($entries)) {
            return true;
        }

        try {
            // Build bulk insert query
            $placeholders = [];
            $values = [];

            foreach ($entries as $entry) {
                $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $values = array_merge($values, [
                    $entry['module'],
                    $entry['operation'],
                    $entry['error_type'],
                    $entry['error_message'],
                    $entry['error_context'],
                    $entry['rendered_output'],
                    $entry['severity'],
                    $entry['user_id'],
                    $entry['project_id'],
                    $entry['customer_id'],
                    $entry['stack_trace'],
                    $entry['request_url'],
                    $entry['request_method'],
                    $entry['user_agent'],
                    $entry['ip_address']
                ]);
            }

            $sql = "INSERT INTO silent_fail_logs (
                module, operation, error_type, error_message, error_context,
                rendered_output, severity, user_id, project_id, customer_id,
                stack_trace, request_url, request_method, user_agent, ip_address
            ) VALUES " . implode(', ', $placeholders);

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($values);

        } catch (PDOException $e) {
            // Fallback to file logging if database fails
            error_log("Silent Fail Handler DB Error: " . $e->getMessage());
            foreach ($entries as $entry) {
                $this->logToFile($entry);
            }
            return false;
        }
    }

    /**
     * Log to file
     *
     * @param array $entry Log entry
     * @return bool Success status
     */
    private function logToFile(array $entry): bool {
        $logLine = sprintf(
            "[%s] [%s] [%s/%s] %s - Context: %s - Output: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($entry['severity']),
            $entry['module'],
            $entry['operation'],
            $entry['error_message'],
            $entry['error_context'],
            $entry['rendered_output']
        );

        $logDir = dirname($this->config['file_path']);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        return file_put_contents($this->config['file_path'], $logLine, FILE_APPEND) !== false;
    }

    /**
     * Check if severity level should be logged
     *
     * @param string $severity Severity to check
     * @return bool Should log
     */
    private function shouldLog(string $severity): bool {
        $levels = ['info' => 0, 'warning' => 1, 'error' => 2, 'critical' => 3];
        $minLevel = $levels[$this->config['min_severity']] ?? 0;
        $currentLevel = $levels[$severity] ?? 0;
        return $currentLevel >= $minLevel;
    }

    /**
     * Get stack trace (excluding this handler)
     *
     * @return string Stack trace
     */
    private function getStackTrace(): string {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        // Remove this handler from trace
        array_shift($trace);
        array_shift($trace);

        $formatted = array_map(function($frame) {
            return sprintf(
                "%s%s%s() in %s:%d",
                $frame['class'] ?? '',
                $frame['type'] ?? '',
                $frame['function'] ?? '',
                $frame['file'] ?? 'unknown',
                $frame['line'] ?? 0
            );
        }, $trace);

        return implode("\n", $formatted);
    }

    /**
     * Get current user ID from session
     *
     * @return int|null User ID
     */
    private function getCurrentUserId(): ?int {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get client IP address
     *
     * @return string|null IP address
     */
    private function getClientIp(): ?string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',   // Proxy
            'HTTP_X_REAL_IP',         // Nginx
            'REMOTE_ADDR'             // Direct
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                return $ip;
            }
        }

        return null;
    }

    /**
     * Get default database connection
     *
     * @return PDO Database connection
     */
    private function getDefaultConnection(): PDO {
        global $db; // Assuming global $db is available
        return $db;
    }

    /**
     * Disable logging (for testing or performance-critical operations)
     */
    public function disable(): void {
        $this->isEnabled = false;
    }

    /**
     * Enable logging
     */
    public function enable(): void {
        $this->isEnabled = true;
    }

    /**
     * Get statistics for last N days
     *
     * @param int $days Number of days to look back
     * @return array Statistics
     */
    public function getStatistics(int $days = 7): array {
        $sql = "SELECT
            module,
            error_type,
            severity,
            COUNT(*) as count,
            MAX(created_at) as last_occurrence
        FROM silent_fail_logs
        WHERE created_at >= NOW() - INTERVAL '{$days} days'
        GROUP BY module, error_type, severity
        ORDER BY count DESC
        LIMIT 50";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Mark errors as resolved
     *
     * @param array $ids Array of log IDs to mark as resolved
     * @param string $notes Resolution notes
     * @return bool Success status
     */
    public function markResolved(array $ids, string $notes = ''): bool {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "UPDATE silent_fail_logs
                SET is_resolved = TRUE,
                    resolved_at = NOW(),
                    resolved_by_user_id = ?,
                    resolution_notes = ?
                WHERE id IN ({$placeholders})";

        $stmt = $this->db->prepare($sql);
        $params = array_merge([$this->getCurrentUserId(), $notes], $ids);
        return $stmt->execute($params);
    }
}
