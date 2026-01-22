/**
 * CAPEX Summary Card Component
 *
 * Interactive collapsible cards showing CAPEX distribution
 * across time intervals with detailed breakdowns
 *
 * @version 1.0.0
 * @author Claude Code
 */

class CapexSummaryCard {
    constructor(container, data, options = {}) {
        this.container = typeof container === 'string'
            ? document.querySelector(container)
            : container;

        if (!this.container) {
            console.error('[CapexSummaryCard] Container not found');
            return;
        }

        this.data = data;
        this.options = {
            currency: options.currency || 'DKK',
            locale: options.locale || 'da-DK',
            showDetails: options.showDetails !== false,
            showCharts: options.showCharts !== false,
            expandAll: options.expandAll || false,
            onExpand: options.onExpand || null,
            onCollapse: options.onCollapse || null,
            colorScheme: options.colorScheme || 'default'
        };

        this.intervals = [
            { key: 'year_0_1', label: '< 1 år', color: '#dc2626', icon: '🔴' },
            { key: 'year_1_2', label: '1-2 år', color: '#f97316', icon: '🟠' },
            { key: 'year_3_5', label: '3-5 år', color: '#fbbf24', icon: '🟡' },
            { key: 'year_5_10', label: '5-10 år', color: '#10b981', icon: '🟢' },
            { key: 'year_10_plus', label: '10+ år', color: '#3b82f6', icon: '🔵' }
        ];

        this.state = {
            expandedCards: new Set()
        };

        this.init();
    }

    /**
     * Initialize component
     */
    init() {
        this.render();

        if (this.options.expandAll) {
            this.expandAll();
        }

        console.log('[CapexSummaryCard] Initialized');
    }

    /**
     * Render main component
     */
    render() {
        const totalCapex = this.calculateTotal();

        const html = `
            <div class="capex-summary-container">
                <div class="capex-summary-header">
                    <h2>CAPEX Oversigt</h2>
                    <div class="capex-summary-total">
                        <span>Total CAPEX:</span>
                        <strong>${this.formatCurrency(totalCapex)}</strong>
                    </div>
                </div>

                <div class="capex-summary-actions">
                    <button class="btn-expand-all" onclick="capexCard.expandAll()">
                        Fold alle ud
                    </button>
                    <button class="btn-collapse-all" onclick="capexCard.collapseAll()">
                        Fold alle sammen
                    </button>
                </div>

                <div class="capex-summary-cards">
                    ${this.renderIntervalCards()}
                </div>

                ${this.options.showCharts ? this.renderChart(totalCapex) : ''}
            </div>
        `;

        this.container.innerHTML = html;
        this.attachEventListeners();
    }

    /**
     * Render interval cards
     */
    renderIntervalCards() {
        return this.intervals.map(interval => {
            const amount = this.data[interval.key] || 0;
            const percentage = this.calculatePercentage(amount);
            const cardId = `capex-card-${interval.key}`;
            const isExpanded = this.state.expandedCards.has(interval.key);

            return `
                <div class="capex-card ${isExpanded ? 'expanded' : ''}"
                     id="${cardId}"
                     data-interval="${interval.key}"
                     style="--interval-color: ${interval.color}">

                    <div class="capex-card-header" onclick="capexCard.toggleCard('${interval.key}')">
                        <div class="capex-card-icon">${interval.icon}</div>

                        <div class="capex-card-title">
                            <h3>${interval.label}</h3>
                            <div class="capex-card-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: ${percentage}%"></div>
                                </div>
                                <span class="progress-text">${percentage.toFixed(1)}%</span>
                            </div>
                        </div>

                        <div class="capex-card-amount">
                            ${this.formatCurrency(amount)}
                        </div>

                        <div class="capex-card-toggle">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>

                    <div class="capex-card-body">
                        ${this.renderCardDetails(interval.key, amount)}
                    </div>
                </div>
            `;
        }).join('');
    }

    /**
     * Render card details (breakdown by building/element)
     */
    renderCardDetails(intervalKey, totalAmount) {
        if (!this.options.showDetails) {
            return `<div class="capex-card-details-empty">Ingen detaljer tilgængelige</div>`;
        }

        // Group by building if data available
        const breakdown = this.data.breakdown?.[intervalKey] || [];

        if (breakdown.length === 0) {
            return `
                <div class="capex-card-details-empty">
                    <p>Ingen elementer for denne periode</p>
                </div>
            `;
        }

        return `
            <div class="capex-card-details">
                <table class="capex-details-table">
                    <thead>
                        <tr>
                            <th>Bygning</th>
                            <th>Element</th>
                            <th>Kategori</th>
                            <th>Beløb</th>
                            <th>%</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${breakdown.map(item => {
                            const percentage = ((item.amount / totalAmount) * 100).toFixed(1);
                            return `
                                <tr>
                                    <td>${this.escapeHtml(item.building_name)}</td>
                                    <td>${this.escapeHtml(item.element_name)}</td>
                                    <td>
                                        <span class="category-badge">${this.escapeHtml(item.category)}</span>
                                    </td>
                                    <td class="amount-cell">${this.formatCurrency(item.amount)}</td>
                                    <td class="percentage-cell">${percentage}%</td>
                                </tr>
                            `;
                        }).join('')}
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>Total</strong></td>
                            <td class="amount-cell"><strong>${this.formatCurrency(totalAmount)}</strong></td>
                            <td class="percentage-cell"><strong>100%</strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        `;
    }

