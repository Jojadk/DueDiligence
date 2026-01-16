/**
 * Client-Side Router
 * Handles navigation and content loading
 */

const Router = {
    currentRoute: null,
    currentModule: null,
    currentAction: null,
    history: [],

    /**
     * Initialize router
     */
    init() {
        // Handle browser back/forward
        window.addEventListener('popstate', (event) => {
            if (event.state && event.state.module) {
                this.loadRoute(event.state.module, event.state.action, event.state.params, false);
            }
        });

        // Load initial route from URL
        const params = getQueryParams();
        const module = params.module || 'dashboard';
        const action = params.action || 'index';
        delete params.module;
        delete params.action;

        this.loadRoute(module, action, params, false);
    },

    /**
     * Navigate to a route
     */
    navigate(route, params = {}, pushState = true) {
        // Parse route (e.g., "customer", "project/edit", etc.)
        const parts = route.split('/');
        const module = parts[0];
        const action = parts[1] || 'index';

        this.loadRoute(module, action, params, pushState);
    },

    /**
     * Load a route
     */
    async loadRoute(module, action = 'index', params = {}, pushState = true) {
        // Show loading state
        this.showLoading();

        // Update URL if needed
        if (pushState) {
            const url = this._buildUrl(module, action, params);
            window.history.pushState(
                { module, action, params },
                '',
                url
            );
        }

        // Update active navigation
        this.updateActiveNav(module);

        // Store current route
        this.currentRoute = { module, action, params };
        this.currentModule = module;
        this.currentAction = action;

        // Add to history
        this.history.push({ module, action, params, timestamp: Date.now() });

        try {
            // Load module content
            const result = await API.loadModule(module, action, params);

            if (result.success && result.html) {
                this.renderContent(result.html);
            } else {
                throw new Error('Failed to load module content');
            }
        } catch (error) {
            console.error('Route load error:', error);
            this.showError('Der opstod en fejl ved indlæsning af siden');
        }
    },

    /**
     * Reload current route
     */
    reload() {
        if (this.currentRoute) {
            this.loadRoute(
                this.currentRoute.module,
                this.currentRoute.action,
                this.currentRoute.params,
                false
            );
        }
    },

    /**
     * Go back to previous route
     */
    back() {
        if (this.history.length > 1) {
            // Remove current route
            this.history.pop();
            // Get previous route
            const previous = this.history[this.history.length - 1];
            if (previous) {
                this.loadRoute(previous.module, previous.action, previous.params, false);
            }
        } else {
            this.navigate('dashboard');
        }
    },

    /**
     * Render content in main area
     */
    renderContent(html) {
        const mainContent = document.getElementById('mainContent');
        if (mainContent) {
            mainContent.innerHTML = html;

            // Execute any scripts in the loaded content
            const scripts = mainContent.querySelectorAll('script');
            scripts.forEach(script => {
                const newScript = document.createElement('script');
                if (script.src) {
                    newScript.src = script.src;
                } else {
                    newScript.textContent = script.textContent;
                }
                document.body.appendChild(newScript);
                document.body.removeChild(newScript);
            });

            // Scroll to top
            mainContent.scrollTop = 0;
        }
    },

    /**
     * Show loading state
     */
    showLoading() {
        const mainContent = document.getElementById('mainContent');
        if (mainContent) {
            mainContent.innerHTML = `
                <div class="loading-container">
                    <div class="spinner"></div>
                    <p>Indlæser...</p>
                </div>
            `;
        }
    },

    /**
     * Show error state
     */
    showError(message) {
        const mainContent = document.getElementById('mainContent');
        if (mainContent) {
            mainContent.innerHTML = `
                <div class="error-container">
                    <div class="error-icon">⚠️</div>
                    <h2>Der opstod en fejl</h2>
                    <p>${escapeHtml(message)}</p>
                    <button onclick="Router.reload()" class="btn btn-primary">Prøv igen</button>
                </div>
            `;
        }
    },

    /**
     * Update active navigation item
     */
    updateActiveNav(module) {
        // Remove all active classes
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });

        // Add active class to current module
        const activeItem = document.querySelector(`.nav-item[data-route="${module}"]`);
        if (activeItem) {
            activeItem.classList.add('active');
        }
    },

    /**
     * Build URL from route parts
     */
    _buildUrl(module, action, params) {
        const queryParams = new URLSearchParams({
            module: module,
            action: action,
            ...params
        });

        return `/?${queryParams.toString()}`;
    }
};

// Global navigate function
function navigate(route, params = {}) {
    Router.navigate(route, params);
}

// Export Router globally
window.Router = Router;
window.navigate = navigate;
