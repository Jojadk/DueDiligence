<?php
// Admin > Users Index View
// Note: Layout is handled by the Controller view method or parent template
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h2>Users</h2>
        <a href="?module=Admin&action=create" class="btn btn-primary">Create User</a>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td data-label="Username">
                            <?= htmlspecialchars($user['username']) ?>
                        </td>
                        <td data-label="Email">
                            <?= htmlspecialchars($user['email']) ?>
                        </td>
                        <td data-label="Role"><span class="badge">
                                <?= htmlspecialchars($user['role_name'] ?? 'None') ?>
                            </span></td>
                        <td data-label="Created">
                            <?= date('M d, Y', strtotime($user['created_at'])) ?>
                        </td>
                        <td data-label="Actions">
                            <a href="?module=Admin&action=edit&id=<?= $user['id'] ?>" class="btn-sm">Edit</a>
                            <a href="?module=Admin&action=delete&id=<?= $user['id'] ?>" class="btn-sm text-danger"
                                onclick="return confirm('Delete user?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>