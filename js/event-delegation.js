/**
 * Event Delegation System
 * - Optimized event handling for dynamic content
 * - Reduced memory footprint
 * - Automatic cleanup tracking
 */

class EventDelegation {
    constructor() {
        this.delegatedHandlers = new Map();
        this.handlerId = 0;
        this.init();
    }

    init() {
        this.setupGlobalDelegations();
    }

    /**
     * Setup global event delegations for common patterns
     */
    setupGlobalDelegations() {
        // Delegate all data-action clicks
        this.delegate(document.body, 'click', '[data-action]', (e, target) => {
            const action = target.dataset.action;
            const handler = window[action];

            if (typeof handler === 'function') {
                e.preventDefault();
                handler(target);
            }
        });

        // Delegate all data-toggle clicks
        this.delegate(document.body, 'click', '[data-toggle]', (e, target) => {
            e.preventDefault();
            const toggleTarget = target.dataset.toggle;
            const element = document.getElementById(toggleTarget);

            if (element) {
                element.classList.toggle('active');
            }
        });

        // Delegate all data-dismiss clicks
        this.delegate(document.body, 'click', '[data-dismiss]', (e, target) => {
            e.preventDefault();
            const dismissTarget = target.dataset.dismiss;

            if (dismissTarget === 'modal' && typeof Modal !== 'undefined') {
                Modal.close();
            } else if (dismissTarget === 'notification') {
                const notification = target.closest('.notification');
                if (notification && notification.parentNode) {
                    notification.remove();
                }
            }
        });

        // Delegate table row clicks with data-row-click
        this.delegate(document.body, 'click', 'tr[data-row-click]', (e, target) => {
            // Don't trigger if clicking on buttons or inputs
            if (e.target.closest('button, a, input, select, textarea')) {
                return;
            }

            const action = target.dataset.rowClick;
            const id = target.dataset.rowId;

            if (action && id) {
                const handler = window[action];
                if (typeof handler === 'function') {
                    handler(id, target);
                }
            }
        });

        // Delegate form submissions with data-ajax-form
        this.delegate(document.body, 'submit', 'form[data-ajax-form]', (e, target) => {
            e.preventDefault();
            this.handleAjaxForm(target);
        });

        // Delegate sortable list interactions
        this.setupSortableDelegate();

        // Delegate file input changes with data-file-upload
        this.delegate(document.body, 'change', 'input[type="file"][data-file-upload]', (e, target) => {
            const uploadTarget = target.dataset.fileUpload;
            if (uploadTarget && window.UploadSystem) {
                const files = Array.from(target.files);
                const container = document.getElementById(uploadTarget);

                if (container && files.length > 0) {
                    // Trigger upload system
                    const event = new CustomEvent('filesSelected', {
                        detail: { files, target: uploadTarget }
                    });
                    container.dispatchEvent(event);
                }
            }
        });
    }

    /**
     * Delegate event to parent element
     */
    delegate(parent, eventType, selector, handler) {
        if (!parent) {
            if (window.logError) {
                window.logError(new Error('Parent element not provided for delegation'));
            }
            return null;
        }

        const handlerId = ++this.handlerId;

        const delegatedHandler = (e) => {
            const target = e.target.closest(selector);
            if (target && parent.contains(target)) {
                handler(e, target);
            }
        };

        parent.addEventListener(eventType, delegatedHandler);

        this.delegatedHandlers.set(handlerId, {
            parent,
            eventType,
            handler: delegatedHandler
        });

        return handlerId;
    }

    /**
     * Remove delegated event
     */
    undelegate(handlerId) {
        const delegated = this.delegatedHandlers.get(handlerId);
        if (delegated) {
            delegated.parent.removeEventListener(delegated.eventType, delegated.handler);
            this.delegatedHandlers.delete(handlerId);
        }
    }

    /**
     * Handle AJAX form submission
     */
    async handleAjaxForm(form) {
        if (!form) return;

        const formData = new FormData(form);
        const url = form.action || window.APP_CONFIG.apiUrl;
        const method = form.method || 'POST';

        // Add CSRF token if not present
        if (!formData.has(window.CSRF_TOKEN_NAME)) {
            formData.append(window.CSRF_TOKEN_NAME, window.CSRF_TOKEN);
        }

        // Show loading state
        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sender...';
        }

