/**
 * Main JavaScript File
 * General utilities and helpers
 */

// Logout function
async function logout() {
    const confirmed = await ModalBuilder.confirm(
        'logout-confirm',
        'Log ud',
        'Er du sikker på, at du vil logge ud?',
        {
            confirmText: 'Log ud',
            confirmClass: 'btn-primary',
            cancelText: 'Annuller'
        }
    );

    if (confirmed) {
        window.location.href = '/?module=auth&action=logout';
    }
}

// Close notification panel
function closeNotificationPanel() {
    const panel = document.getElementById('notificationPanel');
    const backdrop = document.getElementById('backdrop');

    if (panel) {
        panel.classList.remove('active');
    }
    if (backdrop) {
        backdrop.classList.remove('active');
    }
}

// Toggle sidebar
function toggleSidebar() {
    const sidebar = document.getElementById('appSidebar');
    if (sidebar) {
        sidebar.classList.toggle('collapsed');
    }
}

// Global search handler
const globalSearch = debounce(async function(query) {
    if (!query || query.length < 2) {
        hideSearchResults();
        return;
    }

    try {
        const response = await API.get('/api.php', {
            action: 'search',
            q: query
        });

        if (response.success && response.data) {
            displaySearchResults(response.data);
        }
    } catch (error) {
        console.error('Search error:', error);
    }
}, 300);

function displaySearchResults(results) {
    const resultsContainer = document.getElementById('searchResults');
    if (!resultsContainer) return;

    if (results.length === 0) {
        resultsContainer.innerHTML = '<div class="search-empty">Ingen resultater</div>';
        resultsContainer.classList.add('active');
        return;
    }

    resultsContainer.innerHTML = results.map(result => `
        <a href="#" onclick="navigate('${escapeJs(result.module)}', {id: ${result.id}}); hideSearchResults(); return false;" class="search-result">
            <div class="search-result-icon">
                ${result.icon || '📄'}
            </div>
            <div class="search-result-content">
                <div class="search-result-title">${escapeHtml(result.title)}</div>
                <div class="search-result-subtitle">${escapeHtml(result.subtitle || '')}</div>
            </div>
        </a>
    `).join('');

    resultsContainer.classList.add('active');
}

function hideSearchResults() {
    const resultsContainer = document.getElementById('searchResults');
    if (resultsContainer) {
        resultsContainer.classList.remove('active');
        resultsContainer.innerHTML = '';
    }
}

// Initialize global event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const toggleBtn = document.getElementById('toggleSidebar');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleSidebar);
    }

    // User menu
    const userMenuBtn = document.getElementById('userMenuBtn');
    const userDropdown = document.getElementById('userDropdown');
    if (userMenuBtn && userDropdown) {
        userMenuBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            userDropdown.classList.toggle('active');
        });

        // Close on outside click
        document.addEventListener('click', (e) => {
            if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                userDropdown.classList.remove('active');
            }
        });
    }

    // Notification button
    const notificationBtn = document.getElementById('notificationBtn');
    const notificationPanel = document.getElementById('notificationPanel');
    const backdrop = document.getElementById('backdrop');

    if (notificationBtn && notificationPanel) {
        notificationBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            notificationPanel.classList.toggle('active');
            backdrop.classList.toggle('active');
        });
    }

    // Global search
    const globalSearchInput = document.getElementById('globalSearch');
    if (globalSearchInput) {
        globalSearchInput.addEventListener('input', (e) => {
            globalSearch(e.target.value);
        });

        globalSearchInput.addEventListener('focus', (e) => {
            if (e.target.value.length >= 2) {
                globalSearch(e.target.value);
            }
        });

        // Close results on outside click
        document.addEventListener('click', (e) => {
            if (!globalSearchInput.contains(e.target)) {
                hideSearchResults();
            }
        });
    }

    // Handle escape key globally
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            hideSearchResults();
            closeNotificationPanel();

            const userDropdown = document.getElementById('userDropdown');
            if (userDropdown) {
                userDropdown.classList.remove('active');
            }
        }
    });
});
