<?php
/**
 * Template Parser
 * Supports: {if} {else} {endif}, {variable}, {variable|filter}, {condition?val1:val2}, {while}
 */

class TemplateParser {
    private array $data = [];
    private array $filters = [];

    public function __construct(array $data = []) {
        $this->data = $data;
        $this->registerDefaultFilters();
    }

    /**
     * Parse template string
     */
    public function parse(string $template): string {
        // Parse nested structures
        $template = $this->parseWhileLoops($template);
        $template = $this->parseIfStatements($template);
        $template = $this->parseTernaryOperators($template);
        $template = $this->parseVariables($template);

        return $template;
    }

    /**
     * Parse {if condition} {else} {endif}
     */
    private function parseIfStatements(string $template): string {
        $pattern = '/\{if\s+([^\}]+)\}(.*?)(?:\{else\}(.*?))?\{endif\}/s';

        return preg_replace_callback($pattern, function($matches) {
            $condition = trim($matches[1]);
            $ifBlock = $matches[2];
            $elseBlock = $matches[3] ?? '';

            $result = $this->evaluateCondition($condition);

            return $result ? $ifBlock : $elseBlock;
        }, $template);
    }

    /**
     * Parse {condition ? value1 : value2}
     */
    private function parseTernaryOperators(string $template): string {
        $pattern = '/\{([^\?]+)\?\s*([^:]+):\s*([^\}]+)\}/';

        return preg_replace_callback($pattern, function($matches) {
            $condition = trim($matches[1]);
            $trueValue = trim($matches[2]);
            $falseValue = trim($matches[3]);

            $result = $this->evaluateCondition($condition);

            $value = $result ? $trueValue : $falseValue;
            return $this->resolveValue($value);
        }, $template);
    }

    /**
     * Parse {while array as item} {endwhile}
     */
    private function parseWhileLoops(string $template): string {
        $pattern = '/\{while\s+(\w+)\s+as\s+(\w+)\}(.*?)\{endwhile\}/s';

        return preg_replace_callback($pattern, function($matches) {
            $arrayName = $matches[1];
            $itemName = $matches[2];
            $loopBlock = $matches[3];

            $array = $this->data[$arrayName] ?? [];
            if (!is_array($array)) return '';

            $output = '';
            foreach ($array as $index => $item) {
                $loopParser = new TemplateParser(array_merge($this->data, [
                    $itemName => $item,
                    $itemName . '_index' => $index,
                    $itemName . '_first' => $index === 0,
                    $itemName . '_last' => $index === count($array) - 1
                ]));
                $output .= $loopParser->parse($loopBlock);
            }

            return $output;
        }, $template);
    }

    /**
     * Parse {variable} and {variable|filter}
     */
    private function parseVariables(string $template): string {
        $pattern = '/\{(\w+(?:\.\w+)*(?:\|[\w:]+)?)\}/';

        return preg_replace_callback($pattern, function($matches) {
            $expression = $matches[1];

            // Check for filter
            if (strpos($expression, '|') !== false) {
                [$variable, $filter] = explode('|', $expression, 2);
                $value = $this->getValue(trim($variable));
                return $this->applyFilter($value, trim($filter));
            }

            return $this->getValue($expression);
        }, $template);
    }

    /**
     * Get value from data using dot notation
     */
    private function getValue(string $path) {
        $keys = explode('.', $path);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } elseif (is_object($value) && property_exists($value, $key)) {
                $value = $value->$key;
            } else {
                return '';
            }
        }

        return $value;
    }

    /**
     * Resolve value (could be variable or literal)
     */
    private function resolveValue(string $value) {
        $value = trim($value);

        // String literal
        if (preg_match('/^[\'"](.+)[\'"]$/', $value, $matches)) {
            return $matches[1];
        }

        // Number literal
        if (is_numeric($value)) {
            return $value;
        }

        // Variable
        return $this->getValue($value);
    }

    /**
     * Evaluate condition
     */
    private function evaluateCondition(string $condition): bool {
        // Replace variables with values
        $condition = preg_replace_callback('/\b(\w+(?:\.\w+)*)\b/', function($matches) {
            $value = $this->getValue($matches[1]);
            if (is_bool($value)) return $value ? 'true' : 'false';
            if (is_string($value)) return "'" . addslashes($value) . "'";
            if (is_numeric($value)) return $value;
            return $value ? 'true' : 'false';
        }, $condition);

        // Safe evaluation
        try {
            return eval("return ($condition);");
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Apply filter to value
     */
    private function applyFilter($value, string $filterExpression) {
        // Parse filter with parameters: filter:param1:param2
        $parts = explode(':', $filterExpression);
        $filterName = array_shift($parts);
        $params = $parts;

        if (!isset($this->filters[$filterName])) {
            return $value;
        }

        return call_user_func($this->filters[$filterName], $value, ...$params);
    }

    /**
     * Register default filters
     */
    private function registerDefaultFilters() {
        $this->filters = [
            'escape' => fn($v) => htmlspecialchars($v, ENT_QUOTES, 'UTF-8'),
            'upper' => fn($v) => mb_strtoupper($v),
            'lower' => fn($v) => mb_strtolower($v),
            'capitalize' => fn($v) => mb_convert_case($v, MB_CASE_TITLE),
            'trim' => fn($v) => trim($v),
            'money' => fn($v) => number_format($v, 2, ',', '.') . ' kr',
            'number' => fn($v, $decimals = 0) => number_format($v, $decimals, ',', '.'),
            'date' => fn($v, $format = 'd-m-Y') => date($format, strtotime($v)),
            'default' => fn($v, $default = '') => $v ?: $default,
            'length' => fn($v) => is_array($v) || is_countable($v) ? count($v) : strlen($v),
            'truncate' => fn($v, $length = 100) => mb_substr($v, 0, $length) . (mb_strlen($v) > $length ? '...' : ''),
            'nl2br' => fn($v) => nl2br($v),
            'json' => fn($v) => json_encode($v),
            'url' => fn($v) => urlencode($v)
        ];
    }

    /**
     * Register custom filter
     */
    public function addFilter(string $name, callable $callback) {
        $this->filters[$name] = $callback;
    }

    /**
     * Set data
     */
    public function setData(array $data) {
        $this->data = $data;
    }

    /**
     * Add data
     */
    public function addData(string $key, $value) {
        $this->data[$key] = $value;
    }
}

/**
 * Helper function to render template
 */
function render_template(string $template, array $data = []): string {
    $parser = new TemplateParser($data);
    return $parser->parse($template);
}
