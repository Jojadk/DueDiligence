<?php
/**
 * Advanced Template Parser v2.0
 *
 * Features:
 * - Double curly braces syntax: {{variable}}
 * - Advanced loops: {{for item in collection}}...{{endfor}}
 * - Conditionals: {{if condition}}...{{else}}...{{endif}}
 * - Ternary operators: {{condition ? valueTrue : valueFalse}}
 * - Nullish coalescing: {{variable ?? defaultValue}}
 * - Pipe filters: {{value | filter:param1:param2}}
 * - Silent fail with error logging
 * - Automatic figure numbering
 * - No crashes - always renders with placeholders for errors
 *
 * @package DueDiligence
 * @version 2.0.0
 * @author Claude Code
 */

require_once __DIR__ . '/silent_fail_handler.php';

class AdvancedTemplateParser {

    private array $data = [];
    private array $filters = [];
    private ?SilentFailHandler $errorHandler = null;
    private array $context = [];
    private bool $debugMode = false;

    // Figure numbering state
    private array $figureNumbers = []; // [section => count]
    private ?string $currentSection = null;

    // Performance metrics
    private float $parseStartTime = 0;
    private int $errorCount = 0;

    /**
     * Constructor
     *
     * @param array $data Template data
     * @param array $context Additional context (project_id, customer_id, etc.)
     * @param bool $debugMode Enable debug mode (shows detailed errors)
     */
    public function __construct(array $data = [], array $context = [], bool $debugMode = false) {
        $this->data = $data;
        $this->context = $context;
        $this->debugMode = $debugMode;

        $this->registerDefaultFilters();
        $this->initializeErrorHandler();
    }

    /**
     * Initialize error handler
     */
    private function initializeErrorHandler(): void {
        try {
            $this->errorHandler = new SilentFailHandler(null, [
                'log_to_database' => !$this->debugMode,
                'log_to_file' => true,
                'min_severity' => 'warning'
            ]);
        } catch (Exception $e) {
            // Fallback if error handler fails to initialize
            error_log("Failed to initialize SilentFailHandler: " . $e->getMessage());
            $this->errorHandler = null;
        }
    }

    /**
     * Parse template string
     *
     * @param string $template Template content
     * @return string Rendered output
     */
    public function parse(string $template): string {
        $this->parseStartTime = microtime(true);
        $this->errorCount = 0;

        try {
            // Reset figure numbering for each parse
            $this->figureNumbers = [];
            $this->currentSection = null;

            // Parse in order (nested structures first)
            $template = $this->parseForLoops($template);
            $template = $this->parseIfStatements($template);
            $template = $this->parseNullishCoalescing($template);
            $template = $this->parseTernaryOperators($template);
            $template = $this->parseVariables($template);
            $template = $this->processAutomaticFigureNumbering($template);

            return $template;

        } catch (Exception $e) {
            $this->logError(
                'template_parse',
                'parse_error',
                'Template parsing failed: ' . $e->getMessage(),
                ['exception' => get_class($e)]
            );

            return "[Template Parsing Failed: " . ($this->debugMode ? $e->getMessage() : "Unknown error") . "]";
        } finally {
            $this->logPerformanceMetrics();
        }
    }

