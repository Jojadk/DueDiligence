/**
 * Offline Sync Manager
 * Handles local storage and online/offline synchronization
 */

const OfflineSync = {
    dbName: 'DueDiligence',
    dbVersion: 1,
    db: null,
    isOnline: navigator.onLine,
    syncQueue: [],

    /**
     * Initialize offline sync
     */
    async init() {
        // Open IndexedDB
        await this.openDB();

        // Listen for online/offline events
        window.addEventListener('online', () => this.handleOnline());
        window.addEventListener('offline', () => this.handleOffline());

        // Listen for Service Worker messages
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.addEventListener('message', (event) => {
                if (event.data.type === 'SYNC_REQUESTED') {
                    this.syncAll();
                }
            });
        }

        // Initial sync if online
        if (this.isOnline) {
            await this.syncAll();
        }

        console.log('[OfflineSync] Initialized');
    },

    /**
     * Open IndexedDB
     */
    openDB() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(this.dbName, this.dbVersion);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve();
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Create object stores
                if (!db.objectStoreNames.contains('customers')) {
                    db.createObjectStore('customers', { keyPath: 'id', autoIncrement: true });
                }
                if (!db.objectStoreNames.contains('projects')) {
                    db.createObjectStore('projects', { keyPath: 'id', autoIncrement: true });
                }
                if (!db.objectStoreNames.contains('buildings')) {
                    db.createObjectStore('buildings', { keyPath: 'id', autoIncrement: true });
                }
                if (!db.objectStoreNames.contains('building_elements')) {
                    db.createObjectStore('building_elements', { keyPath: 'id', autoIncrement: true });
                }
                if (!db.objectStoreNames.contains('sync_queue')) {
                    db.createObjectStore('sync_queue', { keyPath: 'id', autoIncrement: true });
                }
            };
        });
    },

    /**
     * Handle online event
     */
    handleOnline() {
        console.log('[OfflineSync] Back online');
        this.isOnline = true;
        Toast.info('Forbindelse genoprettet - synkroniserer...');
        this.syncAll();
    },

    /**
     * Handle offline event
     */
    handleOffline() {
        console.log('[OfflineSync] Gone offline');
        this.isOnline = false;
        Toast.warning('Ingen forbindelse - arbejder offline');
    },

    /**
     * Save data locally
     */
    async save(storeName, data) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.put(data);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    /**
     * Get data locally
     */
    async get(storeName, id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.get(id);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    /**
     * Get all data from store
     */
    async getAll(storeName) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readonly');
            const store = transaction.objectStore(storeName);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    },

    /**
     * Delete data locally
     */
    async delete(storeName, id) {
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([storeName], 'readwrite');
            const store = transaction.objectStore(storeName);
            const request = store.delete(id);

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    },

    /**
     * Queue operation for sync
     */
    async queueOperation(operation) {
        const queueItem = {
            operation: operation.type, // 'create', 'update', 'delete'
            module: operation.module,
            data: operation.data,
            timestamp: Date.now(),
            synced: false
        };

        await this.save('sync_queue', queueItem);

        // Try to sync immediately if online
        if (this.isOnline) {
            await this.syncAll();
        }
    },

    /**
     * Sync all pending operations
     */
    async syncAll() {
        if (!this.isOnline) return;

        const queue = await this.getAll('sync_queue');
        const unsynced = queue.filter(item => !item.synced);

        console.log(`[OfflineSync] Syncing ${unsynced.length} operations`);

        for (const item of unsynced) {
            try {
                await this.syncOperation(item);
                item.synced = true;
                await this.save('sync_queue', item);
            } catch (error) {
                console.error('[OfflineSync] Sync error:', error);
            }
        }

        // Clean up old synced items (older than 7 days)
        const cutoff = Date.now() - (7 * 24 * 60 * 60 * 1000);
        const toDelete = queue.filter(item => item.synced && item.timestamp < cutoff);

        for (const item of toDelete) {
            await this.delete('sync_queue', item.id);
        }

        if (unsynced.length > 0) {
            Toast.success('Synkronisering fuldført');
        }
    },

    /**
     * Sync single operation
     */
    async syncOperation(item) {
        const formData = new FormData();
        formData.append('module', item.module);
        formData.append('action', item.operation);

        for (const [key, value] of Object.entries(item.data)) {
            formData.append(key, value);
        }

        const response = await API.post('/', formData, true);

        if (!response.success) {
            throw new Error(response.error || 'Sync failed');
        }

        return response;
    },

    /**
     * Get online/offline status
     */
    getStatus() {
        return {
            online: this.isOnline,
            pendingSync: this.syncQueue.length
        };
    }
};

// Export globally
window.OfflineSync = OfflineSync;

// Initialize on load
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => OfflineSync.init());
} else {
    OfflineSync.init();
}
