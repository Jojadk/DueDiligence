<?php /* Building Module - SPA Partial */ ?>

<header class="page-header">
    <div><h1><?= icon('building', 32) ?> Bygninger</h1><p class="subtitle">Administrer bygninger</p></div>
    <button class="btn btn-primary" onclick="BuildingModule.openCreate()"><?= icon('plus', 20) ?> Opret Bygning</button>
</header>

<?php if ($successMessage): ?><script>notify('<?= esc_js($successMessage) ?>', {type: 'success'});</script><?php endif; ?>
<?php if ($errorMessage): ?><script>notify('<?= esc_js($errorMessage) ?>', {type: 'success'});</script><?php endif; ?>

<div class="search-bar">
    <form onsubmit="return BuildingModule.search(event);">
        <div class="search-group">
            <?= icon('search', 20) ?>
            <input type="text" id="searchInput" placeholder="Søg bygninger..." value="<?= esc_attr($searchTerm) ?>">
            <?php if ($searchTerm): ?><button type="button" onclick="BuildingModule.clearSearch()" class="btn-clear"><?= icon('x', 16) ?></button><?php endif; ?>
        </div>
        <button type="submit" class="btn btn-secondary">Søg</button>
    </form>
</div>

<div class="stats-card"><span class="label">Total bygninger:</span><span class="value"><?= number_format($totalBuildings, 0, ',', '.') ?></span></div>

<?php if (empty($buildings)): ?>
<div class="empty-state"><?= icon('building', 48) ?><h3>Ingen bygninger</h3><p><?= $searchTerm ? 'Prøv anden søgning' : 'Opret din første bygning' ?></p>
<?php if (!$searchTerm): ?><button class="btn btn-primary" onclick="BuildingModule.openCreate()"><?= icon('plus', 20) ?> Opret Bygning</button><?php endif; ?></div>
<?php else: ?>
<div class="table-container"><table class="data-table"><thead><tr><th>Bygning</th><th>Projekt</th><th>Bygningsnr</th><th>Areal (m²)</th><th>Byggeår</th><th>Etager</th><th>Elementer</th><th>Handlinger</th></tr></thead><tbody>
<?php foreach ($buildings as $b): ?>
<tr><td class="font-semibold"><a href="#" onclick="navigate('building_element', {building_id: <?= $b['id'] ?>}); return false;" class="link"><?= esc_html($b['name']) ?></a></td><td><a href="#" onclick="navigate('project'); return false;" class="link-muted"><?= esc_html($b['project_name']) ?></a></td><td><?= esc_html($b['building_number'] ?: '-') ?></td><td><?= $b['area_m2'] ? number_format($b['area_m2'], 0, ',', '.') : '-' ?></td><td><?= $b['construction_year'] ?: '-' ?></td><td><?= $b['floors'] ?></td><td><?php if ($b['element_count'] > 0): ?><a href="#" onclick="navigate('building_element', {building_id: <?= $b['id'] ?>}); return false;" class="badge badge-info"><?= $b['element_count'] ?></a><?php else: ?>-<?php endif; ?></td><td class="actions-column"><button class="btn-icon" onclick="BuildingModule.openEdit(<?= $b['id'] ?>)"><?= icon('edit', 18) ?></button><button class="btn-icon btn-icon-danger" onclick="BuildingModule.confirmDelete(<?= $b['id'] ?>, '<?= esc_js($b['name']) ?>')"><?= icon('trash', 18) ?></button></td></tr>
<?php endforeach; ?>
</tbody></table></div>

