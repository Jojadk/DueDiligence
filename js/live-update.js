/**
 * Live Update Manager - Real-time collaboration
 *
 * Handles live updates for budget calculations and other modules
 * Non-intrusive updates that don't interrupt user workflow
 *
 * Features:
 * - Budget total updates in real-time
 * - Element changes reflected live
 * - Smart merging of changes (only updates non-focused fields)
 * - Visual indicators for changes
 *
 * Usage:
 *   const liveUpdate = new LiveUpdateManager({
 *       recordType: 'budget_template_items',
 *       recordId: templateId,
 *       onUpdate: (changes) => { ... }
 *   });
 */

class LiveUpdateManager {
    constructor(options = {}) {
        this.recordType = options.recordType;
        this.recordId = options.recordId;
        this.pollInterval = options.pollInterval || 3000; // 3 seconds active
        this.pollIntervalInactive = options.pollIntervalInactive || 30000; // 30 seconds inactive
        this.currentPollInterval = this.pollInterval;
        this.pollTimer = null;
        this.lastPollTimestamp = null;
        this.onUpdate = options.onUpdate || null;
        this.apiBase = options.apiBase || '/api.php';
        this.isActive = false;
        this.isWindowActive = true;
        this.isModalOpen = false;
        this.changeQueue = [];

        // Setup visibility change listeners
        this.setupVisibilityListeners();
    }

    /**
     * Setup Page Visibility API listeners
     */
    setupVisibilityListeners() {
        // Page visibility change
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.onWindowInactive();
            } else {
                this.onWindowActive();
            }
        });

        // Window focus/blur (fallback for older browsers)
        window.addEventListener('focus', () => this.onWindowActive());
        window.addEventListener('blur', () => this.onWindowInactive());

        // Modal detection (listen for modal events)
        document.addEventListener('modalOpen', () => this.onModalOpen());
        document.addEventListener('modalClose', () => this.onModalClose());
    }

    /**
     * Handle window becoming inactive
     */
    onWindowInactive() {
        if (!this.isWindowActive) return;

        this.isWindowActive = false;
        console.log('Window inactive - reducing poll frequency');

        if (this.isActive) {
            this.adjustPollInterval();
        }
    }

    /**
     * Handle window becoming active
     */
    onWindowActive() {
        if (this.isWindowActive) return;

        this.isWindowActive = true;
        console.log('Window active - restoring poll frequency');

        if (this.isActive) {
            // Poll immediately on activation
            this.poll();
            // Adjust interval back to normal
            this.adjustPollInterval();
        }
    }

    /**
     * Handle modal opening
     */
    onModalOpen() {
        this.isModalOpen = true;
        console.log('Modal opened - reducing poll frequency');

        if (this.isActive) {
            this.adjustPollInterval();
        }
    }

    /**
     * Handle modal closing
     */
    onModalClose() {
        this.isModalOpen = false;
        console.log('Modal closed - restoring poll frequency');

        if (this.isActive) {
            // Poll immediately when modal closes
            this.poll();
            // Adjust interval back
            this.adjustPollInterval();
        }
    }

    /**
     * Adjust polling interval based on window/modal state
     */
    adjustPollInterval() {
        const newInterval = this.getActiveInterval();

        if (newInterval !== this.currentPollInterval) {
            this.currentPollInterval = newInterval;

            // Restart timer with new interval
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = setInterval(() => {
                    this.poll();
                }, this.currentPollInterval);
            }
        }
    }

    /**
     * Get appropriate interval based on current state
     */
    getActiveInterval() {
        // Use slow interval if window is inactive OR modal is open
        if (!this.isWindowActive || this.isModalOpen) {
            return this.pollIntervalInactive;
        }
        return this.pollInterval;
    }

    /**
     * Start polling for live updates
     */
    start() {
        if (this.isActive) {
            return;
        }

        this.isActive = true;
        this.lastPollTimestamp = new Date().toISOString();

        // Use current interval based on window state
        this.currentPollInterval = this.getActiveInterval();

        // Start polling
        this.pollTimer = setInterval(() => {
            this.poll();
        }, this.currentPollInterval);

        // Initial poll
        this.poll();
    }

    /**
     * Stop polling
     */
    stop() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
        this.isActive = false;
    }

    /**
     * Poll for changes
     */
    async poll() {
        try {
            const url = new URL(this.apiBase, window.location.origin);
            url.searchParams.set('module', 'lock');
            url.searchParams.set('action', 'get_changes');
            url.searchParams.set('since', this.lastPollTimestamp);
            url.searchParams.set('record_type', this.recordType);

            if (this.recordId) {
                url.searchParams.set('record_id', this.recordId);
            }

            const response = await fetch(url);
            const result = await response.json();

            if (result.success && result.changes && result.changes.length > 0) {
                this.handleChanges(result.changes);
            }

            if (result.timestamp) {
                this.lastPollTimestamp = result.timestamp;
            }
        } catch (error) {
            console.error('Failed to poll for changes:', error);
        }
    }

    /**
     * Handle incoming changes
     */
    handleChanges(changes) {
        // Filter out changes to focused elements (don't interrupt user)
        const safeChanges = changes.filter(change => {
            const element = document.querySelector(
                `[data-record-type="${change.record_type}"][data-record-id="${change.record_id}"]`
            );

            // If element is focused, skip this change
            if (element && document.activeElement === element) {
                // Queue for later
                this.changeQueue.push(change);
                return false;
            }

            return true;
        });

        if (safeChanges.length > 0 && this.onUpdate) {
            this.onUpdate(safeChanges);
        }
    }

    /**
     * Process queued changes (call this on blur events)
     */
    processQueue() {
        if (this.changeQueue.length > 0 && this.onUpdate) {
            this.onUpdate(this.changeQueue);
            this.changeQueue = [];
        }
    }
}

