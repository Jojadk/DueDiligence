/**
 * Component Loader - Lazy load components kun når nødvendigt
 * Reducerer initial bundle size betydeligt
 */

const ComponentLoader = {
    loaded: new Set(),
    loading: new Map(),

    components: {
        'drag-drop': '/assets/js/components/drag-drop.js',
        'image-upload': '/assets/js/components/image-upload.js',
        'project-snapshot': '/assets/js/components/project-snapshot.js',
        'report-tree': '/assets/js/components/report-tree.js',
        'budget-modal': '/assets/js/components/budget-modal.js'
    },

    /**
     * Load component dynamically
     */
    async load(componentName) {
        // Already loaded
        if (this.loaded.has(componentName)) {
            return true;
        }

        // Currently loading
        if (this.loading.has(componentName)) {
            return this.loading.get(componentName);
        }

        // Component not registered
        if (!this.components[componentName]) {
            console.error(`Component '${componentName}' not registered`);
            return false;
        }

        // Start loading
        const loadPromise = this.loadScript(this.components[componentName])
            .then(() => {
                this.loaded.add(componentName);
                this.loading.delete(componentName);
                return true;
            })
            .catch(error => {
                console.error(`Failed to load component '${componentName}':`, error);
                this.loading.delete(componentName);
                return false;
            });

        this.loading.set(componentName, loadPromise);
        return loadPromise;
    },

    /**
     * Load multiple components
     */
    async loadMultiple(componentNames) {
        const promises = componentNames.map(name => this.load(name));
        const results = await Promise.all(promises);
        return results.every(result => result === true);
    },

    /**
     * Load script dynamically
     */
    loadScript(url) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = url;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    },

    /**
     * Check if component is loaded
     */
    isLoaded(componentName) {
        return this.loaded.has(componentName);
    },

    /**
     * Preload components for a module
     */
    async preloadForModule(moduleName) {
        const moduleComponents = {
            'building_element': ['drag-drop', 'image-upload'],
            'project': ['project-snapshot'],
            'report': ['report-tree'],
            'budget': ['budget-modal'],
            'opex': ['budget-modal']
        };

        const components = moduleComponents[moduleName] || [];
        if (components.length > 0) {
            return this.loadMultiple(components);
        }
        return true;
    }
};

// Global access
window.ComponentLoader = ComponentLoader;

// Auto-preload based on current route
document.addEventListener('DOMContentLoaded', () => {
    const currentModule = window.APP_CONFIG?.currentModule;
    if (currentModule) {
        ComponentLoader.preloadForModule(currentModule);
    }
});
