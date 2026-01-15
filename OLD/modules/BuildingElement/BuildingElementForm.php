<div class="card max-w-lg">
    <div class="card-header">
        <h2>
            <?= $element ? 'Edit Element' : 'New Building Element' ?>
        </h2>
    </div>
    <div class="card-body">
        <form action="?module=BuildingElement&action=<?= $element ? 'update' : 'store' ?>" method="POST">
            <?php if ($element): ?>
                <input type="hidden" name="id" value="<?= $element['id'] ?>">
            <?php endif; ?>
            <input type="hidden" name="project_id" value="<?= $project_id ?>">

            <div class="form-group">
                <label>Name</label>
                <input type="text" name="name" value="<?= htmlspecialchars($element['name'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Location</label>
                <input type="text" name="location" value="<?= htmlspecialchars($element['location'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Condition (1-5)</label>
                <input type="number" name="condition_rating" min="1" max="5"
                    value="<?= $element['condition_rating'] ?? 5 ?>">
            </div>

            <div class="form-group">
                <label>Quantity</label>
                <input type="number" step="0.01" name="quantity" value="<?= $element['quantity'] ?? 0 ?>">
            </div>

            <div class="form-group">
                <label>Price Catalog Item</label>
                <select name="price_catalog_id" class="searchable-select">
                    <option value="">Select Item...</option>
                    <?php foreach ($prices as $p): ?>
                        <option value="<?= $p['id'] ?>" <?= ($element && $element['price_catalog_id'] == $p['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($p['item_code'] . ' - ' . $p['description'] . ' (' . $p['unit_price'] . ' ' . $p['currency'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php foreach ($customFields as $field): ?>
                <div class="form-group">
                    <label>
                        <?= htmlspecialchars($field['label']) ?>
                    </label>
                    <?php if ($field['field_type'] == 'text'): ?>
                        <input type="text" name="custom_fields[<?= $field['id'] ?>]"
                            value="<?= htmlspecialchars($values[$field['id']] ?? $field['default_value']) ?>">
                    <?php elseif ($field['field_type'] == 'dropdown'):
                        $opts = json_decode($field['options'], true) ?: [];
                        ?>
                        <select name="custom_fields[<?= $field['id'] ?>]">
                            <?php foreach ($opts as $opt): ?>
                                <option value="<?= htmlspecialchars($opt) ?>">
                                    <?= htmlspecialchars($opt) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit" class="btn btn-primary">Save Element</button>
            <a href="?module=BuildingElement&action=index&project_id=<?= $project_id ?>"
                class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>