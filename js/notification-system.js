/**
 * Advanced Notification System
 * - Timeout countdown bar
 * - Modal-aware positioning
 * - Notification center
 * - Mark as read functionality
 * - Always on top (z-index 10000)
 */

class NotificationSystem {
    constructor() {
        this.notifications = new Map();
        this.notificationId = 0;
        this.unreadCount = 0;
        this.centerOpen = false;

        this.init();
    }

    init() {
        this.createContainers();
        this.loadNotifications();
        this.updateBadge();
    }

    createContainers() {
        // Global notification container (top center, above everything)
        if (!document.getElementById('globalNotificationContainer')) {
            const container = document.createElement('div');
            container.id = 'globalNotificationContainer';
            container.className = 'notification-container';
            document.body.appendChild(container);
        }

        // Notification center
        if (!document.getElementById('notificationCenter')) {
            const center = document.createElement('div');
            center.id = 'notificationCenter';
            center.className = 'notification-center';
            center.innerHTML = `
                <div class="notification-center-header">
                    <h3>Notifikationer</h3>
                    <div class="notification-center-actions">
                        <button type="button" class="btn-mark-all-read" id="markAllRead">
                            Marker alle som læst
                        </button>
                        <button type="button" class="btn-close-center" id="closeNotificationCenter">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="notification-center-list" id="notificationCenterList">
                    <!-- Notifications loaded here -->
                </div>
            `;
            document.body.appendChild(center);

            // Event listeners
            document.getElementById('markAllRead').addEventListener('click', () => this.markAllAsRead());
            document.getElementById('closeNotificationCenter').addEventListener('click', () => this.closeCenter());
        }
    }

    show(message, options = {}) {
        if (!message) return null;

        const {
            type = 'info',
            duration = 30000,
            persistent = false,
            inModal = false,
            actions = []
        } = options;

        const notifId = ++this.notificationId;
        const container = this.getContainer(inModal);

        if (!container) {
            if (window.logError) {
                window.logError(new Error('Notification container not found'));
            }
            return null;
        }

        const notif = document.createElement('div');
        notif.className = `notification notification-${type}`;
        notif.dataset.notifId = notifId;

        const icons = {
            success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9 12l2 2 4-4"></path></svg>',
            error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>',
            warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
            info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
        };

        let actionsHtml = '';
        if (actions.length > 0) {
            actionsHtml = '<div class="notification-actions">';
            actions.forEach(action => {
                actionsHtml += `<button type="button" class="btn-notif-action" data-action="${action.id}">${escapeHtml(action.label)}</button>`;
            });
            actionsHtml += '</div>';
        }

        notif.innerHTML = `
            <div class="notification-icon">${icons[type] || icons.info}</div>
            <div class="notification-content">
                <div class="notification-message">${escapeHtml(message)}</div>
                ${actionsHtml}
            </div>
            <button type="button" class="notification-close">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            ${!persistent && duration > 0 ? '<div class="notification-progress"></div>' : ''}
        `;

        container.appendChild(notif);

        setTimeout(() => {
            if (notif) notif.classList.add('show');
        }, 10);

        // Close button
        const closeBtn = notif.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => this.dismiss(notifId));

        // Action buttons
        actions.forEach(action => {
            const btn = notif.querySelector(`[data-action="${action.id}"]`);
            if (btn) {
                btn.addEventListener('click', () => {
                    if (action.callback) action.callback();
                    this.dismiss(notifId);
                });
            }
        });

        // Progress bar animation
        let progressBar = null;
        let startTime = null;
        let animationFrame = null;

        if (!persistent && duration > 0) {
            progressBar = notif.querySelector('.notification-progress');
            startTime = Date.now();

            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = Math.min((elapsed / duration) * 100, 100);

                if (progressBar) {
                    progressBar.style.width = `${100 - progress}%`;
                }

                if (progress < 100) {
                    animationFrame = requestAnimationFrame(animate);
                } else {
                    this.dismiss(notifId);
                }
            };

