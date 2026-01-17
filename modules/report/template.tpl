<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapport - <?= htmlspecialchars($project['name']) ?></title>
    <style>
        @media print {
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            body { background: white; }
            .card { border: 1px solid #ccc; box-shadow: none; }
        }
    </style>
</head>
<body>

<div class="module-container">
    <!-- Header with actions (no-print) -->
    <div class="module-header no-print">
        <div>
            <h1><?= icon('file-text', 24) ?> Projektrapport</h1>
            <div class="breadcrumb">
                <a href="/index.php?module=project">Projekter</a> /
                <a href="/index.php?module=project&id=<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></a> /
                <span>Rapport</span>
            </div>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <button class="btn-secondary" onclick="window.print()">
                <?= icon('printer', 18) ?> Print rapport
            </button>
            <button class="btn-primary" onclick="ReportModule.exportToPDF()">
                <?= icon('download', 18) ?> Eksporter PDF
            </button>
        </div>
    </div>

    <!-- Report Content -->
    <div id="reportContent">

        <!-- Executive Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h2>Executive Summary</h2>
                <p class="text-muted"><?= htmlspecialchars($project['name']) ?></p>
            </div>
            <div class="card-body">
                <div id="executiveSummary">
                    <div class="loading-spinner">
                        <?= icon('loader', 24) ?> Beregner oversigt...
                    </div>
                </div>
            </div>
        </div>

        <!-- Red Flags Summary -->
        <div class="card mb-4 page-break">
            <div class="card-header">
                <h2><?= icon('alert-triangle', 20) ?> Red Flags</h2>
            </div>
            <div class="card-body">
                <div id="redFlagsSummary">
                    <div class="loading-spinner">
                        <?= icon('loader', 24) ?> Indlæser red flags...
                    </div>
                </div>
            </div>
        </div>

        <!-- CAPEX Summary by Building -->
        <div class="card mb-4 page-break">
            <div class="card-header">
                <h2>CAPEX Oversigt per Bygning</h2>
            </div>
            <div class="card-body">
                <div id="capexByBuilding">
                    <div class="loading-spinner">
                        <?= icon('loader', 24) ?> Beregner CAPEX...
                    </div>
                </div>
            </div>
        </div>

        <!-- CAPEX Summary by Category -->
        <div class="card mb-4">
            <div class="card-header">
                <h2>CAPEX Oversigt per Kategori</h2>
            </div>
            <div class="card-body">
                <div id="capexByCategory">
                    <div class="loading-spinner">
                        <?= icon('loader', 24) ?> Beregner kategorier...
                    </div>
                </div>
            </div>
        </div>

        <!-- OPEX Summary by Building -->
        <div class="card mb-4 page-break">
            <div class="card-header">
                <h2>OPEX Oversigt per Bygning</h2>
            </div>
            <div class="card-body">
                <div id="opexByBuilding">
                    <div class="loading-spinner">
                        <?= icon('loader', 24) ?> Beregner OPEX...
                    </div>
                </div>
            </div>
        </div>

        <!-- TCO Summary -->
        <div class="card mb-4 page-break">
            <div class="card-header">
                <h2>Total Cost of Ownership (TCO)</h2>
            </div>
            <div class="card-body">
                <div id="tcoSummary">
                    <div class="loading-spinner">
                        <?= icon('loader', 24) ?> Beregner TCO...
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Hierarchy (from tree structure) -->
        <div class="card mb-4 page-break">
            <div class="card-header">
                <h2>Detaljeret Hierarki</h2>
            </div>
            <div class="card-body">
                <div id="hierarchyTree">
                    <div class="loading-spinner">
                        <?= icon('loader', 24) ?> Indlæser hierarki...
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
const ReportModule = {
    projectId: <?= $project['id'] ?>,
    tcoConfig: <?= json_encode($tcoConfigMap) ?>,

    async init() {
        await Promise.all([
            this.loadExecutiveSummary(),
            this.loadRedFlagsSummary(),
            this.loadCapexByBuilding(),
            this.loadCapexByCategory(),
            this.loadOpexByBuilding(),
            this.loadTcoSummary(),
            this.loadHierarchyTree()
        ]);
    },

    async loadExecutiveSummary() {
        // Calculate total CAPEX
        const treeResponse = await fetch(`/api.php?action=get_project_tree&project_id=${this.projectId}`);
        const treeData = await treeResponse.json();

        let totalCapex = 0;
        if (treeData.success && treeData.tree) {
            totalCapex = treeData.tree.total_capex || 0;
        }

        // Get red flags count
        const redFlagsResponse = await fetch(`/api.php?action=get_red_flags_summary&project_id=${this.projectId}`);
        const redFlagsData = await redFlagsResponse.json();

        const urgentItems = redFlagsData.success ? redFlagsData.summary.totals.total_urgent_items : 0;
        const urgentCapex = redFlagsData.success ? redFlagsData.summary.totals.total_urgent_capex : 0;

        // Calculate total OPEX (sum across all buildings)
        let totalOpexPerYear = 0;
        const buildings = <?= json_encode($buildings) ?>;

        for (const building of buildings) {
            const opexResponse = await fetch(`/api.php?action=calculate_building_tco&building_id=${building.id}`);
            const opexData = await opexResponse.json();
            if (opexData.success) {
                totalOpexPerYear += opexData.tco.opex_per_year || 0;
            }
        }

        const lifecycleYears = this.tcoConfig.lifecycle_years || 30;
        const capexContingency = this.tcoConfig.capex_contingency || 0.10;

        // Simple TCO calculation (for summary)
        const capexWithContingency = totalCapex * (1 + capexContingency);
        const totalOpexLifecycle = totalOpexPerYear * lifecycleYears;
        const tco = capexWithContingency + totalOpexLifecycle;

        const html = `
            <div class="executive-summary">
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-label">Total CAPEX</div>
                        <div class="summary-value">${formatCurrency(totalCapex)}</div>
                        <div class="summary-sub">Med ${(capexContingency*100).toFixed(0)}% sikkerhedsmargin: ${formatCurrency(capexWithContingency)}</div>
                    </div>
                    <div class="summary-card">
                        <div class="summary-label">OPEX per år</div>
                        <div class="summary-value">${formatCurrency(totalOpexPerYear)}</div>
                        <div class="summary-sub">Over ${lifecycleYears} år: ${formatCurrency(totalOpexLifecycle)}</div>
                    </div>
                    <div class="summary-card summary-card-primary">
                        <div class="summary-label">Total Cost of Ownership</div>
                        <div class="summary-value">${formatCurrency(tco)}</div>
                        <div class="summary-sub">CAPEX + OPEX over ${lifecycleYears} år</div>
                    </div>
                    <div class="summary-card summary-card-danger">
                        <div class="summary-label">Kritiske elementer</div>
                        <div class="summary-value">${urgentItems}</div>
                        <div class="summary-sub">CAPEX: ${formatCurrency(urgentCapex)}</div>
                    </div>
                </div>

                <div class="summary-notes" style="margin-top: 2rem;">
                    <h4>Sammenfatning</h4>
                    <p>
                        Dette projekt har en samlet CAPEX på <strong>${formatCurrency(totalCapex)}</strong> med
                        ${urgentItems} kritiske elementer til en værdi af ${formatCurrency(urgentCapex)}.
                    </p>
                    <p>
                        De årlige driftsomkostninger (OPEX) er estimeret til <strong>${formatCurrency(totalOpexPerYear)}</strong>,
                        hvilket over en ${lifecycleYears}-årig periode giver samlede driftsomkostninger på
                        ${formatCurrency(totalOpexLifecycle)}.
                    </p>
                    <p>
                        Den samlede Total Cost of Ownership (TCO) er derfor <strong>${formatCurrency(tco)}</strong>,
                        fordelt med ${((capexWithContingency/tco)*100).toFixed(1)}% CAPEX og
                        ${((totalOpexLifecycle/tco)*100).toFixed(1)}% OPEX.
                    </p>
                </div>
            </div>
        `;

        document.getElementById('executiveSummary').innerHTML = html;
    },

    async loadRedFlagsSummary() {
        const response = await fetch(`/api.php?action=get_red_flags_summary&project_id=${this.projectId}`);
        const data = await response.json();

        if (!data.success) {
            document.getElementById('redFlagsSummary').innerHTML = '<p class="text-muted">Ingen red flags data tilgængelig</p>';
            return;
        }

        const summary = data.summary;

        const html = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Antal elementer</th>
                        <th>Total CAPEX</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="danger-row">
                        <td><strong>Kritisk prioritet</strong></td>
                        <td>${summary.urgency.critical.count}</td>
                        <td>${formatCurrency(summary.urgency.critical.capex)}</td>
                    </tr>
                    <tr class="warning-row">
                        <td><strong>Høj prioritet</strong></td>
                        <td>${summary.urgency.high.count}</td>
                        <td>${formatCurrency(summary.urgency.high.capex)}</td>
                    </tr>
                    <tr>
                        <td>Dårlig tilstand</td>
                        <td>${summary.condition.poor_condition_count}</td>
                        <td>${formatCurrency(summary.condition.poor_condition_capex)}</td>
                    </tr>
                    <tr>
                        <td>Høje omkostninger (>500k)</td>
                        <td>${summary.costs.high_cost_count}</td>
                        <td>${formatCurrency(summary.costs.high_cost_capex)}</td>
                    </tr>
                    <tr>
                        <td>Manglende beskrivelse</td>
                        <td colspan="2">${summary.data_quality.missing_description} elementer</td>
                    </tr>
                    <tr>
                        <td>Manglende mængde</td>
                        <td colspan="2">${summary.data_quality.missing_quantity} elementer</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>Total kritiske/høj prioritet</strong></td>
                        <td><strong>${summary.totals.total_urgent_items}</strong></td>
                        <td><strong>${formatCurrency(summary.totals.total_urgent_capex)}</strong></td>
                    </tr>
                </tfoot>
            </table>
        `;

        document.getElementById('redFlagsSummary').innerHTML = html;
    },

    async loadCapexByBuilding() {
        const response = await fetch(`/api.php?action=get_project_tree&project_id=${this.projectId}`);
        const data = await response.json();

        if (!data.success || !data.tree) {
            document.getElementById('capexByBuilding').innerHTML = '<p class="text-muted">Ingen data tilgængelig</p>';
            return;
        }

        const buildings = data.tree.buildings || [];

        let html = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bygning</th>
                        <th>Antal elementer</th>
                        <th>Total CAPEX</th>
                        <th>% af total</th>
                    </tr>
                </thead>
                <tbody>
        `;

        const totalCapex = data.tree.total_capex || 1; // Avoid division by zero

        buildings.forEach(building => {
            const percentage = (building.capex / totalCapex * 100).toFixed(1);
            html += `
                <tr>
                    <td><strong>${escapeHtml(building.name)}</strong></td>
                    <td>${building.element_count || 0}</td>
                    <td>${formatCurrency(building.capex || 0)}</td>
                    <td>${percentage}%</td>
                </tr>
            `;
        });

        html += `
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>Total</strong></td>
                        <td><strong>${buildings.reduce((sum, b) => sum + (b.element_count || 0), 0)}</strong></td>
                        <td><strong>${formatCurrency(totalCapex)}</strong></td>
                        <td><strong>100%</strong></td>
                    </tr>
                </tfoot>
            </table>
        `;

        document.getElementById('capexByBuilding').innerHTML = html;
    },

    async loadCapexByCategory() {
        const response = await fetch(`/api.php?action=get_project_tree&project_id=${this.projectId}`);
        const data = await response.json();

        if (!data.success || !data.tree) {
            document.getElementById('capexByCategory').innerHTML = '<p class="text-muted">Ingen data tilgængelig</p>';
            return;
        }

        // Aggregate by category across all buildings and elements
        const categories = {};
        const buildings = data.tree.buildings || [];

        function processElements(elements) {
            elements.forEach(element => {
                const category = element.category || 'Ikke kategoriseret';
                if (!categories[category]) {
                    categories[category] = { count: 0, capex: 0 };
                }
                categories[category].count++;
                categories[category].capex += element.capex || 0;

                // Process children recursively
                if (element.children && element.children.length > 0) {
                    processElements(element.children);
                }
            });
        }

        buildings.forEach(building => {
            if (building.elements) {
                processElements(building.elements);
            }
        });

        // Convert to array and sort by CAPEX
        const categoryArray = Object.entries(categories).map(([name, data]) => ({
            name,
            count: data.count,
            capex: data.capex
        })).sort((a, b) => b.capex - a.capex);

        const totalCapex = categoryArray.reduce((sum, cat) => sum + cat.capex, 0) || 1;

        let html = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Kategori</th>
                        <th>Antal elementer</th>
                        <th>Total CAPEX</th>
                        <th>% af total</th>
                    </tr>
                </thead>
                <tbody>
        `;

        categoryArray.forEach(cat => {
            const percentage = (cat.capex / totalCapex * 100).toFixed(1);
            html += `
                <tr>
                    <td><strong>${escapeHtml(cat.name)}</strong></td>
                    <td>${cat.count}</td>
                    <td>${formatCurrency(cat.capex)}</td>
                    <td>${percentage}%</td>
                </tr>
            `;
        });

        html += `
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td><strong>Total</strong></td>
                        <td><strong>${categoryArray.reduce((sum, c) => sum + c.count, 0)}</strong></td>
                        <td><strong>${formatCurrency(totalCapex)}</strong></td>
                        <td><strong>100%</strong></td>
                    </tr>
                </tfoot>
            </table>
        `;

        document.getElementById('capexByCategory').innerHTML = html;
    },

    async loadOpexByBuilding() {
        const buildings = <?= json_encode($buildings) ?>;

        if (buildings.length === 0) {
            document.getElementById('opexByBuilding').innerHTML = '<p class="text-muted">Ingen bygninger fundet</p>';
            return;
        }

        let html = `
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bygning</th>
                        <th>Areal (m²)</th>
                        <th>OPEX per år</th>
                        <th>OPEX per m²</th>
                        <th>OPEX over ${this.tcoConfig.lifecycle_years || 30} år</th>
                    </tr>
                </thead>
                <tbody>
        `;

        let totalOpexPerYear = 0;
        const lifecycleYears = this.tcoConfig.lifecycle_years || 30;

        for (const building of buildings) {
            const response = await fetch(`/api.php?action=calculate_building_tco&building_id=${building.id}`);
            const tcoData = await response.json();

            const opexPerYear = tcoData.success ? (tcoData.tco.opex_per_year || 0) : 0;
            const area = parseFloat(building.area || 0);
            const opexPerSqm = area > 0 ? opexPerYear / area : 0;
            const opexLifecycle = opexPerYear * lifecycleYears;

            totalOpexPerYear += opexPerYear;

            html += `
                <tr>
                    <td><strong>${escapeHtml(building.name)}</strong></td>
                    <td>${formatNumber(area)}</td>
                    <td>${formatCurrency(opexPerYear)}</td>
                    <td>${formatNumber(opexPerSqm)} kr/m²</td>
                    <td>${formatCurrency(opexLifecycle)}</td>
                </tr>
            `;
        }

        const totalOpexLifecycle = totalOpexPerYear * lifecycleYears;

        html += `
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="2"><strong>Total OPEX</strong></td>
                        <td><strong>${formatCurrency(totalOpexPerYear)}</strong></td>
                        <td></td>
                        <td><strong>${formatCurrency(totalOpexLifecycle)}</strong></td>
                    </tr>
                </tfoot>
            </table>
        `;

        document.getElementById('opexByBuilding').innerHTML = html;
    },

    async loadTcoSummary() {
        const buildings = <?= json_encode($buildings) ?>;

        // Get total CAPEX from tree
        const treeResponse = await fetch(`/api.php?action=get_project_tree&project_id=${this.projectId}`);
        const treeData = await treeResponse.json();
        const totalCapex = treeData.success ? (treeData.tree.total_capex || 0) : 0;

        // Calculate total OPEX NPV across all buildings
        let totalOpexNpv = 0;
        let totalOpexPerYear = 0;

        for (const building of buildings) {
            const response = await fetch(`/api.php?action=calculate_building_tco&building_id=${building.id}`);
            const tcoData = await response.json();

            if (tcoData.success) {
                totalOpexNpv += tcoData.tco.opex_npv || 0;
                totalOpexPerYear += tcoData.tco.opex_per_year || 0;
            }
        }

        const capexContingency = this.tcoConfig.capex_contingency || 0.10;
        const lifecycleYears = this.tcoConfig.lifecycle_years || 30;
        const discountRate = this.tcoConfig.discount_rate || 0.03;

        const capexWithContingency = totalCapex * (1 + capexContingency);
        const tco = capexWithContingency + totalOpexNpv;

        const html = `
            <div class="tco-breakdown">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Komponent</th>
                            <th>Værdi</th>
                            <th>% af TCO</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>CAPEX (basis)</td>
                            <td>${formatCurrency(totalCapex)}</td>
                            <td>${((totalCapex/tco)*100).toFixed(1)}%</td>
                        </tr>
                        <tr>
                            <td>CAPEX sikkerhedsmargin (${(capexContingency*100).toFixed(0)}%)</td>
                            <td>${formatCurrency(totalCapex * capexContingency)}</td>
                            <td>${((totalCapex * capexContingency/tco)*100).toFixed(1)}%</td>
                        </tr>
                        <tr>
                            <td><strong>CAPEX med sikkerhedsmargin</strong></td>
                            <td><strong>${formatCurrency(capexWithContingency)}</strong></td>
                            <td><strong>${((capexWithContingency/tco)*100).toFixed(1)}%</strong></td>
                        </tr>
                        <tr>
                            <td>OPEX per år</td>
                            <td>${formatCurrency(totalOpexPerYear)}</td>
                            <td>-</td>
                        </tr>
                        <tr>
                            <td>OPEX NPV (${lifecycleYears} år, ${(discountRate*100).toFixed(1)}% diskonto)</td>
                            <td>${formatCurrency(totalOpexNpv)}</td>
                            <td>${((totalOpexNpv/tco)*100).toFixed(1)}%</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Cost of Ownership (TCO)</strong></td>
                            <td><strong>${formatCurrency(tco)}</strong></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="tco-notes" style="margin-top: 2rem;">
                    <h4>TCO Konfiguration</h4>
                    <ul>
                        <li>Beregningsperiode: ${lifecycleYears} år</li>
                        <li>Diskonteringsrente: ${(discountRate*100).toFixed(1)}%</li>
                        <li>CAPEX sikkerhedsmargin: ${(capexContingency*100).toFixed(0)}%</li>
                        <li>OPEX eskalering: ${((this.tcoConfig.opex_escalation || 0.025)*100).toFixed(1)}% årligt</li>
                    </ul>
                    <p>
                        <strong>Fordeling:</strong> CAPEX udgør ${((capexWithContingency/tco)*100).toFixed(1)}% af TCO,
                        mens OPEX over ${lifecycleYears} år udgør ${((totalOpexNpv/tco)*100).toFixed(1)}% af TCO.
                    </p>
                </div>
            </div>
        `;

        document.getElementById('tcoSummary').innerHTML = html;
    },

    async loadHierarchyTree() {
        const response = await fetch(`/api.php?action=get_project_tree&project_id=${this.projectId}`);
        const data = await response.json();

        if (!data.success || !data.tree) {
            document.getElementById('hierarchyTree').innerHTML = '<p class="text-muted">Ingen hierarki data tilgængelig</p>';
            return;
        }

        const container = document.getElementById('hierarchyTree');
        container.innerHTML = '';

        // Render the tree (simplified for print)
        this.renderTreeNode(data.tree.buildings || [], container, 0);
    },

    renderTreeNode(elements, container, depth) {
        elements.forEach(element => {
            const div = document.createElement('div');
            div.style.marginLeft = (depth * 20) + 'px';
            div.style.padding = '8px';
            div.style.borderLeft = depth > 0 ? '2px solid #ddd' : 'none';

            const isBuilding = element.type === 'building';

            div.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <strong>${escapeHtml(element.name)}</strong>
                        ${element.category ? `<span class="badge">${escapeHtml(element.category)}</span>` : ''}
                        ${element.urgency && element.urgency !== 'normal' ? `<span class="badge badge-${element.urgency === 'critical' ? 'danger' : 'warning'}">${element.urgency}</span>` : ''}
                    </div>
                    <div style="text-align: right;">
                        ${element.quantity ? `${formatNumber(element.quantity)} ${element.unit || ''} - ` : ''}
                        <strong>${formatCurrency(element.capex || 0)}</strong>
                    </div>
                </div>
            `;

            container.appendChild(div);

            // Render children
            if (element.children && element.children.length > 0) {
                this.renderTreeNode(element.children, container, depth + 1);
            } else if (element.elements && element.elements.length > 0) {
                this.renderTreeNode(element.elements, container, depth + 1);
            }
        });
    },

    exportToPDF() {
        alert('PDF eksport kræver backend implementering. Brug Print funktionen i mellemtiden.');
    }
};

