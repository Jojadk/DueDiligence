/**
 * Modal Builder - JavaScript counterpart to PHP ModalBuilder
 *
 * Provides standardized modal HTML generation with consistent structure
 * Matches PHP modal-builder.php for backend-generated modals
 *
 * Usage:
 *   const modal = ModalBuilder.create('project-form')
 *       .title('Opret Projekt')
 *       .body(formHtml)
 *       .footer([
 *           { text: 'Annuller', class: 'btn-secondary', action: 'close' },
 *           { text: 'Gem', class: 'btn-primary', action: 'submit', form: 'project-form' }
 *       ])
 *       .size('large')
 *       .show();
 */

class ModalBuilder {
    constructor(id) {
        this.id = id;
        this.config = {
            title: '',
            body: '',
            footer: [],
            size: 'medium', // small, medium, large, xlarge
            closeButton: true,
            backdrop: true,
            keyboard: true,
            headerButtons: [],
            attributes: {},
            onShow: null,
            onClose: null
        };
    }

    /**
     * Create new modal builder
     */
    static create(id) {
        return new ModalBuilder(id);
    }

    /**
     * Set modal title
     */
    title(text) {
        this.config.title = text;
        return this;
    }

    /**
     * Set modal body content
     */
    body(content) {
        this.config.body = content;
        return this;
    }

    /**
     * Set modal footer buttons
     *
     * Button format:
     * {
     *   text: 'Button Text',
     *   class: 'btn-primary',
     *   action: 'submit|close|custom',
     *   form: 'form-id',  // Optional: associate with form
     *   icon: '<svg>...</svg>',  // Optional
     *   onClick: () => {},  // Optional click handler
     *   data: { key: 'value' }  // Optional data attributes
     * }
     */
    footer(buttons) {
        this.config.footer = buttons;
        return this;
    }

    /**
     * Set modal size
     */
    size(size) {
        this.config.size = size;
        return this;
    }

    /**
     * Enable/disable close button
     */
    closeButton(enabled) {
        this.config.closeButton = enabled;
        return this;
    }

    /**
     * Enable/disable backdrop click to close
     */
    backdrop(enabled) {
        this.config.backdrop = enabled;
        return this;
    }

    /**
     * Enable/disable ESC key to close
     */
    keyboard(enabled) {
        this.config.keyboard = enabled;
        return this;
    }

    /**
     * Add custom header buttons
     */
    headerButtons(buttons) {
        this.config.headerButtons = buttons;
        return this;
    }

    /**
     * Add custom data attributes
     */
    attributes(attrs) {
        this.config.attributes = attrs;
        return this;
    }

    /**
     * Set onShow callback
     */
    onShow(callback) {
        this.config.onShow = callback;
        return this;
    }

    /**
     * Set onClose callback
     */
    onClose(callback) {
        this.config.onClose = callback;
        return this;
    }

    /**
     * Build modal HTML
     */
    build() {
        const sizeClass = this.getSizeClass();
        const dataAttrs = this.buildDataAttributes();

        let html = `<div class="modal-content ${sizeClass}" ${dataAttrs}>`;

        // Header
        if (this.config.title || this.config.closeButton || this.config.headerButtons.length > 0) {
            html += this.buildHeader();
        }

        // Body
        html += '<div class="modal-body">';
        html += this.config.body;
        html += '</div>';

        // Footer
        if (this.config.footer.length > 0) {
            html += this.buildFooter();
        }

        html += '</div>';

        return html;
    }

    /**
     * Build modal header
     */
    buildHeader() {
        let html = '<div class="modal-header">';

        if (this.config.title) {
            html += `<h2 class="modal-title">${Validation.escapeHtml(this.config.title)}</h2>`;
        }

        // Header buttons
        if (this.config.headerButtons.length > 0) {
            html += '<div class="modal-header-buttons">';
            for (const button of this.config.headerButtons) {
                html += this.buildButton(button, true);
            }
            html += '</div>';
        }

        // Close button
        if (this.config.closeButton) {
            html += `
                <button type="button" class="btn-modal-close" data-modal-close aria-label="Luk">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            `;
        }

        html += '</div>';

        return html;
    }

    /**
     * Build modal footer
     */
    buildFooter() {
        let html = '<div class="modal-footer">';

        for (const button of this.config.footer) {
            html += this.buildButton(button);
        }

        html += '</div>';

        return html;
    }

    /**
     * Build individual button
     */
    buildButton(button, isHeaderButton = false) {
        const text = button.text || '';
        const btnClass = button.class || 'btn';
        const action = button.action || '';
        const form = button.form || '';
        const icon = button.icon || '';
        const data = button.data || {};

        const attrs = [];

        // Add classes
        if (isHeaderButton) {
            attrs.push(`class="btn-icon ${btnClass}"`);
        } else {
            attrs.push(`class="btn ${btnClass}"`);
        }

        // Add action attribute
        if (action === 'close') {
            attrs.push('data-modal-close');
        } else if (action === 'submit' && form) {
            attrs.push('type="submit"');
            attrs.push(`form="${form}"`);
        } else if (action) {
            attrs.push(`data-action="${action}"`);
        }

        // Add custom data attributes
        for (const [key, value] of Object.entries(data)) {
            attrs.push(`data-${key}="${Validation.escapeHtml(String(value))}"`);
        }

        let html = `<button ${attrs.join(' ')}>`;

        if (icon) {
            html += icon;
        }

        if (text) {
            html += Validation.escapeHtml(text);
        }

        html += '</button>';

        return html;
    }