            animationFrame = requestAnimationFrame(animate);
        }

        // Store notification
        this.notifications.set(notifId, {
            id: notifId,
            element: notif,
            message: message,
            type: type,
            timestamp: new Date(),
            read: false,
            persistent: persistent,
            animationFrame: animationFrame
        });

        this.unreadCount++;
        this.updateBadge();
        this.saveToCenter(notifId);

        if (!persistent && duration > 0) {
            setTimeout(() => this.dismiss(notifId), duration);
        }

        return notifId;
    }

    dismiss(notifId) {
        const notifData = this.notifications.get(notifId);
        if (!notifData) return;

        // Cancel animation
        if (notifData.animationFrame) {
            cancelAnimationFrame(notifData.animationFrame);
        }

        const notif = notifData.element;
        if (!notif) return;

        notif.classList.remove('show');

        setTimeout(() => {
            if (notif && notif.parentNode) {
                notif.parentNode.removeChild(notif);
            }
        }, 300);
    }

    getContainer(inModal) {
        if (inModal) {
            // Find active modal
            const activeModal = document.querySelector('.modal.active .modal-content');
            if (activeModal) {
                let container = activeModal.querySelector('.modal-notification-container');
                if (!container) {
                    container = document.createElement('div');
                    container.className = 'modal-notification-container';
                    activeModal.insertBefore(container, activeModal.firstChild);
                }
                return container;
            }
        }

        return document.getElementById('globalNotificationContainer');
    }

    saveToCenter(notifId) {
        const notifData = this.notifications.get(notifId);
        if (!notifData) return;

        const centerList = document.getElementById('notificationCenterList');
        if (!centerList) return;

        const item = document.createElement('div');
        item.className = `notification-center-item ${notifData.type} ${notifData.read ? 'read' : 'unread'}`;
        item.dataset.notifId = notifId;

        const time = this.formatTime(notifData.timestamp);

        item.innerHTML = `
            <div class="notif-center-icon">
                ${notifData.type === 'success' ? '✓' : notifData.type === 'error' ? '✕' : notifData.type === 'warning' ? '⚠' : 'ℹ'}
            </div>
            <div class="notif-center-content">
                <div class="notif-center-message">${escapeHtml(notifData.message)}</div>
                <div class="notif-center-time">${time}</div>
            </div>
            <button type="button" class="notif-center-mark-read" data-id="${notifId}">
                ${notifData.read ? 'Ulæst' : 'Marker som læst'}
            </button>
        `;

        centerList.insertBefore(item, centerList.firstChild);

        // Mark as read button
        const markBtn = item.querySelector('.notif-center-mark-read');
        markBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleRead(notifId);
        });

        // Save to localStorage
        this.saveNotifications();
    }

    toggleRead(notifId) {
        const notifData = this.notifications.get(notifId);
        if (!notifData) return;

        notifData.read = !notifData.read;

        const item = document.querySelector(`.notification-center-item[data-notif-id="${notifId}"]`);
        if (item) {
            item.classList.toggle('read', notifData.read);
            item.classList.toggle('unread', !notifData.read);

            const btn = item.querySelector('.notif-center-mark-read');
            if (btn) {
                btn.textContent = notifData.read ? 'Ulæst' : 'Marker som læst';
            }
        }

        if (notifData.read) {
            this.unreadCount = Math.max(0, this.unreadCount - 1);
        } else {
            this.unreadCount++;
        }

        this.updateBadge();
        this.saveNotifications();
    }

    markAllAsRead() {
        this.notifications.forEach((notifData, notifId) => {
            if (!notifData.read) {
                notifData.read = true;

                const item = document.querySelector(`.notification-center-item[data-notif-id="${notifId}"]`);
                if (item) {
                    item.classList.add('read');
                    item.classList.remove('unread');

                    const btn = item.querySelector('.notif-center-mark-read');
                    if (btn) {
                        btn.textContent = 'Ulæst';
                    }
                }
            }
        });

        this.unreadCount = 0;
        this.updateBadge();
        this.saveNotifications();
    }

    openCenter() {
        const center = document.getElementById('notificationCenter');
        if (center) {
            center.classList.add('open');
            this.centerOpen = true;
        }
    }

    closeCenter() {
        const center = document.getElementById('notificationCenter');
        if (center) {
            center.classList.remove('open');
            this.centerOpen = false;
        }
    }

    toggleCenter() {
        if (this.centerOpen) {
            this.closeCenter();
        } else {
            this.openCenter();
        }
    }

    updateBadge() {
        const badge = document.getElementById('notificationCount');
        if (badge) {
            if (this.unreadCount > 0) {
                badge.textContent = this.unreadCount > 99 ? '99+' : this.unreadCount;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
    }

    formatTime(date) {
        const now = new Date();
        const diff = now - date;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return 'Lige nu';
        if (minutes < 60) return `${minutes} min siden`;
        if (hours < 24) return `${hours} timer siden`;
        if (days < 7) return `${days} dage siden`;

        return date.toLocaleDateString('da-DK', { day: 'numeric', month: 'short' });
    }

    saveNotifications() {
        try {
            const data = Array.from(this.notifications.values()).map(n => ({
                id: n.id,
                message: n.message,
                type: n.type,
                timestamp: n.timestamp.toISOString(),
                read: n.read
            }));

            localStorage.setItem('notifications', JSON.stringify(data));
        } catch (error) {
            if (window.logError) {
                window.logError(error, { method: 'saveNotifications' });
            }
        }
    }

    loadNotifications() {
        try {
            const data = localStorage.getItem('notifications');
            if (!data) return;

            const notifications = JSON.parse(data);
            const centerList = document.getElementById('notificationCenterList');
            if (!centerList) return;

            notifications.forEach(n => {
                this.notifications.set(n.id, {
                    ...n,
                    timestamp: new Date(n.timestamp),
                    element: null,
                    animationFrame: null
                });

                if (!n.read) {
                    this.unreadCount++;
                }

                // Add to center
                const item = document.createElement('div');
                item.className = `notification-center-item ${n.type} ${n.read ? 'read' : 'unread'}`;
                item.dataset.notifId = n.id;

                const time = this.formatTime(new Date(n.timestamp));

                item.innerHTML = `
                    <div class="notif-center-icon">
                        ${n.type === 'success' ? '✓' : n.type === 'error' ? '✕' : n.type === 'warning' ? '⚠' : 'ℹ'}
                    </div>
                    <div class="notif-center-content">
                        <div class="notif-center-message">${escapeHtml(n.message)}</div>
                        <div class="notif-center-time">${time}</div>
                    </div>
                    <button type="button" class="notif-center-mark-read" data-id="${n.id}">
                        ${n.read ? 'Ulæst' : 'Marker som læst'}
                    </button>
                `;

                centerList.appendChild(item);

                const markBtn = item.querySelector('.notif-center-mark-read');
                markBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.toggleRead(n.id);
                });
            });

            if (this.notificationId < Math.max(...notifications.map(n => n.id))) {
                this.notificationId = Math.max(...notifications.map(n => n.id));
            }
        } catch (error) {
            if (window.logError) {
                window.logError(error, { method: 'loadNotifications' });
            }
        }
    }

    // Helper methods
    success(message, options = {}) {
        return this.show(message, { ...options, type: 'success' });
    }

    error(message, options = {}) {
        return this.show(message, { ...options, type: 'error' });
    }

    warning(message, options = {}) {
        return this.show(message, { ...options, type: 'warning' });
    }

    info(message, options = {}) {
        return this.show(message, { ...options, type: 'info' });
    }
}

// Global instance
window.NotificationSystem = new NotificationSystem();
window.notify = (message, options) => window.NotificationSystem.show(message, options);

// Backward compatibility alias for Toast
window.Toast = {
    show: (message, options) => window.NotificationSystem.show(message, options),
    success: (message, duration) => window.NotificationSystem.success(message, { duration }),
    error: (message, duration) => window.NotificationSystem.error(message, { duration }),
    warning: (message, duration) => window.NotificationSystem.warning(message, { duration }),
    info: (message, duration) => window.NotificationSystem.info(message, { duration }),
    dismiss: (notifId) => window.NotificationSystem.dismiss(notifId),
    clearAll: () => {
        window.NotificationSystem.notifications.forEach((_, id) => {
            window.NotificationSystem.dismiss(id);
        });
    }
};

// Wire up notification button
document.addEventListener('DOMContentLoaded', () => {
    const notifBtn = document.getElementById('notificationBtn');
    if (notifBtn) {
        notifBtn.addEventListener('click', () => {
            window.NotificationSystem.toggleCenter();
        });
    }
});
