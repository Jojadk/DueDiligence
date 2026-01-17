<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Flags - Kritiske elementer</title>
</head>
<body>

<div class="module-container">
    <div class="module-header">
        <div>
            <h1><?= icon('alert-triangle', 24) ?> Red Flags - Kritiske elementer</h1>
            <?php if ($project): ?>
                <div class="breadcrumb">
                    <a href="/index.php?module=project">Projekter</a> /
                    <a href="/index.php?module=project&id=<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></a> /
                    <span>Red Flags</span>
                </div>
            <?php else: ?>
                <p class="text-muted">Oversigt over alle kritiske elementer og problemer</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Cards -->
    <div id="redFlagsSummary" class="mb-4">
        <div class="loading-spinner" style="padding: 40px; text-align: center;">
            <?= icon('loader', 32) ?> Indlæser oversigt...
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="filter-group" style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <div class="form-group" style="margin: 0; min-width: 200px;">
                    <label>Alvorlighed</label>
                    <select id="severityFilter" class="form-control" onchange="RedFlagsModule.applyFilters()">
                        <option value="">Alle</option>
                        <option value="critical">Kritisk</option>
                        <option value="high">Høj</option>
                        <option value="normal">Normal</option>
                        <option value="low">Lav</option>
                    </select>
                </div>
                <div class="form-group" style="margin: 0; min-width: 200px;">
                    <label>Flag type</label>
                    <select id="typeFilter" class="form-control" onchange="RedFlagsModule.applyFilters()">
                        <option value="">Alle</option>
                        <option value="urgency">Høj prioritet</option>
                        <option value="condition">Dårlig tilstand</option>
                        <option value="high_cost">Høj omkostning</option>
                        <option value="missing_data">Manglende data</option>
                    </select>
                </div>
                <div class="form-group" style="margin: 0; min-width: 200px;">
                    <label>Sortering</label>
                    <select id="sortFilter" class="form-control" onchange="RedFlagsModule.applyFilters()">
                        <option value="score">Højeste score først</option>
                        <option value="capex">Højeste CAPEX først</option>
                        <option value="project">Efter projekt</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- Red Flags List -->
    <div class="card">
        <div class="card-header">
            <h3>Detaljeret liste</h3>
            <span id="redFlagsCount" class="badge badge-danger">0</span>
        </div>
        <div class="card-body">
            <div id="redFlagsList">
                <div class="loading-spinner" style="padding: 40px; text-align: center;">
                    <?= icon('loader', 32) ?> Indlæser red flags...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const RedFlagsModule = {
    allRedFlags: [],
    filteredRedFlags: [],
    projectId: <?= $project ? $project['id'] : 'null' ?>,

    async init() {
        await this.loadSummary();
        await this.loadRedFlags();
    },

    async loadSummary() {
        const url = '/api.php?action=get_red_flags_summary' + (this.projectId ? '&project_id=' + this.projectId : '');
        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            this.renderSummary(data.summary);
        } else {
            document.getElementById('redFlagsSummary').innerHTML = `
                <div class="alert alert-danger">
                    ${escapeHtml(data.error || 'Kunne ikke indlæse oversigt')}
                </div>
            `;
        }
    },

    renderSummary(summary) {
        const html = `
            <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));">
                <div class="stat-card stat-card-danger">
                    <div class="stat-icon"><?= icon('alert-circle', 32) ?></div>
                    <div class="stat-content">
                        <div class="stat-label">Kritiske elementer</div>
                        <div class="stat-value">${summary.urgency.critical.count}</div>
                        <div class="stat-sub">CAPEX: ${formatNumber(summary.urgency.critical.capex)} kr</div>
                    </div>
                </div>

                <div class="stat-card stat-card-warning">
                    <div class="stat-icon"><?= icon('alert-triangle', 32) ?></div>
                    <div class="stat-content">
                        <div class="stat-label">Høj prioritet</div>
                        <div class="stat-value">${summary.urgency.high.count}</div>
                        <div class="stat-sub">CAPEX: ${formatNumber(summary.urgency.high.capex)} kr</div>
                    </div>
                </div>

                <div class="stat-card stat-card-info">
                    <div class="stat-icon"><?= icon('tool', 32) ?></div>
                    <div class="stat-content">
                        <div class="stat-label">Dårlig tilstand</div>
                        <div class="stat-value">${summary.condition.poor_condition_count}</div>
                        <div class="stat-sub">CAPEX: ${formatNumber(summary.condition.poor_condition_capex)} kr</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><?= icon('trending-up', 32) ?></div>
                    <div class="stat-content">
                        <div class="stat-label">Høje omkostninger</div>
                        <div class="stat-value">${summary.costs.high_cost_count}</div>
                        <div class="stat-sub">CAPEX: ${formatNumber(summary.costs.high_cost_capex)} kr</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><?= icon('file-text', 32) ?></div>
                    <div class="stat-content">
                        <div class="stat-label">Manglende beskrivelse</div>
                        <div class="stat-value">${summary.data_quality.missing_description}</div>
                        <div class="stat-sub">Data kvalitet</div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon"><?= icon('hash', 32) ?></div>
                    <div class="stat-content">
                        <div class="stat-label">Manglende mængde</div>
                        <div class="stat-value">${summary.data_quality.missing_quantity}</div>
                        <div class="stat-sub">Data kvalitet</div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('redFlagsSummary').innerHTML = html;
    },

    async loadRedFlags() {
        const url = '/api.php?action=get_red_flags' + (this.projectId ? '&project_id=' + this.projectId : '');
        const response = await fetch(url);
        const data = await response.json();

        if (data.success) {
            this.allRedFlags = data.red_flags || [];
            this.filteredRedFlags = [...this.allRedFlags];
            this.renderRedFlags();
        } else {
            document.getElementById('redFlagsList').innerHTML = `
                <div class="alert alert-danger">
                    ${escapeHtml(data.error || 'Kunne ikke indlæse red flags')}
                </div>
            `;
        }
    },

    applyFilters() {
        const severityFilter = document.getElementById('severityFilter').value;
        const typeFilter = document.getElementById('typeFilter').value;
        const sortFilter = document.getElementById('sortFilter').value;

        // Apply filters
        this.filteredRedFlags = this.allRedFlags.filter(item => {
            if (severityFilter && item.severity !== severityFilter) return false;
            if (typeFilter && !item.flags.some(f => f.type === typeFilter)) return false;
            return true;
        });

        // Apply sorting
        if (sortFilter === 'capex') {
            this.filteredRedFlags.sort((a, b) => (b.element.capex || 0) - (a.element.capex || 0));
        } else if (sortFilter === 'project') {
            this.filteredRedFlags.sort((a, b) => {
                const projA = a.element.project_name || '';
                const projB = b.element.project_name || '';
                return projA.localeCompare(projB);
            });
        } else {
            // Default: sort by score
            this.filteredRedFlags.sort((a, b) => b.score - a.score);
        }

        this.renderRedFlags();
    },

    renderRedFlags() {
        document.getElementById('redFlagsCount').textContent = this.filteredRedFlags.length;

        if (this.filteredRedFlags.length === 0) {
            document.getElementById('redFlagsList').innerHTML = `
                <div class="empty-state">
                    <?= icon('check-circle', 48) ?>
                    <p>Ingen red flags fundet</p>
                    <small class="text-muted">Alle elementer ser fine ud!</small>
                </div>
            `;
            return;
        }

        let html = '<div class="red-flags-list">';

        this.filteredRedFlags.forEach(item => {
            const element = item.element;
            const severityClass = item.severity === 'critical' ? 'danger' : item.severity === 'high' ? 'warning' : 'info';
            const severityLabel = {
                'critical': 'Kritisk',
                'high': 'Høj',
                'normal': 'Normal',
                'low': 'Lav'
            }[item.severity] || item.severity;

            html += `
                <div class="red-flag-item" data-severity="${item.severity}">
                    <div class="red-flag-header">
                        <div>
                            <h4>
                                <span class="badge badge-${severityClass}">${severityLabel}</span>
                                ${escapeHtml(element.name)}
                            </h4>
                            <div class="text-muted">
                                <small>
                                    ${escapeHtml(element.project_name)} / ${escapeHtml(element.building_name)}
                                    ${element.category ? ' / ' + escapeHtml(element.category) : ''}
                                </small>
                            </div>
                        </div>
                        <div class="red-flag-score">
                            <div class="score-badge">${item.score}</div>
                            <small class="text-muted">Score</small>
                        </div>
                    </div>
                    <div class="red-flag-flags">
                        ${item.flags.map(flag => `
                            <div class="flag-badge flag-badge-${flag.severity}">
                                <?= icon('alert-circle', 16) ?>
                                <strong>${escapeHtml(flag.label)}:</strong> ${escapeHtml(flag.description)}
                            </div>
                        `).join('')}
                    </div>
                    <div class="red-flag-details">
                        <div class="detail-row">
                            <span class="detail-label">CAPEX:</span>
                            <span class="detail-value">${formatNumber(element.capex || 0)} kr</span>
                        </div>
                        ${element.quantity ? `
                            <div class="detail-row">
                                <span class="detail-label">Mængde:</span>
                                <span class="detail-value">${element.quantity} ${element.unit || ''}</span>
                            </div>
                        ` : ''}
                        ${element.condition ? `
                            <div class="detail-row">
                                <span class="detail-label">Tilstand:</span>
                                <span class="detail-value">${escapeHtml(element.condition)}</span>
                            </div>
                        ` : ''}
                    </div>
                    <div class="red-flag-actions">
                        <a href="/index.php?module=building_element&id=${element.id}"
                           class="btn-secondary btn-sm">
                            <?= icon('edit', 16) ?> Rediger element
                        </a>
                    </div>
                </div>
            `;
        });

        html += '</div>';

        document.getElementById('redFlagsList').innerHTML = html;
    }
};

function formatNumber(num) {
    return new Intl.NumberFormat('da-DK', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(num);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    RedFlagsModule.init();
});
</script>

<style>
.red-flags-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.red-flag-item {
    border: 1px solid var(--color-gray-300);
    border-radius: 8px;
    padding: 1.5rem;
    background: white;
    transition: box-shadow 0.2s ease;
}

.red-flag-item:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.red-flag-item[data-severity="critical"] {
    border-left: 4px solid var(--color-danger);
}

.red-flag-item[data-severity="high"] {
    border-left: 4px solid var(--color-warning);
}

.red-flag-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.red-flag-header h4 {
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.red-flag-score {
    text-align: center;
}

.score-badge {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--color-primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: bold;
    margin-bottom: 0.25rem;
}

.red-flag-flags {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.flag-badge {
    padding: 0.5rem 1rem;
    border-radius: 4px;
    background: var(--color-gray-100);
    border-left: 3px solid var(--color-gray-400);
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.flag-badge-critical {
    background: #fee;
    border-left-color: var(--color-danger);
}

.flag-badge-high {
    background: #fef3e0;
    border-left-color: var(--color-warning);
}

.flag-badge-normal {
    background: #e0f2fe;
    border-left-color: var(--color-info);
}

.red-flag-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 0.5rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: var(--color-gray-50);
    border-radius: 4px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
}

.detail-label {
    font-weight: 500;
    color: var(--color-gray-600);
}

.detail-value {
    font-weight: 600;
}

.red-flag-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.stat-card-danger .stat-icon {
    color: var(--color-danger);
}

.stat-card-warning .stat-icon {
    color: var(--color-warning);
}

.stat-card-info .stat-icon {
    color: var(--color-info);
}
</style>

</body>
</html>
