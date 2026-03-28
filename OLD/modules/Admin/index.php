<div class="panel">
    <div class="panel-header">
        <h2>Administration</h2>
    </div>
    <div class="panel-body">
        <div class="row">
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h3><i class="fas fa-users"></i> Users</h3>
                        <p>Manage users, roles, and permissions.</p>
                        <a href="?module=Admin&action=users" class="btn btn-primary">Manage Users</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h3><i class="fas fa-list"></i> Custom Fields</h3>
                        <p>Define dynamic fields for projects and elements.</p>
                        <a href="?module=CustomField&action=index" class="btn btn-primary">Manage Fields</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h3><i class="fas fa-sliders-h"></i> Constants</h3>
                        <p>Global system constants and settings.</p>
                        <a href="?module=Constant&action=index" class="btn btn-primary">Manage Constants</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h3><i class="fas fa-address-book"></i> Customers</h3>
                        <p>Manage customer database and details.</p>
                        <a href="?module=Customer" class="btn btn-primary">Manage Customers</a>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body">
                        <h3><i class="fas fa-server"></i> System Maintenance</h3>
                        <p>Manage cache and system settings.</p>
                        <div class="status-indicator">
                            Cache Status:
                            <?php if (defined('ASSET_CACHE_ENABLED') && ASSET_CACHE_ENABLED): ?>
                                <span class="badge badge-success">Enabled</span>
                            <?php else: ?>
                                <span class="badge badge-warning">Disabled (Dev Mode)</span>
                            <?php endif; ?>
                        </div>
                        <br>
                        <form action="?module=Admin&action=clearCache" method="post"
                            onsubmit="return confirm('Rydd cachen?');">
                            <input type="hidden" name="_csrf"
                                value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Clear Asset Cache</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>