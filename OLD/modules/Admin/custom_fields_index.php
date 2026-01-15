<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2>Custom Fields Configuration</h2>
        <a href="?module=CustomField&action=create" class="btn btn-primary">New Field</a>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Entity</th>
                    <th>Type</th>
                    <th>Scope</th>
                    <th>Project ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fields as $f): ?>
                    <tr>
                        <td data-label="Label">
                            <?= htmlspecialchars($f['label']) ?>
                        </td>
                        <td data-label="Entity">
                            <?= ucfirst($f['entity_type']) ?>
                        </td>
                        <td data-label="Type">
                            <?= ucfirst($f['field_type']) ?>
                        </td>
                        <td data-label="Scope"><span class="badge">
                                <?= ucfirst($f['scope']) ?>
                            </span></td>
                        <td data-label="Project ID">
                            <?= $f['project_id'] ? $f['project_id'] : '-' ?>
                        </td>
                        <td data-label="Actions">
                            <a href="?module=CustomField&action=edit&id=<?= $f['id'] ?>"
                                class="btn-sm text-primary mr-2">Edit</a>
                            <a href="?module=CustomField&action=delete&id=<?= $f['id'] ?>" class="btn-sm text-danger"
                                onclick="return confirm('Delete field? Values will be preserved but validation lost.');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>