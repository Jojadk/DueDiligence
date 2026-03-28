/**
 * Live Preview System
 *
 * Provides real-time WYSIWYG editing for report templates
 * with split-view interface and automatic rendering
 *
 * @version 1.0.0
 * @author Claude Code
 */

class LivePreview {
    constructor(options = {}) {
        this.options = {
            editorSelector: options.editorSelector || '#template-editor',
            previewSelector: options.previewSelector || '#template-preview',
            projectId: options.projectId || null,
            debounceDelay: options.debounceDelay || 300, // ms
            autoSave: options.autoSave !== false,
            autoSaveDelay: options.autoSaveDelay || 2000, // ms
            onRender: options.onRender || null,
            onError: options.onError || null,
            onSave: options.onSave || null
        };

        this.editor = document.querySelector(this.options.editorSelector);
        this.preview = document.querySelector(this.options.previewSelector);

        if (!this.editor || !this.preview) {
            console.error('[LivePreview] Editor or preview element not found');
            return;
        }

        this.debounceTimer = null;
        this.autoSaveTimer = null;
        this.lastContent = '';
        this.isRendering = false;
        this.renderQueue = [];

        this.init();
    }

    /**
     * Initialize live preview
     */
    init() {
        // Setup editor listener
        this.editor.addEventListener('input', () => this.handleInput());
        this.editor.addEventListener('paste', () => this.handleInput());

        // Setup keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // Initial render
        this.render();

        // Setup split view resizer
        this.setupSplitViewResizer();

        // Setup toolbar
        this.setupToolbar();

        console.log('[LivePreview] Initialized');
    }

    /**
     * Handle input with debouncing
     */
    handleInput() {
        clearTimeout(this.debounceTimer);
        clearTimeout(this.autoSaveTimer);

        // Show "typing" indicator
        this.showStatus('Opdaterer...', 'info');

        // Debounce rendering
        this.debounceTimer = setTimeout(() => {
            this.render();

            // Auto-save after render
            if (this.options.autoSave) {
                this.autoSaveTimer = setTimeout(() => {
                    this.save();
                }, this.options.autoSaveDelay);
            }
        }, this.options.debounceDelay);
    }

