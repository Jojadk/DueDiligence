<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - TDD System</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Google Fonts Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
</head>

<body class="auth-body">
    <div class="auth-container">
        <div class="auth-card">
            <h1>Construction Admin</h1>
            <p class="subtitle">Secure Login</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="?module=Auth&action=login" method="POST">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <?php if (isset($otp_required) && $otp_required): ?>
                    <input type="hidden" name="username" value="<?= htmlspecialchars($username_val) ?>">
                    <input type="hidden" name="password" value="<?= htmlspecialchars($password_val) ?>">
                    <div class="form-group">
                        <label for="otp_code">2FA Code</label>
                        <input type="text" name="otp_code" id="otp_code" placeholder="000000" autofocus required>
                    </div>
                <?php else: ?>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" name="username" id="username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" name="password" id="password" required>
                    </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary btn-block">Sign In</button>
            </form>

            <div class="auth-footer">
                <p>Default: admin / admin123</p>
            </div>
        </div>
    </div>
</body>

</html>