/**
 * BaseModule - Fælles funktionalitet for alle moduler
 * Reducerer dubleret kode i templates
 */

class BaseModule {
    constructor(moduleName, config = {}) {
        this.moduleName = moduleName;
        this.config = {
            titleSingular: config.titleSingular || moduleName,
            titlePlural: config.titlePlural || moduleName,
            modalSize: config.modalSize || 'medium',
            ...config
        };
        this.search = '';
        this.page = 1;
    }

    /**
     * Åbn create modal med form
     */
    openCreate() {
        if (!this.getFormFields) {
            console.error(`${this.moduleName}: getFormFields() method not implemented`);
            return;
        }

        const formId = `${this.moduleName}-form-create`;
        const formHtml = this._buildFormHtml({}, null, formId);

        ModalBuilder.form(
            `${this.moduleName}-create`,
            `Opret ${this.config.titleSingular}`,
            formHtml,
            formId,
            {
                size: this.config.modalSize,
                submitText: 'Opret',
                cancelText: 'Annuller',
                onSubmit: (e) => this._handleFormSubmit(formId, null),
                onShow: () => {
                    // Focus first input
                    const firstInput = document.querySelector(`#${formId} input, #${formId} textarea, #${formId} select`);
                    if (firstInput) firstInput.focus();
                }
            }
        );
    }

    /**
     * Åbn edit modal med eksisterende data
     */
    async openEdit(id) {
        try {
            App.showLoading();
            const r = await API.get('/', {
                module: this.moduleName,
                action: 'get',
                id
            });
            App.hideLoading();

            if (r.success) {
                const formId = `${this.moduleName}-form-edit-${id}`;
                const formHtml = this._buildFormHtml(r.data, id, formId);

                ModalBuilder.form(
                    `${this.moduleName}-edit-${id}`,
                    `Rediger ${this.config.titleSingular}`,
                    formHtml,
                    formId,
                    {
                        size: this.config.modalSize,
                        submitText: 'Gem',
                        cancelText: 'Annuller',
                        onSubmit: () => this._handleFormSubmit(formId, id),
                        onShow: () => {
                            // Focus first input
                            const firstInput = document.querySelector(`#${formId} input, #${formId} textarea, #${formId} select`);
                            if (firstInput) firstInput.focus();
                        }
                    }
                );
            } else {
                notify(r.error, { type: 'error' });
            }
        } catch (e) {
            App.hideLoading();
            notify('Fejl ved hentning', { type: 'error' });
            if (window.logError) window.logError(e);
        }
    }

    /**
     * Handle form submission (internal method)
     */
    async _handleFormSubmit(formId, id = null) {
        const form = document.getElementById(formId);
        if (!form) {
            console.error('Form not found:', formId);
            return;
        }

        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        const formData = new FormData(form);
        formData.append('module', this.moduleName);
        formData.append('action', id ? 'update' : 'create');
        if (id) formData.append('id', id);

        try {
            const r = await API.post('/', formData, true);

            if (r.success) {
                Modal.close();
                notify(r.message || 'Gemt', { type: 'success' });
                Router.reload();
            } else {
                this.showErrors(r.errors || ['Fejl ved lagring'], formId);
            }
        } catch (e) {
            notify('Lagringsfejl', { type: 'error' });
            if (window.logError) window.logError(e);
        }
    }

    /**
     * Submit form (create eller update) - Legacy support
     * @deprecated Use ModalBuilder instead
     */
    async submit(event, id = null) {
        event.preventDefault();
        await this._handleFormSubmit(event.target.id, id);
        return false;
    }

    /**
     * Bekræft og slet entitet
     */
    async confirmDelete(id, name = '') {
        // Use ModalHelpers if available, otherwise fallback
        const confirmed = typeof ModalHelpers !== 'undefined'
            ? await ModalHelpers.confirmDelete(name, this.config.titleSingular.toLowerCase())
            : await Modal.confirm(
                `Slet ${this.config.titleSingular.toLowerCase()} "${name}"?`,
                {
                    title: 'Bekræft sletning',
                    confirmText: 'Slet',
                    confirmClass: 'btn-danger'
                }
            );

        if (!confirmed) return;

        const formData = new FormData();
        formData.append('module', this.moduleName);
        formData.append('action', 'delete');
        formData.append('id', id);

        try {
            const r = await API.post('/', formData, true);

            if (r.success) {
                notify('Slettet', { type: 'success' });
                Router.reload();
            } else {
                notify(r.error, { type: 'error' });
            }
        } catch (e) {
            notify('Sletningsfejl', { type: 'error' });
            if (window.logError) window.logError(e);
        }
    }

    /**
     * Vis form errors
     */
    showErrors(errors, formId = null) {
        // Try to find error element in specific form first
        let errorEl = null;
        if (formId) {
            const form = document.getElementById(formId);
            if (form) {
                errorEl = form.querySelector('.form-errors');
            }
        }

        // Fallback to global error element
        if (!errorEl) {
            errorEl = document.getElementById('formErrors');
        }

        if (!errorEl) {
            // Create error element if it doesn't exist
            const form = formId ? document.getElementById(formId) : null;
            if (form) {
                errorEl = document.createElement('div');
                errorEl.className = 'form-errors';
                form.insertBefore(errorEl, form.firstChild);
            } else {
                console.error('Cannot display errors: error element not found');
                return;
            }
        }

        errorEl.innerHTML = `
            <div class="alert alert-error">
                ${Array.isArray(errors) ? errors.map(e => escapeHtml(e)).join('<br>') : escapeHtml(errors)}
            </div>
        `;
        errorEl.style.display = 'block';
    }

    /**
     * Søg
     */
    performSearch(event) {
        event.preventDefault();
        const searchInput = document.getElementById('searchInput');
        const searchTerm = searchInput ? searchInput.value : '';

        navigate(this.moduleName, searchTerm ? { search: searchTerm } : {});
        return false;
    }

    /**
     * Clear search
     */
    clearSearch() {
        navigate(this.moduleName);
    }

    /**
     * Load page
     */
    loadPage(page) {
        const params = { page };
        if (this.search) params.search = this.search;
        navigate(this.moduleName, params);
    }

    /**
     * Helper til form fields (til override)
     */
    getFormFields(data, id) {
        // Override denne i specific modules
        return '';
    }

    /**
     * Build form HTML (internal method)
     */
    _buildFormHtml(data, id, formId) {
        return `
            <form id="${formId}" class="modal-form">
                <div class="form-errors" style="display:none;"></div>
                ${this.getFormFields(data, id)}
            </form>
        `;
    }

    /**
     * Standard form template - Legacy support
     * @deprecated Use ModalBuilder instead
     */
    getForm(data, id = null) {
        return `
            <form onsubmit="return ${this.config.instanceName || this.moduleName}.submit(event, ${id || null});">
                <div class="modal-body">
                    <div id="formErrors"></div>
                    ${this.getFormFields(data, id)}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>
                    <button type="submit" class="btn btn-primary">${id ? 'Gem' : 'Opret'}</button>
                </div>
            </form>
        `;
    }
}

/**
 * Opret modul instance helper
 */
function createModule(moduleName, config = {}) {
    const module = new BaseModule(moduleName, {
        instanceName: `${moduleName}Module`,
        ...config
    });

    // Bind methods
    module.submit = module.submit.bind(module);
    module.performSearch = module.performSearch.bind(module);

    return module;
}

// Global tilgængelig
window.BaseModule = BaseModule;
window.createModule = createModule;
