<div class="card">
    <div class="card-header">
        <h2>Global System Constants</h2>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Key</th>
                    <th>Value</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($constants as $c): ?>
                    <tr>
                        <form action="?module=Constant&action=update" method="POST">
                            <input type="hidden" name="id" value="<?= $c['id'] ?>">
                            <td data-label="Key"><strong>
                                    <?= htmlspecialchars($c['key_name']) ?>
                                </strong></td>
                            <td data-label="Value"><input type="text" name="value"
                                    value="<?= htmlspecialchars($c['value']) ?>" class="form-input"></td>
                            <td data-label="Description"><input type="text" name="description"
                                    value="<?= htmlspecialchars($c['description']) ?>" class="form-input"></td>
                            <td data-label="Actions"><button type="submit" class="btn-sm">Save</button></td>
                        </form>
                    </tr>
                <?php endforeach; ?>
                <!-- New Constant Row -->
                <tr style="background:#f8fafc">
                    <form action="?module=Constant&action=store" method="POST">
                        <td><input type="text" name="key_name" placeholder="NEW_CONSTANT" required></td>
                        <td><input type="text" name="value" placeholder="Value" required></td>
                        <td><input type="text" name="description" placeholder="Description"></td>
                        <td><button type="submit" class="btn-sm btn-primary">Add</button></td>
                    </form>
                </tr>
            </tbody>
        </table>
    </div>
</div>
<style>
    .form-input {
        border: 1px solid #ddd;
        padding: 4px;
        border-radius: 4px;
        width: 100%;
    }
</style>