    /**
     * Render template with preview
     */
    async render() {
        const content = this.editor.value;

        // Skip if content unchanged
        if (content === this.lastContent) {
            return;
        }

        this.lastContent = content;
        this.isRendering = true;

        // Show loading state
        this.showLoadingState();

        try {
            const startTime = performance.now();

            // Call preview API
            const response = await fetch('?module=report_builder&action=preview', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    project_id: this.options.projectId,
                    template: content,
                    csrf_token: this.getCsrfToken()
                })
            });

            const result = await response.json();

            if (result.success) {
                // Update preview
                this.preview.innerHTML = result.rendered;

                // Calculate render time
                const renderTime = Math.round(performance.now() - startTime);

                // Show success status
                this.showStatus(`Opdateret (${renderTime}ms)`, 'success');

                // Process automatic features
                this.processAutoFeatures();

                // Callback
                if (this.options.onRender) {
                    this.options.onRender(result);
                }

            } else {
                throw new Error(result.message || 'Rendering fejlede');
            }

        } catch (error) {
            console.error('[LivePreview] Render error:', error);
            this.showStatus('Fejl: ' + error.message, 'error');

            if (this.options.onError) {
                this.options.onError(error);
            }

        } finally {
            this.isRendering = false;
            this.hideLoadingState();
        }
    }

    /**
     * Save template
     */
    async save() {
        const content = this.editor.value;

        if (!content.trim()) {
            return;
        }

        try {
            this.showStatus('Gemmer...', 'info');

            const response = await fetch('?module=report_builder&action=save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({
                    id: this.getTemplateId(),
                    name: this.getTemplateName(),
                    template: content,
                    report_type: this.getReportType(),
                    template_scope: this.getTemplateScope(),
                    csrf_token: this.getCsrfToken()
                })
            });

            const result = await response.json();

            if (result.success) {
                this.showStatus('Gemt', 'success');

                if (this.options.onSave) {
                    this.options.onSave(result);
                }
            } else {
                throw new Error(result.message || 'Kunne ikke gemme');
            }

        } catch (error) {
            console.error('[LivePreview] Save error:', error);
            this.showStatus('Fejl ved gemning: ' + error.message, 'error');
        }
    }

    /**
     * Process automatic features (TOC, figure numbers, etc.)
     */
    processAutoFeatures() {
        // Add smooth scroll to TOC links
        this.preview.querySelectorAll('.toc-link').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                const targetId = link.getAttribute('href').substring(1);
                const target = this.preview.querySelector(`#${targetId}`);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });

        // Highlight active section in TOC
        this.setupTOCHighlighting();

        // Process figure captions
        this.processFigureCaptions();
    }

    /**
     * Setup TOC highlighting based on scroll position
     */
    setupTOCHighlighting() {
        const sections = Array.from(this.preview.querySelectorAll('h2[id]'));
        const tocLinks = Array.from(this.preview.querySelectorAll('.toc-link'));

        if (sections.length === 0) return;

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const id = entry.target.id;
                    tocLinks.forEach(link => {
                        const href = link.getAttribute('href').substring(1);
                        if (href === id) {
                            link.classList.add('active');
                        } else {
                            link.classList.remove('active');
                        }
                    });
                }
            });
        }, { threshold: 0.5 });

        sections.forEach(section => observer.observe(section));
    }

    /**
     * Process figure captions with auto-numbering
     */
    processFigureCaptions() {
        const images = this.preview.querySelectorAll('img[data-figure]');

        images.forEach(img => {
            const figureNumber = img.getAttribute('data-figure');

            // Check if already wrapped in figure
            if (img.parentElement.tagName !== 'FIGURE') {
                // Wrap in figure with caption
                const figure = document.createElement('figure');
                const figcaption = document.createElement('figcaption');
                figcaption.textContent = figureNumber;

                img.parentNode.insertBefore(figure, img);
                figure.appendChild(img);
                figure.appendChild(figcaption);
            }
        });
    }

    /**
     * Setup split view resizer
     */
    setupSplitViewResizer() {
        const container = this.editor.closest('.split-view-container');
        if (!container) return;

        const resizer = container.querySelector('.split-view-resizer');
        if (!resizer) return;

        let isResizing = false;
        let startX = 0;
        let startWidth = 0;

        resizer.addEventListener('mousedown', (e) => {
            isResizing = true;
            startX = e.clientX;
            startWidth = this.editor.offsetWidth;
            document.body.style.cursor = 'col-resize';
            e.preventDefault();
        });

        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return;

            const delta = e.clientX - startX;
            const newWidth = startWidth + delta;
            const containerWidth = container.offsetWidth;
            const minWidth = 300;
            const maxWidth = containerWidth - 300;

            if (newWidth >= minWidth && newWidth <= maxWidth) {
                const percentage = (newWidth / containerWidth) * 100;
                container.style.setProperty('--editor-width', `${percentage}%`);
            }
        });

        document.addEventListener('mouseup', () => {
            isResizing = false;
            document.body.style.cursor = '';
        });
    }

    /**
     * Setup toolbar with shortcuts
     */
    setupToolbar() {
        const toolbar = document.querySelector('.live-preview-toolbar');
        if (!toolbar) return;

        // Save button
        const saveBtn = toolbar.querySelector('[data-action="save"]');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.save());
        }

        // Refresh button
        const refreshBtn = toolbar.querySelector('[data-action="refresh"]');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.render());
        }

        // Fullscreen button
        const fullscreenBtn = toolbar.querySelector('[data-action="fullscreen"]');
        if (fullscreenBtn) {
            fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());
        }

        // Split/Single view toggle
        const viewToggleBtn = toolbar.querySelector('[data-action="toggle-view"]');
        if (viewToggleBtn) {
            viewToggleBtn.addEventListener('click', () => this.toggleView());
        }

        // Variable inserter
        const variableBtn = toolbar.querySelector('[data-action="insert-variable"]');
        if (variableBtn) {
            variableBtn.addEventListener('click', () => this.showVariableInserter());
        }
    }

    /**
     * Handle keyboard shortcuts
     */
    handleKeyboard(e) {
        // Ctrl+S / Cmd+S: Save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            this.save();
        }

        // Ctrl+Shift+P / Cmd+Shift+P: Preview only
        if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'P') {
            e.preventDefault();
            this.toggleView();
        }

        // F11: Fullscreen
        if (e.key === 'F11') {
            e.preventDefault();
            this.toggleFullscreen();
        }
    }

    /**
     * Toggle fullscreen mode
     */
    toggleFullscreen() {
        const container = this.editor.closest('.split-view-container');
        if (!container) return;

        if (!document.fullscreenElement) {
            container.requestFullscreen().catch(err => {
                console.error('[LivePreview] Fullscreen error:', err);
            });
        } else {
            document.exitFullscreen();
        }
    }

    /**
     * Toggle between split and single view
     */
    toggleView() {
        const container = this.editor.closest('.split-view-container');
        if (!container) return;

        container.classList.toggle('preview-only');
    }

    /**
     * Show variable inserter modal
     */
    async showVariableInserter() {
        try {
            const response = await fetch('?module=report_builder&action=get_variables');
            const result = await response.json();

            if (result.success) {
                this.renderVariableModal(result.variables);
            }

        } catch (error) {
            console.error('[LivePreview] Failed to load variables:', error);
        }
    }

    /**
     * Render variable inserter modal
     */
    renderVariableModal(variables) {
        // Create modal HTML
        const modal = document.createElement('div');
        modal.className = 'variable-inserter-modal';
        modal.innerHTML = `
            <div class="modal-overlay"></div>
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Indsæt Variable</h3>
                    <button class="modal-close">&times;</button>
                </div>
                <div class="modal-body">
                    <input type="text" class="variable-search" placeholder="Søg efter variable...">
                    <div class="variable-list">
                        ${this.renderVariableList(variables)}
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Setup modal interactions
        modal.querySelector('.modal-close').addEventListener('click', () => modal.remove());
        modal.querySelector('.modal-overlay').addEventListener('click', () => modal.remove());

        // Setup variable insertion
        modal.querySelectorAll('.variable-item').forEach(item => {
            item.addEventListener('click', () => {
                const variable = item.getAttribute('data-variable');
                this.insertVariable(variable);
                modal.remove();
            });
        });

        // Setup search
        const searchInput = modal.querySelector('.variable-search');
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            modal.querySelectorAll('.variable-item').forEach(item => {
                const text = item.textContent.toLowerCase();
                item.style.display = text.includes(query) ? '' : 'none';
            });
        });

        searchInput.focus();
    }

    /**
     * Render variable list HTML
     */
    renderVariableList(variables) {
        let html = '';

        Object.entries(variables).forEach(([category, data]) => {
            html += `<div class="variable-category">`;
            html += `<h4>${data.label}</h4>`;

            if (data.variables) {
                Object.entries(data.variables).forEach(([varName, description]) => {
                    html += `
                        <div class="variable-item" data-variable="${varName}">
                            <code>${varName}</code>
                            <span>${description}</span>
                        </div>
                    `;
                });
            }

            if (data.loop) {
                html += `<div class="variable-loop"><code>${data.loop}</code></div>`;
            }

            if (data.examples) {
                html += '<div class="variable-examples">';
                Object.entries(data.examples).forEach(([example, description]) => {
                    html += `
                        <div class="variable-item" data-variable="${example}">
                            <code>${example}</code>
                            <span>${description}</span>
                        </div>
                    `;
                });
                html += '</div>';
            }

            html += `</div>`;
        });

        return html;
    }

    /**
     * Insert variable at cursor position
     */
    insertVariable(variable) {
        const start = this.editor.selectionStart;
        const end = this.editor.selectionEnd;
        const text = this.editor.value;

        this.editor.value = text.substring(0, start) + variable + text.substring(end);
        this.editor.selectionStart = this.editor.selectionEnd = start + variable.length;

        this.editor.focus();
        this.handleInput();
    }

    /**
     * Show status message
     */
    showStatus(message, type = 'info') {
        const statusBar = document.querySelector('.live-preview-status');
        if (!statusBar) return;

        statusBar.textContent = message;
        statusBar.className = `live-preview-status status-${type}`;

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                statusBar.textContent = '';
                statusBar.className = 'live-preview-status';
            }, 3000);
        }
    }

    /**
     * Show loading state
     */
    showLoadingState() {
        this.preview.classList.add('loading');
    }

    /**
     * Hide loading state
     */
    hideLoadingState() {
        this.preview.classList.remove('loading');
    }

    /**
     * Get CSRF token
     */
    getCsrfToken() {
        return document.querySelector('[name="csrf_token"]')?.value || '';
    }

    /**
     * Get template ID
     */
    getTemplateId() {
        return document.querySelector('[name="template_id"]')?.value || null;
    }

    /**
     * Get template name
     */
    getTemplateName() {
        return document.querySelector('[name="template_name"]')?.value || 'Untitled Template';
    }

    /**
     * Get report type
     */
    getReportType() {
        return document.querySelector('[name="report_type"]')?.value || 'custom';
    }

    /**
     * Get template scope
     */
    getTemplateScope() {
        return document.querySelector('[name="template_scope"]')?.value || 'global';
    }

    /**
     * Destroy instance
     */
    destroy() {
        clearTimeout(this.debounceTimer);
        clearTimeout(this.autoSaveTimer);
        console.log('[LivePreview] Destroyed');
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = LivePreview;
}
