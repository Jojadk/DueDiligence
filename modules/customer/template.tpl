<?php /* Customer Module - SPA Partial */ ?>

<header class="page-header">
    <div><h1><?= icon('users', 32) ?> Kunder</h1><p class="subtitle">Administrer dine kunder</p></div>
    <button class="btn btn-primary" onclick="CustomerModule.openCreate()"><?= icon('plus', 20) ?> Opret Kunde</button>
</header>

<?php if ($successMessage): ?><script>notify('<?= esc_js($successMessage) ?>', {type: 'success'});</script><?php endif; ?>
<?php if ($errorMessage): ?><script>notify('<?= esc_js($errorMessage) ?>', {type: 'success'});</script><?php endif; ?>

<div class="search-bar">
    <form onsubmit="return CustomerModule.search(event);">
        <div class="search-group">
            <?= icon('search', 20) ?>
            <input type="text" id="searchInput" placeholder="Søg kunder..." value="<?= esc_attr($searchTerm) ?>">
            <?php if ($searchTerm): ?><button type="button" onclick="CustomerModule.clearSearch()" class="btn-clear"><?= icon('x', 16) ?></button><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-secondary">Søg</button>
    </form>
</div>

<div class="stats-card"><span class="label">Totalkunder:</span><span class="value"><?= number_format($totalCustomers, 0, ',', '.') ?></span></div>

<?php if (empty($customers)): ?>
<div class="empty-state"><?= icon('users', 48) ?><h3>Ingen kunder</h3><p><?= $searchTerm ? 'Prøv anden søgning' : 'Opret din første kunde' ?></p>
<?php if (!$searchTerm): ?><button class="btn btn-primary" onclick="CustomerModule.openCreate()"><?= icon('plus', 20) ?> Opret Kunde</button><?php endif; ?></div>
<?php else: ?>
<div class="table-container"><table class="data-table"><thead><tr><th>Firma</th><th>CVR</th><th>Kontakt</th><th>Email</th><th>Telefon</th><th>Projekter</th><th>Handlinger</th></tr></thead><tbody>
<?php foreach ($customers as $c): ?>
<tr><td class="font-semibold"><?= esc_html($c['name']) ?></td><td><?= esc_html($c['cvr_number'] ?: '-') ?></td><td><?= esc_html($c['contact_person'] ?: '-') ?></td><td><?= esc_html($c['email'] ?: '-') ?></td><td><?= esc_html($c['phone'] ?: '-') ?></td><td><?php if ($c['project_count'] > 0): ?><a href="#" onclick="navigate('project', {customer_id: <?= $c['id'] ?>}); return false;" class="badge badge-info"><?= $c['project_count'] ?></a><?php else: ?>-<?php endif; ?></td><td class="actions-column"><button class="btn-icon" onclick="CustomerModule.openEdit(<?= $c['id'] ?>)"><?= icon('edit', 18) ?></button><button class="btn-icon btn-icon-danger" onclick="CustomerModule.confirmDelete(<?= $c['id'] ?>, '<?= esc_js($c['name']) ?>')"><?= icon('trash', 18) ?></button></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
<?php if ($page > 1): ?><a href="#" onclick="CustomerModule.loadPage(<?= $page - 1 ?>); return false;" class="btn btn-secondary"><?= icon('chevron-left', 16) ?> Forrige</a><?php endif; ?>
<span>Side <?= $page ?> af <?= $totalPages ?></span>
<?php if ($page < $totalPages): ?><a href="#" onclick="CustomerModule.loadPage(<?= $page + 1 ?>); return false;" class="btn btn-secondary">Næste <?= icon('chevron-right', 16) ?></a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
const CustomerModule = {
    search: '<?= esc_js($searchTerm) ?>',
    page: <?= $page ?>,

    openCreate() {
        Modal.open(this.getForm({}), {size: 'medium', title: 'Ny Kunde'});
    },

    async openEdit(id) {
        try {
            App.showLoading();
            const r = await API.get('/', {module: 'customer', action: 'get', id});
            App.hideLoading();
            if (r.success) Modal.open(this.getForm(r.data, id), {size: 'medium', title: 'Rediger Kunde'});
            else notify(r.error, {type: 'error'});
        } catch(e) { App.hideLoading(); notify('Fejl ved hentning', {type: 'error'}); }
    },

    getForm(d, id) {
        return `<form onsubmit="return CustomerModule.submit(event, ${id||null});">
        <input type="hidden" name="action" value="${id ? 'update' : 'create'}">
        ${id ? `<input type="hidden" name="id" value="${id}">` : ''}
        <div class="modal-body"><div id="formErrors"></div>
        <div class="form-group"><label class="required">Firmanavn</label><input name="name" value="${escapeHtml(d.name||'')}" required maxlength="255" class="form-control"></div>
        <div class="form-row">
            <div class="form-group"><label>CVR</label><input name="cvr_number" value="${escapeHtml(d.cvr_number||'')}" pattern="^[0-9]{8}$" class="form-control"><small class="form-help">8 cifre</small></div>
            <div class="form-group"><label>Kontakt</label><input name="contact_person" value="${escapeHtml(d.contact_person||'')}" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Email</label><input type="email" name="email" value="${escapeHtml(d.email||'')}" class="form-control"></div>
            <div class="form-group"><label>Telefon</label><input name="phone" value="${escapeHtml(d.phone||'')}" class="form-control"></div>
        </div>
        <div class="form-group"><label>Adresse</label><input name="address" value="${escapeHtml(d.address||'')}" class="form-control"></div>
        <div class="form-row">
            <div class="form-group"><label>Postnr</label><input name="postal_code" value="${escapeHtml(d.postal_code||'')}" class="form-control"></div>
            <div class="form-group"><label>By</label><input name="city" value="${escapeHtml(d.city||'')}" class="form-control"></div>
        </div>
        <div class="form-group"><label>Noter</label><textarea name="notes" rows="4" class="form-control">${escapeHtml(d.notes||'')}</textarea></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-modal-close>Annuller</button>
            <button type="submit" class="btn btn-primary">${id ? 'Gem' : 'Opret'}</button>
        </div></form>`;
    },

    async submit(e, id) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('module', 'customer');
        try {
            const r = await API.post('/', fd, true);
            if (r.success) { Modal.close(); notify(r.message || 'Gemt', {type: 'success'}); Router.reload(); }
            else this.showErrors(r.errors || ['Fejl']);
        } catch(e) { notify('Lagringsfejl', {type: 'error'}); }
        return false;
    },

    showErrors(errs) {
        const el = document.getElementById('formErrors');
        if (el) { el.innerHTML = `<div class="alert alert-error">${errs.map(e => escapeHtml(e)).join('<br>')}</div>`; el.style.display = 'block'; }
    },

    async confirmDelete(id, name) {
        if (await Modal.confirm(`Slet kunde "${name}"?`, {title: 'Bekræft', confirmText: 'Slet', confirmClass: 'btn-danger'})) {
            const fd = new FormData();
            fd.append('module', 'customer');
            fd.append('action', 'delete');
            fd.append('id', id);
            try {
                const r = await API.post('/', fd, true);
                if (r.success) { notify('Slettet', {type: 'success'}); Router.reload(); }
                else notify(r.error, {type: 'error'});
            } catch(e) { notify('Sletningsfejl', {type: 'error'}); }
        }
    },

    search(e) { e.preventDefault(); navigate('customer', {search: document.getElementById('searchInput').value}); return false; },
    clearSearch() { navigate('customer'); },
    loadPage(p) { navigate('customer', this.search ? {page: p, search: this.search} : {page: p}); }
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
