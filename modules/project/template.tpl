<?php /* Project Module - SPA Partial */ ?>

<header class="page-header">
    <div><h1><?= icon('folder', 32) ?> Projekter</h1><p class="subtitle">Administrer dine projekter</p></div>
    <button class="btn btn-primary" onclick="ProjectModule.openCreate()"><?= icon('plus', 20) ?> Opret Projekt</button>
</header>

<?php if ($successMessage): ?><script>notify('<?= esc_js($successMessage) ?>', {type: 'success'});</script><?php endif; ?>
<?php if ($errorMessage): ?><script>notify('<?= esc_js($errorMessage) ?>', {type: 'success'});</script><?php endif; ?>

<div class="search-bar">
    <form onsubmit="return ProjectModule.search(event);">
        <div class="search-group">
            <?= icon('search', 20) ?>
            <input type="text" id="searchInput" placeholder="Søg projekter..." value="<?= esc_attr($searchTerm) ?>">
            <?php if ($searchTerm): ?><button type="button" onclick="ProjectModule.clearSearch()" class="btn-clear"><?= icon('x', 16) ?></button><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-secondary">Søg</button>
    </form>
</div>

<div class="stats-card"><span class="label">Total projekter:</span><span class="value"><?= number_format($totalProjects, 0, ',', '.') ?></span></div>

<?php if (empty($projects)): ?>
<div class="empty-state"><?= icon('folder', 48) ?><h3>Ingen projekter</h3><p><?= $searchTerm ? 'Prøv anden søgning' : 'Opret dit første projekt' ?></p>
<?php if (!$searchTerm): ?><button class="btn btn-primary" onclick="ProjectModule.openCreate()"><?= icon('plus', 20) ?> Opret Projekt</button><?php endif; ?></div>
<?php else: ?>
<div class="table-container"><table class="data-table"><thead><tr><th>Projekt</th><th>Kunde</th><th>Adresse</th><th>Bygninger</th><th>Elementer</th><th>Status</th><th>Handlinger</th></tr></thead><tbody>
<?php foreach ($projects as $p): 
$statusMap = ['planning'=>'Planlægning','active'=>'Aktiv','on_hold'=>'På vent','completed'=>'Afsluttet','archived'=>'Arkiveret'];
$statusClass = ['planning'=>'badge-warning','active'=>'badge-success','on_hold'=>'badge-secondary','completed'=>'badge-info','archived'=>'badge-muted'];
?>
<tr>
    <td class="font-semibold">
        <a href="#" onclick="navigate('building', {project_id: <?= $p['id'] ?>}); return false;" class="link">
            <?= esc_html($p['name']) ?>
        </a>
    </td>
    <td><?= esc_html($p['customer_name']) ?></td>
    <td><?= esc_html($p['address']) ?>, <?= esc_html($p['postal_code']) ?> <?= esc_html($p['city']) ?></td>
    <td>
        <?php if ($p['building_count'] > 0): ?>
            <a href="#" onclick="navigate('building', {project_id: <?= $p['id'] ?>}); return false;" class="badge badge-info">
                <?= $p['building_count'] ?>
            </a>
        <?php else: ?>-<?php endif; ?>
    </td>
    <td><?= $p['element_count'] > 0 ? $p['element_count'] : '-' ?></td>
    <td><span class="badge <?= $statusClass[$p['status']??'badge-secondary'] ?>"><?= $statusMap[$p['status']]??$p['status'] ?></span></td>
    <td class="actions-column">
        <?php if ($permissions['create_snapshots']): ?>
        <button class="btn-icon" onclick="ProjectSnapshot.showManager(<?= $p['id'] ?>)" title="Snapshots">
            <?= icon('archive', 18) ?>
        </button>
        <?php endif; ?>
        <?php if ($permissions['copy_projects']): ?>
        <button class="btn-icon" onclick="ProjectModule.copyProject(<?= $p['id'] ?>, '<?= esc_js($p['name']) ?>')" title="Kopier projekt">
            <?= icon('copy', 18) ?>
        </button>
        <?php endif; ?>
        <button class="btn-icon" onclick="ProjectModule.openEdit(<?= $p['id'] ?>)">
            <?= icon('edit', 18) ?>
        </button>
        <button class="btn-icon btn-icon-danger" onclick="ProjectModule.confirmDelete(<?= $p['id'] ?>, '<?= esc_js($p['name']) ?>')">
            <?= icon('trash', 18) ?>
        </button>
    </td>
