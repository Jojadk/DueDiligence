<?php /* Dashboard - Backend-Controlled Widgets */ ?>

<header class="page-header">
    <div><h1><?= icon('home', 32) ?> Dashboard</h1><p class="subtitle">Oversigt over dit system</p></div>
    <?php if ($permissions['admin']): ?>
    <button class="btn btn-secondary" onclick="DashboardModule.refreshWidgets()"><?= icon('refresh-cw', 20) ?> Opdater</button>
    <?php endif; ?>
</header>

<!-- Stats Grid (loaded via API) -->
<div id="statsGrid" class="stats-grid">
    <div class="loading-card"><div class="spinner-small"></div> Indlæser statistik...</div>
</div>

<!-- Widgets Grid (backend-controlled) -->
<div id="widgetsGrid" class="dashboard-row">
    <div class="loading-card"><div class="spinner-small"></div> Indlæser widgets...</div>
</div>

<!-- Admin-only widgets (if user has permission) -->
<?php if ($permissions['admin']): ?>
<div id="adminWidgets" class="admin-section">
    <h2><?= icon('shield', 24) ?> Administration</h2>
    <div id="adminContent" class="dashboard-row">
        <div class="loading-card"><div class="spinner-small"></div> Indlæser admin data...</div>
    </div>
</div>
<?php endif; ?>

