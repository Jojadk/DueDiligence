/**
 * Collaboration System - Unified Multi-User Support
 *
 * Combines record locking, live updates, and modal management into a single
 * optimized system with unified API calls.
 *
 * Features:
 * - Record locking with auto-release (120 sec)
 * - Live updates via polling
 * - Adaptive intervals (window/modal aware)
 * - Unified sync API (single request for all data)
 * - Modal state management
 *
 * Usage:
 *   const collab = new CollaborationManager({
 *       recordType: 'budget_template_items',
 *       recordId: templateId,
 *       onUpdate: (data) => handleUpdates(data)
 *   });
 *   collab.start();
 */

// ============================================================================
// MODAL MANAGER
// ============================================================================

class ModalManager {
    static modalCount = 0;

    static open() {
        this.modalCount++;
        if (this.modalCount === 1) {
            document.dispatchEvent(new CustomEvent('modalOpen', {
                detail: { count: this.modalCount }
            }));
        }
    }

    static close() {
        this.modalCount = Math.max(0, this.modalCount - 1);
        if (this.modalCount === 0) {
            document.dispatchEvent(new CustomEvent('modalClose'));
        }
    }

    static isOpen() {
        return this.modalCount > 0;
    }

    static reset() {
        const wasOpen = this.modalCount > 0;
        this.modalCount = 0;
        if (wasOpen) {
            document.dispatchEvent(new CustomEvent('modalClose'));
        }
    }
}

// Auto-detect Bootstrap modals
if (typeof jQuery !== 'undefined' && typeof jQuery.fn.modal !== 'undefined') {
    jQuery(document).on('show.bs.modal', '.modal', () => ModalManager.open());
    jQuery(document).on('hide.bs.modal', '.modal', () => ModalManager.close());
}

// ============================================================================
// COLLABORATION MANAGER (Unified Lock + Live Updates)
// ============================================================================

class CollaborationManager {
    constructor(options = {}) {
        // Configuration
        this.clientId = this.generateClientId();
        this.recordType = options.recordType;
        this.recordId = options.recordId;
        this.apiBase = options.apiBase || '/api.php';
        this.csrfToken = this.getCSRFToken();

        // Intervals
        this.syncInterval = options.syncInterval || 3000; // 3 sec active
        this.syncIntervalInactive = options.syncIntervalInactive || 30000; // 30 sec inactive
        this.currentSyncInterval = this.syncInterval;
        this.heartbeatInterval = options.heartbeatInterval || 30000; // 30 sec
        this.heartbeatIntervalInactive = options.heartbeatIntervalInactive || 60000; // 60 sec
        this.currentHeartbeatInterval = this.heartbeatInterval;
        this.inactivityTimeout = options.inactivityTimeout || 120000; // 120 sec

        // State
        this.isActive = false;
        this.isWindowActive = true;
        this.isModalOpen = false;
        this.activeLocks = new Map();
        this.inactivityTimers = new Map();
        this.lastSyncTimestamp = null;

        // Timers
        this.syncTimer = null;
        this.heartbeatTimer = null;

        // Callbacks
        this.onUpdate = options.onUpdate || null;
        this.onLockAcquired = options.onLockAcquired || null;
        this.onLockReleased = options.onLockReleased || null;
        this.onLockDenied = options.onLockDenied || null;
        this.onNotification = options.onNotification || null;

        // Setup
        this.setupVisibilityListeners();
        this.setupBeforeUnload();
    }

    // ------------------------------------------------------------------------
    // INITIALIZATION
    // ------------------------------------------------------------------------

