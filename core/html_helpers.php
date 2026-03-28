<?php
/**
 * HTML Helpers
 *
 * Clean, semantic HTML generation with no inline JavaScript
 * Uses data attributes for event delegation
 *
 * @package DueDiligence
 * @version 2.0.0
 * @author Claude Code
 */

/**
 * Generate HTML document structure
 *
 * @param string $content Main content
 * @param array $options Page options
 * @return string Complete HTML document
 */
function html_page(string $content, array $options = []): string {
    $defaults = [
        'title' => 'DueDiligence',
        'css' => [],
        'js' => [],
        'meta' => [],
        'bodyClass' => '',
        'preloadModules' => []
    ];

    $opts = array_merge($defaults, $options);

    $html = '<!DOCTYPE html>';
    $html .= '<html lang="da">';
    $html .= '<head>';
    $html .= '<meta charset="UTF-8">';
    $html .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    $html .= '<title>' . htmlspecialchars($opts['title']) . '</title>';

    // Meta tags
    foreach ($opts['meta'] as $name => $content) {
        $html .= sprintf('<meta name="%s" content="%s">', htmlspecialchars($name), htmlspecialchars($content));
    }

    // CSS files
    foreach ($opts['css'] as $css) {
        $html .= sprintf('<link rel="stylesheet" href="%s">', htmlspecialchars($css));
    }

    // Preload modules
    if (!empty($opts['preloadModules'])) {
        $html .= '<script>window.preloadModules = ' . json_encode($opts['preloadModules']) . ';</script>';
    }

    $html .= '</head>';
    $html .= sprintf('<body class="%s">', htmlspecialchars($opts['bodyClass']));
    $html .= $content;

    // JS files (at end for performance)
    foreach ($opts['js'] as $js) {
        $html .= sprintf('<script src="%s" defer></script>', htmlspecialchars($js));
    }

    $html .= '</body>';
    $html .= '</html>';

    return $html;
}

/**
 * Generate button with data attributes (no inline onclick)
 *
 * @param string $text Button text
 * @param array $attributes Button attributes
 * @return string HTML button
 */
