/**
 * Building Element Module Logic
 */
const BuildingElementModule = {
    projectId: null,
    activeLocks: {},
    budgetElementId: null,
    activeAnnotationElementId: null,
    saveTimeout: null,

    // Money Helpers
    formatMoney: function (val) {
        if (val === undefined || val === null || val === '') return '';
        return Number(val).toLocaleString('da-DK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    parseMoney: function (str) {
        if (typeof str === 'number') return str;
        if (!str) return 0;
        let v = str.toString();
        // Remove thousands dots
        v = v.replace(/\./g, '');
        // Replace decimal comma
        v = v.replace(',', '.');
        return parseFloat(v) || 0;
    },

    /**
     * Initialize the module
     * @param {number} projectId - The ID of the current project
     */
    init: function (projectId) {
        this.projectId = projectId;
        this.setupDragAndDrop();
        this.startAutoSaveLoop();
        this.initSearch();

        // Ensure manual numbering is hidden if JS numbering runs
        // But we rely on renumberTree for that.
        this.renumberTree();
        this.setupEventDelegation();

        // EXPOSE METHODS TO WINDOW (Required for inline HTML handlers)
        window.acquireLock = this.acquireLock.bind(this);
        window.updateElementField = this.updateElementField.bind(this);
        // Note: handleInputAutoSave is provided by autosave.js to handle dirty state + polling
        // Release locks on page unload
        window.addEventListener('beforeunload', () => {
            Object.keys(this.activeLocks).forEach(field => {
                // Use navigator.sendBeacon if possible for reliable transmission
                const data = new FormData();
                data.append('element_id', this.budgetElementId); // WARNING: budgetElementId might be null if only looking at budget?
                // Wait, activeLocks are keyed by field name. But which element?
                // The current implementation assumes one active element at a time on screen? 
                // Ah, `acquireLock(id, field)` in `building_element.js` doesn't store ID in `activeLocks` object, only field name.
                // `activeLocks = { 'description': true }`.
                // This is a flaw if we support multiple elements. But `ContentPartial` suggests only one `activeElement` is loaded.
                // The ID is available in `this.activeLocks`? No.
                // I need to retrieve the ID.
                // I can look at DOM `[data-element-id]` or use `this.activeElementId` if I store it.
                // `loadNodeContent` updates URL but doesn't explicitly store ID in `this`.
                // But `index.php` (ContentPartial) puts a button with `data-element-id`.
                // Let's grab it from DOM.
            });

            // Actually, simpler: just iterate and try best effort. 
            // Or rely on the fact that `updateElementField` (called on blur) releases it.
            // If I close tab while focused? Blur fires? In some browsers yes.

            // To be safe, I should store (id, field) in activeLocks.
        });

        // Revised init to fix activeLocks storage structure or just iterate what we have?
        // Let's update `acquireLock` to store ID too.

        window.acquireLock = this.acquireLock.bind(this);
        window.updateElementField = this.updateElementField.bind(this);
        window.deleteElement = this.deleteElement.bind(this);
        window.openBudgetModal = this.openBudgetModal.bind(this);
        window.openVersionSaveModal = this.openVersionSaveModal.bind(this);
        window.saveAndReload = this.saveAndReload.bind(this);

        // Add beforeunload
        window.addEventListener('beforeunload', () => {
            // Best effort release
            const activeId = document.querySelector('.btn-edit-element')?.getAttribute('data-element-id');
            if (!activeId) return;

            Object.keys(this.activeLocks).forEach(field => {
                // Use Beacon for reliability
                const url = '?module=BuildingElement&action=lockrelease';
                const data = new URLSearchParams();
                data.append('element_id', activeId);
                data.append('field_name', field);
                navigator.sendBeacon(url, data);
            });
        });
    },

    startAutoSaveLoop: function () {
        // Auto-save logic is primarily event-driven (oninput/onblur), 
        // but this loop can handle periodic checks or queue flushing if needed.
        setInterval(() => {
            // Periodic check or keep-alive could go here
        }, 30000);
    },

    initSearch: function () {
        const searchInput = document.querySelector('.toc-search input');
        if (!searchInput) return;

        // Create Dropdown Container
        const dropdown = document.createElement('ul');
        dropdown.className = 'search-dropdown';
        dropdown.style.cssText = 'position:absolute; width:90%; background:white; color:#333; list-style:none; padding:0; margin:5px 0 0 0; border-radius:4px; box-shadow:0 4px 10px rgba(0,0,0,0.2); max-height:300px; overflow-y:auto; z-index:1000; display:none;';
        searchInput.parentElement.style.position = 'relative'; // Ensure parent is ref for absolute
        searchInput.parentElement.appendChild(dropdown);

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            dropdown.innerHTML = '';

            if (query.length < 2) {
                dropdown.style.display = 'none';
                return;
            }

            const nodes = document.querySelectorAll('.tree-node');
            let matches = 0;

            nodes.forEach(node => {
                const nameEl = node.querySelector('.tree-name');
                const name = nameEl ? nameEl.textContent : '';

                if (name.toLowerCase().includes(query)) {
                    matches++;
                    const li = document.createElement('li');
                    li.style.cssText = 'padding:8px 12px; cursor:pointer; border-bottom:1px solid #eee; font-size:0.9rem;';
                    li.innerHTML = name.replace(new RegExp(query, 'gi'), (match) => `<strong style="color:#3498db">${match}</strong>`);

                    li.addEventListener('click', () => {
                        this.loadNodeContent(node.getAttribute('data-id'));
                        dropdown.style.display = 'none';
                        searchInput.value = '';
                    });
                    li.addEventListener('mouseover', () => {
                        li.style.background = '#f8f9fa';
                    });
                    li.addEventListener('mouseout', () => {
                        li.style.background = 'white';
                    });

                    dropdown.appendChild(li);
                }
            });

            dropdown.style.display = matches > 0 ? 'block' : 'none';
        });

        // Close on click outside
        document.addEventListener('click', (e) => {
            if (dropdown.style.display === 'block' && !searchInput.parentElement.contains(e.target)) {
                dropdown.style.display = 'none';
            }
        });
    },

    /**
     * Sets up global event delegation for the module
     * Handles clicks on tree nodes, modal buttons, and dynamic content.
     */
    setupEventDelegation: function () {
        // Delegate tree node clicks
        document.addEventListener('click', (e) => {
            // Handle tree toggle clicks
            const toggle = e.target.closest('[data-action="toggle"]');
            if (toggle) {
                e.stopPropagation();
                this.toggleTreeNode(toggle);
                return;
            }

            // Handle tree content clicks (load node)
            const content = e.target.closest('[data-node-id]');
            if (content) {
                const nodeId = content.getAttribute('data-node-id');
                if (nodeId) {
                    this.loadNodeContent(nodeId);
                }
                return;
            }

            // Handle edit element button
            const editBtn = e.target.closest('.btn-edit-element');
            if (editBtn) {
                const elementId = editBtn.getAttribute('data-element-id');
                if (elementId && window.openEditSectionModal) {
                    window.openEditSectionModal(elementId);
                }
                return;
            }

            // Handle delete element button
            const deleteBtn = e.target.closest('.btn-delete-element');
            if (deleteBtn) {
                const elementId = deleteBtn.getAttribute('data-element-id');
                if (elementId) {
                    this.deleteElement(elementId);
                }
                return;
            }

            // Handle Header Buttons
            if (e.target.closest('#btn-version-save')) {
                this.openVersionSaveModal();
                return;
            }

            if (e.target.closest('#btn-save-reload')) {
                window.location.reload();
                return;
            }

            if (e.target.closest('#btn-version-history')) {
                this.openVersionHistoryModal();
                return;
            }

            if (e.target.closest('#btn-new-section')) {
                this.openNewSectionModal();
                return;
            }

            // Budget Modal Events
            if (e.target.closest('#btn-close-budget')) {
                document.getElementById('budgetModal').style.display = 'none';
                return;
            }
            if (e.target.closest('#btn-add-budget-row')) {
                this.addBudgetRow();
                return;
            }
            if (e.target.closest('#btn-save-budget')) {
                this.saveBudget(e);
                return;
            }
            if (e.target.closest('.btn-delete-budget-row')) {
                e.target.closest('tr').remove();
                this.calculateBudgetTotal();
                return;
            }

            // Image Modal Events
            if (e.target.closest('#btn-add-image-field')) {
                this.addImageField();
                return;
            }
            if (e.target.closest('#btn-close-image-modal')) {
                this.closeImageModal();
                return;
            }
            if (e.target.closest('#btn-save-images')) {
                const btn = e.target.closest('#btn-save-images');
                const eid = btn.getAttribute('data-element-id');
                const pid = btn.getAttribute('data-project-id');
                this.saveImages(eid, pid);
                return;
            }

            // New Section Modal Events
            if (e.target.closest('#btn-close-new-section') || e.target.closest('#btn-cancel-new-section')) {
                this.closeNewSectionModal();
                return;
            }
        });

        // Add hover effects via JS to header items (replacement for inline onmouseover)
        const versionSpan = document.getElementById('btn-version-save');
        if (versionSpan) {
            versionSpan.addEventListener('mouseenter', () => versionSpan.style.background = '#f0f0f0');
            versionSpan.addEventListener('mouseleave', () => versionSpan.style.background = 'transparent');
        }

        // Budget input delegation (replaces onchange in addBudgetRow HTML)
        document.getElementById('budget-rows')?.addEventListener('input', (e) => {
            if (e.target.classList.contains('b-qty') || e.target.classList.contains('b-price')) {
                this.calculateBudgetTotal();
            }
        });
    },

    toggleTreeNode: function (toggle) {
        const li = toggle.closest('li');
        const ul = li.querySelector('ul');
        if (ul) {
            if (ul.style.display === 'none') {
                ul.style.display = 'block';
                toggle.style.transform = 'rotate(90deg)';
                toggle.setAttribute('data-state', 'open');
            } else {
                ul.style.display = 'none';
                toggle.style.transform = 'rotate(0deg)';
                toggle.setAttribute('data-state', 'closed');
            }
        }
    },

    // --- Modal Methods ---



    // --- Window Manager Modals ---

    openNewSectionModal: function () {
        // Use projectId from init if available
        const pid = this.projectId; // Need to ensure projectId is stored in init
        if (!pid) return alert('Project ID missing');

        App.api('?module=BuildingElement&action=getForm&project_id=' + pid)
            .then(html => {
                wm.createWindow({
                    id: 'win-element-form',
                    title: i18n.t('element.new_section') || 'Opret Nyt Afsnit',
                    content: html,
                    width: 500,
                    height: 600,
                    x: 150,
                    y: 150
                });
            });
    },

    openEditSectionModal: function (id) {
        App.api('?module=BuildingElement&action=getForm&id=' + id)
            .then(html => {
                wm.createWindow({
                    id: 'win-element-form',
                    title: (i18n.t('element.edit') || 'Rediger') + ' Element #' + id,
                    content: html,
                    width: 500,
                    height: 600,
                    x: 150,
                    y: 150
                });
            });
    },

    // --- Version Modals ---
    openVersionSaveModal: function () {
        if (!this.projectId) return;
        App.api('?module=BuildingElement&action=getVersionForm&project_id=' + this.projectId)
            .then(html => {
                wm.createWindow({
                    id: 'win-version-save',
                    title: 'Gem Version Snapshot',
                    content: html,
                    width: 600,
                    height: 700
                });
            });
    },

    openVersionHistoryModal: function () {
        const content = '<div id="wm-version-list" style="padding:10px; height:100%; overflow-y:auto;">Indlæser...</div>';
        wm.createWindow({
            id: 'win-version-history',
            title: 'Version Historie',
            content: content,
            width: 700,
            height: 600
        });
        this.loadVersionHistory('wm-version-list');
    },

    loadVersionHistory: function (containerId) {
        const container = document.getElementById(containerId);
        if (!container) return;

        App.api('?module=BuildingElement&action=getVersions&project_id=' + this.projectId)
            .then(res => {
                if (res.status === 'success' && res.versions) {
                    if (res.versions.length === 0) {
                        container.innerHTML = '<p style="text-align:center; color:#999;">Ingen versioner gemt endnu</p>';
                        return;
                    }

                    let html = '<div style="display:flex; flex-direction:column; gap:10px;">';
                    res.versions.forEach(v => {
                        html += `
                            <div style="border:1px solid #ddd; border-radius:8px; padding:15px; background:#f9f9f9;">
                                <div style="display:flex; justify-content:space-between; align-items:start; margin-bottom:10px;">
                                    <div>
                                        <h4 style="margin:0; color:#333;">${v.version_number}</h4>
                                        <small style="color:#666;">${v.created_at} · af ${v.created_by_name}</small>
                                    </div>
                                    <button class="btn btn-sm btn-primary" onclick="confirmRestoreVersion(${v.id}, '${v.version_number}')">
                                        <i class="fas fa-undo"></i> Gendan
                                    </button>
                                </div>
                                ${v.version_note ? `<p style="margin:5px 0; color:#555;"><strong>Note:</strong> ${v.version_note}</p>` : ''}
                                ${v.internal_remark ? `<p style="margin:5px 0; color:#777; font-size:0.9em;"><em>Intern: ${v.internal_remark}</em></p>` : ''}
                                <small style="color:#999;">${v.element_count || 0} elementer i snapshot</small>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p style="text-align:center; color:#e74c3c;">Fejl ved indlæsning</p>';
                }
            })
            .catch(err => {
                container.innerHTML = '<p style="text-align:center; color:#e74c3c;">Netværksfejl</p>';
            });
    },

    /**
     * Dynamically adds a new file input field for image uploads
     */
    addImageField: function () {
        const div = document.createElement('div');
        div.innerHTML = '<input type="file" name="images[]" class="form-control mb-1">';
        const container = document.getElementById('image-fields');
        if (container) container.appendChild(div);
    },

    /**
     * Closes the image upload modal
     */
    closeImageModal: function () {
        const modal = document.getElementById('imageUploadModal');
        if (modal) modal.style.display = 'none';
    },

    saveImages: function (eid, pid) {
        const files = [];
        document.querySelectorAll('#image-fields input[type=file]').forEach(inp => {
            for (let f of inp.files) files.push(f);
        });
        this.uploadFiles(files, eid, pid);
        this.closeImageModal();
    },

    /**
     * Loads the content for a specific building element node via AJAX
     * @param {number} id - The ID of the building element
     */
    loadNodeContent: function (id) {
        try {
            const pid = this.projectId;
            if (!pid) {
                console.error('Project ID not set');
                App.toast(i18n.t('error.system') + ': Project ID mangler', 'error');
                return;
            }

            const url = `?module=BuildingElement&action=index&project_id=${pid}&id=${id}`;
            history.pushState({ id: id }, '', url);

            // Update UI: highlight selected node
            document.querySelectorAll('.tree-content').forEach(el => el.classList.remove('active'));
            const li = document.querySelector(`li[data-id="${id}"]`);
            if (li) {
                const contentDiv = li.querySelector('.tree-content');
                if (contentDiv) contentDiv.classList.add('active');
            }

            const contentArea = document.querySelector('.inspection-main');
            if (!contentArea) {
                console.error('Content area (.inspection-main) not found');
                return;
            }

            contentArea.innerHTML = `<div style="padding:40px; text-align:center;"><i class="fas fa-spinner fa-spin"></i> ${i18n.t('element.loading_content')}</div>`;

            App.api(`?module=BuildingElement&action=index&project_id=${pid}&id=${id}&ajax_content=1`)
                .then(html => {
                    if (contentArea) {
                        contentArea.innerHTML = html;
                        // Content loaded for element
                        if (window.i18n && typeof window.i18n.updatePageLanguage === 'function') {
                            window.i18n.updatePageLanguage();
                        }
                        this.setupImageSort();
                    }
                })
                .catch(err => {
                    console.error('Error loading element content:', err);
                    if (contentArea) {
                        contentArea.innerHTML = `
                            <div style="padding:40px; text-align:center;">
                                <i class="fas fa-exclamation-triangle" style="font-size:3rem; color:#dc3545;"></i>
                                <h3>${i18n.t('element.load_failed')}</h3>
                                <p>${err.message || i18n.t('error.system')}</p>
                                <button class="btn btn-primary" onclick="location.reload()">${i18n.t('common.close')}</button>
                            </div>
                        `;
                    }
                    App.toast(i18n.t('error.network'), 'error');
                });
        } catch (error) {
            console.error('Exception in loadNodeContent:', error);
            App.toast(i18n.t('error.system') + ': ' + error.message, 'error');
        }
    },


    openBudgetModal: function (elementId) {
        if (typeof wm === 'undefined') return;
        const winId = 'budget_window_' + elementId;
        if (wm.windows[winId]) { wm.focus(winId); return; }
        this.budgetElementId = elementId;

        const content = `
            <style>
                .budget-list-container { flex: 1; overflow-y: auto; padding: 15px; }
                .budget-card { background: #fff; border: 1px solid #e0e0e0; border-radius: 6px; padding: 12px; margin-bottom: 12px; display: flex; gap: 12px; position: relative; transition: box-shadow 0.2s; }
                .budget-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
                .ac-dropdown { position: absolute; background: white; border: 1px solid #ccc; z-index: 1000; width: 100%; max-height: 200px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); top: 100%; left: 0; }
                .ac-item { padding: 8px; cursor: pointer; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; font-size: 11px; }
                .ac-item:hover, .ac-item.active { background: #f0f8ff; }
                .drag-handle { cursor: grab; color: #ccc; display: flex; align-items: center; font-size: 18px; user-select: none; }
                .drag-handle::after { content: '⋮⋮'; letter-spacing: -2px; }
                .card-content { flex: 1; display: flex; flex-direction: column; gap: 10px; }
                .row-top { display: flex; gap: 10px; }
                .row-metrics { display: flex; gap: 8px; align-items: flex-end; }
                .row-timeframes { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-top: 5px; padding-top: 10px; border-top: 1px dashed #eee; }
                .budget-card label { font-size: 10px; color: #888; display: block; margin-bottom: 3px; }
                .budget-card input, .budget-card select { width: 100%; padding: 6px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; box-sizing: border-box; }
                .budget-card input:focus { border-color: #007bff; outline: none; }
                .val-total { font-weight: bold; text-align: right; min-width: 80px; padding-bottom: 7px; font-size: 14px; }
                .tf-box { position: relative; }
                .tf-box input { padding-right: 25px; }
                .magic-wand { position: absolute; right: 8px; bottom: 8px; cursor: pointer; color: #555; z-index: 10; font-size: 12px; }
                .magic-wand:hover { opacity: 1; }
                .bg-t1 { background: #fff0f0; border-color: #ffdada; }
                .bg-t2 { background: #ffffe0; border-color: #eeeebb; }
                .bg-t3 { background: #f0fff0; border-color: #ccffcc; }
                .bg-t4 { background: #f0f8ff; border-color: #cce5ff; }
                .btn-delete { border: none; background: none; color: #dc3545; cursor: pointer; font-size: 18px; padding: 0 5px; line-height: 1; }
                .btn-add { width: 100%; padding: 10px; border: 1px dashed #bbb; background: #fff; color: #666; border-radius: 6px; cursor: pointer; font-weight: 500; margin-bottom: 20px; }
                .btn-add:hover { background: #fefefe; border-color: #888; color: #333; }
                .wm-footer-wrapper { background: #fff; border-top: 1px solid #ddd; flex-shrink: 0; }
                .summary-bar { display: grid; grid-template-columns: repeat(4, 1fr); text-align: center; font-size: 11px; border-bottom: 1px solid #eee; }
                .sum-item { padding: 8px 5px; border-right: 1px solid #f5f5f5; }
                .sum-item:last-child { border-right: none; }
                .sum-label { color: #888; font-size: 9px; display: block; margin-bottom: 2px; }
                .sum-val { font-weight: bold; font-size: 12px; }
                .wm-footer-actions { padding: 12px 15px; display: flex; justify-content: space-between; align-items: center; }
                .btn-save { background: #007bff; color: white; border: none; padding: 8px 20px; border-radius: 4px; font-weight: 500; cursor: pointer; }
            </style>
            <div style="display:flex; flex-direction:column; height:100%; background:#f8f9fa;">
                <div class="budget-list-container" id="budget-rows-container">
                    <div id="sortable-list"></div>
                    <button class="btn-add" id="btn-add-budget-row">+ Tilføj Budgetlinje</button>
                </div>
                <div class="wm-footer-wrapper">
                    <div class="summary-bar">
                        <div class="sum-item" style="background: #fffafa;"><span class="sum-label">< 1 ÅR</span><span class="sum-val" id="total-t1">0,00</span></div>
                        <div class="sum-item" style="background: #fffffc;"><span class="sum-label">1-2 ÅR</span><span class="sum-val" id="total-t2">0,00</span></div>
                        <div class="sum-item" style="background: #fafffa;"><span class="sum-label">3-5 ÅR</span><span class="sum-val" id="total-t3">0,00</span></div>
                        <div class="sum-item" style="background: #f9fcff;"><span class="sum-label">5-10 ÅR</span><span class="sum-val" id="total-t4">0,00</span></div>
                    </div>
                    <div class="wm-footer-actions">
                        <div style="font-size: 15px;">Total CAPEX: <strong id="grand-total">0,00 DKK</strong></div>
                        <button class="btn-save" id="btn-save-budget">Gem Budget</button>
                    </div>
                </div>
            </div>
        `;

        wm.createWindow({ id: winId, title: 'Budget & CAPEX', width: 600, height: 750, content: content, x: 'center', y: 'center' });

        setTimeout(() => {
            const btnSave = document.getElementById('btn-save-budget');
            if (btnSave) btnSave.onclick = (e) => this.saveBudget(e);

            const btnAdd = document.getElementById('btn-add-budget-row');
            if (btnAdd) btnAdd.onclick = () => this.addBudgetRow();
        }, 100);

        App.api(`?module=BuildingElement&action=getbudget&id=${elementId}`)
            .then(data => {
                const list = document.getElementById('sortable-list');
                if (list) list.innerHTML = '';
                const items = data.items || (data.data && data.data.items) || [];
                if (items.length > 0) items.forEach(item => this.addBudgetRow(item));
                else this.addBudgetRow();
                this.calculateBudgetTotal();
            })
            .catch(e => {
                App.toast('Fejl ved indlæsning: ' + e, 'error');
            });
    },

    addBudgetRow: function (item = {}) {
        const container = document.getElementById('sortable-list');
        if (!container) return;
        const card = document.createElement('div');
        card.className = 'budget-card';
        card.draggable = true;

        card.innerHTML = `
            <div class="drag-handle"></div>
            <div class="card-content">
                <div class="row-top" style="position:relative">
                    <input type="hidden" class="b-catalog-id" value="${item.price_catalog_id || ''}">
                    <input type="text" class="b-desc" value="${item.description || ''}" placeholder="Beskrivelse..." autocomplete="off">
                    <button class="btn-delete" title="Slet">×</button>
                </div>
                <div class="row-metrics">
                    <div style="flex: 0 0 60px;"><label>Mængde</label><input type="text" class="b-qty" value="${this.formatMoney(item.quantity)}" style="text-align:right;"></div>
                    <div style="flex: 0 0 60px;"><label>Enhed</label>
                         <select class="b-unit" style="width:100%; padding:6px; border:1px solid #ddd; border-radius:4px; font-size:13px;">
                            ${['stk', 'm', 'm2', 'm3', 'set', 'time', 'dag', 'uge', 'mdr', 'l', 'kg', 'ton'].map(u => `<option value="${u}" ${item.unit === u ? 'selected' : ''}>${u}</option>`).join('')}
                         </select>
                    </div>
                    <div style="flex: 1;"><label>Pris DKK</label><input type="text" class="b-price" value="${this.formatMoney(item.unit_price)}" style="text-align:right;"></div>
                    <div class="val-total b-total-row">${this.formatMoney(item.total_calculated)}</div>
                </div>
                <div class="row-timeframes">
                    <div class="tf-box"><label>< 1 år</label><input type="text" class="bg-t1 b-t1" value="${this.formatMoney(item.amount_0_1)}" style="text-align:right;"><span class="magic-wand" data-target=".b-t1"><i class="fas fa-magic"></i></span></div>
                    <div class="tf-box"><label>1-2 år</label><input type="text" class="bg-t2 b-t2" value="${this.formatMoney(item.amount_1_2)}" style="text-align:right;"><span class="magic-wand" data-target=".b-t2"><i class="fas fa-magic"></i></span></div>
                    <div class="tf-box"><label>3-5 år</label><input type="text" class="bg-t3 b-t3" value="${this.formatMoney(item.amount_3_5)}" style="text-align:right;"><span class="magic-wand" data-target=".b-t3"><i class="fas fa-magic"></i></span></div>
                    <div class="tf-box"><label>5-10 år</label><input type="text" class="bg-t4 b-t4" value="${this.formatMoney(item.amount_5_10)}" style="text-align:right;"><span class="magic-wand" data-target=".b-t4"><i class="fas fa-magic"></i></span></div>
                </div>
            </div>
        `;

        card.querySelectorAll('input').forEach(inp => {
            inp.addEventListener('input', () => this.calculateBudgetTotal());
            if (inp.type === 'text') {
                inp.addEventListener('blur', () => {
                    if (inp.classList.contains('b-desc')) return; // Desc is safe
                    const v = this.parseMoney(inp.value);
                    inp.value = this.formatMoney(v);
                    this.calculateBudgetTotal();
                });
            }
        });

        // Autocomplete Logic
        const descInput = card.querySelector('.b-desc');
        const resultsDiv = document.createElement('div');
        resultsDiv.className = 'ac-dropdown';
        resultsDiv.style.display = 'none';
        descInput.parentNode.appendChild(resultsDiv);
        let activeIndex = -1;

        descInput.addEventListener('input', (e) => {
            const val = e.target.value;
            if (val.length < 2) { resultsDiv.style.display = 'none'; return; }

            if (this.acTimeout) clearTimeout(this.acTimeout);
            this.acTimeout = setTimeout(() => {
                App.api(`module=BuildingElement&action=search_catalog&q=${encodeURIComponent(val)}`)
                    .then(res => {
                        resultsDiv.innerHTML = '';
                        if (res.items && res.items.length > 0) {
                            res.items.forEach((item, idx) => {
                                const div = document.createElement('div');
                                div.className = 'ac-item';
                                div.innerHTML = `<span>${item.name} <small style="color:#888">(${item.item_code})</small></span> <span>${this.formatMoney(item.unit_price)} / ${item.unit}</span>`;
                                div.onclick = () => {
                                    descInput.value = item.name;
                                    card.querySelector('.b-qty').value = this.formatMoney(1);
                                    card.querySelector('.b-unit').value = item.unit;
                                    card.querySelector('.b-price').value = this.formatMoney(item.unit_price);
                                    card.querySelector('.b-catalog-id').value = item.id;
                                    this.calculateBudgetTotal();
                                    resultsDiv.style.display = 'none';
                                };
                                resultsDiv.appendChild(div);
                            });
                            resultsDiv.style.display = 'block';
                            activeIndex = -1;
                        } else {
                            resultsDiv.style.display = 'none';
                        }
                    });
            }, 300);
        });

        descInput.addEventListener('keydown', (e) => {
            const items = resultsDiv.querySelectorAll('.ac-item');
            if (resultsDiv.style.display === 'none' || items.length === 0) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = (activeIndex + 1) % items.length;
                updateActive(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex - 1 + items.length) % items.length;
                updateActive(items);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeIndex >= 0) items[activeIndex].click();
            }
        });
        const updateActive = (items) => {
            items.forEach((it, i) => {
                if (i === activeIndex) it.classList.add('active');
                else it.classList.remove('active');
            });
        };
        document.addEventListener('click', (e) => {
            if (!descInput.contains(e.target) && !resultsDiv.contains(e.target)) resultsDiv.style.display = 'none';
        });

        card.querySelector('select').addEventListener('change', () => this.calculateBudgetTotal());

        card.querySelector('.btn-delete').addEventListener('click', () => {
            card.remove();
            this.calculateBudgetTotal();
        });

        card.querySelectorAll('.magic-wand').forEach(btn => {
            btn.addEventListener('click', () => {
                const targetSelector = btn.getAttribute('data-target');
                const targetInput = card.querySelector(targetSelector);

                const qty = this.parseMoney(card.querySelector('.b-qty').value);
                const price = this.parseMoney(card.querySelector('.b-price').value);
                const total = qty * price;

                const currentVal = this.parseMoney(targetInput.value);

                // Toggle Logic: If already full total, clear it.
                if (Math.abs(currentVal - total) < 0.01 && total > 0) {
                    targetInput.value = this.formatMoney(0);
                } else {
                    // Otherwise reset all others and fill this one
                    card.querySelectorAll('.b-t1, .b-t2, .b-t3, .b-t4').forEach(t => t.value = this.formatMoney(0));
                    targetInput.value = this.formatMoney(total);
                }
                this.calculateBudgetTotal();
            });
        });

        // Native Drag
        card.addEventListener('dragstart', () => { window.draggedBudgetCard = card; card.style.opacity = '0.5'; });
        card.addEventListener('dragend', () => { card.style.opacity = ''; window.draggedBudgetCard = null; });
        card.addEventListener('dragover', (e) => {
            e.preventDefault();
            const target = e.target.closest('.budget-card');
            if (target && target !== window.draggedBudgetCard) {
                const rect = target.getBoundingClientRect();
                const next = (e.clientY - rect.top) > (rect.height / 2);
                container.insertBefore(window.draggedBudgetCard, next ? target.nextSibling : target);
            }
        });

        container.appendChild(card);
    },

    calculateBudgetTotal: function () {
        let totalCapex = 0;
        let sumT1 = 0;
        let sumT2 = 0;
        let sumT3 = 0;
        let sumT4 = 0;

        document.querySelectorAll('.budget-card').forEach(card => {
            const qty = this.parseMoney(card.querySelector('.b-qty').value);
            const price = this.parseMoney(card.querySelector('.b-price').value);
            const sum = qty * price;

            // Auto-update Linked Fields (Magic Wand Active)
            // If a field marked as 'fa-times' (full alloc), keep it in sync with total
            // UNLESS user is editing that specific field
            const linkedWand = card.querySelector('.magic-wand i.fa-times');
            if (linkedWand) {
                const btn = linkedWand.closest('.magic-wand');
                const targetSel = btn.getAttribute('data-target');
                const targetInput = card.querySelector(targetSel);
                if (targetInput && targetInput !== document.activeElement) {
                    targetInput.value = this.formatMoney(sum);
                }
            }

            card.querySelector('.b-total-row').textContent = this.formatMoney(sum);

            const v1 = this.parseMoney(card.querySelector('.b-t1').value);
            const v2 = this.parseMoney(card.querySelector('.b-t2').value);
            const v3 = this.parseMoney(card.querySelector('.b-t3').value);
            const v4 = this.parseMoney(card.querySelector('.b-t4').value);
            const timeSum = v1 + v2 + v3 + v4;

            // Mismatch Warning
            const totalEl = card.querySelector('.b-total-row');
            if (Math.abs(timeSum - sum) > 0.01) {
                totalEl.style.color = '#dc3545'; // Red
                totalEl.title = `Mismatch: Fordelt ${this.formatMoney(timeSum)} vs Total ${this.formatMoney(sum)}`;
                // Optional: visual indicator if needed, but red text is strong
            } else {
                totalEl.style.color = '';
                totalEl.title = '';
            }

            // Update Icons dynamically
            const updateIcon = (sel, val) => {
                const icon = card.querySelector(`.magic-wand[data-target="${sel}"] i`);
                if (icon) {
                    if (Math.abs(val - sum) < 0.01 && sum > 0) {
                        icon.className = 'fas fa-times'; // Indicate delete/clear/linked
                        icon.style.color = '#dc3545';
                    } else {
                        icon.className = 'fas fa-magic';
                        icon.style.color = '#555';
                    }
                }
            };
            updateIcon('.b-t1', v1);
            updateIcon('.b-t2', v2);
            updateIcon('.b-t3', v3);
            updateIcon('.b-t4', v4);

            sumT1 += v1;
            sumT2 += v2;
            sumT3 += v3;
            sumT4 += v4;
            totalCapex += sum;
        });

        if (document.getElementById('total-t1')) document.getElementById('total-t1').textContent = this.formatMoney(sumT1);
        if (document.getElementById('total-t2')) document.getElementById('total-t2').textContent = this.formatMoney(sumT2);
        if (document.getElementById('total-t3')) document.getElementById('total-t3').textContent = this.formatMoney(sumT3);
        if (document.getElementById('total-t4')) document.getElementById('total-t4').textContent = this.formatMoney(sumT4);

        if (document.getElementById('grand-total')) document.getElementById('grand-total').textContent = this.formatMoney(totalCapex) + " DKK";
        if (document.getElementById('capex-display')) document.getElementById('capex-display').value = this.formatMoney(totalCapex);

        // Live Update Main View Breakdown
        const dispT1 = document.getElementById('main-sum-t1');
        if (dispT1) dispT1.innerText = this.formatMoney(sumT1);
        const dispT2 = document.getElementById('main-sum-t2');
        if (dispT2) dispT2.innerText = this.formatMoney(sumT2);
        const dispT3 = document.getElementById('main-sum-t3');
        if (dispT3) dispT3.innerText = this.formatMoney(sumT3);
        const dispT4 = document.getElementById('main-sum-t4');
        if (dispT4) dispT4.innerText = this.formatMoney(sumT4);
    },

    saveBudget: function (event) {
        const rows = [];
        const saveBtn = event.target;
        saveBtn.disabled = true;
        saveBtn.textContent = 'Gemmer...';

        document.querySelectorAll('.budget-card').forEach(card => {
            rows.push({
                price_catalog_id: card.querySelector('.b-catalog-id').value,
                description: card.querySelector('.b-desc').value,
                quantity: this.parseMoney(card.querySelector('.b-qty').value),
                unit: card.querySelector('.b-unit').value,
                unit_price: this.parseMoney(card.querySelector('.b-price').value),
                amount_0_1: this.parseMoney(card.querySelector('.b-t1').value),
                amount_1_2: this.parseMoney(card.querySelector('.b-t2').value),
                amount_3_5: this.parseMoney(card.querySelector('.b-t3').value),
                amount_5_10: this.parseMoney(card.querySelector('.b-t4').value)
            });
        });

        App.api('module=BuildingElement&action=savebudget', 'POST', {
            element_id: this.budgetElementId,
            items: rows
        }).then(res => {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Gem Budget';

            if (res.status === 'success') {
                if (typeof wm !== 'undefined') {
                    wm.close('budget_window_' + this.budgetElementId);
                }
                const disp = document.getElementById('capex-display');
                if (disp) disp.value = res.total_formatted;

                // Update breakdown in main view
                if (res.breakdown) {
                    if (document.getElementById('main-sum-t1')) document.getElementById('main-sum-t1').innerText = res.breakdown.sum_0_1;
                    if (document.getElementById('main-sum-t2')) document.getElementById('main-sum-t2').innerText = res.breakdown.sum_1_2;
                    if (document.getElementById('main-sum-t3')) document.getElementById('main-sum-t3').innerText = res.breakdown.sum_3_5;
                    if (document.getElementById('main-sum-t4')) document.getElementById('main-sum-t4').innerText = res.breakdown.sum_5_10;
                }

                App.toast('Budget gemt korrekt', 'success');
            } else {
                App.toast(res.message || 'Fejl ved gem', 'error');
            }
        }).catch(err => {
            saveBtn.disabled = false;
            App.toast('Netværksfejl: ' + err, 'error');
        });
    },

    acquireLock: function (id, field) {
        const payload = {
            element_id: id,
            field_name: field,
            client_id: window.TAB_CLIENT_ID || null
        };
        App.api('?module=BuildingElement&action=lockcheck', 'POST', payload)
            .then(data => {
                if (data.success === false) {
                    App.toast('⚠️ Feltet redigeres af ' + (data.locked_by || 'anden bruger'), 'warning');
                } else {
                    this.activeLocks[field] = true;
                }
            });
    },

    releaseLock: function (id, field) {
        if (this.activeLocks[field]) {
            App.api('?module=BuildingElement&action=lockrelease', 'POST', { element_id: id, field_name: field });
            delete this.activeLocks[field];
        }
    },

    updateElementField: function (id, field, value) {
        // Clear timeout if manual save triggers
        if (this.saveTimeout) clearTimeout(this.saveTimeout);

        return App.api('?module=BuildingElement&action=updatefield', 'POST', { id: id, field: field, value: value })
            .then(data => {
                if (data.status !== 'success') {
                    App.toast('Fejl ved gemning', 'error');
                }
                this.releaseLock(id, field);
                return data;
            });
    },

    saveAndReload: function (id) {
        const desc = document.querySelector('.observation-box textarea').value;
        const rec = document.querySelector('.recommendation-box textarea').value;

        App.toast('Gemmer...', 'info');

        Promise.all([
            this.updateElementField(id, 'description', desc),
            this.updateElementField(id, 'recommendation', rec)
        ]).then(() => {
            window.location.reload();
        }).catch(err => {
            alert('Fejl under gemning. Prøv igen.');
        });
    },



    // --- Version Control ---
    // --- Version Control ---
    // --- Version Control ---
    openVersionSaveModal: function () {
        // Use the structure from VersionSavePartial.php
        const currentUser = document.body.dataset.username || 'Bruger'; // Fallback

        const content = `
            <div style="padding:15px; max-height:80vh; overflow-y:auto;">
                <!-- Versioning Guide (Collapsible) -->
                <div style="background:#f0f8ff; border:1px solid #b3d9ff; border-radius:6px; padding:12px; margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; cursor:pointer;"
                        onclick="this.nextElementSibling.style.display = this.nextElementSibling.style.display === 'none' ? 'block' : 'none'">
                        <strong style="color:#0066cc;"><i class="fas fa-info-circle"></i> Versioneringsvejledning</strong>
                        <i class="fas fa-chevron-down" style="color:#0066cc;"></i>
                    </div>
                    <div style="display:none; margin-top:10px; font-size:0.9em; color:#333; line-height:1.6;">
                        <p style="margin:5px 0;"><strong>Format: X.Y</strong></p>
                        <ul style="margin:5px 0; padding-left:20px;">
                            <li><strong>Første ciffer (X.0)</strong> = Godkendelsesstadier:
                                <ul style="margin:3px 0; padding-left:20px; font-size:0.95em;">
                                    <li>1.0 = Første kladde</li>
                                    <li>2.0 = Andet udkast</li>
                                    <li>3.0 = Endelig version</li>
                                </ul>
                            </li>
                            <li><strong>Andet ciffer (X.Y)</strong> = Interne milepæle mellem godkendelser:
                                <ul style="margin:3px 0; padding-left:20px; font-size:0.95em;">
                                    <li>1.1 = Struktur opbygget</li>
                                    <li>1.2 = Redflags sat</li>
                                    <li>1.3 = Økonomi indsat</li>
                                    <li>1.4 = Tekster skrevet</li>
                                </ul>
                            </li>
                        </ul>
                        <p style="margin:8px 0 0 0; padding:8px; background:#fff6e5; border-left:3px solid #ffa500; font-style:italic;">
                            <strong>Eksempel:</strong> Version 1.3 kunne være "Første kladde med økonomi indsat",
                            mens version 2.0 ville være "Andet udkast til godkendelse"
                        </p>
                    </div>
                </div>

                <!-- Quick Version Type Selector -->
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="font-weight:600;">Hurtigvalg af versiontype</label>
                    <select id="version_type_select" class="form-control" onchange="window.fillVersionFromType(this.value)"
                        style="width:100%; padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:4px;">
                        <option value="">-- Vælg versiontype --</option>
                        <optgroup label="Godkendelsesstadier">
                            <option value="1.0|Første kladde">1.0 - Første kladde</option>
                            <option value="2.0|Andet udkast">2.0 - Andet udkast</option>
                            <option value="3.0|Endelig version">3.0 - Endelig version</option>
                        </optgroup>
                        <optgroup label="Interne milepæle (under 1.x)">
                            <option value="1.1|Struktur opbygget">1.1 - Struktur opbygget</option>
                            <option value="1.2|Redflags sat">1.2 - Redflags sat</option>
                            <option value="1.3|Økonomi indsat">1.3 - Økonomi indsat</option>
                            <option value="1.4|Tekster skrevet">1.4 - Tekster skrevet</option>
                        </optgroup>
                        <optgroup label="Interne milepæle (under 2.x)">
                            <option value="2.1|Andet udkast - struktur justeret">2.1 - Struktur justeret</option>
                            <option value="2.2|Andet udkast - feedback implementeret">2.2 - Feedback implementeret</option>
                        </optgroup>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom:15px;">
                    <label style="font-weight:600;">Version Nummer <span style="color:red">*</span></label>
                    <input type="text" id="version_number" class="form-control" placeholder="f.eks. 1.2 eller 2.0"
                        style="width:100%; padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:4px;">
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="font-weight:600;">Version Note / Beskrivelse</label>
                    <input type="text" id="version_note" class="form-control" placeholder="Beskrivelse af versionen..."
                        style="width:100%; padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:4px;">
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="font-weight:600;">Intern Bemærkning</label>
                    <textarea id="internal_remark" class="form-control" placeholder="Interne noter til teamet..."
                        style="width:100%; padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:4px; min-height:60px;"></textarea>
                </div>
                <div class="form-group" style="margin-bottom:15px;">
                    <label style="font-weight:600;">Gemt af (Navn)</label>
                    <input type="text" id="created_by_name" class="form-control" value="${currentUser}"
                        style="width:100%; padding:8px; margin-top:5px; border:1px solid #ddd; border-radius:4px;">
                </div>
            </div>
        `;

        App.modal('Gem Ny Version', content, [
            {
                text: 'Opret Version',
                type: 'primary',
                onClick: () => this.saveVersion()
            }
        ]);

        // Focus input
        setTimeout(() => {
            const el = document.getElementById('version_number');
            if (el) el.focus();
        }, 100);
    },

    saveVersion: function () {
        const numInput = document.getElementById('version_number');
        const noteInput = document.getElementById('version_note');
        const internalInput = document.getElementById('internal_remark');
        const creatorInput = document.getElementById('created_by_name');

        if (!numInput) return;

        const num = numInput.value.trim();
        const note = noteInput ? noteInput.value.trim() : '';
        const internal = internalInput ? internalInput.value.trim() : '';
        const creator = creatorInput ? creatorInput.value.trim() : '';

        if (!num) {
            App.toast('Versionsnummer er påkrævet', 'warning');
            numInput.focus();
            return;
        }

        App.api('?module=BuildingElement&action=saveVersion', 'POST', {
            project_id: this.projectId,
            version_number: num,
            version_note: note,
            internal_remark: internal,
            created_by_name: creator
        }).then(res => {
            if (res.status === 'success') {
                App.toast('Version gemt korrekt', 'success');
                App.closeModal();
            } else {
                App.toast('Fejl: ' + (res.message || 'Kunne ikke gemme'), 'error');
            }
        }).catch(err => App.toast('Systemfejl: ' + err, 'error'));
    },

    openVersionHistoryModal: function () {
        App.api('?module=BuildingElement&action=getVersions&project_id=' + this.projectId).then(res => {
            let html = '<div style="max-height:400px; overflow-y:auto;"><table class="table" style="width:100%;"><thead><tr><th align="left">Dato</th><th align="left">Note</th><th>Handling</th></tr></thead><tbody>';

            if (res.data && res.data.length > 0) {
                res.data.forEach(v => {
                    html += `<tr>
                          <td>${v.created_at}</td>
                          <td>${v.note || '-'}</td>
                          <td align="right"><button onclick="BuildingElementModule.restoreVersion(${v.id})" class="btn-sm btn-warning" style="padding:4px 8px;">Gendan</button></td>
                      </tr>`;
                });
            } else {
                html += '<tr><td colspan="3" align="center">Ingen versioner gemt endnu.</td></tr>';
            }

            html += '</tbody></table></div>';
            App.modal('Versionshistorik', html);
        });
    },

    restoreVersion: function (versionId) {
        if (!confirm('Er du sikker på at du vil gendanne denne version? Alt nuværende data overskrives!')) return;

        App.api('?module=BuildingElement&action=restoreVersion', 'POST', {
            version_id: versionId
        }).then(res => {
            if (res.status === 'success') {
                App.toast('Version gendannet. Genindlæser...', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                App.toast('Fejl: ' + res.message, 'error');
            }
        });
    },

    // --- Drag and Drop ---
    setupDragAndDrop: function () {
        const container = document.querySelector('.inspection-toc');
        if (!container) return;

        let dragSrcEl = null;

        // Drag Start
        container.addEventListener('dragstart', (e) => {
            const li = e.target.closest('li.tree-node');
            if (li) {
                dragSrcEl = li;
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', li.outerHTML);
                e.dataTransfer.setData('text/plain', li.getAttribute('data-id'));
                li.classList.add('dragging');
            }
        });

        // Drag End
        container.addEventListener('dragend', (e) => {
            const li = e.target.closest('li.tree-node');
            if (li) li.classList.remove('dragging');

            // Remove all visual cues
            document.querySelectorAll('.drop-target-child', '.drop-target-before', '.drop-target-after').forEach(el => {
                el.classList.remove('drop-target-child', 'drop-target-before', 'drop-target-after');
                el.style.borderTop = '';
                el.style.borderBottom = '';
                el.style.background = '';
            });
        });

        // Drag Over
        container.addEventListener('dragover', (e) => {
            e.preventDefault(); // Necessary. Allows us to drop.
            e.dataTransfer.dropEffect = 'move';

            // Clear previous hints (optimization: scoped)
            document.querySelectorAll('.tree-content').forEach(el => {
                el.style.borderTop = '';
                el.style.borderBottom = '';
                el.style.background = '';
            });

            const targetContent = e.target.closest('.tree-content');
            if (!targetContent) return;

            const rect = targetContent.getBoundingClientRect();
            const relY = e.clientY - rect.top;
            const height = rect.height;

            // Logic: Top 25% = Before (Sibling), Bottom 25% = After (Sibling), Middle 50% = Inside (Child)
            if (relY < height * 0.25) {
                targetContent.style.borderTop = '2px solid #3498db';
                targetContent.setAttribute('data-drop-pos', 'before');
            } else if (relY > height * 0.75) {
                targetContent.style.borderBottom = '2px solid #3498db';
                targetContent.setAttribute('data-drop-pos', 'after');
            } else {
                targetContent.style.background = 'rgba(52, 152, 219, 0.3)';
                targetContent.setAttribute('data-drop-pos', 'inside');
            }
        });

        // Drop
        container.addEventListener('drop', (e) => {
            e.stopPropagation();
            e.preventDefault();

            const targetContent = e.target.closest('.tree-content');
            if (!dragSrcEl || !targetContent) return;

            // Don't drop on self
            if (dragSrcEl.contains(targetContent)) return;
            const draggedId = dragSrcEl.getAttribute('data-id');
            const targetId = targetContent.getAttribute('data-node-id');
            if (draggedId === targetId) return;

            const pos = targetContent.getAttribute('data-drop-pos');
            const targetLi = targetContent.closest('li');

            if (pos === 'inside') {
                // Find or create UL inside target LI
                let ul = targetLi.querySelector('ul');
                if (!ul) {
                    ul = document.createElement('ul');
                    ul.style.paddingLeft = '20px';
                    targetLi.appendChild(ul);
                }
                ul.appendChild(dragSrcEl);
                // Open the node
                const toggle = targetLi.querySelector('.tree-toggle');
                if (toggle) { // Force open
                    let existingUl = targetLi.querySelector('ul');
                    if (existingUl) existingUl.style.display = 'block';
                }
            } else if (pos === 'before') {
                targetLi.parentNode.insertBefore(dragSrcEl, targetLi);
            } else if (pos === 'after') {
                targetLi.parentNode.insertBefore(dragSrcEl, targetLi.nextSibling);
            }

            // Cleanup
            targetContent.style.borderTop = '';
            targetContent.style.borderBottom = '';
            targetContent.style.background = '';

            // Renumber and Save
            this.renumberTree();
            this.saveTreeOrder();
        });
    },

    renumberTree: function () {
        // Recursive function to number nodes
        const processList = (ul, prefix = '') => {
            let index = 1;
            const lis = ul.querySelectorAll(':scope > li'); // Direct children only

            lis.forEach(li => {
                const numberSpan = li.querySelector('.tree-content .tree-number');
                const currentNum = prefix + index;

                if (numberSpan) {
                    numberSpan.textContent = currentNum;
                }

                // Process children
                const childUl = li.querySelector('ul');
                if (childUl) {
                    processList(childUl, currentNum + '.');
                }

                index++;
            });
        };

        const rootUl = document.querySelector('.inspection-toc .toc-list > ul');
        if (rootUl) {
            processList(rootUl);
        }
    },

    saveTreeOrder: function () {
        const structure = [];
        const rootUl = document.querySelector('.inspection-toc > .toc-list > ul');

        const processUl = (ul, parentId) => {
            const lis = ul.querySelectorAll(':scope > li');
            lis.forEach((li, index) => {
                const id = li.dataset.id;
                structure.push({ id: id, parent_id: parentId, sort_order: index });
                const childUl = li.querySelector('ul');
                if (childUl) processUl(childUl, id);
            });
        };

        if (rootUl) processUl(rootUl, 0);

        App.api('?module=BuildingElement&action=reorder', 'POST', { structure: structure })
            .then(res => {
                if (res.status === 'success') {
                    // App.toast('Rækkefølge gemt', 'success'); 
                } else {
                    App.toast('Fejl ved sortering', 'error');
                }
            });
    },

    // --- Canvas ---
    openCanvas: function (mediaId, urlNotUsed, elementId) {
        // mediaId is the ID in element_media. elementId is the building element ID.
        // The URL passed in might be stale if we edit multiple times, so we fetch fresh data.
        this.activeAnnotationElementId = elementId;
        this.activeMediaId = mediaId;
        const winId = 'win-canvas-editor';

        const content = `
        <div style="display:flex; flex-direction:column; height:100%;">
            <div style="background:#2c3e50; padding:10px; display:flex; justify-content:space-between; align-items:center;">
                <div class="canvas-tools" style="display:flex; gap:5px; align-items:center;">
                    <button class="btn btn-sm btn-light" title="Select" onclick="window.activeCanvas.setTool('select')"><i class="fas fa-mouse-pointer"></i></button>
                    <button class="btn btn-sm btn-secondary" title="Rectangle" onclick="window.activeCanvas.setTool('rect')"><i class="far fa-square"></i></button>
                    <button class="btn btn-sm btn-secondary" title="Circle" onclick="window.activeCanvas.setTool('circle')"><i class="far fa-circle"></i></button>
                    <button class="btn btn-sm btn-secondary" title="Arrow" onclick="window.activeCanvas.setTool('arrow')"><i class="fas fa-long-arrow-alt-right"></i></button>
                    <button class="btn btn-sm btn-secondary" title="Text" onclick="window.activeCanvas.setTool('text')"><i class="fas fa-font"></i></button>
                    
                    <div style="width:1px; background:gray; margin:0 5px; height:20px;"></div>
                    
                    <input type="number" title="Skriftstørrelse" value="24" min="10" max="1000" 
                        onchange="window.activeCanvas.setFontSize(this.value)" 
                        style="width:50px; height:28px; border-radius:3px; border:none; padding-left:5px;">
                    
                    <input type="number" title="Stregtykkelse" value="3" min="1" max="50" 
                        onchange="window.activeCanvas.setLineWidth(this.value)" 
                        style="width:50px; height:28px; border-radius:3px; border:none; padding-left:5px;">
                    
                    <input type="color" onchange="window.activeCanvas.setColor(this.value)" value="#ff0000"
                        style="width:30px; height:28px; border:none; background:transparent; cursor:pointer; padding:0;">

                    <div style="width:1px; background:gray; margin:0 5px; height:20px;"></div>
                    
                    <div style="display:flex; align-items:center; background:rgba(255,255,255,0.1); padding:2px 5px; border-radius:3px;">
                        <input type="checkbox" id="canvas-bg-check" title="Baggrund" 
                            onchange="const c = document.getElementById('canvas-bg-color'); const o = document.getElementById('canvas-bg-opacity'); if(this.checked) { c.style.display='inline-block'; o.style.display='inline-block'; window.activeCanvas.setBackgroundColor(c.value); } else { c.style.display='none'; o.style.display='none'; window.activeCanvas.setBackgroundColor(null); }">
                        <label for="canvas-bg-check" style="color:white; margin:0 5px 0 2px; font-size:12px; cursor:pointer;">Bg</label>
                        
                        <input type="color" id="canvas-bg-color" value="#ffffff" style="display:none; width:20px; height:20px; border:none; padding:0; cursor:pointer; margin-right:5px;"
                            onchange="window.activeCanvas.setBackgroundColor(this.value)">
                            
                        <input type="number" id="canvas-bg-opacity" value="100" min="0" max="100" step="10" style="display:none; width:45px; height:20px; border:none; padding-left:2px; font-size:11px;" title="Opacitet %"
                            onchange="window.activeCanvas.setBackgroundOpacity(this.value / 100)">
                    </div>

                    <div style="width:1px; background:gray; margin:0 5px; height:20px;"></div>

                    <button class="btn btn-sm btn-warning" title="Undo" onclick="window.activeCanvas.undo()"><i class="fas fa-undo"></i></button>
                    <button class="btn btn-sm btn-danger" title="Clean" onclick="window.activeCanvas.clear()"><i class="fas fa-trash"></i></button>
                    <button class="btn btn-sm btn-info" title="Rotate" onclick="window.activeCanvas.rotate(90)"><i class="fas fa-sync"></i></button>
                </div>
                <div>
                    <button class="btn btn-success btn-sm" onclick="BuildingElementModule.saveAnnotation()">💾 Gem</button>
                </div>
            </div>
            <div id="wm-canvas-container" style="flex:1; background:#333; display:flex; justify-content:center; align-items:center; overflow:hidden; position:relative;">
                <canvas id="wmEditorCanvas"></canvas>
            </div>
        </div>
        `;

        wm.createWindow({
            id: winId,
            title: 'Billederedigering',
            content: content,
            width: 900,
            height: 700,
            x: 50,
            y: 50
        });

        // Initialize
        setTimeout(() => {
            // Add style for active buttons
            const style = document.createElement('style');
            style.textContent = `
                .canvas-tools .btn.active { 
                    background: #3498db !important; 
                    color: white !important;
                    border: 1px solid #2980b9;
                }
            `;
            document.head.appendChild(style);

            App.api(`?module=BuildingElement&action=getMedia&id=${mediaId}`).then(res => {
                if (res.status === 'success') {
                    const src = res.data.src;
                    const annotations = JSON.parse(res.data.annotations || '[]');

                    const initEngine = () => {
                        window.activeCanvas = new CanvasEngine('wmEditorCanvas', src, annotations);
                        window.canvasEngine = window.activeCanvas;

                        // Set initial active button
                        const btn = document.querySelector(`.canvas-tools .btn[onclick*="'select'"]`);
                        if (btn) btn.classList.add('active');

                        // Keyboard shortcuts
                        document.addEventListener('keydown', (e) => {
                            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

                            if (e.key === 'Delete' || e.key === 'Backspace') {
                                if (window.activeCanvas && window.activeCanvas.selectedObjectIndex !== -1) {
                                    window.activeCanvas.clear();
                                }
                            }
                        });
                    };

                    if (typeof CanvasEngine !== 'undefined') {
                        initEngine();
                    } else {
                        App.loadScript('assets/js/canvas-engine.js').then(initEngine);
                    }
                } else {
                    document.getElementById('wm-canvas-container').innerHTML = '<p style="color:white">Fejl ved indlæsning af billede</p>';
                }
            });
        }, 200);

        // Update setTool to handle button classes
        const originalSetTool = CanvasEngine.prototype.setTool;
        CanvasEngine.prototype.setTool = function (tool) {
            originalSetTool.call(this, tool);
            document.querySelectorAll('.canvas-tools .btn').forEach(b => b.classList.remove('active'));
            const btn = document.querySelector(`.canvas-tools .btn[onclick*="'${tool}'"]`);
            if (btn) btn.classList.add('active');
        };
    },

    saveAnnotation: function () {
        if (!window.activeCanvas) {
            App.toast('Ingen aktiv canvas fundet', 'error');
            return;
        }

        const mediaId = this.activeMediaId;
        const elementId = this.activeAnnotationElementId;

        if (!mediaId) {
            App.toast('Fejl: Mangler Billede-ID', 'error');
            return;
        }

        // UI Feedback: Disable Save Button
        const saveBtn = document.querySelector('.btn-success[onclick="BuildingElementModule.saveAnnotation()"]');
        let originalText = '💾 Gem';
        if (saveBtn) {
            originalText = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gemmer...';
        }

        App.toast('Gemmer billede...', 'info');

        try {
            // Use 0.7 quality for better file size
            const dataUrl = window.activeCanvas.exportHighRes('image/jpeg', 0.7);
            const jsonData = window.activeCanvas.save();
            const blob = this.dataURItoBlob(dataUrl);

            const form = new FormData();
            form.append('id', mediaId);
            form.append('annotations', jsonData);
            form.append('image', blob, 'annotated.jpg');

            App.api('?module=BuildingElement&action=saveImageAnnotation', 'POST', form)
                .then(res => {
                    if (res.status === 'success') {
                        if (typeof wm !== 'undefined') {
                            wm.close('win-canvas-editor');
                        }

                        // Reload content
                        const container = document.querySelector(`.building-element[data-id="${elementId}"] .element-content`);
                        if (container) {
                            BuildingElementModule.loadNodeContent(elementId, container);
                        }
                        App.toast('Billede gemt', 'success');
                    } else {
                        App.toast('Kunne ikke gemme billede', 'error');
                        if (saveBtn) {
                            saveBtn.disabled = false;
                            saveBtn.innerHTML = originalText;
                        }
                    }
                })
                .catch(err => {
                    console.error(err);
                    App.toast('Fejl ved preservering', 'error');
                    if (saveBtn) {
                        saveBtn.disabled = false;
                        saveBtn.innerHTML = originalText;
                    }
                });
        } catch (e) {
            console.error(e);
            App.toast('Fejl under generering af billede', 'error');
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalText;
            }
        }
    },

    updateMediaCaption: function (mediaId, caption) {
        const form = new FormData();
        form.append('id', mediaId);
        form.append('caption', caption);

        App.api('?module=BuildingElement&action=saveMediaCaption', 'POST', form)
            .then(res => {
                if (res.status !== 'success') App.toast('Fejl', 'error');
            })
            .catch(e => App.toast('Systemfejl', 'error'));
    },


    // --- Image Sorting & Upload ---
    setupImageSort: function () {
        const container = document.getElementById('sortable-images');
        if (!container) {
            console.warn('setupImageSort: sortable-images container not found');
            return;
        }

        // Remove existing listeners to prevent duplicates
        const existingItems = container.querySelectorAll('.media-item, .empty-slot');
        existingItems.forEach(item => {
            const clone = item.cloneNode(true);
            item.parentNode.replaceChild(clone, item);
        });

        // Initializing image drag-and-drop

        // Add CSS for dragging feedback
        const style = document.createElement('style');
        style.textContent = `
            .media-item.dragging {
                opacity: 0.5;
                cursor: grabbing !important;
            }
            .media-item.drag-over .image-slot {
                border-color: #007bff !important;
                border-width: 3px !important;
                background: rgba(0, 123, 255, 0.1);
            }
            .empty-slot.drag-over {
                border-color: #28a745 !important;
                border-width: 3px !important;
                background: rgba(40, 167, 69, 0.1) !important;
            }
            .drag-handle {
                transition: all 0.2s;
            }
            .media-item:hover .drag-handle {
                background: rgba(0, 123, 255, 0.9) !important;
            }
        `;
        if (!document.getElementById('drag-styles')) {
            style.id = 'drag-styles';
            document.head.appendChild(style);
        }

        let dragSrcEl = null;

        // Drag Start - only for media items
        const handleDragStart = (e) => {
            dragSrcEl = e.currentTarget;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', dragSrcEl.outerHTML);
            dragSrcEl.classList.add('dragging');
            dragSrcEl.style.cursor = 'grabbing';
        };

        const handleDragOver = (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
        };

        const handleDragEnter = (e) => {
            const target = e.currentTarget;
            if (target !== dragSrcEl) {
                target.classList.add('drag-over');
            }
        };

        const handleDragLeave = (e) => {
            e.currentTarget.classList.remove('drag-over');
        };

        const handleDrop = (e) => {
            e.stopPropagation();
            e.preventDefault();

            const dropTarget = e.currentTarget;
            dropTarget.classList.remove('drag-over');

            if (dragSrcEl && dropTarget.classList.contains('media-item') && dragSrcEl !== dropTarget) {
                // Simple swap: insert dragSrcEl before dropTarget
                dropTarget.parentNode.insertBefore(dragSrcEl, dropTarget);
                this.saveImageOrder();
                App.toast('Rækkefølge opdateret', 'success');
            }
        };

        const handleDragEnd = (e) => {
            container.querySelectorAll('.media-item, .empty-slot').forEach(item => {
                item.classList.remove('drag-over', 'dragging');
            });
            if (dragSrcEl) {
                dragSrcEl.style.cursor = 'grab';
                dragSrcEl = null;
            }
        };

        // Setup media item drag listeners
        const mediaItems = container.querySelectorAll('.media-item');
        // Found media items for drag-and-drop
        mediaItems.forEach(item => {
            item.addEventListener('dragstart', handleDragStart, false);
            item.addEventListener('dragenter', handleDragEnter, false);
            item.addEventListener('dragover', handleDragOver, false);
            item.addEventListener('dragleave', handleDragLeave, false);
            item.addEventListener('drop', handleDrop, false);
            item.addEventListener('dragend', handleDragEnd, false);
        });

        // Setup file drop on empty slots
        const emptySlots = container.querySelectorAll('.empty-slot');
        // Found empty slots for drag-and-drop
        emptySlots.forEach(slot => {
            slot.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                e.dataTransfer.dropEffect = 'copy';
                slot.classList.add('drag-over');
            });

            slot.addEventListener('dragleave', (e) => {
                e.preventDefault();
                slot.classList.remove('drag-over');
            });

            slot.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                slot.classList.remove('drag-over');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    const elementId = container.dataset.elementId;
                    const projectId = container.dataset.projectId;
                    this.uploadFiles(files, elementId, projectId);
                }
            });
        });
    },

    saveImageOrder: function () {
        const container = document.getElementById('sortable-images');
        const items = container.querySelectorAll('.media-item');
        const order = [];
        items.forEach((item, index) => {
            order.push({
                id: item.dataset.mediaId,
                sort_order: index
            });
        });

        // Backend save
        App.api('?module=BuildingElement&action=reorderMedia', 'POST', { media_order: order })
            .then(res => {
                if (res.status === 'success') {
                    // App.toast('Billedrækkefølge gemt', 'success');
                } else {
                    App.toast('Fejl ved sortering af billeder', 'error');
                }
            });
    },

    // --- Media Upload / Handling ---
    uploadFiles: async function (files, elementId, projectId) {
        if (!files || files.length === 0) return;

        // 1. Create/Reset Progress Bar
        let pBar = document.getElementById('upload-progress-bar');
        let pContainer = document.getElementById('upload-progress-container');

        if (!pContainer) {
            pContainer = document.createElement('div');
            pContainer.id = 'upload-progress-container';
            pContainer.style.position = 'fixed';
            pContainer.style.bottom = '20px';
            pContainer.style.right = '20px';
            pContainer.style.width = '300px';
            pContainer.style.padding = '15px';
            pContainer.style.background = '#fff';
            pContainer.style.boxShadow = '0 0 10px rgba(0,0,0,0.2)';
            pContainer.style.borderRadius = '8px';
            pContainer.style.zIndex = '9999';
            pContainer.style.display = 'none';

            const label = document.createElement('div');
            label.innerText = 'Uploader billeder...';
            label.style.marginBottom = '5px';
            label.style.fontWeight = 'bold';
            pContainer.appendChild(label);

            const barBg = document.createElement('div');
            barBg.style.width = '100%';
            barBg.style.height = '10px';
            barBg.style.background = '#eee';
            barBg.style.borderRadius = '5px';
            barBg.style.overflow = 'hidden';

            pBar = document.createElement('div');
            pBar.id = 'upload-progress-bar';
            pBar.style.width = '0%';
            pBar.style.height = '100%';
            pBar.style.background = '#4CAF50';
            pBar.style.transition = 'width 0.3s';

            barBg.appendChild(pBar);
            pContainer.appendChild(barBg);
            document.body.appendChild(pContainer);
        }

        pBar.style.width = '0%';
        pContainer.style.display = 'block';

        const form = new FormData();
        form.append('id', elementId);
        form.append('project_id', projectId);

        // 2. Compress Images
        App.toast('Komprimerer billeder...', 'info');

        try {
            for (let i = 0; i < files.length; i++) {
                const file = files[i];
                if (file.type.startsWith('image/')) {
                    const compressed = await this.compressImage(file);
                    form.append('images[]', compressed, file.name);
                } else {
                    form.append('images[]', file);
                }
            }
        } catch (e) {
            console.error('Compression error:', e);
            App.toast('Fejl under komprimering', 'error');
            pContainer.style.display = 'none';
            return;
        }

        // 3. Upload with Progress via XHR
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '?module=BuildingElement&action=addimage', true);

        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) {
                const percent = (e.loaded / e.total) * 100;
                pBar.style.width = percent + '%';
            }
        };

        xhr.onload = () => {
            pContainer.style.display = 'none';
            if (xhr.status === 200) {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.status === 'success') {
                        App.toast('Upload gennemført', 'success');
                        this.loadNodeContent(elementId);
                    } else {
                        App.toast('Fejl ved upload: ' + (res.message || 'Ukendt fejl'), 'error');
                    }
                } catch (e) {
                    App.toast('Ugyldigt svar fra server', 'error');
                }
            } else {
                App.toast('Upload fejlede (HTTP ' + xhr.status + ')', 'error');
            }
        };

        xhr.onerror = () => {
            pContainer.style.display = 'none';
            App.toast('Netværksfejl under upload', 'error');
        };

        xhr.send(form);
    },

    compressImage: function (file) {
        return new Promise((resolve, reject) => {
            const maxWidth = 1920;
            const quality = 0.7;
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = (event) => {
                const img = new Image();
                img.src = event.target.result;
                img.onload = () => {
                    const elem = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;

                    if (width > maxWidth) {
                        height = Math.round(height * (maxWidth / width));
                        width = maxWidth;
                    }

                    elem.width = width;
                    elem.height = height;
                    const ctx = elem.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    ctx.canvas.toBlob((blob) => {
                        resolve(blob);
                    }, 'image/jpeg', quality);
                };
                img.onerror = (err) => reject(err);
            };
            reader.onerror = (err) => reject(err);
        });
    },

    deleteElement: function (elementId) {
        if (!confirm('Er du sikker på at du vil slette dette element? Dette kan ikke fortrydes.')) return;

        App.api('?module=BuildingElement&action=delete', 'POST', { id: elementId })
            .then(res => {
                if (res.status === 'success') {
                    App.toast('Element slettet', 'success');
                    // Reload tree/page logic. Ideally just reload page or remove node.
                    // Simple reload:
                    window.location.reload();
                } else {
                    App.toast('Fejl ved sletning: ' + res.message, 'error');
                }
            });
    },

    deleteImage: function (mediaId, silent = false) {
        if (!silent && !confirm('Er du sikker på at du vil slette dette billede permanent?')) return;

        if (!silent) App.toast('Sletter billede...', 'info');

        App.api('?module=BuildingElement&action=deleteMedia', 'POST', { id: mediaId })
            .then(res => {
                if (res.status === 'success') {
                    if (!silent) App.toast('Billede slettet', 'success');

                    // Reload content to update view
                    const container = document.getElementById('sortable-images');
                    const elementId = container ? container.dataset.elementId : null;
                    if (elementId) {
                        // Use module reference
                        BuildingElementModule.loadNodeContent(elementId);
                    } else {
                        // Only reload page if we can't refresh locally
                        // But wait, if we are in a modal?
                        // If silent (saveAnnotation), we handle reload there.
                        if (!silent) window.location.reload();
                    }
                } else {
                    if (!silent) App.toast('Fejl ved sletning: ' + (res.message || 'Ukendt fejl'), 'error');
                }
            })
            .catch(e => {
                if (!silent) App.toast('Systemfejl: ' + e, 'error');
            });
    },

    dataURItoBlob: function (dataURI) {
        var byteString = atob(dataURI.split(',')[1]);
        var mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];
        var ab = new ArrayBuffer(byteString.length);
        var ia = new Uint8Array(ab);
        for (var i = 0; i < byteString.length; i++) {
            ia[i] = byteString.charCodeAt(i);
        }
        return new Blob([ab], { type: mimeString });
    }
};

// Global Exposure
window.deleteImage = (id) => BuildingElementModule.deleteImage(id);
// Cache bust 10