    generateClientId() {
        return 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) return meta.getAttribute('content');
        const match = document.cookie.match(/csrf_token=([^;]+)/);
        return match ? match[1] : '';
    }

    setupVisibilityListeners() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.onWindowInactive();
            } else {
                this.onWindowActive();
            }
        });

        window.addEventListener('focus', () => this.onWindowActive());
        window.addEventListener('blur', () => this.onWindowInactive());
        document.addEventListener('modalOpen', () => this.onModalOpen());
        document.addEventListener('modalClose', () => this.onModalClose());
    }

    setupBeforeUnload() {
        window.addEventListener('beforeunload', () => {
            this.destroy();
        });
    }

    // ------------------------------------------------------------------------
    // WINDOW/MODAL STATE MANAGEMENT
    // ------------------------------------------------------------------------

    onWindowInactive() {
        if (!this.isWindowActive) return;
        this.isWindowActive = false;
        console.log('[Collab] Window inactive - reducing frequency');
        this.adjustIntervals();
    }

    onWindowActive() {
        if (this.isWindowActive) return;
        this.isWindowActive = true;
        console.log('[Collab] Window active - restoring frequency');

        if (this.isActive) {
            this.syncNow(); // Immediate sync
        }

        this.adjustIntervals();
    }

    onModalOpen() {
        this.isModalOpen = true;
        console.log('[Collab] Modal opened - reducing frequency');
        this.adjustIntervals();
    }

    onModalClose() {
        this.isModalOpen = false;
        console.log('[Collab] Modal closed - restoring frequency');

        if (this.isActive) {
            this.syncNow(); // Immediate sync
        }

        this.adjustIntervals();
    }

    getActiveSyncInterval() {
        return (!this.isWindowActive || this.isModalOpen)
            ? this.syncIntervalInactive
            : this.syncInterval;
    }

    getActiveHeartbeatInterval() {
        return (!this.isWindowActive || this.isModalOpen)
            ? this.heartbeatIntervalInactive
            : this.heartbeatInterval;
    }

    adjustIntervals() {
        // Adjust sync interval
        const newSyncInterval = this.getActiveSyncInterval();
        if (newSyncInterval !== this.currentSyncInterval) {
            this.currentSyncInterval = newSyncInterval;
            if (this.syncTimer) {
                clearInterval(this.syncTimer);
                this.syncTimer = setInterval(() => this.sync(), this.currentSyncInterval);
            }
        }

        // Adjust heartbeat interval
        const newHeartbeatInterval = this.getActiveHeartbeatInterval();
        if (newHeartbeatInterval !== this.currentHeartbeatInterval) {
            this.currentHeartbeatInterval = newHeartbeatInterval;
            if (this.heartbeatTimer) {
                clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = setInterval(() => this.sendHeartbeat(), this.currentHeartbeatInterval);
            }
        }
    }

    // ------------------------------------------------------------------------
    // FIELD LOCKING
    // ------------------------------------------------------------------------

    enableLocking(fields, options = {}) {
        const elements = this.getElements(fields);
        if (!elements || elements.length === 0) {
            console.warn('[Collab] No elements found for locking');
            return;
        }

        elements.forEach(element => {
            if (!element || !element.dataset) {
                console.warn('[Collab] Invalid element skipped:', element);
                return;
            }

            const fieldName = options.fieldName || element.name || element.dataset.field;
            if (!fieldName) {
                console.warn('[Collab] Element missing field name:', element);
                return;
            }

            element.dataset.recordType = this.recordType;
            element.dataset.recordId = options.recordId || this.recordId || element.dataset.recordId;
            element.dataset.fieldName = fieldName;

            // Ensure unique element ID
            if (!element.id) {
                element.id = `field_${this.recordType}_${element.dataset.recordId}_${fieldName}_${Date.now()}`;
            }

            element.addEventListener('focus', (e) => this.handleFocus(e.target));
            element.addEventListener('blur', (e) => this.handleBlur(e.target));
            element.addEventListener('input', (e) => this.handleInput(e.target));

            // Check initial lock status
            this.checkLock(element);
        });
    }

    getElements(fields) {
        if (typeof fields === 'string') return document.querySelectorAll(fields);
        if (fields instanceof NodeList || Array.isArray(fields)) return fields;
        if (fields instanceof HTMLElement) return [fields];
        return [];
    }

    async handleFocus(element) {
        if (!element || !element.dataset) {
            console.warn('[Collab] Invalid element in handleFocus');
            return;
        }

        const lockKey = this.getLockKey(element);
        if (this.activeLocks.has(lockKey)) {
            this.resetInactivityTimer(lockKey);
            return;
        }

        const result = await this.acquireLock(element);
        if (result && result.success) {
            this.activeLocks.set(lockKey, {
                element,
                recordType: element.dataset.recordType,
                recordId: element.dataset.recordId,
                fieldName: element.dataset.fieldName,
                acquiredAt: Date.now()
            });

            this.markAsLocked(element, 'self');
            this.resetInactivityTimer(lockKey);

            if (this.onLockAcquired) {
                this.onLockAcquired(element, result);
            }
        } else {
            this.markAsLocked(element, 'other', result || {});
            if (element.blur) element.blur();
            element.disabled = true;

            if (this.onLockDenied) {
                this.onLockDenied(element, result);
            }

            const userName = (result && result.locked_by_user_name) || 'en anden bruger';
            this.showNotification('warning',
                `Feltet redigeres af ${userName}. Venter på at det bliver frigivet...`);
        }
    }

    async handleBlur(element) {
        const lockKey = this.getLockKey(element);

        if (this.inactivityTimers.has(lockKey)) {
            clearTimeout(this.inactivityTimers.get(lockKey));
            this.inactivityTimers.delete(lockKey);
        }

        setTimeout(async () => {
            if (document.activeElement !== element && this.activeLocks.has(lockKey)) {
                await this.releaseLock(element);
                this.activeLocks.delete(lockKey);
                this.markAsUnlocked(element);

                if (this.onLockReleased) {
                    this.onLockReleased(element);
                }
            }
        }, 500);
    }

    handleInput(element) {
        const lockKey = this.getLockKey(element);
        this.resetInactivityTimer(lockKey);
    }

    getLockKey(element) {
        if (!element || !element.dataset) {
            console.warn('[Collab] Invalid element for lock key');
            return null;
        }
        return `${element.dataset.recordType}:${element.dataset.recordId}:${element.dataset.fieldName}`;
    }

    resetInactivityTimer(lockKey) {
        if (this.inactivityTimers.has(lockKey)) {
            clearTimeout(this.inactivityTimers.get(lockKey));
        }

        const timerId = setTimeout(() => {
            const lock = this.activeLocks.get(lockKey);
            if (lock) {
                this.releaseLock(lock.element);
                this.activeLocks.delete(lockKey);
                this.markAsUnlocked(lock.element);

                if (this.onLockReleased) {
                    this.onLockReleased(lock.element);
                }
            }
        }, this.inactivityTimeout);

        this.inactivityTimers.set(lockKey, timerId);
    }

    markAsLocked(element, lockType, lockInfo = {}) {
        if (!element || !element.classList) {
            console.warn('[Collab] Invalid element in markAsLocked');
            return;
        }

        element.classList.add('is-locked', `locked-by-${lockType}`);

        if (!element.parentElement) {
            console.warn('[Collab] Element has no parent for lock indicator');
            return;
        }

        if (!element.parentElement.querySelector('.lock-indicator')) {
            const indicator = document.createElement('span');
            indicator.className = 'lock-indicator';
            indicator.innerHTML = lockType === 'self'
                ? '<i class="icon-lock"></i> Redigerer...'
                : `<i class="icon-lock"></i> ${lockInfo.locked_by_user_name || 'Anden bruger'} redigerer`;
            element.parentElement.appendChild(indicator);
        }
    }

    markAsUnlocked(element) {
        if (!element || !element.classList) {
            console.warn('[Collab] Invalid element in markAsUnlocked');
            return;
        }

        element.classList.remove('is-locked', 'locked-by-self', 'locked-by-other');
        element.disabled = false;

        if (!element.parentElement) return;

        const indicator = element.parentElement.querySelector('.lock-indicator');
        if (indicator) indicator.remove();
    }

    async checkLock(element) {
        try {
            const lockKey = this.getLockKey(element);

            const response = await fetch(this.apiBase, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    module: 'sync',
                    action: 'get_state',
                    client_id: this.clientId,
                    record_type: element.dataset.recordType,
                    record_id: element.dataset.recordId,
                    active_locks: [lockKey]
                })
            });

            const result = await response.json();

            if (result.success && result.locks && result.locks[lockKey]) {
                const lock = result.locks[lockKey];
                if (lock.locked && !lock.by_self) {
                    this.markAsLocked(element, 'other', { locked_by_user_name: lock.by_user });
                    element.disabled = true;
                }
            }
        } catch (error) {
            console.error('[Collab] Failed to check lock:', error);
        }
    }

    // ------------------------------------------------------------------------
    // API CALLS
    // ------------------------------------------------------------------------

    async acquireLock(element) {
        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    module: 'sync',
                    action: 'acquire',
                    record_type: element.dataset.recordType,
                    record_id: element.dataset.recordId,
                    field_name: element.dataset.fieldName || '',
                    client_id: this.clientId,
                    csrf_token: this.csrfToken
                })
            });
            return await response.json();
        } catch (error) {
            console.error('[Collab] Failed to acquire lock:', error);
            return { success: false, error: error.message };
        }
    }

    async releaseLock(element) {
        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    module: 'sync',
                    action: 'release',
                    record_type: element.dataset.recordType,
                    record_id: element.dataset.recordId,
                    field_name: element.dataset.fieldName || '',
                    client_id: this.clientId,
                    csrf_token: this.csrfToken
                })
            });
            return await response.json();
        } catch (error) {
            console.error('[Collab] Failed to release lock:', error);
            return { success: false };
        }
    }

    async sendHeartbeat() {
        if (this.activeLocks.size === 0) return;

        const locks = Array.from(this.activeLocks.values()).map(lock => ({
            record_type: lock.recordType,
            record_id: lock.recordId,
            field_name: lock.fieldName
        }));

        try {
            await fetch(this.apiBase, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    module: 'sync',
                    action: 'heartbeat',
                    client_id: this.clientId,
                    locks: locks,
                    csrf_token: this.csrfToken
                })
            });
        } catch (error) {
            console.error('[Collab] Heartbeat failed:', error);
        }
    }

    // ------------------------------------------------------------------------
    // UNIFIED SYNC (Lock Status + Changes + Notifications in ONE call)
    // ------------------------------------------------------------------------

    async sync() {
        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    module: 'sync',
                    action: 'get_state',
                    client_id: this.clientId,
                    record_type: this.recordType,
                    record_id: this.recordId,
                    since: this.lastSyncTimestamp,
                    active_locks: Array.from(this.activeLocks.keys())
                })
            });

            const result = await response.json();

            if (result.success) {
                this.handleSyncResponse(result);
                this.lastSyncTimestamp = result.timestamp;
            }
        } catch (error) {
            console.error('[Collab] Sync failed:', error);
        }
    }

    async syncNow() {
        await this.sync();
    }

    handleSyncResponse(data) {
        // Handle changes
        if (data.changes && data.changes.length > 0) {
            const safeChanges = data.changes.filter(change => {
                const lockKey = `${change.record_type}:${change.record_id}:${change.field_name || ''}`;
                return !this.activeLocks.has(lockKey);
            });

            if (safeChanges.length > 0 && this.onUpdate) {
                this.onUpdate({
                    changes: safeChanges,
                    locks: data.locks,
                    notifications: data.notifications
                });
            }
        }

        // Handle lock status changes
        if (data.locks) {
            this.handleLockStatusChanges(data.locks);
        }

        // Handle notifications
        if (data.notifications && data.notifications.length > 0) {
            data.notifications.forEach(notif => {
                this.showNotification(notif.type, notif.message);
            });
        }
    }

    handleLockStatusChanges(locks) {
        // Update UI based on lock status changes
        locks.forEach(lock => {
            const elements = document.querySelectorAll(
                `[data-record-type="${lock.record_type}"][data-record-id="${lock.record_id}"]`
            );

            elements.forEach(element => {
                if (lock.locked_by_self) {
                    // Still locked by us - do nothing
                } else if (lock.locked) {
                    // Locked by someone else
                    this.markAsLocked(element, 'other', lock);
                    element.disabled = true;
                } else {
                    // Unlocked
                    this.markAsUnlocked(element);
                }
            });
        });
    }

    // ------------------------------------------------------------------------
    // START/STOP
    // ------------------------------------------------------------------------

    start() {
        if (this.isActive) return;

        this.isActive = true;
        this.lastSyncTimestamp = new Date().toISOString();

        // Start sync timer
        this.currentSyncInterval = this.getActiveSyncInterval();
        this.syncTimer = setInterval(() => this.sync(), this.currentSyncInterval);
        this.sync(); // Initial sync

        // Start heartbeat timer
        this.currentHeartbeatInterval = this.getActiveHeartbeatInterval();
        this.heartbeatTimer = setInterval(() => this.sendHeartbeat(), this.currentHeartbeatInterval);

        console.log('[Collab] Started');
    }

    stop() {
        if (this.syncTimer) {
            clearInterval(this.syncTimer);
            this.syncTimer = null;
        }

        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }

        this.isActive = false;
        console.log('[Collab] Stopped');
    }

    async destroy() {
        // Release all locks
        const promises = [];
        for (const lock of this.activeLocks.values()) {
            promises.push(this.releaseLock(lock.element));
        }
        await Promise.all(promises);

        // Clear timers
        this.stop();

        for (const timerId of this.inactivityTimers.values()) {
            clearTimeout(timerId);
        }

        this.activeLocks.clear();
        this.inactivityTimers.clear();

        console.log('[Collab] Destroyed');
    }

    // ------------------------------------------------------------------------
    // HELPERS
    // ------------------------------------------------------------------------

    showNotification(type, message) {
        if (this.onNotification) {
            this.onNotification(type, message);
            return;
        }

        // Fallback notification
        console.log(`[Collab] ${type.toUpperCase()}: ${message}`);

        if (window.showNotification) {
            window.showNotification(type, message);
        }
    }
}

