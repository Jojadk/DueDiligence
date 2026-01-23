/**
 * Central Validation Library
 * Provides standardized client-side validation for all input types
 * Complements backend validation in core/api-helpers.php
 */

const Validation = {
    /**
     * Validation rules and patterns
     */
    patterns: {
        email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
        phone: /^[\d\s\+\-\(\)]+$/,
        url: /^https?:\/\/.+/,
        cvr: /^\d{8}$/,
        date: /^\d{4}-\d{2}-\d{2}$/,
        datetime: /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/,
        alphanumeric: /^[a-zA-Z0-9]+$/,
        alpha: /^[a-zA-Z]+$/,
        numeric: /^[\d\.,]+$/,
        integer: /^-?\d+$/,
        float: /^-?\d+(\.\d+)?$/
    },

    /**
     * File validation constraints
     */
    fileConstraints: {
        image: {
            types: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
            maxSize: 10 * 1024 * 1024, // 10MB
            extensions: ['jpg', 'jpeg', 'png', 'webp', 'gif']
        },
        pdf: {
            types: ['application/pdf'],
            maxSize: 20 * 1024 * 1024, // 20MB
            extensions: ['pdf']
        },
        document: {
            types: ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                   'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            maxSize: 50 * 1024 * 1024, // 50MB
            extensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx']
        }
    },

    /**
     * Validate string input
     * @param {string} value - Value to validate
     * @param {object} options - Validation options
     * @returns {object} {valid: boolean, error: string}
     */
    string(value, options = {}) {
        const {
            required = false,
            minLength = 0,
            maxLength = null,
            pattern = null,
            trim = true
        } = options;

        // Trim if needed
        const val = trim && typeof value === 'string' ? value.trim() : value;

        // Check required
        if (required && (!val || val.length === 0)) {
            return { valid: false, error: 'Dette felt er påkrævet' };
        }

        // Skip further validation if empty and not required
        if (!val || val.length === 0) {
            return { valid: true };
        }

        // Check minLength
        if (minLength > 0 && val.length < minLength) {
            return { valid: false, error: `Minimum ${minLength} tegn påkrævet` };
        }

        // Check maxLength
        if (maxLength && val.length > maxLength) {
            return { valid: false, error: `Maksimum ${maxLength} tegn tilladt` };
        }

        // Check pattern
        if (pattern) {
            const regex = typeof pattern === 'string' ? this.patterns[pattern] : pattern;
            if (regex && !regex.test(val)) {
                return { valid: false, error: 'Ugyldig format' };
            }
        }

        return { valid: true, sanitized: val };
    },

    /**
     * Validate email
     */
    email(value, required = false) {
        const stringValidation = this.string(value, { required, trim: true });
        if (!stringValidation.valid) return stringValidation;

        if (!value) return { valid: true };

        if (!this.patterns.email.test(value.trim())) {
            return { valid: false, error: 'Ugyldig email-adresse' };
        }

        return { valid: true, sanitized: value.trim().toLowerCase() };
    },

    /**
     * Validate integer
     */
    int(value, options = {}) {
        const {
            required = false,
            min = null,
            max = null
        } = options;

        // Check required
        if (required && (value === null || value === undefined || value === '')) {
            return { valid: false, error: 'Dette felt er påkrævet' };
        }

        // Skip if empty and not required
        if (value === null || value === undefined || value === '') {
            return { valid: true, sanitized: null };
        }

        // Parse integer
        const num = parseInt(value, 10);

        if (isNaN(num) || !this.patterns.integer.test(String(value).trim())) {
            return { valid: false, error: 'Skal være et heltal' };
        }

        // Check range
        if (min !== null && num < min) {
            return { valid: false, error: `Minimum værdi er ${min}` };
        }

        if (max !== null && num > max) {
            return { valid: false, error: `Maksimum værdi er ${max}` };
        }

        return { valid: true, sanitized: num };
    },

    /**
     * Validate float/decimal
     */
    float(value, options = {}) {
        const {
            required = false,
            min = null,
            max = null,
            decimals = null
        } = options;

        // Check required
        if (required && (value === null || value === undefined || value === '')) {
            return { valid: false, error: 'Dette felt er påkrævet' };
        }

        // Skip if empty and not required
        if (value === null || value === undefined || value === '') {
            return { valid: true, sanitized: null };
        }

        // Parse float
        const num = parseFloat(String(value).replace(',', '.'));

        if (isNaN(num)) {
            return { valid: false, error: 'Skal være et tal' };
        }

        // Check range
        if (min !== null && num < min) {
            return { valid: false, error: `Minimum værdi er ${min}` };
        }

        if (max !== null && num > max) {
            return { valid: false, error: `Maksimum værdi er ${max}` };
        }

        // Round to decimals if specified
        const sanitized = decimals !== null ? parseFloat(num.toFixed(decimals)) : num;

        return { valid: true, sanitized };
    },

    /**
     * Validate date
     */
    date(value, options = {}) {
        const {
            required = false,
            minDate = null,
            maxDate = null,
            format = 'YYYY-MM-DD'
        } = options;

        // Check required
        if (required && !value) {
            return { valid: false, error: 'Dette felt er påkrævet' };
        }

        // Skip if empty and not required
        if (!value) {
            return { valid: true, sanitized: null };
        }

        // Check format
        if (!this.patterns.date.test(value)) {
            return { valid: false, error: 'Ugyldig datoformat (YYYY-MM-DD)' };
        }

        // Parse date
        const date = new Date(value);
        if (isNaN(date.getTime())) {
            return { valid: false, error: 'Ugyldig dato' };
        }

        // Check range
        if (minDate) {
            const min = new Date(minDate);
            if (date < min) {
                return { valid: false, error: `Dato skal være efter ${minDate}` };
            }
        }

        if (maxDate) {
            const max = new Date(maxDate);
            if (date > max) {
                return { valid: false, error: `Dato skal være før ${maxDate}` };
            }
        }

        return { valid: true, sanitized: value };
    },

    /**
     * Validate file
     */
    file(file, type = 'image', options = {}) {
        if (!file) {
            return { valid: !options.required, error: 'Fil er påkrævet' };
        }

        const constraints = this.fileConstraints[type] || this.fileConstraints.image;
        const maxSize = options.maxSize || constraints.maxSize;

        // Check file type
        if (!constraints.types.includes(file.type)) {
            return {
                valid: false,
                error: `Ugyldig filtype. Tilladte typer: ${constraints.extensions.join(', ')}`
            };
        }

        // Check file size
        if (file.size > maxSize) {
            const maxMB = Math.round(maxSize / (1024 * 1024));
            return {
                valid: false,
                error: `Filen er for stor. Maksimum størrelse: ${maxMB}MB`
            };
        }

        // Check file extension
        const extension = file.name.split('.').pop().toLowerCase();
        if (!constraints.extensions.includes(extension)) {
            return {
                valid: false,
                error: `Ugyldig fil-extension. Tilladte: ${constraints.extensions.join(', ')}`
            };
        }

        return { valid: true };
    },

    /**
     * Validate multiple files
     */
    files(files, type = 'image', options = {}) {
        const {
            maxFiles = 10,
            required = false
        } = options;

        if (!files || files.length === 0) {
            return { valid: !required, error: 'Mindst en fil er påkrævet' };
        }

        if (files.length > maxFiles) {
            return { valid: false, error: `Maksimum ${maxFiles} filer tilladt` };
        }

        const errors = [];
        for (let i = 0; i < files.length; i++) {
            const result = this.file(files[i], type, options);
            if (!result.valid) {
                errors.push(`${files[i].name}: ${result.error}`);
            }
        }

        if (errors.length > 0) {
            return { valid: false, error: errors.join('; ') };
        }

        return { valid: true };
    },

    /**
     * Validate CVR number (Danish company registration)
     */
    cvr(value, required = false) {
        const stringValidation = this.string(value, { required, trim: true });
        if (!stringValidation.valid) return stringValidation;

        if (!value) return { valid: true };

        const cleaned = value.replace(/\s/g, '');
        if (!this.patterns.cvr.test(cleaned)) {
            return { valid: false, error: 'CVR-nummer skal være 8 cifre' };
        }

        return { valid: true, sanitized: cleaned };
    },

    /**
     * Validate phone number
     */
    phone(value, required = false) {
        const stringValidation = this.string(value, { required, trim: true });
        if (!stringValidation.valid) return stringValidation;

        if (!value) return { valid: true };

        const cleaned = value.replace(/\s/g, '');
        if (!this.patterns.phone.test(cleaned)) {
            return { valid: false, error: 'Ugyldigt telefonnummer' };
        }

        return { valid: true, sanitized: cleaned };
    },

    /**
     * Validate URL
     */
    url(value, required = false) {
        const stringValidation = this.string(value, { required, trim: true });
        if (!stringValidation.valid) return stringValidation;

        if (!value) return { valid: true };

        if (!this.patterns.url.test(value.trim())) {
            return { valid: false, error: 'Ugyldig URL (skal starte med http:// eller https://)' };
        }

        return { valid: true, sanitized: value.trim() };
    },

    /**
     * Validate form data object
     * @param {object} data - Form data object
     * @param {object} rules - Validation rules
     * @returns {object} {valid: boolean, errors: object, sanitized: object}
     */
    validateForm(data, rules) {
        const errors = {};
        const sanitized = {};
        let valid = true;

        for (const [field, rule] of Object.entries(rules)) {
            const value = data[field];
            const type = rule.type || 'string';
            const options = rule.options || {};

            let result;
            switch (type) {
                case 'email':
                    result = this.email(value, options.required);
                    break;
                case 'int':
                    result = this.int(value, options);
                    break;
                case 'float':
                    result = this.float(value, options);
                    break;
                case 'date':
                    result = this.date(value, options);
                    break;
                case 'cvr':
                    result = this.cvr(value, options.required);
                    break;
                case 'phone':
                    result = this.phone(value, options.required);
                    break;
                case 'url':
                    result = this.url(value, options.required);
                    break;
                case 'file':
                    result = this.file(value, options.fileType, options);
                    break;
                default:
                    result = this.string(value, options);
            }

            if (!result.valid) {
                errors[field] = result.error;
                valid = false;
            } else if (result.sanitized !== undefined) {
                sanitized[field] = result.sanitized;
            }
        }

        return { valid, errors, sanitized };
    },

    /**
     * Show validation error on form field
     */
    showFieldError(fieldElement, errorMessage) {
        // Remove existing error
        this.clearFieldError(fieldElement);

        // Add error class to field
        fieldElement.classList.add('is-invalid');

        // Create error element
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = errorMessage;

        // Insert after field
        fieldElement.parentNode.insertBefore(errorDiv, fieldElement.nextSibling);
    },

    /**
     * Clear validation error from form field
     */
    clearFieldError(fieldElement) {
        fieldElement.classList.remove('is-invalid');
        const errorDiv = fieldElement.parentNode.querySelector('.invalid-feedback');
        if (errorDiv) {
            errorDiv.remove();
        }
    },

    /**
     * Clear all validation errors in a form
     */
    clearFormErrors(formElement) {
        const fields = formElement.querySelectorAll('.is-invalid');
        fields.forEach(field => this.clearFieldError(field));
    },

    /**
     * Display multiple form errors
     */
    showFormErrors(formElement, errors) {
        this.clearFormErrors(formElement);

        for (const [fieldName, errorMessage] of Object.entries(errors)) {
            const field = formElement.querySelector(`[name="${fieldName}"]`);
            if (field) {
                this.showFieldError(field, errorMessage);
            }
        }
    },

    /**
     * Format file size for display
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    },

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Sanitize HTML by removing script tags and dangerous attributes
     */
    sanitizeHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html;

        // Remove script tags
        const scripts = div.querySelectorAll('script');
        scripts.forEach(script => script.remove());

        // Remove dangerous attributes
        const dangerousAttrs = ['onclick', 'onload', 'onerror', 'onmouseover'];
        const allElements = div.querySelectorAll('*');
        allElements.forEach(el => {
            dangerousAttrs.forEach(attr => {
                if (el.hasAttribute(attr)) {
                    el.removeAttribute(attr);
                }
            });
        });

        return div.innerHTML;
    }
};

// Export globally
window.Validation = Validation;
