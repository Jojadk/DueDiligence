/**
 * Project Snapshot Component
 * Handles creating, restoring, and managing project snapshots
 * Backend API handles all data processing and permissions
 */

const ProjectSnapshot = {
    /**
     * Show snapshots manager modal for a project
     * @param {number} projectId - Project ID
     */
    async showManager(projectId) {
        const modalContent = `
            <div class="snapshot-manager">
                <div class="modal-body">
                    <div id="snapshotErrors"></div>

                    <div class="snapshot-actions">
                        <button class="btn btn-primary" onclick="ProjectSnapshot.openCreateModal(${projectId})">
                            ${this.getIcon('save', 18)} Opret nyt snapshot
                        </button>
                    </div>

                    <div id="snapshotsList" class="snapshots-list">
                        <div class="loading-state">
                            <div class="spinner-small"></div>
                            Indlæser snapshots...
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="Modal.close()">Luk</button>
                </div>
            </div>
        `;

        Modal.open(modalContent, {
            size: 'large',
            title: 'Project Snapshots'
        });

        // Load snapshots list
        setTimeout(() => {
            this.loadSnapshots(projectId);
        }, 100);
    },

    /**
     * Load snapshots list from API
     */
    async loadSnapshots(projectId) {
        const container = document.getElementById('snapshotsList');
        if (!container) return;

        try {
            const response = await API.get('/api.php', {
                action: 'list_snapshots',
                project_id: projectId
            });

            if (response.success) {
                this.renderSnapshots(response.snapshots, projectId);
            } else {
                container.innerHTML = `<div class="error-state">${escapeHtml(response.error)}</div>`;
            }
        } catch (error) {
            console.error('Snapshots load error:', error);
            container.innerHTML = '<div class="error-state">Kunne ikke indlæse snapshots</div>';
        }
    },

    /**
     * Render snapshots list
     */
    renderSnapshots(snapshots, projectId) {
        const container = document.getElementById('snapshotsList');
        if (!container) return;

        if (!snapshots || snapshots.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    ${this.getIcon('archive', 48)}
                    <h3>Ingen snapshots</h3>
                    <p>Opret et snapshot for at gemme projektets nuværende tilstand</p>
                    <button class="btn btn-primary" onclick="ProjectSnapshot.openCreateModal(${projectId})">
                        ${this.getIcon('save', 18)} Opret snapshot
                    </button>
                </div>
            `;
            return;
        }

        let html = '<div class="snapshots-grid">';

        snapshots.forEach(snapshot => {
            const date = new Date(snapshot.created_at);
            const formattedDate = date.toLocaleString('da-DK', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            html += `
                <div class="snapshot-card">
                    <div class="snapshot-header">
                        <div class="snapshot-icon">${this.getIcon('archive', 24)}</div>
                        <div class="snapshot-info">
                            <h4>${escapeHtml(snapshot.name || 'Unavngivet snapshot')}</h4>
                            <div class="snapshot-meta">
                                <span class="snapshot-date">${formattedDate}</span>
                                <span class="snapshot-user">af ${escapeHtml(snapshot.created_by_name || 'Ukendt')}</span>
                            </div>
                        </div>
                    </div>

                    ${snapshot.description ? `
                        <div class="snapshot-description">
                            ${escapeHtml(snapshot.description)}
                        </div>
                    ` : ''}

                    <div class="snapshot-stats">
                        <div class="stat-item">
                            <span class="stat-label">Bygninger:</span>
                            <span class="stat-value">${snapshot.stats?.buildings || 0}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Elementer:</span>
                            <span class="stat-value">${snapshot.stats?.elements || 0}</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Filer:</span>
                            <span class="stat-value">${snapshot.stats?.files || 0}</span>
                        </div>
                    </div>

                    <div class="snapshot-actions">
                        <button class="btn btn-sm btn-primary"
                                onclick="ProjectSnapshot.confirmRestore(${snapshot.id}, ${projectId}, '${escapeHtml(snapshot.name || 'Unavngivet')}')">
                            ${this.getIcon('refresh-cw', 16)} Gendan
                        </button>
                        <button class="btn btn-sm btn-secondary"
                                onclick="ProjectSnapshot.viewDetails(${snapshot.id})">
                            ${this.getIcon('eye', 16)} Detaljer
                        </button>
                        <button class="btn-icon btn-icon-danger"
                                onclick="ProjectSnapshot.deleteSnapshot(${snapshot.id}, ${projectId})"
                                title="Slet snapshot">
                            ${this.getIcon('trash', 16)}
                        </button>
                    </div>
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    },

    /**
     * Open create snapshot modal
     */
    openCreateModal(projectId) {
        const createContent = `
            <form id="createSnapshotForm" onsubmit="return ProjectSnapshot.handleCreate(event, ${projectId});">
                <div class="modal-body">
                    <div id="createErrors"></div>

                    <div class="form-group">
                        <label class="required">Snapshot navn</label>
                        <input type="text"
                               name="name"
                               class="form-control"
                               placeholder="F.eks. 'Før renovering'"
                               required>
                    </div>

                    <div class="form-group">
                        <label>Beskrivelse</label>
                        <textarea name="description"
                                  rows="4"
                                  class="form-control"
                                  placeholder="Beskriv hvad dette snapshot indeholder..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        ${this.getIcon('info', 18)}
                        <div>
                            <strong>Info:</strong> Snapshot vil gemme hele projektets nuværende tilstand,
                            inklusiv alle bygninger, elementer, og filer.
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="Modal.close()">Annuller</button>
                    <button type="submit" class="btn btn-primary" id="createBtn">
                        ${this.getIcon('save', 18)} Opret snapshot
                    </button>
                </div>
            </form>
        `;

        Modal.open(createContent, {
            size: 'medium',
            title: 'Opret nyt snapshot'
        });
    },

    /**
     * Handle snapshot creation
     */
    async handleCreate(event, projectId) {
        event.preventDefault();

        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'create_snapshot');
        formData.append('project_id', projectId);

        const createBtn = document.getElementById('createBtn');
        createBtn.disabled = true;
        createBtn.innerHTML = `${this.getIcon('loader', 18)} Opretter...`;

        try {
            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Snapshot oprettet');
                Modal.close();

                // Reload snapshots manager
                setTimeout(() => {
                    this.showManager(projectId);
                }, 300);
            } else {
                this.showError(response.error || 'Kunne ikke oprette snapshot', 'createErrors');
                createBtn.disabled = false;
                createBtn.innerHTML = `${this.getIcon('save', 18)} Opret snapshot`;
            }
        } catch (error) {
            console.error('Snapshot creation error:', error);
            this.showError('Netværksfejl ved oprettelse', 'createErrors');
            createBtn.disabled = false;
            createBtn.innerHTML = `${this.getIcon('save', 18)} Opret snapshot`;
        }

        return false;
    },

    /**
     * Confirm snapshot restore
     */
    async confirmRestore(snapshotId, projectId, snapshotName) {
        const confirmed = await Modal.confirm(
            `Er du sikker på at du vil gendanne projektet til snapshot "${snapshotName}"?\n\n` +
            `⚠️ ADVARSEL: Den nuværende tilstand vil blive overskrevet. Overvej at oprette et snapshot af den nuværende tilstand først.`,
            {
                title: 'Bekræft gendannelse',
                confirmText: 'Gendan',
                confirmClass: 'btn-warning'
            }
        );

        if (!confirmed) return;

        App.showLoading('Gendanner snapshot...');

        try {
            const formData = new FormData();
            formData.append('action', 'restore_snapshot');
            formData.append('snapshot_id', snapshotId);

            const response = await API.post('/api.php', formData, true);

            App.hideLoading();

            if (response.success) {
                Toast.success('Snapshot gendannet succesfuldt');
                Modal.close();

                // Reload project page
                setTimeout(() => {
                    Router.reload();
                }, 500);
            } else {
                Toast.error(response.error || 'Kunne ikke gendanne snapshot');
            }
        } catch (error) {
            console.error('Snapshot restore error:', error);
            App.hideLoading();
            Toast.error('Netværksfejl ved gendannelse');
        }
    },

    /**
     * View snapshot details
     */
    async viewDetails(snapshotId) {
        // TODO: Implement detailed view of snapshot contents
        Toast.info('Snapshot detaljer kommer snart');
    },

    /**
     * Delete snapshot
     */
    async deleteSnapshot(snapshotId, projectId) {
        if (!await Modal.confirm('Slet dette snapshot permanent?', {
            title: 'Bekræft sletning',
            confirmText: 'Slet',
            confirmClass: 'btn-danger'
        })) {
            return;
        }

        try {
            const formData = new FormData();
            formData.append('action', 'delete_snapshot');
            formData.append('snapshot_id', snapshotId);

            const response = await API.post('/api.php', formData, true);

            if (response.success) {
                Toast.success('Snapshot slettet');
                this.loadSnapshots(projectId);
            } else {
                Toast.error(response.error || 'Kunne ikke slette snapshot');
            }
        } catch (error) {
            console.error('Snapshot delete error:', error);
            Toast.error('Netværksfejl ved sletning');
        }
    },

    /**
     * Show error message
     */
    showError(message, containerId = 'snapshotErrors') {
        const errorsEl = document.getElementById(containerId);
        if (errorsEl) {
            errorsEl.innerHTML = `<div class="alert alert-error">${escapeHtml(message)}</div>`;
            errorsEl.style.display = 'block';
        }
    },

    /**
     * Get icon SVG
     */
    getIcon(name, size) {
        return `<svg width="${size}" height="${size}" class="icon"><use href="#icon-${name}"></use></svg>`;
    }
};

// Export globally
window.ProjectSnapshot = ProjectSnapshot;
