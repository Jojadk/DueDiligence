<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'DueDiligence v2.0' ?></title>
    <meta name="description" content="DueDiligence - Bygningsgennemgang og tilstandsrapporter">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/image-upload.css">
    <link rel="stylesheet" href="/assets/css/project-snapshot.css">
    <link rel="stylesheet" href="/assets/css/report-tree.css">
</head>
<body>
    <!-- Top Header -->
    <header class="app-header">
        <div class="header-left">
            <button type="button" class="btn-menu" id="toggleSidebar" aria-label="Toggle menu">
                <?= icon('menu', 24) ?>
            </button>
            <div class="app-logo">
                <?= icon('home', 28) ?>
                <span class="logo-text">DueDiligence</span>
                <span class="version-badge">v2.0</span>
            </div>
        </div>

        <div class="header-center">
            <div class="global-search">
                <?= icon('search', 20) ?>
                <input
                    type="text"
                    id="globalSearch"
                    placeholder="Søg i projekter, kunder, bygninger..."
                    class="search-input"
                    autocomplete="off"
                >
                <div id="searchResults" class="search-results"></div>
            </div>
        </div>

        <div class="header-right">
            <button type="button" class="btn-icon" id="notificationBtn" aria-label="Notifikationer">
                <?= icon('bell', 22) ?>
                <span class="notification-badge" id="notificationCount" style="display: none;">0</span>
            </button>

            <div class="user-menu">
                <button type="button" class="user-button" id="userMenuBtn">
                    <?= icon('user', 20) ?>
                    <span class="user-name"><?= esc_html($currentUser['name'] ?? 'Bruger') ?></span>
                    <?= icon('chevron-down', 16) ?>
                </button>
                <div class="user-dropdown" id="userDropdown">
                    <a href="#" onclick="navigate('settings'); return false;">
                        <?= icon('settings', 18) ?>
                        Indstillinger
                    </a>
                    <a href="#" onclick="navigate('profile'); return false;">
                        <?= icon('user', 18) ?>
                        Min Profil
                    </a>
                    <hr>
                    <a href="#" onclick="logout(); return false;">
                        <?= icon('log-out', 18) ?>
                        Log ud
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Sidebar Navigation -->
    <aside class="app-sidebar" id="appSidebar">
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Hovedmenu</div>
                <a href="#" onclick="navigate('dashboard'); return false;" class="nav-item" data-route="dashboard">
                    <?= icon('home', 20) ?>
                    <span>Dashboard</span>
                </a>
                <a href="#" onclick="navigate('customer'); return false;" class="nav-item" data-route="customer">
                    <?= icon('users', 20) ?>
                    <span>Kunder</span>
                </a>
                <a href="#" onclick="navigate('project'); return false;" class="nav-item" data-route="project">
                    <?= icon('folder', 20) ?>
                    <span>Projekter</span>
                </a>
                <a href="#" onclick="navigate('building'); return false;" class="nav-item" data-route="building">
                    <?= icon('building', 20) ?>
                    <span>Bygninger</span>
                </a>
                <a href="#" onclick="navigate('building_element'); return false;" class="nav-item" data-route="building_element">
                    <?= icon('list', 20) ?>
                    <span>Bygningsdele</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Data</div>
                <a href="#" onclick="navigate('price_catalog'); return false;" class="nav-item" data-route="price_catalog">
                    <?= icon('database', 20) ?>
                    <span>Priskatalog</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Rapporter</div>
                <a href="#" onclick="navigate('reports'); return false;" class="nav-item" data-route="reports">
                    <?= icon('file-text', 20) ?>
                    <span>Rapporter</span>
                </a>
                <a href="#" onclick="navigate('reports/export'); return false;" class="nav-item" data-route="reports/export">
                    <?= icon('download', 20) ?>
                    <span>Eksporter</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Administration</div>
                <a href="#" onclick="navigate('users'); return false;" class="nav-item" data-route="users">
                    <?= icon('users', 20) ?>
                    <span>Brugere</span>
                </a>
                <a href="#" onclick="navigate('settings'); return false;" class="nav-item" data-route="settings">
                    <?= icon('settings', 20) ?>
                    <span>Indstillinger</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="app-info">
                <small class="text-muted">
                    DueDiligence v2.0<br>
                    © <?= date('Y') ?>
                </small>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <main class="app-main" id="appMain">
        <div class="main-content" id="mainContent">
            <!-- Content loaded dynamically here -->
            <div class="loading-container">
                <div class="spinner"></div>
                <p>Indlæser...</p>
            </div>
        </div>
    </main>

    <!-- Global Modal Container -->
    <div id="globalModal" class="modal">
        <div class="modal-content" id="modalContent">
            <!-- Modal content loaded dynamically here -->
        </div>
    </div>

    <!-- Large Modal Container (for complex forms) -->
    <div id="largeModal" class="modal">
        <div class="modal-content modal-large" id="largeModalContent">
            <!-- Large modal content loaded dynamically here -->
        </div>
    </div>

    <!-- Small Modal Container (for confirmations) -->
    <div id="smallModal" class="modal">
        <div class="modal-content modal-small" id="smallModalContent">
            <!-- Small modal content loaded dynamically here -->
        </div>
    </div>

    <!-- Notification Toast Container -->
    <div id="toastContainer" class="toast-container">
        <!-- Toasts appear here -->
    </div>

    <!-- Notification Panel (slide-out) -->
    <div id="notificationPanel" class="notification-panel">
        <div class="notification-header">
            <h3>
                <?= icon('bell', 20) ?>
                Notifikationer
            </h3>
            <button type="button" class="btn-close" onclick="closeNotificationPanel()">
                <?= icon('x', 20) ?>
            </button>
        </div>
        <div class="notification-list" id="notificationList">
            <!-- Notifications loaded here -->
            <div class="empty-notifications">
                <?= icon('bell', 48) ?>
                <p>Ingen notifikationer</p>
            </div>
        </div>
    </div>

    <!-- Context Menu (right-click menu) -->
    <div id="contextMenu" class="context-menu" style="display: none;">
        <!-- Context menu items loaded dynamically -->
    </div>

    <!-- Backdrop for modals and panels -->
    <div id="backdrop" class="backdrop"></div>

    <!-- CSRF Token for AJAX requests -->
    <script>
        window.CSRF_TOKEN = '<?= csrf_token() ?>';
        window.CSRF_TOKEN_NAME = '<?= CSRF_TOKEN_NAME ?>';
        window.APP_CONFIG = {
            baseUrl: '<?= BASE_URL ?>',
            apiUrl: '<?= BASE_URL ?>/api.php',
            currentUser: <?= json_encode($currentUser ?? null) ?>,
            permissions: <?= json_encode($permissions ?? []) ?>
        };
    </script>

    <!-- Core JavaScript -->
    <script src="/assets/js/utils.js"></script>
    <script src="/assets/js/api.js"></script>
    <script src="/assets/js/router.js"></script>
    <script src="/assets/js/modal.js"></script>
    <script src="/assets/js/toast.js"></script>
    <script src="/assets/js/searchable-select.js"></script>
    <script src="/assets/js/drag-drop.js"></script>
    <script src="/assets/js/image-upload.js"></script>
    <script src="/assets/js/project-snapshot.js"></script>
    <script src="/assets/js/report-tree.js"></script>
    <script src="/assets/js/offline-sync.js"></script>
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/app.js"></script>

    <script>
        // Register Service Worker for offline support
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('[SW] Registered:', reg.scope))
                .catch(err => console.error('[SW] Registration failed:', err));
        }

        // Initialize app on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            App.init();
        });
    </script>
</body>
</html>
