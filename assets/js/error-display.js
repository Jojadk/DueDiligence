/**
 * Error Display System
 * Enhanced error handling and display
 *
 * @version 1.0.0
 */

const ErrorDisplay = {
    // Configuration
    config: {
        showDetails: false, // Show technical details (enable in dev mode)
        autoHide: true,
        autoHideDelay: 5000
    },

    /**
     * Initialize error display system
     */
    init() {
        console.log('ErrorDisplay: Initializing...');

        // Enable details in development mode
        if (window.APP_CONFIG && window.APP_CONFIG.debug) {
            this.config.showDetails = true;
        }

        // Global error handler
        window.addEventListener('error', (e) => {
            this.handleGlobalError(e);
        });

        // Unhandled promise rejection handler
        window.addEventListener('unhandledrejection', (e) => {
            this.handlePromiseRejection(e);
        });

        console.log('ErrorDisplay: Initialized');
    },

    /**
     * Handle global errors
     */
    handleGlobalError(event) {
        console.error('Global error:', event.error);

        this.showError({
            title: 'Der opstod en fejl',
            message: 'Noget gik galt. Prøv venligst igen.',
            details: event.error ? event.error.stack : null,
            type: 'error'
        });
    },

    /**
     * Handle unhandled promise rejections
     */
    handlePromiseRejection(event) {
        console.error('Unhandled promise rejection:', event.reason);

        this.showError({
            title: 'Asynkron fejl',
            message: 'En baggrundsfejl opstod. Prøv venligst igen.',
            details: event.reason,
            type: 'error'
        });
    },

    /**
     * Show error message
     *
     * @param {Object} options - Error options
     */
    showError(options) {
        const {
            title = 'Fejl',
            message = 'Der opstod en uventet fejl',
            details = null,
            type = 'error',
            actions = null,
            container = null
        } = options;

        // If container provided, show inline error
        if (container) {
            this.showInlineError(container, { title, message, details, type, actions });
            return;
        }

        // Show as notification
        if (typeof NotificationSystem !== 'undefined') {
            NotificationSystem.error(message, {
                title: title,
                duration: this.config.autoHideDelay
            });
        }

        // Log to error logger
        if (typeof ErrorLogger !== 'undefined') {
            ErrorLogger.log({
                type: type,
                message: message,
                details: details
            });
        }
    },

    /**
     * Show inline error in container
     */
    showInlineError(container, options) {
        const {
            title = 'Fejl',
            message = 'Der opstod en fejl',
            details = null,
            type = 'error',
            actions = null
        } = options;

        // Clear existing content
        container.innerHTML = '';

        // Create error element
        const errorEl = document.createElement('div');
        errorEl.className = `error-state error-${type}`;

        // Icon
        const iconEl = document.createElement('div');
        iconEl.className = 'error-state-icon';
        iconEl.innerHTML = this.getIcon(type);
        errorEl.appendChild(iconEl);

        // Title
        const titleEl = document.createElement('div');
        titleEl.className = 'error-state-title';
        titleEl.textContent = title;
        errorEl.appendChild(titleEl);

        // Message
        const messageEl = document.createElement('div');
        messageEl.className = 'error-state-message';
        messageEl.textContent = message;
        errorEl.appendChild(messageEl);

        // Details (if enabled and available)
        if (this.config.showDetails && details) {
            const detailsEl = document.createElement('div');
            detailsEl.className = 'error-state-details';
            detailsEl.textContent = typeof details === 'string' ? details : JSON.stringify(details, null, 2);
            errorEl.appendChild(detailsEl);
        }

        // Actions
        if (actions || type === 'error') {
            const actionsEl = document.createElement('div');
            actionsEl.className = 'error-state-actions';

            if (actions) {
                actions.forEach(action => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = `btn ${action.class || 'btn-primary'}`;
                    btn.textContent = action.label;
                    btn.onclick = action.callback;
                    actionsEl.appendChild(btn);
                });
            } else {
                // Default retry action
                const retryBtn = document.createElement('button');
                retryBtn.type = 'button';
                retryBtn.className = 'btn btn-primary';
                retryBtn.textContent = 'Prøv igen';
                retryBtn.onclick = () => window.location.reload();
                actionsEl.appendChild(retryBtn);
            }

            errorEl.appendChild(actionsEl);
        }

        container.appendChild(errorEl);
    },

    /**
     * Show loading state in container
     */
    showLoading(container, message = 'Indlæser...') {
        container.innerHTML = '';

        const loadingEl = document.createElement('div');
        loadingEl.className = 'loading-container';

        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        loadingEl.appendChild(spinner);

        if (message) {
            const messageEl = document.createElement('p');
            messageEl.textContent = message;
            loadingEl.appendChild(messageEl);
        }

        // Add screen reader text
        const srText = document.createElement('span');
        srText.className = 'sr-only-loading';
        srText.setAttribute('role', 'status');
        srText.setAttribute('aria-live', 'polite');
        srText.textContent = message;
        loadingEl.appendChild(srText);

        container.appendChild(loadingEl);
    },

    /**
     * Show empty state in container
     */
    showEmpty(container, options = {}) {
        const {
            title = 'Ingen data',
            message = 'Der er ingen data at vise',
            icon = '📭',
            action = null
        } = options;

        container.innerHTML = '';

        const emptyEl = document.createElement('div');
        emptyEl.className = 'empty-state';

        // Icon
        const iconEl = document.createElement('div');
        iconEl.className = 'empty-state-icon';
        iconEl.textContent = icon;
        emptyEl.appendChild(iconEl);

        // Title
        const titleEl = document.createElement('div');
        titleEl.className = 'empty-state-title';
        titleEl.textContent = title;
        emptyEl.appendChild(titleEl);

        // Message
        const messageEl = document.createElement('div');
        messageEl.className = 'empty-state-description';
        messageEl.textContent = message;
        emptyEl.appendChild(messageEl);

        // Action
        if (action) {
            const actionEl = document.createElement('div');
            actionEl.className = 'empty-state-action';

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `btn ${action.class || 'btn-primary'}`;
            btn.textContent = action.label;
            btn.onclick = action.callback;

            actionEl.appendChild(btn);
            emptyEl.appendChild(actionEl);
        }

        container.appendChild(emptyEl);
    },

    /**
     * Show skeleton loading
     */
    showSkeleton(container, type = 'list', count = 3) {
        container.innerHTML = '';

        for (let i = 0; i < count; i++) {
            let skeletonEl;

            switch (type) {
                case 'list':
                    skeletonEl = this.createListSkeleton();
                    break;
                case 'card':
                    skeletonEl = this.createCardSkeleton();
                    break;
                case 'table':
                    skeletonEl = this.createTableSkeleton();
                    break;
                case 'form':
                    skeletonEl = this.createFormSkeleton();
                    break;
                default:
                    skeletonEl = this.createListSkeleton();
            }

            container.appendChild(skeletonEl);
        }
    },

    /**
     * Create list item skeleton
     */
    createListSkeleton() {
        const item = document.createElement('div');
        item.className = 'skeleton-list-item';

        const avatar = document.createElement('div');
        avatar.className = 'skeleton skeleton-avatar';
        item.appendChild(avatar);

        const content = document.createElement('div');
        content.className = 'skeleton-content';

        const title = document.createElement('div');
        title.className = 'skeleton skeleton-text';
        title.style.width = '60%';
        content.appendChild(title);

        const text = document.createElement('div');
        text.className = 'skeleton skeleton-text';
        text.style.width = '40%';
        content.appendChild(text);

        item.appendChild(content);

        return item;
    },

    /**
     * Create card skeleton
     */
    createCardSkeleton() {
        const card = document.createElement('div');
        card.className = 'skeleton-card';

        const title = document.createElement('div');
        title.className = 'skeleton skeleton-title';
        card.appendChild(title);

        for (let i = 0; i < 3; i++) {
            const text = document.createElement('div');
            text.className = 'skeleton skeleton-text';
            card.appendChild(text);
        }

        return card;
    },

    /**
     * Create table row skeleton
     */
    createTableSkeleton() {
        const row = document.createElement('div');
        row.className = 'skeleton-table-row';

        for (let i = 0; i < 4; i++) {
            const cell = document.createElement('div');
            cell.className = 'skeleton skeleton-table-cell';
            row.appendChild(cell);
        }

        return row;
    },

    /**
     * Create form skeleton
     */
    createFormSkeleton() {
        const form = document.createElement('div');
        form.className = 'skeleton-form-group';

        const label = document.createElement('div');
        label.className = 'skeleton skeleton-label';
        form.appendChild(label);

        const input = document.createElement('div');
        input.className = 'skeleton skeleton-input';
        form.appendChild(input);

        return form;
    },

    /**
     * Show loading overlay
     */
    showOverlay(container, message = 'Indlæser...', fixed = false) {
        // Remove existing overlay
        this.hideOverlay(container);

        const overlay = document.createElement('div');
        overlay.className = `loading-overlay ${fixed ? 'fixed' : ''}`;
        overlay.id = 'loadingOverlay';

        const content = document.createElement('div');
        content.className = 'loading-overlay-content';

        const spinner = document.createElement('div');
        spinner.className = 'spinner';
        content.appendChild(spinner);

        if (message) {
            const text = document.createElement('div');
            text.className = 'loading-overlay-text';
            text.textContent = message;
            content.appendChild(text);
        }

        overlay.appendChild(content);

        if (container) {
            container.style.position = 'relative';
            container.appendChild(overlay);
        } else {
            document.body.appendChild(overlay);
        }

        return overlay;
    },

    /**
     * Hide loading overlay
     */
    hideOverlay(container) {
        const overlay = container
            ? container.querySelector('#loadingOverlay')
            : document.getElementById('loadingOverlay');

        if (overlay) {
            overlay.remove();
        }
    },

    /**
     * Get icon for error type
     */
    getIcon(type) {
        const icons = {
            'error': '⚠️',
            'warning': '⚡',
            'info': 'ℹ️',
            'success': '✅'
        };

        return icons[type] || icons.error;
    },

    /**
     * Show button loading state
     */
    setButtonLoading(button, loading = true) {
        if (loading) {
            button.classList.add('btn-loading');
            button.disabled = true;
            button.dataset.originalText = button.textContent;
        } else {
            button.classList.remove('btn-loading');
            button.disabled = false;
            if (button.dataset.originalText) {
                button.textContent = button.dataset.originalText;
            }
        }
    },

    /**
     * Wrap async function with loading state
     */
    async withLoading(asyncFn, button, errorHandler = null) {
        this.setButtonLoading(button, true);

        try {
            const result = await asyncFn();
            return result;
        } catch (error) {
            if (errorHandler) {
                errorHandler(error);
            } else {
                this.showError({
                    title: 'Fejl',
                    message: error.message || 'Der opstod en fejl',
                    details: error
                });
            }
            throw error;
        } finally {
            this.setButtonLoading(button, false);
        }
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => ErrorDisplay.init());
} else {
    ErrorDisplay.init();
}

// Expose globally
window.ErrorDisplay = ErrorDisplay;