// ============================================================================
// BUDGET LIVE UPDATE (Specialized for budgets)
// ============================================================================

class BudgetCollaboration extends CollaborationManager {
    constructor(templateId, options = {}) {
        super({
            recordType: 'budget_template_items',
            recordId: templateId,
            ...options,
            onUpdate: (data) => this.handleBudgetUpdate(data)
        });

        this.templateId = templateId;
        this.totalUpdateCallbacks = [];
    }

    onTotalUpdate(callback) {
        this.totalUpdateCallbacks.push(callback);
    }

    async handleBudgetUpdate(data) {
        // Update totals
        await this.updateTotals();

        // Update changed items
        if (data.changes && data.changes.length > 0) {
            const itemIds = data.changes.map(c => c.record_id);
            await this.updateItems(itemIds);
            this.showChangeNotification(data.changes);
        }
    }

    async updateTotals() {
        try {
            const response = await fetch(
                `${this.apiBase}?module=template&action=get_hierarchy&template_id=${this.templateId}`
            );
            const result = await response.json();

            if (result.success && result.hierarchy) {
                this.updateTotalDisplay(result.hierarchy);
                this.totalUpdateCallbacks.forEach(cb => cb(result.hierarchy));
            }
        } catch (error) {
            console.error('[Budget] Failed to update totals:', error);
        }
    }

