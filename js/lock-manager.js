/**
 * Lock Manager - Multi-user collaborative editing
 *
 * Features:
 * - Automatic lock acquisition on field focus
 * - Heartbeat every 30 seconds to prevent timeout
 * - Auto-release on blur or 120 seconds inactivity
 * - Visual feedback when field is locked by another user
 * - Live updates via polling (every 3 seconds)
 *
 * Usage:
 *   const lockManager = new LockManager();
 *   lockManager.enableLocking('.editable-field', 'building_elements', recordId);
 *   lockManager.startLiveUpdates('building_elements', recordId, (changes) => {
 *       // Handle changes
 *   });
 */

class LockManager {
    constructor(options = {}) {
        this.clientId = this.generateClientId();
        this.activeLocks = new Map(); // Map of field selector -> lock info
        this.heartbeatInterval = options.heartbeatInterval || 30000; // 30 seconds
        this.heartbeatTimer = null;
        this.pollInterval = options.pollInterval || 3000; // 3 seconds for live updates
        this.pollTimer = null;
        this.lastPollTimestamp = null;
        this.inactivityTimeout = options.inactivityTimeout || 120000; // 120 seconds
        this.inactivityTimers = new Map(); // Map of field -> timeout ID
        this.csrfToken = this.getCSRFToken();
        this.apiBase = options.apiBase || '/api.php';
        this.onLockAcquired = options.onLockAcquired || null;
        this.onLockReleased = options.onLockReleased || null;
        this.onLockDenied = options.onLockDenied || null;
        this.onChanges = options.onChanges || null;
    }

    /**
     * Generate unique client ID for this browser tab
     */
    generateClientId() {
        return 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Get CSRF token from meta tag or cookie
     */
    getCSRFToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        if (meta) {
            return meta.getAttribute('content');
        }
        // Fallback to cookie
        const match = document.cookie.match(/csrf_token=([^;]+)/);
        return match ? match[1] : '';
    }

    /**
     * Enable locking on fields
     * @param {string|NodeList|Array} fields - Selector or elements
     * @param {string} recordType - Record type (e.g., 'building_elements')
     * @param {number} recordId - Record ID
     * @param {object} options - Additional options
     */
    enableLocking(fields, recordType, recordId, options = {}) {
        const elements = this.getElements(fields);

        elements.forEach(element => {
            const fieldName = options.fieldName || element.name || element.dataset.field;

            if (!fieldName) {
                console.warn('Field has no name or data-field attribute', element);
                return;
            }

            // Store record info on element
            element.dataset.recordType = recordType;
            element.dataset.recordId = recordId;
            element.dataset.fieldName = fieldName;

            // Add event listeners
            element.addEventListener('focus', (e) => this.handleFocus(e.target));
            element.addEventListener('blur', (e) => this.handleBlur(e.target));
            element.addEventListener('input', (e) => this.handleInput(e.target));

            // Check initial lock status
            this.checkLock(recordType, recordId, fieldName, element);
        });

        // Start heartbeat if not running
        if (!this.heartbeatTimer) {
            this.startHeartbeat();
        }
    }

    /**
     * Get elements from selector or NodeList
     */
    getElements(fields) {
        if (typeof fields === 'string') {
            return document.querySelectorAll(fields);
        } else if (fields instanceof NodeList || Array.isArray(fields)) {
            return fields;
        } else if (fields instanceof HTMLElement) {
            return [fields];
        }
        return [];
    }

    /**
     * Handle field focus - attempt to acquire lock
     */
    async handleFocus(element) {
        const recordType = element.dataset.recordType;
        const recordId = element.dataset.recordId;
        const fieldName = element.dataset.fieldName;

        const lockKey = `${recordType}:${recordId}:${fieldName}`;

        // Check if already locked
        if (this.activeLocks.has(lockKey)) {
            this.resetInactivityTimer(lockKey);
            return;
        }

        // Attempt to acquire lock
        const result = await this.acquireLock(recordType, recordId, fieldName);

        if (result.success) {
            this.activeLocks.set(lockKey, {
                recordType,
                recordId,
                fieldName,
                element,
                acquiredAt: Date.now()
            });

            this.markAsLocked(element, 'self');
            this.resetInactivityTimer(lockKey);

            if (this.onLockAcquired) {
                this.onLockAcquired(element, result);
            }
        } else {
            // Lock denied - field is locked by another user
            this.markAsLocked(element, 'other', result);
            element.blur();
            element.disabled = true;

            if (this.onLockDenied) {
                this.onLockDenied(element, result);
            }

            // Show notification
            this.showLockNotification(element, result);
        }
    }

