/**
 * Shared Module Utilities
 * Common functions used across multiple modules
 */
const ModuleUtils = {
    /**
     * Get the container element (window manager body, form container, or document body)
     * @param {HTMLElement} el - The element to find container for
     * @returns {HTMLElement} The container element
     */
    getContainer: function (el) {
        return el.closest('.wm-body') ||
            el.closest('.project-form-container') ||
            el.closest('.customer-form-container') ||
            el.closest('.form-container') ||
            document.body;
    },

    /**
     * Switch between tabs in a container
     * @param {HTMLElement} tabElement - The clicked tab element
     * @param {string} targetId - ID of the target content panel
     */
    switchTab: function (tabElement, targetId) {
        const container = this.getContainer(tabElement);

        // Deactivate all tabs
        container.querySelectorAll('.tab-item').forEach(t => {
            t.style.borderBottom = '2px solid transparent';
            t.classList.remove('active');
            t.style.fontWeight = 'normal';
        });

        // Activate clicked tab
        tabElement.style.borderBottom = '2px solid #007bff';
        tabElement.classList.add('active');
        tabElement.style.fontWeight = 'bold';

        // Hide all content panels
        container.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

        // Show target panel
        const target = container.querySelector('#' + targetId);
        if (target) target.style.display = 'block';
    },

    /**
     * Validate form input
     * @param {HTMLFormElement} form - Form element
     * @param {Object} rules - Validation rules
     * @returns {Object} {valid: boolean, errors: Array}
     */
    validateForm: function (form, rules) {
        const errors = [];

        for (const [field, rule] of Object.entries(rules)) {
            const input = form.querySelector(`[name="${field}"]`);
            if (!input) continue;

            const value = input.value.trim();

            if (rule.required && !value) {
                errors.push(`${rule.label || field} er påkrævet`);
            }

            if (rule.minLength && value.length < rule.minLength) {
                errors.push(`${rule.label || field} skal være mindst ${rule.minLength} tegn`);
            }

            if (rule.maxLength && value.length > rule.maxLength) {
                errors.push(`${rule.label || field} må max være ${rule.maxLength} tegn`);
            }

            if (rule.pattern && !rule.pattern.test(value)) {
                errors.push(rule.message || `${rule.label || field} har ugyldig format`);
            }
        }

        return {
            valid: errors.length === 0,
            errors: errors
        };
    },

    /**
     * Format date to Danish format
     * @param {Date|string} date - Date to format
     * @returns {string} Formatted date
     */
    formatDate: function (date) {
        if (!date) return '';
        const d = new Date(date);
        const day = String(d.getDate()).padStart(2, '0');
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const year = d.getFullYear();
        return `${day}/${month}-${year}`;
    },

    /**
     * Debounce function calls
     * @param {Function} func - Function to debounce
     * @param {number} wait - Wait time in ms
     * @returns {Function} Debounced function
     */
    debounce: function (func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Safe JSON parse with fallback
     * @param {string} str - String to parse
     * @param {*} fallback - Fallback value if parse fails
     * @returns {*} Parsed object or fallback
     */
    safeJsonParse: function (str, fallback = null) {
        try {
            return JSON.parse(str);
        } catch (e) {
            console.error('JSON parse error:', e);
            return fallback;
        }
    }
};

// Make it globally available
window.ModuleUtils = ModuleUtils;