        try {
            const response = await fetch(url, {
                method: method,
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Trigger success event
                const event = new CustomEvent('ajaxFormSuccess', {
                    detail: { result, form }
                });
                form.dispatchEvent(event);

                if (window.NotificationSystem) {
                    window.NotificationSystem.success(result.message || 'Gemt');
                }

                // Reset form if specified
                if (form.dataset.resetOnSuccess !== 'false') {
                    form.reset();
                }

                // Close modal if in modal
                if (form.closest('.modal') && typeof Modal !== 'undefined') {
                    setTimeout(() => Modal.close(), 500);
                }
            } else {
                // Trigger error event
                const event = new CustomEvent('ajaxFormError', {
                    detail: { result, form }
                });
                form.dispatchEvent(event);

                if (window.NotificationSystem) {
                    window.NotificationSystem.error(result.error || 'Der opstod en fejl');
                }
            }
        } catch (error) {
            if (window.logError) {
                window.logError(error, { method: 'handleAjaxForm', formAction: url });
            }

            if (window.NotificationSystem) {
                window.NotificationSystem.error('Netværksfejl');
            }
        } finally {
            // Restore button state
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }

    /**
     * Setup sortable list delegation
     */
    setupSortableDelegate() {
        let draggedElement = null;

        this.delegate(document.body, 'dragstart', '[draggable="true"]', (e, target) => {
            draggedElement = target;
            target.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', target.innerHTML);
        });

        this.delegate(document.body, 'dragend', '[draggable="true"]', (e, target) => {
            target.classList.remove('dragging');
            draggedElement = null;
        });

        this.delegate(document.body, 'dragover', '.sortable-list', (e, target) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';

            const afterElement = this.getDragAfterElement(target, e.clientY);
            if (draggedElement && afterElement === null) {
                target.appendChild(draggedElement);
            } else if (draggedElement && afterElement) {
                target.insertBefore(draggedElement, afterElement);
            }
        });

        this.delegate(document.body, 'drop', '.sortable-list', (e, target) => {
            e.preventDefault();

            // Trigger reorder event
            const items = Array.from(target.children);
            const order = items.map((item, index) => ({
                id: item.dataset.id || item.dataset.fieldId,
                order: index
            }));

            const event = new CustomEvent('listReordered', {
                detail: { order, list: target }
            });
            target.dispatchEvent(event);
        });
    }

    /**
     * Get element after drag position
     */
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('[draggable="true"]:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    /**
     * Debounce function for input events
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Throttle function for scroll/resize events
     */
    throttle(func, limit = 100) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    }

    /**
     * Setup optimized search input delegation
     */
    setupSearchDelegation(searchInputSelector = '[data-search]', resultContainerId = null) {
        const debouncedSearch = this.debounce(async (input) => {
            const query = input.value.trim();
            const searchTarget = input.dataset.search;
            const minLength = parseInt(input.dataset.searchMinLength) || 2;

            if (query.length < minLength) {
                return;
            }

            const containerId = resultContainerId || input.dataset.searchResults;
            const container = document.getElementById(containerId);

            if (!container) return;

            // Show loading
            container.innerHTML = '<div class="search-loading">Søger...</div>';

            try {
                const response = await fetch(window.APP_CONFIG.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        module: 'search',
                        action: 'query',
                        target: searchTarget,
                        query: query,
                        [window.CSRF_TOKEN_NAME]: window.CSRF_TOKEN
                    })
                });

                const result = await response.json();

                if (result.success && result.data) {
                    // Trigger search results event
                    const event = new CustomEvent('searchResults', {
                        detail: { results: result.data, query, target: searchTarget }
                    });
                    container.dispatchEvent(event);
                }
            } catch (error) {
                if (window.logError) {
                    window.logError(error, { method: 'search', query });
                }
            }
        }, 300);

        this.delegate(document.body, 'input', searchInputSelector, (e, target) => {
            debouncedSearch(target);
        });
    }

    /**
     * Cleanup all delegated events
     */
    cleanup() {
        this.delegatedHandlers.forEach((delegated, handlerId) => {
            this.undelegate(handlerId);
        });
    }
}

// Initialize global event delegation
window.EventDelegation = new EventDelegation();

// Cleanup on page unload
window.addEventListener('beforeunload', () => {
    if (window.EventDelegation) {
        window.EventDelegation.cleanup();
    }
});
