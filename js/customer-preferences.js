/**
 * Customer Preferences System
 * - Custom field creation and management
 * - Field placement configuration
 * - Logo upload with drag & drop
 * - Customer-specific settings
 */

class CustomerPreferences {
    constructor() {
        this.customFields = new Map();
        this.fieldTypes = [
            { value: 'text', label: 'Tekst', icon: 'align-left' },
            { value: 'number', label: 'Nummer', icon: 'hash' },
            { value: 'email', label: 'Email', icon: 'mail' },
            { value: 'phone', label: 'Telefon', icon: 'phone' },
            { value: 'date', label: 'Dato', icon: 'calendar' },
            { value: 'textarea', label: 'Tekst område', icon: 'file-text' },
            { value: 'select', label: 'Dropdown', icon: 'list' },
            { value: 'checkbox', label: 'Afkrydsning', icon: 'check-square' },
            { value: 'file', label: 'Fil upload', icon: 'upload' }
        ];
    }

    /**
     * Open customer preferences modal
     */
    openPreferencesModal(customerId, customerName = '') {
        if (!customerId) {
            if (window.NotificationSystem) {
                window.NotificationSystem.error('Kunde ID mangler');
            }
            return;
        }

        this.loadCustomerPreferences(customerId).then(preferences => {
            this.showPreferencesModal(customerId, customerName, preferences);
        });
    }

