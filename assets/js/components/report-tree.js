/**
 * Report Tree View Component
 * Hierarchical tree structure for buildings and elements with drag-drop sorting
 * Automatic cost summation up the hierarchy
 */

const ReportTree = {
    currentProjectId: null,
    treeData: null,
    expandedNodes: new Set(),

    /**
     * Initialize tree view for a project
     * @param {number} projectId - Project ID
     * @param {string} containerSelector - Container element selector
     */
    async init(projectId, containerSelector) {
        this.currentProjectId = projectId;
        const container = document.querySelector(containerSelector);

        if (!container) {
            console.error('Report tree container not found:', containerSelector);
            return;
        }

        container.innerHTML = '<div class="tree-loading"><div class="spinner-small"></div> Indlæser træstruktur...</div>';

        try {
            // Load hierarchical data from API
            const response = await API.get('/api.php', {
                action: 'get_project_tree',
                project_id: projectId
            });

            if (response.success) {
                this.treeData = response.tree;
                this.render(container);
            } else {
                container.innerHTML = `<div class="tree-error">${escapeHtml(response.error)}</div>`;
            }
        } catch (error) {
            console.error('Tree load error:', error);
            container.innerHTML = '<div class="tree-error">Kunne ikke indlæse træstruktur</div>';
        }
    },

    /**
     * Render tree structure
     */
    render(container) {
        if (!this.treeData || !this.treeData.buildings || this.treeData.buildings.length === 0) {
            container.innerHTML = `
                <div class="tree-empty">
                    ${this.getIcon('building', 48)}
                    <p>Ingen bygninger i projektet</p>
                </div>
            `;
            return;
        }

        let html = '<div class="report-tree">';

        // Project header
        html += `
            <div class="tree-header">
                <h3>${escapeHtml(this.treeData.project_name)}</h3>
                <div class="tree-total">
                    <span class="total-label">Total CAPEX:</span>
                    <span class="total-value">${this.formatMoney(this.treeData.total_capex)}</span>
                </div>
            </div>
        `;

        // Buildings and their elements
        html += '<div class="tree-nodes" id="treeNodes">';

        this.treeData.buildings.forEach(building => {
            html += this.renderBuildingNode(building);
        });

        html += '</div>';
        html += '</div>';

        container.innerHTML = html;

        // Initialize drag-and-drop for sortable elements
        this.initDragDrop();
    },

    /**
     * Render building node
     */
    renderBuildingNode(building) {
        const isExpanded = this.expandedNodes.has(`building-${building.id}`) !== false;
        const hasElements = building.elements && building.elements.length > 0;

        let html = `
            <div class="tree-node building-node" data-type="building" data-id="${building.id}">
                <div class="node-header">
                    ${hasElements ? `
                        <button class="node-toggle ${isExpanded ? 'expanded' : ''}"
                                onclick="ReportTree.toggleNode('building-${building.id}')">
                            ${this.getIcon('chevron-right', 16)}
                        </button>
                    ` : '<span class="node-spacer"></span>'}

                    <span class="node-icon building-icon">${this.getIcon('building', 18)}</span>
                    <span class="node-label">${escapeHtml(building.name)}</span>

                    <div class="node-stats">
                        <span class="stat-elements">${building.element_count || 0} elementer</span>
                        <span class="stat-capex">${this.formatMoney(building.total_capex)}</span>
                    </div>
                </div>

                ${hasElements && isExpanded ? `
                    <div class="node-children building-elements" data-building-id="${building.id}">
                        ${this.renderElements(building.elements, 1)}
                    </div>
                ` : ''}
            </div>
        `;

        return html;
    },

    /**
     * Render elements recursively
     */
    renderElements(elements, level) {
        let html = '';

        elements.forEach(element => {
            html += this.renderElementNode(element, level);
        });

        return html;
    },

    /**
     * Render single element node
     */
    renderElementNode(element, level) {
        const isExpanded = this.expandedNodes.has(`element-${element.id}`) !== false;
        const hasChildren = element.children && element.children.length > 0;
        const indent = level * 20;

        let html = `
            <div class="tree-node element-node"
                 data-type="element"
                 data-id="${element.id}"
                 data-parent-id="${element.parent_id || ''}"
                 data-level="${level}"
                 style="padding-left: ${indent}px;">

                <div class="node-header">
                    <span class="drag-handle" title="Træk for at flytte">${this.getIcon('menu', 14)}</span>

                    ${hasChildren ? `
                        <button class="node-toggle ${isExpanded ? 'expanded' : ''}"
                                onclick="ReportTree.toggleNode('element-${element.id}')">
                            ${this.getIcon('chevron-right', 14)}
                        </button>
                    ` : '<span class="node-spacer"></span>'}

                    <span class="node-icon element-icon">${this.getIcon('box', 16)}</span>
                    <span class="node-label">${escapeHtml(element.name)}</span>

                    <div class="node-stats">
                        ${element.urgency ? `<span class="urgency-badge urgency-${element.urgency}">${element.urgency}</span>` : ''}
                        ${element.unit ? `<span class="stat-unit">${escapeHtml(element.unit)}</span>` : ''}
                        ${element.quantity ? `<span class="stat-quantity">${element.quantity}</span>` : ''}
                        <span class="stat-capex">${this.formatMoney(element.total_capex || element.capex)}</span>
                    </div>

                    <div class="node-actions">
                        <button class="btn-icon-mini" onclick="ReportTree.editElement(${element.id})" title="Rediger">
                            ${this.getIcon('edit', 14)}
                        </button>
                    </div>
                </div>

                ${hasChildren && isExpanded ? `
                    <div class="node-children element-children">
                        ${this.renderElements(element.children, level + 1)}
                    </div>
                ` : ''}
            </div>
        `;

        return html;
    },

    /**
     * Toggle node expansion
     */
    toggleNode(nodeId) {
        if (this.expandedNodes.has(nodeId)) {
            this.expandedNodes.delete(nodeId);
        } else {
            this.expandedNodes.add(nodeId);
        }

        // Re-render tree
        const container = document.querySelector('.report-tree-container');
        if (container) {
            this.render(container);
        }
    },

    /**
     * Initialize drag-and-drop for tree nodes
     */
    initDragDrop() {
        const elementNodes = document.querySelectorAll('.element-node');
        let draggedElement = null;
        let originalParent = null;
        let originalNextSibling = null;

        elementNodes.forEach(node => {
            const dragHandle = node.querySelector('.drag-handle');
            if (!dragHandle) return;

            node.draggable = true;

            node.addEventListener('dragstart', (e) => {
                draggedElement = node;
                originalParent = node.parentElement;
                originalNextSibling = node.nextElementSibling;
                node.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            node.addEventListener('dragend', (e) => {
                node.classList.remove('dragging');

                // Check if position changed
                const newParent = draggedElement.parentElement;
                if (newParent !== originalParent || draggedElement.nextElementSibling !== originalNextSibling) {
                    this.handleDrop(draggedElement);
                }

                draggedElement = null;
            });

            node.addEventListener('dragover', (e) => {
                e.preventDefault();

                if (!draggedElement || draggedElement === node) return;

                const parent = node.parentElement;
                const afterElement = this.getDragAfterElement(parent, e.clientY);

                if (afterElement == null) {
                    parent.appendChild(draggedElement);
                } else {
                    parent.insertBefore(draggedElement, afterElement);
                }
            });
        });
    },

    /**
     * Get element after drag position
     */
    getDragAfterElement(container, y) {
        const draggableElements = [...container.querySelectorAll('.element-node:not(.dragging)')];

        return draggableElements.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;

            if (offset < 0 && offset > closest.offset) {
                return { offset: offset, element: child };
            } else {
                return closest;
            }
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    },

    /**
     * Handle drop - save new order and hierarchy
     */
    async handleDrop(element) {
        const elementId = element.dataset.id;
        const newParent = element.parentElement;
        const newParentId = newParent.dataset.buildingId || newParent.closest('.element-node')?.dataset.id || null;

        // Get new order within parent
        const siblings = [...newParent.querySelectorAll('.element-node')];
        const newOrder = siblings.map(node => node.dataset.id);

        try {
            const formData = new FormData();
            formData.append('action', 'update_element_hierarchy');
            formData.append('element_id', elementId);
            formData.append('parent_id', newParentId || '');
            formData.append('order', JSON.stringify(newOrder));

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Hierarki opdateret');
                // Reload tree to get updated totals
                this.init(this.currentProjectId, '.report-tree-container');
            } else {
                Toast.error(response.error || 'Kunne ikke opdatere hierarki');
                // Revert
                this.init(this.currentProjectId, '.report-tree-container');
            }
        } catch (error) {
            console.error('Hierarchy update error:', error);
            Toast.error('Netværksfejl ved opdatering');
            // Revert
            this.init(this.currentProjectId, '.report-tree-container');
        }
    },

    /**
     * Edit element
     */
    editElement(elementId) {
        // Navigate to building element module with edit action
        navigate('building_element', { action: 'get', id: elementId });
    },

    /**
     * Format money
     */
    formatMoney(amount) {
        if (!amount || amount === 0) return '-';
        return new Intl.NumberFormat('da-DK', {
            style: 'currency',
            currency: 'DKK',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    },

    /**
     * Get icon SVG
     */
    getIcon(name, size) {
        return `<svg width="${size}" height="${size}" class="icon"><use href="#icon-${name}"></use></svg>`;
    }
};

// Export globally
window.ReportTree = ReportTree;
