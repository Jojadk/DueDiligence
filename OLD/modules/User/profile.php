<div class="card">
    <div class="card-header">
        <h1>My Profile</h1>
    </div>
    <div class="card-body">
        <p>Profile placeholder.</p>
        <p>Username:
            <?= htmlspecialchars($user['username'] ?? 'Unknown') ?>
        </p>
        <p>Email:
            <?= htmlspecialchars($user['email'] ?? 'Unknown') ?>
        </p>
    </div>
</div>