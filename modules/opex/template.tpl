<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OPEX - Driftsomkostninger</title>
</head>
<body>

<div class="module-container">
    <?php if ($building): ?>
        <!-- Building-specific OPEX view -->
        <div class="module-header">
            <div>
                <h1><?= icon('trending-up', 24) ?> OPEX - Driftsomkostninger</h1>
                <div class="breadcrumb">
                    <a href="/index.php?module=project">Projekter</a> /
                    <a href="/index.php?module=project&id=<?= $project['id'] ?>"><?= htmlspecialchars($project['name']) ?></a> /
                    <a href="/index.php?module=building&id=<?= $building['id'] ?>"><?= htmlspecialchars($building['name']) ?></a> /
                    <span>OPEX</span>
                </div>
            </div>
            <?php if ($permissions['edit_buildings']): ?>
                <button class="btn-primary" onclick="OpexModule.openAssignModal(<?= $building['id'] ?>)">
                    <?= icon('plus', 18) ?> Tilføj OPEX kategori
                </button>
            <?php endif; ?>
        </div>

        <!-- OPEX Summary Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h3>OPEX Oversigt</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                    <div class="stat-card">
                        <div class="stat-label">Bygningsareal</div>
                        <div class="stat-value"><?= number_format($building['area'] ?? 0, 0, ',', '.') ?> m²</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">OPEX per år</div>
                        <div class="stat-value"><?= number_format($totalOpexPerYear, 0, ',', '.') ?> kr</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">OPEX per m² per år</div>
                        <div class="stat-value">
                            <?php
                            $area = (float)($building['area'] ?? 0);
                            $perSqm = $area > 0 ? $totalOpexPerYear / $area : 0;
                            echo number_format($perSqm, 2, ',', '.');
                            ?> kr/m²
                        </div>
                    </div>
                    <?php
                    $lifecycleYears = (float)($tcoConfig['lifecycle_years']['config_value'] ?? 30);
                    $totalOpexLifecycle = $totalOpexPerYear * $lifecycleYears;
                    ?>
                    <div class="stat-card">
                        <div class="stat-label">OPEX over <?= $lifecycleYears ?> år</div>
                        <div class="stat-value"><?= number_format($totalOpexLifecycle, 0, ',', '.') ?> kr</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assigned OPEX Categories -->
        <div class="card">
            <div class="card-header">
                <h3>Tilknyttede OPEX kategorier</h3>
            </div>
            <div class="card-body">
                <?php if (empty($assignedOpex)): ?>
                    <div class="empty-state">
                        <?= icon('inbox', 48) ?>
                        <p>Ingen OPEX kategorier tilknyttet endnu</p>
                        <?php if ($permissions['edit_buildings']): ?>
                            <button class="btn-primary" onclick="OpexModule.openAssignModal(<?= $building['id'] ?>)">
                                Tilføj første kategori
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kategori</th>
                                <th>Type</th>
                                <th>Sats per m²/år</th>
                                <th>Total per år</th>
                                <th>Noter</th>
                                <?php if ($permissions['edit_buildings']): ?>
                                    <th>Handlinger</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($assignedOpex as $opex): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($opex['name']) ?></strong>
                                        <?php if ($opex['custom_rate_per_sqm']): ?>
                                            <span class="badge badge-info" title="Tilpasset sats">Tilpasset</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge">
                                            <?= htmlspecialchars($categoryTypeLabels[$opex['category_type']] ?? $opex['category_type']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($opex['effective_rate'], 2, ',', '.') ?> kr/m²</td>
                                    <td>
                                        <strong>
                                            <?= number_format($opex['effective_rate'] * ($building['area'] ?? 0), 0, ',', '.') ?> kr
                                        </strong>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($opex['notes'] ?? '-') ?>
                                    </td>
                                    <?php if ($permissions['edit_buildings']): ?>
                                        <td>
                                            <button class="btn-icon"
                                                    onclick="OpexModule.editAssignment(<?= $opex['id'] ?>, <?= $building['id'] ?>)"
                                                    title="Rediger">
                                                <?= icon('edit', 16) ?>
                                            </button>
                                            <button class="btn-icon btn-danger"
                                                    onclick="OpexModule.removeAssignment(<?= $opex['id'] ?>, <?= $building['id'] ?>)"
                                                    title="Fjern">
                                                <?= icon('trash', 16) ?>
                                            </button>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3"><strong>Total OPEX per år</strong></td>
                                <td><strong><?= number_format($totalOpexPerYear, 0, ',', '.') ?> kr</strong></td>
                                <td colspan="<?= $permissions['edit_buildings'] ? 2 : 1 ?>"></td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- Admin view: Manage OPEX categories and TCO config -->
        <div class="module-header">
            <div>
                <h1><?= icon('trending-up', 24) ?> OPEX Administration</h1>
                <p class="text-muted">Administrer OPEX kategorier og TCO konfiguration</p>
            </div>
        </div>

        <!-- Tabs for Categories and TCO Config -->
        <div class="tabs">
            <button class="tab-btn active" data-tab="categories">OPEX Kategorier</button>
            <?php if ($permissions['admin']): ?>
                <button class="tab-btn" data-tab="tco-config">TCO Konfiguration</button>
            <?php endif; ?>
        </div>

        <!-- OPEX Categories Tab -->
        <div class="tab-content active" id="tab-categories">
            <div class="card">
                <div class="card-header">
                    <h3>OPEX Kategorier (Erfaringstal per m² per år)</h3>
                    <?php if ($permissions['admin']): ?>
                        <button class="btn-primary" onclick="OpexModule.createCategory()">
                            <?= icon('plus', 18) ?> Opret kategori
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php foreach ($categoriesByType as $type => $categories): ?>
                        <h4 class="mt-4 mb-2"><?= htmlspecialchars($categoryTypeLabels[$type] ?? $type) ?></h4>
                        <table class="data-table mb-4">
                            <thead>
                                <tr>
                                    <th>Navn</th>
                                    <th>Beskrivelse</th>
                                    <th>Sats per m²/år</th>
                                    <th>Status</th>
                                    <?php if ($permissions['admin']): ?>
                                        <th>Handlinger</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $cat): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                                        <td><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                                        <td><?= number_format($cat['rate_per_sqm'], 2, ',', '.') ?> kr/m²</td>
                                        <td>
                                            <?php if ($cat['is_active']): ?>
                                                <span class="badge badge-success">Aktiv</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Inaktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($permissions['admin']): ?>
                                            <td>
                                                <button class="btn-icon"
                                                        onclick="OpexModule.editCategory(<?= $cat['id'] ?>)"
                                                        title="Rediger">
                                                    <?= icon('edit', 16) ?>
                                                </button>
                                                <button class="btn-icon btn-danger"
                                                        onclick="OpexModule.toggleCategory(<?= $cat['id'] ?>, <?= $cat['is_active'] ? 'false' : 'true' ?>)"
                                                        title="<?= $cat['is_active'] ? 'Deaktiver' : 'Aktiver' ?>">
                                                    <?= icon($cat['is_active'] ? 'eye-off' : 'eye', 16) ?>
                                                </button>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TCO Configuration Tab -->
        <?php if ($permissions['admin']): ?>
            <div class="tab-content" id="tab-tco-config">
                <div class="card">
                    <div class="card-header">
                        <h3>TCO Konfiguration</h3>
                        <p class="text-muted">Konfigurer konstanter for Total Cost of Ownership beregninger</p>
                    </div>
                    <div class="card-body">
                        <form id="tcoConfigForm" onsubmit="OpexModule.saveTcoConfig(event)">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Parameter</th>
                                        <th>Beskrivelse</th>
                                        <th>Værdi</th>
                                        <th>Enhed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tcoConfig as $config): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($config['config_key']) ?></strong></td>
                                            <td><?= htmlspecialchars($config['description'] ?? '-') ?></td>
                                            <td>
                                                <input type="number"
                                                       step="0.0001"
                                                       name="config[<?= htmlspecialchars($config['config_key']) ?>]"
                                                       value="<?= htmlspecialchars($config['config_value']) ?>"
                                                       class="form-control"
                                                       style="width: 150px;">
                                            </td>
                                            <td><?= htmlspecialchars($config['unit'] ?? '-') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <div class="mt-4">
                                <button type="submit" class="btn-primary">
                                    <?= icon('save', 18) ?> Gem ændringer
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const tabId = btn.dataset.tab;

        // Update buttons
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        // Update content
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        document.getElementById('tab-' + tabId).classList.add('active');
    });
});

