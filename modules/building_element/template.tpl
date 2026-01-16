<?php /* Building Element Module - Hierarchical */ ?>

<header class="page-header">
    <div><h1><?= icon('list', 32) ?> Bygningsdele</h1><p class="subtitle">Administrer bygningsdele</p></div>
    <button class="btn btn-primary" onclick="ElementModule.openCreate()"><?= icon('plus', 20) ?> Ny Bygningsdel</button>
</header>

<?php if ($successMessage): ?><script>Toast.success('<?= esc_js($successMessage) ?>');</script><?php endif; ?>
<?php if ($errorMessage): ?><script>Toast.error('<?= esc_js($errorMessage) ?>');</script><?php endif; ?>

<div class="stats-card"><span class="label">Total elementer:</span><span class="value"><?= number_format($totalElements, 0, ',', '.') ?></span></div>

<?php if (empty($elements)): ?>
<div class="empty-state"><?= icon('list', 48) ?><h3>Ingen bygningsdele</h3><p>Opret din første bygningsdel</p>
<button class="btn btn-primary" onclick="ElementModule.openCreate()"><?= icon('plus', 20) ?> Opret Element</button></div>
<?php else: ?>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <?php if ($permissions['edit_elements']): ?><th style="width:40px;"></th><?php endif; ?>
                <th>Element</th>
                <th>Bygning</th>
                <th>Type</th>
                <th>Lokation</th>
                <th>Tilstand</th>
                <th>Hastighed</th>
                <th>CAPEX</th>
                <th>Handlinger</th>
            </tr>
        </thead>
        <tbody id="elementsTable">
            <?php foreach ($elements as $e):
            $urgencyMap = ['low'=>'Lav','normal'=>'Normal','high'=>'Høj','critical'=>'Kritisk'];
            $urgencyClass = ['low'=>'badge-info','normal'=>'badge-secondary','high'=>'badge-warning','critical'=>'badge-error'];
            ?>
            <tr data-id="<?= $e['id'] ?>">
                <?php if ($permissions['edit_elements']): ?>
                <td class="drag-handle" title="Træk for at ændre rækkefølge"><?= icon('menu', 18) ?></td>
                <?php endif; ?>
                <td class="font-semibold"><?= $e['parent_name'] ? '↳ ' : '' ?><?= esc_html($e['name']) ?></td>
                <td><?= esc_html($e['building_name']) ?></td>
                <td><?= esc_html($e['element_type'] ?: '-') ?></td>
                <td><?= esc_html($e['location'] ?: '-') ?></td>
                <td><?= $e['condition_score'] ? $e['condition_score'] . '/10' : '-' ?></td>
                <td><span class="badge <?= $urgencyClass[$e['urgency']??'normal'] ?>"><?= $urgencyMap[$e['urgency']??'normal'] ?></span></td>
                <td><?= $e['capex'] ? number_format($e['capex'], 0, ',', '.') . ' kr' : '-' ?></td>
                <td class="actions-column">
                    <button class="btn-icon" onclick="ElementModule.openEdit(<?= $e['id'] ?>)"><?= icon('edit', 18) ?></button>
                    <button class="btn-icon btn-icon-danger" onclick="ElementModule.confirmDelete(<?= $e['id'] ?>, '<?= esc_js($e['name']) ?>')"><?= icon('trash', 18) ?></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<script>
