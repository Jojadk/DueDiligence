/**
 * DueDiligence App Features - Konsolideret
 * Indeholder: Event Delegation, User Settings, Upload System, Customer Preferences
 */

// ============================================================================
// EVENT DELEGATION SYSTEM
// ============================================================================

class EventDelegation {
    constructor() {
        this.delegatedHandlers = new Map();
        this.handlerId = 0;
        this.init();
    }

    init() {
        this.setupGlobalDelegations();
    }

    setupGlobalDelegations() {
        // Delegate data-action clicks
        this.delegate(document.body, 'click', '[data-action]', (e, target) => {
            const action = target.dataset.action;
            const handler = window[action];
            if (typeof handler === 'function') {
                e.preventDefault();
                handler(target);
            }
        });

        // Delegate data-toggle clicks
        this.delegate(document.body, 'click', '[data-toggle]', (e, target) => {
            e.preventDefault();
            const toggleTarget = target.dataset.toggle;
            const element = document.getElementById(toggleTarget);
            if (element) element.classList.toggle('active');
        });

        // Delegate data-dismiss clicks
        this.delegate(document.body, 'click', '[data-dismiss]', (e, target) => {
            e.preventDefault();
            const dismissTarget = target.dataset.dismiss;
            if (dismissTarget === 'modal' && typeof Modal !== 'undefined') {
                Modal.close();
            } else if (dismissTarget === 'notification') {
                const notification = target.closest('.notification');
                if (notification && notification.parentNode) notification.remove();
            }
        });

        // Delegate form submissions
        this.delegate(document.body, 'submit', 'form[data-ajax-form]', (e, target) => {
            e.preventDefault();
            this.handleAjaxForm(target);
        });

        this.setupSortableDelegate();
    }

    delegate(parent, eventType, selector, handler) {
        if (!parent) return null;

        const handlerId = ++this.handlerId;
        const delegatedHandler = (e) => {
            const target = e.target.closest(selector);
            if (target && parent.contains(target)) handler(e, target);
        };

        parent.addEventListener(eventType, delegatedHandler);
        this.delegatedHandlers.set(handlerId, { parent, eventType, handler: delegatedHandler });
        return handlerId;
    }

    undelegate(handlerId) {
        const delegated = this.delegatedHandlers.get(handlerId);
        if (delegated) {
            delegated.parent.removeEventListener(delegated.eventType, delegated.handler);
            this.delegatedHandlers.delete(handlerId);
        }
    }

    async handleAjaxForm(form) {
        if (!form) return;

        const formData = new FormData(form);
        const url = form.action || window.APP_CONFIG.apiUrl;
        const method = form.method || 'POST';

        if (!formData.has(window.CSRF_TOKEN_NAME)) {
            formData.append(window.CSRF_TOKEN_NAME, window.CSRF_TOKEN);
        }

        const submitBtn = form.querySelector('[type="submit"]');
        const originalText = submitBtn ? submitBtn.textContent : '';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Sender...';
        }

        try {
            const response = await fetch(url, { method, body: formData });
            const result = await response.json();

            if (result.success) {
                if (window.NotificationSystem) {
                    window.NotificationSystem.success(result.message || 'Gemt');
                }
                if (form.dataset.resetOnSuccess !== 'false') form.reset();
                if (form.closest('.modal') && typeof Modal !== 'undefined') {
                    setTimeout(() => Modal.close(), 500);
                }
            } else {
                if (window.NotificationSystem) {
                    window.NotificationSystem.error(result.error || 'Der opstod en fejl');
                }
            }
        } catch (error) {
            if (window.logError) window.logError(error);
            if (window.NotificationSystem) {
                window.NotificationSystem.error('Netværksfejl');
            }
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            }
        }
    }

    setupSortableDelegate() {
        let draggedElement = null;

        this.delegate(document.body, 'dragstart', '[draggable="true"]', (e, target) => {
            draggedElement = target;
            target.classList.add('dragging');
        });

        this.delegate(document.body, 'dragend', '[draggable="true"]', (e, target) => {
            target.classList.remove('dragging');
            draggedElement = null;
        });

        this.delegate(document.body, 'drop', '.sortable-list', (e, target) => {
            e.preventDefault();
            const items = Array.from(target.children);
            const order = items.map((item, index) => ({
                id: item.dataset.id || item.dataset.fieldId,
                order: index
            }));
            const event = new CustomEvent('listReordered', { detail: { order, list: target } });
            target.dispatchEvent(event);
        });
    }

    cleanup() {
        this.delegatedHandlers.forEach((delegated, handlerId) => {
            this.undelegate(handlerId);
        });
    }
}

