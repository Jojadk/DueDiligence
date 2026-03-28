/**
 * Customer Module Logic
 */
const CustomerModule = {
    init: function () {
        this.setupEventDelegation();
    },

    setupEventDelegation: function () {
        document.addEventListener('click', (e) => {
            // Tabs
            if (e.target.closest('[data-tab-target]')) {
                const tab = e.target.closest('[data-tab-target]');
                const targetId = tab.getAttribute('data-tab-target');
                this.switchTab(tab, targetId);
                return;
            }

            // New Customer (from index)
            if (e.target.closest('#btn-new-customer')) {
                this.openCustomerModal();
                return;
            }

            // Edit Customer (from index)
            if (e.target.closest('.btn-edit-customer')) {
                const id = e.target.closest('.btn-edit-customer').getAttribute('data-customer-id');
                this.openEditCustomerModal(id);
                return;
            }

            // Delete Customer (from form)
            if (e.target.closest('#btn-delete-customer')) {
                const btn = e.target.closest('#btn-delete-customer');
                const id = btn.getAttribute('data-customer-id');
                const name = btn.getAttribute('data-customer-name');
                this.deleteCustomer(id, name);
                return;
            }

            // Edit Project Link (from projects tab)
            if (e.target.closest('.btn-edit-project')) {
                const id = e.target.closest('.btn-edit-project').getAttribute('data-project-id');
                window.open('?module=Project&action=edit&id=' + id, '_blank');
                return;
            }
        });

        document.addEventListener('submit', (e) => {
            if (e.target.id === 'customer-form') {
                e.preventDefault();
                this.saveCustomer(e);
            }
        });
    },

    // Use shared utility or local fallback
    getContainer: function (el) {
        return el.closest('.customer-form-container') || el.closest('.card-body') || document;
    },

    switchTab: function (el, targetId) {
        const container = this.getContainer(el);
        container.querySelectorAll('.tab-item').forEach(t => {
            t.classList.remove('active');
            t.style.borderBottom = '2px solid transparent';
            t.style.fontWeight = 'normal';
        });
        el.classList.add('active');
        el.style.borderBottom = '2px solid #007bff';
        el.style.fontWeight = 'bold';

        container.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
        const target = container.querySelector('#' + targetId);
        if (target) target.style.display = 'block';
    },

    openCustomerModal: function () {
        const template = document.getElementById('customer-template');
        if (!template) return;
        const content = template.innerHTML;

        const title = (typeof i18n !== 'undefined' && i18n.t) ? i18n.t('common.create_customer') : 'New Customer';

        wm.createWindow({
            id: 'win-create-customer',
            title: title,
            content: content,
            width: 600,
            height: 600
        });
    },

    openEditCustomerModal: function (id) {
        API.customers.get(id)
            .then(res => {
                const html = res; // Assuming response is HTML
                wm.createWindow({
                    id: 'win-edit-customer-' + id,
                    title: (i18n.t('common.edit') || 'Edit') + ' Customer #' + id,
                    content: html,
                    width: 600,
                    height: 600
                });
            });
    },

    saveCustomer: function (e) {
        // e is event
        const form = e.target;
        if (form.name.value.trim().length < 2) {
            App.toast(i18n.t('error.validation') || 'Navn er påkrævet (min 2 tegn).', 'warning');
            return;
        }

        const id = form.querySelector('[name="id"]')?.value;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData);

        const apiCall = id ? API.customers.update(id, data) : API.customers.create(data);

        apiCall.then(res => {
            if (res.status === 'success') {
                App.toast(i18n.t('common.success') || 'Kunde gemt!', 'success');
                if (window.opener) window.opener.location.reload();
                window.location.href = '?module=Customer&action=index';
            } else {
                App.toast(res.message || i18n.t('error.system'), 'error');
            }
        })
            .catch(err => App.toast('Systemfejl: ' + err, 'error'));
    },

    deleteCustomer: function (id, name) {
        const content = `
            <div style="padding:15px;">
                <div class="alert alert-danger" style="background:#f8d7da; color:#721c24; padding:10px; border-radius:4px; margin-bottom:15px;">
                    <strong>${i18n.t('common.warning') || 'Advarsel'}:</strong> Dette sletter også tilknyttede data.
                </div>
                <p style="margin-bottom:10px;">Skriv kundenavn <strong>${name}</strong> for at slette:</p>
                <input type="text" id="del-cust-input" class="form-input w-full" style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; margin-bottom:15px;">
            </div>
         `;

        App.modal(i18n.t('common.delete') + ' Kunde?', content, [
            { text: 'Slet', onClick: () => this.confirmDelete(id), type: 'danger' }
        ]);

        // Disable button initially and setup input listenter
        setTimeout(() => {
            const btns = document.querySelectorAll('#dynamic-modal .modal-footer button');
            if (btns.length > 0) {
                // The last one is usually the action button
                const delBtn = btns[btns.length - 1];
                delBtn.disabled = true;
                delBtn.style.opacity = '0.5';
                delBtn.id = 'btn-del-cust';
            }
            const input = document.getElementById('del-cust-input');
            if (input) {
                input.focus();
                input.addEventListener('keyup', () => this.checkDeleteInput(input, name));
            }
        }, 50);
    },

    checkDeleteInput: function (input, targetName) {
        const btn = document.getElementById('btn-del-cust');
        if (!btn) return;
        if (input.value === targetName) {
            btn.disabled = false;
            btn.style.opacity = '1';
        } else {
            btn.disabled = true;
            btn.style.opacity = '0.5';
        }
    },

    confirmDelete: function (id) {
        API.customers.delete(id)
            .then(data => {
                if (data.status === 'success') {
                    App.toast(i18n.t('common.success') || 'Kunde slettet', 'success');
                    App.closeModal();
                    window.location.href = '?module=Customer&action=index';
                } else {
                    App.toast('Fejl: ' + data.message, 'error');
                }
            })
            .catch(e => App.toast('Fejl: ' + e, 'error'));
    }
};