const ElementModule = {
    buildingSelect: null,
    parentSelect: null,
    priceSelect: null,

    openCreate() {
        Modal.open(this.getForm({}), {size: 'large', title: 'Ny Bygningsdel'});
        setTimeout(() => {
            this.initBuildingSelect(null, null);
            this.initParentSelect(null, null);
            this.initPriceSelect(null, null);
        }, 100);
    },

    async openEdit(id) {
        try {
            App.showLoading();
            const r = await API.get('/', {module: 'building_element', action: 'get', id});
            App.hideLoading();
            if (r.success) {
                Modal.open(this.getForm(r.data, id), {size: 'large', title: 'Rediger Bygningsdel'});
                setTimeout(() => {
                    this.initBuildingSelect(r.data.building_id, r.data.building_label);
                    this.initParentSelect(r.data.parent_id, r.data.parent_label);
                    this.initPriceSelect(r.data.price_catalog_id, r.data.price_catalog_label);
                }, 100);
            }
            else Toast.error(r.error);
        } catch(e) { App.hideLoading(); Toast.error('Fejl'); }
    },

    initBuildingSelect(value, label) {
        const c = document.getElementById('buildingSelectContainer');
        if (c) this.buildingSelect = new SearchableSelect({container: c, name: 'building_id', placeholder: 'Søg bygning...', searchUrl: '/?module=building_element&action=search_buildings', required: true, value, label});
    },

    initParentSelect(value, label) {
        const c = document.getElementById('parentSelectContainer');
        if (c) this.parentSelect = new SearchableSelect({container: c, name: 'parent_id', placeholder: 'Søg overordnet element...', searchUrl: '/?module=building_element&action=search_parents', required: false, value, label});
    },

    initPriceSelect(value, label) {
        const c = document.getElementById('priceSelectContainer');
        if (c) this.priceSelect = new SearchableSelect({container: c, name: 'price_catalog_id', placeholder: 'Søg priskatalog...', searchUrl: '/?module=building_element&action=search_price_catalog', required: false, value, label});
    },

    getForm(d, id) {
        return `<form onsubmit="return ElementModule.submit(event, ${id||null});">
        <input type="hidden" name="action" value="${id ? 'update' : 'create'}">
        ${id ? `<input type="hidden" name="id" value="${id}">` : ''}
        <div class="modal-body">
        <div id="formErrors"></div>

        ${id ? `
        <div class="form-tabs">
            <button type="button" class="tab-btn active" onclick="ElementModule.switchTab('details')">Detaljer</button>
            <button type="button" class="tab-btn" onclick="ElementModule.switchTab('images')">Billeder</button>
        </div>
        ` : ''}

        <div id="tabDetails" class="tab-content active">
        <div class="form-group"><label class="required">Navn</label><input name="name" value="${escapeHtml(d.name||'')}" required class="form-control"></div>
        <div class="form-group"><label class="required">Bygning</label><div id="buildingSelectContainer"></div></div>
        <div class="form-group"><label>Overordnet element</label><div id="parentSelectContainer"></div><small class="form-help">Lad stå tom for rodelement</small></div>
        <div class="form-group"><label>Priskatalog template</label><div id="priceSelectContainer"></div></div>
        <div class="form-row">
            <div class="form-group"><label>Type</label><input name="element_type" value="${escapeHtml(d.element_type||'')}" class="form-control"></div>
            <div class="form-group"><label>Lokation</label><input name="location" value="${escapeHtml(d.location||'')}" class="form-control"></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Tilstand (0-10)</label><input type="number" name="condition_score" value="${d.condition_score||''}" min="0" max="10" class="form-control"></div>
            <div class="form-group"><label>Hastighed</label><select name="urgency" class="form-control">
                <option value="low" ${d.urgency=='low'?'selected':''}>Lav</option>
                <option value="normal" ${(d.urgency||'normal')=='normal'?'selected':''}>Normal</option>
                <option value="high" ${d.urgency=='high'?'selected':''}>Høj</option>
                <option value="critical" ${d.urgency=='critical'?'selected':''}>Kritisk</option>
            </select></div>
        </div>
        <div class="form-row">
            <div class="form-group"><label>Tidshorisont (år)</label><input type="number" name="time_horizon" value="${d.time_horizon||''}" min="0" max="50" class="form-control"></div>
            <div class="form-group"><label>CAPEX (kr)</label><input type="number" step="0.01" name="capex" value="${d.capex||''}" class="form-control"></div>
        </div>
        <div class="form-group"><label>Genanskaffelsesværdi (kr)</label><input type="number" step="0.01" name="replacement_value" value="${d.replacement_value||''}" class="form-control"></div>
        <div class="form-group"><label>Beskrivelse</label><textarea name="description" rows="3" class="form-control">${escapeHtml(d.description||'')}</textarea></div>
        </div>

        ${id ? `
        <div id="tabImages" class="tab-content">
            <div class="images-section">
                <div class="images-header">
                    <button type="button" class="btn btn-primary" onclick="ImageUpload.openUploadModal('building_element', ${id}, () => ImageUpload.showGallery('building_element', ${id}, '.element-gallery'))">
                        ${this.getIcon('upload', 18)} Upload billeder
                    </button>
                </div>
                <div class="element-gallery"></div>
            </div>
        </div>
        ` : ''}

        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="Modal.close(); ElementModule.cleanup();">Annuller</button>
            <button type="submit" class="btn btn-primary">${id ? 'Gem' : 'Opret'}</button>
        </div></form>`;
    },

    cleanup() {
        this.buildingSelect?.destroy();
        this.parentSelect?.destroy();
        this.priceSelect?.destroy();
    },

    async submit(e, id) {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('module', 'building_element');
        try {
            const r = await API.post('/', fd, true);
            if (r.success) { Modal.close(); this.cleanup(); Toast.success(r.message || 'Gemt'); Router.reload(); }
            else { const el = document.getElementById('formErrors'); if (el) { el.innerHTML = `<div class="alert alert-error">${r.errors.map(e => escapeHtml(e)).join('<br>')}</div>`; el.style.display = 'block'; } }
        } catch(e) { Toast.error('Lagringsfejl'); }
        return false;
    },

    async confirmDelete(id, name) {
        if (await Modal.confirm(`Slet element "${name}"?`, {title: 'Bekræft', confirmText: 'Slet', confirmClass: 'btn-danger'})) {
            const fd = new FormData();
            fd.append('module', 'building_element');
            fd.append('action', 'delete');
            fd.append('id', id);
            try {
                const r = await API.post('/', fd, true);
                if (r.success) { Toast.success('Slettet'); Router.reload(); }
                else Toast.error(r.error);
            } catch(e) { Toast.error('Sletningsfejl'); }
        }
    },

    switchTab(tabName) {
        // Remove active from all tabs and buttons
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

        // Add active to selected tab
        document.querySelector(`[onclick*="'${tabName}'"]`)?.classList.add('active');
        const tabContent = document.getElementById(`tab${tabName.charAt(0).toUpperCase() + tabName.slice(1)}`);
        if (tabContent) {
            tabContent.classList.add('active');

            // Load images when images tab is opened
            if (tabName === 'images') {
                const elementId = new URLSearchParams(window.location.search).get('id');
                if (elementId) {
                    ImageUpload.showGallery('building_element', elementId, '.element-gallery');
                }
            }
        }
    },

    getIcon(name, size) {
        return `<svg width="${size}" height="${size}" class="icon"><use href="#icon-${name}"></use></svg>`;
    }
};

