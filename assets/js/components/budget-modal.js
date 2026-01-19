
/**
 * Budget Modal System
 * For CAPEX, OPEX, and Reinstatement cost line-by-line entry
 * with template and price catalog lookup
 */
const BudgetModal = {
    elementId: null,
    budgetType: 'capex',
    lines: [],

    /**
     * Open budget modal for an element
     */
    async open(elementId, budgetType = 'capex') {
        this.elementId = elementId;
        this.budgetType = budgetType;

        // Load existing budget lines
        await this.loadBudgetLines();

        // Show modal
        this.render();
    },

    /**
     * Load existing budget lines from backend
     */
    async loadBudgetLines() {
        try {
            const response = await fetch(`/api.php?action=get_budget_lines&element_id=${this.elementId}&budget_type=${this.budgetType}`);
            const data = await response.json();

            if (data.success) {
                this.lines = data.lines || [];
            } else {
                this.lines = [];
            }
        } catch (error) {
            console.error('Failed to load budget lines:', error);
            this.lines = [];
        }
    },

    /**
     * Render budget modal
     */
    render() {
        const typeLabels = {
            'capex': 'CAPEX',
            'opex': 'OPEX',
            'reinstatement': 'Genoprettelsesomkostninger'
        };

        const modalHTML = `
            <div class="modal-overlay" id="budgetModal" onclick="if(event.target===this) BudgetModal.close()">
                <div class="modal-large" onclick="event.stopPropagation()">
                    <div class="modal-header">
                        <h2>Budget & ${typeLabels[this.budgetType]}</h2>
                        <button class="btn-close" onclick="BudgetModal.close()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <!-- Toolbar -->
                        <div class="budget-toolbar">
                            <button class="btn-secondary btn-sm" onclick="BudgetModal.addLine()">
                                ${this.getIcon('plus', 16)} Tilføj linje
                            </button>
                            <button class="btn-secondary btn-sm" onclick="BudgetModal.showPriceCatalog()">
                                ${this.getIcon('search', 16)} Søg priser
                            </button>
                            <button class="btn-secondary btn-sm" onclick="BudgetModal.showTemplates()">
                                ${this.getIcon('file-text', 16)} Indlæs template
                            </button>
                        </div>

                        <!-- Budget Lines Table -->
                        <div class="budget-table-container">
                            <table class="budget-table">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;">#</th>
                                        <th style="width: 250px;">Beskrivelse</th>
                                        <th style="width: 80px;">Mængde</th>
                                        <th style="width: 80px;">Enhed</th>
                                        <th style="width: 100px;">Pris DKK</th>
                                        <th style="width: 90px;">&lt; 1 år</th>
                                        <th style="width: 90px;">1-2 år</th>
                                        <th style="width: 90px;">3-5 år</th>
                                        <th style="width: 90px;">5-10 år</th>
                                        <th style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="budgetLinesBody">
                                    ${this.renderLines()}
                                </tbody>
                                <tfoot>
                                    <tr class="total-row">
                                        <td colspan="4"><strong>Total ${typeLabels[this.budgetType]}</strong></td>
                                        <td id="totalCapex"><strong>0,00</strong></td>
                                        <td id="totalYear01">0,00</td>
                                        <td id="totalYear12">0,00</td>
                                        <td id="totalYear35">0,00</td>
                                        <td id="totalYear510">0,00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn-secondary" onclick="BudgetModal.close()">Annuller</button>
                        <button class="btn-primary" onclick="BudgetModal.save()">
                            ${this.getIcon('save', 16)} Gem Budget
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Remove existing modal if any
        const existing = document.getElementById('budgetModal');
        if (existing) existing.remove();

        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modalHTML);

        // Calculate and update totals
        this.updateTotals();

        // Add input event listeners for live updates
        setTimeout(() => this.attachEventListeners(), 100);
    },

    /**
     * Render budget lines
     */
    renderLines() {
        if (this.lines.length === 0) {
            return `
                <tr>
                    <td colspan="10" class="text-center text-muted">
                        Ingen budgetlinjer endnu. Klik "Tilføj linje" for at komme i gang.
                    </td>
                </tr>
            `;
        }

        return this.lines.map((line, index) => `
            <tr data-line-index="${index}">
                <td>${index + 1}</td>
                <td>
                    <input type="text"
                           class="form-control form-control-sm"
                           data-field="description"
                           value="${this.escapeHtml(line.description || '')}"
                           placeholder="Beskrivelse...">
                </td>
                <td>
                    <input type="number"
                           step="0.001"
                           class="form-control form-control-sm text-right"
                           data-field="quantity"
                           value="${line.quantity || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <select class="form-control form-control-sm" data-field="unit">
                        ${this.renderUnitOptions(line.unit || 'stk')}
                    </select>
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right"
                           data-field="price_per_unit"
                           value="${line.price_per_unit || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right phase-input"
                           data-field="year_0_1"
                           value="${line.year_0_1 || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right phase-input"
                           data-field="year_1_2"
                           value="${line.year_1_2 || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right phase-input"
                           data-field="year_3_5"
                           value="${line.year_3_5 || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <input type="number"
                           step="0.01"
                           class="form-control form-control-sm text-right phase-input"
                           data-field="year_5_10"
                           value="${line.year_5_10 || 0}"
                           onchange="BudgetModal.updateTotals()">
                </td>
                <td>
                    <button class="btn-icon btn-danger btn-sm"
                            onclick="BudgetModal.deleteLine(${index})"
                            title="Slet linje">
                        ${this.getIcon('trash', 14)}
                    </button>
                </td>
            </tr>
        `).join('');
    },

    /**
     * Render unit select options
     */
    renderUnitOptions(selectedUnit) {
        const units = ['stk', 'm2', 'm', 'm3', 'kg', 'ton', 'l', 'time', 'pauschalt'];
        return units.map(unit =>
            `<option value="${unit}" ${unit === selectedUnit ? 'selected' : ''}>${unit}</option>`
        ).join('');
    },

    /**
     * Add a new empty line
     */
    addLine() {
        this.lines.push({
            description: '',
            quantity: 0,
            unit: 'stk',
            price_per_unit: 0,
            year_0_1: 0,
            year_1_2: 0,
            year_3_5: 0,
            year_5_10: 0,
            year_10_plus: 0
        });

        this.refreshLines();
    },

    /**
     * Delete a line
     */
    deleteLine(index) {
        if (confirm('Er du sikker på at du vil slette denne linje?')) {
            this.lines.splice(index, 1);
            this.refreshLines();
        }
    },

    /**
     * Refresh lines display
     */
    refreshLines() {
        const tbody = document.getElementById('budgetLinesBody');
        if (tbody) {
            tbody.innerHTML = this.renderLines();
            this.attachEventListeners();
            this.updateTotals();
        }
    },

    /**
     * Attach event listeners to inputs
     */
    attachEventListeners() {
        const rows = document.querySelectorAll('#budgetLinesBody tr[data-line-index]');

        rows.forEach((row, index) => {
            const inputs = row.querySelectorAll('input, select');

            inputs.forEach(input => {
                input.addEventListener('input', (e) => {
                    const field = e.target.dataset.field;
                    let value = e.target.value;

                    // Convert numeric fields
                    if (['quantity', 'price_per_unit', 'year_0_1', 'year_1_2', 'year_3_5', 'year_5_10', 'year_10_plus'].includes(field)) {
                        value = parseFloat(value) || 0;
                    }

                    this.lines[index][field] = value;
                });
            });
        });
    },

    /**
     * Update totals
     */
    updateTotals() {
        let totalCapex = 0;
        let totalYear01 = 0;
        let totalYear12 = 0;
        let totalYear35 = 0;
        let totalYear510 = 0;

        this.lines.forEach(line => {
            const qty = parseFloat(line.quantity) || 0;
            const price = parseFloat(line.price_per_unit) || 0;
            totalCapex += qty * price;

            totalYear01 += parseFloat(line.year_0_1) || 0;
            totalYear12 += parseFloat(line.year_1_2) || 0;
            totalYear35 += parseFloat(line.year_3_5) || 0;
            totalYear510 += parseFloat(line.year_5_10) || 0;
        });

        // Update footer
        const formatNumber = (num) => new Intl.NumberFormat('da-DK', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(num);

        document.getElementById('totalCapex').innerHTML = `<strong>${formatNumber(totalCapex)}</strong>`;
        document.getElementById('totalYear01').textContent = formatNumber(totalYear01);
        document.getElementById('totalYear12').textContent = formatNumber(totalYear12);
        document.getElementById('totalYear35').textContent = formatNumber(totalYear35);
        document.getElementById('totalYear510').textContent = formatNumber(totalYear510);
    },

    /**
     * Show price catalog search modal
     */
    async showPriceCatalog() {
        const searchHTML = `
            <div class="price-catalog-search">
                <input type="text"
                       id="priceSearchInput"
                       class="form-control mb-3"
                       placeholder="Søg efter priser..."
                       onkeyup="BudgetModal.searchPrices(this.value)">
                <div id="priceSearchResults" style="max-height: 400px; overflow-y: auto;">
                    Indtast søgeord for at finde priser...
                </div>
            </div>
        `;

        Modal.show('Søg i priskatalog', searchHTML, {
            width: '600px'
        });
    },

    /**
     * Search prices in catalog
     */
    async searchPrices(query) {
        if (query.length < 2) {
            document.getElementById('priceSearchResults').innerHTML = 'Indtast mindst 2 tegn...';
            return;
        }

        try {
            const response = await fetch(`/api.php?action=search_price_catalog&q=${encodeURIComponent(query)}`);
            const data = await response.json();

            if (data.success && data.items.length > 0) {
                const html = `
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Navn</th>
                                <th>Enhed</th>
                                <th>Pris</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${data.items.map(item => `
                                <tr>
                                    <td>
                                        <strong>${this.escapeHtml(item.name)}</strong><br>
                                        <small class="text-muted">${this.escapeHtml(item.description || '')}</small>
                                    </td>
                                    <td>${this.escapeHtml(item.unit)}</td>
                                    <td>${parseFloat(item.price_per_unit).toFixed(2)} kr</td>
                                    <td>
                                        <button class="btn-primary btn-sm" onclick="BudgetModal.addPriceToLine(${item.id}, '${this.escapeHtml(item.name)}', '${item.unit}', ${item.price_per_unit})">
                                            Tilføj
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                `;
                document.getElementById('priceSearchResults').innerHTML = html;
            } else {
                document.getElementById('priceSearchResults').innerHTML = '<p class="text-muted">Ingen resultater fundet</p>';
            }
        } catch (error) {
            console.error('Price search error:', error);
            document.getElementById('priceSearchResults').innerHTML = '<p class="text-danger">Fejl ved søgning</p>';
        }
    },

    /**
     * Add price catalog item to budget
     */
    addPriceToLine(catalogId, name, unit, price) {
        this.lines.push({
            description: name,
            quantity: 1,
            unit: unit,
            price_per_unit: price,
            price_catalog_id: catalogId,
            year_0_1: 0,
            year_1_2: 0,
            year_3_5: 0,
            year_5_10: 0,
            year_10_plus: 0
        });

        Modal.close();
        this.refreshLines();
        Toast.success('Pris tilføjet til budget');
    },

    /**
     * Show templates modal
     */
    async showTemplates() {
        try {
            const response = await fetch(`/api.php?action=get_budget_templates`);
            const data = await response.json();

            if (data.success && data.templates.length > 0) {
                const html = `
                    <div class="templates-list">
                        ${data.templates.map(template => `
                            <div class="template-card" style="border: 1px solid #ddd; padding: 1rem; margin-bottom: 1rem; border-radius: 4px;">
                                <h4>${this.escapeHtml(template.name)}</h4>
                                <p class="text-muted">${this.escapeHtml(template.description || '')}</p>
                                <div class="mt-2">
                                    <span class="badge">${this.escapeHtml(template.category || 'General')}</span>
                                    ${template.is_public ? '<span class="badge badge-success">Offentlig</span>' : '<span class="badge badge-secondary">Privat</span>'}
                                </div>
                                <button class="btn-primary btn-sm mt-2" onclick="BudgetModal.loadTemplate(${template.id})">
                                    Indlæs template
                                </button>
                            </div>
                        `).join('')}
                    </div>
                `;

                Modal.show('Vælg budget template', html, {
                    width: '600px'
                });
            } else {
                Modal.show('Vælg budget template', '<p class="text-muted">Ingen templates tilgængelige</p>');
            }
        } catch (error) {
            console.error('Template load error:', error);
            Toast.error('Kunne ikke indlæse templates');
        }
    },

    /**
     * Load template into budget
     */
    async loadTemplate(templateId) {
        try {
            const formData = new FormData();
            formData.append('action', 'load_budget_template');
            formData.append('template_id', templateId);
            formData.append('element_id', this.elementId);
            formData.append('budget_type', this.budgetType);

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success(response.message || 'Template indlæst');
                Modal.close();

                // Reload budget lines
                await this.loadBudgetLines();
                this.refreshLines();
            } else {
                Toast.error(response.error || 'Kunne ikke indlæse template');
            }
        } catch (error) {
            console.error('Template load error:', error);
            Toast.error('Fejl ved indlæsning af template');
        }
    },

    /**
     * Save budget to backend
     */
    async save() {
        try {
            const formData = new FormData();
            formData.append('action', 'save_budget_lines');
            formData.append('element_id', this.elementId);
            formData.append('budget_type', this.budgetType);
            formData.append('lines', JSON.stringify(this.lines));

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Budget gemt');
                this.close();

                // Reload page to reflect updated totals
                window.location.reload();
            } else {
                Toast.error(response.error || 'Kunne ikke gemme budget');
            }
        } catch (error) {
            console.error('Budget save error:', error);
            Toast.error('Fejl ved gemning af budget');
        }
    },

    /**
     * Close modal
     */
    close() {
        const modal = document.getElementById('budgetModal');
        if (modal) {
            modal.remove();
        }
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    /**
     * Get icon SVG
     */
    getIcon(name, size) {
        return `<svg width="${size}" height="${size}" class="icon"><use href="#icon-${name}"></use></svg>`;