    /**
     * Get CSS size class
     */
    getSizeClass() {
        switch (this.config.size) {
            case 'small':
                return 'modal-sm';
            case 'large':
                return 'modal-lg';
            case 'xlarge':
                return 'modal-xl';
            default:
                return 'modal-md';
        }
    }

    /**
     * Build data attributes string
     */
    buildDataAttributes() {
        const attrs = [];

        attrs.push(`data-modal-id="${Validation.escapeHtml(this.id)}"`);

        if (!this.config.backdrop) {
            attrs.push('data-backdrop="false"');
        }

        if (!this.config.keyboard) {
            attrs.push('data-keyboard="false"');
        }

        for (const [key, value] of Object.entries(this.config.attributes)) {
            attrs.push(`data-${key}="${Validation.escapeHtml(String(value))}"`);
        }

        return attrs.join(' ');
    }

    /**
     * Show the modal
     */
    show() {
        const html = this.build();

        const modalId = Modal.open(html, {
            size: this.config.size,
            title: '', // Title already in content
            closeButton: false, // Close button already in content
            backdrop: this.config.backdrop,
            keyboard: this.config.keyboard,
            onClose: this.config.onClose
        });

        // Attach click handlers to footer buttons
        if (this.config.footer.length > 0) {
            const modalEl = document.getElementById(modalId);
            if (modalEl) {
                this.config.footer.forEach((button, index) => {
                    if (button.onClick) {
                        const buttonEl = modalEl.querySelector(`[data-action="${button.action}"]`);
                        if (buttonEl) {
                            buttonEl.addEventListener('click', button.onClick);
                        }
                    }
                });
            }
        }

        // Call onShow callback
        if (this.config.onShow) {
            setTimeout(() => this.config.onShow(modalId), 0);
        }

        return modalId;
    }

    /**
     * Create standard form modal
     */
    static form(id, title, formHtml, formId, options = {}) {
        const size = options.size || 'medium';
        const submitText = options.submitText || 'Gem';
        const cancelText = options.cancelText || 'Annuller';
        const submitClass = options.submitClass || 'btn-primary';
        const onSubmit = options.onSubmit || null;

        const builder = ModalBuilder.create(id)
            .title(title)
            .body(formHtml)
            .footer([
                {
                    text: cancelText,
                    class: 'btn-secondary',
                    action: 'close'
                },
                {
                    text: submitText,
                    class: submitClass,
                    action: 'submit',
                    form: formId,
                    onClick: onSubmit
                }
            ])
            .size(size);

        if (options.onShow) builder.onShow(options.onShow);
        if (options.onClose) builder.onClose(options.onClose);

        return builder.show();
    }

    /**
     * Create confirmation modal
     */
    static confirm(id, title, message, options = {}) {
        return new Promise((resolve) => {
            const confirmText = options.confirmText || 'Bekræft';
            const cancelText = options.cancelText || 'Annuller';
            const confirmClass = options.confirmClass || 'btn-danger';
            const icon = options.icon || '';

            let body = '<div class="modal-confirm-message">';
            if (icon) {
                body += `<div class="modal-confirm-icon">${icon}</div>`;
            }
            body += `<p>${Validation.escapeHtml(message)}</p>`;
            body += '</div>';

            ModalBuilder.create(id)
                .title(title)
                .body(body)
                .footer([
                    {
                        text: cancelText,
                        class: 'btn-secondary',
                        action: 'cancel',
                        onClick: () => {
                            Modal.close();
                            resolve(false);
                        }
                    },
                    {
                        text: confirmText,
                        class: confirmClass,
                        action: 'confirm',
                        onClick: () => {
                            Modal.close();
                            resolve(true);
                        }
                    }
                ])
                .size('small')
                .onClose(() => resolve(false))
                .show();
        });
    }

    /**
     * Create alert modal
     */
    static alert(id, title, message, options = {}) {
        return new Promise((resolve) => {
            const buttonText = options.buttonText || 'OK';
            const buttonClass = options.buttonClass || 'btn-primary';
            const icon = options.icon || '';
            const type = options.type || 'info'; // info, success, warning, error

            let body = `<div class="modal-alert modal-alert-${type}">`;
            if (icon) {
                body += `<div class="modal-alert-icon">${icon}</div>`;
            }
            body += `<p>${Validation.escapeHtml(message)}</p>`;
            body += '</div>';

            ModalBuilder.create(id)
                .title(title)
                .body(body)
                .footer([
                    {
                        text: buttonText,
                        class: buttonClass,
                        action: 'close',
                        onClick: () => {
                            Modal.close();
                            resolve();
                        }
                    }
                ])
                .size('small')
                .onClose(() => resolve())
                .show();
        });
    }

    /**
     * Create table/list modal
     */
    static table(id, title, tableHtml, options = {}) {
        const size = options.size || 'large';
        const showActions = options.showActions !== false;

        const body = `<div class="modal-table-wrapper">${tableHtml}</div>`;

        const footer = [];
        if (showActions) {
            footer.push({
                text: 'Luk',
                class: 'btn-secondary',
                action: 'close'
            });
        }

        const builder = ModalBuilder.create(id)
            .title(title)
            .body(body)
            .footer(footer)
            .size(size);

        if (options.onShow) builder.onShow(options.onShow);
        if (options.onClose) builder.onClose(options.onClose);

        return builder.show();
    }
}

// Export globally
window.ModalBuilder = ModalBuilder;