/**
 * Budget Live Update Handler
 * Specifically designed for budget templates
 */
class BudgetLiveUpdate {
    constructor(templateId, options = {}) {
        this.templateId = templateId;
        this.liveUpdate = new LiveUpdateManager({
            recordType: 'budget_template_items',
            recordId: templateId,
            onUpdate: (changes) => this.handleBudgetChanges(changes),
            ...options
        });
        this.totalUpdateCallbacks = [];
    }

    /**
     * Start live updates
     */
    start() {
        this.liveUpdate.start();
    }

    /**
     * Stop live updates
     */
    stop() {
        this.liveUpdate.stop();
    }

    /**
     * Register callback for total updates
     */
    onTotalUpdate(callback) {
        this.totalUpdateCallbacks.push(callback);
    }

    /**
     * Handle budget item changes
     */
    async handleBudgetChanges(changes) {
        // Fetch updated totals
        await this.updateTotals();

        // Fetch updated items if needed
        const itemChanges = changes.filter(c => c.change_type !== 'delete');

        if (itemChanges.length > 0) {
            await this.updateItems(itemChanges.map(c => c.record_id));
        }

        // Show subtle notification
        this.showChangeNotification(changes);
    }

    /**
     * Update budget totals
     */
    async updateTotals() {
        try {
            const response = await fetch(
                `${this.liveUpdate.apiBase}?module=template&action=get_hierarchy&template_id=${this.templateId}`
            );

            const result = await response.json();

            if (result.success && result.hierarchy) {
                // Update totals in UI
                this.updateTotalDisplay(result.hierarchy);

                // Notify callbacks
                this.totalUpdateCallbacks.forEach(cb => cb(result.hierarchy));
            }
        } catch (error) {
            console.error('Failed to update totals:', error);
        }
    }