function formatCurrency(amount) {
    return new Intl.NumberFormat('da-DK', {
        style: 'currency',
        currency: 'DKK',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

function formatNumber(num) {
    return new Intl.NumberFormat('da-DK', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 2
    }).format(num);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    ReportModule.init();
});
</script>

<style>
.executive-summary {
    padding: 1rem 0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.summary-card {
    padding: 1.5rem;
    border: 2px solid var(--color-gray-300);
    border-radius: 8px;
    background: white;
}

.summary-card-primary {
    border-color: var(--color-primary);
    background: linear-gradient(135deg, #f8f9ff 0%, white 100%);
}

.summary-card-danger {
    border-color: var(--color-danger);
    background: linear-gradient(135deg, #fff5f5 0%, white 100%);
}

.summary-label {
    font-size: 0.875rem;
    color: var(--color-gray-600);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.summary-value {
    font-size: 2rem;
    font-weight: bold;
    color: var(--color-gray-900);
    margin-bottom: 0.25rem;
}

.summary-sub {
    font-size: 0.875rem;
    color: var(--color-gray-500);
}

.summary-notes {
    padding: 1.5rem;
    background: var(--color-gray-50);
    border-radius: 8px;
}

.summary-notes h4 {
    margin-top: 0;
}

.summary-notes p {
    margin-bottom: 1rem;
}

.tco-breakdown {
    padding: 1rem 0;
}

.tco-notes {
    padding: 1.5rem;
    background: var(--color-gray-50);
    border-radius: 8px;
}

.tco-notes h4 {
    margin-top: 0;
}

.tco-notes ul {
    margin: 1rem 0;
    padding-left: 1.5rem;
}

.tco-notes li {
    margin-bottom: 0.5rem;
}

.danger-row {
    background: #fff5f5;
}

.warning-row {
    background: #fffaf0;
}

.total-row {
    background: var(--color-gray-100);
    font-weight: bold;
    border-top: 2px solid var(--color-gray-400);
}

@media print {
    .summary-card {
        break-inside: avoid;
    }

    table {
        break-inside: avoid;
    }
}
</style>

</body>
</html>