    /**
     * Parse {{for item in collection}}...{{endfor}} loops
     */
    private function parseForLoops(string $template): string {
        // Match: {{for item in collection}} ... {{endfor}}
        // Also supports: {{for key, value in collection}}
        $pattern = '/\{\{for\s+([\w]+)(?:,\s*([\w]+))?\s+in\s+([\w\.]+)\}\}(.*?)\{\{endfor\}\}/s';

        return preg_replace_callback($pattern, function($matches) {
            try {
                $keyVar = isset($matches[2]) && !empty($matches[2]) ? $matches[1] : null;
                $valueVar = isset($matches[2]) && !empty($matches[2]) ? $matches[2] : $matches[1];
                $collectionPath = $matches[3];
                $loopBlock = $matches[4];

                // Resolve collection
                $collection = $this->resolveVariable($collectionPath);

                if (!is_array($collection) && !is_object($collection)) {
                    $this->logError(
                        'for_loop',
                        'invalid_collection',
                        "Collection '{$collectionPath}' is not iterable",
                        ['collection_path' => $collectionPath, 'type' => gettype($collection)]
                    );
                    return "[Invalid Collection: {$collectionPath}]";
                }

                // Convert objects to arrays
                if (is_object($collection)) {
                    $collection = (array) $collection;
                }

                if (empty($collection)) {
                    return ''; // Empty collection = no output
                }

                $output = '';
                $count = count($collection);
                $index = 0;

                foreach ($collection as $key => $value) {
                    // Create loop context
                    $loopData = array_merge($this->data, [
                        $valueVar => $value,
                        $valueVar . '_index' => $index,
                        $valueVar . '_key' => $key,
                        $valueVar . '_first' => $index === 0,
                        $valueVar . '_last' => $index === $count - 1,
                        $valueVar . '_count' => $count,
                        'loop' => [
                            'index' => $index,
                            'key' => $key,
                            'first' => $index === 0,
                            'last' => $index === $count - 1,
                            'count' => $count
                        ]
                    ]);

                    // Add key variable if specified
                    if ($keyVar !== null) {
                        $loopData[$keyVar] = $key;
                    }

                    // Parse loop block with new context
                    $loopParser = new AdvancedTemplateParser($loopData, $this->context, $this->debugMode);
                    $loopParser->errorHandler = $this->errorHandler; // Share error handler
                    $loopParser->filters = $this->filters; // Share filters
                    $output .= $loopParser->parse($loopBlock);

                    $index++;
                }

                return $output;

            } catch (Exception $e) {
                $this->logError(
                    'for_loop',
                    'loop_execution_error',
                    "For loop failed: " . $e->getMessage(),
                    ['loop_pattern' => $matches[0]]
                );
                return "[For Loop Error: " . ($this->debugMode ? $e->getMessage() : "Loop failed") . "]";
            }
        }, $template);
    }

    /**
     * Parse {{if condition}}...{{else}}...{{endif}}
     */
    private function parseIfStatements(string $template): string {
        // Match nested if statements
        $pattern = '/\{\{if\s+([^\}]+)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{endif\}\}/s';

        return preg_replace_callback($pattern, function($matches) {
            try {
                $condition = trim($matches[1]);
                $ifBlock = $matches[2] ?? '';
                $elseBlock = $matches[3] ?? '';

                $result = $this->evaluateCondition($condition);

                return $result ? $ifBlock : $elseBlock;

            } catch (Exception $e) {
                $this->logError(
                    'if_statement',
                    'condition_evaluation_error',
                    "If condition failed: " . $e->getMessage(),
                    ['condition' => $matches[1]]
                );
                return "[If Statement Error: " . ($this->debugMode ? $e->getMessage() : "Condition failed") . "]";
            }
        }, $template);
    }