    /**
     * Handle field blur - release lock
     */
    async handleBlur(element) {
        const recordType = element.dataset.recordType;
        const recordId = element.dataset.recordId;
        const fieldName = element.dataset.fieldName;

        const lockKey = `${recordType}:${recordId}:${fieldName}`;

        // Clear inactivity timer
        if (this.inactivityTimers.has(lockKey)) {
            clearTimeout(this.inactivityTimers.get(lockKey));
            this.inactivityTimers.delete(lockKey);
        }

        // Release lock after short delay (500ms) to prevent flicker on quick blur/focus
        setTimeout(async () => {
            // Check if still blurred
            if (document.activeElement !== element && this.activeLocks.has(lockKey)) {
                await this.releaseLock(recordType, recordId, fieldName);
                this.activeLocks.delete(lockKey);
                this.markAsUnlocked(element);

                if (this.onLockReleased) {
                    this.onLockReleased(element);
                }
            }
        }, 500);
    }

    /**
     * Handle field input - reset inactivity timer
     */
    handleInput(element) {
        const recordType = element.dataset.recordType;
        const recordId = element.dataset.recordId;
        const fieldName = element.dataset.fieldName;

        const lockKey = `${recordType}:${recordId}:${fieldName}`;

        this.resetInactivityTimer(lockKey);
    }

    /**
     * Reset inactivity timer for a lock
     */
    resetInactivityTimer(lockKey) {
        // Clear existing timer
        if (this.inactivityTimers.has(lockKey)) {
            clearTimeout(this.inactivityTimers.get(lockKey));
        }

        // Set new timer
        const timerId = setTimeout(() => {
            const lock = this.activeLocks.get(lockKey);
            if (lock) {
                this.releaseLock(lock.recordType, lock.recordId, lock.fieldName);
                this.activeLocks.delete(lockKey);
                this.markAsUnlocked(lock.element);

                if (this.onLockReleased) {
                    this.onLockReleased(lock.element);
                }
            }
        }, this.inactivityTimeout);

        this.inactivityTimers.set(lockKey, timerId);
    }

