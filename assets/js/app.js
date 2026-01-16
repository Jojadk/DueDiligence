/**
 * Application Controller
 * Main SPA initialization and coordination
 */

const App = {
    config: {},
    initialized: false,

    /**
     * Initialize application
     */
    init() {
        if (this.initialized) {
            return;
        }

        console.log('Initializing DueDiligence v2.0...');

        // Load config
        this.config = window.APP_CONFIG || {};

        // Initialize router
        Router.init();

        // Load notifications
        this.loadNotifications();

        // Setup periodic tasks
        this.setupPeriodicTasks();

        // Mark as initialized
        this.initialized = true;

        console.log('DueDiligence v2.0 initialized successfully');
    },

    /**
     * Load notifications
     */
    async loadNotifications() {
        try {
            const response = await API.get('/api.php', {
                action: 'get_notifications'
            });

            if (response.success && response.data) {
                this.renderNotifications(response.data);
                this.updateNotificationBadge(response.unread || 0);
            }
        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    },

    /**
     * Render notifications
     */
    renderNotifications(notifications) {
        const notificationList = document.getElementById('notificationList');
        if (!notificationList) return;

        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="empty-notifications">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <p>Ingen notifikationer</p>
                </div>
            `;
            return;
        }

        notificationList.innerHTML = notifications.map(notification => `
            <div class="notification-item ${notification.read ? '' : 'unread'}" data-id="${notification.id}">
                <div class="notification-icon notification-${notification.type}">
                    ${this.getNotificationIcon(notification.type)}
                </div>
                <div class="notification-content">
                    <div class="notification-title">${escapeHtml(notification.title)}</div>
                    <div class="notification-message">${escapeHtml(notification.message)}</div>
                    <div class="notification-time">${formatDateTime(notification.created_at)}</div>
                </div>
                ${!notification.read ? `
                    <button type="button" class="btn-mark-read" onclick="App.markNotificationRead(${notification.id})">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </button>
                ` : ''}
            </div>
        `).join('');
    },

    /**
     * Get notification icon
     */
    getNotificationIcon(type) {
        const icons = {
            success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>',
            error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
            warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path></svg>',
            info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
        };
        return icons[type] || icons.info;
    },

    /**
     * Update notification badge
     */
    updateNotificationBadge(count) {
        const badge = document.getElementById('notificationCount');
        if (!badge) return;

        if (count > 0) {
            badge.textContent = count > 99 ? '99+' : count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    },

    /**
     * Mark notification as read
     */
    async markNotificationRead(id) {
        try {
            const response = await API.post('/api.php', {
                action: 'mark_notification_read',
                id: id
            });

            if (response.success) {
                // Reload notifications
                this.loadNotifications();
            }
        } catch (error) {
            console.error('Failed to mark notification as read:', error);
        }
    },

    /**
     * Setup periodic tasks
     */
    setupPeriodicTasks() {
        // Reload notifications every 60 seconds
        setInterval(() => {
            this.loadNotifications();
        }, 60000);

        // Refresh CSRF token every 30 minutes
        setInterval(() => {
            this.refreshCsrfToken();
        }, 1800000);
    },

    /**
     * Refresh CSRF token
     */
    async refreshCsrfToken() {
        try {
            const response = await API.get('/api.php', {
                action: 'refresh_csrf_token'
            });

            if (response.success && response.token) {
                window.CSRF_TOKEN = response.token;
                console.log('CSRF token refreshed');
            }
        } catch (error) {
            console.error('Failed to refresh CSRF token:', error);
        }
    },

    /**
     * Show loading overlay
     */
    showLoading(message = 'Indlæser...') {
        const overlay = document.createElement('div');
        overlay.id = 'loadingOverlay';
        overlay.className = 'loading-overlay';
        overlay.innerHTML = `
            <div class="loading-content">
                <div class="spinner"></div>
                <p>${escapeHtml(message)}</p>
            </div>
        `;
        document.body.appendChild(overlay);
    },

    /**
     * Hide loading overlay
     */
    hideLoading() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.remove();
        }
    },

    /**
     * Handle errors globally
     */
    handleError(error, userMessage = 'Der opstod en fejl') {
        console.error('Application error:', error);
        Toast.error(userMessage);
    },

    /**
     * Confirm before leaving with unsaved changes
     */
    confirmLeave(message = 'Du har ikke-gemte ændringer. Er du sikker på, at du vil forlade siden?') {
        return window.confirm(message);
    }
};

// Export App globally
window.App = App;

// Handle global errors
window.addEventListener('error', (event) => {
    console.error('Global error:', event.error);
});

// Handle unhandled promise rejections
window.addEventListener('unhandledrejection', (event) => {
    console.error('Unhandled promise rejection:', event.reason);
});
