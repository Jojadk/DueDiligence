/**
 * Theme Manager
 * Manages light/dark theme switching with persistence
 *
 * @version 1.0.0
 */

const ThemeManager = {
    // Configuration
    config: {
        storageKey: 'duediligence_theme',
        defaultTheme: 'light',
        enableSystemPreference: true,
        transitionClass: 'theme-transitioning'
    },

    // Current theme
    currentTheme: null,

    /**
     * Initialize theme manager
     */
    init() {
        console.log('ThemeManager: Initializing...');

        // Get initial theme
        this.currentTheme = this.getInitialTheme();

        // Apply theme immediately (before page renders)
        this.applyTheme(this.currentTheme, false);

        // Listen for system theme changes
        if (this.config.enableSystemPreference) {
            this.listenForSystemChanges();
        }

        // Add theme toggle to header
        this.addThemeToggle();

        console.log('ThemeManager: Initialized with theme:', this.currentTheme);
    },

    /**
     * Get initial theme
     *
     * @return {string} Theme name ('light' or 'dark')
     */
    getInitialTheme() {
        // 1. Check localStorage
        const stored = localStorage.getItem(this.config.storageKey);
        if (stored && (stored === 'light' || stored === 'dark')) {
            return stored;
        }

        // 2. Check system preference
        if (this.config.enableSystemPreference && window.matchMedia) {
            if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
        }

        // 3. Use default
        return this.config.defaultTheme;
    },

    /**
     * Apply theme
     *
     * @param {string} theme - Theme name ('light' or 'dark')
     * @param {boolean} animate - Whether to animate the transition
     */
    applyTheme(theme, animate = true) {
        if (theme !== 'light' && theme !== 'dark') {
            console.warn('ThemeManager: Invalid theme:', theme);
            return;
        }

        console.log('ThemeManager: Applying theme:', theme);

        // Add transition class if animating
        if (animate) {
            document.body.classList.add(this.config.transitionClass);
        }

        // Apply theme
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else {
            document.documentElement.removeAttribute('data-theme');
        }

        // Update current theme
        this.currentTheme = theme;

        // Save to localStorage
        localStorage.setItem(this.config.storageKey, theme);

        // Remove transition class after animation
        if (animate) {
            setTimeout(() => {
                document.body.classList.remove(this.config.transitionClass);
            }, 300);
        }

        // Dispatch theme change event
        window.dispatchEvent(new CustomEvent('themechange', {
            detail: { theme }
        }));
    },

    /**
     * Toggle theme
     */
    toggle() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';
        this.applyTheme(newTheme, true);
    },

    /**
     * Listen for system theme changes
     */
    listenForSystemChanges() {
        if (!window.matchMedia) return;

        const darkModeQuery = window.matchMedia('(prefers-color-scheme: dark)');

        // Modern browsers
        if (darkModeQuery.addEventListener) {
            darkModeQuery.addEventListener('change', (e) => {
                // Only apply system preference if user hasn't manually set theme
                const hasManualPreference = localStorage.getItem(this.config.storageKey);
                if (!hasManualPreference) {
                    const newTheme = e.matches ? 'dark' : 'light';
                    this.applyTheme(newTheme, true);
                }
            });
        }
        // Legacy browsers
        else if (darkModeQuery.addListener) {
            darkModeQuery.addListener((e) => {
                const hasManualPreference = localStorage.getItem(this.config.storageKey);
                if (!hasManualPreference) {
                    const newTheme = e.matches ? 'dark' : 'light';
                    this.applyTheme(newTheme, true);
                }
            });
        }
    },

    /**
     * Add theme toggle button to header
     */
    addThemeToggle() {
        // Wait for DOM to be ready
        const addToggle = () => {
            const headerRight = document.querySelector('.header-right');
            if (!headerRight) {
                console.warn('ThemeManager: Header not found, cannot add toggle');
                return;
            }

            // Create toggle button
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'btn-icon theme-toggle-btn';
            toggleBtn.id = 'themeToggle';
            toggleBtn.setAttribute('aria-label', 'Skift tema');
            toggleBtn.setAttribute('data-tooltip', 'Skift mellem lys og mørk tema');
            toggleBtn.innerHTML = `
                <div class="theme-toggle">
                    <div class="theme-toggle-slider"></div>
                </div>
            `;

            // Add click handler
            toggleBtn.addEventListener('click', () => {
                this.toggle();
            });

            // Insert before help button or notification button
            const helpBtn = document.getElementById('helpBtn');
            const notificationBtn = document.getElementById('notificationBtn');
            const insertBefore = helpBtn || notificationBtn;

            if (insertBefore) {
                headerRight.insertBefore(toggleBtn, insertBefore);
            } else {
                headerRight.insertBefore(toggleBtn, headerRight.firstChild);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', addToggle);
        } else {
            addToggle();
        }
    },

    /**
     * Get current theme
     *
     * @return {string} Current theme name
     */
    getTheme() {
        return this.currentTheme;
    },

    /**
     * Set theme
     *
     * @param {string} theme - Theme name ('light' or 'dark')
     */
    setTheme(theme) {
        this.applyTheme(theme, true);
    },

    /**
     * Reset to system preference
     */
    resetToSystem() {
        localStorage.removeItem(this.config.storageKey);

        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            this.applyTheme('dark', true);
        } else {
            this.applyTheme('light', true);
        }
    },

    /**
     * Enable system preference tracking
     */
    enableSystemPreference() {
        this.config.enableSystemPreference = true;
        this.listenForSystemChanges();
    },

    /**
     * Disable system preference tracking
     */
    disableSystemPreference() {
        this.config.enableSystemPreference = false;
    }
};

// Initialize immediately (before DOMContentLoaded) to prevent flash
ThemeManager.init();

// Expose globally
window.ThemeManager = ThemeManager;