</tr>
<?php endforeach; ?>
</tbody></table></div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
<?php if ($page > 1): ?><a href="#" onclick="ProjectModule.loadPage(<?= $page - 1 ?>); return false;" class="btn btn-secondary"><?= icon('chevron-left', 16) ?> Forrige</a><?php endif; ?>
<span>Side <?= $page ?> af <?= $totalPages ?></span>
<?php if ($page < $totalPages): ?><a href="#" onclick="ProjectModule.loadPage(<?= $page + 1 ?>); return false;" class="btn btn-secondary">Næste <?= icon('chevron-right', 16) ?></a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
// Brug BaseModule for at reducere dubleret kode
const ProjectModule = Object.assign(
    new BaseModule('project', {
        titleSingular: 'Projekt',
        titlePlural: 'Projekter',
        instanceName: 'ProjectModule',
        modalSize: 'large'
    }),
    {
        search: '<?= esc_js($searchTerm) ?>',
        page: <?= $page ?>,
        customerSelect: null,

        // Override openCreate for at initialisere customerSelect
        openCreate() {
            Modal.open(this.getForm({}), {
                size: this.config.modalSize,
                title: `Opret ${this.config.titleSingular}`
            });
            setTimeout(() => this.initCustomerSelect(null, null), 100);
        },

        // Override openEdit for at initialisere customerSelect med data
        async openEdit(id) {
            try {
                App.showLoading();
                const r = await API.get('/', {
                    module: this.moduleName,
                    action: 'get',
                    id
                });
                App.hideLoading();

                if (r.success) {
                    Modal.open(this.getForm(r.data, id), {
                        size: this.config.modalSize,
                        title: `Rediger ${this.config.titleSingular}`
                    });
                    setTimeout(() => this.initCustomerSelect(r.data.customer_id, r.data.customer_label), 100);
                } else {
                    notify(r.error, { type: 'error' });
                }
            } catch (e) {
                App.hideLoading();
                notify('Fejl ved hentning', { type: 'error' });
                if (window.logError) window.logError(e);
            }
        },

        // Override submit for at rydde op i customerSelect
        async submit(event, id = null) {
            event.preventDefault();

            const formData = new FormData(event.target);
            formData.append('module', this.moduleName);
            formData.append('action', id ? 'update' : 'create');
            if (id) formData.append('id', id);

            try {
                const r = await API.post('/', formData, true);

                if (r.success) {
                    Modal.close();
                    this.customerSelect?.destroy();
                    notify(r.message || 'Gemt', { type: 'success' });
                    Router.reload();
                } else {
                    this.showErrors(r.errors || ['Fejl ved lagring']);
                }
            } catch (e) {
                notify('Lagringsfejl', { type: 'error' });
                if (window.logError) window.logError(e);
            }

            return false;
        },

        // Initialiser SearchableSelect for kunde-valg
        initCustomerSelect(value, label) {
            const container = document.getElementById('customerSelectContainer');
            if (container) {
                this.customerSelect = new SearchableSelect({
                    container: container,
                    name: 'customer_id',
                    placeholder: 'Søg og vælg kunde...',
                    searchUrl: '/?module=project&action=search_customers',
                    required: true,
                    value: value,
                    label: label
                });
            }
        },

        // Override getFormFields med projekt-specifikke felter
        getFormFields(d, id) {
            return `
                <div class="form-group">
                    <label class="required">Projektnavn</label>
                    <input name="name" value="${escapeHtml(d.name||'')}" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="required">Kunde</label>
                    <div id="customerSelectContainer"></div>
                </div>
                <div class="form-group">
                    <label class="required">Adresse</label>
                    <input name="address" value="${escapeHtml(d.address||'')}" required class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="required">Postnr</label>
                        <input name="postal_code" value="${escapeHtml(d.postal_code||'')}" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="required">By</label>
                        <input name="city" value="${escapeHtml(d.city||'')}" required class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>BBR Nummer</label>
                        <input name="bbr_number" value="${escapeHtml(d.bbr_number||'')}" class="form-control">
                        <small class="form-help">Bygnings- og Boligregistret</small>
                    </div>
                    <div class="form-group">
                        <label>Besigtigelsesdato</label>
                        <input type="date" name="inspection_date" value="${escapeHtml(d.inspection_date||'')}" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label class="required">Status</label>
                    <select name="status" required class="form-control">
                        <option value="planning" ${(d.status||'planning')=='planning'?'selected':''}>Planlægning</option>
                        <option value="active" ${d.status=='active'?'selected':''}>Aktiv</option>
                        <option value="on_hold" ${d.status=='on_hold'?'selected':''}>På vent</option>
                        <option value="completed" ${d.status=='completed'?'selected':''}>Afsluttet</option>
                        <option value="archived" ${d.status=='archived'?'selected':''}>Arkiveret</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Beskrivelse</label>
                    <textarea name="description" rows="4" class="form-control">${escapeHtml(d.description||'')}</textarea>
                </div>
            `;
        },

        // Custom getForm for at tilføje cleanup i annuller knap
        getForm(data, id = null) {
            return `
                <form onsubmit="return ${this.config.instanceName}.submit(event, ${id || null});">
                    <div class="modal-body">
                        <div id="formErrors"></div>
                        ${this.getFormFields(data, id)}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="Modal.close(); ${this.config.instanceName}.customerSelect?.destroy();">Annuller</button>
                        <button type="submit" class="btn btn-primary">${id ? 'Gem' : 'Opret'}</button>
                    </div>
                </form>
            `;
        },

        // Projekt-specifik funktionalitet: kopier projekt
        async copyProject(id, name) {
            const newName = await Modal.prompt(`Kopier projekt "${name}"`, {
                label: 'Nyt projektnavn:',
                defaultValue: `${name} (kopi)`,
                confirmText: 'Kopier',
                confirmClass: 'btn-primary'
            });

            if (!newName) return;

            App.showLoading('Kopierer projekt...');

            try {
                const fd = new FormData();
                fd.append('action', 'copy_project');
                fd.append('project_id', id);
                fd.append('new_name', newName);

                const r = await API.post('/api.php', fd, true);

                App.hideLoading();

                if (r.success) {
                    notify('Projekt kopieret', {type: 'success'});
                    Router.reload();
                } else {
                    notify(r.error || 'Kunne ikke kopiere projekt', {type: 'error'});
                }
            } catch(e) {
                App.hideLoading();
                notify('Netværksfejl ved kopiering', {type: 'error'});
                if (window.logError) window.logError(e);
            }
        },

        // Wrapper for search to use BaseModule's performSearch
        search(e) {
            return this.performSearch(e);
        }
    }
);
</script>