    /**
     * Render chart (simple bar chart)
     */
    renderChart(totalCapex) {
        const maxValue = Math.max(...this.intervals.map(i => this.data[i.key] || 0));

        return `
            <div class="capex-chart">
                <h3>CAPEX Fordeling</h3>
                <div class="chart-bars">
                    ${this.intervals.map(interval => {
                        const amount = this.data[interval.key] || 0;
                        const heightPercent = maxValue > 0 ? (amount / maxValue) * 100 : 0;

                        return `
                            <div class="chart-bar-wrapper">
                                <div class="chart-bar"
                                     style="height: ${heightPercent}%; background-color: ${interval.color};"
                                     title="${interval.label}: ${this.formatCurrency(amount)}">
                                    <span class="chart-bar-value">${this.formatCurrencyShort(amount)}</span>
                                </div>
                                <div class="chart-bar-label">${interval.label}</div>
                            </div>
                        `;
                    }).join('')}
                </div>
            </div>
        `;
    }

    /**
     * Toggle card expansion
     */
    toggleCard(intervalKey) {
        const card = this.container.querySelector(`[data-interval="${intervalKey}"]`);
        if (!card) return;

        const isExpanded = card.classList.contains('expanded');

        if (isExpanded) {
            card.classList.remove('expanded');
            this.state.expandedCards.delete(intervalKey);

            if (this.options.onCollapse) {
                this.options.onCollapse(intervalKey);
            }
        } else {
            card.classList.add('expanded');
            this.state.expandedCards.add(intervalKey);

            if (this.options.onExpand) {
                this.options.onExpand(intervalKey);
            }
        }
    }

    /**
     * Expand all cards
     */
    expandAll() {
        this.intervals.forEach(interval => {
            const card = this.container.querySelector(`[data-interval="${interval.key}"]`);
            if (card && !card.classList.contains('expanded')) {
                card.classList.add('expanded');
                this.state.expandedCards.add(interval.key);
            }
        });
    }

    /**
     * Collapse all cards
     */
    collapseAll() {
        this.intervals.forEach(interval => {
            const card = this.container.querySelector(`[data-interval="${interval.key}"]`);
            if (card && card.classList.contains('expanded')) {
                card.classList.remove('expanded');
                this.state.expandedCards.delete(interval.key);
            }
        });
    }

    /**
     * Calculate total CAPEX
     */
    calculateTotal() {
        return this.intervals.reduce((sum, interval) => {
            return sum + (this.data[interval.key] || 0);
        }, 0);
    }

    /**
     * Calculate percentage of total
     */
    calculatePercentage(amount) {
        const total = this.calculateTotal();
        return total > 0 ? (amount / total) * 100 : 0;
    }

    /**
     * Format currency
     */
    formatCurrency(amount) {
        return new Intl.NumberFormat(this.options.locale, {
            style: 'currency',
            currency: this.options.currency,
            minimumFractionDigits: 0,
            maximumFractionDigits: 0
        }).format(amount);
    }

    /**
     * Format currency (short version for charts)
     */
    formatCurrencyShort(amount) {
        if (amount >= 1000000) {
            return (amount / 1000000).toFixed(1) + 'M';
        } else if (amount >= 1000) {
            return (amount / 1000).toFixed(0) + 'K';
        }
        return this.formatCurrency(amount);
    }

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Attach event listeners
     */
    attachEventListeners() {
        // Card headers are handled by onclick in HTML
        // Additional listeners can be added here
    }

    /**
     * Update data and re-render
     */
    update(newData) {
        this.data = newData;
        this.render();
    }

    /**
     * Destroy component
     */
    destroy() {
        this.container.innerHTML = '';
        console.log('[CapexSummaryCard] Destroyed');
    }

    /**
     * Export data as JSON
     */
    exportJSON() {
        return JSON.stringify({
            total: this.calculateTotal(),
            intervals: this.intervals.map(interval => ({
                key: interval.key,
                label: interval.label,
                amount: this.data[interval.key] || 0,
                percentage: this.calculatePercentage(this.data[interval.key] || 0)
            }))
        }, null, 2);
    }

    /**
     * Export data as CSV
     */
    exportCSV() {
        const total = this.calculateTotal();
        let csv = 'Periode,Beløb,Procent\n';

        this.intervals.forEach(interval => {
            const amount = this.data[interval.key] || 0;
            const percentage = this.calculatePercentage(amount).toFixed(2);
            csv += `"${interval.label}",${amount},${percentage}\n`;
        });

        csv += `"Total",${total},100\n`;

        return csv;
    }
}

// Auto-initialize from data attributes
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-capex-summary]').forEach(element => {
        try {
            const data = JSON.parse(element.getAttribute('data-capex-summary'));
            const options = JSON.parse(element.getAttribute('data-options') || '{}');

            const card = new CapexSummaryCard(element, data, options);

            // Store instance for global access
            if (!window.capexCards) {
                window.capexCards = [];
            }
            window.capexCards.push(card);

        } catch (error) {
            console.error('[CapexSummaryCard] Auto-init error:', error);
        }
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CapexSummaryCard;
}

// Make available globally
window.CapexSummaryCard = CapexSummaryCard;
