/**
 * Keyboard Shortcuts System
 * Provides global keyboard shortcuts for common actions
 *
 * @version 1.0.0
 */

const KeyboardShortcuts = {
    // Configuration
    config: {
        enabled: true,
        shortcuts: new Map()
    },

    // Active shortcuts
    activeShortcuts: [],

    /**
     * Initialize keyboard shortcuts
     */
    init() {
        console.log('KeyboardShortcuts: Initializing...');

        // Register default shortcuts
        this.registerDefaults();

        // Listen for keyboard events
        document.addEventListener('keydown', (e) => this.handleKeyDown(e));

        console.log('KeyboardShortcuts: Initialized with', this.config.shortcuts.size, 'shortcuts');
    },

    /**
     * Register default system shortcuts
     */
    registerDefaults() {
        // Global search (Ctrl/Cmd + K)
        this.register('ctrl+k', () => {
            const searchInput = document.getElementById('globalSearch');
            if (searchInput) {
                searchInput.focus();
                searchInput.select();
            }
        }, 'Åbn global søgning');

        // Create new (Ctrl/Cmd + N)
        this.register('ctrl+n', () => {
            // Get current module and trigger create action
            const currentRoute = window.location.hash.substring(1) || 'dashboard';
            const moduleName = currentRoute.split('/')[0];

            // Try to find and click the "Create" button
            const createBtn = document.querySelector('[data-action="create"], .btn-create, #createBtn');
            if (createBtn) {
                createBtn.click();
            } else {
                // Module-specific create handlers
                switch (moduleName) {
                    case 'customer':
                        if (typeof window.CustomerModule !== 'undefined' && window.CustomerModule.openCreate) {
                            window.CustomerModule.openCreate();
                        }
                        break;
                    case 'project':
                        if (typeof window.ProjectModule !== 'undefined' && window.ProjectModule.openCreate) {
                            window.ProjectModule.openCreate();
                        }
                        break;
                    case 'building':
                        if (typeof window.BuildingModule !== 'undefined' && window.BuildingModule.openCreate) {
                            window.BuildingModule.openCreate();
                        }
                        break;
                    default:
                        console.log('KeyboardShortcuts: No create action available for module:', moduleName);
                }
            }
        }, 'Opret nyt element');

        // Close modal (Escape)
        this.register('escape', () => {
            // Try different modal close methods
            if (typeof Modal !== 'undefined' && Modal.close) {
                Modal.close();
            } else if (typeof ModalBuilder !== 'undefined' && ModalBuilder.close) {
                ModalBuilder.close();
            } else {
                // Try to find and click close button
                const closeBtn = document.querySelector('.modal.active .modal-close, .modal.active [data-modal-close]');
                if (closeBtn) {
                    closeBtn.click();
                }
            }
        }, 'Luk modal/dialog');

        // Show help (?)
        this.register('shift+/', () => {
            if (typeof UserGuide !== 'undefined' && UserGuide.showHelpMenu) {
                UserGuide.showHelpMenu();
            }
        }, 'Vis hjælp');

        // Save (Ctrl/Cmd + S)
        this.register('ctrl+s', () => {
            // Find active form and submit it
            const activeForm = document.querySelector('.modal.active form, form.active');
            if (activeForm) {
                const submitBtn = activeForm.querySelector('[type="submit"], .btn-submit, .btn-save');
                if (submitBtn) {
                    submitBtn.click();
                }
            }
        }, 'Gem formular');

        // Navigate to dashboard (Alt + H)
        this.register('alt+h', () => {
            if (typeof navigate !== 'undefined') {
                navigate('dashboard');
            }
        }, 'Gå til dashboard');

        // Navigate to customers (Alt + C)
        this.register('alt+c', () => {
            if (typeof navigate !== 'undefined') {
                navigate('customer');
            }
        }, 'Gå til kunder');

        // Navigate to projects (Alt + P)
        this.register('alt+p', () => {
            if (typeof navigate !== 'undefined') {
                navigate('project');
            }
        }, 'Gå til projekter');

        // Navigate to buildings (Alt + B)
        this.register('alt+b', () => {
            if (typeof navigate !== 'undefined') {
                navigate('building');
            }
        }, 'Gå til bygninger');

        // Navigate to reports (Alt + R)
        this.register('alt+r', () => {
            if (typeof navigate !== 'undefined') {
                navigate('reports');
            }
        }, 'Gå til rapporter');

        // Refresh page (Ctrl/Cmd + R) - prevented and handled
        this.register('ctrl+r', () => {
            // Reload current module instead of full page refresh
            const currentRoute = window.location.hash.substring(1) || 'dashboard';
            if (typeof navigate !== 'undefined') {
                navigate(currentRoute);
            }
        }, 'Genindlæs nuværende side', true);

        // Toggle sidebar (Ctrl/Cmd + B)
        this.register('ctrl+b', () => {
            const toggleBtn = document.getElementById('toggleSidebar');
            if (toggleBtn) {
                toggleBtn.click();
            }
        }, 'Skjul/vis sidebar');

        // Focus on notifications (Alt + N)
        this.register('alt+n', () => {
            const notificationBtn = document.getElementById('notificationBtn');
            if (notificationBtn) {
                notificationBtn.click();
            }
        }, 'Åbn notifikationer');

        // Show keyboard shortcuts (Ctrl/Cmd + /)
        this.register('ctrl+/', () => {
            this.showShortcutsModal();
        }, 'Vis genvejstaster');
    },

    /**
     * Register a keyboard shortcut
     *
     * @param {string} combination - Key combination (e.g., 'ctrl+k', 'alt+shift+p')
     * @param {function} callback - Function to execute
     * @param {string} description - Description of the shortcut
     * @param {boolean} preventDefault - Whether to prevent default browser action
     */
    register(combination, callback, description = '', preventDefault = true) {
        const normalizedCombo = this.normalizeCombo(combination);

        this.config.shortcuts.set(normalizedCombo, {
            callback,
            description,
            preventDefault,
            combination: combination
        });
    },

    /**
     * Unregister a keyboard shortcut
     *
     * @param {string} combination - Key combination to unregister
     */
    unregister(combination) {
        const normalizedCombo = this.normalizeCombo(combination);
        this.config.shortcuts.delete(normalizedCombo);
    },

    /**
     * Normalize key combination for consistent matching
     *
     * @param {string} combo - Key combination
     * @return {string} Normalized combination
     */
    normalizeCombo(combo) {
        const parts = combo.toLowerCase().split('+');
        const modifiers = [];
        let key = '';

        parts.forEach(part => {
            if (['ctrl', 'alt', 'shift', 'meta', 'cmd'].includes(part)) {
                // Normalize cmd to ctrl on Windows
                if (part === 'cmd') {
                    part = 'ctrl';
                }
                modifiers.push(part);
            } else {
                key = part;
            }
        });

        // Sort modifiers for consistency
        modifiers.sort();

        return modifiers.length > 0 ? modifiers.join('+') + '+' + key : key;
    },

    /**
     * Handle keydown event
     *
     * @param {KeyboardEvent} e - Keyboard event
     */
    handleKeyDown(e) {
        if (!this.config.enabled) return;

        // Don't trigger shortcuts when typing in inputs (except Escape)
        const target = e.target;
        const isInput = target.tagName === 'INPUT' || target.tagName === 'TEXTAREA' || target.isContentEditable;

        if (isInput && e.key !== 'Escape') {
            // Only allow certain shortcuts in inputs
            const allowedInInputs = ['ctrl+s', 'ctrl+k'];
            const combo = this.buildCombo(e);
            if (!allowedInInputs.includes(combo)) {
                return;
            }
        }

        // Build key combination from event
        const combo = this.buildCombo(e);

        // Find matching shortcut
        const shortcut = this.config.shortcuts.get(combo);

        if (shortcut) {
            if (shortcut.preventDefault) {
                e.preventDefault();
            }

            try {
                shortcut.callback(e);
                console.log('KeyboardShortcuts: Executed:', combo);
            } catch (error) {
                console.error('KeyboardShortcuts: Error executing shortcut:', combo, error);
            }
        }
    },

    /**
     * Build key combination string from keyboard event
     *
     * @param {KeyboardEvent} e - Keyboard event
     * @return {string} Key combination string
     */
    buildCombo(e) {
        const modifiers = [];
        let key = e.key.toLowerCase();

        // Map special keys
        const keyMap = {
            'escape': 'escape',
            'esc': 'escape',
            ' ': 'space',
            'arrowup': 'up',
            'arrowdown': 'down',
            'arrowleft': 'left',
            'arrowright': 'right',
            'delete': 'del'
        };

        key = keyMap[key] || key;

        // Check modifiers
        if (e.ctrlKey || e.metaKey) modifiers.push('ctrl');
        if (e.altKey) modifiers.push('alt');
        if (e.shiftKey && key !== 'shift') modifiers.push('shift');

        // Sort modifiers
        modifiers.sort();

        return modifiers.length > 0 ? modifiers.join('+') + '+' + key : key;
    },

    /**
     * Enable keyboard shortcuts
     */
    enable() {
        this.config.enabled = true;
        console.log('KeyboardShortcuts: Enabled');
    },

    /**
     * Disable keyboard shortcuts
     */
    disable() {
        this.config.enabled = false;
        console.log('KeyboardShortcuts: Disabled');
    },

    /**
     * Show keyboard shortcuts modal
     */
    async showShortcutsModal() {
        // Group shortcuts by category
        const categories = {
            'Navigation': [],
            'Handlinger': [],
            'Søgning': [],
            'Hjælp': []
        };

        this.config.shortcuts.forEach((shortcut, combo) => {
            const displayCombo = this.formatComboForDisplay(shortcut.combination);
            const item = { combo: displayCombo, description: shortcut.description };

            // Categorize
            if (combo.includes('alt+') && combo.match(/[hcpbr]$/)) {
                categories['Navigation'].push(item);
            } else if (combo.includes('ctrl+k') || combo.includes('search')) {
                categories['Søgning'].push(item);
            } else if (combo.includes('help') || combo.includes('shift+/') || combo.includes('ctrl+/')) {
                categories['Hjælp'].push(item);
            } else {
                categories['Handlinger'].push(item);
            }
        });

        // Build HTML
        let html = '<div class="keyboard-shortcuts-modal">';
        html += '<h3>Genvejstaster</h3>';

        Object.entries(categories).forEach(([category, shortcuts]) => {
            if (shortcuts.length === 0) return;

            html += `<div class="shortcuts-category">`;
            html += `<h4>${category}</h4>`;
            html += '<div class="shortcuts-list">';

            shortcuts.forEach(shortcut => {
                html += `<div class="shortcut-row">`;
                html += `<span class="shortcut-keys">${shortcut.combo}</span>`;
                html += `<span class="shortcut-desc">${shortcut.description}</span>`;
                html += `</div>`;
            });

            html += '</div></div>';
        });

        html += '</div>';

        if (typeof ModalBuilder !== 'undefined') {
            await ModalBuilder.alert('Genvejstaster', html, {
                size: 'large',
                confirmText: 'Luk'
            });
        }
    },

    /**
     * Format key combination for display
     *
     * @param {string} combo - Key combination
     * @return {string} Formatted HTML
     */
    formatComboForDisplay(combo) {
        const parts = combo.split('+');
        const formatted = parts.map(part => {
            // Capitalize and format
            const displayMap = {
                'ctrl': navigator.platform.includes('Mac') ? '⌘' : 'Ctrl',
                'alt': navigator.platform.includes('Mac') ? '⌥' : 'Alt',
                'shift': navigator.platform.includes('Mac') ? '⇧' : 'Shift',
                'escape': 'Esc',
                '/': '?',
                'meta': '⌘'
            };

            const display = displayMap[part.toLowerCase()] || part.toUpperCase();
            return `<kbd>${display}</kbd>`;
        });

        return formatted.join(' + ');
    },

    /**
     * Get all registered shortcuts
     *
     * @return {Map} All shortcuts
     */
    getAll() {
        return this.config.shortcuts;
    }
};

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => KeyboardShortcuts.init());
} else {
    KeyboardShortcuts.init();
}

// Expose globally
window.KeyboardShortcuts = KeyboardShortcuts;
