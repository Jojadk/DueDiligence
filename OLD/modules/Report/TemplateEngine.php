<?php
namespace Modules\Report;

class TemplateEngine
{
    private $data = [];

    public function render($template, $data)
    {
        $this->data = $data;
        $code = $this->compile($template);

        // Output buffering to capture eval output
        ob_start();
        try {
            eval ('?>' . $code);
        } catch (\Throwable $e) {
            echo "Template Error: " . $e->getMessage();
        }
        return ob_get_clean();
    }

    private function compile($html)
    {
        // 1. Escape basic curly braces that shouldn't be parsed?
        // Assuming strict syntax {tag}.

        // 2. Foreach: {foreach list as item}
        // distinct variable names: list -> $this->get('list'), item -> $item
        // We inject $item back into $this->data for subsequent lookups.
        // NOTE: This simple engine doesn't support nested scope restoration automatically safely, 
        // implies 'item' overwrites any global 'item'.
        $html = preg_replace_callback('/\{foreach\s+([a-zA-Z0-9_.]+)\s+as\s+([a-zA-Z0-9_]+)\}/', function ($m) {
            $list = $m[1];
            $item = $m[2];
            return "<?php foreach (\$this->get('$list') as \$$item): \$this->set('$item', \$$item); ?>";
        }, $html);

        $html = str_replace('{/foreach}', '<?php endforeach; ?>', $html);

        // 3. If: {if var}
        $html = preg_replace_callback('/\{if\s+([a-zA-Z0-9_.]+)\}/', function ($m) {
            $var = $m[1];
            return "<?php if (\$this->get('$var')): ?>";
        }, $html);

        $html = str_replace('{else}', '<?php else: ?>', $html);
        $html = str_replace('{/if}', '<?php endif; ?>', $html);

        // 4. Variables: {{ var }} or {{ var | money }}
        $html = preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.]+)(\s*\|\s*[a-zA-Z0-9_]+)?\s*\}\}/', function ($m) {
            $var = $m[1];
            $filter = isset($m[2]) ? trim($m[2]) : '';
            // filter is "| money"
            $modifier = "";
            if ($filter) {
                $fName = trim(substr($filter, 1)); // remove |
                if ($fName == 'money')
                    $modifier = ", 'money'";
                if ($fName == 'date')
                    $modifier = ", 'date'";
                if ($fName == 'nl2br')
                    $modifier = ", 'nl2br'";
            }
            return "<?php echo \$this->e(\$this->get('$var')$modifier); ?>";
        }, $html);

        return $html;
    }

    private function get($key)
    {
        // Support dot notation: user.name
        $parts = explode('.', $key);
        $val = $this->data;

        foreach ($parts as $p) {
            if (is_array($val) && isset($val[$p])) {
                $val = $val[$p];
            } elseif (is_object($val) && isset($val->$p)) {
                $val = $val->$p;
            } else {
                return null; // Not found
            }
        }
        return $val;
    }

    private function set($key, $val)
    {
        $this->data[$key] = $val;
    }

    private function e($val, $filter = null)
    {
        if ($filter === 'money') {
            return number_format((float) $val, 2, ',', '.') . ' DKK';
        }
        if ($filter === 'date') {
            return date('d.m.Y', strtotime($val ?? 'now'));
        }
        if ($filter === 'nl2br') {
            return nl2br(htmlspecialchars($val));
        }
        return htmlspecialchars((string) $val);
    }

    // Helper to allow simple logic in templates like generic PHP calls? No.
    // Kept strictly to data.

    // Special helper for iterating logic support
    public function iter($val)
    {
        return is_array($val) ? $val : [];
    }
}
