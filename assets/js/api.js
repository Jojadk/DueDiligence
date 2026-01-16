/**
 * API Helper
 * Handles all AJAX/fetch requests with CSRF tokens
 */

const API = {
    /**
     * Make a GET request
     */
    async get(url, params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;

        try {
            const response = await fetch(fullUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json, text/html'
                },
                credentials: 'same-origin'
            });

            return await this._handleResponse(response);
        } catch (error) {
            console.error('API GET error:', error);
            throw error;
        }
    },

    /**
     * Make a POST request
     */
    async post(url, data = {}, isFormData = false) {
        try {
            let body;
            let headers = {
                'X-Requested-With': 'XMLHttpRequest'
            };

            if (isFormData) {
                body = data;
                // Add CSRF token to FormData if not already present
                if (!data.has(window.CSRF_TOKEN_NAME)) {
                    data.append(window.CSRF_TOKEN_NAME, window.CSRF_TOKEN);
                }
            } else {
                body = JSON.stringify(data);
                headers['Content-Type'] = 'application/json';
                // Add CSRF token to JSON data
                if (!data[window.CSRF_TOKEN_NAME]) {
                    data[window.CSRF_TOKEN_NAME] = window.CSRF_TOKEN;
                    body = JSON.stringify(data);
                }
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: headers,
                body: body,
                credentials: 'same-origin'
            });

            return await this._handleResponse(response);
        } catch (error) {
            console.error('API POST error:', error);
            throw error;
        }
    },

    /**
     * Make a PUT request
     */
    async put(url, data = {}) {
        try {
            const body = JSON.stringify({
                ...data,
                [window.CSRF_TOKEN_NAME]: window.CSRF_TOKEN
            });

            const response = await fetch(url, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body,
                credentials: 'same-origin'
            });

            return await this._handleResponse(response);
        } catch (error) {
            console.error('API PUT error:', error);
            throw error;
        }
    },

    /**
     * Make a DELETE request
     */
    async delete(url, data = {}) {
        try {
            const body = JSON.stringify({
                ...data,
                [window.CSRF_TOKEN_NAME]: window.CSRF_TOKEN
            });

            const response = await fetch(url, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body,
                credentials: 'same-origin'
            });

            return await this._handleResponse(response);
        } catch (error) {
            console.error('API DELETE error:', error);
            throw error;
        }
    },

    /**
     * Upload file(s)
     */
    async upload(url, files, additionalData = {}) {
        try {
            const formData = new FormData();

            // Add CSRF token
            formData.append(window.CSRF_TOKEN_NAME, window.CSRF_TOKEN);

            // Add files
            if (files instanceof FileList) {
                for (let file of files) {
                    formData.append('files[]', file);
                }
            } else if (files instanceof File) {
                formData.append('file', files);
            }

            // Add additional data
            for (let [key, value] of Object.entries(additionalData)) {
                formData.append(key, value);
            }

            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData,
                credentials: 'same-origin'
            });

            return await this._handleResponse(response);
        } catch (error) {
            console.error('API upload error:', error);
            throw error;
        }
    },

    /**
     * Load module content (HTML)
     */
    async loadModule(module, action = 'index', params = {}) {
        const url = '/';
        const queryParams = {
            module: module,
            action: action,
            ...params
        };

        try {
            const response = await fetch(url + '?' + new URLSearchParams(queryParams), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'text/html'
                },
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const html = await response.text();
            return { success: true, html: html };
        } catch (error) {
            console.error('Module load error:', error);
            throw error;
        }
    },

    /**
     * Handle response
     */
    async _handleResponse(response) {
        // Check if user is unauthorized
        if (response.status === 401) {
            window.location.href = '/?module=auth&action=login';
            throw new Error('Unauthorized');
        }

        // Get content type
        const contentType = response.headers.get('content-type');

        // Handle JSON response
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();

            // Check for redirect in JSON response
            if (data.redirect) {
                window.location.href = data.redirect;
                return data;
            }

            if (!response.ok) {
                throw new Error(data.error || `HTTP error! status: ${response.status}`);
            }

            return data;
        }

        // Handle HTML response
        if (contentType && contentType.includes('text/html')) {
            const html = await response.text();

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return { success: true, html: html };
        }

        // Handle other responses
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        return { success: true };
    }
};

// Export API globally
window.API = API;
