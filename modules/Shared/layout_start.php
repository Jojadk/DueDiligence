<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">
    <title>Construction Admin System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/window.css?v=<?= time() ?>">

    <!-- Core Scripts -->
    <script src="<?= \Core\Asset::js('assets/js/error_handler.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/i18n.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/modules/utils.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/core.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/canvas-engine.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/window_manager.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/app.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/modules/autosave.js') ?>"></script>


    <style>
        /* Locking Styles */
        .locked-field {
            border: 2px solid #e74c3c !important;
            /* Red border or distinct color */
            position: relative;
        }

        .locked-label {
            display: block;
            background: #e74c3c;
            color: white;
            font-size: 0.75rem;
            padding: 2px 6px;
            border-radius: 0 0 4px 4px;
            margin-top: -1px;
            width: fit-content;
        }

        /* Dropdown Styles for Tools */
        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: #f9f9f9;
            min-width: 160px;
            box-shadow: 0px 8px 16px 0px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            border-radius: 4px;
        }

        .dropdown-content a,
        .dropdown-content button {
            color: black;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            width: 100%;
            text-align: left;
            border: none;
            background: none;
            cursor: pointer;
        }

        .dropdown-content a:hover,
        .dropdown-content button:hover {
            background-color: #f1f1f1
        }

        .dropdown:hover .dropdown-content {
            display: block;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
            if (isCollapsed) {
                document.body.classList.add('sidebar-collapsed');
            }
        });

        function toggleSidebar() {
            document.body.classList.toggle('sidebar-collapsed');
            const isCollapsed = document.body.classList.contains('sidebar-collapsed');
            localStorage.setItem('sidebar-collapsed', isCollapsed);
        }
    </script>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <h2>TDD Admin</h2>
                <button onclick="toggleSidebar()" class="btn btn-sm btn-link"
                    style="color:white; padding:0 5px; background:none; border:none; cursor:pointer; margin-left: auto;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                        stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>
            </div>
            <nav class="main-menu" style="display: flex; flex-direction: column; height: calc(100% - 60px);">
                <div style="flex-grow: 1; overflow-y: auto;">
                    <?php
                    // Re-use logic from main layout if possible, or duplicate safely
                    $db = \Core\Database::getInstance();
                    // Ensure table exists or handle error gracefully - assuming consistency with layout.php
                    try {
                        $db->query("SELECT * FROM menu_items WHERE parent_id IS NULL ORDER BY sort_order ASC");
                        $parents = $db->resultSet();

                        if ($parents) {
                            foreach ($parents as $item):
                                if ($item['required_permission'] && !\Core\Auth::hasPermission($item['required_permission']))
                                    continue;
                                $isActive = (isset($_GET['module']) && $_GET['module'] == $item['module']) ? 'active' : '';
                                ?>
                                <div class="menu-item <?= $isActive ?>">
                                    <a href="?module=<?= $item['module'] ?>&action=<?= $item['action'] ?>">
                                        <i class="fas <?= $item['icon'] ?>"></i>
                                        <span><?= $item['title'] ?></span>
                                    </a>
                                    <?php
                                    $db->query("SELECT * FROM menu_items WHERE parent_id = :id ORDER BY sort_order ASC");
                                    $db->bind(':id', $item['id']);
                                    $children = $db->resultSet();
                                    if ($children):
                                        ?>
                                        <div class="submenu">
                                            <?php foreach ($children as $child):
                                                if ($child['required_permission'] && !\Core\Auth::hasPermission($child['required_permission']))
                                                    continue;
                                                ?>
                                                <a href="?module=<?= $child['module'] ?>&action=<?= $child['action'] ?>">
                                                    <i class="fas <?= $child['icon'] ?>"></i>
                                                    <?= $child['title'] ?>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach;
                        } else {
                            // Fallback if DB empty
                            echo '<div style="padding:10px;">Menu empty</div>';
                        }
                    } catch (\Exception $e) {
                        echo "<!-- Menu Error: " . $e->getMessage() . " -->";
                    }
                    ?>
                </div>

                <!-- Language Switcher in Sidebar -->
                <div class="language-switcher" style="padding: 10px 20px; border-top: 1px solid rgba(255,255,255,0.1);">
                    <div style="display:flex; gap:10px;">
                        <button onclick="i18n.setLanguage('da')" class="btn btn-sm"
                            style="padding:4px 8px; border:1px solid #ddd; background: #fff; color: #333;"
                            title="Dansk">
                            🇩🇰 DA
                        </button>
                        <button onclick="i18n.setLanguage('en')" class="btn btn-sm"
                            style="padding:4px 8px; border:1px solid #ddd; background: #fff; color: #333;"
                            title="English">
                            🇬🇧 EN
                        </button>
                    </div>
                </div>

                <div class="user-panel p-3 border-top border-secondary"
                    style="color:rgba(255,255,255,0.7); margin-top:10px;">
                    <small>Logged in as:
                        <?= $_SESSION['username'] ?? 'User' ?>
                    </small><br>
                    <a href="?module=Auth&action=logout" class="text-danger"><small>Logout</small></a>
                </div>
            </nav>
        </aside>

        <!-- Main Content Area -->
        <div class="main-content">
            <!-- We do NOT close main-content or app-container here, to allow custom Layout content -->