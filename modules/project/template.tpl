<?php /* Project Module - SPA Partial */ ?>

<header class="page-header">
    <div><h1><?= icon('folder', 32) ?> Projekter</h1><p class="subtitle">Administrer dine projekter</p></div>
    <button class="btn btn-primary" onclick="ProjectModule.openCreate()"><?= icon('plus', 20) ?> Nyt Projekt</button>
</header>

<?php if ($successMessage): ?><script>Toast.success('<?= esc_js($successMessage) ?>');</script><?php endif; ?>
<?php if ($errorMessage): ?><script>Toast.error('<?= esc_js($errorMessage) ?>');</script><?php endif; ?>

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
const ProjectModule = {
    search: '<?= esc_js($searchTerm) ?>',
    page: <?= $page ?>,
    customerSelect: null,

    openCreate() {
        Modal.open(this.getForm({}), {size: 'large', title: 'Nyt Projekt'});
        setTimeout(() => this.initCustomerSelect(null, null), 100);
    },

    async openEdit(id) {
        try {
            App.showLoading();
            const r = await API.get('/', {module: 'project', action: 'get', id});
            App.hideLoading();
            if (r.success) {
                Modal.open(this.getForm(r.data, id), {size: 'large', title: 'Rediger Projekt'});
                setTimeout(() => this.initCustomerSelect(r.data.customer_id, r.data.customer_label), 100);
            }
            else Toast.error(r.error);
        } catch(e) { App.hideLoading(); Toast.error('Fejl ved hentning'); }
    },

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

    getForm(d, id) {
        return `<form onsubmit="return ProjectModule.submit(event, ${id||null});">
        <input type="hidden" name="action" value="${id ? 'update' : 'create'}">
        ${id ? `<input type="hidden" name="id" value="${id}">` : ''}
        <div class="modal-body"><div id="formErrors"></div>
        <div class="form-group"><label class="required">Projektnavn</label><input name="name" value="${escapeHtml(d.name||'')}" required class="form-control"></div>
        <div class="form-group"><label class="required">Kunde</label><div id="customerSelectContainer"></div></div>
        <div class="form-group"><label class="required">Adresse</label><input name="address" value="${escapeHtml(d.address||'')}" required class="form-control"></div>
        <div class="form-row">
            <div class="form-group"><label class="required">Postnr</label><input name="postal_code" value="${escapeHtml(d.postal_code||'')}" required class="form-control"></div>
            <div class="form-group"><label class="required">By</label><input name="city" value="${escapeHtml(d.city||'')}" required class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>BBR Nummer</label><input name="bbr_number" value="${escapeHtml(d.bbr_number||'')}" class="form-control"><small class="form-help">Bygnings- og Boligregistret</small></div>
            <div class="form-group"><label>Besigtigelsesdato</label><input type="date" name="inspection_date" value="${escapeHtml(d.inspection_date||'')}" class="form-control"></div>
        </div>
        <div class="form-group"><label class="required">Status</label><select name="status" required class="form-control">
            <option value="planning" ${(d.status||'planning')=='planning'?'selected':''}>Planlægning</option>
            <option value="active" ${d.status=='active'?'selected':''}>Aktiv</option>
            <option value="on_hold" ${d.status=='on_hold'?'selected':''}>På vent</option>
            <option value="completed" ${d.status=='completed'?'selected':''}>Afsluttet</option>
            <option value="archived" ${d.status=='archived'?'selected':''}>Arkiveret</option>
        </select></div>
        <div class="form-group"><label>Beskrivelse</label><textarea name="description" rows="4" class="form-control">${escapeHtml(d.description||'')}</textarea></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="Modal.close(); ProjectModule.customerSelect?.destroy();">Annuller</button>
            <button type="submit" class="btn btn-primary">${id ? 'Gem' : 'Opret'}</button>
        </div></form>`;
    },

    async submit(e, id) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('module', 'project');
        try {
            const r = await API.post('/', fd, true);
            if (r.success) { Modal.close(); this.customerSelect?.destroy(); Toast.success(r.message || 'Gemt'); Router.reload(); }
            else this.showErrors(r.errors || ['Fejl']);
        } catch(e) { Toast.error('Lagringsfejl'); }
        return false;
    },

    showErrors(errs) {
        const el = document.getElementById('formErrors');
        if (el) { el.innerHTML = `<div class="alert alert-error">${errs.map(e => escapeHtml(e)).join('<br>')}</div>`; el.style.display = 'block'; }
    },

    async confirmDelete(id, name) {
        if (await Modal.confirm(`Slet projekt "${name}"?`, {title: 'Bekræft', confirmText: 'Slet', confirmClass: 'btn-danger'})) {
            const fd = new FormData();
            fd.append('module', 'project');
            fd.append('action', 'delete');
            fd.append('id', id);
            try {
                const r = await API.post('/', fd, true);
                if (r.success) { Toast.success('Slettet'); Router.reload(); }
                else Toast.error(r.error);
            } catch(e) { Toast.error('Sletningsfejl'); }
        }
    },

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
                Toast.success('Projekt kopieret');
                Router.reload();
            } else {
                Toast.error(r.error || 'Kunne ikke kopiere projekt');
            }
        } catch(e) {
            App.hideLoading();
            Toast.error('Netværksfejl ved kopiering');
        }
    },

    search(e) { e.preventDefault(); navigate('project', {search: document.getElementById('searchInput').value}); return false; },
    clearSearch() { navigate('project'); },
    loadPage(p) { navigate('project', this.search ? {page: p, search: this.search} : {page: p}); }
};
</script>

<style>
.page-header {display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; padding-bottom: 24px; border-bottom: 2px solid var(--color-gray-200);}
.page-header h1 {display: flex; align-items: center; gap: 12px; font-size: 30px; font-weight: 700; margin: 0;}
.subtitle {color: var(--color-gray-600); font-size: 14px;}
.search-bar {margin-bottom: 24px;}
.search-bar form {display: flex; gap: 12px;}
.search-group {position: relative; flex: 1; display: flex; align-items: center;}
.search-group svg {position: absolute; left: 12px; color: var(--color-gray-400);}
.search-group input {flex: 1; padding-left: 40px;}
.btn-clear {position: absolute; right: 8px; padding: 4px; background: transparent; border: none; color: var(--color-gray-400); cursor: pointer; border-radius: 4px;}
.btn-clear:hover {background: var(--color-gray-100);}
.stats-card {display: flex; align-items: baseline; gap: 12px; margin-bottom: 24px; padding: 16px; background: white; border-radius: 8px; border: 1px solid var(--color-gray-200);}
.stats-card .label {font-size: 14px; color: var(--color-gray-600);}
.stats-card .value {font-size: 24px; font-weight: 700; color: var(--color-primary);}
.empty-state {display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 64px; text-align: center; background: white; border-radius: 8px; border: 2px dashed var(--color-gray-300);}
.empty-state svg {color: var(--color-gray-300); margin-bottom: 16px;}
.empty-state h3 {font-size: 20px; font-weight: 600; margin: 8px 0;}
.empty-state p {color: var(--color-gray-600); margin-bottom: 24px;}
.pagination {display: flex; align-items: center; justify-content: center; gap: 16px; margin-top: 24px;}
</style>
