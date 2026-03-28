<!DOCTYPE html>
<html>

<head>
    <title>Report Editor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="p-4">
    <h1>Report Templates</h1>
    <a href="?module=Report&action=editTemplate" class="btn btn-primary mb-3">Create New Template</a>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Active</th>
                <th>Updated</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($templates as $tpl): ?>
                <tr>
                    <td>
                        <?= $tpl['id'] ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($tpl['name']) ?>
                    </td>
                    <td>
                        <?= $tpl['is_active'] ? 'Yes' : 'No' ?>
                    </td>
                    <td>
                        <?= $tpl['updated_at'] ?>
                    </td>
                    <td>
                        <a href="?module=Report&action=editTemplate&id=<?= $tpl['id'] ?>"
                            class="btn btn-sm btn-info">Edit</a>
                        <!-- Preview requires a project ID, let's just show an info or default to test? -->
                        <!-- Ideally we prompt for project ID. -->
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>

</html>