    async updateItems(itemIds) {
        for (const itemId of itemIds) {
            const row = document.querySelector(`tr[data-item-id="${itemId}"]`);
            if (!row) continue;

            try {
                const response = await fetch(
                    `${this.apiBase}?module=template&action=get_item&id=${itemId}`
                );
                const result = await response.json();

                if (result.success && result.item) {
                    this.updateItemRow(row, result.item);
                }
            } catch (error) {
                console.error('[Budget] Failed to update item:', error);
            }
        }
    }

    updateTotalDisplay(hierarchy) {
        const totalElement = document.getElementById('budget-total');
        if (!totalElement) {
            console.warn('[Budget] Total element not found');
            return;
        }

        if (!hierarchy || typeof hierarchy.total === 'undefined') {
            console.warn('[Budget] Invalid hierarchy data');
            return;
        }

        const total = hierarchy.total || 0;
        const formattedTotal = new Intl.NumberFormat('da-DK', {
            style: 'currency',
            currency: 'DKK',
            minimumFractionDigits: 0
        }).format(total);

        if (totalElement.classList) {
            totalElement.classList.add('updating');
        }

        setTimeout(() => {
            if (totalElement) {
                totalElement.textContent = formattedTotal;
                if (totalElement.classList) {
                    totalElement.classList.remove('updating');
                }
            }
        }, 200);
    }