<?php if ($totalPages > 1): ?>
<div class="pagination">
<?php if ($page > 1): ?><a href="#" onclick="BuildingModule.loadPage(<?= $page - 1 ?>); return false;" class="btn btn-secondary"><?= icon('chevron-left', 16) ?> Forrige</a><?php endif; ?>
<span>Side <?= $page ?> af <?= $totalPages ?></span>
<?php if ($page < $totalPages): ?><a href="#" onclick="BuildingModule.loadPage(<?= $page + 1 ?>); return false;" class="btn btn-secondary">Næste <?= icon('chevron-right', 16) ?></a><?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
// Brug BaseModule for at reducere dubleret kode
const BuildingModule = Object.assign(
    new BaseModule('building', {
        titleSingular: 'Bygning',
        titlePlural: 'Bygninger',
        instanceName: 'BuildingModule',
        modalSize: 'large'
    }),
    {
        search: '<?= esc_js($searchTerm) ?>',
        page: <?= $page ?>,
        projectSelect: null,

        // Override openCreate for at initialisere projectSelect
        openCreate() {
            Modal.open(this.getForm({}), {
                size: this.config.modalSize,
                title: `Opret ${this.config.titleSingular}`
            });
            setTimeout(() => this.initProjectSelect(null, null), 100);
        },

        // Override openEdit for at initialisere projectSelect med data
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
                    setTimeout(() => this.initProjectSelect(r.data.project_id, r.data.project_label), 100);
                } else {
                    notify(r.error, { type: 'error' });
                }
            } catch (e) {
                App.hideLoading();
                notify('Fejl ved hentning', { type: 'error' });
                if (window.logError) window.logError(e);
            }
        },

        // Override submit for at rydde op i projectSelect
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
                    this.projectSelect?.destroy();
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

        // Initialiser SearchableSelect for projekt-valg
        initProjectSelect(value, label) {
            const container = document.getElementById('projectSelectContainer');
            if (container) {
                this.projectSelect = new SearchableSelect({
                    container: container,
                    name: 'project_id',
                    placeholder: 'Søg og vælg projekt...',
                    searchUrl: '/?module=building&action=search_projects',
                    required: true,
                    value: value,
                    label: label
                });
            }
        },

        // Override getFormFields med bygnings-specifikke felter
        getFormFields(d, id) {
            return `
                <div class="form-group">
                    <label class="required">Bygningsnavn</label>
                    <input name="name" value="${escapeHtml(d.name||'')}" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="required">Projekt</label>
                    <div id="projectSelectContainer"></div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Bygningsnummer</label>
                        <input name="building_number" value="${escapeHtml(d.building_number||'')}" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Areal (m²)</label>
                        <input type="number" step="0.01" name="area_m2" value="${d.area_m2||''}" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Byggeår</label>
                        <input type="number" name="construction_year" value="${d.construction_year||''}" min="1800" max="2100" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Renoveringsår</label>
                        <input type="number" name="renovation_year" value="${d.renovation_year||''}" min="1800" max="2100" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Varmetype</label>
                        <select name="heating_type" class="form-control">
                            <option value="">Vælg...</option>
                            <option ${d.heating_type=='fjernvarme'?'selected':''}>fjernvarme</option>
                            <option ${d.heating_type=='naturgas'?'selected':''}>naturgas</option>
                            <option ${d.heating_type=='olie'?'selected':''}>olie</option>
                            <option ${d.heating_type=='el'?'selected':''}>el</option>
                            <option ${d.heating_type=='varmepumpe'?'selected':''}>varmepumpe</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Anvendelse</label>
                        <select name="usage_type" class="form-control">
                            <option value="">Vælg...</option>
                            <option ${d.usage_type=='bolig'?'selected':''}>bolig</option>
                            <option ${d.usage_type=='erhverv'?'selected':''}>erhverv</option>
                            <option ${d.usage_type=='industri'?'selected':''}>industri</option>
                            <option ${d.usage_type=='offentlig'?'selected':''}>offentlig</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Antal etager</label>
                    <input type="number" name="floors" value="${d.floors||1}" min="1" max="100" class="form-control">
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
                        <button type="button" class="btn btn-secondary" onclick="Modal.close(); ${this.config.instanceName}.projectSelect?.destroy();">Annuller</button>
                        <button type="submit" class="btn btn-primary">${id ? 'Gem' : 'Opret'}</button>
                    </div>
                </form>
            `;
        },

        // Wrapper for search to use BaseModule's performSearch
        search(e) {
            return this.performSearch(e);
        }
    }
);
</script>
