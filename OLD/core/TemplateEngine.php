<?php
namespace Core;

class TemplateEngine
{
    private $template;
    private $data;

    public function __construct($templateContent, $data)
    {
        $this->template = $templateContent;
        $this->data = $data;
    }

    public function render()
    {
        $content = $this->template;

        // 1. Handle Foreach Loops {{foreach elements}} ... {{endforeach}}
        // Simple regex parser for flat loops
        $content = preg_replace_callback('/\{\{foreach\s+(\w+)\}\}(.*?)\{\{endforeach\}\}/s', function ($matches) {
            $arrayName = $matches[1];
            $block = $matches[2];
            $output = '';

            if (isset($this->data[$arrayName]) && is_array($this->data[$arrayName])) {
                foreach ($this->data[$arrayName] as $item) {
                    // Replace {{item.key}} in block
                    $itemOutput = $block;
                    foreach ($item as $key => $val) {
                        if (is_array($val))
                            continue; // skip nested for this simple engine
                        $itemOutput = str_replace("{{{$key}}}", $val, $itemOutput);
                    }
                    $output .= $itemOutput;
                }
            }
            return $output;
        }, $content);

        // 2. Handle Simple Variables {{project_name}}
        // Include Global Constants and Project Constants in Data
        foreach ($this->data as $key => $val) {
            if (is_string($val) || is_numeric($val)) {
                $content = str_replace("{{{$key}}}", $val, $content);
            }
        }

        // 3. Handle Auto-ToC (Look for <h2>)
        // In a real PDF engine (like TCPDF/MPDF) we insert TOC. 
        // Here we simulate by generating an HTML list
        preg_match_all('/<h2>(.*?)<\/h2>/', $content, $headings);
        $toc = "<ul>";
        foreach ($headings[1] as $h) {
            $toc .= "<li>$h</li>";
        }
        $toc .= "</ul>";
        $content = str_replace("{{TOC}}", $toc, $content);

        return $content;
    }
}
