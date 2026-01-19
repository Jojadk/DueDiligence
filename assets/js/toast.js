/**
 * Toast Notification System
 */

const Toast = {
    /**
     * Show a toast notification
     */
    show(message, options = {}) {
        if (!message) return null;

        const {
            type = 'info',
            duration = 4000,
            position = 'top-right',
            icon = true,
            dismissible = true
        } = options;

        const container = document.getElementById('toastContainer');
        if (!container) {
            if (window.logError) {
                window.logError(new Error('Toast container not found'));
            }
            return null;
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.setAttribute('role', 'alert');

        // Add icon
        let iconHtml = '';
        if (icon) {
            const icons = {
                success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>',
                error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
                warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
            };
            iconHtml = `<span class="toast-icon">${icons[type] || icons.info}</span>`;
        }

        // Add dismiss button
        let dismissHtml = '';
        if (dismissible) {
            dismissHtml = `
                <button type="button" class="toast-close" aria-label="Luk">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            `;
        }

        toast.innerHTML = `
            ${iconHtml}
            <span class="toast-message">${escapeHtml(message)}</span>
            ${dismissHtml}
        `;

        // Add to container
        container.appendChild(toast);

        // Trigger animation
        setTimeout(() => toast.classList.add('show'), 10);

        // Auto dismiss
        let dismissTimeout;
        if (duration > 0) {
            dismissTimeout = setTimeout(() => this.dismiss(toast), duration);
        }

        // Dismiss button handler
        if (dismissible) {
            const dismissBtn = toast.querySelector('.toast-close');
            if (dismissBtn) {
                dismissBtn.onclick = () => {
                    if (dismissTimeout) clearTimeout(dismissTimeout);
                    this.dismiss(toast);
                };
            }
        }

        return toast;
    },

    /**
     * Dismiss a toast
     */
    dismiss(toast) {
        if (!toast || !toast.classList) return;

        toast.classList.remove('show');
        setTimeout(() => {
            if (toast && toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    },

    /**
     * Show success toast
     */
    success(message, duration = 4000) {
        return this.show(message, { type: 'success', duration });
    },

    /**
     * Show error toast
     */
    error(message, duration = 6000) {
        return this.show(message, { type: 'error', duration });
    },

    /**
     * Show warning toast
     */
    warning(message, duration = 5000) {
        return this.show(message, { type: 'warning', duration });
    },

    /**
     * Show info toast
     */
    info(message, duration = 4000) {
        return this.show(message, { type: 'info', duration });
    },

    /**
     * Clear all toasts
     */
    clearAll() {
        const container = document.getElementById('toastContainer');
        if (container) {
            container.innerHTML = '';
        }
    }
};

// Export Toast globally
window.Toast = Toast;