// ============================================================================
// USER SETTINGS SYSTEM
// ============================================================================

class UserSettings {
    constructor() {
        this.settings = {
            theme: 'light',
            themeColor: '#3b82f6',
            fontSize: 'medium',
            language: 'da',
            notifications: { enabled: true, sound: false }
        };
        this.init();
    }

    init() {
        this.loadSettings();
        this.applyTheme();
        this.setupEventListeners();
    }

    setupEventListeners() {
        const userMenuBtn = document.getElementById('userMenuBtn');
        const userDropdown = document.getElementById('userDropdown');

        if (userMenuBtn && userDropdown) {
            userMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                userDropdown.classList.toggle('show');
            });

            document.addEventListener('click', (e) => {
                if (!userMenuBtn.contains(e.target) && !userDropdown.contains(e.target)) {
                    userDropdown.classList.remove('show');
                }
            });
        }
    }

    openSettingsModal() {
        const modalContent = `
            <div class="modal-body">
                <h3>Tema</h3>
                <div class="theme-selector">
                    <label class="theme-option">
                        <input type="radio" name="theme" value="light" checked>
                        <div class="theme-preview theme-light">
                            <span>Lys</span>
                        </div>
                    </label>
                    <label class="theme-option">
                        <input type="radio" name="theme" value="dark">
                        <div class="theme-preview theme-dark">
                            <span>Mørk</span>
                        </div>
                    </label>
                </div>
                <h3 style="margin-top: 24px;">Temafarve</h3>
                <div class="preset-colors">
                    <button type="button" class="color-preset active" data-color="#3b82f6" style="background: #3b82f6;"></button>
                    <button type="button" class="color-preset" data-color="#10b981" style="background: #10b981;"></button>
                    <button type="button" class="color-preset" data-color="#f59e0b" style="background: #f59e0b;"></button>
                    <button type="button" class="color-preset" data-color="#ef4444" style="background: #ef4444;"></button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>
                <button type="button" class="btn btn-primary" id="saveSettings">Gem</button>
            </div>
        `;

        if (typeof Modal !== 'undefined') {
            Modal.open(modalContent, { size: 'medium', title: 'Indstillinger', closeButton: true });
            this.attachSettingsEventListeners();
        }
    }

    attachSettingsEventListeners() {
        const colorPresets = document.querySelectorAll('.color-preset');
        colorPresets.forEach(btn => {
            btn.addEventListener('click', () => {
                this.settings.themeColor = btn.dataset.color;
                this.applyThemeColor(btn.dataset.color);
                colorPresets.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });
        });

        const saveBtn = document.getElementById('saveSettings');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.saveSettings();
                if (typeof Modal !== 'undefined') Modal.close();
                if (window.NotificationSystem) {
                    window.NotificationSystem.success('Indstillinger gemt');
                }
            });
        }
    }

    applyTheme() {
        const body = document.body;
        const isDark = this.settings.theme === 'dark';
        body.classList.toggle('theme-dark', isDark);
        this.applyThemeColor(this.settings.themeColor);
    }

    applyThemeColor(color) {
        document.documentElement.style.setProperty('--color-primary', color);
    }

    loadSettings() {
        try {
            const saved = localStorage.getItem('userSettings');
            if (saved) {
                this.settings = { ...this.settings, ...JSON.parse(saved) };
            }
        } catch (error) {
            if (window.logError) window.logError(error);
        }
    }

    saveSettings() {
        try {
            localStorage.setItem('userSettings', JSON.stringify(this.settings));
        } catch (error) {
            if (window.logError) window.logError(error);
        }
    }
}