    updateItemRow(row, itemData) {
        if (!row || !itemData) {
            console.warn('[Budget] Invalid row or item data');
            return;
        }

        const cells = row.querySelectorAll('td');
        if (!cells || cells.length === 0) {
            console.warn('[Budget] No cells found in row');
            return;
        }

        cells.forEach(cell => {
            if (!cell || !cell.dataset) return;

            const field = cell.dataset.field;
            if (!field) return;

            const input = cell.querySelector('input, textarea, select');
            if (input && document.activeElement === input) return;

            if (itemData[field] !== undefined) {
                if (input) {
                    input.value = itemData[field];
                } else {
                    cell.textContent = this.formatValue(field, itemData[field]);
                }

                if (cell.classList) {
                    cell.classList.add('live-updated');
                    setTimeout(() => {
                        if (cell && cell.classList) {
                            cell.classList.remove('live-updated');
                        }
                    }, 1000);
                }
            }
        });
    }

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

    showChangeNotification(changes) {
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

        setTimeout(() => badge.classList.remove('show'), 3000);
    }
}

// ============================================================================
// AUTO-INIT
// ============================================================================

document.addEventListener('DOMContentLoaded', () => {
    const budgetContainer = document.querySelector('[data-budget-template-id]');
    if (budgetContainer) {
        const templateId = budgetContainer.dataset.budgetTemplateId;
        window.budgetCollab = new BudgetCollaboration(templateId);
        window.budgetCollab.enableLocking('.editable');
        window.budgetCollab.start();
    }
});

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { CollaborationManager, BudgetCollaboration, ModalManager };
}