// Initialize drag-and-drop sorting if user has permission
<?php if ($permissions['edit_elements'] && !empty($elements)): ?>
DragDrop.init('elementsTable', {
    handle: '.drag-handle',
    buildingId: <?= $buildingId ?: 'null' ?>,
    onUpdate: (itemIds) => {
        console.log('Elements reordered:', itemIds);
    }
});
<?php endif; ?>
</script>

<style>
.drag-handle {
    color: var(--color-gray-400);
    cursor: move;
    user-select: none;
    text-align: center;
    padding: 8px !important;
}

.drag-handle:hover {
    color: var(--color-primary);
}

tr.dragging {
    opacity: 0.5;
    background: var(--color-gray-100);
}

tbody#elementsTable tr {
    transition: background-color 0.15s ease;
}

tbody#elementsTable tr:hover {
    background: var(--color-gray-50);
}

/* Form Tabs */
.form-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    border-bottom: 2px solid var(--color-gray-200);
}

.tab-btn {
    padding: 12px 24px;
    background: transparent;
    border: none;
    border-bottom: 2px solid transparent;
    color: var(--color-gray-600);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    margin-bottom: -2px;
}

.tab-btn:hover {
    color: var(--color-primary);
    background: var(--color-gray-50);
}

.tab-btn.active {
    color: var(--color-primary);
    border-bottom-color: var(--color-primary);
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Images Section */
.images-section {
    padding: 16px 0;
}

.images-header {
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
</style>