    /**
     * Parse {{variable ?? defaultValue}} (nullish coalescing)
     */
    private function parseNullishCoalescing(string $template): string {
        // Match: {{variable ?? default}}
        $pattern = '/\{\{([^\?]+)\?\?\s*([^\}]+)\}\}/';

        return preg_replace_callback($pattern, function($matches) {
            try {
                $variablePath = trim($matches[1]);
                $defaultValue = trim($matches[2]);

                // Try to resolve variable
                try {
                    $value = $this->resolveVariable($variablePath, false); // Don't log error yet

                    // Check if value is "empty" (null, empty string, empty array)
                    if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                        $value = $this->resolveValue($defaultValue);
                    }

                    return $this->formatOutput($value);

                } catch (Exception $e) {
                    // Variable doesn't exist, use default
                    return $this->formatOutput($this->resolveValue($defaultValue));
                }

            } catch (Exception $e) {
                $this->logError(
                    'nullish_coalescing',
                    'evaluation_error',
                    "Nullish coalescing failed: " . $e->getMessage(),
                    ['expression' => $matches[0]]
                );
                return "[Nullish Coalescing Error]";
            }
        }, $template);
    }

    /**
     * Parse {{condition ? valueTrue : valueFalse}} (ternary)
     */
    private function parseTernaryOperators(string $template): string {
        // Match: {{condition ? value1 : value2}}
        $pattern = '/\{\{([^\?]+)\?\s*([^:]+):\s*([^\}]+)\}\}/';

        return preg_replace_callback($pattern, function($matches) {
            try {
                $condition = trim($matches[1]);
                $trueValue = trim($matches[2]);
                $falseValue = trim($matches[3]);

                $result = $this->evaluateCondition($condition);
                $value = $result ? $trueValue : $falseValue;

                return $this->formatOutput($this->resolveValue($value));

            } catch (Exception $e) {
                $this->logError(
                    'ternary_operator',
                    'evaluation_error',
                    "Ternary operator failed: " . $e->getMessage(),
                    ['expression' => $matches[0]]
                );
                return "[Ternary Error]";
            }
        }, $template);
    }

    /**
     * Parse {{variable}} and {{variable | filter}}
     */
    private function parseVariables(string $template): string {
        // Match: {{variable}} or {{variable | filter:param1:param2}}
        $pattern = '/\{\{([\w\.]+(?:\s*\|\s*[\w:]+)?)\}\}/';

        return preg_replace_callback($pattern, function($matches) {
            try {
                $expression = trim($matches[1]);

                // Check for filter
                if (strpos($expression, '|') !== false) {
                    list($variablePath, $filterExpression) = array_map('trim', explode('|', $expression, 2));

                    try {
                        $value = $this->resolveVariable($variablePath);
                        return $this->applyFilter($value, $filterExpression);
                    } catch (Exception $e) {
                        // Variable not found, return placeholder
                        return $this->createMissingVariablePlaceholder($variablePath);
                    }
                }

                // Simple variable
                try {
                    $value = $this->resolveVariable($expression);
                    return $this->formatOutput($value);
                } catch (Exception $e) {
                    return $this->createMissingVariablePlaceholder($expression);
                }

            } catch (Exception $e) {
                $this->logError(
                    'variable_parsing',
                    'parse_error',
                    "Variable parsing failed: " . $e->getMessage(),
                    ['expression' => $matches[0]]
                );
                return "[Parse Error]";
            }
        }, $template);
    }

    /**
     * Resolve variable using dot notation
     *
     * @param string $path Variable path (e.g., 'project.name')
     * @param bool $logError Whether to log error if not found
     * @return mixed Variable value
     * @throws Exception If variable not found
     */
    private function resolveVariable(string $path, bool $logError = true) {
        $keys = explode('.', $path);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } elseif (is_object($value) && property_exists($value, $key)) {
                $value = $value->$key;
            } else {
                if ($logError) {
                    $this->createMissingVariablePlaceholder($path);
                }
                throw new Exception("Variable not found: {$path}");
            }
        }

        return $value;
    }

    /**
     * Resolve value (variable or literal)
     */
    private function resolveValue(string $value) {
        $value = trim($value);

        // String literal (single or double quotes)
        if (preg_match('/^([\'"])(.*)\\1$/', $value, $matches)) {
            return $matches[2];
        }

        // Number literal
        if (is_numeric($value)) {
            return $value;
        }

        // Boolean literal
        if ($value === 'true') return true;
        if ($value === 'false') return false;
        if ($value === 'null') return null;

        // Variable - try to resolve
        try {
            return $this->resolveVariable($value, false);
        } catch (Exception $e) {
            // Return as literal string if not found
            return $value;
        }
    }

    /**
     * Evaluate condition
     */
    private function evaluateCondition(string $condition): bool {
        try {
            // Replace variables with actual values
            $condition = preg_replace_callback('/\b([\w\.]+)\b/', function($matches) {
                $varName = $matches[1];

                // Skip operators and keywords
                $reserved = ['and', 'or', 'not', 'true', 'false', 'null', 'empty', 'isset'];
                if (in_array(strtolower($varName), $reserved)) {
                    return $varName;
                }

                try {
                    $value = $this->resolveVariable($varName, false);

                    // Convert to evaluable format
                    if (is_bool($value)) {
                        return $value ? 'true' : 'false';
                    } elseif (is_string($value)) {
                        return "'" . addslashes($value) . "'";
                    } elseif (is_numeric($value)) {
                        return $value;
                    } elseif (is_null($value)) {
                        return 'null';
                    } elseif (is_array($value) || is_object($value)) {
                        return empty($value) ? 'false' : 'true';
                    }

                    return $value ? 'true' : 'false';
                } catch (Exception $e) {
                    return 'false'; // Variable not found = false
                }
            }, $condition);

            // Convert operators
            $condition = str_replace(['&&', '||', '!'], [' and ', ' or ', ' not '], $condition);

            // Safe evaluation using PHP's expression parser
            $result = @eval("return ({$condition});");

            return (bool) $result;

        } catch (Exception $e) {
            $this->logError(
                'condition_evaluation',
                'evaluation_error',
                "Condition evaluation failed: " . $e->getMessage(),
                ['condition' => $condition]
            );
            return false;
        }
    }

    /**
     * Apply filter to value
     */
    private function applyFilter($value, string $filterExpression) {
        try {
            // Parse filter with parameters: filter:param1:param2
            $parts = explode(':', $filterExpression);
            $filterName = trim(array_shift($parts));
            $params = array_map('trim', $parts);

            if (!isset($this->filters[$filterName])) {
                $this->logError(
                    'filter_application',
                    'filter_not_found',
                    "Filter '{$filterName}' not found",
                    ['filter_name' => $filterName, 'available_filters' => array_keys($this->filters)]
                );
                return "[Unknown Filter: {$filterName}]";
            }

            // Apply filter
            $result = call_user_func($this->filters[$filterName], $value, $params, $this->data);
            return $this->formatOutput($result);

        } catch (Exception $e) {
            $this->logError(
                'filter_application',
                'filter_execution_error',
                "Filter execution failed: " . $e->getMessage(),
                ['filter_expression' => $filterExpression, 'value_type' => gettype($value)]
            );
            return "[Filter Error: {$filterExpression}]";
        }
    }

    /**
     * Format output value
     */
    private function formatOutput($value): string {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        if (is_null($value)) {
            return '';
        }

        return (string) $value;
    }

    /**
     * Create missing variable placeholder and log error
     */
    private function createMissingVariablePlaceholder(string $variablePath): string {
        $this->errorCount++;

        if ($this->errorHandler) {
            return $this->errorHandler->missingVariable(
                $variablePath,
                'advanced_template_parser',
                array_merge($this->context, ['template_data_keys' => array_keys($this->data)])
            );
        }

        return "[Missing Variable: {$variablePath}]";
    }

    /**
     * Log error
     */
    private function logError(string $operation, string $errorType, string $message, array $context = []): void {
        $this->errorCount++;

        if ($this->errorHandler) {
            $this->errorHandler->log(
                'advanced_template_parser',
                $operation,
                $errorType,
                $message,
                array_merge($this->context, $context),
                '',
                'warning'
            );
        } else {
            error_log("[AdvancedTemplateParser] {$operation}/{$errorType}: {$message}");
        }
    }

    /**
     * Process automatic figure numbering
     * Finds <img> tags and adds figure numbers like Fig. 1.2.3
     */
    private function processAutomaticFigureNumbering(string $template): string {
        // Track current section by finding H2 headers
        $this->currentSection = '1'; // Default to section 1

        // Replace H2 headers and track section numbers
        $sectionNumber = 1;
        $template = preg_replace_callback('/<h2[^>]*>(.*?)<\/h2>/i', function($matches) use (&$sectionNumber) {
            $this->currentSection = (string) $sectionNumber;
            $sectionNumber++;
            return $matches[0]; // Return unchanged
        }, $template);

        // Replace IMG tags with figure numbering
        $template = preg_replace_callback('/<img([^>]*)>/i', function($matches) {
            $section = $this->currentSection ?? '1';

            // Initialize section counter if needed
            if (!isset($this->figureNumbers[$section])) {
                $this->figureNumbers[$section] = 0;
            }

            $this->figureNumbers[$section]++;
            $figureNumber = "{$section}.{$this->figureNumbers[$section]}";

            // Check if image already has a figure number data attribute
            if (strpos($matches[1], 'data-figure=') !== false) {
                return $matches[0]; // Already numbered
            }

            // Add figure number as data attribute
            $imgTag = '<img' . $matches[1] . ' data-figure="Fig. ' . $figureNumber . '">';

            // If next element is not a figcaption, add one
            return $imgTag;
        }, $template);

        return $template;
    }

    /**
     * Log performance metrics
     */
    private function logPerformanceMetrics(): void {
        if (!$this->debugMode) {
            return;
        }

        $duration = microtime(true) - $this->parseStartTime;
        error_log(sprintf(
            "[AdvancedTemplateParser] Parse completed in %.4fs with %d errors",
            $duration,
            $this->errorCount
        ));
    }

    /**
     * Register default filters
     */
    private function registerDefaultFilters(): void {
        $this->filters = [
            // Text filters
            'escape' => fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'),
            'upper' => fn($v) => mb_strtoupper($v),
            'lower' => fn($v) => mb_strtolower($v),
            'capitalize' => fn($v) => mb_convert_case($v, MB_CASE_TITLE),
            'trim' => fn($v) => trim($v),
            'truncate' => fn($v, $params) => mb_substr($v, 0, $params[0] ?? 100) . (mb_strlen($v) > ($params[0] ?? 100) ? '...' : ''),
            'nl2br' => fn($v) => nl2br($v),
            'strip_tags' => fn($v) => strip_tags($v),

            // Number filters
            'currency' => fn($v) => number_format((float)$v, 2, ',', '.') . ' kr',
            'money' => fn($v) => number_format((float)$v, 2, ',', '.') . ' kr',
            'number' => fn($v, $params) => number_format((float)$v, $params[0] ?? 0, ',', '.'),
            'percent' => fn($v) => number_format((float)$v, 2) . '%',
            'round' => fn($v, $params) => round((float)$v, $params[0] ?? 0),

            // Date filters
            'date' => fn($v, $params) => date($params[0] ?? 'd-m-Y', is_numeric($v) ? $v : strtotime($v)),
            'datetime' => fn($v) => date('d-m-Y H:i', is_numeric($v) ? $v : strtotime($v)),
            'time' => fn($v) => date('H:i', is_numeric($v) ? $v : strtotime($v)),

            // Array filters
            'length' => fn($v) => is_array($v) || is_countable($v) ? count($v) : strlen($v),
            'count' => fn($v) => is_array($v) || is_countable($v) ? count($v) : 0,
            'join' => fn($v, $params) => is_array($v) ? implode($params[0] ?? ', ', $v) : $v,
            'first' => fn($v) => is_array($v) ? reset($v) : $v,
            'last' => fn($v) => is_array($v) ? end($v) : $v,

            // Encoding filters
            'json' => fn($v) => json_encode($v, JSON_UNESCAPED_UNICODE),
            'url' => fn($v) => urlencode($v),
            'base64' => fn($v) => base64_encode($v),

            // Other filters
            'default' => fn($v, $params) => $v ?: ($params[0] ?? ''),
            'trans' => fn($v, $params, $data) => $this->translate($v, $data['locale'] ?? 'da'),
        ];
    }

    /**
     * Translation helper (placeholder - implement based on your i18n system)
     */
    private function translate(string $text, string $locale = 'da'): string {
        // TODO: Implement actual translation system
        // For now, just return the text as-is
        return $text;
    }

    /**
     * Add custom filter
     */
    public function addFilter(string $name, callable $callback): void {
        $this->filters[$name] = $callback;
    }

    /**
     * Set data
     */
    public function setData(array $data): void {
        $this->data = $data;
    }

    /**
     * Add data
     */
    public function addData(string $key, $value): void {
        $this->data[$key] = $value;
    }

    /**
     * Set context
     */
    public function setContext(array $context): void {
        $this->context = $context;
    }

    /**
     * Get error count
     */
    public function getErrorCount(): int {
        return $this->errorCount;
    }

    /**
     * Flush error handler (save buffered logs)
     */
    public function flush(): void {
        if ($this->errorHandler) {
            $this->errorHandler->flush();
        }
    }
}

/**
 * Helper function to render advanced template
 */
function render_advanced_template(string $template, array $data = [], array $context = []): string {
    $parser = new AdvancedTemplateParser($data, $context);
    $output = $parser->parse($template);
    $parser->flush(); // Ensure all errors are logged
    return $output;
}
