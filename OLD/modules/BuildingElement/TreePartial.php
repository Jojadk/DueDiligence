<?php
// Recursive Tree Partial
// Expects: $nodes (array of elements with 'children' key)
// Expects: $activeId (int/null)

if (!function_exists('renderTreeNodes')) {
    function renderTreeNodes($nodes, $activeId, $depth = 0)
    {
        if (empty($nodes))
            return;

        echo '<ul class="tree-level-' . $depth . '" style="list-style:none; padding-left:' . ($depth ? '5px' : '0') . '; min-height:10px;">';
        foreach ($nodes as $node) {
            $isActive = ($activeId == $node['id']);
            $hasChildren = !empty($node['children']);

            // Add Draggable Attributes
            echo '<li class="tree-node" data-id="' . $node['id'] . '" draggable="true">';

            // Content Row (Drop Target)
            echo '<div class="tree-content ' . ($isActive ? 'active' : '') . '" 
                    style="display:flex; align-items:center; padding:4px 8px; cursor:pointer; position:relative;" 
                    data-node-id="' . $node['id'] . '">';

            // Icon / Toggler
            if ($hasChildren) {
                echo '<span class="tree-toggle" data-action="toggle" style="margin-right:5px; transform:rotate(90deg); display:inline-block; width:15px; text-align:center;"><i class="fas fa-caret-right"></i></span>';
            } else {
                echo '<span class="tree-toggle" style="width:15px; display:inline-block; margin-right:5px;"></span>';
            }

            // Auto Numbering Span (JS will populate)
            echo '<span class="tree-number" style="font-weight:600; margin-right:5px; color:' . ($isActive ? '#fff' : '#aaa') . ';"></span>';

            // Name
            echo '<span class="tree-name" data-i18n="' . htmlspecialchars($node['name']) . '">' . htmlspecialchars($node['name']) . '</span>';
            echo '</div>'; // End content row

            // Children Recursive
            if ($hasChildren) {
                renderTreeNodes($node['children'], $activeId, $depth + 1);
            } else {
                // Empty placeholder UL for dropping children
                echo '<ul class="tree-level-' . ($depth + 1) . '" style="list-style:none; padding-left:5px; min-height:5px;"></ul>';
            }

            echo '</li>';
        }
        echo '</ul>';
    }
}

// Render the root list
renderTreeNodes($tree, $activeElement['id'] ?? null);
?>