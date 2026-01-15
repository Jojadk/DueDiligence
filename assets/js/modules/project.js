/**
 * Project Module Logic
 */
if (typeof ProjectModule === 'undefined') {
    window.ProjectModule = {
        teamMembers: [],

        getContainer: function (el) {
            return el.closest('.project-form-container') || document;
        },

        init: function () {
            this.setupEventDelegation();
        },

        setupEventDelegation: function () {
            document.addEventListener('click', (e) => {
                // Close search results if clicked outside
                if (!e.target.closest('.client-results') && !e.target.closest('input[name="client_name"]')) {
                    document.querySelectorAll('.client-results').forEach(r => r.style.display = 'none');
                }
                if (!e.target.closest('.search-results') && !e.target.closest('input[placeholder="Search user..."]')) {
                    document.querySelectorAll('.search-results').forEach(r => r.style.display = 'none');
                }

                // Tabs
                if (e.target.closest('[data-tab-target]')) {
                    const tab = e.target.closest('[data-tab-target]');
                    const targetId = tab.getAttribute('data-tab-target');
                    ProjectModule.switchTab(tab, targetId);
                    return;
                }

                // Remove Team Member
                if (e.target.closest('.remove-team-member')) {
                    const id = e.target.closest('.remove-team-member').getAttribute('data-id');
                    ProjectModule.removeTeamMember(id);
                    return;
                }

                // New Project Button (from index.php)
                if (e.target.closest('#btn-new-project')) {
                    ProjectModule.openProjectModal();
                    return;
                }

                // Delete Project Button
                if (e.target.closest('#btn-delete-project')) {
                    const btn = e.target.closest('#btn-delete-project');
                    const id = btn.getAttribute('data-project-id');
                    const name = btn.getAttribute('data-project-name');
                    ProjectModule.deleteProject(id, name);
                    return;
                }

                // Client Search Result Selection
                if (e.target.closest('.client-result-item')) {
                    const item = e.target.closest('.client-result-item');
                    const input = item.closest('.form-group').querySelector('input[name="client_name"]');
                    const hidden = item.closest('.form-group').querySelector('input[name="client_id"]');
                    const resultsContainer = item.closest('.client-results');

                    if (input) input.value = item.getAttribute('data-name');
                    if (hidden) hidden.value = item.getAttribute('data-id');
                    if (resultsContainer) resultsContainer.style.display = 'none';
                    return;
                }

                // User Search Result Selection
                if (e.target.closest('.user-result-item')) {
                    const item = e.target.closest('.user-result-item');
                    const user = {
                        id: item.getAttribute('data-id'),
                        username: item.getAttribute('data-username'),
                        email: item.getAttribute('data-email')
                    };
                    ProjectModule.addTeamMember(user);
                    const resultsContainer = item.closest('.search-results');
                    if (resultsContainer) resultsContainer.style.display = 'none';
                    const input = item.closest('.form-group').querySelector('input'); // Or id="user-search-input"
                    if (input) input.value = '';
                    // also clear specific id input
                    const specificInput = document.getElementById('user-search-input');
                    if (specificInput) specificInput.value = '';
                    return;
                }
            });

            // Form Submit
            document.addEventListener('submit', (e) => {
                if (e.target.id === 'project-form') {
                    e.preventDefault();
                    ProjectModule.saveProject(e);
                }
            });

            // Input Handling (Debounced search)
            document.addEventListener('keyup', (e) => {
                if (e.target.id === 'client-search-input') {
                    ModuleUtils.debounce(() => ProjectModule.searchClients(e.target), 300)();
                }
                if (e.target.id === 'user-search-input') {
                    ModuleUtils.debounce(() => ProjectModule.searchUsers(e.target), 300)();
                }
            });

            // Clear hidden ID on input clear
            document.addEventListener('change', (e) => {
                if (e.target.id === 'client-search-input' && e.target.value === '') {
                    const hidden = document.querySelector('input[name="client_id"]');
                    if (hidden) hidden.value = '';
                }
            });
        },

        setTeamMembers: function (members) {
            this.teamMembers = members || [];
            this.renderTeamList();
        },

        editCoverImage: function (projectId, imageUrl) {
            if (typeof CanvasEngine === 'undefined') {
                App.toast('Billedværktøj indlæses...', 'info');
                App.loadScript('assets/js/modules/canvas_engine.js').then(() => {
                    ProjectModule.editCoverImage(projectId, imageUrl);
                });
                return;
            }

            const winId = 'win-canvas-editor-project';
            if (wm.windows[winId]) {
                wm.focusWindow(winId);
                return;
            }

            const content = `
            <div style="display:flex; flex-direction:column; height:100%;">
                <div id="canvas-container" style="flex:1; background:#e5e5e5; position:relative; overflow:hidden;"></div>
                <div class="canvas-toolbar" style="height:50px; background:#333; display:flex; align-items:center; padding:0 10px; gap:10px;">
                    <button class="btn btn-sm btn-light" onclick="window.activeCanvas.setMode('select')"><i class="fas fa-mouse-pointer"></i></button>
                    <button class="btn btn-sm btn-light" onclick="window.activeCanvas.setMode('draw', {color:'red', width:5})"><i class="fas fa-pencil-alt"></i></button>
                    <button class="btn btn-sm btn-light" onclick="window.activeCanvas.setMode('arrow', {color:'red', width:5})"><i class="fas fa-long-arrow-alt-right"></i></button>
                    <button class="btn btn-sm btn-light" onclick="window.activeCanvas.setMode('rect', {color:'red', width:5})"><i class="far fa-square"></i></button>
                    <div style="flex:1;"></div>
                    <button class="btn btn-sm btn-secondary" onclick="window.activeCanvas.undo()"><i class="fas fa-undo"></i></button>
                    <button class="btn btn-sm btn-success" onclick="ProjectModule.saveCoverAnnotation(${projectId})">💾 Gem Forside</button>
                </div>
            </div>
        `;

            wm.createWindow({
                id: winId,
                title: 'Rediger Forsidebillede',
                width: 1000,
                height: 700,
                content: content,
                onClose: () => { window.activeCanvas = null; }
            });

            // Initialize Canvas
            setTimeout(() => {
                const container = document.getElementById('canvas-container');
                const canvas = new CanvasEngine(container);
                canvas.loadImage(imageUrl);
                window.activeCanvas = canvas;
            }, 100);
        },

        saveCoverAnnotation: function (projectId) {
            if (!window.activeCanvas) return;
            App.toast('Gemmer forside...', 'info');

            try {
                const dataUrl = window.activeCanvas.exportHighRes('image/jpeg', 0.8);
                const blob = ProjectModule.dataURItoBlob(dataUrl);

                const form = new FormData();
                form.append('project_id', projectId);
                form.append('cover_image', blob, 'cover_annotated.jpg');

                API.projects.saveCoverImage(projectId, blob)
                    .then(res => {
                        if (res.status === 'success') {
                            App.toast('Forside gemt', 'success');
                            wm.close('win-canvas-editor-project');
                            const formImg = document.querySelector('#project-form img');
                            if (formImg) {
                                formImg.src = '/assets/uploads/' + res.filename + '?t=' + Date.now();
                            }
                        } else {
                            App.toast('Fejl: ' + res.message, 'error');
                        }
                    })
                    .catch(e => {
                        console.error(e);
                        App.toast('Kunne ikke gemme', 'error');
                    });
            } catch (e) {
                console.error(e);
                App.toast('Fejl ved eksport', 'error');
            }
        },

        dataURItoBlob: function (dataURI) {
            const byteString = atob(dataURI.split(',')[1]);
            const mimeString = dataURI.split(',')[0].split(':')[1].split(';')[0];
            const ab = new ArrayBuffer(byteString.length);
            const ia = new Uint8Array(ab);
            for (let i = 0; i < byteString.length; i++) {
                ia[i] = byteString.charCodeAt(i);
            }
            return new Blob([ab], { type: mimeString });
        },

        switchTab: function (el, targetId) {
            const container = ProjectModule.getContainer(el);
            // Toggle active tab
            container.querySelectorAll('.tab-item').forEach(t => {
                t.classList.remove('active');
                t.style.borderBottom = '2px solid transparent';
                t.style.fontWeight = 'normal';
            });
            el.classList.add('active');
            el.style.borderBottom = '2px solid #007bff';
            el.style.fontWeight = 'bold';

            // Toggle content
            container.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');
            const target = container.querySelector('#' + targetId);
            if (target) target.style.display = 'block';

            if (targetId === 'tab-versions') {
                // Check if we have project ID
                const form = container.querySelector('form');
                if (form && form.querySelector('input[name="id"]')) {
                    ProjectModule.loadSnapshots(form.querySelector('input[name="id"]').value);
                }
            }
        },

        loadSnapshots: function (projectId) {
            const container = document.getElementById('snapshot-list');
            if (!container) return;
            container.innerHTML = '<small>Loading...</small>';

            API.projects.listSnapshots(projectId)
                .then(res => {
                    container.innerHTML = '';
                    if (res.snapshots && res.snapshots.length > 0) {
                        const table = document.createElement('table');
                        table.className = 'table table-sm table-striped';
                        table.style.width = '100%';
                        table.innerHTML = `
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Title</th>
                                <th>Economy</th>
                                <th>Risks</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    `;
                        const tbody = table.querySelector('tbody');
                        res.snapshots.forEach(s => {
                            let riskHtml = '';
                            try {
                                const stats = JSON.parse(s.stats_summary);
                                if (stats && stats.risk_counts) {
                                    for (let [k, v] of Object.entries(stats.risk_counts)) {
                                        let color = 'gray';
                                        if (k.includes('Rød')) color = '#dc3545';
                                        if (k.includes('Gul')) color = '#ffc107';
                                        if (k.includes('Grøn')) color = '#28a745';
                                        riskHtml += `<span class="badge" style="background:${color}; color:white; margin-right:2px; padding:2px 5px; border-radius:3px; font-size:10px;">${k}: ${v}</span> `;
                                    }
                                }
                            } catch (e) { }

                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                            <td>${s.created_at}</td>
                            <td>${s.title}</td>
                            <td>${parseFloat(s.total_price).toLocaleString('da-DK', { minimumFractionDigits: 2 })}</td>
                            <td>${riskHtml}</td>
                            <td>
                                <button class="btn btn-xs btn-warning" style="margin-right:2px;" onclick="ProjectModule.restoreSnapshot(${s.id})" title="Gendan">↩️</button>
                                <button class="btn btn-xs btn-info" onclick="ProjectModule.forkSnapshot(${s.id})" title="Fork">🍴</button>
                            </td>
                        `;
                            tbody.appendChild(tr);
                        });
                        container.appendChild(table);
                    } else {
                        container.innerHTML = '<small>No snapshots found.</small>';
                    }
                });
        },

        createSnapshot: function (projectId) {
            if (typeof API === 'undefined' || !API.projects) {
                App.toast('Systemfejl: API ikke klar', 'error');
                return;
            }
            const title = prompt("Snapshot Title (f.eks. 'Før revision'):");
            if (title === null) return;

            App.toast('Opretter snapshot...', 'info');
            const fd = new FormData();
            fd.append('project_id', projectId);
            fd.append('title', title);

            API.projects.createSnapshot(projectId, title)
                .then(res => {
                    if (res.status === 'success') {
                        App.toast('Snapshot created!', 'success');
                        ProjectModule.loadSnapshots(projectId);
                    } else {
                        App.toast('Error: ' + res.message, 'error');
                    }
                });
        },

        restoreSnapshot: function (snapshotId) {
            if (!confirm("ER DU SIKKER? Dette vil overskrive AL data i det aktuelle projekt med denne version. Handlingen kan ikke fortrydes (medmindre du har lavet et snapshot først).")) return;

            App.toast('Gendanner...', 'info');
            const fd = new FormData();
            fd.append('snapshot_id', snapshotId);

            API.projects.restoreSnapshot(snapshotId)
                .then(res => {
                    if (res.status === 'success') {
                        App.toast('Projekt gendannet!', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        App.toast('Fejl: ' + res.message, 'error');
                    }
                });
        },

        forkSnapshot: function (snapshotId) {
            const newName = prompt("Navn på nyt projekt:");
            if (!newName) return;

            App.toast('Opretter nyt projekt...', 'info');
            const fd = new FormData();
            fd.append('snapshot_id', snapshotId);
            fd.append('new_name', newName);

            App.api('?module=Project&action=forkSnapshot', 'POST', fd)
                .then(res => {
                    if (res.status === 'success') {
                        App.toast('Projekt oprettet!', 'success');
                        if (confirm("Projekt oprettet. Vil du gå til det nu?")) {
                            window.location.href = '?module=Project&action=edit&id=' + res.new_project_id;
                        }
                    } else {
                        App.toast('Fejl: ' + res.message, 'error');
                    }
                });
        },

        openProjectModal: function () {
            const template = document.getElementById('project-template');
            if (!template) return;
            const content = template.innerHTML;

            wm.createWindow({
                id: 'win-create-project',
                title: (typeof i18n !== 'undefined' ? i18n.t('project.create') : 'New Project'),
                content: content,
                width: 650,
                height: 750
            });
        },

        searchClients: function (input) {
            try {
                const query = input.value;
                const resultsContainer = input.parentElement.querySelector('.client-results');
                if (!resultsContainer) return;
                if (query.length < 2) { resultsContainer.style.display = 'none'; return; }

                API.customers.search(query)
                    .then(clients => {
                        resultsContainer.innerHTML = '';
                        if (clients.length > 0) {
                            resultsContainer.style.display = 'block';
                            clients.forEach(c => {
                                const div = document.createElement('div');
                                div.className = 'client-result-item';
                                div.style.padding = '5px';
                                div.style.cursor = 'pointer';
                                div.style.borderBottom = '1px solid #eee';
                                div.innerText = c.name;
                                div.setAttribute('data-id', c.id);
                                div.setAttribute('data-name', c.name);
                                div.onmouseover = () => div.style.background = '#f0f0f0';
                                div.onmouseout = () => div.style.background = 'white';
                                resultsContainer.appendChild(div);
                            });
                        } else {
                            resultsContainer.style.display = 'none';
                        }
                    })
                    .catch(err => {
                        console.error('Client search error:', err);
                        App.toast(i18n.t ? i18n.t('error.system') : 'Fejl ved kundesøgning', 'error');
                    });
            } catch (error) {
                console.error('Exception in searchClients:', error);
            }
        },

        searchUsers: function (input) {
            const query = input.value;
            const resultsContainer = input.parentElement.querySelector('.search-results');
            if (!resultsContainer) return;
            if (query.length < 2) { resultsContainer.style.display = 'none'; return; }

            API.users.search(query)
                .then(users => {
                    resultsContainer.innerHTML = '';
                    if (users.length > 0) {
                        resultsContainer.style.display = 'block';
                        users.forEach(u => {
                            const div = document.createElement('div');
                            div.className = 'user-result-item';
                            div.style.padding = '5px';
                            div.style.cursor = 'pointer';
                            div.style.borderBottom = '1px solid #eee';
                            div.innerText = u.username + ' (' + u.email + ')';
                            div.setAttribute('data-id', u.id);
                            div.setAttribute('data-username', u.username);
                            div.setAttribute('data-email', u.email);

                            div.onmouseover = () => div.style.background = '#f0f0f0';
                            div.onmouseout = () => div.style.background = 'white';
                            resultsContainer.appendChild(div);
                        });
                    } else {
                        resultsContainer.style.display = 'none';
                    }
                });
        },

        addTeamMember: function (user) {
            if (!this.teamMembers.find(m => m.id == user.id)) {
                user.role = user.role || 'specialist'; // Default role
                this.teamMembers.push(user);
                this.renderTeamList();
            }
        },

        removeTeamMember: function (id) {
            this.teamMembers = this.teamMembers.filter(m => m.id != id);
            this.renderTeamList();
        },

        updateMemberRole: function (id, newRole) {
            const m = this.teamMembers.find(m => m.id == id);
            if (m) {
                m.role = newRole;
                // No re-render needed as the select stays focused
            }
        },

        renderTeamList: function () {
            const container = document.getElementById('team-list');
            if (!container) return;
            container.innerHTML = '';
            if (this.teamMembers.length === 0) {
                container.innerHTML = '<small style="color:#888;">No members added yet.</small>';
                return;
            }

            this.teamMembers.forEach(tm => {
                const div = document.createElement('div');
                div.style = 'display:flex; justify-content:space-between; align-items:center; background:#f9f9f9; padding:8px; margin-bottom:5px; border-radius:4px; border:1px solid #eee;';

                const role = tm.role || 'specialist';
                const roleSelect = `
                <select name="team_members[${tm.id}]" class="form-select input-sm" 
                        style="padding:2px 5px; font-size:12px; border:1px solid #ccc; border-radius:3px; margin-left:10px;"
                        onchange="ProjectModule.updateMemberRole(${tm.id}, this.value)">
                    <option value="specialist" ${role === 'specialist' ? 'selected' : ''}>Specialist</option>
                    <option value="owner" ${role === 'owner' ? 'selected' : ''}>Ejer</option>
                    <option value="admin" ${role === 'admin' ? 'selected' : ''}>Admin</option>
                    <option value="viewer" ${role === 'viewer' ? 'selected' : ''}>Læser</option>
                </select>
            `;

                div.innerHTML = `
                <div style="display:flex; align-items:center;">
                    <span style="font-weight:500;">${tm.username}</span>
                    ${roleSelect}
                </div>
                <span class="remove-team-member" data-id="${tm.id}" style="color:#dc3545; cursor:pointer; padding:0 5px;" title="Fjern">
                    <i class="fas fa-times"></i> &times;
                </span>
            `;
                container.appendChild(div);
            });
        },

        saveProject: function (e) {
            // e is ensured to be event object now
            const form = e.target;
            if (form.name.value.trim().length < 2) {
                App.toast('Project Name is required (min 2 chars).', 'warning');
                return;
            }

            const formData = new FormData(form);
            // App.api handles FormData automatically if implemented in core.js 
            // (We updated core.js to handle FormData)

            const action = form.querySelector('[name="id"]') ? 'update' : 'store';
            App.api('?module=Project&action=' + action + '&ajax=1', 'POST', formData)
                .then(res => {
                    if (res.status === 'success') {
                        App.toast('Projekt gemt!', 'success');
                        // Reload logic
                        if (window.opener) window.opener.location.reload();
                        window.location.href = '?module=Project&action=index';
                    } else {
                        App.toast(res.message || 'Fejl ved gemning', 'error');
                    }
                })
                .catch(err => App.toast('Fejl: ' + err, 'error'));
        },

        deleteProject: function (id, name) {
            App.confirm(`Er du sikker på at du vil slette projektet "${name}"?`, () => {
                API.projects.delete(id)
                    .then(data => {
                        if (data.status === 'success') {
                            App.toast('Projekt slettet', 'success');
                            window.location.href = '?module=Project&action=index';
                        } else {
                            App.toast(data.message, 'error');
                        }
                    });
            });
        }
    };

    // Global Exposure
    window.openProjectModal = () => ProjectModule.openProjectModal();
    window.deleteProject = (id, name) => ProjectModule.deleteProject(id, name);

    // Init
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ProjectModule.init());
    } else {
        ProjectModule.init();
    }
}
