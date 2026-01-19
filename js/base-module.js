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
        if (!this.getForm) {
            console.error(`${this.moduleName}: getForm() method not implemented`);
            return;
        }

        Modal.open(this.getForm({}), {
            size: this.config.modalSize,
            title: `Opret ${this.config.titleSingular}`
        });
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
                Modal.open(this.getForm(r.data, id), {
                    size: this.config.modalSize,
                    title: `Rediger ${this.config.titleSingular}`
                });
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
     * Submit form (create eller update)
     */
    async submit(event, id = null) {
        event.preventDefault();

        const formData = new FormData(event.target);
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
                this.showErrors(r.errors || ['Fejl ved lagring']);
            }
        } catch (e) {
            notify('Lagringsfejl', { type: 'error' });
            if (window.logError) window.logError(e);
        }

        return false;
    }

    /**
     * Bekræft og slet entitet
     */
    async confirmDelete(id, name = '') {
        const confirmed = await Modal.confirm(
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
    showErrors(errors) {
        const errorEl = document.getElementById('formErrors');
        if (!errorEl) return;

        errorEl.innerHTML = `
            <div class="alert alert-error">
                ${errors.map(e => escapeHtml(e)).join('<br>')}
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
     * Standard form template
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
