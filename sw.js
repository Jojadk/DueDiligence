/**
 * Service Worker for Offline-First Architecture
 */

const CACHE_NAME = 'duediligence-v2.1';
const OFFLINE_URL = '/offline.html';

// Assets to cache
const CACHE_ASSETS = [
    '/',
    '/offline.html',
    '/assets/css/main.css',
    '/assets/js/utils.js',
    '/assets/js/api.js',
    '/assets/js/router.js',
    '/assets/js/modal.js',
    '/assets/js/modal-builder.js',
    '/assets/js/modal-helpers.js',
    '/assets/js/validation.js',
    '/assets/js/toast.js',
    '/assets/js/searchable-select.js',
    '/assets/js/main.js',
    '/assets/js/app.js',
    '/assets/js/offline-sync.js',
    '/js/base-module.js'
];

// Install event
self.addEventListener('install', (event) => {
    console.log('[SW] Installing...');

    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            console.log('[SW] Caching app shell');
            return cache.addAll(CACHE_ASSETS);
        })
    );

    self.skipWaiting();
});

// Activate event
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating...');

    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((cacheName) => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Removing old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );

    self.clients.claim();
});

// Fetch event - Network First, fallback to Cache
self.addEventListener('fetch', (event) => {
    const { request } = event;

    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }

    // API requests - Network First
    if (request.url.includes('/api.php') || request.url.includes('module=')) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Clone response and cache it
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                    return response;
                })
                .catch(() => {
                    // If network fails, try cache
                    return caches.match(request).then((cachedResponse) => {
                        return cachedResponse || caches.match(OFFLINE_URL);
                    });
                })
        );
        return;
    }

    // Static assets - Cache First
    event.respondWith(
        caches.match(request).then((cachedResponse) => {
            if (cachedResponse) {
                return cachedResponse;
            }

            return fetch(request).then((response) => {
                // Cache new static assets
                if (response.status === 200) {
                    const responseClone = response.clone();
                    caches.open(CACHE_NAME).then((cache) => {
                        cache.put(request, responseClone);
                    });
                }
                return response;
            });
        }).catch(() => {
            return caches.match(OFFLINE_URL);
        })
    );
});

// Background Sync
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync:', event.tag);

    if (event.tag === 'sync-data') {
        event.waitUntil(syncPendingData());
    }
});

// Sync pending data
async function syncPendingData() {
    try {
        const clients = await self.clients.matchAll();
        clients.forEach((client) => {
            client.postMessage({ type: 'SYNC_REQUESTED' });
        });
    } catch (error) {
        console.error('[SW] Sync error:', error);
    }
}

// Push notifications (future)
self.addEventListener('push', (event) => {
    const data = event.data ? event.data.json() : {};

    event.waitUntil(
        self.registration.showNotification(data.title || 'DueDiligence', {
            body: data.body || 'Du har en ny notifikation',
            icon: '/assets/images/icon-192.png',
            badge: '/assets/images/badge-72.png'
        })
    );
});
