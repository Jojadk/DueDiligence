<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= \Core\Security::generateCSRFToken() ?>">
    <title>Construction Admin</title>
    <script>
        // Set DEV_MODE for JavaScript based on PHP config
        window.DEV_MODE = <?= defined('DEV_MODE') && DEV_MODE ? 'true' : 'false' ?>;
    </script>
    <link rel="stylesheet" href="<?= \Core\Asset::css('assets/css/style.css') ?>">
    <link rel="stylesheet" href="<?= \Core\Asset::css('assets/css/responsive.css') ?>">
    <script src="<?= \Core\Asset::js('assets/js/error_handler.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/i18n.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/modules/utils.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/core.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/canvas-engine.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/window_manager.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/app.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/api.js') ?>"></script>
    <script src="<?= \Core\Asset::js('assets/js/modules/autosave.js') ?>"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= \Core\Asset::css('assets/css/window.css') ?>">
    <style>
        /* Shared Styles for Tables and Forms */
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .card-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid var(--border-color);
            font-weight: 600;
        }

        .card-body {
            padding: 1.5rem;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .badge {
            background: var(--bg-color);
            padding: 0.25rem 0.5rem;
            border-radius: 99px;
            font-size: 0.85rem;
        }

        .d-flex {
            display: flex;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .align-items-center {
            align-items: center;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.85rem;
            background: var(--secondary-color);
            color: white;
            border-radius: 4px;
            text-decoration: none;
            margin-left: 0.5rem;
        }

        .max-w-lg {
            max-width: 600px;
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        /* Module specific */
        .module-window {
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
    </style>
</head>

<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <h2>TDD Admin</h2>
            </div>
            <nav class="main-menu" style="display: flex; flex-direction: column; height: calc(100% - 60px);">
                <div style="flex-grow: 1;">
                    <?php
                    $db = \Core\Database::getInstance();
                    $db->query("SELECT * FROM menu_items WHERE parent_id IS NULL ORDER BY sort_order ASC");
                    $parents = $db->resultSet();

                    foreach ($parents as $item):
                        if ($item['required_permission'] && !\Core\Auth::hasPermission($item['required_permission']))
                            continue;
                        $isActive = (isset($_GET['module']) && $_GET['module'] == $item['module']) ? 'active' : '';
                        ?>
                        <div class="menu-item <?= $isActive ?>">
                            <a href="?module=<?= $item['module'] ?>&action=<?= $item['action'] ?>">
                                <i class="fas <?= $item['icon'] ?>"></i>
                                <span>
                                    <?= $item['title'] ?>
                                </span>
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
                    <?php endforeach; ?>
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
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="topbar">
                <div style="display:flex; align-items:center; gap:20px; width:100%;">

                    <!-- User Info -->
                    <div class="user-info" style="position:relative;">
                        <div class="user-dropdown"
                            style="cursor:pointer; display:flex; align-items:center; gap:8px; padding:8px 12px; border-radius:6px; transition:background 0.2s;"
                            onmouseover="this.style.background='#f0f0f0'"
                            onmouseout="this.style.background='transparent'" onclick="toggleUserMenu()">
                            <i class="fas fa-user-circle" style="font-size:1.3em; color:#666;"></i>
                            <span>Velkommen, <strong><?= $_SESSION['username'] ?? 'User' ?></strong></span>
                            <i class="fas fa-chevron-down" style="font-size:0.8em; color:#999;"></i>
                        </div>
                        <div id="user-menu" class="dropdown-menu"
                            style="display:none; position:absolute; top:100%; right:0; background:white; border:1px solid #ddd; border-radius:6px; box-shadow:0 4px 8px rgba(0,0,0,0.15); min-width:200px; margin-top:8px; z-index:1000;">
                            <a href="?module=User&action=settings" class="dropdown-item"
                                style="display:flex; align-items:center; gap:10px; padding:12px 16px; text-decoration:none; color:#333; transition:background 0.2s;"
                                onmouseover="this.style.background='#f5f5f5'"
                                onmouseout="this.style.background='white'">
                                <i class="fas fa-cog"></i>
                                <span>Indstillinger</span>
                            </a>
                            <a href="?module=User&action=profile" class="dropdown-item"
                                style="display:flex; align-items:center; gap:10px; padding:12px 16px; text-decoration:none; color:#333; transition:background 0.2s;"
                                onmouseover="this.style.background='#f5f5f5'"
                                onmouseout="this.style.background='white'">
                                <i class="fas fa-user"></i>
                                <span>Min Profil</span>
                            </a>
                            <div style="border-top:1px solid #eee; margin:4px 0;"></div>
                            <a href="?module=Auth&action=logout" class="dropdown-item"
                                style="display:flex; align-items:center; gap:10px; padding:12px 16px; text-decoration:none; color:#dc3545; transition:background 0.2s;"
                                onmouseover="this.style.background='#fff5f5'"
                                onmouseout="this.style.background='white'">
                                <i class="fas fa-sign-out-alt"></i>
                                <span>Log ud</span>
                            </a>
                        </div>
                    </div>
            </header>
            <script>
                function toggleUserMenu() {
                    const menu = document.getElementById('user-menu');
                    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
                }
                // Close menu when clicking outside
                document.addEventListener('click', function (e) {
                    const userInfo = document.querySelector('.user-info');
                    const menu = document.getElementById('user-menu');
                    if (userInfo && !userInfo.contains(e.target)) {
                        menu.style.display = 'none';
                    }
                });
            </script>

            <div class="content-wrapper">
                <!-- CONTENT INJECTION START -->
                <?php include $viewFile; ?>
                <!-- CONTENT INJECTION END -->
            </div>
        </main>
    </div>
    <!-- Central Modal Template -->
    <template id="modal-template">
        <div id="dynamic-modal" class="modal-overlay"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center;">
            <div class="modal-window"
                style="background:white; border-radius:8px; min-width:400px; box-shadow:0 4px 6px rgba(0,0,0,0.1); overflow:hidden;">
                <!-- WM Header Style -->
                <div class="wm-header">
                    <div class="wm-controls">
                        <button class="wm-btn wm-close" onclick="App.closeModal()"></button>
                        <button class="wm-btn wm-min" style="opacity:0.3; cursor:default;"></button>
                        <button class="wm-btn wm-max" style="opacity:0.3; cursor:default;"></button>
                    </div>
                    <div class="wm-title modal-title">Title</div>
                </div>
                <div class="modal-body" style="padding:20px; overflow-y:auto; max-height:80vh;"></div>
                <div class="modal-footer" style="text-align:right; display:flex; justify-content:flex-end; gap:10px;">
                </div>
            </div>
        </div>
    </template>
    <div id="toast-container"
        style="position: fixed; bottom: 20px; right: 20px; z-index: 10000; display: flex; flex-direction: column; gap: 10px;">
    </div>
    <div id="wm-dock"></div>
</body>

</html>