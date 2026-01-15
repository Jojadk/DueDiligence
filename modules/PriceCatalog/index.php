<div class="card">
    <div class="card-header">
        <h2>Price Catalog</h2>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Description</th>
                    <th>Unit</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td data-label="Code">
                            <?= htmlspecialchars($item['item_code']) ?>
                        </td>
                        <td data-label="Description">
                            <?= htmlspecialchars($item['description']) ?>
                        </td>
                        <td data-label="Unit">
                            <?= htmlspecialchars($item['unit']) ?>
                        </td>
                        <td data-label="Price">
                            <?= htmlspecialchars($item['unit_price']) ?>
                        </td>
                        <td data-label="Actions">
                            <a href="?module=Price&action=delete&id=<?= $item['id'] ?>" class="btn-sm text-danger"
                                onclick="return confirm('Delete this price item?');">Delete</a>
                            <a href="#" onclick="// Simple alert for now as we don't have an edit view
                                        alert('Editing not implemented. Please delete and re-add.'); return false;"
                                class="btn-sm" style="opacity:0.5">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <!-- Quick Add Row -->
                <tr style="background:#f8fafc">
                    <form action="?module=Price&action=store" method="POST">
                        <td><input type="text" name="item_code" placeholder="Code" class="form-input" required></td>
                        <td><input type="text" name="description" placeholder="Description" class="form-input" required>
                        </td>
                        <td><input type="text" name="unit" placeholder="Unit" class="form-input" required></td>
                        <td><input type="number" step="0.01" name="unit_price" placeholder="0.00" class="form-input"
                                required></td>
                        <td><button type="submit" class="btn-sm btn-primary">Add</button></td>
                    </form>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<style>
    .form-input {
        width: 100%;
        padding: 4px;
    }
</style>