    /**
     * Acquire lock via API
     */
    async acquireLock(recordType, recordId, fieldName) {
        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    module: 'lock',
                    action: 'acquire',
                    record_type: recordType,
                    record_id: recordId,
                    field_name: fieldName || '',
                    client_id: this.clientId,
                    csrf_token: this.csrfToken
                })
            });

            return await response.json();
        } catch (error) {
            console.error('Failed to acquire lock:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Release lock via API
     */
    async releaseLock(recordType, recordId, fieldName) {
        try {
            const response = await fetch(this.apiBase, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    module: 'lock',
                    action: 'release',
                    record_type: recordType,
                    record_id: recordId,
                    field_name: fieldName || '',
                    client_id: this.clientId,
                    csrf_token: this.csrfToken
                })
            });

            return await response.json();
        } catch (error) {
            console.error('Failed to release lock:', error);
            return { success: false, error: error.message };
        }
    }

    /**
     * Send heartbeat for all active locks
     */
    async sendHeartbeat() {
        const promises = [];

        for (const [lockKey, lock] of this.activeLocks.entries()) {
            promises.push(
                fetch(this.apiBase, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        module: 'lock',
                        action: 'heartbeat',
                        record_type: lock.recordType,
                        record_id: lock.recordId,
                        field_name: lock.fieldName || '',
                        client_id: this.clientId,
                        csrf_token: this.csrfToken
                    })
                })
            );
        }

        try {
            await Promise.all(promises);
        } catch (error) {
            console.error('Heartbeat failed:', error);
        }
    }

    /**
     * Start heartbeat timer
     */
    startHeartbeat() {
        if (this.heartbeatTimer) {
            return;
        }

        this.heartbeatTimer = setInterval(() => {
            if (this.activeLocks.size > 0) {
                this.sendHeartbeat();
            }
        }, this.heartbeatInterval);
    }

    /**
     * Stop heartbeat timer
     */
    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }

    /**
     * Check lock status for a field
     */
    async checkLock(recordType, recordId, fieldName, element) {
        try {
            const response = await fetch(
                `${this.apiBase}?module=lock&action=check&record_type=${recordType}&record_id=${recordId}&field_name=${fieldName || ''}`
            );

            const result = await response.json();

            if (result.success && result.locked) {
                if (!result.locked_by_self) {
                    this.markAsLocked(element, 'other', result);
                    element.disabled = true;
                }
            }
        } catch (error) {
            console.error('Failed to check lock:', error);
        }
    }

    /**
     * Mark element as locked
     */
    markAsLocked(element, lockType, lockInfo = {}) {
        element.classList.add('is-locked');
        element.classList.add(`locked-by-${lockType}`);

        // Add visual indicator
        if (!element.parentElement.querySelector('.lock-indicator')) {
            const indicator = document.createElement('span');
            indicator.className = 'lock-indicator';
            indicator.innerHTML = lockType === 'self'
                ? '<i class="icon-lock"></i> Redigerer...'
                : `<i class="icon-lock"></i> ${lockInfo.locked_by_user_name || 'Anden bruger'} redigerer`;

            element.parentElement.appendChild(indicator);
        }
    }

    /**
     * Mark element as unlocked
     */
    markAsUnlocked(element) {
        element.classList.remove('is-locked', 'locked-by-self', 'locked-by-other');
        element.disabled = false;

        // Remove visual indicator
        const indicator = element.parentElement.querySelector('.lock-indicator');
        if (indicator) {
            indicator.remove();
        }
    }

    /**
     * Show lock notification
     */
    showLockNotification(element, lockInfo) {
        const message = `Dette felt redigeres af ${lockInfo.locked_by_user_name}. Venter på at feltet bliver frigivet...`;

        // Check if notification library is available
        if (window.showNotification) {
            window.showNotification('warning', message);
        } else {
            alert(message);
        }
    }

    /**
     * Start live updates polling
     */
    startLiveUpdates(recordType, recordId, callback) {
        this.onChanges = callback;
        this.lastPollTimestamp = new Date().toISOString();

        if (this.pollTimer) {
            return;
        }

        this.pollTimer = setInterval(async () => {
            await this.pollChanges(recordType, recordId);
        }, this.pollInterval);

        // Do initial poll
        this.pollChanges(recordType, recordId);
    }

    /**
     * Stop live updates polling
     */
    stopLiveUpdates() {
        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }
    }

    /**
     * Poll for changes
     */
    async pollChanges(recordType, recordId) {
        try {
            const response = await fetch(
                `${this.apiBase}?module=lock&action=get_changes&since=${encodeURIComponent(this.lastPollTimestamp)}&record_type=${recordType}&record_id=${recordId || ''}`
            );

            const result = await response.json();

            if (result.success && result.changes && result.changes.length > 0) {
                this.lastPollTimestamp = result.timestamp;

                if (this.onChanges) {
                    this.onChanges(result.changes);
                }
            }

            if (result.timestamp) {
                this.lastPollTimestamp = result.timestamp;
            }
        } catch (error) {
            console.error('Failed to poll changes:', error);
        }
    }

    /**
     * Release all locks and cleanup
     */
    async destroy() {
        // Release all locks
        const promises = [];
        for (const [lockKey, lock] of this.activeLocks.entries()) {
            promises.push(this.releaseLock(lock.recordType, lock.recordId, lock.fieldName));
        }

        await Promise.all(promises);

        // Clear timers
        this.stopHeartbeat();
        this.stopLiveUpdates();

        // Clear inactivity timers
        for (const timerId of this.inactivityTimers.values()) {
            clearTimeout(timerId);
        }

        // Clear maps
        this.activeLocks.clear();
        this.inactivityTimers.clear();
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LockManager;
}