    /**
     * Update specific budget items
     */
    async updateItems(itemIds) {
        // Fetch updated item data and update UI
        for (const itemId of itemIds) {
            try {
                const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
                if (!row) continue;

                // Fetch updated data
                const response = await fetch(
                    `${this.liveUpdate.apiBase}?module=template&action=get_item&id=${itemId}`
                );

                const result = await response.json();

                if (result.success && result.item) {
                    this.updateItemRow(row, result.item);
                }
            } catch (error) {
                console.error('Failed to update item:', error);
            }
        }
    }

    /**
     * Update total display in UI
     */
    updateTotalDisplay(hierarchy) {
        const totalElement = document.getElementById('budget-total');
        if (!totalElement) return;

        const total = hierarchy.total || 0;
        const formattedTotal = new Intl.NumberFormat('da-DK', {
            style: 'currency',
            currency: 'DKK',
            minimumFractionDigits: 0
        }).format(total);

        // Animate change
        totalElement.classList.add('updating');
        setTimeout(() => {
            totalElement.textContent = formattedTotal;
            totalElement.classList.remove('updating');
        }, 200);
    }

    /**
     * Update item row in table
     */
    updateItemRow(row, itemData) {
        // Only update cells that are not being edited
        const cells = row.querySelectorAll('td');

        cells.forEach(cell => {
            const field = cell.dataset.field;
            if (!field) return;

            // Skip if cell contains focused input
            const input = cell.querySelector('input, textarea, select');
            if (input && document.activeElement === input) return;

            // Update cell content
            if (itemData[field] !== undefined) {
                if (input) {
                    input.value = itemData[field];
                } else {
                    cell.textContent = this.formatValue(field, itemData[field]);
                }

                // Flash animation
                cell.classList.add('live-updated');
                setTimeout(() => cell.classList.remove('live-updated'), 1000);
            }
        });
    }

    /**
     * Format value for display
     */
    formatValue(field, value) {
        if (['quantity', 'price_per_unit', 'multiplier'].includes(field)) {
            return new Intl.NumberFormat('da-DK', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(value);
        }

        if (field === 'total') {
            return new Intl.NumberFormat('da-DK', {
                style: 'currency',
                currency: 'DKK',
                minimumFractionDigits: 0
            }).format(value);
        }

        return value;
    }

    /**
     * Show subtle change notification
     */
    showChangeNotification(changes) {
        // Create or update notification badge
        let badge = document.getElementById('live-update-badge');

        if (!badge) {
            badge = document.createElement('div');
            badge.id = 'live-update-badge';
            badge.className = 'live-update-badge';
            document.body.appendChild(badge);
        }

        const count = changes.length;
        badge.textContent = `${count} opdatering${count > 1 ? 'er' : ''}`;
        badge.classList.add('show');

        // Auto-hide after 3 seconds
        setTimeout(() => {
            badge.classList.remove('show');
        }, 3000);
    }
}

/**
 * Initialize budget page with live updates and locking
 */
function initBudgetPage(templateId) {
    // Initialize lock manager
    const lockManager = new LockManager();

    // Enable locking on all input fields
    lockManager.enableLocking(
        'input.editable, textarea.editable, select.editable',
        'budget_template_items',
        null, // Will use data-record-id from each element
        {
            fieldName: null // Will use element.name
        }
    );

    // Initialize live updates
    const budgetLiveUpdate = new BudgetLiveUpdate(templateId);
    budgetLiveUpdate.start();

    // Update totals when data changes
    budgetLiveUpdate.onTotalUpdate((hierarchy) => {
        console.log('Budget totals updated:', hierarchy.total);
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', () => {
        lockManager.destroy();
        budgetLiveUpdate.stop();
    });

    return {
        lockManager,
        budgetLiveUpdate
    };
}

// Auto-initialize if budget template ID is found
document.addEventListener('DOMContentLoaded', () => {
    const budgetContainer = document.querySelector('[data-budget-template-id]');
    if (budgetContainer) {
        const templateId = budgetContainer.dataset.budgetTemplateId;
        window.budgetPageManager = initBudgetPage(templateId);
    }
});
