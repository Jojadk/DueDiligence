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
// Brug BaseModule for at reducere dubleret kode
const CustomerModule = Object.assign(
    new BaseModule('customer', {
        titleSingular: 'Kunde',
        titlePlural: 'Kunder',
        instanceName: 'CustomerModule',
        modalSize: 'medium'
    }),
    {
        search: '<?= esc_js($searchTerm) ?>',
        page: <?= $page ?>,

        // Override getFormFields med kunde-specifikke felter
        getFormFields(d, id) {
            return `
                <div class="form-group">
                    <label class="required">Firmanavn</label>
                    <input name="name" value="${escapeHtml(d.name||'')}" required maxlength="255" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>CVR</label>
                        <input name="cvr_number" value="${escapeHtml(d.cvr_number||'')}" pattern="^[0-9]{8}$" class="form-control">
                        <small class="form-help">8 cifre</small>
                    </div>
                    <div class="form-group">
                        <label>Kontakt</label>
                        <input name="contact_person" value="${escapeHtml(d.contact_person||'')}" class="form-control">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="${escapeHtml(d.email||'')}" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Telefon</label>
                        <input name="phone" value="${escapeHtml(d.phone||'')}" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Adresse</label>
                    <input name="address" value="${escapeHtml(d.address||'')}" class="form-control">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Postnr</label>
                        <input name="postal_code" value="${escapeHtml(d.postal_code||'')}" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>By</label>
                        <input name="city" value="${escapeHtml(d.city||'')}" class="form-control">
                    </div>
                </div>
                <div class="form-group">
                    <label>Noter</label>
                    <textarea name="notes" rows="4" class="form-control">${escapeHtml(d.notes||'')}</textarea>
                </div>
            `;
        },

        // Wrapper for search to use BaseModule's performSearch
        search(e) {
            return this.performSearch(e);
        }
    }
);
</script>

