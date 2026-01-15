/**
 * Centralized API Layer
 * Provides standardized CRUD operations for all entities
 * @version 2.0
 */

const API = {
    /**
     * Base request handler with built-in CSRF, error handling, and retry logic
     * @param {string} endpoint - API endpoint (relative)
     * @param {string} method - HTTP method
     * @param {*} data - Request payload (FormData, Object, or null)
     * @param {object} options - Additional options
     * @returns {Promise} Response data
     */
    async request(endpoint, method = 'GET', data = null, options = {}) {
        const defaultOptions = {
            retries: 1,
            timeout: 30000,
            showToast: true,
            ...options
        };

        // Build full URL - strip leading ? from endpoint to prevent /?? issues
        const baseUrl = window.location.origin;
        const endpointClean = endpoint.startsWith('?') ? endpoint.substring(1) : endpoint;
        const url = endpointClean.startsWith('http') ? endpointClean : `${baseUrl}/?${endpointClean}`;

        // Prepare request options
        const fetchOptions = {
            method: method,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        };

        // Add CSRF token for state-changing requests
        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method.toUpperCase())) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ||
                document.querySelector('input[name="_csrf"]')?.value;
            if (csrfToken) {
                fetchOptions.headers['X-CSRF-Token'] = csrfToken;
            }
        }

        // Handle request body
        if (data) {
            if (data instanceof FormData) {
                // Let browser set Content-Type with boundary
                fetchOptions.body = data;
            } else if (method.toUpperCase() === 'GET') {
                // Append to URL as query params
                const params = new URLSearchParams(data);
                const separator = url.includes('?') ? '&' : '?';
                endpoint = url + separator + params.toString();
            } else {
                fetchOptions.headers['Content-Type'] = 'application/json';
                fetchOptions.body = JSON.stringify(data);
            }
        }

        // Execute request with timeout
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), defaultOptions.timeout);

            fetchOptions.signal = controller.signal;

            const response = await fetch(url, fetchOptions);
            clearTimeout(timeoutId);

            // Handle non-2xx responses
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            // Parse response
            const contentType = response.headers.get('content-type');
            let result;

            if (contentType?.includes('application/json')) {
                result = await response.json();
            } else {
                result = await response.text();
            }

            // Show success toast if configured
            if (defaultOptions.showToast && result?.status === 'success' && result?.message) {
                App.toast(result.message, 'success');
            }

            return result;

        } catch (error) {
            // Handle timeout
            if (error.name === 'AbortError') {
                if (defaultOptions.showToast) {
                    App.toast('Request timeout - prøv igen', 'error');
                }
                throw new Error('Request timeout');
            }

            // Retry logic
            if (defaultOptions.retries > 0) {
                console.warn(`Request failed, retrying... (${defaultOptions.retries} attempts left)`);
                await new Promise(r => setTimeout(r, 1000)); // Wait 1s before retry
                return this.request(endpoint, method, data, { ...defaultOptions, retries: defaultOptions.retries - 1 });
            }

            // Show error toast
            if (defaultOptions.showToast) {
                const errorMsg = error.message || 'En fejl opstod';
                App.toast(errorMsg, 'error');
            }

            console.error('API Request Error:', error);
            throw error;
        }
    },

    // ========================================
    // PROJECTS
    // ========================================
    projects: {
        getAll: (params = {}) => API.request('module=Project&action=index', 'GET', params),
        get: (id) => API.request(`module=Project&action=get&id=${id}`, 'GET'),
        create: (data) => API.request('module=Project&action=store', 'POST', data),
        update: (id, data) => API.request(`module=Project&action=update&id=${id}`, 'POST', data),
        delete: (id) => API.request(`module=Project&action=delete&id=${id}`, 'POST'),

        // Specialized methods
        listSnapshots: (projectId) => API.request(`module=Project&action=listSnapshots&project_id=${projectId}`, 'GET'),
        createSnapshot: (projectId, title) => {
            const fd = new FormData();
            fd.append('project_id', projectId);
            fd.append('title', title);
            return API.request('module=Project&action=createSnapshot', 'POST', fd);
        },
        restoreSnapshot: (snapshotId) => {
            const fd = new FormData();
            fd.append('snapshot_id', snapshotId);
            return API.request('module=Project&action=restoreSnapshot', 'POST', fd);
        },
        saveCoverImage: (projectId, blob) => {
            const fd = new FormData();
            fd.append('project_id', projectId);
            fd.append('cover_image', blob, 'cover_annotated.jpg');
            return API.request('module=Project&action=saveCoverImage', 'POST', fd);
        }
    },

    // ========================================
    // BUILDING ELEMENTS
    // ========================================
    buildingElements: {
        getAll: (projectId) => API.request(`module=BuildingElement&action=getAll&project_id=${projectId}`, 'GET'),
        get: (id) => API.request(`module=BuildingElement&action=get&id=${id}`, 'GET'),
        create: (data) => API.request('module=BuildingElement&action=store', 'POST', data),
        update: (id, data) => API.request(`module=BuildingElement&action=update&id=${id}`, 'POST', data),
        delete: (id) => API.request(`module=BuildingElement&action=delete&id=${id}`, 'POST'),

        // Specialized methods
        getForm: (id = null, projectId = null) => {
            const params = id ? `id=${id}` : `project_id=${projectId}`;
            return API.request(`module=BuildingElement&action=getForm&${params}`, 'GET');
        },
        updateField: (id, field, value) => {
            return API.request('module=BuildingElement&action=updatefield', 'POST', { id, field, value });
        },
        updateSort: (items) => {
            return API.request('module=BuildingElement&action=updatesort', 'POST', { items });
        },
        lockCheck: (id, clientId) => {
            return API.request(`module=BuildingElement&action=lockcheck&id=${id}&client_id=${clientId}`, 'POST');
        },
        lockRelease: (id, clientId) => {
            return API.request(`module=BuildingElement&action=lockrelease&id=${id}&client_id=${clientId}`, 'POST');
        },
        poll: (id, clientId) => {
            return API.request(`module=BuildingElement&action=poll&id=${id}&client_id=${clientId}`, 'GET');
        }
    },

    // ========================================
    // CUSTOMERS
    // ========================================
    customers: {
        getAll: () => API.request('module=Customer&action=index', 'GET'),
        get: (id) => API.request(`module=Customer&action=edit&ajax=1&id=${id}`, 'GET'),
        create: (data) => API.request('module=Customer&action=store', 'POST', data),
        update: (id, data) => API.request(`module=Customer&action=update&id=${id}`, 'POST', data),
        delete: (id) => API.request(`module=Customer&action=delete&id=${id}`, 'POST'),
        search: (query) => API.request(`module=Admin&action=searchClients&q=${encodeURIComponent(query)}`, 'GET')
    },

    // ========================================
    // BUDGET ITEMS
    // ========================================
    budgetItems: {
        getAll: (elementId) => API.request(`module=Budget&action=getAll&element_id=${elementId}`, 'GET'),
        get: (id) => API.request(`module=Budget&action=get&id=${id}`, 'GET'),
        create: (data) => API.request('module=Budget&action=store', 'POST', data),
        update: (id, data) => API.request(`module=Budget&action=update&id=${id}`, 'POST', data),
        delete: (id) => API.request(`module=Budget&action=delete&id=${id}`, 'POST')
    },

    // ========================================
    // MEDIA / FILES
    // ========================================
    media: {
        upload: (elementId, files) => {
            const fd = new FormData();
            fd.append('element_id', elementId);
            for (let i = 0; i < files.length; i++) {
                fd.append('files[]', files[i]);
            }
            return API.request('module=Media&action=upload', 'POST', fd);
        },
        delete: (id) => API.request(`module=Media&action=delete&id=${id}`, 'POST'),
        updateSort: (items) => API.request('module=Media&action=updateSort', 'POST', { items }),
        updateCaption: (id, caption) => API.request(`module=Media&action=updateCaption&id=${id}`, 'POST', { caption })
    },

    // ========================================
    // NOTIFICATIONS
    // ========================================
    notifications: {
        getAll: () => API.request('module=Notification&action=index', 'GET'),
        getUnread: () => API.request('module=Notification&action=unread', 'GET'),
        get: (id) => API.request(`module=Notification&action=get&id=${id}`, 'GET'),
        markRead: (id) => API.request(`module=Notification&action=markRead&id=${id}`, 'POST'),
        markAllRead: () => API.request('module=Notification&action=markAllRead', 'POST'),
        delete: (id) => API.request(`module=Notification&action=delete&id=${id}`, 'POST')
    },

    // ========================================
    // REPORTS
    // ========================================
    reports: {
        generate: (projectId, templateId = null) => {
            const params = templateId
                ? `module=Report&action=renderFromTemplate&project_id=${projectId}&template_id=${templateId}`
                : `module=Report&action=index&project_id=${projectId}`;
            return API.request(params, 'GET');
        },
        excel: (projectId) => API.request(`module=Report&action=excel&project_id=${projectId}`, 'GET'),

        templates: {
            getAll: () => API.request('module=Report&action=editor', 'GET'),
            get: (id) => API.request(`module=Report&action=editTemplate&id=${id}`, 'GET'),
            save: (data) => API.request('module=Report&action=saveTemplate', 'POST', data)
        }
    },

    // ========================================
    // USERS / AUTHENTICATION
    // ========================================
    auth: {
        login: (username, password) => API.request('module=Auth&action=login', 'POST', { username, password }),
        logout: () => API.request('module=Auth&action=logout', 'POST'),
        forgotPassword: (email) => API.request('module=Auth&action=forgotPassword', 'POST', { email }),
        resetPassword: (token, password) => API.request('module=Auth&action=resetPassword', 'POST', { token, password })
    },

    users: {
        getAll: () => API.request('module=User&action=index', 'GET'),
        get: (id) => API.request(`module=User&action=get&id=${id}`, 'GET'),
        create: (data) => API.request('module=User&action=store', 'POST', data),
        update: (id, data) => API.request(`module=User&action=update&id=${id}`, 'POST', data),
        delete: (id) => API.request(`module=User&action=delete&id=${id}`, 'POST'),
        search: (query) => API.request(`module=Admin&action=search&q=${encodeURIComponent(query)}`, 'GET')
    },

    // ========================================
    // CUSTOM FIELDS
    // ========================================
    customFields: {
        getDefinitions: (entityType, projectId = null) => {
            const params = projectId ? `&project_id=${projectId}` : '';
            return API.request(`module=CustomField&action=getDefinitions&entity_type=${entityType}${params}`, 'GET');
        },
        saveValue: (entityId, definitionId, value) => {
            return API.request('module=CustomField&action=saveValue', 'POST', {
                entity_id: entityId,
                definition_id: definitionId,
                value: value
            });
        }
    },

    // ========================================
    // PRICE CATALOG
    // ========================================
    priceCatalog: {
        search: (query) => API.request(`module=PriceCatalog&action=search&q=${encodeURIComponent(query)}`, 'GET'),
        get: (id) => API.request(`module=PriceCatalog&action=get&id=${id}`, 'GET'),
        import: (file) => {
            const fd = new FormData();
            fd.append('file', file);
            return API.request('module=PriceCatalog&action=import', 'POST', fd);
        }
    }
};

// Expose globally for legacy code
window.API = API;

// Backwards compatibility aliases
if (typeof App !== 'undefined') {
    App.API = API;
    // Keep legacy App.api method working
    if (!App.api.migrated) {
        App.legacyApi = App.api;
        App.api = API.request.bind(API);
        App.api.migrated = true;
    }
}

// Only log in development mode
if (typeof window.DEV_MODE !== 'undefined' && window.DEV_MODE) {
    console.log('✅ Centralized API v2.0 loaded');
}
