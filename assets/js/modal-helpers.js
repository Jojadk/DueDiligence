/**
 * Modal Helper Utilities
 *
 * Common modal patterns and utilities built on top of ModalBuilder
 * Provides reusable modal templates for common use cases
 */

const ModalHelpers = {
    /**
     * Show form modal for creating/editing entity
     *
     * @param {Object} options Modal configuration
     * @returns {Promise<Object>} Form data or null if cancelled
     */
    async showFormModal(options) {
        const {
            id = 'form-modal',
            title = 'Form',
            fields = [],
            data = {},
            size = 'medium',
            submitText = 'Gem',
            cancelText = 'Annuller',
            onValidate = null,
            onSubmit = null
        } = options;

        return new Promise((resolve) => {
            // Build form HTML
            const formId = `${id}-form`;
            let formHtml = `<form id="${formId}" class="modal-form">`;

            for (const field of fields) {
                formHtml += this._buildFormField(field, data);
            }

            formHtml += '</form>';

            // Show modal
            ModalBuilder.form(id, title, formHtml, formId, {
                size,
                submitText,
                cancelText,
                onSubmit: async () => {
                    const form = document.getElementById(formId);
                    const formData = new FormData(form);
                    const formObj = Object.fromEntries(formData.entries());

                    // Client-side validation
                    if (!form.checkValidity()) {
                        form.reportValidity();
                        return;
                    }

                    // Custom validation
                    if (onValidate) {
                        const validationResult = await onValidate(formObj);
                        if (validationResult !== true) {
                            Toast.error(validationResult || 'Validation failed');
                            return;
                        }
                    }

                    // Handle submission
                    if (onSubmit) {
                        try {
                            const result = await onSubmit(formObj);
                            if (result !== false) {
                                Modal.close();
                                resolve(formObj);
                            }
                        } catch (error) {
                            Toast.error('Submission failed: ' + error.message);
                        }
                    } else {
                        Modal.close();
                        resolve(formObj);
                    }
                },
                onShow: () => {
                    // Focus first input
                    const firstInput = document.querySelector(`#${formId} input, #${formId} textarea, #${formId} select`);
                    if (firstInput) firstInput.focus();
                },
                onClose: () => resolve(null)
            });
        });
    },

    /**
     * Build form field HTML
     */
    _buildFormField(field, data) {
        const {
            name,
            label,
            type = 'text',
            required = false,
            placeholder = '',
            options = [],
            rows = 3,
            min,
            max,
            step,
            pattern,
            help
        } = field;

        const value = data[name] || '';
        const requiredAttr = required ? 'required' : '';
        const id = `field-${name}`;

        let html = '<div class="form-group">';
        html += `<label for="${id}">${Validation.escapeHtml(label)}${required ? ' *' : ''}</label>`;

        switch (type) {
            case 'textarea':
                html += `<textarea id="${id}" name="${name}" rows="${rows}" ${requiredAttr} placeholder="${Validation.escapeHtml(placeholder)}">${Validation.escapeHtml(value)}</textarea>`;
                break;

            case 'select':
                html += `<select id="${id}" name="${name}" ${requiredAttr}>`;
                html += `<option value="">Vælg...</option>`;
                for (const opt of options) {
                    const selected = value === opt.value ? 'selected' : '';
                    html += `<option value="${Validation.escapeHtml(opt.value)}" ${selected}>${Validation.escapeHtml(opt.label)}</option>`;
                }
                html += '</select>';
                break;

            case 'checkbox':
                const checked = value ? 'checked' : '';
                html += `<input type="checkbox" id="${id}" name="${name}" ${checked}>`;
                break;

            case 'number':
                const minAttr = min !== undefined ? `min="${min}"` : '';
                const maxAttr = max !== undefined ? `max="${max}"` : '';
                const stepAttr = step !== undefined ? `step="${step}"` : '';
                html += `<input type="number" id="${id}" name="${name}" value="${Validation.escapeHtml(value)}" ${requiredAttr} ${minAttr} ${maxAttr} ${stepAttr} placeholder="${Validation.escapeHtml(placeholder)}">`;
                break;

            case 'date':
                html += `<input type="date" id="${id}" name="${name}" value="${Validation.escapeHtml(value)}" ${requiredAttr}>`;
                break;

            case 'email':
                html += `<input type="email" id="${id}" name="${name}" value="${Validation.escapeHtml(value)}" ${requiredAttr} placeholder="${Validation.escapeHtml(placeholder)}">`;
                break;

            default: // text
                const patternAttr = pattern ? `pattern="${pattern}"` : '';
                html += `<input type="text" id="${id}" name="${name}" value="${Validation.escapeHtml(value)}" ${requiredAttr} ${patternAttr} placeholder="${Validation.escapeHtml(placeholder)}">`;
        }

        if (help) {
            html += `<small class="form-help">${Validation.escapeHtml(help)}</small>`;
        }

        html += '</div>';

        return html;
    },

    /**
     * Show delete confirmation modal
     */
    async confirmDelete(entityName, entityType = 'element') {
        return await ModalBuilder.confirm(
            'delete-confirm',
            'Bekræft sletning',
            `Er du sikker på, at du vil slette ${entityType} "${entityName}"? Denne handling kan ikke fortrydes.`,
            {
                confirmText: 'Slet',
                confirmClass: 'btn-danger',
                cancelText: 'Annuller',
                icon: `<svg class="icon-warning" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>`
            }
        );
    },

    /**
     * Show success message
     */
    async showSuccess(message, title = 'Success') {
        return await ModalBuilder.alert(
            'success-modal',
            title,
            message,
            {
                type: 'success',
                buttonText: 'OK',
                buttonClass: 'btn-primary',
                icon: `<svg class="icon-success" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>`
            }
        );
    },

    /**
     * Show error message
     */
    async showError(message, title = 'Fejl') {
        return await ModalBuilder.alert(
            'error-modal',
            title,
            message,
            {
                type: 'error',
                buttonText: 'OK',
                buttonClass: 'btn-danger',
                icon: `<svg class="icon-error" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="15" y1="9" x2="9" y2="15"></line>
                    <line x1="9" y1="9" x2="15" y2="15"></line>
                </svg>`
            }
        );
    },

    /**
     * Show warning message
     */
    async showWarning(message, title = 'Advarsel') {
        return await ModalBuilder.alert(
            'warning-modal',
            title,
            message,
            {
                type: 'warning',
                buttonText: 'OK',
                buttonClass: 'btn-warning',
                icon: `<svg class="icon-warning" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"></path>
                    <line x1="12" y1="9" x2="12" y2="13"></line>
                    <line x1="12" y1="17" x2="12.01" y2="17"></line>
                </svg>`
            }
        );
    },

    /**
     * Show info message
     */
    async showInfo(message, title = 'Information') {
        return await ModalBuilder.alert(
            'info-modal',
            title,
            message,
            {
                type: 'info',
                buttonText: 'OK',
                buttonClass: 'btn-primary',
                icon: `<svg class="icon-info" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>`
            }
        );
    },

    /**
     * Show image viewer modal
     */
    showImageViewer(imageUrl, title = 'Billede') {
        const content = `
            <div class="image-viewer">
                <img src="${Validation.escapeHtml(imageUrl)}" alt="${Validation.escapeHtml(title)}" style="max-width: 100%; height: auto;">
            </div>
        `;

        return ModalBuilder.create('image-viewer')
            .title(title)
            .body(content)
            .size('large')
            .footer([
                { text: 'Luk', class: 'btn-secondary', action: 'close' }
            ])
            .show();
    },

    /**
     * Show loading modal
     */
    showLoading(message = 'Indlæser...') {
        const content = `
            <div class="modal-loading">
                <div class="spinner"></div>
                <p>${Validation.escapeHtml(message)}</p>
            </div>
        `;

        return ModalBuilder.create('loading-modal')
            .body(content)
            .size('small')
            .closeButton(false)
            .backdrop(false)
            .keyboard(false)
            .show();
    },

    /**
     * Hide loading modal
     */
    hideLoading() {
        Modal.close('loading-modal');
    },

    /**
     * Show progress modal
     */
    showProgress(title, items, processor) {
        return new Promise((resolve) => {
            let processed = 0;
            const total = items.length;
            const results = [];

            const content = `
                <div class="modal-progress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
                    </div>
                    <p id="progress-text">0 / ${total}</p>
                </div>
            `;

            ModalBuilder.create('progress-modal')
                .title(title)
                .body(content)
                .size('medium')
                .closeButton(false)
                .backdrop(false)
                .keyboard(false)
                .onShow(async () => {
                    const progressFill = document.getElementById('progress-fill');
                    const progressText = document.getElementById('progress-text');

                    for (const item of items) {
                        try {
                            const result = await processor(item);
                            results.push({ success: true, item, result });
                        } catch (error) {
                            results.push({ success: false, item, error: error.message });
                        }

                        processed++;
                        const percent = Math.round((processed / total) * 100);
                        progressFill.style.width = `${percent}%`;
                        progressText.textContent = `${processed} / ${total}`;
                    }

                    // Wait a bit to show completion
                    setTimeout(() => {
                        Modal.close('progress-modal');
                        resolve(results);
                    }, 500);
                })
                .show();
        });
    },

    /**
     * Show choice modal
     */
    async showChoice(title, message, choices) {
        return new Promise((resolve) => {
            const content = `
                <div class="modal-choice">
                    <p>${Validation.escapeHtml(message)}</p>
                </div>
            `;

            const footer = choices.map((choice, index) => ({
                text: choice.label,
                class: choice.class || 'btn-secondary',
                action: `choice-${index}`,
                onClick: () => {
                    Modal.close();
                    resolve(choice.value);
                }
            }));

            ModalBuilder.create('choice-modal')
                .title(title)
                .body(content)
                .footer(footer)
                .size('small')
                .onClose(() => resolve(null))
                .show();
        });
    }
};

// Export globally
window.ModalHelpers = ModalHelpers;