// ============================================================================
// UPLOAD SYSTEM
// ============================================================================

class UploadSystem {
    constructor() {
        this.uploads = new Map();
        this.uploadId = 0;
        this.maxFileSize = 50 * 1024 * 1024;
        this.allowedTypes = {
            image: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            document: ['application/pdf', 'application/msword'],
            archive: ['application/zip']
        };
    }

    createUploadZone(containerId, options = {}) {
        const {
            uploadUrl = '/api.php?module=upload&action=upload',
            targetFolder = 'uploads',
            allowedFileTypes = ['image'],
            multiple = true,
            onComplete = null
        } = options;

        const container = document.getElementById(containerId);
        if (!container) return null;

        const zoneId = `upload-zone-${++this.uploadId}`;
        const inputId = `upload-input-${this.uploadId}`;
        const previewId = `upload-preview-${this.uploadId}`;

        container.innerHTML = `
            <div class="upload-zone" id="${zoneId}">
                <h3>Upload filer</h3>
                <p>Træk og slip filer her, eller klik for at vælge</p>
                <input type="file" id="${inputId}" style="display: none;" ${multiple ? 'multiple' : ''}>
            </div>
            <div class="upload-preview" id="${previewId}"></div>
        `;

        const zone = document.getElementById(zoneId);
        const input = document.getElementById(inputId);

        if (zone && input) {
            zone.addEventListener('click', () => input.click());

            input.addEventListener('change', (e) => {
                if (e.target.files) {
                    this.handleFiles(Array.from(e.target.files), options);
                }
            });

            zone.addEventListener('dragover', (e) => {
                e.preventDefault();
                zone.classList.add('drag-over');
            });

            zone.addEventListener('drop', (e) => {
                e.preventDefault();
                zone.classList.remove('drag-over');
                if (e.dataTransfer.files) {
                    this.handleFiles(Array.from(e.dataTransfer.files), options);
                }
            });
        }

        return { zoneId, inputId, previewId };
    }

    handleFiles(files, options) {
        files.forEach(file => {
            this.uploadFile(file, options);
        });
    }

    uploadFile(file, options) {
        const { uploadUrl, onComplete } = options;
        const formData = new FormData();
        formData.append('file', file);
        formData.append(window.CSRF_TOKEN_NAME, window.CSRF_TOKEN);

        fetch(uploadUrl, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                if (window.NotificationSystem) {
                    window.NotificationSystem.success(`${file.name} uploadet`);
                }
                if (onComplete) onComplete(result);
            }
        })
        .catch(error => {
            if (window.logError) window.logError(error);
        });
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }
}

// ============================================================================
// CUSTOMER PREFERENCES
// ============================================================================

class CustomerPreferences {
    openPreferencesModal(customerId, customerName = '') {
        if (!customerId) {
            if (window.NotificationSystem) {
                window.NotificationSystem.error('Kunde ID mangler');
            }
            return;
        }

        const modalContent = `
            <div class="modal-body">
                <h3>Logo</h3>
                <div id="customerLogoUpload"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>
                <button type="button" class="btn btn-primary">Gem</button>
            </div>
        `;

        if (typeof Modal !== 'undefined') {
            Modal.open(modalContent, {
                size: 'medium',
                title: `Præferencer - ${escapeHtml(customerName)}`,
                closeButton: true
            });

            if (window.UploadSystem) {
                window.UploadSystem.createUploadZone('customerLogoUpload', {
                    allowedFileTypes: ['image'],
                    multiple: false
                });
            }
        }
    }
}

// ============================================================================
// GLOBAL INITIALIZATION
// ============================================================================

window.EventDelegation = new EventDelegation();
window.UserSettings = new UserSettings();
window.UploadSystem = new UploadSystem();
window.CustomerPreferences = new CustomerPreferences();

window.openSettings = () => window.UserSettings.openSettingsModal();

window.addEventListener('beforeunload', () => {
    if (window.EventDelegation) window.EventDelegation.cleanup();
});
