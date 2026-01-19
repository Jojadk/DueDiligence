/**
 * User Settings System
 * - Theme preferences (light/dark/custom)
 * - User profile settings
 * - Persistent storage in localStorage
 */

class UserSettings {
    constructor() {
        this.settings = {
            theme: 'light',
            themeColor: '#3b82f6',
            fontSize: 'medium',
            language: 'da',
            notifications: {
                enabled: true,
                sound: false,
                desktop: false
            }
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

        if (!userMenuBtn || !userDropdown) {
            if (window.logError) {
                window.logError(new Error('User menu elements not found'));
            }
            return;
        }

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

    openSettingsModal() {
        const modalContent = `
            <div class="modal-body">
                <div class="settings-tabs">
                    <button type="button" class="settings-tab active" data-tab="appearance">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        Udseende
                    </button>
                    <button type="button" class="settings-tab" data-tab="notifications">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        Notifikationer
                    </button>
                    <button type="button" class="settings-tab" data-tab="account">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        Konto
                    </button>
                </div>

                <div class="settings-content">
                    <!-- Appearance Tab -->
                    <div class="settings-panel active" data-panel="appearance">
                        <h3>Tema</h3>
                        <div class="theme-selector">
                            <label class="theme-option">
                                <input type="radio" name="theme" value="light" ${this.settings.theme === 'light' ? 'checked' : ''}>
                                <div class="theme-preview theme-light">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle cx="12" cy="12" r="5"></circle>
                                        <line x1="12" y1="1" x2="12" y2="3"></line>
                                        <line x1="12" y1="21" x2="12" y2="23"></line>
                                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                                        <line x1="1" y1="12" x2="3" y2="12"></line>
                                        <line x1="21" y1="12" x2="23" y2="12"></line>
                                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                                    </svg>
                                    <span>Lys</span>
                                </div>
                            </label>
                            <label class="theme-option">
                                <input type="radio" name="theme" value="dark" ${this.settings.theme === 'dark' ? 'checked' : ''}>
                                <div class="theme-preview theme-dark">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                                    </svg>
                                    <span>Mørk</span>
                                </div>
                            </label>
                            <label class="theme-option">
                                <input type="radio" name="theme" value="auto" ${this.settings.theme === 'auto' ? 'checked' : ''}>
                                <div class="theme-preview theme-auto">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                        <line x1="8" y1="21" x2="16" y2="21"></line>
                                        <line x1="12" y1="17" x2="12" y2="21"></line>
                                    </svg>
                                    <span>Auto</span>
                                </div>
                            </label>
                        </div>

                        <h3 style="margin-top: 24px;">Temafarve</h3>
                        <div class="color-picker-group">
                            <div class="preset-colors">
                                <button type="button" class="color-preset" data-color="#3b82f6" style="background: #3b82f6;" title="Blå"></button>
                                <button type="button" class="color-preset" data-color="#10b981" style="background: #10b981;" title="Grøn"></button>
                                <button type="button" class="color-preset" data-color="#f59e0b" style="background: #f59e0b;" title="Orange"></button>
                                <button type="button" class="color-preset" data-color="#ef4444" style="background: #ef4444;" title="Rød"></button>
                                <button type="button" class="color-preset" data-color="#8b5cf6" style="background: #8b5cf6;" title="Lilla"></button>
                                <button type="button" class="color-preset" data-color="#ec4899" style="background: #ec4899;" title="Pink"></button>
                                <button type="button" class="color-preset" data-color="#14b8a6" style="background: #14b8a6;" title="Teal"></button>
                                <button type="button" class="color-preset" data-color="#f97316" style="background: #f97316;" title="Mandarin"></button>
                            </div>
                            <div class="custom-color-input">
                                <label for="customColor">Brugerdefineret farve:</label>
                                <input type="color" id="customColor" value="${this.settings.themeColor}">
                            </div>
                        </div>

                        <h3 style="margin-top: 24px;">Skriftstørrelse</h3>
                        <div class="font-size-selector">
                            <label>
                                <input type="radio" name="fontSize" value="small" ${this.settings.fontSize === 'small' ? 'checked' : ''}>
                                <span>Lille</span>
                            </label>
                            <label>
                                <input type="radio" name="fontSize" value="medium" ${this.settings.fontSize === 'medium' ? 'checked' : ''}>
                                <span>Medium</span>
                            </label>
                            <label>
                                <input type="radio" name="fontSize" value="large" ${this.settings.fontSize === 'large' ? 'checked' : ''}>
                                <span>Stor</span>
                            </label>
                        </div>
                    </div>

                    <!-- Notifications Tab -->
                    <div class="settings-panel" data-panel="notifications">
                        <h3>Notifikationsindstillinger</h3>
                        <div class="settings-group">
                            <label class="settings-checkbox">
                                <input type="checkbox" id="notifEnabled" ${this.settings.notifications.enabled ? 'checked' : ''}>
                                <span>Aktiver notifikationer</span>
                            </label>
                            <label class="settings-checkbox">
                                <input type="checkbox" id="notifSound" ${this.settings.notifications.sound ? 'checked' : ''}>
                                <span>Afspil lyd</span>
                            </label>
                            <label class="settings-checkbox">
                                <input type="checkbox" id="notifDesktop" ${this.settings.notifications.desktop ? 'checked' : ''}>
                                <span>Desktop notifikationer</span>
                            </label>
                        </div>
                    </div>

                    <!-- Account Tab -->
                    <div class="settings-panel" data-panel="account">
                        <h3>Kontoindstillinger</h3>
                        <div class="settings-group">
                            <div class="form-group">
                                <label>Navn</label>
                                <input type="text" class="form-control" id="userName" value="${escapeHtml(window.APP_CONFIG?.currentUser?.name || '')}">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" class="form-control" id="userEmail" value="${escapeHtml(window.APP_CONFIG?.currentUser?.email || '')}" readonly>
                            </div>
                            <div class="form-group">
                                <label>Sprog</label>
                                <select class="form-control" id="userLanguage">
                                    <option value="da" ${this.settings.language === 'da' ? 'selected' : ''}>Dansk</option>
                                    <option value="en" ${this.settings.language === 'en' ? 'selected' : ''}>English</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>
                <button type="button" class="btn btn-primary" id="saveSettings">Gem indstillinger</button>
            </div>
        `;

        if (typeof Modal !== 'undefined') {
            Modal.open(modalContent, {
                size: 'medium',
                title: 'Indstillinger',
                closeButton: true,
                backdrop: true,
                keyboard: true
            });

            this.attachSettingsEventListeners();
        }
    }

    attachSettingsEventListeners() {
        const tabs = document.querySelectorAll('.settings-tab');
        const panels = document.querySelectorAll('.settings-panel');

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

        const themeInputs = document.querySelectorAll('input[name="theme"]');
        themeInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                this.settings.theme = e.target.value;
                this.applyTheme();
            });
        });

        const colorPresets = document.querySelectorAll('.color-preset');
        colorPresets.forEach(btn => {
            btn.addEventListener('click', () => {
                const color = btn.dataset.color;
                this.settings.themeColor = color;
                this.applyThemeColor(color);

                const customColorInput = document.getElementById('customColor');
                if (customColorInput) {
                    customColorInput.value = color;
                }

                colorPresets.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
            });

            if (btn.dataset.color === this.settings.themeColor) {
                btn.classList.add('active');
            }
        });

        const customColorInput = document.getElementById('customColor');
        if (customColorInput) {
            customColorInput.addEventListener('change', (e) => {
                this.settings.themeColor = e.target.value;
                this.applyThemeColor(e.target.value);
                colorPresets.forEach(b => b.classList.remove('active'));
            });
        }

        const fontSizeInputs = document.querySelectorAll('input[name="fontSize"]');
        fontSizeInputs.forEach(input => {
            input.addEventListener('change', (e) => {
                this.settings.fontSize = e.target.value;
                this.applyFontSize();
            });
        });

        const saveBtn = document.getElementById('saveSettings');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => {
                this.saveSettings();

                const notifEnabled = document.getElementById('notifEnabled');
                const notifSound = document.getElementById('notifSound');
                const notifDesktop = document.getElementById('notifDesktop');
                const userName = document.getElementById('userName');
                const userLanguage = document.getElementById('userLanguage');

                if (notifEnabled) this.settings.notifications.enabled = notifEnabled.checked;
                if (notifSound) this.settings.notifications.sound = notifSound.checked;
                if (notifDesktop) this.settings.notifications.desktop = notifDesktop.checked;
                if (userLanguage) this.settings.language = userLanguage.value;

                if (userName && userName.value !== window.APP_CONFIG?.currentUser?.name) {
                    this.updateUserProfile({ name: userName.value });
                }

                this.saveSettings();

                if (typeof Modal !== 'undefined') {
                    Modal.close();
                }

                if (window.NotificationSystem) {
                    window.NotificationSystem.success('Indstillinger gemt');
                }
            });
        }
    }

    applyTheme() {
        const body = document.body;
        const isDark = this.settings.theme === 'dark' ||
                      (this.settings.theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches);

        body.classList.toggle('theme-dark', isDark);
        body.classList.toggle('theme-light', !isDark);

        this.applyThemeColor(this.settings.themeColor);
    }

    applyThemeColor(color) {
        document.documentElement.style.setProperty('--color-primary', color);

        const rgb = this.hexToRgb(color);
        if (rgb) {
            document.documentElement.style.setProperty('--color-primary-light', `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.1)`);
            document.documentElement.style.setProperty('--color-primary-dark', this.darkenColor(color, 20));
        }
    }

    applyFontSize() {
        const body = document.body;
        body.classList.remove('font-small', 'font-medium', 'font-large');
        body.classList.add(`font-${this.settings.fontSize}`);
    }

    hexToRgb(hex) {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        return result ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        } : null;
    }

    darkenColor(hex, percent) {
        const rgb = this.hexToRgb(hex);
        if (!rgb) return hex;

        const factor = 1 - (percent / 100);
        const r = Math.floor(rgb.r * factor);
        const g = Math.floor(rgb.g * factor);
        const b = Math.floor(rgb.b * factor);

        return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
    }

    loadSettings() {
        try {
            const saved = localStorage.getItem('userSettings');
            if (saved) {
                const parsed = JSON.parse(saved);
                this.settings = { ...this.settings, ...parsed };
            }
        } catch (error) {
            if (window.logError) {
                window.logError(error, { method: 'loadSettings' });
            }
        }
    }

    saveSettings() {
        try {
            localStorage.setItem('userSettings', JSON.stringify(this.settings));
        } catch (error) {
            if (window.logError) {
                window.logError(error, { method: 'saveSettings' });
            }
        }
    }

    async updateUserProfile(data) {
        try {
            const response = await fetch(window.APP_CONFIG.apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    module: 'user',
                    action: 'update_profile',
                    data: data,
                    [window.CSRF_TOKEN_NAME]: window.CSRF_TOKEN
                })
            });

            const result = await response.json();
            if (result.success && window.APP_CONFIG.currentUser) {
                window.APP_CONFIG.currentUser.name = data.name;

                const userNameEl = document.querySelector('.user-name');
                if (userNameEl) {
                    userNameEl.textContent = data.name;
                }
            }
        } catch (error) {
            if (window.logError) {
                window.logError(error, { method: 'updateUserProfile' });
            }
        }
    }
}

window.UserSettings = new UserSettings();

function openSettings() {
    if (window.UserSettings) {
        window.UserSettings.openSettingsModal();
    }
}

window.openSettings = openSettings;