    /**
     * Load customer preferences from API
     */
    async loadCustomerPreferences(customerId) {
        try {
            const response = await fetch(window.APP_CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    module: 'customer',
                    action: 'get_preferences',
                    customer_id: customerId,
                    [window.CSRF_TOKEN_NAME]: window.CSRF_TOKEN
                })
            });

            const result = await response.json();
            if (result.success) {
                return result.data || { custom_fields: [], logo: null, settings: {} };
            }
            return { custom_fields: [], logo: null, settings: {} };
        } catch (error) {
            if (window.logError) {
                window.logError(error, { method: 'loadCustomerPreferences', customerId });
            }
            return { custom_fields: [], logo: null, settings: {} };
        }
    }

    /**
     * Show preferences modal
     */
    showPreferencesModal(customerId, customerName, preferences) {
        const { custom_fields = [], logo = null, settings = {} } = preferences;

        const modalContent = `
            <div class="modal-body">
                <div class="customer-prefs-tabs">
                    <button type="button" class="customer-prefs-tab active" data-tab="logo">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        Logo
                    </button>
                    <button type="button" class="customer-prefs-tab" data-tab="fields">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="8" y1="6" x2="21" y2="6"></line>
                            <line x1="8" y1="12" x2="21" y2="12"></line>
                            <line x1="8" y1="18" x2="21" y2="18"></line>
                            <line x1="3" y1="6" x2="3.01" y2="6"></line>
                            <line x1="3" y1="12" x2="3.01" y2="12"></line>
                            <line x1="3" y1="18" x2="3.01" y2="18"></line>
                        </svg>
                        Brugerdefinerede felter
                    </button>
                    <button type="button" class="customer-prefs-tab" data-tab="settings">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M12 1v6m0 6v6M5.64 5.64l4.24 4.24m4.24 4.24l4.24 4.24M1 12h6m6 0h6M5.64 18.36l4.24-4.24m4.24-4.24l4.24-4.24"></path>
                        </svg>
                        Indstillinger
                    </button>
                </div>

                <div class="customer-prefs-content">
                    <!-- Logo Tab -->
                    <div class="customer-prefs-panel active" data-panel="logo">
                        <h3>Kunde logo</h3>
                        <p class="text-muted">Upload et logo for denne kunde. Logoet vil blive brugt i rapporter og dokumenter.</p>

                        <div id="customerLogoUpload"></div>

                        ${logo ? `
                        <div class="current-logo">
                            <h4>Nuværende logo</h4>
                            <div class="logo-preview">
                                <img src="${escapeHtml(logo)}" alt="Customer logo">
                                <button type="button" class="btn btn-sm btn-danger" onclick="CustomerPreferences.deleteLogo(${customerId})">
                                    Slet logo
                                </button>
                            </div>
                        </div>
                        ` : ''}
                    </div>

                    <!-- Custom Fields Tab -->
                    <div class="customer-prefs-panel" data-panel="fields">
                        <div class="custom-fields-header">
                            <h3>Brugerdefinerede felter</h3>
                            <button type="button" class="btn btn-sm btn-primary" id="addCustomField">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Tilføj felt
                            </button>
                        </div>
                        <p class="text-muted">Opret brugerdefinerede felter til denne kunde. Felterne vil være tilgængelige i projekter og rapporter.</p>

                        <div id="customFieldsList" class="custom-fields-list">
                            ${this.renderCustomFields(custom_fields)}
                        </div>
                    </div>

                    <!-- Settings Tab -->
                    <div class="customer-prefs-panel" data-panel="settings">
                        <h3>Kunde indstillinger</h3>

                        <div class="form-group">
                            <label>Standard valuta</label>
                            <select class="form-control" id="customerCurrency">
                                <option value="DKK" ${settings.currency === 'DKK' ? 'selected' : ''}>DKK - Danske kroner</option>
                                <option value="EUR" ${settings.currency === 'EUR' ? 'selected' : ''}>EUR - Euro</option>
                                <option value="USD" ${settings.currency === 'USD' ? 'selected' : ''}>USD - US Dollar</option>
                                <option value="GBP" ${settings.currency === 'GBP' ? 'selected' : ''}>GBP - Britiske pund</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Standard sprog</label>
                            <select class="form-control" id="customerLanguage">
                                <option value="da" ${settings.language === 'da' ? 'selected' : ''}>Dansk</option>
                                <option value="en" ${settings.language === 'en' ? 'selected' : ''}>English</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Rapport format</label>
                            <select class="form-control" id="customerReportFormat">
                                <option value="pdf" ${settings.report_format === 'pdf' ? 'selected' : ''}>PDF</option>
                                <option value="docx" ${settings.report_format === 'docx' ? 'selected' : ''}>Word (DOCX)</option>
                                <option value="xlsx" ${settings.report_format === 'xlsx' ? 'selected' : ''}>Excel (XLSX)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="customerAutoInvoice" ${settings.auto_invoice ? 'checked' : ''}>
                                <span>Automatisk fakturering</span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="checkbox-label">
                                <input type="checkbox" id="customerEmailNotifications" ${settings.email_notifications ? 'checked' : ''}>
                                <span>Email notifikationer</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>
                <button type="button" class="btn btn-primary" id="saveCustomerPreferences">Gem præferencer</button>
            </div>
        `;

        if (typeof Modal !== 'undefined') {
            Modal.open(modalContent, {
                size: 'large',
                title: `Præferencer for ${escapeHtml(customerName)}`,
                closeButton: true,
                backdrop: true,
                keyboard: true
            });

            this.attachPreferencesEventListeners(customerId, custom_fields);
        }
    }

    /**
     * Render custom fields list
     */
    renderCustomFields(fields) {
        if (!fields || fields.length === 0) {
            return '<p class="text-muted text-center">Ingen brugerdefinerede felter endnu</p>';
        }

        return fields.map((field, index) => `
            <div class="custom-field-item" data-field-id="${field.id || index}">
                <div class="field-drag-handle">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </div>
                <div class="field-info">
                    <strong>${escapeHtml(field.label)}</strong>
                    <span class="field-type">${this.getFieldTypeName(field.type)}</span>
                    ${field.required ? '<span class="badge badge-required">Påkrævet</span>' : ''}
                </div>
                <div class="field-actions">
                    <button type="button" class="btn-icon" onclick="CustomerPreferences.editField(${field.id || index})">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                    <button type="button" class="btn-icon btn-icon-danger" onclick="CustomerPreferences.deleteField(${field.id || index})">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </div>
        `).join('');
    }

    /**
     * Get field type name
     */
    getFieldTypeName(type) {
        const fieldType = this.fieldTypes.find(t => t.value === type);
        return fieldType ? fieldType.label : type;
    }

    /**
     * Attach event listeners
     */
    attachPreferencesEventListeners(customerId, customFields) {
        const tabs = document.querySelectorAll('.customer-prefs-tab');
        const panels = document.querySelectorAll('.customer-prefs-panel');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                const targetPanel = tab.dataset.tab;

                tabs.forEach(t => t.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));

                tab.classList.add('active');
                const panel = document.querySelector(`[data-panel="${targetPanel}"]`);
                if (panel) {
                    panel.classList.add('active');
                }
            });
        });

        const logoUploadContainer = document.getElementById('customerLogoUpload');
        if (logoUploadContainer && window.UploadSystem) {
            window.UploadSystem.createUploadZone('customerLogoUpload', {
                uploadUrl: '/api.php?module=customer&action=upload_logo',
                targetFolder: `customer_${customerId}/logo`,
                customerId: customerId,
                allowedFileTypes: ['image'],
                multiple: false,
                maxFiles: 1,
                onComplete: (result) => {
                    if (window.NotificationSystem) {
                        window.NotificationSystem.success('Logo uploadet');
                    }
                    setTimeout(() => {
                        if (typeof Modal !== 'undefined') {
                            Modal.close();
                        }
                    }, 1000);
                }
            });
        }

        const addFieldBtn = document.getElementById('addCustomField');
        if (addFieldBtn) {
            addFieldBtn.addEventListener('click', () => {
                this.showFieldEditor(customerId, null, customFields);
            });
        }

        const saveBtn = document.getElementById('saveCustomerPreferences');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.savePreferences(customerId);
            });
        }
    }

    /**
     * Show field editor
     */
    showFieldEditor(customerId, fieldId, existingFields) {
        const field = fieldId !== null ? existingFields[fieldId] : null;

        const editorContent = `
            <div class="modal-body">
                <div class="form-group">
                    <label>Feltnavn</label>
                    <input type="text" class="form-control" id="fieldLabel" value="${escapeHtml(field?.label || '')}" placeholder="F.eks. CVR nummer">
                </div>

                <div class="form-group">
                    <label>Felttype</label>
                    <select class="form-control" id="fieldType">
                        ${this.fieldTypes.map(type => `
                            <option value="${type.value}" ${field?.type === type.value ? 'selected' : ''}>
                                ${type.label}
                            </option>
                        `).join('')}
                    </select>
                </div>

                <div class="form-group" id="fieldOptionsGroup" style="display: none;">
                    <label>Valgmuligheder (en per linje)</label>
                    <textarea class="form-control" id="fieldOptions" rows="4" placeholder="Option 1\nOption 2\nOption 3">${field?.options ? field.options.join('\n') : ''}</textarea>
                </div>

                <div class="form-group">
                    <label>Placeholder</label>
                    <input type="text" class="form-control" id="fieldPlaceholder" value="${escapeHtml(field?.placeholder || '')}" placeholder="F.eks. 12345678">
                </div>

                <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" id="fieldRequired" ${field?.required ? 'checked' : ''}>
                        <span>Påkrævet felt</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>
                <button type="button" class="btn btn-primary" id="saveField">Gem felt</button>
            </div>
        `;

        if (typeof Modal !== 'undefined') {
            Modal.open(editorContent, {
                size: 'medium',
                title: fieldId !== null ? 'Rediger felt' : 'Nyt brugerdefineret felt',
                closeButton: true
            });

            const typeSelect = document.getElementById('fieldType');
            const optionsGroup = document.getElementById('fieldOptionsGroup');

            if (typeSelect && optionsGroup) {
                const updateOptionsVisibility = () => {
                    optionsGroup.style.display = typeSelect.value === 'select' ? 'block' : 'none';
                };
                updateOptionsVisibility();
                typeSelect.addEventListener('change', updateOptionsVisibility);
            }

            const saveBtn = document.getElementById('saveField');
            if (saveBtn) {
                saveBtn.addEventListener('click', () => {
                    const newField = {
                        id: field?.id || Date.now(),
                        label: document.getElementById('fieldLabel')?.value || '',
                        type: document.getElementById('fieldType')?.value || 'text',
                        placeholder: document.getElementById('fieldPlaceholder')?.value || '',
                        required: document.getElementById('fieldRequired')?.checked || false,
                        options: []
                    };

                    if (newField.type === 'select') {
                        const optionsText = document.getElementById('fieldOptions')?.value || '';
                        newField.options = optionsText.split('\n').filter(o => o.trim());
                    }

                    if (!newField.label) {
                        if (window.NotificationSystem) {
                            window.NotificationSystem.warning('Feltnavn er påkrævet');
                        }
                        return;
                    }

                    if (fieldId !== null) {
                        existingFields[fieldId] = newField;
                    } else {
                        existingFields.push(newField);
                    }

                    const fieldsList = document.getElementById('customFieldsList');
                    if (fieldsList) {
                        fieldsList.innerHTML = this.renderCustomFields(existingFields);
                    }

                    if (typeof Modal !== 'undefined') {
                        Modal.close();
                    }
                });
            }
        }
    }

    /**
     * Save customer preferences
     */
    async savePreferences(customerId) {
        const currency = document.getElementById('customerCurrency')?.value || 'DKK';
        const language = document.getElementById('customerLanguage')?.value || 'da';
        const reportFormat = document.getElementById('customerReportFormat')?.value || 'pdf';
        const autoInvoice = document.getElementById('customerAutoInvoice')?.checked || false;
        const emailNotifications = document.getElementById('customerEmailNotifications')?.checked || false;

        const customFieldsList = document.getElementById('customFieldsList');
        const customFields = [];
        if (customFieldsList) {
            const fieldItems = customFieldsList.querySelectorAll('.custom-field-item');
            fieldItems.forEach((item, index) => {
                const fieldId = item.dataset.fieldId;
                customFields.push({ id: fieldId, order: index });
            });
        }

        try {
            const response = await fetch(window.APP_CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    module: 'customer',
                    action: 'save_preferences',
                    customer_id: customerId,
                    preferences: {
                        custom_fields: customFields,
                        settings: {
                            currency,
                            language,
                            report_format: reportFormat,
                            auto_invoice: autoInvoice,
                            email_notifications: emailNotifications
                        }
                    },
                    [window.CSRF_TOKEN_NAME]: window.CSRF_TOKEN
                })
            });

            const result = await response.json();
            if (result.success) {
                if (window.NotificationSystem) {
                    window.NotificationSystem.success('Præferencer gemt');
                }

                if (typeof Modal !== 'undefined') {
                    Modal.close();
                }
            } else {
                if (window.NotificationSystem) {
                    window.NotificationSystem.error(result.error || 'Kunne ikke gemme præferencer');
                }
            }
        } catch (error) {
            if (window.logError) {
                window.logError(error, { method: 'savePreferences', customerId });
            }
            if (window.NotificationSystem) {
                window.NotificationSystem.error('Fejl ved gemning af præferencer');
            }
        }
    }
}

window.CustomerPreferences = new CustomerPreferences();