function html_button(string $text, array $attributes = []): string {
    $defaults = [
        'type' => 'button',
        'class' => 'btn',
        'disabled' => false,
        'data' => []
    ];

    $attrs = array_merge($defaults, $attributes);

    $html = '<button';
    $html .= sprintf(' type="%s"', htmlspecialchars($attrs['type']));
    $html .= sprintf(' class="%s"', htmlspecialchars($attrs['class']));

    if ($attrs['disabled']) {
        $html .= ' disabled';
    }

    // Data attributes for event delegation
    foreach ($attrs['data'] as $key => $value) {
        $html .= sprintf(' data-%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
    }

    // Other attributes
    foreach ($attrs as $key => $value) {
        if (!in_array($key, ['type', 'class', 'disabled', 'data'])) {
            $html .= sprintf(' %s="%s"', htmlspecialchars($key), htmlspecialchars($value));
        }
    }

    $html .= '>';
    $html .= htmlspecialchars($text);
    $html .= '</button>';

    return $html;
}

/**
 * Generate link with data attributes
 *
 * @param string $text Link text
 * @param string $href Link URL
 * @param array $attributes Link attributes
 * @return string HTML link
 */
function html_link(string $text, string $href, array $attributes = []): string {
    $defaults = [
        'class' => '',
        'target' => '',
        'data' => []
    ];

    $attrs = array_merge($defaults, $attributes);

    $html = '<a';
    $html .= sprintf(' href="%s"', htmlspecialchars($href));

    if ($attrs['class']) {
        $html .= sprintf(' class="%s"', htmlspecialchars($attrs['class']));
    }

    if ($attrs['target']) {
        $html .= sprintf(' target="%s"', htmlspecialchars($attrs['target']));
    }

    // Data attributes
    foreach ($attrs['data'] as $key => $value) {
        $html .= sprintf(' data-%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
    }

    $html .= '>';
    $html .= htmlspecialchars($text);
    $html .= '</a>';

    return $html;
}

/**
 * Generate module container with lazy loading
 *
 * @param string $moduleName Module name for lazy loading
 * @param string $content Module content
 * @param array $options Container options
 * @return string HTML container
 */
function html_module_container(string $moduleName, string $content, array $options = []): string {
    $defaults = [
        'class' => '',
        'lazy' => false,
        'data' => []
    ];

    $opts = array_merge($defaults, $options);

    $html = '<div';
    $html .= sprintf(' data-module="%s"', htmlspecialchars($moduleName));

    if ($opts['class']) {
        $html .= sprintf(' class="%s"', htmlspecialchars($opts['class']));
    }

    if ($opts['lazy']) {
        $html .= ' data-lazy';
    }

    // Additional data attributes
    foreach ($opts['data'] as $key => $value) {
        $html .= sprintf(' data-%s="%s"', htmlspecialchars($key), is_array($value) ? json_encode($value) : htmlspecialchars($value));
    }

    $html .= '>';
    $html .= $content;
    $html .= '</div>';

    return $html;
}

/**
 * Generate table with sortable columns
 *
 * @param array $columns Table columns ['key' => 'Label']
 * @param array $rows Table rows
 * @param array $options Table options
 * @return string HTML table
 */
function html_table(array $columns, array $rows, array $options = []): string {
    $defaults = [
        'class' => 'table',
        'sortable' => false,
        'striped' => true,
        'hover' => true,
        'rowData' => [] // Data attributes per row
    ];

    $opts = array_merge($defaults, $options);

    $html = sprintf('<table class="%s">', htmlspecialchars($opts['class']));

    // Header
    $html .= '<thead><tr>';
    foreach ($columns as $key => $label) {
        $html .= '<th';
        if ($opts['sortable']) {
            $html .= sprintf(' data-sort="%s"', htmlspecialchars($key));
        }
        $html .= '>';
        $html .= htmlspecialchars($label);
        if ($opts['sortable']) {
            $html .= ' <span class="sort-icon"></span>';
        }
        $html .= '</th>';
    }
    $html .= '</tr></thead>';

    // Body
    $html .= '<tbody>';
    foreach ($rows as $index => $row) {
        $html .= '<tr';

        // Row data attributes
        if (isset($opts['rowData'][$index])) {
            foreach ($opts['rowData'][$index] as $key => $value) {
                $html .= sprintf(' data-%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
            }
        }

        $html .= '>';

        foreach ($columns as $key => $label) {
            $html .= '<td>';
            $html .= htmlspecialchars($row[$key] ?? '');
            $html .= '</td>';
        }

        $html .= '</tr>';
    }
    $html .= '</tbody>';

    $html .= '</table>';

    return $html;
}

/**
 * Generate form with CSRF token
 *
 * @param string $action Form action URL
 * @param string $content Form content
 * @param array $options Form options
 * @return string HTML form
 */
function html_form(string $action, string $content, array $options = []): string {
    $defaults = [
        'method' => 'POST',
        'class' => '',
        'enctype' => '',
        'data' => []
    ];

    $opts = array_merge($defaults, $options);

    $html = '<form';
    $html .= sprintf(' action="%s"', htmlspecialchars($action));
    $html .= sprintf(' method="%s"', htmlspecialchars($opts['method']));

    if ($opts['class']) {
        $html .= sprintf(' class="%s"', htmlspecialchars($opts['class']));
    }

    if ($opts['enctype']) {
        $html .= sprintf(' enctype="%s"', htmlspecialchars($opts['enctype']));
    }

    // Data attributes
    foreach ($opts['data'] as $key => $value) {
        $html .= sprintf(' data-%s="%s"', htmlspecialchars($key), htmlspecialchars($value));
    }

    $html .= '>';

    // CSRF token
    if (strtoupper($opts['method']) === 'POST') {
        $html .= '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
    }

    $html .= $content;
    $html .= '</form>';

    return $html;
}

/**
 * Generate pagination controls
 *
 * @param array $pagination Pagination data from api_crud_list
 * @param array $options Pagination options
 * @return string HTML pagination
 */
function html_pagination(array $pagination, array $options = []): string {
    $defaults = [
        'class' => 'pagination',
        'showFirst' => true,
        'showLast' => true,
        'maxPages' => 5
    ];

    $opts = array_merge($defaults, $options);

    if ($pagination['pages'] <= 1) {
        return '';
    }

    $html = sprintf('<nav class="%s">', htmlspecialchars($opts['class']));
    $html .= '<ul>';

    $current = $pagination['page'];
    $total = $pagination['pages'];

    // First page
    if ($opts['showFirst'] && $current > 1) {
        $html .= '<li><a href="?page=1" data-page="1">&laquo; Første</a></li>';
    }

    // Previous
    if ($current > 1) {
        $html .= sprintf('<li><a href="?page=%d" data-page="%d">&lsaquo; Forrige</a></li>', $current - 1, $current - 1);
    }

    // Page numbers
    $start = max(1, $current - floor($opts['maxPages'] / 2));
    $end = min($total, $start + $opts['maxPages'] - 1);

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i === $current) ? ' class="active"' : '';
        $html .= sprintf('<li%s><a href="?page=%d" data-page="%d">%d</a></li>', $active, $i, $i, $i);
    }

    // Next
    if ($current < $total) {
        $html .= sprintf('<li><a href="?page=%d" data-page="%d">Næste &rsaquo;</a></li>', $current + 1, $current + 1);
    }

    // Last page
    if ($opts['showLast'] && $current < $total) {
        $html .= sprintf('<li><a href="?page=%d" data-page="%d">Sidste &raquo;</a></li>', $total, $total);
    }

    $html .= '</ul>';
    $html .= '</nav>';

    return $html;
}

/**
 * Generate alert/notification box
 *
 * @param string $message Alert message
 * @param string $type Alert type (success, error, warning, info)
 * @param array $options Alert options
 * @return string HTML alert
 */
function html_alert(string $message, string $type = 'info', array $options = []): string {
    $defaults = [
        'dismissible' => true,
        'icon' => true
    ];

    $opts = array_merge($defaults, $options);

    $icons = [
        'success' => '✓',
        'error' => '✕',
        'warning' => '⚠',
        'info' => 'ℹ'
    ];

    $html = sprintf('<div class="alert alert-%s"', htmlspecialchars($type));
    if ($opts['dismissible']) {
        $html .= ' data-dismissible';
    }
    $html .= '>';

    if ($opts['icon'] && isset($icons[$type])) {
        $html .= sprintf('<span class="alert-icon">%s</span>', $icons[$type]);
    }

    $html .= sprintf('<span class="alert-message">%s</span>', htmlspecialchars($message));

    if ($opts['dismissible']) {
        $html .= '<button class="alert-close" data-action="dismiss" aria-label="Close">&times;</button>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Generate card/panel
 *
 * @param string $title Card title
 * @param string $content Card content
 * @param array $options Card options
 * @return string HTML card
 */
function html_card(string $title, string $content, array $options = []): string {
    $defaults = [
        'class' => 'card',
        'header' => true,
        'footer' => null
    ];

    $opts = array_merge($defaults, $options);

    $html = sprintf('<div class="%s">', htmlspecialchars($opts['class']));

    if ($opts['header']) {
        $html .= '<div class="card-header">';
        $html .= sprintf('<h3>%s</h3>', htmlspecialchars($title));
        $html .= '</div>';
    }

    $html .= '<div class="card-body">';
    $html .= $content;
    $html .= '</div>';

    if ($opts['footer']) {
        $html .= '<div class="card-footer">';
        $html .= $opts['footer'];
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Generate modal dialog
 *
 * @param string $id Modal ID
 * @param string $title Modal title
 * @param string $content Modal content
 * @param array $options Modal options
 * @return string HTML modal
 */
function html_modal(string $id, string $title, string $content, array $options = []): string {
    $defaults = [
        'size' => 'medium', // small, medium, large
        'footer' => null,
        'closable' => true
    ];

    $opts = array_merge($defaults, $options);

    $html = sprintf('<div class="modal" id="%s" data-modal>', htmlspecialchars($id));
    $html .= '<div class="modal-overlay" data-action="close-modal"></div>';
    $html .= sprintf('<div class="modal-dialog modal-%s">', htmlspecialchars($opts['size']));

    $html .= '<div class="modal-header">';
    $html .= sprintf('<h3>%s</h3>', htmlspecialchars($title));
    if ($opts['closable']) {
        $html .= '<button class="modal-close" data-action="close-modal" aria-label="Close">&times;</button>';
    }
    $html .= '</div>';

    $html .= '<div class="modal-body">';
    $html .= $content;
    $html .= '</div>';

    if ($opts['footer']) {
        $html .= '<div class="modal-footer">';
        $html .= $opts['footer'];
        $html .= '</div>';
    }

    $html .= '</div>';
    $html .= '</div>';

    return $html;
}

/**
 * Generate loading spinner
 *
 * @param array $options Spinner options
 * @return string HTML spinner
 */
function html_spinner(array $options = []): string {
    $defaults = [
        'size' => 'medium', // small, medium, large
        'text' => 'Indlæser...'
    ];

    $opts = array_merge($defaults, $options);

    $html = sprintf('<div class="spinner spinner-%s">', htmlspecialchars($opts['size']));
    $html .= '<div class="spinner-icon"></div>';
    if ($opts['text']) {
        $html .= sprintf('<span class="spinner-text">%s</span>', htmlspecialchars($opts['text']));
    }
    $html .= '</div>';

    return $html;
}