// OPEX Module JavaScript
const OpexModule = {
    async openAssignModal(buildingId) {
        // Get available categories
        const fd = new FormData();
        fd.append('action', 'get_available_opex_categories');
        fd.append('building_id', buildingId);

        const response = await API.post('/api.php', fd, true);

        if (!response.success) {
            notify(response.error || 'Kunne ikke hente kategorier');
            return;
        }

        // Show modal with categories
        const categories = response.categories || [];

        const html = `
            <form id="assignOpexForm" onsubmit="OpexModule.assignCategory(event, ${buildingId})">
                <div class="form-group">
                    <label>OPEX Kategori</label>
                    <select name="category_id" class="form-control" required>
                        <option value="">Vælg kategori...</option>
                        ${categories.map(cat => `
                            <option value="${cat.id}">
                                ${escapeHtml(cat.name)} (${cat.rate_per_sqm} kr/m²/år)
                            </option>
                        `).join('')}
                    </select>
                </div>
                <div class="form-group">
                    <label>Tilpasset sats per m²/år (valgfri)</label>
                    <input type="number" step="0.01" name="custom_rate" class="form-control"
                           placeholder="Brug standard sats">
                    <small class="text-muted">Lad feltet være tomt for at bruge standard satsen</small>
                </div>
                <div class="form-group">
                    <label>Noter (valgfri)</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-modal-close>Annuller</button>
                    <button type="submit" class="btn-primary">Tilføj OPEX</button>
                </div>
            </form>
        `;

        Modal.show('Tilføj OPEX Kategori', html);
    },

    async assignCategory(event, buildingId) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'assign_opex_to_building');
        formData.append('building_id', buildingId);

        const response = await API.post('/api.php', formData, true);

        if (response.success) {
            notify('OPEX kategori tilføjet', {type: 'success'});
            Modal.close();
            Router.reload();
        } else {
            notify(response.error || 'Kunne ikke tilføje kategori');
        }
    },

    async editAssignment(assignmentId, buildingId) {
        // Get current assignment
        const fd = new FormData();
        fd.append('action', 'get_opex_assignment');
        fd.append('assignment_id', assignmentId);

        const response = await API.post('/api.php', fd, true);

        if (!response.success) {
            notify(response.error || 'Kunne ikke hente data');
            return;
        }

        const assignment = response.assignment;

        const html = `
            <form id="editOpexForm" onsubmit="OpexModule.updateAssignment(event, ${assignmentId}, ${buildingId})">
                <div class="form-group">
                    <label>Kategori</label>
                    <input type="text" class="form-control" value="${escapeHtml(assignment.name)}" readonly>
                </div>
                <div class="form-group">
                    <label>Tilpasset sats per m²/år</label>
                    <input type="number" step="0.01" name="custom_rate" class="form-control"
                           value="${assignment.custom_rate_per_sqm || ''}"
                           placeholder="Standard: ${assignment.default_rate} kr/m²">
                    <small class="text-muted">Standard sats: ${assignment.default_rate} kr/m²/år</small>
                </div>
                <div class="form-group">
                    <label>Noter</label>
                    <textarea name="notes" class="form-control" rows="3">${escapeHtml(assignment.notes || '')}</textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-modal-close>Annuller</button>
                    <button type="submit" class="btn-primary">Gem ændringer</button>
                </div>
            </form>
        `;

        Modal.show('Rediger OPEX', html);
    },

    async updateAssignment(event, assignmentId, buildingId) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'update_opex_assignment');
        formData.append('assignment_id', assignmentId);

        const response = await API.post('/api.php', formData, true);

        if (response.success) {
            notify('OPEX opdateret', {type: 'success'});
            Modal.close();
            Router.reload();
        } else {
            notify(response.error || 'Kunne ikke opdatere');
        }
    },

    async removeAssignment(assignmentId, buildingId) {
        const confirmed = await Modal.confirm(
            'Fjern OPEX kategori',
            'Er du sikker på at du vil fjerne denne OPEX kategori fra bygningen?'
        );

        if (!confirmed) return;

        const fd = new FormData();
        fd.append('action', 'remove_opex_assignment');
        fd.append('assignment_id', assignmentId);

        const response = await API.post('/api.php', fd, true);

        if (response.success) {
            notify('OPEX kategori fjernet', {type: 'success'});
            Router.reload();
        } else {
            notify(response.error || 'Kunne ikke fjerne kategori');
        }
    },

    async createCategory() {
        const html = `
            <form id="createCategoryForm" onsubmit="OpexModule.saveCategory(event)">
                <div class="form-group">
                    <label>Navn *</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Beskrivelse</label>
                    <textarea name="description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-group">
                    <label>Type *</label>
                    <select name="category_type" class="form-control" required>
                        <option value="maintenance">Vedligeholdelse</option>
                        <option value="energy">Energi</option>
                        <option value="utilities">Forsyning</option>
                        <option value="insurance">Forsikring</option>
                        <option value="tax">Skatter og afgifter</option>
                        <option value="security">Sikkerhed</option>
                        <option value="waste">Affald</option>
                        <option value="admin">Administration</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sats per m²/år (kr) *</label>
                    <input type="number" step="0.01" name="rate_per_sqm" class="form-control" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-modal-close>Annuller</button>
                    <button type="submit" class="btn-primary">Opret kategori</button>
                </div>
            </form>
        `;

        Modal.show('Opret OPEX Kategori', html);
    },

    async editCategory(categoryId) {
        // Get category data
        const fd = new FormData();
        fd.append('action', 'get_opex_category');
        fd.append('category_id', categoryId);

        const response = await API.post('/api.php', fd, true);

        if (!response.success) {
            notify(response.error || 'Kunne ikke hente data');
            return;
        }

        const cat = response.category;

        const html = `
            <form id="editCategoryForm" onsubmit="OpexModule.saveCategory(event, ${categoryId})">
                <div class="form-group">
                    <label>Navn *</label>
                    <input type="text" name="name" class="form-control" value="${escapeHtml(cat.name)}" required>
                </div>
                <div class="form-group">
                    <label>Beskrivelse</label>
                    <textarea name="description" class="form-control" rows="2">${escapeHtml(cat.description || '')}</textarea>
                </div>
                <div class="form-group">
                    <label>Type *</label>
                    <select name="category_type" class="form-control" required>
                        <option value="maintenance" ${cat.category_type === 'maintenance' ? 'selected' : ''}>Vedligeholdelse</option>
                        <option value="energy" ${cat.category_type === 'energy' ? 'selected' : ''}>Energi</option>
                        <option value="utilities" ${cat.category_type === 'utilities' ? 'selected' : ''}>Forsyning</option>
                        <option value="insurance" ${cat.category_type === 'insurance' ? 'selected' : ''}>Forsikring</option>
                        <option value="tax" ${cat.category_type === 'tax' ? 'selected' : ''}>Skatter og afgifter</option>
                        <option value="security" ${cat.category_type === 'security' ? 'selected' : ''}>Sikkerhed</option>
                        <option value="waste" ${cat.category_type === 'waste' ? 'selected' : ''}>Affald</option>
                        <option value="admin" ${cat.category_type === 'admin' ? 'selected' : ''}>Administration</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Sats per m²/år (kr) *</label>
                    <input type="number" step="0.01" name="rate_per_sqm" class="form-control" value="${cat.rate_per_sqm}" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" data-modal-close>Annuller</button>
                    <button type="submit" class="btn-primary">Gem ændringer</button>
                </div>
            </form>
        `;

        Modal.show('Rediger OPEX Kategori', html);
    },

    async saveCategory(event, categoryId = null) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', categoryId ? 'update_opex_category' : 'create_opex_category');
        if (categoryId) formData.append('category_id', categoryId);

        const response = await API.post('/api.php', formData, true);

        if (response.success) {
            notify(categoryId ? 'Kategori opdateret' : 'Kategori oprettet');
            Modal.close();
            Router.reload();
        } else {
            notify(response.error || 'Kunne ikke gemme kategori');
        }
    },

    async toggleCategory(categoryId, activate) {
        const fd = new FormData();
        fd.append('action', 'toggle_opex_category');
        fd.append('category_id', categoryId);
        fd.append('is_active', activate);

        const response = await API.post('/api.php', fd, true);

        if (response.success) {
            notify(activate === 'true' ? 'Kategori aktiveret' : 'Kategori deaktiveret');
            Router.reload();
        } else {
            notify(response.error || 'Kunne ikke ændre status');
        }
    },

    async saveTcoConfig(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'update_tco_config');

        const response = await API.post('/api.php', formData, true);

        if (response.success) {
            notify('TCO konfiguration opdateret', {type: 'success'});
        } else {
            notify(response.error || 'Kunne ikke gemme konfiguration');
        }
    }
};

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

</body>
</html>
