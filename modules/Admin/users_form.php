<div class="card max-w-lg">
    <div class="card-header">
        <h2>
            <?= $user ? 'Edit User' : 'Create User' ?>
        </h2>
    </div>
    <div class="card-body">
        <form action="?module=Admin&action=<?= $user ? 'update' : 'store' ?>" method="POST">
            <?php if ($user): ?>
                <input type="hidden" name="id" value="<?= $user['id'] ?>">
            <?php endif; ?>

            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label>Role</label>
                <select name="role_id" required>
                    <option value="">Select Role</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= ($user && $user['role_id'] == $role['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($role['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Password
                    <?= $user ? '(Leave blank to keep current)' : '' ?>
                </label>
                <input type="password" name="password" <?= $user ? '' : 'required' ?>>
            </div>

            <button type="submit" class="btn btn-primary">
                <?= $user ? 'Update' : 'Create' ?>
            </button>
            <a href="?module=Admin&action=users" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</div>