<script>
const DashboardModule = {
    permissions: <?= json_encode($permissions) ?>,
    
    async init() {
        await this.loadStats();
        await this.loadWidgets();
        
        // Auto-refresh every 5 minutes
        setInterval(() => this.refreshWidgets(), 300000);
    },

    async loadStats() {
        try {
            const response = await API.get('/api.php', { action: 'get_dashboard_stats' });
            
            if (response.success) {
                this.renderStats(response.stats);
            } else {
                notify('Kunne ikke hente statistik', {type: 'success'});
            }
        } catch (error) {
            console.error('Stats load error:', error);
            document.getElementById('statsGrid').innerHTML = '<div class="error-card">Fejl ved indlæsning</div>';
        }
    },

    renderStats(stats) {
        const grid = document.getElementById('statsGrid');
        
        const statCards = [
            { icon: 'users', label: 'Kunder', value: stats.customers, link: 'customer', show: this.permissions.view_customers },
            { icon: 'folder', label: 'Projekter', value: stats.projects, link: 'project', show: true },
            { icon: 'building', label: 'Bygninger', value: stats.buildings, link: 'building', show: true },
            { icon: 'list', label: 'Bygningsdele', value: stats.elements, link: 'building_element', show: true },
            { icon: 'activity', label: 'Aktive Projekter', value: stats.active_projects, link: 'project', show: true },
            { icon: 'alert-triangle', label: 'Hastende', value: stats.urgent_items, link: 'building_element', show: true },
            { icon: 'dollar-sign', label: 'Total CAPEX', value: formatMoney(stats.total_capex), link: null, show: this.permissions.view_reports }
        ].filter(card => card.show);

        grid.innerHTML = statCards.map(card => `
            <div class="stat-card ${card.link ? 'clickable' : ''}" ${card.link ? `onclick="navigate('${card.link}')"` : ''}>
                <div class="stat-icon">${this.getIcon(card.icon, 32)}</div>
                <div class="stat-info">
                    <div class="stat-value">${escapeHtml(card.value.toString())}</div>
                    <div class="stat-label">${escapeHtml(card.label)}</div>
                </div>
                ${card.link ? '<div class="stat-arrow">→</div>' : ''}
            </div>
        `).join('');
    },

    async loadWidgets() {
        try {
            const response = await API.get('/api.php', { action: 'get_dashboard_widgets' });
            
            if (response.success) {
                this.renderWidgets(response.widgets);
            } else {
                notify('Kunne ikke hente widgets', {type: 'success'});
            }
        } catch (error) {
            console.error('Widgets load error:', error);
            document.getElementById('widgetsGrid').innerHTML = '<div class="error-card">Fejl ved indlæsning</div>';
        }
    },

    renderWidgets(widgets) {
        const grid = document.getElementById('widgetsGrid');
        
        let html = '';

        // Recent Projects
        if (widgets.recent_projects) {
            html += `
                <div class="dashboard-card">
                    <h2>${this.getIcon('clock', 24)} Seneste Projekter</h2>
                    ${widgets.recent_projects.length === 0 ? 
                        '<p class="text-muted">Ingen projekter endnu</p>' :
                        `<div class="list-group">${widgets.recent_projects.map(p => this.renderProjectItem(p)).join('')}</div>`
                    }
                </div>
            `;
        }

        // Urgent Elements
        if (widgets.urgent_elements) {
            html += `
                <div class="dashboard-card">
                    <h2>${this.getIcon('alert-triangle', 24)} Hastende Bygningsdele</h2>
                    ${widgets.urgent_elements.length === 0 ?
                        '<p class="text-muted">Ingen hastende elementer</p>' :
                        `<div class="list-group">${widgets.urgent_elements.map(e => this.renderElementItem(e)).join('')}</div>`
                    }
                </div>
            `;
        }

        grid.innerHTML = html;

        // Admin widgets (if user is admin)
        if (this.permissions.admin && widgets.recent_users) {
            const adminGrid = document.getElementById('adminContent');
            if (adminGrid) {
                let adminHtml = `
                    <div class="dashboard-card">
                        <h2>${this.getIcon('users', 24)} Seneste Brugere</h2>
                        <div class="list-group">
                            ${widgets.recent_users.map(u => `
                                <div class="list-item">
                                    <div>
                                        <strong>${escapeHtml(u.name)}</strong>
                                        <small class="text-muted">${escapeHtml(u.email)}</small>
                                    </div>
                                    <small>${formatDate(u.created_at)}</small>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;

                if (widgets.system_health) {
                    adminHtml += `
                        <div class="dashboard-card">
                            <h2>${this.getIcon('cpu', 24)} System Status</h2>
                            <div class="health-stats">
                                <div class="health-item">
                                    <span>Database:</span>
                                    <span>${formatFileSize(widgets.system_health.database_size)}</span>
                                </div>
                                <div class="health-item">
                                    <span>Records:</span>
                                    <span>${widgets.system_health.total_records.toLocaleString('da-DK')}</span>
                                </div>
                            </div>
                        </div>
                    `;
                }

                adminGrid.innerHTML = adminHtml;
            }
        }
    },

    renderProjectItem(project) {
        const statusMap = {
            'planning': { label: 'Planlægning', class: 'badge-warning' },
            'active': { label: 'Aktiv', class: 'badge-success' },
            'on_hold': { label: 'På vent', class: 'badge-secondary' },
            'completed': { label: 'Afsluttet', class: 'badge-info' },
            'archived': { label: 'Arkiveret', class: 'badge-muted' }
        };

        const status = statusMap[project.status] || statusMap.planning;

        return `
            <a href="#" onclick="navigate('project', {id: ${project.id}}); return false;" class="list-item">
                <div>
                    <strong>${escapeHtml(project.name)}</strong>
                    <small class="text-muted">${escapeHtml(project.customer_name || '')}</small>
                </div>
                <span class="badge ${status.class}">${status.label}</span>
            </a>
        `;
    },

    renderElementItem(element) {
        const urgencyMap = {
            'high': { label: 'Høj', class: 'badge-warning' },
            'critical': { label: 'Kritisk', class: 'badge-error' }
        };

        const urgency = urgencyMap[element.urgency] || urgencyMap.high;

        return `
            <a href="#" onclick="navigate('building_element', {id: ${element.id}}); return false;" class="list-item">
                <div>
                    <strong>${escapeHtml(element.name)}</strong>
                    <small class="text-muted">${escapeHtml(element.building_name)} • ${element.time_horizon} år</small>
                </div>
                <span class="badge ${urgency.class}">${urgency.label}</span>
            </a>
        `;
    },

    getIcon(name, size) {
        // Simplified - use actual icon() function from backend
        return `<svg width="${size}" height="${size}"><use href="#icon-${name}"></use></svg>`;
    },

    async refreshWidgets() {
        App.showLoading('Opdaterer dashboard...');
        await this.loadStats();
        await this.loadWidgets();
        App.hideLoading();
        notify('Dashboard opdateret', {type: 'success'});
    }
};

// Initialize when template loads
DashboardModule.init();
</script>

